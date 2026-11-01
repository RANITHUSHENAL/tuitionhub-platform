<?php

namespace Tests\Feature\Http\Guardians;

use App\Models\GuardianInvitation;
use App\Models\Institute;
use App\Models\StudentProfile;
use App\Services\GuardianInvitationService;
use App\Support\PhoneMasker;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Tests\Feature\Http\Concerns\AssertsReadOnly;
use Tests\Feature\Http\Concerns\GuardianFixtures;
use Tests\TestCase;

/**
 * GET /api/v1/students/{uuid}/guardian-invitations (contract v0.10 §9.7, E2b; D-028):
 * the student's invitation history, newest first. `status` — shown and filtered — is the effective
 * status (§9.1 lazy expiry), computed without changing the stored row. Never the code, its hash,
 * the full phone or an internal ID.
 */
class StudentGuardianInvitationsTest extends TestCase
{
    use AssertsReadOnly;
    use GuardianFixtures;
    use RefreshDatabase;

    public function test_unauthenticated_requests_get_401(): void
    {
        $student = $this->makeStudent($this->makeInstitute());

        $this->spa('GET', $this->invitationsUrl($student))->assertUnauthorized();
    }

    public function test_a_pending_invitation_has_the_exact_fields_and_no_secrets(): void
    {
        $institute = $this->makeInstitute();
        $student = $this->makeStudent($institute);
        $inviter = $this->staffOf($institute);
        $parent = $this->makeParent();
        [$invitation, $code] = $this->issueInvitation($inviter, $student, $parent->phone, 'father');
        $this->loginAs($this->adminOf($institute));

        $response = $this->assertReadOnly(fn () => $this->spa('GET', $this->invitationsUrl($student)))
            ->assertOk()
            ->assertHeader('Cache-Control', 'no-store, private')
            ->assertExactJson([
                'data' => [[
                    'uuid' => $invitation->uuid,
                    'relationship' => 'father',
                    'guardian_phone_masked' => PhoneMasker::mask($parent->phone),
                    'status' => 'pending',
                    'expires_at' => $invitation->expires_at->utc()->format('Y-m-d\TH:i:s\Z'),
                    'created_at' => $invitation->created_at->utc()->format('Y-m-d\TH:i:s\Z'),
                    'invited_by' => ['uuid' => $inviter->uuid, 'name' => 'Fictional Staff Member'],
                ]],
                'meta' => ['current_page' => 1, 'per_page' => 25, 'total' => 1, 'last_page' => 1],
            ]);

        $this->assertNoInternalIds($response);
        $body = $response->getContent();
        $this->assertStringNotContainsString($code, $body);
        $this->assertStringNotContainsString(str_replace('-', '', $code), $body);
        $this->assertStringNotContainsString($parent->phone, $body);
        $this->assertStringNotContainsString((string) DB::table('guardian_invitations')->value('code_hash'), $body);
        $this->assertStringNotContainsString('code', $body);
    }

    public function test_effective_status_is_shown_and_filtered_without_changing_the_stored_row(): void
    {
        [$student, $institute, $ids] = $this->invitationHistory();
        $lapsedBefore = (array) DB::table('guardian_invitations')->where('uuid', $ids['lapsed'])->first();
        $this->assertSame('pending', $lapsedBefore['status']);
        $this->loginAs($this->adminOf($institute));

        $all = $this->assertReadOnly(fn () => $this->spa('GET', $this->invitationsUrl($student)))->assertOk();
        // Newest first (assertSame also checks the order); the lapsed (stored pending) invitation is
        // reported expired but its row is left exactly as it was.
        $this->assertSame([
            $ids['revoked'] => 'revoked',
            $ids['accepted'] => 'accepted',
            $ids['pending'] => 'pending',
            $ids['lapsed'] => 'expired',
            $ids['retired'] => 'expired',
        ], array_column($all->json('data'), 'status', 'uuid'));
        $this->assertSame($lapsedBefore, (array) DB::table('guardian_invitations')->where('uuid', $ids['lapsed'])->first());

        $filtered = fn (string $status) => array_column(
            $this->assertReadOnly(fn () => $this->spa('GET', $this->invitationsUrl($student)."?status={$status}"))->assertOk()->json('data'),
            'status',
            'uuid',
        );

        $this->assertSame([$ids['pending'] => 'pending'], $filtered('pending'));
        $this->assertSame([$ids['lapsed'] => 'expired', $ids['retired'] => 'expired'], $filtered('expired'));
        $this->assertSame([$ids['accepted'] => 'accepted'], $filtered('accepted'));
        $this->assertSame([$ids['revoked'] => 'revoked'], $filtered('revoked'));

        $this->assertSame('pending', DB::table('guardian_invitations')->where('uuid', $ids['lapsed'])->value('status'));
    }

