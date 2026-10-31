<?php

namespace App\Otp;

use App\Models\OtpChallenge;
use App\Models\User;
use App\Otp\Exceptions\AccountSuspendedException;
use App\Otp\Exceptions\InvalidOtpException;
use App\Otp\Exceptions\OtpThrottledException;
use App\Support\Transactions;
use Carbon\CarbonImmutable;
use Illuminate\Database\UniqueConstraintViolationException;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Throwable;

/**
 * Phone OTP login (contract v0.4 §4.1–§4.2; D-019, D-022).
 *
 * - Requests never read the users table, so responses and timing are identical for known and
 *   unknown phones. Per-phone and per-IP request limits are applied by the `otp-request` limiter.
 * - Only one code per phone is valid at a time: a new request expires older pending challenges.
 * - The challenge is committed before the code is sent, and a code from a rolled-back attempt is
 *   never sent.
 * - A failed verification's attempt increment is committed before the error is raised.
 * - A correct code consumes the challenge exactly once; a suspended account is not logged in.
 *
 * Lock order: otp_challenges → users (users are never locked before they exist). No range
 * locking reads or range updates: rows are locked or updated by primary or unique key only.
 */
class OtpService
{
    public const PURPOSE_LOGIN = 'login';

    private const VERIFIED = 'verified';

    private const INVALID = 'invalid';

    private const EXHAUSTED = 'exhausted';

    private const SUSPENDED = 'suspended';

    /** Time for sending the code after the issuing transaction has committed. */
    private const SEND_MARGIN_SECONDS = 15;

    public function __construct(
        private readonly OtpDriver $driver,
        private readonly OtpCodeHasher $hasher,
    ) {}

    /**
     * How long one request for a phone holds the per-phone cache lock. It must outlast the worst
     * case of every retried attempt waiting the full lock wait timeout, plus the send; otherwise a
     * second request for the same phone could start while the first is still issuing.
     */
    public static function requestLockSeconds(): int
    {
        return Transactions::MAX_ATTEMPTS * (int) config('database.connections.mysql.lock_wait_timeout', 10)
            + self::SEND_MARGIN_SECONDS;
    }

