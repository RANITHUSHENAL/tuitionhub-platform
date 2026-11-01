<?php

namespace Tests\Feature\Http\Teachers;

use App\Models\TeacherProfile;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Tests\Feature\Http\Concerns\ApiActors;
use Tests\Feature\Http\Concerns\AssertsReadOnly;
use Tests\TestCase;

/**
 * GET /api/v1/me/teacher-profile (contract v0.10 §6.5, E2b; D-028): the caller's own
 * profile, read-only. Replaces the F3 empty-POST workaround.
 */
class ShowMyTeacherProfileTest extends TestCase
{
    use ApiActors;
    use AssertsReadOnly;
    use RefreshDatabase;

    private const URL = '/api/v1/me/teacher-profile';

    public function test_unauthenticated_requests_get_401(): void
    {
        $this->spa('GET', self::URL)->assertUnauthorized();
    }

    public function test_it_returns_the_callers_own_profile_in_the_teacher_shape(): void
    {
        $user = $this->makeUser(['name' => 'Fictional Teacher Owner']);
        $profile = $this->teacherProfileFor($user);
        DB::table('teacher_profiles')->where('id', $profile->getKey())->update([
            'headline' => 'A/L Combined Maths',
            'bio' => 'Fictional bio.',
            'years_experience' => 8,
        ]);
        $this->loginAs($user);

        $response = $this->assertReadOnly(fn () => $this->spa('GET', self::URL))
            ->assertOk()
            ->assertHeader('Cache-Control', 'no-store, private')
            ->assertExactJson(['data' => [
                'uuid' => $profile->uuid,
                'display_name' => 'Mr. Fictional Teacher',
                'headline' => 'A/L Combined Maths',
                'bio' => 'Fictional bio.',
                'years_experience' => 8,
            ]]);

        $this->assertNoInternalIds($response);
    }

    public function test_a_caller_without_a_profile_gets_404_and_nothing_is_created(): void
    {
        // Another user's profile is never returned instead.
        $this->teacherProfileFor();
        $this->loginAs($this->makeUser());

        $this->assertReadOnly(fn () => $this->spa('GET', self::URL))
            ->assertNotFound()
            ->assertExactJson(['message' => 'Not found.']);

        $this->assertSame(1, TeacherProfile::query()->count());
    }

    public function test_query_fields_are_rejected(): void
    {
        $user = $this->makeUser();
        $this->teacherProfileFor($user);
        $this->loginAs($user);

        $this->spa('GET', self::URL.'?user_id=1')->assertUnprocessable()->assertJsonValidationErrors('user_id');
    }
}
