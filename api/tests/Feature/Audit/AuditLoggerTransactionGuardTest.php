<?php

namespace Tests\Feature\Audit;

use App\Audit\AuditAction;
use App\Audit\AuditLogger;
use App\Models\Institute;
use Illuminate\Support\Facades\DB;
use LogicException;
use Tests\TestCase;

/**
 * VP-36: AuditLogger refuses to write outside a transaction.
 * Deliberately without RefreshDatabase, whose wrapping transaction would hide the check.
 * The guard throws before any query, so no table is touched.
 */
class AuditLoggerTransactionGuardTest extends TestCase
{
    public function test_recording_outside_a_transaction_is_refused_before_any_query(): void
    {
        DB::enableQueryLog();

        try {
            app(AuditLogger::class)->record(AuditAction::INSTITUTE_CREATED, new Institute);
            $this->fail('Expected recording outside a transaction to be refused.');
        } catch (LogicException $exception) {
            $this->assertStringContainsString('inside the business transaction', $exception->getMessage());
        }

        $this->assertSame([], DB::getQueryLog());
    }
}
