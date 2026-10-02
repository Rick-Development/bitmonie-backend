<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\GiftCardTransaction;
use App\Models\GiftCardCategory;
use App\Models\GiftCardCountry;
use App\Services\ReloadlyService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Exception;

class GiftCardController extends Controller
{
    protected $reloadly;

    public function __construct(ReloadlyService $reloadly)
    {
        $this->reloadly = $reloadly;
    }

    /**
     * Display a listing of gift card transactions
     */
    public function index()
    {
        $page_title = "Gift Card Transactions";
        $transactions = GiftCardTransaction::with(['user', 'wallet'])
            ->latest()
            ->paginate(20);

        return view('admin.sections.giftcards.index', compact('page_title', 'transactions'));
    }

    /**
     * Display product management view
     */
    public function products()
    {
        $page_title = "Manage Gift Card Products";
        $categories = GiftCardCategory::all();
        $countries = GiftCardCountry::all();

        return view('admin.sections.giftcards.products', compact('page_title', 'categories', 'countries'));
    }

    /**
     * Sync metadata from Reloadly
     */
    public function syncMetadata()
    {
        try {
            DB::transaction(function() {
                // Sync Categories
                $categories = $this->reloadly->getCategories();
                foreach($categories as $cat) {
                    GiftCardCategory::updateOrCreate(
                        ['id' => $cat['id']],
                        ['name' => $cat['name']]
                    );
                }

                // Sync Countries
                $countries = $this->reloadly->getCountries();
                foreach($countries as $country) {
                    GiftCardCountry::updateOrCreate(
                        ['iso_name' => $country['isoName']],
                        [
                            'name' => $country['name'],
                            'currency_code' => $country['currencyCode'],
                            'flag_url' => $country['flag']
                        ]
                    );
                }
            });

            return back()->with(['success' => ['Metadata synced successfully from Reloadly']]);
        } catch (Exception $e) {
            return back()->with(['error' => ['Sync failed: ' . $e->getMessage()]]);
        }
    }

    /**
     * Toggle status of category or country
     */
    public function toggleStatus(Request $request)
    {
        $request->validate([
            'type' => 'required|in:category,country',
            'id' => 'required',
        ]);

        if ($request->type === 'category') {
            $item = GiftCardCategory::findOrFail($request->id);
        } else {
            $item = GiftCardCountry::findOrFail($request->id);
        }

        $item->status = !$item->status;
        $item->save();

        return Response::successResponse('Status updated successfully');
    }
}
