<?php

namespace App\Http\Controllers\Api\V1\User;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use App\Http\Helpers\Response;
use Illuminate\Support\Facades\Validator;
use Illuminate\Support\Facades\Hash;
use Exception;
use App\Traits\PinValidationTrait;

class TransactionPinController extends Controller
{
    use PinValidationTrait;

    /**
     * Store (Setup) Transaction PIN (4 digits)
     */
    public function store(Request $request)
    {
        $validator = Validator::make($request->all(), [
            'pin_code' => 'required|digits:4',
        ]);

        if ($validator->fails()) {
            return Response::errorResponse('Invalid PIN', $validator->errors()->all());
        }

        $validated = $validator->validated();
        $user = auth()->user();

        if ($this->hasTransactionPin($user)) {
             return Response::errorResponse('PIN is already setup. Please use update.', [], 400);
        }

        try {
            $user->update([
                'pin_code'   => Hash::make($validated['pin_code']),
                'pin_status' => true,
            ]);
        } catch (Exception $e) {
            return Response::errorResponse('Something went wrong! Please try again', [], 500);
        }

        return Response::successResponse('Transaction PIN setup successfully.', [], 200);
    }

    /**
     * Update Transaction PIN (4 digits)
     */
    public function update(Request $request)
    {
        $validator = Validator::make($request->all(), [
            'old_pin' => 'required|digits:4',
            'new_pin' => 'required|digits:4|confirmed', // expects new_pin_confirmation
        ]);

        if ($validator->fails()) {
            return Response::errorResponse('Invalid PIN', $validator->errors()->all());
        }

        $validated = $validator->validated();
        $user = auth()->user();

        if (!$this->validateTransactionPin($user, $validated['old_pin'])) {
            return Response::errorResponse('Current PIN is incorrect.', [], 400);
        }

        try {
            $user->update([
                'pin_code'   => Hash::make($validated['new_pin']),
                'pin_status' => true,
            ]);
        } catch (Exception $e) {
            return Response::errorResponse('Something went wrong! Please try again', [], 500);
        }

        return Response::successResponse('Transaction PIN updated successfully.', [], 200);
    }

    /**
     * Check/Verify Transaction PIN
     */
    public function check(Request $request)
    {
        $validator = Validator::make($request->all(), [
            'pin_code' => 'required|digits:4',
        ]);

        if ($validator->fails()) {
            return Response::errorResponse('Invalid PIN', $validator->errors()->all());
        }

        $user = auth()->user();

        if (!$this->hasTransactionPin($user)) {
             return Response::errorResponse('Please setup your PIN first.', [], 400);
        }

        if ($this->validateTransactionPin($user, $request->pin_code)) {
            return Response::successResponse('PIN Verified Successfully', [], 200);
        } else {
            return Response::errorResponse('Invalid PIN', [], 400);
        }
    }
}
