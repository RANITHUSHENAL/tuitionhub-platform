<?php

namespace Tests\Feature\Auth;

use App\Models\OtpChallenge;
use App\Models\Role;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Tests\Feature\Auth\Concerns\InteractsWithSpa;
use Tests\Feature\Concerns\AppFixtures;
use Tests\TestCase;

/**
 * POST /api/v1/auth/otp/verify (contract v0.4 §4.2; D-019).
 */
class OtpVerifyTest extends TestCase
{
    use AppFixtures;
    use InteractsWithSpa;
    use RefreshDatabase;

    private const PHONE = '+94700000401';

    private const GENERIC = 'The code is invalid or has expired.';

    public function test_first_login_creates_a_user_without_name_or_roles_and_logs_in(): void
    {
        $response = $this->loginWithOtp(self::PHONE)->assertOk()
            ->assertJsonPath('data.onboarding_required', true)
            ->assertJsonPath('data.user.name', null)
            ->assertJsonPath('data.user.phone', self::PHONE)
            ->assertJsonPath('data.user.roles', [])
            ->assertJsonPath('data.user.teaching_at', [])
            ->assertJsonPath('data.user.active_child_link_count', 0)
            ->assertHeader('Cache-Control', 'no-store, private');

        $user = User::query()->where('phone', self::PHONE)->sole();
        $this->assertSame('active', $user->status);
        $this->assertNotNull($user->phone_verified_at);
        $this->assertSame($user->uuid, $response->json('data.user.uuid'));
        $this->assertDatabaseCount('user_roles', 0);
        $this->assertNotNull(OtpChallenge::query()->sole()->consumed_at);

        $this->spa('GET', '/api/v1/auth/session')->assertOk()->assertJsonPath('data.user.uuid', $user->uuid);
    }

    public function test_an_existing_user_logs_in_without_duplication_and_sees_stored_roles(): void
    {
        $user = $this->makeUser(['phone' => self::PHONE, 'name' => 'Fictional Admin']);
        $institute = $this->makeInstitute($user);
        $this->insertUserRole($user->getKey(), Role::INSTITUTE_ADMIN, $institute->getKey());

        $this->loginWithOtp(self::PHONE)->assertOk()
            ->assertJsonPath('data.onboarding_required', false)
            ->assertJsonPath('data.user.roles', [['role' => 'institute_admin', 'institute_uuid' => $institute->uuid]]);

        $this->assertSame(1, User::query()->where('phone', self::PHONE)->count());
    }

    public function test_login_regenerates_the_session_and_the_old_session_id_is_not_authenticated(): void
    {
        $before = $this->spa('GET', '/sanctum/csrf-cookie')->assertNoContent();
        $oldSessionId = $this->sessionIdFrom($before);
        $oldRawCookie = $before->getCookie($this->sessionCookieName(), decrypt: false)->getValue();

        $after = $this->loginWithOtp(self::PHONE)->assertOk();
        $newSessionId = $this->sessionIdFrom($after);

        $this->assertNotNull($oldSessionId);
        $this->assertNotNull($newSessionId);
        $this->assertNotSame($oldSessionId, $newSessionId);

        $this->spa('GET', '/api/v1/auth/session')->assertOk();

        // Session fixation: replaying the pre-login session cookie is not authenticated.
        $this->withUnencryptedCookie($this->sessionCookieName(), $oldRawCookie);
        $this->spa('GET', '/api/v1/auth/session')->assertUnauthorized();
    }

    public function test_a_wrong_code_persists_the_attempt_increment_before_returning_422(): void
    {
        $challengeId = $this->requestOtp(self::PHONE)->json('data.challenge_id');

        $this->verifyOtp($challengeId, self::PHONE, $this->wrongCode())
            ->assertUnprocessable()->assertJsonPath('message', self::GENERIC)->assertJsonValidationErrors('code');

        // Read straight from the database: the increment survived the error response.
        $this->assertSame(1, (int) DB::table('otp_challenges')->where('uuid', $challengeId)->value('attempts'));
        $this->assertNull(DB::table('otp_challenges')->where('uuid', $challengeId)->value('consumed_at'));
    }

    public function test_after_max_attempts_even_the_correct_code_is_refused_with_429(): void
    {
        $challengeId = $this->requestOtp(self::PHONE)->json('data.challenge_id');

        foreach (range(1, 5) as $attempt) {
            $this->verifyOtp($challengeId, self::PHONE, $this->wrongCode())->assertUnprocessable();
            $this->assertSame($attempt, (int) DB::table('otp_challenges')->where('uuid', $challengeId)->value('attempts'));
        }

        $this->verifyOtp($challengeId, self::PHONE, $this->sentCode(self::PHONE))
            ->assertStatus(429)->assertHeader('Retry-After');

        $this->assertSame(5, (int) DB::table('otp_challenges')->where('uuid', $challengeId)->value('attempts'));
        $this->assertNull(DB::table('otp_challenges')->where('uuid', $challengeId)->value('consumed_at'));
        $this->assertDatabaseCount('users', 0);
    }

