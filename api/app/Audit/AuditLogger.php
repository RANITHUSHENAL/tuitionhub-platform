<?php

namespace App\Audit;

use App\Models\AuditLog;
use App\Models\Institute;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use LogicException;
use Throwable;

/**
 * The only writer of audit_logs (docs/SPRINT_SCHEMA.md R2.0; contract v0.3 §13; D-015, D-017).
 *
 * Must be called inside the business operation's database transaction, so the audit row and the
 * change it describes commit or roll back together.
 */
class AuditLogger
{
    public const USER_AGENT_LIMIT = 512;

    public function __construct(private readonly AuditContext $context) {}

    /**
     * @param  array<string, scalar|null>  $oldValues
     * @param  array<string, scalar|null>  $newValues
     */
    public function record(
        string $action,
        Model $target,
        ?Institute $institute = null,
        array $oldValues = [],
        array $newValues = [],
    ): AuditLog {
        if (DB::transactionLevel() === 0) {
            throw new LogicException('Audit events must be written inside the business transaction.');
        }

        $log = new AuditLog;
        $log->forceFill([
            'user_id' => $this->context->actorId,
            'institute_id' => $institute?->getKey(),
            'action' => $action,
            // The enforced morph map guarantees a stable alias, never a class name.
            'auditable_type' => $target->getMorphClass(),
            'auditable_id' => $target->getKey(),
            'old_values' => AuditPayload::filter($action, $oldValues),
            'new_values' => AuditPayload::filter($action, $newValues),
            'ip_address' => $this->ipAddress($action),
            'user_agent' => $this->userAgent(),
            'created_at' => now('UTC'),
        ]);
        $log->save();

        return $log;
    }

    /**
     * No request: NULL, no warning. Valid IPv4/IPv6: stored intact, never truncated.
     * Missing or invalid from a request: NULL and a warning without the raw value (D-017).
     * This never fails the business transaction.
     */
    private function ipAddress(string $action): ?string
    {
        if (! $this->context->fromRequest) {
            return null;
        }

        $ip = $this->context->ipAddress;

        if (is_string($ip) && filter_var($ip, FILTER_VALIDATE_IP) !== false) {
            return $ip;
        }

        try {
            Log::warning('audit.ip_invalid', [
                'action' => $action,
                'reason' => $ip === null || $ip === '' ? 'missing' : 'invalid',
                'length' => strlen((string) $ip),
            ]);
        } catch (Throwable) {
            // Logging problems must not fail the business operation.
        }

        return null;
    }

    private function userAgent(): ?string
    {
        $userAgent = $this->context->userAgent;

        if ($userAgent === null || $userAgent === '') {
            return null;
        }

        return mb_substr($userAgent, 0, self::USER_AGENT_LIMIT);
    }
}
