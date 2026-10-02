<?php

namespace App\Http\Controllers\Api;

use App\Http\Helpers\Response;
use App\Models\Savings;
use App\Models\SavingsTargets;
use App\Models\UserWallet;
use Illuminate\Support\Str;
use Illuminate\Http\Request;
use App\Models\SavingsTransaction;
use App\Http\Controllers\Controller;
use App\Http\Helpers\Payscribe\PayscribePayoutHelper;
use App\Http\Helpers\Payscribe\PayscribeBalanceHelper;
use App\Http\Helpers\Payscribe\PayscribeSavingsHelper;
use App\Notifications\User\SavingsNotification;

class PayscribeSavingsController extends Controller
{
    // public function __construct(private PayscribeSavingsHelper $payscribeSavingsHelper, private PayscribeBalanceHelper $payscribeBalanceHelper)
    // {
    // }

    public function createSavings(Request $request)
    {
        $validator = \Validator::make($request->all(), [
            'target_title' => 'required|string|max:100',
            'target_amount' => 'required|numeric|min:0',
        ]);

        if ($validator->fails()) {
            return response()->json([
                'status' => 'failed',
                'message' => 'Validation failed',
                'data' => $validator->errors()
            ], 422);
        }

        $user = auth()->user();

        // create a savings account if it does not exist
        $savings_account = Savings::firstOrCreate(
            ['user_id' => $user->id],
        );

        // 🧠 Check if a savings with same title already exists for this user
        $existing = SavingsTargets::where('user_id', $user->id)
            ->whereRaw('LOWER(target_title) = ?', [strtolower($request->target_title)])
            ->first();

        if ($existing) {
            return response()->json([
                'status' => 'failed',
                'message' => 'You already have a savings account with this title.',
                'data' => $existing,
            ], 409); // 409 Conflict
        }

        $plan_id = Str::random(8);

        $savings = SavingsTargets::create([
            'user_id' => $user->id,
            'savings_id' => $savings_account['id'],
            'plan_id' => $plan_id,
            'target_title' => $request->target_title,
            'target_amount' => $request->target_amount,
            'status' => 'active',
        ]);

        // Notify
        $user->notify(new SavingsNotification('Payscribe Savings', 'Created', $request->target_amount));

        return Response::success('Savings account created successfully.', $savings, 201);
    }

    public function depositSavings(Request $request)
    {
        $validator = \Validator::make($request->all(), [
            'plan_id' => 'required|exists:savings_targets,plan_id',
            'amount' => 'required|numeric|min:50',
            'narration' => 'required'
        ]);

        if ($validator->fails()) {
            return response()->json([
                'status' => 'failed',
                'message' => 'Validation failed',
                'data' => $validator->errors()
            ], 422);
        }

        $user = auth()->user();
        $amount = $request->amount;
        $savingsId = $request->plan_id;

        try {
            \DB::transaction(function () use ($user, $amount, $savingsId, $request) {

                // Lock the user's wallet
                $wallet = UserWallet::where('user_id', $user->id)->lockForUpdate()->firstOrFail();

                if ($wallet->balance < $amount) {
                    throw new \Exception('Insufficient wallet balance.');
                }

                // Deduct from wallet
                $wallet->balance -= $amount;
                $wallet->save();

                // Lock and update the savings target
                $savings = SavingsTargets::where('plan_id', $savingsId)
                    ->where('user_id', $user->id)
                    ->lockForUpdate()
                    ->firstOrFail();

                if ($savings->status !== 'active') {
                    throw new \Exception('This savings target is not active.');
                }

                // get the savings account associated with the user
                $user_savings = Savings::where('user_id', $user->id)->firstOrFail();
                $user_savings->balance += $amount;
                $user_savings->save();

                $savings->balance += $amount;
                $savings->save();

                // Record transaction
                SavingsTransaction::create([
                    'user_id' => $user->id,
                    'savings_id' => $savings->plan_id,
                    'type' => 'deposit',
                    'amount' => $amount,
                    'status' => 'successful',
                    'source' => 'wallet',
                    'narration' => $request->narration,
                ]);
            });

            $wallet = UserWallet::where('user_id', $user->id)->first();
            $savings = SavingsTargets::where('plan_id', $savingsId)->first();
            $user_savings = Savings::where('user_id', $user->id)->firstOrFail();

            // Notify
            $user->notify(new SavingsNotification('Payscribe Savings', 'Topup', $amount));

            return Response::success('Savings top-up successful', [
                'wallet_balance' => $wallet->balance,
                'savings' => $savings,
                'total_savings' => $user_savings->balance
            ], 200);

        } catch (\Exception $e) {
            return response()->json([
                'status' => 'failed',
                'message' => $e->getMessage(),
            ], 400);
        }
    }

