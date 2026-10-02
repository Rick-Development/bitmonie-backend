<?php

namespace App\Services;

use App\Http\Helpers\SafeHeaven\AccountHelper;
use App\Http\Helpers\SafeHeaven\VASHelper;
use App\Models\OrderTransaction;
use App\Models\VasServiceCategory;
use App\Models\VirtualAccounts;
use App\Notifications\User\BillPaymentNotification;
use Exception;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Str;

class SafeHavenBillService
{
    protected $vasHelper;
    protected $accountHelper;

    public function __construct(VASHelper $vasHelper, AccountHelper $accountHelper)
    {
        $this->vasHelper = $vasHelper;
        $this->accountHelper = $accountHelper;
    }

    public function getServices()
    {
        $services = \App\Models\VasService::all();

        return [
            'statusCode' => 200,
            'message' => 'Services fetched successfully',
            'data' => $services->map(function ($service) {
                return [
                    '_id' => $service->safehaven_id,
                    'name' => $service->name,
                    'identifier' => $service->identifier,
                    'description' => $service->description,
                    'createdAt' => $service->created_at,
                    'updatedAt' => $service->updated_at,
                ];
            }),
        ];
    }

    public function getCategories(string $serviceId)
    {
        return $this->handleResponse($this->vasHelper->serviceCategories($serviceId));
    }

    public function getProducts(string $categoryId)
    {
        return $this->handleResponse($this->vasHelper->serviceProducts($categoryId));
    }

    public function verifyCustomer(array $data)
    {
        return $this->handleResponse($this->vasHelper->verifyProduct($data));
    }

    public function purchase($user, string $type, array $data)
    {
        $data = $this->normalizePurchasePayload($type, $data);
        $data = $this->mapFieldsToApi($type, $data);
        $this->assertRequiredPurchaseFields($type, $data);
        $data = $this->validateSelectedProduct($type, $data);

        $amount = $this->normalizeMoneyValue($data['amount'] ?? 0) ?? '0.00000000';

        $wallet = $user->wallets()->where('currency_code', 'NGN')->first();
        if (!$wallet) {
            throw new Exception("NGN Wallet not found.");
        }

        if (bccomp($wallet->balance, $amount, 8) < 0) {
            throw new Exception("Insufficient wallet balance.");
        }

        $virtualAccount = $this->resolveDebitVirtualAccount($user);
        $accountState = $this->fetchDebitAccountState($virtualAccount);
        $this->assertDebitAccountIsReady($accountState, $amount);

        $data['debitAccountNumber'] = $virtualAccount->account_number;
        $data['channel'] = $data['channel'] ?? 'WEB';
        $reference = 'BILL-' . strtoupper(Str::random(12));

        return DB::transaction(function () use ($user, $wallet, $amount, $type, $data, $reference, $virtualAccount, $accountState) {
            WalletService::debit($wallet->id, $amount, $reference, [
                'type' => 'bill_payment',
                'bill_type' => $type,
                'provider' => 'safehaven',
                'details' => $data,
            ]);

            try {
                $response = $this->dispatchPurchase($type, $amount, $data);
                $result = $this->handleResponse($response);

                Log::info("SafeHaven Bill Purchase Success", [
                    'user_id' => $user->id,
                    'type' => $type,
                    'result' => $result,
                ]);

                $result = $this->recordSuccessfulPurchase($user, $type, $amount, $reference, $result, $data);

                return $result;
            } catch (Exception $e) {
                $resolvedMessage = $this->resolvePurchaseFailureMessage($e, $accountState, $amount);

                Log::error("SafeHaven Bill Purchase Failed - Refunding", [
                    'error' => $e->getMessage(),
                    'resolved_error' => $resolvedMessage,
                    'user_id' => $user->id,
                    'type' => $type,
                    'debit_account_number' => $virtualAccount->account_number,
                ]);

                WalletService::credit($wallet->id, $amount, $reference . '-REFUND', [
                    'reason' => 'Bill Payment Failed: ' . $resolvedMessage,
                    'original_ref' => $reference,
                ]);

                throw new Exception($resolvedMessage, (int) $e->getCode(), $e);
            }
        });
    }

