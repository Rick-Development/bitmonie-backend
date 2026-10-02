<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\GiftCardTradeCountry;
use Illuminate\Http\Request;

class GiftCardCountryController extends Controller
{
    public function index()
    {
        $pageTitle = 'Gift Card Countries';
        $countries = GiftCardTradeCountry::latest()->paginate(10);
        return view('admin.gift-card-trade.countries.index', compact('pageTitle', 'countries'));
    }

    public function store(Request $request)
    {
        $request->validate([
            'name' => 'required|string|max:255',
            'code' => 'required|string|max:3|unique:gift_card_trade_countries,code',
            'flag_icon' => 'nullable|string',
            'flag' => 'nullable|image|mimes:jpeg,png,jpg,gif,svg|max:2048',
        ]);

        $country = new GiftCardTradeCountry();
        $country->name = $request->name;
        $country->code = strtoupper($request->code);
        
        // Handle Flag Upload if provided
        if($request->hasFile('flag')) {
            $image = $request->file('flag');
            $imageName = time().'.'.$image->extension();
            $image->move(public_path('assets/images/country-flag'), $imageName);
            $country->flag_icon = 'assets/images/country-flag/'.$imageName;
        } else {
            $country->flag_icon = $request->flag_icon;
        }
        
        $country->save();

        $notify[] = ['success', 'Country created successfully'];
        return back()->withNotify($notify);
    }

    public function update(Request $request, $id)
    {
        $request->validate([
            'name' => 'required|string|max:255',
            'code' => 'required|string|max:3|unique:gift_card_trade_countries,code,' . $id,
            'flag_icon' => 'nullable|string',
            'flag' => 'nullable|image|mimes:jpeg,png,jpg,gif,svg|max:2048',
        ]);

        $country = GiftCardTradeCountry::findOrFail($id);
        $country->name = $request->name;
        $country->code = strtoupper($request->code);
        
        if($request->hasFile('flag')) {
            // Delete old details if exists and not a URL (basic check)
             if($country->flag_icon && file_exists(public_path($country->flag_icon))) {
                unlink(public_path($country->flag_icon));
            }
            $image = $request->file('flag');
            $imageName = time().'.'.$image->extension();
            $image->move(public_path('assets/images/country-flag'), $imageName);
            $country->flag_icon = 'assets/images/country-flag/'.$imageName;
        } elseif ($request->has('flag_icon') && !empty($request->flag_icon)) {
            $country->flag_icon = $request->flag_icon;
        }
        
        $country->save();

        $notify[] = ['success', 'Country updated successfully'];
        return back()->withNotify($notify);
    }

    public function destroy($id)
    {
        $country = GiftCardTradeCountry::findOrFail($id);
        $country->delete();

        $notify[] = ['success', 'Country deleted successfully'];
        return back()->withNotify($notify);
    }
}
