<?php

namespace App\Http\Controllers\Api\Savings;

use App\Http\Controllers\Controller;
use App\Http\Helpers\Response;
use App\Models\SafeLock;
use App\Models\UserWallet;
use App\Models\SavingsTransaction;
use App\Models\SavingsPlan;
use App\Models\OrderTransaction;
use App\Services\SavingsAutosaveSetupService;
use App\Services\Savings\Contracts\SavingsFundingProviderInterface;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Validator;
use Illuminate\Support\Str;
use App\Notifications\User\SavingsNotification;
use RuntimeException;

class SafeLockController extends Controller
{
    public function index()
    {
        $userId = auth()->id();

        $locks = SafeLock::where('user_id', $userId)->get();

        Log::info('SafeLock list retrieved', [
            'user_id' => $userId,
            'count' => $locks->count(),
        ]);

        return Response::success([
            'safe_locks' => $locks,
        ]);
    }

    public function plans()
    {
        $plans = SavingsPlan::where('status', true)->get();

        Log::info('Savings plans retrieved', [
            'count' => $plans->count(),
        ]);

        return Response::success([
            'plans' => $plans,
        ]);
    }

    public function create(
        Request $request,
        SavingsAutosaveSetupService $autosaveSetup,
        SavingsFundingProviderInterface $fundingProvider
    ) {
        $validator = Validator::make(
            $request->all(),
            array_merge([
                'amount' => 'required|numeric',
                'plan_id' => 'required|exists:savings_plans,id',
                'title' => 'nullable|string',
            ], $autosaveSetup->rules())
        );

        if ($validator->fails()) {
            Log::warning('SafeLock creation validation failed', [
                'user_id' => auth()->id(),
                'errors' => $validator->errors()->toArray(),
            ]);

            return Response::error(
                $validator->errors()->all()
            );
        }

        $user = auth()->user();

        try {
            $result = DB::transaction(function () use (
                $request,
                $user,
                $autosaveSetup,
                $fundingProvider
            ) {
                $plan = SavingsPlan::where('id', $request->plan_id)
                    ->lockForUpdate()
                    ->first();

                if (!$plan) {
                    throw new RuntimeException(
                        'Savings plan not found.'
                    );
                }

                if ($request->amount < $plan->min_amount) {
                    Log::warning('SafeLock amount below minimum', [
                        'user_id' => $user->id,
                        'amount' => $request->amount,
                        'plan_id' => $plan->id,
                    ]);

                    throw new RuntimeException(
                        'Amount is less than minimum for this plan.'
                    );
                }

                if (
                    $plan->max_amount &&
                    $request->amount > $plan->max_amount
                ) {
                    Log::warning('SafeLock amount exceeds maximum', [
                        'user_id' => $user->id,
                        'amount' => $request->amount,
                        'plan_id' => $plan->id,
                    ]);

                    throw new RuntimeException(
                        'Amount exceeds maximum for this plan.'
                    );
                }

                $wallet = UserWallet::where('user_id', $user->id)
                    ->where('currency_code', 'NGN')
                    ->lockForUpdate()
                    ->first();

                if (!$wallet) {
                    throw new RuntimeException(
                        'NGN Wallet not found'
                    );
                }

                if (
                    bccomp(
                        (string) $wallet->balance,
                        (string) $request->amount,
                        8
                    ) < 0
                ) {
                    Log::warning(
                        'Insufficient wallet balance for SafeLock creation',
                        [
                            'user_id' => $user->id,
                            'balance' => $wallet->balance,
                            'amount' => $request->amount,
                        ]
                    );

                    throw new RuntimeException(
                        'Insufficient wallet balance'
                    );
                }

                $lockDate = now();
                $maturityDate = now()->addDays(
                    $plan->duration_days
                );

                $interestRate = $plan->interest_rate;

                /*
                 * Generate the provider reference before the
                 * external transfer because the SafeLock does
                 * not exist yet.
                 */
                $reference = 'safelock:create:' . Str::uuid();

                /*
                 * External provider operation:
                 *
                 * User SafeHaven Sub-Account
                 *          ↓
                 * Platform SafeHaven Main Account
                 *
                 * If the provider reports Failed/Canceled,
                 * it throws and this DB transaction rolls back.
                 */
                $narration ='Safelock Deposit';
                
                try {
                    $providerResponse = $fundingProvider->deposit(
                        $user,
                        (string) $request->amount,
                        $reference,
                        $narration
                    );
                } catch (\Throwable $e) {
                    Log::error(
                        'SafeLock provider deposit failed',
                        [
                            'user_id' => $user->id,
                            'amount' => $request->amount,
                            'reference' => $reference,
                            'error' => $e->getMessage(),
                        ]
                    );

                    throw new RuntimeException(
                        'Unable to fund SafeLock at this time.',
                        0,
                        $e
                    );
                }

                /*
                 * Provider accepted the transfer.
                 *
                 * Now update local wallet and create the
                 * SafeLock records.
                 */
                $wallet->balance = bcsub(
                    (string) $wallet->balance,
                    (string) $request->amount,
                    8
                );

                $wallet->save();

                $lock = SafeLock::create([
                    'user_id' => $user->id,
                    'title' => $request->title ?? $plan->name,
                    'amount' => $request->amount,
                    'interest_rate' => $interestRate,
                    'interest_accrued' => 0,
                    'last_interest_date' => $lockDate->copy()->startOfDay(),
                    'lock_date' => $lockDate,
                    'maturity_date' => $maturityDate,
                    'status' => 'active',
                ]);

                SavingsTransaction::create([
                    'user_id' => $user->id,
                    'savingsable_id' => $lock->id,
                    'savingsable_type' => SafeLock::class,
                    'amount' => $request->amount,
                    'balance_after' => $lock->amount,
                    'type' => 'deposit',
                    'status' => 'success',
                    'source' => 'wallet',
                    'narration' => 'SafeLock Creation: ' . $lock->title,
                ]);

                OrderTransaction::create([
                    'user_wallet_id' => $wallet->id,
                    'type' => 'debit',
                    'amount' => $request->amount,
                    'balance_after' => $wallet->balance,
                    'reference' => 'safelock:' . $lock->id,
                    'metadata' => [
                        'source' => 'savings',
                        'savings_type' => 'safe_lock',
                        'savings_id' => $lock->id,
                        'title' => $lock->title,
                    ],
                ]);

                try {
                    $autosavePlan = $autosaveSetup->createForTarget(
                        $user,
                        $lock,
                        'safe_lock',
                        $request->input('autosave'),
                        [
                            'name' => $lock->title . ' AutoSave',
                            'title' => $lock->title,
                            'maturity_date' => $lock->maturity_date,
                        ]
                    );
                } catch (\InvalidArgumentException $e) {
                    Log::error(
                        'SafeLock AutoSave creation failed',
                        [
                            'user_id' => $user->id,
                            'lock_id' => $lock->id,
                            'error' => $e->getMessage(),
                        ]
                    );

                    throw $e;
                }

                return [
                    'lock' => $lock,
                    'autosave_plan' => $autosavePlan,
                    'provider_response' => $providerResponse,
                    'reference' => $reference,
                ];
            });

            $user->notify(
                new SavingsNotification(
                    'SafeLock',
                    'Created',
                    $result['lock']->amount
                )
            );

            Log::info('SafeLock created successfully', [
                'user_id' => $user->id,
                'lock_id' => $result['lock']->id,
                'amount' => $result['lock']->amount,
                'reference' => $result['reference'],
            ]);

            return Response::success([
                'message' => 'SafeLock created successfully',
                'data' => $result['lock'],
                'autosave_plan' => $result['autosave_plan'],
            ]);
        } catch (\Throwable $e) {
            Log::error('SafeLock creation failed', [
                'user_id' => $user->id,
                'plan_id' => $request->plan_id,
                'amount' => $request->amount,
                'error' => $e->getMessage(),
            ]);

            return Response::error([
                $e->getMessage(),
            ]);
        }
    }

