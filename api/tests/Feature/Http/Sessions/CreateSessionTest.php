<?php

namespace Tests\Feature\Http\Sessions;

use App\Audit\AuditAction;
use App\Audit\AuditLogger;
use App\Models\ClassSession;
use App\Models\TuitionClass;
use Illuminate\Foundation\Testing\RefreshDatabase;
use RuntimeException;
use Tests\Feature\Http\Concerns\ClassFixtures;
use Tests\TestCase;

/**
 * POST /api/v1/classes/{uuid}/sessions (contract v0.9 §10.1; D-024; D-027). Today is 2026-11-10.
 */
class CreateSessionTest extends TestCase
{
    use ClassFixtures;
    use RefreshDatabase;

    private const SESSION = ['session_date' => '2026-11-12', 'start_time' => '15:30', 'end_time' => '17:30'];

    public function test_unauthenticated_requests_get_401(): void
    {
        $this->spa('POST', $this->url($this->makeClass($this->makeInstitute())), self::SESSION)->assertUnauthorized();
    }

    public function test_hidden_callers_get_404_and_visible_callers_without_the_right_get_403(): void
    {
        $institute = $this->makeInstitute();
        $class = $this->makeClass($institute);

        foreach ([$this->teacherAt($institute), $this->adminOf($this->makeInstitute()), $this->makeUser()] as $user) {
            $this->loginAs($user);
            $this->spa('POST', $this->url($class), self::SESSION)->assertNotFound();
            $this->spa('POST', $this->url($class), ['start_time' => 'later'])->assertNotFound();
        }

        foreach ([$this->staffOf($institute, 'clerk'), $this->superAdmin()] as $user) {
            $this->loginAs($user);
            $this->spa('POST', $this->url($class), self::SESSION)->assertForbidden()->assertJsonMissingPath('reason');
        }

        $this->assertDatabaseCount('class_sessions', 0);
    }

    public function test_the_assigned_teacher_creates_a_session_and_it_is_audited(): void
    {
        $institute = $this->makeInstitute();
        $class = $this->makeClass($institute);
        $teacher = $this->teacherUserOf($class);
        $this->loginAs($teacher);

        $response = $this->spa('POST', $this->url($class), self::SESSION)->assertCreated()->assertHeader('Cache-Control', 'no-store, private');

        $session = ClassSession::query()->sole();
        $this->assertSame(['data' => [
            'uuid' => $session->uuid,
            'class_uuid' => $class->uuid,
            'session_date' => '2026-11-12',
            'start_time' => '15:30',
            'end_time' => '17:30',
            'status' => 'scheduled',
            'created_at' => $response->json('data.created_at'),
        ]], $response->json());
        $this->assertNoInternalIds($response);
        $this->assertSame('15:30:00', $session->start_time);
        $this->assertEquals($teacher->getKey(), $session->created_by_user_id);

        $rows = $this->auditRows(AuditAction::SESSION_CREATED);
        $this->assertCount(1, $rows);
        $this->assertSame('class_session', $rows[0]['auditable_type']);
        $this->assertEquals([
            'session_uuid' => $session->uuid, 'class_uuid' => $class->uuid, 'session_date' => '2026-11-12',
            'start_time' => '15:30', 'end_time' => '17:30', 'status' => 'scheduled',
        ], $rows[0]['new_values']);
    }

    public function test_institute_managers_admins_and_manager_staff_may_create_sessions(): void
    {
        $institute = $this->makeInstitute();
        $class = $this->makeClass($institute);

        foreach ([$this->adminOf($institute), $this->staffOf($institute, 'manager')] as $index => $user) {
            $this->loginAs($user);
            $this->spa('POST', $this->url($class), [...self::SESSION, 'start_time' => '0'.(8 + $index).':00', 'end_time' => '1'.$index.':00'])->assertCreated();
        }

        $this->assertSame(2, ClassSession::query()->count());
    }

