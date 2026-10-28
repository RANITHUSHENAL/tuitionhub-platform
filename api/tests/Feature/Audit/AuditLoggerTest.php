<?php

namespace Tests\Feature\Audit;

use App\Audit\AuditAction;
use App\Audit\AuditLogger;
use App\Models\AuditLog;
use App\Models\Institute;
use Illuminate\Database\QueryException;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use LogicException;
use RuntimeException;
use Tests\Feature\Concerns\AppFixtures;
use Tests\TestCase;

/**
 * VP-57 (AuditLogger input rules) and VP-36 (audit and business change roll back together).
 */
class AuditLoggerTest extends TestCase
{
    use AppFixtures;
    use RefreshDatabase;

    public function test_vp57_it_records_alias_actor_institute_and_only_allowlisted_values(): void
    {
        $actor = $this->makeUser();
        $institute = $this->makeInstitute($actor);
        $this->useAuditContext($actor->getKey());

        $log = $this->record(AuditAction::INSTITUTE_CREATED, $institute, $institute, [
            'name' => 'Fictional Academy',
            'district' => 'Colombo',
            'city' => null,
            'status' => 'active',
            'description' => 'not allowlisted',
            'guardian_phone_masked' => '+9477*****67',
        ]);

        $row = AuditLog::query()->findOrFail($log->getKey());

        $this->assertSame('institute', $row->auditable_type);
        $this->assertEquals($institute->getKey(), $row->auditable_id);
        $this->assertEquals($actor->getKey(), $row->user_id);
        $this->assertEquals($institute->getKey(), $row->institute_id);
        $this->assertNull($row->old_values);
        $this->assertEquals(['name' => 'Fictional Academy', 'district' => 'Colombo', 'city' => null, 'status' => 'active'], $row->new_values);
        $this->assertNotNull($row->created_at);
    }

    public function test_vp57_forbidden_keys_throw_before_anything_is_written(): void
    {
        $institute = $this->makeInstitute();
        $this->useAuditContext();
        $allowlist = ['name', 'district', 'city', 'status'];

        foreach (['code', 'code_hash', 'full_name', 'date_of_birth', 'phone', 'contact_phone', 'password', 'remember_token', 'session_token'] as $key) {
            // None of these keys is allowlisted for institute.created, so if the allowlist ran first
            // the key would be dropped silently. The exception proves the denylist runs before it.
            $this->assertNotContains($key, $allowlist);

            try {
                $this->record(AuditAction::INSTITUTE_CREATED, $institute, $institute, ['name' => 'Fictional Academy', $key => 'x']);
                $this->fail("Expected [{$key}] to be refused.");
            } catch (LogicException $exception) {
                $this->assertStringContainsString($key, $exception->getMessage());
            }
        }

        $this->assertSame(0, AuditLog::query()->count());
    }

    public function test_vp57_an_action_without_an_allowlist_is_refused(): void
    {
        $institute = $this->makeInstitute();

        $this->expectException(LogicException::class);

        $this->record('class.created', $institute, $institute, []);
    }

    public function test_vp57_the_user_agent_is_limited_to_512_characters(): void
    {
        $institute = $this->makeInstitute();
        $this->useAuditContext(userAgent: str_repeat('é', 600));

        $log = $this->record(AuditAction::INSTITUTE_CREATED, $institute, $institute, ['name' => 'Fictional Academy']);

        $this->assertSame(512, mb_strlen(AuditLog::query()->findOrFail($log->getKey())->user_agent));
    }

    public function test_vp57_valid_ipv4_and_ipv6_addresses_are_stored_intact(): void
    {
        $institute = $this->makeInstitute();

        foreach (['203.0.113.10', '2001:db8:85a3::8a2e:370:7334', '::ffff:192.0.2.128', '2001:0db8:0000:0000:0000:ff00:0042:8329'] as $ip) {
            $this->useAuditContext(ipAddress: $ip);

            $log = $this->record(AuditAction::INSTITUTE_CREATED, $institute, $institute, ['name' => 'Fictional Academy']);

            $this->assertSame($ip, AuditLog::query()->findOrFail($log->getKey())->ip_address);
        }
    }

