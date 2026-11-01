<?php

namespace Tests\Support;

use App\Guardians\GuardianInviteCodes;

/**
 * Hands out a fixed sequence of codes, to force a code_hash collision in tests. Hashing and the key
 * behave exactly as in GuardianInviteCodes. Test-only.
 */
final class ScriptedInviteCodes extends GuardianInviteCodes
{
    public int $generated = 0;

    /**
     * @param  list<string>  $codes  normalised codes, returned in order
     */
    public function __construct(#[\SensitiveParameter] ?string $key, private array $codes)
    {
        parent::__construct($key);
    }

    public function generate(): string
    {
        $this->generated++;

        return array_shift($this->codes) ?? parent::generate();
    }
}
