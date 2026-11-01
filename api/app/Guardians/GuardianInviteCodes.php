<?php

namespace App\Guardians;

use App\Guardians\Exceptions\GuardianInviteUnavailableException;
use InvalidArgumentException;

/**
 * Guardian invitation codes (contract §9.1–§9.2; D-015 Q29; D-023).
 *
 * A code is 8 characters from an unambiguous 30-symbol alphabet (no 0 O 1 I L U), drawn with
 * random_int, shown as XXXX-XXXX and stored only as HMAC-SHA256(normalised code) under the dedicated
 * GUARDIAN_INVITE_HMAC_KEY. The key is read lazily, so a missing key fails invitation create and
 * accept only (503), never boot or Artisan. Every parameter that can hold a code is redacted from
 * stack traces.
 */
class GuardianInviteCodes
{
    public const ALPHABET = '23456789ABCDEFGHJKMNPQRSTVWXYZ';

    public const LENGTH = 8;

    private const MIN_KEY_BYTES = 32;

    public function __construct(#[\SensitiveParameter] private readonly ?string $configuredKey) {}

    /**
     * @throws GuardianInviteUnavailableException
     */
    public function assertConfigured(): void
    {
        $this->key();
    }

    /**
     * A new normalised code (8 characters, no separator).
     */
    public function generate(): string
    {
        $code = '';
        $last = strlen(self::ALPHABET) - 1;

        for ($i = 0; $i < self::LENGTH; $i++) {
            $code .= self::ALPHABET[random_int(0, $last)];
        }

        return $code;
    }

    /**
     * XXXX-XXXX, the only form ever shown (once, in the create response).
     */
    public function display(#[\SensitiveParameter] string $normalized): string
    {
        $this->assertWellFormed($normalized);

        return substr($normalized, 0, 4).'-'.substr($normalized, 4);
    }

    /**
     * Upper-cased, with spaces and hyphens removed (§9.2). The result may still be malformed.
     */
    public function normalize(#[\SensitiveParameter] string $input): string
    {
        return strtoupper(str_replace([' ', '-'], '', $input));
    }

    public function isWellFormed(#[\SensitiveParameter] string $normalized): bool
    {
        return preg_match('/^['.self::ALPHABET.']{'.self::LENGTH.'}$/D', $normalized) === 1;
    }

    /**
     * Hex HMAC-SHA256 of a well-formed normalised code: the stored code_hash.
     *
     * @throws GuardianInviteUnavailableException
     */
    public function hash(#[\SensitiveParameter] string $normalized): string
    {
        $this->assertWellFormed($normalized);

        return hash_hmac('sha256', $normalized, $this->key());
    }

    private function assertWellFormed(#[\SensitiveParameter] string $normalized): void
    {
        if (! $this->isWellFormed($normalized)) {
            throw new InvalidArgumentException('Invitation codes must be normalised and well-formed.');
        }
    }

    private function key(): string
    {
        $key = (string) $this->configuredKey;

        if (str_starts_with($key, 'base64:')) {
            $key = (string) base64_decode(substr($key, 7), true);
        }

        if (strlen($key) < self::MIN_KEY_BYTES) {
            throw new GuardianInviteUnavailableException;
        }

        return $key;
    }
}
