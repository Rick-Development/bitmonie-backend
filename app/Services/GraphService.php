<?php

namespace App\Services;

use App\Models\GraphCustomer;
use App\Models\GraphTransaction;
use App\Models\GraphWallet;
use App\Models\User;
use Exception;
use Illuminate\Http\Client\Response as HttpResponse;
use Illuminate\Support\Arr;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Str;

class GraphService
{
    protected string $baseUrl;
    protected ?string $secretKey;

    public function __construct()
    {
        $this->baseUrl = rtrim((string) config('graph.base_url'), '/');
        $this->secretKey = config('graph.secret_key');
    }

    protected function client()
    {
        return Http::baseUrl($this->baseUrl)
            ->withToken($this->secretKey)
            ->acceptJson()
            ->asJson()
            ->timeout(30);
    }

    protected function request(string $method, string $uri, array $payload = [], array $query = []): array
    {
        $method = strtolower($method);
        $payload = $this->filterNullValues($payload);
        $query = $this->filterNullValues($query);

        Log::info('Graph API request', [
            'method' => strtoupper($method),
            'uri' => $uri,
            'query' => $this->sanitizeForLog($query),
            'payload' => $this->sanitizeForLog($payload),
        ]);

        $response = match ($method) {
            'get' => $this->client()->get($uri, $query),
            'post' => $this->client()->post($uri, $payload),
            'patch' => $this->client()->patch($uri, $payload),
            'put' => $this->client()->put($uri, $payload),
            'delete' => $this->client()->delete($uri, $payload),
            default => throw new Exception("Unsupported Graph request method [{$method}]"),
        };

        $responseData = $response->json();

        Log::info('Graph API response', [
            'method' => strtoupper($method),
            'uri' => $uri,
            'status' => $response->status(),
            'body' => $this->sanitizeForLog(is_array($responseData) ? $responseData : ['body' => $response->body()]),
        ]);

        if ($response->failed()) {
            throw new Exception($this->extractErrorMessage($response));
        }

        return is_array($responseData) ? $responseData : [];
    }

    /**
     * Create a person (customer) on Graph/Oval.
     */
    public function createPerson(User $user, array $kycData)
    {
        $idType = $this->normalizeIdType($kycData['id_type'] ?? 'passport');
        $phone = $this->normalizePhone($user->full_mobile ?? $user->mobile);
        $documents = [
            [
                'type' => $idType,
                'url' => $kycData['id_image_url'] ?? '',
                'issue_date' => $kycData['id_issue_date'] ?? '2020-01-01',
                'expiry_date' => $kycData['id_expiry_date'] ?? '2030-01-01',
                'id_number' => $kycData['id_number'] ?? null,
            ],
        ];

        if (!empty($kycData['bank_statement_url'])) {
            $documents[] = [
                'type' => 'bank_statement',
                'url' => $kycData['bank_statement_url'],
                'issue_date' => $kycData['bank_statement_issue_date'] ?? '2020-01-01',
                'expiry_date' => $kycData['bank_statement_expiry_date'] ?? '2030-01-01',
            ];
        }

        $payload = [
            'id_level' => $kycData['id_level'] ?? ($idType === 'passport' ? 'primary' : 'secondary'),
            'id_type' => $idType,
            'kyc_level' => $kycData['kyc_level'] ?? 'basic',
            'name_first' => trim((string) $user->firstname),
            'name_last' => trim((string) $user->lastname),
            'name_other' => trim((string) ($kycData['middle_name'] ?? '')),
            'email' => $user->email,
            'phone' => $phone,
            'dob' => $kycData['dob'] ?? null,
            'id_number' => $kycData['id_number'] ?? null,
            'id_country' => strtoupper((string) ($kycData['id_country'] ?? 'NG')),
            'bank_id_number' => $kycData['bvn'] ?? $kycData['bank_id_number'] ?? null,
            'address' => [
                'line1' => $kycData['address'] ?? 'Address',
                'line2' => $kycData['address_line2'] ?? '',
                'city' => $kycData['city'] ?? 'Lagos',
                'state' => $kycData['state'] ?? 'Lagos',
                'country' => strtoupper((string) ($kycData['country'] ?? 'NG')),
                'postal_code' => $kycData['zip_code'] ?? $kycData['postal_code'] ?? '100001',
            ],
            'background_information' => [
                'employment_status' => $kycData['background_information']['employment_status'] ?? 'employed',
                'occupation' => $kycData['background_information']['occupation'] ?? 'Trader',
                'primary_purpose' => $kycData['background_information']['primary_purpose'] ?? 'personal',
                'source_of_funds' => $kycData['background_information']['source_of_funds'] ?? 'salary',
                'expected_monthly_inflow' => (int) ($kycData['background_information']['expected_monthly_inflow'] ?? 100000),
            ],
            'documents' => $documents,
        ];

        $responseData = $this->request('post', '/person', $payload);
        $personData = Arr::get($responseData, 'data', []);

        if (empty($personData['id'])) {
            throw new Exception('Graph did not return a customer identifier.');
        }

        return GraphCustomer::updateOrCreate(
            ['user_id' => $user->id],
            [
                'graph_id' => $personData['id'],
                'kyc_status' => $personData['kyc_status'] ?? 'pending',
                'data' => $personData,
            ]
        );
    }

