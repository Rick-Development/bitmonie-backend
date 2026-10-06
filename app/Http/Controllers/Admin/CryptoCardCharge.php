<?php

declare(strict_types=1);

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\CryptoCardSetup;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;
use Throwable;

class CryptoCardCharge extends Controller
{
    /**
     * Display crypto card charge settings.
     */
    public function index(): View
    {
        $page_title = 'Crypto Card Charges';

        $setup = CryptoCardSetup::query()->first();

        return view(
            'admin.sections.crypto-card-charge.index',
            compact('page_title','setup')
        );
    }

    /**
     * Show the crypto card charge settings page.
     */
    public function create(): View
    {
        $page_title = 'Crypto Card Charges';

        $setup = CryptoCardSetup::query()->first();

        return view(
            'admin.sections.crypto-card-charge.create',
            compact('page_title','setup')
        );
    }

    /**
     * Store crypto card charge settings.
     *
     * Only one crypto card setup record is allowed.
     */
    public function store(Request $request): RedirectResponse
    {
        $data = $request->validate([
            'physical_card_fee' => [
                'nullable',
                'numeric',
                'min:0',
            ],
            'physical_card_funding_fee' => [
                'nullable',
                'numeric',
                'min:0',
            ],
            'physical_monthly_card_maintenance_fee' => [
                'nullable',
                'numeric',
                'min:0',
            ],

            'virtual_card_issuance_fee' => [
                'nullable',
                'numeric',
                'min:0',
            ],
            'virtual_card_funding_fee' => [
                'nullable',
                'numeric',
                'min:0',
            ],
            'virtual_monthly_card_maintenance_fee' => [
                'nullable',
                'numeric',
                'min:0',
            ],

            'card_issuance_fee' => [
                'nullable',
                'numeric',
                'min:0',
            ],
            'card_funding_fee' => [
                'nullable',
                'numeric',
                'min:0',
            ],
            'monthly_card_maintenance_fee' => [
                'nullable',
                'numeric',
                'min:0',
            ],
        ]);

        $data['physical_card_fee'] = $data['physical_card_fee'] ?? 0;
        $data['physical_card_funding_fee'] = $data['physical_card_funding_fee'] ?? 0;
        $data['physical_monthly_card_maintenance_fee'] = $data['physical_monthly_card_maintenance_fee'] ?? 0;

        $data['virtual_card_issuance_fee'] = $data['virtual_card_issuance_fee'] ?? $data['card_issuance_fee'] ?? 0;
        $data['virtual_card_funding_fee'] = $data['virtual_card_funding_fee'] ?? $data['card_funding_fee'] ?? 0;
        $data['virtual_monthly_card_maintenance_fee'] = $data['virtual_monthly_card_maintenance_fee'] ?? $data['monthly_card_maintenance_fee'] ?? 0;

        $data['card_issuance_fee'] = $data['virtual_card_issuance_fee'];
        $data['card_funding_fee'] = $data['virtual_card_funding_fee'];
        $data['monthly_card_maintenance_fee'] = $data['virtual_monthly_card_maintenance_fee'];

        try {
            CryptoCardSetup::query()->updateOrCreate(
                [],
                $data
            );

            return redirect()
                ->route('admin.crypto-card-charge.index')
                ->with([
                    'success' => [
                        'Crypto card charges saved successfully.',
                    ],
                ]);
        } catch (Throwable $e) {
            return back()
                ->withInput()
                ->with([
                    'error' => [
                        $e->getMessage(),
                    ],
                ]);
        }
    }

    /**
     * Show crypto card charge details.
     */
    public function show(): View
    {
        $page_title = 'Crypto Card Charges';

        $setup = CryptoCardSetup::query()->first();

        return view(
            'admin.sections.crypto-card-charge.details',
            compact(
                'page_title',
                'setup'
            )
        );
    }

    /**
     * Show edit crypto card charge settings.
     */
    public function edit(): View
    {
        $page_title = 'Edit Crypto Card Charges';

        $setup = CryptoCardSetup::query()->first();

        return view(
            'admin.sections.crypto-card-charge.edit',
            compact(
                'page_title',
                'setup'
            )
        );
    }