    /**
     * @throws Exceptions\OtpUnavailableException driver not allowed here or HMAC key missing (503)
     * @throws OtpThrottledException resend cooldown (429)
     */
    public function request(#[\SensitiveParameter] string $phone, string $purpose, ?string $ip): OtpChallenge
    {
        // Fail closed before anything is written.
        $this->hasher->assertConfigured();
        $this->driver->assertAvailable();

        // An atomic cache lock serialises requests for one phone; the challenge row may not exist yet.
        $lock = Cache::lock('otp-request:'.hash('sha256', $purpose.'|'.$phone), self::requestLockSeconds());

        if (! $lock->get()) {
            throw new OtpThrottledException(1);
        }

        try {
            // Issue and commit first. A retried attempt is rolled back completely, so its code is
            // discarded and never sent; only the committed attempt's code is delivered.
            [$challenge, $code] = Transactions::retrying(fn () => $this->issue($phone, $purpose, $ip));

            $this->deliver($challenge, $phone, $code);

            return $challenge;
        } finally {
            $lock->release();
        }
    }

    /**
     * @throws Exceptions\OtpUnavailableException HMAC key missing (503)
     * @throws InvalidOtpException unknown, expired, consumed, mismatched or wrong code (422)
     * @throws OtpThrottledException attempts exhausted (429)
     * @throws AccountSuspendedException correct code for a suspended account (403)
     */
    public function verify(string $challengeUuid, #[\SensitiveParameter] string $phone, #[\SensitiveParameter] string $code): User
    {
        $this->hasher->assertConfigured();

        // The transaction returns an outcome instead of throwing, so the attempt increment and the
        // consumption are committed before any error is raised. Throwing inside would roll them back.
        [$outcome, $user] = Transactions::retrying(fn () => $this->attempt($challengeUuid, $phone, $code));

        return match ($outcome) {
            self::VERIFIED => $user,
            self::SUSPENDED => throw new AccountSuspendedException,
            self::EXHAUSTED => throw new OtpThrottledException((int) config('otp.resend_cooldown_seconds')),
            default => throw new InvalidOtpException,
        };
    }

    /**
     * @return array{0: OtpChallenge, 1: string} the committed challenge and its plaintext code
     */
    private function issue(#[\SensitiveParameter] string $phone, string $purpose, ?string $ip): array
    {
        $now = CarbonImmutable::now('UTC');
        $cooldown = (int) config('otp.resend_cooldown_seconds');

        $latest = OtpChallenge::query()
            ->where('phone', $phone)
            ->where('purpose', $purpose)
            ->latest('last_sent_at')
            ->first();

        if ($latest !== null && $latest->last_sent_at->addSeconds($cooldown)->gt($now)) {
            throw new OtpThrottledException(max(1, $latest->last_sent_at->addSeconds($cooldown)->getTimestamp() - $now->getTimestamp()));
        }

        // Only one code is valid at a time. Plain read of the open challenge IDs, then an update by
        // primary key (C-2, D-022): a range UPDATE on (phone, purpose, …) locks index gaps, and two
        // first-time requests for different phones in the same gap could deadlock on their inserts.
        // Requests for one phone are already serialised by the cache lock in request().
        $open = OtpChallenge::query()
            ->where('phone', $phone)
            ->where('purpose', $purpose)
            ->whereNull('consumed_at')
            ->where('expires_at', '>', $now)
            ->pluck('id');

        if ($open->isNotEmpty()) {
            OtpChallenge::query()->whereKey($open->all())->update(['expires_at' => $now]);
        }

        $code = str_pad((string) random_int(0, 999_999), 6, '0', STR_PAD_LEFT);

        $challenge = new OtpChallenge;
        $challenge->forceFill([
            'phone' => $phone,
            'purpose' => $purpose,
            // Placeholder until the server-generated uuid exists; replaced below in the same transaction.
            'code_hash' => bin2hex(random_bytes(32)),
            'attempts' => 0,
            'max_attempts' => (int) config('otp.max_attempts'),
            'expires_at' => $now->addSeconds((int) config('otp.ttl_seconds')),
            'consumed_at' => null,
            'last_sent_at' => $now,
            'request_ip_hash' => $this->hasher->ipHash($ip),
        ]);
        $challenge->save();

        $challenge->forceFill(['code_hash' => $this->hasher->codeHash($challenge->uuid, $phone, $code)]);
        $challenge->save();

        return [$challenge, $code];
    }

    /**
     * Sends the code of a committed challenge. If sending fails, the challenge is retired at once,
     * so a code nobody received can never be used. The original delivery error is always rethrown,
     * even when retiring fails (D-022): that failure is logged by challenge UUID only and the
     * unsent code stays valid until it expires. There is never a fallback driver.
     */
    private function deliver(OtpChallenge $challenge, #[\SensitiveParameter] string $phone, #[\SensitiveParameter] string $code): void
    {
        try {
            $this->driver->send($phone, $code);
        } catch (Throwable $delivery) {
            try {
                OtpChallenge::query()->whereKey($challenge->getKey())->update(['expires_at' => CarbonImmutable::now('UTC')]);
            } catch (Throwable) {
                // Never the phone, the code or the database error (its message may contain values).
                Log::warning('otp.retire_failed', ['challenge' => $challenge->uuid]);
            }

            throw $delivery;
        }
    }

    /**
     * @return array{0: string, 1: User|null}
     */
    private function attempt(string $challengeUuid, #[\SensitiveParameter] string $phone, #[\SensitiveParameter] string $code): array
    {
        $now = CarbonImmutable::now('UTC');

        $challenge = OtpChallenge::query()->where('uuid', $challengeUuid)->lockForUpdate()->first();

        if ($challenge === null
            || ! hash_equals($challenge->phone, $phone)
            || $challenge->purpose !== self::PURPOSE_LOGIN
            || $challenge->consumed_at !== null
            || $challenge->expires_at->lte($now)) {
            return [self::INVALID, null];
        }

        // An exhausted challenge is never compared again, even with the right code.
        if ($challenge->attempts >= $challenge->max_attempts) {
            return [self::EXHAUSTED, null];
        }

        if (! $this->hasher->matches($challenge->code_hash, $challenge->uuid, $phone, $code)) {
            $counted = DB::table('otp_challenges')
                ->where('id', $challenge->getKey())
                ->whereNull('consumed_at')
                ->whereColumn('attempts', '<', 'max_attempts')
                ->update(['attempts' => DB::raw('attempts + 1'), 'updated_at' => $now]);

            return [$counted === 1 ? self::INVALID : self::EXHAUSTED, null];
        }

        $consumed = DB::table('otp_challenges')
            ->where('id', $challenge->getKey())
            ->whereNull('consumed_at')
            ->where('expires_at', '>', $now)
            ->whereColumn('attempts', '<', 'max_attempts')
            ->update(['consumed_at' => $now, 'updated_at' => $now]);

        if ($consumed !== 1) {
            return [self::INVALID, null];
        }

        $user = $this->userFor($phone, $now);

        return [$user->status === 'active' ? self::VERIFIED : self::SUSPENDED, $user];
    }

    /**
     * Finds the user or creates one on first login: name NULL, no roles (contract §4.2).
     */
    private function userFor(#[\SensitiveParameter] string $phone, CarbonImmutable $now): User
    {
        $user = User::query()->where('phone', $phone)->first();

        if ($user === null) {
            $user = new User;
            $user->forceFill([
                'phone' => $phone,
                'name' => null,
                'status' => 'active',
                'phone_verified_at' => $now,
            ]);

            try {
                $user->save();

                return $user;
            } catch (UniqueConstraintViolationException $exception) {
                // A concurrent first login created the user; use that row.
                if (! str_contains($exception->getMessage(), 'users_phone_unique')) {
                    throw $exception;
                }

                // A locking read, not a plain SELECT: under MySQL REPEATABLE READ an ordinary SELECT can
                // still see this transaction's earlier snapshot (without the user), whereas a locking
                // read sees the committed row created by the competing transaction.
                $user = User::query()
                    ->where('phone', $phone)
                    ->lockForUpdate()
                    ->firstOrFail();
            }
        }

        if ($user->phone_verified_at === null) {
            $user->forceFill(['phone_verified_at' => $now])->save();
        }

        return $user;
    }
}
