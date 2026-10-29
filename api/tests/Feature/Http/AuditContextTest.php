<?php

namespace Tests\Feature\Http;

use App\Audit\AuditAction;
use App\Audit\AuditContext;
use App\Models\Role;
use App\Services\RoleAssignmentService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Tests\Feature\Http\Concerns\ApiActors;
use Tests\TestCase;

/**
 * BindAuditContext (D-020): each request records its own actor, IP and user agent, and nothing
 * survives into a later request or a system write.
 */
class AuditContextTest extends TestCase
{
    use ApiActors;
    use RefreshDatabase;

    public function test_each_request_records_its_own_actor_and_the_binding_ends_with_the_request(): void
    {
        $first = $this->makeUser(['name' => null]);
        $second = $this->makeUser(['name' => null]);

        $this->loginAs($first);
        $this->spa('PATCH', '/api/v1/me', ['name' => 'Fictional First'], ['User-Agent' => 'FictionalBrowser/1.0'])->assertOk();
        $this->assertSystemContext();

        $this->loginAs($second);
        $this->spa('PATCH', '/api/v1/me', ['name' => 'Fictional Second'], ['User-Agent' => 'FictionalBrowser/2.0'])->assertOk();
        $this->assertSystemContext();

        $rows = $this->auditRows(AuditAction::USER_PROFILE_UPDATED);
        $this->assertEquals([$first->getKey(), $second->getKey()], array_column($rows, 'user_id'));
        $this->assertSame(['FictionalBrowser/1.0', 'FictionalBrowser/2.0'], array_column($rows, 'user_agent'));
        $this->assertSame(['127.0.0.1', '127.0.0.1'], array_column($rows, 'ip_address'));
    }

    public function test_a_system_write_after_a_request_has_no_actor_ip_or_user_agent(): void
    {
        $user = $this->makeUser(['name' => null]);
        $this->loginAs($user);
        $this->spa('PATCH', '/api/v1/me', ['name' => 'Fictional Person'])->assertOk();

        // As a seeder or console command would, after the request has finished.
        $institute = $this->makeInstitute();
        app(RoleAssignmentService::class)->grant($user, Role::INSTITUTE_ADMIN, $institute);

        $row = DB::table('audit_logs')->where('action', AuditAction::ROLE_GRANTED)->sole();
        $this->assertNull($row->user_id);
        $this->assertNull($row->ip_address);
        $this->assertNull($row->user_agent);
    }

    public function test_an_invalid_ip_is_dropped_and_a_long_user_agent_is_bounded(): void
    {
        $user = $this->makeUser(['name' => null]);
        $this->loginAs($user);

        $this->withServerVariables(['REMOTE_ADDR' => 'not-an-ip']);
        $this->spa('PATCH', '/api/v1/me', ['name' => 'Fictional Person'], ['User-Agent' => str_repeat('u', 2000)])->assertOk();

        $row = $this->auditRows(AuditAction::USER_PROFILE_UPDATED)[0];
        $this->assertEquals($user->getKey(), $row['user_id']);
        $this->assertNull($row['ip_address']);
        $this->assertSame(512, mb_strlen($row['user_agent']));
    }

    private function assertSystemContext(): void
    {
        $context = $this->app->make(AuditContext::class);

        $this->assertNull($context->actorId);
        $this->assertNull($context->ipAddress);
        $this->assertFalse($context->fromRequest);
    }
}
