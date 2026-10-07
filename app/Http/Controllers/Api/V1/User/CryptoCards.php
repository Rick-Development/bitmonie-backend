<?php

namespace App\Http\Controllers\Api\V1\User;

use App\Http\Controllers\Controller;
use App\Models\KycVerification;
use App\Models\OrderTransaction;
use App\Models\UserWallet;
use App\Models\Admin\Currency;
use App\Services\CryptoCardService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Arr;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Validator;
use Throwable;
use App\Models\CryptoCardOrder;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Str;
use App\Models\CryptoCardSetup;
use Illuminate\Validation\Rule;
use App\Services\QuidaxService;
use App\Services\CryptoTransactionService;

class CryptoCards extends Controller
{
  public function __construct(
    protected CryptoCardService $cardService,
    protected QuidaxService $quidaxService,
    protected CryptoTransactionService $cryptoTransactionService
) {
}

    /*
    |--------------------------------------------------------------------------
    | AUTHENTICATION / HELPERS
    |--------------------------------------------------------------------------
    */

    protected function userId(): ?int
    {
        $id = auth()->id();
        return $id ? (int) $id : null;
    }

    protected function unauthenticated(): JsonResponse
    {
        return response()->json([
            'success' => false,
            'message' => 'Unauthenticated.',
        ], 401);
    }

    protected function serviceResponse(array $result, int $successStatus = 200): JsonResponse
    {
        if (($result['success'] ?? false) === true) {
            return response()->json($result, $successStatus);
        }

        $statusCode = (int) ($result['statusCode'] ?? 422);
        if ($statusCode < 400 || $statusCode > 599) {
            $statusCode = 422;
        }

        return response()->json($result, $statusCode);
    }

    protected function validateProvider(?string $provider = null, bool $required = false): ?JsonResponse
    {
        if (!$required && ($provider === null || trim($provider) === '')) {
            return null;
        }

        if ($provider === null || trim($provider) === '') {
            return response()->json([
                'success' => false,
                'message' => 'Provider is required.',
            ], 422);
        }

        $provider = strtolower(trim($provider));

        if (!$this->cardService->supports($provider)) {
            return response()->json([
                'success' => false,
                'message' => 'Unsupported provider.',
                'provider' => $provider,
                'supported_providers' => $this->cardService->supportedProviders(),
            ], 422);
        }

        return null;
    }

    protected function providerFromRequest(Request $request, string $default = 'sudo'): string
    {
        return strtolower(trim((string) $request->input('provider', $default)));
    }

    protected function resolveRequestProvider(Request $request, string $default = 'sudo'): array|JsonResponse
    {
        $provider = $this->providerFromRequest($request, $default);
        $validation = $this->validateProvider($provider);

        if ($validation) {
            return $validation;
        }

        return ['provider' => $provider];
    }

    /**
     * Transform a CryptoCardsModel into a clean API response.
     */
   /**
 * Format card data for API response.
 */
protected function formatCard($card): array
{
    $currency = strtoupper((string) $card->card_currency);

    return [
        'id'                     => $card->id,
        'card_holder_id'         => $card->card_holder_id,
        'provider'               => $card->card_provider,
        'provider_card_id'       => $card->card_provider_id,
        'type'                   => $card->card_type,
        'brand'                  => $card->card_brand,
        'currency'               => $currency === 'USD' ? 'USDT' : $currency,
        'balance'                => (float) $card->card_balance,
        'status'                 => $card->card_status,
        'masked_pan'             => $card->masked_pan,
        'last_four'              => $card->card_last_four,
        'expiry_month'           => $card->expiry_month,
        'expiry_year'            => $card->expiry_year,
        'is_2fa_enrolled'        => (bool) $card->is_2fa_enrolled,
        'is_default_pin_changed' => (bool) $card->is_default_pin_changed,
        'is_disposable'          => (bool) $card->is_disposable,
        'spending_controls'      => is_string($card->spending_controls)
            ? json_decode($card->spending_controls, true)
            : $card->spending_controls,
        'created_at'             => $card->created_at,
        'updated_at'             => $card->updated_at,
    ];
}

    /*
    |--------------------------------------------------------------------------
    | CARD HOLDERS
    |--------------------------------------------------------------------------
    */

    /**
     * Create a card holder/customer.
     * POST /api/v1/user/crypto-cards/create/holder
     */
    public function createHolder(Request $request): JsonResponse
    {
        $validator = Validator::make($request->all(), [
            'provider' => ['required', 'string', 'in:sudo'],
            'type' => ['nullable', 'string', 'in:individual,company'],
            'name' => ['nullable', 'string', 'max:255'],
            'phoneNumber' => ['nullable', 'string', 'max:30'],
            'emailAddress' => ['nullable', 'email', 'max:255'],
            'status' => ['nullable', 'string', 'in:active,inactive'],
            'billingAddress' => ['nullable', 'array'],
            'billingAddress.line1' => ['nullable', 'string', 'max:255'],
            'billingAddress.line2' => ['nullable', 'string', 'max:255'],
            'billingAddress.city' => ['nullable', 'string', 'max:100'],
            'billingAddress.state' => ['nullable', 'string', 'max:100'],
            'billingAddress.postalCode' => ['nullable', 'string', 'max:20'],
            'billingAddress.country' => ['nullable', 'string', 'max:100'],
            'individual' => ['nullable', 'array'],
            'individual.firstName' => ['nullable', 'string', 'max:100'],
            'individual.lastName' => ['nullable', 'string', 'max:100'],
            'individual.otherNames' => ['nullable', 'string', 'max:150'],
            'individual.dob' => ['nullable', 'date'],
            'individual.identity' => ['nullable', 'array'],
            'individual.identity.type' => ['nullable', 'string', 'max:50'],
            'individual.identity.number' => ['nullable', 'string', 'max:100'],
            'individual.documents' => ['nullable', 'array'],
            'metadata' => ['nullable', 'array'],
        ]);

        if ($validator->fails()) {
            return response()->json([
                'success' => false,
                'message' => 'Validation failed.',
                'errors' => $validator->errors(),
            ], 422);
        }

        $userId = $this->userId();
        if (!$userId) {
            return $this->unauthenticated();
        }

        $user = auth()->user();
        $provider = strtolower(trim((string) $request->input('provider')));

        // Get latest verified KYC data
        $kycData = $user->getVerifiedKycData();
        $providerResponse = Arr::get($kycData, 'verification_result.providerResponse');
        if (!is_array($providerResponse)) {
            $providerResponse = Arr::get($kycData, 'providerResponse', []);
        }
        if (!is_array($providerResponse)) {
            $providerResponse = [];
        }

        $payload = $request->except('provider');
        $requestIndividual = is_array($payload['individual'] ?? null) ? $payload['individual'] : [];
        $requestAddress = is_array($payload['billingAddress'] ?? null) ? $payload['billingAddress'] : [];

        // Individual: use request, fallback to verified KYC, fallback to User model
        $firstName = $requestIndividual['firstName']
            ?? Arr::get($providerResponse, 'firstName')
            ?? $user->firstname;

        $lastName = $requestIndividual['lastName']
            ?? Arr::get($providerResponse, 'lastName')
            ?? $user->lastname;

        $otherNames = $requestIndividual['otherNames']
            ?? Arr::get($providerResponse, 'otherNames')
            ?? null;

        $dob = $requestIndividual['dob']
            ?? Arr::get($providerResponse, 'dateOfBirth')
            ?? Arr::get($providerResponse, 'dob')
            ?? $user->birthdate;

        if ($dob) {
            try {
                $timestamp = strtotime($dob);
                $dob = $timestamp !== false ? date('Y-m-d', $timestamp) : null;
            } catch (Throwable) {
                $dob = null;
            }
        }

        $identityType = Arr::get($requestIndividual, 'identity.type') ?? 'BVN';
        $identityNumber = Arr::get($requestIndividual, 'identity.number')
            ?? $user->getVerifiedBvn()
            ?? Arr::get($providerResponse, 'bvn')
            ?? Arr::get($kycData, 'bvn');

        if (empty($identityNumber)) {
            return response()->json([
                'success' => false,
                'message' => 'BVN is required. Please verify your BVN or provide a valid BVN.',
                'errors' => [
                    'individual.identity.number' => ['BVN is required for unverified users.'],
                ],
            ], 422);
        }

        $individual = [
            'firstName' => $firstName,
            'lastName' => $lastName,
        ];
        if ($otherNames) {
            $individual['otherNames'] = $otherNames;
        }
        if ($dob) {
            $individual['dob'] = $dob;
        }
        if ($identityNumber) {
            $individual['identity'] = [
                'type' => $identityType,
                'number' => $identityNumber,
            ];
        }

        // Billing Address: use request, fallback to KYC, fallback to User address
        $userAddress = (array) ($user->address ?? []);
        $addressLine1 = $requestAddress['line1']
            ?? Arr::get($providerResponse, 'residentialAddress')
            ?? Arr::get($providerResponse, 'address')
            ?? ($userAddress['address'] ?? 'Lagos');

        $addressLine2 = $requestAddress['line2'] ?? null;

        $city = $requestAddress['city']
            ?? Arr::get($providerResponse, 'city')
            ?? Arr::get($providerResponse, 'lgaOfResidence')
            ?? ($userAddress['city'] ?? 'Lagos');

        $state = $requestAddress['state']
            ?? Arr::get($providerResponse, 'state')
            ?? Arr::get($providerResponse, 'stateOfResidence')
            ?? ($userAddress['state'] ?? 'Lagos');

        $postalCode = $requestAddress['postalCode']
            ?? Arr::get($providerResponse, 'postalCode')
            ?? Arr::get($providerResponse, 'postal_code')
            ?? Arr::get($kycData, 'postalCode')
            ?? ($userAddress['zip'] ?? '100001');

        $country = $requestAddress['country']
            ?? Arr::get($providerResponse, 'country')
            ?? ($userAddress['country'] ?? 'NG');

        // Customer Details
        $name = $payload['name']
            ?? Arr::get($providerResponse, 'fullName')
            ?? Arr::get($providerResponse, 'nameOnCard');

        if (!$name) {
            $name = trim(implode(' ', array_filter([$firstName, $otherNames, $lastName])));
        }
        if (!$name) {
            $name = $user->fullname ?? ($user->firstname . ' ' . $user->lastname);
        }

        $phoneNumber = $payload['phoneNumber']
            ?? $user->full_mobile
            ?? $user->mobile
            ?? Arr::get($providerResponse, 'phoneNumber1')
            ?? Arr::get($providerResponse, 'phoneNumber');

        $emailAddress = $payload['emailAddress'] ?? $user->email ?? Arr::get($providerResponse, 'email');
        $status = $payload['status'] ?? 'active';
        $type = $payload['type'] ?? 'individual';

        if ($type === 'company') {
            return response()->json([
                'success' => false,
                'message' => 'Company card holders are not yet supported.',
            ], 422);
        }

        // Required fields
        $requiredErrors = [];
        if (empty($name)) {
            $requiredErrors['name'] = ['Customer name is required.'];
        }
        if (empty($phoneNumber)) {
            $requiredErrors['phoneNumber'] = ['Customer phone number is required.'];
        }
        if (empty($addressLine1)) {
            $requiredErrors['billingAddress.line1'] = ['Billing address line1 is required.'];
        }
        if (empty($city)) {
            $requiredErrors['billingAddress.city'] = ['Billing address city is required.'];
        }
        if (empty($state)) {
            $requiredErrors['billingAddress.state'] = ['Billing address state is required.'];
        }
        if (empty($postalCode)) {
            $requiredErrors['billingAddress.postalCode'] = ['Billing address postal code is required by Sudo.'];
        }
        if (empty($country)) {
            $requiredErrors['billingAddress.country'] = ['Billing address country is required.'];
        }
        if (empty($firstName)) {
            $requiredErrors['individual.firstName'] = ['First name is required.'];
        }
        if (empty($lastName)) {
            $requiredErrors['individual.lastName'] = ['Last name is required.'];
        }

        if (!empty($requiredErrors)) {
            return response()->json([
                'success' => false,
                'message' => 'Insufficient information to create the card holder.',
                'errors' => $requiredErrors,
            ], 422);
        }

        $billingAddress = [
            'line1' => $addressLine1,
            'city' => $city,
            'state' => $state,
            'postalCode' => (string) $postalCode,
            'country' => $country,
        ];
        if (!empty($addressLine2)) {
            $billingAddress['line2'] = $addressLine2;
        }

        $providerPayload = [
            'type' => $type,
            'name' => $name,
            'phoneNumber' => $phoneNumber,
            'status' => $status,
            'billingAddress' => $billingAddress,
            'individual' => $individual,
        ];

        if (!empty($emailAddress)) {
            $providerPayload['emailAddress'] = $emailAddress;
        }
        if (!empty($payload['metadata'])) {
            $providerPayload['metadata'] = $payload['metadata'];
        }

        $result = $this->cardService->createCardHolder(
            userId: $userId,
            provider: $provider,
            payload: $providerPayload
        );

        return $this->serviceResponse($result, 201);
    }

