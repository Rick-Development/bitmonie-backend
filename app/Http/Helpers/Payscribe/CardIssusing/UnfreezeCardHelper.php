<?php

namespace App\Http\Helpers\Payscribe\CardIssusing;

use App\Http\Helpers\ConnectionHelper;

class UnfreezeCardHelper extends ConnectionHelper {
    public function __construct(){
        parent::__construct();
    }

    public function unfreezeCard($data){
        $url = "/cards/{$data['ref']}/unfreeze";
        
        return $this->post($url,$data); /// could be put or patch

    }

}