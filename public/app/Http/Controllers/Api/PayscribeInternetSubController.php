<?php

namespace App\Http\Controllers\API;

use App\Http\Controllers\Controller;
use App\Http\Helpers\Payscribe\BillsPayments\InternetSubscriptionHelper;
use App\Http\Helpers\Payscribe\PayscribeBalanceHelper;
use App\Http\Controllers\API\BillPurchaseController;
use App\Http\Helpers\Payscribe\BillsPayments\BillPaymentHelper;
use App\Models\Transaction;
use Illuminate\Http\Request;
use Illuminate\Support\Str;

class PayscribeInternetSubController extends Controller
{
    private $billType = 'Internet Subscription';

    //
    public function __construct(private InternetSubscriptionHelper $internetSubHelper, private PayscribeBalanceHelper $payscribeBalanceHelper, private BillPaymentHelper $billPaymentHelper ){}

    public function internetServices() {
        $response =  $response = json_decode($this->internetSubHelper->listInternetServices(), true);
        return $response;
    }

    public function spectranetPinPlans() {
        $response =  $response = json_decode($this->internetSubHelper->spectranetPinPlans(), true);
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

        $response =  $response = json_decode($this->internetSubHelper->purchaseSpectranetPins($data), true);

        if($response['status'] === true){
            $this->payscribeBalanceHelper->createTransaction($data, $response, $this->billType);
            $this->sendBillPaymentEmail($data['amount'], $this->billType);
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
            "service"=> "smile",
            "vend_type"=> "subscription",
            "code"=>"Z0RzQWovR3Y5RndoY2hRUHJMWkkyVG0zRklVcWxjVEFqZllvYjk3eW1RaXdGRTFmTnBqZEpEa1cyc2Fxd29vNw==",
            "phone"=> "07038067493",
            "productCode"=> "CE91947F8855E210DE4DFCC2DF76E5411B3EF657|eyJzZXJ2aWNlIjoic21pbGUiLCJjaGFubmVsIjoiQjJCIiwidHlwZSI6ImFjY291bnQiLCJhY2NvdW50IjoiMTkwNDAwMzI5MyIsImF1dGgiOnsiaXNzIjoiaXRleHZhcyIsInN1YiI6IjkxNjE4NjM1Iiwid2FsbGV0IjoiOTE2MTg2MzUiLCJ0ZXJtaW5hbCI6IjkxNjE4NjM1IiwidXNlcm5hbWUiOiJwaGlsbzR1MmNAZ21haWwuY29tIiwiaWRlbnRpZmllciI6Inplcm9uZXMiLCJrZXkiOiJhZTQ3YWI5NGMwZTIwNjUwYjMyODk2YjRhMzcxZDU2NiIsInZlbmRUeXB9tZXIgVmFsaWRhdGlvbiBTdWNjZXNzZnVsIn0%3D",
            "ref"=> "my-system-transaction-id"

        ]);
        $reposne = $this->internetSubHelper->payInternetSubscription($data);
        return $reposne;

    }

}