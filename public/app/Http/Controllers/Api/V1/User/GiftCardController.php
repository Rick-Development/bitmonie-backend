<?php

namespace App\Http\Controllers\Api\V1\User;

use App\Http\Controllers\Controller;
use App\Services\ReloadlyService;
use App\Http\Helpers\Response;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Validator;
use Illuminate\Support\Facades\Log;
use App\Models\GiftCardCountry;
use App\Models\GiftCardCategory;

class GiftCardController extends Controller
{
    protected $reloadly;

    public function __construct(ReloadlyService $reloadly)
    {
        $this->reloadly = $reloadly;
    }

    /**
     * Get all gift card categories with database and default fallback
     */
    public function categories()
    {
        try {
            $categories = $this->reloadly->getCategories();
            if (empty($categories)) {
                $categories = $this->getFallbackCategories();
            }
            return Response::successResponse('Gift card categories fetched successfully', ['categories' => $categories]);
        } catch (\Throwable $e) {
            Log::warning('Failed to fetch categories from Reloadly, using fallback: ' . $e->getMessage());
            $categories = $this->getFallbackCategories();
            return Response::successResponse('Gift card categories fetched successfully', ['categories' => $categories]);
        }
    }

    /**
     * Discovery (Categories + Countries + Featured Discounts) with fallback resilience
     */
    public function discovery()
    {
        try {
            try {
                $categories = $this->reloadly->getCategories();
            } catch (\Throwable $e) {
                Log::warning('Discovery categories fetch failed: ' . $e->getMessage());
                $categories = $this->getFallbackCategories();
            }

            try {
                $countries = $this->reloadly->getCountries();
            } catch (\Throwable $e) {
                Log::warning('Discovery countries fetch failed: ' . $e->getMessage());
                $countries = $this->getFallbackCountries();
            }

            $featuredDiscounts = [];
            try {
                $discounts = $this->reloadly->getDiscounts(['page' => 1, 'size' => 10]);
                $featuredDiscounts = $discounts['content'] ?? $discounts ?? [];
            } catch (\Throwable $e) {
                Log::warning('Discovery discounts fetch failed: ' . $e->getMessage());
            }

            return Response::successResponse('Discovery data fetched', [
                'categories' => $categories ?: $this->getFallbackCategories(),
                'countries' => $countries ?: $this->getFallbackCountries(),
                'featured_discounts' => $featuredDiscounts,
            ]);
        } catch (\Throwable $e) {
            Log::warning('Failed to fetch discovery data, returning fallbacks: ' . $e->getMessage());
            return Response::successResponse('Discovery data fetched', [
                'categories' => $this->getFallbackCategories(),
                'countries' => $this->getFallbackCountries(),
                'featured_discounts' => [],
            ]);
        }
    }

    /**
     * Get gift card countries with database and curated fallback
     */
    public function countries()
    {
        try {
            $countries = $this->reloadly->getCountries();
            if (empty($countries)) {
                $countries = $this->getFallbackCountries();
            }
            return Response::successResponse('Gift card countries fetched successfully', ['countries' => $countries]);
        } catch (\Throwable $e) {
            Log::warning('Failed to fetch countries from Reloadly, using fallback: ' . $e->getMessage());
            $countries = $this->getFallbackCountries();
            return Response::successResponse('Gift card countries fetched successfully', ['countries' => $countries]);
        }
    }

    /**
     * Get single country details
     */
    public function countryDetails($isoCode)
    {
        try {
            $country = $this->reloadly->getCountry($isoCode);
            return Response::successResponse('Country details fetched successfully', ['country' => $country]);
        } catch (\Throwable $e) {
            Log::warning('Failed to fetch country details from Reloadly: ' . $e->getMessage());
            $fallback = collect($this->getFallbackCountries())->firstWhere('isoName', strtoupper($isoCode));
            if ($fallback) {
                return Response::successResponse('Country details fetched successfully', ['country' => $fallback]);
            }
            return Response::errorResponse('Country not found');
        }
    }

    /**
     * Get products with error recovery
     */
    public function products(Request $request)
    {
        try {
            $filters = $request->all();
            $products = $this->reloadly->getProducts($filters);
            return Response::successResponse('Gift card products fetched successfully', ['products' => $products]);
        } catch (\Throwable $e) {
            Log::warning('Failed to fetch products from Reloadly: ' . $e->getMessage());
            return Response::successResponse('Gift card products fetched successfully', ['products' => []]);
        }
    }

