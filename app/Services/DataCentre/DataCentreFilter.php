<?php

namespace App\Services\DataCentre;

use App\Models\FiscalYear;
use Carbon\Carbon;
use Illuminate\Http\Request;

class DataCentreFilter
{
    public function __construct(
        public readonly ?int $districtId,
        public readonly ?int $quarter,
        public readonly ?int $fiscalMonth,
        public readonly ?string $dateFrom,
        public readonly ?string $dateTo,
    ) {}

    public static function empty(): self
    {
        return new self(null, null, null, null, null);
    }

    public static function fromRequest(Request $request): self
    {
        $quarter = $request->query('quarter');
        $fiscalMonth = $request->query('fiscal_month');

        return new self(
            districtId: $request->query('district_id') ? (int) $request->query('district_id') : null,
            quarter: $quarter !== null && $quarter !== '' ? (int) $quarter : null,
            fiscalMonth: $fiscalMonth !== null && $fiscalMonth !== '' ? (int) $fiscalMonth : null,
            dateFrom: self::normalizeDate($request->query('date_from')),
            dateTo: self::normalizeDate($request->query('date_to')),
        );
    }

    public function isActive(): bool
    {
        return $this->districtId !== null
            || $this->quarter !== null
            || $this->fiscalMonth !== null
            || $this->dateFrom !== null
            || $this->dateTo !== null;
    }

    /**
     * @return array{0: ?Carbon, 1: ?Carbon}
     */
    public function resolveDatePeriod(?FiscalYear $fiscalYear): array
    {
        if ($this->quarter !== null && $this->quarter >= 1 && $this->quarter <= 4 && $fiscalYear) {
            $period = $fiscalYear->fiscalQuarterPeriod($this->quarter);
            if ($period !== null) {
                return $this->clampToFiscalYear($period[0], $period[1], $fiscalYear);
            }
        }

        if ($this->fiscalMonth !== null && $this->fiscalMonth >= 1 && $this->fiscalMonth <= 12 && $fiscalYear?->starts_on) {
            $monthStart = Carbon::parse($fiscalYear->starts_on)->startOfMonth()->addMonths($this->fiscalMonth - 1);
            $from = $monthStart->copy()->startOfDay();
            $to = $monthStart->copy()->endOfMonth()->endOfDay();

            return $this->clampToFiscalYear($from, $to, $fiscalYear);
        }

        if ($this->dateFrom && $this->dateTo) {
            $from = Carbon::parse($this->dateFrom)->startOfDay();
            $to = Carbon::parse($this->dateTo)->endOfDay();
            if ($from->gt($to)) {
                $to = $from->copy()->endOfDay();
            }

            return $this->clampToFiscalYear($from, $to, $fiscalYear);
        }

        return [null, null];
    }

    public function hasDateFilter(): bool
    {
        return $this->quarter !== null
            || $this->fiscalMonth !== null
            || ($this->dateFrom !== null && $this->dateTo !== null);
    }

    /**
     * @return array{dateFrom: ?string, dateTo: ?string}
     */
    public function formDates(?FiscalYear $fiscalYear): array
    {
        [$from, $to] = $this->resolveDatePeriod($fiscalYear);

        if ($from !== null && $to !== null) {
            return [
                'dateFrom' => $from->toDateString(),
                'dateTo' => $to->toDateString(),
            ];
        }

        return [
            'dateFrom' => $this->dateFrom,
            'dateTo' => $this->dateTo,
        ];
    }

    /**
     * @return list<array{value: int, label: string}>
     */
    public static function fiscalMonthOptions(?FiscalYear $fiscalYear): array
    {
        if ($fiscalYear?->starts_on === null) {
            return [];
        }

        $options = [];
        for ($m = 1; $m <= 12; $m++) {
            $start = Carbon::parse($fiscalYear->starts_on)->startOfMonth()->addMonths($m - 1);
            $options[] = [
                'value' => $m,
                'label' => $start->format('M Y'),
            ];
        }

        return $options;
    }

    public function cacheKeySuffix(): string
    {
        if (! $this->isActive()) {
            return 'none';
        }

        return md5(json_encode([
            'd' => $this->districtId,
            'q' => $this->quarter,
            'm' => $this->fiscalMonth,
            'f' => $this->dateFrom,
            't' => $this->dateTo,
        ], JSON_THROW_ON_ERROR));
    }

    /**
     * @return array<string, int|string>
     */
    public function queryParams(): array
    {
        return array_filter([
            'district_id' => $this->districtId,
            'quarter' => $this->quarter,
            'fiscal_month' => $this->fiscalMonth,
            'date_from' => $this->dateFrom,
            'date_to' => $this->dateTo,
        ], fn ($v) => $v !== null && $v !== '');
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

        return [$from, $to];
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
}
