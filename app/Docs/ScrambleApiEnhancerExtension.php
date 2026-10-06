<?php

namespace App\Docs;

use Dedoc\Scramble\Extensions\OperationExtension;
use Dedoc\Scramble\Support\Generator\Operation;
use Dedoc\Scramble\Support\Generator\Parameter;
use Dedoc\Scramble\Support\Generator\RequestBodyObject;
use Dedoc\Scramble\Support\Generator\Response;
use Dedoc\Scramble\Support\Generator\Schema;
use Dedoc\Scramble\Support\Generator\Types\ArrayType;
use Dedoc\Scramble\Support\Generator\Types\BooleanType;
use Dedoc\Scramble\Support\Generator\Types\IntegerType;
use Dedoc\Scramble\Support\Generator\Types\NumberType;
use Dedoc\Scramble\Support\Generator\Types\ObjectType;
use Dedoc\Scramble\Support\Generator\Types\StringType;
use Dedoc\Scramble\Support\Generator\Types\Type;
use Dedoc\Scramble\Support\RouteInfo;
use Illuminate\Support\Str;

class ScrambleApiEnhancerExtension extends OperationExtension
{
    public function handle(Operation $operation, RouteInfo $routeInfo)
    {
        $uri = trim($routeInfo->route->uri(), '/');
        $method = strtolower($operation->method);

        // 1. Enrich / Set Request Body
        $this->enrichRequestBody($operation, $uri, $method, $routeInfo);

        // 2. Enrich / Set Responses (Success & Error schemas and examples)
        $this->enrichResponses($operation, $uri, $method, $routeInfo);
    }

    /**
     * Enrich or create request body with realistic examples and typed fields.
     */
    protected function enrichRequestBody(Operation $operation, string $uri, string $method, RouteInfo $routeInfo): void
    {
        if ($method === 'get') {
            return;
        }

        $requestExample = $this->getRequestExampleForUri($uri, $method);

        if ($requestExample !== null) {
            $schemaType = $this->buildTypeFromData($requestExample);
            $operation->addRequestBodyObject(
                RequestBodyObject::make()
                    ->setContent('application/json', Schema::fromType($schemaType))
            );
        }
    }

    /**
     * Enrich responses: fix generic strings, add realistic 200, 400, 401, 422 responses.
     */
    protected function enrichResponses(Operation $operation, string $uri, string $method, RouteInfo $routeInfo): void
    {
        $successExample = $this->getSuccessResponseForUri($uri, $method);

        // Check if existing 200/201 response is a generic string
        $hasDetailed200 = false;
        $responses = [];

        foreach ($operation->responses as $response) {
            if ($response instanceof Response) {
                $code = (int) $response->code;
                if ($code === 200 || $code === 201) {
                    $content = $response->content['application/json'] ?? null;
                    $schemaArray = $content instanceof Schema ? $content->toArray() : ($content['schema'] ?? []);
                    
                    // If it's a generic string or empty object, replace with our rich schema
                    if (isset($schemaArray['type']) && $schemaArray['type'] === 'string' && $successExample !== null) {
                        $schemaType = $this->buildTypeFromData($successExample);
                        $response->setContent('application/json', Schema::fromType($schemaType));
                        $response->description = 'Successful response';
                        $hasDetailed200 = true;
                    } elseif (isset($schemaArray['properties']) || isset($schemaArray['anyOf'])) {
                        $hasDetailed200 = true;
                    }
                }
            }
            $responses[] = $response;
        }

        // If no detailed 200 was present and we have an example, add one
        if (!$hasDetailed200) {
            $example = $successExample ?? [
                'status' => 'success',
                'message' => 'Operation completed successfully.',
                'data' => [
                    'id' => 1,
                    'status' => 'completed',
                    'created_at' => '2026-10-06T18:00:00.000000Z'
                ],
                'type' => 'success'
            ];

            $successResponse = Response::make(200)
                ->description('Successful response')
                ->setContent('application/json', Schema::fromType($this->buildTypeFromData($example)));

            $responses = array_filter($responses, fn($r) => !($r instanceof Response) || (int) $r->code !== 200);
            array_unshift($responses, $successResponse);
        }

        // Add 400 Bad Request error response if not present
        if (!collect($responses)->contains(fn($r) => $r instanceof Response && (int) $r->code === 400)) {
            $badRequestExample = [
                'status' => 'error',
                'message' => [
                    'error' => ['The requested action could not be processed due to invalid parameters or state.']
                ],
                'data' => null,
                'type' => 'error'
            ];
            $responses[] = Response::make(400)
                ->description('Bad Request / Business Logic Error')
                ->setContent('application/json', Schema::fromType($this->buildTypeFromData($badRequestExample)));
        }

        // Add 401 Unauthorized for authenticated endpoints
        $isPublic = $this->isPublicRoute($uri);
        if (!$isPublic && !collect($responses)->contains(fn($r) => $r instanceof Response && (int) $r->code === 401)) {
            $unauthExample = [
                'status' => 'error',
                'message' => [
                    'error' => ['Unauthenticated. Please provide a valid Bearer token in the Authorization header.']
                ],
                'type' => 'error'
            ];
            $responses[] = Response::make(401)
                ->description('Unauthorized / Expired Token')
                ->setContent('application/json', Schema::fromType($this->buildTypeFromData($unauthExample)));
        }

        // Add 422 Validation Error for POST/PUT/PATCH endpoints
        if ($method !== 'get' && !collect($responses)->contains(fn($r) => $r instanceof Response && (int) $r->code === 422)) {
            $validationExample = [
                'status' => 'error',
                'message' => [
                    'error' => ['The given data was invalid.']
                ],
                'errors' => [
                    'amount' => ['The amount field is required and must be greater than 0.'],
                    'currency' => ['The selected currency is invalid or unsupported.']
                ],
                'type' => 'error'
            ];
            $responses[] = Response::make(422)
                ->description('Validation Error')
                ->setContent('application/json', Schema::fromType($this->buildTypeFromData($validationExample)));
        }

        $operation->responses = $responses;
    }

