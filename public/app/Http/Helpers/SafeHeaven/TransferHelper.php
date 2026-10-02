<?php
namespace App\Http\Helpers\SafeHeaven;

use App\Http\Helpers\SafeHeaven\ApiConnectionHelper;   
use App\Models\UserWallet;


class TransferHelper extends ApiConnectionHelper{
    
    public function bankList(){
        $url = '/transfers/banks';
        $response = $this->get($url);
        $decodedResponse = json_decode($response,true);
        return $decodedResponse;
    }  
    
    public function customBankList()
{
    $url = 'https://wema-alatdev-apimgt.azure-api.net/wallet-transfer/api/Shared/GetAllBanks';

    // Initialize cURL
    $ch = curl_init($url);

    // Set cURL options
    curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
    curl_setopt($ch, CURLOPT_HTTPHEADER, [
        'Content-Type: application/json',
        // Add your required headers here if any (e.g., API Key)
        'Ocp-Apim-Subscription-Key: 1fc7350b7aac48cc81ad00a9ff662d6f'
    ]);

    // Execute request
    $response = curl_exec($ch);

    // Handle cURL error
    if (curl_errno($ch)) {
        $error = curl_error($ch);
        curl_close($ch);
        echo json_encode([
            'statusCode' => 500,
            'responseCode' => '99',
            'message' => 'Error: ' . $error,
            'data' => []
        ]);
        return;
    }

    // Close cURL
    curl_close($ch);

    // Decode original JSON
    $decoded = json_decode($response, true);

    $formattedBanks = [];

    if (isset($decoded['result']) && is_array($decoded['result'])) {
        foreach ($decoded['result'] as $bank) {
            $formattedBanks[] = [
                'name' => $bank['bankName'] ?? '',
                'alias' => $bank['bankName'], //[$bank['bankName'] ?? ''],
                'routingKey' => $bank['bankCode'] ?? '',
                'logoImage' => $bank['bankLogo'] ?? null,
                'bankCode' => $bank['bankCode'] ?? '',
                'categoryId' => '9',
                'nubanCode' => null
            ];
        }
    }

    // Build final formatted response
    $finalResponse = [
        'statusCode' => 200,
        'responseCode' => '00',
        'message' => 'Approved or completed successfully',
        'data' => $formattedBanks
    ];

    // Output JSON
    header('Content-Type: application/json');
    echo json_encode($finalResponse);
}


    public function nameEnquiry($data){
        $url = '/transfers/name-enquiry';
        $response = $this->post($url, $data);
        $decodedResponse = json_decode($response,true);
        return $decodedResponse;
    }  

    public function transfer($data){
        $url = '/transfers';
        $response = $this->post($url, $data);
        $decodedResponse = json_decode($response,true);
        return $decodedResponse;
    }
    public function transferStatus($data){
        $url = '/transfers/status';
        $response = $this->post($url, $data);
        $decodedResponse = json_decode($response,true);
        return $decodedResponse;
    }
    public function getTransfer($data){
        $url = '/transfers/'.$data['accountId'];
        $response = $this->get($url);
        $decodedResponse = json_decode($response,true);
        return $decodedResponse;
    }


    public function validateBalance($amount){
        $user = auth()->user();
        $user_balance = UserWallet::where('user_id', $user->id)
            ->where('currency_code', 'NGN')
            ->value('balance');

        $amount = (int) $amount;
        if($user_balance< $amount){
            return response()->json([
                'message' => 'Insufficient balance'
            ]);
        }
    }
    

}