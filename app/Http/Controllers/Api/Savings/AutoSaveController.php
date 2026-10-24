<?php

namespace App\Http\Controllers\Api\Savings;

use App\Http\Controllers\Api\Savings\Concerns\RespondsToSavings;
use App\Http\Controllers\Controller;
use App\Http\Helpers\Response;
use App\Models\SavingsAccount;
use App\Models\SavingsEntry;
use App\Models\SavingsExecution;
use App\Services\Savings\Money;
use App\Services\Savings\SavingsHistoryQuery;
use App\Services\Savings\SavingsManager;
use App\Services\Savings\SavingsPresenter;
use Illuminate\Http\Request;

/**
 * Auto Save: recurring automatic savings funded from the user's NGN wallet.
 *
 * Collections run from a persistent schedule (`savings:run-due`). The payment
 * provider is never asked for an unattended debit; funds come from the
 * internal wallet. Missed instalments are never collected in bulk.
 *
 * @tags Savings - Auto Save
 */
class AutoSaveController extends Controller
{
    use RespondsToSavings;

    private const SCHEDULE_RULES = [
        'amount' => ['required', 'numeric'],
        'frequency' => ['required', 'in:daily,weekly,monthly,DAILY,WEEKLY,MONTHLY'],
        'day_of_week' => ['sometimes', 'nullable', 'integer', 'between:0,6'],
        'day_of_month' => ['sometimes', 'nullable', 'integer', 'between:1,31'],
        'run_time' => ['sometimes', 'date_format:H:i'],
        'timezone' => ['sometimes', 'timezone'],
        'start_date' => ['sometimes', 'date'],
        'end_date' => ['sometimes', 'nullable', 'date'],
    ];

    public function __construct(private readonly SavingsManager $savings)
    {
    }

    /** List the user's Auto Save plans (paginated, filter by `status`). */
    public function index(Request $request)
    {
        $request->validate(['per_page' => ['sometimes', 'integer', 'min:1', 'max:100'], 'status' => ['sometimes', 'in:ACTIVE,PAUSED,CANCELLED,active,paused,cancelled']]);

        return $this->guarded(function () use ($request) {
            $q = SavingsAccount::with('plan')->where('user_id', $request->user()->id)->where('product', SavingsAccount::AUTO);
            if ($request->filled('status')) {
                $q->where('status', strtoupper($request->query('status')));
            }
            $page = $q->orderByDesc('id')->paginate($this->page($request));

            return Response::successResponse('Auto Save plans retrieved.', $this->paginated($page, fn ($a) => SavingsPresenter::account($a)));
        });
    }

    /**
     * Create an Auto Save plan
     *
     * `frequency`: daily | weekly | monthly. Weekly needs `day_of_week`
     * (0=Sunday..6), monthly needs `day_of_month` (1-31; clamped to month end
     * in shorter months). `run_time` is HH:MM in the plan `timezone`
     * (default Africa/Lagos). Timestamps are returned in UTC.
     */
    public function store(Request $request)
    {
        $data = $request->validate(self::SCHEDULE_RULES + [
            'title' => ['sometimes', 'string', 'max:120'],
            'description' => ['sometimes', 'nullable', 'string', 'max:500'],
            'initial_deposit' => ['sometimes', 'nullable', 'numeric', 'min:0'],
        ]);

        return $this->guarded(function () use ($request, $data) {
            $created = $this->savings->createAuto($request->user(), $data + [
                'idempotency_key' => $request->header('Idempotency-Key'),
            ]);

            return Response::successResponse('Auto Save plan created.', [
                'account' => SavingsPresenter::account($created['account']),
                'plan' => SavingsPresenter::plan($created['plan']),
            ], 201);
        });
    }

    /** Get a plan with its next scheduled run and last successful run. */
    public function show(Request $request, int $id)
    {
        return $this->guarded(function () use ($request, $id) {
            $account = $this->savings->find($request->user(), $id, SavingsAccount::AUTO)->load('plan');

            return Response::successResponse('Auto Save plan retrieved.', SavingsPresenter::account($account));
        });
    }

    /** Update supported schedule settings (amount, frequency, days, time, timezone, end date, title). */
    public function update(Request $request, int $id)
    {
        $rules = collect(self::SCHEDULE_RULES)->map(fn ($r) => array_merge(['sometimes'], array_values(array_diff($r, ['required']))))->all();
        $data = $request->validate($rules + [
            'title' => ['sometimes', 'string', 'max:120'],
            'description' => ['sometimes', 'nullable', 'string', 'max:500'],
        ]);

        return $this->guarded(function () use ($request, $id, $data) {
            $updated = $this->savings->updateAuto($request->user(), $id, $data);

            return Response::successResponse('Auto Save plan updated.', [
                'account' => SavingsPresenter::account($updated['account']),
                'plan' => SavingsPresenter::plan($updated['plan']),
            ]);
        });
    }

