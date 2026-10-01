<?php

namespace App\Http\Controllers\Api\User;

use App\Http\Controllers\Controller;
use App\Http\Helpers\Response;
use App\Services\YouVerifyService;
use App\Models\KycVerification;
use App\Models\Tier;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Validator;
use Illuminate\Support\Facades\Log;

class KycController extends Controller
{
    protected $youVerifyService;
    protected $safeHavenKycService;
    protected $graphService;

    public function __construct(YouVerifyService $youVerifyService, \App\Services\SafeHavenKycService $safeHavenKycService, \App\Services\GraphService $graphService)
    {
        $this->youVerifyService = $youVerifyService;
        $this->safeHavenKycService = $safeHavenKycService;
        $this->graphService = $graphService;
    }

    /**
     * Get the active KYC service provider based on admin settings.
     */
    protected function getKycService()
    {
        $basicSettings = \App\Models\Admin\BasicSettings::first();
        if ($basicSettings->kyc_provider === 'safehaven') {
            return $this->safeHavenKycService;
        }
        return $this->youVerifyService;
    }

    /**
     * Get all KYC Tiers with requirements and descriptions.
     */
    public function tiers()
    {
        $tiers = Tier::active()->orderBy('level')->get()->values();
        return Response::success($tiers, null);
    }

    /**
     * Get the current user's KYC tier.
     */
    public function userTier()
    {
        $user = auth()->user();
        $tier = Tier::where('level', $user->kyc_tier)->first();
        
        return Response::success([
            'current_level' => $user->kyc_tier,
            'tier' => $tier,
            'kyc_verified' => $user->kyc_verified
        ]);
    }

    /**
     * Tier 1 Initiation: BVN + Selfie
     */
    public function tier1Initiate(Request $request)
    {
        $validator = Validator::make($request->all(), [
            'bvn' => 'required|string|size:11',
            'selfie' => 'required|image|mimes:jpeg,png,jpg', // Accept image file
        ]);

        if ($validator->fails()) {
            return Response::error($validator->errors()->all());
        }

        try {
            $user = auth()->user();
            if ($user->kyc_tier >= 1) {
                return Response::error(['You are already at Tier 1 or higher']);
            }

            // Convert uploaded image to base64/data-uri
            $selfieDataUri = null;
            if ($request->hasFile('selfie')) {
                $image = $request->file('selfie');
                $mimeType = $image->getMimeType();
                $base64 = base64_encode(file_get_contents($image->getRealPath()));
                $selfieDataUri = "data:{$mimeType};base64,{$base64}";
            }

            $service = $this->getKycService();
            $result = $service->verifyBvn($request->bvn, $selfieDataUri);

            if (isset($result['status']) && ($result['status'] === 'success' || $result['status'] === 'pending')) {
                $transactionId = $result['data']['id'] ?? $result['data']['_id'] ?? null;
                $reference = 'KYC_T1_' . $user->id . '_' . time();

                KycVerification::create([
                    'user_id' => $user->id,
                    'reference' => $reference,
                    'transaction_id' => $transactionId,
                    'level' => 1,
                    'status' => 'pending',
                    'data' => array_merge(['bvn' => $request->bvn, 'provider' => $service instanceof \App\Services\SafeHavenKycService ? 'safehaven' : 'youverify'], (array)($result['data'] ?? []))
                ]);

                return Response::success([
                    'message' => $result['message'] ?? 'BVN initiation successful. OTP sent to your registered phone number.',
                    'transaction_id' => $transactionId,
                    'reference' => $reference
                ]);
            }

            Log::warning("BVN Initiation Failed", ['result' => $result]);
            return Response::error([$result['message'] ?? 'Failed to initiate BVN verification']);

        } catch (\Exception $e) {
            Log::error("Tier 1 Initiation Error: " . $e->getMessage());
            return Response::error(['Failed to initiate Tier 1: ' . $e->getMessage()]);
        }
    }