    /**
     * Update card holder.
     * PUT /api/v1/user/crypto-cards/update/holder
     */
    public function updateHolder(Request $request): JsonResponse
    {
        $validator = Validator::make($request->all(), [
            'provider' => ['nullable', 'string', 'in:sudo'],
            'name' => ['nullable', 'string', 'max:255'],
            'first_name' => ['nullable', 'string', 'max:100'],
            'last_name' => ['nullable', 'string', 'max:100'],
            'other_names' => ['nullable', 'string', 'max:150'],
            'email' => ['nullable', 'email', 'max:255'],
            'phone_number' => ['nullable', 'string', 'max:30'],
            'status' => ['nullable', 'string', 'in:active,inactive,suspended'],
            'is_approved' => ['nullable', 'boolean'],
            'address_line1' => ['nullable', 'string', 'max:255'],
            'address_line2' => ['nullable', 'string', 'max:255'],
            'city' => ['nullable', 'string', 'max:100'],
            'state' => ['nullable', 'string', 'max:100'],
            'postal_code' => ['nullable', 'string', 'max:20'],
            'country' => ['nullable', 'string', 'max:100'],
            'identity_type' => ['nullable', 'string', 'max:50'],
            'identity_number' => ['nullable', 'string', 'max:100'],
            'metadata' => ['nullable', 'array'],
            'provider_data' => ['nullable', 'array'],
        ]);

        if ($validator->fails()) {
            return response()->json([
                'success' => false,
                'message' => 'Validation failed.',
                'errors' => $validator->errors(),
            ], 422);
        }

        $userId = $this->userId();
        if (!$userId) {
            return $this->unauthenticated();
        }

        $provider = $request->filled('provider')
            ? strtolower(trim((string) $request->input('provider')))
            : null;

        if ($provider !== null) {
            $validation = $this->validateProvider($provider);
            if ($validation) {
                return $validation;
            }
        }

        $payload = $request->except(['provider']);

        $result = $this->cardService->updateCardHolder(
            userId: $userId,
            payload: $payload,
            provider: $provider
        );

        return $this->serviceResponse($result);
    }

    /**
     * Get authenticated user's card holder.
     * GET /api/v1/user/crypto-cards/get/holder
     */
    public function getHolder(Request $request): JsonResponse
    {
        $userId = $this->userId();
        if (!$userId) {
            return $this->unauthenticated();
        }

        $provider = $request->query('provider');
        if ($provider !== null) {
            $validation = $this->validateProvider($provider);
            if ($validation) {
                return $validation;
            }
        }

        $holder = $this->cardService->getCardHolder(
            userId: $userId,
            provider: $provider
        );

        if (!$holder) {
            return response()->json([
                'success' => false,
                'message' => 'Card holder not found.',
            ], 404);
        }

        return response()->json([
            'success' => true,
            'data' => $holder,
        ]);
    }

    /*
    |--------------------------------------------------------------------------
    | CARDS
    |--------------------------------------------------------------------------
    */

    /**
     * List user's cards.
     * GET /api/v1/user/crypto-cards
     */
    public function index(Request $request): JsonResponse
    {
        $userId = $this->userId();
        if (!$userId) {
            return $this->unauthenticated();
        }

        $provider = $request->query('provider');
        if ($provider !== null) {
            $validation = $this->validateProvider($provider);
            if ($validation) {
                return $validation;
            }
        }

        $cards = $this->cardService->getUserCards(
            userId: $userId,
            provider: $provider
        );

        $data = $cards->map(fn ($card) => $this->formatCard($card));

        return response()->json([
            'success' => true,
            'count'   => $data->count(),
            'data'    => $data,
        ]);
    }