    protected function dispatchPurchase(string $type, string $amount, array $data): array
    {
        switch ($type) {
            case 'airtime':
                return $this->vasHelper->airtime([
                    'amount' => (float) $amount,
                    'phoneNumber' => $data['phoneNumber'],
                    'serviceCategoryId' => $data['serviceCategoryId'],
                    'debitAccountNumber' => $data['debitAccountNumber'],
                    'channel' => $data['channel'],
                ]);

            case 'data':
                return $this->vasHelper->data(array_filter([
                    'amount' => (float) $amount,
                    'phoneNumber' => $data['phoneNumber'],
                    'serviceCategoryId' => $data['serviceCategoryId'],
                    'bundleCode' => $data['bundleCode'] ?? $data['productId'] ?? null,
                    'debitAccountNumber' => $data['debitAccountNumber'],
                    'channel' => $data['channel'],
                ], fn ($value) => $value !== null));

            case 'cable':
                return $this->vasHelper->cableTv(array_filter([
                    'amount' => (float) $amount,
                    'smartCardNumber' => $data['smartCardNumber'] ?? $data['entityNumber'],
                    'productId' => $data['productId'] ?? null,
                    'serviceCategoryId' => $data['serviceCategoryId'],
                    'debitAccountNumber' => $data['debitAccountNumber'],
                    'channel' => $data['channel'],
                ], fn ($value) => $value !== null));

            case 'utility':
                return $this->vasHelper->utilityBills(array_filter([
                    'amount' => (float) $amount,
                    'meterNumber' => $data['meterNumber'] ?? $data['entityNumber'],
                    'productId' => $data['productId'] ?? null,
                    'serviceCategoryId' => $data['serviceCategoryId'],
                    'vendType' => $data['vendType'] ?? null,
                    'debitAccountNumber' => $data['debitAccountNumber'],
                    'channel' => $data['channel'],
                ], fn ($value) => $value !== null));
        }

        throw new Exception("Invalid bill payment type.");
    }

    protected function mapFieldsToApi(string $type, array $data): array
    {
        $originalServiceCategoryId = $data['serviceCategoryId'] ?? null;

        switch ($type) {
            case 'airtime':
                $data['serviceCategoryId'] = VasServiceCategory::whereHas('service', function ($query) {
                    $query->where('name', 'Mobile Recharge')->orWhere('name', 'Airtime');
                })->where('name', $data['network'] ?? '')->value('identifier');
                break;

            case 'data':
                $data['serviceCategoryId'] = VasServiceCategory::whereHas('service', function ($query) {
                    $query->where('name', 'DATA PURCHASE')->orWhere('name', 'Data');
                })->where('name', $data['network'] ?? '')->value('identifier');
                break;

            case 'cable':
                $data['serviceCategoryId'] = VasServiceCategory::whereHas('service', function ($query) {
                    $query->where('name', 'CABLE TV')->orWhere('name', 'Cable');
                })->where('name', $data['provider'] ?? '')->value('identifier');
                break;

            case 'utility':
                $data['serviceCategoryId'] = VasServiceCategory::whereHas('service', function ($query) {
                    $query->where('name', 'UTILITY BILLS')->orWhere('name', 'Utility');
                })->where('name', $data['provider'] ?? '')->value('identifier');
                break;
        }

        if (empty($data['serviceCategoryId'])) {
            $data['serviceCategoryId'] = $originalServiceCategoryId;
        }

        return $data;
    }

