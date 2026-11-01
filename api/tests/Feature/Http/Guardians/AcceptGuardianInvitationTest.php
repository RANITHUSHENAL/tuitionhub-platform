<?php

namespace Tests\Feature\Http\Guardians;

use App\Audit\AuditAction;
use App\Audit\AuditLogger;
use App\Guardians\GuardianInviteCodes;
use App\Models\GuardianInvitation;
use App\Models\GuardianProfile;
use App\Models\StudentGuardian;
use App\Services\GuardianInvitationService;
use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use RuntimeException;
use Tests\Feature\Http\Concerns\GuardianFixtures;
use Tests\TestCase;

/**
 * POST /api/v1/guardian-invitations/accept (contract v0.7 §9.2; D-015 Q25/Q29/Q37, D-023; VP-29,
 * VP-30, VP-35, VP-39, VP-42 (sequential), VP-45, VP-46, VP-49). Fictional data only.
 */
class AcceptGuardianInvitationTest extends TestCase
{
    use GuardianFixtures;
    use RefreshDatabase;

    public function test_unauthenticated_requests_get_401(): void
    {
        $this->acceptCode('7KQ2-M9XD')->assertUnauthorized();
    }

    public function test_the_invited_guardian_is_linked_without_any_role(): void
    {
        $institute = $this->makeInstitute();
        $student = $this->makeStudent($institute);
        $staff = $this->staffOf($institute);
        $parent = $this->makeParent();
        [$invitation, $code] = $this->issueInvitation($staff, $student, $parent->phone, 'father');
        $rolesBefore = DB::table('user_roles')->count();
        $this->loginAs($parent);
        $this->travelTo(now('UTC')->startOfSecond());

        $response = $this->acceptCode($code)->assertCreated()->assertHeader('Cache-Control', 'no-store, private');

        $this->assertSame(['data' => [
            'student' => ['uuid' => $student->uuid, 'full_name' => $student->full_name],
            'relationship' => 'father',
            'linked_at' => now('UTC')->format('Y-m-d\TH:i:s\Z'),
        ]], $response->json());
        $this->assertNoInternalIds($response);

        $profile = GuardianProfile::query()->where('user_id', $parent->getKey())->sole();
        $link = StudentGuardian::query()->sole();
        $this->assertEquals($student->getKey(), $link->student_id);
        $this->assertEquals($profile->getKey(), $link->guardian_id);
        $this->assertSame('father', $link->relationship);
        $this->assertTrue($link->is_primary);
        $this->assertEquals($staff->getKey(), $link->linked_by_user_id, 'linked_by is the inviter.');
        $this->assertEquals($invitation->getKey(), $link->invitation_id);
        $this->assertNull($link->revoked_at);

        $invitation->refresh();
        $this->assertSame('accepted', $invitation->status);
        $this->assertEquals($parent->getKey(), $invitation->accepted_by_user_id);
        $this->assertNotNull($invitation->accepted_at);

        // Guardian access is derived from the link alone (contract §3).
        $this->assertSame($rolesBefore, DB::table('user_roles')->count());
        $this->spa('GET', '/api/v1/auth/session')->assertOk()->assertJsonPath('data.roles', []);
    }

    public function test_codes_are_normalised_before_matching(): void
    {
        $institute = $this->makeInstitute();
        $staff = $this->staffOf($institute);
        $parent = $this->makeParent();
        [, $first] = $this->issueInvitation($staff, $this->makeStudent($institute), $parent->phone);
        [, $second] = $this->issueInvitation($staff, $this->makeStudent($institute), $parent->phone);
        $this->loginAs($parent);

        $this->acceptCode(strtolower(str_replace('-', ' ', $first)))->assertCreated();
        $this->acceptCode(' '.str_replace('-', '', $second).' ')->assertCreated();
        $this->assertSame(2, StudentGuardian::query()->count());
    }

