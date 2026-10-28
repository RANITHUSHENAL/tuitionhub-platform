<?php

namespace App\Otp;

use App\Otp\Exceptions\OtpUnavailableException;
use Illuminate\Contracts\Foundation\Application;

/**
 * Automated tests only: keeps sent codes in memory so tests can read them without log files.
 * Fails closed in production (503).
 */
final class FakeOtpDriver implements OtpDriver
{
    /** @var array<string, list<string>> */
    private array $sent = [];

    public function __construct(private readonly Application $app) {}

    public function name(): string
    {
        return 'fake';
    }

    public function assertAvailable(): void
    {
        if ($this->app->isProduction()) {
            throw new OtpUnavailableException;
        }
    }

    public function send(string $phone, string $code): void
    {
        $this->assertAvailable();

        $this->sent[$phone][] = $code;
    }

    public function lastCodeFor(string $phone): ?string
    {
        $codes = $this->sent[$phone] ?? [];

        return $codes === [] ? null : $codes[array_key_last($codes)];
    }

    public function sentCount(string $phone): int
    {
        return count($this->sent[$phone] ?? []);
    }
}
