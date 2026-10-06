<?php

namespace App\Http\Controllers\Api\Savings;

use App\Http\Controllers\Controller;
use App\Http\Helpers\Response;
use App\Models\FlexSavings;
use App\Models\OrderTransaction;
use App\Models\SavingsTransaction;
use App\Models\UserWallet;
use App\Notifications\User\SavingsNotification;
use App\Services\Savings\Contracts\SavingsFundingProviderInterface;
use App\Services\SavingsAutosaveSetupService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Validator;
use RuntimeException;
use Throwable;

class FlexController extends Controller
{
    /**
     * Get Flex Savings details.
     */
    public function index()
    {
        $user = auth()->user();

        $flex = FlexSavings::firstOrCreate([
            'user_id' => $user->id,
        ]);

        return Response::success([
            'flex_savings' => $flex,
        ]);
    }

    /**
     * Deposit into Flex Savings.
     *
     * Flow:
     *
     * User SafeHaven Sub-Account
     *          ↓
     * Platform SafeHaven Main Account
     *          ↓
     * Flex Savings internal ledger
     *
     * The actual SafeHaven movement is handled by
     * SavingsFundingProviderInterface.
     */
    public function deposit(
        Request $request,
        SavingsAutosaveSetupService $autosaveSetup,
        SavingsFundingProviderInterface $fundingProvider
    ) {
        if ($request->has('autosave') && is_array($request->input('autosave'))) {
            $request->merge([
                'autosave' => $autosaveSetup->normalizeConfig($request->input('autosave')),
            ]);
        }

        $validator = Validator::make(
            $request->all(),
            array_merge(
                [
                    'amount' => 'required|numeric|min:100',
                ],
                $autosaveSetup->rules()
            )
        );

        if ($validator->fails()) {
            return Response::error(
                $validator->errors()->all()
            );
        }

        $user = auth()->user();

        /*
         * Keep the original string amount for financial operations
         * instead of converting to float unnecessarily.
         */
        $amount = (string) $request->input('amount');

        /*
         * Make sure Flex Savings exists.
         */
        $flex = FlexSavings::firstOrCreate([
            'user_id' => $user->id,
        ]);

        /*
         * Validate autosave configuration semantics BEFORE
         * initiating the financial transaction.
         */
        if ($request->filled('autosave')) {
            try {
                $autosaveSetup->validateConfig($request->input('autosave'));
            } catch (\InvalidArgumentException $e) {
                return Response::error([
                    $e->getMessage(),
                ]);
            }
        }

        /*
         * Generate a unique reference BEFORE calling the provider.
         *
         * This same reference should be used throughout the
         * provider transaction and internal ledger.
         */
        $reference = 'flex-deposit:' . $user->id . ':' . uniqid();

        /*
         * IMPORTANT:
         *
         * The SafeHaven provider performs the actual movement:
         *
         * User SafeHaven Sub-Account
         *              ↓
         * Platform SafeHaven Main Account
         *
         * Do NOT debit UserWallet here if the money being moved
         * is coming from the user's SafeHaven account.
         */
        try {
            $providerResult = $fundingProvider->deposit(
                $user,
                $amount,
                $reference
            );

            /*
             * If the provider returned an explicit failure,
             * do not update the internal savings balance.
             */
            if (
                isset($providerResult['success']) &&
                $providerResult['success'] === false
            ) {
                return Response::error([
                    $providerResult['message']
                        ?? 'Unable to process savings deposit.',
                ]);
            }
        } catch (RuntimeException $e) {
            return Response::error([
                $e->getMessage(),
            ]);
        } catch (\Exception $e) {
            report($e);

            return Response::error([
                $e->getMessage() ?: 'Unable to process Flex Savings deposit.',
            ]);
        } catch (Throwable $e) {
            report($e);

            return Response::error([
                'Unable to process Flex Savings deposit.',
            ]);
        }

        /*
         * Only update the internal Flex Savings balance after
         * SafeHaven has accepted/successfully completed the transfer.
         *
         * Lock the Flex Savings row to prevent concurrent deposits
         * from overwriting the balance.
         */
        $autosavePlan = null;

        try {
            DB::transaction(function () use (
                $user,
                $amount,
                $flex,
                $reference,
                $request,
                $autosaveSetup,
                &$autosavePlan
            ) {
                $flex = FlexSavings::where('id', $flex->id)
                    ->where('user_id', $user->id)
                    ->lockForUpdate()
                    ->first();

                if (!$flex) {
                    throw new RuntimeException(
                        'Flex Savings account not found.'
                    );
                }

                /*
                 * Credit Flex Savings.
                 */
                $flex->balance =
                    (float) $flex->balance + (float) $amount;

                /*
                 * Create AutoSave plan atomically with deposit if configured.
                 */
                if ($request->filled('autosave')) {
                    $autosavePlan = $autosaveSetup->createForTarget(
                        $user,
                        $flex,
                        'flex_savings',
                        $request->input('autosave'),
                        [
                            'name' => 'Flex Savings AutoSave',
                            'title' => 'Flex Savings',
                        ]
                    );

                    if ($autosavePlan) {
                        $flex->auto_save = true;
                    }
                }

                $flex->save();

                /*
                 * Synchronize UserWallet balance if user funded from internal wallet.
                 */
                $wallet = UserWallet::where('user_id', $user->id)
                    ->where('currency_code', 'NGN')
                    ->lockForUpdate()
                    ->first();

                if ($wallet && bccomp((string) $wallet->balance, (string) $amount, 8) >= 0) {
                    $wallet->balance = bcsub((string) $wallet->balance, (string) $amount, 8);
                    $wallet->save();
                }

                /*
                 * Log savings transaction.
                 */
                SavingsTransaction::create([
                    'user_id' => $user->id,
                    'savingsable_id' => $flex->id,
                    'savingsable_type' => FlexSavings::class,
                    'amount' => $amount,
                    'balance_after' => $flex->balance,
                    'type' => 'deposit',
                    'status' => 'success',
                    'source' => 'safehaven',
                    'narration' => 'Flex Savings Deposit',
                    'reference' => $reference,
                ]);
            });
        } catch (Throwable $e) {
            /*
             * This is important:
             *
             * SafeHaven may already have moved the money while the
             * internal database transaction failed.
             *
             * Therefore this situation should be investigated/reconciled
             * rather than simply pretending the provider transaction
             * never happened.
             */
            report($e);

            return Response::error([
                'The SafeHaven transfer succeeded, but the savings '
                . 'account could not be updated. Please contact support.',
            ]);
        }

        /*
         * Refresh latest Flex Savings balance.
         */
        $flex->refresh();

        /*
         * Notify after successful transaction.
         */
        try {
            $user->notify(
                new SavingsNotification(
                    'Flex Savings',
                    'Topup',
                    (float) $amount
                )
            );
        } catch (Throwable $e) {
            /*
             * Notification failure must never invalidate
             * a successful financial transaction.
             */
            report($e);
        }

        return Response::success([
            'message' => 'Deposit successful',
            'balance' => $flex->balance,
            'reference' => $reference,
            'provider_response' => $providerResult,
            'autosave_plan' => $autosavePlan,
        ]);
    }

