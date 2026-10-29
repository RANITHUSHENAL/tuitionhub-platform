<?php

namespace Tests\Feature\Http;

use App\Audit\AuditAction;
use App\Audit\AuditLogger;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use RuntimeException;
use Tests\Feature\Http\Concerns\ApiActors;
use Tests\TestCase;

/**
 * PATCH /api/v1/me (contract v0.5 §4.5; FD-1, FD-2). Fictional names only.
 */
class MeTest extends TestCase
{
    use ApiActors;
    use RefreshDatabase;

    private const PHONE = '+94700000701';

    private const SINHALA_NAME = "\u{0DC1}\u{0DCA}\u{200D}\u{0DBB}\u{0DD3}\u{0DB8}\u{0DCF}\u{0DBD}\u{0DD2}";

    public function test_unauthenticated_and_bearer_only_requests_get_401(): void
    {
        $this->spa('PATCH', '/api/v1/me', ['name' => 'Kasun Silva'])->assertUnauthorized();
        $this->nonStateful('PATCH', '/api/v1/me', ['name' => 'Kasun Silva'], ['Authorization' => 'Bearer 1|token'])
            ->assertUnauthorized();
    }

    public function test_a_suspended_user_gets_403_and_nothing_changes(): void
    {
        $user = $this->makeUser(['name' => 'Fictional Original']);
        $this->loginAs($user);
        DB::table('users')->where('id', $user->getKey())->update(['status' => 'suspended']);

        $this->spa('PATCH', '/api/v1/me', ['name' => 'Kasun Silva'])
            ->assertForbidden()->assertJsonPath('message', 'This account is suspended.');

        $this->assertSame('Fictional Original', $user->fresh()->name);
        $this->assertSame([], $this->auditRows(AuditAction::USER_PROFILE_UPDATED));
    }

    public function test_setting_the_first_name_completes_onboarding_and_returns_the_session_shape(): void
    {
        $this->loginWithOtp(self::PHONE)->assertOk()->assertJsonPath('data.onboarding_required', true);
        $user = User::query()->where('phone', self::PHONE)->sole();

        $response = $this->spa('PATCH', '/api/v1/me', ['name' => 'Kasun Silva'])->assertOk()
            ->assertHeader('Cache-Control', 'no-store, private')
            ->assertJsonPath('data.user.uuid', $user->uuid)
            ->assertJsonPath('data.user.name', 'Kasun Silva')
            ->assertJsonPath('data.onboarding_required', false);

        $this->assertSame(
            ['user', 'roles', 'teaching_at', 'active_child_link_count', 'has_teacher_profile', 'has_guardian_profile', 'onboarding_required'],
            array_keys($response->json('data')),
        );
        $this->assertNoInternalIds($response);
        $this->assertSame('Kasun Silva', $user->fresh()->name);

        $this->spa('GET', '/api/v1/auth/session')->assertOk()->assertJsonPath('data.onboarding_required', false);

        $rows = $this->auditRows(AuditAction::USER_PROFILE_UPDATED);
        $this->assertCount(1, $rows);
        $this->assertEquals($user->getKey(), $rows[0]['user_id']);
        $this->assertNull($rows[0]['institute_id']);
        $this->assertSame('user', $rows[0]['auditable_type']);
        $this->assertSame(['name_was_set' => false], $rows[0]['old_values']);
        $this->assertEquals(['user_uuid' => $user->uuid, 'field' => 'name', 'onboarding_completed' => true], $rows[0]['new_values']);
        $this->assertSame('127.0.0.1', $rows[0]['ip_address']);
        // The name itself is never written to the audit log.
        $this->assertStringNotContainsString('Kasun', (string) DB::table('audit_logs')->value('new_values'));
    }

    public function test_the_name_is_normalised_before_it_is_stored(): void
    {
        $user = $this->makeUser(['name' => null]);
        $this->loginAs($user);

        $this->spa('PATCH', '/api/v1/me', ['name' => "  Kasun \u{00A0}\u{3000} Silva  "])->assertOk()
            ->assertJsonPath('data.user.name', 'Kasun Silva');

        $this->assertSame('Kasun Silva', $user->fresh()->name);
    }

