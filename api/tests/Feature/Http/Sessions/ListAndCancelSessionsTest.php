<?php

namespace Tests\Feature\Http\Sessions;

use App\Audit\AuditAction;
use App\Models\ClassSession;
use App\Models\TuitionClass;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\Feature\Http\Concerns\ClassFixtures;
use Tests\TestCase;

/**
 * GET /api/v1/classes/{uuid}/sessions (§10.2) and POST /api/v1/sessions/{uuid}/cancel (§10.6),
 * contract v0.9; D-024 Q50; D-027.
 */
class ListAndCancelSessionsTest extends TestCase
{
    use ClassFixtures;
    use RefreshDatabase;

    public function test_everyone_who_can_see_the_class_lists_its_sessions_in_date_and_time_order(): void
    {
        $institute = $this->makeInstitute();
        $class = $this->makeClass($institute);
        $this->makeSession($class, ['session_date' => '2026-11-05', 'start_time' => '16:00:00', 'end_time' => '17:00:00']);
        $this->makeSession($class, ['session_date' => '2026-11-05', 'start_time' => '08:00:00', 'end_time' => '09:00:00', 'status' => 'cancelled']);
        $this->makeSession($class, ['session_date' => '2026-11-03']);
        $this->makeSession($this->makeClass($institute));

        foreach ([$this->teacherUserOf($class), $this->staffOf($institute), $this->adminOf($institute), $this->superAdmin()] as $user) {
            $this->loginAs($user);
            $response = $this->spa('GET', $this->url($class))->assertOk();

            $this->assertSame(
                [['2026-11-03', '15:30'], ['2026-11-05', '08:00'], ['2026-11-05', '16:00']],
                array_map(fn (array $row) => [$row['session_date'], $row['start_time']], $response->json('data')),
            );
            $this->assertNoInternalIds($response);
        }

        $dates = fn (string $query) => array_column($this->spa('GET', $this->url($class).'?'.$query)->assertOk()->json('data'), 'start_time');
        $this->assertSame(['08:00'], $dates('status=cancelled'));
        $this->assertSame(['15:30'], $dates('to=2026-11-04'));
        $this->assertSame(['08:00', '16:00'], $dates('from=2026-11-05&to=2026-11-05'));

        foreach (['from=2026-11-05&to=2026-11-01', 'status=done', 'from=05/11/2026'] as $query) {
            $this->spa('GET', $this->url($class).'?'.$query)->assertUnprocessable();
        }
    }

    public function test_hidden_callers_get_404_on_the_list(): void
    {
        $institute = $this->makeInstitute();
        $class = $this->makeClass($institute);

        foreach ([$this->teacherAt($institute), $this->adminOf($this->makeInstitute()), $this->makeUser()] as $user) {
            $this->loginAs($user);
            $this->spa('GET', $this->url($class))->assertNotFound();
        }
    }

    public function test_a_publicly_listed_class_never_exposes_its_schedule(): void
    {
        $institute = $this->makeInstitute();
        $class = $this->makeClass($institute, ['public_listing' => 1]);
        $this->makeSession($class);

        // The class page itself is public.
        $this->nonStateful('GET', "/api/v1/classes/{$class->uuid}")->assertOk();

        // Unauthenticated: 401 before any lookup (contract §1.4 rule 1); never the sessions.
        $this->nonStateful('GET', $this->url($class))->assertUnauthorized()->assertJsonMissingPath('data');

        // Unrelated, signed in: the same 404 as for a private class.
        $this->loginAs($this->makeUser());
        $this->spa('GET', $this->url($class))->assertNotFound()->assertExactJson(['message' => 'Not found.']);

        // Staff still see it.
        $this->loginAs($this->staffOf($institute));
        $this->spa('GET', $this->url($class))->assertOk()->assertJsonPath('meta.total', 1);
    }

    public function test_the_assigned_teacher_cancels_a_session_which_is_kept_and_audited(): void
    {
        $institute = $this->makeInstitute();
        $class = $this->makeClass($institute);
        $session = $this->makeSession($class);
        $teacher = $this->teacherUserOf($class);
        $this->loginAs($teacher);

        $this->spa('POST', $this->cancelUrl($session))
            ->assertOk()
            ->assertJsonPath('data.uuid', $session->uuid)
            ->assertJsonPath('data.status', 'cancelled');

        $this->assertSame('cancelled', $session->fresh()->status);
        $rows = $this->auditRows(AuditAction::SESSION_CANCELLED);
        $this->assertCount(1, $rows);
        $this->assertEquals($teacher->getKey(), $rows[0]['user_id']);
        $this->assertSame(['status' => 'scheduled'], $rows[0]['old_values']);
        $this->assertSame('cancelled', $rows[0]['new_values']['status']);

        $this->spa('POST', $this->cancelUrl($session))
            ->assertStatus(409)->assertExactJson(['message' => 'This session is cancelled.', 'reason' => 'session_cancelled']);
    }

    public function test_cancelling_permissions_follow_session_creation(): void
    {
        $institute = $this->makeInstitute();
        $class = $this->makeClass($institute);
        $session = $this->makeSession($class);

        foreach ([$this->teacherAt($institute), $this->makeUser()] as $user) {
            $this->loginAs($user);
            $this->spa('POST', $this->cancelUrl($session))->assertNotFound();
        }

        foreach ([$this->staffOf($institute, 'clerk'), $this->superAdmin()] as $user) {
            $this->loginAs($user);
            $this->spa('POST', $this->cancelUrl($session))->assertForbidden();
        }

        $this->loginAs($this->staffOf($institute, 'manager'));
        $this->spa('POST', $this->cancelUrl($session), ['reason' => 'rain'])->assertUnprocessable()->assertJsonValidationErrors('reason');
        $this->spa('POST', $this->cancelUrl($session))->assertOk();

        $this->spa('POST', '/api/v1/sessions/not-a-uuid/cancel')->assertNotFound();
    }

    public function test_a_session_with_attendance_or_in_an_archived_class_cannot_be_cancelled(): void
    {
        $institute = $this->makeInstitute();
        $class = $this->makeClass($institute);
        $marked = $this->makeSession($class);
        $this->mark($marked, $this->enrol($class));
        $archived = $this->makeClass($institute, ['status' => 'archived']);
        $archivedSession = $this->makeSession($archived);
        $this->loginAs($this->adminOf($institute));

        $this->spa('POST', $this->cancelUrl($marked))
            ->assertStatus(409)->assertExactJson(['message' => 'Attendance has been recorded for this session.', 'reason' => 'session_has_attendance']);
        $this->spa('POST', $this->cancelUrl($archivedSession))->assertStatus(409)->assertJsonPath('reason', 'class_archived');

        $this->assertSame(0, ClassSession::query()->where('status', 'cancelled')->count());

        $this->suspend($institute);
        $this->spa('POST', $this->cancelUrl($this->makeSession($class, ['session_date' => '2026-11-04'])))
            ->assertForbidden()->assertJsonPath('reason', 'institute_suspended');
    }

    private function url(TuitionClass $class): string
    {
        return "/api/v1/classes/{$class->uuid}/sessions";
    }

    private function cancelUrl(ClassSession $session): string
    {
        return "/api/v1/sessions/{$session->uuid}/cancel";
    }
}
