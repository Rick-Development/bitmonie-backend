<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use App\Http\Helpers\Response;

class P2PKycMiddleware
{
    /**
     * Handle an incoming request.
     *
     * @param  \Illuminate\Http\Request  $request
     * @param  \Closure  $next
     * @param  int  $requiredLevel
     * @return mixed
     */
    public function handle(Request $request, Closure $next, $requiredLevel = 1)
    {
        $user = auth()->user();

        if (!$user) {
            return Response::errorResponse('Unauthenticated', null, 401);
        }

        $userKycLevel = $user->kyc_tier ?? 0;

        if ($userKycLevel < $requiredLevel) {
            return Response::errorResponse(
                "KYC Level {$requiredLevel} required. Your current level: {$userKycLevel}",
                [
                    'current_level' => $userKycLevel,
                    'required_level' => (int)$requiredLevel,
                    'kyc_status' => $user->kyc_status ?? 'unverified'
                ],
                403
            );
        }

        return $next($request);
    }
}
