<?php

namespace App\Http\Controllers\Api\Savings;

use App\Http\Controllers\Api\Savings\Concerns\RespondsToSavings;
use App\Http\Controllers\Controller;
use App\Http\Helpers\Response;
use App\Models\SavingsEntry;
use App\Services\Savings\Money;
use App\Services\Savings\SavingsHistoryQuery;
use App\Services\Savings\SavingsManager;
use App\Services\Savings\SavingsPresenter;
use Illuminate\Http\Request;

/**
 * Flexi Save: the user's flexible savings account.
 *
 * @tags Savings - Flexi Save
 */
class FlexiSaveController extends Controller
{
    use RespondsToSavings;

    public function __construct(private readonly SavingsManager $savings)
    {
    }

    /**
     * Get Flexi Save account
     *
     * Returns the authenticated user's Flexi Save account (created on first use),
     * with available balance, total deposited and total withdrawn.
     */
    public function show()
    {
        return $this->guarded(function () {
            $account = $this->savings->flexi(auth()->user())->load('plan');

            return Response::successResponse('Flexi Save retrieved.', SavingsPresenter::account($account, $this->savings));
        });
    }

    /**
     * Deposit into Flexi Save
     *
     * Funds are moved atomically from the user's NGN wallet. Requires an
     * `Idempotency-Key` header. Amount: 2 decimals max, within configured limits.
     */
    public function deposit(Request $request)
    {
        $data = $request->validate(['amount' => ['required', 'numeric']]);

        return $this->guarded(function () use ($request, $data) {
            $result = $this->savings->depositFlexi(
                $request->user(),
                Money::parse($data['amount']),
                $this->idempotencyKey($request)
            );

            return Response::successResponse(
                $result['replayed'] ? 'Deposit already processed.' : 'Deposit successful.',
                ['transaction' => SavingsPresenter::entry($result['entry']), 'replayed' => $result['replayed']],
                $result['replayed'] ? 200 : 201
            );
        });
    }

    /**
     * Withdraw from Flexi Save
     *
     * Pays the amount to the user's own NGN wallet. Rejects amounts above the
     * available balance. Requires an `Idempotency-Key` header.
     */
    public function withdraw(Request $request)
    {
        $data = $request->validate(['amount' => ['required', 'numeric']]);

        return $this->guarded(function () use ($request, $data) {
            $flexi = $this->savings->flexi($request->user());
            $result = $this->savings->withdraw(
                $request->user(),
                $flexi->id,
                Money::parse($data['amount']),
                $this->idempotencyKey($request)
            );

            return Response::successResponse(
                $result['replayed'] ? 'Withdrawal already processed.' : 'Withdrawal successful.',
                ['transaction' => SavingsPresenter::entry($result['entry']), 'replayed' => $result['replayed']]
            );
        });
    }

    /**
     * Transfer from Flexi Save to another of the user's savings accounts
     *
     * Destination must be an active Auto Save or Target account owned by the
     * caller. Both ledger legs commit atomically.
     */
    public function transfer(Request $request)
    {
        $data = $request->validate([
            'to_account_id' => ['required', 'integer'],
            'amount' => ['required', 'numeric'],
        ]);

        return $this->guarded(function () use ($request, $data) {
            $flexi = $this->savings->flexi($request->user());
            $result = $this->savings->transfer(
                $request->user(),
                $flexi->id,
                (int) $data['to_account_id'],
                Money::parse($data['amount']),
                $this->idempotencyKey($request)
            );

            return Response::successResponse(
                $result['replayed'] ? 'Transfer already processed.' : 'Transfer successful.',
                ['transaction' => SavingsPresenter::entry($result['entry']), 'replayed' => $result['replayed']]
            );
        });
    }

    /**
     * Flexi Save transaction history
     *
     * Paginated; filter by `type`, `status`, `direction`, `from`, `to` (dates).
     */
    public function transactions(Request $request)
    {
        $request->validate([
            'per_page' => ['sometimes', 'integer', 'min:1', 'max:100'],
            'type' => ['sometimes', 'string'],
            'status' => ['sometimes', 'string'],
            'direction' => ['sometimes', 'in:credit,debit,CREDIT,DEBIT'],
            'from' => ['sometimes', 'date'],
            'to' => ['sometimes', 'date'],
        ]);

        return $this->guarded(function () use ($request) {
            $flexi = $this->savings->flexi($request->user());
            $page = SavingsHistoryQuery::paginate($request, $request->user()->id, $flexi->id, $this->page($request));

            return Response::successResponse('Transactions retrieved.', $this->paginated($page, fn (SavingsEntry $e) => SavingsPresenter::entry($e)));
        });
    }