    /**
     * Recursively convert PHP array/scalar data into OpenAPI Types with examples.
     */
    protected function buildTypeFromData($data): Type
    {
        if (is_array($data)) {
            $isAssoc = $this->isAssociativeArray($data);

            if ($isAssoc) {
                $object = new ObjectType();
                $required = [];

                foreach ($data as $key => $value) {
                    $propType = $this->buildTypeFromData($value);
                    $propType->setDescription(Str::headline((string) $key));
                    $object->addProperty((string) $key, $propType);

                    if ($value !== null) {
                        $required[] = (string) $key;
                    }
                }

                if (!empty($required)) {
                    $object->setRequired($required);
                }

                return $object;
            }

            // Sequential Array (List)
            $arrayType = new ArrayType();
            if (!empty($data)) {
                $itemType = $this->buildTypeFromData($data[0]);
                $arrayType->setItems($itemType);
            } else {
                $arrayType->setItems(new StringType());
            }

            return $arrayType;
        }

        if (is_int($data)) {
            $type = new IntegerType();
            $type->example($data);
            return $type;
        }

        if (is_float($data)) {
            $type = new NumberType();
            $type->example($data);
            return $type;
        }

        if (is_bool($data)) {
            $type = new BooleanType();
            $type->example($data);
            return $type;
        }

        if ($data === null) {
            $type = new StringType();
            $type->nullable(true);
            return $type;
        }

        // String
        $type = new StringType();
        $type->example((string) $data);
        return $type;
    }

    protected function isAssociativeArray(array $arr): bool
    {
        if ([] === $arr) return false;
        return array_keys($arr) !== range(0, count($arr) - 1);
    }

    protected function isPublicRoute(string $uri): bool
    {
        $publicPatterns = [
            'api/login', 'api/register', 'api/v1/login', 'api/v1/register',
            'api/forget/password', 'api/verify/code', 'api/reset/password',
            'api/auth/biometric/login', 'api/webhook', 'api/webhook/*'
        ];

        foreach ($publicPatterns as $pattern) {
            if (Str::is($pattern, $uri)) {
                return true;
            }
        }

        return false;
    }

