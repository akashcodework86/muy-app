<?php

namespace Tests\Unit;

use App\Services\DataCentre\ApplicationAnalysisService;
use App\Services\DataCentre\ProgramDataCentreService;
use Tests\TestCase;
use ReflectionClass;

class ApplicationAnalysisMergeTest extends TestCase
{
    public function test_merge_sums_totals_and_recalculates_percentages(): void
    {
        $reflection = new ReflectionClass(ApplicationAnalysisService::class);
        $service = $reflection->newInstanceWithoutConstructor();
        $phase3 = $this->createStub(ProgramDataCentreService::class);
        $reflection->getProperty('phase3')->setValue($service, $phase3);

        $merged = $service->mergeAnalyses([
            [
                'total' => 100,
                'full_total' => 120,
                'entrepreneur' => [
                    ['label' => 'Women Entrepreneurs', 'count' => 80, 'pct' => 80.0],
                    ['label' => 'Seed-Stage Entrepreneurs', 'count' => 40, 'pct' => 40.0],
                    ['label' => 'Early-Stage Entrepreneurs', 'count' => 50, 'pct' => 50.0],
                    ['label' => 'Growth-Stage Entrepreneurs', 'count' => 10, 'pct' => 10.0],
                    ['label' => 'CBOs', 'count' => 5, 'pct' => 5.0],
                    ['label' => 'Generating Employment', 'count' => 20, 'pct' => 20.0],
                    ['label' => 'Employment Generated', 'count' => 30, 'pct' => null, 'is_jobs_total' => true],
                ],
                'sectors' => [
                    ['sector' => 'Agri Allied', 'count' => 60, 'pct' => 60.0],
                    ['sector' => 'Others', 'count' => 40, 'pct' => 40.0],
                ],
                'other_breakdown' => [
                    ['product' => 'Pickle', 'count' => 25],
                    ['product' => 'Not specified', 'count' => 15],
                ],
                'business_stats' => [
                    ['label' => 'No Credit History', 'count' => 70, 'pct' => 70.0],
                    ['label' => 'Businesses are unorganized', 'count' => 90, 'pct' => 90.0],
                ],
                'income_slabs' => [
                    ['label' => 'Zero income', 'count' => 40, 'pct' => 40.0],
                    ['label' => 'INR > 0 – 1 Lakh', 'count' => 60, 'pct' => 60.0],
                ],
            ],
            [
                'total' => 50,
                'full_total' => 50,
                'entrepreneur' => [
                    ['label' => 'Women Entrepreneurs', 'count' => 20, 'pct' => 40.0],
                    ['label' => 'Seed-Stage Entrepreneurs', 'count' => 20, 'pct' => 40.0],
                    ['label' => 'Early-Stage Entrepreneurs', 'count' => 20, 'pct' => 40.0],
                    ['label' => 'Growth-Stage Entrepreneurs', 'count' => 10, 'pct' => 20.0],
                    ['label' => 'CBOs', 'count' => 1, 'pct' => 2.0],
                    ['label' => 'Generating Employment', 'count' => 5, 'pct' => 10.0],
                    ['label' => 'Employment Generated', 'count' => 8, 'pct' => null, 'is_jobs_total' => true],
                ],
                'sectors' => [
                    ['sector' => 'Agri Allied', 'count' => 30, 'pct' => 60.0],
                    ['sector' => 'Homestay', 'count' => 20, 'pct' => 40.0],
                ],
                'other_breakdown' => [
                    ['product' => 'Pickle', 'count' => 10],
                ],
                'business_stats' => [
                    ['label' => 'No Credit History', 'count' => 40, 'pct' => 80.0],
                    ['label' => 'Businesses are unorganized', 'count' => 45, 'pct' => 90.0],
                ],
                'income_slabs' => [
                    ['label' => 'Zero income', 'count' => 10, 'pct' => 20.0],
                    ['label' => 'INR > 0 – 1 Lakh', 'count' => 40, 'pct' => 80.0],
                ],
            ],
        ]);

        $this->assertSame(150, $merged['total']);
        $this->assertSame(170, $merged['full_total']);
        $women = collect($merged['entrepreneur'])->firstWhere('label', 'Women Entrepreneurs');
        $this->assertSame(100, $women['count']);
        $this->assertSame(66.7, $women['pct']);
        $jobs = collect($merged['entrepreneur'])->firstWhere('label', 'Employment Generated');
        $this->assertSame(38, $jobs['count']);
        $this->assertNull($jobs['pct']);
        $agri = collect($merged['sectors'])->firstWhere('sector', 'Agri Allied');
        $this->assertSame(90, $agri['count']);
        $this->assertSame(60.0, $agri['pct']);
        $pickle = collect($merged['other_breakdown'])->firstWhere('product', 'Pickle');
        $this->assertSame(35, $pickle['count']);
        $this->assertTrue(collect($merged['accuracy_checks'])->every(fn ($c) => $c['pass']));
    }

    public function test_normalize_sector_label_collapses_mojibake_to_others(): void
    {
        $service = app(ApplicationAnalysisService::class);

        $this->assertSame('Others', $service->normalizeSectorLabel('à¤…à¤¨à¥à¤¯'));
        $this->assertSame('Agri Allied', $service->normalizeSectorLabel('à¤•à¥ƒà¤·à¤¿ à¤…à¤¨à¥à¤¬à¤‚à¤§'));
        $this->assertSame('Not specified', $service->normalizeSectorLabel(''));
    }
}
