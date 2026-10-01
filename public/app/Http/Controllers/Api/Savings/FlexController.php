<?php

namespace App\Http\Controllers\Api\Savings;

use App\Http\Controllers\Controller;
use App\Http\Helpers\Response;
use App\Models\FlexSavings;
use App\Models\UserWallet;
use App\Models\SavingsTransaction;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Validator;
use App\Notifications\User\SavingsNotification;

class FlexController extends Controller
{
    /**
     * Get Flex Savings details.
     */
    public function index()
    {
        $user = auth()->user();
        $flex = FlexSavings::firstOrCreate(['user_id' => $user->id]);
        return Response::success(['flex_savings' => $flex]);
    }

    /**
     * Deposit into Flex Savings (from main NGN Wallet).
     */
    public function deposit(Request $request)
    {
        $validator = Validator::make($request->all(), [
            'amount' => 'required|numeric|min:100',
        ]);

        if ($validator->fails()) {
            return Response::error($validator->errors()->all());
        }

        $user = auth()->user();
        $amount = $request->amount;
        $flex = FlexSavings::firstOrCreate(['user_id' => $user->id]);

        // 1. Get NGN Wallet
        $wallet = UserWallet::where('user_id', $user->id)->where('currency_code', 'NGN')->first();
        if (!$wallet) {
            return Response::error(['NGN Wallet not found for this user']);
        }

        if ($wallet->balance < $amount) {
            return Response::error(['Insufficient wallet balance']);
        }

        // 2. Transact
        $wallet->balance -= $amount;
        $wallet->save();

        $flex->balance += $amount;
        $flex->save();

        // 3. Log Transaction
        SavingsTransaction::create([
            'user_id' => $user->id,
            'savingsable_id' => $flex->id,
            'savingsable_type' => FlexSavings::class,
            'amount' => $amount,
            'balance_after' => $flex->balance,
            'type' => 'deposit',
            'status' => 'success',
            'source' => 'wallet',
            'narration' => 'Flex Savings Deposit'
        ]);

        // Notify
        $user->notify(new SavingsNotification('Flex Savings', 'Topup', $amount));

        return Response::success(['message' => 'Deposit successful', 'balance' => $flex->balance]);
    }

    /**
     * Withdraw from Flex Savings (to main NGN Wallet).
     */
    public function withdraw(Request $request)
    {
        $validator = Validator::make($request->all(), [
            'amount' => 'required|numeric|min:100',
        ]);

        if ($validator->fails()) {
            return Response::error($validator->errors()->all());
        }

        $user = auth()->user();
        $amount = $request->amount;
        $flex = FlexSavings::firstOrCreate(['user_id' => $user->id]);

        if ($flex->balance < $amount) {
            return Response::error(['Insufficient flex balance']);
        }

        // 1. Get NGN Wallet
        $wallet = UserWallet::where('user_id', $user->id)->where('currency_code', 'NGN')->first();
        if (!$wallet) {
            return Response::error(['NGN Wallet not found']);
        }

        // Limit Logic: 4 free withdrawals per quarter
        $quarterStart = now()->startOfQuarter();
        $withdrawalCount = SavingsTransaction::where('user_id', $user->id)
            ->where('savingsable_type', FlexSavings::class)
            ->where('type', 'withdrawal')
            ->where('created_at', '>=', $quarterStart)
            ->count();

        $penaltyFee = 0;
        if ($withdrawalCount >= 4) {
             $penaltyFee = 100; // Flat 100 NGN fee
             if ($flex->balance < ($amount + $penaltyFee)) {
                 return Response::error(["Withdrawal limit reached. Additional withdrawals cost 100 NGN. Insufficient balance for fee."]);
             }
        }

        // 2. Transact
        $flex->balance -= ($amount + $penaltyFee);
        $flex->save();

        $wallet->balance += $amount;
        $wallet->save();

        // 3. Log Transaction
        SavingsTransaction::create([
            'user_id' => $user->id,
            'savingsable_id' => $flex->id,
            'savingsable_type' => FlexSavings::class,
            'amount' => $amount,
            'balance_after' => $flex->balance,
            'type' => 'withdrawal',
            'status' => 'success',
            'source' => 'flex',
            'narration' => 'Flex Savings Withdrawal' . ($penaltyFee > 0 ? " - Limit Fee Applied: $penaltyFee" : "")
        ]);

        // Notify
        $user->notify(new SavingsNotification('Flex Savings', 'Withdrawn', $amount));

        return Response::success([
            'message' => 'Withdrawal successful' . ($penaltyFee > 0 ? " (Fee of $penaltyFee applied)" : ""),
            'balance' => $flex->balance
        ]);
    }

    /**
     * Get Transaction History.
     */
    public function history()
    {
        $user = auth()->user();
        $flex = FlexSavings::firstOrCreate(['user_id' => $user->id]);
        
        $transactions = SavingsTransaction::where('savingsable_id', $flex->id)
            ->where('savingsable_type', FlexSavings::class)
            ->latest()
            ->get();

        return Response::success(['transactions' => $transactions]);
    }
}
