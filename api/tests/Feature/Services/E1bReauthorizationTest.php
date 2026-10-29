<?php

namespace Tests\Feature\Services;

use App\Exceptions\DomainRuleException;
use App\Models\Role;
use App\Models\TeacherProfile;
use App\Services\StudentService;
use App\Services\TeacherAssignmentService;
use App\Support\LocalClock;
use Database\Seeders\LookupSeeder;
use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;
use Tests\Feature\Concerns\AppFixtures;
use Tests\TestCase;

/**
 * TeacherAssignmentService::assign and StudentService::create authorize again against the locked
 * institute row (D-021), so changes committed after the request's first check are respected.
 */
class E1bReauthorizationTest extends TestCase
{
    use AppFixtures;
    use RefreshDatabase;

    private const STUDENT = ['full_name' => 'Fictional Student', 'grade_code' => 'G11', 'medium_code' => 'sin'];

    protected function setUp(): void
    {
        parent::setUp();

        $this->seed(LookupSeeder::class);
    }

    public function test_assign_refuses_an_admin_whose_role_was_revoked(): void
    {
        $institute = $this->makeInstitute();
        $admin = $this->makeUser();
        $roleId = $this->insertUserRole($admin->getKey(), Role::INSTITUTE_ADMIN, $institute->getKey());
        $teacher = TeacherProfile::query()->findOrFail($this->insertTeacherProfile());
        $this->useAuditContext($admin->getKey());

        DB::table('user_roles')->where('id', $roleId)->delete();

        try {
            app(TeacherAssignmentService::class)->assign($admin, $institute, $teacher, '2026-10-20');
            $this->fail('Expected the revoked admin to be refused.');
        } catch (AuthorizationException $exception) {
            $this->assertSame(404, $exception->status());
        }

        $this->assertDatabaseCount('teacher_institute', 0);
        $this->assertDatabaseCount('audit_logs', 0);
    }

    public function test_assign_respects_a_suspension_committed_after_the_first_check(): void
    {
        $institute = $this->makeInstitute();
        $admin = $this->makeUser();
        $this->insertUserRole($admin->getKey(), Role::INSTITUTE_ADMIN, $institute->getKey());
        $teacher = TeacherProfile::query()->findOrFail($this->insertTeacherProfile());
        $this->useAuditContext($admin->getKey());

        DB::table('institutes')->where('id', $institute->getKey())->update(['status' => 'suspended']);

        $this->assertDomainRule(DomainRuleException::INSTITUTE_SUSPENDED, fn () => app(TeacherAssignmentService::class)
            ->assign($admin, $institute, $teacher, '2026-10-20'));
        $this->assertDatabaseCount('teacher_institute', 0);
    }

    public function test_assign_rejects_a_future_joined_on_even_when_called_directly(): void
    {
        $institute = $this->makeInstitute();
        $admin = $this->makeUser();
        $this->insertUserRole($admin->getKey(), Role::INSTITUTE_ADMIN, $institute->getKey());
        $teacher = TeacherProfile::query()->findOrFail($this->insertTeacherProfile());
        $this->useAuditContext($admin->getKey());
        $tomorrow = Carbon::now(LocalClock::TIMEZONE)->addDay()->toDateString();

        try {
            app(TeacherAssignmentService::class)->assign($admin, $institute, $teacher, $tomorrow);
            $this->fail('Expected a future joined_on to be refused.');
        } catch (ValidationException $exception) {
            $this->assertArrayHasKey('joined_on', $exception->errors());
        }

        $this->assertDatabaseCount('teacher_institute', 0);
        $this->assertDatabaseCount('audit_logs', 0);
    }

    public function test_student_create_refuses_staff_whose_staff_row_ended(): void
    {
        $institute = $this->makeInstitute();
        $staff = $this->makeUser();
        $this->insertUserRole($staff->getKey(), Role::INSTITUTE_STAFF, $institute->getKey());
        $staffRow = $this->insertStaff($institute->getKey(), $staff->getKey());
        $this->useAuditContext($staff->getKey());

        // The role row alone grants nothing (contract §3): staff access needs an active staff row.
        DB::table('institute_staff')->where('id', $staffRow)->update(['status' => 'former', 'left_on' => '2026-10-20']);

        try {
            app(StudentService::class)->create($staff, $institute, self::STUDENT);
            $this->fail('Expected the former staff member to be refused.');
        } catch (AuthorizationException $exception) {
            $this->assertSame(404, $exception->status());
        }

        $this->assertDatabaseCount('student_profiles', 0);
    }

    public function test_student_create_respects_a_suspension_committed_after_the_first_check(): void
    {
        $institute = $this->makeInstitute();
        $admin = $this->makeUser();
        $this->insertUserRole($admin->getKey(), Role::INSTITUTE_ADMIN, $institute->getKey());
        $this->useAuditContext($admin->getKey());

        DB::table('institutes')->where('id', $institute->getKey())->update(['status' => 'suspended']);

        $this->assertDomainRule(DomainRuleException::INSTITUTE_SUSPENDED, fn () => app(StudentService::class)
            ->create($admin, $institute, self::STUDENT));
        $this->assertDatabaseCount('student_profiles', 0);
    }
}
