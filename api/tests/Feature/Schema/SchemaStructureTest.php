<?php

namespace Tests\Feature\Schema;

use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Tests\TestCase;

/**
 * VP-1: the G1–G3 migrations build the schema in docs/SPRINT_SCHEMA.md.
 */
class SchemaStructureTest extends TestCase
{
    use RefreshDatabase;

    private const G1_G3_TABLES = [
        'subjects', 'grades', 'streams', 'exam_targets', 'mediums', 'syllabi', 'roles',
        'users', 'user_roles', 'otp_challenges', 'teacher_profiles', 'guardian_profiles',
        'institutes', 'institute_staff', 'teacher_institute',
    ];

    public function test_it_runs_on_mysql_8_4(): void
    {
        $this->assertStringStartsWith('8.4.', DB::scalar('SELECT VERSION()'));
    }

    public function test_all_g1_to_g3_tables_exist_and_password_reset_tokens_does_not(): void
    {
        $tables = $this->column('SELECT TABLE_NAME AS name FROM information_schema.TABLES WHERE TABLE_SCHEMA = DATABASE()');

        foreach ([...self::G1_G3_TABLES, 'sessions'] as $table) {
            $this->assertContains($table, $tables);
        }

        $this->assertNotContains('password_reset_tokens', $tables);
    }

    public function test_instants_are_datetime_columns(): void
    {
        foreach (self::G1_G3_TABLES as $table) {
            foreach (['created_at', 'updated_at'] as $column) {
                $this->assertSame('datetime', $this->dataType($table, $column), "{$table}.{$column}");
            }
        }

        $this->assertSame('datetime', $this->dataType('users', 'phone_verified_at'));
        $this->assertSame('datetime', $this->dataType('otp_challenges', 'expires_at'));
        $this->assertSame('date', $this->dataType('institute_staff', 'joined_on'));
        $this->assertSame('date', $this->dataType('teacher_institute', 'left_on'));
    }

    public function test_generated_columns_are_stored(): void
    {
        $generated = [
            'user_roles' => ['scope_key', ['institute_id']],
            'institute_staff' => ['active_flag', ['status', 'left_on']],
            'teacher_institute' => ['active_flag', ['status', 'left_on']],
        ];

        foreach ($generated as $table => [$column, $baseColumns]) {
            $row = DB::selectOne(
                'SELECT EXTRA AS extra, GENERATION_EXPRESSION AS expression, IS_NULLABLE AS nullable
                   FROM information_schema.COLUMNS
                  WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = ? AND COLUMN_NAME = ?',
                [$table, $column],
            );

            $this->assertNotNull($row, "{$table}.{$column} missing");
            $this->assertSame('STORED GENERATED', $row->extra, "{$table}.{$column}");

            foreach ($baseColumns as $baseColumn) {
                $this->assertStringContainsString("`{$baseColumn}`", $row->expression, "{$table}.{$column}");
            }
        }

        $this->assertSame('NO', DB::scalar(
            "SELECT IS_NULLABLE FROM information_schema.COLUMNS
              WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'user_roles' AND COLUMN_NAME = 'scope_key'"
        ));
    }

    public function test_every_planned_check_constraint_exists(): void
    {
        $expected = [
            'subjects_category_chk', 'grades_level_chk', 'exam_targets_exam_chk',
            'roles_scope_chk', 'roles_name_scope_chk',
            'users_status_chk', 'users_phone_format_chk',
            'otp_challenges_purpose_chk', 'otp_challenges_attempts_chk',
            'teacher_profiles_visibility_chk',
            'institutes_status_chk', 'institutes_district_chk',
            'user_roles_scope_chk',
            'institute_staff_status_chk', 'institute_staff_position_chk',
            'institute_staff_status_left_chk', 'institute_staff_dates_chk',
            'teacher_institute_status_chk', 'teacher_institute_status_left_chk', 'teacher_institute_dates_chk',
        ];

        $actual = $this->column(
            "SELECT CONSTRAINT_NAME AS name FROM information_schema.TABLE_CONSTRAINTS
              WHERE TABLE_SCHEMA = DATABASE() AND CONSTRAINT_TYPE = 'CHECK'"
        );

        $this->assertEqualsCanonicalizing($expected, $actual);
    }

    public function test_foreign_keys_restrict_deletes_and_take_no_update_action(): void
    {
        $expected = [
            'teacher_profiles_user_fk' => 'users',
            'guardian_profiles_user_fk' => 'users',
            'institutes_owner_fk' => 'users',
            'user_roles_user_fk' => 'users',
            'user_roles_institute_fk' => 'institutes',
            'user_roles_role_scope_fk' => 'roles',
            'institute_staff_institute_fk' => 'institutes',
            'institute_staff_user_fk' => 'users',
            'teacher_institute_teacher_fk' => 'teacher_profiles',
            'teacher_institute_institute_fk' => 'institutes',
        ];

        $rows = DB::select(
            'SELECT CONSTRAINT_NAME AS name, REFERENCED_TABLE_NAME AS parent, DELETE_RULE AS on_delete, UPDATE_RULE AS on_update
               FROM information_schema.REFERENTIAL_CONSTRAINTS
              WHERE CONSTRAINT_SCHEMA = DATABASE()'
        );

        $this->assertEqualsCanonicalizing(array_keys($expected), array_column($rows, 'name'));

        foreach ($rows as $row) {
            $this->assertSame($expected[$row->name], $row->parent, $row->name);
            $this->assertSame('RESTRICT', $row->on_delete, $row->name);
            $this->assertSame('NO ACTION', $row->on_update, $row->name);
        }
    }

    public function test_role_scope_columns_have_identical_type_charset_and_collation(): void
    {
        $definition = fn (string $table, string $column) => DB::selectOne(
            'SELECT COLUMN_TYPE AS type, CHARACTER_SET_NAME AS charset, COLLATION_NAME AS collation, IS_NULLABLE AS nullable
               FROM information_schema.COLUMNS
              WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = ? AND COLUMN_NAME = ?',
            [$table, $column],
        );

        $scope = $definition('roles', 'scope');
        $roleScope = $definition('user_roles', 'role_scope');

        $this->assertEquals($scope, $roleScope);
        $this->assertSame('varchar(32)', $scope->type);
        $this->assertSame('utf8mb4', $scope->charset);
        $this->assertSame('utf8mb4_unicode_ci', $scope->collation);
        $this->assertSame('NO', $roleScope->nullable);
    }

    public function test_roles_are_seeded_with_exactly_the_three_stored_roles(): void
    {
        $roles = DB::table('roles')->orderBy('id')->pluck('scope', 'name')->all();

        $this->assertSame([
            'super_admin' => 'global',
            'institute_admin' => 'institute',
            'institute_staff' => 'institute',
        ], $roles);
    }

    /**
     * @return list<string>
     */
    private function column(string $sql): array
    {
        return array_map(fn (object $row) => $row->name, DB::select($sql));
    }

    private function dataType(string $table, string $column): ?string
    {
        return DB::scalar(
            'SELECT DATA_TYPE FROM information_schema.COLUMNS WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = ? AND COLUMN_NAME = ?',
            [$table, $column],
        );
    }
}
