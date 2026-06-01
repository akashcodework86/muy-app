<?php

namespace Tests\Unit;

use App\Services\Cfa\CfaFyOnboardingStatsService;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

class CfaFyOnboardingStatsServiceTest extends TestCase
{
    #[Test]
    public function it_returns_expected_breakdown_keys(): void
    {
        if (! \Illuminate\Support\Facades\Schema::hasTable('fiscal_years')) {
            $this->markTestSkipped('fiscal_years table not available in test DB.');
        }

        $stats = CfaFyOnboardingStatsService::breakdown();

        $this->assertArrayHasKey('fiscal_year_code', $stats);
        $this->assertArrayHasKey('total', $stats);
        $this->assertArrayHasKey('phase1', $stats);
        $this->assertArrayHasKey('phase2', $stats);
        $this->assertArrayHasKey('phase3', $stats);
        $this->assertSame(
            (int) $stats['total'],
            (int) $stats['phase1'] + (int) $stats['phase2'] + (int) $stats['phase3']
        );
    }
}
