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
}