    /**
     * Create or sync a user bank account / wallet on Graph.
     */
    public function createWallet(User $user, $currency = 'USD')
    {
        $customer = GraphCustomer::where('user_id', $user->id)->first();

        if (!$customer) {
            throw new Exception('User is not a registered Graph customer.');
        }

        $currency = strtoupper((string) $currency);
        $payload = [
            'person_id' => $customer->graph_id,
            'currency' => $currency,
            'autosweep_enabled' => false,
            'whitelist_enabled' => false,
            'label' => 'Wallet for ' . ($user->username ?? $user->email),
        ];

        $responseData = $this->request('post', '/bank_account', $payload);
        $walletData = Arr::get($responseData, 'data', []);

        if (empty($walletData['id'])) {
            throw new Exception('Graph did not return a wallet identifier.');
        }

        return $this->persistWalletRecord($user, $customer, $walletData, $currency);
    }

    public function getWallet($walletId): array
    {
        return $this->request('get', "/bank_account/{$walletId}");
    }

    public function getTransactions($walletId, $page = 1, $limit = 20): array
    {
        return $this->request('get', '/transaction', [], [
            'account_id' => $walletId,
            'page' => $page,
            'limit' => $limit,
        ]);
    }

    /**
     * Create a stablecoin deposit address.
     *
     * This is not the primary user-facing USD receive flow. Use getReceiveInstructions()
     * for the normal USD funding account details.
     */
    public function createDepositAddress(User $user, $walletId, $currency = 'USDT', $network = 'ERC20'): array
    {
        $payload = [
            'currency' => strtoupper((string) $currency),
            'network' => strtoupper((string) $network),
            'label' => 'Deposit for ' . ($user->username ?? $user->email) . ' (' . $walletId . ')',
        ];

        return $this->request('post', '/address', $payload);
    }

    public function getDeposits($walletId, $page = 1, $limit = 20): array
    {
        return $this->request('get', '/deposit', [], [
            'account_id' => $walletId,
            'page' => $page,
            'limit' => $limit,
        ]);
    }

    public function mockDeposit($walletId, $amount, $currency = 'USD'): array
    {
        $payload = [
            'account_id' => $walletId,
            'amount' => $this->amountToSubunits($amount, $currency),
            'description' => 'Mock deposit via API',
            'sender_name' => 'Sandbox Tester',
        ];

        return $this->request('post', '/deposit/mock', $payload);
    }

