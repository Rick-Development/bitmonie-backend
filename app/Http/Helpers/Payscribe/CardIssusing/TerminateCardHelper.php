<?php

namespace App\Http\Helpers\Payscribe\CardIssusing;

use App\Http\Helpers\ConnectionHelper;

class TerminateCardHelper extends ConnectionHelper {
    public function __construct(){
        parent::__construct();
    }

    public function terminateCard($data){
        $url = "/cards/{$data['ref']}/terminate";
        
        return $this->post($url,$data); /// could be put or patch

    }

}