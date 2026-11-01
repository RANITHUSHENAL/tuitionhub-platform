<?php

namespace Tests\Feature\Http\Guardians;

use App\Audit\AuditAction;
use App\Audit\AuditLogger;
use App\Models\Institute;
use App\Models\StudentGuardian;
use App\Models\StudentProfile;
use App\Models\User;
use App\Services\GuardianLinkService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use RuntimeException;
use Tests\Feature\Http\Concerns\GuardianFixtures;
use Tests\TestCase;

/**
 * DELETE /api/v1/students/{uuid}/guardian-links/{guardianUuid} (contract v0.7 §9.4; C6; D-015
 * Q25/Q37, D-023; VP-34, VP-41, VP-49).
 */
class RevokeGuardianLinkTest extends TestCase
{
    use GuardianFixtures;
    use RefreshDatabase;

    public function test_unauthenticated_requests_get_401(): void
    {
        [$student, $parent] = $this->linkedStudent();

        $this->spa('DELETE', $this->linkUrl($student, $parent))->assertUnauthorized();
        $this->assertSame(1, StudentGuardian::query()->active()->count());
    }

    public function test_an_admin_soft_revokes_the_link_and_history_is_kept(): void
    {
        [$student, $parent, $institute] = $this->linkedStudent();
        $admin = $this->adminOf($institute);
        $this->loginAs($admin);

        $this->spa('DELETE', $this->linkUrl($student, $parent))
            ->assertNoContent()
            ->assertHeader('Cache-Control', 'no-store, private');

        $link = StudentGuardian::query()->sole();
        $this->assertNotNull($link->revoked_at);
        $this->assertEquals($admin->getKey(), $link->revoked_by_user_id);
        $this->assertSame(0, StudentGuardian::query()->active()->count());

        // Re-linking creates a new row through a new invitation; the revoked row stays (C6).
        $this->linkParent($this->staffOf($institute), $student, $parent, 'father');
        $this->assertSame(2, StudentGuardian::query()->count());
        $this->assertSame(1, StudentGuardian::query()->active()->count());
        $this->assertNotNull($link->fresh()->revoked_at);
    }

    public function test_super_admin_may_revoke_even_while_the_institute_is_suspended(): void
    {
        [$student, $parent, $institute] = $this->linkedStudent();
        $this->suspend($institute);

        $this->loginAs($this->adminOf($institute));
        $this->spa('DELETE', $this->linkUrl($student, $parent))
            ->assertForbidden()
            ->assertExactJson(['message' => 'This institute is suspended.', 'reason' => 'institute_suspended']);

        $this->loginAs($this->superAdmin());
        $this->spa('DELETE', $this->linkUrl($student, $parent))->assertNoContent();
        $this->assertSame(0, StudentGuardian::query()->active()->count());
    }

    public function test_staff_get_403_and_unrelated_callers_get_404(): void
    {
        [$student, $parent, $institute] = $this->linkedStudent();

        $this->loginAs($this->staffOf($institute));
        $this->spa('DELETE', $this->linkUrl($student, $parent))->assertForbidden()->assertJsonMissingPath('reason');

        foreach ([$this->adminOf($this->makeInstitute()), $this->teacherAt($institute), $parent] as $user) {
            $this->loginAs($user);
            $this->spa('DELETE', $this->linkUrl($student, $parent))->assertNotFound()->assertExactJson(['message' => 'Not found.']);
        }

        $this->assertSame(1, StudentGuardian::query()->active()->count());
    }