    protected function normalizePurchasePayload(string $type, array $data): array
    {
        if (isset($data['phoneNumber'])) {
            $originalPhoneNumber = preg_replace('/\s+/', '', (string) $data['phoneNumber']);
            $data['phoneNumber'] = $this->normalizePhoneNumberForSafeHaven($originalPhoneNumber, $type);

            if ($data['phoneNumber'] !== $originalPhoneNumber) {
                Log::info("SafeHaven Phone Number Normalized", [
                    'type' => $type,
                    'original' => $originalPhoneNumber,
                    'normalized' => $data['phoneNumber'],
                ]);
            }
        }

        if (isset($data['productId'])) {
            $data['productId'] = trim((string) $data['productId']);
        }

        if (isset($data['serviceCategoryId'])) {
            $data['serviceCategoryId'] = trim((string) $data['serviceCategoryId']);
        }

        if (isset($data['network'])) {
            $data['network'] = $this->normalizeCategoryName($data['network'], [
                'ETISALAT' => '9MOBILE',
                '9 MOBILE' => '9MOBILE',
            ]);
        }

        if (isset($data['provider'])) {
            $data['provider'] = $this->normalizeCategoryName($data['provider'], [
                'STARTIME' => 'STARTIMES',
                'PHEDC' => 'PHED',
                'PORT HARCOURT' => 'PHED',
                'PORTHARCOURT' => 'PHED',
                'ABUJA' => 'AEDC',
                'BENIN' => 'BEDC',
                'EKO' => 'EKEDC',
                'ENUGU' => 'EEDC',
                'IBADAN' => 'IBEDC',
                'IKEJA' => 'IKEDC',
                'JOS' => 'JEDC',
                'KADUNA' => 'KAEDC',
                'YOLA' => 'YEDC',
            ]);
        }

        if (empty($data['productId']) && !empty($data['bundleCode'])) {
            $data['productId'] = $data['bundleCode'];
        }

        if (empty($data['bundleCode']) && !empty($data['productId'])) {
            $data['bundleCode'] = trim((string) $data['productId']);
        }

        if ($type === 'cable') {
            if (empty($data['smartCardNumber']) && !empty($data['cardNumber'])) {
                $data['smartCardNumber'] = trim((string) $data['cardNumber']);
            }

            if (empty($data['smartCardNumber']) && !empty($data['entityNumber'])) {
                $data['smartCardNumber'] = trim((string) $data['entityNumber']);
            }

            if (empty($data['entityNumber']) && !empty($data['smartCardNumber'])) {
                $data['entityNumber'] = $data['smartCardNumber'];
            }
        }

        if ($type === 'utility') {
            if (empty($data['meterNumber']) && !empty($data['entityNumber'])) {
                $data['meterNumber'] = trim((string) $data['entityNumber']);
            }

            if (empty($data['entityNumber']) && !empty($data['meterNumber'])) {
                $data['entityNumber'] = trim((string) $data['meterNumber']);
            }

            if (isset($data['vendType'])) {
                $data['vendType'] = strtoupper(trim((string) $data['vendType']));
            }
        }

        return $data;
    }

    protected function normalizePhoneNumberForSafeHaven(string $value, string $type): string
    {
        $trimmed = trim($value);

        if ($trimmed === '' || $type !== 'data') {
            return $trimmed;
        }

        $digits = preg_replace('/\D+/', '', $trimmed);

        if ($digits === '') {
            return $trimmed;
        }

        if (str_starts_with($digits, '00234') && strlen($digits) === 15) {
            return '+' . substr($digits, 2);
        }

        if (str_starts_with($digits, '234') && strlen($digits) === 13) {
            return '+' . $digits;
        }

        if (str_starts_with($digits, '0') && strlen($digits) === 11) {
            return '+234' . substr($digits, 1);
        }

        if (strlen($digits) === 10) {
            return '+234' . $digits;
        }

        return $trimmed;
    }

    protected function validateSelectedProduct(string $type, array $data): array
    {
        if (!in_array($type, ['data', 'cable'], true)) {
            return $data;
        }

        $serviceCategoryId = trim((string) ($data['serviceCategoryId'] ?? ''));
        $productId = trim((string) ($data['productId'] ?? ''));
        $amount = $this->normalizeMoneyValue($data['amount'] ?? null);

        if ($serviceCategoryId === '' || $productId === '' || $amount === null) {
            return $data;
        }

        try {
            $products = $this->handleResponse($this->vasHelper->serviceProducts($serviceCategoryId));
        } catch (Exception $e) {
            Log::warning("SafeHaven Product Validation Skipped", [
                'type' => $type,
                'service_category_id' => $serviceCategoryId,
                'product_id' => $productId,
                'error' => $e->getMessage(),
            ]);

            return $data;
        }

        $productCodeField = $type === 'data' ? 'bundleCode' : 'bundleCode';

        $matchingProducts = array_values(array_filter($products, function ($product) use ($productCodeField, $productId) {
            return trim((string) ($product[$productCodeField] ?? $product['productId'] ?? '')) === $productId;
        }));

        if (empty($matchingProducts)) {
            throw new Exception(sprintf(
                'Selected %s plan is invalid. Refresh products and use the exact bundleCode from the latest response.',
                $type
            ));
        }

        $exactAmountMatches = array_values(array_filter($matchingProducts, function ($product) use ($amount) {
            $productAmount = $this->normalizeMoneyValue($product['amount'] ?? null);

            return $productAmount !== null && bccomp($productAmount, $amount, 8) === 0;
        }));

        if (empty($exactAmountMatches)) {
            $allowedAmounts = array_values(array_unique(array_filter(array_map(function ($product) {
                $productAmount = $this->normalizeMoneyValue($product['amount'] ?? null);

                return $productAmount === null ? null : number_format((float) $productAmount, 0, '.', '');
            }, $matchingProducts))));

            $amountList = implode(', ', array_slice(array_map(fn ($value) => 'N' . $value, $allowedAmounts), 0, 5));

            throw new Exception(sprintf(
                'Selected %s plan amount does not match bundleCode %s. Refresh products and use an exact amount%s.',
                $type,
                $productId,
                $amountList !== '' ? " ({$amountList})" : ''
            ));
        }

        Log::info("SafeHaven Product Validation Passed", [
            'type' => $type,
            'service_category_id' => $serviceCategoryId,
            'product_id' => $productId,
            'amount' => $amount,
            'matched_product' => $exactAmountMatches[0],
        ]);

        return $data;
    }