    public function break(
        Request $request,
        SavingsFundingProviderInterface $fundingProvider
    ) {
        $validator = Validator::make($request->all(), [
            'lock_id' => 'required|exists:safe_locks,id',
        ]);

        if ($validator->fails()) {
            Log::warning('SafeLock break validation failed', [
                'user_id' => auth()->id(),
                'errors' => $validator->errors()->toArray(),
            ]);

            return Response::error(
                $validator->errors()->all()
            );
        }

        $user = auth()->user();

        try {
            $result = DB::transaction(function () use (
                $request,
                $user,
                $fundingProvider
            ) {
                $lock = SafeLock::where('user_id', $user->id)
                    ->where('id', $request->lock_id)
                    ->lockForUpdate()
                    ->first();

                if (!$lock) {
                    throw new RuntimeException(
                        'SafeLock not found'
                    );
                }

                if ($lock->status !== 'active') {
                    throw new RuntimeException(
                        'SafeLock is not active'
                    );
                }

                $wallet = UserWallet::where('user_id', $user->id)
                    ->where('currency_code', 'NGN')
                    ->lockForUpdate()
                    ->first();

                if (!$wallet) {
                    throw new RuntimeException(
                        'NGN Wallet not found'
                    );
                }

                $amountToReturn = (string) $lock->amount;
                $penalty = '0';

                if (
                    $lock->maturity_date &&
                    $lock->maturity_date->isFuture()
                ) {
                    $penalty = bcmul(
                        $amountToReturn,
                        '0.01',
                        8
                    );

                    $amountToReturn = bcsub(
                        $amountToReturn,
                        $penalty,
                        8
                    );
                }

                if (
                    bccomp(
                        $amountToReturn,
                        '0',
                        8
                    ) <= 0
                ) {
                    throw new RuntimeException(
                        'SafeLock withdrawal amount must be greater than zero.'
                    );
                }

                $reference = 'safelock:break:' .
                    $lock->id . ':' . Str::uuid();

                /*
                 * External provider operation:
                 *
                 * Platform SafeHaven Main Account
                 *          ↓
                 * User SafeHaven Sub-Account
                 */
                try {
                    $providerResponse = $fundingProvider->withdraw(
                        $user,
                        $amountToReturn,
                        $reference
                    );
                } catch (\Throwable $e) {
                    Log::error(
                        'SafeLock provider withdrawal failed',
                        [
                            'user_id' => $user->id,
                            'lock_id' => $lock->id,
                            'amount' => $amountToReturn,
                            'reference' => $reference,
                            'error' => $e->getMessage(),
                        ]
                    );

                    throw new RuntimeException(
                        'Unable to return SafeLock funds at this time.',
                        0,
                        $e
                    );
                }

                $wallet->balance = bcadd(
                    (string) $wallet->balance,
                    $amountToReturn,
                    8
                );

                $wallet->save();

                $lock->status = 'broken';
                $lock->save();

                SavingsTransaction::create([
                    'user_id' => $user->id,
                    'savingsable_id' => $lock->id,
                    'savingsable_type' => SafeLock::class,
                    'amount' => $amountToReturn,
                    'balance_after' => 0,
                    'type' => 'withdrawal',
                    'status' => 'success',
                    'source' => 'safelock',
                    'narration' => 'SafeLock Broken (Early Withdrawal)'
                        . (
                            bccomp($penalty, '0', 8) > 0
                                ? " - 1% Penalty Applied: {$penalty}"
                                : ''
                        ),
                ]);

                OrderTransaction::create([
                    'user_wallet_id' => $wallet->id,
                    'type' => 'credit',
                    'amount' => $amountToReturn,
                    'balance_after' => $wallet->balance,
                    'reference' => $reference,
                    'metadata' => [
                        'source' => 'savings',
                        'savings_type' => 'safelock',
                        'savings_id' => $lock->id,
                        'penalty' => $penalty,
                    ],
                ]);

                return [
                    'lock' => $lock,
                    'amount' => $amountToReturn,
                    'penalty' => $penalty,
                    'reference' => $reference,
                    'provider_response' => $providerResponse,
                ];
            });

            $user->notify(
                new SavingsNotification(
                    'SafeLock',
                    'Withdrawn',
                    $result['amount']
                )
            );

            Log::info('SafeLock broken successfully', [
                'user_id' => $user->id,
                'lock_id' => $result['lock']->id,
                'amount_returned' => $result['amount'],
                'penalty' => $result['penalty'],
                'reference' => $result['reference'],
            ]);

            return Response::success([
                'message' => bccomp(
                    $result['penalty'],
                    '0',
                    8
                ) > 0
                    ? "SafeLock broken successfully. A 1% penalty of {$result['penalty']} was applied."
                    : 'SafeLock broken successfully. Funds returned to wallet.',
            ]);
        } catch (\Throwable $e) {
            Log::error('SafeLock break failed', [
                'user_id' => $user->id,
                'lock_id' => $request->lock_id,
                'error' => $e->getMessage(),
            ]);

            return Response::error([
                $e->getMessage(),
            ]);
        }
    }

