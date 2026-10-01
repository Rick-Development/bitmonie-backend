<?php

namespace App\Http\Controllers\Api\V1\User;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use App\Http\Helpers\Response;
use Illuminate\Support\Facades\Validator;
use Illuminate\Support\Facades\Hash;
use Exception;
use App\Traits\PinValidationTrait;

class LoginPinController extends Controller
{
    use PinValidationTrait;

    /**
     * Store (Setup) Login PIN (6 digits)
     */
    public function store(Request $request)
    {
        $validator = Validator::make($request->all(), [
            'login_pin' => 'required|digits:6',
        ]);

        if ($validator->fails()) {
            return Response::errorResponse("Validation Error", $validator->errors()->all());
        }

        $validated = $validator->validated();
        $user = auth()->user();

        if ($this->hasLoginPin($user)) {
             return Response::errorResponse('Login PIN is already setup. Please use update.', [], 400);
        }

        try {
            $user->update([
                'login_pin' => Hash::make($validated['login_pin']),
            ]);
        } catch (Exception $e) {
            return Response::errorResponse('Something went wrong! Please try again', [], 500);
        }

        return Response::successResponse('Login PIN setup successfully.', [], 200);
    }

    /**
     * Update Login PIN (6 digits)
     */
    public function update(Request $request)
    {
        $validator = Validator::make($request->all(), [
            'old_pin' => 'required|digits:6',
            'new_pin' => 'required|digits:6|confirmed', // expects new_pin_confirmation
        ]);

        if ($validator->fails()) {
            return Response::errorResponse("Validation Error", $validator->errors()->all());
        }

        $validated = $validator->validated();
        $user = auth()->user();

        if (!$this->validateLoginPin($user, $validated['old_pin'])) {
            return Response::errorResponse('Current PIN is incorrect.', [], 400);
        }

        try {
            $user->update([
                'login_pin' => Hash::make($validated['new_pin']),
            ]);
        } catch (Exception $e) {
            return Response::errorResponse('Something went wrong! Please try again', [], 500);
        }

        return Response::successResponse('Login PIN updated successfully.', [], 200);
    }

    /**
     * Check/Verify Login PIN
     */
    public function check(Request $request)
    {
        $validator = Validator::make($request->all(), [
            'login_pin' => 'required|digits:6',
        ]);

        if ($validator->fails()) {
            return Response::errorResponse("Validation Error", $validator->errors()->all());
        }

        $user = auth()->user();

        if (!$this->hasLoginPin($user)) {
             return Response::errorResponse('Please setup your Login PIN first.', [], 400);
        }

        if ($this->validateLoginPin($user, $request->login_pin)) {
            return Response::successResponse('Login PIN Verified Successfully', [], 200);
        } else {
            return Response::errorResponse('Invalid Login PIN', [], 400);
        }
    }
}
