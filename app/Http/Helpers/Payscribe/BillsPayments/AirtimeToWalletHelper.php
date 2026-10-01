<?php

namespace App\Http\Helpers\Payscribe\BillsPayments;

use App\Http\Helpers\ConnectionHelper;


class AirtimeToWalletHelper extends ConnectionHelper {
    public function __construct(){
        parent::__construct();
    }

    public function airtimeToWalletLookup(){
        $url = "/airtime_to_wallet";
        $data = [];
        return $this->post($url,$data);

    }
    public function airtimeToWallet($data){
        $url = "/airtime_to_wallet/vend";

        return $this->post($url,$data);
    }
}