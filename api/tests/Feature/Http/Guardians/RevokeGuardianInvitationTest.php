<?php

namespace Tests\Feature\Http\Guardians;

use App\Audit\AuditAction;
use App\Audit\AuditLogger;
use App\Models\GuardianInvitation;
use App\Models\Institute;
use App\Models\StudentProfile;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use RuntimeException;
use Tests\Feature\Http\Concerns\GuardianFixtures;
use Tests\TestCase;

/**
 * POST /api/v1/guardian-invitations/{uuid}/revoke (contract v0.7 §9.3; E2-5, E2-9; D-023; VP-47).
 */
class RevokeGuardianInvitationTest extends TestCase
{
    use GuardianFixtures;
    use RefreshDatabase;

    private const PHONE = '+94771234567';

    public function test_unauthenticated_requests_get_401(): void
    {
        [$invitation] = $this->pendingInvitation();

        $this->spa('POST', $this->revokeUrl($invitation))->assertUnauthorized();
        $this->assertSame('pending', $invitation->fresh()->status);
    }

    public function test_the_inviter_revokes_a_pending_invitation(): void
    {
        [$invitation, $staff, $student] = $this->pendingInvitation();
        $this->loginAs($staff);
        $this->freezeSecond();

        $response = $this->spa('POST', $this->revokeUrl($invitation))->assertOk()->assertHeader('Cache-Control', 'no-store, private');

        $this->assertSame(['data' => [
            'uuid' => $invitation->uuid,
            'status' => 'revoked',
            'revoked_at' => now('UTC')->format('Y-m-d\TH:i:s\Z'),
        ]], $response->json());

        $invitation->refresh();
        $this->assertSame('revoked', $invitation->status);
        $this->assertEquals($staff->getKey(), $invitation->revoked_by_user_id);

        $rows = $this->auditRows(AuditAction::GUARDIAN_INVITATION_REVOKED);
        $this->assertCount(1, $rows);
        $this->assertEquals($staff->getKey(), $rows[0]['user_id']);
        $this->assertSame('guardian_invitation', $rows[0]['auditable_type']);
        $this->assertSame(['status' => 'pending'], $rows[0]['old_values']);
        $this->assertEquals(['invitation_uuid' => $invitation->uuid, 'student_uuid' => $student->uuid, 'status' => 'revoked'], $rows[0]['new_values']);
        $this->assertStringNotContainsString(self::PHONE, json_encode(DB::table('audit_logs')->get()));
    }

    public function test_any_admin_of_the_institute_and_super_admin_may_revoke(): void
    {
        [$first, , , $institute] = $this->pendingInvitation();
        [$second] = $this->pendingInvitation($institute);

        // E2-5: an admin who did not send the invitation.
        $this->loginAs($this->adminOf($institute));
        $this->spa('POST', $this->revokeUrl($first))->assertOk();

        // super_admin, also while the institute is suspended (§3).
        $this->suspend($institute);
        $this->loginAs($this->superAdmin());
        $this->spa('POST', $this->revokeUrl($second))->assertOk();

        $this->assertSame(2, GuardianInvitation::query()->where('status', 'revoked')->count());
    }

    public function test_other_staff_get_403_and_unrelated_callers_get_404(): void
    {
        [$invitation, , , $institute] = $this->pendingInvitation();

        $this->loginAs($this->staffOf($institute));
        $this->spa('POST', $this->revokeUrl($invitation))->assertForbidden()->assertJsonMissingPath('reason');

        foreach ([$this->adminOf($this->makeInstitute()), $this->teacherAt($institute), $this->makeParent()] as $user) {
            $this->loginAs($user);
            $this->spa('POST', $this->revokeUrl($invitation))->assertNotFound()->assertExactJson(['message' => 'Not found.']);
        }

        $this->spa('POST', '/api/v1/guardian-invitations/not-a-uuid/revoke')->assertNotFound();
        $this->spa('POST', '/api/v1/guardian-invitations/6b0e3f9a-1c2d-4e5f-8a9b-0c1d2e3f4a5b/revoke')->assertNotFound();
        $this->assertSame('pending', $invitation->fresh()->status);
    }

    public function test_an_inviter_who_has_left_the_institute_can_no_longer_revoke(): void
    {
        [$invitation, $staff] = $this->pendingInvitation();
        DB::table('institute_staff')->where('user_id', $staff->getKey())->update(['status' => 'former', 'left_on' => now('UTC')->toDateString()]);

        $this->loginAs($staff);
        $this->spa('POST', $this->revokeUrl($invitation))->assertNotFound();
        $this->assertSame('pending', $invitation->fresh()->status);
    }

