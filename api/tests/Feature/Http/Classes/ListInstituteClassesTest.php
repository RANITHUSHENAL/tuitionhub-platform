<?php

namespace Tests\Feature\Http\Classes;

use App\Support\Lookups;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\Feature\Http\Concerns\ClassFixtures;
use Tests\TestCase;

/**
 * GET /api/v1/institutes/{uuid}/classes (contract v0.8 §7.2; FD-3, FD-4; D-026).
 */
class ListInstituteClassesTest extends TestCase
{
    use ClassFixtures;
    use RefreshDatabase;

    public function test_unauthenticated_requests_get_401(): void
    {
        $this->spa('GET', $this->url($this->makeInstitute()))->assertUnauthorized();
    }

    public function test_staff_admins_and_super_admin_list_the_classes_of_this_institute_only(): void
    {
        $institute = $this->makeInstitute();
        $this->makeClass($institute, ['title' => 'Beta Fictional Class']);
        $this->makeClass($institute, ['title' => 'Alpha Fictional Class', 'status' => 'draft']);
        $this->makeClass($this->makeInstitute(), ['title' => 'Other Institute Class']);
        $this->suspend($institute);

        foreach ([$this->staffOf($institute), $this->adminOf($institute), $this->superAdmin()] as $user) {
            $this->loginAs($user);
            $response = $this->spa('GET', $this->url($institute))->assertOk();

            $this->assertSame(['Alpha Fictional Class', 'Beta Fictional Class'], array_column($response->json('data'), 'title'));
            $this->assertSame(['current_page' => 1, 'per_page' => 25, 'total' => 2, 'last_page' => 1], $response->json('meta'));
            $this->assertNoInternalIds($response);
        }
    }

    public function test_teacher_only_callers_get_403_and_unrelated_callers_get_404(): void
    {
        $institute = $this->makeInstitute();
        $class = $this->makeClass($institute);

        $this->loginAs($this->teacherUserOf($class));
        $this->spa('GET', $this->url($institute))->assertForbidden();

        $this->loginAs($this->adminOf($this->makeInstitute()));
        $this->spa('GET', $this->url($institute))->assertNotFound()->assertExactJson(['message' => 'Not found.']);
    }

    public function test_filters_by_status_subject_grade_and_teacher(): void
    {
        $institute = $this->makeInstitute();
        $teacher = $this->teacherAt($institute);
        $this->makeClass($institute, ['title' => 'Maths A', 'status' => 'draft'], $teacher);
        $this->makeClass($institute, ['title' => 'Physics B', 'subject_id' => Lookups::id('subjects', 'PHY'), 'grade_id' => Lookups::id('grades', 'AL2')]);
        $this->makeClass($institute, ['title' => 'Maths C']);
        $teacherUuid = $this->teacherProfileOf($teacher)->uuid;
        $this->loginAs($this->adminOf($institute));

        $titles = fn (string $query) => array_column($this->spa('GET', $this->url($institute).'?'.$query)->assertOk()->json('data'), 'title');

        $this->assertSame(['Maths A'], $titles('status=draft'));
        $this->assertSame(['Physics B'], $titles('subject_code=PHY'));
        $this->assertSame(['Physics B'], $titles('grade_code=AL2'));
        $this->assertSame(['Maths A'], $titles("teacher_uuid={$teacherUuid}"));
        $this->assertSame([], $titles('teacher_uuid=6b0e3f9a-1c2d-4e5f-8a9b-0c1d2e3f4a5b'));

        foreach (['status=open', 'subject_code=phy', 'grade_code=G99', 'teacher_uuid=nope', 'per_page=101'] as $query) {
            $this->spa('GET', $this->url($institute).'?'.$query)->assertUnprocessable();
        }
    }

    private function url($institute): string
    {
        return "/api/v1/institutes/{$institute->uuid}/classes";
    }
}
