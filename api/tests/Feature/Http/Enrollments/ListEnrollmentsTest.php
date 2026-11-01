<?php

namespace Tests\Feature\Http\Enrollments;

use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Tests\Feature\Http\Concerns\ClassFixtures;
use Tests\TestCase;

/**
 * GET /api/v1/classes/{uuid}/enrollments (contract v0.8 §8.4; D-026): the roster. Guardians and
 * anyone not allowed get 404; the roster is never revealed.
 */
class ListEnrollmentsTest extends TestCase
{
    use ClassFixtures;
    use RefreshDatabase;

    public function test_the_assigned_teacher_staff_admins_and_super_admin_see_the_roster_in_name_order(): void
    {
        $institute = $this->makeInstitute();
        $class = $this->makeClass($institute);
        $zara = $this->makeStudent($institute);
        DB::table('student_profiles')->where('id', $zara->getKey())->update(['full_name' => 'Zara Fictional']);
        $amal = $this->makeStudent($institute);
        DB::table('student_profiles')->where('id', $amal->getKey())->update(['full_name' => 'Amal Fictional']);
        $this->enrol($class, $zara);
        $this->withdrawEnrollment($this->enrol($class, $amal)->getKey(), '2026-11-05');
        $this->enrol($this->makeClass($institute));

        foreach ([$this->teacherUserOf($class), $this->staffOf($institute), $this->adminOf($institute), $this->superAdmin()] as $user) {
            $this->loginAs($user);
            $response = $this->spa('GET', $this->enrollmentsUrl($class))->assertOk();

            $this->assertSame(['Amal Fictional', 'Zara Fictional'], array_column(array_column($response->json('data'), 'student'), 'full_name'));
            $this->assertSame(['withdrawn', 'active'], array_column($response->json('data'), 'status'));
            $this->assertSame(2, $response->json('meta.total'));
            $this->assertNoInternalIds($response);
        }

        $this->assertSame(['Zara Fictional'], array_column(array_column($this->spa('GET', $this->enrollmentsUrl($class).'?status=active')->json('data'), 'student'), 'full_name'));
        $this->spa('GET', $this->enrollmentsUrl($class).'?status=paused')->assertUnprocessable();
    }

    public function test_other_teachers_unrelated_callers_and_guardians_get_404(): void
    {
        $institute = $this->makeInstitute();
        $class = $this->makeClass($institute);
        $this->enrol($class);

        foreach ([$this->teacherAt($institute), $this->adminOf($this->makeInstitute()), $this->makeUser()] as $user) {
            $this->loginAs($user);
            $this->spa('GET', $this->enrollmentsUrl($class))->assertNotFound()->assertExactJson(['message' => 'Not found.']);
        }

        $this->spa('GET', '/api/v1/classes/not-a-uuid/enrollments')->assertNotFound();
    }
}
