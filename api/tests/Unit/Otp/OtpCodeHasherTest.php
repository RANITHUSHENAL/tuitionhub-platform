<?php

namespace Tests\Unit\Otp;

use App\Otp\Exceptions\OtpUnavailableException;
use App\Otp\OtpCodeHasher;
use PHPUnit\Framework\TestCase;

/**
 * HMAC-SHA256 code hashing bound to challenge, phone and code (D-019). Throwaway keys only.
 */
class OtpCodeHasherTest extends TestCase
{
    private const UUID = '6b0e3f9a-1c2d-4e5f-8a9b-0c1d2e3f4a5b';

    private const PHONE = '+94700000501';

    public function test_the_hash_is_a_deterministic_hmac_sha256_of_uuid_phone_and_code(): void
    {
        $key = random_bytes(32);
        $hasher = new OtpCodeHasher('base64:'.base64_encode($key));

        $hash = $hasher->codeHash(self::UUID, self::PHONE, '123456');

        $this->assertSame(hash_hmac('sha256', self::UUID.'|'.self::PHONE.'|123456', $key), $hash);
        $this->assertSame($hash, $hasher->codeHash(self::UUID, self::PHONE, '123456'));
        $this->assertTrue($hasher->matches($hash, self::UUID, self::PHONE, '123456'));
    }

    public function test_the_hash_is_bound_to_the_challenge_the_phone_and_the_key(): void
    {
        $hasher = new OtpCodeHasher('base64:'.base64_encode(random_bytes(32)));
        $hash = $hasher->codeHash(self::UUID, self::PHONE, '123456');

        $this->assertFalse($hasher->matches($hash, '00000000-0000-4000-8000-000000000000', self::PHONE, '123456'));
        $this->assertFalse($hasher->matches($hash, self::UUID, '+94700000502', '123456'));
        $this->assertFalse($hasher->matches($hash, self::UUID, self::PHONE, '123457'));

        $otherKey = new OtpCodeHasher('base64:'.base64_encode(random_bytes(32)));
        $this->assertFalse($otherKey->matches($hash, self::UUID, self::PHONE, '123456'));
    }

    public function test_missing_or_short_keys_fail_closed(): void
    {
        foreach ([null, '', 'short', 'base64:'.base64_encode(random_bytes(16)), 'base64:not-valid-base64!'] as $key) {
            try {
                (new OtpCodeHasher($key))->codeHash(self::UUID, self::PHONE, '123456');
                $this->fail('Expected the hasher to refuse key '.var_export($key, true).'.');
            } catch (OtpUnavailableException) {
                $this->addToAssertionCount(1);
            }
        }
    }

    public function test_ip_hashes_are_keyed_and_never_the_raw_ip(): void
    {
        $hasher = new OtpCodeHasher('base64:'.base64_encode(random_bytes(32)));

        $this->assertNull($hasher->ipHash(null));
        $this->assertSame(64, strlen($hasher->ipHash('203.0.113.10')));
        $this->assertStringNotContainsString('203.0.113.10', $hasher->ipHash('203.0.113.10'));
    }
}
