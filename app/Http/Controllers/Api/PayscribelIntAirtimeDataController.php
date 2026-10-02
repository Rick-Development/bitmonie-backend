<?php

namespace App\Http\Controllers\API;

use App\Http\Controllers\Controller;
use App\Http\Helpers\Payscribe\BillsPayments\IntAirtimeDataHelper;
use App\Http\Helpers\Payscribe\PayscribeBalanceHelper;
use App\Http\Controllers\API\BillPurchaseController;
use App\Http\Helpers\Payscribe\BillsPayments\BillPaymentHelper;
use App\Models\Transaction;
use Illuminate\Http\Request;
use Illuminate\Support\Str;
use App\Traits\Notify;

class PayscribelIntAirtimeDataController extends Controller
{
    use Notify;

    private $billType = 'Int Airtime/Data';
    public function __construct(private IntAirtimeDataHelper $intAirtimeDataHelper, private PayscribeBalanceHelper $payscribeBalanceHelper, private BillPaymentHelper $billPaymentHelper){}

    public function IntBillsCountries() {
        try {
            $response = json_decode($this->intAirtimeDataHelper->getIntBillCountries(), true);
            return $response;
        }
        catch(\Exception $e){
            return response()->json([
                'status' => 'error',
                'message' => $e->getMessage()
            ]);
        }
    }

    public function IntBillsProviders(Request $request) {
        $data = $request->validate([
            'iso' => 'required | string',
        ]);
        try {
            $response = json_decode($this->intAirtimeDataHelper->getIntBillProviders($data), true);
            return $response;
        }
        catch(\Exception $e){
            return response()->json([
                'status' => 'error',
                'message' => $e->getMessage()
            ]);
        }
    }

    public function IntBillsProducts(Request $request) {
        $data = $request->validate([
            'iso' => 'required | string',
            'code' => 'required | string',
        ]);
        try {
            $response = json_decode($this->intAirtimeDataHelper->getIntBillProducts($data), true);
            return $response;
        }
        catch(\Exception $e){
            return response()->json([
                'status' => 'error',
                'message' => $e->getMessage()
            ]);
        }
    }

    public function EstimateRates(Request $request){
        $data = $request->validate([
            'iso' => 'required | string',
            'sku' => 'required | string',
            'amount' => 'required | string',
        ]);
        try {
            $response = json_decode($this->intAirtimeDataHelper->getIntEstimateRates($data), true);
            return $response;
        }
        catch(\Exception $e){
            return response()->json([
                'status' => 'error',
                'message' => $e->getMessage()
            ]);
        }
    }

    public function VendIntBills(Request $request) {
        $data = $request->validate([
            'iso' => 'required | string',
            'provider_code' => 'required | string',
            'sku' => 'required | string',
            'amount' => 'required | string',
            'account' => 'required | string',
            'debit_currency' => 'sometimes | string',
        ]);
        $referenceId = Str::uuid();
        $referenceIdString = (string) $referenceId . '-auto_bill';
        $data = array_merge($data, ['ref' => $referenceIdString]);
        try {
            $validateBalance = $this->payscribeBalanceHelper->validateBalance($data['amount']);

            if(!!$validateBalance){
                return $validateBalance;
            }

            $response = json_decode($this->intAirtimeDataHelper->vendIntBills($data), true);
            if(isset($response['status']) && $response['status'] === true){
                $this->payscribeBalanceHelper->createTransaction($data, $response, $this->billType);
                
                $user = auth()->user();

                // Notify User using the Notify trait instead of the missing sendBillPaymentEmail method
                $this->sendNotification(
                    user: $user,
                    templateKey: 'INT_AIRTIME_DATA_SUCCESS',
                    params: [
                        'user' => $user->firstname,
                        'amount' => number_format($data['amount']),
                        'iso' => $data['iso'],
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
            return $response;
        }
        catch(\Exception $e){
            return response()->json([
                'status' => 'error',
                'message' => $e->getMessage()
            ]);
        }
    }
}