    /**
     * Withdraw from Flex Savings.
     *
     * Flow:
     *
     * Platform SafeHaven Main Account
     *          ↓
     * User SafeHaven Sub-Account
     *          ↓
     * Flex Savings internal ledger
     */
    public function withdraw(
        Request $request,
        SavingsFundingProviderInterface $fundingProvider
    ) {
        $validator = Validator::make(
            $request->all(),
            [
                'amount' => 'required|numeric|min:100',
            ]
        );

        if ($validator->fails()) {
            return Response::error(
                $validator->errors()->all()
            );
        }

        $user = auth()->user();

        /*
         * Keep amount as a string for the provider.
         */
        $amount = (string) $request->input('amount');

        /*
         * Generate one reference for the entire operation.
         */
        $reference = 'flex-withdrawal:' . $user->id . ':' . uniqid();

        /*
         * First lock and validate the internal savings balance.
         *
         * We do NOT permanently deduct it yet because the provider
         * transfer must succeed first.
         */
        try {
            $withdrawalData = DB::transaction(
                function () use ($user, $amount) {

                    $flex = FlexSavings::where(
                            'user_id',
                            $user->id
                        )
                        ->lockForUpdate()
                        ->first();

                    if (!$flex) {
                        throw new RuntimeException(
                            'Flex Savings account not found.'
                        );
                    }

                    /*
                     * Check savings balance.
                     */
                    if (
                        (float) $flex->balance
                        < (float) $amount
                    ) {
                        throw new RuntimeException(
                            'Insufficient Flex Savings balance.'
                        );
                    }

                    /*
                     * Count successful withdrawals in the
                     * current quarter.
                     */
                    $quarterStart = now()->startOfQuarter();

                    $withdrawalCount =
                        SavingsTransaction::where(
                            'user_id',
                            $user->id
                        )
                        ->where(
                            'savingsable_id',
                            $flex->id
                        )
                        ->where(
                            'savingsable_type',
                            FlexSavings::class
                        )
                        ->where(
                            'type',
                            'withdrawal'
                        )
                        ->where(
                            'status',
                            'success'
                        )
                        ->where(
                            'created_at',
                            '>=',
                            $quarterStart
                        )
                        ->count();

                    /*
                     * Four withdrawals per quarter are free.
                     * Additional withdrawals incur a 100 NGN fee.
                     */
                    $penaltyFee = 0;

                    if ($withdrawalCount >= 4) {
                        $penaltyFee = 100;

                        if (
                            (float) $flex->balance
                            < (
                                (float) $amount
                                + $penaltyFee
                            )
                        ) {
                            throw new RuntimeException(
                                'Withdrawal limit reached. '
                                . 'Additional withdrawals cost 100 NGN. '
                                . 'Insufficient balance for fee.'
                            );
                        }
                    }

                    return [
                        'flex_id' => $flex->id,
                        'balance' => (float) $flex->balance,
                        'penalty_fee' => $penaltyFee,
                    ];
                }
            );
        } catch (RuntimeException $e) {
            return Response::error([
                $e->getMessage(),
            ]);
        } catch (Throwable $e) {
            report($e);

            return Response::error([
                'Unable to validate Flex Savings withdrawal.',
            ]);
        }

        $penaltyFee = $withdrawalData['penalty_fee'];

        /*
         * The actual SafeHaven transfer:
         *
         * Platform SafeHaven Main Account
         *              ↓
         * User SafeHaven Sub-Account
         *
         * The provider handles the SafeHaven details.
         */
        try {
            $providerResult = $fundingProvider->withdraw(
                $user,
                $amount,
                $reference
            );

            /*
             * Do not modify the savings balance if the provider
             * explicitly reports failure.
             */
            if (
                isset($providerResult['success']) &&
                $providerResult['success'] === false
            ) {
                return Response::error([
                    $providerResult['message']
                        ?? 'Unable to process savings withdrawal.',
                ]);
            }
        } catch (RuntimeException $e) {
            return Response::error([
                $e->getMessage(),
            ]);
        } catch (Throwable $e) {
            report($e);

            return Response::error([
                'Unable to process Flex Savings withdrawal.',
            ]);
        }

        /*
         * Provider succeeded.
         *
         * Now update our internal savings ledger.
         */
        try {
            DB::transaction(function () use (
                $user,
                $amount,
                $reference,
                $penaltyFee
            ) {
                $flex = FlexSavings::where(
                        'user_id',
                        $user->id
                    )
                    ->lockForUpdate()
                    ->first();

                if (!$flex) {
                    throw new RuntimeException(
                        'Flex Savings account not found.'
                    );
                }

                /*
                 * Re-check balance because another request could have
                 * changed the balance while the SafeHaven request
                 * was being processed.
                 */
                $totalDeduction =
                    (float) $amount
                    + (float) $penaltyFee;

                if (
                    (float) $flex->balance
                    < $totalDeduction
                ) {
                    throw new RuntimeException(
                        'Flex Savings balance changed during withdrawal.'
                    );
                }

                /*
                 * Deduct requested amount + applicable fee.
                 */
                $flex->balance =
                    (float) $flex->balance
                    - $totalDeduction;

                $flex->save();

                /*
                 * Credit user's NGN wallet with the withdrawn amount.
                 */
                $wallet = UserWallet::where('user_id', $user->id)
                    ->where('currency_code', 'NGN')
                    ->lockForUpdate()
                    ->first();

                if ($wallet) {
                    $wallet->balance = bcadd((string) $wallet->balance, (string) $amount, 8);
                    $wallet->save();
                }

                /*
                 * Log savings withdrawal.
                 */
                SavingsTransaction::create([
                    'user_id' => $user->id,
                    'savingsable_id' => $flex->id,
                    'savingsable_type' => FlexSavings::class,
                    'amount' => $amount,
                    'balance_after' => $flex->balance,
                    'type' => 'withdrawal',
                    'status' => 'success',
                    'source' => 'safehaven',
                    'narration' =>
                        'Flex Savings Withdrawal'
                        . (
                            $penaltyFee > 0
                                ? ' - Limit Fee Applied: '
                                    . $penaltyFee
                                : ''
                        ),
                    'reference' => $reference,
                ]);
            });
        } catch (Throwable $e) {
            /*
             * The external SafeHaven transfer already succeeded.
             *
             * This requires reconciliation rather than pretending
             * the transaction failed.
             */
            report($e);

            return Response::error([
                'The SafeHaven withdrawal succeeded, but the '
                . 'Flex Savings ledger could not be updated. '
                . 'Please contact support.',
            ]);
        }

        /*
         * Refresh latest balance.
         */
        $flex = FlexSavings::where(
            'user_id',
            $user->id
        )->first();

        /*
         * Notify user.
         */
        try {
            $user->notify(
                new SavingsNotification(
                    'Flex Savings',
                    'Withdrawn',
                    (float) $amount
                )
            );
        } catch (Throwable $e) {
            /*
             * Notification failure must never affect
             * the financial transaction.
             */
            report($e);
        }

        return Response::success([
            'message' =>
                'Withdrawal successful'
                . (
                    $penaltyFee > 0
                        ? " (Fee of {$penaltyFee} applied)"
                        : ''
                ),
            'balance' => $flex?->balance,
            'reference' => $reference,
            'provider_response' => $providerResult,
            'fee' => $penaltyFee,
        ]);
    }

    /**
     * Get Transaction History.
     */
    public function history()
    {
        $user = auth()->user();

        $flex = FlexSavings::firstOrCreate([
            'user_id' => $user->id,
        ]);

        $transactions = SavingsTransaction::where(
                'savingsable_id',
                $flex->id
            )
            ->where(
                'savingsable_type',
                FlexSavings::class
            )
            ->latest()
            ->get();

        return Response::success([
            'transactions' => $transactions,
        ]);
    }
}
