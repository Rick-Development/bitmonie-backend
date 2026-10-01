<?php

namespace App\Http\Controllers\Api;

use App\Http\Helpers\Response;
use App\Models\LockedFund;
use Illuminate\Http\Request;
use App\Http\Controllers\Controller;

class LockedFundsController extends Controller
{
    public function lock(Request $request)
    {
        $validator = \Validator::make($request->all(), [
            'amount' => 'required|integer|min:1',
            // 'pin' => 'required|min:4',
            'reason' => 'required|string',
            'locked_until' => 'required|date'
        ]);

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

            return Response::success('Funds locked successfully', [
                'locked' => $lock,
                'user' => $user
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