<?php

namespace Tests\Feature\Services;

use App\Audit\AuditAction;
use App\Authorization\TenantAccess;
use App\Exceptions\DomainRuleException;
use App\Models\AuditLog;
use App\Models\TeacherAssignment;
use App\Models\User;
use App\Services\TeacherAssignmentService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Tests\Feature\Concerns\AppFixtures;
use Tests\TestCase;

/**
 * VP-20 and VP-21 (teachers): an ended assignment grants nothing; history is kept.
 */
class TeacherAssignmentServiceTest extends TestCase
{
    use AppFixtures;
    use RefreshDatabase;

    public function test_vp20_ending_an_assignment_removes_teaching_access_and_is_audited(): void
    {
        [$user, $teacher, $institute] = $this->teacherAt();
        $assignment = TeacherAssignment::query()->findOrFail($this->insertAssignment($teacher, $institute->getKey()));
        $this->assertTrue($this->access()->isActiveTeacherAt($user, $institute));

        app(TeacherAssignmentService::class)->end($assignment, '2026-10-20');

        $this->assertFalse($this->access()->isActiveTeacherAt($user, $institute));
        $this->assertCount(0, $this->access()->activeTeachingInstitutes($user));
        $ended = TeacherAssignment::query()->findOrFail($assignment->getKey());
        $this->assertSame('former', $ended->status);
        $this->assertSame('2026-10-20', $ended->left_on->toDateString());

        $audit = AuditLog::query()->where('action', AuditAction::TEACHER_ASSIGNMENT_ENDED)->sole();
        $this->assertSame('teacher_assignment', $audit->auditable_type);
        $this->assertSame(DB::table('teacher_profiles')->where('id', $teacher)->value('uuid'), $audit->new_values['teacher_uuid']);
    }

    public function test_only_active_assignments_can_be_ended(): void
    {
        [, $teacher, $institute] = $this->teacherAt();
        $assignment = TeacherAssignment::query()->findOrFail($this->insertAssignment($teacher, $institute->getKey()));
        app(TeacherAssignmentService::class)->end($assignment, '2026-10-20');

        $this->assertDomainRule(DomainRuleException::NOT_ACTIVE, fn () => app(TeacherAssignmentService::class)->end($assignment, '2026-10-21'));
    }

    public function test_vp21_a_new_active_assignment_after_ending_grants_access_again(): void
    {
        [$user, $teacher, $institute] = $this->teacherAt();
        $assignment = TeacherAssignment::query()->findOrFail($this->insertAssignment($teacher, $institute->getKey()));
        app(TeacherAssignmentService::class)->end($assignment, '2026-10-20');

        $this->insertAssignment($teacher, $institute->getKey(), ['joined_on' => '2026-10-25']);

        $this->assertTrue($this->access()->isActiveTeacherAt($user, $institute));
        $this->assertSame('former', TeacherAssignment::query()->findOrFail($assignment->getKey())->status);
    }

    /**
     * @return array{0: User, 1: int, 2: \App\Models\Institute}
     */
    private function teacherAt(): array
    {
        $user = $this->makeUser();

        return [$user, $this->insertTeacherProfile(['user_id' => $user->getKey()]), $this->makeInstitute()];
    }

    private function access(): TenantAccess
    {
        return app(TenantAccess::class);
    }
}