    public function test_no_active_link_unknown_or_malformed_guardians_and_students_are_the_same_404(): void
    {
        [$student, $parent, $institute] = $this->linkedStudent();
        $this->loginAs($this->adminOf($institute));

        $urls = [
            $this->linkUrl($student, $this->makeParent()),
            $this->linkUrl($student, 'not-a-uuid'),
            $this->linkUrl($student, '6b0e3f9a-1c2d-4e5f-8a9b-0c1d2e3f4a5b'),
            $this->linkUrl($this->makeStudent($institute), $parent),
            "/api/v1/students/not-a-uuid/guardian-links/{$parent->uuid}",
        ];

        foreach ($urls as $url) {
            $this->spa('DELETE', $url)->assertNotFound()->assertExactJson(['message' => 'Not found.']);
        }

        // A link that is already revoked is no longer active: 404, and the history is untouched.
        $this->spa('DELETE', $this->linkUrl($student, $parent))->assertNoContent();
        $revokedAt = StudentGuardian::query()->sole()->revoked_at;
        $this->travel(5)->minutes();
        $this->loginAs($this->adminOf($institute));
        $this->spa('DELETE', $this->linkUrl($student, $parent))->assertNotFound();
        $this->assertEquals($revokedAt, StudentGuardian::query()->sole()->revoked_at);
    }

    public function test_revoking_the_primary_promotes_the_oldest_remaining_link_in_order(): void
    {
        $institute = $this->makeInstitute();
        $student = $this->makeStudent($institute);
        $staff = $this->staffOf($institute);
        [$a, $b, $c] = [$this->makeParent(), $this->makeParent(), $this->makeParent()];

        $linkA = $this->linkParent($staff, $student, $a);
        $this->travel(1)->minutes();
        $linkB = $this->linkParent($staff, $student, $b, 'father');
        $this->travel(1)->minutes();
        $linkC = $this->linkParent($staff, $student, $c, 'aunt');
        $this->assertSame([true, false, false], [$linkA->fresh()->is_primary, $linkB->fresh()->is_primary, $linkC->fresh()->is_primary]);

        $this->loginAs($this->adminOf($institute));

        $this->spa('DELETE', $this->linkUrl($student, $a))->assertNoContent();
        $this->assertSame($linkB->getKey(), $this->activePrimaryId($student));

        $this->spa('DELETE', $this->linkUrl($student, $b))->assertNoContent();
        $this->assertSame($linkC->getKey(), $this->activePrimaryId($student));

        $this->spa('DELETE', $this->linkUrl($student, $c))->assertNoContent();
        $this->assertNull($this->activePrimaryId($student));

        // VP-49: every revocation of a primary records the old and the new primary link IDs.
        $rows = $this->auditRows(AuditAction::GUARDIAN_LINK_REVOKED);
        $this->assertCount(3, $rows);
        $this->assertSame([
            [['primary_link_id' => $linkA->getKey()], $linkB->getKey()],
            [['primary_link_id' => $linkB->getKey()], $linkC->getKey()],
            [['primary_link_id' => $linkC->getKey()], null],
        ], array_map(fn (array $row) => [$row['old_values'], $row['new_values']['primary_link_id']], $rows));
        $this->assertEquals([
            'student_uuid' => $student->uuid,
            'guardian_uuid' => $a->uuid,
            'relationship' => 'mother',
            'primary_link_id' => $linkB->getKey(),
        ], $rows[0]['new_values']);
        $this->assertSame('student_guardian', $rows[0]['auditable_type']);
        $this->assertEquals($linkA->getKey(), $rows[0]['auditable_id']);

        $stored = json_encode(DB::table('audit_logs')->get());
        foreach ([$a->phone, $b->phone, $c->phone, $student->full_name] as $forbidden) {
            $this->assertStringNotContainsString($forbidden, $stored);
        }
    }

    public function test_equal_linked_at_times_are_broken_by_id(): void
    {
        $institute = $this->makeInstitute();
        $student = $this->makeStudent($institute);
        $staff = $this->staffOf($institute);
        $this->freezeSecond();
        [$a, $b, $c] = [$this->makeParent(), $this->makeParent(), $this->makeParent()];
        $this->linkParent($staff, $student, $a);
        $linkB = $this->linkParent($staff, $student, $b);
        $this->linkParent($staff, $student, $c);

        $this->asActor($staff, fn () => app(GuardianLinkService::class)->revoke($this->adminOf($institute), $student, $a));

        $this->assertSame($linkB->getKey(), $this->activePrimaryId($student));
    }

