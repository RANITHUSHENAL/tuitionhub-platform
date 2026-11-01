<?php

namespace Tests\Feature\Schema;

use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Tests\TestCase;

/**
 * VP-79: the M3 migrations build the G5/G6 schema in docs/SPRINT_SCHEMA.md Part 3 (D-024).
 */
class R3SchemaStructureTest extends TestCase
{
    use RefreshDatabase;

    private const R3_TABLES = ['classes', 'class_fee_versions', 'class_sessions', 'class_enrollments', 'attendances'];

    public function test_r3_tables_exist_without_soft_deletes(): void
    {
        $tables = array_map(
            fn (object $row) => $row->name,
            DB::select('SELECT TABLE_NAME AS name FROM information_schema.TABLES WHERE TABLE_SCHEMA = DATABASE()'),
        );

        foreach (self::R3_TABLES as $table) {
            $this->assertContains($table, $tables);
            $this->assertNull($this->column($table, 'deleted_at'), "{$table} must not soft-delete (§0.8)");
        }

        // Not built (D-012, contract §2.3).
        foreach ([['classes', 'hall_id'], ['classes', 'slug'], ['class_enrollments', 'qr_token'], ['class_enrollments', 'student_card_no'], ['class_sessions', 'substitute_teacher_id'], ['attendances', 'device_id']] as [$table, $column]) {
            $this->assertNull($this->column($table, $column), "{$table}.{$column}");
        }
    }

    public function test_r3_column_types_follow_the_date_time_and_money_conventions(): void
    {
        $expected = [
            'classes' => [
                'starts_on' => 'date', 'ends_on' => 'date', 'max_students' => 'int unsigned', 'title' => 'varchar(255)',
                'description' => 'text', 'status' => 'varchar(32)', 'mode' => 'varchar(32)', 'public_listing' => 'tinyint(1)',
                'created_at' => 'datetime', 'updated_at' => 'datetime',
            ],
            'class_fee_versions' => [
                'amount' => 'decimal(15,2)', 'currency' => 'char(3)', 'billing_cycle' => 'varchar(32)',
                'effective_from' => 'date', 'effective_to' => 'date', 'created_at' => 'datetime', 'updated_at' => 'datetime',
            ],
            'class_sessions' => [
                'session_date' => 'date', 'start_time' => 'time', 'end_time' => 'time', 'status' => 'varchar(32)',
                'created_at' => 'datetime', 'updated_at' => 'datetime',
            ],
            'class_enrollments' => [
                'enrolled_on' => 'date', 'withdrawn_on' => 'date', 'status' => 'varchar(32)',
                'created_at' => 'datetime', 'updated_at' => 'datetime',
            ],
            'attendances' => [
                'status' => 'varchar(32)', 'method' => 'varchar(32)', 'notes' => 'varchar(500)',
                'marked_at' => 'datetime', 'created_at' => 'datetime', 'updated_at' => 'datetime',
            ],
        ];

        foreach ($expected as $table => $columns) {
            foreach ($columns as $column => $type) {
                $this->assertSame($type, $this->column($table, $column)?->column_type, "{$table}.{$column}");
            }
        }

        // Required columns and server-set defaults.
        $this->assertSame('NO', $this->column('classes', 'max_students')->nullable);
        $this->assertSame('NO', $this->column('attendances', 'marked_at')->nullable);
        $this->assertSame('draft', $this->column('classes', 'status')->default_value);
        $this->assertSame('0', $this->column('classes', 'public_listing')->default_value);
        $this->assertSame('LKR', $this->column('class_fee_versions', 'currency')->default_value);
        $this->assertSame('scheduled', $this->column('class_sessions', 'status')->default_value);
        $this->assertSame('active', $this->column('class_enrollments', 'status')->default_value);
        $this->assertSame('manual', $this->column('attendances', 'method')->default_value);
    }

    public function test_r3_generated_columns_are_stored_and_nullable(): void
    {
        $generated = [
            ['class_fee_versions', 'current_flag', 'effective_to'],
            ['class_sessions', 'slot_flag', 'status'],
            ['class_enrollments', 'active_flag', 'status'],
        ];

        foreach ($generated as [$table, $column, $baseColumn]) {
            $definition = $this->column($table, $column);

            $this->assertNotNull($definition, "{$table}.{$column} missing");
            $this->assertSame('STORED GENERATED', $definition->extra, "{$table}.{$column}");
            $this->assertSame('YES', $definition->nullable, "{$table}.{$column}");
            $this->assertSame('tinyint unsigned', $definition->column_type, "{$table}.{$column}");
            $this->assertStringContainsString("`{$baseColumn}`", $definition->expression, "{$table}.{$column}");
        }
    }

