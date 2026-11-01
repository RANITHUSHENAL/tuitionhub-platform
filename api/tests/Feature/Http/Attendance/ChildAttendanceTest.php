<?php

namespace Tests\Feature\Http\Attendance;

use App\Models\Institute;
use App\Models\StudentProfile;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Tests\Feature\Http\Concerns\ClassFixtures;
use Tests\TestCase;

/**
 * GET /api/v1/parents/me/children/{uuid}/attendance (contract v0.9 §10.5; D-024 Q51) and the
 * children list's active_enrollments (§9.5); D-027. Today is 2026-11-10.
 */
class ChildAttendanceTest extends TestCase
{
    use ClassFixtures;
    use RefreshDatabase;

    public function test_a_linked_guardian_sees_only_their_childs_attendance_newest_first(): void
    {
        $institute = $this->makeInstitute();
        $class = $this->makeClass($institute, ['title' => 'Fictional Maths']);
        $child = $this->makeStudent($institute);
        $parent = $this->makeUser(['name' => 'Fictional Parent']);
        $this->linkGuardian($child, $parent);
        $enrollment = $this->enrol($class, $child);
        $classmate = $this->enrol($class);

        $older = $this->makeSession($class, ['session_date' => '2026-11-03']);
        $newer = $this->makeSession($class, ['session_date' => '2026-11-05', 'start_time' => '08:00:00', 'end_time' => '10:00:00']);
        $this->mark($older, $enrollment, ['status' => 'present', 'notes' => 'Private fictional note.']);
        $this->mark($newer, $enrollment, ['status' => 'absent']);
        $this->mark($older, $classmate);

        $this->loginAs($parent);
        $response = $this->spa('GET', $this->url($child))->assertOk()->assertHeader('Cache-Control', 'no-store, private');

        $this->assertSame([
            'session' => ['uuid' => $newer->uuid, 'session_date' => '2026-11-05', 'start_time' => '08:00', 'end_time' => '10:00'],
            'class' => ['uuid' => $class->uuid, 'title' => 'Fictional Maths'],
            'status' => 'absent',
            'marked_at' => $response->json('data.0.marked_at'),
        ], $response->json('data.0'));
        $this->assertSame(['absent', 'present'], array_column($response->json('data'), 'status'));
        $this->assertSame(['current_page' => 1, 'per_page' => 25, 'total' => 2, 'last_page' => 1], $response->json('meta'));
        $this->assertStringNotContainsString('Private fictional note', $response->getContent());
        $this->assertNoInternalIds($response);
    }

    public function test_without_an_active_link_the_child_is_404(): void
    {
        $institute = $this->makeInstitute();
        $child = $this->makeStudent($institute);
        $parent = $this->makeUser();
        $this->linkGuardian($child, $parent);
        $otherChild = $this->makeStudent($institute);

        $this->loginAs($parent);
        $this->spa('GET', $this->url($otherChild))->assertNotFound()->assertExactJson(['message' => 'Not found.']);
        $this->spa('GET', '/api/v1/parents/me/children/not-a-uuid/attendance')->assertNotFound();

        // Staff of the institute are not guardians either.
        $this->loginAs($this->staffOf($institute));
        $this->spa('GET', $this->url($child))->assertNotFound();

        // A revoked link loses access on the next request.
        $this->loginAs($parent);
        $this->spa('GET', $this->url($child))->assertOk();
        DB::table('student_guardians')->update(['revoked_at' => now('UTC'), 'revoked_by_user_id' => $parent->getKey()]);
        $this->spa('GET', $this->url($child))->assertNotFound();
    }

    public function test_past_attendance_stays_visible_after_a_withdrawal(): void
    {
        $institute = $this->makeInstitute();
        $class = $this->makeClass($institute);
        $child = $this->makeStudent($institute);
        $parent = $this->makeUser();
        $this->linkGuardian($child, $parent);
        $enrollment = $this->enrol($class, $child);
        $this->mark($this->makeSession($class), $enrollment);
        $this->withdrawEnrollment($enrollment->getKey(), '2026-11-05');

        $this->loginAs($parent);
        $this->spa('GET', $this->url($child))->assertOk()->assertJsonPath('meta.total', 1);
    }

    public function test_the_window_defaults_to_30_days_is_at_most_366_and_filters_by_class(): void
    {
        $institute = $this->makeInstitute();
        $class = $this->makeClass($institute);
        $other = $this->makeClass($institute);
        $child = $this->makeStudent($institute);
        $parent = $this->makeUser();
        $this->linkGuardian($child, $parent);
        $enrollment = $this->enrol($class, $child, ['enrolled_on' => '2026-01-01']);
        $otherEnrollment = $this->enrol($other, $child, ['enrolled_on' => '2026-01-01']);

        // 2026-10-12 is the first day of the default window (30 days ending today).
        foreach (['2026-10-11', '2026-10-12', '2026-11-10'] as $date) {
            $this->mark($this->makeSession($class, ['session_date' => $date]), $enrollment);
        }
        $this->mark($this->makeSession($other, ['session_date' => '2026-11-01']), $otherEnrollment);

        $this->loginAs($parent);
        $dates = fn (string $query) => array_column(array_column($this->spa('GET', $this->url($child).$query)->assertOk()->json('data'), 'session'), 'session_date');

        $this->assertSame(['2026-11-10', '2026-11-01', '2026-10-12'], $dates(''));
        $this->assertSame(['2026-10-11'], $dates('?from=2026-10-01&to=2026-10-11'));
        $this->assertSame(['2026-11-01'], $dates("?class_uuid={$other->uuid}"));
        $this->assertSame([], $dates('?class_uuid=6b0e3f9a-1c2d-4e5f-8a9b-0c1d2e3f4a5b'));
        $this->assertSame(['2026-11-10', '2026-11-01', '2026-10-12', '2026-10-11'], $dates('?from=2025-11-10&to=2026-11-10'));

        $this->spa('GET', $this->url($child).'?from=2025-11-09&to=2026-11-10')->assertUnprocessable()->assertJsonValidationErrors('to');
        $this->spa('GET', $this->url($child).'?from=2026-11-10&to=2026-11-01')->assertUnprocessable()->assertJsonValidationErrors('from');
        $this->spa('GET', $this->url($child).'?class_uuid=nope')->assertUnprocessable()->assertJsonValidationErrors('class_uuid');
    }

    public function test_the_children_list_shows_each_childs_active_enrolments(): void
    {
        $institute = $this->makeInstitute(overrides: ['name' => 'Lakmina Academy']);
        $child = $this->makeStudent($institute);
        $parent = $this->makeUser();
        $this->linkGuardian($child, $parent);
        $maths = $this->makeClass($institute, ['title' => 'Fictional Maths']);
        $physics = $this->makeClass($institute, ['title' => 'Fictional Physics']);
        $active = $this->enrol($maths, $child, ['enrolled_on' => '2026-02-01']);
        $this->withdrawEnrollment($this->enrol($physics, $child)->getKey(), '2026-11-05');

        $this->loginAs($parent);
        $response = $this->spa('GET', '/api/v1/parents/me/children')->assertOk();

        $this->assertSame([
            ['uuid' => $active->uuid, 'class' => ['uuid' => $maths->uuid, 'title' => 'Fictional Maths'], 'institute' => ['name' => 'Lakmina Academy']],
        ], $response->json('data.0.active_enrollments'));
        $this->assertNoInternalIds($response);
    }

    private function url(StudentProfile $student): string
    {
        return "/api/v1/parents/me/children/{$student->uuid}/attendance";
    }
}