    public function listBanks($country = 'NG'): array
    {
        return $this->request('get', '/bank', [], [
            'country' => strtoupper((string) $country),
        ]);
    }

    public function resolveBankAccount($bankCode, $accountNumber): array
    {
        $payload = [
            'bank_code' => trim((string) $bankCode),
            'account_number' => preg_replace('/\D+/', '', (string) $accountNumber),
        ];

        return $this->request('post', '/bank/resolve', $payload);
    }

    public function listPayoutDestinations(array $filters = []): array
    {
        return $this->request('get', '/payout-destination', [], [
            'account_id' => $filters['wallet_id'] ?? $filters['account_id'] ?? null,
            'type' => $filters['type'] ?? null,
            'page' => $filters['page'] ?? null,
            'limit' => $filters['limit'] ?? null,
        ]);
    }

    /**
     * Create a payout destination (beneficiary).
     */
    public function createPayoutDestination(User $user, array $data): array
    {
        $payload = $this->buildPayoutDestinationPayload($user, $data);

        return $this->request('post', '/payout-destination', $payload);
    }

    /**
     * Create a payout (withdrawal/transfer).
     */
    public function createPayout(User $user, $walletId, array $data): array
    {
        $destinationId = $data['destination_id'] ?? $data['recipient'] ?? $data['payout_destination_id'] ?? null;

        if (!$destinationId) {
            throw new Exception('A payout destination ID is required.');
        }

        $payload = [
            'destination_id' => $destinationId,
            'amount' => $this->amountToSubunits($data['amount'] ?? 0, $data['currency'] ?? 'USD'),
            'description' => trim((string) ($data['description'] ?? $data['narration'] ?? 'Withdrawal')),
            'account_id' => $data['account_id'] ?? $walletId,
        ];

        if (!empty($data['reference'])) {
            $payload['custom_reference'] = $data['reference'];
        }

        if (!empty($data['type'])) {
            $payload['type'] = strtolower((string) $data['type']);
        }

        $responseData = $this->request('post', '/payout', $payload);
        $payoutData = Arr::get($responseData, 'data', []);
        $localWalletId = GraphWallet::where('wallet_id', $walletId)->value('id');
        $transactionId = $payoutData['payout_id'] ?? $payoutData['id'] ?? ($data['reference'] ?? (string) Str::uuid());

        GraphTransaction::updateOrCreate(
            ['transaction_id' => $transactionId],
            [
                'user_id' => $user->id,
                'graph_wallet_id' => $localWalletId,
                'type' => 'withdrawal',
                'amount' => (float) ($data['amount'] ?? 0),
                'currency' => strtoupper((string) ($data['currency'] ?? 'USD')),
                'status' => $this->normalizeTransactionStatus($payoutData['status'] ?? 'processing'),
                'reference' => $data['reference'] ?? $payoutData['custom_reference'] ?? null,
                'description' => $payload['description'],
                'metadata' => $responseData,
            ]
        );

        return $responseData;
    }

    public function getPayouts($walletId, $page = 1, $limit = 20): array
    {
        return $this->request('get', '/payout', [], [
            'account_id' => $walletId,
            'page' => $page,
            'limit' => $limit,
        ]);
    }

    /**
     * Refresh the local wallet snapshot from Graph and keep local balances in base units.
     */
    public function updateWalletBalance($walletId)
    {
        $response = $this->getWallet($walletId);
        $walletData = Arr::get($response, 'data', []);
        $wallet = GraphWallet::where('wallet_id', $walletId)->first();

        if (!$wallet || empty($walletData)) {
            return null;
        }

        $wallet->update([
            'account_number' => $walletData['account_number'] ?? $wallet->account_number,
            'currency' => strtoupper((string) ($walletData['currency'] ?? $wallet->currency)),
            'balance' => $this->normalizeMoneyFromGraph($walletData['balance'] ?? $wallet->balance),
            'status' => $walletData['status'] ?? $wallet->status,
            'data' => $walletData,
        ]);

        return $wallet->fresh();
    }

