<?php

namespace Tests\Feature\Models;

use App\Models\GuardianInvitation;
use App\Models\InstituteStaff;
use App\Models\Role;
use App\Models\StudentGuardian;
use App\Models\UserRole;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Foundation\Testing\RefreshDatabase;
use LogicException;
use Tests\Feature\Concerns\AppFixtures;
use Tests\TestCase;

/**
 * VP-58: generated columns are never written through Eloquent; seeded roles are read-only.
 */
class GeneratedColumnGuardTest extends TestCase
{
    use AppFixtures;
    use RefreshDatabase;

    public function test_dirty_generated_columns_are_refused_before_any_query(): void
    {
        $institute = $this->insertInstitute();
        $student = $this->insertStudentProfile($institute);

        $cases = [
            [InstituteStaff::query()->findOrFail($this->insertStaff($institute, $this->insertUser())), 'active_flag'],
            [UserRole::query()->findOrFail($this->insertUserRole($this->insertUser(), 'institute_admin', $institute)), 'scope_key'],
            [GuardianInvitation::query()->findOrFail($this->insertInvitation($student, $institute)), 'pending_flag'],
            [StudentGuardian::query()->findOrFail($this->insertGuardianLink($student, $this->insertGuardianProfile())), 'active_flag'],
            [StudentGuardian::query()->findOrFail($this->insertGuardianLink($student, $this->insertGuardianProfile())), 'primary_flag'],
        ];

        foreach ($cases as [$model, $column]) {
            $this->assertGeneratedColumnRefused($model, $column);
        }
    }

    public function test_seeded_roles_are_read_only(): void
    {
        $role = Role::query()->where('name', Role::SUPER_ADMIN)->firstOrFail();

        $this->expectException(LogicException::class);

        $role->forceFill(['scope' => 'institute'])->save();
    }

    private function assertGeneratedColumnRefused(Model $model, string $column): void
    {
        $original = $model->getAttribute($column);
        $model->setAttribute($column, 9);

        try {
            $model->save();
            $this->fail("Expected [{$column}] on ".$model::class.' to be refused.');
        } catch (LogicException) {
            $this->assertEquals($original, $model->newQuery()->whereKey($model->getKey())->value($column));
        }
    }
}
