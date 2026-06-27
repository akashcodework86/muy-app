<?php

namespace Tests\Unit;

use App\Models\CfaSubmission;
use App\Services\LegacyPhase1ApplicationDetailService;
use Tests\TestCase;

class LegacyPhase1ApplicationDetailServiceTest extends TestCase
{
    public function test_try_build_returns_null_for_non_phase1_source(): void
    {
        $submission = new CfaSubmission([
            'source' => 'legacy_phase2',
            'application_no' => 'P2-001',
            'payload' => ['legacy_application_id' => 99],
        ]);

        $this->assertNull(app(LegacyPhase1ApplicationDetailService::class)->tryBuild($submission));
    }

    public function test_try_build_returns_null_when_legacy_db_not_configured(): void
    {
        config(['database.connections.legacy_phase1.database' => '']);

        $submission = new CfaSubmission([
            'source' => 'legacy_phase1',
            'application_no' => 'P1-001',
            'payload' => ['legacy_phase1_id' => 1],
        ]);

        $this->assertNull(app(LegacyPhase1ApplicationDetailService::class)->tryBuild($submission));
    }

    public function test_accepts_rbiphase1_source_alias(): void
    {
        config(['database.connections.legacy_phase1.database' => '']);

        $submission = new CfaSubmission([
            'source' => 'rbiphase1',
            'application_no' => 'P1-002',
            'payload' => ['legacy_phase1_id' => 2],
        ]);

        $this->assertNull(app(LegacyPhase1ApplicationDetailService::class)->tryBuild($submission));
    }
}