    public function withdraw(
        Request $request,
        SavingsFundingProviderInterface $fundingProvider
    ) {
        $validator = Validator::make($request->all(), [
            'lock_id' => 'required|exists:safe_locks,id',
        ]);

        if ($validator->fails()) {
            Log::warning(
                'SafeLock withdrawal validation failed',
                [
                    'user_id' => auth()->id(),
                    'errors' => $validator->errors()->toArray(),
                ]
            );

            return Response::error(
                $validator->errors()->all()
            );
        }

        $user = auth()->user();

        try {
            $result = DB::transaction(function () use (
                $request,
                $user,
                $fundingProvider
            ) {
                $lock = SafeLock::where('user_id', $user->id)
                    ->where('id', $request->lock_id)
                    ->lockForUpdate()
                    ->first();

                if (!$lock) {
                    throw new RuntimeException(
                        'SafeLock not found'
                    );
                }

                if ($lock->is_redeemed || in_array($lock->status, ['matured', 'completed'], true)) {
                    return [
                        'already_redeemed' => true,
                        'lock'             => $lock,
                        'principal'        => (string) $lock->amount,
                        'interest_earned'  => (string) $lock->interest_accrued,
                        'total_amount'     => bcadd((string) $lock->amount, (string) $lock->interest_accrued, 8),
                    ];
                }

                if ($lock->status !== 'active') {
                    throw new RuntimeException(
                        'SafeLock is not active'
                    );
                }

                if (
                    $lock->maturity_date &&
                    $lock->maturity_date->isFuture()
                ) {
                    Log::warning(
                        'Premature SafeLock withdrawal attempted',
                        [
                            'user_id' => $user->id,
                            'lock_id' => $lock->id,
                            'maturity_date' => $lock->maturity_date,
                        ]
                    );

                    throw new RuntimeException(
                        'SafeLock has not matured yet. Use break endpoint for early withdrawal.'
                    );
                }

                $wallet = UserWallet::where('user_id', $user->id)
                    ->where('currency_code', 'NGN')
                    ->lockForUpdate()
                    ->first();

                if (!$wallet) {
                    throw new RuntimeException(
                        'NGN Wallet not found'
                    );
                }

                $interestEarned = (string) $lock->interest_accrued;

                $totalAmount = bcadd(
                    (string) $lock->amount,
                    $interestEarned,
                    8
                );

                if (
                    bccomp(
                        $totalAmount,
                        '0',
                        8
                    ) <= 0
                ) {
                    throw new RuntimeException(
                        'SafeLock withdrawal amount must be greater than zero.'
                    );
                }

                $reference = 'safelock:maturity:' .
                    $lock->id . ':' . Str::uuid();

                /*
                 * External provider operation:
                 *
                 * Platform SafeHaven Main Account
                 *          ↓
                 * User SafeHaven Sub-Account
                 */
                $providerResponse = null;
                try {
                    $providerResponse = $fundingProvider->withdraw(
                        $user,
                        $totalAmount,
                        $reference,
                        'SafeLock Maturity Withdrawal'
                    );
                } catch (\Throwable $e) {
                    Log::warning(
                        'SafeLock maturity provider withdrawal warning (proceeding with ledger credit)',
                        [
                            'user_id' => $user->id,
                            'lock_id' => $lock->id,
                            'amount' => $totalAmount,
                            'reference' => $reference,
                            'error' => $e->getMessage(),
                        ]
                    );
                }

                $wallet->balance = bcadd(
                    (string) $wallet->balance,
                    $totalAmount,
                    8
                );

                $wallet->save();

                $lock->status = 'completed';
                $lock->is_redeemed = true;
                $lock->interest_accrued = $interestEarned;
                $lock->save();

                SavingsTransaction::create([
                    'user_id' => $user->id,
                    'savingsable_id' => $lock->id,
                    'savingsable_type' => SafeLock::class,
                    'amount' => $totalAmount,
                    'balance_after' => 0,
                    'type' => 'withdrawal',
                    'status' => 'success',
                    'source' => 'safelock',
                    'narration' => "SafeLock Matured - Principal: {$lock->amount}, Interest: {$interestEarned}",
                ]);

                OrderTransaction::create([
                    'user_wallet_id' => $wallet->id,
                    'type' => 'credit',
                    'amount' => $totalAmount,
                    'balance_after' => $wallet->balance,
                    'reference' => $reference,
                    'metadata' => [
                        'source' => 'savings',
                        'savings_type' => 'safelock',
                        'savings_id' => $lock->id,
                        'interest_earned' => $interestEarned,
                        'principal' => $lock->amount,
                    ],
                ]);

                return [
                    'already_redeemed' => false,
                    'lock' => $lock,
                    'principal' => $lock->amount,
                    'interest_earned' => $interestEarned,
                    'total_amount' => $totalAmount,
                    'reference' => $reference,
                    'provider_response' => $providerResponse,
                ];
            });

            if (!empty($result['already_redeemed'])) {
                return Response::success([
                    'message' => 'SafeLock has already matured and funds were credited to your NGN wallet.',
                    'data' => [
                        'principal' => $result['principal'],
                        'interest_earned' => $result['interest_earned'],
                        'total_amount' => $result['total_amount'],
                        'status' => $result['lock']->status,
                    ],
                ]);
            }

            $user->notify(
                new SavingsNotification(
                    'SafeLock',
                    'Withdrawn',
                    $result['total_amount']
                )
            );

            Log::info('SafeLock withdrawn successfully', [
                'user_id' => $user->id,
                'lock_id' => $result['lock']->id,
                'principal' => $result['principal'],
                'interest_earned' => $result['interest_earned'],
                'total_amount' => $result['total_amount'],
                'reference' => $result['reference'],
            ]);

            return Response::success([
                'message' => 'SafeLock withdrawn successfully',
                'data' => [
                    'principal' => $result['principal'],
                    'interest_earned' => $result['interest_earned'],
                    'total_amount' => $result['total_amount'],
                ],
            ]);
        } catch (\Throwable $e) {
            Log::error('SafeLock maturity withdrawal failed', [
                'user_id' => $user->id,
                'lock_id' => $request->lock_id,
                'error' => $e->getMessage(),
            ]);

            return Response::error([
                $e->getMessage(),
            ]);
        }
    }

    public function history()
    {
        $userId = auth()->id();

        $transactions = SavingsTransaction::where(
            'user_id',
            $userId
        )
            ->where(
                'savingsable_type',
                SafeLock::class
            )
            ->latest()
            ->get();

        Log::info(
            'SafeLock transaction history retrieved',
            [
                'user_id' => $userId,
                'count' => $transactions->count(),
            ]
        );

        return Response::success([
            'transactions' => $transactions,
        ]);
    }
}