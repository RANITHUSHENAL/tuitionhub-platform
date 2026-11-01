<?php

namespace Tests\Feature\Models;

use App\Models\Attendance;
use App\Models\ClassEnrollment;
use App\Models\ClassFeeVersion;
use App\Models\ClassSession;
use App\Models\Concerns\HasPublicUuid;
use App\Models\TuitionClass;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use LogicException;
use Tests\Feature\Concerns\AppFixtures;
use Tests\TestCase;

/**
 * VP-75 and VP-80: the G5/G6 models (docs/SPRINT_SCHEMA.md Part 3, D-024). Fictional data only.
 */
class R3ModelsTest extends TestCase
{
    use AppFixtures;
    use RefreshDatabase;

    public function test_vp75_a_stored_fee_version_never_changes_except_closing_it_once(): void
    {
        $class = $this->insertClass($this->insertInstitute());
        $id = $this->insertFeeVersion($class, ['effective_from' => '2026-11-01']);
        $before = (array) DB::table('class_fee_versions')->where('id', $id)->first();

        $changes = [
            'amount' => '2750.00',
            'currency' => 'USD',
            'billing_cycle' => 'term',
            'effective_from' => '2026-10-01',
            'class_id' => $this->insertClass($this->insertInstitute()),
            'created_by_user_id' => $this->insertUser(),
        ];

        foreach ($changes as $column => $value) {
            $version = ClassFeeVersion::query()->findOrFail($id);

            try {
                $version->forceFill([$column => $value])->save();
                $this->fail("Changing [{$column}] must be refused.");
            } catch (LogicException $exception) {
                $this->assertStringContainsString($column, $exception->getMessage());
            }
        }

        $this->assertEquals($before, (array) DB::table('class_fee_versions')->where('id', $id)->first());

        // Closing the open version (contract §7.4) is the one allowed change, and only once.
        $version = ClassFeeVersion::query()->findOrFail($id);
        $version->forceFill(['effective_to' => '2026-11-30'])->save();
        $this->assertNull(DB::table('class_fee_versions')->where('id', $id)->value('current_flag'));

        $closed = ClassFeeVersion::query()->findOrFail($id);
        $this->expectException(LogicException::class);
        $closed->forceFill(['effective_to' => '2026-12-31'])->save();
    }

    public function test_vp75_fee_versions_are_never_deleted_through_the_model(): void
    {
        $id = $this->insertFeeVersion($this->insertClass($this->insertInstitute()));

        try {
            ClassFeeVersion::query()->findOrFail($id)->delete();
            $this->fail('Deleting a fee version must be refused.');
        } catch (LogicException) {
            $this->assertSame(1, DB::table('class_fee_versions')->where('id', $id)->count());
        }
    }

    public function test_vp80_classes_sessions_and_enrolments_have_public_v4_uuids_and_the_others_do_not(): void
    {
        $institute = $this->insertInstitute();
        $teacher = $this->insertTeacherProfile();
        $lookups = DB::table('classes')->where('id', $this->insertClass($institute, ['teacher_id' => $teacher]))->first();

        $class = new TuitionClass;
        $class->forceFill([
            'uuid' => '00000000-0000-4000-8000-000000000000',
            'institute_id' => $institute,
            'teacher_id' => $teacher,
            'subject_id' => $lookups->subject_id,
            'grade_id' => $lookups->grade_id,
            'exam_target_id' => $lookups->exam_target_id,
            'medium_id' => $lookups->medium_id,
            'syllabus_id' => $lookups->syllabus_id,
            'title' => 'Fictional A/L Combined Maths',
            'mode' => 'in_person',
            'max_students' => 40,
            'starts_on' => '2026-11-01',
            'status' => TuitionClass::STATUS_DRAFT,
        ])->save();

        $this->assertMatchesRegularExpression(self::V4_UUID, $class->uuid);
        $this->assertNotSame('00000000-0000-4000-8000-000000000000', $class->uuid, 'A supplied UUID is never kept.');
        $this->assertSame('uuid', $class->getRouteKeyName());
        $this->assertSame('classes', $class->getTable());

        foreach ([TuitionClass::class, ClassSession::class, ClassEnrollment::class] as $model) {
            $this->assertContains(HasPublicUuid::class, class_uses_recursive($model), $model);
        }

        foreach ([ClassFeeVersion::class, Attendance::class] as $model) {
            $this->assertNotContains(HasPublicUuid::class, class_uses_recursive($model), $model);
        }
    }

    public function test_vp80_serialization_hides_internal_ids_and_keeps_calendar_dates_and_times_local(): void
    {
        $institute = $this->insertInstitute();
        $class = $this->insertClass($institute, ['starts_on' => '2026-11-01', 'ends_on' => '2027-08-31']);
        $session = $this->insertSession($class, ['session_date' => '2026-11-03', 'start_time' => '15:30:00', 'end_time' => '17:30:00']);
        $enrollment = $this->insertEnrollment($class, $this->insertStudentProfile($institute), $institute, ['enrolled_on' => '2026-11-02']);
        $this->insertFeeVersion($class);
        $this->insertAttendance($session, $enrollment, $class, ['marked_at' => '2026-11-03 10:15:00']);

        $serialized = [
            TuitionClass::query()->findOrFail($class)->toArray(),
            ClassSession::query()->findOrFail($session)->toArray(),
            ClassEnrollment::query()->findOrFail($enrollment)->toArray(),
            ClassFeeVersion::query()->where('class_id', $class)->sole()->toArray(),
            Attendance::query()->sole()->toArray(),
        ];

        foreach ($serialized as $attributes) {
            foreach (array_keys($attributes) as $key) {
                $this->assertFalse($key === 'id' || str_ends_with($key, '_id') || str_ends_with($key, '_flag'), "Internal key [{$key}] serialized.");
            }
        }

        $this->assertSame('2026-11-01', $serialized[0]['starts_on']);
        $this->assertSame('2027-08-31', $serialized[0]['ends_on']);
        $this->assertFalse($serialized[0]['public_listing']);
        $this->assertSame('2026-11-03', $serialized[1]['session_date']);
        $this->assertSame('15:30:00', $serialized[1]['start_time']);
        $this->assertSame('2026-11-02', $serialized[2]['enrolled_on']);
        $this->assertSame('2500.00', $serialized[3]['amount']);
        $this->assertSame('2026-11-03T10:15:00Z', $serialized[4]['marked_at']);
    }

    public function test_relations_resolve_across_the_g5_g6_tables(): void
    {
        $institute = $this->insertInstitute();
        $class = $this->insertClass($institute);
        $student = $this->insertStudentProfile($institute);
        $session = $this->insertSession($class);
        $enrollment = $this->insertEnrollment($class, $student, $institute);
        $this->insertFeeVersion($class);
        $this->insertAttendance($session, $enrollment, $class);

        $attendance = Attendance::query()->with(['session.tuitionClass', 'enrollment.student', 'tuitionClass.institute', 'markedBy'])->sole();

        $this->assertSame($class, $attendance->session->tuitionClass->getKey());
        $this->assertSame($student, $attendance->enrollment->student->getKey());
        $this->assertSame($institute, $attendance->tuitionClass->institute->getKey());

        $loaded = TuitionClass::query()->with(['feeVersions', 'sessions', 'enrollments'])->findOrFail($class);
        $this->assertCount(1, $loaded->feeVersions);
        $this->assertCount(1, $loaded->sessions);
        $this->assertSame(1, ClassEnrollment::query()->active()->where('class_id', $class)->count());
    }
}
