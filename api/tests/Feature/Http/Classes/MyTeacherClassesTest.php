<?php

namespace Tests\Feature\Http\Classes;

use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Tests\Feature\Http\Concerns\ClassFixtures;
use Tests\TestCase;

/**
 * GET /api/v1/me/teacher/classes (contract v0.8 §6.4; §3; D-026).
 */
class MyTeacherClassesTest extends TestCase
{
    use ClassFixtures;
    use RefreshDatabase;

    private const URL = '/api/v1/me/teacher/classes';

    public function test_unauthenticated_requests_get_401_and_callers_without_a_teacher_profile_get_403(): void
    {
        $this->spa('GET', self::URL)->assertUnauthorized();

        $this->loginAs($this->makeUser());
        $this->spa('GET', self::URL)->assertForbidden()->assertExactJson(['message' => 'You do not have a teacher profile.']);
    }

    public function test_only_the_callers_classes_at_institutes_with_an_active_assignment_are_listed(): void
    {
        $first = $this->makeInstitute();
        $second = $this->makeInstitute();
        $teacher = $this->teacherAt($first);
        $this->insertAssignment($this->teacherProfileOf($teacher)->getKey(), $second->getKey());

        $this->makeClass($first, ['title' => 'Mine B', 'starts_on' => '2026-03-01'], $teacher);
        $this->makeClass($second, ['title' => 'Mine A', 'starts_on' => '2026-02-01', 'status' => 'draft'], $teacher);
        $this->makeClass($first, ['title' => 'Someone Else']);
        $this->loginAs($teacher);

        $response = $this->spa('GET', self::URL)->assertOk();
        $this->assertSame(['Mine A', 'Mine B'], array_column($response->json('data'), 'title'));
        $this->assertSame('draft', $response->json('data.0.status'));
        $this->assertNoInternalIds($response);

        $this->assertSame(['Mine B'], array_column($this->spa('GET', self::URL.'?status=active')->json('data'), 'title'));
        $this->spa('GET', self::URL.'?status=open')->assertUnprocessable();

        // Ending the assignment at the second institute hides its classes on the next request.
        DB::table('teacher_institute')->where('institute_id', $second->getKey())->update(['status' => 'former', 'left_on' => '2026-11-01']);
        $this->assertSame(['Mine B'], array_column($this->spa('GET', self::URL)->json('data'), 'title'));
    }
}
