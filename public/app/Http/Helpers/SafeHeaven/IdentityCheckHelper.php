<?php
namespace App\Http\Helpers\SafeHeaven;

use App\Http\Helpers\SafeHeaven\ApiConnectionHelper;

class IdentityCheckHelper extends ApiConnectionHelper
{

    public function initiateVerification($data){
        $url = '/identity/v2';
        $response = $this->post($url, $data);
        $decodedResponse = json_decode($response,true);
        return $decodedResponse;
    }  

    public function validateVerification($data){
        $url = '/identity/v2/validate';
        $response = $this->post($url, $data);
        $decodedResponse = json_decode($response,true);
        return $decodedResponse;
    }  

}