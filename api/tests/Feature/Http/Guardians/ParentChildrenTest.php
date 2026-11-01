<?php

namespace Tests\Feature\Http\Guardians;

use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Tests\Feature\Http\Concerns\GuardianFixtures;
use Tests\TestCase;

/**
 * GET /api/v1/parents/me/children (contract v0.7 §9.5; E2-1, E2-3; VP-34). Fictional data only.
 */
class ParentChildrenTest extends TestCase
{
    use GuardianFixtures;
    use RefreshDatabase;

    public function test_unauthenticated_requests_get_401(): void
    {
        $this->spa('GET', '/api/v1/parents/me/children')->assertUnauthorized();
    }

    public function test_a_user_without_any_link_gets_an_empty_page(): void
    {
        $this->loginAs($this->makeUser());

        $this->spa('GET', '/api/v1/parents/me/children')
            ->assertOk()
            ->assertHeader('Cache-Control', 'no-store, private')
            ->assertExactJson(['data' => [], 'meta' => ['current_page' => 1, 'per_page' => 25, 'total' => 0, 'last_page' => 1]]);
    }

    public function test_only_the_callers_active_links_are_listed_in_linked_order_with_the_contract_shape(): void
    {
        $institute = $this->makeInstitute();
        $staff = $this->staffOf($institute);
        $parent = $this->makeParent();
        $older = $this->makeStudent($institute);
        $newer = $this->makeStudent($institute);
        $revoked = $this->makeStudent($institute);
        $notMine = $this->makeStudent($institute);

        $this->freezeSecond();
        $this->linkParent($staff, $older, $parent, 'father');
        $olderLinkedAt = now('UTC')->format('Y-m-d\TH:i:s\Z');
        $this->travel(1)->hours();
        $this->linkParent($staff, $newer, $parent);
        $revokedLink = $this->linkParent($staff, $revoked, $parent);
        DB::table('student_guardians')->where('id', $revokedLink->getKey())->update(['revoked_at' => now('UTC'), 'revoked_by_user_id' => $staff->getKey()]);
        $this->linkParent($staff, $notMine, $this->makeParent());

        $this->loginAs($parent);
        $response = $this->spa('GET', '/api/v1/parents/me/children')->assertOk();

        $this->assertSame([
            'student' => ['uuid' => $older->uuid, 'full_name' => $older->full_name, 'grade' => ['code' => 'G11', 'name' => 'Grade 11']],
            'relationship' => 'father',
            'linked_at' => $olderLinkedAt,
            'active_enrollments' => [],
        ], $response->json('data.0'));
        $this->assertSame([$older->uuid, $newer->uuid], array_column(array_column($response->json('data'), 'student'), 'uuid'));
        $this->assertSame(2, $response->json('meta.total'));
        $this->assertNoInternalIds($response);
    }

    public function test_another_guardians_children_never_appear(): void
    {
        $institute = $this->makeInstitute();
        $staff = $this->staffOf($institute);
        $student = $this->makeStudent($institute);
        $this->linkParent($staff, $student, $this->makeParent());

        // Staff and admins of the institute are not guardians: nothing is listed for them either.
        foreach ([$this->makeParent(), $staff, $this->adminOf($institute)] as $user) {
            $this->loginAs($user);
            $this->spa('GET', '/api/v1/parents/me/children')->assertOk()->assertJsonPath('data', [])->assertJsonPath('meta.total', 0);
        }
    }

    public function test_children_at_several_institutes_including_a_suspended_one_are_listed(): void
    {
        $parent = $this->makeParent();
        $active = $this->makeInstitute();
        $suspended = $this->makeInstitute();
        $first = $this->makeStudent($active);
        $second = $this->makeStudent($suspended);
        $this->linkParent($this->staffOf($active), $first, $parent);
        $this->travel(1)->minutes();
        $this->linkParent($this->staffOf($suspended), $second, $parent);
        $this->suspend($suspended);

        $this->loginAs($parent);

        // E2-3: read-only, and it is the guardian's own child.
        $this->assertSame(
            [$first->uuid, $second->uuid],
            array_column(array_column($this->spa('GET', '/api/v1/parents/me/children')->assertOk()->json('data'), 'student'), 'uuid'),
        );
    }

    public function test_pagination_follows_the_contract(): void
    {
        $institute = $this->makeInstitute();
        $staff = $this->staffOf($institute);
        $parent = $this->makeParent();
        for ($i = 0; $i < 3; $i++) {
            $this->linkParent($staff, $this->makeStudent($institute), $parent);
            $this->travel(1)->minutes();
        }
        $this->loginAs($parent);

        $this->spa('GET', '/api/v1/parents/me/children?per_page=2&page=2')
            ->assertOk()
            ->assertJsonCount(1, 'data')
            ->assertJsonPath('meta', ['current_page' => 2, 'per_page' => 2, 'total' => 3, 'last_page' => 2]);

        foreach (['per_page=0', 'per_page=101', 'page=0', 'per_page=abc'] as $query) {
            $this->spa('GET', "/api/v1/parents/me/children?{$query}")->assertUnprocessable();
        }

        $this->spa('GET', '/api/v1/parents/me/children?page=9')->assertOk()->assertJsonPath('data', [])->assertJsonPath('meta.total', 3);
    }
}
