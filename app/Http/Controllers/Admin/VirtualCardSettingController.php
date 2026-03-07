<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\VirtualCardSetting;
use Illuminate\Http\Request;

class VirtualCardSettingController extends Controller
{
    /**
     * GET /admin/virtual-card/settings
     * Show the settings page.
     */
    public function index()
    {
        $page_title = 'Virtual Card Settings';
        $settings   = VirtualCardSetting::getSettings();

        return view('admin.sections.virtual-card.settings', compact('page_title', 'settings'));
    }

    /**
     * PUT /admin/virtual-card/settings
     * Update the settings.
     */
    public function update(Request $request)
    {
        $validated = $request->validate([
            // Issuance
            'card_creation_fee'       => 'required|numeric|min:0',
            'min_card_topup'          => 'required|numeric|min:0',
            'max_card_topup'          => 'required|numeric|min:0',
            'min_card_withdrawal'     => 'required|numeric|min:0',
            'max_card_withdrawal'     => 'required|numeric|min:0',
            // Rates
            'usdt_to_usd_rate'        => 'required|numeric|min:0',
            'topup_fee_percent'       => 'required|numeric|min:0|max:100',
            'withdrawal_fee_percent'  => 'required|numeric|min:0|max:100',
            'fx_markup_percent'       => 'required|numeric|min:0|max:100',
            // Limits
            'max_cards_per_user'      => 'required|integer|min:1',
            'max_card_balance'        => 'required|numeric|min:0',
            // Brands
            'visa_enabled'            => 'boolean',
            'mastercard_enabled'      => 'boolean',
            // Feature switches
            'card_creation_enabled'   => 'boolean',
            'card_topup_enabled'      => 'boolean',
            'card_withdrawal_enabled' => 'boolean',
            'card_freeze_enabled'     => 'boolean',
            'card_terminate_enabled'  => 'boolean',
            // Notifications
            'email_on_topup'          => 'boolean',
            'email_on_withdrawal'     => 'boolean',
            'email_on_decline'        => 'boolean',
        ]);

        // Checkboxes send nothing when unchecked — default them to false
        $booleans = [
            'visa_enabled', 'mastercard_enabled',
            'card_creation_enabled', 'card_topup_enabled', 'card_withdrawal_enabled',
            'card_freeze_enabled', 'card_terminate_enabled',
            'email_on_topup', 'email_on_withdrawal', 'email_on_decline',
        ];

        foreach ($booleans as $field) {
            $validated[$field] = $request->boolean($field);
        }

        $settings = VirtualCardSetting::getSettings();
        $settings->update($validated);

        return back()->with('success', 'Virtual card settings updated successfully.');
    }
}
