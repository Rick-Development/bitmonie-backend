<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\GiftCardTradeCategory;
use Illuminate\Http\Request;
use Illuminate\Support\Str;

class GiftCardCategoryController extends Controller
{
    /**
     * Display a listing of the resource.
     */
    public function index()
    {
        $pageTitle = 'Gift Card Categories';
        $categories = GiftCardTradeCategory::latest()->paginate(10);
        return view('admin.gift-card-trade.categories.index', compact('pageTitle', 'categories'));
    }

    /**
     * Store a newly created resource in storage.
     */
    public function store(Request $request)
    {
        $request->validate([
            'name' => 'required|string|max:255|unique:gift_card_trade_categories,name',
            'icon' => 'nullable|string', // Assuming URL input for now based on view, or switch to file upload
            'image' => 'nullable|image|mimes:jpeg,png,jpg,gif,svg|max:2048',
            'description' => 'nullable|string',
        ]);

        $category = new GiftCardTradeCategory();
        $category->name = $request->name;
        $category->slug = Str::slug($request->name);
        
        // Handle Image Upload if provided
        if($request->hasFile('image')) {
            $image = $request->file('image');
            $imageName = time().'.'.$image->extension();
            $image->move(public_path('assets/images/gift-card'), $imageName);
            $category->icon = 'assets/images/gift-card/'.$imageName;
        } else {
             $category->icon = $request->icon; // Fallback to URL if needed
        }

        $category->description = $request->description;
        $category->status = 1;
        $category->save();

        $notify[] = ['success', 'Category created successfully'];
        return back()->withNotify($notify);
    }

    /**
     * Update the specified resource in storage.
     */
    public function update(Request $request, $id)
    {
        $request->validate([
            'name' => 'required|string|max:255|unique:gift_card_trade_categories,name,' . $id,
            'icon' => 'nullable|string',
            'image' => 'nullable|image|mimes:jpeg,png,jpg,gif,svg|max:2048',
            'description' => 'nullable|string',
        ]);

        $category = GiftCardTradeCategory::findOrFail($id);
        $category->name = $request->name;
        $category->slug = Str::slug($request->name);
        
        if($request->hasFile('image')) {
            // Delete old image if exists and not a URL
            if($category->icon && file_exists(public_path($category->icon))) {
                unlink(public_path($category->icon));
            }
            $image = $request->file('image');
            $imageName = time().'.'.$image->extension();
            $image->move(public_path('assets/images/gift-card'), $imageName);
            $category->icon = 'assets/images/gift-card/'.$imageName;
        } elseif ($request->has('icon') && !empty($request->icon)) {
             $category->icon = $request->icon;
        }
        
        $category->description = $request->description;
        $category->save();

        $notify[] = ['success', 'Category updated successfully'];
        return back()->withNotify($notify);
    }

    /**
     * Remove the specified resource from storage.
     */
    public function destroy($id)
    {
        $category = GiftCardTradeCategory::findOrFail($id);
        $category->delete();

        $notify[] = ['success', 'Category deleted successfully'];
        return back()->withNotify($notify);
    }
    
    public function status($id)
    {
        return GiftCardTradeCategory::changeStatus($id);
    }
}
