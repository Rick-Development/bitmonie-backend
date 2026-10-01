<?php

namespace App\Services;

use App\Models\AutosavePlan;
use App\Models\AutosaveTransaction;
use App\Models\EduSave;
use App\Models\FlexSavings;
use App\Models\LockedFund;
use App\Models\OrderTransaction;
use App\Models\SafeLock;
use App\Models\SavingsTransaction;
use App\Models\TargetSavings;
use App\Models\User;
use App\Models\UserWallet;
use App\Traits\Notify;
use App\Notifications\AutosaveDeductionNotification;
use App\Support\UserScopedCache;
use App\Services\Savings\Contracts\SavingsFundingProviderInterface;
use Carbon\Carbon;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

class AutosaveService
{
    use Notify;

    public function __construct(
        protected SavingsFundingProviderInterface $fundingProvider
    ) {
    }

    public function createPlan(User $user, array $data): AutosavePlan
    {
        $nextDueAt = $this->nextDueAt($data['frequency'] ?? null);

        return AutosavePlan::create([
            'user_id' => $user->id,
            'name' => $data['name'],
            'mode' => $data['mode'],
            'amount' => $data['amount'] ?? null,
            'percentage' => $data['percentage'] ?? null,
            'frequency' => $data['frequency'] ?? null,
            'goal_amount' => $data['goal_amount'] ?? null,
            'maturity_date' => $data['maturity_date'] ?? null,
            'status' => $data['status'] ?? AutosavePlan::STATUS_ACTIVE,
            'balance' => '0',
            'next_due_at' => in_array(
                $data['mode'],
                [
                    AutosavePlan::MODE_SCHEDULED,
                    AutosavePlan::MODE_BOTH,
                ],
                true
            ) ? $nextDueAt : null,
            'metadata' => $data['metadata'] ?? null,
        ]);
    }

    public function updatePlan(
        AutosavePlan $plan,
        array $data
    ): AutosavePlan {
        $updates = collect($data)->only([
            'name',
            'mode',
            'amount',
            'percentage',
            'frequency',
            'goal_amount',
            'maturity_date',
            'status',
            'metadata',
        ])->all();

        $mode = $updates['mode'] ?? $plan->mode;
        $frequency = $updates['frequency'] ?? $plan->frequency;

        if (
            in_array(
                $mode,
                [
                    AutosavePlan::MODE_SCHEDULED,
                    AutosavePlan::MODE_BOTH,
                ],
                true
            )
            && (
                !$plan->next_due_at
                || array_key_exists('frequency', $updates)
            )
        ) {
            $updates['next_due_at'] = $this->nextDueAt($frequency);
        }

        if (!in_array(
            $mode,
            [
                AutosavePlan::MODE_SCHEDULED,
                AutosavePlan::MODE_BOTH,
            ],
            true
        )) {
            $updates['next_due_at'] = null;
        }

        $plan->update($updates);

        return $plan->refresh();
    }

    public function cancelPlan(AutosavePlan $plan): AutosavePlan
    {
        return DB::transaction(function () use ($plan) {

            $plan = AutosavePlan::whereKey($plan->id)
                ->lockForUpdate()
                ->firstOrFail();

            /*
             * Nothing to return to the user's SafeHaven
             * sub-account if the plan has no accumulated balance.
             */
            if (bccomp((string) $plan->balance, '0', 8) > 0) {

                $reference = 'autosave:cancel:' . Str::uuid();

                /*
                 * Platform SafeHaven Main Account
                 *              ↓
                 * User SafeHaven Sub-Account
                 *
                 * Only Completed is considered successful.
                 */
                $fundingResponse = $this->fundingProvider->withdraw(
                    $plan->user,
                    (string) $plan->balance,
                    $reference,
                    'Autosave Plan Cancellation: ' . $plan->name
                );

                /*
                 * Do not cancel the local plan unless SafeHaven
                 * has confirmed that the withdrawal is Completed.
                 */
                if (!$this->isCompletedFundingResponse($fundingResponse)) {
                    return $plan->refresh();
                }
            }

            $plan->update([
                'status' => AutosavePlan::STATUS_CANCELLED,
                'next_due_at' => null,
            ]);

            return $plan->refresh();
        });
    }

