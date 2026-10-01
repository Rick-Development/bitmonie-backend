<?php

namespace App\Services;

use App\Jobs\InitiateQuidaxCardFundingJob;
use App\Models\CryptoCardEscrow;
use App\Models\CryptoCardHolders;
use App\Models\CryptoCardsModel;
use App\Models\CryptoCardTransactions;
use App\Models\User;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use InvalidArgumentException;
use Throwable;
use Illuminate\Support\Facades\Schema;

class CryptoCardService
{
    /**
     * Supported card providers.
     */
    protected array $providers = [
        'sudo' => SudoCardService::class,
    ];

    /**
     * Internal method => provider method mapping.
     */
    protected array $providerMethodMap = [
        'sudo' => [
            'sendPin'      => 'sendCardPin',
            'updatePin'    => 'changeCardPin',
            'enroll2FA'    => 'enrollCard2FA',
            'getCardToken' => 'generateCardToken',
            'transfer'     => 'fundTransfer',
            'getTransfer'  => 'getTransferStatus',
            'fundAccount'  => 'simulatorFundAccount',
        ],
    ];

    /*
    |--------------------------------------------------------------------------
    | PROVIDER HELPERS
    |--------------------------------------------------------------------------
    */

    protected function resolveProvider(string $provider): object
    {
        $provider = $this->normalizeProvider($provider);

        if (!isset($this->providers[$provider])) {
            throw new InvalidArgumentException(
                "Unsupported card provider: {$provider}"
            );
        }

        return app($this->providers[$provider]);
    }

    protected function normalizeProvider(string $provider): string
    {
        return strtolower(trim($provider));
    }

    protected function resolveProviderMethod(
        string $provider,
        string $method
    ): string {
        $provider = $this->normalizeProvider($provider);

        return $this->providerMethodMap[$provider][$method] ?? $method;
    }

    protected function removeNullValues(array $data): array
    {
        foreach ($data as $key => $value) {
            if (is_array($value)) {
                $value = $this->removeNullValues($value);

                if ($value === []) {
                    unset($data[$key]);
                    continue;
                }

                $data[$key] = $value;
                continue;
            }

            if ($value === null) {
                unset($data[$key]);
            }
        }

        return $data;
    }

    protected function providerResponse(
        mixed $response,
        string $defaultMessage = 'Provider operation failed.'
    ): array {
        if (!is_array($response)) {
            return [
                'success' => false,
                'message' => $defaultMessage,
                'data' => $response,
            ];
        }

        return $response;
    }

    protected function providerMethod(
        string $provider,
        string $method,
        array $arguments = []
    ): array {
        $provider = $this->normalizeProvider($provider);

        $resolvedMethod = $this->resolveProviderMethod(
            $provider,
            $method
        );

        
        try {
            $service = $this->resolveProvider($provider);
        
            

            if (!method_exists($service, $resolvedMethod)) {
                return [
                    'success' => false,
                    'message' =>
                        "Method {$resolvedMethod} does not exist on {$provider} service.",
                    'statusCode' => 500,
                ];
            }

            $response = $service->{$resolvedMethod}(...$arguments);

            return $this->providerResponse(
                $response,
                'Provider operation failed.'
            );
        } catch (Throwable $e) {
            Log::error('Provider operation failed', [
                'provider' => $provider,
                'method' => $resolvedMethod,
                'error' => $e->getMessage(),
            ]);

            return [
                'success' => false,
                'message' => 'Provider operation failed.',
                'statusCode' => 500,
                'error' => $e->getMessage(),
            ];
        }
    }

    /*
    |--------------------------------------------------------------------------
    | NOTIFICATIONS
    |--------------------------------------------------------------------------
    */

    protected function sendNotification(
        int $userId,
        string $templateKey,
        array $data = []
    ): void {
        try {
            $user = User::find($userId);

            if ($user && !isset($data['user'])) {
                $data['user'] =
                    $user->firstname
                    ?? $user->name
                    ?? 'User';
            }

            $notificationService = app(NotificationService::class);

            $notificationService->send(
                userId: $userId,
                templateKey: $templateKey,
                data: $data
            );
        } catch (Throwable $e) {
            /*
             * Notification failure must never break
             * a successful card operation.
             */
            Log::warning('Crypto card notification failed', [
                'user_id' => $userId,
                'template_key' => $templateKey,
                'error' => $e->getMessage(),
            ]);
        }
    }

    /*
    |--------------------------------------------------------------------------
    | CARD HOLDERS
    |--------------------------------------------------------------------------
    */

    public function createCardHolder(
        int $userId,
        string $provider,
        array $payload
    ): array {
        $provider = $this->normalizeProvider($provider);

        try {
            $service = $this->resolveProvider($provider);

            $payload = $this->removeNullValues($payload);

            /*
             * Card holder is the source of truth.
             */
            $existingHolder = CryptoCardHolders::forUser($userId)
                ->first();

            if (
                $existingHolder &&
                $existingHolder->hasProvider($provider)
            ) {
                return [
                    'success' => true,
                    'message' => 'Card holder already exists.',
                    'data' => [
                        'holder' => $existingHolder,
                        'provider' => $provider,
                        'provider_customer_id' =>
                            $existingHolder
                                ->getProviderCustomerId($provider),
                    ],
                ];
            }

            $response = $service->createCustomer($payload);

            if (!($response['success'] ?? false)) {
                Log::warning(
                    'Card holder provider creation failed',
                    [
                        'user_id' => $userId,
                        'provider' => $provider,
                        'status_code' =>
                            $response['statusCode'] ?? null,
                        'message' =>
                            $response['message'] ?? null,
                        'errors' =>
                            $response['errors'] ?? null,
                    ]
                );

                return [
                    'success' => false,
                    'message' =>
                        $response['message']
                        ?? 'Unable to create card holder with provider.',
                    'statusCode' =>
                        $response['statusCode']
                        ?? 422,
                    'errors' =>
                        $response['errors'] ?? null,
                    'data' =>
                        $response['data'] ?? null,
                ];
            }

            $holder = DB::transaction(
                function () use (
                    $userId,
                    $provider,
                    $response
                ) {
                    return CryptoCardHolders::syncFromProvider(
                        userId: $userId,
                        provider: $provider,
                        response: $response
                    );
                }
            );

            return [
                'success' => true,
                'message' => 'Card holder created successfully.',
                'data' => [
                    'holder' => $holder,
                    'provider' => $provider,
                    'provider_customer_id' =>
                        $holder->getProviderCustomerId($provider),
                    'response' => $response,
                ],
            ];
        } catch (Throwable $e) {
            Log::error('Create card holder failed', [
                'user_id' => $userId,
                'provider' => $provider,
                'error' => $e->getMessage(),
            ]);

            return [
                'success' => false,
                'message' => 'Unable to create card holder.',
                'error' => $e->getMessage(),
            ];
        }
    }

    public function getCardHolder(
        int $userId,
        ?string $provider = null
    ): ?CryptoCardHolders {
        $holder = CryptoCardHolders::forUser($userId)
            ->where('status', 'active')
            ->first();

        if (!$holder) {
            return null;
        }

        if ($provider !== null) {
            $provider = $this->normalizeProvider($provider);

            if (!$holder->hasProvider($provider)) {
                return null;
            }
        }

        return $holder;
    }

