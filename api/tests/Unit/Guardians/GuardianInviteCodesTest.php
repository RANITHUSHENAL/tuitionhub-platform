<?php

namespace Tests\Unit\Guardians;

use App\Guardians\Exceptions\GuardianInviteUnavailableException;
use App\Guardians\GuardianInviteCodes;
use App\Services\GuardianInvitationService;
use App\Support\SriLankanMobile;
use InvalidArgumentException;
use PHPUnit\Framework\TestCase;
use ReflectionClass;
use ReflectionMethod;
use SensitiveParameter;

/**
 * Invitation code generation, normalisation and hashing (contract v0.7 §9.1–§9.2; D-015 Q29;
 * VP-46). Throwaway keys only.
 */
class GuardianInviteCodesTest extends TestCase
{
    private function codes(?string $key = null): GuardianInviteCodes
    {
        return new GuardianInviteCodes($key ?? 'base64:'.base64_encode(random_bytes(32)));
    }

    public function test_generated_codes_are_8_characters_from_the_unambiguous_alphabet(): void
    {
        $codes = $this->codes();
        $seen = [];

        for ($i = 0; $i < 300; $i++) {
            $code = $codes->generate();
            $this->assertMatchesRegularExpression('/^[23456789ABCDEFGHJKMNPQRSTVWXYZ]{8}$/', $code);
            $this->assertTrue($codes->isWellFormed($code));
            $seen[$code] = true;
            foreach (str_split($code) as $character) {
                $seen['char:'.$character] = true;
            }
        }

        $this->assertSame(30, strlen(GuardianInviteCodes::ALPHABET));
        foreach (['0', 'O', '1', 'I', 'L', 'U'] as $ambiguous) {
            $this->assertStringNotContainsString($ambiguous, GuardianInviteCodes::ALPHABET);
            $this->assertArrayNotHasKey('char:'.$ambiguous, $seen);
        }

        // Random, not constant: 300 draws of ~39 bits never repeat in practice.
        $this->assertCount(300, array_filter(array_keys($seen), fn (string $key) => ! str_starts_with($key, 'char:')));
    }

    public function test_codes_are_displayed_as_xxxx_dash_xxxx(): void
    {
        $this->assertSame('7KQ2-M9XD', $this->codes()->display('7KQ2M9XD'));

        $this->expectException(InvalidArgumentException::class);
        $this->codes()->display('7KQ2-M9XD');
    }

    public function test_normalisation_upper_cases_and_removes_spaces_and_hyphens(): void
    {
        $codes = $this->codes();

        foreach (['7KQ2-M9XD', '7kq2 m9xd', ' 7kq2-m9xd ', '7-K-Q-2-M-9-X-D', '7KQ2M9XD'] as $input) {
            $normalized = $codes->normalize($input);
            $this->assertSame('7KQ2M9XD', $normalized, $input);
            $this->assertTrue($codes->isWellFormed($normalized), $input);
        }
    }

    public function test_malformed_codes_are_never_well_formed(): void
    {
        $codes = $this->codes();

        foreach (['7KQ2-M9X', '0KQ2-M9XD', 'IKQ2M9XD', 'UKQ2M9XD', '7KQ2M9XDA', '7KQ2_M9XD', '7KQ2.M9XD', '', "7KQ2M9XD\n", "7KQ2\tM9XD", "\u{00C4}KQ2M9XD"] as $input) {
            $this->assertFalse($codes->isWellFormed($codes->normalize($input)), json_encode($input));
        }
    }

    public function test_the_hash_is_hmac_sha256_of_the_normalised_code_under_the_dedicated_key(): void
    {
        $key = random_bytes(32);
        $codes = $this->codes('base64:'.base64_encode($key));

        $hash = $codes->hash('7KQ2M9XD');

        $this->assertSame(hash_hmac('sha256', '7KQ2M9XD', $key), $hash);
        $this->assertSame(64, strlen($hash));
        $this->assertSame($hash, $codes->hash($codes->normalize('7kq2 m9xd')));
        $this->assertNotSame($hash, $codes->hash('7KQ2M9XE'));
        $this->assertNotSame($hash, $this->codes()->hash('7KQ2M9XD'), 'A different key gives a different hash.');
    }

