<?php

namespace App\Http\Controllers\Api\Savings;

use App\Http\Controllers\Controller;
use App\Http\Helpers\Response;
use App\Models\OrderTransaction;
use App\Models\TargetSavings;
use App\Models\UserWallet;
use App\Models\SavingsTransaction;
use App\Services\SavingsAutosaveSetupService;
use App\Services\SavingsService;
use App\Services\Savings\Contracts\SavingsFundingProviderInterface;
use Illuminate\Http\Request;
use App\Notifications\User\SavingsNotification;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Validator;
use Illuminate\Support\Str;
use RuntimeException;
use Throwable;

class TargetController extends Controller
{
    /**
     * List user's target savings.
     */
    public function index()
    {
        $targets = TargetSavings::where('user_id', auth()->id())->get();

        return Response::success(['targets' => $targets]);
    }

    /**
     * Create a new Target Savings.
     */
    public function create(Request $request, SavingsAutosaveSetupService $autosaveSetup)
    {
        $validator = Validator::make($request->all(), array_merge([
            'title' => 'required|string',
            'target_amount' => 'required|numeric|min:100',
            'frequency' => 'nullable|in:daily,weekly,monthly',
            'target_date' => 'required|date|after:today',
        ], $autosaveSetup->rules()));

        if ($validator->fails()) {
            return Response::error($validator->errors());
        }

        // Create logic
        $target = TargetSavings::create([
            'user_id' => auth()->id(),
            'title' => $request->title,
            'target_amount' => $request->target_amount,
            'target_date' => $request->target_date,
            'frequency' => $request->frequency,
            'status' => 'active',
        ]);

        // Notify
        auth()->user()->notify(new SavingsNotification('Target Savings', 'Created', $request->target_amount));

        try {
            $autosavePlan = $autosaveSetup->createForTarget(auth()->user(), $target, 'target_savings', $request->input('autosave'), [
                'name' => $target->title . ' AutoSave',
                'title' => $target->title,
                'goal_amount' => $target->target_amount,
                'maturity_date' => $target->target_date,
            ]);
        } catch (\InvalidArgumentException $e) {
            return Response::error([$e->getMessage()]);
        }

        return Response::success([
            'message' => 'Target created successfully',
            'data' => $target,
            'autosave_plan' => $autosavePlan,
        ]);
    }

    /**
     * Quick Save: Add funds to a target manually.
     */
    public function quickSave(
        Request $request,
        SavingsAutosaveSetupService $autosaveSetup,
        SavingsFundingProviderInterface $fundingProvider
    ) {
        $validator = Validator::make($request->all(), array_merge([
            'target_id' => 'required|exists:target_savings,id',
            'amount' => 'required|numeric|min:100',
        ], $autosaveSetup->rules()));

        if ($validator->fails()) {
            return Response::error($validator->errors()->all());
        }

        $user = auth()->user();
        $amount = (string) $request->input('amount');
        $reference = 'target:deposit:' . $request->target_id . ':' . Str::uuid();

        try {
            $autosavePlan = null;

            DB::transaction(function () use (
                $user,
                $request,
                $amount,
                $reference,
                $fundingProvider,
                $autosaveSetup,
                &$target,
                &$autosavePlan
            ) {
                $target = TargetSavings::where('user_id', $user->id)
                    ->where('id', $request->target_id)
                    ->lockForUpdate()
                    ->first();

                if (!$target) {
                    throw new RuntimeException('Target plan not found');
                }

                if ($target->status !== 'active') {
                    throw new RuntimeException('Target plan is not active');
                }

                $wallet = UserWallet::where('user_id', $user->id)
                    ->where('currency_code', 'NGN')
                    ->lockForUpdate()
                    ->first();

                if (!$wallet) {
                    throw new RuntimeException('NGN Wallet not found');
                }

                if (bccomp((string) $wallet->balance, $amount, 8) < 0) {
                    throw new RuntimeException('Insufficient wallet balance');
                }

                // Attempt funding provider deposit if user has SafeHaven subaccount
                try {
                    $fundingProvider->deposit($user, $amount, $reference, 'Target Savings QuickSave: ' . $target->title);
                } catch (Throwable $e) {
                    Log::warning('Target savings provider deposit warning', [
                        'user_id' => $user->id,
                        'target_id' => $target->id,
                        'error' => $e->getMessage(),
                    ]);
                }

                $wallet->balance = bcsub((string) $wallet->balance, $amount, 8);
                $wallet->save();

                $target->current_balance = bcadd((string) $target->current_balance, $amount, 8);
                $target->save();

                SavingsTransaction::create([
                    'user_id' => $user->id,
                    'savingsable_id' => $target->id,
                    'savingsable_type' => TargetSavings::class,
                    'amount' => $amount,
                    'balance_after' => $target->current_balance,
                    'type' => 'deposit',
                    'status' => 'success',
                    'source' => 'wallet',
                    'narration' => 'Target Savings QuickSave: ' . $target->title,
                    'reference' => $reference,
                ]);

                OrderTransaction::create([
                    'user_wallet_id' => $wallet->id,
                    'type' => 'debit',
                    'amount' => $amount,
                    'balance_after' => $wallet->balance,
                    'reference' => $reference,
                    'metadata' => [
                        'source' => 'savings',
                        'savings_type' => 'target_savings',
                        'savings_id' => $target->id,
                        'title' => $target->title,
                    ],
                ]);

                if ($request->filled('autosave')) {
                    $autosavePlan = $autosaveSetup->createForTarget($user, $target, 'target_savings', $request->input('autosave'), [
                        'name' => $target->title . ' AutoSave',
                        'title' => $target->title,
                        'goal_amount' => $target->target_amount,
                        'maturity_date' => $target->target_date,
                    ]);
                }
            });

            // Notify
            $user->notify(new SavingsNotification('Target Savings', 'Topup', (float) $amount));

            return Response::success([
                'message' => 'Quick save successful',
                'data' => $target,
                'autosave_plan' => $autosavePlan,
            ]);
        } catch (Throwable $e) {
            Log::error('Target savings quickSave failed', [
                'user_id' => $user->id,
                'target_id' => $request->target_id,
                'error' => $e->getMessage(),
            ]);

            return Response::error([$e->getMessage()]);
        }
    }

