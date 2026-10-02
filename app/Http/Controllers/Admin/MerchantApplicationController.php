<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\AdminSetting;
use App\Models\MerchantApplication;
use App\Services\MerchantSecurityDepositService;
use Illuminate\Http\Request;
use Throwable;

class MerchantApplicationController extends Controller
{
    public function __construct(protected MerchantSecurityDepositService $merchantSecurityDepositService)
    {
    }

    /**
     * List all applications
     */
    public function index(Request $request)
    {
        $page_title = "Merchant Applications";
        $query = MerchantApplication::with(['user']);

        if ($request->filled('status')) {
            $query->where('status', $request->status);
        }

        $applications = $query->latest()->paginate(20);

        return view('admin.sections.p2p.merchant.index', compact('page_title', 'applications'));
    }

    /**
     * Show application details
     */
    public function show($id)
    {
        $page_title = "Application Details";
        $application = MerchantApplication::with(['user', 'reviewer'])->findOrFail($id);

        return view('admin.sections.p2p.merchant.details', compact('page_title', 'application'));
    }

    /**
     * Approve application
     */
    public function approve(Request $request, $id)
    {
        $application = MerchantApplication::findOrFail($id);

        try {
            $this->merchantSecurityDepositService->approve(
                $application,
                auth()->guard('admin')->id(),
                $request->admin_notes
            );
        } catch (Throwable $e) {
            return back()->with(['error' => [$e->getMessage()]]);
        }

        return back()->with(['success' => ['Merchant application approved successfully']]);
    }

    /**
     * Reject application
     */
    public function reject(Request $request, $id)
    {
        $request->validate([
            'reason' => 'required|string|max:1000',
        ]);

        $application = MerchantApplication::findOrFail($id);

        try {
            $this->merchantSecurityDepositService->reject(
                $application,
                $request->reason,
                auth()->guard('admin')->id()
            );
        } catch (Throwable $e) {
            return back()->with(['error' => [$e->getMessage()]]);
        }

        return back()->with(['success' => ['Merchant application rejected and the security deposit was released if one was locked.']]);
    }

    public function deactivate(Request $request, $id)
    {
        $request->validate([
            'reason' => 'nullable|string|max:1000',
        ]);

        $application = MerchantApplication::with('user')->findOrFail($id);

        try {
            $this->merchantSecurityDepositService->deactivate(
                $application->user,
                $request->input('reason'),
                auth()->guard('admin')->id()
            );
        } catch (Throwable $e) {
            return back()->with(['error' => [$e->getMessage()]]);
        }

        return back()->with(['success' => ['Merchant status deactivated and the security deposit was released successfully.']]);
    }

    /**
     * Merchant Settings Page
     */
    public function settings()
    {
        $page_title = "Merchant Settings";
        $settings = AdminSetting::query()
            ->whereIn('setting_key', ['merchant_min_usdt', 'merchant_security_deposit_usdt'])
            ->pluck('setting_value', 'setting_key');

        return view('admin.sections.p2p.merchant.settings', compact('page_title', 'settings'));
    }

    /**
     * Update Merchant Settings
     */
    public function updateSettings(Request $request)
    {
        $request->validate([
            'merchant_min_usdt' => 'required|numeric|min:0',
            'merchant_security_deposit_usdt' => 'required|numeric|min:0',
        ]);

        AdminSetting::updateOrCreate(
            ['setting_key' => 'merchant_min_usdt'],
            [
                'setting_value' => $request->merchant_min_usdt,
                'description'   => 'Minimum USDT balance required for merchant application'
            ]
        );

        AdminSetting::updateOrCreate(
            ['setting_key' => 'merchant_security_deposit_usdt'],
            [
                'setting_value' => $request->merchant_security_deposit_usdt,
                'description'   => 'Refundable USDT security deposit required for merchant activation'
            ]
        );

        return back()->with(['success' => ['Settings updated successfully']]);
    }
}
