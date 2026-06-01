<?php

namespace Tests\Unit;

use App\Services\LegacyPhase1\LegacyPhase1DistrictResolver;
use App\Services\LegacyPhase1\LegacyPhase1ListQuery;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

class LegacyPhase1DistrictResolverTest extends TestCase
{
    #[Test]
    public function it_maps_legacy_father_name_to_canonical_district(): void
    {
        $this->assertSame('Udham Singh Nagar', LegacyPhase1DistrictResolver::canonicalNameForLegacyFatherName('US_Nagar'));
        $this->assertSame('Tehri Garhwal', LegacyPhase1DistrictResolver::canonicalNameForLegacyFatherName('Tehri_Garhwal'));
        $this->assertSame('Pauri Garhwal', LegacyPhase1DistrictResolver::canonicalNameForLegacyFatherName('Pauri'));
        $this->assertSame('Almora', LegacyPhase1DistrictResolver::canonicalNameForLegacyFatherName('Almora'));
    }

    #[Test]
    public function it_returns_legacy_keys_for_canonical_district(): void
    {
        $this->assertSame(['us_nagar'], LegacyPhase1DistrictResolver::legacyKeysForDistrict('Udham Singh Nagar'));
        $this->assertEqualsCanonicalizing(
            ['tehri', 'tehri_garhwal'],
            LegacyPhase1DistrictResolver::legacyKeysForDistrict('Tehri Garhwal')
        );
    }

    #[Test]
    public function it_enriches_row_with_district_name(): void
    {
        $row = (object) ['father_name_legacy' => 'US_Nagar', 'legacy_region' => 'kumaon'];
        $enriched = LegacyPhase1DistrictResolver::enrichRow($row);

        $this->assertSame('Udham Singh Nagar', $enriched->district_name);
    }

    #[Test]
    public function it_detects_onboard_status_from_legacy_column(): void
    {
        $this->assertTrue(LegacyPhase1DistrictResolver::isOnboardedRaw('yes'));
        $this->assertTrue(LegacyPhase1DistrictResolver::isOnboardedRaw(' YES '));
        $this->assertFalse(LegacyPhase1DistrictResolver::isOnboardedRaw(''));
        $this->assertFalse(LegacyPhase1DistrictResolver::isOnboardedRaw(null));

        $onboarded = LegacyPhase1DistrictResolver::enrichRow((object) ['onboard_raw' => 'yes']);
        $this->assertSame('onboarded', $onboarded->onboard_status);
        $this->assertSame('Onboarded', $onboarded->onboard_label);

        $pending = LegacyPhase1DistrictResolver::enrichRow((object) ['onboard_raw' => '']);
        $this->assertSame('non_onboarded', $pending->onboard_status);
        $this->assertSame('Non onboarded', $pending->onboard_label);
    }

    #[Test]
    public function it_exposes_filter_columns_and_blank_sentinel(): void
    {
        $this->assertSame('__blank__', LegacyPhase1ListQuery::BLANK);
        $this->assertContains('application_status', LegacyPhase1ListQuery::FILTERABLE_COLUMNS);
        $this->assertContains('gender', LegacyPhase1ListQuery::filterParamNames(false));
        $this->assertContains('district', LegacyPhase1ListQuery::filterParamNames(true));
    }
}