    public function processScheduledPlan(
        AutosavePlan $plan
    ): AutosaveTransaction {
        return $this->processDeduction(
            $plan,
            $this->defaultWallet($plan->user_id),
            (string) $plan->amount,
            'scheduled',
            [
                'frequency' => $plan->frequency,
                'due_at' => optional($plan->next_due_at)->toDateTimeString(),
            ],
            true
        );
    }

    public function processPercentagePlan(
        AutosavePlan $plan,
        UserWallet $wallet,
        OrderTransaction $sourceTransaction
    ): ?AutosaveTransaction {
        if (!$plan->usesPercentageMode() || !$plan->percentage) {
            return null;
        }

        $existing = AutosaveTransaction::where(
            'autosave_plan_id',
            $plan->id
        )
            ->where(
                'source_order_transaction_id',
                $sourceTransaction->id
            )
            ->first();

        if ($existing) {
            return $existing;
        }

        $sourceAmount = (string) $sourceTransaction->amount;

        $amount = bcdiv(
            bcmul(
                $sourceAmount,
                (string) $plan->percentage,
                8
            ),
            '100',
            8
        );

        if (bccomp($amount, '0.00000000', 8) <= 0) {
            return null;
        }

        return $this->processDeduction(
            $plan,
            $wallet,
            $amount,
            'percentage',
            [
                'source_order_transaction_id' => $sourceTransaction->id,
                'source_reference' => $sourceTransaction->reference,
                'source_amount' => $sourceAmount,
                'percentage' => (string) $plan->percentage,
            ]
        );
    }

