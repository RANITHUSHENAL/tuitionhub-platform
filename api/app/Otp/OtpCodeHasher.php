<?php

namespace App\Otp;

use App\Otp\Exceptions\OtpUnavailableException;

/**
 * HMAC-SHA256 hashing for OTP challenges (D-019).
 *
 * code_hash = HMAC(OTP_HMAC_KEY, "{challenge uuid}|{phone}|{code}"): a database leak alone does not
 * allow brute-forcing the 10^6 code space, and a hash is bound to one challenge and one phone.
 * The key is read lazily, so a missing key fails only OTP operations (503), never boot or Artisan.
 */
class OtpCodeHasher
{
    private const MIN_KEY_BYTES = 32;

    public function __construct(private readonly ?string $configuredKey) {}

    public function assertConfigured(): void
    {
        $this->key();
    }

    public function codeHash(string $challengeUuid, string $phone, string $code): string
    {
        return hash_hmac('sha256', $challengeUuid.'|'.$phone.'|'.$code, $this->key());
    }

    public function matches(string $storedHash, string $challengeUuid, string $phone, string $code): bool
    {
        return hash_equals($storedHash, $this->codeHash($challengeUuid, $phone, $code));
    }

    /**
     * The raw IP is never stored; a keyed hash allows correlation without keeping the address.
     */
    public function ipHash(?string $ip): ?string
    {
        if ($ip === null || $ip === '') {
            return null;
        }

        return hash_hmac('sha256', 'ip|'.$ip, $this->key());
    }

    private function key(): string
    {
        $key = (string) $this->configuredKey;

        if (str_starts_with($key, 'base64:')) {
            $key = (string) base64_decode(substr($key, 7), true);
        }

        if (strlen($key) < self::MIN_KEY_BYTES) {
            throw new OtpUnavailableException;
        }

        return $key;
    }
}