    public function test_a_used_code_cannot_be_redeemed_again(): void
    {
        $institute = $this->makeInstitute();
        $parent = $this->makeParent();
        [, $code] = $this->issueInvitation($this->staffOf($institute), $this->makeStudent($institute), $parent->phone);
        $this->loginAs($parent);

        $this->acceptCode($code)->assertCreated();
        $this->assertGenericInvalidCode($this->acceptCode($code));

        $this->assertSame(1, StudentGuardian::query()->count());
        $this->assertCount(1, $this->auditRows(AuditAction::GUARDIAN_LINK_CREATED));
    }

    public function test_a_different_account_gets_the_generic_422_and_the_invitation_stays_pending(): void
    {
        $institute = $this->makeInstitute();
        $parent = $this->makeParent();
        [$invitation, $code] = $this->issueInvitation($this->staffOf($institute), $this->makeStudent($institute), $parent->phone);

        $this->loginAs($this->makeParent());
        $this->assertGenericInvalidCode($this->acceptCode($code));

        $this->assertSame('pending', $invitation->fresh()->status);
        $this->assertDatabaseCount('student_guardians', 0);
        $this->assertDatabaseCount('guardian_profiles', 0);

        // The right guardian can still use it.
        $this->loginAs($parent);
        $this->acceptCode($code)->assertCreated();
    }

    public function test_an_unverified_phone_gets_the_generic_422(): void
    {
        $institute = $this->makeInstitute();
        $parent = $this->makeParent();
        [$invitation, $code] = $this->issueInvitation($this->staffOf($institute), $this->makeStudent($institute), $parent->phone);
        $this->loginAs($parent);
        DB::table('users')->where('id', $parent->getKey())->update(['phone_verified_at' => null]);

        $this->assertGenericInvalidCode($this->acceptCode($code));
        $this->assertSame('pending', $invitation->fresh()->status);
        $this->assertDatabaseCount('student_guardians', 0);
    }

    public function test_an_expired_invitation_gets_the_generic_422_and_is_saved_as_expired(): void
    {
        $institute = $this->makeInstitute();
        $parent = $this->makeParent();
        [$invitation, $code] = $this->issueInvitation($this->staffOf($institute), $this->makeStudent($institute), $parent->phone);

        $this->travel(48)->hours();
        $this->loginAs($parent);
        $this->assertGenericInvalidCode($this->acceptCode($code));

        // Lazy expiry is committed even though the response is an error (VP-39).
        $this->assertSame('expired', $invitation->fresh()->status);
        $this->assertDatabaseCount('student_guardians', 0);
        $this->assertCount(0, $this->auditRows(AuditAction::GUARDIAN_INVITATION_ACCEPTED));
    }

    public function test_revoked_and_unknown_codes_get_the_same_generic_422(): void
    {
        $institute = $this->makeInstitute();
        $staff = $this->staffOf($institute);
        $parent = $this->makeParent();
        [$invitation, $code] = $this->issueInvitation($staff, $this->makeStudent($institute), $parent->phone);
        $this->asActor($staff, fn () => app(GuardianInvitationService::class)->revoke($staff, $invitation));
        $this->loginAs($parent);

        $this->assertGenericInvalidCode($this->acceptCode($code));
        $this->assertGenericInvalidCode($this->acceptCode('ABCD-2345'));
        $this->assertDatabaseCount('student_guardians', 0);
    }

    public function test_malformed_codes_get_the_generic_422_without_any_invitation_lookup(): void
    {
        $this->loginAs($this->makeParent());

        DB::flushQueryLog();
        DB::enableQueryLog();
        // Five: the sixth attempt within 15 minutes would be throttled (E2-7).
        foreach (['7KQ2-M9X', '0KQ2-M9XD', 'IKQ2M9XD', '7KQ2_M9XD', '- - -'] as $code) {
            $this->assertGenericInvalidCode($this->acceptCode($code));
        }
        $lookups = array_filter(array_column(DB::getQueryLog(), 'query'), fn (string $sql) => str_contains($sql, '`guardian_invitations`'));
        DB::disableQueryLog();

        $this->assertSame([], $lookups, 'A malformed code must never reach the database (VP-46).');
    }