    /**
     * Create card.
     * POST /api/v1/user/crypto-cards/create
     */
public function createCard(Request $request): JsonResponse
{
  
    $validator = Validator::make($request->all(), [
        'provider' => ['nullable', 'string', 'in:sudo'],
        'type' => ['required', 'string', 'in:virtual,physical'],
        'currency' => ['required', 'string', 'in:USDT,usdt,NGN,ngn,USD,usd'],
        'status' => ['nullable', 'string', 'in:active,inactive'],
        'brand' => ['nullable', 'string', 'max:100'],
        'debitAccountId' => ['nullable', 'string'],
        'fundingSourceId' => ['nullable', 'string'],
        'number' => ['required_if:type,physical', 'nullable', 'string'],
        'enable2FA' => ['nullable', 'boolean'],
        'issuerCountry' => ['nullable', 'string', 'max:10'],
        'spendingControls' => ['nullable', 'array'],
        'metadata' => ['nullable', 'array'],
        'amount' => ['nullable', 'numeric', 'min:0'],
        'sendPINSMS' => ['nullable', 'boolean'],
        'cardHolderId' => ['nullable', 'integer'],
    ]);

    if ($validator->fails()) {
        return response()->json([
            'success' => false,
            'message' => 'Validation failed.',
            'errors' => $validator->errors(),
        ], 422);
    }

    $userId = $this->userId();
    if (!$userId) {
        return $this->unauthenticated();
    }

    /*
    |--------------------------------------------------------------------------
    | Charge card issuance fee FIRST based on card type
    | (This already records the charge in CryptoTransactionService)
    |--------------------------------------------------------------------------
    */

    $cardType = $request->input('type', 'virtual');
    $issuanceChargeType = ($cardType === 'physical') ? 'physical_card_fee' : 'virtual_card_issuance_fee';
    $charge = $this->transferCardCharge($issuanceChargeType);
    

    if (!$charge['success']) {
        return $this->serviceResponse($charge);
    }

    $cardHolderId = $request->integer('cardHolderId');
    if ($cardHolderId > 0) {
        $holder = $this->cardService->getCardHolder($userId);
        if (!$holder || (int) $holder->id !== $cardHolderId) {
            return response()->json([
                'success' => false,
                'message' => 'Card holder does not belong to the authenticated user.',
            ], 403);
        }
    }

    $provider = $this->providerFromRequest($request);

    $request->merge([
        'currency' => 'NGN',
        'status' => $request->input('status', 'active'),
        'brand' => $request->input('brand', 'Visa'),
    ]);

    $result = $this->cardService->createCard(
        userId: $userId,
        provider: $provider,
        payload: $request->except(['provider', 'cardHolderId']),
        cardHolderId: $cardHolderId ?: null
    );

    return $this->serviceResponse($result, 201);
}

    /**
     * Show card.
     * GET /api/v1/user/crypto-cards/show/{id}
     */
    public function show(int $id): JsonResponse
    {
        $userId = $this->userId();
        if (!$userId) {
            return $this->unauthenticated();
        }

        $card = $this->cardService->getCard(userId: $userId, cardId: $id);

        if (!$card) {
            return response()->json([
                'success' => false,
                'message' => 'Card not found.',
            ], 404);
        }

        return response()->json([
            'success' => true,
            'data'    => $this->formatCard($card),
        ]);
    }

    /**
     * Update card.
     * POST /api/v1/user/crypto-cards/update/{id}
     */
    public function update(Request $request, int $id): JsonResponse
    {
        $validator = Validator::make($request->all(), [
            'status' => ['nullable', 'string', 'in:active,inactive,canceled'],
            'fundingSourceId' => ['nullable', 'string'],
            'spendingControls' => ['nullable', 'array'],
            'metadata' => ['nullable', 'array'],
            'cancellationReason' => [
                'required_if:status,canceled',
                'nullable',
                'string',
                'in:lost,stolen',
            ],
        ]);

        if ($validator->fails()) {
            return response()->json([
                'success' => false,
                'message' => 'Validation failed.',
                'errors' => $validator->errors(),
            ], 422);
        }

        $userId = $this->userId();
        if (!$userId) {
            return $this->unauthenticated();
        }

        $result = $this->cardService->updateCard(
            userId: $userId,
            cardId: $id,
            payload: $request->all()
        );

        return $this->serviceResponse($result);
    }

    /*
    |--------------------------------------------------------------------------
    | CARD OPERATIONS
    |--------------------------------------------------------------------------
    */

    public function sendPin(int $id): JsonResponse
    {
        $userId = $this->userId();
        if (!$userId) {
            return $this->unauthenticated();
        }

        return $this->serviceResponse(
            $this->cardService->sendCardPin(userId: $userId, cardId: $id)
        );
    }

    public function updatePin(Request $request, int $id): JsonResponse
    {
        $validator = Validator::make($request->all(), [
            'pin' => ['required', 'string', 'digits:4'],
            'newPin' => ['nullable', 'string', 'digits:4'],
            'currentPin' => ['nullable', 'string', 'digits:4'],
        ]);

        if ($validator->fails()) {
            return response()->json([
                'success' => false,
                'message' => 'Validation failed.',
                'errors' => $validator->errors(),
            ], 422);
        }

        $userId = $this->userId();
        if (!$userId) {
            return $this->unauthenticated();
        }

        return $this->serviceResponse(
            $this->cardService->updateCardPin(
                userId: $userId,
                cardId: $id,
                payload: $request->all()
            )
        );
    }

    public function enroll2FA(int $id): JsonResponse
    {
        $userId = $this->userId();
        if (!$userId) {
            return $this->unauthenticated();
        }

        return $this->serviceResponse(
            $this->cardService->enrollCard2FA(userId: $userId, cardId: $id)
        );
    }

    public function digitalize(int $id): JsonResponse
    {
        $userId = $this->userId();
        if (!$userId) {
            return $this->unauthenticated();
        }

        return $this->serviceResponse(
            $this->cardService->digitalizeCard(userId: $userId, cardId: $id)
        );
    }

    public function cardToken(int $id): JsonResponse
    {
        $userId = $this->userId();
        if (!$userId) {
            return $this->unauthenticated();
        }

        return $this->serviceResponse(
            $this->cardService->getCardToken(userId: $userId, cardId: $id)
        );
    }

    // public function orderCard(Request $request): JsonResponse
    // {
    //     $validator = Validator::make($request->all(), [
    //         'provider' => ['nullable', 'string', 'in:sudo'],
    //         'type' => ['required', 'string', 'in:physical'],
    //         'currency' => ['nullable', 'string'],
    //         'customerId' => ['nullable', 'string'],
    //         'cardId' => ['nullable', 'string'],
    //         'debitAccountId' => ['nullable', 'string'],
    //         'fundingSourceId' => ['nullable', 'string'],
    //         'shippingAddress' => ['nullable', 'array'],
    //         'metadata' => ['nullable', 'array'],
    //     ]);

    //     if ($validator->fails()) {
    //         return response()->json([
    //             'success' => false,
    //             'message' => 'Validation failed.',
    //             'errors' => $validator->errors(),
    //         ], 422);
    //     }

    //     $userId = $this->userId();
    //     if (!$userId) {
    //         return $this->unauthenticated();
    //     }

    //     $provider = $this->providerFromRequest($request);

    //     $holder = $this->cardService->getCardHolder(userId: $userId, provider: $provider);
    //     if (!$holder) {
    //         return response()->json([
    //             'success' => false,
    //             'message' => 'Card holder not found.',
    //         ], 404);
    //     }

    //     $payload = $request->except('provider');
    //     $customerId = $holder->getProviderCustomerId($provider);
    //     if ($customerId) {
    //         $payload['customerId'] = $customerId;
    //     }

    //     $result = $this->cardService->orderCard(
    //         provider: $provider,
    //         payload: $payload
    //     );

    //     return $this->serviceResponse($result, 201);
    // }


public function getCardOrders(Request $request): JsonResponse
{
    $validator = Validator::make($request->all(), [
        'provider' => ['nullable', 'string', 'in:sudo'],
        'status' => ['nullable', 'string', 'max:50'],
        'reference' => ['nullable', 'string', 'max:100'],
        'order_type' => ['nullable', 'string', 'in:physical'],
        'per_page' => ['nullable', 'integer', 'min:1', 'max:100'],
    ]);

    if ($validator->fails()) {
        return response()->json([
            'success' => false,
            'message' => 'Validation failed.',
            'errors' => $validator->errors(),
        ], 422);
    }

    $userId = $this->userId();

    if (!$userId) {
        return $this->unauthenticated();
    }

    try {
        $query = CryptoCardOrder::query()
            ->where('user_id', $userId)
            ->latest('created_at');

        if ($request->filled('provider')) {
            $query->where(
                'provider',
                strtolower(trim((string) $request->input('provider')))
            );
        }

        if ($request->filled('status')) {
            $query->where(
                'status',
                strtolower(trim((string) $request->input('status')))
            );
        }

        if ($request->filled('reference')) {
            $query->where(
                'reference',
                trim((string) $request->input('reference'))
            );
        }

        if ($request->filled('order_type')) {
            $query->where(
                'order_type',
                strtolower(trim((string) $request->input('order_type')))
            );
        }

        $perPage = $request->integer('per_page', 20);

        $orders = $query->paginate($perPage);

        Log::info('Crypto card orders retrieved.', [
            'user_id' => $userId,
            'count' => $orders->count(),
            'total' => $orders->total(),
            'current_page' => $orders->currentPage(),
            'per_page' => $orders->perPage(),
            'filters' => [
                'provider' => $request->input('provider'),
                'status' => $request->input('status'),
                'reference' => $request->input('reference'),
                'order_type' => $request->input('order_type'),
            ],
        ]);

        return response()->json([
            'success' => true,
            'message' => 'Crypto card orders retrieved successfully.',
            'data' => $orders->items(),
            'pagination' => [
                'current_page' => $orders->currentPage(),
                'last_page' => $orders->lastPage(),
                'per_page' => $orders->perPage(),
                'total' => $orders->total(),
                'from' => $orders->firstItem(),
                'to' => $orders->lastItem(),
            ],
        ]);
    } catch (Throwable $e) {
        Log::error('Failed to retrieve crypto card orders.', [
            'user_id' => $userId,
            'error' => $e->getMessage(),
            'exception' => get_class($e),
        ]);

        return response()->json([
            'success' => false,
            'message' => 'Unable to retrieve crypto card orders.',
        ], 500);
    }
}




