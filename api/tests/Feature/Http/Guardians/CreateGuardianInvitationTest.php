<?php

namespace Tests\Feature\Http\Guardians;

use App\Audit\AuditAction;
use App\Audit\AuditLogger;
use App\Guardians\GuardianInviteCodes;
use App\Models\GuardianInvitation;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use RuntimeException;
use Tests\Feature\Http\Concerns\GuardianFixtures;
use Tests\Support\ScriptedInviteCodes;
use Tests\TestCase;

/**
 * POST /api/v1/students/{uuid}/guardian-invitations (contract v0.7 §9.1; D-015, D-023; VP-28,
 * VP-35, VP-45, VP-47). Fictional students and phones only.
 */
class CreateGuardianInvitationTest extends TestCase
{
    use GuardianFixtures;
    use RefreshDatabase;

    private const PHONE = '+94771234567';

    private const INVITE = ['guardian_phone' => self::PHONE, 'relationship' => 'mother'];

    public function test_unauthenticated_requests_get_401(): void
    {
        $student = $this->makeStudent($this->makeInstitute());

        $this->spa('POST', $this->invitationsUrl($student), self::INVITE)->assertUnauthorized();
        $this->assertDatabaseCount('guardian_invitations', 0);
    }

    public function test_unrelated_and_teacher_only_callers_get_404_and_super_admin_gets_403(): void
    {
        $institute = $this->makeInstitute();
        $student = $this->makeStudent($institute);

        foreach ([$this->adminOf($this->makeInstitute()), $this->teacherAt($institute), $this->makeUser()] as $user) {
            $this->loginAs($user);
            $this->spa('POST', $this->invitationsUrl($student), self::INVITE)->assertNotFound()->assertExactJson(['message' => 'Not found.']);
            // Authorization runs before validation: an invalid body is still a 404.
            $this->spa('POST', $this->invitationsUrl($student), ['code' => 'X'])->assertNotFound();
        }

        $this->loginAs($this->superAdmin());
        $this->spa('POST', $this->invitationsUrl($student), self::INVITE)->assertForbidden()->assertJsonMissingPath('reason');

        $this->assertDatabaseCount('guardian_invitations', 0);
    }

    public function test_malformed_and_unknown_student_uuids_are_404(): void
    {
        $this->loginAs($this->adminOf($this->makeInstitute()));

        DB::flushQueryLog();
        DB::enableQueryLog();
        $this->spa('POST', '/api/v1/students/not-a-uuid/guardian-invitations', self::INVITE)->assertNotFound()->assertExactJson(['message' => 'Not found.']);
        $studentQueries = array_filter(array_column(DB::getQueryLog(), 'query'), fn (string $sql) => str_contains($sql, '`student_profiles`'));
        DB::disableQueryLog();
        $this->assertSame([], $studentQueries, 'A malformed UUID must not be looked up.');

        $this->spa('POST', '/api/v1/students/6b0e3f9a-1c2d-4e5f-8a9b-0c1d2e3f4a5b/guardian-invitations', self::INVITE)->assertNotFound();
    }

    public function test_a_suspended_institute_refuses_invitations_with_institute_suspended(): void
    {
        $institute = $this->makeInstitute();
        $student = $this->makeStudent($institute);
        $staff = $this->staffOf($institute);
        $this->suspend($institute);
        $this->loginAs($staff);

        $this->spa('POST', $this->invitationsUrl($student), self::INVITE)
            ->assertForbidden()
            ->assertExactJson(['message' => 'This institute is suspended.', 'reason' => 'institute_suspended']);
        $this->assertDatabaseCount('guardian_invitations', 0);
    }

