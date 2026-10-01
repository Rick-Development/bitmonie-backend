<?php
namespace App\Http\Helpers;
use Illuminate\Support\Facades\Log;

class ConnectionHelper{

    private $apiKey;
    private $apiUrl;

     public function __construct() {
     $this->apiKey = config('services.payscribe.key'); // Store in .env
    //'ps_pk_test_Od2eDKnXWrVAAXat85kV4fQYjV0sAi';
     $this->apiUrl = config('services.payscribe.api_url');
    //'https://sandbox.payscribe.ng/api/v1';

     }



     public function get($url){
        $curl = curl_init();

        curl_setopt_array($curl, array(
        CURLOPT_URL =>$this->apiUrl . $url,
        CURLOPT_RETURNTRANSFER => true,
        CURLOPT_ENCODING => '',
        CURLOPT_MAXREDIRS => 10,
        CURLOPT_TIMEOUT => 0,
        CURLOPT_FOLLOWLOCATION => true,
        CURLOPT_HTTP_VERSION => CURL_HTTP_VERSION_1_1,
        CURLOPT_CUSTOMREQUEST => 'GET',
        CURLOPT_HTTPHEADER => array(
            "Authorization: Bearer $this->apiKey"
        ),
        ));

        $response = curl_exec($curl);

        curl_close($curl);
        return $response;
        // return json_decode($response, true);

    }

    public function post($url, array $data) {
        $curl = curl_init();

        // Log the request details
        Log::info(json_encode([
            "url" => $this->apiUrl . $url,
            "data" => $data,
            "Api key" => $this->apiKey,
        ]));

        // Convert the data array to JSON
        $jsonData = json_encode($data);

        curl_setopt_array($curl, array(
            CURLOPT_URL => $this->apiUrl . $url,
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_ENCODING => '',
            CURLOPT_MAXREDIRS => 10,
            CURLOPT_TIMEOUT => 0,
            CURLOPT_FOLLOWLOCATION => true,
            CURLOPT_HTTP_VERSION => CURL_HTTP_VERSION_1_1,
            CURLOPT_CUSTOMREQUEST => 'POST',
            CURLOPT_POSTFIELDS => $jsonData,
            CURLOPT_HTTPHEADER => array(
                "Authorization: Bearer $this->apiKey",
                "Content-Type: application/json", // Set the content type to JSON
                // "Accept: application/json", // Set the content type to JSON
                // "Content-Length: " . strlen($jsonData) // Set the content length
            ),
        ));

        $response = curl_exec($curl);
        // $response = json_decode( $response,true);

        // Log the response
        Log::info($response);

        curl_close($curl);

        // {"status":false,"description":"Missing information, please check the errors","errors":{"email":"The email field must contain a valid email address."}}

        return $response;
        // return json_decode($response, true);
    }

    public function patch($url, $data){

        $curl = curl_init();

        curl_setopt_array($curl, array(
        CURLOPT_URL => $this->apiUrl . $url,
        CURLOPT_RETURNTRANSFER => true,
        CURLOPT_ENCODING => '',
        CURLOPT_MAXREDIRS => 10,
        CURLOPT_TIMEOUT => 0,
        CURLOPT_FOLLOWLOCATION => true,
        CURLOPT_HTTP_VERSION => CURL_HTTP_VERSION_1_1,
        CURLOPT_CUSTOMREQUEST => 'PATCH',
        CURLOPT_POSTFIELDS => json_encode($data),
        CURLOPT_HTTPHEADER => array(
            "Authorization: Bearer $this->apiKey",
            "Content-Type: application/json",
        ),
        ));

        $response = curl_exec($curl);

        curl_close($curl);
        return json_decode($response, true);

}
}





/////////////////////////////////////////////////////
// public function post($url, array $data) {
//     $curl = curl_init();

//     // Log the request details
//     Log::info([
//         "url" => $this->apiUrl . $url,
//         "data" => $data,
//         "Api key" => $this->apiKey,
//     ]);

//     // Convert the data array to JSON
//     $jsonData = json_encode($data);


//     curl_setopt_array($curl, array(
//         CURLOPT_URL => $this->apiUrl . $url,
//         CURLOPT_RETURNTRANSFER => true,
//         CURLOPT_ENCODING => '',
//         CURLOPT_MAXREDIRS => 10,
//         CURLOPT_TIMEOUT => 0,
//         CURLOPT_FOLLOWLOCATION => true,
//         CURLOPT_HTTP_VERSION => CURL_HTTP_VERSION_1_1,
//         CURLOPT_CUSTOMREQUEST => 'POST',
//         CURLOPT_POSTFIELDS => $jsonData,
//         CURLOPT_HTTPHEADER => array(
//             "Authorization: Bearer $this->apiKey",
//             "Content-Type: application/json", // Set the content type to JSON
//             "Content-Length: " . strlen($jsonData) // Set the content length
//         ),
//     ));

//     $response = curl_exec($curl);
//     // $response = json_decode( $response,true);
//     //error handling
//     $httpCode = curl_getinfo($curl, CURLINFO_HTTP_CODE);
//     $error = curl_error($curl); // Get cURL error if any
//     curl_close($curl);

//     if ($error) {
//         // Log error and throw exception
//         Log::error("cURL Error: " . $error);
//         throw new \Exception("cURL Error: " . $error);
//     }

//     if ($httpCode >= 400) {
//         // Handle HTTP errors
//         Log::error("HTTP Error: $httpCode - Response: " . $response);
//         // throw new \Exception("HTTP Error: $httpCode - Response: " . $response);
//         throw new \Exception(json_encode([
//             "HTTP Error" => $httpCode,
//             "Response" => $response
//         ]));
//     }

//     // Log the response
//     Log::info($response);

//     // curl_close($curl);

//     // {"status":false,"description":"Missing information, please check the errors","errors":{"email":"The email field must contain a valid email address."}}

//     return $response;
// }

// public function get($url){
//     $curl = curl_init();

//     curl_setopt_array($curl, array(
//     CURLOPT_URL =>$this->apiUrl . $url,
//     CURLOPT_RETURNTRANSFER => true,
//     CURLOPT_ENCODING => '',
//     CURLOPT_MAXREDIRS => 10,
//     CURLOPT_TIMEOUT => 0,
//     CURLOPT_FOLLOWLOCATION => true,
//     CURLOPT_HTTP_VERSION => CURL_HTTP_VERSION_1_1,
//     CURLOPT_CUSTOMREQUEST => 'GET',
//     CURLOPT_HTTPHEADER => array(
//         "Authorization: Bearer $this->apiKey"
//     ),
//     ));

//     $response = curl_exec($curl);
//     //error handling
//     $httpCode = curl_getinfo($curl, CURLINFO_HTTP_CODE);
//     $error = curl_error($curl); // Get cURL error if any
//     curl_close($curl);

//     if ($error) {
//         // Log error and throw exception
//         Log::error("cURL Error: " . $error);
//         throw new \Exception("cURL Error: " . $error);
//     }

//     if ($httpCode >= 400) {
//         // Handle HTTP errors
//         Log::error("HTTP Error: $httpCode - Response: " . $response);
//         throw new \Exception("HTTP Error: $httpCode - Response: " . $response);
//     }

//     // curl_close($curl);
//     return $response;

// }
