<?php

namespace Tests\Feature\Http\Attendance;

use App\Audit\AuditAction;
use App\Audit\AuditLogger;
use App\Models\Attendance;
use App\Models\ClassEnrollment;
use App\Models\ClassSession;
use App\Models\Institute;
use App\Models\TuitionClass;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use RuntimeException;
use Tests\Feature\Http\Concerns\ClassFixtures;
use Tests\TestCase;

/**
 * POST /api/v1/sessions/{uuid}/attendance (contract v0.9 §10.3; D-024 Q48/Q49/Q55; D-027).
 * Today is 2026-11-10; the default session is 2026-11-03 and enrolments start 2026-11-01.
 */
class MarkAttendanceTest extends TestCase
{
    use ClassFixtures;
    use RefreshDatabase;

    public function test_unauthenticated_requests_get_401(): void
    {
        [$session, $enrollment] = $this->sessionWithEnrollment();

        $this->spa('POST', $this->url($session), $this->body($enrollment))->assertUnauthorized();
    }

    public function test_hidden_callers_get_404_and_super_admin_gets_403(): void
    {
        [$session, $enrollment, $class] = $this->sessionWithEnrollment();
        $institute = $this->instituteOf($class);

        foreach ([$this->teacherAt($institute), $this->adminOf($this->makeInstitute()), $this->makeUser()] as $user) {
            $this->loginAs($user);
            $this->spa('POST', $this->url($session), $this->body($enrollment))->assertNotFound();
            $this->spa('POST', $this->url($session), ['records' => 'nonsense'])->assertNotFound();
        }

        $this->loginAs($this->superAdmin());
        $this->spa('POST', $this->url($session), $this->body($enrollment))->assertForbidden();

        $this->assertDatabaseCount('attendances', 0);
    }

    public function test_the_assigned_teacher_marks_attendance_and_each_new_row_is_audited(): void
    {
        [$session, $enrollment, $class] = $this->sessionWithEnrollment();
        $second = $this->enrol($class);
        $teacher = $this->teacherUserOf($class);
        $this->loginAs($teacher);

        $response = $this->spa('POST', $this->url($session), ['records' => [
            ['enrollment_uuid' => $enrollment->uuid, 'status' => 'present', 'method' => 'manual', 'notes' => null],
            ['enrollment_uuid' => $second->uuid, 'status' => 'late', 'method' => 'manual', 'notes' => 'Bus delayed.'],
        ]])->assertOk()->assertHeader('Cache-Control', 'no-store, private');

        $now = now('UTC')->format('Y-m-d\TH:i:s\Z');
        $this->assertSame([
            ['enrollment_uuid' => $enrollment->uuid, 'status' => 'present', 'method' => 'manual', 'notes' => null, 'marked_at' => $now, 'marked_by' => ['uuid' => $teacher->uuid, 'name' => $teacher->name]],
            ['enrollment_uuid' => $second->uuid, 'status' => 'late', 'method' => 'manual', 'notes' => 'Bus delayed.', 'marked_at' => $now, 'marked_by' => ['uuid' => $teacher->uuid, 'name' => $teacher->name]],
        ], $response->json('data'));
        $this->assertNoInternalIds($response);

        $this->assertSame(2, Attendance::query()->where('session_id', $session->getKey())->count());
        $this->assertEquals($teacher->getKey(), Attendance::query()->first()->marked_by_user_id);

        $rows = $this->auditRows(AuditAction::ATTENDANCE_RECORDED);
        $this->assertCount(2, $rows);
        $this->assertSame('attendance', $rows[0]['auditable_type']);
        $this->assertEquals(['session_uuid' => $session->uuid, 'enrollment_uuid' => $enrollment->uuid, 'status' => 'present', 'method' => 'manual'], $rows[0]['new_values']);
        $this->assertStringNotContainsString('Bus delayed', json_encode(DB::table('audit_logs')->get()));
    }

    public function test_staff_and_admins_may_mark_and_a_change_is_a_correction_without_the_note_text(): void
    {
        [$session, $enrollment, $class] = $this->sessionWithEnrollment();
        $institute = $this->instituteOf($class);
        $this->loginAs($this->staffOf($institute, 'clerk'));
        $this->spa('POST', $this->url($session), $this->body($enrollment, 'absent'))->assertOk();

        $admin = $this->adminOf($institute);
        $this->loginAs($admin);
        $this->spa('POST', $this->url($session), $this->body($enrollment, 'excused', 'Doctor visit (fictional).'))
            ->assertOk()
            ->assertJsonPath('data.0.status', 'excused')
            ->assertJsonPath('data.0.marked_by.uuid', $admin->uuid);

        $row = Attendance::query()->sole();
        $this->assertSame(['excused', 'Doctor visit (fictional).'], [$row->status, $row->notes]);
        $this->assertEquals($admin->getKey(), $row->marked_by_user_id);

        $corrections = $this->auditRows(AuditAction::ATTENDANCE_CORRECTED);
        $this->assertCount(1, $corrections);
        $this->assertEquals(['status' => 'absent', 'method' => 'manual'], $corrections[0]['old_values']);
        $this->assertEquals(['session_uuid' => $session->uuid, 'enrollment_uuid' => $enrollment->uuid, 'status' => 'excused', 'method' => 'manual', 'notes_changed' => true], $corrections[0]['new_values']);
        $this->assertStringNotContainsString('Doctor visit', json_encode(DB::table('audit_logs')->get()));

        // Resubmitting the same mark writes nothing (§1.6): no new marker, time or audit row.
        $this->travel(2)->hours();
        $this->loginAs($this->staffOf($institute));
        $this->spa('POST', $this->url($session), $this->body($enrollment, 'excused', 'Doctor visit (fictional).'))
            ->assertOk()->assertJsonPath('data.0.marked_by.uuid', $admin->uuid);
        $this->assertCount(1, $this->auditRows(AuditAction::ATTENDANCE_CORRECTED));
        $this->assertCount(1, $this->auditRows(AuditAction::ATTENDANCE_RECORDED));
    }

