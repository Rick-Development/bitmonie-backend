<?php

namespace App\Http\Controllers\Api\V1\User;

use App\Http\Controllers\Controller;
use App\Models\FireBaseToken;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Validator;
use App\Http\Helpers\Response;

class PushController extends Controller
{
    /**
     * Update/Register Firebase Token
     */
    public function updateToken(Request $request)
    {
        $validator = Validator::make($request->all(), [
            'token' => 'required|string',
            'device_id' => 'nullable|string',
        ]);

        if ($validator->fails()) {
            return Response::error($validator->errors()->all());
        }

        $validated = $validator->validated();
        $user = auth()->user();

        // Update or Create Token Record
        FireBaseToken::updateOrCreate(
            [
                'token' => $validated['token'],
            ],
            [
                'tokenable_id' => $user->id,
                'tokenable_type' => get_class($user),
                'device_id' => $validated['device_id'] ?? null,
            ]
        );

        return Response::success(['Token registered successfully']);
    }
}
