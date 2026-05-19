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
    ) {}

    public static function fromRequest(Request $request): self
    {
        $month = $request->query('month');
        $year = $request->query('year');

        return new self(
            fiscalYearId: $request->query('fiscal_year_id') ? (int) $request->query('fiscal_year_id') : null,
            districtId: $request->query('district_id') ? (int) $request->query('district_id') : null,
            month: $month !== null && $month !== '' ? (int) $month : null,
            year: $year !== null && $year !== '' ? (int) $year : null,
            dateFrom: self::normalizeDate($request->query('date_from')),
            dateTo: self::normalizeDate($request->query('date_to')),
        );
    }

    /**
     * @return array{0: ?Carbon, 1: ?Carbon}
     */
    public function resolvePeriod(?FiscalYear $fiscalYear): array
    {
        if ($this->dateFrom && $this->dateTo) {
            $from = Carbon::parse($this->dateFrom)->startOfDay();
            $to = Carbon::parse($this->dateTo)->endOfDay();

            return $this->clampToFiscalYear($from, $to, $fiscalYear);
        }

        if ($this->month !== null && $this->month >= 1 && $this->month <= 12) {
            $year = $this->year ?? (int) ($fiscalYear?->starts_on?->year ?? now()->year);
            $from = Carbon::create($year, $this->month, 1)->startOfDay();
            $to = $from->copy()->endOfMonth()->endOfDay();

            return $this->clampToFiscalYear($from, $to, $fiscalYear);
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
        return $this->month !== null
            || $this->dateFrom !== null
            || $this->dateTo !== null;
    }

    /**
     * @return array<string, mixed>
     */
    public function queryParams(): array
    {
        return array_filter([
            'fiscal_year_id' => $this->fiscalYearId,
            'district_id' => $this->districtId,
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
     * @return array{0: Carbon, 1: Carbon}
     */
    private function clampToFiscalYear(Carbon $from, Carbon $to, ?FiscalYear $fiscalYear): array
    {
        if ($fiscalYear?->starts_on && $fiscalYear?->ends_on) {
            $fyStart = $fiscalYear->starts_on->copy()->startOfDay();
            $fyEnd = $fiscalYear->ends_on->copy()->endOfDay();
            if ($from->lt($fyStart)) {
                $from = $fyStart;
            }
            if ($to->gt($fyEnd)) {
                $to = $fyEnd;
            }
        }

        if ($from->gt($to)) {
            $to = $from->copy()->endOfDay();
        }

        return [$from, $to];
    }
}
