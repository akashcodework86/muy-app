<?php

namespace App\Services;

use App\Models\Deliverable;
use App\Models\Service;
use App\Models\ServiceCategory;
use RuntimeException;

class ServiceTargetDeliverableSyncService
{
    public function syncForService(Service $service): Deliverable
    {
        $category = $service->relationLoaded('category') ? $service->category : $service->category()->first();
        $isCategoryMode = $category && $category->target_mode === ServiceCategory::TARGET_MODE_CATEGORY;

        $code = $isCategoryMode
            ? $this->deliverableCodeForCategorySlug((string) ($category->slug ?? ''))
            : $this->deliverableCodeForServiceCode((string) $service->code);
        $name = $isCategoryMode
            ? (string) ($category->name ?: $service->name)
            : (string) $service->name;
        $isActive = $isCategoryMode
            ? Service::query()
                ->where('service_category_id', $category->id)
                ->where('is_active', true)
                ->exists()
            : (bool) $service->is_active;

        $existing = Deliverable::query()->where('code', $code)->first();

        if ($existing) {
            $existing->update([
                'name' => $name,
                'mis_entry_label' => $name,
                'is_active' => $isActive,
            ]);

            return $existing->fresh();
        }

        return Deliverable::query()->create([
            'sort_order' => $this->nextAvailableSortOrder(),
            'code' => $code,
            'name' => $name,
            'mis_entry_label' => $name,
            'is_active' => $isActive,
        ]);
    }

    public function syncAllServices(): void
    {
        $services = Service::query()
            ->with('category')
            ->orderBy('id')
            ->get();

        $services->each(function (Service $service): void {
            $deliverable = $this->syncForService($service);
            if ((int) $service->deliverable_id !== (int) $deliverable->id) {
                $service->deliverable_id = (int) $deliverable->id;
                $service->save();
            }
        });

        $activeCategoryCodes = ServiceCategory::query()
            ->where('target_mode', ServiceCategory::TARGET_MODE_CATEGORY)
            ->pluck('slug')
            ->map(fn ($slug) => $this->deliverableCodeForCategorySlug((string) $slug))
            ->all();

        $obsoleteCategoryDeliverables = Deliverable::query()
            ->where('code', 'like', 'svc_cat_%');
        if ($activeCategoryCodes !== []) {
            $obsoleteCategoryDeliverables->whereNotIn('code', $activeCategoryCodes);
        }
        $obsoleteCategoryDeliverables->update(['is_active' => false]);
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

    public function deliverableCodeForCategorySlug(string $categorySlug): string
    {
        $categorySlug = strtolower(trim($categorySlug));
        if ($categorySlug === '') {
            return 'svc_cat_unknown';
        }
        $categorySlug = preg_replace('/[^a-z0-9_]+/', '_', $categorySlug) ?: 'unknown';
        $categorySlug = trim($categorySlug, '_');
        if ($categorySlug === '') {
            $categorySlug = 'unknown';
        }

        $base = 'svc_cat_'.$categorySlug;
        if (strlen($base) <= 64) {
            return $base;
        }

        $prefix = substr($categorySlug, 0, 43);
        $hash = substr(sha1($categorySlug), 0, 12);

        return 'svc_cat_'.$prefix.'_'.$hash;
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

