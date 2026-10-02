<?php

namespace App\Console\Commands;

use App\Models\AutosavePlan;
use App\Models\AutosaveTransaction;
use App\Services\Savings\Contracts\SavingsFundingProviderInterface;
use App\Support\UserScopedCache;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

class ProcessMaturedAutosaves extends Command
{
    protected $signature = 'autosave:process-matured
                            {--limit=100 : Maximum number of plans to process}
                            {--dry-run : Only show matured plans without processing them}';

    protected $description =
        'Process matured autosave plans and return funds to users';

    public function __construct(
        protected SavingsFundingProviderInterface $fundingProvider
    ) {
        parent::__construct();
    }

    public function handle(): int
    {
        $limit = max(
            1,
            (int) $this->option('limit')
        );

        $dryRun = (bool) $this->option('dry-run');

        /*
         * maturity_date is a DATE field, so compare it with today()
         * rather than comparing it with the current timestamp.
         */
        $plans = AutosavePlan::query()
            ->where(
                'status',
                AutosavePlan::STATUS_ACTIVE
            )
            ->whereNotNull('maturity_date')
            ->whereDate(
                'maturity_date',
                '<=',
                today()
            )
            ->where(
                'balance',
                '>',
                '0'
            )
            ->orderBy('maturity_date')
            ->limit($limit)
            ->get();

        if ($plans->isEmpty()) {
            $this->info(
                'No matured autosave plans found.'
            );

            return self::SUCCESS;
        }

        $this->info(
            'Found '
            . $plans->count()
            . ' matured autosave plan(s).'
        );

        if ($dryRun) {
            foreach ($plans as $plan) {
                $this->line(
                    'DRY RUN: #'
                    . $plan->id
                    . ' | '
                    . $plan->name
                    . ' | User: '
                    . $plan->user_id
                    . ' | Balance: '
                    . $plan->balance
                    . ' | Maturity: '
                    . $plan->maturity_date
                );
            }

            return self::SUCCESS;
        }

        foreach ($plans as $plan) {
            try {
                $result = $this->processPlan($plan);

                if ($result === 'successful') {
                    $this->info(
                        "Matured autosave #{$plan->id} successfully."
                    );
                } elseif ($result === 'pending') {
                    $this->warn(
                        "Autosave #{$plan->id} maturity is pending."
                    );
                } elseif ($result === 'failed') {
                    $this->error(
                        "Autosave #{$plan->id} maturity failed."
                    );
                } elseif ($result === 'skipped') {
                    $this->line(
                        "Skipped autosave #{$plan->id}."
                    );
                }
            } catch (\Throwable $e) {
                report($e);

                $this->error(
                    "Failed autosave #{$plan->id}: "
                    . $e->getMessage()
                );
            }
        }

        return self::SUCCESS;
    }

