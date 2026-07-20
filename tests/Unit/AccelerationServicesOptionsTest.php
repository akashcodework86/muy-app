<?php

namespace Tests\Unit;

use App\Support\AccelerationServicesOptions;
use PHPUnit\Framework\TestCase;

class AccelerationServicesOptionsTest extends TestCase
{
    public function test_generic_and_legacy_market_repeat_keys_resolve_to_their_base_service(): void
    {
        $this->assertSame('coaching_mentorship', AccelerationServicesOptions::baseItemKey('coaching_mentorship__2'));
        $this->assertSame(2, AccelerationServicesOptions::repeatNumber('coaching_mentorship__2'));
        $this->assertSame('coaching_mentorship__3', AccelerationServicesOptions::repeatedItemKey('coaching_mentorship', 3));

        $this->assertSame('market_linkage', AccelerationServicesOptions::baseItemKey('market_linkage_2'));
        $this->assertSame(2, AccelerationServicesOptions::repeatNumber('market_linkage_2'));
        $this->assertSame('market_linkage_3', AccelerationServicesOptions::repeatedItemKey('market_linkage', 3));
    }
}
