<?php

namespace App\Http\Middleware\User;

use Closure;
use Illuminate\Http\Request;

class GoogleTwoFactor
{
    /**
     * Handle an incoming request.
     *
     * @param  \Illuminate\Http\Request  $request
     * @param  \Closure(\Illuminate\Http\Request): (\Illuminate\Http\Response|\Illuminate\Http\RedirectResponse)  $next
     * @return \Illuminate\Http\Response|\Illuminate\Http\RedirectResponse
     */
    public function handle(Request $request, Closure $next)
    {
        $user = auth()->user();
        if($user->two_factor_status && $user->two_factor_verified == false) return google_two_factor_verification_user_template($user);
        return $next($request);
    }
}
