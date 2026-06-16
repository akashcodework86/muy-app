<?php

namespace App\Services;

use App\Models\District;
use App\Models\FiscalYear;
use App\Models\Hub;
use App\Models\OfficialDistrictMonthlyTarget;
use App\Models\OfficialHubMonthlyTarget;
use App\Models\OfficialStateMonthlyTarget;
use Illuminate\Support\Facades\DB;
use InvalidArgumentException;

class OfficialDistrictMonthlyTargetService
{
    public function __construct(
        private readonly OfficialMonthlyTargetCodeResolver $codeResolver,
        private readonly DistrictHubMonthlyTargetsService $monthlyTargets,
        private readonly OfficialMonthlyTargetPersistenceService $persistence,
    ) {}

    /**
     * @return array<int, string>
     */
    public function fiscalMonthLabels(?FiscalYear $fiscalYear): array
    {
        return $this->monthlyTargets->fiscalMonthLabels($fiscalYear);
    }

    /**
     * @return array{
     *     district_blocks: list<array<string, mixed>>,
     *     state_only_rows: list<array<string, mixed>>,
     *     districts: \Illuminate\Support\Collection<int, District>
     * }
     */
    public function buildViewData(int $fiscalYearId): array
    {
        $config = config('official_district_monthly_targets', []);
        $blocks = is_array($config['district_blocks'] ?? null) ? $config['district_blocks'] : [];
        $stateRows = is_array($config['state_only_rows'] ?? null) ? $config['state_only_rows'] : [];

        $districts = District::query()
            ->orderBy('hub_id')
            ->orderBy('sort_order')
            ->orderBy('name')
            ->get();

        $districtBySlug = $districts->keyBy(fn (District $d) => (string) $d->slug);
        $hubs = Hub::query()->orderBy('name')->get()->keyBy(fn (Hub $h) => (string) $h->slug);

        $enrichedBlocks = [];
        $blockIndex = 0;
        foreach ($blocks as $block) {
            if (! is_array($block)) {
                continue;
            }

            $misSerial = (string) ($block['mis_serial'] ?? '');
            if ($misSerial === '') {
                continue;
            }

            $name = (string) ($block['name'] ?? '');
            $mapped = true;
            $mapError = null;
            $deliverable = null;

            try {
                $deliverable = $this->codeResolver->deliverableForMisSerial($misSerial, $name);
            } catch (InvalidArgumentException $e) {
                $mapped = false;
                $mapError = $e->getMessage();
            }

            $savedDistrictMonths = [];
            $savedHubMonths = [];
            if ($deliverable) {
                $deliverableId = (int) $deliverable->id;
                foreach (OfficialDistrictMonthlyTarget::query()
                    ->where('fiscal_year_id', $fiscalYearId)
                    ->where('deliverable_id', $deliverableId)
                    ->get(['district_id', 'month_number', 'target_count']) as $savedRow) {
                    $savedDistrictMonths[(int) $savedRow->district_id][(int) $savedRow->month_number] = (int) $savedRow->target_count;
                }
                foreach (OfficialHubMonthlyTarget::query()
                    ->where('fiscal_year_id', $fiscalYearId)
                    ->where('deliverable_id', $deliverableId)
                    ->get(['hub_id', 'month_number', 'target_count']) as $savedRow) {
                    $savedHubMonths[(int) $savedRow->hub_id][(int) $savedRow->month_number] = (int) $savedRow->target_count;
                }
            }

            $districtRows = [];
            foreach ((array) ($block['districts'] ?? []) as $slug => $months) {
                $district = $districtBySlug->get($slug);
                if (! $district || ! is_array($months)) {
                    continue;
                }

                $officialMonths = [];
                for ($m = 1; $m <= 12; $m++) {
                    $officialMonths[$m] = max(0, (int) ($months[$m - 1] ?? $months[$m] ?? 0));
                }

                $savedMonths = [];
                for ($m = 1; $m <= 12; $m++) {
                    $savedMonths[$m] = (int) ($savedDistrictMonths[(int) $district->id][$m] ?? 0);
                }

                $districtRows[] = [
                    'district' => $district,
                    'official_months' => $officialMonths,
                    'official_total' => array_sum($officialMonths),
                    'saved_months' => $savedMonths,
                    'saved_total' => array_sum($savedMonths),
                ];
            }

            $hubRows = [];
            foreach ((array) ($block['hubs'] ?? []) as $slug => $months) {
                $hub = $hubs->get($slug);
                if (! $hub || ! is_array($months)) {
                    continue;
                }

                $officialMonths = [];
                for ($m = 1; $m <= 12; $m++) {
                    $officialMonths[$m] = max(0, (int) ($months[$m - 1] ?? $months[$m] ?? 0));
                }

                $savedMonths = [];
                for ($m = 1; $m <= 12; $m++) {
                    $savedMonths[$m] = (int) ($savedHubMonths[(int) $hub->id][$m] ?? 0);
                }

                $hubRows[] = [
                    'hub' => $hub,
                    'official_months' => $officialMonths,
                    'official_total' => array_sum($officialMonths),
                    'saved_months' => $savedMonths,
                    'saved_total' => array_sum($savedMonths),
                ];
            }

            $columnTotals = array_fill(1, 12, 0);
            $savedGrand = 0;
            foreach ($districtRows as $dRow) {
                foreach (($dRow['saved_months'] ?? []) as $m => $val) {
                    $columnTotals[(int) $m] += (int) $val;
                }
                $savedGrand += (int) ($dRow['saved_total'] ?? 0);
            }
            $columnTotals['grand'] = $savedGrand;

            $enrichedBlocks[] = array_merge($block, [
                'block_index' => $blockIndex++,
                'deliverable' => $deliverable,
                'mapped' => $mapped,
                'map_error' => $mapError,
                'district_rows' => $districtRows,
                'hub_rows' => $hubRows,
                'official_state_total' => (int) array_sum(array_column($districtRows, 'official_total'))
                    + (int) array_sum(array_column($hubRows, 'official_total')),
                'saved_state_total' => $savedGrand + (int) array_sum(array_column($hubRows, 'saved_total')),
                'column_totals' => $columnTotals,
            ]);
        }

        $enrichedStateRows = [];
        foreach ($stateRows as $row) {
            if (! is_array($row)) {
                continue;
            }

            $misSerial = (string) ($row['mis_serial'] ?? '');
            $name = (string) ($row['name'] ?? '');
            $months = is_array($row['months'] ?? null) ? $row['months'] : [];
            $officialMonths = [];
            for ($m = 1; $m <= 12; $m++) {
                $officialMonths[$m] = max(0, (int) ($months[$m - 1] ?? $months[$m] ?? 0));
            }

            $mapped = true;
            $mapError = null;
            $deliverable = null;

            try {
                $deliverable = $this->codeResolver->deliverableForMisSerial($misSerial, $name);
            } catch (InvalidArgumentException $e) {
                $mapped = false;
                $mapError = $e->getMessage();
            }

            $savedMonths = array_fill(1, 12, 0);
            if ($deliverable) {
                $savedRows = OfficialStateMonthlyTarget::query()
                    ->where('fiscal_year_id', $fiscalYearId)
                    ->where('deliverable_id', (int) $deliverable->id)
                    ->get(['month_number', 'target_count']);
                foreach ($savedRows as $savedRow) {
                    $savedMonths[(int) $savedRow->month_number] = (int) $savedRow->target_count;
                }
            }

            $enrichedStateRows[] = array_merge($row, [
                'deliverable' => $deliverable,
                'mapped' => $mapped,
                'map_error' => $mapError,
                'official_months' => $officialMonths,
                'official_total' => (int) ($row['total'] ?? array_sum($officialMonths)),
                'saved_months' => $savedMonths,
                'saved_total' => array_sum($savedMonths),
            ]);
        }

        return [
            'district_blocks' => $enrichedBlocks,
            'state_only_rows' => $enrichedStateRows,
            'districts' => $districts,
        ];
    }

