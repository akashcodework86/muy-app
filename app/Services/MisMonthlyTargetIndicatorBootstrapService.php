<?php

namespace App\Services;

use App\Models\Deliverable;

/**
 * Ensures MIS rows 1.3, 1.3.1, 3.1–3.4 exist as deliverables for district/hub monthly targets.
 */
class MisMonthlyTargetIndicatorBootstrapService
{
    /**
     * @return list<array{serial: string, code: string, name: string, mis_entry_label: string, scope: string, sort_order: int}>
     */
    public function indicatorDefinitions(): array
    {
        $rows = config('program_deliverables.district_hub_monthly_indicators', []);

        return is_array($rows) ? array_values($rows) : [];
    }

    /**
     * @return list<string>
     */
    public function allowedDeliverableCodes(): array
    {
        return array_values(array_unique(array_filter(array_map(
            fn (array $row): string => strtolower(trim((string) ($row['code'] ?? ''))),
            $this->indicatorDefinitions(),
        ))));
    }

    public function ensureDeliverables(): void
    {
        foreach ($this->indicatorDefinitions() as $row) {
            $code = strtolower(trim((string) ($row['code'] ?? '')));
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

    public function scopeForCode(string $code): string
    {
        $code = strtolower(trim($code));
        foreach ($this->indicatorDefinitions() as $row) {
            if (strtolower(trim((string) ($row['code'] ?? ''))) === $code) {
                return (string) ($row['scope'] ?? DistrictHubMonthlyTargetsService::SCOPE_DISTRICT);
            }
        }

        return DistrictHubMonthlyTargetsService::SCOPE_DISTRICT;
    }

    public function isAllowedDeliverable(Deliverable $deliverable): bool
    {
        return in_array(strtolower(trim((string) $deliverable->code)), $this->allowedDeliverableCodes(), true);
    }

    /**
     * @return list<array{serial: string, code: string, name: string, scope: string}>
     */
    public function indicatorsForScope(string $scope): array
    {
        return array_values(array_filter(
            $this->indicatorDefinitions(),
            fn (array $row): bool => (string) ($row['scope'] ?? DistrictHubMonthlyTargetsService::SCOPE_DISTRICT) === $scope,
        ));
    }
}