    public function test_only_an_active_class_takes_sessions_within_its_dates(): void
    {
        $institute = $this->makeInstitute();
        $this->loginAs($this->adminOf($institute));

        foreach (['draft', 'archived'] as $status) {
            $this->spa('POST', $this->url($this->makeClass($institute, ['status' => $status])), self::SESSION)
                ->assertStatus(409)->assertJsonPath('reason', 'not_active');
        }

        $class = $this->makeClass($institute, ['starts_on' => '2026-11-01', 'ends_on' => '2026-12-31']);
        foreach (['2026-10-31', '2027-01-01'] as $date) {
            $this->spa('POST', $this->url($class), [...self::SESSION, 'session_date' => $date])
                ->assertUnprocessable()->assertJsonValidationErrors('session_date');
        }

        $this->spa('POST', $this->url($class), [...self::SESSION, 'session_date' => '2026-12-31'])->assertCreated();
        $this->assertSame(1, ClassSession::query()->count());
    }

    public function test_times_are_whole_minutes_and_end_after_start(): void
    {
        $institute = $this->makeInstitute();
        $class = $this->makeClass($institute);
        $this->loginAs($this->adminOf($institute));

        $cases = [
            ['end_time', '15:30'], ['end_time', '15:00'], ['start_time', '15:30:00'], ['start_time', '3:30 PM'],
            ['start_time', '24:00'], ['end_time', '17:61'], ['session_date', '2026-02-30'], ['session_date', null],
        ];

        foreach ($cases as [$field, $value]) {
            $this->spa('POST', $this->url($class), [...self::SESSION, $field => $value])->assertUnprocessable()->assertJsonValidationErrors($field);
        }

        $this->spa('POST', $this->url($class), [...self::SESSION, 'status' => 'cancelled'])->assertUnprocessable()->assertJsonValidationErrors('status');
        $this->assertDatabaseCount('class_sessions', 0);
    }

    public function test_a_duplicate_slot_is_409_but_a_cancelled_session_frees_it(): void
    {
        $institute = $this->makeInstitute();
        $class = $this->makeClass($institute);
        $this->loginAs($this->adminOf($institute));

        $first = $this->spa('POST', $this->url($class), self::SESSION)->assertCreated()->json('data.uuid');
        $this->spa('POST', $this->url($class), [...self::SESSION, 'end_time' => '18:00'])
            ->assertStatus(409)
            ->assertExactJson(['message' => 'This class already has a session at this date and start time.', 'reason' => 'session_slot_taken']);

        // A different start time, or another class, is fine.
        $this->spa('POST', $this->url($class), [...self::SESSION, 'start_time' => '16:00', 'end_time' => '18:00'])->assertCreated();
        $this->spa('POST', $this->url($this->makeClass($institute)), self::SESSION)->assertCreated();

        $this->spa('POST', "/api/v1/sessions/{$first}/cancel")->assertOk();
        $this->spa('POST', $this->url($class), self::SESSION)->assertCreated();
        // The cancelled session, the 16:00 one and the replacement.
        $this->assertSame(3, ClassSession::query()->where('class_id', $class->getKey())->count());
    }

    public function test_a_suspended_institute_refuses_new_sessions_even_from_the_teacher(): void
    {
        $institute = $this->makeInstitute();
        $class = $this->makeClass($institute);
        $this->suspend($institute);
        $this->loginAs($this->teacherUserOf($class));

        $this->spa('POST', $this->url($class), self::SESSION)->assertForbidden()->assertJsonPath('reason', 'institute_suspended');
    }

    public function test_a_failed_audit_insert_rolls_the_session_back(): void
    {
        $institute = $this->makeInstitute();
        $class = $this->makeClass($institute);
        $this->loginAs($this->adminOf($institute));
        $this->mock(AuditLogger::class)->shouldReceive('record')->andThrow(new RuntimeException('Fictional audit outage.'));

        $this->spa('POST', $this->url($class), self::SESSION)->assertStatus(500);
        $this->assertDatabaseCount('class_sessions', 0);
    }

    private function url(TuitionClass $class): string
    {
        return "/api/v1/classes/{$class->uuid}/sessions";
    }
}