    /*
    |--------------------------------------------------------------------------
    | LOCAL CARD CREATION
    |--------------------------------------------------------------------------
    */

    public function createCard(
        int $userId,
        string $provider,
        array $payload,
        ?int $cardHolderId = null
    ): array {
        $provider = $this->normalizeProvider($provider);

        try {
            $service = $this->resolveProvider($provider);

            /*
             * ALWAYS resolve the holder first.
             */
            $holder = $cardHolderId
                ? CryptoCardHolders::where('id', $cardHolderId)
                    ->where('user_id', $userId)
                    ->where('status', 'active')
                    ->first()
                : CryptoCardHolders::forUser($userId)
                    ->where('status', 'active')
                    ->first();

            if (!$holder) {
                return [
                    'success' => false,
                    'message' => 'Card holder not found.',
                    'statusCode' => 404,
                ];
            }

            if (!$holder->hasProvider($provider)) {
                return [
                    'success' => false,
                    'message' =>
                        "User does not have a {$provider} card holder.",
                    'statusCode' => 422,
                ];
            }

            $customerId =
                $holder->getProviderCustomerId($provider);

            if (!$customerId) {
                return [
                    'success' => false,
                    'message' =>
                        "No {$provider} customer ID is associated with this card holder.",
                    'statusCode' => 422,
                ];
            }

            $payload['customerId'] = $customerId;

            $payload = $this->removeNullValues($payload);

            $response = $service->createCard($payload);
$providerStatusCode = $response['response']['statusCode']
    ?? $response['statusCode']
    ?? null;

$providerSuccess =
    ($response['success'] ?? false) &&
    (!$providerStatusCode || (int) $providerStatusCode < 400);

if (!$providerSuccess) {
                return [
                    'success' => false,
                    'message' =>
                        $response['message']
                        ?? 'Unable to create card.',
                    'statusCode' =>
                        $response['statusCode']
                        ?? 422,
                    'errors' =>
                        $response['errors'] ?? null,
                    'data' =>
                        $response['data'] ?? null,
                ];
            }

            $card = DB::transaction(
                function () use (
                    $userId,
                    $provider,
                    $response,
                    $holder
                ) {
                    $card =
                        CryptoCardsModel::createFromSudoResponse(
                            userId: $userId,
                            sudoResponse: $response,
                            provider: $provider
                        );

                    /*
                     * Local association only.
                     *
                     * Ownership has already been established
                     * through CryptoCardHolders.
                     */
                    $card->card_holder_id = $holder->id;

                    $card->user_id = $holder->user_id;

                    $card->save();

                    return $card;
                }
            );

            return [
                'success' => true,
                'message' => 'Card created successfully.',
                'data' => [
                    'card' => $card,
                    'holder' => $holder,
                    'provider' => $provider,
                    'response' => $response,
                ],
            ];
        } catch (Throwable $e) {
            Log::error('Create card failed', [
                'user_id' => $userId,
                'provider' => $provider,
                'card_holder_id' => $cardHolderId,
                'error' => $e->getMessage(),
            ]);

            return [
                'success' => false,
                'message' => 'Unable to create card.',
                'error' => $e->getMessage(),
            ];
        }
    }

    /*
    |--------------------------------------------------------------------------
    | GET USER CARDS
    |--------------------------------------------------------------------------
    */

    /**
     * Get cards belonging to a user's card holder.
     *
     * IMPORTANT:
     * CryptoCardHolders is the ownership/source-of-truth record.
     *
     * CryptoCardsModel is only the local synchronized copy.
     */
    public function getUserCards(
        int $userId,
        ?string $provider = null
    ): Collection {
        $provider = $provider !== null
            ? $this->normalizeProvider($provider)
            : 'sudo';

        /*
         * 1. Resolve the user's ACTIVE card holder.
         *
         * We do NOT start with crypto_cards.
         */
        
        $holder = CryptoCardHolders::forUser($userId)
            ->where('status', 'active')
            ->first();
    

        if (!$holder) {
            return collect();
        }

        /*
         * 2. Verify that this holder actually has
         *    the requested provider.
         */
        if (!$holder->hasProvider($provider)) {
            return collect();
        }
        

        /*
         * 3. Get the provider customer ID from the HOLDER.
         */
        $customerId =
            $holder->getProviderCustomerId($provider);
            

        if (!$customerId) {
            return collect();
        }

        /*
         * 4. Check the local synchronized cards.
         *
         * This is only a cache lookup.
         *
         * Ownership was already established by the holder.
         */
        // $localCards = CryptoCardsModel::query()
        //     ->where('card_holder_id', $holder->id)
        //     ->where('card_provider', $provider)
        //     ->where('is_deleted', false)
        //     ->latest()
        //     ->get();

        // /*
        //  * If local cards exist, return them.
        //  *
        //  * This avoids hitting Sudo unnecessarily.
        //  */
        // if ($localCards->isNotEmpty()) {
        //     return $localCards;
        // }

        /*
         * 5. No local cards.
         *
         * Fetch them using the PROVIDER CUSTOMER ID
         * belonging to the card holder.
         */
        return $this->syncUserCards(
            userId: $userId,
            provider: $provider
        );
    }

    /*
    |--------------------------------------------------------------------------
    | GET SINGLE CARD
    |--------------------------------------------------------------------------
    */

    /**
     * Get one card.
     *
     * The card holder must belong to the supplied user before
     * the local card is considered valid.
     */
    public function getCard(
        int $userId,
        int|string $cardId,
        string $provider = 'sudo'
    ): ?CryptoCardsModel {
        $provider = $this->normalizeProvider($provider);

        /*
         * 1. Resolve user's active card holder FIRST.
         */
        $holder = CryptoCardHolders::forUser($userId)
            ->where('status', 'active')
            ->first();

        if (!$holder) {
            return null;
        }

        /*
         * 2. Ensure the holder has the requested provider.
         */
        if (!$holder->hasProvider($provider)) {
            return null;
        }

        /*
         * 3. Find local card ONLY through the holder.
         *
         * Do not use:
         *
         *     where('user_id', $userId)
         *
         * as the ownership mechanism.
         */
        $card = CryptoCardsModel::query()
            ->where('card_holder_id', $holder->id)
            ->where('card_provider', $provider)
            ->where('is_deleted', false)
            ->where(function ($query) use ($cardId) {
                $query
                    ->where('id', $cardId)
                    ->orWhere(
                        'card_provider_id',
                        (string) $cardId
                    );
            })
            ->first();
            

        if ($card) {
            return $card;
        }

        /*
         * 4. No local card.
         *
         * Synchronize all cards belonging to the holder's
         * provider customer.
         */
        $cards = $this->syncUserCards(
            userId: $userId,
            provider: $provider
        );

        /*
         * 5. Try local ID.
         */
        $card = $cards->first(
            fn (CryptoCardsModel $item) =>
                (string) $item->id === (string) $cardId
        );

        if ($card) {
            return $card;
        }

        /*
         * 6. Try provider card ID.
         */
        return $cards->first(
            fn (CryptoCardsModel $item) =>
                (string) $item->card_provider_id ===
                (string) $cardId
        );
    }

    /*
    |--------------------------------------------------------------------------
    | UPDATE CARD
    |--------------------------------------------------------------------------
    */

