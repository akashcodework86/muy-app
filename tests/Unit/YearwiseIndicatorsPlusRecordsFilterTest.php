<?php

namespace Tests\Unit;

use App\Services\DataCentre\YearwiseIndicatorsPlusRecordsService;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

class YearwiseIndicatorsPlusRecordsFilterTest extends TestCase
{
    #[Test]
    public function phase2_scope_limits_years_to_2025_26_and_2026_27(): void
    {
        $service = app(YearwiseIndicatorsPlusRecordsService::class);

        $this->assertSame(
            ['2025-26', '2026-27'],
            $service->yearsForFilters([
                'metric' => 'market_linkage',
                'scope' => 'phase',
                'phase' => 'phase2',
            ]),
        );
    }

    #[Test]
    public function selected_phase_filters_even_when_scope_is_left_on_grand(): void
    {
        $service = app(YearwiseIndicatorsPlusRecordsService::class);

        $this->assertSame(
            ['2025-26', '2026-27'],
            $service->yearsForFilters([
                'metric' => 'market_linkage',
                'scope' => 'grand',
                'phase' => 'phase2',
            ]),
        );
    }

    #[Test]
    public function selected_year_filters_even_when_scope_is_left_on_grand(): void
    {
        $service = app(YearwiseIndicatorsPlusRecordsService::class);

        $this->assertSame(
            ['2024-25'],
            $service->yearsForFilters([
                'metric' => 'market_linkage',
                'scope' => 'grand',
                'year' => '2024-25',
            ]),
        );
    }

    #[Test]
    public function phase1_scope_limits_years_to_2023_24(): void
    {
        $service = app(YearwiseIndicatorsPlusRecordsService::class);

        $this->assertSame(
            ['2023-24'],
            $service->yearsForFilters([
                'metric' => 'onboarding',
                'scope' => 'phase',
                'phase' => 'phase1',
            ]),
        );
    }

    #[Test]
    public function explicit_year_scope_wins_over_phase(): void
    {
        $service = app(YearwiseIndicatorsPlusRecordsService::class);

        $this->assertSame(
            ['2025-26'],
            $service->yearsForFilters([
                'metric' => 'market_linkage',
                'scope' => 'year',
                'year' => '2025-26',
                'phase' => 'phase2',
            ]),
        );
    }
}