    /**
     * Break a Target Savings early.
     */
    public function break(
        Request $request,
        SavingsFundingProviderInterface $fundingProvider
    ) {
        $validator = Validator::make($request->all(), [
            'target_id' => 'required|exists:target_savings,id',
        ]);

        if ($validator->fails()) {
            return Response::error($validator->errors()->all());
        }

        $user = auth()->user();

        try {
            $result = DB::transaction(function () use (
                $request,
                $user,
                $fundingProvider
            ) {
                $target = TargetSavings::where('user_id', $user->id)
                    ->where('id', $request->target_id)
                    ->lockForUpdate()
                    ->first();

                if (!$target) {
                    throw new RuntimeException('Target plan not found');
                }

                if ($target->status !== 'active') {
                    throw new RuntimeException('Target plan is not active');
                }

                $wallet = UserWallet::where('user_id', $user->id)
                    ->where('currency_code', 'NGN')
                    ->lockForUpdate()
                    ->first();

                if (!$wallet) {
                    throw new RuntimeException('NGN Wallet not found');
                }

                $amountToReturn = (string) $target->current_balance;
                $penalty = '0';

                if (bccomp($amountToReturn, '0', 8) <= 0) {
                    throw new RuntimeException('Target savings balance is zero.');
                }

                // Penalty Logic: If broken before target date, charge 2%
                if ($target->target_date && $target->target_date->isFuture()) {
                    $penalty = bcmul($amountToReturn, '0.02', 8);
                    $amountToReturn = bcsub($amountToReturn, $penalty, 8);
                }

                $reference = 'target:break:' . $target->id . ':' . Str::uuid();

                // Transfer from Platform Main Account to User Sub-Account if SafeHaven
                try {
                    $fundingProvider->withdraw(
                        $user,
                        $amountToReturn,
                        $reference,
                        'Target Savings Broken (Funds Returned)'
                    );
                } catch (Throwable $e) {
                    Log::warning('Target savings provider withdrawal notice', [
                        'user_id' => $user->id,
                        'target_id' => $target->id,
                        'amount' => $amountToReturn,
                        'error' => $e->getMessage(),
                    ]);
                }

                $wallet->balance = bcadd((string) $wallet->balance, $amountToReturn, 8);
                $wallet->save();

                $target->current_balance = 0;
                $target->status = 'broken';
                $target->save();

                SavingsTransaction::create([
                    'user_id' => $user->id,
                    'savingsable_id' => $target->id,
                    'savingsable_type' => TargetSavings::class,
                    'amount' => $amountToReturn,
                    'balance_after' => 0,
                    'type' => 'withdrawal',
                    'status' => 'success',
                    'source' => 'target',
                    'reference' => $reference,
                    'narration' => 'Target Savings Broken (Funds Returned)' . (bccomp($penalty, '0', 8) > 0 ? " - 2% Penalty Applied: {$penalty}" : ''),
                ]);

                OrderTransaction::create([
                    'user_wallet_id' => $wallet->id,
                    'type' => 'credit',
                    'amount' => $amountToReturn,
                    'balance_after' => $wallet->balance,
                    'reference' => $reference,
                    'metadata' => [
                        'source' => 'savings',
                        'savings_type' => 'target_savings',
                        'savings_id' => $target->id,
                        'title' => $target->title,
                        'penalty' => $penalty,
                    ],
                ]);

                return [
                    'target' => $target,
                    'amount' => $amountToReturn,
                    'penalty' => $penalty,
                ];
            });

            // Notify
            $user->notify(new SavingsNotification('Target Savings', 'Withdrawn', (float) $result['amount']));

            return Response::success([
                'message' => bccomp($result['penalty'], '0', 8) > 0
                    ? "Target savings broken successfully. A 2% early withdrawal penalty of {$result['penalty']} was applied."
                    : 'Target savings broken successfully. Principal returned to wallet.',
                'data' => [
                    'amount_returned' => $result['amount'],
                    'penalty' => $result['penalty'],
                ],
            ]);
        } catch (Throwable $e) {
            Log::error('Target savings break failed', [
                'user_id' => $user->id,
                'target_id' => $request->target_id,
                'error' => $e->getMessage(),
            ]);

            return Response::error([$e->getMessage()]);
        }
    }

    /**
     * Get Transaction History.
     */
    public function history()
    {
        $transactions = SavingsTransaction::where('user_id', auth()->id())
            ->where('savingsable_type', TargetSavings::class)
            ->latest()
            ->get();

        return Response::success(['transactions' => $transactions]);
    }
}

