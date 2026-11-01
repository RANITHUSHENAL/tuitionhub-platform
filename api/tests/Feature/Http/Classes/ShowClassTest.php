<?php

namespace Tests\Feature\Http\Classes;

use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Tests\Feature\Http\Concerns\ClassFixtures;
use Tests\TestCase;

/**
 * GET /api/v1/classes/{uuid} (contract v0.8 §7.3; D-026): public minimal shape for a publicly
 * listed class; the full `Class` for the assigned teacher, staff, admins and super_admin; 404
 * otherwise.
 */
class ShowClassTest extends TestCase
{
    use ClassFixtures;
    use RefreshDatabase;

    public function test_anyone_sees_the_public_shape_of_a_publicly_listed_class(): void
    {
        $institute = $this->makeInstitute(overrides: ['name' => 'Lakmina Academy', 'district' => 'Colombo', 'city' => 'Nugegoda']);
        $class = $this->makeClass($institute, ['public_listing' => 1, 'description' => 'Fictional theory class.']);
        $teacher = $this->teacherProfileOf($this->teacherUserOf($class));

        $response = $this->nonStateful('GET', $this->classUrl($class))->assertOk()->assertHeader('Cache-Control', 'no-store, private');

        $this->assertSame([
            'uuid' => $class->uuid,
            'title' => $class->title,
            'description' => 'Fictional theory class.',
            'mode' => 'in_person',
            'starts_on' => '2026-01-01',
            'subject' => ['code' => 'COMBINED_MATH', 'name' => 'Combined Mathematics'],
            'grade' => ['code' => 'G11', 'name' => 'Grade 11'],
            'stream' => null,
            'exam_target' => ['code' => 'AL_2027', 'name' => 'A/L 2027'],
            'medium' => ['code' => 'sin', 'name' => 'Sinhala'],
            'institute' => ['name' => 'Lakmina Academy', 'district' => 'Colombo', 'city' => 'Nugegoda'],
            'teacher' => ['display_name' => $teacher->display_name],
            'current_fee' => ['amount' => '2500.00', 'currency' => 'LKR', 'billing_cycle' => 'monthly', 'effective_from' => '2026-01-01'],
        ], $response->json('data'));
        $this->assertNoInternalIds($response);

        // A signed-in user with no relationship gets the same public shape.
        $this->loginAs($this->makeUser());
        $this->spa('GET', $this->classUrl($class))->assertOk()->assertJsonMissingPath('data.max_students')->assertJsonMissingPath('data.status');
    }

    public function test_a_hidden_teacher_is_omitted_from_the_public_shape(): void
    {
        $class = $this->makeClass($this->makeInstitute(), ['public_listing' => 1]);
        DB::table('teacher_profiles')->where('id', $class->teacher_id)->update(['public_visibility' => 'hidden']);

        $this->nonStateful('GET', $this->classUrl($class))->assertOk()->assertJsonMissingPath('data.teacher');
    }

    public function test_draft_archived_private_and_suspended_institute_classes_are_404_to_the_public(): void
    {
        $institute = $this->makeInstitute();
        $suspended = $this->makeInstitute();
        $classes = [
            $this->makeClass($institute, ['status' => 'draft', 'public_listing' => 1]),
            $this->makeClass($institute, ['status' => 'archived', 'public_listing' => 1]),
            $this->makeClass($institute, ['public_listing' => 0]),
            $this->makeClass($suspended, ['public_listing' => 1]),
        ];
        $this->suspend($suspended);

        foreach ($classes as $class) {
            $this->nonStateful('GET', $this->classUrl($class))->assertNotFound()->assertExactJson(['message' => 'Not found.']);
        }

        $this->nonStateful('GET', '/api/v1/classes/not-a-uuid')->assertNotFound();
        $this->nonStateful('GET', '/api/v1/classes/6b0e3f9a-1c2d-4e5f-8a9b-0c1d2e3f4a5b')->assertNotFound();
    }

    public function test_staff_admins_super_admin_and_the_assigned_teacher_see_the_full_class_even_when_private(): void
    {
        $institute = $this->makeInstitute();
        $class = $this->makeClass($institute, ['status' => 'draft', 'public_listing' => 0, 'max_students' => 25]);
        $this->enrol($class);
        $this->suspend($institute);

        foreach ([$this->staffOf($institute), $this->adminOf($institute), $this->superAdmin(), $this->teacherUserOf($class)] as $user) {
            $this->loginAs($user);
            $this->spa('GET', $this->classUrl($class))
                ->assertOk()
                ->assertJsonPath('data.status', 'draft')
                ->assertJsonPath('data.max_students', 25)
                ->assertJsonPath('data.active_enrollment_count', 1)
                ->assertJsonPath('data.syllabus.code', 'local');
        }
    }

    public function test_other_teachers_and_a_teacher_whose_assignment_ended_cannot_see_a_private_class(): void
    {
        $institute = $this->makeInstitute();
        $class = $this->makeClass($institute, ['public_listing' => 0]);
        $assigned = $this->teacherUserOf($class);

        $this->loginAs($this->teacherAt($institute));
        $this->spa('GET', $this->classUrl($class))->assertNotFound();

        $this->loginAs($this->adminOf($this->makeInstitute()));
        $this->spa('GET', $this->classUrl($class))->assertNotFound();

        // Access ends with the assignment, although classes.teacher_id still names the teacher (§3).
        DB::table('teacher_institute')->where('teacher_id', $class->teacher_id)->update(['status' => 'former', 'left_on' => '2026-11-01']);
        $this->loginAs($assigned);
        $this->spa('GET', $this->classUrl($class))->assertNotFound();
    }

    public function test_current_fee_is_the_version_in_force_today_or_else_the_earliest_future_one(): void
    {
        $institute = $this->makeInstitute();
        $class = $this->makeClass($institute, ['public_listing' => 1]);
        DB::table('class_fee_versions')->where('class_id', $class->getKey())->update(['effective_to' => '2026-11-30']);
        $this->insertFeeVersion($class->getKey(), ['amount' => '2750.00', 'effective_from' => '2026-12-01']);

        $this->nonStateful('GET', $this->classUrl($class))->assertOk()->assertJsonPath('data.current_fee.amount', '2500.00');

        $future = $this->insertClass($institute->getKey(), ['teacher_id' => $class->teacher_id, 'starts_on' => '2027-01-01', 'status' => 'active', 'public_listing' => 1]);
        $this->insertFeeVersion($future, ['amount' => '3000.00', 'effective_from' => '2027-01-01']);
        $uuid = DB::table('classes')->where('id', $future)->value('uuid');

        $this->nonStateful('GET', "/api/v1/classes/{$uuid}")->assertOk()->assertJsonPath('data.current_fee.effective_from', '2027-01-01');
    }
}