    /**
     * Return production-safe USD funding account instructions for the user wallet.
     */
    public function getReceiveInstructions($walletId): array
    {
        $wallet = $this->updateWalletBalance($walletId) ?? GraphWallet::where('wallet_id', $walletId)->first();

        if (!$wallet) {
            throw new Exception('Wallet not found.');
        }

        $walletData = is_array($wallet->data) ? $wallet->data : [];

        return [
            'account_id' => $wallet->wallet_id,
            'account_name' => $walletData['account_name'] ?? null,
            'account_number' => $wallet->account_number,
            'routing_number' => $walletData['routing_number'] ?? null,
            'bank_name' => $walletData['bank_name'] ?? null,
            'currency' => $wallet->currency,
            'status' => $wallet->status,
            'type' => $walletData['type'] ?? null,
            'label' => $walletData['label'] ?? null,
            'bank_address' => $walletData['bank_address'] ?? null,
        ];
    }

    public function getExchangeRate($fromCurrency, $toCurrency): ?array
    {
        return $this->request('get', '/rate', [], [
            'from' => strtoupper((string) $fromCurrency),
            'to' => strtoupper((string) $toCurrency),
        ]);
    }

    public function convertCurrency(User $user, $walletId, array $data): array
    {
        $payload = [
            'currency_source' => strtoupper((string) $data['from_currency']),
            'currency_destination' => strtoupper((string) $data['to_currency']),
            'amount_source' => $this->amountToSubunits($data['amount'] ?? 0, $data['from_currency'] ?? 'USD'),
        ];

        if (!empty($data['account_id'])) {
            $payload['account_id'] = $data['account_id'];
        } elseif (!empty($walletId)) {
            $payload['account_id'] = $walletId;
        }

        $responseData = $this->request('post', '/conversion', $payload);
        $conversionData = Arr::get($responseData, 'data', []);
        $localWalletId = GraphWallet::where('wallet_id', $walletId)->value('id');
        $transactionId = $conversionData['conversion_id'] ?? $conversionData['id'] ?? (string) Str::uuid();

        GraphTransaction::updateOrCreate(
            ['transaction_id' => $transactionId],
            [
                'user_id' => $user->id,
                'graph_wallet_id' => $localWalletId,
                'type' => 'conversion',
                'amount' => (float) ($data['amount'] ?? 0),
                'currency' => strtoupper((string) ($data['from_currency'] ?? 'USD')),
                'status' => $this->normalizeTransactionStatus($conversionData['status'] ?? 'processing'),
                'reference' => $data['reference'] ?? ('CONV_' . time()),
                'description' => 'Converted ' . ($data['amount'] ?? 0) . ' ' . strtoupper((string) ($data['from_currency'] ?? 'USD')) . ' to ' . strtoupper((string) ($data['to_currency'] ?? 'NGN')),
                'metadata' => $responseData,
            ]
        );

        return $responseData;
    }

