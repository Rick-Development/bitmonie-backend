<?php
namespace App\Http\Helpers\SafeHeaven;

use App\Http\Helpers\SafeHeaven\ApiConnectionHelper;

class AccountHelper extends ApiConnectionHelper
{

  public function createAccount($data)
  {
    $url = '/accounts';
    $response = $this->post($url, $data);
    $decodedResponse = json_decode($response, true);
    return $decodedResponse;
  }

  public function createSubAccountInd($data)
  {
    $url = '/accounts/v2/subaccount';
    $response = $this->post($url, $data);
    $decodedResponse = json_decode($response, true);
    return $decodedResponse;
  }

  // public function createSubAccountCorpate($data){
  //     $url = 'accounts/v2/subaccount/';
  //     $response = $this->post($url, $data);
  //     $decodedResponse = json_decode($response,true);
  //     return $decodedResponse;
  // }    
  public function getAccounts($data)
  {
    $url = '/accounts?page=0&limit=100&isSubAccount=false';
    $response = $this->get($url);
    $decodedResponse = json_decode($response, true);
    return $decodedResponse;
  }

  public function getAccount($id)
  {
    $url = '/accounts/' . $id;
    $response = $this->get($url);
    $decodedResponse = json_decode($response, true);
    return $decodedResponse;
  }

  public function updateAccount($data)
  {
    $url = '/accounts/' . $data['accountId'];
    $response = $this->put($url, $data);
    $decodedResponse = json_decode($response, true);
    return $decodedResponse;
  }

  public function accountStatement(string $id)
  {
    $url = "/accounts/$id/statement";
    $response = $this->get($url);
    $decodedResponse = json_decode($response, true);
    return $decodedResponse;
  }
}