    public function test_a_missing_non_string_or_overlong_code_and_unknown_fields_are_422(): void
    {
        $this->loginAs($this->makeParent());

        $this->spa('POST', '/api/v1/guardian-invitations/accept', [])->assertUnprocessable()->assertJsonValidationErrors('code');
        $this->acceptCode(['7KQ2-M9XD'])->assertUnprocessable()->assertJsonValidationErrors('code');
        $this->acceptCode(12345678)->assertUnprocessable()->assertJsonValidationErrors('code');
        $this->acceptCode(str_repeat('7', 33))->assertUnprocessable()->assertJsonValidationErrors('code');
        $this->spa('POST', '/api/v1/guardian-invitations/accept', ['code' => '7KQ2-M9XD', 'student_uuid' => '6b0e3f9a-1c2d-4e5f-8a9b-0c1d2e3f4a5b'])
            ->assertUnprocessable()->assertJsonValidationErrors('student_uuid');
    }

    public function test_the_inviter_can_never_accept_their_own_invitation(): void
    {
        $institute = $this->makeInstitute();
        $student = $this->makeStudent($institute);
        $staff = $this->staffOf($institute);
        $codes = app(GuardianInviteCodes::class);
        // Create refuses a self-invite, so this row is inserted directly to test the accept guard.
        $this->insertInvitation($student->getKey(), $institute->getKey(), [
            'invited_by_user_id' => $staff->getKey(),
            'guardian_phone' => $staff->phone,
            'code_hash' => $codes->hash('ABCD2345'),
        ]);
        $this->loginAs($staff);

        $this->assertGenericInvalidCode($this->acceptCode('ABCD-2345'));
        $this->assertDatabaseCount('student_guardians', 0);
        $this->assertSame('pending', GuardianInvitation::query()->sole()->status);
    }

    public function test_an_already_active_link_is_409_and_the_new_invitation_stays_pending(): void
    {
        $institute = $this->makeInstitute();
        $student = $this->makeStudent($institute);
        $staff = $this->staffOf($institute);
        $parent = $this->makeParent();
        $this->linkParent($staff, $student, $parent);
        [$second, $code] = $this->issueInvitation($staff, $student, $parent->phone, 'guardian');
        $this->loginAs($parent);

        $this->acceptCode($code)
            ->assertStatus(409)
            ->assertExactJson(['message' => 'This guardian is already linked to the student.', 'reason' => 'link_already_active']);

        $this->assertSame('pending', $second->fresh()->status);
        $this->assertSame(1, StudentGuardian::query()->count());
    }

    public function test_the_first_active_guardian_becomes_primary_and_later_ones_do_not(): void
    {
        $institute = $this->makeInstitute();
        $student = $this->makeStudent($institute);
        $staff = $this->staffOf($institute);
        [$mother, $father, $aunt] = [$this->makeParent(), $this->makeParent(), $this->makeParent()];

        $first = $this->linkParent($staff, $student, $mother);
        $second = $this->linkParent($staff, $student, $father, 'father');

        $this->assertTrue($first->fresh()->is_primary);
        $this->assertFalse($second->fresh()->is_primary);

        // Once every link is revoked, the next guardian becomes primary again.
        DB::table('student_guardians')->update(['revoked_at' => now('UTC'), 'revoked_by_user_id' => $staff->getKey()]);
        $third = $this->linkParent($staff, $student, $aunt, 'aunt');
        $this->assertTrue($third->fresh()->is_primary);
        $this->assertSame(1, StudentGuardian::query()->active()->where('is_primary', true)->count());
    }

