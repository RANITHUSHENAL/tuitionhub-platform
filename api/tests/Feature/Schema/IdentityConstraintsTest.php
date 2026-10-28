<?php

namespace Tests\Feature\Schema;

use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Tests\Feature\Schema\Concerns\SchemaFixtures;
use Tests\TestCase;

/**
 * VP-23 and VP-24: OTP, user and institute value CHECKs.
 */
class IdentityConstraintsTest extends TestCase
{
    use RefreshDatabase;
    use SchemaFixtures;

    public function test_vp23_otp_attempts_cannot_exceed_max_attempts(): void
    {
        $this->assertConstraintViolation(
            fn () => $this->insertOtpChallenge(['attempts' => 6, 'max_attempts' => 5]),
            self::CHECK_VIOLATED,
            'otp_challenges_attempts_chk',
        );
    }

    public function test_vp23_otp_purpose_is_login_only(): void
    {
        $this->assertConstraintViolation(
            fn () => $this->insertOtpChallenge(['purpose' => 'signup']),
            self::CHECK_VIOLATED,
            'otp_challenges_purpose_chk',
        );
    }

    public function test_vp24_phone_must_be_a_sri_lankan_mobile_in_e164(): void
    {
        foreach (['0771234567', '+9477123456', '+947712345678', '+94 771234567'] as $phone) {
            $this->assertConstraintViolation(
                fn () => $this->insertUser(['phone' => $phone]),
                self::CHECK_VIOLATED,
                'users_phone_format_chk',
            );
        }
    }

    public function test_vp24_user_status_pending_is_rejected(): void
    {
        $this->assertConstraintViolation(
            fn () => $this->insertUser(['status' => 'pending']),
            self::CHECK_VIOLATED,
            'users_status_chk',
        );
    }

    public function test_vp24_district_must_be_one_of_the_25_districts(): void
    {
        $this->assertConstraintViolation(
            fn () => $this->insertInstitute(['district' => 'Atlantis']),
            self::CHECK_VIOLATED,
            'institutes_district_chk',
        );
    }

    public function test_vp24_the_case_insensitive_collation_lets_non_canonical_casing_pass_the_check(): void
    {
        // Documents docs/SPRINT_SCHEMA.md §0.1: the server, not the CHECK, must store 'Colombo'.
        $institute = $this->insertInstitute(['district' => 'colombo']);

        $this->assertSame('colombo', DB::table('institutes')->where('id', $institute)->value('district'));
    }

    private function insertOtpChallenge(array $overrides = []): int
    {
        $now = now('UTC');

        return DB::table('otp_challenges')->insertGetId(array_merge([
            'uuid' => (string) Str::uuid(),
            'phone' => '+94700000001',
            'code_hash' => 'not-a-real-hash',
            'purpose' => 'login',
            'attempts' => 0,
            'max_attempts' => 5,
            'expires_at' => $now->copy()->addMinutes(5),
            'last_sent_at' => $now,
            'created_at' => $now,
            'updated_at' => $now,
        ], $overrides));
    }
}