    public function test_a_suspended_institute_refuses_the_inviter_with_institute_suspended(): void
    {
        [$invitation, $staff, , $institute] = $this->pendingInvitation();
        $this->suspend($institute);
        $this->loginAs($staff);

        $this->spa('POST', $this->revokeUrl($invitation))
            ->assertForbidden()
            ->assertExactJson(['message' => 'This institute is suspended.', 'reason' => 'institute_suspended']);
    }

    public function test_only_pending_invitations_can_be_revoked(): void
    {
        [$invitation, $staff, $student] = $this->pendingInvitation();
        $this->loginAs($staff);
        $this->spa('POST', $this->revokeUrl($invitation))->assertOk();

        $this->spa('POST', $this->revokeUrl($invitation))
            ->assertStatus(409)
            ->assertExactJson(['message' => 'This invitation is no longer pending.', 'reason' => 'invitation_not_pending']);

        $parent = $this->makeParent();
        $accepted = $this->linkParent($staff, $student, $parent)->invitation()->firstOrFail();
        $this->loginAs($staff);
        $this->spa('POST', $this->revokeUrl($accepted))->assertStatus(409)->assertJsonPath('reason', 'invitation_not_pending');

        $this->assertSame('accepted', $accepted->fresh()->status);
        $this->assertCount(1, $this->auditRows(AuditAction::GUARDIAN_INVITATION_REVOKED));
    }

    public function test_an_expired_invitation_is_409_and_is_saved_as_expired(): void
    {
        [$invitation, $staff] = $this->pendingInvitation();

        $this->travel(49)->hours();
        $this->loginAs($staff);
        $this->spa('POST', $this->revokeUrl($invitation))->assertStatus(409)->assertJsonPath('reason', 'invitation_not_pending');

        $this->assertSame('expired', $invitation->fresh()->status);
        $this->assertCount(0, $this->auditRows(AuditAction::GUARDIAN_INVITATION_REVOKED));
    }

    public function test_a_revoked_code_can_no_longer_be_accepted(): void
    {
        $parent = $this->makeParent();
        [$invitation, $staff, , , $code] = $this->pendingInvitation(phone: $parent->phone);
        $this->loginAs($staff);
        $this->spa('POST', $this->revokeUrl($invitation))->assertOk();

        $this->loginAs($parent);
        $this->assertGenericInvalidCode($this->acceptCode($code));
        $this->assertDatabaseCount('student_guardians', 0);
    }

    public function test_a_body_is_rejected_with_422_after_authorization(): void
    {
        [$invitation, $staff] = $this->pendingInvitation();

        $this->loginAs($this->makeParent());
        $this->spa('POST', $this->revokeUrl($invitation), ['status' => 'revoked'])->assertNotFound();

        $this->loginAs($staff);
        $this->spa('POST', $this->revokeUrl($invitation), ['status' => 'pending'])
            ->assertUnprocessable()->assertJsonValidationErrors('status');
        $this->assertSame('pending', $invitation->fresh()->status);
    }

    public function test_an_unknown_query_field_is_rejected_with_422(): void
    {
        [$invitation, $staff] = $this->pendingInvitation();
        $this->loginAs($staff);

        $this->spa('POST', $this->revokeUrl($invitation).'?unexpected=value')
            ->assertUnprocessable()
            ->assertJsonValidationErrors('unexpected');

        $this->assertSame('pending', $invitation->fresh()->status);
        $this->assertCount(0, $this->auditRows(AuditAction::GUARDIAN_INVITATION_REVOKED));
    }

    public function test_a_failed_audit_insert_rolls_the_revocation_back(): void
    {
        [$invitation, $staff] = $this->pendingInvitation();
        $this->loginAs($staff);
        $this->mock(AuditLogger::class)->shouldReceive('record')->andThrow(new RuntimeException('Fictional audit outage.'));

        $this->spa('POST', $this->revokeUrl($invitation))->assertStatus(500);
        $this->assertSame('pending', $invitation->fresh()->status);
    }

    /**
     * @return array{0: GuardianInvitation, 1: User, 2: StudentProfile, 3: Institute, 4: string}
     */
    private function pendingInvitation(?Institute $institute = null, string $phone = self::PHONE): array
    {
        $institute ??= $this->makeInstitute();
        $student = $this->makeStudent($institute);
        $staff = $this->staffOf($institute);
        [$invitation, $code] = $this->issueInvitation($staff, $student, $phone);

        return [$invitation, $staff, $student, $institute, $code];
    }

    private function revokeUrl(GuardianInvitation $invitation): string
    {
        return "/api/v1/guardian-invitations/{$invitation->uuid}/revoke";
    }
}
