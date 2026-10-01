<?php

namespace App\Http\Controllers\Api\V1\User;

use App\Http\Controllers\Controller;
use App\Http\Helpers\Response;
use App\Models\P2PDisclaimer;
use App\Models\P2PDisclaimerAcceptance;
use Illuminate\Http\Request;

class P2PDisclaimerController extends Controller
{
    /**
     * Get all active disclaimers
     */
    public function index()
    {
        $disclaimers = P2PDisclaimer::where('is_active', true)->get();

        return Response::successResponse('Disclaimers fetched', ['disclaimers' => $disclaimers]);
    }

    /**
     * Get specific disclaimer by key
     */
    public function show($key)
    {
        $disclaimer = P2PDisclaimer::where('key', $key)
            ->where('is_active', true)
            ->firstOrFail();

        // Check if user has accepted
        $hasAccepted = P2PDisclaimerAcceptance::where('user_id', auth()->id())
            ->where('disclaimer_id', $disclaimer->id)
            ->exists();

        return Response::successResponse('Disclaimer details', [
            'disclaimer' => $disclaimer,
            'has_accepted' => $hasAccepted,
        ]);
    }

    /**
     * Accept disclaimer
     */
    public function accept($key)
    {
        $disclaimer = P2PDisclaimer::where('key', $key)
            ->where('is_active', true)
            ->firstOrFail();

        if (!$disclaimer->requires_acceptance) {
            return Response::errorResponse('This disclaimer does not require acceptance');
        }

        $acceptance = P2PDisclaimerAcceptance::updateOrCreate(
            [
                'user_id' => auth()->id(),
                'disclaimer_id' => $disclaimer->id,
            ],
            [
                'ip_address' => request()->ip(),
            ]
        );

        return Response::successResponse('Disclaimer accepted', ['acceptance' => $acceptance]);
    }
}
