<?php

namespace Tests\Support;

use App\Otp\OtpDriver;
use Closure;
use Illuminate\Support\Facades\DB;
use Throwable;

/**
 * Records every send together with the database state at that moment, to prove the code is sent
 * only after the issuing transaction has committed (D-022). Test-only.
 */
final class RecordingOtpDriver implements OtpDriver
{
    /** @var list<array{phone: string, code: string, transaction_level: int, challenges: int}> */
    public array $sends = [];

    /** @var (Closure(): Throwable)|null */
    public ?Closure $failWith = null;

    public function name(): string
    {
        return 'fake';
    }

    public function assertAvailable(): void {}

    public function send(#[\SensitiveParameter] string $phone, #[\SensitiveParameter] string $code): void
    {
        $this->sends[] = [
            'phone' => $phone,
            'code' => $code,
            'transaction_level' => DB::transactionLevel(),
            'challenges' => DB::table('otp_challenges')->where('phone', $phone)->count(),
        ];

        if ($this->failWith !== null) {
            throw ($this->failWith)();
        }
    }
}
