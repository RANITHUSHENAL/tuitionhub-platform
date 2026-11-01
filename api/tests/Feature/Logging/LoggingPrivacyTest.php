<?php

namespace Tests\Feature\Logging;

use App\Exceptions\DomainRuleException;
use App\Exceptions\ServiceBusyException;
use App\Otp\Exceptions\AccountSuspendedException;
use App\Otp\Exceptions\InvalidOtpException;
use App\Otp\Exceptions\OtpThrottledException;
use App\Otp\Exceptions\OtpUnavailableException;
use App\Otp\FakeOtpDriver;
use App\Otp\LogOtpDriver;
use App\Otp\OtpCodeHasher;
use App\Otp\OtpService;
use App\Support\PhoneMasker;
use Illuminate\Contracts\Debug\ExceptionHandler;
use Illuminate\Database\QueryException;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use InvalidArgumentException;
use Tests\Support\FakeDatabaseErrors;
use Tests\TestCase;
use Throwable;

/**
 * D-022 logging privacy: masked query bindings, expected outcomes not reported, OTP codes and
 * phones redacted from stack traces, and the app's own lock wait timeout. Fictional values only.
 */
class LoggingPrivacyTest extends TestCase
{
    use RefreshDatabase;

    private const PHONE = '+94700000977';

    private const CODE = '483920';

    public function test_query_exception_messages_never_contain_bound_values(): void
    {
        try {
            DB::select('select * from `users` where `phone` = ? and `no_such_column` = 1', [self::PHONE]);
            $this->fail('Expected a QueryException.');
        } catch (QueryException $exception) {
            $this->assertStringNotContainsString(self::PHONE, $exception->getMessage());
            $this->assertStringContainsString('`phone` = ?', $exception->getMessage());
        }
    }

    public function test_expected_outcomes_are_not_reported_but_faults_are(): void
    {
        $handler = $this->app->make(ExceptionHandler::class);

        foreach ([
            new DomainRuleException(DomainRuleException::ALREADY_ACTIVE, FakeDatabaseErrors::uniqueViolation()),
            new InvalidOtpException,
            new OtpThrottledException(30),
            new AccountSuspendedException,
            new ServiceBusyException,
        ] as $expected) {
            $this->assertFalse($handler->shouldReport($expected), $expected::class.' must not be logged.');
        }

        // A misconfigured OTP setup is an operational fault and must still be logged.
        $this->assertTrue($handler->shouldReport(new OtpUnavailableException));
    }

    public function test_otp_codes_and_phones_are_redacted_from_stack_traces(): void
    {
        $this->app['env'] = 'production';

        $calls = [
            fn () => (new OtpCodeHasher(null))->codeHash('6b0e3f9a-1c2d-4e5f-8a9b-0c1d2e3f4a5b', self::PHONE, self::CODE),
            fn () => (new OtpCodeHasher(null))->matches(str_repeat('a', 64), '6b0e3f9a-1c2d-4e5f-8a9b-0c1d2e3f4a5b', self::PHONE, self::CODE),
            fn () => (new LogOtpDriver($this->app))->send(self::PHONE, self::CODE),
            fn () => (new FakeOtpDriver($this->app))->send(self::PHONE, self::CODE),
            fn () => (new OtpService(new FakeOtpDriver($this->app), new OtpCodeHasher(null)))
                ->verify('6b0e3f9a-1c2d-4e5f-8a9b-0c1d2e3f4a5b', self::PHONE, self::CODE),
            fn () => PhoneMasker::mask('0'.self::CODE.'77'),
        ];

        foreach ($calls as $index => $call) {
            $trace = $this->traceOf($call);

            $this->assertStringNotContainsString(self::PHONE, $trace, "Call {$index} leaked the phone.");
            $this->assertStringNotContainsString(self::CODE, $trace, "Call {$index} leaked the code.");

            // With argument capture on (zend.exception_ignore_args=Off), the redaction marker shows.
            if (! ini_get('zend.exception_ignore_args')) {
                $this->assertStringContainsString('SensitiveParameterValue', $trace, "Call {$index} was not redacted.");
            }
        }
    }

    public function test_this_apps_connection_waits_at_most_ten_seconds_for_a_row_lock(): void
    {
        $this->assertSame(10, (int) DB::selectOne('select @@session.innodb_lock_wait_timeout as t')->t);
    }

    private function traceOf(callable $call): string
    {
        try {
            $call();
        } catch (OtpUnavailableException|InvalidArgumentException $exception) {
            return $exception->getTraceAsString();
        } catch (Throwable $exception) {
            $this->fail('Unexpected '.$exception::class);
        }

        $this->fail('Expected the call to throw.');
    }
}
