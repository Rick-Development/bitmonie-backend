<?php

namespace App\Jobs;

use App\Models\RampTransaction;
use App\Services\QuidaxService;
use Exception;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Schema;

class ProcessRampSell implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    /**
     * Maximum number of attempts before the job permanently fails.
     */
    public int $tries = 10;

    /**
     * Time to wait when running synchronously.
     */
    protected int $syncBalanceWaitSeconds = 120;

    /**
     * Poll interval while waiting synchronously.
     */
    protected int $syncBalancePollIntervalSeconds = 5;

    public $user;
    public $merchantReference;
    public $mainAccountData;
    public $sourceCurrency;
    public $sourceAmount;
    public $rampAddress;
    public $network;

    public function __construct(
        $user,
        $merchantReference,
        $mainAccountData,
        $sourceCurrency,
        $sourceAmount,
        $rampAddress,
        $network
    ) {
        $this->user = $user;
        $this->merchantReference = $merchantReference;
        $this->mainAccountData = $mainAccountData;
        $this->sourceCurrency = $sourceCurrency;
        $this->sourceAmount = $sourceAmount;
        $this->rampAddress = $rampAddress;
        $this->network = $network;
    }

    /**
     * Dedicated logger for this job.
     */
    protected function rampLog()
    {
        return Log::channel('ramp_sell');
    }

    /**
     * Execute the queued job.
     */