    public function test_r3_check_constraints_are_exactly_the_approved_seventeen(): void
    {
        $expected = [
            'classes' => ['classes_status_chk', 'classes_mode_chk', 'classes_max_students_chk', 'classes_dates_chk', 'classes_public_listing_chk'],
            'class_fee_versions' => ['class_fee_versions_amount_chk', 'class_fee_versions_currency_chk', 'class_fee_versions_cycle_chk', 'class_fee_versions_dates_chk'],
            'class_sessions' => ['class_sessions_status_chk', 'class_sessions_times_chk', 'class_sessions_minutes_chk'],
            'class_enrollments' => ['class_enrollments_status_chk', 'class_enrollments_withdrawal_chk', 'class_enrollments_dates_chk'],
            'attendances' => ['attendances_status_chk', 'attendances_method_chk'],
        ];

        $actual = [];
        foreach (DB::select(
            "SELECT TABLE_NAME AS table_name, CONSTRAINT_NAME AS name FROM information_schema.TABLE_CONSTRAINTS
              WHERE TABLE_SCHEMA = DATABASE() AND CONSTRAINT_TYPE = 'CHECK' AND TABLE_NAME IN (?, ?, ?, ?, ?)",
            self::R3_TABLES,
        ) as $row) {
            $actual[$row->table_name][] = $row->name;
        }

        $this->assertEqualsCanonicalizing(array_keys($expected), array_keys($actual));
        foreach ($expected as $table => $names) {
            $this->assertEqualsCanonicalizing($names, $actual[$table], $table);
        }
    }

    public function test_r3_foreign_keys_are_exactly_the_approved_seventeen_and_all_restrict(): void
    {
        // name => [child table, parent table, child columns, parent columns]
        $expected = [
            'classes_institute_fk' => ['classes', 'institutes', ['institute_id'], ['id']],
            'classes_teacher_fk' => ['classes', 'teacher_profiles', ['teacher_id'], ['id']],
            'classes_subject_fk' => ['classes', 'subjects', ['subject_id'], ['id']],
            'classes_grade_fk' => ['classes', 'grades', ['grade_id'], ['id']],
            'classes_stream_fk' => ['classes', 'streams', ['stream_id'], ['id']],
            'classes_exam_target_fk' => ['classes', 'exam_targets', ['exam_target_id'], ['id']],
            'classes_medium_fk' => ['classes', 'mediums', ['medium_id'], ['id']],
            'classes_syllabus_fk' => ['classes', 'syllabi', ['syllabus_id'], ['id']],
            'class_fee_versions_class_fk' => ['class_fee_versions', 'classes', ['class_id'], ['id']],
            'class_fee_versions_created_by_fk' => ['class_fee_versions', 'users', ['created_by_user_id'], ['id']],
            'class_sessions_class_fk' => ['class_sessions', 'classes', ['class_id'], ['id']],
            'class_sessions_created_by_fk' => ['class_sessions', 'users', ['created_by_user_id'], ['id']],
            'class_enrollments_class_institute_fk' => ['class_enrollments', 'classes', ['class_id', 'institute_id'], ['id', 'institute_id']],
            'class_enrollments_student_institute_fk' => ['class_enrollments', 'student_profiles', ['student_id', 'institute_id'], ['id', 'managing_institute_id']],
            'attendances_session_class_fk' => ['attendances', 'class_sessions', ['session_id', 'class_id'], ['id', 'class_id']],
            'attendances_enrollment_class_fk' => ['attendances', 'class_enrollments', ['enrollment_id', 'class_id'], ['id', 'class_id']],
            'attendances_marked_by_fk' => ['attendances', 'users', ['marked_by_user_id'], ['id']],
        ];

        $rules = DB::select(
            'SELECT CONSTRAINT_NAME AS name, TABLE_NAME AS child, REFERENCED_TABLE_NAME AS parent, DELETE_RULE AS on_delete, UPDATE_RULE AS on_update
               FROM information_schema.REFERENTIAL_CONSTRAINTS
              WHERE CONSTRAINT_SCHEMA = DATABASE() AND TABLE_NAME IN (?, ?, ?, ?, ?)',
            self::R3_TABLES,
        );

        $this->assertEqualsCanonicalizing(array_keys($expected), array_column($rules, 'name'));

        foreach ($rules as $rule) {
            [$child, $parent] = $expected[$rule->name];

            $this->assertSame($child, $rule->child, $rule->name);
            $this->assertSame($parent, $rule->parent, $rule->name);
            $this->assertSame('RESTRICT', $rule->on_delete, $rule->name);
            $this->assertSame('NO ACTION', $rule->on_update, $rule->name);
        }

        $actual = [];
        foreach (DB::select(
            'SELECT CONSTRAINT_NAME AS name, COLUMN_NAME AS child_column, REFERENCED_COLUMN_NAME AS parent_column
               FROM information_schema.KEY_COLUMN_USAGE
              WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME IN (?, ?, ?, ?, ?) AND REFERENCED_TABLE_NAME IS NOT NULL
              ORDER BY CONSTRAINT_NAME, ORDINAL_POSITION',
            self::R3_TABLES,
        ) as $column) {
            $actual[$column->name][0][] = $column->child_column;
            $actual[$column->name][1][] = $column->parent_column;
        }

        foreach ($expected as $name => [, , $childColumns, $parentColumns]) {
            $this->assertSame([$childColumns, $parentColumns], $actual[$name], $name);
        }
    }

