<?php

namespace Tests;

use Illuminate\Foundation\Application;
use Illuminate\Foundation\Testing\TestCase as BaseTestCase;
use RuntimeException;

abstract class TestCase extends BaseTestCase
{
    public const REQUIRED_ENVIRONMENT = 'testing';

    public const REQUIRED_CONNECTION = 'mysql';

    public const REQUIRED_DATABASE = 'tuitionhub_test';

    public const REQUIRED_USERNAME = 'tuitionhub_test_runner';

    /**
     * Boot the application and refuse to continue unless it targets the test database.
     *
     * This runs before setUpTraits(), so RefreshDatabase and similar traits
     * never migrate or wipe a database that failed the check.
     */
    public function createApplication(): Application
    {
        $app = parent::createApplication();

        $connection = (string) $app['config']->get('database.default');
        $settings = (array) $app['config']->get("database.connections.{$connection}", []);

        static::assertSafeTestDatabase(
            (string) $app->environment(),
            $connection,
            (string) ($settings['database'] ?? ''),
            (string) ($settings['url'] ?? ''),
            (string) ($settings['username'] ?? ''),
        );

        return $app;
    }

    /**
     * Throw unless the environment, connection, database and username are exactly
     * the test ones and no connection URL can override them.
     */
    public static function assertSafeTestDatabase(
        string $environment,
        string $connection,
        string $database,
        string $url,
        string $username,
    ): void {
        $problems = [];

        if ($environment !== self::REQUIRED_ENVIRONMENT) {
            $problems[] = sprintf('APP_ENV must be %s, got %s', self::REQUIRED_ENVIRONMENT, $environment);
        }

        if ($connection !== self::REQUIRED_CONNECTION) {
            $problems[] = sprintf('connection must be %s, got %s', self::REQUIRED_CONNECTION, $connection);
        }

        if ($database !== self::REQUIRED_DATABASE) {
            $problems[] = sprintf('database must be %s, got %s', self::REQUIRED_DATABASE, $database);
        }

        if ($url !== '') {
            // The URL itself is not echoed because it may contain a password.
            $problems[] = 'DB_URL must be empty';
        }

        if ($username !== self::REQUIRED_USERNAME) {
            $problems[] = sprintf('username must be %s, got %s', self::REQUIRED_USERNAME, $username);
        }

        if ($problems !== []) {
            throw new RuntimeException('Refusing to run tests: '.implode('; ', $problems).'.');
        }
    }
}
