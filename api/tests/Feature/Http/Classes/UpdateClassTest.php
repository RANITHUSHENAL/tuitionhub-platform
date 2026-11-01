<?php

namespace Tests\Feature\Http\Classes;

use App\Audit\AuditAction;
use App\Audit\AuditLogger;
use App\Models\ClassFeeVersion;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use RuntimeException;
use Tests\Feature\Http\Concerns\ClassFixtures;
use Tests\TestCase;

/**
 * PATCH /api/v1/classes/{uuid} (contract v0.8 §7.4; D-024 Q46; D-026).
 */
class UpdateClassTest extends TestCase
{
    use ClassFixtures;
    use RefreshDatabase;

    public function test_unauthenticated_requests_get_401(): void
    {
        $class = $this->makeClass($this->makeInstitute());

        $this->spa('PATCH', $this->classUrl($class), ['title' => 'Fictional'])->assertUnauthorized();
    }

    public function test_staff_and_teachers_get_403_unrelated_callers_404_and_suspension_blocks_admins_only(): void
    {
        $institute = $this->makeInstitute();
        $class = $this->makeClass($institute);

        foreach ([$this->staffOf($institute), $this->teacherUserOf($class)] as $user) {
            $this->loginAs($user);
            $this->spa('PATCH', $this->classUrl($class), ['title' => 'Changed'])->assertForbidden();
        }

        $this->loginAs($this->adminOf($this->makeInstitute()));
        $this->spa('PATCH', $this->classUrl($class), ['title' => 'Changed'])->assertNotFound();

        $admin = $this->adminOf($institute);
        $this->suspend($institute);
        $this->loginAs($admin);
        $this->spa('PATCH', $this->classUrl($class), ['title' => 'Changed'])->assertForbidden()->assertJsonPath('reason', 'institute_suspended');

        $this->loginAs($this->superAdmin());
        $this->spa('PATCH', $this->classUrl($class), ['title' => 'Changed by operator'])->assertOk()->assertJsonPath('data.title', 'Changed by operator');
    }

    public function test_an_admin_updates_fields_and_the_audit_row_records_only_the_changes(): void
    {
        $institute = $this->makeInstitute();
        $class = $this->makeClass($institute, ['title' => 'Old Title', 'max_students' => 30]);
        $admin = $this->adminOf($institute);
        $newTeacher = $this->teacherProfileOf($this->teacherAt($institute));
        $this->loginAs($admin);

        $this->spa('PATCH', $this->classUrl($class), [
            'title' => 'New Title', 'max_students' => 45, 'public_listing' => true,
            'description' => 'A private description.', 'teacher_uuid' => $newTeacher->uuid, 'stream_code' => 'SCI_MATH',
        ])
            ->assertOk()
            ->assertJsonPath('data.title', 'New Title')
            ->assertJsonPath('data.max_students', 45)
            ->assertJsonPath('data.public_listing', true)
            ->assertJsonPath('data.teacher.uuid', $newTeacher->uuid)
            ->assertJsonPath('data.stream.code', 'SCI_MATH');

        $rows = $this->auditRows(AuditAction::CLASS_UPDATED);
        $this->assertCount(1, $rows);
        $this->assertEquals($admin->getKey(), $rows[0]['user_id']);
        $this->assertEquals(['title' => 'Old Title', 'max_students' => 30, 'public_listing' => false, 'teacher_uuid' => $this->teacherProfileOf($this->teacherUserOf($class))->uuid, 'stream_code' => null], $rows[0]['old_values']);
        $this->assertEquals([
            'class_uuid' => $class->uuid, 'title' => 'New Title', 'max_students' => 45, 'public_listing' => true,
            'teacher_uuid' => $newTeacher->uuid, 'stream_code' => 'SCI_MATH', 'description_changed' => true,
        ], $rows[0]['new_values']);
        $this->assertStringNotContainsString('private description', json_encode(DB::table('audit_logs')->get()));
    }

    public function test_an_update_that_changes_nothing_writes_nothing(): void
    {
        $institute = $this->makeInstitute();
        $class = $this->makeClass($institute, ['title' => 'Same Title', 'max_students' => 30]);
        $this->loginAs($this->adminOf($institute));

        $this->spa('PATCH', $this->classUrl($class), ['title' => 'Same Title', 'max_students' => 30, 'starts_on' => '2026-01-01'])->assertOk();
        $this->assertCount(0, $this->auditRows(AuditAction::CLASS_UPDATED));
    }

    public function test_a_fee_change_appends_a_version_and_closes_the_open_one_the_day_before(): void
    {
        $institute = $this->makeInstitute();
        $class = $this->makeClass($institute);
        $this->loginAs($this->adminOf($institute));
        $fee = ['amount' => '2750.00', 'currency' => 'LKR', 'billing_cycle' => 'monthly', 'effective_from' => '2026-12-01'];

        $this->spa('PATCH', $this->classUrl($class), ['fee' => $fee])
            ->assertOk()
            ->assertJsonPath('data.current_fee.amount', '2500.00');

        $versions = ClassFeeVersion::query()->where('class_id', $class->getKey())->orderBy('effective_from')->get();
        $this->assertCount(2, $versions);
        $this->assertSame(['2500.00', '2026-11-30'], [$versions[0]->amount, $versions[0]->effective_to->format('Y-m-d')]);
        $this->assertSame(['2750.00', null], [$versions[1]->amount, $versions[1]->effective_to]);

        $audit = $this->auditRows(AuditAction::FEE_VERSION_CREATED);
        $this->assertSame('2026-11-30', $audit[0]['new_values']['previous_effective_to']);
        $this->assertCount(0, $this->auditRows(AuditAction::CLASS_UPDATED));

        // The new version must start after the open version's start (contract §7.4).
        foreach (['2026-12-01', '2026-11-15'] as $from) {
            $this->spa('PATCH', $this->classUrl($class), ['fee' => [...$fee, 'effective_from' => $from]])
                ->assertUnprocessable()->assertJsonValidationErrors('fee.effective_from');
        }

        $this->assertSame(2, ClassFeeVersion::query()->where('class_id', $class->getKey())->count());
    }