    /**
     * Update crypto card charge settings.
     */
    public function update(
        Request $request
    ): RedirectResponse {
        $data = $request->validate([
            'physical_card_fee' => [
                'nullable',
                'numeric',
                'min:0',
            ],
            'physical_card_funding_fee' => [
                'nullable',
                'numeric',
                'min:0',
            ],
            'physical_monthly_card_maintenance_fee' => [
                'nullable',
                'numeric',
                'min:0',
            ],

            'virtual_card_issuance_fee' => [
                'nullable',
                'numeric',
                'min:0',
            ],
            'virtual_card_funding_fee' => [
                'nullable',
                'numeric',
                'min:0',
            ],
            'virtual_monthly_card_maintenance_fee' => [
                'nullable',
                'numeric',
                'min:0',
            ],

            'card_issuance_fee' => [
                'nullable',
                'numeric',
                'min:0',
            ],
            'card_funding_fee' => [
                'nullable',
                'numeric',
                'min:0',
            ],
            'monthly_card_maintenance_fee' => [
                'nullable',
                'numeric',
                'min:0',
            ],
        ]);

        $data['physical_card_fee'] = $data['physical_card_fee'] ?? 0;
        $data['physical_card_funding_fee'] = $data['physical_card_funding_fee'] ?? 0;
        $data['physical_monthly_card_maintenance_fee'] = $data['physical_monthly_card_maintenance_fee'] ?? 0;

        $data['virtual_card_issuance_fee'] = $data['virtual_card_issuance_fee'] ?? $data['card_issuance_fee'] ?? 0;
        $data['virtual_card_funding_fee'] = $data['virtual_card_funding_fee'] ?? $data['card_funding_fee'] ?? 0;
        $data['virtual_monthly_card_maintenance_fee'] = $data['virtual_monthly_card_maintenance_fee'] ?? $data['monthly_card_maintenance_fee'] ?? 0;

        $data['card_issuance_fee'] = $data['virtual_card_issuance_fee'];
        $data['card_funding_fee'] = $data['virtual_card_funding_fee'];
        $data['monthly_card_maintenance_fee'] = $data['virtual_monthly_card_maintenance_fee'];

        try {
            $setup = CryptoCardSetup::query()->first();

            if (!$setup) {
                CryptoCardSetup::query()->create($data);
            } else {
                $setup->update($data);
            }

            return redirect()
                ->route('admin.crypto-card-charge.index')
                ->with([
                    'success' => [
                        'Crypto card charges updated successfully.',
                    ],
                ]);
        } catch (Throwable $e) {
            return back()
                ->withInput()
                ->with([
                    'error' => [
                        $e->getMessage(),
                    ],
                ]);
        }
    }

    /**
     * Update a single crypto card charge.
     */
    public function updateCharge(
        Request $request,
        string $charge
    ): RedirectResponse {
        $allowedCharges = [
            'physical_card_fee',
            'physical_card_funding_fee',
            'physical_monthly_card_maintenance_fee',
            'virtual_card_issuance_fee',
            'virtual_card_funding_fee',
            'virtual_monthly_card_maintenance_fee',
            'card_issuance_fee',
            'card_funding_fee',
            'monthly_card_maintenance_fee',
        ];

        if (!in_array($charge, $allowedCharges, true)) {
            abort(404);
        }

        $data = $request->validate([
            'amount' => [
                'nullable',
                'numeric',
                'min:0',
            ],
        ]);

        $amount = $data['amount'] ?? 0;

        $updatePayload = [
            $charge => $amount,
        ];

        // Keep legacy and new virtual keys synchronized
        if ($charge === 'virtual_card_issuance_fee') {
            $updatePayload['card_issuance_fee'] = $amount;
        } elseif ($charge === 'card_issuance_fee') {
            $updatePayload['virtual_card_issuance_fee'] = $amount;
        } elseif ($charge === 'virtual_card_funding_fee') {
            $updatePayload['card_funding_fee'] = $amount;
        } elseif ($charge === 'card_funding_fee') {
            $updatePayload['virtual_card_funding_fee'] = $amount;
        } elseif ($charge === 'virtual_monthly_card_maintenance_fee') {
            $updatePayload['monthly_card_maintenance_fee'] = $amount;
        } elseif ($charge === 'monthly_card_maintenance_fee') {
            $updatePayload['virtual_monthly_card_maintenance_fee'] = $amount;
        }

        try {
            $setup = CryptoCardSetup::query()->first();

            if (!$setup) {
                $setup = CryptoCardSetup::query()->create($updatePayload);
            } else {
                $setup->update($updatePayload);
            }

            return back()->with([
                'success' => [
                    'Crypto card charge updated successfully.',
                ],
            ]);
        } catch (Throwable $e) {
            return back()->with([
                'error' => [
                    $e->getMessage(),
                ],
            ]);
        }
    }
}
