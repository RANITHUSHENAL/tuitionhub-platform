<?php

namespace Tests\Feature\Schema;

use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Tests\TestCase;

/**
 * VP-37 and VP-51a: the R2 migrations build the audit_logs and G4 schema in docs/SPRINT_SCHEMA.md Part 2.
 */
class R2SchemaStructureTest extends TestCase
{
    use RefreshDatabase;

    private const R2_TABLES = ['audit_logs', 'student_profiles', 'guardian_invitations', 'student_guardians'];

    public function test_r2_tables_exist(): void
    {
        $tables = array_map(
            fn (object $row) => $row->name,
            DB::select('SELECT TABLE_NAME AS name FROM information_schema.TABLES WHERE TABLE_SCHEMA = DATABASE()'),
        );

        foreach (self::R2_TABLES as $table) {
            $this->assertContains($table, $tables);
        }
    }

    public function test_r2_instants_are_datetime_and_calendar_dates_are_date(): void
    {
        $expected = [
            'audit_logs' => ['created_at' => 'datetime'],
            'student_profiles' => ['created_at' => 'datetime', 'updated_at' => 'datetime', 'date_of_birth' => 'date'],
            'guardian_invitations' => [
                'expires_at' => 'datetime', 'accepted_at' => 'datetime', 'revoked_at' => 'datetime',
                'created_at' => 'datetime', 'updated_at' => 'datetime',
            ],
            'student_guardians' => [
                'linked_at' => 'datetime', 'revoked_at' => 'datetime',
                'created_at' => 'datetime', 'updated_at' => 'datetime',
            ],
        ];

        foreach ($expected as $table => $columns) {
            foreach ($columns as $column => $type) {
                $this->assertSame($type, $this->column($table, $column)?->data_type, "{$table}.{$column}");
            }
        }
    }

    public function test_audit_logs_requires_created_at_and_has_no_updated_or_deleted_at(): void
    {
        $createdAt = $this->column('audit_logs', 'created_at');

        $this->assertSame('NO', $createdAt->nullable);
        $this->assertNull($createdAt->default_value);
        $this->assertNull($this->column('audit_logs', 'updated_at'));
        $this->assertNull($this->column('audit_logs', 'deleted_at'));
        $this->assertSame('json', $this->column('audit_logs', 'old_values')->data_type);
        $this->assertSame('json', $this->column('audit_logs', 'new_values')->data_type);
        $this->assertSame('varchar(45)', $this->column('audit_logs', 'ip_address')->column_type);
        $this->assertSame('varchar(512)', $this->column('audit_logs', 'user_agent')->column_type);
    }

    public function test_r2_generated_columns_are_stored_and_nullable(): void
    {
        $generated = [
            ['guardian_invitations', 'pending_flag', ['status']],
            ['student_guardians', 'active_flag', ['revoked_at']],
            ['student_guardians', 'primary_flag', ['is_primary', 'revoked_at']],
        ];

        foreach ($generated as [$table, $column, $baseColumns]) {
            $definition = $this->column($table, $column);

            $this->assertNotNull($definition, "{$table}.{$column} missing");
            $this->assertSame('STORED GENERATED', $definition->extra, "{$table}.{$column}");
            $this->assertSame('YES', $definition->nullable, "{$table}.{$column}");
            $this->assertSame('tinyint unsigned', $definition->column_type, "{$table}.{$column}");

            foreach ($baseColumns as $baseColumn) {
                $this->assertStringContainsString("`{$baseColumn}`", $definition->expression, "{$table}.{$column}");
            }
        }
    }

    public function test_r2_check_constraints_are_exactly_the_approved_ten(): void
    {
        $expected = [
            'guardian_invitations' => [
                'guardian_invitations_status_chk', 'guardian_invitations_relationship_chk',
                'guardian_invitations_phone_format_chk', 'guardian_invitations_accepted_chk',
                'guardian_invitations_revoked_chk', 'guardian_invitations_accept_before_expiry_chk',
            ],
            'student_guardians' => [
                'student_guardians_relationship_chk', 'student_guardians_is_primary_chk',
                'student_guardians_revocation_chk', 'student_guardians_revoked_after_linked_chk',
            ],
        ];

        $rows = DB::select(
            "SELECT TABLE_NAME AS table_name, CONSTRAINT_NAME AS name FROM information_schema.TABLE_CONSTRAINTS
              WHERE TABLE_SCHEMA = DATABASE() AND CONSTRAINT_TYPE = 'CHECK' AND TABLE_NAME IN (?, ?, ?, ?)",
            self::R2_TABLES,
        );

        $actual = [];
        foreach ($rows as $row) {
            $actual[$row->table_name][] = $row->name;
        }

        $this->assertEqualsCanonicalizing(array_keys($expected), array_keys($actual));

        foreach ($expected as $table => $names) {
            $this->assertEqualsCanonicalizing($names, $actual[$table], $table);
        }
    }