    public function test_both_audit_rows_record_the_acceptance_and_the_initial_primary_without_personal_data(): void
    {
        $institute = $this->makeInstitute();
        $student = $this->makeStudent($institute);
        $staff = $this->staffOf($institute);
        [$mother, $father] = [$this->makeParent(), $this->makeParent()];
        [$invitation, $code] = $this->issueInvitation($staff, $student, $mother->phone);
        $this->loginAs($mother);

        $this->acceptCode($code)->assertCreated();
        $link = StudentGuardian::query()->sole();

        $accepted = $this->auditRows(AuditAction::GUARDIAN_INVITATION_ACCEPTED);
        $this->assertCount(1, $accepted);
        $this->assertEquals($mother->getKey(), $accepted[0]['user_id']);
        $this->assertEquals($institute->getKey(), $accepted[0]['institute_id']);
        $this->assertSame('guardian_invitation', $accepted[0]['auditable_type']);
        $this->assertSame(['status' => 'pending'], $accepted[0]['old_values']);
        $this->assertEquals(['invitation_uuid' => $invitation->uuid, 'student_uuid' => $student->uuid, 'status' => 'accepted'], $accepted[0]['new_values']);

        $created = $this->auditRows(AuditAction::GUARDIAN_LINK_CREATED);
        $this->assertCount(1, $created);
        $this->assertSame('student_guardian', $created[0]['auditable_type']);
        $this->assertEquals($link->getKey(), $created[0]['auditable_id']);
        $this->assertSame(['primary_link_id' => null], $created[0]['old_values']);
        $this->assertEquals([
            'student_uuid' => $student->uuid,
            'guardian_uuid' => $mother->uuid,
            'relationship' => 'mother',
            'is_primary' => true,
            'primary_link_id' => $link->getKey(),
        ], $created[0]['new_values']);

        // A second, non-primary guardian: no primary change is recorded.
        $this->linkParent($staff, $student, $father, 'father');
        $second = $this->auditRows(AuditAction::GUARDIAN_LINK_CREATED)[1];
        $this->assertNull($second['old_values']);
        $this->assertArrayNotHasKey('primary_link_id', $second['new_values']);
        $this->assertFalse($second['new_values']['is_primary']);

        $stored = json_encode(DB::table('audit_logs')->get());
        foreach ([$mother->phone, $father->phone, $student->full_name, $code, str_replace('-', '', $code), $invitation->code_hash] as $forbidden) {
            $this->assertStringNotContainsString($forbidden, $stored);
        }
    }

    public function test_a_failed_audit_insert_rolls_the_whole_acceptance_back(): void
    {
        $institute = $this->makeInstitute();
        $parent = $this->makeParent();
        [$invitation, $code] = $this->issueInvitation($this->staffOf($institute), $this->makeStudent($institute), $parent->phone);
        $this->loginAs($parent);
        $this->mock(AuditLogger::class)->shouldReceive('record')->andThrow(new RuntimeException('Fictional audit outage.'));

        $this->acceptCode($code)->assertStatus(500);

        $this->assertSame('pending', $invitation->fresh()->status);
        $this->assertDatabaseCount('student_guardians', 0);
        $this->assertDatabaseCount('guardian_profiles', 0);
    }

    public function test_six_attempts_in_15_minutes_are_throttled_per_user(): void
    {
        $parent = $this->makeParent();
        $other = $this->makeParent();
        $this->loginAs($parent);

        for ($i = 1; $i <= 5; $i++) {
            $this->assertGenericInvalidCode($this->acceptCode('ABCD-234'.($i + 1)));
        }

        // Every attempt counts (E2-7), even a correctly formed one.
        $this->acceptCode('ABCD-2345')->assertStatus(429)->assertHeader('Retry-After')->assertHeader('Cache-Control', 'no-store, private');

        // Keyed by user: another account is unaffected.
        $this->loginAs($other);
        $this->assertGenericInvalidCode($this->acceptCode('ABCD-2345'));

        // The window resets after 15 minutes.
        $this->travel(15)->minutes();
        $this->loginAs($parent);
        $this->assertGenericInvalidCode($this->acceptCode('ABCD-2345'));
    }

    public function test_a_suspended_institute_refuses_the_invited_guardian_but_reveals_nothing_to_anyone_else(): void
    {
        $institute = $this->makeInstitute();
        $parent = $this->makeParent();
        [$invitation, $code] = $this->issueInvitation($this->staffOf($institute), $this->makeStudent($institute), $parent->phone);
        $this->suspend($institute);

        $this->loginAs($this->makeParent());
        $this->assertGenericInvalidCode($this->acceptCode($code));

        $this->loginAs($parent);
        $this->acceptCode($code)
            ->assertForbidden()
            ->assertExactJson(['message' => 'This institute is suspended.', 'reason' => 'institute_suspended']);

        $this->assertSame('pending', $invitation->fresh()->status);
        $this->assertDatabaseCount('student_guardians', 0);
    }

