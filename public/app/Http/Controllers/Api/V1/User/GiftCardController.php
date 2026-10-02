<?php

namespace App\Http\Controllers\Api\V1\User;

use App\Http\Controllers\Controller;
use App\Services\ReloadlyService;
use App\Http\Helpers\Response;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Validator;


class GiftCardController extends Controller
{
    protected $reloadly;

    public function __construct(ReloadlyService $reloadly)
    {
        $this->reloadly = $reloadly;
    }

    /**
     * Get all gift card categories from Reloadly
     */
    public function categories()
    {
        try {
            $categories = $this->reloadly->getCategories();
            return Response::successResponse('Gift card categories fetched successfully', ['categories' => $categories]);
        } catch (\Exception $e) {
            return Response::errorResponse('Failed to fetch categories: ' . $e->getMessage());
        }
    }

    /**
     * Discovery (Categories + Countries + Featured Discounts)
     */
    public function discovery()
    {
        try {
            // Get Categories
            $categories = $this->reloadly->getCategories();

            // Get Countries
            $countries = $this->reloadly->getCountries();

            // Get Featured Discounts (Top 10)
            // We fetch simple discounts/products. 
            // In a real scenario, you might filter by 'featured' or sort by discount percentage.
            $discounts = $this->reloadly->getDiscounts(['page' => 1, 'size' => 10]);
            
            // If API response structure needs mapping, we can do it here. 
            // Assuming getDiscounts returns the structure close to what is needed or we pass it as is.
            // Based on user sample, 'featured_discounts' contains 'product' object and 'discountPercentage'.
            // Reloadly 'discounts' endpoint usually returns exactly that list.
            
            // Extract the list from response if wrapped (Reloadly often wraps in 'content' or just returns list)
            // The service parseResponse usually returns the body array. 
            // If it's paginated, it might be in 'content'. Let's assume it returns the list or handle it.
            $featuredDiscounts = $discounts['content'] ?? $discounts;

            return Response::successResponse('Discovery data fetched', [
                'categories' => $categories,
                'countries' => $countries,
                'featured_discounts' => $featuredDiscounts,
            ]);
        } catch (\Exception $e) {
            return Response::errorResponse('Failed to fetch discovery data: ' . $e->getMessage());
        }
    }

    public function countries()
    {
        try {
            $countries = $this->reloadly->getCountries();
            return Response::successResponse('Gift card countries fetched successfully', ['countries' => $countries]);
        } catch (\Exception $e) {
            return Response::errorResponse('Failed to fetch countries: ' . $e->getMessage());
        }
    }

    public function countryDetails($isoCode)
    {
        try {
            $country = $this->reloadly->getCountry($isoCode);
            return Response::successResponse('Country details fetched successfully', ['country' => $country]);
        } catch (\Exception $e) {
            return Response::errorResponse('Failed to fetch country details: ' . $e->getMessage());
        }
    }

    public function products(Request $request)
    {
        try {
            $filters = $request->all();
            $products = $this->reloadly->getProducts($filters);
            return Response::successResponse('Gift card products fetched successfully', ['products' => $products]);
        } catch (\Exception $e) {
            return Response::errorResponse('Failed to fetch products: ' . $e->getMessage());
        }
    }

    public function productDetails($id)
    {
        try {
            $product = $this->reloadly->getProductById($id);
            return Response::successResponse('Product details fetched successfully', ['product' => $product]);
        } catch (\Exception $e) {
            return Response::errorResponse('Failed to fetch product details: ' . $e->getMessage());
        }
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
