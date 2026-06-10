<?php

namespace Database\Seeders;

use App\Models\FiscalYear;
use App\Services\DistrictHubMonthlyTargetPresetService;
use App\Services\MisMonthlyTargetIndicatorBootstrapService;
use Illuminate\Database\Seeder;

/**
 * Monthly district targets for MIS 1.3 — Awareness cum Outreach activities (field_work_workshops).
 */
class FieldWorkWorkshopsMonthlyTargetSeeder extends Seeder
{
    public function run(): void
    {
        app(MisMonthlyTargetIndicatorBootstrapService::class)->ensureDeliverables();

        $fiscalYear = FiscalYear::query()
            ->where('code', '2026-27')
            ->first()
            ?? FiscalYear::query()->where('is_active', true)->orderByDesc('starts_on')->first()
            ?? FiscalYear::query()->orderByDesc('starts_on')->first();

        if (! $fiscalYear) {
            $this->command?->warn('No fiscal year found.');

            return;
        }

        $result = app(DistrictHubMonthlyTargetPresetService::class)->applyDistrictPreset(
            (int) $fiscalYear->id,
            'field_work_workshops',
        );

        $this->command?->info(sprintf(
            'Loaded field_work_workshops monthly targets for FY %s (%d districts, state total %d).',
            $fiscalYear->code,
            $result['districts'],
            $result['state_total'],
        ));
    }
}
