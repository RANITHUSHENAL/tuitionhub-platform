<?php

namespace Tests\Feature\Http\Institutes;

use App\Audit\AuditAction;
use App\Audit\AuditLogger;
use App\Models\Institute;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use RuntimeException;
use Tests\Feature\Http\Concerns\ApiActors;
use Tests\TestCase;

/**
 * PATCH /api/v1/institutes/{uuid} (contract v0.5 §3, §5.5; FD-3, FD-4, FD-9). Fictional data only.
 */
class UpdateInstituteTest extends TestCase
{
    use ApiActors;
    use RefreshDatabase;

    public function test_unauthenticated_requests_get_401(): void
    {
        $institute = $this->makeInstitute();

        $this->spa('PATCH', "/api/v1/institutes/{$institute->uuid}", ['name' => 'Fictional Renamed'])->assertUnauthorized();
    }

    public function test_unrelated_callers_get_404_even_for_a_suspended_institute_or_an_invalid_body(): void
    {
        $institute = $this->makeInstitute(null, ['name' => 'Fictional Original']);
        $this->loginAs($this->adminOf($this->makeInstitute()));

        $this->spa('PATCH', "/api/v1/institutes/{$institute->uuid}", ['name' => 'Fictional Renamed'])
            ->assertNotFound()->assertExactJson(['message' => 'Not found.']);
        $this->spa('PATCH', "/api/v1/institutes/{$institute->uuid}", ['owner_user_id' => 1, 'status' => 'suspended'])
            ->assertNotFound()->assertExactJson(['message' => 'Not found.']);

        $this->suspend($institute);
        $this->spa('PATCH', "/api/v1/institutes/{$institute->uuid}", ['name' => 'Fictional Renamed'])
            ->assertNotFound()->assertExactJson(['message' => 'Not found.']);

        $this->assertSame('Fictional Original', $institute->fresh()->name);
    }

    public function test_staff_and_teacher_only_callers_get_403(): void
    {
        $institute = $this->makeInstitute(null, ['name' => 'Fictional Original']);

        foreach ([$this->staffOf($institute, 'manager'), $this->teacherAt($institute)] as $user) {
            $this->loginAs($user);
            $this->spa('PATCH', "/api/v1/institutes/{$institute->uuid}", ['name' => 'Fictional Renamed'])
                ->assertForbidden()->assertJsonMissingPath('reason');
        }

        $this->assertSame('Fictional Original', $institute->fresh()->name);
    }

    public function test_an_admin_updates_fields_and_the_audit_row_holds_no_raw_contact_details(): void
    {
        $institute = $this->makeInstitute(null, ['name' => 'Fictional Original', 'city' => 'Maharagama']);
        $admin = $this->adminOf($institute);
        $this->loginAs($admin);

        $response = $this->spa('PATCH', "/api/v1/institutes/{$institute->uuid}", [
            'name' => 'Fictional Renamed',
            'city' => 'Nugegoda',
            'district' => 'gampaha',
            'description' => 'A fictional description.',
            'contact_phone' => '+94700000556',
            'contact_email' => 'renamed@example.test',
            'business_reg_no' => 'FICT-001',
        ])->assertOk()
            ->assertJsonPath('data.name', 'Fictional Renamed')
            ->assertJsonPath('data.district', 'Gampaha')
            ->assertJsonPath('data.contact_email', 'renamed@example.test');
        $this->assertNoInternalIds($response);

        $rows = $this->auditRows(AuditAction::INSTITUTE_UPDATED);
        $this->assertCount(1, $rows);
        $this->assertEquals($admin->getKey(), $rows[0]['user_id']);
        $this->assertEquals($institute->getKey(), $rows[0]['institute_id']);
        // assertEquals: MySQL's JSON type stores object keys in its own order.
        $this->assertEquals(['name' => 'Fictional Original', 'district' => 'Colombo', 'city' => 'Maharagama'], $rows[0]['old_values']);
        $this->assertEquals([
            'name' => 'Fictional Renamed', 'district' => 'Gampaha', 'city' => 'Nugegoda',
            'description_changed' => true, 'contact_phone_changed' => true, 'contact_email_changed' => true, 'business_reg_no_changed' => true,
        ], $rows[0]['new_values']);
        $this->assertSame([], $this->auditRows(AuditAction::INSTITUTE_STATUS_CHANGED));

        $stored = (string) DB::table('audit_logs')->where('action', AuditAction::INSTITUTE_UPDATED)->value('new_values');
        foreach (['renamed@example.test', '+94700000556', 'FICT-001', 'fictional description'] as $raw) {
            $this->assertStringNotContainsString($raw, $stored);
        }
    }

