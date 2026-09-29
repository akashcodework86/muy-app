<?php

namespace App\Services;

use App\Models\Deliverable;
use App\Models\OfficialDistrictMonthlyTarget;
use App\Models\OfficialHubMonthlyTarget;
use App\Models\OfficialStateMonthlyTarget;
use App\Models\Service;
use App\Services\Deliverables\ProgramDeliverableCodeLookup;
use App\Services\Deliverables\ProgramDeliverablesMatrix;
use InvalidArgumentException;

class OfficialMonthlyTargetCodeResolver
{
    public function __construct(
        private readonly ProgramDeliverableCodeLookup $codeLookup,
        private readonly ServiceTargetDeliverableSyncService $serviceDeliverables,
    ) {}

    public function deliverableForMisSerial(string $misSerial, string $indicatorName = ''): Deliverable
    {
        $misSerial = trim($misSerial);
        if ($misSerial === '') {
            throw new InvalidArgumentException('Missing MIS serial for ['.$indicatorName.'].');
        }

        $this->codeLookup->boot();

        $resolved = null;
        $overrides = config('official_monthly_target_serial_codes', []);
        $override = is_array($overrides) ? ($overrides[$misSerial] ?? null) : null;
        if (is_string($override) && $override !== '') {
            $resolved = $this->findOrBootstrapDeliverable(strtolower($override), $indicatorName);
        }

        $leaf = ProgramDeliverablesMatrix::findLeafBySerial($misSerial);
        if ($resolved === null && $leaf !== null) {
            $source = is_array($leaf['source'] ?? null) ? $leaf['source'] : [];
            $ids = $this->codeLookup->deliverableIdsForSource($source, $indicatorName);
            if ($ids !== []) {
                $resolved = Deliverable::query()->find((int) $ids[0]);
            }

            if ($resolved === null) {
                $code = strtolower(trim((string) ($source['deliverable_code'] ?? $source['code'] ?? '')));
                if ($code !== '') {
                    $resolved = $this->findOrBootstrapDeliverable($code, $indicatorName);
                }
            }
        }

        if ($resolved === null) {
            $resolved = Deliverable::query()
                ->where('is_active', true)
                ->where(function ($q) use ($indicatorName): void {
                    $q->where('name', $indicatorName)
                        ->orWhere('mis_entry_label', $indicatorName);
                })
                ->first();
        }

        if ($resolved === null) {
            throw new InvalidArgumentException('No deliverable mapped for MIS serial '.$misSerial.' ('.$indicatorName.').');
        }

        return $this->preferServiceDeliverableHoldingTargets($resolved, $misSerial, $indicatorName, $leaf);
    }

    /**
     * GST and FSSAI plans are stored on the service-catalog deliverable (svc_*),
     * while the MIS code row (gst / fssai) can exist with no monthly targets.
     *
     * @param  array<string, mixed>|null  $leaf
     */
    private function preferServiceDeliverableHoldingTargets(
        Deliverable $resolved,
        string $misSerial,
        string $indicatorName,
        ?array $leaf,
    ): Deliverable {
        if ($this->savedOfficialTargetTotal((int) $resolved->id) > 0) {
            return $resolved;
        }

        $source = is_array($leaf['source'] ?? null) ? $leaf['source'] : [];
        $name = $indicatorName !== '' ? $indicatorName : (string) ($leaf['name'] ?? '');
        $candidateIds = $this->codeLookup->deliverableIdsForOfficialTargets($source, $misSerial, $name);
        $candidateIds[] = (int) $resolved->id;

        $holding = $this->serviceDeliverableWithLargestSavedTargets($candidateIds, (int) $resolved->id);

        return $holding ?? $resolved;
    }

