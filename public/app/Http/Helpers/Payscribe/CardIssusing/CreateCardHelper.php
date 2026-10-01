<?php

namespace App\Http\Helpers\Payscribe\CardIssusing;

use App\Http\Helpers\ConnectionHelper;

class CreateCardHelper extends ConnectionHelper {
    public function __construct(){
        parent::__construct();
    }

    public function createCard($data){
        $url = "/cards/create";
        
        return $this->post($url,$data);

    }
}