<?php

namespace App\Models;

use Carbon\Carbon;
use Illuminate\Database\Eloquent\Model;

class FiscalYear extends Model
{
    protected $fillable = ['code', 'name', 'starts_on', 'ends_on', 'is_active'];

    protected function casts(): array
    {
        return [
            'starts_on' => 'date',
            'ends_on' => 'date',
            'is_active' => 'boolean',
        ];
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
