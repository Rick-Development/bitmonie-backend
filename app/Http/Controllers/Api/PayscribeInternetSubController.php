<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Helpers\Payscribe\BillsPayments\InternetSubscriptionHelper;
use App\Http\Helpers\Payscribe\PayscribeBalanceHelper;
use App\Http\Controllers\API\BillPurchaseController;
use App\Http\Helpers\Payscribe\BillsPayments\BillPaymentHelper;
use App\Models\Transaction;
use Illuminate\Http\Request;
use Illuminate\Support\Str;
use App\Traits\Notify;

class PayscribeInternetSubController extends Controller
{
    use Notify;

    private $billType = 'Internet Subscription';

    //
    public function __construct(private InternetSubscriptionHelper $internetSubHelper, private PayscribeBalanceHelper $payscribeBalanceHelper, private BillPaymentHelper $billPaymentHelper ){}

    public function internetServices() {
        $response = json_decode($this->internetSubHelper->listInternetServices(), true);
        return $response;
    }

    public function spectranetPinPlans() {
        $response = json_decode($this->internetSubHelper->spectranetPinPlans(), true);
        return $response;

    }

    public function purchaseSpectranetPlans(Request $request) {
        $data = $request->validate([
            "plan_id" => 'required | string',
            "qty" => 'required | string',
            'amount' => 'required | string',
        ]);

        $validateBalance = $this->payscribeBalanceHelper->validateBalance($data['amount']);

        if(!!$validateBalance){
            return $validateBalance;
        }

        $referenceId = Str::uuid();
        $referenceIdString = (string) $referenceId . '-auto_bill';
        $data = array_merge($data, ['ref' => $referenceIdString]);

        $response = json_decode($this->internetSubHelper->purchaseSpectranetPins($data), true);

        if(isset($response['status']) && $response['status'] === true){
            $this->payscribeBalanceHelper->createTransaction($data, $response, $this->billType);
            
            $user = auth()->user();

            // Notify User using the Notify trait instead of the missing sendBillPaymentEmail method
            $this->sendNotification(
                user: $user,
                templateKey: 'INTERNET_SUB_SUCCESS',
                params: [
                    'user' => $user->firstname,
                    'amount' => number_format($data['amount']),
                    'plan_id' => $data['plan_id'],
                    'qty' => $data['qty'],
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



    public function validateInternetSubsription(Request $request) {
        $data = $request->validate([
            "account" => 'required | string',
            'type' => 'required | string'
        ]);

        $reposne = $this->internetSubHelper->validateInternetSubscriptio($data);
        return $reposne;
    }

    public function internetSubsriptionBundles(Request $request) {
        $data = $request->validate([
            "type" => 'required | string',
            "account" => 'required | string'
            ]);

        $reposne = $this->internetSubHelper->internetSubscriptionBundles($data);
        return $reposne;
    }

    public function payInternetSubsription(Request $request) {

        //TODO : confirm form docs..
        $data = $request->validate([
            "service"=> "required|string",
            "vend_type"=> "required|string",
            "code"=>"required|string",
            "phone"=> "required|string",
            "productCode"=> "required|string",
            "ref"=> "sometimes|string"
        ]);
        
        $reposne = $this->internetSubHelper->payInternetSubscription($data);
        return $reposne;

    }

}