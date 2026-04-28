<?php

namespace App\Services;

use App\Models\Deliverable;
use App\Models\Service;
use RuntimeException;

class ServiceTargetDeliverableSyncService
{
    public function syncForService(Service $service): Deliverable
    {
        $code = $this->deliverableCodeForServiceCode((string) $service->code);
        $existing = Deliverable::query()->where('code', $code)->first();

        if ($existing) {
            $existing->update([
                'name' => (string) $service->name,
                'mis_entry_label' => (string) $service->name,
                'is_active' => (bool) $service->is_active,
            ]);

            return $existing->fresh();
        }

        return Deliverable::query()->create([
            'sort_order' => $this->nextAvailableSortOrder(),
            'code' => $code,
            'name' => (string) $service->name,
            'mis_entry_label' => (string) $service->name,
            'is_active' => (bool) $service->is_active,
        ]);
    }

    public function syncAllServices(): void
    {
        Service::query()
            ->orderBy('id')
            ->get()
            ->each(function (Service $service): void {
                $deliverable = $this->syncForService($service);
                if ((int) $service->deliverable_id !== (int) $deliverable->id) {
                    $service->deliverable_id = (int) $deliverable->id;
                    $service->save();
                }
            });
    }

    public function deactivateIfServiceMissing(string $serviceCode): void
    {
        $serviceCode = trim($serviceCode);
        if ($serviceCode === '') {
            return;
        }

        $hasAnyService = Service::query()->where('code', $serviceCode)->exists();
        if ($hasAnyService) {
            return;
        }

        Deliverable::query()
            ->where('code', $this->deliverableCodeForServiceCode($serviceCode))
            ->update(['is_active' => false]);
    }

    public function deliverableCodeForServiceCode(string $serviceCode): string
    {
        $serviceCode = strtolower(trim($serviceCode));
        if ($serviceCode === '') {
            return 'svc_unknown';
        }

        $base = 'svc_'.$serviceCode;
        if (strlen($base) <= 64) {
            return $base;
        }

        $prefix = substr($serviceCode, 0, 47);
        $hash = substr(sha1($serviceCode), 0, 12);

        return 'svc_'.$prefix.'_'.$hash;
    }

    private function nextAvailableSortOrder(): int
    {
        $used = Deliverable::query()->pluck('sort_order')->map(fn ($v) => (int) $v)->all();
        $usedMap = array_fill_keys($used, true);

        for ($i = 5; $i <= 255; $i++) {
            if (! isset($usedMap[$i])) {
                return $i;
            }
        }

        for ($i = 1; $i <= 255; $i++) {
            if (! isset($usedMap[$i])) {
                return $i;
            }
        }

        throw new RuntimeException('No free deliverable sort order available.');
    }
}