    public function test_r2_foreign_keys_are_exactly_the_approved_seventeen(): void
    {
        // name => [child table, parent table, child columns, parent columns]
        $expected = [
            'audit_logs_user_fk' => ['audit_logs', 'users', ['user_id'], ['id']],
            'audit_logs_institute_fk' => ['audit_logs', 'institutes', ['institute_id'], ['id']],
            'student_profiles_institute_fk' => ['student_profiles', 'institutes', ['managing_institute_id'], ['id']],
            'student_profiles_created_by_fk' => ['student_profiles', 'users', ['created_by_user_id'], ['id']],
            'student_profiles_user_fk' => ['student_profiles', 'users', ['user_id'], ['id']],
            'student_profiles_grade_fk' => ['student_profiles', 'grades', ['grade_id'], ['id']],
            'student_profiles_medium_fk' => ['student_profiles', 'mediums', ['medium_id'], ['id']],
            'student_profiles_exam_target_fk' => ['student_profiles', 'exam_targets', ['exam_target_id'], ['id']],
            'guardian_invitations_student_institute_fk' => ['guardian_invitations', 'student_profiles', ['student_id', 'institute_id'], ['id', 'managing_institute_id']],
            'guardian_invitations_invited_by_fk' => ['guardian_invitations', 'users', ['invited_by_user_id'], ['id']],
            'guardian_invitations_accepted_by_fk' => ['guardian_invitations', 'users', ['accepted_by_user_id'], ['id']],
            'guardian_invitations_revoked_by_fk' => ['guardian_invitations', 'users', ['revoked_by_user_id'], ['id']],
            'student_guardians_student_fk' => ['student_guardians', 'student_profiles', ['student_id'], ['id']],
            'student_guardians_guardian_fk' => ['student_guardians', 'guardian_profiles', ['guardian_id'], ['id']],
            'student_guardians_linked_by_fk' => ['student_guardians', 'users', ['linked_by_user_id'], ['id']],
            'student_guardians_revoked_by_fk' => ['student_guardians', 'users', ['revoked_by_user_id'], ['id']],
            'student_guardians_invitation_student_fk' => ['student_guardians', 'guardian_invitations', ['invitation_id', 'student_id'], ['id', 'student_id']],
        ];

        $rules = DB::select(
            'SELECT CONSTRAINT_NAME AS name, TABLE_NAME AS child, REFERENCED_TABLE_NAME AS parent, DELETE_RULE AS on_delete, UPDATE_RULE AS on_update
               FROM information_schema.REFERENTIAL_CONSTRAINTS
              WHERE CONSTRAINT_SCHEMA = DATABASE() AND TABLE_NAME IN (?, ?, ?, ?)',
            self::R2_TABLES,
        );

        $this->assertEqualsCanonicalizing(array_keys($expected), array_column($rules, 'name'));

        foreach ($rules as $rule) {
            [$child, $parent] = $expected[$rule->name];

            $this->assertSame($child, $rule->child, $rule->name);
            $this->assertSame($parent, $rule->parent, $rule->name);
            $this->assertSame('RESTRICT', $rule->on_delete, $rule->name);
            $this->assertSame('NO ACTION', $rule->on_update, $rule->name);
        }

        $columns = DB::select(
            'SELECT CONSTRAINT_NAME AS name, COLUMN_NAME AS child_column, REFERENCED_COLUMN_NAME AS parent_column
               FROM information_schema.KEY_COLUMN_USAGE
              WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME IN (?, ?, ?, ?) AND REFERENCED_TABLE_NAME IS NOT NULL
              ORDER BY CONSTRAINT_NAME, ORDINAL_POSITION',
            self::R2_TABLES,
        );

        $actual = [];
        foreach ($columns as $column) {
            $actual[$column->name][0][] = $column->child_column;
            $actual[$column->name][1][] = $column->parent_column;
        }

        foreach ($expected as $name => [, , $childColumns, $parentColumns]) {
            $this->assertSame([$childColumns, $parentColumns], $actual[$name], $name);
        }
    }

