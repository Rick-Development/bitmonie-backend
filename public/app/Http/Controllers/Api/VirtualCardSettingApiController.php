<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\VirtualCardSetting;
use App\Http\Helpers\Response;

class VirtualCardSettingApiController extends Controller
{
    /**
     * GET /api/user/payscribe/card-config
     * Returns the public card configuration for the currently authenticated user.
     */
    public function config()
    {
        $settings = VirtualCardSetting::getSettings();

        return Response::successResponse('Card Configuration', [
            'card_config' => [
                'fees' => [
                    'card_creation_fee'      => (float) $settings->card_creation_fee,
                    'topup_fee_percent'      => (float) $settings->topup_fee_percent,
                    'withdrawal_fee_percent' => (float) $settings->withdrawal_fee_percent,
                    'fx_markup_percent'      => (float) $settings->fx_markup_percent,
                ],
                'limits' => [
                    'min_card_topup'      => (float) $settings->min_card_topup,
                    'max_card_topup'      => (float) $settings->max_card_topup,
                    'min_card_withdrawal' => (float) $settings->min_card_withdrawal,
                    'max_card_withdrawal' => (float) $settings->max_card_withdrawal,
                    'max_card_balance'    => (float) $settings->max_card_balance,
                    'max_cards_per_user'  => (int)   $settings->max_cards_per_user,
                ],
                'rates' => [
                    'usdt_to_usd_rate' => (float) $settings->usdt_to_usd_rate,
                ],
                'available_brands' => array_filter([
                    $settings->visa_enabled       ? 'VISA'       : null,
                    $settings->mastercard_enabled  ? 'MASTERCARD' : null,
                ]),
                'features' => [
                    'card_creation_enabled'   => $settings->card_creation_enabled,
                    'card_topup_enabled'      => $settings->card_topup_enabled,
                    'card_withdrawal_enabled' => $settings->card_withdrawal_enabled,
                    'card_freeze_enabled'     => $settings->card_freeze_enabled,
                    'card_terminate_enabled'  => $settings->card_terminate_enabled,
                ],
            ],
        ]);
    }
}
