<?php

namespace Tests\Support;

use App\Otp\OtpCodeHasher;

/**
 * Throws a MySQL-style deadlock from codeHash() for the first $failures calls, then behaves
 * normally. codeHash() runs inside the issuing transaction, after the challenge row is inserted,
 * so a failure there exercises the rollback and retry path (D-022). Test-only.
 */
final class DeadlockingOtpCodeHasher extends OtpCodeHasher
{
    public int $calls = 0;

    public function __construct(?string $configuredKey, private readonly int $failures)
    {
        parent::__construct($configuredKey);
    }

    public function codeHash(string $challengeUuid, #[\SensitiveParameter] string $phone, #[\SensitiveParameter] string $code): string
    {
        $this->calls++;

        if ($this->calls <= $this->failures) {
            throw FakeDatabaseErrors::deadlock();
        }

        return parent::codeHash($challengeUuid, $phone, $code);
    }
}
