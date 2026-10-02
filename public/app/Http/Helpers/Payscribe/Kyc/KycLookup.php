<?php

namespace App\Http\Helpers\Payscribe\Kyc;

use App\Http\Helpers\ConnectionHelper;


class KycLookup extends ConnectionHelper{

    public function __construct(){
        parent::__construct();
    }
    public function bvnLookup($type, $value){ 
       
    $url =  "/kyc/lookup?type=$type&value=$value";
    return   $this->get($url);
    }

}