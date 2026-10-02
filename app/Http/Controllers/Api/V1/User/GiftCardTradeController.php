<?php

namespace App\Http\Controllers\Api\V1\User;

use App\Http\Controllers\Controller;
use App\Services\GiftCardTradeService;
use App\Http\Helpers\Response;
use Illuminate\Http\Request;
use Exception;
use Illuminate\Support\Facades\Validator;

class GiftCardTradeController extends Controller
{
    protected $service;

    public function __construct(GiftCardTradeService $service)
    {
        $this->service = $service;
    }

    /**
     * Get all categories
     * GET /api/user/gift-card-trade/categories
     */
    public function getCategories()
    {
        try {
            $categories = \App\Models\GiftCardTradeCategory::where('status', true)->get();
            return Response::successResponse('Categories retrieved successfully', $categories);
        } catch (Exception $e) {
            return Response::errorResponse($e->getMessage());
        }
    }

    /**
     * Get types (supports filtering by category and country)
     * GET /api/user/gift-card-trade/types?category_id=1&country_id=1
     */
    public function getTypes(Request $request)
    {
        try {
            $query = \App\Models\GiftCardTradeType::query();
            
            // If filters are provided, only show types that have active rates for the selection
            if ($request->has('category_id') || $request->has('country_id')) {
                $query->whereHas('rates', function($q) use ($request) {
                    $q->where('status', true);
                    if ($request->has('category_id')) {
                        $q->where('category_id', $request->category_id);
                    }
                    if ($request->has('country_id')) {
                        $q->where('country_id', $request->country_id);
                    }
                });
            }

            $types = $query->distinct()->get();
            return Response::successResponse('Types retrieved successfully', $types);
        } catch (Exception $e) {
            return Response::errorResponse($e->getMessage());
        }
    }

    /**
     * Get countries (supports filtering by category)
     * GET /api/user/gift-card-trade/countries?category_id=1
     */
    public function getCountries(Request $request)
    {
        try {
            $query = \App\Models\GiftCardTradeCountry::query();

            // If category is provided, only show countries that have active rates for this category
            if ($request->has('category_id')) {
                $query->whereHas('rates', function($q) use ($request) {
                    $q->where('status', true)
                      ->where('category_id', $request->category_id);
                });
            }

            $countries = $query->distinct()->get();
            return Response::successResponse('Countries retrieved successfully', $countries);
        } catch (Exception $e) {
            return Response::errorResponse($e->getMessage());
        }
    }

    /**
     * Get active rates/giftcards with filters
     * GET /api/user/gift-card-trade/rates
     * GET /api/user/gift-card-trade/gift-cards
     */
    public function getRates(Request $request)
    {
        try {
            $filters = $request->only(['category_id', 'type_id', 'country_id']);
            $rates = $this->service->getRates($filters);
            return Response::successResponse('Gift cards retrieved successfully', $rates);
        } catch (Exception $e) {
            return Response::errorResponse($e->getMessage());
        }
    }

    /**
     * Calculate trade amount (preview)
     * POST /api/user/giftcards/calculate
     */
    public function calculate(Request $request)
    {
        $validator = Validator::make($request->all(), [
            'category_id' => 'required|exists:gift_card_trade_categories,id',
            'type_id' => 'required|exists:gift_card_trade_types,id',
            'country_id' => 'required|exists:gift_card_trade_countries,id',
            'card_amount' => 'required|numeric|min:0.01',
        ]);

        if ($validator->fails()) {
            return Response::errorResponse($validator->errors()->first());
        }

        try {
            $result = $this->service->calculateAmount(
                $request->category_id,
                $request->type_id,
                $request->country_id,
                $request->card_amount
            );
            return Response::successResponse('Trade calculation successful', $result);
        } catch (Exception $e) {
            return Response::errorResponse($e->getMessage());
        }
    }

    /**
     * Submit a new trade
     * POST /api/user/giftcards/trade
     */
    public function submitTrade(Request $request)
    {
        $validator = Validator::make($request->all(), [
            'category_id' => 'required|exists:gift_card_trade_categories,id',
            'type_id' => 'required|exists:gift_card_trade_types,id',
            'country_id' => 'required|exists:gift_card_trade_countries,id',
            'card_amount' => 'required|numeric|min:0.01',
            'card_code' => 'nullable|string',
            'images' => 'required|array|min:1',
            'images.*' => 'image|mimes:jpeg,png,jpg|max:2048', // Validate each image
        ]);

        if ($validator->fails()) {
            return Response::errorResponse($validator->errors()->first());
        }

        try {
            $user = auth()->user();
            $trade = $this->service->submitTrade($user, $request->all());
            return Response::successResponse('Trade submitted successfully', $trade);
        } catch (Exception $e) {
            return Response::errorResponse($e->getMessage());
        }
    }

    /**
     * Get my trade history
     * GET /api/user/giftcards/trades
     */
    public function getTrades(Request $request)
    {
        try {
            $user = auth()->user();
            $trades = \App\Models\GiftCardTradeSubmission::with(['category', 'type', 'country', 'rate'])
                ->where('user_id', $user->id)
                ->latest()
                ->paginate($request->input('per_page', 20));
                
            return Response::successResponse('Trade history retrieved successfully', $trades);
        } catch (Exception $e) {
            return Response::errorResponse($e->getMessage());
        }
    }

    /**
     * Get single trade details
     * GET /api/user/gift-card-trade/details/{id}
     */
    public function getTrade($id)
    {
        try {
            $user = auth()->user();
            $trade = \App\Models\GiftCardTradeSubmission::with(['category', 'type', 'country', 'rate', 'images'])
                ->where('user_id', $user->id)
                ->where('id', $id)
                ->firstOrFail();
                
            return Response::successResponse('Trade details retrieved successfully', $trade);
        } catch (Exception $e) {
            return Response::errorResponse('Trade not found');
        }
    }

    /**
     * Check trade status
     * GET /api/user/gift-card-trade/status/{id}
     */
    public function checkStatus($id)
    {
        try {
            $user = auth()->user();
            $trade = \App\Models\GiftCardTradeSubmission::where('user_id', $user->id)
                ->where('id', $id)
                ->select(['id', 'status', 'admin_note', 'rejection_reason', 'created_at', 'updated_at'])
                ->firstOrFail();
                
            return Response::successResponse('Trade status retrieved successfully', $trade);
        } catch (Exception $e) {
            return Response::errorResponse('Trade not found');
        }
    }
}
