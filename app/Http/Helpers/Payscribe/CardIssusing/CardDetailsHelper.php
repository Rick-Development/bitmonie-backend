<?php

namespace App\Http\Helpers\Payscribe\CardIssusing;

use App\Http\Helpers\ConnectionHelper;

class CardDetailsHelper extends ConnectionHelper {
    public function __construct(){
        parent::__construct();
    }

    public function createCard($data)
    {
        $url = "/cards/create";
        return $this->post($url, $data);
    }

    public function getCardDetails($cardId) {
        $url = "/cards/{$cardId}";
        return $this->get($url);
    }

    public function getUserCards($customerId) {
        $url = "/cards?customer_id={$customerId}";
        return $this->get($url);
    }
}
