<?php

namespace App\Services\Reports;

use App\Models\District;
use App\Models\FiscalYear;
use App\Models\User;
use App\Services\Deliverables\ProgramDeliverablesAchievementBreakdownService;
use App\Services\Deliverables\ProgramDeliverablesFilter;
use App\Services\Deliverables\ProgramDeliverablesScope;
use App\Services\MediaGalleryService;
use App\Services\ProgramDeliverablesReportService;
use Carbon\Carbon;
use Illuminate\Contracts\Auth\Authenticatable;
use Throwable;

class ProgressReportDataService
{
    public function __construct(
        private readonly ProgramDeliverablesReportService $reportService,
        private readonly ProgramDeliverablesAchievementBreakdownService $breakdownService,
        private readonly MediaGalleryService $mediaGallery,
    ) {}

    public function buildMonthly(Authenticatable $user, Carbon $month): ProgressReportContext
    {
        $month = $month->copy()->startOfMonth();
        $fiscalYear = $this->resolveFiscalYear($month);

        $filter = new ProgramDeliverablesFilter(
            fiscalYearId: (int) $fiscalYear->id,
            districtId: null,
            month: (int) $month->month,
            year: (int) $month->year,
            dateFrom: $month->toDateString(),
            dateTo: $month->copy()->endOfMonth()->toDateString(),
        );

        return $this->assemble(
            reportType: 'mpr',
            periodFrom: $month->copy()->startOfDay(),
            periodTo: $month->copy()->endOfMonth()->endOfDay(),
            periodLabel: $month->format('F Y'),
            reportKindLabel: 'Monthly Progress Report',
            filePrefix: 'MUY-MPR',
            headerMonth: $month,
            fiscalYearLabel: (string) ($fiscalYear->name ?? $fiscalYear->code),
            filter: $filter,
            user: $user,
        );
    }

    public function buildQuarterly(Authenticatable $user, FiscalYear $fiscalYear, int $quarter): ProgressReportContext
    {
        abort_unless($quarter >= 1 && $quarter <= 4, 422, 'Quarter must be between 1 and 4.');

        $period = $fiscalYear->fiscalQuarterPeriod($quarter);
        abort_if($period === null, 422, 'Could not resolve the selected fiscal quarter.');

        [$periodFrom, $periodTo] = $period;

        $filter = new ProgramDeliverablesFilter(
            fiscalYearId: (int) $fiscalYear->id,
            districtId: null,
            month: null,
            year: null,
            dateFrom: $periodFrom->toDateString(),
            dateTo: $periodTo->toDateString(),
            quarter: $quarter,
        );

        return $this->assemble(
            reportType: 'qpr',
            periodFrom: $periodFrom->copy()->startOfDay(),
            periodTo: $periodTo->copy()->endOfDay(),
            periodLabel: $fiscalYear->fiscalQuarterLabel($quarter),
            reportKindLabel: 'Quarterly Progress Report',
            filePrefix: 'MUY-QPR',
            headerMonth: $periodTo->copy()->startOfMonth(),
            fiscalYearLabel: (string) ($fiscalYear->name ?? $fiscalYear->code),
            filter: $filter,
            user: $user,
        );
    }

    private function assemble(
        string $reportType,
        Carbon $periodFrom,
        Carbon $periodTo,
        string $periodLabel,
        string $reportKindLabel,
        string $filePrefix,
        Carbon $headerMonth,
        string $fiscalYearLabel,
        ProgramDeliverablesFilter $filter,
        Authenticatable $user,
    ): ProgressReportContext {
        $scope = ProgramDeliverablesScope::forUser($user);
        $report = $this->reportService->build($filter, $scope);

        return new ProgressReportContext(
            reportType: $reportType,
            periodFrom: $periodFrom,
            periodTo: $periodTo,
            periodLabel: $periodLabel,
            reportKindLabel: $reportKindLabel,
            filePrefix: $filePrefix,
            headerMonth: $headerMonth,
            fiscalYearLabel: $fiscalYearLabel,
            rows: $report['rows'],
            districtRows: $this->districtRows($filter, $scope),
            photos: $this->mediaGallery->monthlyReportHighlights($periodFrom, $periodTo, 12),
            photosBySection: $this->mediaGallery->monthlyReportPhotosBySection($periodFrom, $periodTo, 4),
            breakdowns: $this->breakdowns($filter, $scope),
            teamRoster: $this->teamRoster(),
        );
    }

    private function resolveFiscalYear(Carbon $month): FiscalYear
    {
        return FiscalYear::query()
            ->whereDate('starts_on', '<=', $month->copy()->endOfMonth()->toDateString())
            ->whereDate('ends_on', '>=', $month->toDateString())
            ->orderByDesc('starts_on')
            ->first()
            ?? FiscalYear::phase3Default()
            ?? abort(422, 'No fiscal year is configured for the selected month.');
    }

    /**
     * @return list<array{district: string, cfa: int, onboarding: int}>
     */
    private function districtRows(ProgramDeliverablesFilter $filter, ProgramDeliverablesScope $scope): array
    {
        $counts = [];
        foreach (District::query()->orderBy('name')->pluck('name')->all() as $district) {
            $counts[(string) $district] = ['district' => (string) $district, 'cfa' => 0, 'onboarding' => 0];
        }

        foreach (['1.1' => 'cfa', '2.1' => 'onboarding'] as $serial => $key) {
            try {
                $breakdown = $this->breakdownService->build($filter, $scope, $serial);
            } catch (Throwable) {
                continue;
            }

            foreach ((array) ($breakdown['by_district'] ?? []) as $row) {
                $district = trim((string) ($row['district'] ?? 'Unknown')) ?: 'Unknown';
                $counts[$district] ??= ['district' => $district, 'cfa' => 0, 'onboarding' => 0];
                $counts[$district][$key] = (int) ($row['count'] ?? 0);
            }
        }

        return array_values($counts);
    }

    /**
     * @return array<string, array<string, mixed>>
     */
    private function breakdowns(ProgramDeliverablesFilter $filter, ProgramDeliverablesScope $scope): array
    {
        $breakdowns = [];
        foreach (ProgressReportSectionCatalog::breakdownSerials() as $serial) {
            try {
                $breakdowns[$serial] = $this->breakdownService->build($filter, $scope, $serial);
            } catch (Throwable) {
                $breakdowns[$serial] = ['total' => 0, 'by_district' => [], 'records' => []];
            }
        }

        return $breakdowns;
    }

    /**
     * @return list<array{name: string, designation: string, district: string}>
     */
    private function teamRoster(): array
    {
        return User::query()
            ->with(['district:id,name', 'designationRecord:id,name', 'spocDistrictAssignments.district:id,name'])
            ->where('is_active', true)
            ->whereIn('role', ['state_admin', 'state_staff', 'hub_admin', 'district_staff'])
            ->orderBy('name')
            ->get()
            ->map(function (User $user): array {
                $district = (string) ($user->district?->name ?? '');
                if ($district === '' && $user->spocDistrictAssignments->isNotEmpty()) {
                    $district = $user->spocDistrictAssignments
                        ->map(fn ($a) => (string) ($a->district?->name ?? ''))
                        ->filter()
                        ->unique()
                        ->implode(', ');
                }

                return [
                    'name' => (string) $user->name,
                    'designation' => (string) ($user->designationRecord?->name ?? ucfirst(str_replace('_', ' ', (string) $user->role))),
                    'district' => $district !== '' ? $district : 'State',
                ];
            })
            ->values()
            ->all();
    }
}