    /**
     * @param  array{
     *     blocks?: array<int|string, array{districts?: array<int|string, array<int|string, int|string|null>>, hubs?: array<int|string, array<int|string, int|string|null>>}>,
     *     state_only?: array<int|string, array<int|string, int|string|null>>
     * }  $input
     * @return array{applied: int, skipped: int, errors: list<string>}
     */
    public function applyFromInput(int $fiscalYearId, array $input): array
    {
        $applied = 0;
        $skipped = 0;
        $errors = [];

        DB::transaction(function () use ($fiscalYearId, $input, &$applied, &$skipped, &$errors): void {
            foreach ((array) ($input['blocks'] ?? []) as $deliverableId => $blockInput) {
                if (! is_array($blockInput)) {
                    continue;
                }

                $deliverableId = (int) $deliverableId;
                if ($deliverableId <= 0) {
                    $skipped++;

                    continue;
                }

                $applied += $this->persistDistrictBlock($fiscalYearId, $deliverableId, $blockInput, $skipped);
            }

            foreach ((array) ($input['state_only'] ?? []) as $deliverableId => $months) {
                $deliverableId = (int) $deliverableId;
                if ($deliverableId <= 0 || ! is_array($months)) {
                    $skipped++;

                    continue;
                }

                $this->persistence->saveStateGrid($fiscalYearId, $deliverableId, $months);
                $applied++;
            }
        });

        return compact('applied', 'skipped', 'errors');
    }

