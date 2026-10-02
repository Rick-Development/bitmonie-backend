<?php
namespace App\Http\Helpers\Payscribe\BillsPayments;

use App\Http\Helpers\ConnectionHelper;

class CableTVSubscriptionHelper extends ConnectionHelper {

    public function __construct(){
        parent::__construct();
    }


    function fetchBouquets($service)  {
        $url = "/bouquets?service=$service";
        return $this->get($url);
    }

   
    function validateSmartCardNumber($data) {
        $url = "/multichoice/validate";
        // $data = [
        //         "service" => "dstv",
        //         "account" => "8062415043",
        //         "month" => 1,
        //         "plan_id" => "RDJhb0xweVR6VjNLY0kyT2hhWkVsQT09"
        // ];
        return $this->post($url, $data);
    }

    function payCableTV($data) {
        $url = "/multichoice/vend";
        // $randomUUID = rand(1,1000);
        // $data = [
        //     "plan_id" => "TnpRK0N5Z2hwbElEa0srdjJXQnBUdz09",
        //     "customer_name" => "Prince EffiongLiYiik",
        //     "account" => "7040366486",
        //     "service" => "dstv",
        //     "ref" => "$randomUUID",
        //     "phone" => "08199228811",
        //     "email" => "user@gmail.com",
        //     "month" => 1
        //     ];

        return $this->post($url,$data);
    }

    function topupCableTV($data){
        $url = "/multichoice/topup";
        
        // $randomUUID = rand(1,1000);
        // $data = [
        //         "amount"  => 10000,
        //         "customer_name" => "ADEBAYO MOSUNMOLA",
        //         "account" => "2009594253",
        //         "service" => "gotv",
        //         "ref" => "$randomUUID",
        //         // "phone" => "08199228811",
        //         // "email" => "user@gmail.com",
        //         "month" => 1
        // ];
        return $this->post($url,$data);
    }


}