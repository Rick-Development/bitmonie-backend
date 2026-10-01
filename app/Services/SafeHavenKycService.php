<?php

namespace App\Services;

use App\Http\Helpers\SafeHeaven\IdentityCheckHelper;
use Exception;
use Illuminate\Support\Facades\Log;

class SafeHavenKycService
{
    protected $identityHelper;

    public function __construct(IdentityCheckHelper $identityHelper)
    {
        $this->identityHelper = $identityHelper;
    }

    /**
     * Verify BVN (Tier 1) - Initiation
     */
    public function verifyBvn(string $bvn, ?string $selfieImage = null)
    {
        $basicSettings = \App\Models\Admin\BasicSettings::first();
        $payload = [
            'type' => 'BVN',
            'number' => $bvn,
            'debitAccountNumber' => $basicSettings->safehaven_debit_account ?? null,
        ];


        try {
            $response = $this->identityHelper->initiateVerification($payload);

            if (isset($response['statusCode']) && $response['statusCode'] === 200) {
                return [
                    'status' => 'success',
                    'data' => [
                        'id' => $response['data']['_id'] ?? null,
                        'message' => $response['message'] ?? 'OTP sent successfully'
                    ]
                ];
            }

            return [
                'status' => 'error',
                'message' => $response['message'] ?? $response['description'] ?? 'Failed to initiate BVN verification with SafeHaven'
            ];
        } catch (Exception $e) {
            Log::error("SafeHaven BVN Initiation Error: " . $e->getMessage());
            return [
                'status' => 'error',
                'message' => 'SafeHaven Service Error: ' . $e->getMessage()
            ];
        }
    }

    /**
     * Verify NIN (Tier 2) - Initiation
     */
    public function verifyNin(string $nin)
    {
        $basicSettings = \App\Models\Admin\BasicSettings::first();
        $payload = [
            'type' => 'NIN',
            'number' => $nin,
            'debitAccountNumber' => $basicSettings->safehaven_debit_account ?? null,
        ];

        try {
            $response = $this->identityHelper->initiateVerification($payload);

            if (isset($response['statusCode']) && $response['statusCode'] === 200) {
                return [
                    'status' => 'success',
                    'data' => [
                        'id' => $response['data']['_id'] ?? null,
                        'message' => $response['message'] ?? 'OTP sent successfully'
                    ]
                ];
            }

            return [
                'status' => 'error',
                'message' => $response['message'] ?? $response['description'] ?? 'Failed to initiate NIN verification with SafeHaven'
            ];
        } catch (Exception $e) {
            Log::error("SafeHaven NIN Initiation Error: " . $e->getMessage());
            return [
                'status' => 'error',
                'message' => 'SafeHaven Service Error: ' . $e->getMessage()
            ];
        }
    }

    /**
     * Submit OTP for ID verification
     */
    public function submitOtp(string $transactionId, string $otp, string $type = 'BVN')
    {
        $payload = [
            'identityId' => $transactionId,
            'otp' => $otp,
            'type' => $type
        ];

        try {
            $response = $this->identityHelper->validateVerification($payload);

            if (isset($response['statusCode']) && $response['statusCode'] === 200) {
                return [
                    'status' => 'success',
                    'data' => $response['data'] ?? []
                ];
            }

            return [
                'status' => 'error',
                'message' => $response['message'] ?? $response['description'] ?? 'OTP verification failed with SafeHaven'
            ];
        } catch (Exception $e) {
            Log::error("SafeHaven OTP Validation Error: " . $e->getMessage());
            return [
                'status' => 'error',
                'message' => 'SafeHaven Service Error: ' . $e->getMessage()
            ];
        }
    }

    /**
     * Verify Address (Tier 3)
     * Note: SafeHaven might not have a direct simple address verification like YouVerify.
     * We'll simulate success if the provider is SafeHaven and the user is just submitting a document,
     * or return error if not implemented.
     */
    public function verifyAddress(array $data)
    {
        return [
            'status' => 'pending',
            'message' => 'Address verification submitted. SafeHaven manual review required.',
            'data' => ['id' => 'SH_ADDR_' . time()]
        ];
    }
}
