<?php

namespace App\Http\Controllers\Api;
use Illuminate\Support\Facades\DB;
use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Http;
use Illuminate\Http\JsonResponse;
use Barryvdh\DomPDF\Facade\Pdf;
use App\Models\User;
use Illuminate\Support\Facades\Mail;
use App\Traits\ApiResponse;
use App\Traits\ManageWallet;
use App\Traits\Notify;
use App\Traits\Upload;
use App\Traits\VirtualCardTrait;

class PayscribeUserCardController extends Controller
{
    use ApiResponse, Notify, Upload, ManageWallet, VirtualCardTrait;
    
    protected $baseUrl = 'https://sandbox.payscribe.ng/api/v1/cards'; // Base URL for card-related API
    protected $currencyPairUrl = 'https://sandbox.payscribe.ng/api/v1/currency-pair'; // Base URL for currency pair
    // protected $apiKey = 'ps_live_b9a258625363b2a3863e45053da267134152cd5606029bcfe1a39e71e1f72c3c';
    protected $apiKey = 'ps_pk_test_mjwKJDOh41Zrl5uMXUJqwy3pyPYx5d';
    
    protected function makeCurrencyGet($from, $to){
        

$curl = curl_init();

curl_setopt_array($curl, array(
  CURLOPT_URL => "https://sandbox.payscribe.ng/api/v1//currency-pair?from=$from&to=$to",
  CURLOPT_RETURNTRANSFER => true,
  CURLOPT_ENCODING => '',
  CURLOPT_MAXREDIRS => 10,
  CURLOPT_TIMEOUT => 0,
  CURLOPT_FOLLOWLOCATION => true,
  CURLOPT_HTTP_VERSION => CURL_HTTP_VERSION_1_1,
  CURLOPT_CUSTOMREQUEST => 'GET',
  CURLOPT_HTTPHEADER => array(
    "Authorization: Bearer $this->apiKey",
  ),
));

$response = curl_exec($curl);

curl_close($curl);
return $response;

    }

    protected function makePostRequest($endPoint, $postFields = array()) {
        $curl = curl_init();
        $url = $this->baseUrl . $endPoint;
    
        curl_setopt_array($curl, array(
            CURLOPT_URL => $url,
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_ENCODING => '',
            CURLOPT_MAXREDIRS => 10,
            CURLOPT_TIMEOUT => 0,
            CURLOPT_FOLLOWLOCATION => true,
            CURLOPT_HTTP_VERSION => CURL_HTTP_VERSION_1_1,
            CURLOPT_CUSTOMREQUEST => 'POST',
            CURLOPT_POSTFIELDS => json_encode($postFields),
            CURLOPT_HTTPHEADER => array(
                "Authorization: Bearer $this->apiKey",
                "Content-Type: application/json"
            ),
        ));
    
        $response = curl_exec($curl);
    
        // Check for errors
        // if (curl_errno($curl)) {
        //     throw new \Exception('cURL error: ' . curl_error($curl));
        // }
    
        curl_close($curl);
        // return $response;
        return $response;
    }
    
  protected function makePatchRequest($endPoint, $patchFields = [])
{
    $url = $this->baseUrl . $endPoint;

    // Log the endpoint and request data for debugging (sanitize if needed)
    //\Log::info('API Request', [
    //     'patchFields' => $patchFields,
    //     'url' => $url
    // ]);

    // Initialize cURL
    $curl = curl_init();

    // Convert $patchFields to JSON
    $jsonPayload = json_encode($patchFields);

    // Set cURL options
    curl_setopt_array($curl, [
        CURLOPT_URL => $url,
        CURLOPT_RETURNTRANSFER => true,
        CURLOPT_ENCODING => '',
        CURLOPT_MAXREDIRS => 10,
        CURLOPT_TIMEOUT => 0,
        CURLOPT_FOLLOWLOCATION => true,
        CURLOPT_HTTP_VERSION => CURL_HTTP_VERSION_1_1,
        CURLOPT_CUSTOMREQUEST => 'PATCH',
        CURLOPT_POSTFIELDS => $jsonPayload,
        CURLOPT_HTTPHEADER => [
            "Authorization: Bearer $this->apiKey",
            "Content-Type: application/json"
        ],
    ]);

    // Execute the request and capture the response
    $response = curl_exec($curl);

    // Check for cURL errors
    if (curl_errno($curl)) {
        $errorMessage = curl_error($curl);
        //\Log::error('cURL Error', ['error' => $errorMessage]);
        curl_close($curl);
        return null; // Return or handle error as needed
    }

    // Close cURL
    curl_close($curl);

    // Log and return the response
    //\Log::info('API Response', ['response' => $response]);

    return $response;
}


    
    