    public function test_vp57_an_invalid_ip_is_stored_as_null_with_a_warning_that_omits_the_value(): void
    {
        Log::spy();
        $institute = $this->makeInstitute();
        $this->useAuditContext(ipAddress: 'not-an-ip<script>');

        $log = $this->record(AuditAction::INSTITUTE_CREATED, $institute, $institute, ['name' => 'Fictional Academy']);

        $this->assertNull(AuditLog::query()->findOrFail($log->getKey())->ip_address);
        Log::shouldHaveReceived('warning')->once()->withArgs(fn (string $message, array $context) => $message === 'audit.ip_invalid'
            && $context['reason'] === 'invalid'
            && ! str_contains(json_encode($context), 'not-an-ip'));
    }

    public function test_vp57_a_request_without_an_ip_stores_null_with_a_warning(): void
    {
        Log::spy();
        $institute = $this->makeInstitute();
        $this->useAuditContext(ipAddress: null);

        $log = $this->record(AuditAction::INSTITUTE_CREATED, $institute, $institute, ['name' => 'Fictional Academy']);

        $this->assertNull(AuditLog::query()->findOrFail($log->getKey())->ip_address);
        Log::shouldHaveReceived('warning')->once()->withArgs(fn (string $message, array $context) => $context['reason'] === 'missing');
    }

    public function test_vp57_a_system_event_without_a_request_stores_null_without_a_warning(): void
    {
        Log::spy();
        $institute = $this->makeInstitute();

        $log = $this->record(AuditAction::INSTITUTE_CREATED, $institute, $institute, ['name' => 'Fictional Academy']);

        $row = AuditLog::query()->findOrFail($log->getKey());
        $this->assertNull($row->ip_address);
        $this->assertNull($row->user_agent);
        $this->assertNull($row->user_id);
        Log::shouldNotHaveReceived('warning');
    }

    public function test_vp36_the_audit_row_and_the_business_change_roll_back_together(): void
    {
        $owner = $this->makeUser();

        try {
            DB::transaction(function () use ($owner) {
                $institute = $this->makeInstitute($owner, ['name' => 'Fictional Rollback Academy']);
                app(AuditLogger::class)->record(AuditAction::INSTITUTE_CREATED, $institute, $institute, [], ['name' => 'Fictional Rollback Academy']);

                throw new RuntimeException('forced failure');
            });
        } catch (RuntimeException) {
            // expected
        }

        $this->assertSame(0, Institute::query()->where('name', 'Fictional Rollback Academy')->count());
        $this->assertSame(0, AuditLog::query()->count());
    }

    public function test_vp36_a_failed_audit_insert_propagates_and_rolls_back_the_business_change(): void
    {
        $owner = $this->makeUser();

        try {
            DB::transaction(function () use ($owner) {
                $this->makeInstitute($owner, ['name' => 'Fictional Failed Audit Academy']);

                // An unsaved target has no key, so the NOT NULL auditable_id makes the audit INSERT itself fail.
                app(AuditLogger::class)->record(AuditAction::INSTITUTE_CREATED, new Institute, null, [], ['name' => 'x']);
            });
            $this->fail('Expected the failed audit insert to propagate.');
        } catch (QueryException $exception) {
            $this->assertStringContainsString('auditable_id', $exception->getMessage());
        }

        $this->assertSame(0, Institute::query()->where('name', 'Fictional Failed Audit Academy')->count());
        $this->assertSame(0, AuditLog::query()->count());
    }

    public function test_vp57_a_failing_ip_warning_never_fails_the_business_transaction(): void
    {
        Log::shouldReceive('warning')->once()->andThrow(new RuntimeException('log channel unavailable'));
        $institute = $this->makeInstitute();
        $this->useAuditContext(ipAddress: 'not-an-ip');

        $log = $this->record(AuditAction::INSTITUTE_CREATED, $institute, $institute, ['name' => 'Fictional Academy']);

        $this->assertNull(AuditLog::query()->findOrFail($log->getKey())->ip_address);
    }

    private function record(string $action, Institute $target, ?Institute $institute, array $newValues): AuditLog
    {
        return DB::transaction(fn () => app(AuditLogger::class)->record($action, $target, $institute, [], $newValues));
    }
}
