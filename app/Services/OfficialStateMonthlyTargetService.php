<?php

namespace App\Services;

use App\Models\Deliverable;
use App\Models\FiscalYear;
use App\Models\OfficialStateMonthlyTarget;
use Carbon\Carbon;
use Illuminate\Support\Facades\DB;
use InvalidArgumentException;

class OfficialStateMonthlyTargetService
{
    public function __construct(
        private readonly OfficialMonthlyTargetCodeResolver $codeResolver,
        private readonly OfficialMonthlyTargetPersistenceService $persistence,
        private readonly OfficialMonthlyTargetCrossCheckService $crossCheck,
    ) {}

    /**
     * @return array<int, string>
     */
    public function fiscalMonthLabels(?FiscalYear $fiscalYear): array
    {
        if (! $fiscalYear?->starts_on) {
            return collect(range(1, 12))
                ->mapWithKeys(fn (int $m) => [$m => 'M'.$m])
                ->all();
        }

        $start = Carbon::parse($fiscalYear->starts_on)->startOfMonth();
        $out = [];
        for ($m = 1; $m <= 12; $m++) {
            $out[$m] = 'M'.$m.' '.$start->copy()->addMonths($m - 1)->format('M');
        }

        return $out;
    }

    /**
     * @return list<array<string, mixed>>
     */
    public function buildGrid(int $fiscalYearId): array
    {
        $rows = config('official_state_monthly_targets.rows', []);
        if (! is_array($rows)) {
            return [];
        }

        $deliverableIds = [];
        foreach ($rows as $row) {
            if (! is_array($row) || ($row['row_type'] ?? '') !== 'leaf') {
                continue;
            }
            try {
                $deliverable = $this->codeResolver->deliverableForMisSerial(
                    (string) ($row['serial'] ?? ''),
                    (string) ($row['name'] ?? ''),
                );
                $deliverableIds[(int) $deliverable->id] = true;
            } catch (InvalidArgumentException) {
                continue;
            }
        }

        $monthlyByDeliverable = [];
        if ($deliverableIds !== []) {
            $monthlyRows = OfficialStateMonthlyTarget::query()
                ->where('fiscal_year_id', $fiscalYearId)
                ->whereIn('deliverable_id', array_keys($deliverableIds))
                ->get(['deliverable_id', 'month_number', 'target_count']);

            foreach ($monthlyRows as $monthlyRow) {
                $monthlyByDeliverable[(int) $monthlyRow->deliverable_id][(int) $monthlyRow->month_number] = (int) $monthlyRow->target_count;
            }
        }

        $districtSplitIds = $this->crossCheck->deliverableIdsWithDistrictSplit();
        $districtAllocatedByDeliverable = $this->crossCheck->districtAllocatedTargets(
            $fiscalYearId,
            array_keys(array_intersect_key($deliverableIds, $districtSplitIds)),
        );

        $grid = [];
        foreach ($rows as $row) {
            if (! is_array($row)) {
                continue;
            }

            $rowType = (string) ($row['row_type'] ?? '');
            if ($rowType !== 'leaf') {
                $grid[] = $row;

                continue;
            }

            $configMonths = is_array($row['months'] ?? null) ? $row['months'] : [];
            $officialMonths = [];
            for ($m = 1; $m <= 12; $m++) {
                $officialMonths[$m] = (int) ($configMonths[$m - 1] ?? $configMonths[$m] ?? 0);
            }

            $deliverable = null;
            $savedMonths = array_fill(1, 12, 0);
            $mapped = true;
            $mapError = null;

            try {
                $deliverable = $this->codeResolver->deliverableForMisSerial(
                    (string) ($row['serial'] ?? ''),
                    (string) ($row['name'] ?? ''),
                );
                for ($m = 1; $m <= 12; $m++) {
                    $savedMonths[$m] = (int) ($monthlyByDeliverable[(int) $deliverable->id][$m] ?? 0);
                }
            } catch (InvalidArgumentException $e) {
                $mapped = false;
                $mapError = $e->getMessage();
            }

            $savedTotal = array_sum($savedMonths);
            $hasDistrictSplit = $deliverable && isset($districtSplitIds[(int) $deliverable->id]);
            $districtAllocatedMonths = array_fill(1, 12, 0);
            $districtAllocatedTotal = 0;
            if ($hasDistrictSplit && $deliverable) {
                $allocated = $districtAllocatedByDeliverable[(int) $deliverable->id] ?? null;
                if (is_array($allocated)) {
                    $districtAllocatedMonths = $allocated['months'] ?? $districtAllocatedMonths;
                    $districtAllocatedTotal = (int) ($allocated['total'] ?? 0);
                }
            }

            $grid[] = array_merge($row, [
                'deliverable' => $deliverable,
                'mapped' => $mapped,
                'map_error' => $mapError,
                'official_months' => $officialMonths,
                'saved_months' => $savedMonths,
                'official_total' => (int) ($row['total'] ?? array_sum($officialMonths)),
                'saved_total' => $savedTotal,
                'has_district_split' => $hasDistrictSplit,
                'district_allocated_months' => $districtAllocatedMonths,
                'district_allocated_total' => $districtAllocatedTotal,
                'verify_district' => $hasDistrictSplit
                    ? $this->crossCheck->compareTotals($districtAllocatedTotal, $savedTotal)
                    : null,
            ]);
        }

        return $grid;
    }

    /**
     * @param  array<int, array<int, int|string|null>>  $input
     * @return array{applied: int, skipped: int, errors: list<string>}
     */
    public function applyFromInput(int $fiscalYearId, array $input): array
    {
        $applied = 0;
        $skipped = 0;
        $errors = [];

        DB::transaction(function () use ($fiscalYearId, $input, &$applied, &$skipped, &$errors): void {
            foreach ($input as $deliverableId => $months) {
                $deliverableId = (int) $deliverableId;
                if ($deliverableId <= 0 || ! is_array($months)) {
                    $skipped++;

                    continue;
                }

                if (! Deliverable::query()->whereKey($deliverableId)->exists()) {
                    $skipped++;
                    $errors[] = 'Unknown deliverable #'.$deliverableId;

                    continue;
                }

                $this->persistence->saveStateGrid($fiscalYearId, $deliverableId, $months);
                $applied++;
            }
        });

        return compact('applied', 'skipped', 'errors');
    }

    /**
     * @param  list<array<string, mixed>>  $grid
     * @return array<int, int>
     */
    public function columnTotals(array $grid): array
    {
        $totals = array_fill(1, 12, 0);
        $grandOfficial = 0;
        $grandSaved = 0;

        foreach ($grid as $row) {
            if (($row['row_type'] ?? '') !== 'leaf') {
                continue;
            }

            foreach (($row['official_months'] ?? []) as $m => $val) {
                $totals[(int) $m] += (int) $val;
            }
            $grandOfficial += (int) ($row['official_total'] ?? 0);
            $grandSaved += (int) ($row['saved_total'] ?? 0);
        }

        $totals['grand_official'] = $grandOfficial;
        $totals['grand_saved'] = $grandSaved;

        return $totals;
    }
}
