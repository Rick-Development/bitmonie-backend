<?php
namespace App\Http\Helpers\Payscribe\BillsPayments;

use App\Http\Helpers\ConnectionHelper;

class InternetSubscriptionHelper extends ConnectionHelper{
    public function __construct(){
        parent::__construct();
    }

    public function listInternetServices()  {
        $url = '/internet/list';
        return $this->get($url);
        
    }

    public function spectranetPinPlans() {
        $url = '/internet/spectranet/pins/plans';
        return $this->get($url);
    }

    public function purchaseSpectranetPins($data) {
        $url = '/internet/spectranet/pins/vend';
        // $data = [
        //     "plan_id" => "PSPLAN_1270",
        //     "qty" => 1,
        //     "ref" => "randomUUID"
        // ];
        return $this->post($url,$data);
    }

    public function validateInternetSubscriptio($data) {
        $url = '/internet/spectranet/validate';
        // $data = [
        //     "account" => "210001607",
        //     'type' => 'intel'
        // ];
        return $this->post($url,$data);
        
    }

    public function internetSubscriptionBundles($data){
        $url = '/internet/bundles';
        // $data = [
        //     "type" => "smile",
        //     "account" => "1905003293"
        //     ];
        return $this->post($url,$data);
    }
    public function payInternetSubscription($data){
        $url = '/internet/vend';
        $data = [
                "service"=> "smile",
                "vend_type"=> "subscription",
                "code"=>"Z0RzQWovR3Y5RndoY2hRUHJMWkkyVG0zRklVcWxjVEFqZllvYjk3eW1RaXdGRTFmTnBqZEpEa1cyc2Fxd29vNw==",
                "phone"=> "07038067493",
                "productCode"=> "CE91947F8855E210DE4DFCC2DF76E5411B3EF657|eyJzZXJ2aWNlIjoic21pbGUiLCJjaGFubmVsIjoiQjJCIiwidHlwZSI6ImFjY291bnQiLCJhY2NvdW50IjoiMTkwNDAwMzI5MyIsImF1dGgiOnsiaXNzIjoiaXRleHZhcyIsInN1YiI6IjkxNjE4NjM1Iiwid2FsbGV0IjoiOTE2MTg2MzUiLCJ0ZXJtaW5hbCI6IjkxNjE4NjM1IiwidXNlcm5hbWUiOiJwaGlsbzR1MmNAZ21haWwuY29tIiwiaWRlbnRpZmllciI6Inplcm9uZXMiLCJrZXkiOiJhZTQ3YWI5NGMwZTIwNjUwYjMyODk2YjRhMzcxZDU2NiIsInZlbmRUeXB9tZXIgVmFsaWRhdGlvbiBTdWNjZXNzZnVsIn0%3D",
                "ref"=> "my-system-transaction-id"
            
        ];
        return $this->post($url,$data);
    }


    
}