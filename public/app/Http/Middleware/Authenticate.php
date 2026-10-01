<?php

namespace App\Http\Middleware;

use Illuminate\Http\Request;
use Illuminate\Http\Exceptions\HttpResponseException;
use Illuminate\Auth\Middleware\Authenticate as Middleware;

class Authenticate extends Middleware
{
    /**
     * Get the path the user should be redirected to when they are not authenticated.
     */
    protected function redirectTo($request): ?string
    {
        if ($request->expectsJson()) {
            return null; // Laravel handles this by throwing AuthenticationException which Handler renders as JSON
        }

        // For admin users
        if ($request->is('admin') || $request->is('admin/*')) {
            return route('admin.login');
        }

        return route('user.login');
    }

}
