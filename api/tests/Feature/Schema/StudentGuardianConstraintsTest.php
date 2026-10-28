<?php

namespace Tests\Feature\Schema;

use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Tests\Feature\Schema\Concerns\SchemaFixtures;
use Tests\TestCase;

/**
 * VP-29, VP-31 to VP-33, VP-38 (links), VP-43, VP-44, VP-50 (links) and VP-53b: student_guardians constraints.
 * Primary selection, promotion, locking and access checks are service behaviour and are not tested here.
 */
class StudentGuardianConstraintsTest extends TestCase
{
    use RefreshDatabase;
    use SchemaFixtures;

    private const MISSING_ID = 999999999;

    public function test_vp31_a_second_active_link_for_the_same_student_and_guardian_is_rejected(): void
    {
        $student = $this->insertStudentProfile($this->insertInstitute());
        $guardian = $this->insertGuardianProfile();
        $this->insertGuardianLink($student, $guardian);

        $this->assertConstraintViolation(
            fn () => $this->insertGuardianLink($student, $guardian),
            self::DUPLICATE_KEY,
            'student_guardians_active_unique',
        );
    }

    public function test_vp32_revoked_links_are_kept_and_relinking_creates_a_new_row(): void
    {
        $student = $this->insertStudentProfile($this->insertInstitute());
        $guardian = $this->insertGuardianProfile();

        $this->revokeGuardianLink($this->insertGuardianLink($student, $guardian));
        $this->revokeGuardianLink($this->insertGuardianLink($student, $guardian));
        $this->insertGuardianLink($student, $guardian);

        $links = DB::table('student_guardians')->where('student_id', $student)->where('guardian_id', $guardian);

        $this->assertSame(3, (clone $links)->count());
        $this->assertSame(1, (clone $links)->where('active_flag', 1)->count());
        $this->assertSame(2, (clone $links)->whereNotNull('revoked_at')->count());
    }

    public function test_vp33_two_active_primary_guardians_are_rejected(): void
    {
        $student = $this->insertStudentProfile($this->insertInstitute());
        $this->insertGuardianLink($student, $this->insertGuardianProfile(), ['is_primary' => 1]);

        $this->assertConstraintViolation(
            fn () => $this->insertGuardianLink($student, $this->insertGuardianProfile(), ['is_primary' => 1]),
            self::DUPLICATE_KEY,
            'student_guardians_primary_unique',
        );
    }

    public function test_vp33_revoking_the_primary_frees_the_primary_slot(): void
    {
        $student = $this->insertStudentProfile($this->insertInstitute());
        $this->revokeGuardianLink($this->insertGuardianLink($student, $this->insertGuardianProfile(), ['is_primary' => 1]));

        $this->insertGuardianLink($student, $this->insertGuardianProfile(), ['is_primary' => 1]);

        $this->assertSame(1, DB::table('student_guardians')->where('student_id', $student)->where('primary_flag', 1)->count());
    }

    public function test_vp43_an_invitation_for_another_student_is_rejected(): void
    {
        $institute = $this->insertInstitute();
        $studentA = $this->insertStudentProfile($institute);
        $studentB = $this->insertStudentProfile($institute);
        $invitation = $this->insertInvitation($studentA, $institute);

        $this->assertConstraintViolation(
            fn () => $this->insertGuardianLink($studentB, $this->insertGuardianProfile(), ['invitation_id' => $invitation]),
            self::NO_REFERENCED_ROW,
            'student_guardians_invitation_student_fk',
        );
    }

    public function test_vp43_a_matching_invitation_is_accepted_and_an_unknown_one_is_rejected(): void
    {
        $institute = $this->insertInstitute();
        $student = $this->insertStudentProfile($institute);
        $invitation = $this->insertInvitation($student, $institute);

        $this->insertGuardianLink($student, $this->insertGuardianProfile(), ['invitation_id' => $invitation]);

        $this->assertSame(1, DB::table('student_guardians')->where('invitation_id', $invitation)->count());

        $this->assertConstraintViolation(
            fn () => $this->insertGuardianLink($student, $this->insertGuardianProfile(), ['invitation_id' => self::MISSING_ID]),
            self::NO_REFERENCED_ROW,
            'student_guardians_invitation_student_fk',
        );
    }

    public function test_vp44_a_link_without_an_invitation_is_accepted(): void
    {
        $student = $this->insertStudentProfile($this->insertInstitute());

        $link = $this->insertGuardianLink($student, $this->insertGuardianProfile(), ['invitation_id' => null]);

        $this->assertNull(DB::table('student_guardians')->where('id', $link)->value('invitation_id'));
    }

