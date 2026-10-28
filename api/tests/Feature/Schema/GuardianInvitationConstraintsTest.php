<?php

namespace Tests\Feature\Schema;

use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Tests\Feature\Schema\Concerns\SchemaFixtures;
use Tests\TestCase;

/**
 * VP-27, VP-28, VP-38 (invitations), VP-50 (invitations), VP-52 and VP-53a: guardian_invitations constraints.
 * Code generation, HMAC, lazy expiry and acceptance rules are service behaviour and are not tested here.
 */
class GuardianInvitationConstraintsTest extends TestCase
{
    use RefreshDatabase;
    use SchemaFixtures;

    private const MISSING_ID = 999999999;

    public function test_vp27_invitation_institute_must_be_the_students_managing_institute(): void
    {
        $instituteA = $this->insertInstitute();
        $instituteB = $this->insertInstitute();
        $student = $this->insertStudentProfile($instituteA);

        $this->assertConstraintViolation(
            fn () => $this->insertInvitation($student, $instituteB),
            self::NO_REFERENCED_ROW,
            'guardian_invitations_student_institute_fk',
        );
    }

    public function test_vp27_matching_student_and_institute_is_accepted(): void
    {
        $institute = $this->insertInstitute();
        $student = $this->insertStudentProfile($institute);

        $this->insertInvitation($student, $institute);

        $this->assertSame(1, DB::table('guardian_invitations')->where('student_id', $student)->count());
    }

    public function test_vp28_a_second_pending_invitation_for_the_same_student_and_phone_is_rejected(): void
    {
        $institute = $this->insertInstitute();
        $student = $this->insertStudentProfile($institute);
        $this->insertInvitation($student, $institute, ['guardian_phone' => '+94710000001']);

        $this->assertConstraintViolation(
            fn () => $this->insertInvitation($student, $institute, ['guardian_phone' => '+94710000001']),
            self::DUPLICATE_KEY,
            'guardian_invitations_pending_unique',
        );
    }

    public function test_vp28_finished_invitations_do_not_block_a_new_pending_one(): void
    {
        $institute = $this->insertInstitute();
        $student = $this->insertStudentProfile($institute);
        $phone = ['guardian_phone' => '+94710000001'];

        $expired = $this->insertInvitation($student, $institute, $phone);
        DB::table('guardian_invitations')->where('id', $expired)->update(['status' => 'expired']);

        $revoked = $this->insertInvitation($student, $institute, $phone);
        DB::table('guardian_invitations')->where('id', $revoked)->update([
            'status' => 'revoked', 'revoked_at' => now('UTC'), 'revoked_by_user_id' => $this->insertUser(),
        ]);

        $accepted = $this->insertInvitation($student, $institute, $phone);
        DB::table('guardian_invitations')->where('id', $accepted)->update([
            'status' => 'accepted', 'accepted_at' => now('UTC'), 'accepted_by_user_id' => $this->insertUser(),
        ]);

        $this->insertInvitation($student, $institute, $phone);
        $this->insertInvitation($this->insertStudentProfile($institute), $institute, $phone);

        $this->assertSame(4, DB::table('guardian_invitations')->where('student_id', $student)->count());
        $this->assertSame(1, DB::table('guardian_invitations')->where('student_id', $student)->where('pending_flag', 1)->count());
    }

    public function test_vp52_code_hash_is_unique(): void
    {
        $institute = $this->insertInstitute();
        $student = $this->insertStudentProfile($institute);
        $hash = ['code_hash' => hash('sha256', 'fixture-duplicate')];
        $this->insertInvitation($student, $institute, $hash);

        $this->assertConstraintViolation(
            fn () => $this->insertInvitation($student, $institute, $hash),
            self::DUPLICATE_KEY,
            'guardian_invitations_code_hash_unique',
        );
    }

    public function test_vp38_invalid_status_relationship_and_phone_are_rejected(): void
    {
        $institute = $this->insertInstitute();
        $student = $this->insertStudentProfile($institute);

        $this->assertConstraintViolation(
            fn () => $this->insertInvitation($student, $institute, ['status' => 'cancelled']),
            self::CHECK_VIOLATED,
            'guardian_invitations_status_chk',
        );

        $this->assertConstraintViolation(
            fn () => $this->insertInvitation($student, $institute, ['relationship' => 'other']),
            self::CHECK_VIOLATED,
            'guardian_invitations_relationship_chk',
        );

        $this->assertConstraintViolation(
            fn () => $this->insertInvitation($student, $institute, ['guardian_phone' => '0771234567']),
            self::CHECK_VIOLATED,
            'guardian_invitations_phone_format_chk',
        );
    }