    public function updateCard(
        int $userId,
        int|string $cardId,
        array $payload
    ): array {
        $card = $this->getCard($userId, $cardId);

        if (!$card) {
            return [
                'success' => false,
                'message' => 'Card not found.',
                'statusCode' => 404,
            ];
        }

        try {
            $service =
                $this->resolveProvider(
                    $card->card_provider
                );

            $payload =
                $this->removeNullValues($payload);

            $response = $service->updateCard(
                $card->card_provider_id,
                $payload
            );

            if (!($response['success'] ?? false)) {
                return $response;
            }

            $card->updateFromSudoResponse($response);

            return [
                'success' => true,
                'message' => 'Card updated successfully.',
                'data' => [
                    'card' => $card->fresh(),
                    'response' => $response,
                ],
            ];
        } catch (Throwable $e) {
            Log::error('Update card failed', [
                'user_id' => $userId,
                'card_id' => $cardId,
                'error' => $e->getMessage(),
            ]);

            return [
                'success' => false,
                'message' => 'Unable to update card.',
                'error' => $e->getMessage(),
            ];
        }
    }

    /*
    |--------------------------------------------------------------------------
    | PROVIDER CARDS
    |--------------------------------------------------------------------------
    */

    public function providerCards(
        string $provider,
        array $filters = []
    ): array {
        return $this->providerMethod(
            $provider,
            'getCards',
            [$filters]
        );
    }

    public function providerCustomerCards(
        string $provider,
        string $customerId
    ): array {
        return $this->providerMethod(
            $provider,
            'getCustomerCards',
            [$customerId]
        );
    }

    public function providerCard(
        string $provider,
        string $cardId
    ): array {
        return $this->providerMethod(
            $provider,
            'getCard',
            [$cardId]
        );
    }

    public function sendCardPin(
        int $userId,
        int|string $cardId
    ): array {
        return $this->executeCardProviderOperation(
            $userId,
            $cardId,
            'sendPin'
        );
    }

    public function updateCardPin(
        int $userId,
        int|string $cardId,
        array $payload
    ): array {
        return $this->executeCardProviderOperation(
            $userId,
            $cardId,
            'updatePin',
            [
                $this->removeNullValues($payload)
            ]
        );
    }

    public function enrollCard2FA(
        int $userId,
        int|string $cardId
    ): array {
        return $this->executeCardProviderOperation(
            $userId,
            $cardId,
            'enroll2FA'
        );
    }

    public function digitalizeCard(
        int $userId,
        int|string $cardId
    ): array {
        return $this->executeCardProviderOperation(
            $userId,
            $cardId,
            'digitalizeCard'
        );
    }

    public function getCardToken(
        int $userId,
        int|string $cardId
    ): array {
        return $this->executeCardProviderOperation(
            $userId,
            $cardId,
            'getCardToken'
        );
    }


public function orderCard(
    int $userId,
    string $provider,
    array $payload
): array {
    $provider = $this->normalizeProvider($provider);

    $holder = $this->getCardHolder(
        userId: $userId,
        provider: $provider
    );

    if (!$holder) {
        return [
            'success' => false,
            'statusCode' => 404,
            'message' => 'Card holder not found.',
            'errors' => null,
            'data' => null,
        ];
    }

    $customerId = $holder->getProviderCustomerId($provider);

    if (!$customerId) {
        return [
            'success' => false,
            'statusCode' => 422,
            'message' => "No {$provider} customer ID is associated with this card holder.",
            'errors' => [
                'customerId' => [
                    "No {$provider} customer ID is associated with this card holder.",
                ],
            ],
            'data' => null,
        ];
    }

    /*
     * The provider/customer identity is resolved internally.
     * Do not allow a client-supplied customerId to override it.
     */
    $payload['customerId'] = $customerId;

    /*
     * Provider is already supplied separately and should not
     * be forwarded to Sudo.
     */
    unset($payload['provider']);

    return $this->providerMethod(
        $provider,
        'orderCard',
        [
            $this->removeNullValues($payload),
        ]
    );
}


    /*
    |--------------------------------------------------------------------------
    | FUNDING SOURCES
    |--------------------------------------------------------------------------
    */

    public function getFundingSources(
        string $provider = 'sudo'
    ): array {
        return $this->providerMethod(
            $provider,
            'getFundingSources'
        );
    }

    public function getFundingSource(
        string $provider,
        string $fundingSourceId
    ): array {
        return $this->providerMethod(
            $provider,
            'getFundingSource',
            [$fundingSourceId]
        );
    }

    public function createFundingSource(
        string $provider,
        array $payload
    ): array {
        return $this->providerMethod(
            $provider,
            'createFundingSource',
            [
                $this->removeNullValues($payload)
            ]
        );
    }

    public function updateFundingSource(
        string $provider,
        string $fundingSourceId,
        array $payload
    ): array {
        return $this->providerMethod(
            $provider,
            'updateFundingSource',
            [
                $fundingSourceId,
                $this->removeNullValues($payload),
            ]
        );
    }

    /*
    |--------------------------------------------------------------------------
    | ACCOUNTS / WALLETS
    |--------------------------------------------------------------------------
    */

    public function getAccounts(
        string $provider = 'sudo',
        array $filters = []
    ): array {
        return $this->providerMethod(
            $provider,
            'getAccounts',
            [$filters]
        );
    }

    public function getAccount(
        string $provider,
        string $accountId
    ): array {
        return $this->providerMethod(
            $provider,
            'getAccount',
            [$accountId]
        );
    }

    public function getAccountBalance(
        string $provider,
        string $accountId
    ): array {
        return $this->providerMethod(
            $provider,
            'getAccountBalance',
            [$accountId]
        );
    }

    public function getAccountTransactions(
        string $provider,
        string $accountId,
        array $filters = []
    ): array {
        return $this->providerMethod(
            $provider,
            'getAccountTransactions',
            [
                $accountId,
                $filters
            ]
        );
    }

    public function getBanks(
        string $provider = 'sudo',
        array $filters = []
    ): array {
        $country = $filters['country'] ?? 'NG';

        return $this->providerMethod(
            $provider,
            'getBanks',
            [$country]
        );
    }

