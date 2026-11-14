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
            '*buy-sell/on-ramp/initiate*' => [
                'from_currency' => 'NGN',
                'to_currency' => 'USDT',
                'from_amount' => 5000.00,
                'network' => 'BEP20',
                'wallet_address' => '0x1111111111111111111111111111111111111111'
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

            // Referral bonus withdrawal: fiat amount = NGN, crypto amount = USD value (paid as USDT)
            '*referral/withdraw*' => [
                'method' => 'crypto', // fiat | crypto
                'amount' => 10.00, // fiat: NGN amount; crypto: USD amount
                'crypto_currency' => 'USDT'
            ],

            // Unified Savings: Flexi, AutoSave, TargetSavings, Transfers
            '*savings/flexi/deposit*' => [
                'amount' => 25000.00,
                'source' => 'wallet',
                'idempotency_key' => 'flexi-dep-a1b2c3d4'
            ],
            '*savings/flexi/withdraw*' => [
                'amount' => 10000.00,
                'destination' => 'wallet',
                'idempotency_key' => 'flexi-wth-e5f6g7h8'
            ],
            '*savings/flexi/transfer*' => [
                'destination_account_id' => 12,
                'amount' => 5000.00,
                'idempotency_key' => 'flexi-trf-i9j0k1l2'
            ],
            '*savings/auto' => [
                'name' => 'Rainy Day Fund',
                'target_amount' => 500000.00,
                'amount' => 5000.00,
                'frequency' => 'daily',
                'time_of_day' => '08:00',
                'day_of_week' => 1,
                'day_of_month' => 1,
                'start_date' => '2026-11-01',
                'source' => 'wallet',
                'initial_deposit' => 10000.00,
                'idempotency_key' => 'auto-create-12345'
            ],
            '*savings/auto/*/contribute*' => [
                'amount' => 5000.00,
                'source' => 'wallet',
                'idempotency_key' => 'auto-contrib-98765'
            ],
            '*savings/auto/*/withdraw*' => [
                'amount' => 5000.00,
                'destination' => 'wallet',
                'idempotency_key' => 'auto-wth-45678'
            ],
            '*savings/targets' => [
                'name' => 'December Trip to Kigali',
                'target_amount' => 300000.00,
                'target_date' => '2026-12-20',
                'description' => 'Flight & lodging savings',
                'category' => 'vacation',
                'auto_save' => true,
                'frequency' => 'weekly',
                'schedule_amount' => 25000.00,
                'day_of_week' => 1,
                'time_of_day' => '09:00',
                'initial_deposit' => 50000.00,
                'idempotency_key' => 'target-create-abcde'
            ],
            '*savings/targets/*/contribute*' => [
                'amount' => 15000.00,
                'source' => 'wallet',
                'idempotency_key' => 'target-contrib-12345'
            ],
            '*savings/targets/*/withdraw*' => [
                'amount' => 20000.00,
                'destination' => 'wallet',
                'idempotency_key' => 'target-wth-67890'
            ],
            '*savings/targets/*/cancel*' => [
                'acknowledge_penalty' => true,
                'reason' => 'Emergency expenses',
                'idempotency_key' => 'target-cancel-99999'
            ],
            '*savings/transfers*' => [
                'source_account_id' => 5,
                'destination_account_id' => 8,
                'amount' => 15000.00,
                'idempotency_key' => 'trf-acc-to-acc-1111'
            ],
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
                'amount' => 50.00,
                'duration_days' => 30, // 30, 60, or 365
                'auto_compound' => false
            ],
            '*usdt-easyearn/save*' => [
                'amount' => 50.00,
                'duration_days' => 30, // 30, 60, or 365
                'auto_compound' => false
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

            // Quidax crypto wallets
            '*quidax/fetch-user-wallets*' => [
                'message' => ['Wallets fetch successfully!'],
                'type' => 'success',
                'data' => [
                    [
                        'currency' => 'usdt',
                        'balance' => '3.00',
                        'available_balance' => '3.00',
                        'provider_balance' => '6.00',
                        'provider_locked_balance' => '0.00',
                        'locked_balance' => '3.00',
                        'total_balance' => '6.00',
                        'reserved_outgoing_balance' => '3.00',
                        'converted_balance' => '4500.00',
                        'available_converted_balance' => '4500.00',
                        'locked_converted_balance' => '4500.00',
                        'provider_converted_balance' => '9000.00',
                        'reference_currency' => 'NGN',
                    ],
                ],
            ],
            '*quidax/fetch-user-wallet*' => [
                'message' => ['Wallet fetch successfully!'],
                'type' => 'success',
                'data' => [
                    [
                        'currency' => 'usdt',
                        'balance' => '3.00',
                        'available_balance' => '3.00',
                        'provider_balance' => '6.00',
                        'total_balance' => '6.00',
                        'reserved_outgoing_balance' => '3.00',
                        'locked_balance' => '3.00',
                        'reference_currency' => 'NGN',
                    ],
                ],
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
            '*buy-sell/on-ramp/initiate*' => [
                'status' => 'success',
                'message' => 'On-ramp transaction initiated successfully',
                'data' => [
                    'merchant_reference' => 'ONRAMP_12_ABCdef1234',
                    'internal_status' => 'pending',
                    'status' => 'processing',
                    'trade' => [
                        'from_currency' => 'NGN',
                        'to_currency' => 'USDT',
                        'from_amount' => '5000',
                        'network' => 'bep20',
                        'wallet_address' => '0x1111111111111111111111111111111111111111'
                    ],
                    'note' => 'wallet_address is optional. If omitted, the user\'s Quidax deposit address for this coin/network is used. A different address is rejected with 422 WALLET_ADDRESS_MISMATCH.'
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

            // Referral bonus wallet (stored in NGN; crypto view in USD)
            '*referral/withdraw*' => [
                'message' => 'Withdrawal processed successfully',
                'data' => [
                    'withdrawal' => [
                        'id' => 55,
                        'reference' => 'RWD-8K2LQ0ZP4M1X',
                        'amount' => 10.00, // crypto: USD; fiat: NGN
                        'currency' => 'USD', // crypto: USD; fiat: NGN
                        'amount_ngn' => 15000.00,
                        'amount_usd' => 10.00, // null for fiat
                        'crypto_amount' => 10.00, // USDT paid; null for fiat
                        'usd_ngn_rate' => 1500.00, // NGN per USD(T); null for fiat
                        'status' => 'completed',
                        'method' => 'crypto',
                        'crypto_currency' => 'USDT'
                    ]
                ]
            ],
            '*referral/balance*' => [
                'message' => 'Referral wallet balance retrieved successfully',
                'data' => [
                    'currency' => 'NGN',
                    'total_earned' => 45000.00,
                    'available_for_withdrawal' => 30000.00,
                    'available_for_withdrawal_usd' => 20.00,
                    'usd_ngn_rate' => 1500.00,
                    'min_withdrawal_amount' => 1000.00,
                    'max_withdrawal_amount' => null,
                    'withdrawal_methods' => [
                        ['method' => 'fiat', 'currency' => 'NGN', 'amount_currency' => 'NGN', 'available_balance' => 30000.00, 'min_amount' => 1000.00, 'max_amount' => null, 'enabled' => true],
                        ['method' => 'crypto', 'currency' => 'USD', 'amount_currency' => 'USD', 'payout_currency' => 'USDT', 'currencies' => ['USDT'], 'usd_ngn_rate' => 1500.00, 'available_balance' => 20.00, 'min_amount' => 0.66, 'max_amount' => null, 'enabled' => true]
                    ]
                ]
            ],

            // Virtual Cards
            '*crypto-cards/pending*' => [
                'success' => true,
                'count' => 2,
                'data' => [
                    [
                        'id' => 41,
                        'provider' => 'sudo',
                        'type' => 'virtual',
                        'brand' => 'Visa',
                        'currency' => 'NGN',
                        'status' => 'processing', // pending | processing (failed/active never listed)
                        'stage' => 'converting_funds', // queued | converting_funds | creating_card
                        'stage_label' => 'Converting USDT to fund your card',
                        'transaction_id' => 977,
                        'transaction_status' => 'processing',
                        'charge_amount' => 3.5,
                        'charge_currency' => 'USDT',
                        'created_at' => '2026-11-09T11:02:10.000000Z',
                        'updated_at' => '2026-11-09T11:02:14.000000Z'
                    ],
                    [
                        'id' => 42,
                        'provider' => 'sudo',
                        'type' => 'virtual',
                        'brand' => 'Visa',
                        'currency' => 'NGN',
                        'status' => 'pending',
                        'stage' => 'queued',
                        'stage_label' => 'Request received',
                        'transaction_id' => 978,
                        'transaction_status' => 'initiated',
                        'charge_amount' => 3.5,
                        'charge_currency' => 'USDT',
                        'created_at' => '2026-11-09T11:05:00.000000Z',
                        'updated_at' => '2026-11-09T11:05:00.000000Z'
                    ]
                ]
            ],
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

            // Unified Savings (all APY values are percentage strings, without "%")
            '*savings/products*' => [
                'message' => 'Savings products retrieved successfully.',
                'data' => [
                    'products' => [
                        [
                            'id' => 1,
                            'product_key' => 'FLEXI',
                            'name' => 'Flexi Save',
                            'type' => 'flexible',
                            'currency' => 'NGN',
                            'balance' => '75000.00',
                            'available_balance' => '75000.00',
                            'interest_earned' => '245.50',
                            'status' => 'ACTIVE',
                            'enabled' => true,
                            'annual_rate' => '60.00%',
                            'apy' => '60.00',
                        ],
                    ],
                ],
                'type' => 'success',
            ],
            '*savings/flexi*' => [
                'message' => 'Flexi Save retrieved.',
                'data' => [
                    'id' => 1,
                    'product' => 'FLEXI',
                    'title' => 'Flexi Save',
                    'currency' => 'NGN',
                    'status' => 'ACTIVE',
                    'balance' => '75000.00',
                    'available_balance' => '75000.00',
                    'interest_earned' => '245.50',
                    'apy' => '60.00',
                ],
                'type' => 'success',
            ],
            '*savings/auto*' => [
                'message' => 'Auto Save plans retrieved.',
                'data' => [
                    'items' => [[
                        'id' => 2,
                        'product' => 'AUTO',
                        'title' => 'Emergency Fund',
                        'currency' => 'NGN',
                        'status' => 'ACTIVE',
                        'balance' => '50000.00',
                        'available_balance' => '50000.00',
                        'interest_earned' => '125.25',
                        'apy' => '60.00',
                        'plan' => [
                            'frequency' => 'monthly',
                            'amount' => '10000.00',
                            'next_run_at' => '2026-11-01T08:00:00Z',
                            'status' => 'ACTIVE',
                        ],
                    ]],
                    'pagination' => ['current_page' => 1, 'last_page' => 1, 'per_page' => 20, 'total' => 1],
                ],
                'type' => 'success',
            ],
            '*savings/targets*' => [
                'message' => 'Targets retrieved.',
                'data' => [
                    'items' => [[
                        'id' => 3,
                        'product' => 'TARGET',
                        'title' => 'Vacation in Zanzibar',
                        'currency' => 'NGN',
                        'status' => 'ACTIVE',
                        'balance' => '150000.00',
                        'available_balance' => '0.00',
                        'interest_earned' => '420.00',
                        'apy' => '60.00',
                        'target_amount' => '500000.00',
                        'deadline' => '2026-12-15',
                        'progress' => ['percentage' => '30.00', 'remaining' => '350000.00'],
                    ]],
                    'pagination' => ['current_page' => 1, 'last_page' => 1, 'per_page' => 20, 'total' => 1],
                ],
                'type' => 'success',
            ],
            '*savings/safelock/plans*' => [
                'message' => ['success' => 'Operation successful'],
                'data' => [
                    'plans' => [[
                        'id' => 3,
                        'name' => '90 Days',
                        'duration_days' => 90,
                        'interest_rate' => '15.00',
                        'apy' => '15.00',
                        'min_amount' => '10000.00',
                        'max_amount' => '5000000.00',
                        'status' => true,
                    ]],
                    'enabled' => true,
                    'early_break_penalty_percent' => '1.00',
                ],
                'type' => 'success',
            ],
            '*savings/safelock*' => [
                'message' => ['success' => 'Operation successful'],
                'data' => [
                    'safe_locks' => [[
                        'id' => 8,
                        'title' => 'Emergency Lock',
                        'amount' => '100000.00',
                        'interest_rate' => '15.00',
                        'apy' => '15.00',
                        'interest_accrued' => '1250.00',
                        'status' => 'active',
                    ]],
                    'total_locked_balance' => '101250.00',
                ],
                'type' => 'success',
            ],
            '*savings/transfers*' => [
                'status' => 'success',
                'message' => 'Transfer completed successfully.',
                'data' => [
                    'reference' => 'SAV-TRF-66000001',
                    'amount' => '15000.00',
                    'source_account_id' => 5,
                    'destination_account_id' => 8,
                    'status' => 'posted'
                ],
                'type' => 'success'
            ],

            // Savings & EasyEarn
            '*savings/summary*' => [
                'message' => 'Savings products retrieved successfully.',
                'data' => [
                    'products' => [[
                        'id' => 1,
                        'product_key' => 'FLEXI',
                        'name' => 'Flexi Save',
                        'currency' => 'NGN',
                        'balance' => '850000.00',
                        'interest_earned' => '145200.50',
                        'apy' => '60.00',
                    ]],
                ],
                'type' => 'success',
            ],
            '*usdt-easyearn/info*' => [
                'success' => true,
                'message' => 'Product information retrieved successfully',
                'data' => [
                    'current_monthly_rate' => '1.25',
                    'base_apy' => '15.00',
                    'apy' => '15.00',
                    'power_bonus_multiplier' => '2.00',
                    'min_investment' => '10.00000000',
                    'max_investment' => '50000.00000000',
                    'is_active' => true,
                ],
            ],
            '*usdt-easyearn/my-savings*' => [
                'success' => true,
                'message' => 'Savings retrieved successfully',
                'data' => [[
                    'id' => 24,
                    'amount' => '500.00000000',
                    'interest_rate' => '1.25',
                    'base_apy' => '15.00',
                    'apy' => '15.00',
                    'status' => 'active',
                    'total_interest_earned' => '12.50000000',
                ]],
                'pagination' => ['current_page' => 1, 'last_page' => 1, 'per_page' => 20, 'total' => 1],
            ],
            '*usdt-easyearn/my-investments*' => [
                'success' => true,
                'message' => 'Savings retrieved successfully',
                'data' => [[
                    'id' => 24,
                    'amount' => '500.00000000',
                    'interest_rate' => '1.25',
                    'base_apy' => '15.00',
                    'apy' => '15.00',
                    'status' => 'active',
                    'total_interest_earned' => '12.50000000',
                ]],
                'pagination' => ['current_page' => 1, 'last_page' => 1, 'per_page' => 20, 'total' => 1],
            ],
            '*usdt-easyearn/savings/*' => [
                'success' => true,
                'message' => 'Investment retrieved successfully',
                'data' => [
                    'id' => 24,
                    'amount' => '500.00000000',
                    'interest_rate' => '1.25',
                    'base_apy' => '15.00',
                    'apy' => '15.00',
                    'status' => 'active',
                ],
            ],
            '*usdt-easyearn/investment/*' => [
                'success' => true,
                'message' => 'Investment retrieved successfully',
                'data' => [
                    'id' => 24,
                    'amount' => '500.00000000',
                    'interest_rate' => '1.25',
                    'base_apy' => '15.00',
                    'apy' => '15.00',
                    'status' => 'active',
                ],
            ],
            '*usdt-easyearn/plans*' => [
                'status' => 'success',
                'message' => 'USDT EasyEarn plans fetched.',
                'data' => [
                    [
                        'id' => 1,
                        'name' => '30-Day Fixed Vault',
                        'min_deposit' => 10.00,
                        'max_deposit' => 50000.00,
                        'duration_days' => 30,
                        'duration_months' => 1,
                        'interest_distribution' => 'daily'
                    ],
                    [
                        'id' => 2,
                        'name' => '60-Day Fixed Vault',
                        'min_deposit' => 10.00,
                        'max_deposit' => 50000.00,
                        'duration_days' => 60,
                        'duration_months' => 2,
                        'interest_distribution' => 'daily'
                    ],
                    [
                        'id' => 3,
                        'name' => '1-Year Fixed Vault',
                        'min_deposit' => 10.00,
                        'max_deposit' => 100000.00,
                        'duration_days' => 365,
                        'duration_months' => 12,
                        'interest_distribution' => 'daily'
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
