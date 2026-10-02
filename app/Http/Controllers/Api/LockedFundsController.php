<?php

namespace App\Http\Controllers\Api;

use App\Http\Helpers\Response;
use App\Models\LockedFund;
use App\Models\OrderTransaction;
use App\Services\SavingsAutosaveSetupService;
use Illuminate\Http\Request;
use App\Http\Controllers\Controller;

class LockedFundsController extends Controller
{
    public function lock(Request $request, SavingsAutosaveSetupService $autosaveSetup)
    {
        $validator = \Validator::make($request->all(), array_merge([
            'amount' => 'required|integer|min:1',
            // 'pin' => 'required|min:4',
            'reason' => 'required|string',
            'locked_until' => 'required|date'
        ], $autosaveSetup->rules()));

        if ($validator->fails()) {
            return response()->json([
                'status' => false,
                'message' => 'Validation error',
                'errors' => $validator->errors(),
            ], 422);
        }

        $user = auth()->user();
        $wallet = $user->wallet;

        // 2. Validate sufficient balance
        if ($wallet->balance < $request->amount) {
            return response()->json([
                'status' => false,
                'message' => 'Insufficient available balance.',
            ], 400);
        }

        try {
            // 3. Lock the funds using service class
            $lock = app(\App\Services\LockedFundsService::class)
                ->lock(
                    $wallet,
                    $request->amount,
                    $request->reason ?? 'manual-lock',
                    $request->locked_until
                );

            OrderTransaction::create([
                'user_wallet_id' => $wallet->id,
                'type' => 'debit',
                'amount' => $request->amount,
                'balance_after' => $wallet->fresh()->balance,
                'reference' => 'locked-funds:' . $lock->id,
                'metadata' => [
                    'source' => 'savings',
                    'savings_type' => 'locked_funds',
                    'savings_id' => $lock->id,
                    'reason' => $lock->reason,
                ],
            ]);

            try {
                $autosavePlan = $autosaveSetup->createForTarget($user, $lock, 'locked_funds', $request->input('autosave'), [
                    'name' => 'Locked Funds AutoSave',
                    'title' => $lock->reason,
                    'maturity_date' => $lock->locked_until,
                ]);
            } catch (\InvalidArgumentException $e) {
                return response()->json([
                    'status' => false,
                    'message' => $e->getMessage(),
                ], 422);
            }

            return Response::success('Funds locked successfully', [
                'locked' => $lock,
                'user' => $user,
                'autosave_plan' => $autosavePlan,
            ], 201);

        } catch (\Exception $e) {

            return response()->json([
                'status' => false,
                'message' => $e->getMessage(),
            ], 500);
        }
    }

    public function list_locked_funds()
    {
        $q = LockedFund::with(['user', 'wallet']);

        $lists = $q->where('user_id', auth()->user()->id)->get();
        $lists->transform(function ($list) {
            return [
                'id' => $list->id,
                'user_wallet_id' => $list->user_wallet_id,
                'user_id' => $list->user_id,
                'amount' => $list->amount,
                'reason' => $list->reason,
                'status' => $list->status,
                'locked_until' => $list->locked_until,
                'user wallet' => [
                    'balance' => $list->wallet->balance
                ],
                'user' => [
                    'firstname' => $list->user->firstname,
                    'lastname' => $list->user->lastname,
                    'username' => $list->user->username,
                    'email' => $list->user->email
                ]
            ];
        });

        return Response::success('Locked funds fetched successfully', $lists, 200);
    }
}
