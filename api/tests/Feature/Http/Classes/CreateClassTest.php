<?php

namespace Tests\Feature\Http\Classes;

use App\Audit\AuditAction;
use App\Audit\AuditLogger;
use App\Models\ClassFeeVersion;
use App\Models\TeacherProfile;
use App\Models\TuitionClass;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use RuntimeException;
use Tests\Feature\Http\Concerns\ClassFixtures;
use Tests\TestCase;

/**
 * POST /api/v1/institutes/{uuid}/classes (contract v0.8 §7.1; D-024, D-026). Fictional data only.
 */
class CreateClassTest extends TestCase
{
    use ClassFixtures;
    use RefreshDatabase;

    public function test_unauthenticated_requests_get_401(): void
    {
        $this->spa('POST', $this->url($this->makeInstitute()), [])->assertUnauthorized();
    }

    public function test_unrelated_callers_get_404_and_staff_teachers_and_super_admin_get_403(): void
    {
        $institute = $this->makeInstitute();
        $teacher = $this->teacherAt($institute);
        $payload = $this->payload($this->teacherProfileOf($teacher));

        $this->loginAs($this->adminOf($this->makeInstitute()));
        $this->spa('POST', $this->url($institute), $payload)->assertNotFound()->assertExactJson(['message' => 'Not found.']);

        foreach ([$this->staffOf($institute), $teacher, $this->superAdmin()] as $user) {
            $this->loginAs($user);
            $this->spa('POST', $this->url($institute), $payload)->assertForbidden()->assertJsonMissingPath('reason');
        }

        $this->assertDatabaseCount('classes', 0);
    }

    public function test_a_suspended_institute_refuses_new_classes(): void
    {
        $institute = $this->makeInstitute();
        $payload = $this->payload($this->teacherProfileOf($this->teacherAt($institute)));
        $admin = $this->adminOf($institute);
        $this->suspend($institute);
        $this->loginAs($admin);

        $this->spa('POST', $this->url($institute), $payload)
            ->assertForbidden()
            ->assertExactJson(['message' => 'This institute is suspended.', 'reason' => 'institute_suspended']);
        $this->assertDatabaseCount('classes', 0);
    }

    public function test_an_admin_creates_a_class_with_its_first_fee_version(): void
    {
        $institute = $this->makeInstitute(overrides: ['name' => 'Lakmina Academy', 'district' => 'Colombo', 'city' => 'Nugegoda']);
        $teacher = $this->teacherProfileOf($this->teacherAt($institute));
        $admin = $this->adminOf($institute);
        $this->loginAs($admin);

        $response = $this->spa('POST', $this->url($institute), $this->payload($teacher))
            ->assertCreated()
            ->assertHeader('Cache-Control', 'no-store, private');

        $class = TuitionClass::query()->sole();
        $this->assertSame([
            'uuid' => $class->uuid,
            'title' => 'A/L Combined Maths 2027 — Theory',
            'description' => "Weekly theory class.\nBring a calculator.",
            'status' => 'draft',
            'mode' => 'in_person',
            'max_students' => 60,
            'active_enrollment_count' => 0,
            'starts_on' => '2026-11-01',
            'ends_on' => null,
            'public_listing' => true,
            'institute' => ['uuid' => $institute->uuid, 'name' => 'Lakmina Academy', 'district' => 'Colombo', 'city' => 'Nugegoda'],
            'teacher' => ['uuid' => $teacher->uuid, 'display_name' => $teacher->display_name],
            'subject' => ['code' => 'COMBINED_MATH', 'name' => 'Combined Mathematics'],
            'grade' => ['code' => 'AL2', 'name' => 'A/L Year 2 (Grade 13)'],
            'stream' => ['code' => 'SCI_MATH', 'name' => 'Physical Science'],
            'exam_target' => ['code' => 'AL_2027', 'name' => 'A/L 2027'],
            'medium' => ['code' => 'sin', 'name' => 'Sinhala'],
            'syllabus' => ['code' => 'local', 'name' => 'Local (National)'],
            'current_fee' => ['amount' => '2500.00', 'currency' => 'LKR', 'billing_cycle' => 'monthly', 'effective_from' => '2026-11-01'],
            'created_at' => $response->json('data.created_at'),
            'updated_at' => $response->json('data.updated_at'),
        ], $response->json('data'));
        $this->assertNoInternalIds($response);

        $fee = ClassFeeVersion::query()->sole();
        $this->assertEquals($class->getKey(), $fee->class_id);
        $this->assertNull($fee->effective_to);
        $this->assertEquals($admin->getKey(), $fee->created_by_user_id);
        $this->assertEquals($institute->getKey(), $class->institute_id);
    }