    public function test_a_pending_invitation_moves_to_expired_in_the_filters_once_its_time_passes(): void
    {
        $institute = $this->makeInstitute();
        $student = $this->makeStudent($institute);
        $admin = $this->adminOf($institute);
        [$invitation] = $this->issueInvitation($admin, $student, $this->makeParent()->phone);

        $this->travelTo($invitation->expires_at->subMinutes(5));
        $this->loginAs($admin);
        $this->spa('GET', $this->invitationsUrl($student).'?status=pending')->assertOk()->assertJsonPath('meta.total', 1);
        $this->spa('GET', $this->invitationsUrl($student).'?status=expired')->assertOk()->assertJsonPath('meta.total', 0);

        // Exactly at expires_at the invitation is expired (effectiveStatus uses <=).
        $this->travelTo($invitation->expires_at);
        $this->spa('GET', $this->invitationsUrl($student).'?status=pending')->assertOk()->assertJsonPath('meta.total', 0);
        $this->spa('GET', $this->invitationsUrl($student).'?status=expired')->assertOk()
            ->assertJsonPath('meta.total', 1)
            ->assertJsonPath('data.0.status', 'expired');

        $this->assertSame('pending', $invitation->fresh()->status);
    }

    public function test_only_this_students_invitations_are_listed(): void
    {
        $institute = $this->makeInstitute();
        $admin = $this->adminOf($institute);
        $student = $this->makeStudent($institute);
        $sibling = $this->makeStudent($institute);
        $elsewhere = $this->makeStudent($other = $this->makeInstitute());
        $this->issueInvitation($admin, $student, $this->makeParent()->phone);
        $this->issueInvitation($admin, $sibling, $this->makeParent()->phone);
        $this->issueInvitation($this->adminOf($other), $elsewhere, $this->makeParent()->phone);
        $this->loginAs($admin);

        $this->spa('GET', $this->invitationsUrl($student))->assertOk()->assertJsonPath('meta.total', 1);
        $this->spa('GET', $this->invitationsUrl($elsewhere))->assertNotFound()->assertExactJson(['message' => 'Not found.']);
    }

    public function test_authorization_matrix_and_suspended_reads(): void
    {
        $institute = $this->makeInstitute();
        $student = $this->makeStudent($institute);
        $admin = $this->adminOf($institute);
        $staff = $this->staffOf($institute);
        $parent = $this->makeParent();
        $this->issueInvitation($admin, $student, $parent->phone);
        $this->suspend($institute);

        foreach ([$admin, $staff] as $user) {
            $this->loginAs($user);
            $this->spa('GET', $this->invitationsUrl($student))->assertOk()->assertJsonPath('meta.total', 1);
        }

        $this->loginAs($this->superAdmin());
        $this->spa('GET', $this->invitationsUrl($student))->assertForbidden()->assertJsonMissingPath('reason');

        foreach ([$this->teacherAt($institute), $this->adminOf($this->makeInstitute()), $parent] as $user) {
            $this->loginAs($user);
            $this->spa('GET', $this->invitationsUrl($student))->assertNotFound()->assertExactJson(['message' => 'Not found.']);
        }
    }

