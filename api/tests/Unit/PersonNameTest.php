<?php

namespace Tests\Unit;

use App\Support\PersonName;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;

/**
 * Person-name normalisation and validation (contract v0.5 §4.5; FD-1, FD-10). Fictional names only.
 */
class PersonNameTest extends TestCase
{
    /** ශ්‍රී: SHA, AL-LAKUNA (virama), ZWJ, RA, long I vowel sign — a Sinhala conjunct that needs ZWJ. */
    private const SINHALA_SHRI = "\u{0DC1}\u{0DCA}\u{200D}\u{0DBB}\u{0DD3}";

    /**
     * @return array<string, array{string}>
     */
    public static function validNames(): array
    {
        return [
            'latin' => ['Kasun Silva'],
            'initials and full stops' => ['Mr. A. B. Perera'],
            'apostrophe and hyphen' => ["D'Souza-Fernando"],
            'typographic apostrophe' => ["O\u{2019}Neil"],
            'sinhala with zwj conjunct' => [self::SINHALA_SHRI."\u{0DB8}\u{0DCF}\u{0DBD}\u{0DD2} \u{0DB4}\u{0DD9}\u{0DBB}\u{0DDA}\u{0DBB}\u{0DCF}"],
            'tamil ending in a vowel sign' => ["\u{0B95}\u{0BAE}\u{0BB2}\u{0BBE} \u{0BB0}\u{0BBE}\u{0B9C}\u{0BA9}\u{0BCD}"],
            'two characters' => ['Al'],
            'hundred sinhala letters' => [str_repeat("\u{0D85}", 100)],
        ];
    }

    #[DataProvider('validNames')]
    public function test_valid_names_are_accepted_unchanged(string $name): void
    {
        $this->assertSame($name, PersonName::normalize($name));
        $this->assertNull(PersonName::violation($name));
    }

    public function test_normalisation_trims_collapses_spaces_and_composes_nfc(): void
    {
        $this->assertSame('Kasun Silva', PersonName::normalize("  Kasun \u{00A0}\u{3000} Silva \u{00A0}"));
        // e + COMBINING ACUTE (NFD) becomes é (NFC).
        $this->assertSame("Jos\u{00E9}", PersonName::normalize("Jose\u{0301}"));
        // The ZWJ inside a Sinhala conjunct survives normalisation.
        $this->assertStringContainsString("\u{200D}", PersonName::normalize(self::SINHALA_SHRI.'මාලි'));
    }

    public function test_control_characters_are_not_turned_into_spaces(): void
    {
        $this->assertSame("Kasun\nSilva", PersonName::normalize("Kasun\nSilva"));
        $this->assertSame(PersonName::ERROR_CHARACTERS, PersonName::violation(PersonName::normalize("Kasun\nSilva")));
    }

    /**
     * @return array<string, array{string, string}>
     */
    public static function invalidNames(): array
    {
        return [
            'line feed' => ["Kasun\nSilva", PersonName::ERROR_CHARACTERS],
            'tab' => ["Kasun\tSilva", PersonName::ERROR_CHARACTERS],
            'nul' => ["Kasun\0", PersonName::ERROR_CHARACTERS],
            'right-to-left override' => ["\u{202E}Kasun", PersonName::ERROR_CHARACTERS],
            'bidi isolate' => ["Kasun\u{2066}Silva", PersonName::ERROR_CHARACTERS],
            'byte order mark' => ["Kasun\u{FEFF}Silva", PersonName::ERROR_CHARACTERS],
            'soft hyphen' => ["Kasun\u{00AD}Silva", PersonName::ERROR_CHARACTERS],
            'zero width space' => ["Kasun\u{200B}Silva", PersonName::ERROR_CHARACTERS],
            'private use' => ["Kasun\u{E000}", PersonName::ERROR_CHARACTERS],
            'digit' => ['Kasun 2', PersonName::ERROR_CHARACTERS],
            'emoji' => ["Kasun \u{1F600}", PersonName::ERROR_CHARACTERS],
            'symbol' => ['Kasun & Co', PersonName::ERROR_CHARACTERS],
            'leading zwj' => ["\u{200D}Kasun", PersonName::ERROR_CHARACTERS],
            'trailing zwj' => ["Kasun\u{200D}", PersonName::ERROR_CHARACTERS],
            'zwj after a space' => ["Kasun \u{200D}Silva", PersonName::ERROR_CHARACTERS],
            'leading hyphen' => ['-Kasun', PersonName::ERROR_CHARACTERS],
            'trailing hyphen' => ['Kasun-', PersonName::ERROR_CHARACTERS],
            'invalid utf-8' => ["Kas\xC3\x28un", PersonName::ERROR_CHARACTERS],
            'one character' => ['K', PersonName::ERROR_LENGTH],
            'empty' => ['', PersonName::ERROR_LENGTH],
            'hundred and one characters' => [str_repeat("\u{0D85}", 101), PersonName::ERROR_LENGTH],
        ];
    }

    #[DataProvider('invalidNames')]
    public function test_invalid_names_are_refused(string $name, string $reason): void
    {
        $this->assertSame($reason, PersonName::violation(PersonName::normalize($name)));
    }
}