    public function test_staff_create_an_invitation_and_the_code_is_returned_once_and_stored_only_as_its_hmac(): void
    {
        $institute = $this->makeInstitute();
        $student = $this->makeStudent($institute);
        $staff = $this->staffOf($institute);
        $this->loginAs($staff);
        $this->freezeSecond();

        $response = $this->spa('POST', $this->invitationsUrl($student), self::INVITE)
            ->assertCreated()
            ->assertHeader('Cache-Control', 'no-store, private');

        $invitation = GuardianInvitation::query()->sole();
        $code = $response->json('data.code');
        $this->assertMatchesRegularExpression('/^[23456789ABCDEFGHJKMNPQRSTVWXYZ]{4}-[23456789ABCDEFGHJKMNPQRSTVWXYZ]{4}$/', $code);
        $this->assertSame([
            'data' => [
                'uuid' => $invitation->uuid,
                'student' => ['uuid' => $student->uuid, 'full_name' => $student->full_name],
                'guardian_phone_masked' => '+9477*****67',
                'relationship' => 'mother',
                'status' => 'pending',
                'expires_at' => now('UTC')->addHours(48)->format('Y-m-d\TH:i:s\Z'),
                'code' => $code,
            ],
            'meta' => ['code_shown_once' => true],
        ], $response->json());
        $this->assertNoInternalIds($response);

        $codes = app(GuardianInviteCodes::class);
        $this->assertSame($codes->hash($codes->normalize($code)), $invitation->code_hash);
        $this->assertEquals($student->getKey(), $invitation->student_id);
        $this->assertEquals($institute->getKey(), $invitation->institute_id);
        $this->assertEquals($staff->getKey(), $invitation->invited_by_user_id);
        $this->assertSame(self::PHONE, $invitation->guardian_phone);
        $this->assertSame('pending', $invitation->status);

        // The plaintext code is nowhere in the database.
        $row = json_encode(DB::table('guardian_invitations')->first());
        foreach ([$code, str_replace('-', '', $code)] as $plain) {
            $this->assertStringNotContainsString($plain, $row);
            $this->assertStringNotContainsString($plain, json_encode(DB::table('audit_logs')->get()));
        }
    }

    public function test_admins_can_invite_with_every_relationship(): void
    {
        $institute = $this->makeInstitute();
        $student = $this->makeStudent($institute);
        $this->loginAs($this->adminOf($institute));

        foreach (['father', 'mother', 'guardian', 'uncle', 'aunt'] as $index => $relationship) {
            $this->spa('POST', $this->invitationsUrl($student), ['guardian_phone' => '+9477100000'.$index, 'relationship' => $relationship])
                ->assertCreated()
                ->assertJsonPath('data.relationship', $relationship);
        }

        $this->assertSame(5, GuardianInvitation::query()->count());
    }

    public function test_a_local_phone_is_stored_in_e164_and_other_formats_are_rejected(): void
    {
        $institute = $this->makeInstitute();
        $student = $this->makeStudent($institute);
        $this->loginAs($this->adminOf($institute));

        $this->spa('POST', $this->invitationsUrl($student), ['guardian_phone' => '0771234567', 'relationship' => 'father'])
            ->assertCreated()
            ->assertJsonPath('data.guardian_phone_masked', '+9477*****67');
        $this->assertSame(self::PHONE, GuardianInvitation::query()->sole()->guardian_phone);

        foreach (['94771234567', '0112345678', '077123456', '+9477123456', '+94 77 123 4567', '077-123-4567', 'not a phone', '', null, 771234567, ['+94771234568']] as $phone) {
            $this->spa('POST', $this->invitationsUrl($student), ['guardian_phone' => $phone, 'relationship' => 'father'])
                ->assertUnprocessable()->assertJsonValidationErrors('guardian_phone');
        }

        $this->assertSame(1, GuardianInvitation::query()->count());
    }

    public function test_invalid_relationships_and_unknown_fields_are_rejected_with_422(): void
    {
        $institute = $this->makeInstitute();
        $student = $this->makeStudent($institute);
        $admin = $this->adminOf($institute);
        $this->loginAs($admin);

        foreach (['other', 'Mother', 'MOTHER', '', null, ['mother']] as $relationship) {
            $this->spa('POST', $this->invitationsUrl($student), [...self::INVITE, 'relationship' => $relationship])
                ->assertUnprocessable()->assertJsonValidationErrors('relationship');
        }

        $fields = [
            'code' => '7KQ2-M9XD',
            'status' => 'accepted',
            'expires_at' => '2030-01-01T00:00:00Z',
            'student_uuid' => $student->uuid,
            'institute_id' => $institute->getKey(),
            'invited_by_user_id' => $admin->getKey(),
            'uuid' => '6b0e3f9a-1c2d-4e5f-8a9b-0c1d2e3f4a5b',
            'is_primary' => true,
        ];

        foreach ($fields as $field => $value) {
            $this->spa('POST', $this->invitationsUrl($student), [...self::INVITE, $field => $value])
                ->assertUnprocessable()->assertJsonValidationErrors($field);
        }

        $this->assertDatabaseCount('guardian_invitations', 0);
    }

