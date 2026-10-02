<?php

namespace App\Http\Controllers\Api\Savings;

use App\Http\Controllers\Controller;
use App\Http\Helpers\Response;
use App\Models\SafeLock;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Validator;

use App\Models\UserWallet;
use App\Models\SavingsTransaction;
use App\Models\SavingsPlan;
use Illuminate\Support\Carbon;
use App\Notifications\User\SavingsNotification;

class SafeLockController extends Controller
{
    /**
     * List user's Safe Locks.
     */
    public function index()
    {
        $locks = SafeLock::where('user_id', auth()->id())->get();
        return Response::success(['safe_locks' => $locks]);
    }

    /**
     * List available Savings Plans (Fixed Rates).
     */
    public function plans()
    {
        $plans = SavingsPlan::where('status', true)->get();
        return Response::success(['plans' => $plans]);
    }

    /**
     * Create a new Safe Lock.
     */
    public function create(Request $request)
    {
        $validator = Validator::make($request->all(), [
            'amount' => 'required|numeric',
            'plan_id' => 'required|exists:savings_plans,id',
            'title' => 'nullable|string'
        ]);

        if ($validator->fails()) {
            return Response::error($validator->errors()->all());
        }

        $plan = SavingsPlan::find($request->plan_id);
        
        if ($request->amount < $plan->min_amount) {
            return Response::error(['Amount is less than minimum for this plan']);
        }

        if ($plan->max_amount && $request->amount > $plan->max_amount) {
            return Response::error(['Amount exceeds maximum for this plan']);
        }

        // 1. Get NGN Wallet
        $user = auth()->user();
        $wallet = UserWallet::where('user_id', $user->id)->where('currency_code', 'NGN')->first();
        if (!$wallet) {
            return Response::error(['NGN Wallet not found']);
        }

        if ($wallet->balance < $request->amount) {
            return Response::error(['Insufficient wallet balance']);
        }

        $lockDate = now();
        $maturityDate = now()->addDays($plan->duration_days);

        // 2. Transact
        $wallet->balance -= $request->amount;
        $wallet->save();
        
        $lock = SafeLock::create([
            'user_id' => $user->id,
            'title' => $request->title ?? $plan->name,
            'amount' => $request->amount,
            'interest_rate' => $plan->interest_rate,
            'interest_accrued' => 0,
            'lock_date' => $lockDate,
            'maturity_date' => $maturityDate,
            'status' => 'active',
        ]);

        // 3. Log Transaction
        SavingsTransaction::create([
            'user_id' => $user->id,
            'savingsable_id' => $lock->id,
            'savingsable_type' => SafeLock::class,
            'amount' => $request->amount,
            'balance_after' => $lock->amount,
            'type' => 'deposit',
            'status' => 'success',
            'source' => 'wallet',
            'narration' => 'SafeLock Creation: ' . $lock->title
        ]);

        // Notify
        $user->notify(new SavingsNotification('SafeLock', 'Created', $request->amount));

        return Response::success(['message' => 'SafeLock created successfully', 'data' => $lock]);
    }