    protected function buildPayoutDestinationPayload(User $user, array $data): array
    {
        $details = $data['details'] ?? [];
        $type = strtolower((string) ($data['type'] ?? 'nip'));
        $walletId = $data['wallet_id'] ?? $data['account_id'] ?? GraphWallet::where('user_id', $user->id)->value('wallet_id');

        if (!$walletId) {
            throw new Exception('Wallet ID is required to create a payout destination.');
        }

        $payload = [
            'account_id' => $walletId,
            'source_type' => strtolower((string) ($data['source_type'] ?? $details['source_type'] ?? 'wallet_account')),
            'type' => $type,
            'label' => trim((string) ($data['label'] ?? $details['label'] ?? ('Beneficiary for ' . ($user->username ?? $user->email)))),
        ];

        if ($type === 'nip') {
            $payload = array_merge($payload, [
                'destination_type' => 'bank_account',
                'currency' => 'NGN',
                'account_number' => preg_replace('/\D+/', '', (string) ($details['account_number'] ?? '')),
                'bank_code' => trim((string) ($details['bank_code'] ?? '')),
                'beneficiary_name' => trim((string) ($details['beneficiary_name'] ?? $details['account_name'] ?? '')),
                'account_type' => strtolower((string) ($details['account_type'] ?? 'personal')),
            ]);
        } elseif (in_array($type, ['ach', 'wire'], true)) {
            $payload = array_merge($payload, [
                'destination_type' => 'bank_account',
                'currency' => strtoupper((string) ($data['currency'] ?? $details['currency'] ?? 'USD')),
                'account_number' => preg_replace('/\D+/', '', (string) ($details['account_number'] ?? '')),
                'beneficiary_name' => trim((string) ($details['beneficiary_name'] ?? $details['account_name'] ?? '')),
                'routing_number' => trim((string) ($details['routing_number'] ?? $details['swift_code'] ?? '')),
                'routing_type' => strtolower((string) ($details['routing_type'] ?? ($type === 'ach' ? 'aba' : 'swift'))),
                'bank_name' => $details['bank_name'] ?? null,
                'account_type' => strtolower((string) ($details['account_type'] ?? 'personal')),
                'beneficiary_address' => $this->normalizeAddressPayload($details['beneficiary_address'] ?? null),
                'bank_address' => $this->normalizeAddressPayload($details['bank_address'] ?? null),
            ]);
        } elseif ($type === 'stablecoin') {
            $payload = array_merge($payload, [
                'destination_type' => 'address',
                'currency' => strtoupper((string) ($data['currency'] ?? $details['currency'] ?? 'USDT')),
                'address_code' => trim((string) ($details['address_code'] ?? $details['address'] ?? '')),
                'address_network' => strtoupper((string) ($details['address_network'] ?? $details['network'] ?? 'ERC20')),
            ]);
        } elseif ($type === 'internal') {
            $payload = array_merge($payload, [
                'destination_type' => strtolower((string) ($details['destination_type'] ?? 'account')),
                'currency' => strtoupper((string) ($data['currency'] ?? $details['currency'] ?? 'USD')),
                'destination_account_id' => trim((string) ($details['destination_account_id'] ?? $details['account_id'] ?? '')),
                'beneficiary_name' => trim((string) ($details['beneficiary_name'] ?? '')),
            ]);
        } else {
            throw new Exception("Unsupported payout destination type [{$type}].");
        }

        return $this->filterNullValues($payload);
    }

    protected function persistWalletRecord(User $user, GraphCustomer $customer, array $walletData, string $fallbackCurrency): GraphWallet
    {
        $currency = strtoupper((string) ($walletData['currency'] ?? $fallbackCurrency));
        $wallet = GraphWallet::firstOrNew([
            'user_id' => $user->id,
            'currency' => $currency,
        ]);

        $wallet->graph_customer_id = $customer->id;
        $wallet->wallet_id = $walletData['id'];
        $wallet->account_number = $walletData['account_number'] ?? $wallet->account_number;
        $wallet->currency = $currency;
        $wallet->balance = $this->normalizeMoneyFromGraph($walletData['balance'] ?? 0);
        $wallet->status = $walletData['status'] ?? 'active';
        $wallet->data = $walletData;
        $wallet->save();

        return $wallet->fresh();
    }

    protected function normalizeIdType(string $idType): string
    {
        $normalized = strtolower(trim($idType));

        return match ($normalized) {
            'national_id', 'nin' => 'nin',
            'drivers_licence', 'driver_license' => 'drivers_license',
            default => $normalized,
        };
    }

    protected function normalizePhone(?string $phone): ?string
    {
        if ($phone === null) {
            return null;
        }

        $digits = preg_replace('/\D+/', '', $phone);

        return $digits !== '' ? $digits : null;
    }