    /*
    |--------------------------------------------------------------------------
    | PROVIDER CARDS
    |--------------------------------------------------------------------------
    */

    public function providerCards(Request $request): JsonResponse
    {
        $validator = Validator::make($request->all(), [
            'provider' => ['nullable', 'string', 'in:sudo'],
            'page' => ['nullable', 'integer', 'min:1'],
            'limit' => ['nullable', 'integer', 'min:1', 'max:100'],
            'status' => ['nullable', 'string'],
        ]);

        if ($validator->fails()) {
            return response()->json([
                'success' => false,
                'message' => 'Validation failed.',
                'errors' => $validator->errors(),
            ], 422);
        }

        if (!$this->userId()) {
            return $this->unauthenticated();
        }

        $provider = $this->providerFromRequest($request);

        return $this->serviceResponse(
            $this->cardService->providerCards(
                provider: $provider,
                filters: $request->except('provider')
            )
        );
    }

    public function providerCustomerCards(Request $request): JsonResponse
    {
        $userId = $this->userId();
        if (!$userId) {
            return $this->unauthenticated();
        }

        $provider = $this->providerFromRequest($request);

        $holder = $this->cardService->getCardHolder(userId: $userId, provider: $provider);
        if (!$holder) {
            return response()->json([
                'success' => false,
                'message' => 'Card holder not found.',
            ], 404);
        }

        $customerId = $holder->getProviderCustomerId($provider);
        if (!$customerId) {
            return response()->json([
                'success' => false,
                'message' => 'Provider customer ID not found.',
            ], 404);
        }

        return $this->serviceResponse(
            $this->cardService->providerCustomerCards(
                provider: $provider,
                customerId: $customerId
            )
        );
    }

    public function providerCard(Request $request, string $cardId): JsonResponse
    {
        $userId = $this->userId();
        if (!$userId) {
            return $this->unauthenticated();
        }

        $provider = $this->providerFromRequest($request);

        $localCard = $this->cardService
            ->getUserCards(userId: $userId, provider: $provider)
            ->firstWhere('card_provider_id', $cardId);

        if (!$localCard) {
            return response()->json([
                'success' => false,
                'message' => 'Card not found.',
            ], 404);
        }

        return $this->serviceResponse(
            $this->cardService->providerCard(provider: $provider, cardId: $cardId)
        );
    }

    /*
    |--------------------------------------------------------------------------
    | FUNDING SOURCES
    |--------------------------------------------------------------------------
    */

    public function fundingSources(Request $request): JsonResponse
    {
        $userId = $this->userId();
        if (!$userId) {
            return $this->unauthenticated();
        }

        $provider = $this->providerFromRequest($request);

        return $this->serviceResponse(
            $this->cardService->getFundingSources(provider: $provider)
        );
    }

    public function fundingSource(Request $request, string $id): JsonResponse
    {
        $userId = $this->userId();
        if (!$userId) {
            return $this->unauthenticated();
        }

        $provider = $this->providerFromRequest($request);

        return $this->serviceResponse(
            $this->cardService->getFundingSource(
                provider: $provider,
                fundingSourceId: $id
            )
        );
    }

    public function createFundingSource(Request $request): JsonResponse
    {
        $validator = Validator::make($request->all(), [
            'provider' => ['nullable', 'string', 'in:sudo'],
            'type' => ['required', 'string'],
            'accountNumber' => ['nullable', 'string'],
            'bankCode' => ['nullable', 'string'],
            'accountName' => ['nullable', 'string'],
            'metadata' => ['nullable', 'array'],
        ]);

        if ($validator->fails()) {
            return response()->json([
                'success' => false,
                'message' => 'Validation failed.',
                'errors' => $validator->errors(),
            ], 422);
        }

        $userId = $this->userId();
        if (!$userId) {
            return $this->unauthenticated();
        }

        $provider = $this->providerFromRequest($request);

        return $this->serviceResponse(
            $this->cardService->createFundingSource(
                provider: $provider,
                payload: $request->except('provider')
            ),
            201
        );
    }

    public function updateFundingSource(Request $request, string $id): JsonResponse
    {
        $validator = Validator::make($request->all(), [
            'provider' => ['nullable', 'string', 'in:sudo'],
            'status' => ['nullable', 'string'],
            'name' => ['nullable', 'string'],
            'metadata' => ['nullable', 'array'],
        ]);

        if ($validator->fails()) {
            return response()->json([
                'success' => false,
                'message' => 'Validation failed.',
                'errors' => $validator->errors(),
            ], 422);
        }

        $userId = $this->userId();
        if (!$userId) {
            return $this->unauthenticated();
        }

        $provider = $this->providerFromRequest($request);

        return $this->serviceResponse(
            $this->cardService->updateFundingSource(
                provider: $provider,
                fundingSourceId: $id,
                payload: $request->except('provider')
            )
        );
    }

    /*
    |--------------------------------------------------------------------------
    | ACCOUNTS / WALLETS
    |--------------------------------------------------------------------------
    */

    public function accounts(Request $request): JsonResponse
    {
        $userId = $this->userId();
        if (!$userId) {
            return $this->unauthenticated();
        }

        $provider = $this->providerFromRequest($request);

        return $this->serviceResponse(
            $this->cardService->getAccounts(
                provider: $provider,
                filters: $request->except('provider')
            )
        );
    }

    public function account(Request $request, string $id): JsonResponse
    {
        $userId = $this->userId();
        if (!$userId) {
            return $this->unauthenticated();
        }

        $provider = $this->providerFromRequest($request);

        return $this->serviceResponse(
            $this->cardService->getAccount(provider: $provider, accountId: $id)
        );
    }

    public function accountBalance(Request $request, string $id): JsonResponse
    {
        $userId = $this->userId();
        if (!$userId) {
            return $this->unauthenticated();
        }

        $provider = $this->providerFromRequest($request);

        return $this->serviceResponse(
            $this->cardService->getAccountBalance(provider: $provider, accountId: $id)
        );
    }

    public function accountTransactions(Request $request, string $id): JsonResponse
    {
        $validator = Validator::make($request->all(), [
            'provider' => ['nullable', 'string', 'in:sudo'],
            'page' => ['nullable', 'integer', 'min:1'],
            'limit' => ['nullable', 'integer', 'min:1', 'max:100'],
            'from' => ['nullable', 'date'],
            'to' => ['nullable', 'date', 'after_or_equal:from'],
            'status' => ['nullable', 'string'],
           
        ]);

        if ($validator->fails()) {
            return response()->json([
                'success' => false,
                'message' => 'Validation failed.',
                'errors' => $validator->errors(),
            ], 422);
        }

        $userId = $this->userId();
        if (!$userId) {
            return $this->unauthenticated();
        }

        $provider = $this->providerFromRequest($request);

        return $this->serviceResponse(
            $this->cardService->getAccountTransactions(
                provider: $provider,
                accountId: $id,
                filters: $request->except('provider')
            )
        );
    }

    /*
    |--------------------------------------------------------------------------
    | BANKS
    |--------------------------------------------------------------------------
    */

    public function banks(Request $request): JsonResponse
    {
        $validator = Validator::make($request->all(), [
            'provider' => ['nullable', 'string', 'in:sudo'],
            'search' => ['nullable', 'string', 'max:100'],
            'country' => ['nullable', 'string', 'max:10'],
            'currency' => ['nullable', 'string', 'max:10'],
        ]);

        if ($validator->fails()) {
            return response()->json([
                'success' => false,
                'message' => 'Validation failed.',
                'errors' => $validator->errors(),
            ], 422);
        }

        if (!$this->userId()) {
            return $this->unauthenticated();
        }

        $provider = $this->providerFromRequest($request);

        return $this->serviceResponse(
            $this->cardService->getBanks(
                provider: $provider,
                filters: $request->except('provider')
            )
        );
    }

    /*
    |--------------------------------------------------------------------------
    | NAME ENQUIRY
    |--------------------------------------------------------------------------
    */

    public function nameEnquiry(Request $request): JsonResponse
    {
        $validator = Validator::make($request->all(), [
            'provider' => ['nullable', 'string', 'in:sudo'],
            'accountNumber' => ['required', 'string', 'max:50'],
            'bankCode' => ['required', 'string', 'max:20'],
            'country' => ['nullable', 'string', 'max:10'],
        ]);

        if ($validator->fails()) {
            return response()->json([
                'success' => false,
                'message' => 'Validation failed.',
                'errors' => $validator->errors(),
            ], 422);
        }
        if (!$this->userId()) {
            return $this->unauthenticated();
        }

        $provider = $this->providerFromRequest($request);

        return $this->serviceResponse(
            $this->cardService->nameEnquiry(
                provider: $provider,
                payload: $request->except('provider')
            )
        );
    }