 public function nameEnquiry(
    string $provider,
    array $payload
): array {
    $payload = $this->removeNullValues($payload);

    $bankCode = $payload['bankCode'] ?? null;
    $accountNumber = $payload['accountNumber'] ?? null;

    if (
        empty($bankCode) ||
        empty($accountNumber)
    ) {
        return [
            'success' => false,
            'message' => 'bankCode and accountNumber are required.',
            'statusCode' => 422,
        ];
    }

    return $this->providerMethod(
        $provider,
        'nameEnquiry',
        [
            (string) $bankCode,
            (string) $accountNumber,
        ]
    );
}



public function transfer(
    int $userId,
    int|string $cardId,
    string $provider,
    array $payload
): array {
    $provider = $this->normalizeProvider($provider);

    /*
     * Resolve the user's card.
     *
     * getCard() validates ownership through
     * CryptoCardHolders.
     */
    $card = $this->getCard(
        $userId,
        $cardId,
        $provider
    );

    if (!$card) {
        return [
            'success' => false,
            'message' => 'Card not found.',
            'statusCode' => 404,
        ];
    }

    /*
     * Only active cards can initiate transfers.
     */
    if (
        $card->card_status !== 'active' ||
        $card->is_deleted
    ) {
        return [
            'success' => false,
            'message' => 'Only active cards can make transfers.',
            'statusCode' => 422,
        ];
    }

    /*
     * Validate amount.
     */
    $amount = (float) ($payload['amount'] ?? 0);

    if ($amount <= 0) {
        return [
            'success' => false,
            'message' => 'Amount must be greater than zero.',
            'statusCode' => 422,
        ];
    }

    /*
     * Check local spendable balance.
     */
    $spendableBalance = $this->getSpendableBalance($card);

    if ($amount > $spendableBalance) {
        return [
            'success' => false,
            'message' => 'Insufficient card balance.',
            'statusCode' => 422,
            'data' => [
                'requested_amount' => $amount,
                'spendable_balance' => $spendableBalance,
                'currency' => $card->card_currency ?? 'NGN',
            ],
        ];
    }

    /*
     * Provider account must come from our synchronized
     * card record.
     */
    $sourceAccountId =
        $card->card_provider_account_id;

    if (!$sourceAccountId) {
        return [
            'success' => false,
            'message' =>
                'No provider account is associated with this card.',
            'statusCode' => 422,
        ];
    }

    /*
     * Resolve transfer details.
     */
    $currency = strtoupper(
        $payload['currency']
            ?? $card->card_currency
            ?? 'NGN'
    );

    $bankCode =
        data_get(
            $payload,
            'destination.bank_code'
        );

    $accountNumber =
        data_get(
            $payload,
            'destination.account_number'
        );

    $accountName =
        data_get(
            $payload,
            'destination.accountName'
        );

    $narration =
        $payload['narration']
        ?? 'Crypto card transfer';

    $metadata =
        $payload['metadata']
        ?? null;

    /*
     * Create the local transaction BEFORE calling Sudo.
     *
     * This guarantees that even if the provider call fails,
     * we have a local record of the attempted transaction.
     */
    $transaction = null;

    try {
        $transaction = DB::transaction(
            function () use (
                $card,
                $amount,
                $currency,
                $bankCode,
                $accountNumber,
                $accountName,
                $narration,
                $metadata
            ) {
                return CryptoCardTransactions::create([
                    'user_id' =>
                        $card->user_id,

                    'crypto_card_id' =>
                        $card->id,

                    'card_provider' =>
                        $card->card_provider,

                    'provider_card_id' =>
                        $card->card_provider_id,

                    /*
                     * Card to bank transfer.
                     */
                    'transaction_type' =>
                        'transfer',

                    /*
                     * Provider has not processed it yet.
                     */
                    'transaction_status' =>
                        'initiated',

                    /*
                     * Money is leaving the card.
                     */
                    'entry_type' =>
                        'debit',

                    'amount' =>
                        $amount,

                    'currency' =>
                        $currency,

                    'description' =>
                        $narration,

                    'channel' =>
                        'bank_transfer',

                    'transaction_date' =>
                        now(),

                    /*
                     * Keep useful transfer information locally.
                     */
                    'metadata' => [
                        'destination' => [
                            'bank_code' =>
                                $bankCode,

                            'account_number' =>
                                $accountNumber,

                            'account_name' =>
                                $accountName,
                        ],

                        'narration' =>
                            $narration,

                        'provider' =>
                            $card->card_provider,

                        'provider_account_id' =>
                            $card->card_provider_account_id,

                        'request' =>
                            $metadata,
                    ],
                ]);
            }
        );

        /*
         * Convert our clean API structure into the structure
         * expected by Sudo.
         */
        $providerPayload = [
            'debitAccountId' =>
                $sourceAccountId,

            'amount' =>
                $amount,

            'currency' =>
                $currency,

            'beneficiaryBankCode' =>
                $bankCode,

            'beneficiaryAccountNumber' =>
                $accountNumber,

            'beneficiaryAccountName' =>
                $accountName,

            'narration' =>
                $narration,

            'metadata' =>
                $metadata,
        ];

        /*
         * Remove null values before sending to provider.
         */
        $providerPayload =
            $this->removeNullValues(
                $providerPayload
            );

        /*
         * Call Sudo.
         */
        $response = $this->providerMethod(
            $provider,
            'transfer',
            [$providerPayload]
        );

        /*
         * Provider failed.
         *
         * Keep the local transaction because the attempt
         * happened, but mark it as failed.
         */
        if (!($response['success'] ?? false)) {
            $transaction->update([
                'transaction_status' =>
                    'failed',

                'metadata' => array_merge(
                    is_array($transaction->metadata)
                        ? $transaction->metadata
                        : [],
                    [
                        'provider_response' =>
                            $response,
                    ]
                ),
            ]);

            return [
                'success' => false,
                'message' =>
                    $response['message']
                    ?? 'Card transfer failed.',
                'statusCode' =>
                    $response['statusCode']
                    ?? 422,
                'data' => [
                    'transaction_id' =>
                        $transaction->id,

                    'status' =>
                        'failed',
                ],
                'errors' =>
                    $response['errors']
                    ?? null,
            ];
        }

        /*
         * Extract the provider transaction ID.
         *
         * Sudo responses can differ depending on the endpoint
         * response structure.
         */
        $providerTransactionId =
            data_get(
                $response,
                'data._id'
            )
            ?? data_get(
                $response,
                'data.id'
            )
            ?? data_get(
                $response,
                'data.transactionId'
            )
            ?? data_get(
                $response,
                '_id'
            )
            ?? data_get(
                $response,
                'id'
            )
            ?? data_get(
                $response,
                'transactionId'
            );

        /*
         * Determine provider status.
         */
        $providerStatus =
            strtolower(
                (string) (
                    data_get(
                        $response,
                        'data.status'
                    )
                    ?? data_get(
                        $response,
                        'status'
                    )
                    ?? 'pending'
                )
            );

        /*
         * Normalize provider status to our local statuses.
         */
        $localStatus = match ($providerStatus) {
            'completed',
            'complete',
            'success',
            'successful',
            'succeeded' =>
                'completed',

            'failed',
            'failure',
            'declined',
            'rejected' =>
                'failed',

            'cancelled',
            'canceled' =>
                'cancelled',

            'reversed',
            'reversal' =>
                'reversed',

            default =>
                'pending',
        };

        /*
         * Update the local transaction with the provider
         * transaction reference and response.
         */
        $transaction->update([
            'provider_transaction_id' =>
                $providerTransactionId,

            'transaction_status' =>
                $localStatus,

            'metadata' => array_merge(
                is_array($transaction->metadata)
                    ? $transaction->metadata
                    : [],
                [
                    'provider_response' =>
                        $response,

                    'provider_status' =>
                        $providerStatus,
                ]
            ),
        ]);

        /*
         * Return the provider response together with our
         * local transaction ID.
         */
        return [
            'success' => true,
            'message' =>
                $response['message']
                ?? 'Card transfer initiated successfully.',
            'statusCode' =>
                $response['statusCode']
                ?? 200,
            'data' => [
                'transaction_id' =>
                    $transaction->id,

                'provider_transaction_id' =>
                    $providerTransactionId,

                'status' =>
                    $localStatus,

                'amount' =>
                    $amount,

                'currency' =>
                    $currency,

                'card_id' =>
                    $card->id,

                'provider' =>
                    $provider,

                'provider_response' =>
                    $response['data']
                    ?? $response,
            ],
        ];

    } catch (Throwable $e) {

        /*
         * If the transaction was already created but an
         * unexpected exception occurred, preserve the record
         * and mark it as failed.
         */
        if ($transaction) {
            try {
                $transaction->update([
                    'transaction_status' =>
                        'failed',

                    'metadata' => array_merge(
                        is_array($transaction->metadata)
                            ? $transaction->metadata
                            : [],
                        [
                            'exception' =>
                                $e->getMessage(),
                        ]
                    ),
                ]);
            } catch (Throwable $updateException) {
                Log::error(
                    'Failed to update card transfer transaction after exception',
                    [
                        'transaction_id' =>
                            $transaction->id,

                        'error' =>
                            $updateException->getMessage(),
                    ]
                );
            }
        }

        Log::error(
            'Card to bank transfer failed',
            [
                'user_id' =>
                    $userId,

                'card_id' =>
                    $cardId,

                'provider' =>
                    $provider,

                'amount' =>
                    $amount,

                'transaction_id' =>
                    $transaction?->id,

                'error' =>
                    $e->getMessage(),
            ]
        );

        return [
            'success' => false,
            'message' =>
                'Unable to process card transfer.',
            'statusCode' => 500,
            'error' =>
                $e->getMessage(),

            'data' => [
                'transaction_id' =>
                    $transaction?->id,
            ],
        ];
    }
}



    

