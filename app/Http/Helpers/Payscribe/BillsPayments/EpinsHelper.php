<?php

namespace App\Http\Helpers\Payscribe\BillsPayments;

use App\Http\Helpers\ConnectionHelper;


class EpinsHelper extends ConnectionHelper {
    public function __construct(){
        parent::__construct();
    }

    public function getAvaliableEpin(){
        $url = "/epins";

        return $this->get($url);
    }

    public function purchaseEpins($data){
        $url = "/epins/vend";

        return $this->post($url,$data);

    }

    public function jambUserLookup($data){
        $url = "/epins/jamb/user/lookup";

        return $this->post($url,$data);

    }

    public function retreiveEpins($data){
        $url = "/epins/retrieve?trans_id={$data['trans_id']}";

        return $this->get($url);

    }
}