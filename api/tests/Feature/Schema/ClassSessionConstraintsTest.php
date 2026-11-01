<?php

namespace Tests\Feature\Schema;

use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Tests\Feature\Schema\Concerns\SchemaFixtures;
use Tests\TestCase;

/**
 * VP-66, VP-71 (sessions), VP-72 (slot_flag) and VP-73 (sessions): class_sessions constraints
 * (docs/SPRINT_SCHEMA.md G5.4; contract v0.7.1 §10.1; D-024).
 */
class ClassSessionConstraintsTest extends TestCase
{
    use RefreshDatabase;
    use SchemaFixtures;

    public function test_vp66_a_second_non_cancelled_session_in_the_same_slot_is_rejected(): void
    {
        $class = $this->insertClass($this->insertInstitute());
        $this->insertSession($class);

        $this->assertConstraintViolation(
            fn () => $this->insertSession($class, ['end_time' => '18:00:00']),
            self::DUPLICATE_KEY,
            'class_sessions_slot_unique',
        );
    }

    public function test_vp66_a_cancelled_session_frees_its_slot_and_stays_as_history(): void
    {
        $class = $this->insertClass($this->insertInstitute());
        $first = $this->insertSession($class);
        DB::table('class_sessions')->where('id', $first)->update(['status' => 'cancelled']);

        $replacement = $this->insertSession($class);

        // A second cancellation in the same slot is also kept.
        DB::table('class_sessions')->where('id', $replacement)->update(['status' => 'cancelled']);
        $this->insertSession($class);

        $slot = DB::table('class_sessions')->where('class_id', $class)->where('session_date', '2026-11-03')->where('start_time', '15:30:00');
        $this->assertSame(3, (clone $slot)->count());
        $this->assertSame(2, (clone $slot)->where('status', 'cancelled')->whereNull('slot_flag')->count());
        $this->assertSame(1, (clone $slot)->where('status', 'scheduled')->where('slot_flag', 1)->count());

        // Reinstating a cancelled session while the slot is taken is refused.
        $this->assertConstraintViolation(
            fn () => DB::table('class_sessions')->where('id', $first)->update(['status' => 'scheduled']),
            self::DUPLICATE_KEY,
            'class_sessions_slot_unique',
        );
    }

    public function test_other_start_times_dates_and_classes_do_not_collide(): void
    {
        $institute = $this->insertInstitute();
        $class = $this->insertClass($institute);
        $this->insertSession($class);
        $this->insertSession($class, ['start_time' => '16:00:00', 'end_time' => '18:00:00']);
        $this->insertSession($class, ['session_date' => '2026-11-04']);
        $this->insertSession($this->insertClass($institute));

        $this->assertSame(4, DB::table('class_sessions')->count());
    }

    public function test_vp71_session_check_constraints(): void
    {
        $class = $this->insertClass($this->insertInstitute());

        $cases = [
            [['start_time' => '17:30:00', 'end_time' => '15:30:00'], 'class_sessions_times_chk'],
            [['start_time' => '15:30:00', 'end_time' => '15:30:00'], 'class_sessions_times_chk'],
            [['status' => 'done'], 'class_sessions_status_chk'],
            [['start_time' => '15:30:20'], 'class_sessions_minutes_chk'],
            [['end_time' => '17:30:59'], 'class_sessions_minutes_chk'],
        ];

        foreach ($cases as [$overrides, $constraint]) {
            $this->assertConstraintViolation(fn () => $this->insertSession($class, $overrides), self::CHECK_VIOLATED, $constraint);
        }

        $this->assertDatabaseCount('class_sessions', 0);
    }

    public function test_vp72_slot_flag_cannot_be_written(): void
    {
        $class = $this->insertClass($this->insertInstitute());

        $this->assertConstraintViolation(fn () => $this->insertSession($class, ['slot_flag' => 1]), self::GENERATED_COLUMN_WRITE, 'slot_flag');
    }

    public function test_vp73_a_class_or_creator_with_sessions_cannot_be_deleted(): void
    {
        $creator = $this->insertUser();
        $class = $this->insertClass($this->insertInstitute());
        $this->insertSession($class, ['created_by_user_id' => $creator]);

        $this->assertConstraintViolation(fn () => DB::table('classes')->where('id', $class)->delete(), self::ROW_IS_REFERENCED, 'class_sessions_class_fk');
        $this->assertConstraintViolation(fn () => DB::table('users')->where('id', $creator)->delete(), self::ROW_IS_REFERENCED, 'class_sessions_created_by_fk');
        $this->assertConstraintViolation(fn () => $this->insertSession(999999999), self::NO_REFERENCED_ROW, 'class_sessions_class_fk');
    }
}