    protected function processPlan(
        AutosavePlan $plan
    ): string {
        /*
         * =========================================================
         * STEP 1
         * =========================================================
         *
         * Claim the maturity operation.
         *
         * The maturity transaction itself acts as our idempotency
         * marker. This prevents two cron workers from both calling
         * SafeHaven for the same plan.
         */
        $claim = DB::transaction(function () use ($plan) {

            $lockedPlan = AutosavePlan::query()
                ->whereKey($plan->id)
                ->lockForUpdate()
                ->firstOrFail();

            /*
             * Re-check everything after acquiring the lock.
             */
            if (
                $lockedPlan->status
                    !== AutosavePlan::STATUS_ACTIVE
                || !$lockedPlan->maturity_date
                || $lockedPlan->maturity_date->isFuture()
                || bccomp(
                    (string) $lockedPlan->balance,
                    '0',
                    8
                ) <= 0
            ) {
                return null;
            }

            /*
             * Check whether this plan already has a maturity
             * transaction.
             *
             * This makes the command idempotent.
             */
            $existing = AutosaveTransaction::query()
                ->where(
                    'autosave_plan_id',
                    $lockedPlan->id
                )
                ->where(
                    'type',
                    'maturity'
                )
                ->whereIn(
                    'status',
                    [
                        'pending',
                        'successful',
                    ]
                )
                ->latest('id')
                ->first();

            if ($existing) {
                return [
                    'plan' => $lockedPlan,
                    'transaction' => $existing,
                    'already_claimed' => true,
                ];
            }

            $amount = (string) $lockedPlan->balance;

            $reference =
                'autosave:maturity:' . Str::uuid();

            /*
             * Create the pending maturity transaction BEFORE
             * contacting SafeHaven.
             *
             * This is our processing lock.
             */
            $transaction = AutosaveTransaction::create([
                'autosave_plan_id' =>
                    $lockedPlan->id,

                'user_id' =>
                    $lockedPlan->user_id,

                /*
                 * No local wallet is being debited here.
                 *
                 * SafeHaven Main Account is sending to the
                 * user's SafeHaven Sub-Account.
                 */
                'user_wallet_id' => null,

                'source_order_transaction_id' => null,

                'type' => 'maturity',

                'status' => 'pending',

                'amount' => $amount,

                'source_amount' => null,

                'percentage' => null,

                'reference' => $reference,

                'failure_reason' => null,

                'metadata' => [
                    'source' => 'autosave_maturity',
                    'maturity_date' =>
                        optional(
                            $lockedPlan->maturity_date
                        )->toDateString(),
                    'funding_provider' =>
                        get_class(
                            $this->fundingProvider
                        ),
                ],
            ]);

            return [
                'plan' => $lockedPlan,
                'transaction' => $transaction,
                'already_claimed' => false,
            ];
        });

        if (!$claim) {
            return 'skipped';
        }

        $plan = $claim['plan'];
        $transaction = $claim['transaction'];

        /*
         * Another worker already claimed this maturity.
         */
        if ($claim['already_claimed']) {

            /*
             * If it was already successfully completed,
             * nothing more needs to be done.
             */
            if ($transaction->status === 'successful') {
                return 'successful';
            }

            /*
             * A pending transaction belongs to another
             * processing/reconciliation cycle.
             */
            return 'pending';
        }

        $amount = (string) $transaction->amount;
        $reference = (string) $transaction->reference;

        /*
         * =========================================================
         * STEP 2
         * =========================================================
         *
         * Platform SafeHaven Main Account
         *              ↓
         * User SafeHaven Sub-Account
         *
         * Do NOT change the plan balance until SafeHaven confirms
         * Completed.
         */
        try {
            $fundingResponse =
                $this->fundingProvider->withdraw(
                    $plan->user,
                    $amount,
                    $reference,
                    'Autosave Maturity: '
                        . $plan->name
                );
        } catch (\Throwable $e) {

            /*
             * The provider call itself failed.
             *
             * Mark the maturity transaction as failed but keep
             * the autosave plan active because the funds have NOT
             * been confirmed as returned.
             */
            AutosaveTransaction::query()
                ->whereKey($transaction->id)
                ->update([
                    'status' => 'failed',
                    'failure_reason' =>
                        'SafeHaven maturity request failed: '
                        . $e->getMessage(),
                    'metadata' => array_merge(
                        (array) $transaction->metadata,
                        [
                            'provider_exception' =>
                                $e->getMessage(),
                        ]
                    ),
                ]);

            throw $e;
        }

        $providerStatus =
            $this->providerStatus(
                $fundingResponse
            );

        $providerTransactionId =
            $this->providerTransactionId(
                $fundingResponse
            );

        /*
         * =========================================================
         * STEP 3
         * =========================================================
         *
         * SafeHaven did not return Completed.
         */
        if ($providerStatus !== 'completed') {

            $isTerminalFailure = in_array(
                $providerStatus,
                [
                    'failed',
                    'cancelled',
                    'canceled',
                ],
                true
            );

            $metadata = $transaction->metadata;

            if (!is_array($metadata)) {
                $metadata = [];
            }

            $metadata = array_merge(
                $metadata,
                [
                    'provider_status' =>
                        $providerStatus ?: null,

                    'provider_transaction_id' =>
                        $providerTransactionId,

                    'provider_response' =>
                        $fundingResponse,
                ]
            );

            $transaction->update([
                'status' => $isTerminalFailure
                    ? 'failed'
                    : 'pending',

                'provider_status' =>
                    $providerStatus ?: null,

                'provider_transaction_id' =>
                    $providerTransactionId,

                'failure_reason' =>
                    $isTerminalFailure
                        ? 'SafeHaven maturity transfer failed. '
                            . 'Provider status: '
                            . (
                                $providerStatus
                                ?: 'unknown'
                            )
                        : null,

                'metadata' => $metadata,
            ]);

            return $isTerminalFailure
                ? 'failed'
                : 'pending';
        }

        /*
         * =========================================================
         * STEP 4
         * =========================================================
         *
         * SafeHaven confirmed Completed.
         *
         * Now perform local accounting.
         */
        return DB::transaction(
            function () use (
                $transaction,
                $fundingResponse,
                $providerTransactionId
            ) {

                /*
                 * Lock the transaction.
                 */
                $transaction =
                    AutosaveTransaction::query()
                        ->whereKey($transaction->id)
                        ->lockForUpdate()
                        ->firstOrFail();

                /*
                 * Idempotency protection.
                 */
                if (
                    $transaction->status
                    === 'successful'
                ) {
                    return 'successful';
                }

                /*
                 * Lock the plan.
                 */
                $plan = AutosavePlan::query()
                    ->whereKey(
                        $transaction->autosave_plan_id
                    )
                    ->lockForUpdate()
                    ->firstOrFail();

                /*
                 * The plan must still be active.
                 */
                if (
                    $plan->status
                    !== AutosavePlan::STATUS_ACTIVE
                ) {
                    return 'skipped';
                }

                /*
                 * Make sure the plan still has the funds
                 * we are returning.
                 */
                if (
                    bccomp(
                        (string) $plan->balance,
                        '0',
                        8
                    ) <= 0
                ) {
                    return 'skipped';
                }

                $amount =
                    (string) $plan->balance;

                /*
                 * Make sure the provider response is still
                 * Completed before settling locally.
                 */
                if (
                    $this->providerStatus(
                        $fundingResponse
                    ) !== 'completed'
                ) {
                    return 'pending';
                }

                /*
                 * =================================================
                 * SETTLE AUTOSAVE
                 * =================================================
                 *
                 * The money has successfully been returned to
                 * the user's SafeHaven Sub-Account.
                 */
                $plan->balance = '0';

                $plan->status =
                    AutosavePlan::STATUS_MATURED;

                $plan->next_due_at = null;

                /*
                 * Preserve maturity information in metadata.
                 */
                $metadata = $plan->metadata ?? [];

                if (!is_array($metadata)) {
                    $metadata = [];
                }

                $metadata['maturity'] = [
                    'amount' => $amount,

                    'reference' =>
                        $transaction->reference,

                    'provider_status' =>
                        'completed',

                    'provider_transaction_id' =>
                        $providerTransactionId,

                    'provider_response' =>
                        $fundingResponse,

                    'matured_at' =>
                        now()->toISOString(),

                    'funding_provider' =>
                        get_class(
                            $this->fundingProvider
                        ),

                    'returned_to' =>
                        'safehaven_sub_account',
                ];

                $plan->metadata = $metadata;

                $plan->save();

                /*
                 * =================================================
                 * SETTLE MATURITY TRANSACTION
                 * =================================================
                 */
                $transactionMetadata =
                    $transaction->metadata;

                if (!is_array($transactionMetadata)) {
                    $transactionMetadata = [];
                }

                $transactionMetadata = array_merge(
                    $transactionMetadata,
                    [
                        'source' =>
                            'autosave_maturity',

                        'provider_status' =>
                            'completed',

                        'provider_transaction_id' =>
                            $providerTransactionId,

                        'provider_response' =>
                            $fundingResponse,

                        'returned_to' =>
                            'safehaven_sub_account',

                        'matured_at' =>
                            now()->toISOString(),

                        'funding_provider' =>
                            get_class(
                                $this->fundingProvider
                            ),
                    ]
                );

                $transaction->status =
                    'successful';

                $transaction->provider_status =
                    'completed';

                $transaction->provider_transaction_id =
                    $providerTransactionId
                    ?? $transaction
                        ->provider_transaction_id;

                $transaction->amount = $amount;

                $transaction->metadata =
                    $transactionMetadata;

                $transaction->save();

                /*
                 * =================================================
                 * CACHE
                 * =================================================
                 */
                UserScopedCache::flush([
                    'autosave',
                    "user:{$plan->user_id}",
                ]);

                UserScopedCache::flush([
                    'autosave',
                    "user:{$plan->user_id}",
                    "autosave-plan:{$plan->id}",
                ]);

                return 'successful';
            }
        );
    }

    /**
     * Get the provider transaction ID.
     *
     * SafeHaven:
     * data._id
     */
    protected function providerTransactionId(
        array $response
    ): ?string {
        $id = $response['data']['_id'] ?? null;

        return $id !== null
            ? (string) $id
            : null;
    }

    /**
     * Get the provider transfer status.
     *
     * Supports:
     *
     * status
     * data.status
     */
    protected function providerStatus(
        array $response
    ): string {
        $status = $response['status']
            ?? ($response['data']['status'] ?? null);

        return strtolower(
            trim((string) $status)
        );
    }
}