     protected function makeGetRequest($endPoint, $queryParams = array())
{
 

$curl = curl_init();

 // Build the URL with query parameters
    $url = $this->baseUrl . $endPoint;

curl_setopt_array($curl, array(
  CURLOPT_URL => $url,
  CURLOPT_RETURNTRANSFER => true,
  CURLOPT_ENCODING => '',
  CURLOPT_MAXREDIRS => 10,
  CURLOPT_TIMEOUT => 0,
  CURLOPT_FOLLOWLOCATION => true,
  CURLOPT_HTTP_VERSION => CURL_HTTP_VERSION_1_1,
  CURLOPT_CUSTOMREQUEST => 'GET',
  CURLOPT_HTTPHEADER => array(
    "Authorization: Bearer $this->apiKey",
                "Content-Type: application/json"
  ),
));

$response = curl_exec($curl);

curl_close($curl);
return $response;

}

    /**
     * Create a new card.
     *
     * @param Request $request
     * @return JsonResponse
     */
    public function createCard(Request $request): JsonResponse
    {
        
        
    $ref = bin2hex(random_bytes(8)); // Generates a random 16-character string

      $response = $this->makePostRequest('/create', array(
    "customer_id" => $request->input('customer_id'),
    "currency" => $request->input('currency', 'USD'),
    "brand" => $request->input('brand'),
    "type" => $request->input('type', 'virtual'),
    "amount" => '2',//$request->input('amount'),
    "ref" => $ref // Generates a random 16-character string
));



// Log request and response for debugging
//\Log::info('API Request', [
//     'endpoint' => '/create',
//     'payload' => array(
//         "customer_id" => $request->input('customer_id'),
//         "currency" => $request->input('currency', 'USD'),
//         "brand" => $request->input('brand'),
//         "type" => $request->input('type', 'virtual'),
//         "amount" => $request->input('amount'),
//         "ref" => $ref
//     )
// ]);
//\Log::info('API Response', ['response' => $response]);



// Decode JSON into an associative array
$data = json_decode($response, true);
if($data['status'] == true){
    

// Extract card details
$cardDetails = $data['message']['details']['card'];
$customerDetails = $data['message']['details']['customer'];

$extractedDetails = [
    'Transaction ID' => $data['message']['details']['trans_id'],
    'Reference' => $data['message']['details']['ref'],
    'Card ID' => $cardDetails['id'],
    'Card Type' => $cardDetails['card_type'],
    'Currency' => $cardDetails['currency'],
    'Brand' => $cardDetails['brand'],
    'Name' => $cardDetails['name'],
    'First Six Digits' => $cardDetails['first_six'],
    'Last Four Digits' => $cardDetails['last_four'],
    'Masked Number' => $cardDetails['masked'],
    'Card Number' => $cardDetails['number'],
    'Expiry Date' => $cardDetails['expiry'],
    'CCV' => $cardDetails['ccv'],
    'Billing' => $cardDetails['billing'], // This is an array, handle as needed
    'Created At' => $cardDetails['created_at'],
    'Customer ID' => $customerDetails['id'],
    'Customer Name' => $customerDetails['name']
];


//\Log::info('Extracted Details', ['extractedDetails' => $extractedDetails]);

$data = [
    // 'id' => '123',
    'customer_id' => $customerDetails['id'],
    'card_id' => $cardDetails['id'],
    'currency' => $cardDetails['currency'],
    'brand' => $cardDetails['brand'],
    'type' => $cardDetails['card_type'],
    'trans_id' => $data['message']['details']['trans_id'],
    'ref' => $data['message']['details']['ref'],
];
// use Illuminate\Support\Facades\DB;

DB::table('payscribe_virtual_card')->insert($data);
  $params = [
                'amount' => $cardDetails['number'],
                'currency' =>  $cardDetails['currency'],
                'transaction' => $cardDetails['brand'],
            ];
             $action = [
            "link" => "",
            "icon" => "fa fa-money-bill-alt text-white"
        ];
             $user = User::where('payscribe_id', $request->input('customer_id'))->first();
        $this->sendMailSms($user, 'VIRTUAL_CARD_APPROVE', $params);
        $this->userPushNotification($user, 'VIRTUAL_CARD_APPROVE', $params, $action);
        $this->userFirebasePushNotification($user, 'VIRTUAL_CARD_APPROVE', $params);
        

}
return response()->json(['response' => $response]);

    }
    
