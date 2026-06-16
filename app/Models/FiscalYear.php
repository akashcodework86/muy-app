<?php

namespace App\Models;

use Carbon\Carbon;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Collection;

class FiscalYear extends Model
{
    /**
     * FY rows shown in state / district / staff monthly target dropdowns.
     * Legacy 2024-25 stays in DB for imports; UI uses 2025-26 for migrated Phase 2 data.
     */
    public const UI_SELECTABLE_CODES = ['2025-26', '2026-27'];

    protected $fillable = ['code', 'name', 'starts_on', 'ends_on', 'is_active'];

    protected function casts(): array
    {
        return [
            'starts_on' => 'date',
            'ends_on' => 'date',
            'is_active' => 'boolean',
        ];
    }

    /** @param  Builder<self>  $query */
    public function scopeForUiSelection(Builder $query): Builder
    {
        return $query->whereIn('code', self::UI_SELECTABLE_CODES);
    }

    /**
     * Ordered FY rows for admin/staff target forms (dropdown only).
     *
     * @return Collection<int, self>
     */
    public static function forUiDropdown(): Collection
    {
        return static::query()->forUiSelection()->orderByDesc('starts_on')->get();
    }

    /**
     * Resolve FY id from query string, falling back to active FY if it is UI-visible, else newest UI FY.
     *
     * @return array{0: int, 1: Collection<int, self>}
     */
    public static function resolveIdForUi(?int $requestedFiscalYearId): array
    {
        $fiscalYears = static::forUiDropdown();
        $ids = $fiscalYears->pluck('id')->map(fn ($id) => (int) $id)->all();

        if ($requestedFiscalYearId !== null && $requestedFiscalYearId !== 0 && in_array($requestedFiscalYearId, $ids, true)) {
            return [$requestedFiscalYearId, $fiscalYears];
        }

        $activeId = static::query()->where('is_active', true)->value('id');
        if ($activeId !== null && in_array((int) $activeId, $ids, true)) {
            return [(int) $activeId, $fiscalYears];
        }

        return [(int) ($fiscalYears->first()?->id ?? 0), $fiscalYears];
    }

    public static function phase3Default(): ?self
    {
        return static::query()
            ->where('code', '2026-27')
            ->first()
            ?? static::query()->where('name', 'like', '%2026-27%')->first()
            ?? static::query()->where('is_active', true)->orderByDesc('starts_on')->first()
            ?? static::query()->forUiSelection()->orderByDesc('starts_on')->first();
    }

    /**
     * Map an event datetime into M1..M12 using the same calendar-month ladder as month labels
     * (first month = calendar month containing starts_on), but only if the event falls inside
     * [starts_on, ends_on] inclusive. Dates in the 13th calendar month that are still within
     * ends_on map to M12 (boundary fix for FY rows that end on e.g. 1 Apr).
     */
    public function fiscalMonthIndex(Carbon $at): ?int
    {
        $rangeStart = Carbon::parse($this->starts_on)->startOfDay();
        $rangeEnd = Carbon::parse($this->ends_on)->endOfDay();
        if ($at->lt($rangeStart) || $at->gt($rangeEnd)) {
            return null;
        }

        $fyFirstMonth = Carbon::parse($this->starts_on)->startOfMonth();
        $eventMonth = $at->copy()->startOfMonth();
        $months = (int) $fyFirstMonth->diffInMonths($eventMonth);
        $idx = $months + 1;

        if ($idx < 1) {
            return null;
        }

        return min(12, $idx);
    }

    /**
     * Fiscal quarter 1–4 → M# indexes included (Q1 = M1–M3, …, Q4 = M10–M12).
     *
     * @return list<int>
     */
    public function fiscalMonthNumbersForQuarter(int $quarter): array
    {
        if ($quarter < 1 || $quarter > 4) {
            return [];
        }

        $start = ($quarter - 1) * 3 + 1;

        return [$start, $start + 1, $start + 2];
    }

    /**
     * Calendar period for a fiscal quarter: 1st day of first month through last day of last month.
     *
     * @return array{0: Carbon, 1: Carbon}|null
     */
    public function fiscalQuarterPeriod(int $quarter): ?array
    {
        if ($quarter < 1 || $quarter > 4 || ! $this->starts_on) {
            return null;
        }

        $firstMonth = Carbon::parse($this->starts_on)->startOfMonth()->addMonths(($quarter - 1) * 3);
        $lastMonth = $firstMonth->copy()->addMonths(2);

        return [
            $firstMonth->copy()->startOfMonth()->startOfDay(),
            $lastMonth->copy()->endOfMonth()->endOfDay(),
        ];
    }

    /** Short label for filter UI, e.g. "Apr–Jun 2026". */
    public function fiscalQuarterLabel(int $quarter): string
    {
        $period = $this->fiscalQuarterPeriod($quarter);
        if ($period === null) {
            return 'Q'.$quarter;
        }

        [$from, $to] = $period;
        if ($from->year === $to->year) {
            return $from->format('M').'–'.$to->format('M Y');
        }

        return $from->format('M Y').'–'.$to->format('M Y');
    }

    /**
     * Quarter date ranges for deliverables filter UI (JS).
     *
     * @return array<int, array{from: string, to: string, label: string}|null>
     */
    public function fiscalQuarterPeriodsForJs(): array
    {
        $out = [];
        for ($q = 1; $q <= 4; $q++) {
            $period = $this->fiscalQuarterPeriod($q);
            if ($period === null) {
                $out[$q] = null;

                continue;
            }

            [$from, $to] = $period;
            $out[$q] = [
                'from' => $from->toDateString(),
                'to' => $to->toDateString(),
                'label' => $from->format('d M Y').' – '.$to->format('d M Y'),
            ];
        }

        return $out;
    }
}
