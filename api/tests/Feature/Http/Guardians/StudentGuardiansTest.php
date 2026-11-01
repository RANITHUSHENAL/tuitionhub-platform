<?php

namespace Tests\Feature\Http\Guardians;

use App\Models\Institute;
use App\Models\StudentGuardian;
use App\Models\StudentProfile;
use App\Models\User;
use App\Services\GuardianLinkService;
use App\Support\PhoneMasker;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Tests\Feature\Http\Concerns\AssertsReadOnly;
use Tests\Feature\Http\Concerns\GuardianFixtures;
use Tests\TestCase;

/**
 * GET /api/v1/students/{uuid}/guardians (contract v0.10 §9.6, E2b; D-028): the student's
 * **active** guardian links only, primary first. `guardian.uuid` is the guardian's user UUID, the
 * one §9.4 revocation takes. Phones are masked; no internal IDs and no invitation data.
 */
class StudentGuardiansTest extends TestCase
{
    use AssertsReadOnly;
    use GuardianFixtures;
    use RefreshDatabase;

    public function test_unauthenticated_requests_get_401(): void
    {
        [$student] = $this->studentWithParents(1);

        $this->spa('GET', $this->guardiansUrl($student))->assertUnauthorized();
    }

    public function test_it_lists_active_links_with_the_exact_fields_and_masked_phones(): void
    {
        [$student, $institute, [$mother, $father]] = $this->studentWithParents(2, ['mother', 'father']);
        $this->loginAs($this->adminOf($institute));

        $response = $this->assertReadOnly(fn () => $this->spa('GET', $this->guardiansUrl($student)))
            ->assertOk()
            ->assertHeader('Cache-Control', 'no-store, private');

        $links = StudentGuardian::query()->orderBy('id')->get();
        $response->assertExactJson([
            'data' => [
                [
                    'guardian' => ['uuid' => $mother->uuid, 'name' => 'Fictional Parent 1'],
                    'relationship' => 'mother',
                    'is_primary' => true,
                    'linked_at' => $links[0]->linked_at->utc()->format('Y-m-d\TH:i:s\Z'),
                    'guardian_phone_masked' => PhoneMasker::mask($mother->phone),
                ],
                [
                    'guardian' => ['uuid' => $father->uuid, 'name' => 'Fictional Parent 2'],
                    'relationship' => 'father',
                    'is_primary' => false,
                    'linked_at' => $links[1]->linked_at->utc()->format('Y-m-d\TH:i:s\Z'),
                    'guardian_phone_masked' => PhoneMasker::mask($father->phone),
                ],
            ],
            'meta' => ['current_page' => 1, 'per_page' => 25, 'total' => 2, 'last_page' => 1],
        ]);

        $this->assertNoInternalIds($response);
        $body = $response->getContent();
        foreach ([$mother, $father] as $parent) {
            $this->assertStringNotContainsString($parent->phone, $body);
        }
        foreach (DB::table('guardian_invitations')->pluck('code_hash') as $hash) {
            $this->assertStringNotContainsString($hash, $body);
        }
        $this->assertStringNotContainsString('code', $body);
        $this->assertStringNotContainsString('invitation', $body);
    }

    public function test_revoked_links_are_excluded_and_the_listed_uuid_revokes_the_link(): void
    {
        [$student, $institute, [$mother, $father]] = $this->studentWithParents(2);
        $admin = $this->adminOf($institute);
        $this->asActor($admin, fn () => app(GuardianLinkService::class)->revoke($admin, $student, $mother));
        $this->loginAs($admin);

        $listed = $this->spa('GET', $this->guardiansUrl($student))->assertOk()
            ->assertJsonPath('meta.total', 1)
            ->assertJsonPath('data.0.guardian.uuid', $father->uuid)
            // The remaining link was promoted to primary (§9.4).
            ->assertJsonPath('data.0.is_primary', true)
            ->json('data.0.guardian.uuid');

        $this->spa('DELETE', $this->linkUrl($student, $listed))->assertNoContent();
        $this->spa('GET', $this->guardiansUrl($student))->assertOk()->assertExactJson([
            'data' => [],
            'meta' => ['current_page' => 1, 'per_page' => 25, 'total' => 0, 'last_page' => 1],
        ]);
    }

    public function test_primary_comes_first_then_oldest_link(): void
    {
        [$student, $institute, [$first, $second, $third]] = $this->studentWithParents(3);
        // Make the newest link the primary (fixture only), to show primary beats linked_at.
        DB::table('student_guardians')->update(['is_primary' => false]);
        DB::table('student_guardians')->where('guardian_id', $third->guardianProfile()->value('id'))->update(['is_primary' => true]);
        $this->loginAs($this->staffOf($institute));

        $uuids = array_column(array_column($this->spa('GET', $this->guardiansUrl($student))->assertOk()->json('data'), 'guardian'), 'uuid');

        $this->assertSame([$third->uuid, $first->uuid, $second->uuid], $uuids);
    }