    /** Pause the schedule. Waits for any in-flight collection to finish. */
    public function pause(Request $request, int $id)
    {
        return $this->guarded(function () use ($request, $id) {
            $this->savings->find($request->user(), $id, SavingsAccount::AUTO);

            return Response::successResponse('Auto Save paused.', SavingsPresenter::plan($this->savings->pausePlan($request->user(), $id)));
        });
    }

    /** Resume the schedule from its next future slot (no back-filling). */
    public function resume(Request $request, int $id)
    {
        return $this->guarded(function () use ($request, $id) {
            $this->savings->find($request->user(), $id, SavingsAccount::AUTO);

            return Response::successResponse('Auto Save resumed.', SavingsPresenter::plan($this->savings->resumePlan($request->user(), $id)));
        });
    }

    /** Cancel the plan. History is retained and any balance stays withdrawable. */
    public function cancel(Request $request, int $id)
    {
        return $this->guarded(function () use ($request, $id) {
            $account = $this->savings->cancelAuto($request->user(), $id)->load('plan');

            return Response::successResponse('Auto Save cancelled.', SavingsPresenter::account($account));
        });
    }

    /**
     * Manual contribution
     *
     * `source`: `wallet` (default) or `flexi`. Independent of the schedule.
     * Requires an `Idempotency-Key` header.
     */
    public function contribute(Request $request, int $id)
    {
        $data = $request->validate(['amount' => ['required', 'numeric'], 'source' => ['sometimes', 'in:wallet,flexi']]);

        return $this->guarded(function () use ($request, $id, $data) {
            $this->savings->find($request->user(), $id, SavingsAccount::AUTO);
            $result = $this->savings->contribute($request->user(), $id, Money::parse($data['amount']), $data['source'] ?? 'wallet', $this->idempotencyKey($request));

            return Response::successResponse(
                $result['replayed'] ? 'Contribution already processed.' : 'Contribution successful.',
                ['transaction' => SavingsPresenter::entry($result['entry']), 'replayed' => $result['replayed']],
                $result['replayed'] ? 200 : 201
            );
        });
    }

    /** Withdraw to the user's wallet. Requires an `Idempotency-Key` header. */
    public function withdraw(Request $request, int $id)
    {
        $data = $request->validate(['amount' => ['required', 'numeric']]);

        return $this->guarded(function () use ($request, $id, $data) {
            $this->savings->find($request->user(), $id, SavingsAccount::AUTO);
            $result = $this->savings->withdraw($request->user(), $id, Money::parse($data['amount']), $this->idempotencyKey($request));

            return Response::successResponse('Withdrawal successful.', ['transaction' => SavingsPresenter::entry($result['entry']), 'replayed' => $result['replayed']]);
        });
    }

    /** Scheduled collection attempts and their outcomes (paginated). Filter by `status`. */
    public function executions(Request $request, int $id)
    {
        $request->validate(['per_page' => ['sometimes', 'integer', 'min:1', 'max:100'], 'status' => ['sometimes', 'string']]);

        return $this->guarded(function () use ($request, $id) {
            $account = $this->savings->find($request->user(), $id, SavingsAccount::AUTO);
            $q = SavingsExecution::where('account_id', $account->id)->where('user_id', $request->user()->id);
            if ($request->filled('status')) {
                $q->where('status', strtoupper($request->query('status')));
            }

            return Response::successResponse('Executions retrieved.', $this->paginated($q->orderByDesc('scheduled_for')->paginate($this->page($request)), fn ($x) => SavingsPresenter::execution($x)));
        });
    }

    /** Ledger transactions for this plan (paginated, filterable). */
    public function transactions(Request $request, int $id)
    {
        $request->validate(['per_page' => ['sometimes', 'integer', 'min:1', 'max:100']]);

        return $this->guarded(function () use ($request, $id) {
            $account = $this->savings->find($request->user(), $id, SavingsAccount::AUTO);
            $page = SavingsHistoryQuery::paginate($request, $request->user()->id, $account->id, $this->page($request));

            return Response::successResponse('Transactions retrieved.', $this->paginated($page, fn (SavingsEntry $e) => SavingsPresenter::entry($e)));
        });
    }
}