    public function test_only_normalised_well_formed_codes_are_hashed(): void
    {
        $this->expectException(InvalidArgumentException::class);
        $this->codes()->hash('7kq2-m9xd');
    }

    public function test_a_missing_or_short_key_fails_closed(): void
    {
        foreach ([null, '', 'base64:', 'base64:'.base64_encode(random_bytes(31)), str_repeat('k', 31), 'base64:not*base64'] as $key) {
            $codes = new GuardianInviteCodes($key);

            try {
                $codes->hash('7KQ2M9XD');
                $this->fail('Expected the missing key to fail closed.');
            } catch (GuardianInviteUnavailableException $exception) {
                $this->assertSame('Guardian invitations are not available.', $exception->getMessage());
            }

            // Generation and formatting never need the key.
            $this->assertTrue($codes->isWellFormed($codes->generate()));
        }

        // A raw 32-byte key is accepted, as for OTP_HMAC_KEY.
        $this->assertSame(64, strlen((new GuardianInviteCodes(str_repeat('k', 32)))->hash('7KQ2M9XD')));
    }

    public function test_every_code_and_phone_parameter_is_redacted_from_stack_traces(): void
    {
        $checked = 0;

        foreach ((new ReflectionClass(GuardianInviteCodes::class))->getMethods() as $method) {
            foreach ($method->getParameters() as $parameter) {
                $this->assertNotEmpty($parameter->getAttributes(SensitiveParameter::class), "{$method->getName()}(\${$parameter->getName()})");
                $checked++;
            }
        }

        foreach ([
            [GuardianInvitationService::class, 'create', 'guardianPhone'],
            [GuardianInvitationService::class, 'accept', 'code'],
            [GuardianInvitationService::class, 'insertInvitation', 'guardianPhone'],
            [GuardianInvitationService::class, 'insertInvitation', 'codeHash'],
            [SriLankanMobile::class, 'normalize', 'phone'],
        ] as [$class, $method, $name]) {
            $parameters = array_filter(
                (new ReflectionMethod($class, $method))->getParameters(),
                fn ($parameter) => $parameter->getName() === $name,
            );
            $this->assertCount(1, $parameters, "{$class}::{$method}(\${$name}) exists");

            foreach ($parameters as $parameter) {
                $this->assertNotEmpty($parameter->getAttributes(SensitiveParameter::class), "{$class}::{$method}(\${$name})");
                $checked++;
            }
        }

        $this->assertSame(11, $checked);

        // And at runtime: the code never appears in a trace.
        try {
            $this->codes()->hash('7kq2-m9xd');
        } catch (InvalidArgumentException $exception) {
            $trace = $exception->getTraceAsString();
            $this->assertStringNotContainsString('7kq2', $trace);
            if (! ini_get('zend.exception_ignore_args')) {
                $this->assertStringContainsString('SensitiveParameterValue', $trace);
            }
        }
    }

    public function test_local_and_e164_mobile_numbers_are_normalised_to_e164(): void
    {
        $this->assertSame('+94771234567', SriLankanMobile::normalize('0771234567'));
        $this->assertSame('+94771234567', SriLankanMobile::normalize('+94771234567'));

        // Anything else is returned unchanged for validation to reject (E2-6).
        foreach (['94771234567', '0112345678', '077123456', '07712345678', '077 123 4567', '+94 77 123 4567', "0771234567\n"] as $phone) {
            $this->assertSame($phone, SriLankanMobile::normalize($phone));
            $this->assertSame(0, preg_match(SriLankanMobile::E164_PATTERN, $phone), json_encode($phone));
        }
    }
}
