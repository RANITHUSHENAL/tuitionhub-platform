<?php

namespace App\Otp;

/**
 * Delivers an OTP code (docs/PRODUCT_DECISIONS.md OTP approach). Implementations must never fall
 * back to another driver and must never be usable in production unless they send real SMS.
 */
interface OtpDriver
{
    /**
     * The driver name reported in meta.otp_driver.
     */
    public function name(): string;

    /**
     * Throws OtpUnavailableException when the driver must not run (for example in production).
     */
    public function assertAvailable(): void;

    public function send(string $phone, string $code): void;
}
