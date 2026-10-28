<?php

namespace App\Otp;

use App\Models\OtpChallenge;
use App\Models\User;
use App\Otp\Exceptions\AccountSuspendedException;
use App\Otp\Exceptions\InvalidOtpException;
use App\Otp\Exceptions\OtpThrottledException;
use Carbon\CarbonImmutable;
use Illuminate\Database\UniqueConstraintViolationException;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;

/**
 * Phone OTP login (contract v0.4 §4.1–§4.2; D-019).
 *
 * - Requests never read the users table, so responses and timing are identical for known and
 *   unknown phones. Per-phone and per-IP request limits are applied by the `otp-request` limiter.
 * - Only one code per phone is valid at a time: a new request expires older pending challenges.
 * - A failed verification's attempt increment is committed before the error is raised.
 * - A correct code consumes the challenge exactly once; a suspended account is not logged in.
 *
 * Lock order: otp_challenges → users (users are never locked before they exist).
 */
class OtpService
{
    public const PURPOSE_LOGIN = 'login';

    private const VERIFIED = 'verified';

    private const INVALID = 'invalid';

    private const EXHAUSTED = 'exhausted';

    private const SUSPENDED = 'suspended';

    public function __construct(
        private readonly OtpDriver $driver,
        private readonly OtpCodeHasher $hasher,
    ) {}

    /**
     * @throws Exceptions\OtpUnavailableException driver not allowed here or HMAC key missing (503)
     * @throws OtpThrottledException resend cooldown (429)
     */
    public function request(string $phone, string $purpose, ?string $ip): OtpChallenge
    {
        // Fail closed before anything is written.
        $this->hasher->assertConfigured();
        $this->driver->assertAvailable();

        // An atomic cache lock serialises requests for one phone; the challenge row may not exist yet.
        $lock = Cache::lock('otp-request:'.hash('sha256', $purpose.'|'.$phone), 10);

        if (! $lock->get()) {
            throw new OtpThrottledException(1);
        }

        try {
            return DB::transaction(fn () => $this->issue($phone, $purpose, $ip));
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
    public function verify(string $challengeUuid, string $phone, string $code): User
    {
        $this->hasher->assertConfigured();

        // The transaction returns an outcome instead of throwing, so the attempt increment and the
        // consumption are committed before any error is raised. Throwing inside would roll them back.
        [$outcome, $user] = DB::transaction(fn () => $this->attempt($challengeUuid, $phone, $code));

        return match ($outcome) {
            self::VERIFIED => $user,
            self::SUSPENDED => throw new AccountSuspendedException,
            self::EXHAUSTED => throw new OtpThrottledException((int) config('otp.resend_cooldown_seconds')),
            default => throw new InvalidOtpException,
        };
    }

    private function issue(string $phone, string $purpose, ?string $ip): OtpChallenge
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

        // Only one code is valid at a time.
        OtpChallenge::query()
            ->where('phone', $phone)
            ->where('purpose', $purpose)
            ->whereNull('consumed_at')
            ->where('expires_at', '>', $now)
            ->update(['expires_at' => $now]);

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

        // A failing driver rolls the challenge back; there is never a fallback driver.
        $this->driver->send($phone, $code);

        return $challenge;
    }

    /**
     * @return array{0: string, 1: User|null}
     */
    private function attempt(string $challengeUuid, string $phone, string $code): array
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
    private function userFor(string $phone, CarbonImmutable $now): User
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