    public function test_an_admin_sending_status_gets_403_and_nothing_changes(): void
    {
        $institute = $this->makeInstitute(null, ['name' => 'Fictional Original']);
        $this->loginAs($this->adminOf($institute));

        $this->spa('PATCH', "/api/v1/institutes/{$institute->uuid}", ['name' => 'Fictional Renamed', 'status' => 'suspended'])
            ->assertForbidden();

        $fresh = $institute->fresh();
        $this->assertSame('Fictional Original', $fresh->name);
        $this->assertSame('active', $fresh->status);
        $this->assertDatabaseCount('audit_logs', 0);
    }

    public function test_super_admin_suspends_then_admin_writes_get_403_institute_suspended_and_super_admin_reactivates(): void
    {
        $institute = $this->makeInstitute();
        $admin = $this->adminOf($institute);
        $superAdmin = $this->superAdmin();

        $this->loginAs($superAdmin);
        $this->spa('PATCH', "/api/v1/institutes/{$institute->uuid}", ['status' => 'suspended'])->assertOk()
            ->assertJsonPath('data.status', 'suspended');

        $rows = $this->auditRows(AuditAction::INSTITUTE_STATUS_CHANGED);
        $this->assertCount(1, $rows);
        $this->assertEquals($superAdmin->getKey(), $rows[0]['user_id']);
        $this->assertSame(['status' => 'active'], $rows[0]['old_values']);
        $this->assertSame(['status' => 'suspended'], $rows[0]['new_values']);
        $this->assertSame([], $this->auditRows(AuditAction::INSTITUTE_UPDATED));

        $this->loginAs($admin);
        $this->spa('PATCH', "/api/v1/institutes/{$institute->uuid}", ['name' => 'Fictional Renamed'])
            ->assertForbidden()
            ->assertExactJson(['message' => 'This institute is suspended.', 'reason' => 'institute_suspended']);
        $this->spa('GET', "/api/v1/institutes/{$institute->uuid}")->assertOk();

        $this->loginAs($superAdmin);
        $this->spa('PATCH', "/api/v1/institutes/{$institute->uuid}", ['status' => 'active', 'city' => 'Kandy'])->assertOk()
            ->assertJsonPath('data.status', 'active');

        $this->assertCount(2, $this->auditRows(AuditAction::INSTITUTE_STATUS_CHANGED));
        $this->assertCount(1, $this->auditRows(AuditAction::INSTITUTE_UPDATED));
    }

    public function test_owner_id_and_unknown_fields_are_rejected_with_422(): void
    {
        $institute = $this->makeInstitute(null, ['name' => 'Fictional Original']);
        $this->loginAs($this->adminOf($institute));

        foreach (['owner_user_id' => 1, 'owner_uuid' => 'x', 'uuid' => 'x', 'id' => 1, 'created_at' => '2026-01-01'] as $field => $value) {
            $this->spa('PATCH', "/api/v1/institutes/{$institute->uuid}", ['name' => 'Fictional Renamed', $field => $value])
                ->assertUnprocessable()->assertJsonValidationErrors($field);
        }

        $this->spa('PATCH', "/api/v1/institutes/{$institute->uuid}", ['name' => null])
            ->assertUnprocessable()->assertJsonValidationErrors('name');
        $this->spa('PATCH', "/api/v1/institutes/{$institute->uuid}", ['district' => 'Atlantis'])
            ->assertUnprocessable()->assertJsonValidationErrors('district');

        $this->assertSame('Fictional Original', $institute->fresh()->name);
        $this->assertDatabaseCount('audit_logs', 0);
    }

    public function test_an_unchanged_body_writes_nothing_and_records_no_audit(): void
    {
        $institute = $this->makeInstitute(null, ['name' => 'Fictional Original', 'district' => 'Colombo']);
        $updatedAt = DB::table('institutes')->where('id', $institute->getKey())->value('updated_at');
        $this->loginAs($this->adminOf($institute));

        $this->spa('PATCH', "/api/v1/institutes/{$institute->uuid}", ['name' => 'Fictional Original', 'district' => 'colombo'])
            ->assertOk();
        $this->spa('PATCH', "/api/v1/institutes/{$institute->uuid}", [])->assertOk();

        $this->assertDatabaseCount('audit_logs', 0);
        $this->assertSame($updatedAt, DB::table('institutes')->where('id', $institute->getKey())->value('updated_at'));
    }

    public function test_a_failed_audit_insert_rolls_the_update_back(): void
    {
        $institute = $this->makeInstitute(null, ['name' => 'Fictional Original']);
        $this->loginAs($this->superAdmin());
        $this->mock(AuditLogger::class)->shouldReceive('record')->andThrow(new RuntimeException('Fictional audit outage.'));

        $this->spa('PATCH', "/api/v1/institutes/{$institute->uuid}", ['name' => 'Fictional Renamed', 'status' => 'suspended'])
            ->assertStatus(500);

        $fresh = Institute::query()->findOrFail($institute->getKey());
        $this->assertSame('Fictional Original', $fresh->name);
        $this->assertSame('active', $fresh->status);
    }
}