    public function test_revoking_a_non_primary_link_leaves_the_primary_and_records_no_change(): void
    {
        $institute = $this->makeInstitute();
        $student = $this->makeStudent($institute);
        $staff = $this->staffOf($institute);
        [$a, $b] = [$this->makeParent(), $this->makeParent()];
        $linkA = $this->linkParent($staff, $student, $a);
        $this->linkParent($staff, $student, $b, 'father');

        $this->loginAs($this->adminOf($institute));
        $this->spa('DELETE', $this->linkUrl($student, $b))->assertNoContent();

        $this->assertSame($linkA->getKey(), $this->activePrimaryId($student));
        $row = $this->auditRows(AuditAction::GUARDIAN_LINK_REVOKED)[0];
        $this->assertNull($row['old_values']);
        $this->assertEquals(['student_uuid' => $student->uuid, 'guardian_uuid' => $b->uuid, 'relationship' => 'father'], $row['new_values']);
    }

    public function test_the_guardian_loses_the_child_on_the_very_next_request_of_the_same_session(): void
    {
        [$student, $parent, $institute] = $this->linkedStudent();
        $this->loginAs($parent);
        $this->spa('GET', '/api/v1/parents/me/children')->assertOk()->assertJsonPath('data.0.student.uuid', $student->uuid);
        $this->spa('GET', '/api/v1/auth/session')->assertOk()->assertJsonPath('data.active_child_link_count', 1);

        // Revoked outside this session; the guardian's cookies are unchanged.
        $admin = $this->adminOf($institute);
        $this->asActor($admin, fn () => app(GuardianLinkService::class)->revoke($admin, $student, $parent));

        $this->spa('GET', '/api/v1/parents/me/children')->assertOk()->assertJsonPath('data', [])->assertJsonPath('meta.total', 0);
        $this->spa('GET', '/api/v1/auth/session')->assertOk()->assertJsonPath('data.active_child_link_count', 0);
    }

    public function test_a_body_or_query_field_is_rejected_with_422(): void
    {
        [$student, $parent, $institute] = $this->linkedStudent();
        $this->loginAs($this->adminOf($institute));

        $this->spa('DELETE', $this->linkUrl($student, $parent), ['revoked_at' => '2026-01-01T00:00:00Z'])
            ->assertUnprocessable()->assertJsonValidationErrors('revoked_at');
        $this->spa('DELETE', $this->linkUrl($student, $parent).'?is_primary=1')
            ->assertUnprocessable()->assertJsonValidationErrors('is_primary');

        $this->assertSame(1, StudentGuardian::query()->active()->count());
    }

    public function test_a_failed_audit_insert_rolls_the_revocation_and_promotion_back(): void
    {
        $institute = $this->makeInstitute();
        $student = $this->makeStudent($institute);
        $staff = $this->staffOf($institute);
        [$a, $b] = [$this->makeParent(), $this->makeParent()];
        $linkA = $this->linkParent($staff, $student, $a);
        $this->linkParent($staff, $student, $b, 'father');
        $this->loginAs($this->adminOf($institute));
        $this->mock(AuditLogger::class)->shouldReceive('record')->andThrow(new RuntimeException('Fictional audit outage.'));

        $this->spa('DELETE', $this->linkUrl($student, $a))->assertStatus(500);

        $this->assertSame(2, StudentGuardian::query()->active()->count());
        $this->assertSame($linkA->getKey(), $this->activePrimaryId($student));
    }

    /**
     * @return array{0: StudentProfile, 1: User, 2: Institute}
     */
    private function linkedStudent(): array
    {
        $institute = $this->makeInstitute();
        $student = $this->makeStudent($institute);
        $parent = $this->makeParent();
        $this->linkParent($this->staffOf($institute), $student, $parent);

        return [$student, $parent, $institute];
    }

    private function activePrimaryId(StudentProfile $student): ?int
    {
        $id = StudentGuardian::query()->active()->where('student_id', $student->getKey())->where('is_primary', true)->value('id');

        return $id === null ? null : (int) $id;
    }
}
