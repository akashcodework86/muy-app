<?php

namespace Tests\Unit;

use App\Services\Deliverables\ProgramDeliverableRowMetadataService;
use App\Services\ProgramDeliverablesReportService;
use ReflectionMethod;
use Tests\TestCase;

class ProgramDeliverablesStateTargetLabelTest extends TestCase
{
    public function test_format_row_uses_state_label_when_level_is_state(): void
    {
        ProgramDeliverableRowMetadataService::resetCacheForTesting();

        $service = app(ProgramDeliverablesReportService::class);
        $roleProp = new \ReflectionProperty($service, 'viewerRole');
        $roleProp->setAccessible(true);
        $roleProp->setValue($service, 'district_staff');

        $method = new ReflectionMethod($service, 'formatRow');
        $method->setAccessible(true);

        $row = $method->invoke($service, [
            'row_type' => 'leaf',
            'name' => 'Social Media Post',
            'level' => 'State',
            'source' => ['type' => 'deliverable', 'code' => 'social_media'],
        ], '10.1', [
            'target' => 120,
            'achievement' => 15,
        ]);

        $this->assertSame('State', $row['level']);
        $this->assertNull($row['target']);
        $this->assertSame('State', $row['target_label']);
        $this->assertNull($row['achievement_pct']);
        $this->assertSame(15, $row['achievement']);
    }

    public function test_format_row_keeps_numeric_target_for_spoke_and_hub_level(): void
    {
        ProgramDeliverableRowMetadataService::resetCacheForTesting();

        $service = app(ProgramDeliverablesReportService::class);
        $roleProp = new \ReflectionProperty($service, 'viewerRole');
        $roleProp->setAccessible(true);
        $roleProp->setValue($service, 'district_staff');

        $method = new ReflectionMethod($service, 'formatRow');
        $method->setAccessible(true);

        $row = $method->invoke($service, [
            'row_type' => 'leaf',
            'name' => 'Call for Application',
            'level' => 'Spoke & Hub',
            'source' => ['type' => 'cfa_count', 'deliverable_code' => 'cfa'],
        ], '1.1', [
            'target' => 2240,
            'achievement' => 1502,
        ]);

        $this->assertSame('Spoke & Hub', $row['level']);
        $this->assertSame(2240, $row['target']);
        $this->assertNull($row['target_label']);
    }
}
