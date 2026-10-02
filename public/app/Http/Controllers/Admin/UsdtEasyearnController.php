<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\UsdtEasyearnInvestment;
use App\Models\UsdtEasyearnSetting;
use App\Services\UsdtEasyearnService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Validator;
use Exception;

class UsdtEasyearnController extends Controller
{
    protected $easyearnService;

    public function __construct(UsdtEasyearnService $easyearnService)
    {
        $this->easyearnService = $easyearnService;
    }

    /**
     * Dashboard
     */
    public function index()
    {
        $page_title = "USDT EasyEarn Dashboard";
        
        $stats = [
            'total_locked' => UsdtEasyearnInvestment::active()->sum('amount'),
            'total_interest_paid' => \App\Models\UsdtEasyearnInterestCredit::sum('amount'),
            'active_investments' => UsdtEasyearnInvestment::active()->count(),
            'matured_investments' => UsdtEasyearnInvestment::active()->matured()->count(),
        ];

        $settings = UsdtEasyearnSetting::getSettings();
        $recent_investments = UsdtEasyearnInvestment::with('user')->latest()->take(10)->get();

        return view('admin.sections.usdt-easyearn.index', compact('page_title', 'stats', 'settings', 'recent_investments'));
    }

    /**
     * List all investments
     */
    public function investments(Request $request)
    {
        $page_title = "USDT EasyEarn Investments";
        
        $query = UsdtEasyearnInvestment::with(['user', 'interestCredits']);

        // Filters
        if ($request->status) {
            $query->where('status', $request->status);
        }

        if ($request->auto_compound !== null) {
            $query->where('auto_compound', $request->auto_compound);
        }

        if ($request->search) {
            $query->whereHas('user', function($q) use ($request) {
                $q->where('email', 'like', '%' . $request->search . '%')
                  ->orWhere('username', 'like', '%' . $request->search . '%');
            });
        }

        $investments = $query->latest()->paginate(20);

        return view('admin.sections.usdt-easyearn.investments', compact('page_title', 'investments'));
    }

    /**
     * View investment details
     */
    public function details($id)
    {
        $page_title = "Investment Details";
        $investment = UsdtEasyearnInvestment::with(['user', 'interestCredits.admin'])->findOrFail($id);
        
        return view('admin.sections.usdt-easyearn.details', compact('page_title', 'investment'));
    }

    /**
     * Credit interest for specific investment
     */
    public function creditInterest(Request $request, $id)
    {
        try {
            $investment = UsdtEasyearnInvestment::findOrFail($id);
            $admin = auth()->guard('admin')->user();

            $this->easyearnService->creditMonthlyInterest($investment, $admin);

            return back()->with(['success' => ['Interest credited successfully']]);
        } catch (Exception $e) {
            return back()->with(['error' => [$e->getMessage()]]);
        }
    }

    /**
     * Bulk credit page
     */
    public function bulkCreditPage()
    {
        $page_title = "Bulk Credit Interest";
        $settings = UsdtEasyearnSetting::getSettings();
        
        $eligible = UsdtEasyearnInvestment::eligibleForCredit()->with('user')->get();
        $totalRequired = $eligible->sum(function($inv) {
            return $inv->calculateMonthlyInterest();
        });

        return view('admin.sections.usdt-easyearn.bulk-credit', compact('page_title', 'eligible', 'totalRequired', 'settings'));
    }

    /**
     * Execute bulk credit
     */
    public function bulkCredit(Request $request)
    {
        try {
            $admin = auth()->guard('admin')->user();
            $results = $this->easyearnService->bulkCreditInterest($admin);

            return back()->with(['success' => [
                "Bulk credit completed. Success: {$results['success']}, Failed: {$results['failed']}"
            ]]);
        } catch (Exception $e) {
            return back()->with(['error' => [$e->getMessage()]]);
        }
    }

    /**
     * Settings page
     */
    public function settingsPage()
    {
        $page_title = "USDT EasyEarn Settings";
        $settings = UsdtEasyearnSetting::getSettings();
        
        return view('admin.sections.usdt-easyearn.settings', compact('page_title', 'settings'));
    }

    /**
     * Update settings
     */
    public function updateSettings(Request $request)
    {
        $validator = Validator::make($request->all(), [
            'current_monthly_rate' => 'required|numeric|min:5|max:10',
            'min_investment' => 'required|numeric|min:1',
            'max_investment' => 'nullable|numeric|min:1',
            'payout_day' => 'required|integer|min:1|max:28',
            'is_active' => 'boolean',
            'auto_credit_enabled' => 'boolean',
        ]);

        if ($validator->fails()) {
            return back()->withErrors($validator)->withInput();
        }

        try {
            $settings = UsdtEasyearnSetting::getSettings();
            $settings->update($request->only([
                'current_monthly_rate',
                'min_investment',
                'max_investment',
                'payout_day',
                'is_active',
                'auto_credit_enabled',
            ]));

            return back()->with(['success' => ['Settings updated successfully']]);
        } catch (Exception $e) {
            return back()->with(['error' => [$e->getMessage()]]);
        }
    }

    /**
     * Cancel investment
     */
    public function cancel($id)
    {
        try {
            $investment = UsdtEasyearnInvestment::with('user')->findOrFail($id);
            $user = $investment->user;
            
            if ($investment->status !== 'active') {
                throw new Exception("Only active investments can be cancelled");
            }

            \DB::transaction(function() use ($investment, $user) {
                // Return principal to user's Quidax account
                $quidaxService = new \App\Services\QuidaxService();
                $fundResponse = $quidaxService->fundSubAccount($user->quidax_id, $investment->amount, 'usdt');
                
                if (!isset($fundResponse['data'])) {
                    throw new Exception("Failed to return USDT to user's Quidax account.");
                }

                // Unlock amount in internal wallet
                $easyearnService = app(UsdtEasyearnService::class);
                $wallet = $easyearnService->getOrCreateEasyearnWallet($user);
                $wallet->unlockAmount($investment->amount);

                $investment->update(['status' => 'cancelled']);
            });

            return back()->with(['success' => ['Investment cancelled and principal returned to user Quidax account']]);
        } catch (Exception $e) {
            return back()->with(['error' => [$e->getMessage()]]);
        }
    }
}