    public function test_a_second_pending_invitation_for_the_same_student_and_phone_is_409(): void
    {
        $institute = $this->makeInstitute();
        $student = $this->makeStudent($institute);
        $this->loginAs($this->staffOf($institute));

        $this->spa('POST', $this->invitationsUrl($student), self::INVITE)->assertCreated();
        $this->spa('POST', $this->invitationsUrl($student), [...self::INVITE, 'relationship' => 'father'])
            ->assertStatus(409)
            ->assertExactJson(['message' => 'A pending invitation already exists for this phone number.', 'reason' => 'invitation_pending']);
        $this->spa('POST', $this->invitationsUrl($student), ['guardian_phone' => '0771234567', 'relationship' => 'mother'])
            ->assertStatus(409);

        // Another phone, or the same phone for another student, is fine.
        $this->spa('POST', $this->invitationsUrl($student), [...self::INVITE, 'guardian_phone' => '+94771234568'])->assertCreated();
        $this->spa('POST', $this->invitationsUrl($this->makeStudent($institute)), self::INVITE)->assertCreated();

        $this->assertSame(3, GuardianInvitation::query()->count());
        $this->assertCount(3, $this->auditRows(AuditAction::GUARDIAN_INVITATION_CREATED));
    }

    public function test_an_expired_pending_invitation_is_retired_and_replaced(): void
    {
        $institute = $this->makeInstitute();
        $student = $this->makeStudent($institute);
        $staff = $this->staffOf($institute);
        $this->loginAs($staff);

        $first = $this->spa('POST', $this->invitationsUrl($student), self::INVITE)->assertCreated()->json('data.uuid');

        // Stored `pending`, but past expires_at: effectively expired (lazy expiry, VP-47).
        $this->travel(48)->hours();
        $this->loginAs($staff);
        $this->spa('POST', $this->invitationsUrl($student), self::INVITE)->assertCreated();

        $this->assertSame('expired', GuardianInvitation::query()->where('uuid', $first)->value('status'));
        $this->assertSame(1, GuardianInvitation::query()->where('status', 'pending')->count());
    }

    public function test_a_new_invitation_is_allowed_after_the_previous_one_was_revoked_or_accepted(): void
    {
        $institute = $this->makeInstitute();
        $student = $this->makeStudent($institute);
        $staff = $this->staffOf($institute);
        $parent = $this->makeParent();

        [$revoked] = $this->issueInvitation($staff, $student, self::PHONE);
        DB::table('guardian_invitations')->where('id', $revoked->getKey())->update([
            'status' => 'revoked', 'revoked_at' => now('UTC'), 'revoked_by_user_id' => $staff->getKey(),
        ]);
        $this->issueInvitation($staff, $student, self::PHONE);

        $this->linkParent($staff, $this->makeStudent($institute), $parent);
        $this->issueInvitation($staff, $this->makeStudent($institute), $parent->phone);

        $this->assertSame(['accepted' => 1, 'pending' => 2, 'revoked' => 1], GuardianInvitation::query()
            ->selectRaw('status, count(*) as total')->groupBy('status')->orderBy('status')->pluck('total', 'status')->map(fn ($total) => (int) $total)->all());
    }

    public function test_inviting_your_own_phone_is_a_422(): void
    {
        $institute = $this->makeInstitute();
        $student = $this->makeStudent($institute);
        $staff = $this->staffOf($institute);
        $this->loginAs($staff);

        foreach ([$staff->phone, '0'.substr($staff->phone, 3)] as $ownPhone) {
            $this->spa('POST', $this->invitationsUrl($student), ['guardian_phone' => $ownPhone, 'relationship' => 'father'])
                ->assertUnprocessable()
                ->assertJsonPath('errors.guardian_phone.0', 'You cannot invite your own phone number.');
        }

        $this->assertDatabaseCount('guardian_invitations', 0);
    }

