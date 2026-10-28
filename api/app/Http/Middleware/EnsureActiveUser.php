<?php

namespace App\Http\Middleware;

use App\Authorization\TenantAccess;
use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

/**
 * Suspended users get 403 on every authenticated endpoint except logout (contract §3).
 * The status is re-read from the database on every request.
 */
class EnsureActiveUser
{
    public function __construct(private readonly TenantAccess $access) {}

    public function handle(Request $request, Closure $next): Response
    {
        $user = $request->user();

        if ($user === null || ! $this->access->isActive($user)) {
            return response()->json(['message' => 'This account is suspended.'], 403);
        }

        return $next($request);
    }
}
