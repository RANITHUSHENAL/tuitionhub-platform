<?php

namespace App\Audit;

use Illuminate\Http\Request;

/**
 * Who and where an audited action came from. Bound as a scoped (per-request) instance; the default
 * is a system context with no actor, IP or user agent (console, seeders, tests).
 * Values are raw here and are validated by AuditLogger when written.
 */
final class AuditContext
{
    public function __construct(
        public readonly ?int $actorId = null,
        public readonly ?string $ipAddress = null,
        public readonly ?string $userAgent = null,
        public readonly bool $fromRequest = false,
    ) {}

    public static function system(): self
    {
        return new self;
    }

    /**
     * The IP comes from Laravel's request handling, which honours the configured trusted proxies.
     */
    public static function fromRequest(Request $request, ?int $actorId): self
    {
        return new self($actorId, $request->ip(), $request->userAgent(), true);
    }
}