    public function test_vp38_acceptance_fields_must_match_the_status(): void
    {
        $institute = $this->insertInstitute();
        $student = $this->insertStudentProfile($institute);

        $this->assertConstraintViolation(
            fn () => $this->insertInvitation($student, $institute, ['status' => 'accepted', 'accepted_by_user_id' => $this->insertUser()]),
            self::CHECK_VIOLATED,
            'guardian_invitations_accepted_chk',
        );

        $this->assertConstraintViolation(
            fn () => $this->insertInvitation($student, $institute, ['accepted_at' => now('UTC'), 'accepted_by_user_id' => $this->insertUser()]),
            self::CHECK_VIOLATED,
            'guardian_invitations_accepted_chk',
        );
    }

    public function test_vp38_acceptance_cannot_be_after_expiry(): void
    {
        $institute = $this->insertInstitute();
        $student = $this->insertStudentProfile($institute);
        $expiresAt = now('UTC')->addHours(48);

        $this->assertConstraintViolation(
            fn () => $this->insertInvitation($student, $institute, [
                'status' => 'accepted',
                'expires_at' => $expiresAt,
                'accepted_at' => $expiresAt->copy()->addSecond(),
                'accepted_by_user_id' => $this->insertUser(),
            ]),
            self::CHECK_VIOLATED,
            'guardian_invitations_accept_before_expiry_chk',
        );
    }

    public function test_vp38_revocation_fields_must_match_the_status(): void
    {
        $institute = $this->insertInstitute();
        $student = $this->insertStudentProfile($institute);

        $this->assertConstraintViolation(
            fn () => $this->insertInvitation($student, $institute, ['status' => 'revoked', 'revoked_at' => now('UTC')]),
            self::CHECK_VIOLATED,
            'guardian_invitations_revoked_chk',
        );

        $this->assertConstraintViolation(
            fn () => $this->insertInvitation($student, $institute, ['revoked_at' => now('UTC')]),
            self::CHECK_VIOLATED,
            'guardian_invitations_revoked_chk',
        );
    }

    public function test_vp50d_invitation_actor_users_must_exist(): void
    {
        $institute = $this->insertInstitute();
        $student = $this->insertStudentProfile($institute);

        $this->assertConstraintViolation(
            fn () => $this->insertInvitation($student, $institute, ['invited_by_user_id' => self::MISSING_ID]),
            self::NO_REFERENCED_ROW,
            'guardian_invitations_invited_by_fk',
        );

        $this->assertConstraintViolation(
            fn () => $this->insertInvitation($student, $institute, [
                'status' => 'accepted', 'accepted_at' => now('UTC'), 'accepted_by_user_id' => self::MISSING_ID,
            ]),
            self::NO_REFERENCED_ROW,
            'guardian_invitations_accepted_by_fk',
        );

        $this->assertConstraintViolation(
            fn () => $this->insertInvitation($student, $institute, [
                'status' => 'revoked', 'revoked_at' => now('UTC'), 'revoked_by_user_id' => self::MISSING_ID,
            ]),
            self::NO_REFERENCED_ROW,
            'guardian_invitations_revoked_by_fk',
        );
    }

    public function test_vp50e_a_students_managing_institute_cannot_change_while_invited(): void
    {
        $instituteA = $this->insertInstitute();
        $instituteB = $this->insertInstitute();
        $student = $this->insertStudentProfile($instituteA);
        $this->insertInvitation($student, $instituteA);

        $this->assertConstraintViolation(
            fn () => DB::table('student_profiles')->where('id', $student)->update(['managing_institute_id' => $instituteB]),
            self::ROW_IS_REFERENCED,
            'guardian_invitations_student_institute_fk',
        );
    }

    public function test_vp53a_pending_flag_cannot_be_written_directly(): void
    {
        $institute = $this->insertInstitute();
        $student = $this->insertStudentProfile($institute);

        $this->assertConstraintViolation(
            fn () => $this->insertInvitation($student, $institute, ['pending_flag' => 1]),
            self::GENERATED_COLUMN_WRITE,
            'pending_flag',
        );
    }
}
