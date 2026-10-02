<?php

namespace App\Http\Helpers\Payscribe\CardIssusing;

use App\Http\Helpers\ConnectionHelper;

class WithdrawFromCardHelper extends ConnectionHelper {
    public function __construct(){
        parent::__construct();
    }

    public function withdrawFromCard($data, $cardId){
        $url = "/cards/{$cardId}/withdraw";
        
        return $this->post($url,$data);

    }

}