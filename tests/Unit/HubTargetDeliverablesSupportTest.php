<?php

namespace Tests\Unit;

use App\Support\HubTargetDeliverablesSupport;
use Tests\TestCase;

class HubTargetDeliverablesSupportTest extends TestCase
{
    public function test_identifies_hub_target_rows_by_serial(): void
    {
        $this->assertTrue(HubTargetDeliverablesSupport::isHubTargetRow('4.2.3'));
        $this->assertTrue(HubTargetDeliverablesSupport::isHubTargetRow('1.5'));
        $this->assertFalse(HubTargetDeliverablesSupport::isHubTargetRow('4.2.4'));
        $this->assertFalse(HubTargetDeliverablesSupport::isHubTargetRow('3.3.1'));
    }

    public function test_identifies_hub_target_rows_by_deliverable_code(): void
    {
        $this->assertTrue(HubTargetDeliverablesSupport::isHubTargetRow('', [
            'type' => 'deliverable',
            'code' => 'utdb_registration',
        ]));
        $this->assertFalse(HubTargetDeliverablesSupport::isHubTargetRow('', [
            'type' => 'deliverable',
            'code' => 'gst',
        ]));
    }

    public function test_primary_hub_district_slugs_are_almora_and_pauri_garhwal(): void
    {
        $this->assertSame(['almora', 'pauri-garhwal'], HubTargetDeliverablesSupport::primaryDistrictSlugs());
        $this->assertTrue(HubTargetDeliverablesSupport::isPrimaryHubDistrictSlug('almora'));
        $this->assertTrue(HubTargetDeliverablesSupport::isPrimaryHubDistrictSlug('pauri-garhwal'));
        $this->assertFalse(HubTargetDeliverablesSupport::isPrimaryHubDistrictSlug('bageshwar'));
    }
}
