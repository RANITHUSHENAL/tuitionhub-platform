<?php

namespace Tests\Feature\Http\Institutes;

use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Tests\Feature\Http\Concerns\ApiActors;
use Tests\TestCase;

/**
 * GET /api/v1/institutes/{uuid} (contract v0.5 §1.4, §5.4; FD-3, FD-4).
 */
class ShowInstituteTest extends TestCase
{
    use ApiActors;
    use RefreshDatabase;

    private const MISSING_UUID = '6b0e3f9a-1c2d-4e5f-8a9b-0c1d2e3f4a5b';

    public function test_unauthenticated_requests_get_401_before_any_lookup(): void
    {
        $institute = $this->makeInstitute();

        $this->spa('GET', "/api/v1/institutes/{$institute->uuid}")->assertUnauthorized();
        $this->spa('GET', '/api/v1/institutes/not-a-uuid')->assertUnauthorized();
    }

    public function test_admins_staff_and_super_admin_can_read_it(): void
    {
        $institute = $this->makeInstitute(null, ['name' => 'Fictional Visible Academy']);

        foreach ([$this->adminOf($institute), $this->staffOf($institute), $this->staffOf($institute, 'manager'), $this->superAdmin()] as $user) {
            $this->loginAs($user);

            $response = $this->spa('GET', "/api/v1/institutes/{$institute->uuid}")->assertOk()
                ->assertJsonPath('data.uuid', $institute->uuid)
                ->assertJsonPath('data.name', 'Fictional Visible Academy');
            $this->assertNoInternalIds($response);
        }
    }

    public function test_unrelated_callers_get_the_same_404_as_a_missing_institute(): void
    {
        $institute = $this->makeInstitute();
        $otherInstitute = $this->makeInstitute();
        $formerTeacher = $this->teacherAt($institute, ['status' => 'former', 'left_on' => '2026-10-10']);

        foreach ([$this->makeUser(), $this->adminOf($otherInstitute), $formerTeacher] as $user) {
            $this->loginAs($user);

            $this->spa('GET', "/api/v1/institutes/{$institute->uuid}")
                ->assertNotFound()
                ->assertExactJson(['message' => 'Not found.'])
                ->assertHeader('Cache-Control', 'no-store, private');
        }

        $this->spa('GET', '/api/v1/institutes/'.self::MISSING_UUID)->assertNotFound()->assertExactJson(['message' => 'Not found.']);
    }

    public function test_a_teacher_only_caller_gets_403(): void
    {
        $institute = $this->makeInstitute();
        $this->loginAs($this->teacherAt($institute));

        $this->spa('GET', "/api/v1/institutes/{$institute->uuid}")->assertForbidden();
    }

    public function test_a_malformed_uuid_is_a_404_without_querying_institutes(): void
    {
        $this->loginAs($this->superAdmin());
        DB::flushQueryLog();
        DB::enableQueryLog();

        $this->spa('GET', '/api/v1/institutes/not-a-uuid')->assertNotFound()->assertExactJson(['message' => 'Not found.']);
        $this->spa('GET', '/api/v1/institutes/1')->assertNotFound()->assertExactJson(['message' => 'Not found.']);

        foreach (DB::getQueryLog() as $query) {
            $this->assertStringNotContainsString('`institutes`', $query['query']);
        }
    }

    public function test_a_suspended_institute_stays_readable_by_admins_and_staff_only(): void
    {
        $institute = $this->makeInstitute();
        $admin = $this->adminOf($institute);
        $staff = $this->staffOf($institute);
        $teacher = $this->teacherAt($institute);
        $stranger = $this->makeUser();
        $this->suspend($institute);

        foreach ([$admin, $staff] as $user) {
            $this->loginAs($user);
            $this->spa('GET', "/api/v1/institutes/{$institute->uuid}")->assertOk()->assertJsonPath('data.status', 'suspended');
        }

        $this->loginAs($teacher);
        $this->spa('GET', "/api/v1/institutes/{$institute->uuid}")->assertForbidden();

        $this->loginAs($stranger);
        $this->spa('GET', "/api/v1/institutes/{$institute->uuid}")->assertNotFound();
    }

    public function test_a_suspended_user_gets_403(): void
    {
        $institute = $this->makeInstitute();
        $admin = $this->adminOf($institute);
        $this->loginAs($admin);
        DB::table('users')->where('id', $admin->getKey())->update(['status' => 'suspended']);

        $this->spa('GET', "/api/v1/institutes/{$institute->uuid}")->assertForbidden()
            ->assertJsonPath('message', 'This account is suspended.');
    }
}