    protected function processDeduction(
        AutosavePlan $plan,
        UserWallet $wallet,
        string $amount,
        string $type,
        array $metadata = [],
        bool $advanceSchedule = false
    ): AutosaveTransaction {
        return DB::transaction(function () use (
            $plan,
            $wallet,
            $amount,
            $type,
            $metadata,
            $advanceSchedule
        ) {

            /*
             * Lock the plan and wallet so two workers cannot
             * process the same autosave simultaneously.
             */
            $plan = AutosavePlan::whereKey($plan->id)
                ->lockForUpdate()
                ->firstOrFail();

            $wallet = UserWallet::whereKey($wallet->id)
                ->lockForUpdate()
                ->firstOrFail();

            $reference = 'autosave:' . $type . ':' . Str::uuid();

            /*
             * Do not process an inactive plan.
             */
            if ($plan->status !== AutosavePlan::STATUS_ACTIVE) {
                return AutosaveTransaction::create([
                    'autosave_plan_id' => $plan->id,
                    'user_id' => $plan->user_id,
                    'user_wallet_id' => $wallet->id,
                    'source_order_transaction_id' =>
                        $metadata['source_order_transaction_id'] ?? null,
                    'type' => $type,
                    'status' => 'skipped',
                    'amount' => $amount,
                    'source_amount' =>
                        $metadata['source_amount'] ?? null,
                    'percentage' =>
                        $metadata['percentage'] ?? null,
                    'reference' => $reference,
                    'failure_reason' => 'Plan is not active.',
                    'metadata' => $metadata,
                ]);
            }

            /*
             * Check the local wallet before touching SafeHaven.
             */
            if (bccomp((string) $wallet->balance, $amount, 8) < 0) {

                $transaction = AutosaveTransaction::create([
                    'autosave_plan_id' => $plan->id,
                    'user_id' => $plan->user_id,
                    'user_wallet_id' => $wallet->id,
                    'source_order_transaction_id' =>
                        $metadata['source_order_transaction_id'] ?? null,
                    'type' => $type,
                    'status' => 'failed',
                    'amount' => $amount,
                    'source_amount' =>
                        $metadata['source_amount'] ?? null,
                    'percentage' =>
                        $metadata['percentage'] ?? null,
                    'reference' => $reference,
                    'failure_reason' =>
                        'Insufficient wallet balance.',
                    'metadata' => $metadata,
                ]);

                if ($advanceSchedule) {
                    $plan->update([
                        'next_due_at' => $this->nextDueAt(
                            $plan->frequency,
                            now()
                        ),
                    ]);
                }

                $plan->user?->notify(
                    new AutosaveDeductionNotification($transaction)
                );

                $this->flushCache($plan);

                return $transaction;
            }

            /*
             * =========================================================
             * EXTERNAL FUNDS MOVEMENT
             * =========================================================
             *
             * User SafeHaven Sub-Account
             *             ↓
             * Platform SafeHaven Main Account
             *
             * Do this before changing our local balances.
             */
            $fundingResponse = $this->fundingProvider->deposit(
                $plan->user,
                $amount,
                $reference,
                'Autosave Deposit: ' . $plan->name
            );

            /*
             * SafeHaven transfer structure:
             *
             * data._id    = provider transfer ID
             * data.status = provider transfer status
             */
            $providerStatus = $this->providerStatus(
                $fundingResponse
            );

            $providerTransactionId =
                $this->providerTransactionId(
                    $fundingResponse
                );

            /*
             * =========================================================
             * PROVIDER RESULT HANDLING
             * =========================================================
             *
             * Completed
             *     → local accounting is performed.
             *
             * Processing / Created / Initiated / unknown
             *     → pending; reconciliation must check SafeHaven later.
             *
             * Failed / Canceled / Cancelled
             *     → failed; no local accounting.
             */
            if ($providerStatus !== 'completed') {

                $isTerminalFailure = in_array(
                    $providerStatus,
                    [
                        'failed',
                        'canceled',
                        'cancelled',
                    ],
                    true
                );

                return AutosaveTransaction::create([
                    'autosave_plan_id' => $plan->id,
                    'user_id' => $plan->user_id,
                    'user_wallet_id' => $wallet->id,
                    'source_order_transaction_id' =>
                        $metadata['source_order_transaction_id'] ?? null,
                    'type' => $type,
                    'status' => $isTerminalFailure
                        ? 'failed'
                        : 'pending',
                    'provider_status' => $providerStatus ?: null,
                    'provider_transaction_id' =>
                        $providerTransactionId,
                    'amount' => $amount,
                    'source_amount' =>
                        $metadata['source_amount'] ?? null,
                    'percentage' =>
                        $metadata['percentage'] ?? null,
                    'reference' => $reference,
                    'failure_reason' => $isTerminalFailure
                        ? 'SafeHaven transfer failed. '
                            . 'Provider status: '
                            . ($providerStatus ?: 'unknown')
                        : null,
                    'metadata' => array_merge(
                        $metadata,
                        [
                            'provider_status' =>
                                $providerStatus ?: null,
                            'provider_transaction_id' =>
                                $providerTransactionId,
                            'provider_response' =>
                                $fundingResponse,
                            'funding_provider' =>
                                get_class($this->fundingProvider),
                        ]
                    ),
                ]);
            }

            /*
             * =========================================================
             * SAFEHAVEN IS COMPLETED
             * =========================================================
             *
             * Only now do we modify:
             *
             * - User wallet
             * - Autosave plan
             * - Savings target
             * - Order transaction
             * - Autosave transaction
             */
            $autosaveStatus = 'successful';

            $wallet->balance = bcsub(
                (string) $wallet->balance,
                $amount,
                8
            );

            $wallet->save();

            $plan->balance = bcadd(
                (string) $plan->balance,
                $amount,
                8
            );

            $plan->last_deducted_at = now();

            if ($advanceSchedule) {
                $plan->next_due_at = $this->nextDueAt(
                    $plan->frequency,
                    now()
                );
            }

            $plan->save();

            /*
             * Apply the money to the selected savings target.
             */
            $target = $this->applyTargetDeposit(
                $plan,
                $amount,
                $type,
                $metadata
            );

            /*
             * Record wallet debit.
             */
            OrderTransaction::create([
                'user_wallet_id' => $wallet->id,
                'type' => 'debit',
                'amount' => $amount,
                'balance_after' => $wallet->balance,
                'reference' => $reference,
                'metadata' => [
                    'source' => 'autosave',
                    'autosave_plan_id' => $plan->id,
                    'autosave_type' => $type,
                    'autosave_target' => $target,
                    'funding_provider' =>
                        get_class($this->fundingProvider),
                    'provider_status' => $providerStatus,
                    'provider_transaction_id' =>
                        $providerTransactionId,
                    'provider_response' => $fundingResponse,
                ],
            ]);

            /*
             * Record successful autosave transaction.
             */
            $transaction = AutosaveTransaction::create([
                'autosave_plan_id' => $plan->id,
                'user_id' => $plan->user_id,
                'user_wallet_id' => $wallet->id,
                'source_order_transaction_id' =>
                    $metadata['source_order_transaction_id'] ?? null,
                'type' => $type,
                'status' => $autosaveStatus,
                'provider_status' => $providerStatus,
                'provider_transaction_id' =>
                    $providerTransactionId,
                'amount' => $amount,
                'source_amount' =>
                    $metadata['source_amount'] ?? null,
                'percentage' =>
                    $metadata['percentage'] ?? null,
                'reference' => $reference,
                'metadata' => array_merge(
                    $metadata,
                    [
                        'target' => $target,
                        'funding_provider' =>
                            get_class($this->fundingProvider),
                        'provider_status' => $providerStatus,
                        'provider_transaction_id' =>
                            $providerTransactionId,
                        'provider_response' =>
                            $fundingResponse,
                    ]
                ),
            ]);

            /*
             * Notify only after SafeHaven has confirmed Completed.
             */
            $this->sendNotification(
                $plan->user,
                'AUTOSAVE_DEDUCTION_SUCCESS',
                [
                    'user' => $plan->user->firstname,
                    'amount' => number_format($amount, 2),
                    'balance' => number_format(
                        $plan->balance,
                        2
                    ),
                    'reference' => $reference,
                    'saving_name' => $plan->name,
                    'type' => ucfirst($type),
                ],
                ['mail', 'push', 'inapp'],
                [
                    'referenceId' => $reference,
                ]
            );

            $this->flushCache($plan);

            return $transaction;
        });
    }