    public function test_a_missing_key_fails_closed_with_503_before_the_code_is_examined(): void
    {
        $this->loginAs($this->makeParent());
        config(['guardian_invites.hmac_key' => null]);

        foreach (['ABCD-2345', 'malformed'] as $code) {
            $this->acceptCode($code)->assertStatus(503)->assertExactJson(['message' => 'Guardian invitations are not available.']);
        }

        $this->spa('GET', '/api/v1/parents/me/children')->assertOk();
    }

    public function test_an_existing_guardian_profile_is_reused(): void
    {
        $institute = $this->makeInstitute();
        $staff = $this->staffOf($institute);
        $parent = $this->makeParent();
        $this->linkParent($staff, $this->makeStudent($institute), $parent);
        $this->linkParent($this->staffOf($otherInstitute = $this->makeInstitute()), $this->makeStudent($otherInstitute), $parent);

        $this->assertSame(1, GuardianProfile::query()->where('user_id', $parent->getKey())->count());
        $this->assertSame(2, StudentGuardian::query()->active()->count());
    }

    public function test_an_invitation_from_staff_who_have_since_left_stays_valid_until_it_expires(): void
    {
        $institute = $this->makeInstitute();
        $staff = $this->staffOf($institute);
        $parent = $this->makeParent();
        [, $code] = $this->issueInvitation($staff, $this->makeStudent($institute), $parent->phone);

        // E2-9: the inviter leaves the institute.
        DB::table('institute_staff')->where('user_id', $staff->getKey())->update(['status' => 'former', 'left_on' => now('UTC')->toDateString()]);
        DB::table('user_roles')->where('user_id', $staff->getKey())->delete();

        $this->loginAs($parent);
        $this->acceptCode($code)->assertCreated();
        $this->assertEquals($staff->getKey(), StudentGuardian::query()->sole()->linked_by_user_id);
    }

    public function test_an_account_suspended_after_the_request_check_is_refused_by_the_service(): void
    {
        $institute = $this->makeInstitute();
        $parent = $this->makeParent();
        [$invitation, $code] = $this->issueInvitation($this->staffOf($institute), $this->makeStudent($institute), $parent->phone);
        DB::table('users')->where('id', $parent->getKey())->update(['status' => 'suspended']);

        try {
            $this->asActor($parent, fn () => app(GuardianInvitationService::class)->accept($parent, $code));
            $this->fail('Expected the suspended account to be refused.');
        } catch (AuthorizationException $exception) {
            $this->assertSame('This account is suspended.', $exception->getMessage());
        }

        $this->assertSame('pending', $invitation->fresh()->status);
    }

    public function test_codes_and_phones_never_reach_the_log(): void
    {
        $this->captureLogs();
        $institute = $this->makeInstitute();
        $student = $this->makeStudent($institute);
        $parent = $this->makeParent();
        $this->loginAs($this->staffOf($institute));

        $code = $this->spa('POST', $this->invitationsUrl($student), ['guardian_phone' => $parent->phone, 'relationship' => 'mother'])->assertCreated()->json('data.code');
        $this->spa('POST', $this->invitationsUrl($student), ['guardian_phone' => $parent->phone, 'relationship' => 'mother'])->assertStatus(409);

        $this->loginAs($this->makeParent());
        $this->assertGenericInvalidCode($this->acceptCode($code));
        $this->loginAs($parent);
        $this->acceptCode($code)->assertCreated();
        $this->assertGenericInvalidCode($this->acceptCode($code));

        $logged = json_encode($this->logged);
        foreach ([$code, str_replace('-', '', $code), $parent->phone, substr($parent->phone, 3)] as $secret) {
            $this->assertStringNotContainsString($secret, $logged);
        }
    }
}