    public function test_both_audit_rows_are_written_without_the_description(): void
    {
        $institute = $this->makeInstitute();
        $teacher = $this->teacherProfileOf($this->teacherAt($institute));
        $admin = $this->adminOf($institute);
        $this->loginAs($admin);

        $uuid = $this->spa('POST', $this->url($institute), $this->payload($teacher, ['stream_code' => null]))->assertCreated()->json('data.uuid');

        $created = $this->auditRows(AuditAction::CLASS_CREATED);
        $this->assertCount(1, $created);
        $this->assertEquals($admin->getKey(), $created[0]['user_id']);
        $this->assertSame('class', $created[0]['auditable_type']);
        $this->assertEquals([
            'class_uuid' => $uuid, 'institute_uuid' => $institute->uuid, 'teacher_uuid' => $teacher->uuid,
            'title' => 'A/L Combined Maths 2027 — Theory', 'status' => 'draft', 'mode' => 'in_person', 'max_students' => 60,
            'starts_on' => '2026-11-01', 'ends_on' => null, 'public_listing' => true,
            'subject_code' => 'COMBINED_MATH', 'grade_code' => 'AL2', 'stream_code' => null,
            'exam_target_code' => 'AL_2027', 'medium_code' => 'sin', 'syllabus_code' => 'local',
        ], $created[0]['new_values']);

        $fee = $this->auditRows(AuditAction::FEE_VERSION_CREATED);
        $this->assertCount(1, $fee);
        $this->assertSame('class_fee_version', $fee[0]['auditable_type']);
        $this->assertEquals([
            'class_uuid' => $uuid, 'amount' => '2500.00', 'currency' => 'LKR', 'billing_cycle' => 'monthly',
            'effective_from' => '2026-11-01', 'previous_effective_to' => null,
        ], $fee[0]['new_values']);

        $this->assertStringNotContainsString('calculator', json_encode(DB::table('audit_logs')->get()));
    }

    public function test_the_teacher_needs_an_active_assignment_at_this_institute(): void
    {
        $institute = $this->makeInstitute();
        $admin = $this->adminOf($institute);
        $ended = $this->teacherAt($institute, ['status' => 'former', 'left_on' => '2026-10-01']);
        $elsewhere = $this->teacherAt($this->makeInstitute());
        $unassigned = $this->teacherProfileFor();
        $this->loginAs($admin);

        foreach ([$this->teacherProfileOf($ended), $this->teacherProfileOf($elsewhere), $unassigned] as $teacher) {
            $this->spa('POST', $this->url($institute), $this->payload($teacher))
                ->assertUnprocessable()
                ->assertJsonPath('errors.teacher_uuid.0', 'The selected teacher has no active assignment at this institute.');
        }

        foreach (['not-a-uuid', '6b0e3f9a-1c2d-4e5f-8a9b-0c1d2e3f4a5b', null] as $uuid) {
            $this->spa('POST', $this->url($institute), [...$this->payload($unassigned), 'teacher_uuid' => $uuid])
                ->assertUnprocessable()->assertJsonValidationErrors('teacher_uuid');
        }

        $this->assertDatabaseCount('classes', 0);
    }

    public function test_field_rules_lookups_and_dates_are_validated(): void
    {
        $institute = $this->makeInstitute();
        $teacher = $this->teacherProfileOf($this->teacherAt($institute));
        $this->loginAs($this->adminOf($institute));

        $cases = [
            ['title', ''], ['title', str_repeat('t', 256)], ['title', "Bad\u{202E}title"],
            ['subject_code', 'combined_math'], ['grade_code', 'G99'], ['stream_code', 'NOPE'],
            ['exam_target_code', null], ['medium_code', 'SIN'], ['syllabus_code', 'Local'],
            ['mode', 'remote'], ['max_students', 0], ['max_students', 10001], ['max_students', 'many'],
            ['starts_on', '2026-02-30'], ['ends_on', '01/12/2026'], ['ends_on', '2026-10-31'],
            ['status', 'archived'], ['public_listing', 'yes'],
        ];

        foreach ($cases as [$field, $value]) {
            $this->spa('POST', $this->url($institute), [...$this->payload($teacher), $field => $value])
                ->assertUnprocessable()->assertJsonValidationErrors($field);
        }

        $this->assertDatabaseCount('classes', 0);
    }