    public function getTransfer(
        string $provider,
        string $transferId
    ): array {
        return $this->providerMethod(
            $provider,
            'getTransfer',
            [$transferId]
        );
    }

    public function getTransferRate(
        string $provider,
        string $currencyPair
    ): array {
        return $this->providerMethod(
            $provider,
            'getTransferRate',
            [$currencyPair]
        );
    }

    public function fundSandboxAccount(
        string $provider,
        array $payload
    ): array {
        $payload =
            $this->removeNullValues($payload);

        $amount =
            (float) ($payload['amount'] ?? 0);

        $accountId =
            $payload['accountId'] ?? null;

        $bankCode =
            $payload['bankCode'] ?? null;

        $accountNumber =
            $payload['accountNumber'] ?? null;

        return $this->providerMethod(
            $provider,
            'fundAccount',
            [
                $amount,
                $accountId,
                $bankCode,
                $accountNumber
            ]
        );
    }

    /*
    |--------------------------------------------------------------------------
    | TRANSACTIONS
    |--------------------------------------------------------------------------
    */
public function getCardTransactions(
    int $userId,
    int|string $cardId,
    array $filters = []
): LengthAwarePaginator|Collection {
    $card = $this->getCard(
        $userId,
        $cardId
    );

    if (!$card) {
        return collect();
    }

    $perPage = max(
        1,
        min(
            (int) ($filters['per_page'] ?? 20),
            100
        )
    );

    $transactions = CryptoCardTransactions::query()
        ->where(
            'crypto_card_id',
            $card->id
        )
        ->when(
            !empty($filters['status']),
            fn ($query) =>
                $query->where(
                    'transaction_status',
                    $filters['status']
                )
        )
        ->when(
            !empty($filters['from']),
            fn ($query) =>
                $query->whereDate(
                    'transaction_date',
                    '>=',
                    $filters['from']
                )
        )
        ->when(
            !empty($filters['to']),
            fn ($query) =>
                $query->whereDate(
                    'transaction_date',
                    '<=',
                    $filters['to']
                )
        )
        ->latest('transaction_date')
        ->paginate($perPage);

    /*
     * If there are no transactions, return the database
     * columns with null values.
     */
    if ($transactions->isEmpty()) {
        $columns = Schema::getColumnListing(
            (new CryptoCardTransactions)->getTable()
        );

        $emptyTransaction = array_fill_keys(
            $columns,
            null
        );

        $transactions->setCollection(
            collect([$emptyTransaction])
        );
    }

    return $transactions;
}

 public function syncCardTransactions(
    int $userId,
    int|string $cardId
): array {
    $card = $this->getCard(
        $userId,
        $cardId
    );

    if (!$card) {
        return [
            'success' => false,
            'message' => 'Card not found.',
            'statusCode' => 404,
        ];
    }

    try {
        $service = $this->resolveProvider(
            $card->card_provider
        );

        $response = $service->getCardTransactions(
            $card->card_provider_id
        );

        if (!($response['success'] ?? false)) {
            return $response;
        }

        $transactions = $response['data'] ?? [];

        if (
            isset($transactions['data']) &&
            is_array($transactions['data'])
        ) {
            $transactions = $transactions['data'];
        }

        if (!is_array($transactions)) {
            return [
                'success' => false,
                'message' =>
                    'Invalid transaction response from provider.',
            ];
        }

        $created = 0;
        $updated = 0;

        foreach ($transactions as $txn) {
            if (!is_array($txn)) {
                continue;
            }

            /*
             * Get provider transaction ID
             */
            $providerTransactionId =
                $txn['_id']
                ?? $txn['id']
                ?? $txn['transactionId']
                ?? null;

            if (!$providerTransactionId) {
                continue;
            }

            /*
             * Get provider status
             *
             * Support different possible Sudo/provider
             * response structures.
             */
            $providerStatus =
                data_get($txn, 'status')
                ?? data_get($txn, 'transactionStatus')
                ?? data_get($txn, 'transaction_status')
                ?? null;

            /*
             * Normalize provider status to local status.
             */
            $transactionStatus = null;

            if ($providerStatus !== null) {
                $normalizedStatus = strtolower(
                    trim((string) $providerStatus)
                );

                $transactionStatus = match ($normalizedStatus) {
                    'completed',
                    'complete',
                    'success',
                    'successful',
                    'succeeded',
                    'paid' => 'completed',

                    'failed',
                    'failure',
                    'declined',
                    'rejected',
                    'error' => 'failed',

                    'cancelled',
                    'canceled' => 'cancelled',

                    'reversed',
                    'reversal' => 'reversed',

                    'pending',
                    'processing',
                    'initiated',
                    'queued',
                    'in_progress',
                    'in-progress' => 'pending',

                    default => null,
                };
            }

            /*
             * Find existing local transaction.
             */
            $existing = CryptoCardTransactions::where(
                'provider_transaction_id',
                $providerTransactionId
            )->first();

            if ($existing) {
                /*
                 * Always keep the latest provider response.
                 */
                $updateData = [
                    'raw_response' => $txn,
                ];

                /*
                 * Only update local status when the provider
                 * actually returned a recognized status.
                 */
                if ($transactionStatus !== null) {
                    $updateData['transaction_status'] =
                        $transactionStatus;
                }

                $existing->update($updateData);

                $updated++;

                continue;
            }

            /*
             * Transaction does not exist locally.
             *
             * Create it from the provider response.
             */
            $newTransaction =
                CryptoCardTransactions::createFromSudoTransaction(
                    userId: $card->user_id,
                    cryptoCardId: $card->id,
                    sudoResponse: [
                        'data' => $txn,
                    ],
                    provider: $card->card_provider
                );

            /*
             * createFromSudoTransaction may not always map the
             * provider status exactly as required, so explicitly
             * update it when we have a recognized status.
             */
            if (
                $newTransaction &&
                $transactionStatus !== null
            ) {
                $newTransaction->update([
                    'transaction_status' =>
                        $transactionStatus,
                    'raw_response' => $txn,
                ]);
            }

            $created++;
        }

        return [
            'success' => true,
            'message' =>
                'Transactions synced successfully.',
            'count' => count($transactions),
            'created' => $created,
            'updated' => $updated,
        ];
    } catch (Throwable $e) {
        Log::error(
            'Sync card transactions failed',
            [
                'user_id' => $userId,
                'card_id' => $cardId,
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString(),
            ]
        );

        return [
            'success' => false,
            'message' =>
                'Unable to sync transactions.',
            'error' => $e->getMessage(),
        ];
    }
}

