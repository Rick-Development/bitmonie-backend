<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Helpers\Payscribe\BillsPayments\ElectricityBillsHelper;
use App\Http\Helpers\Payscribe\PayscribeBalanceHelper;
use App\Http\Controllers\API\BillPurchaseController;
use App\Http\Helpers\Payscribe\BillsPayments\BillPaymentHelper;
use App\Models\Transaction;
use Illuminate\Http\Request;
use Illuminate\Support\Str;
use App\Traits\Notify;


class PayscribeElectricityBillsController extends Controller
{
    use Notify;

    private $billType = 'Electricity Bill';

    public function __construct(private ElectricityBillsHelper $electricityBillsHelper, private PayscribeBalanceHelper $payscribeBalanceHelper, private BillPaymentHelper $billPaymentHelper) {}


    public function validateElectricity(Request $request) {
        $validator = \Validator::make($request->all(), [
            'meter_number' => 'required | string',
            "meter_type" => 'required | string',
            "amount" => 'required | string',
            "service" => 'required | string',
        ]);

        if ($validator->fails()) {
            return response()->json([
                'status' => false,
                'message' => 'Validation failed',
                'errors' => $validator->errors(),
            ], 422);
        }

        $data = $request->only([
            'meter_number',
            "meter_type",
            "amount",
            "service"
        ]);
        /// No transaction id for validate
        try{

            $response = json_decode($this->electricityBillsHelper->validateElectricity($data), true);
            return $response;
        }
        catch(\Exception $e){
            return [
                'status' => 'error',
                'message' => $e->getMessage()
            ];
        }


    }

    public function payElectricity(Request $request) {
        $validator = \Validator::make($request->all(), [
            'meter_number' => 'required | string',
            "meter_type" => 'required | string',
            "amount" => 'required | string',
            "service" => 'required | string',
            "phone" => 'required | string',
            "customer_name" => 'required | string',
        ]);

        if ($validator->fails()) {
            return response()->json([
                'status' => false,
                'message' => 'Validation failed',
                'errors' => $validator->errors(),
            ], 422);
        }
        $data = $request->only([
            'meter_number',
            "meter_type",
            "amount",
            "service",
            "phone",
            "customer_name",
        ]);

        $referenceId = Str::uuid();
        $referenceIdString = (string) $referenceId . '-auto_bill';
        $data = array_merge($data, ['ref' => $referenceIdString]);

        try {
            $validateBalance = $this->payscribeBalanceHelper->validateBalance($data['amount']);

            if(!!$validateBalance){
                return $validateBalance;
            }
            $response = json_decode($this->electricityBillsHelper->payElectricity($data), true);

            if($response['status'] === true){
                $this->payscribeBalanceHelper->createTransaction($data, $response, $this->billType);
                $user = auth()->user();
                $token = $response['message']['details']['token'] ?? 'N/A';
                
                // Notify User using the Notify trait
                $this->sendNotification(
                    user: $user,
                    templateKey: 'ELECTRICITY_BILL_SUCCESS',
                    params: [
                        'user' => $user->firstname,
                        'amount' => number_format($data['amount']),
                        'service' => $data['service'],
                        'token' => $token,
                        'meter_number' => $data['meter_number'],
                        'reference' => $response['message']['details']['trans_id'] ?? $referenceIdString,
                        'status' => 'Successful',
                    ],
                    channels: ['mail', 'inapp'],
                    options: [
                        'referenceId' => $response['message']['details']['trans_id'] ?? $referenceIdString,
                    ]
                );
            }

            return $response;
        }
        catch(\Exception $e){
            return response()->json([
                'status' => 'error',
                'message' => $e->getMessage()
            ]);
        }
    }

    public function requeryTransaction(Request $request) {
        $data = $request->validate([
            'transaction_id' => 'required | string',
        ]);
        try {

            $response = json_decode($this->electricityBillsHelper->requeryTransaction($data['transaction_id']), true);
            return $response;
        }
        catch(\Exception $e){
            return response()->json([
                'status' => 'error',
                'message' => $e->getMessage()
            ]);
        }
    }

    public function autoPayElectricity(Request $request) {
        $data = $request->validate([
            'meter_number' => 'required | string',
            "meter_type" => 'required | string',
            "amount" => 'required | string',
            "service" => 'required | string',
            "phone" => 'required | string',
            "customer_name" => 'required | string',
        ]);

        // Validate user balance before proceeding
        $validateBalance = $this->payscribeBalanceHelper->validateBalance($data['amount']);

        if(!!$validateBalance){
                return $validateBalance; // from safe heaven balance
        }

        // Transfer to safe heaven
        $response = $this->billPaymentHelper->getBillRequest($data, 'purchase_electricity');
        return $response;
    }

    public function payElectricityBill(array $data) {

        $response = json_decode($this->electricityBillsHelper->payElectricity($data), true);

        if($response['status'] === true){
            $this->createTransaction($data, $response, $this->billType);
            $user = auth()->user();
            $token = $response['message']['details']['token'] ?? 'N/A';

            // Notify User using the Notify trait
            $this->sendNotification(
                user: $user,
                templateKey: 'ELECTRICITY_BILL_SUCCESS',
                params: [
                    'user' => $user->firstname,
                    'amount' => number_format($data['amount']),
                    'service' => $data['service'] ?? 'Electricity',
                    'token' => $token,
                    'meter_number' => $data['meter_number'] ?? 'N/A',
                    'reference' => $response['message']['details']['trans_id'] ?? 'N/A',
                    'status' => 'Successful',
                ],
                channels: ['mail', 'inapp'],
                options: [
                    'referenceId' => $response['message']['details']['trans_id'] ?? 'N/A',
                ]
            );
        }

        return $response;
    }



    private function createTransaction($request, $response, $modelPath) {
        $balance = auth()->user()->account_balance - $request['amount'];
        $transId = $response['message']['details']['trans_id'] ?? null;
        Transaction::create([
            'transactional_type' => $modelPath,
            'user_id' => auth()->user()->id,
            'amount' => $request['amount'],
            'currency' => 'NGN',
            'balance' => $balance,
            'charge' => $request['amount'],
            'trx_type' => '-',
            'remarks' => $response['description'] ?? 'Electricity Bill Payment',
            'trx_id' => $transId,
            'transaction_status' => 'proccessing',
        ]);

        $this->payscribeBalanceHelper->updateUserBalance($balance);
    }

}