    public function test_the_audit_row_has_a_masked_phone_and_never_the_code_hash_full_phone_or_name(): void
    {
        $institute = $this->makeInstitute();
        $student = $this->makeStudent($institute);
        $staff = $this->staffOf($institute);
        $this->loginAs($staff);
        $this->freezeSecond();

        $response = $this->spa('POST', $this->invitationsUrl($student), self::INVITE)->assertCreated();
        $invitation = GuardianInvitation::query()->sole();

        $rows = $this->auditRows(AuditAction::GUARDIAN_INVITATION_CREATED);
        $this->assertCount(1, $rows);
        $this->assertEquals($staff->getKey(), $rows[0]['user_id']);
        $this->assertEquals($institute->getKey(), $rows[0]['institute_id']);
        $this->assertSame('guardian_invitation', $rows[0]['auditable_type']);
        $this->assertEquals($invitation->getKey(), $rows[0]['auditable_id']);
        $this->assertNull($rows[0]['old_values']);
        $this->assertEquals([
            'invitation_uuid' => $invitation->uuid,
            'student_uuid' => $student->uuid,
            'institute_uuid' => $institute->uuid,
            'relationship' => 'mother',
            'guardian_phone_masked' => '+9477*****67',
            'status' => 'pending',
            'expires_at' => $response->json('data.expires_at'),
        ], $rows[0]['new_values']);

        $stored = json_encode(DB::table('audit_logs')->get());
        foreach ([self::PHONE, '771234567', $invitation->code_hash, $response->json('data.code'), $student->full_name] as $forbidden) {
            $this->assertStringNotContainsString($forbidden, $stored);
        }
    }

    public function test_a_failed_audit_insert_rolls_the_invitation_back(): void
    {
        $institute = $this->makeInstitute();
        $student = $this->makeStudent($institute);
        $this->loginAs($this->staffOf($institute));
        $this->mock(AuditLogger::class)->shouldReceive('record')->andThrow(new RuntimeException('Fictional audit outage.'));

        $this->spa('POST', $this->invitationsUrl($student), self::INVITE)->assertStatus(500)->assertJsonMissingPath('data.code');
        $this->assertDatabaseCount('guardian_invitations', 0);
    }

    public function test_a_missing_key_fails_closed_with_503_and_nothing_else_is_affected(): void
    {
        $institute = $this->makeInstitute();
        $student = $this->makeStudent($institute);
        $this->loginAs($this->staffOf($institute));

        foreach ([null, '', 'base64:'.base64_encode('short')] as $key) {
            config(['guardian_invites.hmac_key' => $key]);

            $this->spa('POST', $this->invitationsUrl($student), self::INVITE)
                ->assertStatus(503)
                ->assertExactJson(['message' => 'Guardian invitations are not available.'])
                ->assertHeader('Cache-Control', 'no-store, private');
        }

        $this->assertDatabaseCount('guardian_invitations', 0);
        $this->spa('GET', '/api/v1/me/institutes')->assertOk();
        $this->artisan('inspire')->assertExitCode(0);
    }

    public function test_a_code_hash_collision_is_retried_with_a_new_code(): void
    {
        $institute = $this->makeInstitute();
        $student = $this->makeStudent($institute);
        $staff = $this->staffOf($institute);
        $key = (string) config('guardian_invites.hmac_key');

        // An existing invitation already uses 7KQ2M9XD.
        $taken = new ScriptedInviteCodes($key, []);
        $this->insertInvitation($this->makeStudent($institute)->getKey(), $institute->getKey(), ['code_hash' => $taken->hash('7KQ2M9XD')]);

        $codes = new ScriptedInviteCodes($key, ['7KQ2M9XD', 'ABCD2345']);
        $this->app->instance(GuardianInviteCodes::class, $codes);
        $this->loginAs($staff);

        $this->spa('POST', $this->invitationsUrl($student), self::INVITE)->assertCreated()->assertJsonPath('data.code', 'ABCD-2345');
        $this->assertSame(2, $codes->generated);
        $this->assertSame($codes->hash('ABCD2345'), GuardianInvitation::query()->where('student_id', $student->getKey())->sole()->code_hash);
        $this->assertCount(1, $this->auditRows(AuditAction::GUARDIAN_INVITATION_CREATED));
    }

    public function test_five_collisions_in_a_row_fail_without_creating_anything(): void
    {
        $institute = $this->makeInstitute();
        $student = $this->makeStudent($institute);
        $key = (string) config('guardian_invites.hmac_key');
        $codes = new ScriptedInviteCodes($key, array_fill(0, 5, '7KQ2M9XD'));
        $this->insertInvitation($this->makeStudent($institute)->getKey(), $institute->getKey(), ['code_hash' => $codes->hash('7KQ2M9XD')]);
        $this->app->instance(GuardianInviteCodes::class, $codes);
        $this->loginAs($this->staffOf($institute));

        $this->spa('POST', $this->invitationsUrl($student), self::INVITE)->assertStatus(500)->assertJsonMissingPath('data.code');
        $this->assertSame(5, $codes->generated);
        $this->assertSame(0, GuardianInvitation::query()->where('student_id', $student->getKey())->count());
    }
}