    /**
     * Determine the SafeHaven provider transaction ID.
     *
     * SafeHaven returns the transfer ID as:
     *
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
     * Determine the provider transfer status.
     *
     * SafeHaven normally returns:
     *
     * data.status
     *
     * The top-level status is also supported for providers
     * that return it there.
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

    /**
     * Only Completed is considered successful.
     */
    protected function isCompletedFundingResponse(
        array $response
    ): bool {
        return $this->providerStatus($response) === 'completed';
    }

    public function nextDueAt(
        ?string $frequency,
        ?Carbon $from = null
    ): ?Carbon {
        if (!$frequency) {
            return null;
        }

        $from = $from ? $from->copy() : now();

        return match ($frequency) {
            'daily' => $from->addDay(),
            'weekly' => $from->addWeek(),
            'monthly' => $from->addMonthNoOverflow(),
            default => null,
        };
    }

    protected function defaultWallet(int $userId): UserWallet
    {
        return UserWallet::where('user_id', $userId)
            ->where(function ($query) {
                $query->where('currency_code', 'NGN')
                    ->orWhereHas(
                        'currency',
                        fn ($currency) => $currency->where(
                            'code',
                            'NGN'
                        )
                    );
            })
            ->active()
            ->firstOrFail();
    }

    protected function applyTargetDeposit(
        AutosavePlan $plan,
        string $amount,
        string $type,
        array $metadata
    ): ?array {
        $planMetadata = $plan->metadata ?? [];

        $targetModel = $planMetadata['target_model'] ?? null;
        $targetId = $planMetadata['target_id'] ?? null;

        if (!$targetModel || !$targetId) {
            return null;
        }

        if (!is_subclass_of(
            $targetModel,
            \Illuminate\Database\Eloquent\Model::class
        )) {
            return [
                'status' => 'invalid_target',
                'target_model' => $targetModel,
                'target_id' => $targetId,
            ];
        }

        $target = $targetModel::whereKey($targetId)
            ->lockForUpdate()
            ->first();

        if (
            !$target
            || (int) ($target->user_id ?? 0)
                !== (int) $plan->user_id
        ) {
            return [
                'status' => 'missing',
                'target_model' => $targetModel,
                'target_id' => $targetId,
            ];
        }

        if ($target instanceof FlexSavings) {

            $target->balance = bcadd(
                (string) $target->balance,
                $amount,
                8
            );

            $target->save();

            $this->logTargetSavingsTransaction(
                $plan,
                $target,
                $amount,
                (string) $target->balance,
                $type,
                'Flex Savings AutoSave Deposit'
            );

        } elseif ($target instanceof EduSave) {

            $target->amount = bcadd(
                (string) $target->amount,
                $amount,
                8
            );

            $target->save();

            $this->logTargetSavingsTransaction(
                $plan,
                $target,
                $amount,
                (string) $target->amount,
                $type,
                'EduSave AutoSave Deposit: ' . $target->title
            );

        } elseif ($target instanceof SafeLock) {

            $target->amount = bcadd(
                (string) $target->amount,
                $amount,
                8
            );

            $target->save();

            $this->logTargetSavingsTransaction(
                $plan,
                $target,
                $amount,
                (string) $target->amount,
                $type,
                'SafeLock AutoSave Deposit: ' . $target->title
            );

        } elseif ($target instanceof TargetSavings) {

            $target->current_balance = bcadd(
                (string) $target->current_balance,
                $amount,
                8
            );

            $target->save();

            $this->logTargetSavingsTransaction(
                $plan,
                $target,
                $amount,
                (string) $target->current_balance,
                $type,
                'Target Savings AutoSave Deposit: ' . $target->title
            );

        } elseif ($target instanceof LockedFund) {

            $target->amount = bcadd(
                (string) $target->amount,
                $amount,
                8
            );

            $target->save();
        }

        return [
            'status' => 'applied',
            'target_model' => $targetModel,
            'target_id' => $targetId,
            'target_type' => $planMetadata['target_type'] ?? null,
            'balance_after' => (string) (
                $target->balance
                ?? $target->amount
                ?? $target->current_balance
                ?? '0'
            ),
        ];
    }

