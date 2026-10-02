<?php
namespace App\Http\Helpers\Payscribe\BillsPayments;

use App\Http\Helpers\ConnectionHelper;

class FundBetWalletHelper extends ConnectionHelper {

    public function __construct(){
        parent::__construct();
    }

    public function bettingServiceProviderList(){
        $url= "/betting/list";
        return $this->get($url);
    } 

    public function validateBetAccount($bet_id,$customer_id){
        $url= "/betting/lookup?bet_id=$bet_id&customer_id=$customer_id";
        $response = $this->get($url);
        return $response;
    }

    public function fundWallet($data){
        $url= "/betting/vend";
        // $data = [
        //     "bet_id" => "bet9ja",
        //     "customer_id" =>  "422984",
        //     "customer_name" =>  "obainolala",
        //     "amount" =>  100,
        //     "ref" =>  "4ffeaea6-3be9-48cf-b582-cd061b86ce96"
        // ];
        $response = $this->post($url,$data);
        return $response;
    }

}