    public function getTransfer(Request $request, string $id): JsonResponse
    {
        $userId = $this->userId();
        if (!$userId) {
            return $this->unauthenticated();
        }

        $provider = $this->providerFromRequest($request);

        return $this->serviceResponse(
            $this->cardService->getTransfer(provider: $provider, transferId: $id)
        );
    }

    public function transferRate(Request $request): JsonResponse
    {
        $validator = Validator::make($request->all(), [
            'provider' => ['nullable', 'string', 'in:sudo'],
            'currencyPair' => ['required', 'string', 'max:30'],
        ]);

        if ($validator->fails()) {
            return response()->json([
                'success' => false,
                'message' => 'Validation failed.',
                'errors' => $validator->errors(),
            ], 422);
        }

        if (!$this->userId()) {
            return $this->unauthenticated();
        }

        $provider = $this->providerFromRequest($request);

        return $this->serviceResponse(
            $this->cardService->getTransferRate(
                provider: $provider,
                currencyPair: $request->input('currencyPair')
            )
        );
    }

    /*
    |--------------------------------------------------------------------------
    | SANDBOX
    |--------------------------------------------------------------------------
    */

    public function fundSandboxAccount(Request $request): JsonResponse
    {
        $validator = Validator::make($request->all(), [
            'provider' => ['nullable', 'string', 'in:sudo'],
            'accountId' => ['required', 'string'],
            'amount' => ['required', 'numeric', 'gt:0'],
            'currency' => ['required', 'string', 'max:10'],
        ]);

        if ($validator->fails()) {
            return response()->json([
                'success' => false,
                'message' => 'Validation failed.',
                'errors' => $validator->errors(),
            ], 422);
        }

        if (!$this->userId()) {
            return $this->unauthenticated();
        }

        $provider = $this->providerFromRequest($request);

        return $this->serviceResponse(
            $this->cardService->fundSandboxAccount(
                provider: $provider,
                payload: $request->except('provider')
            )
        );
    }

    /*
    |--------------------------------------------------------------------------
    | LOCAL CARD TRANSACTIONS
    |--------------------------------------------------------------------------
    */

    public function transactions(Request $request, $id): JsonResponse
    {
        
        $validator = Validator::make($request->all(), [
            'status' => ['nullable', 'string'],
            'from' => ['nullable', 'date'],
            'to' => ['nullable', 'date', 'after_or_equal:from'],
            'per_page' => ['nullable', 'integer', 'min:1', 'max:100'],
        ]);
        

        if ($validator->fails()) {
            return response()->json([
                'success' => false,
                'message' => 'Validation failed.',
                'errors' => $validator->errors(),
            ], 422);
        }

        $userId = $this->userId();
        if (!$userId) {
            return $this->unauthenticated();
        }
        $id = (int)$id;
        $card = $this->cardService->getCard(userId: $userId, cardId: $id);
        if (!$card) {
            return response()->json([
                'success' => false,
                'message' => 'Card not found.',
            ], 404);
        }

        $transactions = $this->cardService->getCardTransactions(
            userId: $userId,
            cardId: $id,
            filters: $request->only(['status', 'from', 'to', 'per_page'])
        );

        return response()->json([
            'success' => true,
            'data' => $transactions,
        ]);
    }

    public function syncTransactions(int $id): JsonResponse
    {
        $userId = $this->userId();
        if (!$userId) {
            return $this->unauthenticated();
        }

        return $this->serviceResponse(
            $this->cardService->syncCardTransactions(userId: $userId, cardId: $id)
        );
    }

    /*
    |--------------------------------------------------------------------------
    | PROVIDER TRANSACTIONS
    |--------------------------------------------------------------------------
    */

    public function providerTransactions(Request $request): JsonResponse
    {
        $validator = Validator::make($request->all(), [
            'provider' => ['nullable', 'string', 'in:sudo'],
            'page' => ['nullable', 'integer', 'min:1'],
            'limit' => ['nullable', 'integer', 'min:1', 'max:100'],
            'status' => ['nullable', 'string'],
            'from' => ['nullable', 'date'],
            'to' => ['nullable', 'date', 'after_or_equal:from'],
        ]);

        if ($validator->fails()) {
            return response()->json([
                'success' => false,
                'message' => 'Validation failed.',
                'errors' => $validator->errors(),
            ], 422);
        }

        if (!$this->userId()) {
            return $this->unauthenticated();
        }

        $provider = $this->providerFromRequest($request);

        return $this->serviceResponse(
            $this->cardService->providerTransactions(
                provider: $provider,
                filters: $request->except('provider')
            )
        );
    }

    public function providerTransaction(Request $request, string $id): JsonResponse
    {
        $userId = $this->userId();
        if (!$userId) {
            return $this->unauthenticated();
        }

        $provider = $this->providerFromRequest($request);

        return $this->serviceResponse(
            $this->cardService->providerTransaction(
                provider: $provider,
                transactionId: $id
            )
        );
    }

    public function updateProviderTransaction(Request $request, string $id): JsonResponse
    {
        $validator = Validator::make($request->all(), [
            'provider' => ['nullable', 'string', 'in:sudo'],
            'status' => ['nullable', 'string'],
            'metadata' => ['nullable', 'array'],
        ]);

        if ($validator->fails()) {
            return response()->json([
                'success' => false,
                'message' => 'Validation failed.',
                'errors' => $validator->errors(),
            ], 422);
        }

        if (!$this->userId()) {
            return $this->unauthenticated();
        }

        $provider = $this->providerFromRequest($request);

        return $this->serviceResponse(
            $this->cardService->updateProviderTransaction(
                provider: $provider,
                transactionId: $id,
                payload: $request->except('provider')
            )
        );
    }

    /*
    |--------------------------------------------------------------------------
    | PROVIDERS
    |--------------------------------------------------------------------------
    */

    public function providers(): JsonResponse
    {
        if (!$this->userId()) {
            return $this->unauthenticated();
        }

        return response()->json([
            'success' => true,
            'data' => $this->cardService->supportedProviders(),
        ]);
    }

    public function providerSupported(string $provider): JsonResponse
    {
        if (!$this->userId()) {
            return $this->unauthenticated();
        }

        $provider = strtolower(trim($provider));

        return response()->json([
            'success' => true,
            'provider' => $provider,
            'supported' => $this->cardService->supports($provider),
        ]);
    }

    /*
    |--------------------------------------------------------------------------
    | PROVIDER CALL
    |--------------------------------------------------------------------------
    */

    public function providerCall(Request $request): JsonResponse
    {
        $validator = Validator::make($request->all(), [
            'provider' => ['required', 'string', 'in:sudo'],
            'method' => ['required', 'string', 'max:100', 'regex:/^[A-Za-z_][A-Za-z0-9_]*$/'],
            'arguments' => ['nullable', 'array'],
        ]);

        if ($validator->fails()) {
            return response()->json([
                'success' => false,
                'message' => 'Validation failed.',
                'errors' => $validator->errors(),
            ], 422);
        }

        $userId = $this->userId();
        if (!$userId) {
            return $this->unauthenticated();
        }

        $provider = strtolower(trim((string) $request->input('provider')));

        $allowedMethods = [
            'getCardTransactions',
            'getCard',
            'getCards',
            'getCustomerCards',
            'getFundingSources',
            'getFundingSource',
            'getAccount',
            'getAccountBalance',
            'getAccountTransactions',
            'getBanks',
            'getTransfer',
            'getTransferRate',
            'getTransaction',
            'getTransactions',
        ];

        $method = $request->input('method');

        if (!in_array($method, $allowedMethods, true)) {
            return response()->json([
                'success' => false,
                'message' => 'Provider method is not available through this endpoint.',
            ], 403);
        }

        $arguments = $request->input('arguments', []);
        if (!is_array($arguments)) {
            $arguments = [];
        }

        if (in_array($method, ['getCard', 'getCardTransactions'], true)) {
            $providerCardId = $arguments[0] ?? null;

            if (!$providerCardId) {
                return response()->json([
                    'success' => false,
                    'message' => 'Provider card ID is required.',
                ], 422);
            }

            $localCard = $this->cardService
                ->getUserCards(userId: $userId, provider: $provider)
                ->firstWhere('card_provider_id', $providerCardId);

            if (!$localCard) {
                return response()->json([
                    'success' => false,
                    'message' => 'Card does not belong to the authenticated user.',
                ], 403);
            }
        }

        if ($method === 'getCustomerCards') {
            $holder = $this->cardService->getCardHolder(userId: $userId, provider: $provider);
            if (!$holder) {
                return response()->json([
                    'success' => false,
                    'message' => 'Card holder not found.',
                ], 404);
            }

            $customerId = $holder->getProviderCustomerId($provider);
            if (!$customerId) {
                return response()->json([
                    'success' => false,
                    'message' => 'Provider customer ID not found.',
                ], 404);
            }

            $arguments = [$customerId];
        }

        return $this->serviceResponse(
            $this->cardService->call(
                provider: $provider,
                method: $method,
                arguments: $arguments
            )
        );
    }