        /**
     * Get details for a specific card.
     *
     * @param string $cardId
     * @param Request $request 
     * @return JsonResponse 
     */
    public function getCardDetails($cardId, Request $request): JsonResponse
    {
        $url = "/{$cardId}";
         $response = $this->makeGetRequest($url);


//\Log::info('API Response', ['response' => $response]);

// return $response; 
return response()->json(['response' => $response]);
 
        // if ($response->successful()) {
        //     return response()->json($response->json(), 200);
        // }

        // return response()->json(['error' => 'Unable to fetch transactions.'], $response->status());
    }


  /**
     * Get Currency Pair Details.
     *
     * @param string $from
     * @param string $to
     * @return JsonResponse
     */
   public function getCardId(Request $request): JsonResponse
{
    // Log the incoming request
    //\Log::info('API Request', ['request' => $request->all()]);

    // Query the database for the card ID
    // $card = DB::table('payscribe_virtual_card')
    //     ->select('card_id')
    //     ->where('customer_id', $request->customer_id)
    //     ->first(); // Use first() to fetch a single record

$cards = DB::table('payscribe_virtual_card')
    ->select('card_id')
    ->where('customer_id', $request->customer_id)
    ->get(); // Use get() to fetch all records


    // Log the response
    //\Log::info('API Response', ['response' => $cards]);

    // Handle cases where the card is not found
    if (!$cards) {
        return response()->json([
            'success' => false,
            'message' => 'No card found for the given customer ID.'
        ], 404);
    }

    // Return the card ID in a structured response
    return response()->json([
        'success' => true,
        'data' => $cards
    ], 200);
}


public function getCardRates(Request $request){
    $rates = DB::table('basic_controls')
    ->select('card_withdrawal_rate','card_deposit_rate','card_issuing_rate')
    // ->where('customer_id', $request->customer_id)
    ->get(); // Use get() to fetch all records

    //\Log::info('API Response', ['$rates' => $rates]);
  return response()->json([
        'success' => true,
        'rates' => $rates
    ], 200);
            // $basicControl-> = $purifiedData->card_withdrawal_rate;
            // $basicControl-> = $purifiedData->card_deposit_rate;
            // $basicControl-> = $purifiedData->card_issuing_rate;
}
    
    /**
     * Get Currency Pair Details.
     *
     * @param string $from
     * @param string $to
     * @return JsonResponse
     */
    public function getCurrencyPair($from, $to): JsonResponse
    {
        
    //\Log::info('API Response', ['Request ' => [$from,$to]]);
    
        // $url = "$this->currencyPairUrl/?from={$from}&to={$to}";
        //  $response = $this->makeGetRequest($url);
//   <?php
      $response = $this->makeCurrencyGet($from, $to);


    //\Log::info('API Response', ['response' => $response]);
    
            return response()->json($response);
            
    }

  

