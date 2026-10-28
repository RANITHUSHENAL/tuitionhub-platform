<?php

namespace Tests\Feature\Schema;

use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Tests\Feature\Schema\Concerns\SchemaFixtures;
use Tests\TestCase;

/**
 * VP-51: audit_logs constraints. Redaction, append-only and same-transaction rules belong to the
 * future AuditLogger service and are not tested here.
 */
class AuditLogConstraintsTest extends TestCase
{
    use RefreshDatabase;
    use SchemaFixtures;

    private const MISSING_ID = 999999999;

    public function test_vp51b_created_at_is_required(): void
    {
        $this->assertConstraintViolation(
            fn () => DB::table('audit_logs')->insert([
                'action' => 'student.created',
                'auditable_type' => 'student_profile',
                'auditable_id' => 1,
            ]),
            self::MISSING_DEFAULT,
            'created_at',
        );
    }

    public function test_vp51c_json_columns_reject_invalid_json(): void
    {
        $this->assertConstraintViolation(
            fn () => $this->insertAuditLog(['new_values' => '{not json']),
            self::INVALID_JSON,
            'new_values',
        );
    }

    public function test_vp51d_actor_and_institute_must_exist(): void
    {
        $this->assertConstraintViolation(
            fn () => $this->insertAuditLog(['user_id' => self::MISSING_ID]),
            self::NO_REFERENCED_ROW,
            'audit_logs_user_fk',
        );

        $this->assertConstraintViolation(
            fn () => $this->insertAuditLog(['institute_id' => self::MISSING_ID]),
            self::NO_REFERENCED_ROW,
            'audit_logs_institute_fk',
        );
    }

    public function test_vp51e_an_audited_user_cannot_be_deleted(): void
    {
        $user = $this->insertUser();
        $this->insertAuditLog(['user_id' => $user]);

        $this->assertConstraintViolation(
            fn () => DB::table('users')->where('id', $user)->delete(),
            self::ROW_IS_REFERENCED,
            'audit_logs_user_fk',
        );
    }

    public function test_vp51f_a_system_event_without_actor_or_institute_is_accepted(): void
    {
        $id = $this->insertAuditLog(['new_values' => json_encode(['status' => 'active'])]);

        $row = DB::table('audit_logs')->where('id', $id)->first();

        $this->assertNull($row->user_id);
        $this->assertNull($row->institute_id);
    }
}
