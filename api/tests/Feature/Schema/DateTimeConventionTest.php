<?php

namespace Tests\Feature\Schema;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Tests\TestCase;

/**
 * VP-25: instants are stored as UTC DATETIME, whether written by Eloquent or by a query-builder seed.
 */
class DateTimeConventionTest extends TestCase
{
    use RefreshDatabase;

    public function test_vp25_eloquent_writes_utc_datetime_timestamps(): void
    {
        $this->travelTo(Carbon::parse('2026-10-28 08:30:00', 'UTC'));

        $user = (new User)->forceFill([
            'uuid' => (string) Str::uuid(),
            'phone' => '+94700000001',
        ]);
        $user->save();

        $row = DB::table('users')->where('id', $user->id)->first();

        $this->assertSame('2026-10-28 08:30:00', $row->created_at);
        $this->assertSame('2026-10-28 08:30:00', $row->updated_at);

        // Laravel's default serialisation adds microseconds; the contract format is an API-resource task.
        $this->assertStringStartsWith('2026-10-28T08:30:00', $user->fresh()->toArray()['created_at']);
        $this->assertStringEndsWith('Z', $user->fresh()->toArray()['created_at']);
    }

    public function test_vp25_migration_seeded_roles_have_timestamps(): void
    {
        $this->assertSame(0, DB::table('roles')->whereNull('created_at')->orWhereNull('updated_at')->count());
    }
}