    /**
     * Top up an existing card.
     *
     * @param string $cardId
     * @param Request $request
     * @return JsonResponse
     */
    public function topupCard($cardId, Request $request): JsonResponse
    {
        $user = User::where('username', $request['username'])->first();
        //   $user = auth()->user(); // or $request->user();
        // $request->validate([
        //     'amount' => 'required|numeric',
        //     'ref' => 'nullable|string',
        // ]);
        
//\Log::info('API Request', ['Request' => $request->all(), 'user'=>$user]);

        $url = "/{$cardId}/topup";
         $response = $this->makePostRequest($url,$request->all());
        $responseArray = json_decode($response, TRUE);

    //   $params = [
    //             'amount' => $virtualCardCharge,
    //             'currency' => $baseCurrency,
    //             'transaction' => $transaction->trx_id,
            // ];
        // $this->sendMailSms($user, 'VIRTUAL_CARD_APPLY', $params);
        // $this->userPushNotification($user, 'VIRTUAL_CARD_APPLY', $params, $action);
        // $this->userFirebasePushNotification($user, 'VIRTUAL_CARD_APPLY', $params);
//\Log::info('API Response', ['response' => $response, 'user'=>$user]);


$responseArray = json_decode($response, TRUE);

  $params = [
                'amount' => $request['amount'],
                'currency' =>  'USD',
                'cardNumber' => $responseArray['message']['details']['card']['brand'],
            ];
            
        $action = [
            "link" => "",
            "icon" => "fa fa-money-bill-alt text-white"
        ];
        $this->sendMailSms($user, 'VIRTUAL_CARD_FUND_APPROVE', $params);
        $this->userPushNotification($user, 'VIRTUAL_CARD_FUND_APPROVE', $params, $action);
        $this->userFirebasePushNotification($user, 'VIRTUAL_CARD_FUND_APPROVE', $params);

// return $response; 
return response()->json(['response' => $response]);
 

    }

    /**
     * Withdraw from an existing card.
     *
     * @param string $cardId
     * @param Request $request
     * @return JsonResponse
     */
    public function withdrawFromCard($cardId, Request $request): JsonResponse
    {

// //\Log::info('API Request', ['$request' => $request->all(). '    '. $cardId]);
        $user = User::where('username', $request['username'])->first();
        $url = "/{$cardId}/withdraw";
        


         $response = $this->makePostRequest($url,$request->all());


//\Log::info('API Response', ['response' => $response]);


$responseArray = json_decode($response, TRUE);
if($responseArray['status'] == true){
      $params = [
                'amount' => $request['amount'],
                'currency' =>  'USD',
                'cardNumber' => $responseArray['message']['details']['card']['last_four'],
                'brand' =>$responseArray['message']['details']['card']['brand'],
            ];
            
        $action = [
            "link" => "",
            "icon" => "fa fa-money-bill-alt text-white"
        ];
        $this->sendMailSms($user, 'VIRTUAL_CARD_WITHDRAWAL', $params);
        $this->userPushNotification($user, 'VIRTUAL_CARD_WITHDRAWAL', $params, $action);
        $this->userFirebasePushNotification($user, 'VIRTUAL_CARD_WITHDRAWAL', $params);
}



// return $response; 
return response()->json(['response' => $response]);
 
    }

    /**
     * Get transactions for a specific card.
     *
     * @param string $cardId
     * @param Request $request
     * @return JsonResponse
     */
    public function getCardTransactions($cardId, Request $request): JsonResponse
    {
        

        $url = "/{$cardId}/transactions?" . http_build_query($request->all());

//\Log::info('API Request', ['response' => $request->all()]);
         $response = $this->makeGetRequest($url);


//\Log::info('API Response', ['response' => $response]);

// return $response; 
return response()->json(['response' => $response]);




    }



public function statement($cardId, Request $request): JsonResponse
{
    // Construct the API URL
    $url = "/{$cardId}/transactions?" . http_build_query($request->all());
// $request->
    try {
        // Log the request
        //\Log::info('API Request', ['request' => $request->all()]);

        // Fetch the API response
        $response = $this->makeGetRequest($url);
        
        // Log the API response
        //\Log::info('API Response', ['response' => $response]);

        // Decode the JSON string from the API response
        $decodedResponse = json_decode($response, true);

        // Handle error case from the API response
        if ($decodedResponse && isset($decodedResponse['status']) && !$decodedResponse['status']) {
            return response()->json([
                'status' => $decodedResponse['status'],
                'description' => $decodedResponse['description'] ?? 'An error occurred.'
            ], 400); // Return the error message with a 400 status code
        }

        // Extract transactions from the decoded response
        $transactions = $decodedResponse['message']['details']['transactions'] ?? [];

        // If no transactions, handle the case appropriately (optional)
        if (empty($transactions)) {
            return response()->json([
                'status' => false,
                'description' => 'No transactions found for this period.'
            ], 404); // Return a 404 or other appropriate code
        }
    } catch (\Exception $e) {
        // Handle any exception during the API request
        //\Log::error('API Request Failed', ['error' => $e->getMessage()]);
        return response()->json([
                'status' => false,
                'description' => 'Failed to fetch transactions'], 500);
    }

    try {
        // Generate the PDF
        $pdfPath = storage_path('app/public/card_statement_' . $cardId . '_' . now()->timestamp . '.pdf');
        
        // Pass the transactions data to the PDF view
        $pdf = Pdf::loadView('pdf.statement', ['transactions' => $transactions]);
        
        $pdf->save($pdfPath);

        // Send the email with the PDF attachment
        Mail::to($request->email)->send(new \App\Mail\CardStatementMail($pdfPath));
    } catch (\Exception $e) {
        // Handle any exception during the PDF generation or email sending
        //\Log::error('Failed to generate/send PDF', ['error' => $e->getMessage()]);
        return response()->json([
                'status' => false,
                'description' => 'Failed to generate or send the card statement'], 500);
    }

    return response()->json([
        'status' => true,
        'description' => 'Card statement sent to email successfully!'
    ]);
}