    /**
     * Map endpoint URIs to realistic request payloads.
     */
    protected function getRequestExampleForUri(string $uri, string $method): ?array
    {
        $patterns = [
            // Authentication & Security
            '*register*' => [
                'firstname' => 'John',
                'lastname' => 'Doe',
                'email' => 'john.doe@example.com',
                'phone' => '+2348012345678',
                'password' => 'SecurePass123!',
                'password_confirmation' => 'SecurePass123!',
                'country' => 'Nigeria',
                'referral_code' => 'REF84920'
            ],
            '*login*' => [
                'email' => 'john.doe@example.com',
                'password' => 'SecurePass123!',
                'device_id' => 'iPhone15Pro-Device-UUID-98213'
            ],
            '*forget/password*' => [
                'credentials' => 'john.doe@example.com'
            ],
            '*verify/code*' => [
                'code' => '482910'
            ],
            '*reset/password*' => [
                'token' => '9a8b7c6d5e4f3a2b1',
                'password' => 'NewSecurePassword123!',
                'password_confirmation' => 'NewSecurePassword123!'
            ],
            '*biometric/register*' => [
                'public_key' => 'MIIBIjANBgkqhkiG9w0BAQEFAAOCAQ8AMIIBCgKCAQEA0',
                'device_id' => 'iPhone15Pro-Device-UUID-98213',
                'device_model' => 'iPhone 15 Pro',
                'device_os' => 'iOS 18.1'
            ],
            '*biometric/login*' => [
                'device_id' => 'iPhone15Pro-Device-UUID-98213',
                'signature' => 'MEUCIQCz3Z2k...vR1A=='
            ],
            '*pin/setup*' => [
                'pin' => '1234',
                'pin_confirmation' => '1234'
            ],
            '*pin/check*' => [
                'pin' => '1234'
            ],
            '*pin/update*' => [
                'current_pin' => '1234',
                'new_pin' => '5678',
                'new_pin_confirmation' => '5678'
            ],

            // Profile & KYC
            '*profile/update*' => [
                'firstname' => 'John',
                'lastname' => 'Doe',
                'address' => '15 Marina Street, Lagos Island',
                'city' => 'Lagos',
                'state' => 'Lagos State',
                'zip_code' => '100001'
            ],
            '*profile/update/password*' => [
                'current_password' => 'OldPassword123!',
                'password' => 'NewPassword123!',
                'password_confirmation' => 'NewPassword123!'
            ],
            '*youverify/vnin*' => [
                'id_number' => '12345678901',
                'first_name' => 'John',
                'last_name' => 'Doe',
                'dob' => '1995-05-15'
            ],
            '*youverify/bvn*' => [
                'id_number' => '22113344556',
                'first_name' => 'John',
                'last_name' => 'Doe',
                'dob' => '1995-05-15'
            ],

            // Crypto Buy & Sell / Ramp
            '*buy-sell/quote*' => [
                'from_currency' => 'NGN',
                'to_currency' => 'USDT',
                'from_amount' => 150000.00,
                'side' => 'buy'
            ],
            '*buy-sell/initiate-buy*' => [
                'from_currency' => 'NGN',
                'to_currency' => 'USDT',
                'from_amount' => 150000.00,
                'network' => 'TRC20',
                'wallet_address' => 'TLyqzVGLV1srkB7dToTAwdg296pP48Vn6U'
            ],
            '*buy-sell/confirm-buy*' => [
                'merchant_reference' => 'RAMP-BUY-77492019'
            ],
            '*buy-sell/initiate-sell*' => [
                'from_currency' => 'USDT',
                'to_currency' => 'NGN',
                'from_amount' => 100.00,
                'network' => 'TRC20',
                'bank_code' => '058',
                'account_number' => '0123456789'
            ],
            '*buy-sell/confirm-sell*' => [
                'merchant_reference' => 'RAMP-SELL-88391022'
            ],
            '*buy-sell/add-bank*' => [
                'merchant_reference' => 'RAMP-SELL-88391022',
                'bank_code' => '058',
                'account_number' => '0123456789'
            ],

            // Multi-Currency Virtual Accounts
            '*multi-currency/accounts/create*' => [
                'currency' => 'USD',
                'account_type' => 'individual',
                'channel' => 'ach'
            ],
            '*multi-currency/transfer*' => [
                'source_currency' => 'USD',
                'destination_currency' => 'NGN',
                'amount' => 250.00,
                'beneficiary' => [
                    'account_number' => '0123456789',
                    'bank_code' => '058',
                    'account_name' => 'John Doe'
                ],
                'narration' => 'Payment for services'
            ],
            '*multi-currency/quote*' => [
                'source_currency' => 'USD',
                'destination_currency' => 'NGN',
                'amount' => 500.00
            ],

            // Virtual Cards (Sudo & Crypto Card)
            '*sudo/create-card*' => [
                'currency' => 'USD',
                'type' => 'virtual',
                'brand' => 'MasterCard',
                'amount' => 50.00,
                'billing_address' => [
                    'street' => '123 Broadway St',
                    'city' => 'New York',
                    'state' => 'NY',
                    'postal_code' => '10001',
                    'country' => 'USA'
                ]
            ],
            '*sudo/fund-card*' => [
                'card_id' => 'card_sud_984201948',
                'amount' => 100.00,
                'currency' => 'USD',
                'from_wallet' => 'USDT'
            ],
            '*sudo/withdraw*' => [
                'card_id' => 'card_sud_984201948',
                'amount' => 50.00
            ],
            '*sudo/block-card*' => [
                'card_id' => 'card_sud_984201948',
                'reason' => 'Suspected unusual activity'
            ],
            '*sudo/unblock-card*' => [
                'card_id' => 'card_sud_984201948'
            ],
            '*crypto-card/create*' => [
                'currency' => 'USD',
                'amount' => 50.00,
                'funding_crypto' => 'USDT'
            ],
            '*crypto-card/fund*' => [
                'card_id' => 'cc_8492019',
                'amount' => 75.00,
                'funding_crypto' => 'USDT'
            ],

            // Flex & Lock Savings & EasyEarn
            '*savings/deposit*' => [
                'currency' => 'NGN',
                'amount' => 25000.00,
                'source' => 'wallet'
            ],
            '*savings/withdraw*' => [
                'currency' => 'NGN',
                'amount' => 10000.00,
                'destination' => 'wallet'
            ],
            '*savings/lock/create*' => [
                'title' => 'Car Purchase Goal 2027',
                'currency' => 'NGN',
                'amount' => 500000.00,
                'duration_months' => 6,
                'interest_payout' => 'at_maturity'
            ],
            '*usdt-easyearn/invest*' => [
                'plan_id' => 2,
                'amount' => 500.00
            ],
            '*usdt-easyearn/top-up*' => [
                'amount' => 200.00
            ],

            // Bills & Utilities
            '*bill-pay/validate-customer*' => [
                'category' => 'electricity',
                'operator_id' => 'EKEDC',
                'customer_id' => '01011509281'
            ],
            '*bill-pay/pay*' => [
                'category' => 'airtime',
                'operator_id' => 'MTN_NG',
                'customer_id' => '08031234567',
                'amount' => 2000.00,
                'currency' => 'NGN'
            ],
            '*giftcard/order*' => [
                'product_id' => 1402,
                'quantity' => 1,
                'unit_price' => 50.00,
                'currency' => 'USD',
                'recipient_email' => 'friend@example.com'
            ],

            // Wallets & Swaps
            '*wallets/swap*' => [
                'from_wallet' => 'USDT',
                'to_wallet' => 'NGN',
                'amount' => 100.00
            ]
        ];

        foreach ($patterns as $pattern => $example) {
            if (Str::is($pattern, $uri)) {
                return $example;
            }
        }

        return null;
    }

