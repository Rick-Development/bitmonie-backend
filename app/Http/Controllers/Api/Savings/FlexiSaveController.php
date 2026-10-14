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
            $account = $this->savings->flexi(auth()->user());

            return Response::successResponse('Flexi Save retrieved.', SavingsPresenter::account($account));
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
}
