<?php

namespace App\Otp;

use App\Otp\Exceptions\OtpUnavailableException;

/**
 * Used when OTP_DRIVER is missing or unknown: every OTP operation fails closed (503).
 * There is never a silent fallback to another driver.
 */
final class UnavailableOtpDriver implements OtpDriver
{
    public function __construct(private readonly string $configured) {}

    public function name(): string
    {
        return $this->configured;
    }

    public function assertAvailable(): void
    {
        throw new OtpUnavailableException;
    }

    public function send(string $phone, string $code): void
    {
        $this->assertAvailable();
    }
}