    public function providerTransactions(
        string $provider = 'sudo',
        array $filters = []
    ): array {
        return $this->providerMethod(
            $provider,
            'getCardTransactions',
            [
                null,
                $filters
            ]
        );
    }

    public function providerTransaction(
        string $provider,
        string $transactionId
    ): array {
        return $this->providerMethod(
            $provider,
            'getTransaction',
            [$transactionId]
        );
    }

    public function updateProviderTransaction(
        string $provider,
        string $transactionId,
        array $payload
    ): array {
        return $this->providerMethod(
            $provider,
            'updateTransaction',
            [
                $transactionId,
                $this->removeNullValues($payload),
            ]
        );
    }

    /*
    |--------------------------------------------------------------------------
    | CARD PROVIDER OPERATIONS
    |--------------------------------------------------------------------------
    */

    protected function executeCardProviderOperation(
        int $userId,
        int|string $cardId,
        string $method,
        array $arguments = []
    ): array {
        /*
         * getCard() validates the holder first.
         */
        $card = $this->getCard(
            $userId,
            $cardId
        );

        if (!$card) {
            return [
                'success' => false,
                'message' => 'Card not found.',
                'statusCode' => 404,
            ];
        }

        return $this->providerMethod(
            $card->card_provider,
            $method,
            array_merge(
                [
                    $card->card_provider_id
                ],
                $arguments
            )
        );
    }

    public function call(
        string $provider,
        string $method,
        array $arguments = []
    ): array {
        return $this->providerMethod(
            $provider,
            $method,
            $arguments
        );
    }

    public function supportedProviders(): array
    {
        return array_keys($this->providers);
    }

    public function supports(
        string $provider
    ): bool {
        return isset(
            $this->providers[
                $this->normalizeProvider($provider)
            ]
        );
    }

    /*
    |--------------------------------------------------------------------------
    | CARD FUNDING
    |--------------------------------------------------------------------------
    */

    /**
     * Fund a crypto card via Quidax offramp.
     */
    public function fundCard(
        int $userId,
        int|string $cardId,
        float $amount,
        array $context = []
    ): array {
        /*
         * getCard() validates ownership through
         * CryptoCardHolders.
         */
        $card = $this->getCard(
            $userId,
            $cardId
        );

        if (!$card) {
            return [
                'success' => false,
                'message' => 'Card not found.',
                'statusCode' => 404,
            ];
        }

        if (
            $card->card_status !== 'active' ||
            $card->is_deleted
        ) {
            return [
                'success' => false,
                'message' =>
                    'Only active cards can be funded.',
                'statusCode' => 422,
            ];
        }

        if ($amount <= 0) {
            return [
                'success' => false,
                'message' =>
                    'Amount must be greater than zero.',
                'statusCode' => 422,
            ];
        }

        try {
            $transaction = null;
            $escrow = null;

            DB::transaction(
                function () use (
                    $card,
                    $amount,
                    $context,
                    &$transaction,
                    &$escrow
                ) {
                    $transaction =
                        CryptoCardTransactions::create([
                            'user_id' =>
                                $card->user_id,

                            'crypto_card_id' =>
                                $card->id,

                            'card_provider' =>
                                $card->card_provider,

                            'provider_card_id' =>
                                $card->card_provider_id,

                            'transaction_type' =>
                                'topup',

                            'transaction_status' =>
                                'initiated',

                            'entry_type' =>
                                'credit',

                            'amount' =>
                                $amount,

                            'currency' =>
                                $card->card_currency
                                ?? 'NGN',

                            'description' =>
                                $context['description']
                                ?? 'Card funding via Quidax',

                            'channel' =>
                                $context['channel']
                                ?? 'platform',

                            'transaction_date' =>
                                now(),

                            'metadata' =>
                                $context['metadata']
                                ?? null,
                        ]);

                    $escrow =
                        CryptoCardEscrow::create([
                            'user_id' =>
                                $card->user_id,

                            'crypto_card_id' =>
                                $card->id,

                            'crypto_card_transaction_id' =>
                                $transaction->id,

                            'card_provider' =>
                                $card->card_provider,

                            'type' =>
                                'topup',

                            'direction' =>
                                'credit',

                            'status' =>
                                'initiated',

                            'amount' =>
                                $amount,

                            'currency' =>
                                $card->card_currency
                                ?? 'NGN',

                            'description' =>
                                'Card funding via Quidax offramp',

                            'master_account_impact' =>
                                null,

                            'master_account_reference' =>
                                null,

                            'metadata' =>
                                $context['metadata']
                                ?? null,
                        ]);
                }
            );

            InitiateQuidaxCardFundingJob::dispatch(
                $userId,
                $card->id,
                $amount,
                $transaction->id,
                $escrow->id,
                $context
            );

            $this->sendNotification(
                userId: $userId,
                templateKey:
                    'CRYPTO_CARD_FUNDING_INITIATED',
                data: [
                    'amount' =>
                        number_format(
                            $amount,
                            2,
                            '.',
                            ','
                        ),

                    'currency' =>
                        $card->card_currency
                        ?? 'NGN',

                    'card' =>
                        $card->card_last_four
                        ?? $card->last4
                        ?? $card->card_provider_id,

                    'card_id' =>
                        $card->id,

                    'reference' =>
                        $transaction->id,

                    'transaction_id' =>
                        $transaction->id,

                    'status' =>
                        'Processing',

                    'provider' =>
                        ucfirst(
                            (string)
                            $card->card_provider
                        ),
                ]
            );

            return [
                'success' => true,
                'message' =>
                    'Card funding initiated. You will be notified once the funds are credited.',
                'data' => [
                    'card_id' =>
                        $card->id,

                    'amount' =>
                        $amount,

                    'transaction_id' =>
                        $transaction->id,

                    'escrow_id' =>
                        $escrow->id,

                    'status' =>
                        'initiated',

                    'current_balance' =>
                        $card->card_balance,
                ],
            ];
        } catch (Throwable $e) {
            Log::error(
                'Card funding initiation failed',
                [
                    'user_id' => $userId,
                    'card_id' => $cardId,
                    'amount' => $amount,
                    'error' => $e->getMessage(),
                ]
            );

            return [
                'success' => false,
                'message' =>
                    'Unable to initiate card funding.',
                'statusCode' => 500,
                'error' => $e->getMessage(),
            ];
        }
    }

    /*
    |--------------------------------------------------------------------------
    | CARD SYNCHRONIZATION
    |--------------------------------------------------------------------------
    */

