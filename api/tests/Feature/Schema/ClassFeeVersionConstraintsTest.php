<?php

namespace Tests\Feature\Schema;

use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Tests\Feature\Schema\Concerns\SchemaFixtures;
use Tests\TestCase;

/**
 * VP-68, VP-71 (fees), VP-72 (current_flag) and VP-73 (fees): class_fee_versions constraints
 * (docs/SPRINT_SCHEMA.md G5.2, D-024). Non-overlap and the "after the open version" rule are
 * service rules (VP-76, E3).
 */
class ClassFeeVersionConstraintsTest extends TestCase
{
    use RefreshDatabase;
    use SchemaFixtures;

    public function test_vp68_a_class_has_at_most_one_open_fee_version(): void
    {
        $class = $this->insertClass($this->insertInstitute());
        $this->insertFeeVersion($class, ['effective_from' => '2026-11-01']);

        $this->assertConstraintViolation(
            fn () => $this->insertFeeVersion($class, ['effective_from' => '2026-12-01']),
            self::DUPLICATE_KEY,
            'class_fee_versions_current_unique',
        );
    }

    public function test_vp68_two_versions_cannot_start_on_the_same_day(): void
    {
        $class = $this->insertClass($this->insertInstitute());
        $this->insertFeeVersion($class, ['effective_from' => '2026-11-01', 'effective_to' => '2026-11-30']);

        $this->assertConstraintViolation(
            fn () => $this->insertFeeVersion($class, ['effective_from' => '2026-11-01', 'effective_to' => '2026-12-31']),
            self::DUPLICATE_KEY,
            'class_fee_versions_class_from_unique',
        );
    }

    public function test_closed_history_plus_one_open_version_is_accepted_and_classes_are_independent(): void
    {
        $institute = $this->insertInstitute();
        $class = $this->insertClass($institute);
        $this->insertFeeVersion($class, ['effective_from' => '2026-09-01', 'effective_to' => '2026-09-30', 'amount' => '2000.00']);
        $this->insertFeeVersion($class, ['effective_from' => '2026-10-01', 'effective_to' => '2026-10-31', 'amount' => '2250.00']);
        $this->insertFeeVersion($class, ['effective_from' => '2026-11-01', 'amount' => '2500.00']);

        // Another class has its own open version.
        $this->insertFeeVersion($this->insertClass($institute), ['effective_from' => '2026-11-01']);

        $versions = DB::table('class_fee_versions')->where('class_id', $class);
        $this->assertSame(3, (clone $versions)->count());
        $this->assertSame(1, (clone $versions)->where('current_flag', 1)->count());
        $this->assertSame('2500.00', (clone $versions)->where('current_flag', 1)->value('amount'));

        // Closing the open version frees the slot for the next one.
        DB::table('class_fee_versions')->where('class_id', $class)->whereNull('effective_to')->update(['effective_to' => '2026-11-30']);
        $this->insertFeeVersion($class, ['effective_from' => '2026-12-01', 'amount' => '2750.00']);
        $this->assertSame(4, (clone $versions)->count());
    }

    public function test_vp71_fee_check_constraints(): void
    {
        $class = $this->insertClass($this->insertInstitute());

        $cases = [
            [['amount' => '0.00'], 'class_fee_versions_amount_chk'],
            [['amount' => '-1.00'], 'class_fee_versions_amount_chk'],
            [['currency' => 'USD'], 'class_fee_versions_currency_chk'],
            [['billing_cycle' => 'weekly'], 'class_fee_versions_cycle_chk'],
            [['effective_from' => '2026-11-01', 'effective_to' => '2026-10-31'], 'class_fee_versions_dates_chk'],
        ];

        foreach ($cases as [$overrides, $constraint]) {
            $this->assertConstraintViolation(fn () => $this->insertFeeVersion($class, $overrides), self::CHECK_VIOLATED, $constraint);
        }

        $this->insertFeeVersion($class, ['amount' => '0.01', 'billing_cycle' => 'one_time', 'effective_to' => '2026-11-01']);
        $this->assertSame(1, DB::table('class_fee_versions')->count());
    }

    public function test_vp72_current_flag_cannot_be_written(): void
    {
        $class = $this->insertClass($this->insertInstitute());

        $this->assertConstraintViolation(
            fn () => $this->insertFeeVersion($class, ['current_flag' => 1]),
            self::GENERATED_COLUMN_WRITE,
            'current_flag',
        );
    }

    public function test_vp73_a_class_or_creator_with_fee_versions_cannot_be_deleted(): void
    {
        $creator = $this->insertUser();
        $class = $this->insertClass($this->insertInstitute());
        $this->insertFeeVersion($class, ['created_by_user_id' => $creator]);

        $this->assertConstraintViolation(fn () => DB::table('classes')->where('id', $class)->delete(), self::ROW_IS_REFERENCED, 'class_fee_versions_class_fk');
        $this->assertConstraintViolation(fn () => DB::table('users')->where('id', $creator)->delete(), self::ROW_IS_REFERENCED, 'class_fee_versions_created_by_fk');
        $this->assertConstraintViolation(fn () => $this->insertFeeVersion(999999999), self::NO_REFERENCED_ROW, 'class_fee_versions_class_fk');
    }
}
