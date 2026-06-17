<?php

namespace App\Services;

use App\Models\OfficialDistrictMonthlyTarget;
use App\Models\OfficialHubMonthlyTarget;
use App\Models\OfficialStateMonthlyTarget;

class OfficialMonthlyTargetCrossCheckService
{
    /**
     * @return array<int, true>
     */
    public function deliverableIdsWithDistrictSplit(): array
    {
        $blocks = config('official_district_monthly_targets.district_blocks', []);
        if (! is_array($blocks)) {
            return [];
        }

        $resolver = app(OfficialMonthlyTargetCodeResolver::class);
        $ids = [];

        foreach ($blocks as $block) {
            if (! is_array($block)) {
                continue;
            }

            $misSerial = trim((string) ($block['mis_serial'] ?? ''));
            if ($misSerial === '') {
                continue;
            }

            try {
                $deliverable = $resolver->deliverableForMisSerial($misSerial, (string) ($block['name'] ?? ''));
                $ids[(int) $deliverable->id] = true;
            } catch (\InvalidArgumentException) {
                continue;
            }
        }

        return $ids;
    }

    /**
     * @param  list<int>  $deliverableIds
     * @return array<int, array{months: array<int, int>, total: int}>
     */
    public function stateSavedTargets(int $fiscalYearId, array $deliverableIds): array
    {
        $deliverableIds = array_values(array_unique(array_filter(array_map('intval', $deliverableIds))));
        if ($deliverableIds === []) {
            return [];
        }

        $out = [];
        foreach ($deliverableIds as $deliverableId) {
            $out[$deliverableId] = [
                'months' => array_fill(1, 12, 0),
                'total' => 0,
            ];
        }

        $rows = OfficialStateMonthlyTarget::query()
            ->where('fiscal_year_id', $fiscalYearId)
            ->whereIn('deliverable_id', $deliverableIds)
            ->get(['deliverable_id', 'month_number', 'target_count']);

        foreach ($rows as $row) {
            $deliverableId = (int) $row->deliverable_id;
            $month = (int) $row->month_number;
            if ($month < 1 || $month > 12) {
                continue;
            }

            $count = max(0, (int) $row->target_count);
            $out[$deliverableId]['months'][$month] = $count;
            $out[$deliverableId]['total'] += $count;
        }

        return $out;
    }

    /**
     * @param  list<int>  $deliverableIds
     * @return array<int, array{months: array<int, int>, total: int}>
     */
    public function districtAllocatedTargets(int $fiscalYearId, array $deliverableIds): array
    {
        $deliverableIds = array_values(array_unique(array_filter(array_map('intval', $deliverableIds))));
        if ($deliverableIds === []) {
            return [];
        }

        $out = [];
        foreach ($deliverableIds as $deliverableId) {
            $out[$deliverableId] = [
                'months' => array_fill(1, 12, 0),
                'total' => 0,
            ];
        }

        foreach (OfficialDistrictMonthlyTarget::query()
            ->where('fiscal_year_id', $fiscalYearId)
            ->whereIn('deliverable_id', $deliverableIds)
            ->get(['deliverable_id', 'month_number', 'target_count']) as $row) {
            $deliverableId = (int) $row->deliverable_id;
            $month = (int) $row->month_number;
            if ($month < 1 || $month > 12) {
                continue;
            }

            $count = max(0, (int) $row->target_count);
            $out[$deliverableId]['months'][$month] += $count;
            $out[$deliverableId]['total'] += $count;
        }

        foreach (OfficialHubMonthlyTarget::query()
            ->where('fiscal_year_id', $fiscalYearId)
            ->whereIn('deliverable_id', $deliverableIds)
            ->get(['deliverable_id', 'month_number', 'target_count']) as $row) {
            $deliverableId = (int) $row->deliverable_id;
            $month = (int) $row->month_number;
            if ($month < 1 || $month > 12) {
                continue;
            }

            $count = max(0, (int) $row->target_count);
            $out[$deliverableId]['months'][$month] += $count;
            $out[$deliverableId]['total'] += $count;
        }

        return $out;
    }

    /**
     * @return array{status: string, label: string, delta: int, color: string, bg: string}
     */
    public function compareTotals(int $allocated, int $stateSaved): array
    {
        if ($stateSaved <= 0 && $allocated <= 0) {
            return [
                'status' => 'empty',
                'label' => 'No targets set',
                'delta' => 0,
                'color' => '#64748b',
                'bg' => '#f1f5f9',
            ];
        }

        if ($stateSaved <= 0) {
            return [
                'status' => 'no_state',
                'label' => 'State target not set',
                'delta' => $allocated,
                'color' => '#b45309',
                'bg' => '#fffbeb',
            ];
        }

        if ($allocated === $stateSaved) {
            return [
                'status' => 'match',
                'label' => 'Match',
                'delta' => 0,
                'color' => '#047857',
                'bg' => '#ecfdf5',
            ];
        }

        $delta = $allocated - $stateSaved;

        if ($allocated > $stateSaved) {
            return [
                'status' => 'over',
                'label' => 'Over by '.number_format(abs($delta)),
                'delta' => $delta,
                'color' => '#b91c1c',
                'bg' => '#fef2f2',
            ];
        }

        return [
            'status' => 'under',
            'label' => 'Under by '.number_format(abs($delta)),
            'delta' => $delta,
            'color' => '#b45309',
            'bg' => '#fffbeb',
        ];
    }
}