    public function withdrawFromSavings(Request $request)
    {
        $validator = \Validator::make($request->all(), [
            'plan_id' => 'required|exists:savings_targets,plan_id',
            'amount' => 'required|numeric|min:50',
            'narration' => 'required'
        ]);

        if ($validator->fails()) {
            return response()->json([
                'status' => 'failed',
                'message' => 'Validation failed',
                'data' => $validator->errors()
            ], 422);
        }

        $user = auth()->user();
        $amount = $request->amount;

        try {
            \DB::transaction(function () use ($user, $amount, $request) {
                // Lock both wallet and savings row for consistency
                $wallet = UserWallet::where('user_id', $user->id)->lockForUpdate()->first();
                $savings = SavingsTargets::where('plan_id', $request->plan_id)
                    ->where('user_id', $user->id)
                    ->lockForUpdate()
                    ->first();

                $user_savings = Savings::where('user_id', $user->id)->firstOrFail();

                if (!$savings) {
                    throw new \Exception('Savings target was not found.');
                }

                // Check if savings is locked
                if ($savings->locked_until && now()->lt($savings->locked_until)) {
                    throw new \Exception('This savings target is locked until ' . $savings->locked_until->format('Y-m-d H:i:s'));
                }

                // Check sufficient balance
                if ($user_savings->balance < $amount) {
                    throw new \Exception('Insufficient savings balance.');
                }

                // Deduct from savings
                $user_savings->balance -= $amount;
                $user_savings->save();

                $savings->balance -= $amount;
                $savings->save();

                // Credit back to wallet
                $wallet->balance += $amount;
                $wallet->save();

                // (Optional) Record transaction log
                SavingsTransaction::create([
                    'user_id' => $user->id,
                    'savings_id' => $savings->plan_id,
                    'type' => 'withdrawal',
                    'amount' => $amount,
                    'status' => 'successful',
                    'source' => 'wallet',
                    'narration' => $request->narration,
                ]);
            });

            $savings = SavingsTargets::where('plan_id', $request->plan_id)->first();
            $user_savings = Savings::where('user_id', $user->id)->firstOrFail();
            // Notify
            $user->notify(new SavingsNotification('Payscribe Savings', 'Withdrawn', $amount));

            return Response::success('Withdrawal successful', [
                'details' => $savings,
                'total_savings' => $user_savings->balance
            ]);

        } catch (\Exception $e) {
            return response()->json([
                'status' => 'failed',
                'message' => $e->getMessage(),
            ], 400);
        }
    }

    public function listUserSavings()
    {
        $user = auth()->user();

        $user_savings = Savings::where('user_id', $user->id)->first();
        if (!$user_savings) {
            return response()->json([
                'message' => 'savings not found',
            ]);
        }
        $savings = SavingsTargets::where('savings_id', $user_savings->id)
            ->orderBy('created_at', 'desc')
            ->get();

        if ($savings->isEmpty()) {
            return response()->json([
                'status' => 'success',
                'message' => 'No savings account found.',
                'data' => [],
            ], 200);
        }

        return Response::success('Savings accounts retrieved successfully.', [
            'details' => $savings,
            'total_savings' => $user_savings->balance
        ]);
    }

}