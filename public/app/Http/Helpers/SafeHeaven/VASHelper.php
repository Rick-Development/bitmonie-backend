<?php
namespace App\Http\Helpers\SafeHeaven;

use App\Http\Helpers\SafeHeaven\ApiConnectionHelper;

class VASHelper extends ApiConnectionHelper
{
    public function services()
    {
        $url = '/vas/services';
        $response = $this->get($url);
        $decodedResponse = json_decode($response, true);
        return $decodedResponse;
       
    } 
    public function service(string $id) {
       $url = '/vas/service/'.$id;
        $response = $this->get($url);
        $decodedResponse = json_decode($response, true);
        return $decodedResponse;
    } 

    public function serviceCategories(string $id) {
        $url = "/vas/service/$id/service-categories";
        $response = $this->get($url);
        $decodedResponse = json_decode($response, true);
        return $decodedResponse;
    } 

    public function serviceProducts(string $id) {
        $url = "/vas/service-category/{$id}/products";
        $response = $this->get($url);
        $decodedResponse = json_decode($response, true);
        return $decodedResponse;
    } 

    public function verifyProduct($data) {
       $url = '/vas/verify';
       $response = $this->post($url, $data);
        $decodedResponse = json_decode($response, true);
        return $decodedResponse;
    } 

    public function airtime($data) {
        $url = '/vas/pay/airtime';
        $response = $this->post($url, $data);
        $decodedResponse = json_decode($response, true);
        return $decodedResponse;
    } 

    public function data($data) {
        $url = '/vas/pay/data';
        $response = $this->post($url, $data);
        $decodedResponse = json_decode($response, true);
        return $decodedResponse;
    } 

    public function cableTv($data) {
        $url = '/vas/pay/cable-tv';
        $response = $this->post($url, $data);
        $decodedResponse = json_decode($response, true);
        return $decodedResponse;
    } 

    public function utilityBills($data) {
        $url = '/vas/pay/utility';
        $response = $this->post($url, $data);
        $decodedResponse = json_decode($response, true);
        return $decodedResponse;
    } 

    public function vasTranactions() {
        $url = '/vas/transactions';
        $response = $this->get($url);
        $decodedResponse = json_decode($response, true);
        return $decodedResponse;
    } 

    public function vasTransaction(string $id) {
        $url = '/vas/transactions/'.$id;
        $response = $this->get($url);
        $decodedResponse = json_decode($response, true);
        return $decodedResponse;
    }
}