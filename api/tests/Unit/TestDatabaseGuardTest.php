<?php

namespace Tests\Unit;

use PHPUnit\Framework\TestCase;
use RuntimeException;
use Tests\TestCase as AppTestCase;

class TestDatabaseGuardTest extends TestCase
{
    public function test_it_allows_the_testing_environment_on_the_test_database(): void
    {
        AppTestCase::assertSafeTestDatabase('testing', 'mysql', 'tuitionhub_test', '', 'tuitionhub_test_runner');

        $this->addToAssertionCount(1);
    }

    public function test_it_rejects_a_non_testing_environment(): void
    {
        $this->expectException(RuntimeException::class);

        AppTestCase::assertSafeTestDatabase('local', 'mysql', 'tuitionhub_test', '', 'tuitionhub_test_runner');
    }

    public function test_it_rejects_the_development_database(): void
    {
        $this->expectException(RuntimeException::class);

        AppTestCase::assertSafeTestDatabase('testing', 'mysql', 'tuitionhub', '', 'tuitionhub_test_runner');
    }

    public function test_it_rejects_a_non_empty_connection_url(): void
    {
        $this->expectException(RuntimeException::class);

        AppTestCase::assertSafeTestDatabase(
            'testing',
            'mysql',
            'tuitionhub_test',
            'mysql://tuitionhub_test_runner@127.0.0.1:3306/tuitionhub',
            'tuitionhub_test_runner',
        );
    }

    public function test_it_does_not_echo_the_connection_url(): void
    {
        try {
            AppTestCase::assertSafeTestDatabase('testing', 'mysql', 'tuitionhub_test', 'mysql://user:secret@127.0.0.1/db', 'tuitionhub_test_runner');
            $this->fail('Expected the guard to throw.');
        } catch (RuntimeException $e) {
            $this->assertStringNotContainsString('secret', $e->getMessage());
        }
    }

    public function test_it_rejects_a_different_username(): void
    {
        $this->expectException(RuntimeException::class);

        AppTestCase::assertSafeTestDatabase('testing', 'mysql', 'tuitionhub_test', '', 'tuitionhub_app');
    }
}
