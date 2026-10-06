<?php
namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Helpers\Payscribe\BillsPayments\AirtimeToWalletHelper;
use App\Http\Helpers\Payscribe\PayscribeBalanceHelper;
use App\Models\Transaction;
use App\Traits\Notify; // <-- 1. Import the Notify trait
use Illuminate\Http\Request;
use Illuminate\Support\Str;

class PayscribeAirtimeToWalletController extends Controller
{
    use Notify; // <-- 2. Use the trait inside the class

    private $modelPath = 'Airtime To Wallet';
    
    public function __construct(
        private AirtimeToWalletHelper $airtimeToWalletHelper, 
        private PayscribeBalanceHelper $payscribeBalanceHelper
    ) {
        //
    }

    public function airtimeToWalletLookup(Request $request)
    {
        return json_decode($this->airtimeToWalletHelper->airtimeToWalletLookup(), true);
    }

    public function airtimeToWallet(Request $request) {
        $data = $request->validate([
            'network' => 'required',
            'phone_number' => 'required',
            'from' => 'required',
            'amount' => 'required | integer | min:1000 | max:20000',
        ]);

        $referenceId = Str::uuid();
        $referenceIdString = (string) $referenceId;
        $data = array_merge($data, ['ref' => $referenceIdString]);

        try {
            $response = json_decode($this->airtimeToWalletHelper->airtimeToWallet($data), true);
            
            if ($response['status'] === true) {
                $this->payscribeBalanceHelper->createTransaction($data, $response, $this->modelPath);

                // 3. Get the authenticated user (adjust based on how you fetch your user)
                $user = $request->user() ?? auth()->user();

                if ($user) {
                    // Define parameters for your notification template placeholders
                    $params = [
                        'amount' => $data['amount'],
                        'network' => $data['network'],
                        'phone' => $data['phone_number'],
                        'reference' => $referenceIdString,
                    ];

                    // Send multi-channel notification using the trait
                    $this->sendNotification(
                        user: $user,
                        templateKey: 'AIRTIME_TO_WALLET_SUCCESS', // Ensure this template key exists in your DB
                        params: $params,
                        channels: ['mail', 'sms', 'inapp'], // Select channels you want to use
                        options: [
                            'referenceId' => $referenceIdString,
                        ]
                    );
                }
            }
            
            return $response;
        } catch (\Exception $e) {
            return [
                'status' => 'error',
                'message' => $e->getMessage()
            ];
        }
    }

    public function payDataVending(array $data) {
        $response = json_decode($this->dataBundleHelper->dataVending($data), true);

        if ($response['status'] === true) {
            $this->createTransaction($data, $response, $this->modelPath);

            // Optional: Add notification here as well if needed
            $user = auth()->user();
            if ($user) {
                $this->sendNotification(
                    user: $user,
                    templateKey: 'DATA_VENDING_SUCCESS',
                    params: ['amount' => $data['amount'], 'plan' => $data['plan']],
                    channels: ['mail', 'inapp']
                );
            }
        }

        return $response;
    }
}