    private const SCHEDULE_RULES = [
        'amount' => ['required_without:autosave', 'numeric'],
        'frequency' => ['required_without:autosave', 'in:daily,weekly,monthly,DAILY,WEEKLY,MONTHLY'],
        'day_of_week' => ['sometimes', 'nullable', 'integer', 'between:0,6'],
        'day_of_month' => ['sometimes', 'nullable', 'integer', 'between:1,31'],
        'run_time' => ['sometimes', 'date_format:H:i'],
        'timezone' => ['sometimes', 'timezone'],
        'start_date' => ['sometimes', 'date'],
        'end_date' => ['sometimes', 'nullable', 'date'],
        'autosave' => ['sometimes', 'nullable', 'array'],
        'autosave.enabled' => ['sometimes', 'boolean'],
        'autosave.mode' => ['sometimes', 'in:scheduled,percentage,both'],
        'autosave.amount' => ['required_with:autosave', 'numeric', 'min:1'],
        'autosave.frequency' => ['required_with:autosave', 'in:daily,weekly,monthly,DAILY,WEEKLY,MONTHLY'],
        'autosave.day_of_week' => ['sometimes', 'nullable', 'integer', 'between:0,6'],
        'autosave.day_of_month' => ['sometimes', 'nullable', 'integer', 'between:1,31'],
        'autosave.run_time' => ['sometimes', 'date_format:H:i'],
        'autosave.time_of_day' => ['sometimes', 'date_format:H:i'],
        'autosave.start_date' => ['sometimes', 'date'],
        'autosave.end_date' => ['sometimes', 'nullable', 'date'],
    ];

    /**
     * Set or replace Flexi Save auto-debit schedule
     *
     * Same schedule fields as Auto Save / Target. Also accepts nested `autosave`
     * (`enabled`, `mode`, `amount`, `frequency`, …) for FE parity with SafeLock.
     */
    public function setSchedule(Request $request)
    {
        $data = $request->validate(self::SCHEDULE_RULES);

        return $this->guarded(function () use ($request, $data) {
            $merged = $this->savings->mergeAutosaveAliases($data);
            $schedule = ! empty($merged['schedule']) ? $merged['schedule'] : $merged;
            unset($schedule['autosave']);
            $plan = $this->savings->setFlexiSchedule($request->user(), $schedule);
            $account = $this->savings->flexi($request->user())->load('plan');

            return Response::successResponse('Flexi Save auto-debit schedule saved.', [
                'account' => SavingsPresenter::account($account, $this->savings),
                'plan' => SavingsPresenter::plan($plan),
            ]);
        });
    }

    /** Pause Flexi Save auto-debit (manual deposits still allowed). */
    public function pauseSchedule(Request $request)
    {
        return $this->guarded(function () use ($request) {
            $flexi = $this->savings->flexi($request->user());

            return Response::successResponse(
                'Flexi Save schedule paused.',
                SavingsPresenter::plan($this->savings->pausePlan($request->user(), $flexi->id))
            );
        });
    }

    /** Resume Flexi Save auto-debit from the next future slot. */
    public function resumeSchedule(Request $request)
    {
        return $this->guarded(function () use ($request) {
            $flexi = $this->savings->flexi($request->user());

            return Response::successResponse(
                'Flexi Save schedule resumed.',
                SavingsPresenter::plan($this->savings->resumePlan($request->user(), $flexi->id))
            );
        });
    }

    /** Cancel Flexi Save auto-debit schedule. */
    public function cancelSchedule(Request $request)
    {
        return $this->guarded(function () use ($request) {
            return Response::successResponse(
                'Flexi Save schedule cancelled.',
                SavingsPresenter::plan($this->savings->removeFlexiSchedule($request->user()))
            );
        });
    }
}
