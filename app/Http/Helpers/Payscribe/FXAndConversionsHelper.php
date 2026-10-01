<?php

namespace App\Http\Helpers\Payscribe;

use App\Http\Helpers\ConnectionHelper;


class FXAndConversionsHelper extends ConnectionHelper{

    public function __construct(){
        parent::__construct();
        // $this->apiKey  = 'ps_pk_test_5fJUELCWRxbYyqE0mylVlfeekNK9iY';
    }
    public function convert($from, $to){ 
        $url = "/currency-pair?from=$from&to=$to";
        $this->get($url);
    }

    public function createQuote($data){
        $url = "/create_quote";
        $randomUUID = rand(0,100);
        $data = [
                "currency_from" => "NGN",
                "currency_to" => "USD",
                "amount" => "5000",
                "ref" => "$randomUUID"
        ];
        return $this->post($url, $data);
    }


    public function verifyConversion($ref) {
        $url = "/verify-conversion/$ref";
        $this->get($url);
    }

}