    public function test_omitted_notes_are_preserved_null_clears_them_and_a_string_replaces_them(): void
    {
        [$session, $enrollment, $class] = $this->sessionWithEnrollment();
        $institute = $this->instituteOf($class);
        $this->loginAs($this->adminOf($institute));
        $this->spa('POST', $this->url($session), $this->body($enrollment, 'late', 'Fictional first note.'))->assertOk();

        // Omitted: the note is kept, and only the status change is a correction.
        $this->loginAs($second = $this->staffOf($institute));
        $this->spa('POST', $this->url($session), ['records' => [['enrollment_uuid' => $enrollment->uuid, 'status' => 'present', 'method' => 'manual']]])
            ->assertOk()->assertJsonPath('data.0.notes', 'Fictional first note.')->assertJsonPath('data.0.marked_by.uuid', $second->uuid);
        $this->assertSame('Fictional first note.', Attendance::query()->sole()->notes);
        $this->assertFalse($this->auditRows(AuditAction::ATTENDANCE_CORRECTED)[0]['new_values']['notes_changed']);

        // Omitted notes and the same status: nothing changes, not even the marker or the time.
        $this->travel(1)->hours();
        $this->loginAs($this->adminOf($institute));
        $this->spa('POST', $this->url($session), ['records' => [['enrollment_uuid' => $enrollment->uuid, 'status' => 'present', 'method' => 'manual']]])
            ->assertOk()->assertJsonPath('data.0.marked_by.uuid', $second->uuid);
        $this->assertCount(1, $this->auditRows(AuditAction::ATTENDANCE_CORRECTED));

        // A new string replaces the note.
        $this->spa('POST', $this->url($session), $this->body($enrollment, 'present', 'Fictional second note.'))
            ->assertOk()->assertJsonPath('data.0.notes', 'Fictional second note.');
        $this->assertTrue($this->auditRows(AuditAction::ATTENDANCE_CORRECTED)[1]['new_values']['notes_changed']);

        // An explicit null clears it.
        $this->spa('POST', $this->url($session), $this->body($enrollment, 'present', null))
            ->assertOk()->assertJsonPath('data.0.notes', null);
        $this->assertNull(Attendance::query()->sole()->notes);
        $this->assertCount(3, $this->auditRows(AuditAction::ATTENDANCE_CORRECTED));

        // A first mark without notes stores none.
        $other = $this->enrol($class);
        $this->spa('POST', $this->url($session), ['records' => [['enrollment_uuid' => $other->uuid, 'status' => 'absent', 'method' => 'manual']]])
            ->assertOk()->assertJsonPath('data.0.notes', null);
        $this->assertStringNotContainsString('Fictional', json_encode(DB::table('audit_logs')->get()));
    }

    public function test_cancelled_future_and_archived_class_sessions_are_409(): void
    {
        [$session, $enrollment, $class] = $this->sessionWithEnrollment();
        $this->loginAs($this->adminOf($this->instituteOf($class)));

        $cancelled = $this->makeSession($class, ['session_date' => '2026-11-04', 'status' => 'cancelled']);
        $this->spa('POST', $this->url($cancelled), $this->body($enrollment))
            ->assertStatus(409)->assertJsonPath('reason', 'session_cancelled');

        $future = $this->makeSession($class, ['session_date' => '2026-11-11']);
        $this->spa('POST', $this->url($future), $this->body($enrollment))
            ->assertStatus(409)->assertExactJson(['message' => 'Attendance cannot be recorded for a future session.', 'reason' => 'session_in_future']);

        // Today's session is fine.
        $this->spa('POST', $this->url($this->makeSession($class, ['session_date' => self::TODAY])), $this->body($enrollment))->assertOk();

        DB::table('classes')->where('id', $class->getKey())->update(['status' => 'archived']);
        $this->spa('POST', $this->url($session), $this->body($enrollment))->assertStatus(409)->assertJsonPath('reason', 'class_archived');

        $this->assertSame(1, Attendance::query()->count());
    }

