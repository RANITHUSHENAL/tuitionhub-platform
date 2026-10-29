<?php

namespace App\Http\Middleware;

use App\Audit\AuditContext;
use App\Audit\AuditLogger;
use Closure;
use Illuminate\Contracts\Foundation\Application;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

/**
 * Binds the audit actor, IP and user agent for this request only (D-020).
 *
 * Runs after authentication and the suspended-user check, so the actor is always the active,
 * authenticated caller. The binding is removed when the request ends, so nothing leaks into a
 * later request in the same process (tests, queue workers, long-running servers); the container
 * then falls back to the system context again.
 *
 * Services that write audit rows must be resolved after this middleware (controller method
 * injection), because AuditLogger receives its context when it is constructed.
 */
class BindAuditContext
{
    public function __construct(private readonly Application $app) {}

    public function handle(Request $request, Closure $next): Response
    {
        $this->app->instance(AuditContext::class, new AuditContext(
            $request->user()?->getKey(),
            $this->validIp($request->ip()),
            $this->boundedUserAgent($request->userAgent()),
            true,
        ));

        try {
            return $next($request);
        } finally {
            $this->app->forgetInstance(AuditContext::class);
        }
    }

    /**
     * Only a valid IPv4/IPv6 address is kept. Anything else becomes NULL, which AuditLogger records
     * with a warning that never contains the raw value (D-017). The IP honours trusted proxies once
     * they are configured (pending, D-019).
     */
    private function validIp(?string $ip): ?string
    {
        return is_string($ip) && filter_var($ip, FILTER_VALIDATE_IP) !== false ? $ip : null;
    }

    private function boundedUserAgent(?string $userAgent): ?string
    {
        if ($userAgent === null || $userAgent === '') {
            return null;
        }

        return mb_substr(mb_scrub($userAgent, 'UTF-8'), 0, AuditLogger::USER_AGENT_LIMIT, 'UTF-8');
    }
}
