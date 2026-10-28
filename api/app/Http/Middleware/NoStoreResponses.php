<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

/**
 * Cache-Control: no-store on every /api response (D-019), including errors rendered by the framework
 * (401, 404, 419, 422, 429). Registered as global middleware so it also covers unmatched routes.
 * Laravel's cache.headers middleware is not used: it skips non-cacheable methods such as POST.
 */
class NoStoreResponses
{
    public function handle(Request $request, Closure $next): Response
    {
        $response = $next($request);

        if ($request->is('api/*')) {
            $response->headers->set('Cache-Control', 'no-store, private');
        }

        return $response;
    }
}