    public function test_a_sinhala_name_with_a_zwj_conjunct_is_stored_exactly(): void
    {
        $user = $this->makeUser(['name' => null]);
        $this->loginAs($user);

        $this->spa('PATCH', '/api/v1/me', ['name' => self::SINHALA_NAME])->assertOk()
            ->assertJsonPath('data.user.name', self::SINHALA_NAME);

        $this->assertSame(self::SINHALA_NAME, $user->fresh()->name);
    }

    public function test_invalid_names_are_rejected_with_422_and_nothing_changes(): void
    {
        $user = $this->makeUser(['name' => 'Fictional Original']);
        $this->loginAs($user);

        foreach (["Kasun\nSilva", "\u{202E}Kasun", 'Kasun 2', 'K', str_repeat('a', 101), '', null, 42, ['Kasun']] as $name) {
            $this->spa('PATCH', '/api/v1/me', ['name' => $name])->assertUnprocessable()->assertJsonValidationErrors('name');
        }

        $this->spa('PATCH', '/api/v1/me', [])->assertUnprocessable()->assertJsonValidationErrors('name');

        $this->assertSame('Fictional Original', $user->fresh()->name);
        $this->assertSame([], $this->auditRows(AuditAction::USER_PROFILE_UPDATED));
    }

    public function test_fields_other_than_name_are_rejected_with_422(): void
    {
        $user = $this->makeUser(['name' => 'Fictional Original']);
        $this->loginAs($user);

        $this->spa('PATCH', '/api/v1/me', ['name' => 'Kasun Silva', 'phone' => '+94700000999', 'status' => 'active', 'uuid' => 'x'])
            ->assertUnprocessable()
            ->assertJsonValidationErrors(['phone', 'status', 'uuid'])
            ->assertJsonMissingValidationErrors('name');

        $fresh = $user->fresh();
        $this->assertSame('Fictional Original', $fresh->name);
        $this->assertSame($user->phone, $fresh->phone);
    }

    public function test_the_name_can_be_changed_again_and_each_change_is_audited(): void
    {
        $user = $this->makeUser(['name' => null]);
        $this->loginAs($user);

        $this->spa('PATCH', '/api/v1/me', ['name' => 'Kasun Silva'])->assertOk();
        $this->spa('PATCH', '/api/v1/me', ['name' => 'Kasun Fernando'])->assertOk()->assertJsonPath('data.user.name', 'Kasun Fernando');

        $rows = $this->auditRows(AuditAction::USER_PROFILE_UPDATED);
        $this->assertCount(2, $rows);
        $this->assertSame(['name_was_set' => true], $rows[1]['old_values']);
        $this->assertEquals(['user_uuid' => $user->uuid, 'field' => 'name', 'onboarding_completed' => false], $rows[1]['new_values']);
    }

    public function test_an_unchanged_name_writes_nothing_and_records_no_audit(): void
    {
        $user = $this->makeUser(['name' => 'Kasun Silva']);
        $updatedAt = DB::table('users')->where('id', $user->getKey())->value('updated_at');
        $this->loginAs($user);

        $this->spa('PATCH', '/api/v1/me', ['name' => ' Kasun  Silva '])->assertOk()->assertJsonPath('data.user.name', 'Kasun Silva');

        $this->assertSame([], $this->auditRows(AuditAction::USER_PROFILE_UPDATED));
        $this->assertSame($updatedAt, DB::table('users')->where('id', $user->getKey())->value('updated_at'));
    }

    public function test_a_failed_audit_insert_rolls_the_name_change_back(): void
    {
        $user = $this->makeUser(['name' => 'Fictional Original']);
        $this->loginAs($user);
        $this->mock(AuditLogger::class)->shouldReceive('record')->andThrow(new RuntimeException('Fictional audit outage.'));

        $this->spa('PATCH', '/api/v1/me', ['name' => 'Kasun Silva'])->assertStatus(500);

        $this->assertSame('Fictional Original', $user->fresh()->name);
    }
}
