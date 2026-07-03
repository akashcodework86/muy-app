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

    public function test_format_row_shows_numeric_state_target_for_state_admin(): void
    {
        ProgramDeliverableRowMetadataService::resetCacheForTesting();

        $service = app(ProgramDeliverablesReportService::class);
        $roleProp = new \ReflectionProperty($service, 'viewerRole');
        $roleProp->setAccessible(true);
        $roleProp->setValue($service, 'state_admin');

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
        $this->assertSame(120, $row['target']);
        $this->assertNull($row['target_label']);
        $this->assertSame(13, $row['achievement_pct']);
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

    public function test_format_row_uses_need_based_label_when_target_is_unset(): void
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
            'name' => 'Technical Trainings to Potential Lakhpati Didis/ SHG Members/ CBOs',
            'level' => 'Spoke & Hub',
            'source' => [
                'type' => 'technical_training_potential_lakhpati_sessions',
                'deliverable_code' => 'technical_training_potential_lakhpati',
            ],
        ], '3.3.1', [
            'target' => null,
            'achievement' => 4,
        ]);

        $this->assertNull($row['target']);
        $this->assertSame('(Need Based)', $row['target_label']);
        $this->assertNull($row['achievement_pct']);
        $this->assertSame(4, $row['achievement']);
    }

    public function test_format_row_keeps_numeric_target_for_need_based_row_when_target_exists(): void
    {
        ProgramDeliverableRowMetadataService::resetCacheForTesting();

        $service = app(ProgramDeliverablesReportService::class);
        $roleProp = new \ReflectionProperty($service, 'viewerRole');
        $roleProp->setAccessible(true);
        $roleProp->setValue($service, 'state_admin');

        $method = new ReflectionMethod($service, 'formatRow');
        $method->setAccessible(true);

        $row = $method->invoke($service, [
            'row_type' => 'leaf',
            'name' => 'Advance Licensing Support (Mandi Licensing, Lab Test etc.)',
            'level' => 'Spoke & Hub',
            'source' => ['type' => 'deliverable', 'code' => 'advance_licensing_support'],
        ], '4.2.7', [
            'target' => 25,
            'achievement' => 3,
        ]);

        $this->assertSame(25, $row['target']);
        $this->assertNull($row['target_label']);
        $this->assertSame(12, $row['achievement_pct']);
    }

    public function test_format_row_uses_need_based_label_for_state_level_mentorship_when_target_unset(): void
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
            'name' => 'Mentorship Support through online portal',
            'level' => 'State',
            'source' => ['type' => 'deliverable', 'code' => 'mentorship_online_portal'],
        ], '5.2', [
            'target' => null,
            'achievement' => 0,
        ]);

        $this->assertSame('State', $row['level']);
        $this->assertNull($row['target']);
        $this->assertSame('(Need Based)', $row['target_label']);
    }

    public function test_format_row_uses_state_label_for_line_department_meeting_for_district_staff(): void
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
            'name' => 'Meeting of staff with Line Department at Spoke/Hub/State Level',
            'level' => 'State, Hub and Spokes',
            'source' => [
                'type' => 'line_department_meeting_sessions',
                'deliverable_code' => 'line_department_meeting',
            ],
        ], '12.2', [
            'target' => 50,
            'achievement' => 3,
        ]);

        $this->assertNull($row['target']);
        $this->assertSame('State', $row['target_label']);
        $this->assertNull($row['achievement_pct']);
        $this->assertSame(3, $row['achievement']);
    }
}