    /*
    |--------------------------------------------------------------------------
    | CARD FUNDING
    |--------------------------------------------------------------------------
    */

    /**
     * Fund a crypto card (top-up).
     * POST /api/v1/user/crypto-cards/{id}/fund
     */


public function fund(Request $request, int $id): JsonResponse
{
    $validator = Validator::make($request->all(), [
        'amount' => ['required', 'numeric', 'gt:0', 'max:10000000'],
        'description' => ['nullable', 'string', 'max:255'],
        'channel' => ['nullable', 'string', 'in:platform,wallet,bank_transfer,card'],
        'metadata' => ['nullable', 'array'],
    ], [
        'amount.required' => 'Amount is required.',
        'amount.gt' => 'Amount must be greater than zero.',
    ]);

    if ($validator->fails()) {
        return response()->json([
            'success' => false,
            'message' => 'Validation failed.',
            'errors' => $validator->errors(),
        ], 422);
    }

    $userId = $this->userId();

    if (!$userId) {
        return $this->unauthenticated();
    }

    /*
    |--------------------------------------------------------------------------
    | Transfer configured card funding fee based on card type
    | (This already records the charge in CryptoTransactionService)
    |--------------------------------------------------------------------------
    */

    $cardRecord = \App\Models\CryptoCard::find($id);
    $fundingChargeType = ($cardRecord && $cardRecord->type === 'physical')
        ? 'physical_card_funding_fee'
        : 'virtual_card_funding_fee';
    $charge = $this->transferCardCharge($fundingChargeType);

    if (!$charge['success']) {
        return $this->serviceResponse($charge);
    }

    /*
    |--------------------------------------------------------------------------
    | Generate funding reference
    | Always exactly 30 characters.
    | Format: BM + user ID + timestamp + random characters.
    |--------------------------------------------------------------------------
    */

    $reference = strtoupper(
        'BM' .
        $userId .
        now()->format('ymdHis') .
        Str::random(30)
    );

    $reference = substr($reference, 0, 30);

    /*
    |--------------------------------------------------------------------------
    | Fund card
    |--------------------------------------------------------------------------
    */

    $amount = (float) $request->input('amount');

    $result = $this->cardService->fundCard(
        userId: $userId,
        cardId: $id,
        amount: $amount,
        context: [
            'description'      => $request->input('description', 'Card funding'),
            'reference'        => $reference,
            'channel'          => $request->input('channel', 'platform'),
            'metadata'         => $request->input('metadata'),
            'master_reference' => $reference,
        ]
    );

    /*
    |--------------------------------------------------------------------------
    | Save funding transaction
    |--------------------------------------------------------------------------
    */

    if (($result['success'] ?? false) === true) {
        try {
            $this->cryptoTransactionService->create([
                'internal_trx_type'   => 'card_funding',
                'internal_trx_ref_id' => $id,                    // the card id
                'transaction_type'    => 'credit',               // money going onto the card
                'amount'              => $amount,
                'asset'               => $result['data']['currency'] 
                                          ?? $result['data']['asset'] 
                                          ?? 'USDT',
                'status'              => $result['data']['status'] ?? 'success',
                'txn_hash'            => $result['data']['txId'] 
                                          ?? $result['data']['txn_hash'] 
                                          ?? null,
                'callback_response'   => $result,
            ]);
        } catch (Throwable $e) {
            Log::error('Failed to save card funding transaction', [
                'user_id'   => $userId,
                'card_id'   => $id,
                'reference' => $reference,
                'amount'    => $amount,
                'error'     => $e->getMessage(),
            ]);
            // We still return the original funding result
        }
    }

    return $this->serviceResponse($result);
}


public function orderCard(Request $request): JsonResponse
{
    /*
     * Normalize case-insensitive fields before validation.
     */
    $brand = strtolower(trim((string) $request->input('brand')));
    $shippingMethod = strtoupper(trim((string) $request->input('shippingMethod')));
    $currency = strtoupper(trim((string) $request->input('currency')));

    $normalizedBrand = match ($brand) {
        'verve' => 'Verve',
        'afrigo' => 'AfriGo',
        'mastercard' => 'MasterCard',
        'visa' => 'Visa',
        default => $request->input('brand'),
    };

    $request->merge([
        'brand' => $normalizedBrand,
        'shippingMethod' => $shippingMethod,
        'currency' => $currency,
    ]);

    $validator = Validator::make($request->all(), [
        'provider' => ['nullable', 'string', 'in:sudo'],
        'type' => ['required', 'string', 'in:physical'],
        'currency' => ['required', 'string', 'in:NGN,USD'],
        'cardId' => ['nullable', 'string'],
        'allocation' => ['required', 'integer', 'min:1'],
        'expedite' => ['required', 'boolean'],
        'shippingMethod' => ['required', 'string', 'in:NIPOST,DHL'],
        'shippingAddress' => ['required', 'array'],
        'shippingAddress.line1' => ['required', 'string'],
        'shippingAddress.city' => ['required', 'string'],
        'shippingAddress.state' => ['required', 'string'],
        'shippingAddress.postalCode' => ['required', 'string'],
        'shippingAddress.country' => ['required', 'string'],
        'shippingAddress.line2' => ['nullable', 'string'],
        'design' => ['nullable', 'string'],
        'nameOnCards' => ['required', 'array', 'min:1'],
        'nameOnCards.*' => ['required', 'string'],
        'brand' => ['required', 'string', 'in:Verve,AfriGo,MasterCard,Visa'],
        'metadata' => ['nullable', 'array'],
    ]);

    if ($validator->fails()) {
        return response()->json([
            'success' => false,
            'message' => 'Validation failed.',
            'errors' => $validator->errors(),
        ], 422);
    }

    $userId = $this->userId();

    if (!$userId) {
        return $this->unauthenticated();
    }

    /*
    |--------------------------------------------------------------------------
    | Charge physical card fee FIRST
    | (This already records the charge in CryptoTransactionService)
    |--------------------------------------------------------------------------
    */

    $charge = $this->transferCardCharge('physical_card_fee');

    if (!$charge['success']) {
        return $this->serviceResponse($charge);
    }

    $provider = $this->providerFromRequest($request);

    $holder = $this->cardService->getCardHolder(
        userId: $userId,
        provider: $provider
    );

    if (!$holder) {
        return response()->json([
            'success' => false,
            'message' => 'Card holder not found.',
        ], 404);
    }

    $reference = 'CCO-' . strtoupper(Str::random(24));

    $allocation = $request->integer('allocation');
    $expedite = $request->boolean('expedite');
    $shippingAddress = $request->input('shippingAddress');
    $design = $request->input('design', 'SudoBlack');
    $nameOnCards = $request->input('nameOnCards');
    $brand = $request->input('brand');
    $currency = $request->input('currency');
    $shippingMethod = $request->input('shippingMethod');

    /*
     * Only provider-specific fields are sent to CardService.
     *
     * customerId is resolved internally from the card holder.
     * debitAccountId is resolved internally by SudoCardService.
     */
    $payload = [
        'currency' => $currency,
        'allocation' => $allocation,
        'expedite' => $expedite,
        'shippingMethod' => $shippingMethod,
        'shippingAddress' => $shippingAddress,
        'design' => $design,
        'nameOnCards' => $nameOnCards,
        'brand' => $brand,
    ];

    /*
     * Create the local order before calling the provider so that
     * the complete request is persisted even if the provider fails.
     */
    $order = CryptoCardOrder::create([
        'user_id' => $userId,
        'card_holder_id' => $holder->id,
        'card_id' => $request->input('cardId'),
        'provider' => $provider,
        'reference' => $reference,
        'provider_order_id' => null,
        'order_type' => $request->input('type'),
        'brand' => $brand,
        'currency' => $currency,
        'allocation' => $allocation,
        'expedite' => $expedite,
        'shipping_method' => $shippingMethod,
        'shipping_address' => $shippingAddress,
        'design' => $design,
        'name_on_cards' => $nameOnCards,
        'amount' => null,
        'fee' => null,
        'total_amount' => null,
        'status' => 'pending',
        'metadata' => $request->input('metadata'),
        'provider_data' => null,
        'ordered_at' => now(),
    ]);

    try {
        Log::info('Crypto card order initiated.', [
            'user_id' => $userId,
            'order_id' => $order->id,
            'reference' => $order->reference,
            'provider' => $provider,
            'payload' => [
                'currency' => $currency,
                'allocation' => $allocation,
                'expedite' => $expedite,
                'shippingMethod' => $shippingMethod,
                'shippingAddress' => $shippingAddress,
                'design' => $design,
                'nameOnCards' => $nameOnCards,
                'brand' => $brand,
            ],
        ]);

        $result = $this->cardService->orderCard(
            userId: $userId,
            provider: $provider,
            payload: $payload
        );

        if (!($result['success'] ?? false)) {
            $order->update([
                'status' => 'failed',
                'provider_data' => is_array($result)
                    ? $result
                    : ['response' => $result],
                'cancelled_at' => now(),
            ]);

            Log::warning('Crypto card order rejected by provider.', [
                'user_id' => $userId,
                'order_id' => $order->id,
                'reference' => $order->reference,
                'provider' => $provider,
                'status_code' => $result['statusCode'] ?? null,
                'message' => $result['message'] ?? null,
            ]);

            return response()->json([
                'success' => false,
                'message' => $result['message']
                    ?? 'Unable to submit card order.',
                'data' => [
                    'id' => $order->id,
                    'reference' => $order->reference,
                    'status' => $order->status,
                ],
            ], $result['statusCode'] ?? 422);
        }

        /*
         * Sudo /cards/order response:
         *
         * data.batchReference
         * data.allocation
         * data.brand
         * data.status
         *
         * batchReference is stored locally as provider_order_id.
         */
        $providerOrderId = data_get(
            $result,
            'data.batchReference'
        ) ?? data_get(
            $result,
            'batchReference'
        );

        $providerAllocation = data_get(
            $result,
            'data.allocation'
        );

        $providerBrand = data_get(
            $result,
            'data.brand'
        );

        $status = data_get(
            $result,
            'data.status'
        ) ?? data_get(
            $result,
            'status'
        ) ?? 'pending';

        $normalizedStatus = strtolower(
            trim((string) $status)
        );

        $completedAt = in_array(
            $normalizedStatus,
            ['completed', 'success', 'successful'],
            true
        ) ? now() : null;

        $cancelledAt = in_array(
            $normalizedStatus,
            ['cancelled', 'canceled', 'failed'],
            true
        ) ? now() : null;

        $order->update([
            'provider_order_id' => $providerOrderId,
            'allocation' => $providerAllocation ?? $allocation,
            'brand' => $providerBrand ?? $brand,
            'status' => $status,
            'provider_data' => is_array($result)
                ? $result
                : ['response' => $result],
            'completed_at' => $completedAt,
            'cancelled_at' => $cancelledAt,
        ]);

        $order->refresh();

        Log::info('Crypto card order submitted successfully.', [
            'user_id' => $userId,
            'order_id' => $order->id,
            'reference' => $order->reference,
            'provider' => $provider,
            'provider_order_id' => $providerOrderId,
            'status' => $order->status,
            'allocation' => $order->allocation,
            'brand' => $order->brand,
        ]);

        return response()->json([
            'success' => true,
            'message' => 'Card order submitted successfully.',
            'data' => [
                'id' => $order->id,
                'reference' => $order->reference,
                'provider_order_id' => $order->provider_order_id,
                'provider' => $order->provider,
                'order_type' => $order->order_type,
                'brand' => $order->brand,
                'currency' => $order->currency,
                'allocation' => $order->allocation,
                'expedite' => $order->expedite,
                'shipping_method' => $order->shipping_method,
                'shipping_address' => $order->shipping_address,
                'design' => $order->design,
                'name_on_cards' => $order->name_on_cards,
                'amount' => $order->amount,
                'fee' => $order->fee,
                'total_amount' => $order->total_amount,
                'status' => $order->status,
                'ordered_at' => $order->ordered_at,
                'created_at' => $order->created_at,
            ],
        ], 201);
    } catch (Throwable $e) {
        $order->update([
            'status' => 'failed',
            'provider_data' => [
                'error' => $e->getMessage(),
                'exception' => get_class($e),
            ],
            'cancelled_at' => now(),
        ]);

        Log::error('Crypto card order failed.', [
            'user_id' => $userId,
            'order_id' => $order->id,
            'reference' => $order->reference,
            'provider' => $provider,
            'error' => $e->getMessage(),
            'exception' => get_class($e),
        ]);

        return response()->json([
            'success' => false,
            'message' => 'Unable to submit card order.',
            'data' => [
                'id' => $order->id,
                'reference' => $order->reference,
                'status' => $order->status,
            ],
        ], 502);
    }
}

