<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\UserKycData;
use App\Services\YouVerifyService;
use Illuminate\Http\Request;
use Illuminate\Support\Arr;

class YouVerifyWebhookController extends Controller
{
    /**
     * Handle YouVerify webhook callbacks.
     *
     * This endpoint should be configured as the callback_url in YouVerify.
     * It will update the user's KYC status based on the provider result.
     */
    public function handle(Request $request, YouVerifyService $youVerify)
    {
        $payload = $request->all();

        // TODO: implement real signature verification when you have
        // the exact header & algorithm from YouVerify's documentation.
        // Example placeholder:
        // $signature = $request->header('X-Webhook-Signature');
        // $secret    = config('youverify.webhook_secret');
        // if ($secret && !$this->isValidSignature($payload, $signature, $secret)) { ... }

        $reference = Arr::get($payload, 'data.reference', Arr::get($payload, 'reference'));
        $status    = Arr::get($payload, 'data.status', Arr::get($payload, 'status'));

        if (!$reference) {
            return response()->json(['success' => false, 'message' => 'Missing reference'], 400);
        }

        $kycData = UserKycData::where('youverify_reference', $reference)->first();

        if (!$kycData) {
            // Acknowledge to avoid repeated retries from provider,
            // but log / monitor this in your logging stack if needed.
            return response()->json(['success' => true, 'message' => 'Reference not mapped'], 200);
        }

        $kycData->youverify_status  = $status;
        $kycData->youverify_payload = $payload;
        $kycData->save();

        $user = $kycData->user ?? $kycData->user()->first();
        if ($user) {
            $internalStatus = $youVerify->mapStatusToInternal($status);
            if (!is_null($internalStatus) && $internalStatus !== $user->kyc_verified) {
                $user->update(['kyc_verified' => $internalStatus]);
            }
        }

        return response()->json(['success' => true]);
    }
}



