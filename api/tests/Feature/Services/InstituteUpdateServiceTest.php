<?php

namespace Tests\Feature\Services;

use App\Exceptions\DomainRuleException;
use App\Models\Role;
use App\Services\InstituteService;
use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Tests\Feature\Concerns\AppFixtures;
use Tests\TestCase;

/**
 * InstituteService::update re-evaluates authorization against the locked row (D-020), so a
 * revocation or suspension committed after the request's first check is still respected.
 */
class InstituteUpdateServiceTest extends TestCase
{
    use AppFixtures;
    use RefreshDatabase;

    public function test_a_revoked_admin_is_refused_inside_the_transaction(): void
    {
        $institute = $this->makeInstitute(null, ['name' => 'Fictional Original']);
        $admin = $this->makeUser();
        $roleId = $this->insertUserRole($admin->getKey(), Role::INSTITUTE_ADMIN, $institute->getKey());
        $this->useAuditContext($admin->getKey());

        // The request was authorized, then the role was removed before the service ran.
        DB::table('user_roles')->where('id', $roleId)->delete();

        try {
            app(InstituteService::class)->update($admin, $institute, ['name' => 'Fictional Renamed']);
            $this->fail('Expected the revoked admin to be refused.');
        } catch (AuthorizationException $exception) {
            $this->assertSame(404, $exception->status());
        }

        $this->assertSame('Fictional Original', $institute->fresh()->name);
        $this->assertDatabaseCount('audit_logs', 0);
    }

    public function test_a_suspension_committed_after_the_first_check_is_respected(): void
    {
        $institute = $this->makeInstitute(null, ['name' => 'Fictional Original']);
        $admin = $this->makeUser();
        $this->insertUserRole($admin->getKey(), Role::INSTITUTE_ADMIN, $institute->getKey());
        $this->useAuditContext($admin->getKey());

        DB::table('institutes')->where('id', $institute->getKey())->update(['status' => 'suspended']);

        $this->assertDomainRule(DomainRuleException::INSTITUTE_SUSPENDED, fn () => app(InstituteService::class)
            ->update($admin, $institute, ['name' => 'Fictional Renamed']));

        $this->assertSame('Fictional Original', $institute->fresh()->name);
    }

    public function test_only_super_admin_may_change_status_even_when_called_directly(): void
    {
        $institute = $this->makeInstitute();
        $admin = $this->makeUser();
        $this->insertUserRole($admin->getKey(), Role::INSTITUTE_ADMIN, $institute->getKey());
        $this->useAuditContext($admin->getKey());

        try {
            app(InstituteService::class)->update($admin, $institute, ['status' => 'suspended']);
            $this->fail('Expected an institute admin to be refused a status change.');
        } catch (AuthorizationException $exception) {
            // A plain deny (rendered as 403), not a hidden-resource 404: the admin is related.
            $this->assertNull($exception->status());
        }

        $this->assertSame('active', $institute->fresh()->status);
    }
}
