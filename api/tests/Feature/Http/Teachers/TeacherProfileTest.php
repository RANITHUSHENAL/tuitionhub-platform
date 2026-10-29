<?php

namespace Tests\Feature\Http\Teachers;

use App\Models\TeacherProfile;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Tests\Feature\Http\Concerns\ApiActors;
use Tests\TestCase;

/**
 * POST /api/v1/me/teacher-profile (contract v0.6 §6.1; FD-6). Fictional data only.
 */
class TeacherProfileTest extends TestCase
{
    use ApiActors;
    use RefreshDatabase;

    private const PROFILE = [
        'display_name' => 'Mr. Kasun Silva',
        'headline' => 'A/L Combined Maths',
        'bio' => "Fictional teacher.\nTen years of fictional classes.",
        'years_experience' => 8,
    ];

    public function test_unauthenticated_requests_get_401(): void
    {
        $this->spa('POST', '/api/v1/me/teacher-profile', self::PROFILE)->assertUnauthorized();
        $this->assertDatabaseCount('teacher_profiles', 0);
    }

    public function test_a_suspended_user_gets_403(): void
    {
        $user = $this->makeUser();
        $this->loginAs($user);
        DB::table('users')->where('id', $user->getKey())->update(['status' => 'suspended']);

        $this->spa('POST', '/api/v1/me/teacher-profile', self::PROFILE)->assertForbidden();
        $this->assertDatabaseCount('teacher_profiles', 0);
    }

    public function test_the_first_call_creates_the_profile_with_201_and_grants_no_access(): void
    {
        $user = $this->makeUser();
        $this->loginAs($user);

        $response = $this->spa('POST', '/api/v1/me/teacher-profile', self::PROFILE)->assertCreated()
            ->assertHeader('Cache-Control', 'no-store, private')
            ->assertJsonPath('data.display_name', 'Mr. Kasun Silva')
            ->assertJsonPath('data.years_experience', 8);

        $this->assertSame(['uuid', 'display_name', 'headline', 'bio', 'years_experience'], array_keys($response->json('data')));
        $this->assertNoInternalIds($response);

        $profile = TeacherProfile::query()->sole();
        $this->assertEquals($user->getKey(), $profile->user_id);
        $this->assertSame($profile->uuid, $response->json('data.uuid'));

        // A profile alone grants nothing: no role rows, no institutes, no audit row (FD-6).
        $this->assertDatabaseCount('user_roles', 0);
        $this->assertDatabaseCount('audit_logs', 0);
        $this->spa('GET', '/api/v1/me/institutes')->assertOk()->assertJsonPath('meta.total', 0);
        $this->spa('GET', '/api/v1/auth/session')->assertOk()->assertJsonPath('data.has_teacher_profile', true);
    }

    public function test_the_first_call_requires_a_display_name(): void
    {
        $this->loginAs($this->makeUser());

        $this->spa('POST', '/api/v1/me/teacher-profile', ['headline' => 'A/L Physics'])
            ->assertUnprocessable()->assertJsonValidationErrors('display_name');
        $this->assertDatabaseCount('teacher_profiles', 0);
    }

    public function test_later_calls_update_with_200_keep_the_uuid_and_leave_omitted_fields_unchanged(): void
    {
        $this->loginAs($this->makeUser());
        $uuid = $this->spa('POST', '/api/v1/me/teacher-profile', self::PROFILE)->assertCreated()->json('data.uuid');

        $this->spa('POST', '/api/v1/me/teacher-profile', ['years_experience' => 9])->assertOk()
            ->assertJsonPath('data.uuid', $uuid)
            ->assertJsonPath('data.display_name', 'Mr. Kasun Silva')
            ->assertJsonPath('data.headline', 'A/L Combined Maths')
            ->assertJsonPath('data.years_experience', 9);

        // An explicit null clears headline and bio.
        $this->spa('POST', '/api/v1/me/teacher-profile', ['headline' => null, 'bio' => null])->assertOk()
            ->assertJsonPath('data.uuid', $uuid)
            ->assertJsonPath('data.headline', null)
            ->assertJsonPath('data.bio', null)
            ->assertJsonPath('data.years_experience', 9);

        $this->assertSame(1, TeacherProfile::query()->count());
    }

    public function test_an_unchanged_update_writes_nothing(): void
    {
        $this->loginAs($this->makeUser());
        $this->spa('POST', '/api/v1/me/teacher-profile', self::PROFILE)->assertCreated();
        $updatedAt = DB::table('teacher_profiles')->value('updated_at');
        $this->travel(5)->minutes();

        $this->spa('POST', '/api/v1/me/teacher-profile', ['display_name' => ' Mr.  Kasun Silva '])->assertOk();

        $this->assertSame($updatedAt, DB::table('teacher_profiles')->value('updated_at'));
    }

    public function test_server_owned_and_unknown_fields_are_rejected_with_422(): void
    {
        $other = $this->makeUser();
        $this->loginAs($this->makeUser());

        foreach (['user_id' => $other->getKey(), 'uuid' => '6b0e3f9a-1c2d-4e5f-8a9b-0c1d2e3f4a5b', 'is_independent' => true, 'public_visibility' => 'full'] as $field => $value) {
            $this->spa('POST', '/api/v1/me/teacher-profile', [...self::PROFILE, $field => $value])
                ->assertUnprocessable()->assertJsonValidationErrors($field);
        }

        $this->assertDatabaseCount('teacher_profiles', 0);
    }

    public function test_invalid_values_are_rejected_with_422(): void
    {
        $this->loginAs($this->makeUser());

        $cases = [
            ['display_name', 'Teacher 2'],
            ['display_name', "Mr.\u{202E} Silva"],
            ['display_name', str_repeat('a', 101)],
            ['headline', str_repeat('h', 151)],
            ['headline', "A/L\nPhysics"],
            ['bio', "Fictional\0bio"],
            ['years_experience', 71],
            ['years_experience', -1],
            ['years_experience', null],
            ['years_experience', 'eight'],
        ];

        foreach ($cases as [$field, $value]) {
            $this->spa('POST', '/api/v1/me/teacher-profile', [...self::PROFILE, $field => $value])
                ->assertUnprocessable()->assertJsonValidationErrors($field);
        }

        $this->assertDatabaseCount('teacher_profiles', 0);
    }
}
