<?php

namespace Tests\Feature\Http\Institutes;

use App\Audit\AuditAction;
use App\Audit\AuditLogger;
use App\Models\Institute;
use App\Models\Role;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use RuntimeException;
use Tests\Feature\Http\Concerns\ApiActors;
use Tests\TestCase;

/**
 * POST /api/v1/institutes (contract v0.5 §5.1). Fictional data only.
 */
class CreateInstituteTest extends TestCase
{
    use ApiActors;
    use RefreshDatabase;

    private const FIELDS = ['uuid', 'name', 'description', 'status', 'district', 'city', 'contact_phone', 'contact_email', 'business_reg_no', 'created_at', 'updated_at'];

    public function test_unauthenticated_requests_get_401(): void
    {
        $this->spa('POST', '/api/v1/institutes', $this->payload())->assertUnauthorized();
        $this->assertDatabaseCount('institutes', 0);
    }

    public function test_a_suspended_user_gets_403(): void
    {
        $user = $this->makeUser();
        $this->loginAs($user);
        DB::table('users')->where('id', $user->getKey())->update(['status' => 'suspended']);

        $this->spa('POST', '/api/v1/institutes', $this->payload())
            ->assertForbidden()->assertJsonPath('message', 'This account is suspended.');
        $this->assertDatabaseCount('institutes', 0);
    }

    public function test_a_user_without_a_name_is_not_eligible(): void
    {
        $this->loginAs($this->makeUser(['name' => null]));

        $this->spa('POST', '/api/v1/institutes', $this->payload())
            ->assertForbidden()
            ->assertExactJson(['message' => 'You are not eligible to perform this action.', 'reason' => 'not_eligible']);
        $this->assertDatabaseCount('institutes', 0);
    }

    public function test_it_creates_the_institute_grants_the_owner_admin_and_audits_both(): void
    {
        $owner = $this->makeUser(['name' => 'Fictional Owner']);
        $this->loginAs($owner);

        $response = $this->spa('POST', '/api/v1/institutes', $this->payload(['district' => '  colombo ']))
            ->assertCreated()
            ->assertHeader('Cache-Control', 'no-store, private')
            ->assertJsonPath('data.name', 'Fictional Lakmina Academy')
            ->assertJsonPath('data.status', 'active')
            ->assertJsonPath('data.district', 'Colombo')
            ->assertJsonPath('data.contact_phone', '+94700000555');

        $this->assertSame(self::FIELDS, array_keys($response->json('data')));
        $this->assertMatchesRegularExpression('/^\d{4}-\d{2}-\d{2}T\d{2}:\d{2}:\d{2}Z$/', $response->json('data.created_at'));
        $this->assertNoInternalIds($response);

        $institute = Institute::query()->where('uuid', $response->json('data.uuid'))->sole();
        $this->assertEquals($owner->getKey(), $institute->owner_user_id);
        $this->assertDatabaseHas('user_roles', [
            'user_id' => $owner->getKey(), 'role_id' => $this->roleId(Role::INSTITUTE_ADMIN), 'institute_id' => $institute->getKey(),
        ]);

        foreach ([AuditAction::INSTITUTE_CREATED, AuditAction::ROLE_GRANTED] as $action) {
            $rows = $this->auditRows($action);
            $this->assertCount(1, $rows, $action);
            $this->assertEquals($owner->getKey(), $rows[0]['user_id'], $action);
            $this->assertEquals($institute->getKey(), $rows[0]['institute_id'], $action);
            $this->assertSame('127.0.0.1', $rows[0]['ip_address'], $action);
        }
    }

    public function test_an_unknown_district_is_rejected_with_422(): void
    {
        $this->loginAs($this->makeUser());

        $this->spa('POST', '/api/v1/institutes', $this->payload(['district' => 'Atlantis']))
            ->assertUnprocessable()->assertJsonValidationErrors('district');
        $this->assertDatabaseCount('institutes', 0);
    }

    public function test_owner_status_and_id_fields_are_rejected_as_unknown_fields(): void
    {
        $user = $this->makeUser();
        $this->loginAs($user);

        foreach (['owner_user_id' => $user->getKey(), 'owner_uuid' => $user->uuid, 'status' => 'active', 'uuid' => '6b0e3f9a-1c2d-4e5f-8a9b-0c1d2e3f4a5b', 'id' => 1] as $field => $value) {
            $this->spa('POST', '/api/v1/institutes', $this->payload([$field => $value]))
                ->assertUnprocessable()->assertJsonValidationErrors($field);
        }

        $this->assertDatabaseCount('institutes', 0);
        $this->assertDatabaseCount('user_roles', 0);
    }

    public function test_invalid_fields_are_rejected_with_422(): void
    {
        $this->loginAs($this->makeUser());

        $cases = [
            ['name', ''],
            ['name', "Fictional\u{202E} Academy"],
            ['name', "Fictional\nAcademy"],
            ['contact_phone', '0771234567'],
            ['contact_email', 'not-an-email'],
            // Mid-string: TrimStrings strips leading and trailing NUL bytes before validation.
            ['description', "Line\0one"],
            ['city', "Nuge\u{0007}goda"],
        ];

        foreach ($cases as [$field, $value]) {
            $this->spa('POST', '/api/v1/institutes', $this->payload([$field => $value]))
                ->assertUnprocessable()->assertJsonValidationErrors($field);
        }

        $this->spa('POST', '/api/v1/institutes', $this->payload(['description' => "Line one\nLine two"]))->assertCreated();
    }

    public function test_a_failed_audit_insert_rolls_back_the_institute_and_the_role(): void
    {
        $this->loginAs($this->makeUser());
        $this->mock(AuditLogger::class)->shouldReceive('record')->andThrow(new RuntimeException('Fictional audit outage.'));

        $this->spa('POST', '/api/v1/institutes', $this->payload())->assertStatus(500);

        $this->assertDatabaseCount('institutes', 0);
        $this->assertDatabaseCount('user_roles', 0);
        $this->assertDatabaseCount('audit_logs', 0);
    }

    /**
     * @param  array<string, mixed>  $overrides
     * @return array<string, mixed>
     */
    private function payload(array $overrides = []): array
    {
        return array_merge([
            'name' => 'Fictional Lakmina Academy',
            'description' => 'Fictional A/L science classes.',
            'district' => 'Colombo',
            'city' => 'Nugegoda',
            'contact_phone' => '+94700000555',
            'contact_email' => 'office@example.test',
            'business_reg_no' => null,
        ], $overrides);
    }
}
