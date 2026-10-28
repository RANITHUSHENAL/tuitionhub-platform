<?php

namespace Tests\Unit\Otp;

use App\Otp\Exceptions\OtpUnavailableException;
use App\Otp\FakeOtpDriver;
use App\Otp\LogOtpDriver;
use App\Otp\UnavailableOtpDriver;
use Illuminate\Support\Facades\Log;
use Psr\Log\LoggerInterface;
use Tests\TestCase;

/**
 * Dev/test OTP drivers fail closed in production; the log driver writes only to the `otp` channel (D-019).
 */
class OtpDriversTest extends TestCase
{
    private const PHONE = '+94700000601';

    public function test_the_otp_channel_is_a_dedicated_local_file(): void
    {
        $this->assertSame('single', config('logging.channels.otp.driver'));
        $this->assertSame(storage_path('logs/otp.log'), config('logging.channels.otp.path'));
        $this->assertNotContains('otp', (array) config('logging.channels.stack.channels'));
    }

    public function test_the_log_driver_writes_a_masked_phone_and_the_code_to_the_otp_channel_only(): void
    {
        $channel = $this->mock(LoggerInterface::class);
        $channel->shouldReceive('info')->once()
            ->with('DEV MODE — no SMS is sent. OTP for +9470*****01: 123456');
        Log::shouldReceive('channel')->once()->with('otp')->andReturn($channel);

        (new LogOtpDriver($this->app))->send(self::PHONE, '123456');
    }

    public function test_log_and_fake_drivers_refuse_to_send_in_production(): void
    {
        $this->app['env'] = 'production';
        Log::shouldReceive('channel')->never();

        foreach ([new LogOtpDriver($this->app), new FakeOtpDriver($this->app)] as $driver) {
            try {
                $driver->send(self::PHONE, '123456');
                $this->fail($driver->name().' driver sent a code in production.');
            } catch (OtpUnavailableException) {
                $this->addToAssertionCount(1);
            }
        }
    }

    public function test_the_fake_driver_records_codes_per_phone(): void
    {
        $driver = new FakeOtpDriver($this->app);

        $driver->send(self::PHONE, '111111');
        $driver->send(self::PHONE, '222222');

        $this->assertSame('222222', $driver->lastCodeFor(self::PHONE));
        $this->assertSame(2, $driver->sentCount(self::PHONE));
        $this->assertNull($driver->lastCodeFor('+94700000602'));
    }

    public function test_an_unknown_driver_is_always_unavailable(): void
    {
        $this->expectException(OtpUnavailableException::class);

        (new UnavailableOtpDriver('twilio'))->assertAvailable();
    }
}