    /**
     * Get single product details
     */
    public function productDetails($id)
    {
        try {
            $product = $this->reloadly->getProductById($id);
            return Response::successResponse('Product details fetched successfully', ['product' => $product]);
        } catch (\Throwable $e) {
            Log::warning('Failed to fetch product details from Reloadly: ' . $e->getMessage());
            return Response::errorResponse('Product details are temporarily unavailable. Please try again shortly.');
        }
    }

    /**
     * Fallback countries list from DB or static defaults
     */
    protected function getFallbackCountries()
    {
        try {
            $dbCountries = GiftCardCountry::all();
            if ($dbCountries->isNotEmpty()) {
                return $dbCountries->map(function ($c) {
                    return [
                        'isoName' => $c->iso_name,
                        'name' => $c->name,
                        'currencyCode' => $c->currency_code,
                        'currencySymbol' => $this->getCurrencySymbol($c->currency_code),
                        'flagUrl' => $c->flag_url,
                        'callingCodes' => [],
                    ];
                })->values()->all();
            }
        } catch (\Throwable $e) {
            Log::warning('GiftCardCountry DB query failed: ' . $e->getMessage());
        }

        return [
            ['isoName' => 'US', 'name' => 'United States', 'currencyCode' => 'USD', 'currencySymbol' => '$', 'flagUrl' => 'https://flagcdn.com/w80/us.png', 'callingCodes' => ['+1']],
            ['isoName' => 'GB', 'name' => 'United Kingdom', 'currencyCode' => 'GBP', 'currencySymbol' => '£', 'flagUrl' => 'https://flagcdn.com/w80/gb.png', 'callingCodes' => ['+44']],
            ['isoName' => 'CA', 'name' => 'Canada', 'currencyCode' => 'CAD', 'currencySymbol' => 'CA$', 'flagUrl' => 'https://flagcdn.com/w80/ca.png', 'callingCodes' => ['+1']],
            ['isoName' => 'NG', 'name' => 'Nigeria', 'currencyCode' => 'NGN', 'currencySymbol' => '₦', 'flagUrl' => 'https://flagcdn.com/w80/ng.png', 'callingCodes' => ['+234']],
            ['isoName' => 'GH', 'name' => 'Ghana', 'currencyCode' => 'GHS', 'currencySymbol' => 'GH₵', 'flagUrl' => 'https://flagcdn.com/w80/gh.png', 'callingCodes' => ['+233']],
            ['isoName' => 'DE', 'name' => 'Germany', 'currencyCode' => 'EUR', 'currencySymbol' => '€', 'flagUrl' => 'https://flagcdn.com/w80/de.png', 'callingCodes' => ['+49']],
            ['isoName' => 'FR', 'name' => 'France', 'currencyCode' => 'EUR', 'currencySymbol' => '€', 'flagUrl' => 'https://flagcdn.com/w80/fr.png', 'callingCodes' => ['+33']],
            ['isoName' => 'AE', 'name' => 'United Arab Emirates', 'currencyCode' => 'AED', 'currencySymbol' => 'AED', 'flagUrl' => 'https://flagcdn.com/w80/ae.png', 'callingCodes' => ['+971']],
            ['isoName' => 'AU', 'name' => 'Australia', 'currencyCode' => 'AUD', 'currencySymbol' => 'A$', 'flagUrl' => 'https://flagcdn.com/w80/au.png', 'callingCodes' => ['+61']],
        ];
    }

    /**
     * Fallback categories list from DB or static defaults
     */
    protected function getFallbackCategories()
    {
        try {
            $dbCategories = GiftCardCategory::all();
            if ($dbCategories->isNotEmpty()) {
                return $dbCategories->map(function ($c) {
                    return [
                        'id' => $c->id,
                        'name' => $c->name,
                    ];
                })->values()->all();
            }
        } catch (\Throwable $e) {
            Log::warning('GiftCardCategory DB query failed: ' . $e->getMessage());
        }

        return [
            ['id' => 1, 'name' => 'E-Commerce & Retail'],
            ['id' => 2, 'name' => 'Gaming & Entertainment'],
            ['id' => 3, 'name' => 'Food & Restaurants'],
            ['id' => 4, 'name' => 'Travel & Hotels'],
            ['id' => 5, 'name' => 'Subscriptions & Streaming'],
            ['id' => 6, 'name' => 'App Stores & Software'],
        ];
    }