    public function test_the_guarded_increment_never_exceeds_max_attempts(): void
    {
        $challengeId = $this->requestOtp(self::PHONE)->json('data.challenge_id');
        DB::table('otp_challenges')->where('uuid', $challengeId)->update(['attempts' => 4]);

        $this->verifyOtp($challengeId, self::PHONE, $this->wrongCode())->assertUnprocessable();
        $this->verifyOtp($challengeId, self::PHONE, $this->wrongCode())->assertStatus(429);

        $this->assertSame(5, (int) DB::table('otp_challenges')->where('uuid', $challengeId)->value('attempts'));
    }

    public function test_a_challenge_is_consumed_exactly_once(): void
    {
        $challengeId = $this->requestOtp(self::PHONE)->json('data.challenge_id');
        $code = $this->sentCode(self::PHONE);

        $this->verifyOtp($challengeId, self::PHONE, $code)->assertOk();
        $consumedAt = DB::table('otp_challenges')->where('uuid', $challengeId)->value('consumed_at');

        $this->verifyOtp($challengeId, self::PHONE, $code)->assertUnprocessable()->assertJsonPath('message', self::GENERIC);

        $this->assertSame($consumedAt, DB::table('otp_challenges')->where('uuid', $challengeId)->value('consumed_at'));
        $this->assertSame(1, User::query()->where('phone', self::PHONE)->count());
    }

    public function test_expired_wrong_phone_and_unknown_challenges_get_the_same_generic_422(): void
    {
        $challengeId = $this->requestOtp(self::PHONE)->json('data.challenge_id');
        $code = $this->sentCode(self::PHONE);

        $this->verifyOtp($challengeId, '+94700000402', $code)->assertUnprocessable()->assertJsonPath('message', self::GENERIC);
        $this->verifyOtp('00000000-0000-4000-8000-000000000000', self::PHONE, $code)->assertUnprocessable()->assertJsonPath('message', self::GENERIC);

        $this->travel(301)->seconds();
        $this->verifyOtp($challengeId, self::PHONE, $code)->assertUnprocessable()->assertJsonPath('message', self::GENERIC);

        $this->assertDatabaseCount('users', 0);
    }

    public function test_a_suspended_user_with_the_correct_code_consumes_the_challenge_gets_403_and_is_not_logged_in(): void
    {
        $this->makeUser(['phone' => self::PHONE, 'status' => 'suspended']);
        $challengeId = $this->requestOtp(self::PHONE)->json('data.challenge_id');

        $this->verifyOtp($challengeId, self::PHONE, $this->sentCode(self::PHONE))
            ->assertForbidden()->assertJsonPath('message', 'This account is suspended.');

        $this->assertNotNull(DB::table('otp_challenges')->where('uuid', $challengeId)->value('consumed_at'));
        $this->spa('GET', '/api/v1/auth/session')->assertUnauthorized();
    }

    public function test_verify_requires_a_stateful_spa_request(): void
    {
        $challengeId = $this->requestOtp(self::PHONE)->json('data.challenge_id');

        $this->nonStateful('POST', '/api/v1/auth/otp/verify', [
            'challenge_id' => $challengeId, 'phone' => self::PHONE, 'code' => $this->sentCode(self::PHONE),
        ])->assertForbidden();

        $this->assertNull(DB::table('otp_challenges')->where('uuid', $challengeId)->value('consumed_at'));
    }

    public function test_verify_is_rate_limited_per_ip(): void
    {
        $challengeId = $this->requestOtp(self::PHONE)->json('data.challenge_id');

        foreach (range(1, 10) as $i) {
            $this->verifyOtp($challengeId, self::PHONE, '000000');
        }

        $this->verifyOtp($challengeId, self::PHONE, '000000')->assertStatus(429)->assertHeader('Retry-After');
    }

    public function test_malformed_input_is_rejected_with_422(): void
    {
        $this->verifyOtp('not-a-uuid', self::PHONE, '12345')
            ->assertUnprocessable()->assertJsonValidationErrors(['challenge_id', 'code']);
    }

    private function wrongCode(): string
    {
        $correct = $this->sentCode(self::PHONE);

        return $correct === '000000' ? '111111' : '000000';
    }
}
