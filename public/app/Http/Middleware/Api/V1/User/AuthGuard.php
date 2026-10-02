<?php

namespace App\Http\Middleware\Api\V1\User;

use Closure;
use Illuminate\Http\Request;
use App\Http\Helpers\Response;

class AuthGuard
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
        if(auth()->guard("api")->check()) return Response::error(['You are already authenticated user']);
        return $next($request);
    }
}