    public function test_an_enrolment_must_belong_to_the_class_and_be_active_on_the_session_date(): void
    {
        [$session, $enrollment, $class] = $this->sessionWithEnrollment();
        $institute = $this->instituteOf($class);
        $otherClass = $this->enrol($this->makeClass($institute));
        $startsLater = $this->enrol($class, overrides: ['enrolled_on' => '2026-11-04']);
        $withdrawnBefore = $this->enrol($class);
        $this->withdrawEnrollment($withdrawnBefore->getKey(), '2026-11-03');
        $withdrawnAfter = $this->enrol($class);
        $this->withdrawEnrollment($withdrawnAfter->getKey(), '2026-11-04');
        $this->loginAs($this->adminOf($institute));

        $response = $this->spa('POST', $this->url($session), ['records' => [
            ['enrollment_uuid' => $enrollment->uuid, 'status' => 'present', 'method' => 'manual'],
            ['enrollment_uuid' => $otherClass->uuid, 'status' => 'present', 'method' => 'manual'],
            ['enrollment_uuid' => $startsLater->uuid, 'status' => 'present', 'method' => 'manual'],
            ['enrollment_uuid' => $withdrawnBefore->uuid, 'status' => 'present', 'method' => 'manual'],
            ['enrollment_uuid' => '6b0e3f9a-1c2d-4e5f-8a9b-0c1d2e3f4a5b', 'status' => 'present', 'method' => 'manual'],
        ]])->assertUnprocessable();

        $this->assertSame([
            'records.1.enrollment_uuid' => ['The enrolment is not in this class.'],
            'records.2.enrollment_uuid' => ['The enrolment is not active on the session date.'],
            'records.3.enrollment_uuid' => ['The enrolment is not active on the session date.'],
            'records.4.enrollment_uuid' => ['The enrolment is not in this class.'],
        ], $response->json('errors'));
        $this->assertDatabaseCount('attendances', 0);

        // Withdrawn after the session date: still active on it, so it can be marked (Q48).
        $this->spa('POST', $this->url($session), $this->body($withdrawnAfter))->assertOk();
    }

    public function test_the_request_shape_is_validated(): void
    {
        [$session, $enrollment, $class] = $this->sessionWithEnrollment();
        $this->loginAs($this->adminOf($this->instituteOf($class)));
        $record = ['enrollment_uuid' => $enrollment->uuid, 'status' => 'present', 'method' => 'manual'];

        $cases = [
            ['records', []],
            ['records.1.enrollment_uuid', [$record, $record]],
            ['records.0.status', [[...$record, 'status' => 'sick']]],
            ['records.0.method', [[...$record, 'method' => 'qr']]],
            ['records.0.method', [array_diff_key($record, ['method' => true])]],
            ['records.0.enrollment_uuid', [[...$record, 'enrollment_uuid' => 'nope']]],
            ['records.0.notes', [[...$record, 'notes' => str_repeat('n', 501)]]],
            ['records.0.notes', [[...$record, 'notes' => "Bad\u{202E}note"]]],
            ['records.0', [[...$record, 'marked_by' => 'me']]],
            ['records', array_fill(0, 501, $record)],
        ];

        foreach ($cases as [$field, $records]) {
            $this->spa('POST', $this->url($session), ['records' => $records])->assertUnprocessable()->assertJsonValidationErrors($field);
        }

        $this->spa('POST', $this->url($session), ['records' => [$record], 'marked_at' => 'now'])->assertUnprocessable()->assertJsonValidationErrors('marked_at');
        $this->assertDatabaseCount('attendances', 0);
    }

    public function test_a_suspended_institute_refuses_marks_and_a_failed_audit_rolls_back(): void
    {
        [$session, $enrollment, $class] = $this->sessionWithEnrollment();
        $institute = $this->instituteOf($class);
        $teacher = $this->teacherUserOf($class);
        $this->loginAs($teacher);

        $this->mock(AuditLogger::class)->shouldReceive('record')->andThrow(new RuntimeException('Fictional audit outage.'));
        $this->spa('POST', $this->url($session), $this->body($enrollment))->assertStatus(500);
        $this->assertDatabaseCount('attendances', 0);

        $this->suspend($institute);
        $this->spa('POST', $this->url($session), $this->body($enrollment))->assertForbidden()->assertJsonPath('reason', 'institute_suspended');
    }

    /**
     * @return array{0: ClassSession, 1: ClassEnrollment, 2: TuitionClass}
     */
    private function sessionWithEnrollment(): array
    {
        $class = $this->makeClass($this->makeInstitute());

        return [$this->makeSession($class), $this->enrol($class), $class];
    }

    private function body(ClassEnrollment $enrollment, string $status = 'present', ?string $notes = null): array
    {
        return ['records' => [['enrollment_uuid' => $enrollment->uuid, 'status' => $status, 'method' => 'manual', 'notes' => $notes]]];
    }

    private function instituteOf(TuitionClass $class): Institute
    {
        return Institute::query()->findOrFail($class->institute_id);
    }

    private function url(ClassSession $session): string
    {
        return "/api/v1/sessions/{$session->uuid}/attendance";
    }
}