    protected function getCurrencySymbol(?string $currencyCode): string
    {
        return match (strtoupper($currencyCode ?? '')) {
            'USD' => '$',
            'GBP' => '£',
            'EUR' => '€',
            'NGN' => '₦',
            'GHS' => 'GH₵',
            'CAD' => 'CA$',
            'AUD' => 'A$',
            default => $currencyCode ?? '$',
        };
    }

    public function fxRate(Request $request)
    {
        $validator = Validator::make($request->all(), [
            'currency_code' => 'required|string',
            'amount' => 'required|numeric',
        ]);

        if($validator->fails()) return Response::errorResponse($validator->errors()->all());

        try {
            $rate = $this->reloadly->getFxRate($request->currency_code, $request->amount);
            return Response::successResponse('FX Rate fetched successfully', ['rate' => $rate]);
        } catch (\Exception $e) {
            return Response::errorResponse('Failed to fetch FX rate: ' . $e->getMessage());
        }
    }

    public function storeOrder(Request $request)
    {
        $validator = Validator::make($request->all(), [
            'product_id' => 'required|integer',
            'wallet_id' => 'required|integer|exists:user_wallets,id',
            'quantity' => 'required|integer|min:1',
            'unit_price' => 'required|numeric|min:0',
            'sender_name' => 'nullable|string|max:255',
            'recipient_email' => 'required|email',
        ]);

        if($validator->fails()) {
            return Response::errorResponse('Validation failed', $validator->errors());
        }

        $user = auth('api')->user();
        
        try {
            // Verify wallet belongs to user
            $wallet = \App\Models\UserWallet::where('id', $request->wallet_id)
                ->where('user_id', $user->id)
                ->first();

            if (!$wallet) {
                return Response::errorResponse('Wallet not found or does not belong to you');
            }

            // Calculate total amount
            $totalAmount = $request->quantity * $request->unit_price;

            // Check wallet balance
            if ($wallet->balance < $totalAmount) {
                return Response::errorResponse('Insufficient wallet balance');
            }

            // Fetch product details from Reloadly
            $product = $this->reloadly->getProductById($request->product_id);

            // Generate unique identifier for this transaction
            $customIdentifier = 'GC-' . $user->id . '-' . time() . '-' . uniqid();

            // Prepare Reloadly order payload
            $orderPayload = [
                'productId' => $request->product_id,
                'countryCode' => $product['country']['isoName'] ?? 'US',
                'quantity' => $request->quantity,
                'unitPrice' => $request->unit_price,
                'customIdentifier' => $customIdentifier,
                'senderName' => $request->sender_name ?? $user->fullname ?? $user->username,
                'recipientEmail' => $request->recipient_email,
            ];

            // Place order with Reloadly
            $reloadlyOrder = $this->reloadly->placeOrder($orderPayload);

            // Deduct from wallet
            $wallet->balance -= $totalAmount;
            $wallet->save();

            // Create transaction record
            $transaction = \App\Models\GiftCardTransaction::create([
                'user_id' => $user->id,
                'wallet_id' => $wallet->id,
                'reloadly_transaction_id' => $reloadlyOrder['transactionId'] ?? null,
                'custom_identifier' => $customIdentifier,
                'status' => $reloadlyOrder['status'] ?? 'PENDING',
                'amount' => $totalAmount,
                'currency' => $wallet->currency_code ?? 'USD',
                'fee' => $reloadlyOrder['fee'] ?? 0,
                'discount' => $reloadlyOrder['discount'] ?? 0,
                'product_id' => $request->product_id,
                'product_name' => $product['productName'] ?? 'Unknown Product',
                'quantity' => $request->quantity,
                'unit_price' => $request->unit_price,
                'product_currency' => $product['recipientCurrencyCode'] ?? 'USD',
                'recipient_email' => $request->recipient_email,
                'meta' => [
                    'sender_name' => $request->sender_name,
                    'reloadly_response' => $reloadlyOrder,
                    'product_details' => $product,
                ],
            ]);

            return Response::successResponse('Gift card order placed successfully', [
                'transaction' => $transaction,
                'wallet_balance' => $wallet->balance,
                'reloadly_order' => $reloadlyOrder,
            ]);

        } catch (\Exception $e) {
            return Response::errorResponse('Failed to place order: ' . $e->getMessage());
        }
    }
}
