<?php

namespace App\Otp;

use App\Otp\Exceptions\OtpUnavailableException;
use App\Support\PhoneMasker;
use Illuminate\Contracts\Foundation\Application;
use Illuminate\Support\Facades\Log;

/**
 * Local development only: writes the code to the dedicated `otp` log channel.
 * This is not real phone verification. Fails closed in production (503).
 */
final class LogOtpDriver implements OtpDriver
{
    public function __construct(private readonly Application $app) {}

    public function name(): string
    {
        return 'log';
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

        Log::channel('otp')->info('DEV MODE — no SMS is sent. OTP for '.PhoneMasker::mask($phone).': '.$code);
    }
}
