<?php

namespace App\Services;

use App\Models\Deliverable;
use App\Models\Service;

/**
 * Ensures MIS rows for the state monthly target page exist as deliverables.
 */
class StateMonthlyTargetIndicatorBootstrapService
{
    public function __construct(
        private readonly ServiceTargetDeliverableSyncService $serviceDeliverables,
    ) {}

    /**
     * @return list<array{serial: string, code?: string, service_code?: string, name: string, mis_entry_label: string, sort_order: int}>
     */
    public function indicatorDefinitions(): array
    {
        $rows = config('program_deliverables.state_monthly_indicators', []);

        return is_array($rows) ? array_values($rows) : [];
    }

    /**
     * @return list<string>
     */
    public function allowedDeliverableCodes(): array
    {
        return array_values(array_unique(array_filter(array_map(
            fn (array $row): string => $this->resolveDeliverableCode($row),
            $this->indicatorDefinitions(),
        ))));
    }

    public function ensureDeliverables(): void
    {
        foreach ($this->indicatorDefinitions() as $row) {
            $code = $this->resolveDeliverableCode($row);
            if ($code === '') {
                continue;
            }

            $existing = Deliverable::query()->where('code', $code)->first();
            $desiredSort = max(1, min(255, (int) ($row['sort_order'] ?? 200)));

            Deliverable::query()->updateOrCreate(
                ['code' => $code],
                [
                    'sort_order' => $this->resolveSortOrder($desiredSort, $existing),
                    'name' => (string) ($row['name'] ?? $code),
                    'mis_entry_label' => (string) ($row['mis_entry_label'] ?? $row['name'] ?? $code),
                    'is_active' => true,
                ],
            );
        }
    }

    public function isAllowedDeliverable(Deliverable $deliverable): bool
    {
        return in_array(strtolower(trim((string) $deliverable->code)), $this->allowedDeliverableCodes(), true);
    }

    /**
     * @return array<string, array{serial: string}>
     */
    public function metadataByCode(): array
    {
        $out = [];
        foreach ($this->indicatorDefinitions() as $row) {
            $code = $this->resolveDeliverableCode($row);
            if ($code === '') {
                continue;
            }
            $out[$code] = [
                'serial' => (string) ($row['serial'] ?? ''),
            ];
        }

        return $out;
    }

    /**
     * @param  array{serial?: string, code?: string, service_code?: string, name?: string, mis_entry_label?: string, sort_order?: int}  $row
     */
    public function resolveDeliverableCode(array $row): string
    {
        $serviceCode = strtolower(trim((string) ($row['service_code'] ?? '')));
        if ($serviceCode !== '') {
            $service = Service::query()->where('code', $serviceCode)->first();
            if ($service) {
                $deliverable = $this->serviceDeliverables->syncForService($service);
                if ((int) $service->deliverable_id !== (int) $deliverable->id) {
                    $service->deliverable_id = (int) $deliverable->id;
                    $service->save();
                }

                return strtolower(trim((string) $deliverable->code));
            }

            return strtolower($this->serviceDeliverables->deliverableCodeForServiceCode($serviceCode));
        }

        return strtolower(trim((string) ($row['code'] ?? '')));
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

        for ($i = 1; $i < $desired; $i++) {
            if (! Deliverable::query()->where('sort_order', $i)->exists()) {
                return $i;
            }
        }

        return $desired;
    }
}
