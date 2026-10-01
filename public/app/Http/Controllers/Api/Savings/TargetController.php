<?php

namespace App\Http\Controllers\Api\Savings;

use App\Http\Controllers\Controller;
use App\Http\Helpers\Response;
use App\Models\TargetSavings;
use App\Models\UserWallet;
use App\Models\SavingsTransaction;
use Illuminate\Http\Request;
use App\Notifications\User\SavingsNotification;
use Illuminate\Support\Facades\Validator;

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
    public function create(Request $request)
    {
        $validator = Validator::make($request->all(), [
            'title' => 'required|string',
            'target_amount' => 'required|numeric|min:100',
            'frequency' => 'nullable|in:daily,weekly,monthly',
            'target_date' => 'required|date|after:today',
        ]);

        if ($validator->fails()) {
            return Response::error($validator->errors());
        }

        // Create logic
        $target = TargetSavings::create([
            'user_id' => auth()->id(),
            'title' => $request->title,
            'target_amount' => $request->target_amount,
            // 'target_date' => $request->target_date, // Casted date handling might be needed
            'frequency' => $request->frequency,
            'status' => 'active',
        ]);

        // Notify
        auth()->user()->notify(new SavingsNotification('Target Savings', 'Created', $request->target_amount));

        return Response::success(['message' => 'Target created successfully', 'data' => $target]);
    }

    /**
     * Quick Save: Add funds to a target manually.
     */
    public function quickSave(Request $request)
    {
        $validator = Validator::make($request->all(), [
            'target_id' => 'required|exists:target_savings,id',
            'amount' => 'required|numeric|min:100',
        ]);

        if ($validator->fails()) {
            return Response::error($validator->errors());
        }

        $target = TargetSavings::where('user_id', auth()->id())->find($request->target_id);

        if (!$target) {
            return Response::error(['Target plan not found']);
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

        // 2. Transact
        $wallet->balance -= $request->amount;
        $wallet->save();
        
        $target->current_balance += $request->amount;
        $target->save();

        // 3. Log Transaction
        SavingsTransaction::create([
            'user_id' => $user->id,
            'savingsable_id' => $target->id,
            'savingsable_type' => TargetSavings::class,
            'amount' => $request->amount,
            'balance_after' => $target->current_balance,
            'type' => 'deposit',
            'status' => 'success',
            'source' => 'wallet',
            'narration' => 'Target Savings QuickSave: ' . $target->title
        ]);

        // Notify
        $user->notify(new SavingsNotification('Target Savings', 'Topup', $request->amount));

        return Response::success(['message' => 'Quick save successful', 'data' => $target]);
    }

    /**
     * Break a Target Savings early.
     */
    public function break(Request $request)
    {
        $validator = Validator::make($request->all(), [
            'target_id' => 'required|exists:target_savings,id',
        ]);

        if ($validator->fails()) {
            return Response::error($validator->errors()->all());
        }

        $target = TargetSavings::where('user_id', auth()->id())->find($request->target_id);

        if (!$target) {
            return Response::error(['Target plan not found']);
        }

        if ($target->status !== 'active') {
            return Response::error(['Target plan is not active']);
        }

        // 1. Get NGN Wallet
        $user = auth()->user();
        $wallet = UserWallet::where('user_id', $user->id)->where('currency_code', 'NGN')->first();
        if (!$wallet) {
            return Response::error(['NGN Wallet not found']);
        }

        // 2. Transact
        $amountToReturn = $target->current_balance;
        $penalty = 0;

        // Penalty Logic: If broken before target date, charge 2%
        if ($target->target_date && $target->target_date->isFuture()) {
            $penalty = $target->current_balance * 0.02;
            $amountToReturn -= $penalty;
        }

        $wallet->balance += $amountToReturn;
        $wallet->save();

        $target->status = 'broken';
        $target->save();

        // 3. Log Transaction
        SavingsTransaction::create([
            'user_id' => $user->id,
            'savingsable_id' => $target->id,
            'savingsable_type' => TargetSavings::class,
            'amount' => $amountToReturn,
            'balance_after' => 0,
            'type' => 'withdrawal',
            'status' => 'success',
            'source' => 'target',
            'narration' => 'Target Savings Broken (Funds Returned)' . ($penalty > 0 ? " - 2% Penalty Applied: $penalty" : "")
        ]);

        // Notify
        $user->notify(new SavingsNotification('Target Savings', 'Withdrawn', $amountToReturn));

        return Response::success(['message' => 'Target savings broken successfully.' . ($penalty > 0 ? " A 2% early withdrawal penalty of $penalty was applied." : " Principal returned to wallet.")]);
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
