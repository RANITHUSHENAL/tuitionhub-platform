<?php

namespace Tests\Feature\Http\Attendance;

use App\Models\Institute;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Tests\Feature\Http\Concerns\ClassFixtures;
use Tests\TestCase;

/**
 * GET /api/v1/sessions/{uuid}/attendance (contract v0.9 §10.4; D-027): every enrolment active on
 * the session date, marked or not, plus any enrolment already marked for it.
 */
class SessionAttendanceSheetTest extends TestCase
{
    use ClassFixtures;
    use RefreshDatabase;

    public function test_the_sheet_lists_active_enrolments_marked_or_unmarked_and_keeps_marked_history(): void
    {
        $institute = $this->makeInstitute();
        $class = $this->makeClass($institute);
        $session = $this->makeSession($class);
        [$amal, $bimal, $chamari, $dilan] = array_map(function (string $name) use ($institute) {
            $student = $this->makeStudent($institute);
            DB::table('student_profiles')->where('id', $student->getKey())->update(['full_name' => $name]);

            return $student->fresh();
        }, ['Amal Fictional', 'Bimal Fictional', 'Chamari Fictional', 'Dilan Fictional']);

        $marked = $this->enrol($class, $amal);
        $unmarked = $this->enrol($class, $bimal);
        $withdrawnLater = $this->enrol($class, $chamari);
        $this->mark($session, $marked, ['status' => 'late', 'notes' => 'Fictional note.']);
        $this->mark($session, $withdrawnLater);
        $this->withdrawEnrollment($withdrawnLater->getKey(), '2026-11-05');
        // Enrolled after the session date and never marked for it: not on this sheet.
        $this->enrol($class, $dilan, ['enrolled_on' => '2026-11-04']);

        $this->loginAs($this->teacherUserOf($class));
        $response = $this->spa('GET', $this->url($session))->assertOk();

        $this->assertSame(['Amal Fictional', 'Bimal Fictional', 'Chamari Fictional'], array_column(array_column($response->json('data'), 'student'), 'full_name'));
        $this->assertSame(['status' => 'late', 'method' => 'manual', 'notes' => 'Fictional note.', 'marked_at' => $response->json('data.0.attendance.marked_at')], $response->json('data.0.attendance'));
        $this->assertSame($unmarked->uuid, $response->json('data.1.enrollment_uuid'));
        $this->assertNull($response->json('data.1.attendance'));
        $this->assertSame(['marked_count' => 2, 'unmarked_count' => 1], array_intersect_key($response->json('meta'), array_flip(['marked_count', 'unmarked_count'])));
        $this->assertSame($session->uuid, $response->json('meta.session.uuid'));
        $this->assertSame('15:30', $response->json('meta.session.start_time'));
        $this->assertNoInternalIds($response);
    }

    public function test_staff_and_admins_see_the_sheet_even_while_suspended_others_are_refused(): void
    {
        $institute = $this->makeInstitute();
        $class = $this->makeClass($institute);
        $session = $this->makeSession($class);
        $this->suspend($institute);

        foreach ([$this->staffOf($institute), $this->adminOf($institute)] as $user) {
            $this->loginAs($user);
            $this->spa('GET', $this->url($session))->assertOk();
        }

        $this->loginAs($this->superAdmin());
        $this->spa('GET', $this->url($session))->assertForbidden();

        foreach ([$this->teacherAt(Institute::query()->findOrFail($class->institute_id)), $this->makeUser()] as $user) {
            $this->loginAs($user);
            $this->spa('GET', $this->url($session))->assertNotFound();
        }
    }

    private function url($session): string
    {
        return "/api/v1/sessions/{$session->uuid}/attendance";
    }
}
