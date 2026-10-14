<?php

namespace App\Http\Controllers\Api\Savings;

use App\Http\Controllers\Api\Savings\Concerns\RespondsToSavings;
use App\Http\Controllers\Controller;
use App\Http\Helpers\Response;
use App\Models\SavingsAccount;
use App\Models\SavingsEntry;
use App\Services\Savings\SavingsException;
use App\Services\Savings\SavingsHistoryQuery;
use App\Services\Savings\SavingsManager;
use App\Services\Savings\SavingsPresenter;
use Illuminate\Http\Request;

/**
 * Cross-product transaction lookups and internal transfers.
 *
 * @tags Savings - Transactions
 */
class SavingsTransactionController extends Controller
{
    use RespondsToSavings;

    public function __construct(private readonly SavingsManager $savings)
    {
    }

    /**
     * List all savings transactions
     *
     * Paginated across Flexi, Auto Save and Targets. Filters: `account_id`,
     * `type`, `status`, `direction`, `from`, `to`.
     */
    public function index(Request $request)
    {
        $request->validate(['per_page' => ['sometimes', 'integer', 'min:1', 'max:100'], 'account_id' => ['sometimes', 'integer']]);

        return $this->guarded(function () use ($request) {
            $page = SavingsHistoryQuery::paginate($request, $request->user()->id, null, $this->page($request));

            return Response::successResponse('Transactions retrieved.', $this->paginated($page, fn (SavingsEntry $e) => SavingsPresenter::entry($e)));
        });
    }

    /** Get a single savings transaction by reference. */
    public function show(Request $request, string $reference)
    {
        return $this->guarded(function () use ($request, $reference) {
            $entry = SavingsEntry::where('user_id', $request->user()->id)->where('reference', $reference)->first()
                ?? throw SavingsException::notFound('Transaction');

            return Response::successResponse('Transaction retrieved.', SavingsPresenter::entry($entry));
        });
    }

    /** Get the current processing status of a transaction. */
    public function status(Request $request, string $reference)
    {
        return $this->guarded(function () use ($request, $reference) {
            $entry = SavingsEntry::where('user_id', $request->user()->id)->where('reference', $reference)->first()
                ?? throw SavingsException::notFound('Transaction');

            return Response::successResponse('Status retrieved.', [
                'reference' => $entry->reference,
                'status' => $entry->status,
                'final' => in_array($entry->status, ['COMPLETED', 'FAILED', 'REVERSED'], true),
            ]);
        });
    }

    /**
     * Transfer between two of the caller's savings accounts
     *
     * Allowed sources: Flexi, Auto Save, and completed/expired targets.
     * Allowed destinations: Flexi and active Auto Save / Target accounts.
     * Requires an `Idempotency-Key` header.
     */
    public function transfer(Request $request)
    {
        $data = $request->validate([
            'from_account_id' => ['required', 'integer'],
            'to_account_id' => ['required', 'integer', 'different:from_account_id'],
            'amount' => ['required', 'numeric'],
        ]);

        return $this->guarded(function () use ($request, $data) {
            $result = $this->savings->transfer(
                $request->user(),
                (int) $data['from_account_id'],
                (int) $data['to_account_id'],
                \App\Services\Savings\Money::parse($data['amount']),
                $this->idempotencyKey($request)
            );

            return Response::successResponse(
                $result['replayed'] ? 'Transfer already processed.' : 'Transfer successful.',
                ['transaction' => SavingsPresenter::entry($result['entry']), 'replayed' => $result['replayed']]
            );
        });
    }
}
