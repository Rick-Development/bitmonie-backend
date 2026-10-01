<?php

namespace App\Http\Helpers\Payscribe\Collections;

use App\Http\Helpers\ConnectionHelper;

class WalletSystemHelper extends ConnectionHelper{

    public function __construct(){
        parent::__construct();
    }
    public function walletLookup(){
        $url =  'https://sandbox.payscribe.ng/api/v1//collections/wallet/lookup?tag=payscribe&amount=100';
        $this->get($url);
    }

    public function walletPayment(){
        $data = [
            "tag" => "payscribe",
            "amount" => 3000,
            "pin" => "0891",
            "product" => "MovieTicket",
            "remark" => "Movie Ticket payment for Tomorrow Never Die"
        ];

        $url = 'https://sandbox.payscribe.ng/api/v1//collections/wallet/vend';
        $this->post($url, $data);
    }


}