    public function test_every_approved_r3_index_exists_with_its_columns_in_order(): void
    {
        // name => [table, unique, columns]
        $expected = [
            'classes_uuid_unique' => ['classes', true, ['uuid']],
            'classes_id_institute_unique' => ['classes', true, ['id', 'institute_id']],
            'classes_institute_status_index' => ['classes', false, ['institute_id', 'status']],
            'classes_teacher_status_index' => ['classes', false, ['teacher_id', 'status']],
            'classes_catalogue_index' => ['classes', false, ['status', 'public_listing', 'subject_id', 'grade_id']],
            'class_fee_versions_current_unique' => ['class_fee_versions', true, ['class_id', 'current_flag']],
            'class_fee_versions_class_from_unique' => ['class_fee_versions', true, ['class_id', 'effective_from']],
            'class_sessions_uuid_unique' => ['class_sessions', true, ['uuid']],
            'class_sessions_slot_unique' => ['class_sessions', true, ['class_id', 'session_date', 'start_time', 'slot_flag']],
            'class_sessions_id_class_unique' => ['class_sessions', true, ['id', 'class_id']],
            'class_enrollments_uuid_unique' => ['class_enrollments', true, ['uuid']],
            'class_enrollments_active_unique' => ['class_enrollments', true, ['class_id', 'student_id', 'active_flag']],
            'class_enrollments_id_class_unique' => ['class_enrollments', true, ['id', 'class_id']],
            'class_enrollments_class_institute_index' => ['class_enrollments', false, ['class_id', 'institute_id']],
            'class_enrollments_student_institute_index' => ['class_enrollments', false, ['student_id', 'institute_id']],
            'attendances_session_enrollment_unique' => ['attendances', true, ['session_id', 'enrollment_id']],
            'attendances_session_class_index' => ['attendances', false, ['session_id', 'class_id']],
            'attendances_enrollment_class_index' => ['attendances', false, ['enrollment_id', 'class_id']],
        ];

        $actual = [];
        foreach (DB::select(
            'SELECT TABLE_NAME AS table_name, INDEX_NAME AS name, NON_UNIQUE AS non_unique, COLUMN_NAME AS column_name
               FROM information_schema.STATISTICS
              WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME IN (?, ?, ?, ?, ?)
              ORDER BY TABLE_NAME, INDEX_NAME, SEQ_IN_INDEX',
            self::R3_TABLES,
        ) as $row) {
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

    public function test_composite_fk_columns_are_identical_non_null_bigints(): void
    {
        $columns = [
            ['classes', 'id'], ['classes', 'institute_id'],
            ['student_profiles', 'id'], ['student_profiles', 'managing_institute_id'],
            ['class_sessions', 'id'], ['class_sessions', 'class_id'],
            ['class_enrollments', 'id'], ['class_enrollments', 'class_id'], ['class_enrollments', 'student_id'], ['class_enrollments', 'institute_id'],
            ['attendances', 'session_id'], ['attendances', 'enrollment_id'], ['attendances', 'class_id'],
        ];

        foreach ($columns as [$table, $column]) {
            $definition = $this->column($table, $column);

            $this->assertSame('bigint unsigned', $definition->column_type, "{$table}.{$column}");
            $this->assertSame('NO', $definition->nullable, "{$table}.{$column}");
        }
    }

    public function test_the_m3_migrations_run_in_the_approved_order_after_r2(): void
    {
        $migrations = DB::table('migrations')->orderBy('id')->pluck('migration')->all();
        $r3 = [
            '2026_10_29_000014_create_classes_table',
            '2026_10_29_000015_create_class_fee_versions_table',
            '2026_10_29_000016_create_class_sessions_table',
            '2026_10_29_000017_create_class_enrollments_table',
            '2026_10_29_000018_create_attendances_table',
        ];

        $this->assertSame($r3, array_slice($migrations, -5));
        $this->assertSame('2026_10_29_000013_create_student_guardians_table', $migrations[count($migrations) - 6]);
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
