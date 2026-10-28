<?php

namespace Tests\Feature\Services;

use App\Audit\AuditAction;
use App\Audit\AuditContext;
use App\Audit\AuditLogger;
use App\Authorization\TenantAccess;
use App\Exceptions\DomainRuleException;
use App\Models\AuditLog;
use App\Models\Institute;
use App\Models\UserRole;
use App\Services\InstituteService;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Foundation\Testing\RefreshDatabase;
use RuntimeException;
use Tests\Feature\Concerns\AppFixtures;
use Tests\TestCase;

/**
 * VP-17 and VP-59: institute creation, owner admin grant and audit are atomic; districts are canonical.
 */
class InstituteServiceTest extends TestCase
{
    use AppFixtures;
    use RefreshDatabase;

    public function test_an_institute_is_created_with_its_owner_as_admin_and_audited(): void
    {
        $owner = $this->makeUser();
        $this->useAuditContext($owner->getKey());

        $institute = app(InstituteService::class)->create($owner, [
            'name' => 'Fictional Academy',
            'description' => 'A fictional demo institute.',
            'district' => 'colombo',
            'city' => 'Nugegoda',
            'contact_phone' => '+94770000001',
        ]);

        $institute->refresh();
        $this->assertSame('Colombo', $institute->district);
        $this->assertSame('active', $institute->status);
        $this->assertEquals($owner->getKey(), $institute->owner_user_id);
        $this->assertMatchesRegularExpression(self::V4_UUID, $institute->uuid);
        $this->assertTrue(app(TenantAccess::class)->isInstituteAdmin($owner, $institute));

        $created = AuditLog::query()->where('action', AuditAction::INSTITUTE_CREATED)->sole();
        $this->assertEquals(['name' => 'Fictional Academy', 'district' => 'Colombo', 'city' => 'Nugegoda', 'status' => 'active'], $created->new_values);
        $this->assertSame(1, AuditLog::query()->where('action', AuditAction::ROLE_GRANTED)->count());
    }

    public function test_status_and_owner_are_never_taken_from_input(): void
    {
        $owner = $this->makeUser();
        $other = $this->makeUser();

        $institute = app(InstituteService::class)->create($owner, [
            'name' => 'Fictional Academy',
            'district' => 'Galle',
            'status' => 'suspended',
            'owner_user_id' => $other->getKey(),
        ])->refresh();

        $this->assertSame('active', $institute->status);
        $this->assertEquals($owner->getKey(), $institute->owner_user_id);
    }

    public function test_vp59_the_moneragala_alias_is_stored_as_monaragala(): void
    {
        $institute = app(InstituteService::class)->create($this->makeUser(), [
            'name' => 'Fictional Academy',
            'district' => 'Moneragala',
        ])->refresh();

        $this->assertSame('Monaragala', $institute->district);
    }

    public function test_ineligible_owners_and_unknown_districts_write_nothing(): void
    {
        $service = app(InstituteService::class);
        $attributes = ['name' => 'Fictional Academy', 'district' => 'Colombo'];

        $this->assertDomainRule(DomainRuleException::NOT_ELIGIBLE, fn () => $service->create($this->makeUser(['name' => null]), $attributes));
        $this->assertDomainRule(DomainRuleException::NOT_ELIGIBLE, fn () => $service->create($this->makeUser(['phone_verified_at' => null]), $attributes));
        $this->assertDomainRule(DomainRuleException::NOT_ELIGIBLE, fn () => $service->create($this->makeUser(['status' => 'suspended']), $attributes));
        $this->assertDomainRule(DomainRuleException::UNKNOWN_DISTRICT, fn () => $service->create($this->makeUser(), ['name' => 'X', 'district' => 'Atlantis']));

        $this->assertSame(0, Institute::query()->count());
        $this->assertSame(0, UserRole::query()->count());
        $this->assertSame(0, AuditLog::query()->count());
    }

    public function test_vp17_a_failure_after_the_role_grant_rolls_everything_back(): void
    {
        $this->app->bind(AuditLogger::class, fn ($app) => new class($app->make(AuditContext::class)) extends AuditLogger
        {
            public function record(string $action, Model $target, ?Institute $institute = null, array $oldValues = [], array $newValues = []): AuditLog
            {
                if ($action === AuditAction::INSTITUTE_CREATED) {
                    throw new RuntimeException('forced failure after the role grant');
                }

                return parent::record($action, $target, $institute, $oldValues, $newValues);
            }
        });

        try {
            app(InstituteService::class)->create($this->makeUser(), ['name' => 'Fictional Academy', 'district' => 'Colombo']);
            $this->fail('Expected the forced failure.');
        } catch (RuntimeException $exception) {
            $this->assertSame('forced failure after the role grant', $exception->getMessage());
        }

        $this->assertSame(0, Institute::query()->count());
        $this->assertSame(0, UserRole::query()->count());
        $this->assertSame(0, AuditLog::query()->count());
    }
}