    /**
     * Freeze a specific card.
     * 
     * @param string $cardId
     * @return JsonResponse
     */
    public function freezeCard($cardId, Request $request): JsonResponse
    {
        $url = "/{$cardId}/freeze";
        
//\Log::info('API Request', ['Request' => $request->all(),'url'=>$url]);

//  $url = "/{$cardId}/topup";
         $response = $this->makePatchRequest($url,$request->all());


//\Log::info('API Response', ['response' => $response]);

// return $response; 
return response()->json(['response' => $response]);
       
    }
    
    
    
      /**
     * Freeze a specific card.
     * 
     * @param string $cardId
     * @return JsonResponse
     */
    public function unFreezeCard($cardId, Request $request): JsonResponse
    {
        $url = "/{$cardId}/unfreeze";
        
//\Log::info('API Request', ['Request' => $request->all(),'url'=>$url]);

//  $url = "/{$cardId}/topup";
         $response = $this->makePatchRequest($url,$request->all());


//\Log::info('API Response', ['response' => $response]);

// return $response; 
return response()->json(['response' => $response]);
       
    }
    
    
    
        /**
     * Freeze a specific card.
     * 
     * @param string $cardId
     * @return JsonResponse
     */
    public function terminateCard($cardId, Request $request): JsonResponse
    {
        $url = "/{$cardId}/terminate";
        
//\Log::info('API Request', ['Request' => $request->all(),'url'=>$url]);

//  $url = "/{$cardId}/topup";
         $response = $this->makePostRequest($url,$request->all());
        //  {\"status\":true,\"description\":\"Card action - terminate successfully.\",\"message\":{\"details\":{\"trans_id\":\"36f400d0-478b-4096-a100-e92cb6840de7\",\"ref\":\"828d85f1-e57e-403f-a7da-46da092e80b2\",\"customer\":{\"id\":\"c69a8756-61b4-4817-b2d7-2c351659607a\"},\"card\":{\"id\":\"69cc5311-bbe8-46d0-a551-2c8964267d89\",\"first_six\":\"302996\",\"last_four\":\"1890\",\"prev_balance\":0,\"balance\":0},\"currency\":\"usd\",\"action\":\"terminate\",\"created_at\":\"2024-12-07 21:06:49\"}},\"status_code\":200}"} 
$responseArray = json_decode($response, TRUE);

//\Log::info('API $responseArray', ['$responseArray' => $responseArray['message']['details']['customer']['id'] ]);

if ($responseArray['status'] == true) {
    // Query the nested structure to get the customer ID
    $customerId = $responseArray['message']['details']['customer']['id'] ?? null;

    if ($customerId) {
        // Update the database for the corresponding customer
        DB::table('payscribe_virtual_card')
            ->where('card_id', $cardId)
            ->delete();
            // ->update(['card_id' => null]);
    } else {
        //\Log::error('Customer ID not found in API response', ['response' => $responseArray]);
    }
}


//\Log::info('API Response', ['response' => $response]);

// return $response; 
return response()->json(['response' => $response]);
       
    }
    
    
}