    public function test_every_approved_r2_index_exists_with_its_columns_in_order(): void
    {
        // name => [table, unique, columns]
        $expected = [
            'audit_logs_user_created_index' => ['audit_logs', false, ['user_id', 'created_at']],
            'audit_logs_auditable_index' => ['audit_logs', false, ['auditable_type', 'auditable_id']],
            'audit_logs_action_created_index' => ['audit_logs', false, ['action', 'created_at']],
            'audit_logs_institute_created_index' => ['audit_logs', false, ['institute_id', 'created_at']],
            'student_profiles_uuid_unique' => ['student_profiles', true, ['uuid']],
            'student_profiles_user_id_unique' => ['student_profiles', true, ['user_id']],
            'student_profiles_id_institute_unique' => ['student_profiles', true, ['id', 'managing_institute_id']],
            'student_profiles_institute_grade_index' => ['student_profiles', false, ['managing_institute_id', 'grade_id']],
            'guardian_invitations_uuid_unique' => ['guardian_invitations', true, ['uuid']],
            'guardian_invitations_code_hash_unique' => ['guardian_invitations', true, ['code_hash']],
            'guardian_invitations_pending_unique' => ['guardian_invitations', true, ['student_id', 'guardian_phone', 'pending_flag']],
            'guardian_invitations_id_student_unique' => ['guardian_invitations', true, ['id', 'student_id']],
            'guardian_invitations_student_institute_index' => ['guardian_invitations', false, ['student_id', 'institute_id']],
            'guardian_invitations_status_expires_index' => ['guardian_invitations', false, ['status', 'expires_at']],
            'student_guardians_active_unique' => ['student_guardians', true, ['student_id', 'guardian_id', 'active_flag']],
            'student_guardians_primary_unique' => ['student_guardians', true, ['student_id', 'primary_flag']],
            'student_guardians_invitation_unique' => ['student_guardians', true, ['invitation_id']],
            'student_guardians_guardian_active_index' => ['student_guardians', false, ['guardian_id', 'active_flag']],
            'student_guardians_invitation_student_index' => ['student_guardians', false, ['invitation_id', 'student_id']],
        ];

        $rows = DB::select(
            'SELECT TABLE_NAME AS table_name, INDEX_NAME AS name, NON_UNIQUE AS non_unique, COLUMN_NAME AS column_name
               FROM information_schema.STATISTICS
              WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME IN (?, ?, ?, ?)
              ORDER BY TABLE_NAME, INDEX_NAME, SEQ_IN_INDEX',
            self::R2_TABLES,
        );

        $actual = [];
        foreach ($rows as $row) {
            $actual[$row->name]['table'] = $row->table_name;
            $actual[$row->name]['unique'] = (int) $row->non_unique === 0;
            $actual[$row->name]['columns'][] = $row->column_name;
        }

        foreach ($expected as $name => [$table, $unique, $columns]) {
            $this->assertArrayHasKey($name, $actual, "{$name} missing");
            $this->assertSame($table, $actual[$name]['table'], $name);
            $this->assertSame($unique, $actual[$name]['unique'], $name);
            $this->assertSame($columns, $actual[$name]['columns'], $name);
        }
    }

    public function test_composite_fk_columns_have_identical_types_and_expected_nullability(): void
    {
        $expected = [
            ['student_profiles', 'id', 'NO'],
            ['student_profiles', 'managing_institute_id', 'NO'],
            ['guardian_invitations', 'id', 'NO'],
            ['guardian_invitations', 'student_id', 'NO'],
            ['guardian_invitations', 'institute_id', 'NO'],
            ['student_guardians', 'student_id', 'NO'],
            ['student_guardians', 'invitation_id', 'YES'],
        ];

        foreach ($expected as [$table, $column, $nullable]) {
            $definition = $this->column($table, $column);

            $this->assertSame('bigint unsigned', $definition->column_type, "{$table}.{$column}");
            $this->assertSame($nullable, $definition->nullable, "{$table}.{$column}");
        }
    }

    private function column(string $table, string $column): ?object
    {
        return DB::selectOne(
            'SELECT DATA_TYPE AS data_type, COLUMN_TYPE AS column_type, IS_NULLABLE AS nullable, COLUMN_DEFAULT AS default_value,
                    EXTRA AS extra, GENERATION_EXPRESSION AS expression
               FROM information_schema.COLUMNS
              WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = ? AND COLUMN_NAME = ?',
            [$table, $column],
        );
    }
}
