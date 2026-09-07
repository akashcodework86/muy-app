<?php

namespace App\Services\Deliverables;

use App\Models\FiscalYear;
use Carbon\Carbon;
use Illuminate\Http\Request;

class ProgramDeliverablesFilter
{
    public function __construct(
        public readonly ?int $fiscalYearId,
        public readonly ?int $districtId,
        public readonly ?int $month,
        public readonly ?int $year,
        public readonly ?string $dateFrom,
        public readonly ?string $dateTo,
        public readonly ?int $quarter = null,
        public readonly ?string $indicatorType = null,
        public readonly ?string $level = null,
        public readonly ?int $hubId = null,
    ) {}

    public static function fromRequest(Request $request): self
    {
        $month = $request->query('month');
        $year = $request->query('year');
        $quarter = $request->query('quarter');

        return new self(
            fiscalYearId: $request->query('fiscal_year_id') ? (int) $request->query('fiscal_year_id') : null,
            districtId: $request->query('district_id') ? (int) $request->query('district_id') : null,
            month: $month !== null && $month !== '' ? (int) $month : null,
            year: $year !== null && $year !== '' ? (int) $year : null,
            dateFrom: self::normalizeDate($request->query('date_from')),
            dateTo: self::normalizeDate($request->query('date_to')),
            quarter: $quarter !== null && $quarter !== '' ? (int) $quarter : null,
            indicatorType: self::normalizeIndicatorType($request->query('indicator_type')),
            level: self::normalizeLevel($request->query('level')),
            hubId: $request->query('hub_id') ? (int) $request->query('hub_id') : null,
        );
    }

    /**
     * @return array{0: ?Carbon, 1: ?Carbon}
     */
    public function resolvePeriod(?FiscalYear $fiscalYear): array
    {
        if ($this->quarter !== null && $this->quarter >= 1 && $this->quarter <= 4 && $fiscalYear) {
            $period = $fiscalYear->fiscalQuarterPeriod($this->quarter);
            if ($period !== null) {
                return $period;
            }
        }

        if ($this->month !== null && $this->month >= 1 && $this->month <= 12) {
            $year = $this->year ?? (int) ($fiscalYear?->starts_on?->year ?? now()->year);
            $from = Carbon::create($year, $this->month, 1)->startOfDay();
            $to = $from->copy()->endOfMonth()->endOfDay();

            return [$from, $to];
        }

        if ($this->dateFrom && $this->dateTo) {
            $from = Carbon::parse($this->dateFrom)->startOfDay();
            $to = Carbon::parse($this->dateTo)->endOfDay();
            if ($from->gt($to)) {
                $to = $from->copy()->endOfDay();
            }

            return [$from, $to];
        }

        if ($fiscalYear?->starts_on && $fiscalYear?->ends_on) {
            return [
                $fiscalYear->starts_on->copy()->startOfDay(),
                $fiscalYear->ends_on->copy()->endOfDay(),
            ];
        }

        return [null, null];
    }

    /** User narrowed period beyond the default full fiscal year. */
    public function hasExplicitDateFilter(): bool
    {
        return $this->quarter !== null
            || $this->month !== null
            || $this->dateFrom !== null
            || $this->dateTo !== null;
    }

    /**
     * Cumulative window from fiscal-year start through the end of the filter's
     * last calendar month (selected month, quarter end, or date-to month).
     */
    public function toCumulativeThroughPeriodEnd(?FiscalYear $fiscalYear): self
    {
        [, $periodTo] = $this->resolvePeriod($fiscalYear);

        $cumulFrom = $fiscalYear?->starts_on
            ? $fiscalYear->starts_on->copy()->startOfDay()
            : null;
        $cumulTo = $periodTo?->copy()->endOfMonth()->endOfDay();

        if ($cumulFrom === null && $periodTo !== null) {
            $cumulFrom = $periodTo->copy()->startOfMonth()->startOfDay();
        }

        if ($fiscalYear?->ends_on && $cumulTo !== null && $cumulTo->gt($fiscalYear->ends_on->copy()->endOfDay())) {
            $cumulTo = $fiscalYear->ends_on->copy()->endOfDay();
        }

        if ($cumulFrom !== null && $cumulTo !== null && $cumulFrom->gt($cumulTo)) {
            $cumulTo = $cumulFrom->copy()->endOfDay();
        }

        return new self(
            fiscalYearId: $this->fiscalYearId,
            districtId: $this->districtId,
            month: null,
            year: null,
            dateFrom: $cumulFrom?->toDateString(),
            dateTo: $cumulTo?->toDateString(),
            quarter: null,
            indicatorType: $this->indicatorType,
            level: $this->level,
            hubId: $this->hubId,
        );
    }