    protected function normalizeCategoryName(string $value, array $aliases = []): string
    {
        $normalized = strtoupper(trim($value));

        return $aliases[$normalized] ?? $normalized;
    }

    protected function assertRequiredPurchaseFields(string $type, array $data): void
    {
        $requiredFields = [
            'airtime' => ['amount', 'phoneNumber', 'serviceCategoryId'],
            'data' => ['amount', 'phoneNumber', 'serviceCategoryId', 'productId'],
            'cable' => ['amount', 'smartCardNumber', 'serviceCategoryId', 'productId'],
            'utility' => ['amount', 'meterNumber', 'serviceCategoryId', 'vendType'],
        ];

        $missing = [];

        foreach ($requiredFields[$type] ?? [] as $field) {
            $value = $data[$field] ?? null;

            if (is_string($value)) {
                $value = trim($value);
            }

            if ($value === null || $value === '') {
                $missing[] = $field;
            }
        }

        if (!empty($missing)) {
            throw new Exception('Missing required SafeHaven field(s): ' . implode(', ', $missing) . '.');
        }
    }

    protected function resolveDebitVirtualAccount($user): VirtualAccounts
    {
        $virtualAccount = $user->virtualAccounts()
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

        if (!$virtualAccount || empty($virtualAccount->account_number)) {
            throw new Exception("You do not have a SafeHaven sub-account to fund this transaction. Please create one first.");
        }

        if (empty($virtualAccount->provider)) {
            $virtualAccount->update(['provider' => 'safehaven']);
        }

        return $virtualAccount;
    }

    protected function fetchDebitAccountState(VirtualAccounts $virtualAccount): array
    {
        if (empty($virtualAccount->account_id)) {
            return ['checked' => false];
        }

        try {
            $response = $this->accountHelper->getAccount($virtualAccount->account_id);

            if (!is_array($response) || (int) ($response['statusCode'] ?? 0) !== 200 || !isset($response['data']) || !is_array($response['data'])) {
                return ['checked' => false];
            }

            $account = $response['data'];

            return [
                'checked' => true,
                'canDebit' => $account['canDebit'] ?? null,
                'accountBalance' => $this->normalizeMoneyValue($account['accountBalance'] ?? null),
                'bookBalance' => $this->normalizeMoneyValue($account['bookBalance'] ?? null),
            ];
        } catch (Exception $e) {
            Log::warning("SafeHaven Debit Account Lookup Failed", [
                'account_id' => $virtualAccount->account_id,
                'account_number' => $virtualAccount->account_number,
                'error' => $e->getMessage(),
            ]);

            return ['checked' => false];
        }
    }

    protected function assertDebitAccountIsReady(array $accountState, string $amount): void
    {
        if (($accountState['checked'] ?? false) !== true) {
            return;
        }

        if (($accountState['canDebit'] ?? true) === false) {
            throw new Exception("Your SafeHaven account is not enabled for debits yet. Please contact support.");
        }

        $availableBalance = $accountState['accountBalance'] ?? $accountState['bookBalance'] ?? null;

        if ($availableBalance !== null && bccomp($availableBalance, $amount, 8) < 0) {
            throw new Exception("Insufficient Naira Balance. Please fund your NGN wallet before paying bills.");
        }
    }

    protected function resolvePurchaseFailureMessage(Exception $e, array $accountState, string $amount): string
    {
        $message = $e->getMessage();

        if (!in_array($message, [
            'Bad Request',
            'Failed to buy airtime. Please try again.',
            'SafeHaven Service Error',
        ], true)) {
            return $message;
        }

        if (($accountState['checked'] ?? false) !== true) {
            return $message;
        }

        if (($accountState['canDebit'] ?? true) === false) {
            return "Your SafeHaven account is not enabled for debits yet. Please contact support.";
        }

        $availableBalance = $accountState['accountBalance'] ?? $accountState['bookBalance'] ?? null;

        if ($availableBalance !== null && bccomp($availableBalance, $amount, 8) < 0) {
            return "Insufficient Naira Balance. Please fund your NGN wallet before paying bills.";
        }

        return $message;
    }

