<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

/**
 * Authenticates requests from internal services (e.g. Go dialogue generator)
 * using a shared bearer token configured in DIALOGUE_GENERATOR_TOKEN.
 *
 * This middleware is intentionally separate from Sanctum: internal services
 * are not human users and do not need session-based auth.
 */
class InternalApiTokenMiddleware
{
    public function handle(Request $request, Closure $next): Response
    {
        $configured = config('vendor_dialogue.internal_token');

        if (! $configured) {
            return response()->json([
                'error' => 'Internal API not configured',
                'code'  => 'INTERNAL_API_DISABLED',
            ], 503);
        }

        $bearer = $request->bearerToken();

        if (! $bearer || ! hash_equals($configured, $bearer)) {
            return response()->json([
                'error' => 'Unauthorized',
                'code'  => 'INVALID_INTERNAL_TOKEN',
            ], 401);
        }

        return $next($request);
    }
}