    public function test_capacity_cannot_drop_below_the_active_enrolment_count(): void
    {
        $institute = $this->makeInstitute();
        $class = $this->makeClass($institute, ['max_students' => 5]);
        $this->enrol($class);
        $this->enrol($class);
        $this->withdrawEnrollment($this->enrol($class)->getKey(), '2026-11-01');
        $this->loginAs($this->adminOf($institute));

        $this->spa('PATCH', $this->classUrl($class), ['max_students' => 1])
            ->assertStatus(409)
            ->assertExactJson(['message' => 'The class has more active enrolments than this capacity.', 'reason' => 'max_students_below_active']);

        // Withdrawn enrolments do not count.
        $this->spa('PATCH', $this->classUrl($class), ['max_students' => 2])->assertOk()->assertJsonPath('data.max_students', 2);
    }

    public function test_status_transitions_follow_draft_active_archived_and_archived_is_final(): void
    {
        $institute = $this->makeInstitute();
        $draft = $this->makeClass($institute, ['status' => 'draft']);
        $active = $this->makeClass($institute);
        $this->loginAs($this->adminOf($institute));

        $this->spa('PATCH', $this->classUrl($active), ['status' => 'draft'])
            ->assertStatus(409)->assertExactJson(['message' => 'This status change is not allowed.', 'reason' => 'invalid_status_transition']);

        $this->spa('PATCH', $this->classUrl($draft), ['status' => 'active'])->assertOk()->assertJsonPath('data.status', 'active');
        $this->spa('PATCH', $this->classUrl($draft), ['status' => 'archived'])->assertOk()->assertJsonPath('data.status', 'archived');

        foreach ([['status' => 'active'], ['title' => 'Revived'], ['fee' => ['amount' => '1.00', 'currency' => 'LKR', 'billing_cycle' => 'monthly', 'effective_from' => '2027-01-01']]] as $change) {
            $this->spa('PATCH', $this->classUrl($draft), $change)
                ->assertStatus(409)->assertExactJson(['message' => 'This class is archived and cannot be changed.', 'reason' => 'class_archived']);
        }
    }

    public function test_dates_must_stay_ordered_and_contain_every_active_enrolment(): void
    {
        $institute = $this->makeInstitute();
        $class = $this->makeClass($institute, ['starts_on' => '2026-01-01']);
        $this->enrol($class, overrides: ['enrolled_on' => '2026-03-01']);
        $this->loginAs($this->adminOf($institute));

        $this->spa('PATCH', $this->classUrl($class), ['ends_on' => '2025-12-31'])->assertUnprocessable()->assertJsonValidationErrors('ends_on');
        $this->spa('PATCH', $this->classUrl($class), ['starts_on' => '2026-04-01'])->assertUnprocessable()->assertJsonValidationErrors('starts_on');
        $this->spa('PATCH', $this->classUrl($class), ['ends_on' => '2026-02-28'])->assertUnprocessable()->assertJsonValidationErrors('ends_on');

        $this->spa('PATCH', $this->classUrl($class), ['starts_on' => '2026-03-01', 'ends_on' => '2026-12-31'])
            ->assertOk()->assertJsonPath('data.starts_on', '2026-03-01')->assertJsonPath('data.ends_on', '2026-12-31');
    }

    public function test_a_new_teacher_needs_an_active_assignment_and_unknown_fields_are_rejected(): void
    {
        $institute = $this->makeInstitute();
        $class = $this->makeClass($institute);
        $this->loginAs($this->adminOf($institute));

        $this->spa('PATCH', $this->classUrl($class), ['teacher_uuid' => $this->teacherProfileFor()->uuid])
            ->assertUnprocessable()->assertJsonValidationErrors('teacher_uuid');

        foreach (['institute_uuid' => 'x', 'uuid' => 'x', 'active_enrollment_count' => 1, 'mode' => 'remote', 'subject_code' => 'phy'] as $field => $value) {
            $this->spa('PATCH', $this->classUrl($class), [$field => $value])->assertUnprocessable()->assertJsonValidationErrors($field);
        }
    }

    public function test_a_failed_audit_insert_rolls_the_update_and_the_fee_version_back(): void
    {
        $institute = $this->makeInstitute();
        $class = $this->makeClass($institute, ['title' => 'Kept Title']);
        $this->loginAs($this->adminOf($institute));
        $this->mock(AuditLogger::class)->shouldReceive('record')->andThrow(new RuntimeException('Fictional audit outage.'));

        $this->spa('PATCH', $this->classUrl($class), [
            'title' => 'Lost Title',
            'fee' => ['amount' => '2750.00', 'currency' => 'LKR', 'billing_cycle' => 'monthly', 'effective_from' => '2026-12-01'],
        ])->assertStatus(500);

        $this->assertSame('Kept Title', $class->fresh()->title);
        $this->assertSame(1, ClassFeeVersion::query()->where('class_id', $class->getKey())->count());
        $this->assertNull(ClassFeeVersion::query()->where('class_id', $class->getKey())->value('effective_to'));
    }
}