    public function test_pagination_empty_lists_and_query_validation(): void
    {
        $institute = $this->makeInstitute();
        $student = $this->makeStudent($institute);
        $admin = $this->adminOf($institute);
        $this->loginAs($admin);

        $this->spa('GET', $this->invitationsUrl($student))->assertOk()->assertExactJson([
            'data' => [],
            'meta' => ['current_page' => 1, 'per_page' => 25, 'total' => 0, 'last_page' => 1],
        ]);

        for ($i = 0; $i < 3; $i++) {
            $this->issueInvitation($admin, $student, $this->makeParent()->phone);
            $this->travel(2)->seconds();
        }

        $this->spa('GET', $this->invitationsUrl($student).'?per_page=2&page=2')->assertOk()
            ->assertJsonCount(1, 'data')
            ->assertJsonPath('meta', ['current_page' => 2, 'per_page' => 2, 'total' => 3, 'last_page' => 2]);
        $this->spa('GET', $this->invitationsUrl($student).'?status=used')->assertUnprocessable()->assertJsonValidationErrors('status');
        $this->spa('GET', $this->invitationsUrl($student).'?code=ABCD-EFGH')->assertUnprocessable()->assertJsonValidationErrors('code');
        $this->spa('GET', $this->invitationsUrl($student).'?per_page=0')->assertUnprocessable()->assertJsonValidationErrors('per_page');

        // Authorization comes first: an unrelated caller with a bad query still gets 404.
        $this->loginAs($this->adminOf($this->makeInstitute()));
        $this->spa('GET', $this->invitationsUrl($student).'?code=ABCD-EFGH')->assertNotFound();
    }

    /**
     * One invitation in each effective state, created oldest to newest:
     * retired (stored `expired`), lapsed (stored `pending`, past expires_at), pending, accepted, revoked.
     *
     * @return array{0: StudentProfile, 1: Institute, 2: array<string, string>}
     */
    private function invitationHistory(): array
    {
        $institute = $this->makeInstitute();
        $student = $this->makeStudent($institute);
        $admin = $this->adminOf($institute);
        $retiredPhone = $this->makeParent()->phone;

        [$retired] = $this->issueInvitation($admin, $student, $retiredPhone);
        $this->travel(1)->minutes();
        [$lapsed] = $this->issueInvitation($admin, $student, $this->makeParent()->phone);

        // Past both expiries. Re-inviting the first phone retires its old invitation (stored `expired`);
        // the second one is never touched, so it stays stored `pending`.
        $this->travel(49)->hours();
        $this->issueInvitation($admin, $student, $retiredPhone);
        $this->assertSame(GuardianInvitation::STATUS_EXPIRED, $retired->fresh()->status);
        $this->assertSame(GuardianInvitation::STATUS_PENDING, $lapsed->fresh()->status);
        // The re-invitation itself is revoked below, to keep exactly one invitation per state.
        $reinvite = GuardianInvitation::query()->latest('id')->firstOrFail();

        $this->travel(1)->minutes();
        [$pending] = $this->issueInvitation($admin, $student, $this->makeParent()->phone);

        $this->travel(1)->minutes();
        $parent = $this->makeParent();
        $this->linkParent($admin, $student, $parent);
        $accepted = GuardianInvitation::query()->where('status', GuardianInvitation::STATUS_ACCEPTED)->sole();

        $this->travel(1)->minutes();
        $this->asActor($admin, fn () => app(GuardianInvitationService::class)->revoke($admin, $reinvite));
        // Order by created_at: the revoked re-invitation was created before `pending`; move it last
        // (fixture only) so the history has one row per state in a clear newest-first order.
        DB::table('guardian_invitations')->where('id', $reinvite->getKey())->update(['created_at' => now('UTC')]);

        return [$student, $institute, [
            'retired' => $retired->uuid,
            'lapsed' => $lapsed->uuid,
            'pending' => $pending->uuid,
            'accepted' => $accepted->uuid,
            'revoked' => $reinvite->uuid,
        ]];
    }
}
