<?php
namespace App\Http\Helpers\Payscribe\BillsPayments;

use App\Http\Helpers\ConnectionHelper;

class PayscribeHelper extends ConnectionHelper{

    public function __construct(){
        parent::__construct();
    }
    public function fetchServices($group_by){
        $url = "/misc/services?group_by=$group_by";
        return $this->get($url);

    }


}