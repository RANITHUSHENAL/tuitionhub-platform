<?php

namespace Tests\Feature\Models;

use App\Models\Institute;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use LogicException;
use Tests\Feature\Concerns\AppFixtures;
use Tests\TestCase;

/**
 * VP-54: public UUIDs are random v4, server-owned and immutable; the integer id stays the key.
 */
class PublicUuidTest extends TestCase
{
    use AppFixtures;
    use RefreshDatabase;

    public function test_the_user_factory_creates_a_v4_uuid_and_keeps_an_incrementing_integer_id(): void
    {
        $user = User::factory()->create();

        $this->assertMatchesRegularExpression(self::V4_UUID, $user->uuid);
        $this->assertIsInt($user->getKey());
        $this->assertTrue($user->getIncrementing());
        $this->assertSame('int', $user->getKeyType());
        $this->assertSame('id', $user->getKeyName());
        $this->assertMatchesRegularExpression('/^[+]947[0-9]{8}$/', $user->phone);
    }

    public function test_a_supplied_uuid_is_always_replaced_even_on_a_quiet_save(): void
    {
        $supplied = '00000000-0000-4000-8000-000000000000';

        $user = User::factory()->create(['uuid' => $supplied]);
        $this->assertNotSame($supplied, $user->uuid);
        $this->assertMatchesRegularExpression(self::V4_UUID, $user->uuid);

        $institute = (new Institute)->forceFill([
            'uuid' => $supplied,
            'owner_user_id' => $user->getKey(),
            'name' => 'Fictional Quiet Academy',
            'district' => 'Galle',
            'status' => 'active',
        ]);
        $institute->saveQuietly();

        $this->assertNotSame($supplied, $institute->uuid);
        $this->assertMatchesRegularExpression(self::V4_UUID, $institute->uuid);
    }

    public function test_a_uuid_cannot_be_changed_after_creation(): void
    {
        $user = User::factory()->create();
        $original = $user->uuid;

        $user->uuid = (string) Str::uuid();

        try {
            $user->save();
            $this->fail('Expected the UUID change to be refused.');
        } catch (LogicException) {
            $this->assertSame($original, DB::table('users')->where('id', $user->getKey())->value('uuid'));
        }
    }

    public function test_route_binding_uses_the_uuid_and_rejects_malformed_values_without_querying(): void
    {
        $user = User::factory()->create();

        $this->assertSame('uuid', $user->getRouteKeyName());
        $this->assertTrue($user->is((new User)->resolveRouteBinding($user->uuid)));

        DB::enableQueryLog();
        $this->assertNull((new User)->resolveRouteBinding((string) $user->getKey()));
        $this->assertNull((new User)->resolveRouteBinding('not-a-uuid'));
        $this->assertSame([], DB::getQueryLog());
    }

    public function test_internal_ids_are_not_serialized(): void
    {
        $owner = $this->makeUser();
        $institute = $this->makeInstitute($owner);

        $this->assertArrayNotHasKey('id', $owner->toArray());
        $this->assertArrayHasKey('uuid', $owner->toArray());
        $this->assertArrayNotHasKey('id', $institute->toArray());
        $this->assertArrayNotHasKey('owner_user_id', $institute->toArray());
    }
}