    /**
     * @param  list<int>  $candidateIds
     */
    private function serviceDeliverableWithLargestSavedTargets(array $candidateIds, int $exceptId): ?Deliverable
    {
        $candidateIds = array_values(array_unique(array_filter(
            array_map('intval', $candidateIds),
            fn (int $id): bool => $id > 0 && $id !== $exceptId,
        )));
        if ($candidateIds === []) {
            return null;
        }

        $totals = [];
        foreach ([OfficialStateMonthlyTarget::class, OfficialDistrictMonthlyTarget::class, OfficialHubMonthlyTarget::class] as $model) {
            $rows = $model::query()
                ->whereIn('deliverable_id', $candidateIds)
                ->selectRaw('deliverable_id, SUM(target_count) as total')
                ->groupBy('deliverable_id')
                ->pluck('total', 'deliverable_id');
            foreach ($rows as $id => $total) {
                $totals[(int) $id] = ($totals[(int) $id] ?? 0) + (int) $total;
            }
        }

        $positiveIds = array_keys(array_filter($totals, fn (int $total): bool => $total > 0));
        if ($positiveIds === []) {
            return null;
        }

        $serviceLinked = Service::query()
            ->whereIn('deliverable_id', $positiveIds)
            ->pluck('deliverable_id')
            ->map(fn ($id): int => (int) $id)
            ->all();
        $svcCodes = Deliverable::query()
            ->whereIn('id', $positiveIds)
            ->where('code', 'like', 'svc_%')
            ->pluck('id')
            ->map(fn ($id): int => (int) $id)
            ->all();
        $eligible = array_fill_keys(array_merge($serviceLinked, $svcCodes), true);

        $bestId = null;
        $bestTotal = 0;
        foreach ($totals as $id => $total) {
            if ($total <= 0 || ! isset($eligible[$id])) {
                continue;
            }
            if ($total > $bestTotal) {
                $bestTotal = $total;
                $bestId = $id;
            }
        }

        if ($bestId === null) {
            return null;
        }

        return Deliverable::query()->find($bestId);
    }

    private function savedOfficialTargetTotal(int $deliverableId): int
    {
        if ($deliverableId <= 0) {
            return 0;
        }

        return (int) OfficialStateMonthlyTarget::query()->where('deliverable_id', $deliverableId)->sum('target_count')
            + (int) OfficialDistrictMonthlyTarget::query()->where('deliverable_id', $deliverableId)->sum('target_count')
            + (int) OfficialHubMonthlyTarget::query()->where('deliverable_id', $deliverableId)->sum('target_count');
    }

    private function findOrBootstrapDeliverable(string $code, string $indicatorName): ?Deliverable
    {
        $code = strtolower(trim($code));
        if ($code === '') {
            return null;
        }

        $existing = Deliverable::query()->where('code', $code)->first();
        if ($existing) {
            return $existing;
        }

        if (str_starts_with($code, 'svc_') || Deliverable::query()->where('code', $code)->doesntExist()) {
            $service = \App\Models\Service::query()->where('code', $code)->first();
            if ($service) {
                $deliverable = $this->serviceDeliverables->syncForService($service);

                return Deliverable::query()->find($deliverable->id);
            }
        }

        return Deliverable::query()->updateOrCreate(
            ['code' => $code],
            [
                'name' => $indicatorName !== '' ? $indicatorName : $code,
                'mis_entry_label' => $indicatorName !== '' ? $indicatorName : $code,
                'sort_order' => $this->resolveSortOrder(200, $existing),
                'is_active' => true,
            ],
        );
    }

    private function resolveSortOrder(int $desired, ?Deliverable $existing): int
    {
        $conflict = Deliverable::query()
            ->when($existing, fn ($q) => $q->where('id', '!=', $existing->id))
            ->where('sort_order', $desired)
            ->exists();

        if (! $conflict) {
            return $desired;
        }

        if ($existing !== null) {
            return (int) $existing->sort_order;
        }

        for ($i = $desired; $i <= 255; $i++) {
            if (! Deliverable::query()->where('sort_order', $i)->exists()) {
                return $i;
            }
        }

        return $desired;
    }
}
