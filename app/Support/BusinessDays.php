<?php

namespace App\Support;

use Carbon\CarbonInterface;

/**
 * Adds N weekdays (Mon–Fri), skipping Saturday/Sunday.
 * Public holidays are not modelled (v1).
 */
class BusinessDays
{
    public static function add(CarbonInterface $start, int $businessDays): CarbonInterface
    {
        if ($businessDays <= 0) {
            return $start->copy();
        }

        $d = $start->copy();
        $added = 0;
        while ($added < $businessDays) {
            $d = $d->addDay();
            if (! $d->isWeekend()) {
                $added++;
            }
        }

        return $d;
    }
}
