<?php

namespace App\Services;

use App\Models\Deliverable;
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

        $overrides = config('official_monthly_target_serial_codes', []);
        $override = is_array($overrides) ? ($overrides[$misSerial] ?? null) : null;
        if (is_string($override) && $override !== '') {
            $deliverable = $this->findOrBootstrapDeliverable(strtolower($override), $indicatorName);

            if ($deliverable) {
                return $deliverable;
            }
        }

        $leaf = ProgramDeliverablesMatrix::findLeafBySerial($misSerial);
        if ($leaf !== null) {
            $source = is_array($leaf['source'] ?? null) ? $leaf['source'] : [];
            $ids = $this->codeLookup->deliverableIdsForSource($source, $indicatorName);
            if ($ids !== []) {
                $deliverable = Deliverable::query()->find((int) $ids[0]);
                if ($deliverable) {
                    return $deliverable;
                }
            }

            $code = strtolower(trim((string) ($source['deliverable_code'] ?? $source['code'] ?? '')));
            if ($code !== '') {
                $deliverable = $this->findOrBootstrapDeliverable($code, $indicatorName);
                if ($deliverable) {
                    return $deliverable;
                }
            }
        }

        $byName = Deliverable::query()
            ->where('is_active', true)
            ->where(function ($q) use ($indicatorName): void {
                $q->where('name', $indicatorName)
                    ->orWhere('mis_entry_label', $indicatorName);
            })
            ->first();

        if ($byName) {
            return $byName;
        }

        throw new InvalidArgumentException('No deliverable mapped for MIS serial '.$misSerial.' ('.$indicatorName.').');
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
