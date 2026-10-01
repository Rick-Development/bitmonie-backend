<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

class IdempotencyMiddleware
{
    public function handle(Request $request, Closure $next)
    {
        $key = $request->header('Idempotency-Key');
        if (!$key)
            return $next($request);

        $userId = $request->user()->id ?? null;
        if (!$userId)
            return $next($request);

        $existing = DB::table('idempotency_keys')->where('key', $key)->first();
        if ($existing) {
            // If response saved, return it
            if ($existing->response) {
                return response()->json(json_decode($existing->response, true));
            }
            // Otherwise continue to process (in-progress key)
            return $next($request);
        }

        // store request snapshot
        DB::table('idempotency_keys')->insert([
            'key' => $key,
            'user_id' => $userId,
            'route' => $request->path(),
            'request' => json_encode($request->all()),
            'created_at' => now(),
            'updated_at' => now()
        ]);

        $response = $next($request);

        // Save response for future identical calls
        try {
            DB::table('idempotency_keys')->where('key', $key)->update(['response' => json_encode($response->getData()), 'updated_at' => now()]);
        } catch (\Exception $e) {
            // ignore; idempotency saving failure should not break flow
        }

        return $response;
    }
}