    /*
    |--------------------------------------------------------------------------
    | TRANSFERS
    |--------------------------------------------------------------------------
    */
public function transfer(Request $request): JsonResponse
{
    $validator = Validator::make($request->all(), [
        'provider' => [
            'required',
            'string',
            'in:sudo',
        ],

        'card_id' => [
            'required',
            'integer',
        ],

        'amount' => [
            'required',
            'numeric',
            'gt:0',
        ],

        'currency' => [
            'required',
            'string',
            'size:3',
        ],

        'destination' => [
            'required',
            'array',
        ],

        'destination.account_number' => [
            'required',
            'string',
        ],

        'destination.bank_code' => [
            'required',
            'string',
        ],

        'destination.accountName' => [
            'nullable',
            'string',
        ],

        'narration' => [
            'nullable',
            'string',
            'max:255',
        ],

        'metadata' => [
            'nullable',
            'array',
        ],
    ]);

    if ($validator->fails()) {
        return response()->json([
            'success' => false,
            'message' => 'Validation failed.',
            'errors' => $validator->errors(),
        ], 422);
    }

    $userId = $this->userId();

    if (!$userId) {
        return $this->unauthenticated();
    }

    $provider = $this->providerFromRequest($request);

    $payload = [
        'amount' => (float) $request->input('amount'),

        'currency' => strtoupper(
            $request->input('currency')
        ),

        'destination' => [
            'account_number' => $request->input(
                'destination.account_number'
            ),

            'bank_code' => $request->input(
                'destination.bank_code'
            ),

            'accountName' => $request->input(
                'destination.accountName'
            ),
        ],

        'narration' => $request->input(
            'narration',
            'Crypto card transfer'
        ),

        'metadata' => $request->input('metadata'),
    ];

    return $this->serviceResponse(
        $this->cardService->transfer(
            userId: $userId,
            cardId: $request->input('card_id'),
            provider: $provider,
            payload: $payload
        ),
        201
    );
}


    /**
     * Get all configured crypto card charges before creation/order.
     * Includes physical and virtual card fees (issuance/order, funding, and monthly maintenance).
     * GET /api/v1/user/crypto-cards/charges
     */
    public function charges(): JsonResponse
    {
        $setup = CryptoCardSetup::query()->first();

        $virtualIssuanceFee = $setup ? number_format($setup->virtual_issuance_fee, 2, '.', '') : '0.00';
        $virtualFundingFee = $setup ? number_format($setup->virtual_funding_fee, 2, '.', '') : '0.00';
        $virtualMaintenanceFee = $setup ? number_format($setup->virtual_maintenance_fee, 2, '.', '') : '0.00';

        $physicalOrderFee = $setup ? number_format($setup->physical_order_fee, 2, '.', '') : '0.00';
        $physicalFundingFee = $setup ? number_format($setup->physical_funding_fee, 2, '.', '') : '0.00';
        $physicalMaintenanceFee = $setup ? number_format($setup->physical_maintenance_fee, 2, '.', '') : '0.00';

        return response()->json([
            'success' => true,
            'message' => 'Crypto card charges retrieved successfully.',
            'data' => [
                'currency' => 'USDT',
                'virtual_card' => [
                    'card_issuance_fee' => $virtualIssuanceFee,
                    'card_funding_fee' => $virtualFundingFee,
                    'monthly_card_maintenance_fee' => $virtualMaintenanceFee,
                ],
                'physical_card' => [
                    'physical_card_fee' => $physicalOrderFee,
                    'card_funding_fee' => $physicalFundingFee,
                    'monthly_card_maintenance_fee' => $physicalMaintenanceFee,
                ],
                // Legacy top-level keys for backward compatibility
                'physical_card_fee' => $physicalOrderFee,
                'card_issuance_fee' => $virtualIssuanceFee,
                'monthly_card_maintenance_fee' => $virtualMaintenanceFee,
                'card_funding_fee' => $virtualFundingFee,
                'charges' => [
                    [
                        'key' => 'virtual_card_issuance_fee',
                        'name' => 'Virtual Card Issuance Fee',
                        'amount' => $virtualIssuanceFee,
                        'currency' => 'USDT',
                        'description' => 'Fee charged upon issuing a virtual crypto card.',
                    ],
                    [
                        'key' => 'virtual_card_funding_fee',
                        'name' => 'Virtual Card Funding Fee',
                        'amount' => $virtualFundingFee,
                        'currency' => 'USDT',
                        'description' => 'Fee charged when topping up or funding a virtual crypto card.',
                    ],
                    [
                        'key' => 'virtual_monthly_card_maintenance_fee',
                        'name' => 'Virtual Card Monthly Maintenance Fee',
                        'amount' => $virtualMaintenanceFee,
                        'currency' => 'USDT',
                        'description' => 'Monthly fee charged for virtual crypto card maintenance.',
                    ],
                    [
                        'key' => 'physical_card_fee',
                        'name' => 'Physical Card Order Fee',
                        'amount' => $physicalOrderFee,
                        'currency' => 'USDT',
                        'description' => 'Fee charged when ordering or issuing a physical crypto card.',
                    ],
                    [
                        'key' => 'physical_card_funding_fee',
                        'name' => 'Physical Card Funding Fee',
                        'amount' => $physicalFundingFee,
                        'currency' => 'USDT',
                        'description' => 'Fee charged when topping up or funding a physical crypto card.',
                    ],
                    [
                        'key' => 'physical_monthly_card_maintenance_fee',
                        'name' => 'Physical Card Monthly Maintenance Fee',
                        'amount' => $physicalMaintenanceFee,
                        'currency' => 'USDT',
                        'description' => 'Monthly fee charged for physical crypto card maintenance.',
                    ],
                ],
            ],
        ]);
    }

