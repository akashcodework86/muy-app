<?php

namespace Tests\Unit;

use App\Support\AccelerationItemSchemas;
use App\Support\AccelerationServicesOptions;
use Tests\TestCase;

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

    public function test_legal_licensing_items_are_listed_under_service_detail_without_utdb(): void
    {
        $keys = array_column(
            AccelerationServicesOptions::systemCatalogRows()[AccelerationServicesOptions::SECTION_SERVICE_DETAIL],
            'key'
        );

        foreach (AccelerationServicesOptions::legalLicensingItemKeys() as $key) {
            $this->assertContains($key, $keys);
            $this->assertTrue(AccelerationServicesOptions::requiresAlwaysMedia($key));
            $schema = AccelerationItemSchemas::forKey($key);
            $this->assertNotEmpty($schema);
            $dateFields = array_values(array_filter(
                $schema,
                static fn (array $field): bool => ($field['type'] ?? '') === 'date'
            ));
            $this->assertCount(1, $dateFields, $key.' should have exactly one date field');
            $this->assertSame('Registration date', $dateFields[0]['label'] ?? null);
            $this->assertSame('service_item_date', $dateFields[0]['key'] ?? null);
        }

        $this->assertNotContains('utdb', $keys);
        $this->assertNotContains('utdb_registration', $keys);
        $this->assertContains('business_formalization', $keys);
    }
}
