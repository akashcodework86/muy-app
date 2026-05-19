<?php

namespace App\Services;

use App\Models\FiscalYear;
use App\Models\Service;
use App\Models\ServiceCase;
use App\Models\ServiceCategory;
use App\Models\StateDeliverableTarget;
use Illuminate\Support\Collection;

class ProgramDeliverablesReportService
{
    /**
     * @return array{
     *     fiscalYear: ?FiscalYear,
     *     rows: list<array{
     *         row_type: string,
     *         serial: string,
     *         name: string,
     *         indicator_type: string,
     *         level: string,
     *         target: ?int,
     *         achievement: int,
     *         achievement_pct: ?int
     *     }>
     * }
     */
    public function build(?int $fiscalYearId): array
    {
        $fiscalYears = FiscalYear::forUiDropdown();
        [$resolvedFyId] = FiscalYear::resolveIdForUi($fiscalYearId);
        $fiscalYear = $fiscalYears->firstWhere('id', $resolvedFyId);

        $targetsByDeliverableId = $fiscalYear
            ? StateDeliverableTarget::query()
                ->where('fiscal_year_id', $fiscalYear->id)
                ->pluck('target_total', 'deliverable_id')
                ->map(fn ($v) => (int) $v)
                ->all()
            : [];

        $achievementByServiceId = $this->achievementCountsByServiceId($fiscalYear);

        $roots = ServiceCategory::query()
            ->whereNull('parent_id')
            ->orderBy('sort_order')
            ->orderBy('id')
            ->get();

        $rows = [];
        $categoryIndex = 0;

        foreach ($roots as $root) {
            $categoryIndex++;
            $serialPrefix = (string) $categoryIndex;

            $services = $this->servicesForRootCategory($root);
            $categoryTarget = null;
            $categoryAchievement = 0;

            foreach ($services as $service) {
                $categoryAchievement += (int) ($achievementByServiceId[(int) $service->id] ?? 0);
            }

            if ($root->target_mode === ServiceCategory::TARGET_MODE_CATEGORY) {
                $deliverableId = $services->first()?->deliverable_id;
                if ($deliverableId) {
                    $categoryTarget = $targetsByDeliverableId[(int) $deliverableId] ?? null;
                }
            } else {
                $sumTarget = 0;
                $hasTarget = false;
                foreach ($services as $service) {
                    $t = $targetsByDeliverableId[(int) ($service->deliverable_id ?? 0)] ?? null;
                    if ($t !== null) {
                        $sumTarget += $t;
                        $hasTarget = true;
                    }
                }
                $categoryTarget = $hasTarget ? $sumTarget : null;
            }

            $rows[] = [
                'row_type' => 'category',
                'serial' => $serialPrefix,
                'name' => (string) $root->name,
                'indicator_type' => '',
                'level' => '',
                'target' => $categoryTarget,
                'achievement' => $categoryAchievement,
                'achievement_pct' => $this->percent($categoryTarget, $categoryAchievement),
            ];

            $serviceIndex = 0;
            foreach ($services as $service) {
                $serviceIndex++;
                $achievement = (int) ($achievementByServiceId[(int) $service->id] ?? 0);
                $target = $root->target_mode === ServiceCategory::TARGET_MODE_CATEGORY
                    ? null
                    : ($targetsByDeliverableId[(int) ($service->deliverable_id ?? 0)] ?? null);

                $rows[] = [
                    'row_type' => 'service',
                    'serial' => $serialPrefix.'.'.$serviceIndex,
                    'name' => (string) $service->name,
                    'indicator_type' => $this->indicatorTypeLabel($service),
                    'level' => $this->levelLabel($service),
                    'target' => $target,
                    'achievement' => $achievement,
                    'achievement_pct' => $this->percent($target, $achievement),
                ];
            }
        }

        return [
            'fiscalYear' => $fiscalYear,
            'rows' => $rows,
        ];
    }

    /**
     * @return Collection<int, Service>
     */
    private function servicesForRootCategory(ServiceCategory $root): Collection
    {
        $childCategoryIds = ServiceCategory::query()
            ->where('parent_id', $root->id)
            ->pluck('id')
            ->all();

        $categoryIds = array_merge([(int) $root->id], array_map('intval', $childCategoryIds));

        return Service::query()
            ->with(['deliverable:id,code'])
            ->whereIn('service_category_id', $categoryIds)
            ->where('is_active', true)
            ->orderBy('sort_order')
            ->orderBy('id')
            ->get();
    }

    /**
     * @return array<int, int>
     */
    private function achievementCountsByServiceId(?FiscalYear $fiscalYear): array
    {
        $query = ServiceCase::query()
            ->selectRaw('service_id, COUNT(*) as total')
            ->where('status', ServiceCase::STATUS_APPROVED)
            ->whereNotNull('service_id')
            ->groupBy('service_id');

        if ($fiscalYear) {
            $start = $fiscalYear->starts_on?->toDateString();
            $end = $fiscalYear->ends_on?->toDateString();
            if ($start && $end) {
                $query->where(function ($q) use ($start, $end): void {
                    $q->whereBetween('approved_at', [$start, $end])
                        ->orWhere(function ($q2) use ($start, $end): void {
                            $q2->whereNull('approved_at')
                                ->whereBetween('created_at', [$start.' 00:00:00', $end.' 23:59:59']);
                        });
                });
            }
        }

        return $query->pluck('total', 'service_id')
            ->map(fn ($v) => (int) $v)
            ->all();
    }

    private function indicatorTypeLabel(Service $service): string
    {
        return match ($service->reporting_tier) {
            Service::REPORTING_KEY => 'Key Indicator',
            Service::REPORTING_NON_KEY => 'Non-Key',
            default => 'Non-Key',
        };
    }

    private function levelLabel(Service $service): string
    {
        $code = strtolower((string) ($service->deliverable?->code ?? ''));
        if ($code !== '') {
            $mapped = config('program_deliverables.level_by_deliverable_code.'.$code);
            if (is_string($mapped) && $mapped !== '') {
                return $mapped;
            }
        }

        return (string) config('program_deliverables.default_level', 'Spoke & Hub');
    }

    private function percent(?int $target, int $achievement): ?int
    {
        if ($target === null || $target <= 0) {
            return null;
        }

        return (int) round(($achievement / $target) * 100);
    }
}
