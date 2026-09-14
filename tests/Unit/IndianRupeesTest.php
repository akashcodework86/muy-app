<?php

namespace Tests\Unit;

use App\Support\IndianRupees;
use PHPUnit\Framework\TestCase;

class IndianRupeesTest extends TestCase
{
    public function test_formats_inr_with_two_decimals(): void
    {
        $this->assertSame('₹ 1,250.50', IndianRupees::format('1250.50'));
    }

    public function test_spells_indian_rupees_with_paise(): void
    {
        $this->assertSame(
            '₹ One thousand two hundred and fifty and fifty paise only',
            IndianRupees::inWords('1250.50')
        );
    }
}