    /** Short label for the cumulative end month, e.g. "till May 2026". */
    public function cumulativeThroughLabel(?FiscalYear $fiscalYear): ?string
    {
        [, $periodTo] = $this->resolvePeriod($fiscalYear);
        if ($periodTo === null) {
            return null;
        }

        $end = $periodTo->copy()->endOfMonth();
        if ($fiscalYear?->ends_on && $end->gt($fiscalYear->ends_on)) {
            $end = $fiscalYear->ends_on->copy();
        }

        return 'till '.$end->format('M Y');
    }

    /**
     * @return array{dateFrom: ?string, dateTo: ?string, year: ?int}
     */
    public function formDates(?FiscalYear $fiscalYear): array
    {
        [$from, $to] = $this->resolvePeriod($fiscalYear);

        if ($from !== null && $to !== null) {
            return [
                'dateFrom' => $from->toDateString(),
                'dateTo' => $to->toDateString(),
                'year' => $this->year ?? (int) $from->year,
            ];
        }

        return [
            'dateFrom' => $this->dateFrom,
            'dateTo' => $this->dateTo,
            'year' => $this->year,
        ];
    }

    /** Fill date (and year) fields from quarter / month selection. */
    public function withDerivedDates(?FiscalYear $fiscalYear): self
    {
        if ($this->quarter === null && $this->month === null) {
            return $this;
        }

        $dates = $this->formDates($fiscalYear);
        if ($dates['dateFrom'] === null || $dates['dateTo'] === null) {
            return $this;
        }

        return new self(
            fiscalYearId: $this->fiscalYearId,
            districtId: $this->districtId,
            month: $this->month,
            year: $dates['year'],
            dateFrom: $dates['dateFrom'],
            dateTo: $dates['dateTo'],
            quarter: $this->quarter,
            indicatorType: $this->indicatorType,
            level: $this->level,
            hubId: $this->hubId,
        );
    }

    /**
     * @return array<string, mixed>
     */
    public function queryParams(): array
    {
        return array_filter([
            'fiscal_year_id' => $this->fiscalYearId,
            'hub_id' => $this->hubId,
            'district_id' => $this->districtId,
            'quarter' => $this->quarter,
            'month' => $this->month,
            'year' => $this->year,
            'date_from' => $this->dateFrom,
            'date_to' => $this->dateTo,
            'indicator_type' => $this->indicatorType,
            'level' => $this->level,
        ], fn ($v) => $v !== null && $v !== '');
    }