    protected function logTargetSavingsTransaction(
        AutosavePlan $plan,
        object $target,
        string $amount,
        string $balanceAfter,
        string $type,
        string $narration
    ): void {
        SavingsTransaction::create([
            'user_id' => $plan->user_id,
            'savingsable_id' => $target->id,
            'savingsable_type' => $target::class,
            'amount' => $amount,
            'balance_after' => $balanceAfter,
            'type' => 'deposit',
            'status' => 'success',
            'source' => 'autosave',
            'narration' => $narration . ' (' . $type . ')',
        ]);
    }

    protected function flushCache(AutosavePlan $plan): void
    {
        UserScopedCache::flush([
            'autosave',
            "user:{$plan->user_id}",
        ]);

        UserScopedCache::flush([
            'autosave',
            "user:{$plan->user_id}",
            "autosave-plan:{$plan->id}",
        ]);
    }

/**
 * Apply local accounting after SafeHaven confirms
 * that the autosave transfer is Completed.
 *
 * The provider call must already have happened.
 * This method performs only local database accounting.
 */
public function reconcileCompletedTransaction(
    AutosaveTransaction $transaction,
    array $providerResponse
): AutosaveTransaction {
    return DB::transaction(function () use (
        $transaction,
        $providerResponse
    ) {
        /*
         * Lock the autosave transaction so the same provider
         * transfer cannot be reconciled twice concurrently.
         */
        $transaction = AutosaveTransaction::whereKey($transaction->id)
            ->lockForUpdate()
            ->firstOrFail();

        /*
         * Idempotency protection.
         *
         * If another reconciliation process already completed
         * this transaction, do nothing.
         */
        if ($transaction->status === 'successful') {
            return $transaction;
        }

        /*
         * SafeHaven must be Completed before local accounting.
         */
        if ($this->providerStatus($providerResponse) !== 'completed') {
            return $transaction;
        }

        $plan = AutosavePlan::whereKey($transaction->autosave_plan_id)
            ->lockForUpdate()
            ->firstOrFail();

        $wallet = UserWallet::whereKey($transaction->user_wallet_id)
            ->lockForUpdate()
            ->firstOrFail();

        $amount = (string) $transaction->amount;
        $type = (string) $transaction->type;

        $metadata = $transaction->metadata ?? [];

        if (!is_array($metadata)) {
            $metadata = [];
        }

        $providerTransactionId =
            $this->providerTransactionId($providerResponse);

        /*
         * Keep the provider information in metadata.
         */
        $metadata['provider_status'] = 'completed';
        $metadata['provider_transaction_id'] =
            $providerTransactionId;
        $metadata['provider_response'] =
            $providerResponse;

        /*
         * ---------------------------------------------------------
         * WALLET
         * ---------------------------------------------------------
         *
         * Protect against a negative balance if something changed
         * between the original autosave attempt and reconciliation.
         */
        if (bccomp(
            (string) $wallet->balance,
            $amount,
            8
        ) < 0) {
            throw new \RuntimeException(
                'Insufficient wallet balance during autosave reconciliation.'
            );
        }

        $wallet->balance = bcsub(
            (string) $wallet->balance,
            $amount,
            8
        );

        $wallet->save();

        /*
         * ---------------------------------------------------------
         * AUTOSAVE PLAN
         * ---------------------------------------------------------
         */
        $plan->balance = bcadd(
            (string) $plan->balance,
            $amount,
            8
        );

        $plan->last_deducted_at = now();

        /*
         * Advance the schedule only for scheduled/both plans.
         *
         * We use the transaction metadata to determine whether
         * this was a scheduled autosave.
         */
        $advanceSchedule = $this->shouldAdvanceSchedule(
            $transaction
        );

        if ($advanceSchedule) {
            $plan->next_due_at = $this->nextDueAt(
                $plan->frequency,
                now()
            );
        }

        $plan->save();

        /*
         * ---------------------------------------------------------
         * SAVINGS TARGET
         * ---------------------------------------------------------
         */
        $target = $this->applyTargetDeposit(
            $plan,
            $amount,
            $type,
            $metadata
        );

        /*
         * ---------------------------------------------------------
         * WALLET DEBIT TRANSACTION
         * ---------------------------------------------------------
         */
        OrderTransaction::create([
            'user_wallet_id' => $wallet->id,
            'type' => 'debit',
            'amount' => $amount,
            'balance_after' => $wallet->balance,
            'reference' => $transaction->reference,
            'metadata' => [
                'source' => 'autosave',
                'autosave_plan_id' => $plan->id,
                'autosave_type' => $type,
                'autosave_target' => $target,
                'funding_provider' =>
                    $metadata['funding_provider']
                    ?? get_class($this->fundingProvider),
                'provider_status' => 'completed',
                'provider_transaction_id' =>
                    $providerTransactionId,
                'provider_response' =>
                    $providerResponse,
            ],
        ]);

        /*
         * ---------------------------------------------------------
         * AUTOSAVE TRANSACTION
         * ---------------------------------------------------------
         */
        $transaction->status = 'successful';
        $transaction->provider_status = 'completed';
        $transaction->provider_transaction_id =
            $providerTransactionId
            ?? $transaction->provider_transaction_id;
        $transaction->metadata = $metadata;

        $transaction->save();

        /*
         * ---------------------------------------------------------
         * NOTIFICATION
         * ---------------------------------------------------------
         */
        $this->sendNotification(
            $plan->user,
            'AUTOSAVE_DEDUCTION_SUCCESS',
            [
                'user' => $plan->user->firstname,
                'amount' => number_format($amount, 2),
                'balance' => number_format(
                    $plan->balance,
                    2
                ),
                'reference' => $transaction->reference,
                'saving_name' => $plan->name,
                'type' => ucfirst($type),
            ],
            ['mail', 'push', 'inapp'],
            [
                'referenceId' => $transaction->reference,
            ]
        );

        $this->flushCache($plan);

        return $transaction->refresh();
    });
}

/**
 * Determine whether this transaction should advance
 * the scheduled autosave date.
 */
protected function shouldAdvanceSchedule(
    AutosaveTransaction $transaction
): bool {
    $metadata = $transaction->metadata ?? [];

    if (!is_array($metadata)) {
        return false;
    }

    return array_key_exists('frequency', $metadata)
        && !empty($metadata['frequency']);
}

}
