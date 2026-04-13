<?php

namespace App\Services;

use App\Models\PublicHoliday;
use Carbon\Carbon;

class WorkingCalendarService
{
    /**
     * Second Saturday of the month: day 8–14 that falls on a Saturday.
     */
    public function isSecondSaturday(Carbon $date): bool
    {
        if (! $date->isSaturday()) {
            return false;
        }

        $d = $date->day;

        return $d >= 8 && $d <= 14;
    }

    public function isConfiguredHoliday(Carbon $date): bool
    {
        return PublicHoliday::query()
            ->whereDate('holiday_date', $date->toDateString())
            ->exists();
    }

    public function isWorkingDay(Carbon $date): bool
    {
        if ($date->isSunday()) {
            return false;
        }

        if ($this->isSecondSaturday($date)) {
            return false;
        }

        if ($this->isConfiguredHoliday($date)) {
            return false;
        }

        return true;
    }

    public function isOnOrAfterNineAm(Carbon $moment): bool
    {
        $tz = config('app.timezone', 'Asia/Kolkata');
        $local = $moment->copy()->timezone($tz);
        $cutoff = $local->copy()->startOfDay()->setTime(9, 0, 0);

        return $local->gte($cutoff);
    }

    /**
     * @return array{now: Carbon, today_start: Carbon, is_working_day: bool, is_after_cutoff: bool, seconds_until_cutoff: int}
     */
    public function todayContext(): array
    {
        $tz = config('app.timezone', 'Asia/Kolkata');
        $now = Carbon::now($tz);
        $todayStart = $now->copy()->startOfDay();
        $isWorking = $this->isWorkingDay($now->copy());
        $afterNine = $this->isOnOrAfterNineAm($now);
        $nineAm = $now->copy()->startOfDay()->setTime(9, 0, 0);
        $secondsUntil = 0;
        if ($now->lt($nineAm)) {
            $secondsUntil = max(0, (int) ceil($nineAm->getTimestamp() - $now->getTimestamp()));
        }

        return [
            'now' => $now,
            'today_start' => $todayStart,
            'is_working_day' => $isWorking,
            'is_after_cutoff' => $afterNine,
            'seconds_until_cutoff' => $secondsUntil,
        ];
    }
}