    /**
     * Tier 1 Verification: OTP
     */
    public function tier1Verify(Request $request)
    {
        $validator = Validator::make($request->all(), [
            'transaction_id' => 'required|string',
            'otp' => 'required|string',
        ]);

        if ($validator->fails()) {
            return Response::error($validator->errors()->all());
        }

        try {
            $user = auth()->user();
            $verification = KycVerification::where('transaction_id', $request->transaction_id)
                ->where('user_id', $user->id)
                ->first();

            if (!$verification) {
                return Response::error(['Invalid transaction ID']);
            }

            $service = $this->getKycService();
            if ($service instanceof \App\Services\SafeHavenKycService) {
                $result = $service->submitOtp($request->transaction_id, $request->otp, 'BVN');
            } else {
                $result = $service->submitOtp($request->transaction_id, $request->otp);
            }

            if (isset($result['status']) && $result['status'] === 'success') {
                $user->update([
                    'kyc_tier' => 1,
                    'kyc_verified' => 1
                ]);

                $verification->update([
                    'status' => 'verified',
                    'data' => array_merge((array)$verification->data, [
                        'verification_result' => $result['data'] ?? null,
                        'otp' => $request->otp
                    ])
                ]);

                return Response::success(['message' => 'Tier 1 verification successful!']);
            }

            return Response::error([$result['message'] ?? 'OTP verification failed']);

        } catch (\Exception $e) {
            Log::error("Tier 1 Verification Error: " . $e->getMessage());
            return Response::error(['OTP verification failed: ' . $e->getMessage()]);
        }
    }

    /**
     * Tier 2 Initiation: NIN + DOB
     */
    public function tier2Initiate(Request $request)
    {
        $validator = Validator::make($request->all(), [
            'nin' => 'required|string',
            'dob' => 'required|date',
        ]);

        if ($validator->fails()) {
            return Response::error($validator->errors()->all());
        }

        try {
            $user = auth()->user();
            if ($user->kyc_tier < 1) {
                return Response::error(['Please complete Tier 1 first']);
            }
            if ($user->kyc_tier >= 2) {
                return Response::error(['You are already at Tier 2 or higher']);
            }

            $service = $this->getKycService();
            $result = $service->verifyNin($request->nin);

            if (isset($result['status']) && ($result['status'] === 'success' || $result['status'] === 'pending')) {
                $transactionId = $result['data']['id'] ?? $result['data']['_id'] ?? null;
                $reference = 'KYC_T2_' . $user->id . '_' . time();

                KycVerification::create([
                    'user_id' => $user->id,
                    'reference' => $reference,
                    'transaction_id' => $transactionId,
                    'level' => 2,
                    'status' => 'pending',
                    'data' => array_merge(['dob' => $request->dob, 'provider' => $service instanceof \App\Services\SafeHavenKycService ? 'safehaven' : 'youverify'], (array)($result['data'] ?? []))
                ]);

                return Response::success([
                    'message' => $result['message'] ?? 'NIN initiation successful. OTP sent to your NIN-linked phone number.',
                    'transaction_id' => $transactionId,
                    'reference' => $reference
                ]);
            }

            return Response::error([$result['message'] ?? 'Failed to initiate NIN verification']);

        } catch (\Exception $e) {
            Log::error("Tier 2 Initiation Error: " . $e->getMessage());
            return Response::error(['Failed to initiate Tier 2: ' . $e->getMessage()]);
        }
    }

    /**
     * Tier 2 Verification: OTP
     */
    public function tier2Verify(Request $request)
    {
        // Same logic as tier 1 verify but updates to tier 2
        $validator = Validator::make($request->all(), [
            'transaction_id' => 'required|string',
            'otp' => 'required|string',
        ]);

        if ($validator->fails()) {
            return Response::error($validator->errors()->all());
        }

        try {
            $user = auth()->user();
            $verification = KycVerification::where('transaction_id', $request->transaction_id)
                ->where('user_id', $user->id)
                ->where('level', 2)
                ->first();

            if (!$verification) {
                return Response::error(['Invalid transaction ID for Tier 2']);
            }

            $service = $this->getKycService();
            if ($service instanceof \App\Services\SafeHavenKycService) {
                $result = $service->submitOtp($request->transaction_id, $request->otp, 'NIN');
            } else {
                $result = $service->submitOtp($request->transaction_id, $request->otp);
            }

            if (isset($result['status']) && $result['status'] === 'success') {
                $user->update(['kyc_tier' => 2]);

                $verification->update([
                    'status' => 'verified',
                    'data' => array_merge((array)$verification->data, [
                        'verification_result' => $result['data'] ?? null,
                        'otp' => $request->otp
                    ])
                ]);

                return Response::success(['message' => 'Tier 2 verification successful!']);
            }

            return Response::error([$result['message'] ?? 'OTP verification failed']);

        } catch (\Exception $e) {
            Log::error("Tier 2 Verification Error: " . $e->getMessage());
            return Response::error(['OTP verification failed: ' . $e->getMessage()]);
        }
    }