    protected function amountToSubunits($amount, ?string $currency = null): int
    {
        return (int) round(((float) $amount) * 100);
    }

    public function normalizeMoneyFromGraph($amount): float
    {
        if ($amount === null || $amount === '') {
            return 0.0;
        }

        if (is_int($amount)) {
            return round($amount / 100, 8);
        }

        $amountString = is_string($amount) ? trim($amount) : (string) $amount;

        if ($amountString !== '' && preg_match('/^-?\d+$/', $amountString) === 1) {
            return round(((float) $amountString) / 100, 8);
        }

        return round((float) $amount, 8);
    }

    public function normalizeTransactionStatus(?string $status): string
    {
        return match (strtolower((string) $status)) {
            'successful', 'success', 'completed', 'complete' => 'completed',
            'processing', 'queued', 'initiated', 'in_progress' => 'processing',
            'cancelled', 'canceled' => 'cancelled',
            'failed', 'error' => 'failed',
            default => 'pending',
        };
    }

    protected function normalizeAddressPayload($address): ?array
    {
        if (!is_array($address)) {
            return null;
        }

        $normalized = [
            'line1' => $address['line1'] ?? null,
            'line2' => $address['line2'] ?? null,
            'city' => $address['city'] ?? null,
            'state' => $address['state'] ?? null,
            'country' => isset($address['country']) ? strtoupper((string) $address['country']) : null,
            'postal_code' => $address['postal_code'] ?? null,
        ];

        $normalized = $this->filterNullValues($normalized);

        return $normalized === [] ? null : $normalized;
    }

    protected function extractErrorMessage(HttpResponse $response): string
    {
        $payload = $response->json();

        if (is_array($payload)) {
            $message = Arr::get($payload, 'message');
            if (is_string($message) && $message !== '') {
                return $message;
            }

            if (is_array($message)) {
                $firstMessage = Arr::first(Arr::flatten($message));
                if (is_string($firstMessage) && $firstMessage !== '') {
                    return $firstMessage;
                }
            }

            $error = Arr::get($payload, 'error');
            if (is_string($error) && $error !== '') {
                return $error;
            }
        }

        return 'Graph request failed with status ' . $response->status() . '.';
    }

    protected function filterNullValues(array $payload): array
    {
        $filtered = [];

        foreach ($payload as $key => $value) {
            if (is_array($value)) {
                $value = $this->filterNullValues($value);
                if ($value === []) {
                    continue;
                }
            }

            if ($value === null || $value === '') {
                continue;
            }

            $filtered[$key] = $value;
        }

        return $filtered;
    }

    protected function sanitizeForLog($value)
    {
        if (is_array($value)) {
            $sanitized = [];

            foreach ($value as $key => $item) {
                $keyLower = strtolower((string) $key);

                if (str_contains($keyLower, 'account_number')) {
                    $sanitized[$key] = $this->maskString((string) $item, 4);
                    continue;
                }

                if (str_contains($keyLower, 'routing_number') || str_contains($keyLower, 'swift')) {
                    $sanitized[$key] = $this->maskString((string) $item, 3);
                    continue;
                }

                if (str_contains($keyLower, 'authorization') || str_contains($keyLower, 'secret') || str_contains($keyLower, 'token')) {
                    $sanitized[$key] = '[redacted]';
                    continue;
                }

                $sanitized[$key] = $this->sanitizeForLog($item);
            }

            return $sanitized;
        }

        if (is_string($value) && Str::length($value) > 500) {
            return Str::limit($value, 500, '...');
        }

        return $value;
    }

    protected function maskString(string $value, int $visibleTail = 4): string
    {
        $length = Str::length($value);

        if ($length <= $visibleTail) {
            return $value;
        }

        return str_repeat('*', max($length - $visibleTail, 0)) . Str::substr($value, -$visibleTail);
    }
}
