<?php

namespace App\Http\Controllers\Api;

use App\Models\UserWallet;
use App\Models\Transaction;
use Illuminate\Support\Str;
use Illuminate\Http\Request;
use App\Http\Controllers\Controller;
use App\Http\Controllers\API\BillPurchaseController;
use App\Http\Helpers\Payscribe\PayscribeBalanceHelper;
use App\Http\Helpers\Payscribe\BillsPayments\BillPaymentHelper;
use App\Http\Helpers\Payscribe\BillsPayments\CableTVSubscriptionHelper;
use App\Notifications\User\BillPaymentNotification;

use App\Traits\Notify;

class PayscribeCableTvSubsController extends Controller
{
    use Notify;
    private $billType = 'Cable Tv';

    public function __construct(private CableTVSubscriptionHelper $cableTVSubHelper, private PayscribeBalanceHelper $payscribeBalanceHelper, private BillPaymentHelper $billPaymentHelper)
    {
    }


    public function fetchBouquents(Request $request)
    {
        $validator = \Validator::make($request->all(), [
            'service' => [
                'required',
                'string',
                function ($attribute, $value, $fail) {
                    if (!in_array($value, ['dstv', 'gotv', 'startimes'])) {
                        $fail($value . ' value is invalid. Please use either dstv, gotv or startimes');
                    }
                }
            ],
        ]);

        if ($validator->fails()) {
            return response()->json([
                'status' => false,
                'message' => 'Validation failed',
                'errors' => $validator->errors()
            ], 422);
        }

        $data = $request->only('service');
        try {
            $response = json_decode($this->cableTVSubHelper->fetchBouquets($data['service']), true);
            return $response;
        } catch (\Exception $e) {
            return response()->json([
                'status' => 'error',
                'message' => $e->getMessage()
            ]);
        }
    }

    public function validateSmartCardNumber(Request $request)
    {
        $validator = \Validator::make($request->all(), [
            'service' => 'required|string',
            'account' => 'required|string',
            'month' => 'sometimes | string',
            'plan_id' => 'required | string',
        ]);

        if ($validator->fails()) {
            return response()->json([
                'status' => false,
                'message' => 'Validation failed',
                'errors' => $validator->errors()
            ], 422);
        }

        $data = $request->only('service', 'account', 'month', 'plan_id');
        try {
            return json_decode($this->cableTVSubHelper->validateSmartCardNumber($data), true);
        } catch (\Exception $e) {
            return response()->json([
                'status' => 'error',
                'message' => $e->getMessage()
            ]);
        }
    }

