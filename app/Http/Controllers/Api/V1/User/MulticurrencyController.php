<?php

namespace App\Http\Controllers\Api\V1\User;

use App\Http\Controllers\Controller;
use App\Services\MultiCurrency\MultiCurrencyQuoteService;
use App\Services\MultiCurrency\MultiCurrencyWalletService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;
use Throwable;

class MulticurrencyController extends Controller
{
    public function __construct(
        protected MultiCurrencyWalletService $walletService,
        protected MultiCurrencyQuoteService $quoteService
    ) {
    }

    /**
     * Create a multicurrency wallet / local currency account.
     *
     * POST /api/v1/user/multicurrency/wallets
     */
    public function create(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'currency' => [
                'required',
                'string',
                'size:3',
                Rule::in([
                    'NGN',
                    'GHS',
                    'KES',
                    'TZS',
                ]),
            ],

            'account_type' => [
                'nullable',
                'string',
                Rule::in([
                    'individual',
                    'corporate',
                ]),
            ],
        ]);

        try {
            $wallet = $this->walletService->createWallet(
                user: $request->user(),
                currency: strtoupper($validated['currency']),
                accountType: $validated['account_type'] ?? 'individual'
            );

            return response()->json([
                'status' => true,
                'message' => 'Currency wallet created successfully.',
                'data' => $wallet,
            ], 201);

        } catch (Throwable $e) {
            report($e);

            return response()->json([
                'status' => false,
                'message' => 'Unable to create currency wallet.',
            ], 500);
        }
    }

    /**
     * Get all multicurrency wallets belonging to the authenticated user.
     *
     * GET /api/v1/user/multicurrency/wallets
     */
    public function index(Request $request): JsonResponse
    {
        try {
            $wallets = $this->walletService->getUserWallets(
                $request->user()
            );

            return response()->json([
                'status' => true,
                'message' => 'Currency wallets fetched successfully.',
                'data' => $wallets,
            ]);

        } catch (Throwable $e) {
            report($e);

            return response()->json([
                'status' => false,
                'message' => 'Unable to retrieve currency wallets.',
            ], 500);
        }
    }

    /**
     * Get one wallet.
     *
     * GET /api/v1/user/multicurrency/wallets/{currency}
     */
    public function show(
        Request $request,
        string $currency
    ): JsonResponse {
        $currency = strtoupper($currency);

        if (!in_array($currency, [
            'NGN',
            'GHS',
            'KES',
            'TZS',
        ], true)) {
            return response()->json([
                'status' => false,
                'message' => 'Unsupported currency.',
            ], 422);
        }

        try {
            $wallet = $this->walletService->getWallet(
                user: $request->user(),
                currency: $currency
            );

            if (!$wallet) {
                return response()->json([
                    'status' => false,
                    'message' => 'Currency wallet not found.',
                ], 404);
            }

            return response()->json([
                'status' => true,
                'message' => 'Currency wallet fetched successfully.',
                'data' => $wallet,
            ]);

        } catch (Throwable $e) {
            report($e);

            return response()->json([
                'status' => false,
                'message' => 'Unable to retrieve currency wallet.',
            ], 500);
        }
    }

    /**
     * Get collections/deposits for a wallet.
     *
     * GET /api/v1/user/multicurrency/wallets/{currency}/collections
     */
    public function collections(
        Request $request,
        string $currency
    ): JsonResponse {
        $currency = strtoupper($currency);

        if (!in_array($currency, [
            'NGN',
            'GHS',
            'KES',
            'TZS',
        ], true)) {
            return response()->json([
                'status' => false,
                'message' => 'Unsupported currency.',
            ], 422);
        }

        try {
            $collections = $this->walletService->getCollections(
                user: $request->user(),
                currency: $currency
            );

            return response()->json([
                'status' => true,
                'message' => 'Collections fetched successfully.',
                'data' => $collections,
            ]);

        } catch (Throwable $e) {
            report($e);

            return response()->json([
                'status' => false,
                'message' => 'Unable to retrieve collections.',
            ], 500);
        }
    }

    /**
     * Verify a deposit by merchant reference.
     *
     * GET /api/v1/user/multicurrency/deposits/{merchantReference}
     */
    public function verifyDeposit(
        Request $request,
        string $merchantReference
    ): JsonResponse {
        try {
            $deposit = $this->walletService->verifyDeposit(
                user: $request->user(),
                merchantReference: $merchantReference
            );

            if (!$deposit) {
                return response()->json([
                    'status' => false,
                    'message' => 'Deposit not found or payment has not been received.',
                ], 404);
            }

            return response()->json([
                'status' => true,
                'message' => 'Deposit status retrieved successfully.',
                'data' => $deposit,
            ]);

        } catch (Throwable $e) {
            report($e);

            return response()->json([
                'status' => false,
                'message' => 'Unable to verify deposit.',
            ], 500);
        }
    }

    /**
     * Get supported exchange rates.
     *
     * GET /api/v1/user/multicurrency/rates
     */
    public function rates(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'from' => [
                'nullable',
                'string',
                'size:3',
            ],

            'to' => [
                'nullable',
                'string',
                'size:3',
            ],
        ]);

        try {
            $rates = $this->walletService->getRates(
                from: isset($validated['from'])
                    ? strtoupper($validated['from'])
                    : null,

                to: isset($validated['to'])
                    ? strtoupper($validated['to'])
                    : null
            );

            return response()->json([
                'status' => true,
                'message' => 'Currency rates fetched successfully.',
                'data' => $rates,
            ]);

        } catch (Throwable $e) {
            report($e);

            return response()->json([
                'status' => false,
                'message' => 'Unable to retrieve currency rates.',
            ], 500);
        }
    }

    /**
     * Get banks supported for a currency/country.
     *
     * GET /api/v1/user/multicurrency/banks
     */
    public function banks(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'currency' => [
                'required',
                'string',
                'size:3',
            ],

            'country' => [
                'required',
                'string',
                'size:2',
            ],
        ]);

        try {
            $banks = $this->walletService->getBanks(
                currency: strtoupper($validated['currency']),
                country: strtoupper($validated['country'])
            );

            return response()->json([
                'status' => true,
                'message' => 'Banks fetched successfully.',
                'data' => $banks,
            ]);

        } catch (Throwable $e) {
            report($e);

            return response()->json([
                'status' => false,
                'message' => 'Unable to retrieve banks.',
            ], 500);
        }
    }

    /**
     * Transfer funds to another wallet.
     *
     * POST /api/v1/user/multicurrency/transfers/wallet
     */
    public function transferToWallet(
        Request $request
    ): JsonResponse {
        $validated = $request->validate([
            'currency' => [
                'required',
                'string',
                'size:3',
            ],

            'amount' => [
                'required',
                'numeric',
                'gt:0',
            ],

            'beneficiary_wallet_number' => [
                'required',
                'string',
                'max:100',
            ],

            'description' => [
                'required',
                'string',
                'max:255',
            ],
        ]);

        try {
            $result = $this->walletService->transferToWallet(
                user: $request->user(),
                currency: strtoupper($validated['currency']),
                beneficiaryWalletNumber:
                    $validated['beneficiary_wallet_number'],
                amount: (string) $validated['amount'],
                description: $validated['description']
            );

            return response()->json([
                'status' => true,
                'message' => 'Transfer initiated successfully.',
                'data' => $result,
            ]);

        } catch (Throwable $e) {
            report($e);

            return response()->json([
                'status' => false,
                'message' => 'Unable to initiate wallet transfer.',
            ], 422);
        }
    }

    /**
     * Generate a cross-currency quote.
     *
     * POST /api/v1/user/multicurrency/quotes
     */
    public function quote(
        Request $request
    ): JsonResponse {
        $validated = $request->validate([
            'source_currency' => [
                'required',
                'string',
                'size:3',
            ],

            'destination_currency' => [
                'required',
                'string',
                'size:3',
            ],

            'amount' => [
                'required',
                'numeric',
                'gt:0',
            ],

            'action' => [
                'nullable',
                'string',
                Rule::in([
                    'send',
                    'receive',
                ]),
            ],

            'payment_destination' => [
                'nullable',
                'string',
                Rule::in([
                    'bank_account',
                    'mobile_money_wallet',
                    'crypto_wallet',
                    'fliqpay_wallet',
                ]),
            ],

            'payment_scheme' => [
                'nullable',
                'string',
                Rule::in([
                    'swift',
                    'ach',
                    'fps',
                    'sepа',
                    'sepa_instant',
                    'chaps',
                    'fed_wire',
                    'usdt_erc20',
                    'usdt_trc20',
                    'usdt_solana',
                    'usdt_bep20',
                    'usdc_erc20',
                    'usdc_solana',
                ]),
            ],

            'beneficiary_type' => [
                'nullable',
                'string',
                Rule::in([
                    'individual',
                    'corporate',
                ]),
            ],
        ]);

        try {
            $quote = $this->quoteService->generate(
                user: $request->user(),

                sourceCurrency: strtoupper(
                    $validated['source_currency']
                ),

                destinationCurrency: strtoupper(
                    $validated['destination_currency']
                ),

                amount: (string) $validated['amount'],

                action: $validated['action'] ?? 'send',

                transactionType: 'disbursement',

                feeBearer: 'customer',

                paymentDestination:
                    $validated['payment_destination']
                    ?? 'bank_account',

                paymentScheme:
                    $validated['payment_scheme']
                    ?? null,

                beneficiaryType:
                    $validated['beneficiary_type']
                    ?? 'individual'
            );

            return response()->json([
                'status' => true,
                'message' => 'Quote generated successfully.',
                'data' => $quote,
            ]);

        } catch (Throwable $e) {
            report($e);

            return response()->json([
                'status' => false,
                'message' => 'Unable to generate quote.',
            ], 422);
        }
    }
        /**
     * Initiate a payout to a beneficiary's bank account.
     *
     * POST /api/v1/user/multicurrency/payouts/bank
     */
    public function payoutToBank(
        Request $request
    ): JsonResponse {
        $validated = $request->validate([
            'source_currency' => [
                'required',
                'string',
                'size:3',
            ],

            'destination_currency' => [
                'required',
                'string',
                'size:3',
            ],

            'amount' => [
                'required',
                'numeric',
                'gt:0',
            ],

            'description' => [
                'nullable',
                'string',
                'max:255',
            ],

            'quote_reference' => [
                'nullable',
                'string',
            ],

            'beneficiary' => [
                'required',
                'array',
            ],

            'beneficiary.first_name' => [
                'required_if:beneficiary.type,individual',
                'nullable',
                'string',
                'max:100',
            ],

            'beneficiary.last_name' => [
                'required_if:beneficiary.type,individual',
                'nullable',
                'string',
                'max:100',
            ],

            'beneficiary.account_holder_name' => [
                'required',
                'string',
                'max:150',
            ],

            'beneficiary.type' => [
                'required',
                'string',
                Rule::in([
                    'individual',
                    'corporate',
                ]),
            ],

            'beneficiary.country' => [
                'required',
                'string',
                'size:2',
            ],

            'beneficiary.account_number' => [
                'required',
                'string',
                'max:100',
            ],

            'beneficiary.bank_code' => [
                'required',
                'string',
                'max:20',
            ],
        ]);

        $beneficiary = array_filter([
            'firstName' =>
                $validated['beneficiary']['first_name'] ?? null,

            'lastName' =>
                $validated['beneficiary']['last_name'] ?? null,

            'accountHolderName' =>
                $validated['beneficiary']['account_holder_name'],

            'type' => $validated['beneficiary']['type'],

            'country' =>
                strtoupper($validated['beneficiary']['country']),

            'accountNumber' =>
                $validated['beneficiary']['account_number'],

            'bankCode' =>
                $validated['beneficiary']['bank_code'],
        ], fn ($value) => $value !== null);

        try {
            $result = $this->walletService->initiateBankPayout(
                user: $request->user(),

                sourceCurrency: strtoupper(
                    $validated['source_currency']
                ),

                destinationCurrency: strtoupper(
                    $validated['destination_currency']
                ),

                amount: (string) $validated['amount'],

                beneficiary: $beneficiary,

                description: $validated['description'] ?? null,

                quoteReference: $validated['quote_reference'] ?? null
            );

            return response()->json([
                'status' => true,
                'message' => 'Payout initiated successfully.',
                'data' => $result,
            ]);

        } catch (Throwable $e) {
            report($e);

            return response()->json([
                'status' => false,
                'message' => 'Unable to initiate payout.',
            ], 422);
        }
    }
        /**
     * Initiate a conversion from a previously generated quote.
     *
     * POST /api/v1/user/multicurrency/conversions
     */
    public function convert(
        Request $request
    ): JsonResponse {
        $validated = $request->validate([
            'quote_reference' => [
                'required',
                'string',
            ],
        ]);

        try {
            $result = $this->walletService->convertCurrency(
                user: $request->user(),
                quoteReference: $validated['quote_reference']
            );

            return response()->json([
                'status' => true,
                'message' => 'Conversion initiated successfully.',
                'data' => $result,
            ]);

        } catch (Throwable $e) {
            report($e);

            return response()->json([
                'status' => false,
                'message' => 'Unable to initiate conversion.',
            ], 422);
        }
    }
    /**
 * Verify the status of a currency conversion.
 *
 * GET /api/v1/user/multicurrency/conversions/{conversionReference}
 */
