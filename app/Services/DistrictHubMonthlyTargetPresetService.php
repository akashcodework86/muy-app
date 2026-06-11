<?php

namespace App\Services;

use App\Models\Deliverable;
use App\Models\District;
use App\Models\DistrictDeliverableTarget;
use App\Models\StateDeliverableTarget;
use InvalidArgumentException;

class DistrictHubMonthlyTargetPresetService
{
    public function __construct(
        private readonly DistrictHubMonthlyTargetsService $monthlyTargets,
        private readonly MisMonthlyTargetIndicatorBootstrapService $indicatorBootstrap,
    ) {}

    /**
     * @return list<array{code: string, serial: string, label: string}>
     */
    public function districtPresets(): array
    {
        $presets = config('district_hub_monthly_target_presets', []);
        if (! is_array($presets)) {
            return [];
        }

        $out = [];
        foreach ($presets as $code => $row) {
            if (! is_array($row) || ! isset($row['districts']) || ! is_array($row['districts'])) {
                continue;
            }

            $out[] = [
                'code' => (string) $code,
                'serial' => (string) ($row['serial'] ?? ''),
                'label' => (string) ($row['label'] ?? $code),
            ];
        }

        return $out;
    }

    public function hasDistrictPreset(string $deliverableCode): bool
    {
        $code = strtolower(trim($deliverableCode));
        $presets = config('district_hub_monthly_target_presets', []);

        return is_array($presets)
            && isset($presets[$code]['districts'])
            && is_array($presets[$code]['districts']);
    }

    /**
     * @return array{code: string, districts: int, state_total: int}
     */
    public function applyDistrictPreset(int $fiscalYearId, string $deliverableCode): array
    {
        $this->indicatorBootstrap->ensureDeliverables();

        $code = strtolower(trim($deliverableCode));
        $preset = config("district_hub_monthly_target_presets.{$code}");
        if (! is_array($preset) || ! is_array($preset['districts'] ?? null)) {
            throw new InvalidArgumentException("No district preset for deliverable [{$code}].");
        }

        if (! in_array($code, $this->indicatorBootstrap->allowedDeliverableCodes(), true)) {
            throw new InvalidArgumentException("Deliverable [{$code}] is not a configured monthly target indicator.");
        }

        $deliverable = Deliverable::query()->where('code', $code)->first();
        if (! $deliverable) {
            throw new InvalidArgumentException("Deliverable [{$code}] not found.");
        }

        if ($this->monthlyTargets->resolveScopeForDeliverable($deliverable) !== DistrictHubMonthlyTargetsService::SCOPE_DISTRICT) {
            throw new InvalidArgumentException("Preset applies only to Hub & Spoke (district) indicators.");
        }

        /** @var array<string, list<int>> $monthlyBySlug */
        $monthlyBySlug = $preset['districts'];
        $districts = District::query()
            ->whereIn('slug', array_keys($monthlyBySlug))
            ->get()
            ->keyBy('slug');

        $districtMonths = [];
        $stateTotal = 0;
        $loaded = 0;

        foreach ($monthlyBySlug as $slug => $months) {
            $district = $districts->get($slug);
            if (! $district || ! is_array($months) || count($months) !== 12) {
                continue;
            }

            $rowTotal = (int) array_sum($months);
            $stateTotal += $rowTotal;
            $loaded++;

            DistrictDeliverableTarget::query()->updateOrCreate(
                [
                    'fiscal_year_id' => $fiscalYearId,
                    'district_id' => $district->id,
                    'deliverable_id' => $deliverable->id,
                ],
                ['target_total' => $rowTotal],
            );

            $monthMap = [];
            foreach ($months as $index => $count) {
                $monthMap[$index + 1] = max(0, (int) $count);
            }
            $districtMonths[(int) $district->id] = $monthMap;
        }

        if ($districtMonths === []) {
            throw new InvalidArgumentException('No districts matched the preset allocation.');
        }

        StateDeliverableTarget::query()->updateOrCreate(
            [
                'fiscal_year_id' => $fiscalYearId,
                'deliverable_id' => $deliverable->id,
            ],
            ['target_total' => $stateTotal],
        );

        $this->monthlyTargets->saveDistrictGrid(
            $fiscalYearId,
            (int) $deliverable->id,
            $districtMonths,
        );

        return [
            'code' => $code,
            'districts' => $loaded,
            'state_total' => $stateTotal,
        ];
    }
}
