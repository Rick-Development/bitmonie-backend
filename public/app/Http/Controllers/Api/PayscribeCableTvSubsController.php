<?php

namespace App\Http\Controllers\API;

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


class PayscribeCableTvSubsController extends Controller
{
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
            $response = json_decode($this->cableTVSubHelper->payCableTV($data), true);

            if ($response['status'] === true) {
                $user = auth()->user();
                $user_balance = UserWallet::where('user_id', $user->id)
                    ->where('currency_code', 'NGN')
                    ->value('balance');

                $amount = (int) $response['message']['details']['amount'];
                if ($user_balance < $amount) {
                    return response()->json([
                        'status' => 'error',
                        'message' => 'Insufficient balance'
                    ], 500);
                }

                $this->payscribeBalanceHelper->createTransaction($data, $response, $this->billType);

                // Notify User
                auth()->user()->notify(new BillPaymentNotification(
                    'Cable TV',
                    $data['amount'],
                    $data['service'],
                    'Successful',
                    $response['message']['details']['ref'] ?? $referenceIdString
                ));

            }
            return $response;
        } catch (\Exception $e) {
            return response()->json([
                'status' => 'error',
                'message' => $e->getMessage()
            ]);
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
            // $validateBalance = $this->payscribeBalanceHelper->validateBalance($data['amount']);
            // if (!!$validateBalance) {
            //     return $validateBalance;
            // }
            $response = json_decode($this->cableTVSubHelper->topupCableTV($data), true);

            if ($response['status'] === true) {
                $this->payscribeBalanceHelper->createTransaction($data, $response, $this->billType);

                // Notify User
                auth()->user()->notify(new BillPaymentNotification(
                    'Cable TV Topup',
                    $data['amount'],
                    $data['service'],
                    'Successful',
                    $response['message']['details']['ref'] ?? $referenceIdString
                ));
            }
            return $response;
        } catch (\Exception $e) {
            return response()->json([
                'status' => 'error',
                'message' => $e->getMessage()
            ]);
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
