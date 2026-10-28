<?php

namespace Tests\Unit;

use App\Support\PhoneMasker;
use InvalidArgumentException;
use PHPUnit\Framework\TestCase;

/**
 * Contract v0.3 §9.1 / §13: only masked phones may appear in responses and audit snapshots.
 */
class PhoneMaskerTest extends TestCase
{
    public function test_it_masks_a_sri_lankan_mobile_like_the_contract_example(): void
    {
        $this->assertSame('+9477*****67', PhoneMasker::mask('+94771234567'));
    }

    public function test_it_refuses_anything_that_is_not_an_e164_sri_lankan_mobile(): void
    {
        $this->expectException(InvalidArgumentException::class);

        PhoneMasker::mask('0771234567');
    }
}