    public function test_vp29_one_invitation_creates_at_most_one_link(): void
    {
        $institute = $this->insertInstitute();
        $student = $this->insertStudentProfile($institute);
        $invitation = $this->insertInvitation($student, $institute);
        $this->insertGuardianLink($student, $this->insertGuardianProfile(), ['invitation_id' => $invitation]);

        $this->assertConstraintViolation(
            fn () => $this->insertGuardianLink($student, $this->insertGuardianProfile(), ['invitation_id' => $invitation]),
            self::DUPLICATE_KEY,
            'student_guardians_invitation_unique',
        );
    }

    public function test_vp38_invalid_relationship_and_is_primary_are_rejected(): void
    {
        $student = $this->insertStudentProfile($this->insertInstitute());

        $this->assertConstraintViolation(
            fn () => $this->insertGuardianLink($student, $this->insertGuardianProfile(), ['relationship' => 'other']),
            self::CHECK_VIOLATED,
            'student_guardians_relationship_chk',
        );

        $this->assertConstraintViolation(
            fn () => $this->insertGuardianLink($student, $this->insertGuardianProfile(), ['is_primary' => 2]),
            self::CHECK_VIOLATED,
            'student_guardians_is_primary_chk',
        );
    }

    public function test_vp38_revocation_actor_and_time_must_be_set_together(): void
    {
        $student = $this->insertStudentProfile($this->insertInstitute());

        $this->assertConstraintViolation(
            fn () => $this->insertGuardianLink($student, $this->insertGuardianProfile(), ['revoked_at' => now('UTC')]),
            self::CHECK_VIOLATED,
            'student_guardians_revocation_chk',
        );

        $this->assertConstraintViolation(
            fn () => $this->insertGuardianLink($student, $this->insertGuardianProfile(), ['revoked_by_user_id' => $this->insertUser()]),
            self::CHECK_VIOLATED,
            'student_guardians_revocation_chk',
        );
    }

    public function test_vp38_revocation_cannot_precede_linking(): void
    {
        $student = $this->insertStudentProfile($this->insertInstitute());
        $linkedAt = now('UTC');

        $this->assertConstraintViolation(
            fn () => $this->insertGuardianLink($student, $this->insertGuardianProfile(), [
                'linked_at' => $linkedAt,
                'revoked_at' => $linkedAt->copy()->subSecond(),
                'revoked_by_user_id' => $this->insertUser(),
            ]),
            self::CHECK_VIOLATED,
            'student_guardians_revoked_after_linked_chk',
        );
    }

    public function test_vp50f_link_parents_must_exist(): void
    {
        $student = $this->insertStudentProfile($this->insertInstitute());

        $this->assertConstraintViolation(
            fn () => $this->insertGuardianLink($student, self::MISSING_ID),
            self::NO_REFERENCED_ROW,
            'student_guardians_guardian_fk',
        );

        $this->assertConstraintViolation(
            fn () => $this->insertGuardianLink($student, $this->insertGuardianProfile(), ['linked_by_user_id' => self::MISSING_ID]),
            self::NO_REFERENCED_ROW,
            'student_guardians_linked_by_fk',
        );

        $this->assertConstraintViolation(
            fn () => $this->insertGuardianLink(self::MISSING_ID, $this->insertGuardianProfile()),
            self::NO_REFERENCED_ROW,
            'student_guardians_student_fk',
        );
    }

    public function test_vp50f_a_linked_student_or_guardian_cannot_be_deleted(): void
    {
        $student = $this->insertStudentProfile($this->insertInstitute());
        $guardian = $this->insertGuardianProfile();
        $this->insertGuardianLink($student, $guardian);

        $this->assertConstraintViolation(
            fn () => DB::table('student_profiles')->where('id', $student)->delete(),
            self::ROW_IS_REFERENCED,
            'student_guardians_student_fk',
        );

        $this->assertConstraintViolation(
            fn () => DB::table('guardian_profiles')->where('id', $guardian)->delete(),
            self::ROW_IS_REFERENCED,
            'student_guardians_guardian_fk',
        );
    }

    public function test_vp53b_active_and_primary_flags_cannot_be_written_directly(): void
    {
        $student = $this->insertStudentProfile($this->insertInstitute());

        $this->assertConstraintViolation(
            fn () => $this->insertGuardianLink($student, $this->insertGuardianProfile(), ['active_flag' => 1]),
            self::GENERATED_COLUMN_WRITE,
            'active_flag',
        );

        $this->assertConstraintViolation(
            fn () => $this->insertGuardianLink($student, $this->insertGuardianProfile(), ['primary_flag' => 1]),
            self::GENERATED_COLUMN_WRITE,
            'primary_flag',
        );
    }
}
