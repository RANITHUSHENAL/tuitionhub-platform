<?php

namespace Tests\Feature\Models;

use App\Models\InstituteStaff;
use App\Models\User;
use Carbon\CarbonImmutable;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Carbon;
use Tests\Feature\Concerns\AppFixtures;
use Tests\TestCase;

/**
 * VP-55: instants serialize as YYYY-MM-DDTHH:MM:SSZ; calendar dates as YYYY-MM-DD (contract §1.5).
 */
class DateSerializationTest extends TestCase
{
    use AppFixtures;
    use RefreshDatabase;

    public function test_instants_serialize_in_utc_without_fractional_seconds(): void
    {
        $this->travelTo(Carbon::parse('2026-10-28 08:30:00.654321', 'UTC'));

        $user = User::factory()->create();

        $this->assertSame('2026-10-28T08:30:00Z', $user->toArray()['created_at']);
        $this->assertSame('2026-10-28T08:30:00Z', $user->fresh()->toArray()['updated_at']);
    }

    public function test_an_instant_in_another_time_zone_is_rendered_in_utc(): void
    {
        $user = User::factory()->create();
        $user->forceFill(['phone_verified_at' => CarbonImmutable::parse('2026-10-28 14:00:00', 'Asia/Colombo')]);

        $this->assertSame('2026-10-28T08:30:00Z', $user->toArray()['phone_verified_at']);
    }

    public function test_calendar_dates_serialize_as_plain_dates(): void
    {
        $institute = $this->insertInstitute();
        $staffId = $this->insertStaff($institute, $this->insertUser(), [
            'status' => 'former',
            'joined_on' => '2026-10-01',
            'left_on' => '2026-10-20',
        ]);

        $staff = InstituteStaff::query()->findOrFail($staffId)->toArray();

        $this->assertSame('2026-10-01', $staff['joined_on']);
        $this->assertSame('2026-10-20', $staff['left_on']);
    }
}
