<?php

namespace Tests\Unit;

use App\Exceptions\DomainRuleException;
use App\Support\District;
use PHPUnit\Framework\TestCase;

/**
 * VP-59: districts are stored in their canonical spelling (docs/SPRINT_SCHEMA.md G3.1).
 */
class DistrictTest extends TestCase
{
    public function test_there_are_exactly_25_districts(): void
    {
        $this->assertCount(25, District::ALL);
        $this->assertCount(25, array_unique(District::ALL));
    }

    public function test_any_casing_and_spacing_returns_the_canonical_spelling(): void
    {
        $this->assertSame('Colombo', District::canonical('Colombo'));
        $this->assertSame('Colombo', District::canonical('colombo'));
        $this->assertSame('Nuwara Eliya', District::canonical('  NUWARA   eliya '));
    }

    public function test_moneragala_is_accepted_as_an_alias_for_monaragala(): void
    {
        $this->assertSame('Monaragala', District::canonical('Moneragala'));
        $this->assertSame('Monaragala', District::canonical('monaragala'));
    }

    public function test_an_unknown_district_is_rejected(): void
    {
        try {
            District::canonical('Atlantis');
            $this->fail('Expected an unknown district to be rejected.');
        } catch (DomainRuleException $exception) {
            $this->assertSame(DomainRuleException::UNKNOWN_DISTRICT, $exception->reason);
        }
    }
}