    /**
     * Synchronize all cards belonging to the user's card holder.
     *
     * IMPORTANT:
     *
     * CryptoCardHolders is used to establish ownership.
     * Sudo customer ID comes from provider_customers on the holder.
     * CryptoCardsModel is only the local cache.
     */
    protected function syncUserCards(
        int $userId,
        string $provider
    ): Collection {
        
        $provider =
            $this->normalizeProvider($provider);

        /*
         * 1. Find the user's active card holder.
         */
        $holder = CryptoCardHolders::forUser($userId)
            ->where('status', 'active')
            ->first();

        if (!$holder) {
            Log::warning(
                'Cannot synchronize cards: card holder not found.',
                [
                    'user_id' => $userId,
                    'provider' => $provider,
                ]
            );

            return collect();
        }

        
        /*
         * 2. Make sure the holder has this provider.
         */
        if (!$holder->hasProvider($provider)) {
            Log::warning(
                'Cannot synchronize cards: provider not attached to holder.',
                [
                    'user_id' => $userId,
                    'holder_id' => $holder->id,
                    'provider' => $provider,
                ]
            );

            return collect();
        }

        /*
         * 3. Get provider customer ID from the HOLDER.
         *
         * Example:
         *
         * {
         *   "sudo": {
         *     "customer_id": "6a8c4810f1b9b5290bf9bb6f",
         *     "business_id": "6a0b06aea2fad1bab071e5d7",
         *     "status": "active",
         *     "is_approved": false,
         *     "synced_at": "2026-08-24T13:33:04.892485Z"
         *   }
         * }
         */
        $customerId =
            $holder->getProviderCustomerId(
                $provider
            );
            

        /*
         * Fallback for models where getProviderCustomerId()
         * is not implemented correctly.
         */
        if (!$customerId) {
            $providerCustomers =
                $holder->provider_customers;

            if (is_string($providerCustomers)) {
                $providerCustomers =
                    json_decode(
                        $providerCustomers,
                        true
                    );
            }

            if (!is_array($providerCustomers)) {
                $providerCustomers = [];
            }

            $customerId =
                data_get(
                    $providerCustomers,
                    "{$provider}.customer_id"
                );
        }

        if (!$customerId) {
            Log::warning(
                'Cannot synchronize cards: provider customer ID missing.',
                [
                    'user_id' => $userId,
                    'holder_id' => $holder->id,
                    'provider' => $provider,
                ]
            );

            return collect();
        }

        /*
         * 4. Fetch cards belonging to THIS customer.
         *
         * This is the critical ownership check.
         *
         * We are NOT calling getCards() globally and then
         * attempting to determine ownership from crypto_cards.
         */
        $response = $this->providerMethod(
            $provider,
            'getCustomerCards',
            [$customerId]
        );

        if (!($response['success'] ?? false)) {
            Log::warning(
                'Failed to fetch provider customer cards.',
                [
                    'user_id' => $userId,
                    'holder_id' => $holder->id,
                    'provider' => $provider,
                    'customer_id' => $customerId,
                    'message' =>
                        $response['message'] ?? null,
                ]
            );

            return collect();
        }
    

        /*
         * 5. Normalize provider response.
         */
        $providerCards =
            $response['data'] ?? [];

        /*
         * Some APIs return:
         *
         * data: {
         *     data: [...]
         * }
         */
        if (
            is_array($providerCards) &&
            isset($providerCards['data']) &&
            is_array($providerCards['data'])
        ) {
            $providerCards =
                $providerCards['data'];
        }

        /*
         * Some APIs may return cards directly.
         */
        if (!is_array($providerCards)) {
            return collect();
        }

        /*
         * 6. Synchronize every provider card.
         */
        $syncedCards = collect();

        foreach ($providerCards as $sudoCard) {
            if (!is_array($sudoCard)) {
                continue;
            }

            $card =
                $this->syncCard(
                    userId: $userId,
                    holder: $holder,
                    sudoCard: $sudoCard,
                    provider: $provider
                );

            if ($card) {
                $syncedCards->push($card);
            }
        }

        return $syncedCards
            ->sortByDesc(
                fn (CryptoCardsModel $card) =>
                    $card->created_at
            )
            ->values();
    }

