<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Helpers\Response;
use App\Models\Tier;

class KycController extends Controller
{
    /**
     * Get all KYC tiers
     */
    public function tiers()
    {
        $tiers = Tier::active()->orderBy('level')->get()->values();

        return Response::success($tiers, null);
    }
}
