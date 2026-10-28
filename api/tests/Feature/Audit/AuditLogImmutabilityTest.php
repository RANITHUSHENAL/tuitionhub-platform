<?php

namespace Tests\Feature\Audit;

use App\Audit\AuditAction;
use App\Audit\AuditLogger;
use App\Models\AuditLog;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use LogicException;
use Tests\Feature\Concerns\AppFixtures;
use Tests\TestCase;

/**
 * VP-40: audit rows cannot be updated or deleted through the model or its query builder.
 */
class AuditLogImmutabilityTest extends TestCase
{
    use AppFixtures;
    use RefreshDatabase;

    public function test_vp40_every_eloquent_update_and_delete_path_is_refused(): void
    {
        $institute = $this->makeInstitute();
        $log = DB::transaction(fn () => app(AuditLogger::class)->record(
            AuditAction::INSTITUTE_CREATED, $institute, $institute, [], ['name' => 'Fictional Academy'],
        ));

        $attempts = [
            'save' => fn () => AuditLog::query()->findOrFail($log->getKey())->forceFill(['action' => 'changed'])->save(),
            'saveQuietly' => fn () => AuditLog::query()->findOrFail($log->getKey())->forceFill(['action' => 'changed'])->saveQuietly(),
            'delete' => fn () => AuditLog::query()->findOrFail($log->getKey())->delete(),
            'deleteQuietly' => fn () => AuditLog::query()->findOrFail($log->getKey())->deleteQuietly(),
            'builder update' => fn () => AuditLog::query()->whereKey($log->getKey())->update(['action' => 'changed']),
            'builder delete' => fn () => AuditLog::query()->whereKey($log->getKey())->delete(),
            'builder increment' => fn () => AuditLog::query()->whereKey($log->getKey())->increment('auditable_id'),
            'builder upsert' => fn () => AuditLog::query()->upsert([['id' => $log->getKey(), 'action' => 'changed']], ['id']),
        ];

        foreach ($attempts as $name => $attempt) {
            try {
                $attempt();
                $this->fail("Expected [{$name}] to be refused.");
            } catch (LogicException) {
                // refused
            }
        }

        $row = DB::table('audit_logs')->where('id', $log->getKey())->first();
        $this->assertSame(AuditAction::INSTITUTE_CREATED, $row->action);
        $this->assertEquals($institute->getKey(), $row->auditable_id);
    }
}