    /**
     * @param  array{districts?: array<int|string, array<int|string, int|string|null>>, hubs?: array<int|string, array<int|string, int|string|null>>}  $blockInput
     */
    private function persistDistrictBlock(int $fiscalYearId, int $deliverableId, array $blockInput, int &$skipped): int
    {
        $districtMonths = [];
        foreach ((array) ($blockInput['districts'] ?? []) as $districtId => $months) {
            $districtId = (int) $districtId;
            if ($districtId <= 0 || ! is_array($months)) {
                continue;
            }

            $monthMap = [];
            for ($m = 1; $m <= 12; $m++) {
                $monthMap[$m] = max(0, (int) ($months[$m] ?? $months[(string) $m] ?? 0));
            }
            $districtMonths[$districtId] = $monthMap;
        }

        if ($districtMonths !== []) {
            $this->persistence->saveDistrictGrid($fiscalYearId, $deliverableId, $districtMonths);
        }

        $hubMonths = [];
        foreach ((array) ($blockInput['hubs'] ?? []) as $hubId => $months) {
            $hubId = (int) $hubId;
            if ($hubId <= 0 || ! is_array($months)) {
                continue;
            }

            $monthMap = [];
            for ($m = 1; $m <= 12; $m++) {
                $monthMap[$m] = max(0, (int) ($months[$m] ?? $months[(string) $m] ?? 0));
            }
            $hubMonths[$hubId] = $monthMap;
        }

        if ($hubMonths !== []) {
            $this->persistence->saveHubGrid($fiscalYearId, $deliverableId, $hubMonths);
        }

        if ($districtMonths === [] && $hubMonths === []) {
            $skipped++;

            return 0;
        }

        return 1;
    }

    /**
     * Persist one district block from official_district_monthly_targets config (Excel plan).
     *
     * @return array{districts: int, state_total: int, deliverable_id: int}
     */
    public function applyDistrictBlockFromOfficialConfig(int $fiscalYearId, string $misSerial, string $blockName): array
    {
        $blocks = config('official_district_monthly_targets.district_blocks', []);
        if (! is_array($blocks)) {
            throw new InvalidArgumentException('Official district monthly config is missing.');
        }

        $block = null;
        foreach ($blocks as $candidate) {
            if (! is_array($candidate)) {
                continue;
            }
            if ((string) ($candidate['mis_serial'] ?? '') !== $misSerial) {
                continue;
            }
            if (strcasecmp(trim((string) ($candidate['name'] ?? '')), trim($blockName)) !== 0) {
                continue;
            }
            $block = $candidate;
            break;
        }

        if ($block === null) {
            throw new InvalidArgumentException("No official district block for [{$misSerial}] {$blockName}.");
        }

        $deliverable = $this->codeResolver->deliverableForMisSerial($misSerial, $blockName);
        $districtBySlug = District::query()->pluck('id', 'slug');

        $districtMonths = [];
        foreach ((array) ($block['districts'] ?? []) as $slug => $months) {
            $districtId = (int) ($districtBySlug[(string) $slug] ?? 0);
            if ($districtId <= 0 || ! is_array($months)) {
                continue;
            }

            $monthMap = [];
            for ($m = 1; $m <= 12; $m++) {
                $monthMap[$m] = max(0, (int) ($months[$m - 1] ?? $months[$m] ?? 0));
            }
            $districtMonths[$districtId] = $monthMap;
        }

        if ($districtMonths === []) {
            throw new InvalidArgumentException("Official district block [{$misSerial}] has no district rows.");
        }

        $this->persistence->saveDistrictGrid($fiscalYearId, (int) $deliverable->id, $districtMonths);

        $stateTotal = 0;
        foreach ($districtMonths as $months) {
            $stateTotal += (int) array_sum($months);
        }

        return [
            'districts' => count($districtMonths),
            'state_total' => $stateTotal,
            'deliverable_id' => (int) $deliverable->id,
        ];
    }
}