public function handle(QuidaxService $quidaxService): void
{
    $this->rampLog()->info('ProcessRampSell started.', [
        'merchant_reference' => $this->merchantReference,
        'attempt'            => $this->attempts(),
        'source_currency'    => $this->sourceCurrency,
        'source_amount'      => $this->sourceAmount,
        'network'            => $this->network,
        'ramp_address'       => $this->rampAddress,
    ]);

    try {
        /*
         * ---------------------------------------------------------------
         * LOAD TRANSACTION
         * ---------------------------------------------------------------
         */
        $transaction = RampTransaction::where(
            'merchant_reference',
            $this->merchantReference
        )
            ->where('type', 'off_ramp')
            ->first();

        if (!$transaction) {
            $this->rampLog()->warning(
                'ProcessRampSell transaction not found.',
                [
                    'merchant_reference' => $this->merchantReference,
                ]
            );

            return;
        }

        /*
         * ---------------------------------------------------------------
         * PREVENT DUPLICATE / FINALIZED EXECUTION
         * ---------------------------------------------------------------
         */
        if (in_array($transaction->status, [
            'completed',
            'failed',
            'cancelled',
            'reversed',
        ], true)) {
            $this->rampLog()->info(
                'ProcessRampSell skipped because transaction is already terminal.',
                [
                    'merchant_reference' => $this->merchantReference,
                    'status'             => $transaction->status,
                ]
            );

            return;
        }

        $jobMetadata = $this->getJobMetadata($transaction);

        /*
         * ---------------------------------------------------------------
         * IDEMPOTENT REFERENCES
         * ---------------------------------------------------------------
         */
        $mainWithdrawalReference =
            $this->merchantReference . '_to_master';

        $rampWithdrawalReference =
            $this->merchantReference . '_ramp';

        /*
         * ---------------------------------------------------------------
         * USER -> MASTER WITHDRAWAL AMOUNT
         *
         * Always use the actual amount intended for the first transfer.
         * ---------------------------------------------------------------
         */
        $mainTransferAmount = $this->mainAccountData['amount']
            ?? $this->sourceAmount;

        /*
         * ---------------------------------------------------------------
         * USER -> MASTER WALLET TRANSFER
         * ---------------------------------------------------------------
         */
        $mainAccountResponse =
            $jobMetadata['main_account_withdrawal'] ?? null;

        if (!$this->isSuccessfulWithdrawalResponse($mainAccountResponse)) {

            /*
             * First reconcile an already-created withdrawal using the
             * SAME idempotent reference.
             */
            $existingMainWithdrawal = null;

            try {
                $existingMainWithdrawal =
                    $quidaxService->findWithdrawalByReference(
                        $this->user->quidax_id,
                        $mainWithdrawalReference,
                        $this->sourceCurrency
                    );
            } catch (Exception $e) {
                $this->rampLog()->error(
                    'ProcessRampSell could not reconcile existing User -> Master withdrawal.',
                    [
                        'merchant_reference' => $this->merchantReference,
                        'reference'          => $mainWithdrawalReference,
                        'message'            => $e->getMessage(),
                    ]
                );

                throw $e;
            }

            if ($existingMainWithdrawal) {
                $this->rampLog()->info(
                    'ProcessRampSell recovered existing User -> Master withdrawal.',
                    [
                        'merchant_reference' => $this->merchantReference,
                        'reference'          => $mainWithdrawalReference,
                        'response'           => $existingMainWithdrawal,
                    ]
                );

                $mainAccountResponse = $existingMainWithdrawal;

                $jobMetadata['main_account_withdrawal'] =
                    $mainAccountResponse;

                $jobMetadata['main_account_withdrawal_recovered_at'] =
                    now()->toIso8601String();

                $transaction = $this->updateJobMetadata(
                    $transaction,
                    $jobMetadata
                );
            }

            /*
             * Only create a new withdrawal when we have confirmed
             * that no existing withdrawal exists.
             */
            if (!$mainAccountResponse) {
                $this->rampLog()->info(
                    'ProcessRampSell initiating User -> Master withdrawal.',
                    [
                        'merchant_reference' => $this->merchantReference,
                        'reference'          => $mainWithdrawalReference,
                        'currency'           => strtolower(
                            $this->sourceCurrency
                        ),
                        'network'            => strtolower(
                            $this->mainAccountData['network']
                                ?? $this->network
                        ),
                        'amount'             => $mainTransferAmount,
                    ]
                );

                $mainAccountResponse = $quidaxService->create_withdrawal(
                    $this->user->quidax_id,
                    $this->mainAccountData,
                    $mainWithdrawalReference
                );

                $this->rampLog()->info(
                    'ProcessRampSell User -> Master withdrawal response.',
                    [
                        'merchant_reference' => $this->merchantReference,
                        'reference'          => $mainWithdrawalReference,
                        'response'           => $mainAccountResponse,
                    ]
                );

                $jobMetadata['main_account_withdrawal'] =
                    $mainAccountResponse;

                $jobMetadata['main_account_withdrawal_requested_at'] =
                    $jobMetadata['main_account_withdrawal_requested_at']
                    ?? now()->toIso8601String();

                $transaction = $this->updateJobMetadata(
                    $transaction,
                    $jobMetadata
                );
            }
        } else {
            $this->rampLog()->info(
                'ProcessRampSell using previously recorded User -> Master withdrawal.',
                [
                    'merchant_reference' => $this->merchantReference,
                    'reference'          => $mainWithdrawalReference,
                ]
            );
        }

        /*
         * ---------------------------------------------------------------
         * USER -> MASTER WITHDRAWAL FAILURE
         * ---------------------------------------------------------------
         */
        if (!$this->isSuccessfulWithdrawalResponse($mainAccountResponse)) {

            $jobMetadata['last_main_withdrawal_error'] =
                $mainAccountResponse;

            $jobMetadata['last_main_withdrawal_error_at'] =
                now()->toIso8601String();

            $transaction = $this->updateJobMetadata(
                $transaction,
                $jobMetadata
            );

            /*
             * Ambiguous provider response:
             *
             * NEVER reverse.
             * Retry and reconcile using the same reference.
             */
            if (
                !$this->isDefinitiveProviderFailure(
                    $mainAccountResponse
                )
            ) {
                if ($this->attempts() < $this->tries) {
                    $delay = $this->retryDelaySeconds();

                    $this->rampLog()->warning(
                        'ProcessRampSell retrying ambiguous User -> Master withdrawal response.',
                        [
                            'merchant_reference' => $this->merchantReference,
                            'attempt'            => $this->attempts(),
                            'retry_after'        => $delay,
                            'response'           => $mainAccountResponse,
                        ]
                    );

                    $this->release($delay);

                    return;
                }

                $this->rampLog()->critical(
                    'ProcessRampSell exhausted retries with ambiguous User -> Master withdrawal state.',
                    [
                        'merchant_reference' => $this->merchantReference,
                        'reference'          => $mainWithdrawalReference,
                        'response'           => $mainAccountResponse,
                    ]
                );

                $jobMetadata['manual_reconciliation_required'] = true;

                $jobMetadata['manual_reconciliation_reason'] =
                    'User -> Master withdrawal state could not be confirmed after all retries.';

                $jobMetadata['manual_reconciliation_at'] =
                    now()->toIso8601String();

                $this->updateJobMetadata(
                    $transaction,
                    $jobMetadata
                );

                return;
            }

            /*
             * Definitive provider failure.
             *
             * At this point it is safe to fail the transaction because
             * the User -> Master transfer itself was explicitly rejected.
             */
            $this->failTransaction(
                'Withdrawal from user to master failed: '
                . ($mainAccountResponse['message'] ?? 'Unknown provider error'),
                $jobMetadata
            );

            return;
        }

        /*
         * ---------------------------------------------------------------
         * VERIFY MASTER BALANCE
         * ---------------------------------------------------------------
         */
        $mainWalletResponse = $quidaxService->fetchUserWallet(
            'me',
            strtolower($this->sourceCurrency)
        );

        $this->rampLog()->info(
            'ProcessRampSell main wallet check.',
            [
                'merchant_reference' => $this->merchantReference,
                'response'           => $mainWalletResponse,
            ]
        );

        $availableMainBalance = $this->decimalAmount(
            $mainWalletResponse['data']['balance'] ?? '0'
        );

        /*
         * IMPORTANT:
         *
         * Use the actual amount transferred User -> Master.
         */
        $requiredRampBalance = $this->decimalAmount(
            $mainTransferAmount
        );

        $jobMetadata['main_account_ready_check'] = [
            'required_balance' => $this->formatDecimalAmount(
                $requiredRampBalance
            ),
            'available_balance' => $this->formatDecimalAmount(
                $availableMainBalance
            ),
            'checked_at' => now()->toIso8601String(),
        ];

        $transaction = $this->updateJobMetadata(
            $transaction,
            $jobMetadata
        );

        /*
         * ---------------------------------------------------------------
         * WAIT FOR MASTER WALLET FUNDING
         * ---------------------------------------------------------------
         */
        $masterBalanceReady =
            ($mainWalletResponse['status'] ?? '') === 'success'
            && $this->compareDecimalAmounts(
                $availableMainBalance,
                $requiredRampBalance
            ) >= 0;

        if (!$masterBalanceReady) {
            if ($this->shouldBlockUntilBalanceSettles()) {

                $this->rampLog()->info(
                    'ProcessRampSell waiting synchronously for master balance.',
                    [
                        'merchant_reference' => $this->merchantReference,
                        'required_balance'   => $this->formatDecimalAmount(
                            $requiredRampBalance
                        ),
                        'available_balance'  => $this->formatDecimalAmount(
                            $availableMainBalance
                        ),
                    ]
                );

                $mainWalletResponse = $this->waitForMainWalletBalance(
                    $quidaxService,
                    $requiredRampBalance,
                    $mainWalletResponse
                );

                $availableMainBalance = $this->decimalAmount(
                    $mainWalletResponse['data']['balance'] ?? '0'
                );

                $jobMetadata['main_account_ready_check'] = [
                    'required_balance' => $this->formatDecimalAmount(
                        $requiredRampBalance
                    ),
                    'available_balance' => $this->formatDecimalAmount(
                        $availableMainBalance
                    ),
                    'checked_at' => now()->toIso8601String(),
                ];

                $transaction = $this->updateJobMetadata(
                    $transaction,
                    $jobMetadata
                );

                $masterBalanceReady =
                    ($mainWalletResponse['status'] ?? '') === 'success'
                    && $this->compareDecimalAmounts(
                        $availableMainBalance,
                        $requiredRampBalance
                    ) >= 0;
            }
        }

        /*
         * ---------------------------------------------------------------
         * MASTER STILL NOT FUNDED
         * ---------------------------------------------------------------
         */
        if (!$masterBalanceReady) {

            /*
             * ALWAYS reconcile Master -> Ramp before reversal.
             */
            $rampReconciliation =
                $this->reconcileRampWithdrawal(
                    $quidaxService
                );

            /*
             * Master -> Ramp already exists.
             *
             * NEVER reverse User -> Master.
             */
            if ($rampReconciliation['state'] === 'found') {

                $rampWithdrawal =
                    $rampReconciliation['data'];

                $jobMetadata['ramp_withdrawal'] =
                    $rampWithdrawal;

                $jobMetadata['ramp_withdrawal_recovered_at'] =
                    now()->toIso8601String();

                $transaction = $this->updateJobMetadata(
                    $transaction,
                    $jobMetadata
                );

                $this->markAwaitingPayout(
                    $transaction,
                    $rampWithdrawal,
                    $jobMetadata
                );

                return;
            }

            /*
             * Provider state unknown.
             *
             * NEVER reverse.
             */
            if ($rampReconciliation['state'] === 'unknown') {

                if ($this->attempts() < $this->tries) {

                    $delay = $this->retryDelaySeconds();

                    $this->rampLog()->warning(
                        'ProcessRampSell cannot verify Master -> Ramp withdrawal while master balance is pending. Retrying without reversal.',
                        [
                            'merchant_reference' => $this->merchantReference,
                            'attempt'            => $this->attempts(),
                            'retry_after'        => $delay,
                        ]
                    );

                    $this->release($delay);

                    return;
                }

                $jobMetadata['manual_reconciliation_required'] = true;

                $jobMetadata['manual_reconciliation_reason'] =
                    'Unable to verify Master -> Ramp withdrawal while waiting for master balance.';

                $jobMetadata['manual_reconciliation_at'] =
                    now()->toIso8601String();

                $this->updateJobMetadata(
                    $transaction,
                    $jobMetadata
                );

                $this->rampLog()->critical(
                    'ProcessRampSell exhausted retries while Master -> Ramp withdrawal state is unknown. NO REVERSAL PERFORMED.',
                    [
                        'merchant_reference' => $this->merchantReference,
                        'required_balance'   => $this->formatDecimalAmount(
                            $requiredRampBalance
                        ),
                        'available_balance'  => $this->formatDecimalAmount(
                            $availableMainBalance
                        ),
                    ]
                );

                return;
            }

            /*
             * -----------------------------------------------------------
             * CONFIRMED NOT FOUND + FINAL ATTEMPT
             * -----------------------------------------------------------
             *
             * Only now is reversal allowed.
             */
            if (
                $rampReconciliation['state'] === 'not_found'
                && $this->attempts() >= $this->tries
            ) {

                $this->rampLog()->warning(
                    'ProcessRampSell confirmed Master -> Ramp withdrawal does not exist. Starting reversal.',
                    [
                        'merchant_reference' => $this->merchantReference,
                        'required_balance'   => $this->formatDecimalAmount(
                            $requiredRampBalance
                        ),
                        'available_balance'  => $this->formatDecimalAmount(
                            $availableMainBalance
                        ),
                        'attempt'            => $this->attempts(),
                    ]
                );

                $reversalResponse = $this->reverseWithdrawal(
                    $quidaxService,
                    'Timed out waiting for Quidax main account funding.'
                );

                $jobMetadata['automatic_reversal'] =
                    $reversalResponse;

                $transaction = $this->updateJobMetadata(
                    $transaction,
                    $jobMetadata
                );

                /*
                 * Reversal not confirmed.
                 *
                 * NEVER mark failed.
                 */
                if (!$this->isSuccessfulWithdrawalResponse($reversalResponse)) {

                    $jobMetadata['manual_reconciliation_required'] = true;

                    $jobMetadata['manual_reconciliation_reason'] =
                        'Timed out waiting for Quidax main account funding and automatic reversal could not be confirmed.';

                    $jobMetadata['manual_reconciliation_at'] =
                        now()->toIso8601String();

                    $this->updateJobMetadata(
                        $transaction,
                        $jobMetadata
                    );

                    $this->rampLog()->critical(
                        'ProcessRampSell timeout reversal could not be confirmed. Transaction NOT marked failed.',
                        [
                            'merchant_reference' => $this->merchantReference,
                            'reversal_response'  => $reversalResponse,
                        ]
                    );

                    return;
                }

                /*
                 * Reversal confirmed.
                 */
                $this->failTransaction(
                    'Timed out waiting for the Quidax main account to receive '
                    . 'the user transfer for off-ramp settlement.',
                    $jobMetadata
                );

                return;
            }

            /*
             * Attempts remain.
             */
            $delay = $this->retryDelaySeconds();

            $this->rampLog()->info(
                'ProcessRampSell releasing job while waiting for master balance.',
                [
                    'merchant_reference' => $this->merchantReference,
                    'attempt'            => $this->attempts(),
                    'retry_after'        => $delay,
                    'required_balance'   => $this->formatDecimalAmount(
                        $requiredRampBalance
                    ),
                    'available_balance'  => $this->formatDecimalAmount(
                        $availableMainBalance
                    ),
                ]
            );

            $this->release($delay);

            return;
        }

        /*
         * ---------------------------------------------------------------
         * MASTER -> RAMP WITHDRAWAL
         * ---------------------------------------------------------------
         */
        $rampResponse =
            $jobMetadata['ramp_withdrawal'] ?? null;

        /*
         * Existing successful payout.
         */
        if (!$this->isSuccessfulWithdrawalResponse($rampResponse)) {

            /*
             * ALWAYS reconcile before creating a new withdrawal.
             */
            $rampReconciliation =
                $this->reconcileRampWithdrawal(
                    $quidaxService
                );

            /*
             * Existing payout found.
             */
            if ($rampReconciliation['state'] === 'found') {

                $rampResponse =
                    $rampReconciliation['data'];

                $jobMetadata['ramp_withdrawal'] =
                    $rampResponse;

                $jobMetadata['ramp_withdrawal_recovered_at'] =
                    now()->toIso8601String();

                $transaction = $this->updateJobMetadata(
                    $transaction,
                    $jobMetadata
                );

            /*
             * Unknown state.
             */
            } elseif ($rampReconciliation['state'] === 'unknown') {

                if ($this->attempts() < $this->tries) {

                    $delay = $this->retryDelaySeconds();

                    $this->rampLog()->warning(
                        'ProcessRampSell could not verify existing Master -> Ramp withdrawal. Retrying without creating another withdrawal.',
                        [
                            'merchant_reference' => $this->merchantReference,
                            'reference'          => $rampWithdrawalReference,
                            'attempt'            => $this->attempts(),
                            'retry_after'        => $delay,
                        ]
                    );

                    $this->release($delay);

                    return;
                }

                $jobMetadata['manual_reconciliation_required'] = true;

                $jobMetadata['manual_reconciliation_reason'] =
                    'Unable to verify Master -> Ramp withdrawal before creating payout.';

                $jobMetadata['manual_reconciliation_at'] =
                    now()->toIso8601String();

                $this->updateJobMetadata(
                    $transaction,
                    $jobMetadata
                );

                return;

            /*
             * Confirmed not found.
             *
             * Safe to create.
             */
            } else {

                $this->rampLog()->info(
                    'ProcessRampSell initiating Master -> Ramp withdrawal.',
                    [
                        'merchant_reference' => $this->merchantReference,
                        'reference'          => $rampWithdrawalReference,
                        'currency'           => strtolower(
                            $this->sourceCurrency
                        ),
                        'network'            => strtolower(
                            $this->network
                        ),
                        'amount'             => $this->sourceAmount,
                        'ramp_address'       => $this->rampAddress,
                    ]
                );

                $rampResponse = $quidaxService->create_withdrawal(
                    'me',
                    [
                        'network' => strtolower($this->network),
                        'amount' => $this->sourceAmount,
                        'currency' => strtolower($this->sourceCurrency),
                        'fund_uid' => $this->rampAddress,
                        'transaction_note' =>
                            'Off-Ramp Sell: Master account pay-out to Ramp',
                        'narration' =>
                            'Off-Ramp Sell: Master account pay-out to Ramp',
                    ],
                    $rampWithdrawalReference
                );

                $this->rampLog()->info(
                    'ProcessRampSell Master -> Ramp withdrawal response.',
                    [
                        'merchant_reference' => $this->merchantReference,
                        'reference'          => $rampWithdrawalReference,
                        'response'           => $rampResponse,
                    ]
                );

                $jobMetadata['ramp_withdrawal'] =
                    $rampResponse;

                $jobMetadata['ramp_withdrawal_requested_at'] =
                    $jobMetadata['ramp_withdrawal_requested_at']
                    ?? now()->toIso8601String();

                $transaction = $this->updateJobMetadata(
                    $transaction,
                    $jobMetadata
                );
            }
        }

        /*
         * ---------------------------------------------------------------
         * HANDLE MASTER -> RAMP FAILURE
         * ---------------------------------------------------------------
         */
        if (!$this->isSuccessfulWithdrawalResponse($rampResponse)) {

            $jobMetadata['last_ramp_withdrawal_error'] =
                $rampResponse;

            $jobMetadata['last_ramp_withdrawal_error_at'] =
                now()->toIso8601String();

            $transaction = $this->updateJobMetadata(
                $transaction,
                $jobMetadata
            );

            /*
             * Insufficient balance can be a settlement race.
             *
             * NEVER reverse immediately.
             */
            if (
                $this->isInsufficientBalanceResponse($rampResponse)
                && $this->attempts() < $this->tries
            ) {
                $delay = $this->retryDelaySeconds();

                $this->rampLog()->warning(
                    'ProcessRampSell retrying because Master -> Ramp withdrawal has insufficient balance.',
                    [
                        'merchant_reference' => $this->merchantReference,
                        'attempt'            => $this->attempts(),
                        'retry_after'        => $delay,
                        'network'            => strtolower($this->network),
                        'currency'           => strtolower($this->sourceCurrency),
                        'amount'             => $this->sourceAmount,
                        'provider_message'   => $rampResponse['message'] ?? null,
                        'provider_data'      => $rampResponse['data'] ?? null,
                    ]
                );

                $this->release($delay);

                return;
            }

            /*
             * Final reconciliation before reversal.
             */
            $confirmedRampWithdrawal =
                $this->reconcileRampWithdrawal(
                    $quidaxService
                );

            /*
             * Payout exists.
             *
             * NEVER reverse.
             */
            if ($confirmedRampWithdrawal['state'] === 'found') {

                $confirmedResponse =
                    $confirmedRampWithdrawal['data'];

                $jobMetadata['ramp_withdrawal'] =
                    $confirmedResponse;

                $jobMetadata['ramp_withdrawal_recovered_at'] =
                    now()->toIso8601String();

                $transaction = $this->updateJobMetadata(
                    $transaction,
                    $jobMetadata
                );

                $this->markAwaitingPayout(
                    $transaction,
                    $confirmedResponse,
                    $jobMetadata
                );

                return;
            }

            /*
             * Unknown provider state.
             *
             * NEVER reverse.
             */
            if ($confirmedRampWithdrawal['state'] === 'unknown') {

                if ($this->attempts() < $this->tries) {

                    $delay = $this->retryDelaySeconds();

                    $this->rampLog()->critical(
                        'ProcessRampSell could not verify Master -> Ramp withdrawal after provider failure. Retrying WITHOUT reversal.',
                        [
                            'merchant_reference' => $this->merchantReference,
                            'reference'          => $rampWithdrawalReference,
                            'attempt'            => $this->attempts(),
                            'retry_after'        => $delay,
                        ]
                    );

                    $this->release($delay);

                    return;
                }

                $jobMetadata['manual_reconciliation_required'] = true;

                $jobMetadata['manual_reconciliation_reason'] =
                    'Master -> Ramp withdrawal failed/ambiguous and provider state could not be verified.';

                $jobMetadata['manual_reconciliation_at'] =
                    now()->toIso8601String();

                $this->updateJobMetadata(
                    $transaction,
                    $jobMetadata
                );

                $this->rampLog()->critical(
                    'ProcessRampSell exhausted retries. Master -> Ramp state is UNKNOWN. NO REVERSAL PERFORMED.',
                    [
                        'merchant_reference' => $this->merchantReference,
                        'reference'          => $rampWithdrawalReference,
                        'provider_response'  => $rampResponse,
                    ]
                );

                return;
            }

            /*
             * -----------------------------------------------------------
             * CONFIRMED NOT FOUND + DEFINITIVE PROVIDER FAILURE
             * -----------------------------------------------------------
             *
             * Only here is automatic reversal allowed.
             */
            if (
                $confirmedRampWithdrawal['state'] === 'not_found'
                && $this->isDefinitiveProviderFailure($rampResponse)
            ) {

                $this->rampLog()->warning(
                    'ProcessRampSell confirmed Master -> Ramp withdrawal does not exist. Starting automatic reversal.',
                    [
                        'merchant_reference' => $this->merchantReference,
                        'reference'          => $rampWithdrawalReference,
                        'provider_response'  => $rampResponse,
                    ]
                );

                $reversalResponse = $this->reverseWithdrawal(
                    $quidaxService,
                    'Ramp destination withdrawal failed.'
                );

                $jobMetadata['automatic_reversal'] =
                    $reversalResponse;

                $transaction = $this->updateJobMetadata(
                    $transaction,
                    $jobMetadata
                );

                /*
                 * Reversal NOT confirmed.
                 *
                 * NEVER mark failed.
                 */
                if (!$this->isSuccessfulWithdrawalResponse($reversalResponse)) {

                    $jobMetadata['manual_reconciliation_required'] = true;

                    $jobMetadata['manual_reconciliation_reason'] =
                        'Ramp withdrawal failed but automatic reversal could not be confirmed.';

                    $jobMetadata['manual_reconciliation_at'] =
                        now()->toIso8601String();

                    $this->updateJobMetadata(
                        $transaction,
                        $jobMetadata
                    );

                    $this->rampLog()->critical(
                        'ProcessRampSell reversal could not be confirmed. Transaction NOT marked failed.',
                        [
                            'merchant_reference' => $this->merchantReference,
                            'reversal_response'  => $reversalResponse,
                        ]
                    );

                    return;
                }

                /*
                 * Reversal confirmed.
                 *
                 * Now it is safe to fail.
                 */
                $this->failTransaction(
                    'Master to Ramp withdrawal failed: '
                    . ($rampResponse['message'] ?? 'Unknown error'),
                    $jobMetadata
                );

                return;
            }

            /*
             * Any other state is unsafe.
             */
            $this->rampLog()->critical(
                'ProcessRampSell reached an unsafe withdrawal state. NO REVERSAL PERFORMED.',
                [
                    'merchant_reference' => $this->merchantReference,
                    'reference'          => $rampWithdrawalReference,
                    'provider_response'  => $rampResponse,
                    'reconciliation'     => $confirmedRampWithdrawal,
                ]
            );

            return;
        }

        /*
         * ---------------------------------------------------------------
         * SUCCESS / ACCEPTED BY QUIDAX
         * ---------------------------------------------------------------
         */
        $this->markAwaitingPayout(
            $transaction,
            $rampResponse,
            $jobMetadata
        );

        $this->rampLog()->info(
            'ProcessRampSell completed successfully.',
            [
                'merchant_reference' => $this->merchantReference,
                'status'             => 'awaiting_payout',
                'currency'           => strtolower($this->sourceCurrency),
                'network'            => strtolower($this->network),
                'amount'             => $this->sourceAmount,
                'ramp_address'       => $this->rampAddress,
                'provider_response'  => $rampResponse,
            ]
        );

    } catch (Exception $e) {

        $this->rampLog()->error(
            'ProcessRampSell exception.',
            [
                'merchant_reference' => $this->merchantReference,
                'attempt'            => $this->attempts(),
                'message'            => $e->getMessage(),
                'trace'              => $e->getTraceAsString(),
            ]
        );

        /*
         * NEVER reverse from the generic exception handler.
         *
         * The exception could have happened after Quidax accepted
         * the withdrawal.
         *
         * Throwing allows Laravel's queue system to retry the job,
         * after which the same idempotent references are reconciled.
         */
        if ($this->attempts() < $this->tries) {
            throw $e;
        }

        /*
         * Final attempt:
         *
         * Do not assume the financial operation failed.
         * Leave the transaction for manual reconciliation.
         */
        $transaction = RampTransaction::where(
            'merchant_reference',
            $this->merchantReference
        )
            ->where('type', 'off_ramp')
            ->first();

        if ($transaction) {

            $jobMetadata =
                $this->getJobMetadata($transaction);

            $jobMetadata['manual_reconciliation_required'] = true;

            $jobMetadata['manual_reconciliation_reason'] =
                'ProcessRampSell exhausted retries after an exception.';

            $jobMetadata['manual_reconciliation_at'] =
                now()->toIso8601String();

            $this->updateJobMetadata(
                $transaction,
                $jobMetadata
            );
        }

        /*
         * IMPORTANT:
         *
         * Do NOT reverse here.
         */
    }
}




    protected function getJobMetadata(RampTransaction $transaction): array
    {
        $metadata    = (array) ($transaction->metadata ?? []);
        $jobMetadata = $metadata['job'] ?? [];

        return is_array($jobMetadata) ? $jobMetadata : [];
    }

    protected function updateJobMetadata(RampTransaction $transaction, array $jobMetadata): RampTransaction
    {
        $metadata         = (array) ($transaction->metadata ?? []);
        $metadata['job']  = $jobMetadata;
        $transaction->metadata = $metadata;
        $transaction->save();

        return $transaction->fresh();
    }

    protected function isSuccessfulWithdrawalResponse($response): bool
    {
        if (!is_array($response)) {
            return false;
        }

        $status = strtolower(trim((string) ($response['status'] ?? '')));

        return in_array($status, ['success', 'ok'], true);
    }

    protected function isInsufficientBalanceResponse(array $response): bool
    {
        $message     = strtolower(trim((string) ($response['message'] ?? '')));
        $dataMessage = strtolower(trim((string) ($response['data']['message'] ?? '')));

        return str_contains($message, 'insufficient balance')
            || str_contains($dataMessage, 'insufficient balance')
            || str_contains($message, 'insufficient funds')
            || str_contains($dataMessage, 'insufficient funds');
    }

    protected function retryDelaySeconds(): int
    {
        return match (true) {
            $this->attempts() >= 8 => 300,
            $this->attempts() >= 5 => 120,
            default                => 30,
        };
    }

    protected function shouldBlockUntilBalanceSettles(): bool
    {
        return config('queue.default') === 'sync';
    }

    protected function waitForMainWalletBalance(
        QuidaxService $quidaxService,
        string $requiredRampBalance,
        array $lastResponse = []
    ): array {
        $deadline       = microtime(true) + $this->syncBalanceWaitSeconds;
        $walletResponse = $lastResponse;

        $this->rampLog()->info('ProcessRampSell starting synchronous master balance polling.', [
            'merchant_reference'     => $this->merchantReference,
            'required_balance'       => $this->formatDecimalAmount($requiredRampBalance),
            'poll_interval_seconds'  => $this->syncBalancePollIntervalSeconds,
            'timeout_seconds'        => $this->syncBalanceWaitSeconds,
        ]);

        while (microtime(true) < $deadline) {
            sleep($this->syncBalancePollIntervalSeconds);

            $walletResponse = $quidaxService->fetchUserWallet(
                'me',
                strtolower($this->sourceCurrency)
            );

            $availableMainBalance = $this->decimalAmount(
                $walletResponse['data']['balance'] ?? '0'
            );

            $this->rampLog()->info('ProcessRampSell sync balance poll.', [
                'merchant_reference' => $this->merchantReference,
                'required_balance'   => $this->formatDecimalAmount($requiredRampBalance),
                'available_balance'  => $this->formatDecimalAmount($availableMainBalance),
                'response'           => $walletResponse,
            ]);

            if (
                ($walletResponse['status'] ?? '') === 'success'
                && $this->compareDecimalAmounts($availableMainBalance, $requiredRampBalance) >= 0
            ) {
                $this->rampLog()->info('ProcessRampSell master balance is now sufficient.', [
                    'merchant_reference' => $this->merchantReference,
                    'required_balance'   => $this->formatDecimalAmount($requiredRampBalance),
                    'available_balance'  => $this->formatDecimalAmount($availableMainBalance),
                ]);

                return $walletResponse;
            }
        }

        $this->rampLog()->warning('ProcessRampSell synchronous master balance polling timed out.', [
            'merchant_reference' => $this->merchantReference,
            'required_balance'   => $this->formatDecimalAmount($requiredRampBalance),
            'available_balance'  => $this->formatDecimalAmount($walletResponse['data']['balance'] ?? '0'),
        ]);

        return $walletResponse;
    }

    protected function decimalAmount($value): string
    {
        if ($value === null || $value === '') {
            return '0';
        }

        if (is_float($value)) {
            $value = sprintf('%.18F', $value);
        }

        $value = trim((string) $value);

        if (!preg_match('/^-?\d+(\.\d+)?$/', $value)) {
            return '0';
        }

        return bcadd($value, '0', 18);
    }

    protected function compareDecimalAmounts($left, $right): int
    {
        return bccomp(
            $this->decimalAmount($left),
            $this->decimalAmount($right),
            8
        );
    }

    protected function formatDecimalAmount($value): string
    {
        $formatted = bcadd($this->decimalAmount($value), '0', 8);
        $formatted = rtrim(rtrim($formatted, '0'), '.');

        return ($formatted === '' || $formatted === '-0') ? '0' : $formatted;
    }

    /**
     * Reverse the user -> master withdrawal if the ramp payout cannot proceed.
     */
    private function reverseWithdrawal(QuidaxService $quidaxService, string $reason): array
    {
        $this->rampLog()->warning('ProcessRampSell starting automatic reversal.', [
            'merchant_reference' => $this->merchantReference,
            'reason'             => $reason,
            'currency'           => strtolower($this->sourceCurrency),
            'network'            => strtolower($this->mainAccountData['network'] ?? $this->network),
            'amount'             => $this->mainAccountData['amount'] ?? $this->sourceAmount,
            'destination'        => $this->user->quidax_id,
        ]);

        try {
            $response = $quidaxService->create_withdrawal(
                'me',
                [
                    'currency'         => strtolower($this->sourceCurrency),
                    'network'          => strtolower($this->mainAccountData['network'] ?? $this->network),
                    'amount'           => $this->mainAccountData['amount'] ?? $this->sourceAmount,
                    'fund_uid'         => $this->user->quidax_id,
                    'transaction_note' => "Ramp reversal: {$reason}",
                    'narration'        => "Ramp reversal: {$reason}",
                ],
                $this->merchantReference . '_reversal'   // required unique reference
            );

            $this->rampLog()->info('ProcessRampSell reversal response.', [
                'merchant_reference' => $this->merchantReference,
                'reason'             => $reason,
                'response'           => $response,
            ]);

            return is_array($response) ? $response : [];
        } catch (Exception $e) {
            $this->rampLog()->error('ProcessRampSell reversal failed.', [
                'merchant_reference' => $this->merchantReference,
                'reason'             => $reason,
                'message'            => $e->getMessage(),
                'trace'              => $e->getTraceAsString(),
            ]);

            return [
                'status'  => 'error',
                'message' => $e->getMessage(),
            ];
        }
    }

    private function failTransaction(string $reason, array $jobMetadata = []): void
    {

    $transaction = RampTransaction::where(
    'merchant_reference',
    $this->merchantReference
) ->first();
    
        if (!$transaction) {
            $this->rampLog()->warning('ProcessRampSell failed transaction not found.', [
                'merchant_reference' => $this->merchantReference,
                'reason'             => $reason,
            ]);
            return;
        }

        if (in_array($transaction->status, ['awaiting_payout', 'completed', 'failed'], true)) {
            $this->rampLog()->info('ProcessRampSell failure ignored because transaction is already finalized.', [
                'merchant_reference' => $this->merchantReference,
                'status'             => $transaction->status,
                'reason'             => $reason,
            ]);
            return;
        }

        $existingJobMetadata = $this->getJobMetadata($transaction);
        $metadata            = (array) ($transaction->metadata ?? []);

        $transaction->status = 'failed';

        $transaction->metadata = array_merge($metadata, [
            'failure_reason'             => $reason,
            'job'                        => array_merge($existingJobMetadata, $jobMetadata),
            'reservation_released_at'    => $metadata['reservation_released_at'] ?? now()->toDateTimeString(),
            'reservation_release_reason' => $metadata['reservation_release_reason'] ?? 'failed',
        ]);

        if ($this->hasRampTransactionColumn('provider_status')) {
            $transaction->provider_status = 'failed';
        }

        if ($this->hasRampTransactionColumn('provider_status_checked_at')) {
            $transaction->provider_status_checked_at = now();
        }

        $transaction->save();

        $this->rampLog()->warning('ProcessRampSell transaction marked as failed.', [
            'merchant_reference' => $this->merchantReference,
            'transaction_id'     => $transaction->id,
            'status'             => $transaction->status,
            'reason'             => $reason,
        ]);
    }

    private function providerTrackingAttributesFromWithdrawalResponse(array $response, string $status): array
    {
        $data       = is_array($response['data'] ?? null) ? $response['data'] : [];
        $attributes = [];

        if ($this->hasRampTransactionColumn('provider_transaction_id')) {
            $providerTransactionId = $data['id']
                ?? $data['uuid']
                ?? $data['reference']
                ?? $response['reference']
                ?? null;

            if ($providerTransactionId !== null && $providerTransactionId !== '') {
                $attributes['provider_transaction_id'] = (string) $providerTransactionId;
            }
        }

        if ($this->hasRampTransactionColumn('transaction_hash')) {
            $transactionHash = $data['txid']
                ?? $data['tx_id']
                ?? $data['transaction_hash']
                ?? $data['hash']
                ?? null;

            if ($transactionHash !== null && $transactionHash !== '') {
                $attributes['transaction_hash'] = (string) $transactionHash;
            }
        }

        if ($this->hasRampTransactionColumn('provider_status')) {
            $attributes['provider_status'] = strtolower(
                (string) ($data['status'] ?? $response['status'] ?? $status)
            );
        }

        if ($this->hasRampTransactionColumn('provider_status_checked_at')) {
            $attributes['provider_status_checked_at'] = now();
        }

        return $attributes;
    }

    private function hasRampTransactionColumn(string $column): bool
    {
        static $columns = null;

        if ($columns === null) {
            $columns = Schema::hasTable('ramp_transactions')
                ? Schema::getColumnListing('ramp_transactions')
                : [];
        }

        return in_array($column, $columns, true);
    }
    protected function markAwaitingPayout(
    RampTransaction $transaction,
    array $rampResponse,
    array $jobMetadata
): void {
    $metadata = (array) $transaction->metadata;

    $jobMetadata['ramp_withdrawal'] = $rampResponse;
    $jobMetadata['ramp_withdrawal_requested_at'] =
        $jobMetadata['ramp_withdrawal_requested_at']
        ?? now()->toIso8601String();

    $transaction->status = 'awaiting_payout';

    $transaction->metadata = array_merge($metadata, [
        'job' => $jobMetadata,
        'reservation_released_at' =>
            $metadata['reservation_released_at']
            ?? now()->toDateTimeString(),
        'reservation_release_reason' =>
            $metadata['reservation_release_reason']
            ?? 'awaiting_payout',
    ]);

    $transaction->fill(
        $this->providerTrackingAttributesFromWithdrawalResponse(
            $rampResponse,
            'awaiting_payout'
        )
    );

    $transaction->save();
}
protected function findExistingRampWithdrawal(
    QuidaxService $quidaxService
): ?array {
    $reference = trim(
        $this->merchantReference . '_ramp'
    );

    if ($reference === '') {
        return null;
    }

    $this->rampLog()->info(
        'ProcessRampSell checking Quidax for existing Master -> Ramp withdrawal.',
        [
            'merchant_reference' => $this->merchantReference,
            'reference'          => $reference,
            'currency'            => strtolower($this->sourceCurrency),
        ]
    );

    try {

        $response = $quidaxService->findWithdrawalByReference(
            'me',
            $reference,
            $this->sourceCurrency
        );

        if (!$response) {

            $this->rampLog()->info(
                'ProcessRampSell no existing Quidax ramp withdrawal found.',
                [
                    'merchant_reference' => $this->merchantReference,
                    'reference'          => $reference,
                ]
            );

            return null;
        }

        $this->rampLog()->info(
            'ProcessRampSell recovered existing Quidax ramp withdrawal.',
            [
                'merchant_reference' => $this->merchantReference,
                'reference'          => $reference,
                'response'            => $response,
            ]
        );

        return $response;

    } catch (Exception $e) {

        /*
         * Critical:
         * An API failure is NOT the same as "withdrawal not found".
         *
         * Let the job retry rather than creating another withdrawal.
         */
        $this->rampLog()->error(
            'ProcessRampSell could not verify existing Quidax ramp withdrawal.',
            [
                'merchant_reference' => $this->merchantReference,
                'reference'          => $reference,
                'message'            => $e->getMessage(),
            ]
        );

        throw $e;
    }
}
protected function reconcileRampWithdrawal(
    QuidaxService $quidaxService
): array {
    $reference = trim(
        $this->merchantReference . '_ramp'
    );

    if ($reference === '') {
        return [
            'state' => 'unknown',
            'data'  => null,
        ];
    }

    $this->rampLog()->info(
        'ProcessRampSell reconciling Master -> Ramp withdrawal.',
        [
            'merchant_reference' => $this->merchantReference,
            'reference'          => $reference,
            'currency'           => strtolower($this->sourceCurrency),
        ]
    );

    try {
        $response = $quidaxService->findWithdrawalByReference(
            'me',
            $reference,
            $this->sourceCurrency
        );

        if (is_array($response) && !empty($response)) {
            $this->rampLog()->info(
                'ProcessRampSell Master -> Ramp withdrawal found.',
                [
                    'merchant_reference' => $this->merchantReference,
                    'reference'          => $reference,
                    'response'           => $response,
                ]
            );

            return [
                'state' => 'found',
                'data'  => $response,
            ];
        }

        /*
         * IMPORTANT:
         *
         * A genuine successful API lookup returning no transaction is
         * different from an API exception.
         *
         * The service must guarantee that null/empty here really means
         * "confirmed not found".
         */
        $this->rampLog()->info(
            'ProcessRampSell confirmed no Master -> Ramp withdrawal exists.',
            [
                'merchant_reference' => $this->merchantReference,
                'reference'          => $reference,
            ]
        );

        return [
            'state' => 'not_found',
            'data'  => null,
        ];

    } catch (Exception $e) {

        /*
         * NEVER convert an API error into "not found".
         */
        $this->rampLog()->error(
            'ProcessRampSell could not verify Master -> Ramp withdrawal.',
            [
                'merchant_reference' => $this->merchantReference,
                'reference'          => $reference,
                'message'            => $e->getMessage(),
            ]
        );

        return [
            'state' => 'unknown',
            'data'  => null,
        ];
    }
}
protected function isDefinitiveProviderFailure($response): bool
{
    if (!is_array($response)) {
        return false;
    }

    $status = strtolower(
        trim((string) ($response['status'] ?? ''))
    );

    $message = strtolower(
        trim((string) ($response['message'] ?? ''))
    );

    $dataMessage = strtolower(
        trim((string) ($response['data']['message'] ?? ''))
    );

    /*
     * Explicit provider failure statuses.
     */
    if (in_array($status, [
        'failed',
        'failure',
        'error',
        'rejected',
        'cancelled',
    ], true)) {
        return true;
    }

    /*
     * Explicit failure messages.
     */
    foreach ([
        $message,
        $dataMessage,
    ] as $text) {
        if (
            str_contains($text, 'invalid address')
            || str_contains($text, 'invalid currency')
            || str_contains($text, 'invalid network')
            || str_contains($text, 'withdrawal rejected')
            || str_contains($text, 'withdrawal failed')
            || str_contains($text, 'invalid fund')
            || str_contains($text, 'unsupported network')
        ) {
            return true;
        }
    }

    return false;
}

}