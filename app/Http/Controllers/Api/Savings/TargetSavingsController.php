<?php

namespace App\Http\Controllers\Api\Savings;

use App\Http\Controllers\Api\Savings\Concerns\RespondsToSavings;
use App\Http\Controllers\Controller;
use App\Http\Helpers\Response;
use App\Models\SavingsAccount;
use App\Models\SavingsEntry;
use App\Services\Savings\Money;
use App\Services\Savings\SavingsHistoryQuery;
use App\Services\Savings\SavingsManager;
use App\Services\Savings\SavingsPresenter;
use Illuminate\Http\Request;

/**
 * Target Savings: goal-based savings with progress tracking.
 *
 * Over-target policy: a manual contribution that would exceed the target is
 * REJECTED (never silently truncated); scheduled instalments are clipped to
 * the remaining amount. A target completes automatically when its credited
 * balance reaches the goal.
 *
 * @tags Savings - Target Savings
 */
class TargetSavingsController extends Controller
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

    /** List targets (paginated). Filters: `status`, `category`. */
    public function index(Request $request)
    {
        $request->validate(['per_page' => ['sometimes', 'integer', 'min:1', 'max:100'], 'status' => ['sometimes', 'string'], 'category' => ['sometimes', 'string']]);

        return $this->guarded(function () use ($request) {
            $q = SavingsAccount::with('plan')->where('user_id', $request->user()->id)->where('product', SavingsAccount::TARGET);
            foreach (['status' => strtoupper((string) $request->query('status')), 'category' => $request->query('category')] as $col => $val) {
                if ($request->filled($col)) {
                    $q->where($col, $val);
                }
            }

            return Response::successResponse('Targets retrieved.', $this->paginated($q->orderByDesc('id')->paginate($this->page($request)), fn ($a) => SavingsPresenter::account($a, $this->savings)));
        });
    }

    /**
     * Create a target
     *
     * `target_amount` must be positive and within the configured maximum;
     * `deadline` (optional) must be after `start_date`. An optional `schedule`
     * object (same fields as Auto Save) enables recurring contributions.
     */
    public function store(Request $request)
    {
        $data = $request->validate([
            'title' => ['required', 'string', 'max:120'],
            'description' => ['sometimes', 'nullable', 'string', 'max:500'],
            'category' => ['sometimes', 'nullable', 'string', 'max:60'],
            'target_amount' => ['required', 'numeric'],
            'start_date' => ['sometimes', 'date'],
            'deadline' => ['sometimes', 'nullable', 'date'],
            'initial_deposit' => ['sometimes', 'nullable', 'numeric', 'min:0'],
            'amount' => ['sometimes', 'nullable', 'numeric', 'min:0'],
            'schedule' => ['sometimes', 'array'],
            'schedule.amount' => ['required_with:schedule', 'numeric'],
            'schedule.frequency' => ['required_with:schedule', 'in:daily,weekly,monthly,DAILY,WEEKLY,MONTHLY'],
            'schedule.day_of_week' => ['sometimes', 'nullable', 'integer', 'between:0,6'],
            'schedule.day_of_month' => ['sometimes', 'nullable', 'integer', 'between:1,31'],
            'schedule.run_time' => ['sometimes', 'date_format:H:i'],
            'schedule.timezone' => ['sometimes', 'timezone'],
        ]);

        return $this->guarded(function () use ($request, $data) {
            $created = $this->savings->createTarget($request->user(), $data + [
                'idempotency_key' => $request->header('Idempotency-Key'),
            ]);
            $account = $created['account']->load('plan');

            return Response::successResponse('Target created.', SavingsPresenter::account($account, $this->savings), 201);
        });
    }

    /** Get a target with progress, remaining amount and percentage completed. */
    public function show(Request $request, int $id)
    {
        return $this->guarded(function () use ($request, $id) {
            $account = $this->savings->find($request->user(), $id, SavingsAccount::TARGET)->load('plan');

            return Response::successResponse('Target retrieved.', SavingsPresenter::account($account, $this->savings));
        });
    }

    /** Update title, description, category, target amount (must exceed the amount saved) or deadline. */
    public function update(Request $request, int $id)
    {
        $data = $request->validate([
            'title' => ['sometimes', 'string', 'max:120'],
            'description' => ['sometimes', 'nullable', 'string', 'max:500'],
            'category' => ['sometimes', 'nullable', 'string', 'max:60'],
            'target_amount' => ['sometimes', 'numeric'],
            'deadline' => ['sometimes', 'nullable', 'date'],
        ]);

        return $this->guarded(function () use ($request, $id, $data) {
            $account = $this->savings->updateTarget($request->user(), $id, $data)->load('plan');

            return Response::successResponse('Target updated.', SavingsPresenter::account($account, $this->savings));
        });
    }

    /** Progress summary (credited balance only). */
    public function progress(Request $request, int $id)
    {
        return $this->guarded(function () use ($request, $id) {
            $account = $this->savings->find($request->user(), $id, SavingsAccount::TARGET);

            return Response::successResponse('Progress retrieved.', $this->savings->progress($account));
        });
    }

    /**
     * Contribute to a target
     *
     * `source`: `wallet` (default) or `flexi`. Rejected when the target is not
     * active, past its deadline, or the amount would exceed the remaining
     * amount. Requires an `Idempotency-Key` header.
     */
    public function contribute(Request $request, int $id)
    {
        $data = $request->validate(['amount' => ['required', 'numeric'], 'source' => ['sometimes', 'in:wallet,flexi']]);

        return $this->guarded(function () use ($request, $id, $data) {
            $this->savings->find($request->user(), $id, SavingsAccount::TARGET);
            $result = $this->savings->contribute($request->user(), $id, Money::parse($data['amount']), $data['source'] ?? 'wallet', $this->idempotencyKey($request));

            return Response::successResponse(
                $result['replayed'] ? 'Contribution already processed.' : 'Contribution successful.',
                [
                    'transaction' => SavingsPresenter::entry($result['entry']),
                    'progress' => $this->savings->progress($this->savings->find($request->user(), $id)),
                    'replayed' => $result['replayed'],
                ],
                $result['replayed'] ? 200 : 201
            );
        });
    }

    /** Pause the target: contributions and schedule stop until resumed. */
    public function pause(Request $request, int $id)
    {
        return $this->guarded(fn () => Response::successResponse('Target paused.', SavingsPresenter::account($this->savings->setTargetPaused($request->user(), $id, true)->load('plan'), $this->savings)));
    }

    /** Resume a paused target (schedule restarts from its next future slot). */
    public function resume(Request $request, int $id)
    {
        return $this->guarded(fn () => Response::successResponse('Target resumed.', SavingsPresenter::account($this->savings->setTargetPaused($request->user(), $id, false)->load('plan'), $this->savings)));
    }

    /** Mark the target completed. Only allowed once the credited balance reaches the goal. */
    public function complete(Request $request, int $id)
    {
        return $this->guarded(fn () => Response::successResponse('Target completed.', SavingsPresenter::account($this->savings->completeTarget($request->user(), $id)->load('plan'), $this->savings)));
    }

    /** Set or replace the recurring contribution schedule. */
    public function setSchedule(Request $request, int $id)
    {
        $data = $request->validate(self::SCHEDULE_RULES);

        return $this->guarded(fn () => Response::successResponse('Schedule saved.', SavingsPresenter::plan($this->savings->setTargetSchedule($request->user(), $id, $data))));
    }

    /** Pause the recurring schedule only (target remains open for manual contributions). */
    public function pauseSchedule(Request $request, int $id)
    {
        return $this->guarded(fn () => Response::successResponse('Schedule paused.', SavingsPresenter::plan($this->savings->pausePlan($request->user(), $id))));
    }

    /** Resume the recurring schedule from its next future slot. */
    public function resumeSchedule(Request $request, int $id)
    {
        return $this->guarded(fn () => Response::successResponse('Schedule resumed.', SavingsPresenter::plan($this->savings->resumePlan($request->user(), $id))));
    }

    /** Remove (cancel) the recurring schedule. History is retained. */
    public function cancelSchedule(Request $request, int $id)
    {
        return $this->guarded(fn () => Response::successResponse('Schedule cancelled.', SavingsPresenter::plan($this->savings->removeTargetSchedule($request->user(), $id))));
    }

    /** Preview the early-closure fee (if any) without changing anything. */
    public function closureQuote(Request $request, int $id)
    {
        return $this->guarded(fn () => Response::successResponse('Closure quote.', $this->savings->closureQuote($this->savings->find($request->user(), $id, SavingsAccount::TARGET))));
    }

    /**
     * Close / cancel a target
     *
     * Pays the balance (minus any disclosed early-closure fee) to the wallet.
     * If a fee applies, `acknowledge_fee=true` is required; otherwise a 422
     * with the quote is returned and nothing is charged. Requires an
     * `Idempotency-Key` header.
     */
    public function cancel(Request $request, int $id)
    {
        $data = $request->validate(['acknowledge_fee' => ['sometimes', 'boolean']]);

        return $this->guarded(function () use ($request, $id, $data) {
            $result = $this->savings->closeTarget($request->user(), $id, (bool) ($data['acknowledge_fee'] ?? false), $this->idempotencyKey($request));

            return Response::successResponse('Target closed.', [
                'quote' => $result['quote'],
                'transaction' => isset($result['entry']) ? SavingsPresenter::entry($result['entry']) : null,
                'replayed' => $result['replayed'] ?? false,
            ]);
        });
    }

    /** Withdraw from a completed or expired target to the wallet. Requires `Idempotency-Key`. */
    public function withdraw(Request $request, int $id)
    {
        $data = $request->validate(['amount' => ['required', 'numeric']]);

        return $this->guarded(function () use ($request, $id, $data) {
            $this->savings->find($request->user(), $id, SavingsAccount::TARGET);
            $result = $this->savings->withdraw($request->user(), $id, Money::parse($data['amount']), $this->idempotencyKey($request));

            return Response::successResponse('Withdrawal successful.', ['transaction' => SavingsPresenter::entry($result['entry']), 'replayed' => $result['replayed']]);
        });
    }

    /** Contribution and movement history for the target (paginated, filterable). */
    public function transactions(Request $request, int $id)
    {
        $request->validate(['per_page' => ['sometimes', 'integer', 'min:1', 'max:100']]);

        return $this->guarded(function () use ($request, $id) {
            $account = $this->savings->find($request->user(), $id, SavingsAccount::TARGET);
            $page = SavingsHistoryQuery::paginate($request, $request->user()->id, $account->id, $this->page($request));

            return Response::successResponse('Transactions retrieved.', $this->paginated($page, fn (SavingsEntry $e) => SavingsPresenter::entry($e)));
        });
    }
}