    public function test_pagination_meta_and_bounds(): void
    {
        [$student, $institute, [, $second]] = $this->studentWithParents(3);
        $this->loginAs($this->adminOf($institute));

        $this->spa('GET', $this->guardiansUrl($student).'?per_page=1&page=2')->assertOk()
            ->assertJsonPath('data.0.guardian.uuid', $second->uuid)
            ->assertJsonPath('meta', ['current_page' => 2, 'per_page' => 1, 'total' => 3, 'last_page' => 3]);
        $this->spa('GET', $this->guardiansUrl($student).'?per_page=101')->assertUnprocessable()->assertJsonValidationErrors('per_page');
    }

    public function test_a_student_with_no_links_gets_an_empty_page(): void
    {
        $institute = $this->makeInstitute();
        $student = $this->makeStudent($institute);
        $this->loginAs($this->adminOf($institute));

        $this->spa('GET', $this->guardiansUrl($student))->assertOk()->assertExactJson([
            'data' => [],
            'meta' => ['current_page' => 1, 'per_page' => 25, 'total' => 0, 'last_page' => 1],
        ]);
    }

    public function test_authorization_matrix_and_suspended_reads(): void
    {
        [$student, $institute, [$parent]] = $this->studentWithParents(1);
        $admin = $this->adminOf($institute);
        $staff = $this->staffOf($institute);
        $this->suspend($institute);

        foreach ([$admin, $staff] as $user) {
            $this->loginAs($user);
            $this->spa('GET', $this->guardiansUrl($student))->assertOk()->assertJsonPath('meta.total', 1);
        }

        $this->loginAs($this->superAdmin());
        $this->spa('GET', $this->guardiansUrl($student))->assertForbidden()->assertJsonMissingPath('reason');

        foreach ([$this->teacherAt($institute), $this->adminOf($this->makeInstitute()), $parent] as $user) {
            $this->loginAs($user);
            $this->spa('GET', $this->guardiansUrl($student))->assertNotFound()->assertExactJson(['message' => 'Not found.']);
        }
    }

    public function test_another_institutes_links_never_appear(): void
    {
        [$student, $institute] = $this->studentWithParents(1);
        [$otherStudent] = $this->studentWithParents(2);
        $this->loginAs($this->adminOf($institute));

        $this->spa('GET', $this->guardiansUrl($student))->assertOk()->assertJsonPath('meta.total', 1);
        $this->spa('GET', $this->guardiansUrl($otherStudent))->assertNotFound();
    }

    public function test_query_fields_are_rejected_only_after_authorization(): void
    {
        [$student, $institute] = $this->studentWithParents(1);

        $this->loginAs($this->adminOf($this->makeInstitute()));
        $this->spa('GET', $this->guardiansUrl($student).'?status=revoked')->assertNotFound();

        $this->loginAs($this->adminOf($institute));
        $this->spa('GET', $this->guardiansUrl($student).'?status=revoked')->assertUnprocessable()->assertJsonValidationErrors('status');
    }

    public function test_listing_creates_no_roles_and_no_audit_rows(): void
    {
        [$student, $institute] = $this->studentWithParents(2);
        $this->loginAs($this->staffOf($institute));
        $roles = DB::table('user_roles')->count();
        $audits = DB::table('audit_logs')->count();

        $this->spa('GET', $this->guardiansUrl($student))->assertOk();

        $this->assertSame($roles, DB::table('user_roles')->count());
        $this->assertSame($audits, DB::table('audit_logs')->count());
    }

    /**
     * A student at a new institute with $count parents linked through the real invite/accept flow,
     * in order (the first link becomes primary).
     *
     * @param  list<string>  $relationships
     * @return array{0: StudentProfile, 1: Institute, 2: list<User>}
     */
    private function studentWithParents(int $count, array $relationships = []): array
    {
        $institute = $this->makeInstitute();
        $student = $this->makeStudent($institute);
        $inviter = $this->adminOf($institute);
        $parents = [];

        for ($i = 1; $i <= $count; $i++) {
            $parent = $this->makeParent(['name' => "Fictional Parent {$i}"]);
            $this->linkParent($inviter, $student, $parent, $relationships[$i - 1] ?? 'guardian');
            $parents[] = $parent;
            // Distinct linked_at values, so the order is not decided by the id tiebreaker alone.
            $this->travel(2)->seconds();
        }

        return [$student, $institute, $parents];
    }

    private function guardiansUrl(StudentProfile $student): string
    {
        return "/api/v1/students/{$student->uuid}/guardians";
    }
}
