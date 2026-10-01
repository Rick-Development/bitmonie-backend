<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\GiftCardTradeRate;
use App\Models\GiftCardTradeCategory;
use App\Models\GiftCardTradeType;
use App\Models\GiftCardTradeCountry;
use Illuminate\Http\Request;

class GiftCardRateController extends Controller
{
    public function index()
    {
        $pageTitle = 'Gift Card Rates';
        $rates = GiftCardTradeRate::with(['category', 'type', 'country'])
            ->latest()
            ->paginate(10);
            
        $categories = GiftCardTradeCategory::where('status', 1)->get();
        $types = GiftCardTradeType::all();
        $countries = GiftCardTradeCountry::all();
        
        return view('admin.gift-card-trade.rates.index', compact('pageTitle', 'rates', 'categories', 'types', 'countries'));
    }

    public function store(Request $request)
    {
        $request->validate([
            'category_id' => 'required|exists:gift_card_trade_categories,id',
            'type_id' => 'required|exists:gift_card_trade_types,id',
            'country_id' => 'required|exists:gift_card_trade_countries,id',
            'min_amount' => 'required|numeric|min:0',
            'max_amount' => 'required|numeric|gt:min_amount',
            'rate_per_dollar' => 'required|numeric|min:0',
            'currency' => 'required|string|max:10',
        ]);

        // Check for duplicate rate combination
        $exists = GiftCardTradeRate::where([
            ['category_id', '=', $request->category_id],
            ['type_id', '=', $request->type_id],
            ['country_id', '=', $request->country_id],
        ])->exists();

        if ($exists) {
            $notify[] = ['error', 'Rate for this combination already exists'];
            return back()->withNotify($notify);
        }

        $rate = new GiftCardTradeRate();
        $rate->category_id = $request->category_id;
        $rate->type_id = $request->type_id;
        $rate->country_id = $request->country_id;
        $rate->min_amount = $request->min_amount;
        $rate->max_amount = $request->max_amount;
        $rate->rate_per_dollar = $request->rate_per_dollar;
        $rate->currency = $request->currency;
        $rate->status = 1;
        $rate->save();

        $notify[] = ['success', 'Rate created successfully'];
        return back()->withNotify($notify);
    }

    public function update(Request $request, $id)
    {
        $request->validate([
            'min_amount' => 'required|numeric|min:0',
            'max_amount' => 'required|numeric|gt:min_amount',
            'rate_per_dollar' => 'required|numeric|min:0',
            'currency' => 'required|string|max:10',
        ]);

        $rate = GiftCardTradeRate::findOrFail($id);
        $rate->min_amount = $request->min_amount;
        $rate->max_amount = $request->max_amount;
        $rate->rate_per_dollar = $request->rate_per_dollar;
        $rate->currency = $request->currency;
        $rate->save();

        $notify[] = ['success', 'Rate updated successfully'];
        return back()->withNotify($notify);
    }

    public function destroy($id)
    {
        $rate = GiftCardTradeRate::findOrFail($id);
        $rate->delete();

        $notify[] = ['success', 'Rate deleted successfully'];
        return back()->withNotify($notify);
    }
}
