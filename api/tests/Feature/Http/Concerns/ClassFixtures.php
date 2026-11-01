<?php

namespace Tests\Feature\Http\Concerns;

use App\Models\ClassEnrollment;
use App\Models\Institute;
use App\Models\StudentProfile;
use App\Models\TeacherProfile;
use App\Models\TuitionClass;
use App\Models\User;
use Carbon\CarbonImmutable;

/**
 * Fictional classes, fee versions and enrolments for the E3a tests (contract v0.8 §6.4, §7, §8).
 * The clock is pinned to 2026-11-10 (Asia/Colombo), so date rules are deterministic.
 */
trait ClassFixtures
{
    use ApiActors;

    protected const TODAY = '2026-11-10';

    /**
     * Called automatically by Laravel's test lifecycle.
     */
    protected function setUpClassFixtures(): void
    {
        $this->travelTo(CarbonImmutable::parse(self::TODAY.' 06:00:00', 'UTC'));
        $this->seedLookups();
    }

    /**
     * An active class taught by $teacher (a new teacher with an active assignment when omitted),
     * with one open fee version in force since 2026-01-01.
     */
    protected function makeClass(Institute $institute, array $overrides = [], ?User $teacher = null, string $fee = '2500.00'): TuitionClass
    {
        $teacher ??= $this->teacherAt($institute);

        $id = $this->insertClass($institute->getKey(), array_merge([
            'teacher_id' => $this->teacherProfileOf($teacher)->getKey(),
            'starts_on' => '2026-01-01',
            'status' => 'active',
        ], $overrides));
        $this->insertFeeVersion($id, ['amount' => $fee, 'effective_from' => '2026-01-01']);

        return TuitionClass::query()->findOrFail($id);
    }

    protected function teacherProfileOf(User $user): TeacherProfile
    {
        return TeacherProfile::query()->where('user_id', $user->getKey())->firstOrFail();
    }

    protected function teacherUserOf(TuitionClass $class): User
    {
        return User::query()->findOrFail(TeacherProfile::query()->whereKey($class->teacher_id)->value('user_id'));
    }

    protected function makeStudent(Institute $institute): StudentProfile
    {
        return StudentProfile::query()->findOrFail($this->insertStudentProfile($institute->getKey()));
    }

    protected function enrol(TuitionClass $class, ?StudentProfile $student = null, array $overrides = []): ClassEnrollment
    {
        $student ??= $this->makeStudent(Institute::query()->findOrFail($class->institute_id));

        return ClassEnrollment::query()->findOrFail(
            $this->insertEnrollment($class->getKey(), $student->getKey(), $class->institute_id, $overrides),
        );
    }

    protected function classUrl(TuitionClass $class): string
    {
        return "/api/v1/classes/{$class->uuid}";
    }

    protected function enrollmentsUrl(TuitionClass $class): string
    {
        return "/api/v1/classes/{$class->uuid}/enrollments";
    }
}
