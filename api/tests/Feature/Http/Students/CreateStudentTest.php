<?php

namespace Tests\Feature\Http\Students;

use App\Audit\AuditAction;
use App\Audit\AuditLogger;
use App\Models\Institute;
use App\Models\StudentProfile;
use App\Support\LocalClock;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;
use RuntimeException;
use Tests\Feature\Http\Concerns\ApiActors;
use Tests\TestCase;

/**
 * POST /api/v1/institutes/{uuid}/students (contract v0.6 §8.1; C6). Fictional students only.
 */
class CreateStudentTest extends TestCase
{
    use ApiActors;
    use RefreshDatabase;

    private const STUDENT = [
        'full_name' => 'Sanduni Fernando',
        'grade_code' => 'G11',
        'exam_target_code' => 'OL_2027',
        'medium_code' => 'sin',
        'date_of_birth' => '2011-03-14',
    ];

    protected function setUp(): void
    {
        parent::setUp();

        $this->seedLookups();
    }

    public function test_unauthenticated_requests_get_401(): void
    {
        $this->spa('POST', $this->url($this->makeInstitute()), self::STUDENT)->assertUnauthorized();
        $this->assertDatabaseCount('student_profiles', 0);
    }

    public function test_unrelated_callers_get_404_and_teacher_only_and_super_admin_get_403(): void
    {
        $institute = $this->makeInstitute();

        $this->loginAs($this->adminOf($this->makeInstitute()));
        $this->spa('POST', $this->url($institute), self::STUDENT)->assertNotFound()->assertExactJson(['message' => 'Not found.']);
        $this->spa('POST', $this->url($institute), ['nic' => '200012345678'])->assertNotFound();

        foreach ([$this->teacherAt($institute), $this->superAdmin()] as $user) {
            $this->loginAs($user);
            $this->spa('POST', $this->url($institute), self::STUDENT)->assertForbidden()->assertJsonMissingPath('reason');
        }

        $this->assertDatabaseCount('student_profiles', 0);
    }

    public function test_a_suspended_institute_refuses_new_students_with_institute_suspended(): void
    {
        $institute = $this->makeInstitute();
        $staff = $this->staffOf($institute);
        $this->suspend($institute);
        $this->loginAs($staff);

        $this->spa('POST', $this->url($institute), self::STUDENT)
            ->assertForbidden()
            ->assertExactJson(['message' => 'This institute is suspended.', 'reason' => 'institute_suspended']);
        $this->assertDatabaseCount('student_profiles', 0);
    }

    public function test_staff_create_a_student_with_server_set_institute_creator_and_no_account(): void
    {
        $institute = $this->makeInstitute();
        $staff = $this->staffOf($institute);
        $this->loginAs($staff);

        $response = $this->spa('POST', $this->url($institute), [...self::STUDENT, 'full_name' => "  Sanduni \u{00A0} Fernando "])
            ->assertCreated()
            ->assertHeader('Cache-Control', 'no-store, private');

        $student = StudentProfile::query()->sole();
        $this->assertSame([
            'uuid' => $student->uuid,
            'full_name' => 'Sanduni Fernando',
            'grade' => ['code' => 'G11', 'name' => 'Grade 11'],
            'exam_target' => ['code' => 'OL_2027', 'name' => 'O/L 2027'],
            'medium' => ['code' => 'sin', 'name' => 'Sinhala'],
            'date_of_birth' => '2011-03-14',
            'has_user_account' => false,
            'active_guardian_count' => 0,
            'created_at' => $response->json('data.created_at'),
        ], $response->json('data'));
        $this->assertMatchesRegularExpression('/^\d{4}-\d{2}-\d{2}T\d{2}:\d{2}:\d{2}Z$/', $response->json('data.created_at'));
        $this->assertNoInternalIds($response);

        $this->assertEquals($institute->getKey(), $student->managing_institute_id);
        $this->assertEquals($staff->getKey(), $student->created_by_user_id);
        $this->assertNull($student->user_id);
    }

    public function test_an_admin_can_create_a_student_and_optional_fields_may_be_omitted(): void
    {
        $institute = $this->makeInstitute();
        $this->loginAs($this->adminOf($institute));

        $this->spa('POST', $this->url($institute), ['full_name' => 'Kamal Perera', 'grade_code' => 'AL2', 'medium_code' => 'eng'])
            ->assertCreated()
            ->assertJsonPath('data.exam_target', null)
            ->assertJsonPath('data.date_of_birth', null)
            ->assertJsonPath('data.grade.code', 'AL2');
    }