    public function payCableTv(Request $request)
    {
        $validator = \Validator::make($request->all(), [
            'plan_id' => 'required|string',
            'customer_name' => 'required|string',
            'account' => 'required|string',
            'service' => 'required|string',
            'phone' => 'sometimes|string',
            'email' => 'sometimes|string',
            'month' => 'sometimes|integer',
            'amount' => 'sometimes|integer',
        ]);

        if ($validator->fails()) {
            return response()->json([
                'status' => false,
                'message' => 'Validation failed',
                'errors' => $validator->errors(),
            ], 422);
        }

        $data = $request->only([
            'plan_id',
            'customer_name',
            'account',
            'service',
            'phone',
            'email',
            'month',
            'amount',
        ]);

        // Generate a UUID
        $referenceId = Str::uuid();
        // Convert to string if needed
        $referenceIdString = (string) $referenceId . '-auto_bill';
        $data = array_merge($data, ['ref' => $referenceIdString]);

try {
            // 1. Validate the balance first using your helper
            $amountToCheck = $request->input('amount') ?? 0; // fallback if amount is optional in request
            $validateBalance = $this->payscribeBalanceHelper->validateBalance($amountToCheck);
            
            if ($validateBalance) {
                return $validateBalance;
            }

            // 2. Now perform the vendor call safely
            $response = json_decode($this->cableTVSubHelper->payCableTV($data), true);

            if ($response['status'] === true) {
    $this->payscribeBalanceHelper->createTransaction($data, $response, $this->billType);

    // Notify User via your custom Notify trait
    $this->sendNotification(
        user: auth()->user(),
        templateKey: 'CABLE_TV_SUCCESS', // Ensure this matches your notification_templates key
        params: [
            'user' => auth()->user()->firstname,
            'amount' => number_format($data['amount'] ?? 0),
            'service' => $data['service'],
            'account' => $data['account'],
            'reference' => $response['message']['details']['ref'] ?? $referenceIdString,
            'status' => 'Successful',
        ],
        channels: ['mail', 'inapp'] // Choose the channels you want
    );
}
            
            return $response;
        } catch (\Exception $e) {
            return response()->json([
                'status' => 'error',
                'message' => $e->getMessage()
            ], 500);
        }
    }
    public function autoPayCableTv(Request $request)
    {
        $data = request()->validate([
            'plan_id' => 'required | string',
            'customer_name' => 'required | string',
            'account' => 'required | string',
            'service' => 'required | string',
            'phone' => 'required | string',
            'email' => 'required | string',
            'month' => 'required | integer',
            'amount' => 'required | integer',
        ]);

        // Validate user balance before proceeding
        $validateBalance = $this->payscribeBalanceHelper->validateBalance($data['amount']);

        if (!!$validateBalance) {
            return $validateBalance; // form safe heaven balance
        }

        // Transfer to safe heaven
        $response = $this->billPaymentHelper->getBillRequest($data, 'purchase_cable_tv');
        return $response;
    }
    public function topUpCableTv(Request $request)
    {
        $validator = \Validator::make($request->all(), [
            // 'plan_id' => 'required|string',
            'customer_name' => 'required|string',
            'account' => 'required|string',
            'service' => 'required|string',
            'phone' => 'sometimes|string',
            'email' => 'sometimes|string',
            'month' => 'sometimes|integer',
            'amount' => 'sometimes|integer',
        ]);

        if ($validator->fails()) {
            return response()->json([
                'status' => false,
                'message' => 'Validation failed',
                'errors' => $validator->errors(),
            ], 422);
        }

        $data = $request->only([
            // 'plan_id',
            'customer_name',
            'account',
            'service',
            'phone',
            'email',
            'month',
            'amount',
        ]);

        // Generate a UUID
        $referenceId = Str::uuid();
        // Convert to string if needed
        $referenceIdString = (string) $referenceId . '-auto_bill';
        $data = array_merge($data, ['ref' => $referenceIdString]);

        try {
            // Optional: Un-comment balance check if needed for topups too
            $amountToCheck = $data['amount'] ?? 0;
            $validateBalance = $this->payscribeBalanceHelper->validateBalance($amountToCheck);
            if ($validateBalance) {
                return $validateBalance;
            }

            $response = json_decode($this->cableTVSubHelper->topupCableTV($data), true);

            if ($response['status'] === true) {
                $this->payscribeBalanceHelper->createTransaction($data, $response, $this->billType);

                // Notify User using the Notify trait system
                $user = auth()->user();
                if ($user) {
                    $this->sendNotification(
                        user: $user,
                        templateKey: 'CABLE_TV_TOPUP_SUCCESS', // Ensure this template key exists in your DB
                        params: [
                            'user' => $user->firstname,
                            'amount' => number_format($data['amount'] ?? 0),
                            'service' => $data['service'],
                            'account' => $data['account'],
                            'reference' => $response['message']['details']['ref'] ?? $referenceIdString,
                            'status' => 'Successful',
                        ],
                        channels: ['mail', 'inapp'],
                        options: [
                            'referenceId' => $response['message']['details']['ref'] ?? $referenceIdString,
                        ]
                    );
                }
            }
            return $response;
        } catch (\Exception $e) {
            return response()->json([
                'status' => 'error',
                'message' => $e->getMessage()
            ], 500);
        }
    }
    public function autoTopUpCableTv(Request $request)
    {
        $data = $request->validate([
            'amount' => 'required | string',
            'customer_name' => 'required | string',
            'account' => 'required | string',
            'service' => 'required | string',
            'phone' => 'required | string',
            'email' => 'required | string',
            'month' => 'required | string',
        ]);

        // Validate user balance before proceeding
        $validateBalance = $this->payscribeBalanceHelper->validateBalance($data['amount']);

        if (!!$validateBalance) {
            return $validateBalance; // form safe heaven balance
        }

        // Transfer to safe heaven
        $response = $this->billPaymentHelper->getBillRequest($data, 'purchase_top_cable_tv');
        return $response;
    }


}
