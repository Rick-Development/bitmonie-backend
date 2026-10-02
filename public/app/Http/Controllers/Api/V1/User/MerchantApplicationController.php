<?php

namespace App\Http\Controllers\Api\V1\User;

use App\Http\Controllers\Controller;
use App\Http\Helpers\Response;
use App\Models\MerchantApplication;
use App\Services\MerchantSecurityDepositService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Validator;
use Throwable;

class MerchantApplicationController extends Controller
{
    protected $merchantSecurityDepositService;

    public function __construct(MerchantSecurityDepositService $merchantSecurityDepositService)
    {
        $this->merchantSecurityDepositService = $merchantSecurityDepositService;
    }

    /**
     * Check if user is eligible to apply for merchant status
     */
    public function checkEligibility()
    {
        $user = auth()->user();

        try {
            $eligibility = $this->merchantSecurityDepositService->checkEligibility($user);
        } catch (Throwable $e) {
            $status = in_array($e->getCode(), [400, 404, 409, 422, 502], true) ? $e->getCode() : 400;
            return Response::errorResponse($e->getMessage(), null, $status);
        }

        if (!$eligibility['eligible']) {
            return Response::errorResponse('Insufficient USDT balance', $eligibility, 400);
        }

        return Response::successResponse('You are eligible to apply for merchant status', $eligibility);
    }

    /**
     * Submit merchant application
     */
    public function apply(Request $request)
    {
        $validator = Validator::make($request->all(), [
            'whatsapp' => 'nullable|string|max:20',
            'business_name' => 'nullable|string|max:255',
        ]);

        if ($validator->fails()) {
            return Response::errorResponse('Validation Error', $validator->errors()->all());
        }

        $user = auth()->user();

        try {
            $application = $this->merchantSecurityDepositService->apply($user, $request->only([
                'whatsapp',
                'business_name',
            ]));
        } catch (Throwable $e) {
            $status = in_array($e->getCode(), [400, 404, 409, 422, 502], true) ? $e->getCode() : 400;
            return Response::errorResponse($e->getMessage(), null, $status);
        }

        return Response::successResponse('Merchant application submitted successfully. We will contact you for verification.', [
                'application_id' => $application->id,
                'status' => 'pending',
                'submitted_at' => $application->created_at,
                'security_deposit' => [
                    'amount' => (float) $application->security_deposit_amount,
                    'currency' => strtoupper((string) $application->security_deposit_currency),
                    'status' => $application->security_deposit_status,
                    'reference' => $application->security_deposit_lock_reference,
                ],
            ]);
    }

    /**
     * Check application status
     */
    public function status()
    {
        $user = auth()->user();
        
        $application = MerchantApplication::where('user_id', $user->id)
            ->latest()
            ->first();

        if (!$application) {
            return Response::successResponse('Merchant status', [
                'has_application' => false,
                'status' => 'none',
                'can_apply' => true,
                'message' => 'You have not applied for merchant status yet.'
            ]);
        }

        $displayStatus = $this->merchantSecurityDepositService->getDisplayStatus($application);
        $data = [
            'has_application' => true,
            'status' => $displayStatus,
            'submitted_at' => $application->created_at,
            'admin_notes' => $application->admin_notes,
            'security_deposit' => [
                'amount' => (float) $application->security_deposit_amount,
                'currency' => strtoupper((string) $application->security_deposit_currency),
                'status' => $application->security_deposit_status,
                'locked_at' => $application->security_deposit_locked_at,
                'lock_reference' => $application->security_deposit_lock_reference,
                'released_at' => $application->security_deposit_released_at,
                'release_reference' => $application->security_deposit_release_reference,
                'release_reason' => $application->security_deposit_release_reason,
            ],
        ];

        if ($displayStatus === 'approved') {
            $blockers = $this->merchantSecurityDepositService->getDeactivationBlockers($user);
            $data['approved_at'] = $application->reviewed_at;
            $data['can_deactivate'] = ($blockers['active_orders_count'] ?? 0) === 0
                && ($blockers['pending_appeals_count'] ?? 0) === 0;
            $data['deactivation_blockers'] = $blockers;
            $data['message'] = 'Congratulations! You are a verified merchant.';
        } elseif ($displayStatus === 'deactivated') {
            $data['deactivated_at'] = $application->merchant_deactivated_at;
            $data['can_apply'] = true;
            $data['message'] = 'Your merchant status has been deactivated and your security deposit has been released if one was locked.';
        } elseif ($displayStatus === 'rejected') {
            $data['rejected_at'] = $application->reviewed_at;
            $data['can_reapply'] = true;
            $data['message'] = 'Your application was rejected. See admin notes for details.';
        } else {
            $data['message'] = 'Your application is under pending review. We will contact you.';
        }

        return Response::successResponse('Merchant application status', $data);
    }

    public function deactivate(Request $request)
    {
        $validator = Validator::make($request->all(), [
            'reason' => 'nullable|string|max:1000',
        ]);

        if ($validator->fails()) {
            return Response::errorResponse('Validation Error', $validator->errors()->all());
        }

        try {
            $result = $this->merchantSecurityDepositService->deactivate(
                auth()->user(),
                $request->input('reason')
            );
        } catch (Throwable $e) {
            $status = in_array($e->getCode(), [400, 404, 409, 422, 502], true) ? $e->getCode() : 400;
            return Response::errorResponse($e->getMessage(), null, $status);
        }

        /** @var MerchantApplication $application */
        $application = $result['application'];

        return Response::successResponse('Merchant status deactivated successfully.', [
            'status' => 'deactivated',
            'deactivated_at' => $application->merchant_deactivated_at,
            'offline_ads_count' => $result['offline_ads_count'],
            'security_deposit' => [
                'amount' => (float) $application->security_deposit_amount,
                'currency' => strtoupper((string) $application->security_deposit_currency),
                'status' => $application->security_deposit_status,
                'released_at' => $application->security_deposit_released_at,
                'release_reference' => $application->security_deposit_release_reference,
                'release_reason' => $application->security_deposit_release_reason,
            ],
        ]);
    }
}
