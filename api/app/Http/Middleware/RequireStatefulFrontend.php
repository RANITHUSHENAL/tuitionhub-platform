<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

/**
 * Session endpoints only accept requests that Sanctum treated as coming from the stateful SPA.
 *
 * Sanctum applies session and CSRF middleware only to stateful requests (Origin/Referer listed in
 * sanctum.stateful). A non-stateful request would skip CSRF entirely, so verify, logout and session
 * reject it here instead of relying on it never happening (D-019).
 */
class RequireStatefulFrontend
{
    public function handle(Request $request, Closure $next): Response
    {
        if ($request->attributes->get('sanctum') !== true) {
            return response()->json(['message' => 'This endpoint is only available to the TuitionHub web app.'], 403);
        }

        return $next($request);
    }
}