public function verifyConversion(
    Request $request,
    string $conversionReference
): JsonResponse {
    try {
        $result = $this->walletService->verifyConversion(
            user: $request->user(),
            conversionReference: $conversionReference
        );

        if (!$result) {
            return response()->json([
                'status' => false,
                'message' => 'Conversion not found.',
            ], 404);
        }

        return response()->json([
            'status' => true,
            'message' => 'Conversion status retrieved successfully.',
            'data' => $result,
        ]);

    } catch (Throwable $e) {
        report($e);

        return response()->json([
            'status' => false,
            'message' => 'Unable to verify conversion.',
        ], 422);
    }
}
/**
 * Verify/resolve a bank account.
 *
 * POST /api/v1/user/multicurrency/accounts/verify
 */
/**
 * Verify/resolve a bank account.
 *
 * POST /api/v1/user/multicurrency/accounts/verify
 */
public function verifyAccount(
    Request $request
): JsonResponse {
    $validated = $request->validate([
        'type' => [
            'required',
            'string',
            Rule::in([
                'bank_account',
                'mobile_money',
                'nuban',
                'iban',
            ]),
        ],

        'account_number' => [
            'required_if:type,bank_account,nuban',
            'nullable',
            'string',
            'max:100',
        ],

        'bank_code' => [
            'required_if:type,bank_account,nuban',
            'nullable',
            'string',
            'max:50',
        ],

        'bank_swift_code' => [
            'nullable',
            'string',
            'max:50',
        ],

        'mobile_money_code' => [
            'required_if:type,mobile_money',
            'nullable',
            'string',
            'max:50',
        ],

        'iban' => [
            'required_if:type,iban',
            'nullable',
            'string',
            'max:100',
        ],

        'currency' => [
            'required_if:type,bank_account,mobile_money',
            'nullable',
            'string',
            'size:3',
        ],
    ]);

    try {
        $result = $this->walletService->verifyAccount(
            accountNumber:
                $validated['account_number'] ?? null,

            bankCode:
                $validated['bank_code'] ?? null,

            currency:
                isset($validated['currency'])
                    ? strtoupper($validated['currency'])
                    : null,

            type:
                $validated['type'],

            bankSwiftCode:
                $validated['bank_swift_code'] ?? null,

            mobileMoneyCode:
                $validated['mobile_money_code'] ?? null,

            iban:
                $validated['iban'] ?? null
        );

        return response()->json([
            'status' => true,
            'message' => 'Account verified successfully.',
            'data' => $result,
        ]);

    } catch (Throwable $e) {
        report($e);

        return response()->json([
            'status' => false,
            'message' => 'Unable to verify account.',
        ], 422);
    }
}
/**
 * Initiate a payin/funding transaction.
 */
public function payin(Request $request): JsonResponse
{
    $validated = $request->validate([
        'currency' => [
            'required',
            'string',
            'size:3',
        ],

        'amount' => [
            'required',
            'numeric',
            'gt:0',
        ],

        'payment_method' => [
            'nullable',
            'string',
            'in:bank_transfer,mobile_money,card,eft,payattitude',
        ],

        'quote_reference' => [
            'nullable',
            'string',
            'max:191',
        ],
    ]);

    $result = $this->walletService->initiatePayin(
        user: $request->user(),
        currency: strtoupper($validated['currency']),
        amount: (string) $validated['amount'],
        paymentMethod: $validated['payment_method']
            ?? 'bank_transfer',
        quoteReference: $validated['quote_reference']
            ?? null
    );

    return response()->json([
        'status' => true,
        'message' => 'Payin initiated successfully.',
        'data' => $result,
    ]);
}

}