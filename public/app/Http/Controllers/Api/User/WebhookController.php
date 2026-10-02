<?php

namespace App\Http\Controllers\Api\User;

use App\Http\Controllers\Controller;
use App\Models\KycVerification;
use App\Models\User;
use App\Services\YouVerifyService;
use App\Services\SafeHavenService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;
use Exception;

class WebhookController extends Controller
{
    protected $youVerifyService;
    protected $safeHavenService;

    public function __construct(
        YouVerifyService $youVerifyService,
        SafeHavenService $safeHavenService
    ) {
        $this->youVerifyService = $youVerifyService;
        $this->safeHavenService = $safeHavenService;
    }

    /**
     * Handle YouVerify Webhook Callbacks.
     */
    public function handleYouVerify(Request $request)
    {
        $payload = $request->all();
        $signature = $request->header('X-YouVerify-Signature');

        Log::info("YouVerify Webhook Received", ['payload' => $payload]);

        // 1. Verify Signature (Security)
        if (!$this->youVerifyService->verifyWebhookSignature($signature, $payload)) {
            Log::warning("YouVerify Webhook Signature Mismatch");
            // return response()->json(['message' => 'Invalid signature'], 401); 
        }

        $event = $request->input('event');
        $data = $request->input('data', []);
        $transactionId = $data['id'] ?? null;

        // 2. Find the verification record
        $verification = KycVerification::where('transaction_id', $transactionId)->first();

        if (!$verification) {
            Log::error("KycVerification record not found for transaction: " . $transactionId);
            return response()->json(['status' => 'ignored'], 200);
        }

        $user = $verification->user;

        // 3. Handle Events
        switch ($event) {
            case 'vform.completed':
            case 'identity.verified':
                $verification->update([
                    'status' => 'verified',
                    'data' => $data
                ]);
                
                if ($user) {
                    // Update user's tier if the completed verification is higher than current
                    if ($verification->level > $user->kyc_tier) {
                        $user->update([
                            'kyc_tier' => $verification->level,
                            'kyc_verified' => 1 // Mark as verified generally as well
                        ]);
                        Log::info("User KYC Upgraded to Level $verification->level: " . $user->id);
                    }
                }
                break;

            case 'identity.failed':
                $verification->update([
                    'status' => 'failed',
                    'data' => $data
                ]);
                
                Log::info("User KYC Failed via Webhook for Level $verification->level, User: " . ($user->id ?? 'unknown'));
                break;

            default:
                Log::info("Unhandled YouVerify Event: " . $event);
        }

        return response()->json(['status' => 'success'], 200);
    }

    /**
     * Handle SafeHaven Settlement Webhook Callbacks.
     */
    public function handleSafeHaven(Request $request)
    {
        try {
            $processed = $this->safeHavenService->handleSettlement($request->all());

            return response()->json([
                'status' => $processed ? 'success' : 'ignored',
            ], 200);
        } catch (Exception $e) {
            return response()->json(['status' => 'error', 'message' => $e->getMessage()], 500);
        }
    }
}