    protected function recordSuccessfulPurchase($user, string $type, string $amount, string $reference, array $result, array $requestData): array
    {
        $transaction = OrderTransaction::where('reference', $reference)->latest('id')->first();
        $token = $this->extractVendToken($result);
        $providerReference = $this->extractProviderReference($result) ?: $reference;

        if ($transaction) {
            $metadata = $transaction->metadata ?? [];
            $metadata['status'] = 'successful';
            $metadata['provider_reference'] = $providerReference;
            $metadata['token'] = $token;
            $metadata['token_code'] = $token;
            $metadata['provider_response'] = $result;
            $metadata['bill_purchase'] = [
                'type' => $type,
                'amount' => $amount,
                'provider' => $requestData['provider'] ?? $requestData['network'] ?? $requestData['serviceCategoryId'] ?? null,
                'meter_number' => $requestData['meterNumber'] ?? $requestData['entityNumber'] ?? null,
                'phone_number' => $requestData['phoneNumber'] ?? null,
                'smart_card_number' => $requestData['smartCardNumber'] ?? $requestData['entityNumber'] ?? null,
                'vend_type' => $requestData['vendType'] ?? null,
                'token' => $token,
                'provider_reference' => $providerReference,
                'completed_at' => now()->toDateTimeString(),
            ];

            $transaction->update(['metadata' => $metadata]);
        }

        try {
            $user->notify(new BillPaymentNotification(
                $this->billTypeLabel($type),
                $amount,
                $requestData['provider'] ?? $requestData['network'] ?? 'SafeHaven',
                'successful',
                $providerReference,
                $token
            ));
        } catch (Exception $e) {
            Log::warning('SafeHaven bill email notification failed', [
                'user_id' => $user->id,
                'reference' => $reference,
                'error' => $e->getMessage(),
            ]);
        }

        return array_merge($result, [
            'transaction_reference' => $reference,
            'history_transaction_id' => $transaction?->id,
            'token' => $token,
            'token_code' => $token,
            'provider_reference' => $providerReference,
        ]);
    }

    protected function extractVendToken(array $payload): ?string
    {
        $tokenKeys = [
            'token',
            'Token',
            'TOKEN',
            'vendToken',
            'vend_token',
            'rechargeToken',
            'recharge_token',
            'meterToken',
            'meter_token',
            'pin',
            'code',
        ];

        foreach ($tokenKeys as $key) {
            $value = data_get($payload, $key);
            if (is_scalar($value) && trim((string) $value) !== '') {
                return trim((string) $value);
            }
        }

        foreach ($payload as $value) {
            if (is_array($value)) {
                $found = $this->extractVendToken($value);
                if ($found !== null) {
                    return $found;
                }
            }
        }

        return null;
    }

    protected function extractProviderReference(array $payload): ?string
    {
        foreach (['reference', 'transactionId', 'transaction_id', 'paymentReference', 'payment_reference', 'sessionId', '_id', 'id'] as $key) {
            $value = data_get($payload, $key);
            if (is_scalar($value) && trim((string) $value) !== '') {
                return trim((string) $value);
            }
        }

        foreach ($payload as $value) {
            if (is_array($value)) {
                $found = $this->extractProviderReference($value);
                if ($found !== null) {
                    return $found;
                }
            }
        }

        return null;
    }

    protected function billTypeLabel(string $type): string
    {
        return match ($type) {
            'airtime' => 'Airtime',
            'data' => 'Data',
            'cable' => 'Cable TV',
            'utility' => 'Electricity Bill',
            default => ucfirst($type),
        };
    }

    protected function normalizeMoneyValue($value): ?string
    {
        if ($value === null || $value === '' || !is_numeric($value)) {
            return null;
        }

        return number_format((float) $value, 8, '.', '');
    }

    protected function handleResponse($response)
    {
        if (!is_array($response)) {
            throw new Exception("Invalid response from SafeHaven Service.");
        }

        if (($response['statusCode'] ?? 0) !== 200) {
            throw new Exception($response['message'] ?? $response['description'] ?? 'SafeHaven Service Error');
        }

        return $response['data'] ?? [];
    }
}