    public function getCharge(string $charge): JsonResponse
    {
        $validator = Validator::make(
            ['charge' => $charge],
            [
                'charge' => [
                    'required',
                    'string',
                    Rule::in([
                        'physical_card_fee',
                        'physical_card_funding_fee',
                        'physical_monthly_card_maintenance_fee',
                        'virtual_card_issuance_fee',
                        'virtual_card_funding_fee',
                        'virtual_monthly_card_maintenance_fee',
                        'card_issuance_fee',
                        'card_funding_fee',
                        'monthly_card_maintenance_fee',
                    ]),
                ],
            ]
        );

        if ($validator->fails()) {
            return response()->json([
                'success' => false,
                'message' => 'Invalid charge type.',
                'errors' => $validator->errors(),
            ], 422);
        }

        $setup = CryptoCardSetup::query()->first() ?: new CryptoCardSetup();

        $amount = match ($charge) {
            'virtual_card_issuance_fee', 'card_issuance_fee' => $setup->virtual_issuance_fee,
            'virtual_card_funding_fee', 'card_funding_fee' => $setup->virtual_funding_fee,
            'virtual_monthly_card_maintenance_fee', 'monthly_card_maintenance_fee' => $setup->virtual_maintenance_fee,
            'physical_card_fee' => $setup->physical_order_fee,
            'physical_card_funding_fee' => $setup->physical_funding_fee,
            'physical_monthly_card_maintenance_fee' => $setup->physical_maintenance_fee,
            default => (float) ($setup->{$charge} ?? 0),
        };

        return response()->json([
            'success' => true,
            'charge_type' => $charge,
            'amount' => number_format((float) $amount, 2, '.', ''),
        ]);
    }

    /**
     * Transfer a configured crypto-card charge from the user's
     * fiat (NGN) account to the platform.
     */
    protected function transferCardCharge(string $chargeType): array
    {
        $user = auth()->user();

        if (!$user) {
            return [
                'success' => false,
                'message' => 'Unauthenticated.',
                'statusCode' => 401,
            ];
        }

        $allowedCharges = [
            'physical_card_fee',
            'physical_card_funding_fee',
            'physical_monthly_card_maintenance_fee',
            'virtual_card_issuance_fee',
            'virtual_card_funding_fee',
            'virtual_monthly_card_maintenance_fee',
            'card_issuance_fee',
            'card_funding_fee',
            'monthly_card_maintenance_fee',
        ];

        if (!in_array($chargeType, $allowedCharges, true)) {
            return [
                'success' => false,
                'message' => 'Invalid crypto card charge type.',
                'statusCode' => 422,
            ];
        }

        $setup = CryptoCardSetup::query()->first() ?: new CryptoCardSetup();

        $amount = match ($chargeType) {
            'virtual_card_issuance_fee', 'card_issuance_fee' => $setup->virtual_issuance_fee,
            'virtual_card_funding_fee', 'card_funding_fee' => $setup->virtual_funding_fee,
            'virtual_monthly_card_maintenance_fee', 'monthly_card_maintenance_fee' => $setup->virtual_maintenance_fee,
            'physical_card_fee' => $setup->physical_order_fee,
            'physical_card_funding_fee' => $setup->physical_funding_fee,
            'physical_monthly_card_maintenance_fee' => $setup->physical_maintenance_fee,
            default => (float) ($setup->{$chargeType} ?? 0),
        };

        if (!is_numeric($amount)) {
            return [
                'success' => false,
                'message' => 'Invalid configured crypto card charge.',
                'statusCode' => 422,
            ];
        }

        $amount = number_format((float) $amount, 2, '.', '');

        /*
         * No charge configured.
         */
        if (bccomp($amount, '0', 2) <= 0) {
            return [
                'success' => true,
                'charged' => false,
                'amount' => '0.00',
                'ngn_amount' => '0.00',
                'currency' => 'NGN',
                'charge_type' => $chargeType,
                'reference' => null,
                'response' => null,
                'message' => 'No charge configured.',
            ];
        }

        $reference = 'CARD-' .
            strtoupper($chargeType) .
            '-' .
            Str::uuid()->toString();

        $note = match ($chargeType) {
            'physical_card_fee' =>
                'Crypto physical card order/issuance fee',

            'physical_card_funding_fee' =>
                'Crypto physical card funding fee',

            'physical_monthly_card_maintenance_fee' =>
                'Crypto physical card monthly maintenance fee',

            'virtual_card_issuance_fee', 'card_issuance_fee' =>
                'Crypto virtual card issuance fee',

            'virtual_card_funding_fee', 'card_funding_fee' =>
                'Crypto virtual card funding fee',

            'virtual_monthly_card_maintenance_fee', 'monthly_card_maintenance_fee' =>
                'Crypto virtual card monthly maintenance fee',

            default =>
                'Crypto card charge',
        };

    // Calculate NGN fee using exchange rate
    $ngnCurrency = Currency::where('code', 'NGN')->first();
    $rate = (float) ($ngnCurrency?->rate ?? 1500);
    $ngnAmount = bcmul((string) $amount, (string) $rate, 2);

    try {
        $result = DB::transaction(function () use ($user, $ngnAmount, $amount, $chargeType, $reference, $note) {
            $wallet = UserWallet::where('user_id', $user->id)
                ->where('currency_code', 'NGN')
                ->lockForUpdate()
                ->first();

            if (!$wallet) {
                throw new \RuntimeException('NGN fiat wallet not found for user.');
            }

            if (bccomp((string) $wallet->balance, (string) $ngnAmount, 2) < 0) {
                throw new \RuntimeException("Insufficient fiat balance to cover the card fee. Required: ₦" . number_format((float)$ngnAmount, 2) . ", Available: ₦" . number_format((float)$wallet->balance, 2));
            }

            $wallet->balance = bcsub((string) $wallet->balance, (string) $ngnAmount, 2);
            $wallet->save();

            OrderTransaction::create([
                'user_wallet_id' => $wallet->id,
                'type' => 'debit',
                'amount' => $ngnAmount,
                'balance_after' => $wallet->balance,
                'reference' => $reference,
                'metadata' => [
                    'source' => 'crypto_card',
                    'charge_type' => $chargeType,
                    'configured_fee' => $amount,
                    'ngn_amount' => $ngnAmount,
                    'note' => $note,
                ],
            ]);

            return [
                'wallet' => $wallet,
                'ngn_amount' => $ngnAmount,
            ];
        });

        // Record in CryptoTransactionService for unified history
        try {
            $this->cryptoTransactionService->create([
                'internal_trx_type'   => $chargeType,
                'internal_trx_ref_id' => null,
                'transaction_type'    => 'debit',
                'sender_address'      => 'FIAT_WALLET',
                'receiver_address'    => 'PLATFORM_REVENUE',
                'amount'              => $ngnAmount,
                'asset'               => 'NGN',
                'txn_hash'            => $reference,
                'status'              => 'done',
                'callback_response'   => ['reference' => $reference, 'amount' => $amount, 'ngn_amount' => $ngnAmount],
            ]);
        } catch (Throwable $e) {
            Log::warning('CryptoTransactionService log warning: ' . $e->getMessage());
        }

        Log::info('Crypto card fiat charge deducted successfully.', [
            'user_id'     => $user->id,
            'charge_type' => $chargeType,
            'amount'      => $amount,
            'ngn_amount'  => $ngnAmount,
            'currency'    => 'NGN',
            'reference'   => $reference,
        ]);

        return [
            'success' => true,
            'charged' => true,
            'charge_type' => $chargeType,
            'amount' => $amount,
            'ngn_amount' => $ngnAmount,
            'currency' => 'NGN',
            'reference' => $reference,
            'message' => 'Card charge deducted successfully from fiat account.',
        ];
    } catch (Throwable $e) {
        Log::error('Crypto card fiat charge deduction failed.', [
            'user_id' => $user->id,
            'charge_type' => $chargeType,
            'amount' => $amount,
            'error' => $e->getMessage(),
        ]);

        return [
            'success' => false,
            'message' => $e->getMessage() ?: 'Unable to process crypto card charge from fiat account.',
            'statusCode' => 422,
        ];
    }
}


}