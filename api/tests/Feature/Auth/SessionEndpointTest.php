<?php

namespace Tests\Feature\Auth;

use App\Models\Role;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Tests\Feature\Auth\Concerns\InteractsWithSpa;
use Tests\Feature\Concerns\AppFixtures;
use Tests\TestCase;

/**
 * GET /api/v1/auth/session and POST /api/v1/auth/logout (contract v0.4 §4.3–§4.4).
 */
class SessionEndpointTest extends TestCase
{
    use AppFixtures;
    use InteractsWithSpa;
    use RefreshDatabase;

    public function test_unauthenticated_requests_get_401(): void
    {
        $this->spa('GET', '/api/v1/auth/session')
            ->assertUnauthorized()
            ->assertHeader('Cache-Control', 'no-store, private');
    }

    public function test_the_session_shows_stored_roles_active_teaching_and_active_child_links_only(): void
    {
        $user = $this->makeUser(['name' => 'Fictional Person']);
        $instituteA = $this->makeInstitute(null, ['name' => 'Fictional A Academy']);
        $instituteB = $this->makeInstitute(null, ['name' => 'Fictional B Academy']);
        $this->insertUserRole($user->getKey(), Role::SUPER_ADMIN, null);
        $this->insertUserRole($user->getKey(), Role::INSTITUTE_ADMIN, $instituteA->getKey());
        $teacher = $this->insertTeacherProfile(['user_id' => $user->getKey()]);
        $this->insertAssignment($teacher, $instituteA->getKey());
        $this->insertAssignment($teacher, $instituteB->getKey(), ['status' => 'former', 'left_on' => '2026-10-10']);
        $guardian = $this->insertGuardianProfile(['user_id' => $user->getKey()]);
        $student = $this->insertStudentProfile($instituteA->getKey());
        $this->insertGuardianLink($student, $guardian);
        $this->revokeGuardianLink($this->insertGuardianLink($this->insertStudentProfile($instituteA->getKey()), $guardian));

        $this->loginWithOtp($user->phone)->assertOk();

        $this->spa('GET', '/api/v1/auth/session')->assertOk()
            ->assertJsonPath('data.user.uuid', $user->uuid)
            ->assertJsonPath('data.user.status', 'active')
            ->assertJsonPath('data.roles', [
                ['role' => 'super_admin', 'institute_uuid' => null, 'institute_name' => null],
                ['role' => 'institute_admin', 'institute_uuid' => $instituteA->uuid, 'institute_name' => 'Fictional A Academy'],
            ])
            ->assertJsonPath('data.teaching_at', [['institute_uuid' => $instituteA->uuid, 'institute_name' => 'Fictional A Academy']])
            ->assertJsonPath('data.active_child_link_count', 1)
            ->assertJsonPath('data.has_teacher_profile', true)
            ->assertJsonPath('data.has_guardian_profile', true)
            ->assertJsonPath('data.onboarding_required', false);
    }

    public function test_no_internal_ids_are_exposed(): void
    {
        $user = $this->makeUser();
        $this->insertUserRole($user->getKey(), Role::INSTITUTE_ADMIN, $this->makeInstitute()->getKey());

        $verify = $this->loginWithOtp($user->phone)->assertOk()->json();
        $session = $this->spa('GET', '/api/v1/auth/session')->assertOk()->json();

        foreach ([$verify, $session] as $payload) {
            $this->assertNoInternalIds($payload);
        }
    }

    public function test_a_user_suspended_after_login_gets_403_but_can_still_log_out(): void
    {
        $user = $this->makeUser();
        $this->loginWithOtp($user->phone)->assertOk();

        DB::table('users')->where('id', $user->getKey())->update(['status' => 'suspended']);

        $this->spa('GET', '/api/v1/auth/session')->assertForbidden();
        $this->spa('POST', '/api/v1/auth/logout')->assertNoContent();
    }

    public function test_logout_invalidates_the_session_and_regenerates_the_csrf_token(): void
    {
        $user = $this->makeUser();
        $login = $this->loginWithOtp($user->phone)->assertOk();
        $tokenBefore = $login->getCookie('XSRF-TOKEN')?->getValue();

        $logout = $this->spa('POST', '/api/v1/auth/logout')->assertNoContent();
        $tokenAfter = $logout->getCookie('XSRF-TOKEN')?->getValue();

        $this->assertNotNull($tokenAfter);
        $this->assertNotSame($tokenBefore, $tokenAfter);
        $this->spa('GET', '/api/v1/auth/session')->assertUnauthorized();
    }

    public function test_logout_and_session_require_a_stateful_spa_request(): void
    {
        $this->nonStateful('POST', '/api/v1/auth/logout')->assertUnauthorized();
        $this->nonStateful('GET', '/api/v1/auth/session')->assertUnauthorized();
    }

    /**
     * @param  array<mixed>  $payload
     */
    private function assertNoInternalIds(array $payload): void
    {
        foreach ($payload as $key => $value) {
            if (is_string($key)) {
                $this->assertFalse($key === 'id' || str_ends_with($key, '_id'), "Internal id key [{$key}] exposed.");
            }

            if (is_array($value)) {
                $this->assertNoInternalIds($value);
            }
        }
    }
}
