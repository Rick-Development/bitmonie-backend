<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\Admin\AdminNotification;
use App\Models\SavingsSetting;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Validator;
use Throwable;

class SavingsSettingsController extends Controller
{
    public function index()
    {
        $page_title = 'Savings Product Settings';
        $settings = SavingsSetting::current();

        return view('admin.sections.savings-settings.index', compact('page_title', 'settings'));
    }

    public function update(Request $request)
    {
        $validator = Validator::make($request->all(), [
            'min_transaction' => 'required|numeric|min:0',
            'max_transaction' => 'required|numeric|gt:min_transaction',
            'max_target_amount' => 'required|numeric|min:1',
            'max_active_targets' => 'required|integer|min:1|max:500',
            'max_auto_plans' => 'required|integer|min:1|max:500',
            'flexi_enabled' => 'required|boolean',
            'flexi_interest_enabled' => 'required|boolean',
            'flexi_interest_apr_percent' => 'required|numeric|min:0|max:500',
            'auto_enabled' => 'required|boolean',
            'target_enabled' => 'required|boolean',
            'target_early_closure_fee_percent' => 'required|numeric|min:0|max:100',
            'safelock_enabled' => 'required|boolean',
            'safelock_early_break_penalty_percent' => 'required|numeric|min:0|max:100',
            'edusave_enabled' => 'required|boolean',
            'edusave_min_amount' => 'required|numeric|min:0',
            'edusave_termly_months' => 'required|integer|min:1|max:24',
        ]);

        if ($validator->fails()) {
            return back()->withErrors($validator)->withInput();
        }

        try {
            $admin = auth()->guard('admin')->user();
            $settings = SavingsSetting::current();
            if (! $settings->exists) {
                $settings = SavingsSetting::query()->create(SavingsSetting::defaultsFromConfig());
            }

            $old = $settings->toArray();
            $data = $request->only([
                'min_transaction',
                'max_transaction',
                'max_target_amount',
                'max_active_targets',
                'max_auto_plans',
                'flexi_enabled',
                'flexi_interest_enabled',
                'flexi_interest_apr_percent',
                'auto_enabled',
                'target_enabled',
                'target_early_closure_fee_percent',
                'safelock_enabled',
                'safelock_early_break_penalty_percent',
                'edusave_enabled',
                'edusave_min_amount',
                'edusave_termly_months',
            ]);

            $settings->update($data);
            SavingsSetting::forgetCache();

            Log::info('Admin updated savings product settings', [
                'admin_id' => $admin?->id,
                'old' => $old,
                'new' => $data,
            ]);

            try {
                if ($admin) {
                    AdminNotification::create([
                        'admin_id' => $admin->id,
                        'type' => 'SAVINGS_SETTINGS_UPDATE',
                        'message' => "Savings settings updated by {$admin->name}: Flexi APR={$settings->flexi_interest_apr_percent}%, Target fee={$settings->target_early_closure_fee_percent}%, SafeLock break={$settings->safelock_early_break_penalty_percent}%",
                    ]);
                }
            } catch (Throwable) {
                // ignore notification table issues
            }

            return back()->with(['success' => ['Savings settings updated. Changes apply to the app immediately for new actions (existing SafeLock rates stay as snapshotted at create).']]);
        } catch (Throwable $e) {
            return back()->with(['error' => [$e->getMessage()]]);
        }
    }
}