    public function test_the_audit_row_has_codes_and_actor_but_never_the_name_or_date_of_birth(): void
    {
        $institute = $this->makeInstitute();
        $staff = $this->staffOf($institute);
        $this->loginAs($staff);

        $uuid = $this->spa('POST', $this->url($institute), self::STUDENT)->assertCreated()->json('data.uuid');

        $rows = $this->auditRows(AuditAction::STUDENT_CREATED);
        $this->assertCount(1, $rows);
        $this->assertEquals($staff->getKey(), $rows[0]['user_id']);
        $this->assertEquals($institute->getKey(), $rows[0]['institute_id']);
        $this->assertSame('student_profile', $rows[0]['auditable_type']);
        $this->assertSame('127.0.0.1', $rows[0]['ip_address']);
        $this->assertNull($rows[0]['old_values']);
        $this->assertEquals([
            'student_uuid' => $uuid, 'institute_uuid' => $institute->uuid,
            'grade_code' => 'G11', 'medium_code' => 'sin', 'exam_target_code' => 'OL_2027',
        ], $rows[0]['new_values']);

        $stored = (string) DB::table('audit_logs')->where('action', AuditAction::STUDENT_CREATED)->value('new_values');
        foreach (['Sanduni', 'Fernando', '2011-03-14', 'full_name', 'date_of_birth'] as $forbidden) {
            $this->assertStringNotContainsString($forbidden, $stored);
        }
    }

    public function test_student_names_follow_the_person_name_rule_with_a_150_character_limit(): void
    {
        $institute = $this->makeInstitute();
        $this->loginAs($this->adminOf($institute));
        $sinhala150 = str_repeat("\u{0D85}", 150);

        $this->spa('POST', $this->url($institute), [...self::STUDENT, 'full_name' => $sinhala150])->assertCreated()
            ->assertJsonPath('data.full_name', $sinhala150);

        foreach ([str_repeat("\u{0D85}", 151), 'Sanduni 2', "Sanduni\nFernando", "\u{202E}Sanduni", 'S'] as $name) {
            $this->spa('POST', $this->url($institute), [...self::STUDENT, 'full_name' => $name])
                ->assertUnprocessable()->assertJsonValidationErrors('full_name');
        }

        // Rejected, never truncated.
        $this->assertSame(1, StudentProfile::query()->count());
    }

    public function test_unknown_codes_and_future_or_invalid_dates_of_birth_are_rejected_with_422(): void
    {
        $institute = $this->makeInstitute();
        $this->loginAs($this->adminOf($institute));
        $tomorrow = Carbon::now(LocalClock::TIMEZONE)->addDay()->toDateString();

        $cases = [
            ['grade_code', 'G99'],
            ['grade_code', 'g11'],
            ['grade_code', null],
            ['medium_code', 'SIN'],
            ['medium_code', 'fr'],
            ['exam_target_code', 'OL_1999'],
            ['date_of_birth', $tomorrow],
            ['date_of_birth', '2011-02-30'],
            ['date_of_birth', '14/03/2011'],
        ];

        foreach ($cases as [$field, $value]) {
            $this->spa('POST', $this->url($institute), [...self::STUDENT, $field => $value])
                ->assertUnprocessable()->assertJsonValidationErrors($field);
        }

        // Today (Asia/Colombo) is allowed.
        $this->spa('POST', $this->url($institute), [...self::STUDENT, 'date_of_birth' => LocalClock::today()])->assertCreated();
        $this->assertSame(1, StudentProfile::query()->count());
    }

    public function test_server_owned_identity_and_document_fields_are_rejected_with_422(): void
    {
        $institute = $this->makeInstitute();
        $admin = $this->adminOf($institute);
        $this->loginAs($admin);

        $fields = [
            'managing_institute_id' => $this->makeInstitute()->getKey(),
            'institute_uuid' => $institute->uuid,
            'created_by_user_id' => $admin->getKey(),
            'user_id' => $admin->getKey(),
            'uuid' => '6b0e3f9a-1c2d-4e5f-8a9b-0c1d2e3f4a5b',
            'nic' => '200012345678',
            'photo' => 'data:image/png;base64,AAAA',
        ];

        foreach ($fields as $field => $value) {
            $this->spa('POST', $this->url($institute), [...self::STUDENT, $field => $value])
                ->assertUnprocessable()->assertJsonValidationErrors($field);
        }

        $this->assertDatabaseCount('student_profiles', 0);
    }

    public function test_a_failed_audit_insert_rolls_the_student_back(): void
    {
        $institute = $this->makeInstitute();
        $this->loginAs($this->staffOf($institute));
        $this->mock(AuditLogger::class)->shouldReceive('record')->andThrow(new RuntimeException('Fictional audit outage.'));

        $this->spa('POST', $this->url($institute), self::STUDENT)->assertStatus(500);

        $this->assertDatabaseCount('student_profiles', 0);
    }

    private function url(Institute $institute): string
    {
        return "/api/v1/institutes/{$institute->uuid}/students";
    }
}