    /**
     * Fiscal-month weights for the current filter window.
     *
     * Each included fiscal month gets weight 1.0 (full month plan). `year_fraction`
     * is used to pro-rate FY totals where no monthly breakdown exists.
     *
     * @return array{weights: array<int, float>, year_fraction: float, has_narrowing: bool}
     */
    public function periodMonthWeights(?FiscalYear $fiscalYear): array
    {
        $hasNarrowing = $this->hasExplicitDateFilter();
        [$periodFrom, $periodTo] = $this->resolvePeriod($fiscalYear);

        if (! $hasNarrowing
            || $periodFrom === null
            || $periodTo === null
            || ! $fiscalYear?->starts_on
            || ! $fiscalYear?->ends_on
        ) {
            return ['weights' => [], 'year_fraction' => 1.0, 'has_narrowing' => false];
        }

        $fyStart = $fiscalYear->starts_on->copy()->startOfDay();
        $fyEnd = $fiscalYear->ends_on->copy()->startOfDay();
        $daysInFy = (int) $fyStart->diffInDays($fyEnd) + 1;
        $fromDate = $periodFrom->copy()->startOfDay();
        $toDate = $periodTo->copy()->startOfDay();

        if ($this->quarter !== null && $this->quarter >= 1 && $this->quarter <= 4) {
            $weights = array_fill_keys($fiscalYear->fiscalMonthNumbersForQuarter($this->quarter), 1.0);
            $overlapDays = $fromDate->lte($toDate)
                ? (int) $fromDate->diffInDays($toDate) + 1
                : 0;
            $yearFraction = $daysInFy > 0 ? min(1.0, $overlapDays / $daysInFy) : 0.0;

            return [
                'weights' => $weights,
                'year_fraction' => $yearFraction,
                'has_narrowing' => true,
            ];
        }

        if ($this->month !== null && $this->month >= 1 && $this->month <= 12) {
            $year = $this->year ?? (int) ($fiscalYear->starts_on->year ?? now()->year);
            $anchor = Carbon::create($year, $this->month, 15)->startOfDay();
            $fyMonthIdx = $fiscalYear->fiscalMonthIndex($anchor);
            if ($fyMonthIdx !== null) {
                $overlapDays = $fromDate->lte($toDate)
                    ? (int) $fromDate->diffInDays($toDate) + 1
                    : 0;
                $yearFraction = $daysInFy > 0 ? min(1.0, $overlapDays / $daysInFy) : 0.0;

                return [
                    'weights' => [$fyMonthIdx => 1.0],
                    'year_fraction' => $yearFraction,
                    'has_narrowing' => true,
                ];
            }
        }

        $weights = [];
        $totalOverlapDays = 0;
        $fyFirstCalendarMonth = $fyStart->copy()->startOfMonth();

        for ($m = 1; $m <= 12; $m++) {
            $calendarMonthStart = $fyFirstCalendarMonth->copy()->addMonths($m - 1)->startOfMonth();
            $calendarMonthEnd = $calendarMonthStart->copy()->endOfMonth()->startOfDay();
            $monthStartInFy = $calendarMonthStart->lt($fyStart) ? $fyStart->copy() : $calendarMonthStart->copy();
            $monthEndInFy = $calendarMonthEnd->gt($fyEnd) ? $fyEnd->copy() : $calendarMonthEnd->copy();

            if ($monthStartInFy->gt($monthEndInFy)) {
                continue;
            }

            $overlapStart = $monthStartInFy->gt($fromDate) ? $monthStartInFy : $fromDate;
            $overlapEnd = $monthEndInFy->lt($toDate) ? $monthEndInFy : $toDate;

            if ($overlapEnd->gte($overlapStart)) {
                $weights[$m] = 1.0;
                $totalOverlapDays += (int) $overlapStart->diffInDays($overlapEnd) + 1;
            }
        }

        $yearFraction = $daysInFy > 0 ? min(1.0, $totalOverlapDays / $daysInFy) : 0.0;

        return [
            'weights' => $weights,
            'year_fraction' => $yearFraction,
            'has_narrowing' => true,
        ];
    }

    public function hasRowMetadataFilter(): bool
    {
        return $this->indicatorType !== null || $this->level !== null;
    }

    private static function normalizeDate(mixed $value): ?string
    {
        if (! is_string($value) || trim($value) === '') {
            return null;
        }

        try {
            return Carbon::parse($value)->toDateString();
        } catch (\Throwable) {
            return null;
        }
    }

    private static function normalizeIndicatorType(mixed $value): ?string
    {
        if (! is_string($value) || trim($value) === '') {
            return null;
        }

        $value = trim($value);

        return in_array($value, ProgramDeliverableRowMetadataService::INDICATOR_TYPES, true) ? $value : null;
    }

    private static function normalizeLevel(mixed $value): ?string
    {
        if (! is_string($value) || trim($value) === '') {
            return null;
        }

        $value = trim($value);

        return in_array($value, ProgramDeliverableRowMetadataService::LEVELS, true) ? $value : null;
    }

}
