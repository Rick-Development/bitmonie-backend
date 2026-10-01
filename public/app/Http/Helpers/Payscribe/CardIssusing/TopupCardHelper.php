<?php

namespace App\Http\Helpers\Payscribe\CardIssusing;

use App\Http\Helpers\ConnectionHelper;

class TopupCardHelper extends ConnectionHelper {
    public function __construct(){
        parent::__construct();
    }

    public function topupCard($data, $cardId){
        $url = "/cards/{$cardId}/topup";
        
        return $this->post($url,$data); /// could be put or patch

    }

}