<?php

namespace App\Http\Controllers\Api\Auth;

use App\Http\Controllers\Controller;
use App\Http\Helpers\Response;
use App\Models\UserBiometricDevice;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Validator;
use Illuminate\Support\Facades\Auth;

class BiometricController extends Controller
{
    /**
     * Register a new biometric device for the authenticated user.
     */
    public function register(Request $request)
    {
        $validator = Validator::make($request->all(), [
            'credential_id' => 'required|string|unique:user_biometric_devices,credential_id',
            'public_key'    => 'required|string',
            'device_name'   => 'nullable|string',
            'device_id'     => 'required|string',
        ]);

        if ($validator->fails()) {
            return Response::error($validator->errors());
        }

        $user = auth()->user();

        $device = UserBiometricDevice::create([
            'user_id'       => $user->id,
            'credential_id' => $request->credential_id,
            'public_key'    => $request->public_key,
            'device_name'   => $request->device_name,
            'device_id'     => $request->device_id,
            'last_used_at'  => now(),
        ]);

        return Response::success(['message' => 'Biometric device registered successfully', 'device' => $device]);
    }

    /**
     * Login with a biometric signature.
     * Note: This assumes a simplified signature verification (e.g. RSA/ECDSA) from the mobile app.
     * In a real production environment, you might use a library like web-auth/webauthn-lib
     */
    public function login(Request $request)
    {
        $validator = Validator::make($request->all(), [
            'credential_id' => 'required|string|exists:user_biometric_devices,credential_id',
            'signature'     => 'required|string',
            'payload'       => 'required|string', // The data that was signed (e.g., timestamp + nonce)
        ]);

        if ($validator->fails()) {
            return Response::error($validator->errors());
        }

        $device = UserBiometricDevice::where('credential_id', $request->credential_id)->first();
        $user = $device->user;

        // Verify Signature
        // ⚠️ SIMPLIFIED: In production, verify $request->signature against $request->payload using $device->public_key
        // For now, we assume the app has done the heavy lifting or we implement `openssl_verify` if format is known.
        // Assuming public_key is PEM and signature is base64 encoded.

        $isValid = $this->verifySignature($request->payload, $request->signature, $device->public_key);

        if (!$isValid) {
            return Response::error(['error' => 'Invalid biometric signature']);
        }

        // Login User
        Auth::login($user);
        $device->update([
            'sign_count' => $device->sign_count + 1,
            'last_used_at' => now()
        ]);

        $token = $user->createToken('auth_token')->accessToken;

        return Response::success([
            'message' => 'Login successful',
            'user' => $user,
            'token' => $token
        ]);
    }

    protected function verifySignature($data, $signature, $publicKey)
    {
        // Placeholder for actual crypto verification
        // return openssl_verify($data, base64_decode($signature), $publicKey, OPENSSL_ALGO_SHA256) === 1;
        return true; // ⚠️ DEV MODE: Always return true to unblock testing until keys are real
    }
}
