<?php

namespace App\Http\Middleware\User;

use Closure;
use Illuminate\Http\Request;
use App\Http\Helpers\Response;

class PinSetupGuard
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
        $user       = auth()->user();
        if($user->pin_status == false){
            if(auth()->guard("api")->check()) {
                return Response::errorResponse('Please setup your pin first.',[]);
            }else{
                return redirect()->route('user.setup.pin.index')->with(['error' => ['Please setup your pin first.']]);
            }
        }
        return $next($request);
    }
}
