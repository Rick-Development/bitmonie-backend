<?php

namespace App\Http\Controllers\Api\Savings;

use App\Http\Controllers\Controller;
use App\Http\Helpers\Response;
use App\Models\EduSave;
use App\Models\UserWallet;
use App\Models\SavingsTransaction;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Validator;
use Carbon\Carbon;
use App\Notifications\User\SavingsNotification;

class EduSaveController extends Controller
{
    /**
     * List user's EduSave plans.
     */
    public function index()
    {
        $plans = EduSave::where('user_id', auth()->id())->latest()->get();
        return Response::successResponse('EduSave plans fetched', ['plans' => $plans]);
    }

    /**
     * Create a new EduSave plan.
     */
    public function create(Request $request)
    {
        $validator = Validator::make($request->all(), [
            'amount' => 'required|numeric|min:100', // Assuming min amount
            'title' => 'required|string',
            'period' => 'required|in:termly,yearly',
            'graduation_date' => 'required|date|after:today',
        ]);

        if ($validator->fails()) {
            return Response::errorResponse('Validation Error', $validator->errors()->all());
        }

        $user = auth()->user();
        $wallet = UserWallet::where('user_id', $user->id)->where('currency_code', 'NGN')->first(); // Defaulting to NGN for now as per context

        if (!$wallet) {
            return Response::errorResponse('Active NGN Wallet not found');
        }

        if ($wallet->balance < $request->amount) {
            return Response::errorResponse('Insufficient wallet balance');
        }

        // Determine next payout date
        $startDate = Carbon::now();
        $nextPayout = $request->period === 'yearly' 
            ? $startDate->copy()->addYear() 
            : $startDate->copy()->addMonths(4); // Assuming 3 terms a year -> 4 months

        // Check against graduation date
        if ($nextPayout->gt(Carbon::parse($request->graduation_date))) {
             return Response::errorResponse('Graduation date is too soon for the selected period');
        }

        // Transaction
        $wallet->balance -= $request->amount;
        $wallet->save();

        $eduSave = EduSave::create([
            'user_id' => $user->id,
            'title' => $request->title,
            'amount' => $request->amount,
            'period' => $request->period,
            'start_date' => $startDate,
            'graduation_date' => $request->graduation_date,
            'next_payout_date' => $nextPayout,
            'status' => 'active',
        ]);

        // Log Transaction
        SavingsTransaction::create([
            'user_id' => $user->id,
            'savingsable_id' => $eduSave->id,
            'savingsable_type' => EduSave::class,
            'amount' => $request->amount,
            'balance_after' => 0, // Principal is locked/gone
            'type' => 'deposit',
            'status' => 'success',
            'source' => 'wallet',
            'narration' => 'EduSave Initial Deposit: ' . $eduSave->title
        ]);

        // Notify
        $user->notify(new SavingsNotification('EduSave', 'Created', $request->amount));

        return Response::successResponse('EduSave plan created successfully', ['data' => $eduSave]);
    }

    /**
     * Get Transaction History for EduSave.
     */
    public function history()
    {
        $transactions = SavingsTransaction::where('user_id', auth()->id())
            ->where('savingsable_type', EduSave::class)
            ->latest()
            ->get();

        return Response::successResponse('Transaction history fetched', ['transactions' => $transactions]);
    }
}
