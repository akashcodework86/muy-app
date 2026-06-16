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
            return self::normalizeToWholeMonths(
                Carbon::parse($this->dateFrom)->startOfDay(),
                Carbon::parse($this->dateTo)->endOfDay(),
            );
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
        );
    }

    /**
     * @return array<string, mixed>
     */
    public function queryParams(): array
    {
        return array_filter([
            'fiscal_year_id' => $this->fiscalYearId,
            'district_id' => $this->districtId,
            'quarter' => $this->quarter,
            'month' => $this->month,
            'year' => $this->year,
            'date_from' => $this->dateFrom,
            'date_to' => $this->dateTo,
        ], fn ($v) => $v !== null && $v !== '');
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

    /**
     * Expand a date range to whole calendar months (1st → last day of each month touched).
     *
     * @return array{0: Carbon, 1: Carbon}
     */
    private static function normalizeToWholeMonths(Carbon $from, Carbon $to): array
    {
        if ($from->gt($to)) {
            $to = $from->copy()->endOfDay();
        }

        $from = $from->copy()->startOfMonth()->startOfDay();
        $to = $to->copy()->endOfMonth()->endOfDay();

        return [$from, $to];
    }
}
