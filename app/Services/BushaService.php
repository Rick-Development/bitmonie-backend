<?php

namespace App\Services;

use Illuminate\Support\Facades\Http;
use Exception;
use App\Models\Bank;
use App\Models\VirtualAccounts;

class BushaService
{
    protected $baseUrl;
    protected $apiKey;

    public function __construct()
    {
        $this->baseUrl = config('services.busha.base_url', 'https://api.connect.busha.co');
        $this->apiKey = config('services.busha.api_key');
    }

    protected function client()
    {
        // Busha Connect API uses Bearer Token
        return Http::withToken($this->apiKey)->acceptJson();
    }


    public function quote($payload = []){
         $response = $this->client()->post("{$this->baseUrl}/v1/quotes", $payload);

        if ($response->failed()) {
            // \Illuminate\Support\Facades//\Log::error('Busha Quote Failed', [
            //     'status' => $response->status(),
            //     'body' => $response->body(),
            //     'payload' => $payload
            // ]);
            return $response->json();
            // throw new Exception($response->json('message') ?? 'Failed to create quote. Check logs.');    
        }
        return $response->json();
    }

    /**
     * Get Quote Details
     * Fetch the details of an existing quote by ID.
     */
    public function getQuoteDetails($quoteId)
    {
        $response = $this->client()->get("{$this->baseUrl}/v1/quotes/{$quoteId}");

        //\Log::info('Busha quote details', $response->json());
        if ($response->failed()) {
            throw new Exception($response->json('message') ?? 'Failed to fetch quote details.');
        }

        return $response->json();
    }



    /**
     * Create Conversion Quote
     * Use this to get the rate/amount for a Buy/Sell operation.
     */
    public function createQuote($sourceCurrency, $targetCurrency, $amount, $amountType = 'source')
    {
        // amountType: 'source' (I want to spend X) or 'target' (I want to receive X)
        
        $payload = [
            'type' => 'convert',
            'source_currency' => $sourceCurrency,
            'target_currency' => $targetCurrency,
        ];

        if ($amountType === 'source') {
            $payload['source_amount'] = (string)$amount;
        } else {
            $payload['target_amount'] = (string)$amount;
        }

        $response = $this->client()->post("{$this->baseUrl}/v1/quotes", $payload);

        //\Log::info('Busha quote response', $response->json());
        if ($response->failed()) {
            // \Illuminate\Support\Facades//\Log::error('Busha Quote Failed', [
            //     'status' => $response->status(),
            //     'body' => $response->body(),
            //     'payload' => $payload
            // ]);
            throw new Exception($response->json('message') ?? 'Failed to create quote. Check logs.');
        }

        return $response->json();
    }

    /**
     * Execute Quote (Transfer)
     * This finalizes the conversion.
     */
    public function executeQuote($quoteId, $reference)
    {
        // Endpoint: POST /v1/transfers
        $response = $this->client()->post("{$this->baseUrl}/v1/transfers", [
            'quote_id' => $quoteId,
            'reference' => $reference // Optional but good for tracking
        ]);

        //\Log::info('Busha transfer response', $response->json());
        if ($response->failed()) {
            throw new Exception($response->json('message') ?? 'Failed to execute transfer');
        }

        return $response->json();
    }

    public function getTransfer($transferId)
    {
        // Endpoint: GET /v1/transfers/{id}
        // Expects Transfer ID, not Quote ID.
        $response = $this->client()->get("{$this->baseUrl}/v1/transfers/{$transferId}");
        
        if ($response->failed()) {
            // \Illuminate\Support\Facades//\Log::error('Busha Fetch Transfer Failed', [
            //     'id' => $transferId,
            //     'status' => $response->status(),
            //     'body' => $response->body()
            // ]);
            throw new Exception($response->json('message') ?? 'Failed to fetch transfers');
        }

        return $response->json();
    }


    public function createRecipient($user)
    {
        $bank = Bank::where('id', 188)->first();
        $account = VirtualAccounts::where('user_id', $user->id)
            ->where(function ($query) {
                $query->where('provider', 'safehaven')
                    ->orWhere(function ($fallback) {
                        $fallback->whereNull('provider')
                            ->where(function ($accountQuery) {
                                $accountQuery->where('bank_name', 'like', '%SafeHaven%')
                                    ->orWhereIn('bank_code', ['090286', '090281']);
                            });
                    });
            })
            ->latest('id')
            ->first();

        if (!$account || empty($account->account_number)) {
            return false;
        }

        if (empty($account->provider)) {
            $account->update(['provider' => 'safehaven']);
        }

        $bankName = $bank->name ?? $account->bank_name ?? 'SafeHaven Microfinance Bank';
        $bankCode = $account->bank_code;

        if (empty($bankCode) || $bankCode === '000000' || strlen($bankCode) !== 6) {
            $bankCode = '090286';
        }

        $data = [

                        "currency_id" => "NGN",
                        "country_id" => "NG",
                        "type" => "ngn_bank_transfer",  
                        "legal_entity_type" => "personal",
                        "fields" => [
                            [
                                "name" => "bank_name",
                                "value" => $bankName
                            ],
                            [
                                "name" => "account_number",
                                "value" => $account->account_number,
                            ],
                            [
                                "name" => "bank_code",
                                "value" => $bankCode
                            ],
                            [
                                "name" => "account_name",
                                "value" => $account->account_name,
                            ]
                        ]
                            ];
        $response = $this->client()->post("{$this->baseUrl}/v1/recipients", $data );

        //\Log::info('Busha create recipient response', $response->json());
        if ($response->failed()) {
            return false;
            // throw new Exception($response->json('message') ?? 'Failed to create recipient');
        }

        $user->busha_recipient_id = $response->json('id');
        $user->save();
        return true;
        // return $response->json();
    }
    
    /**
     * Get List of Banks
     */
    public function getBanks()
    {
        $response = $this->client()->get("{$this->baseUrl}/v1/banks");

        if ($response->failed()) {
            throw new Exception($response->json('message') ?? 'Failed to fetch banks');
        }

        return $response->json();
    }

    public function createPayoutRecipient($details)
    {
        $data = [
            "currency_id" => "NGN",
            "country_id" => "NG",
            "type" => "ngn_bank_transfer",
            "legal_entity_type" => "personal",
            "fields" => [
                [
                    "name" => "bank_name",
                    "value" => $details['bank_name']
                ],
                [
                    "name" => "account_number",
                    "value" => $details['account_number'],
                ],
                [
                    "name" => "bank_code",
                    "value" => $details['bank_code']
                ],
                [
                    "name" => "account_name",
                    "value" => $details['account_name'],
                ]
            ]
        ];

        $response = $this->client()->post("{$this->baseUrl}/v1/recipients", $data);

        //\Log::info('Busha create payout recipient response', $response->json());
        
        if ($response->failed()) {
            throw new Exception($response->json()['error']['message'] ?? 'Failed to create recipient on Busha');
        }

        return $response->json();
    }



    public function getCurrencies()
    {
        $response = $this->client()->get("{$this->baseUrl}/v1/currencies?sort=asc&ramp=true");
        // //\Log::info('Busha currencies response', $response->json());

        if ($response->failed()) {
            throw new Exception($response->json('message') ?? 'Failed to fetch currencies');
        }

        return $response->json();
    }


    public function getNetworks($currency)
    {
        $response = $this->client()->get("{$this->baseUrl}/v1/currencies/{$currency}");
        // //\Log::info('Busha currencies response', $response->json());

        if ($response->failed()) {
            throw new Exception($response->json('message') ?? 'Failed to fetch currencies');
        }

        return $response->json();
    }

}