    /**
     * Tier 3 Submission: Address Verification
     */
    public function tier3Submit(Request $request)
    {
        $validator = Validator::make($request->all(), [
            'street' => 'required|string',
            'city' => 'required|string',
            'state' => 'required|string',
            'postcode' => 'nullable|string',
            'document' => 'required|image|mimes:jpeg,png,jpg,pdf|max:10240', // Accept image/pdf file
        ]);

        if ($validator->fails()) {
            return Response::error($validator->errors()->all());
        }

        try {
            $user = auth()->user();
            if ($user->kyc_tier < 2) {
                return Response::error(['Please complete Tier 2 first']);
            }
            if ($user->kyc_tier >= 3) {
                return Response::error(['You are already at Tier 3']);
            }

            $documentBase64 = null;
            if ($request->hasFile('document')) {
                $file = $request->file('document');
                $documentBase64 = base64_encode(file_get_contents($file->getRealPath()));
            }

            $payload = [
                'firstName' => $user->firstname,
                'lastName' => $user->lastname,
                'address' => [
                    'street' => $request->street,
                    'city' => $request->city,
                    'state' => $request->state,
                    'country' => 'Nigeria'
                ],
                'document' => [
                    'type' => 'UTILITY_BILL',
                    'content' => $documentBase64
                ]
            ];

            $service = $this->getKycService();
            $result = $service->verifyAddress($payload);

            if (isset($result['status']) && ($result['status'] === 'success' || $result['status'] === 'pending')) {
                $reference = 'KYC_T3_' . $user->id . '_' . time();
                
                // -------------------------------------------------------------
                // GRAPH API INTEGRATION: Create Customer Account on Success
                // -------------------------------------------------------------
                try {
                    // 1. Retrieve Tier 2 Data (NIN & DOB)
                    $tier2Verification = KycVerification::where('user_id', $user->id)
                        ->where('level', 2)
                        ->where('status', 'verified')
                        ->latest()
                        ->first();

                    if ($tier2Verification) {
                        $tier2Data = $tier2Verification->data;
                        // Extract NIN and DOB from stored data
                        // Note: Ensure your Tier 2 logic saves these keys in 'data'
                        $nin = $tier2Data['nin'] ?? null;
                        
                        // Some providers might return it inside 'verification_result'
                        if (!$nin && isset($tier2Data['verification_result']['data']['idNumber'])) {
                            $nin = $tier2Data['verification_result']['data']['idNumber'];
                        }
                        
                        $dob = $tier2Data['dob'] ?? null;

                        if ($nin && $dob) {
                            $graphData = [
                                'dob' => $dob,
                                'id_number' => $nin,
                                'id_type' => 'NIN',
                                'address' => $request->street,
                                'city' => $request->city,
                                'state' => $request->state,
                                'zip_code' => $request->postcode ?? '000000',
                            ];

                            // Create Graph Customer
                            $this->graphService->createPerson($user, $graphData);
                            Log::info("Graph Customer created automatically for User {$user->id} during Tier 3 submission.");
                        } else {
                             Log::warning("Could not auto-create Graph Customer: Missing NIN or DOB from Tier 2 record.", ['user_id' => $user->id]);
                        }
                    } else {
                        Log::warning("Could not auto-create Graph Customer: No verified Tier 2 record found.", ['user_id' => $user->id]);
                    }
                } catch (\Exception $e) {
                    // Log error but don't fail the main KYC submission
                    Log::error("Failed to auto-create Graph Customer during Tier 3: " . $e->getMessage());
                }
                // -------------------------------------------------------------

                KycVerification::create([
                    'user_id' => $user->id,
                    'reference' => $reference,
                    'transaction_id' => $result['data']['id'] ?? $result['data']['_id'] ?? null,
                    'level' => 3,
                    'status' => 'pending',
                    'data' => array_merge(['provider' => $service instanceof \App\Services\SafeHavenKycService ? 'safehaven' : 'youverify'], (array)($result['data'] ?? []))
                ]);

                return Response::success([
                    'message' => $result['message'] ?? 'Address verification submitted. It will be reviewed shortly.',
                    'reference' => $reference
                ]);
            }

            return Response::error([$result['message'] ?? 'Failed to submit address verification']);

        } catch (\Exception $e) {
            Log::error("Tier 3 Submission Error: " . $e->getMessage());
            return Response::error(['Failed to submit Tier 3: ' . $e->getMessage()]);
        }
    }
}