    /**
     * Synchronize one provider card into crypto_cards.
     *
     * Ownership is established from CryptoCardHolders.
     *
     * The provider card ID is only used to locate/update the
     * local synchronized copy.
     */
    protected function syncCard(int $userId,CryptoCardHolders $holder,array $sudoCard,string $provider = 'sudo'): ?CryptoCardsModel {
        $provider =
            $this->normalizeProvider($provider);

        /*
         * Provider card identifier.
         */
        $providerCardId =
            data_get($sudoCard, '_id')
            ?? data_get($sudoCard, 'id');

        if (!$providerCardId) {
            Log::warning(
                'Provider card missing provider ID.',
                [
                    'user_id' => $userId,
                    'holder_id' => $holder->id,
                    'provider' => $provider,
                ]
            );

            return null;
        }

        /*
         * The holder MUST belong to the requested user.
         *
         * This prevents accidentally synchronizing a provider
         * card under another user's account.
         */
        if ((int) $holder->user_id !== $userId) {
            Log::warning(
                'Card holder does not belong to supplied user.',
                [
                    'user_id' => $userId,
                    'holder_id' => $holder->id,
                    'holder_user_id' =>
                        $holder->user_id,
                    'provider_card_id' =>
                        $providerCardId,
                ]
            );

            return null;
        }

        /*
         * Make sure the holder actually owns/has this provider.
         */
        if (!$holder->hasProvider($provider)) {
            Log::warning(
                'Card holder does not have provider.',
                [
                    'user_id' => $userId,
                    'holder_id' => $holder->id,
                    'provider' => $provider,
                ]
            );

            return null;
        }

        /*
         * Get customer ID from holder.
         */
        $holderCustomerId =
            $holder->getProviderCustomerId(
                $provider
            );

        if (!$holderCustomerId) {
            $providerCustomers =
                $holder->provider_customers;

            if (is_string($providerCustomers)) {
                $providerCustomers =
                    json_decode(
                        $providerCustomers,
                        true
                    );
            }

            if (!is_array($providerCustomers)) {
                $providerCustomers = [];
            }

            $holderCustomerId =
                data_get(
                    $providerCustomers,
                    "{$provider}.customer_id"
                );
        }

        /*
         * Customer ID reported by the provider card.
         */
        $cardCustomerId =
            data_get(
                $sudoCard,
                'customer._id'
            )
            ?? data_get(
                $sudoCard,
                'customerId'
            );

        /*
         * STRICT OWNERSHIP CHECK.
         *
         * The card fetched from Sudo must belong to the
         * same Sudo customer stored on the user's holder.
         *
         * This is an additional safety check.
         */
        if (
            $cardCustomerId &&
            $holderCustomerId &&
            (string) $cardCustomerId !==
            (string) $holderCustomerId
        ) {
            Log::warning(
                'Provider card does not belong to card holder.',
                [
                    'user_id' => $userId,
                    'holder_id' => $holder->id,
                    'provider' => $provider,
                    'provider_card_id' =>
                        $providerCardId,
                    'holder_customer_id' =>
                        $holderCustomerId,
                    'card_customer_id' =>
                        $cardCustomerId,
                ]
            );

            return null;
        }

        /*
         * If provider did not return a customer ID on the card,
         * use the holder's verified customer ID.
         */
        $resolvedCustomerId =
            $cardCustomerId
            ?? $holderCustomerId;

        /*
         * Find the local synchronized copy.
         *
         * IMPORTANT:
         *
         * This lookup is NOT used to determine ownership.
         *
         * Ownership has already been established from:
         *
         *     CryptoCardHolders
         *
         * This query only prevents duplicate local records.
         */
        $card = CryptoCardsModel::query()
            ->where(
                'card_provider',
                $provider
            )
            ->where(
                'card_provider_id',
                $providerCardId
            )
            ->first();

        if (!$card) {
            $card = new CryptoCardsModel();
        }

        /*
         * Local ownership association.
         */
        $card->card_holder_id =
            $holder->id;

        $card->user_id =
            $holder->user_id;

        /*
         * Provider.
         */
        $card->card_provider =
            $provider;

        $card->card_provider_id =
            $providerCardId;

        /*
         * Provider/customer/account identifiers.
         */
        $card->card_provider_customer_id =
            $resolvedCustomerId;

        $card->card_provider_account_id =
            data_get(
                $sudoCard,
                'account._id'
            )
            ?? data_get(
                $sudoCard,
                'accountId'
            );

        $card->card_provider_funding_source_id =
            data_get(
                $sudoCard,
                'fundingSource._id'
            )
            ?? data_get(
                $sudoCard,
                'fundingSourceId'
            );

        /*
         * Card information.
         */
        $card->card_type =
            data_get(
                $sudoCard,
                'type'
            );

        $card->card_brand =
            data_get(
                $sudoCard,
                'brand'
            );

        $card->card_currency =
            data_get(
                $sudoCard,
                'currency'
            );

        /*
         * Balance.
         */
        $providerBalance =
            data_get(
                $sudoCard,
                'balance'
            )
            ?? data_get(
                $sudoCard,
                'cardBalance'
            );

        if ($providerBalance !== null) {
            $card->card_balance =
                (float) $providerBalance;
        }

        /*
         * Status.
         */
        $card->card_status =
            data_get(
                $sudoCard,
                'status',
                'active'
            );

        /*
         * PAN / expiry.
         */
        $maskedPan =
            data_get(
                $sudoCard,
                'maskedPan'
            )
            ?? data_get(
                $sudoCard,
                'masked_pan'
            );

        $card->masked_pan =
            $maskedPan;

        $card->card_last_four =
            data_get(
                $sudoCard,
                'last4'
            )
            ?? data_get(
                $sudoCard,
                'lastFour'
            )
            ?? (
                $maskedPan
                    ? substr(
                        $maskedPan,
                        -4
                    )
                    : null
            );

        $card->expiry_month =
            data_get(
                $sudoCard,
                'expiryMonth'
            )
            ?? data_get(
                $sudoCard,
                'expiry_month'
            );

        $card->expiry_year =
            data_get(
                $sudoCard,
                'expiryYear'
            )
            ?? data_get(
                $sudoCard,
                'expiry_year'
            );

        /*
         * Card controls.
         */
        $card->is_2fa_enrolled =
            (bool) (
                data_get(
                    $sudoCard,
                    'is2FAEnrolled'
                )
                ?? data_get(
                    $sudoCard,
                    'is_2fa_enrolled'
                )
                ?? false
            );

        $card->is_default_pin_changed =
            (bool) (
                data_get(
                    $sudoCard,
                    'isDefaultPINChanged'
                )
                ?? data_get(
                    $sudoCard,
                    'is_default_pin_changed'
                )
                ?? false
            );

        $card->is_disposable =
            (bool) (
                data_get(
                    $sudoCard,
                    'disposable'
                )
                ?? data_get(
                    $sudoCard,
                    'isDisposable'
                )
                ?? false
            );

        /*
         * Spending controls.
         */
        $spendingControls =
            data_get(
                $sudoCard,
                'spendingControls'
            )
            ?? data_get(
                $sudoCard,
                'spending_controls'
            );

        $card->spending_controls =
            $spendingControls !== null
                ? json_encode(
                    $spendingControls
                )
                : null;

        /*
         * Metadata.
         */
        $metadata =
            $card->metadata;

        if (is_string($metadata)) {
            $metadata =
                json_decode(
                    $metadata,
                    true
                );
        }

        if (!is_array($metadata)) {
            $metadata = [];
        }

        $metadata['provider_reference'] =
            data_get(
                $sudoCard,
                'providerReference'
            );

        $metadata['provider_customer_id'] =
            $resolvedCustomerId;

        $metadata['card_holder_id'] =
            $holder->id;

        $metadata['provider'] =
            $provider;

        $card->metadata =
            json_encode(
                $metadata
            );

        /*
         * Store complete provider response.
         */
        $card->raw_response =
            json_encode(
                $sudoCard
            );

        /*
         * If a previously deleted local copy exists and the
         * provider confirms the card still exists, restore it.
         */
        if (
            isset($card->is_deleted) &&
            $card->is_deleted
        ) {
            $card->is_deleted = false;
        }

        /*
         * Save.
         */
        $card->save();

        return $card->fresh();
    }

    /*
    |--------------------------------------------------------------------------
    | PROVIDER / CARD HELPERS
    |--------------------------------------------------------------------------
    */

    public function getProviderCardsForHolder(
        int $userId,
        string $provider = 'sudo'
    ): Collection {
        return $this->syncUserCards(
            $userId,
            $this->normalizeProvider($provider)
        );
    }
    /**
 * Update an existing card holder.
 *
 * - Updates normal holder fields
 * - Optionally merges data into provider_customers JSON
 */
public function updateCardHolder(
    int $userId,
    array $payload,
    ?string $provider = null
): array {
    $provider = $provider !== null
        ? $this->normalizeProvider($provider)
        : null;

    try {
        $holder = CryptoCardHolders::activeForUser($userId)->first()
            ?? CryptoCardHolders::forUser($userId)->first();

        if (!$holder) {
            return [
                'success'    => false,
                'message'    => 'Card holder not found.',
                'statusCode' => 404,
            ];
        }

        // Extract provider-specific data if present
        $providerData = [];
        if ($provider && isset($payload['provider_data']) && is_array($payload['provider_data'])) {
            $providerData = $payload['provider_data'];
            unset($payload['provider_data']);
        }

        // Remove nulls so we don't overwrite existing values with null
        $payload = $this->removeNullValues($payload);

        $holder = $holder->updateHolder(
            payload: $payload,
            provider: $provider,
            providerData: $providerData
        );

        return [
            'success' => true,
            'message' => 'Card holder updated successfully.',
            'data'    => [
                'holder'               => $holder,
                'provider'             => $provider,
                'provider_customer_id' => $provider
                    ? $holder->getProviderCustomerId($provider)
                    : null,
            ],
        ];
    } catch (Throwable $e) {
        Log::error('Update card holder failed', [
            'user_id'  => $userId,
            'provider' => $provider,
            'error'    => $e->getMessage(),
        ]);

        return [
            'success' => false,
            'message' => 'Unable to update card holder.',
            'error'   => $e->getMessage(),
        ];
    }
}
protected function getSpendableBalance(
    ?CryptoCardsModel $card
): float {
    if (!$card) {
        return 0.0;
    }

    return max(
        0.0,
        (float) $card->card_balance
        - (float) $card->pending_holds
    );
}
}