    /**
     * Break a Safe Lock early.
     */
    public function break(Request $request)
    {
        $validator = Validator::make($request->all(), [
            'lock_id' => 'required|exists:safe_locks,id',
        ]);

        if ($validator->fails()) {
            return Response::error($validator->errors()->all());
        }

        $lock = SafeLock::where('user_id', auth()->id())->find($request->lock_id);

        if (!$lock) {
            return Response::error(['SafeLock not found']);
        }

        if ($lock->status !== 'active') {
            return Response::error(['SafeLock is not active']);
        }

        // 1. Get NGN Wallet
        $user = auth()->user();
        $wallet = UserWallet::where('user_id', $user->id)->where('currency_code', 'NGN')->first();
        if (!$wallet) {
            return Response::error(['NGN Wallet not found']);
        }

        // 2. Transact
        $amountToReturn = $lock->amount;
        $penalty = 0;

        // SafeLock Penalty: If broken before maturity, forfeit interest + 1% admin fee
        if ($lock->maturity_date && $lock->maturity_date->isFuture()) {
            $penalty = $lock->amount * 0.01;
            $amountToReturn -= $penalty;
        }

        $wallet->balance += $amountToReturn;
        $wallet->save();

        $lock->status = 'broken';
        $lock->save();

        // 3. Log Transaction
        SavingsTransaction::create([
            'user_id' => $user->id,
            'savingsable_id' => $lock->id,
            'savingsable_type' => SafeLock::class,
            'amount' => $amountToReturn,
            'balance_after' => 0,
            'type' => 'withdrawal',
            'status' => 'success',
            'source' => 'safelock',
            'narration' => 'SafeLock Broken (Early Withdrawal)' . ($penalty > 0 ? " - Interest Forfeited & 1% Penalty Applied: $penalty" : "")
        ]);

        // Notify
        $user->notify(new SavingsNotification('SafeLock', 'Withdrawn', $amountToReturn));

        return Response::success(['message' => 'SafeLock broken successfully. ' . ($penalty > 0 ? "Interest forfeited and a 1% penalty of $penalty was applied." : "Funds returned to wallet.")]);
    }

    /**
     * Withdraw a matured Safe Lock (after due date).
     */
    public function withdraw(Request $request)
    {
        $validator = Validator::make($request->all(), [
            'lock_id' => 'required|exists:safe_locks,id',
        ]);

        if ($validator->fails()) {
            return Response::error($validator->errors()->all());
        }

        $lock = SafeLock::where('user_id', auth()->id())->find($request->lock_id);

        if (!$lock) {
            return Response::error(['SafeLock not found']);
        }

        if ($lock->status !== 'active') {
            return Response::error(['SafeLock is not active']);
        }

        // Check if lock has matured
        if ($lock->maturity_date && $lock->maturity_date->isFuture()) {
            return Response::error(['SafeLock has not matured yet. Use break endpoint for early withdrawal.']);
        }

        // 1. Get NGN Wallet
        $user = auth()->user();
        $wallet = UserWallet::where('user_id', $user->id)->where('currency_code', 'NGN')->first();
        if (!$wallet) {
            return Response::error(['NGN Wallet not found']);
        }

        // 2. Calculate interest (since lock has matured)
        $daysLocked = $lock->lock_date->diffInDays($lock->maturity_date);
        $interestEarned = ($lock->amount * $lock->interest_rate * $daysLocked) / (365 * 100);
        $totalAmount = $lock->amount + $interestEarned;

        // 3. Credit wallet
        $wallet->balance += $totalAmount;
        $wallet->save();

        // 4. Update lock status
        $lock->status = 'completed';
        $lock->interest_accrued = $interestEarned;
        $lock->save();

        // 5. Log Transaction
        SavingsTransaction::create([
            'user_id' => $user->id,
            'savingsable_id' => $lock->id,
            'savingsable_type' => SafeLock::class,
            'amount' => $totalAmount,
            'balance_after' => 0,
            'type' => 'withdrawal',
            'status' => 'success',
            'source' => 'safelock',
            'narration' => "SafeLock Matured - Principal: {$lock->amount}, Interest: {$interestEarned}"
        ]);

        // 6. Notify
        $user->notify(new SavingsNotification('SafeLock', 'Withdrawn', $totalAmount));

        return Response::success([
            'message' => 'SafeLock withdrawn successfully',
            'data' => [
                'principal' => $lock->amount,
                'interest_earned' => $interestEarned,
                'total_amount' => $totalAmount
            ]
        ]);
    }

    /**
     * Get Transaction History.
     */
    public function history()
    {
        $transactions = SavingsTransaction::where('user_id', auth()->id())
            ->where('savingsable_type', SafeLock::class)
            ->latest()
            ->get();

        return Response::success(['transactions' => $transactions]);
    }
}