    /**
     * Map endpoint URIs to realistic success responses.
     */
    protected function getSuccessResponseForUri(string $uri, string $method): ?array
    {
        $patterns = [
            // Authentication & Security
            '*register*' => [
                'status' => 'success',
                'message' => 'Registration successful. Welcome to Bitmonie!',
                'data' => [
                    'token' => '1|laravel_passport_bearer_token_string_example',
                    'user' => [
                        'id' => 1,
                        'firstname' => 'John',
                        'lastname' => 'Doe',
                        'email' => 'john.doe@example.com',
                        'phone' => '+2348012345678',
                        'email_verified' => true,
                        'kyc_status' => 'verified',
                        'two_factor_enabled' => false
                    ]
                ],
                'type' => 'success'
            ],
            '*login*' => [
                'status' => 'success',
                'message' => 'Login successful.',
                'data' => [
                    'token' => '1|laravel_passport_bearer_token_string_example',
                    'user' => [
                        'id' => 1,
                        'firstname' => 'John',
                        'lastname' => 'Doe',
                        'email' => 'john.doe@example.com',
                        'phone' => '+2348012345678',
                        'pin_set' => true
                    ]
                ],
                'type' => 'success'
            ],
            '*user/profile*' => [
                'status' => 'success',
                'message' => 'Profile retrieved successfully.',
                'data' => [
                    'user' => [
                        'id' => 1,
                        'firstname' => 'John',
                        'lastname' => 'Doe',
                        'email' => 'john.doe@example.com',
                        'phone' => '+2348012345678',
                        'address' => '15 Marina Street, Lagos Island',
                        'city' => 'Lagos',
                        'state' => 'Lagos State',
                        'country' => 'Nigeria',
                        'kyc_level' => 2,
                        'created_at' => '2026-01-15T10:30:00.000000Z'
                    ]
                ],
                'type' => 'success'
            ],

            // Crypto Buy & Sell / Ramp
            '*buy-sell/supported-assets*' => [
                'status' => 'success',
                'message' => 'Supported assets fetched successfully',
                'data' => [
                    [
                        'code' => 'USDT',
                        'name' => 'Tether USD',
                        'symbol' => 'USDT',
                        'networks' => ['TRC20', 'BEP20', 'ERC20'],
                        'min_buy' => 10.00,
                        'min_sell' => 10.00
                    ],
                    [
                        'code' => 'USDC',
                        'name' => 'USD Coin',
                        'symbol' => 'USDC',
                        'networks' => ['ERC20', 'SOL', 'POLYGON'],
                        'min_buy' => 10.00,
                        'min_sell' => 10.00
                    ],
                    [
                        'code' => 'BTC',
                        'name' => 'Bitcoin',
                        'symbol' => 'BTC',
                        'networks' => ['BTC', 'BEP20'],
                        'min_buy' => 0.0005,
                        'min_sell' => 0.0005
                    ]
                ],
                'type' => 'success'
            ],
            '*buy-sell/quote*' => [
                'status' => 'success',
                'message' => 'Quote calculated successfully.',
                'data' => [
                    'quote_id' => 'QT_984201849',
                    'from_currency' => 'NGN',
                    'to_currency' => 'USDT',
                    'from_amount' => 150000.00,
                    'to_amount' => 98.68,
                    'exchange_rate' => 1520.00,
                    'fee' => 0.50,
                    'expires_in_seconds' => 120,
                    'expires_at' => '2026-10-06T18:05:00.000000Z'
                ],
                'type' => 'success'
            ],
            '*buy-sell/initiate-buy*' => [
                'status' => 'success',
                'message' => 'On-ramp transaction initiated. Please transfer the exact fiat amount to the dedicated bank account.',
                'data' => [
                    'merchant_reference' => 'RAMP-BUY-77492019',
                    'fiat_amount' => 150000.00,
                    'fiat_currency' => 'NGN',
                    'crypto_amount' => 98.68,
                    'crypto_currency' => 'USDT',
                    'network' => 'TRC20',
                    'bank_details' => [
                        'bank_name' => 'SafeHaven Microfinance Bank',
                        'account_name' => 'Bitmonie / John Doe',
                        'account_number' => '9948201948',
                        'expires_in_minutes' => 60
                    ]
                ],
                'type' => 'success'
            ],
            '*buy-sell/initiate-sell*' => [
                'status' => 'success',
                'message' => 'Off-ramp transaction initiated. Please deposit the crypto amount to the generated address.',
                'data' => [
                    'merchant_reference' => 'RAMP-SELL-88391022',
                    'crypto_amount' => 100.00,
                    'crypto_currency' => 'USDT',
                    'network' => 'TRC20',
                    'deposit_address' => 'TLyqzVGLV1srkB7dToTAwdg296pP48Vn6U',
                    'expected_fiat_amount' => 152000.00,
                    'payout_bank' => [
                        'bank_name' => 'Guaranty Trust Bank',
                        'account_number' => '0123456789',
                        'account_name' => 'John Doe'
                    ]
                ],
                'type' => 'success'
            ],
            '*buy-sell/status*' => [
                'status' => 'success',
                'message' => 'Transaction status retrieved.',
                'data' => [
                    'merchant_reference' => 'RAMP-BUY-77492019',
                    'status' => 'completed',
                    'fiat_amount' => 150000.00,
                    'crypto_amount' => 98.68,
                    'tx_hash' => '0x8f7a9b0c1d2e3f4a5b6c7d8e9f0a1b2c3d4e5f6a',
                    'completed_at' => '2026-10-06T18:02:15.000000Z'
                ],
                'type' => 'success'
            ],

            // Multi-Currency Virtual Accounts
            '*multi-currency/accounts*' => [
                'status' => 'success',
                'message' => 'Multi-currency accounts fetched successfully.',
                'data' => [
                    [
                        'id' => 1,
                        'currency' => 'USD',
                        'account_name' => 'John Doe',
                        'account_number' => '9840291840',
                        'routing_number' => '021000021',
                        'bank_name' => 'Lead Bank',
                        'bank_address' => 'Kansas City, MO, USA',
                        'balance' => 1450.75,
                        'status' => 'active'
                    ],
                    [
                        'id' => 2,
                        'currency' => 'EUR',
                        'account_name' => 'John Doe',
                        'account_number' => 'FR7630006000011234567890189',
                        'bic_swift' => 'BNPAFR21',
                        'bank_name' => 'Banking Circle SA',
                        'balance' => 820.00,
                        'status' => 'active'
                    ]
                ],
                'type' => 'success'
            ],

            // Virtual Cards
            '*sudo/cards*' => [
                'status' => 'success',
                'message' => 'Virtual cards retrieved successfully.',
                'data' => [
                    [
                        'id' => 'card_sud_984201948',
                        'card_name' => 'John Doe',
                        'masked_pan' => '5399 •••• •••• 8492',
                        'expiry_month' => '08',
                        'expiry_year' => '2028',
                        'cvv' => '•••',
                        'currency' => 'USD',
                        'balance' => 250.00,
                        'status' => 'active',
                        'brand' => 'MasterCard',
                        'type' => 'virtual'
                    ]
                ],
                'type' => 'success'
            ],
            '*sudo/card-details*' => [
                'status' => 'success',
                'message' => 'Sensitive card details decrypted.',
                'data' => [
                    'id' => 'card_sud_984201948',
                    'card_number' => '5399849201928492',
                    'expiry' => '08/28',
                    'cvv' => '482',
                    'balance' => 250.00,
                    'billing_address' => [
                        'street' => '123 Broadway St',
                        'city' => 'New York',
                        'state' => 'NY',
                        'postal_code' => '10001',
                        'country' => 'USA'
                    ]
                ],
                'type' => 'success'
            ],

            // Savings & EasyEarn
            '*savings/summary*' => [
                'status' => 'success',
                'message' => 'Savings summary fetched.',
                'data' => [
                    'flex_balance' => 850000.00,
                    'locked_balance' => 2500000.00,
                    'total_interest_earned' => 145200.50,
                    'current_flex_apy' => 12.5,
                    'currency' => 'NGN'
                ],
                'type' => 'success'
            ],
            '*usdt-easyearn/plans*' => [
                'status' => 'success',
                'message' => 'USDT EasyEarn plans fetched.',
                'data' => [
                    [
                        'id' => 1,
                        'name' => 'Flexible Tier',
                        'min_deposit' => 10.00,
                        'max_deposit' => 50000.00,
                        'apy' => 8.5,
                        'duration_days' => 0,
                        'interest_distribution' => 'daily'
                    ],
                    [
                        'id' => 2,
                        'name' => '90-Day Fixed Vault',
                        'min_deposit' => 100.00,
                        'max_deposit' => 100000.00,
                        'apy' => 14.0,
                        'duration_days' => 90,
                        'interest_distribution' => 'at_maturity'
                    ]
                ],
                'type' => 'success'
            ],

            // Wallets & History
            '*wallets*' => [
                'status' => 'success',
                'message' => 'Wallets fetched successfully.',
                'data' => [
                    [
                        'currency' => 'USDT',
                        'name' => 'Tether USD',
                        'balance' => 2450.80,
                        'spendable_balance' => 2350.80,
                        'reserved_balance' => 100.00,
                        'fiat_equivalent_usd' => 2450.80
                    ],
                    [
                        'currency' => 'NGN',
                        'name' => 'Nigerian Naira',
                        'balance' => 895400.00,
                        'spendable_balance' => 895400.00,
                        'reserved_balance' => 0.00,
                        'fiat_equivalent_usd' => 589.08
                    ],
                    [
                        'currency' => 'BTC',
                        'name' => 'Bitcoin',
                        'balance' => 0.045,
                        'spendable_balance' => 0.045,
                        'reserved_balance' => 0.00,
                        'fiat_equivalent_usd' => 2925.00
                    ]
                ],
                'type' => 'success'
            ],
            '*transaction/history*' => [
                'status' => 'success',
                'message' => 'Transactions retrieved.',
                'data' => [
                    [
                        'id' => 101,
                        'trx_id' => 'TRX_849201849',
                        'type' => 'buy_crypto',
                        'title' => 'Bought USDT with NGN',
                        'amount' => 150000.00,
                        'currency' => 'NGN',
                        'exchange_amount' => 98.68,
                        'exchange_currency' => 'USDT',
                        'status' => 'completed',
                        'created_at' => '2026-10-06T17:45:00.000000Z'
                    ]
                ],
                'type' => 'success'
            ]
        ];

        foreach ($patterns as $pattern => $example) {
            if (Str::is($pattern, $uri)) {
                return $example;
            }
        }

        return null;
    }
}