    public function test_the_fee_is_required_and_money_is_a_positive_two_decimal_lkr_string(): void
    {
        $institute = $this->makeInstitute();
        $teacher = $this->teacherProfileOf($this->teacherAt($institute));
        $this->loginAs($this->adminOf($institute));
        $fee = $this->payload($teacher)['fee'];

        $this->spa('POST', $this->url($institute), array_diff_key($this->payload($teacher), ['fee' => true]))
            ->assertUnprocessable()->assertJsonValidationErrors('fee');

        $cases = [
            ['fee.amount', ['amount' => '0.00']], ['fee.amount', ['amount' => '2500']], ['fee.amount', ['amount' => 2500.0]],
            ['fee.amount', ['amount' => '-1.00']], ['fee.amount', ['amount' => '2500.001']], ['fee.amount', ['amount' => '02500.00']],
            ['fee.currency', ['currency' => 'USD']], ['fee.billing_cycle', ['billing_cycle' => 'weekly']],
            ['fee.effective_from', ['effective_from' => 'tomorrow']], ['fee', ['discount' => '10.00']],
        ];

        foreach ($cases as [$field, $change]) {
            $this->spa('POST', $this->url($institute), [...$this->payload($teacher), 'fee' => [...$fee, ...$change]])
                ->assertUnprocessable()->assertJsonValidationErrors($field);
        }

        $this->spa('POST', $this->url($institute), [...$this->payload($teacher), 'fee' => [...$fee, 'amount' => '0.01', 'billing_cycle' => 'one_time']])
            ->assertCreated()->assertJsonPath('data.current_fee.amount', '0.01');
    }

    public function test_server_owned_and_unknown_fields_are_rejected(): void
    {
        $institute = $this->makeInstitute();
        $teacher = $this->teacherProfileOf($this->teacherAt($institute));
        $this->loginAs($this->adminOf($institute));

        foreach (['institute_id' => 1, 'institute_uuid' => $institute->uuid, 'teacher_id' => 1, 'uuid' => '6b0e3f9a-1c2d-4e5f-8a9b-0c1d2e3f4a5b', 'active_enrollment_count' => 3, 'boost' => true] as $field => $value) {
            $this->spa('POST', $this->url($institute), [...$this->payload($teacher), $field => $value])
                ->assertUnprocessable()->assertJsonValidationErrors($field);
        }

        $this->assertDatabaseCount('classes', 0);
    }

    public function test_optional_fields_default_and_an_active_class_can_be_created(): void
    {
        $institute = $this->makeInstitute();
        $teacher = $this->teacherProfileOf($this->teacherAt($institute));
        $this->loginAs($this->adminOf($institute));
        $payload = array_diff_key($this->payload($teacher), array_flip(['description', 'stream_code', 'ends_on', 'public_listing', 'status']));

        $this->spa('POST', $this->url($institute), $payload)
            ->assertCreated()
            ->assertJsonPath('data.status', 'draft')
            ->assertJsonPath('data.public_listing', false)
            ->assertJsonPath('data.stream', null)
            ->assertJsonPath('data.description', null);

        $this->spa('POST', $this->url($institute), [...$payload, 'status' => 'active', 'ends_on' => '2026-11-01'])
            ->assertCreated()->assertJsonPath('data.status', 'active')->assertJsonPath('data.ends_on', '2026-11-01');
    }

    public function test_a_failed_audit_insert_rolls_the_class_and_fee_back(): void
    {
        $institute = $this->makeInstitute();
        $teacher = $this->teacherProfileOf($this->teacherAt($institute));
        $this->loginAs($this->adminOf($institute));
        $this->mock(AuditLogger::class)->shouldReceive('record')->andThrow(new RuntimeException('Fictional audit outage.'));

        $this->spa('POST', $this->url($institute), $this->payload($teacher))->assertStatus(500);

        $this->assertDatabaseCount('classes', 0);
        $this->assertDatabaseCount('class_fee_versions', 0);
    }

    private function url($institute): string
    {
        return "/api/v1/institutes/{$institute->uuid}/classes";
    }

    /**
     * @return array<string, mixed>
     */
    private function payload(TeacherProfile $teacher, array $overrides = []): array
    {
        return array_merge([
            'title' => 'A/L Combined Maths 2027 — Theory',
            'description' => "Weekly theory class.\nBring a calculator.",
            'teacher_uuid' => $teacher->uuid,
            'subject_code' => 'COMBINED_MATH',
            'grade_code' => 'AL2',
            'stream_code' => 'SCI_MATH',
            'exam_target_code' => 'AL_2027',
            'medium_code' => 'sin',
            'syllabus_code' => 'local',
            'mode' => 'in_person',
            'max_students' => 60,
            'starts_on' => '2026-11-01',
            'ends_on' => null,
            'public_listing' => true,
            'status' => 'draft',
            'fee' => ['amount' => '2500.00', 'currency' => 'LKR', 'billing_cycle' => 'monthly', 'effective_from' => '2026-11-01'],
        ], $overrides);
    }
}
