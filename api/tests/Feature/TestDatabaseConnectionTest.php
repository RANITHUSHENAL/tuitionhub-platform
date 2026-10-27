<?php

namespace Tests\Feature;

use Illuminate\Support\Facades\DB;
use Tests\TestCase;

class TestDatabaseConnectionTest extends TestCase
{
    public function test_it_connects_to_the_test_database(): void
    {
        $this->assertSame('tuitionhub_test', DB::scalar('SELECT DATABASE()'));
    }

    public function test_the_session_timezone_is_utc(): void
    {
        $this->assertSame('+00:00', DB::scalar('SELECT @@session.time_zone'));
    }

    public function test_it_connects_as_the_restricted_test_account(): void
    {
        $this->assertSame('tuitionhub_test_runner@127.0.0.1', DB::scalar('SELECT CURRENT_USER()'));
    }
}
