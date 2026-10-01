<?php

namespace App\Http\Helpers\Payscribe\Payout;

use App\Http\Helpers\ConnectionHelper;

class PayoutHelper extends ConnectionHelper {
    public function __construct(){
        parent::__construct();
    }

    public function accountLookUp($data){
        $url = "/payouts/account/lookup";


        return $this->post($url,$data);

    }
    public function payoutsFee($data){
        $url = "/payouts/fee/?amount={$data['amount']}&country={$data['country']}";


        return $this->get($url);

    }

    public function transfer($data){
        $url = "/payouts/transfer";

        return $this->post($url,$data);
    }

    public function verifyTransfer($data){
        $url = "/payouts/verify/{$data['trans_id']}";

        return $this->get($url);
    }
}