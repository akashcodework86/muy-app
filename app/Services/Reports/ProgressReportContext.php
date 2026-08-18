<?php

namespace App\Services\Reports;

use Carbon\Carbon;

class ProgressReportContext
{
    /**
     * @param  list<array<string, mixed>>  $rows
     * @param  list<array{district: string, cfa: int, onboarding: int}>  $districtRows
     * @param  list<array<string, string>>  $photos
     * @param  array<string, list<array<string, string>>>  $photosBySection
     * @param  array<string, array<string, mixed>>  $breakdowns
     * @param  list<array{name: string, designation: string, district: string}>  $teamRoster
     */
    public function __construct(
        public readonly string $reportType,
        public readonly Carbon $periodFrom,
        public readonly Carbon $periodTo,
        public readonly string $periodLabel,
        public readonly string $reportKindLabel,
        public readonly string $filePrefix,
        public readonly Carbon $headerMonth,
        public readonly string $fiscalYearLabel,
        public readonly array $rows,
        public readonly array $districtRows,
        public readonly array $photos,
        public readonly array $photosBySection,
        public readonly array $breakdowns,
        public readonly array $teamRoster,
    ) {}

    public function isQuarterly(): bool
    {
        return $this->reportType === 'qpr';
    }

    public function periodTypeLabel(): string
    {
        return $this->isQuarterly() ? 'quarter' : 'month';
    }
}
