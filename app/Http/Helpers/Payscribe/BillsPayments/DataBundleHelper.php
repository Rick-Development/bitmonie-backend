<?php
namespace App\Http\Helpers\Payscribe\BillsPayments;

use App\Http\Helpers\ConnectionHelper;

class DataBundleHelper extends ConnectionHelper{

    public function __construct(){
        parent::__construct();
    }
    public function dataLookup($network){
        $url = "/data/lookup?network=$network";
        return $this->get($url);

    }

    public function dataVending($data){
        $url = "/data/vend";
        return $this->post($url,$data);

    }

}