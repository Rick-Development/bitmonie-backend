<?php

namespace App\Http\Controllers\Api\V1\User;

use App\Http\Controllers\Controller;
use App\Http\Helpers\Response;
use Illuminate\Http\Request;

class P2PKycController extends Controller
{
    /**
     * Get P2P KYC requirements and user's current status
     */
    public function status()
    {
        $user = auth()->user();
        $merchantApproved = $user->merchant_status === 'approved';

        $kycLevels = [
            0 => [
                'level' => 0,
                'name' => 'Unverified',
                'can_trade' => false,
                'can_create_ads' => false,
                'daily_limit' => 0,
                'requirements' => ['Email verification']
            ],
            1 => [
                'level' => 1,
                'name' => 'Basic',
                'can_trade' => true,
                'can_create_ads' => false,
                'daily_limit' => 100000, // NGN or equivalent
                'requirements' => ['Email', 'Phone', 'Basic Info']
            ],
            2 => [
                'level' => 2,
                'name' => 'Verified',
                'can_trade' => true,
                'can_create_ads' => false,
                'daily_limit' => 1000000,
                'requirements' => ['KYC Level 1', 'ID Verification', 'Selfie', 'Merchant Approval']
            ],
            3 => [
                'level' => 3,
                'name' => 'Merchant',
                'can_trade' => true,
                'can_create_ads' => $merchantApproved,
                'daily_limit' => -1, // Unlimited
                'requirements' => ['KYC Level 2', 'Business Verification', 'Admin Approval']
            ]
        ];

        $currentTierInfo = $kycLevels[$user->kyc_tier ?? 0] ?? $kycLevels[0];
        $currentTierInfo['can_create_ads'] = $merchantApproved;

        $currentLevel = $user->kyc_tier ?? 0;

        return Response::successResponse('P2P KYC status', [
            'current_level' => $currentLevel,
            'current_tier_info' => $currentTierInfo,
            'all_tiers' => $kycLevels,
            'kyc_status' => $user->kyc_status ?? 'unverified',
            'can_upgrade' => $currentLevel < 3,
            'merchant_status' => $user->merchant_status ?? 'none',
        ]);
    }

    /**
     * Get P2P trading limits based on KYC level
     */
    public function limits()
    {
        $user = auth()->user();
        $kycLevel = $user->kyc_tier ?? 0;

        $limits = [
            0 => [
                'daily_buy_limit' => 0,
                'daily_sell_limit' => 0,
                'single_order_max' => 0,
                'can_create_ads' => false
            ],
            1 => [
                'daily_buy_limit' => 100000,
                'daily_sell_limit' => 100000,
                'single_order_max' => 50000,
                'can_create_ads' => false
            ],
            2 => [
                'daily_buy_limit' => 1000000,
                'daily_sell_limit' => 1000000,
                'single_order_max' => 500000,
                'can_create_ads' => false
            ],
            3 => [
                'daily_buy_limit' => -1, // Unlimited
                'daily_sell_limit' => -1,
                'single_order_max' => -1,
                'can_create_ads' => $user->merchant_status === 'approved'
            ]
        ];

        return Response::successResponse('P2P trading limits', [
            'kyc_level' => $kycLevel,
            'limits' => $limits[$kycLevel] ?? $limits[0],
            'currency' => 'NGN'
        ]);
    }

    /**
     * Check if user can perform specific P2P action
     */
    public function checkPermission(Request $request)
    {
        $user = auth()->user();
        $kycLevel = $user->kyc_tier ?? 0;
        $action = $request->input('action'); // 'trade', 'create_ad', 'merchant'

        // New Merchant Logic
        if ($action === 'merchant') {
            if ($user->merchant_status !== 'approved') {
                return Response::errorResponse(
                    'Merchant verification required',
                    [
                        'action' => 'merchant',
                        'allowed' => false,
                        'merchant_status' => $user->merchant_status ?? 'none',
                        'message' => $this->getMerchantStatusMessage($user->merchant_status)
                    ],
                    403
                );
            }
            
            return Response::successResponse('Permission granted', [
                'action' => 'merchant',
                'allowed' => true,
                'merchant_status' => 'approved',
                'kyc_level' => $kycLevel
            ]);
        }

        $permissions = [
            'trade' => $kycLevel >= 1,
            'create_ad' => $user->merchant_status === 'approved',
            // 'merchant' handled above
        ];

        $allowed = $permissions[$action] ?? false;

        if (!$allowed) {
            $requiredLevel = match($action) {
                'trade' => 1,
                'create_ad' => 3,
                default => 0
            };

            $message = $action === 'create_ad'
                ? 'Approved merchant status is required to create ads'
                : "KYC Level {$requiredLevel} required for this action";

            return Response::errorResponse(
                $message,
                [
                    'current_level' => $kycLevel,
                    'required_level' => $requiredLevel,
                    'action' => $action,
                    'merchant_status' => $user->merchant_status ?? 'none',
                ],
                403
            );
        }

        return Response::successResponse('Permission granted', [
            'action' => $action,
            'allowed' => true,
            'kyc_level' => $kycLevel
        ]);
    }

    private function getMerchantStatusMessage($status)
    {
        return match($status) {
            'none' => 'Please apply for merchant status',
            'pending' => 'Your merchant application is under review',
            'rejected' => 'Your merchant application was rejected',
            default => 'Unknown status'
        };
    }
}
