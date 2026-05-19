<?php

namespace App\Services;

use App\Models\Deliverable;
use App\Models\FiscalYear;
use App\Models\FieldCoordinatorAttendanceReport;
use App\Models\Service;
use App\Models\ServiceCase;
use App\Models\StateDeliverableTarget;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

class ProgramDeliverablesReportService
{
    /** @var array<string, int> */
    private array $deliverableIdsByCode = [];

    /** @var array<int, int> */
    private array $achievementByServiceId = [];

    /** @var array<int, int> */
    private array $targetsByDeliverableId = [];

  /** @var array<string, int> */
    private array $serviceIdsByCode = [];

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

        $this->activeFiscalYear = $fiscalYear;

        $this->targetsByDeliverableId = $fiscalYear
            ? StateDeliverableTarget::query()
                ->where('fiscal_year_id', $fiscalYear->id)
                ->pluck('target_total', 'deliverable_id')
                ->map(fn ($v) => (int) $v)
                ->all()
            : [];

        $this->achievementByServiceId = $this->achievementCountsByServiceId($fiscalYear);
        $this->deliverableIdsByCode = Deliverable::query()
            ->pluck('id', 'code')
            ->map(fn ($id) => (int) $id)
            ->all();
        $this->serviceIdsByCode = Service::query()
            ->where('is_active', true)
            ->pluck('id', 'code')
            ->map(fn ($id) => (int) $id)
            ->all();

        $rows = [];
        $pillarIndex = 0;
        foreach (config('program_deliverables.matrix', []) as $pillar) {
            $pillarIndex++;
            $this->appendBranch($rows, $pillar, [(string) $pillarIndex]);
        }

        return [
            'fiscalYear' => $fiscalYear,
            'rows' => $rows,
        ];
    }

    /**
     * @param  list<array<string, mixed>>  $rows
     * @param  list<string>  $serialParts
     */
    /**
     * @return array{target: ?int, achievement: int}
     */
    private function appendBranch(array &$rows, array $node, array $serialParts): array
    {
        $serial = implode('.', $serialParts);
        $children = $node['children'] ?? [];

        if ($children === []) {
            $metrics = $this->resolveNodeMetrics($node);
            $rows[] = $this->formatRow($node, $serial, $metrics);

            return $metrics;
        }

        $childStartIndex = count($rows);
        $childMetrics = [];
        $childIndex = 0;

        foreach ($children as $child) {
            $childIndex++;
            $childMetrics[] = $this->appendBranch($rows, $child, [...$serialParts, (string) $childIndex]);
        }

        $aggregated = $this->aggregateMetrics($childMetrics);
        $metrics = isset($node['source'])
            ? $this->mergeMetrics($this->resolveNodeMetrics($node), $aggregated)
            : $aggregated;

        array_splice($rows, $childStartIndex, 0, [$this->formatRow($node, $serial, $metrics)]);

        return $metrics;
    }

    /**
     * @param  array<string, mixed>  $node
     * @return array{target: ?int, achievement: int}
     */
    private function resolveNodeMetrics(array $node): array
    {
        $source = $node['source'] ?? ['type' => 'none'];
        $achievement = $this->achievementForSource($source);
        $target = $this->targetForSource($source);

        return [
            'target' => $target,
            'achievement' => $achievement,
        ];
    }

    /**
     * @param  array<string, mixed>  $source
     */
    private function achievementForSource(array $source): int
    {
        return match ($source['type'] ?? 'none') {
            'deliverable' => $this->achievementForDeliverableCode((string) ($source['code'] ?? '')),
            'service' => $this->achievementForServiceCode((string) ($source['code'] ?? '')),
            'services' => $this->achievementForServiceCodes((array) ($source['codes'] ?? [])),
            'cfa_count' => $this->cfaCount(),
            'onboarding_count' => $this->onboardingCount(),
            'field_visit_sessions' => $this->fieldVisitSessionsCount(),
            'field_visit_participants' => $this->fieldVisitParticipantsCount(),
            'district_workshop_sessions' => $this->districtWorkshopSessionsCount(),
            'edp_sessions' => $this->edpSessionsCount(),
            'bst_sessions' => $this->bstSessionsCount(),
            'bst_participants' => $this->bstParticipantsCount(),
            'technical_training_sessions' => $this->technicalTrainingSessionsCount(),
            default => 0,
        };
    }

    /**
     * @param  array<string, mixed>  $source
     */
    private function targetForSource(array $source): ?int
    {
        $code = match ($source['type'] ?? 'none') {
            'deliverable' => (string) ($source['code'] ?? ''),
            'cfa_count', 'onboarding_count', 'district_workshop_sessions', 'edp_sessions', 'bst_sessions', 'bst_participants' => (string) ($source['deliverable_code'] ?? ''),
            default => '',
        };

        if ($code === '') {
            return null;
        }

        $deliverableId = $this->deliverableIdsByCode[$code] ?? null;
        if ($deliverableId === null) {
            return null;
        }

        $target = $this->targetsByDeliverableId[(int) $deliverableId] ?? null;

        return $target !== null ? (int) $target : null;
    }

    private function achievementForDeliverableCode(string $code): int
    {
        if ($code === '') {
            return 0;
        }

        $deliverableId = $this->deliverableIdsByCode[$code] ?? null;
        if ($deliverableId === null) {
            return 0;
        }

        $serviceIds = Service::query()
            ->where('deliverable_id', $deliverableId)
            ->where('is_active', true)
            ->pluck('id')
            ->map(fn ($id) => (int) $id)
            ->all();

        $total = 0;
        foreach ($serviceIds as $serviceId) {
            $total += (int) ($this->achievementByServiceId[$serviceId] ?? 0);
        }

        return $total;
    }

    private function achievementForServiceCode(string $code): int
    {
        $serviceId = $this->serviceIdsByCode[$code] ?? null;

        return $serviceId ? (int) ($this->achievementByServiceId[$serviceId] ?? 0) : 0;
    }

    /**
     * @param  list<string>  $codes
     */
    private function achievementForServiceCodes(array $codes): int
    {
        $total = 0;
        foreach ($codes as $code) {
            $total += $this->achievementForServiceCode((string) $code);
        }

        return $total;
    }

    private function cfaCount(): int
    {
        if (! Schema::hasTable('cfa_submissions')) {
            return 0;
        }

        $query = DB::table('cfa_submissions');
        $this->applyFyDateFilter($query, 'created_at');

        return (int) $query->count();
    }

    private function onboardingCount(): int
    {
        if (! Schema::hasTable('onboarding_batch_cfa') || ! Schema::hasTable('onboarding_batches')) {
            return 0;
        }

        $query = DB::table('onboarding_batch_cfa as obc')
            ->join('onboarding_batches as ob', 'ob.id', '=', 'obc.onboarding_batch_id')
            ->where('ob.status', 'locked')
            ->whereNotNull('ob.locked_at');

        $this->applyFyDateFilter($query, 'ob.locked_at');

        return (int) $query->count();
    }

    private function fieldVisitSessionsCount(): int
    {
        if (! Schema::hasTable('field_coordinator_attendance_reports')) {
            return 0;
        }

        $query = FieldCoordinatorAttendanceReport::query();
        $this->applyFyDateFilterOnModel($query, 'visit_date');

        return (int) $query->count();
    }

    private function fieldVisitParticipantsCount(): int
    {
        if (! Schema::hasTable('field_coordinator_attendance_reports')) {
            return 0;
        }

        $query = FieldCoordinatorAttendanceReport::query();
        $this->applyFyDateFilterOnModel($query, 'visit_date');

        return (int) $query->sum(DB::raw('COALESCE(participants_total, participants_male_count + participants_female_count, 0)'));
    }

    private function districtWorkshopSessionsCount(): int
    {
        if (! Schema::hasTable('district_workshop_sessions')) {
            return 0;
        }

        $query = DB::table('district_workshop_sessions');
        $dateCol = Schema::hasColumn('district_workshop_sessions', 'session_date') ? 'session_date' : 'created_at';
        $this->applyFyDateFilter($query, $dateCol);

        return (int) $query->count();
    }

    private function edpSessionsCount(): int
    {
        if (! Schema::hasTable('eap_edp_sessions')) {
            return 0;
        }

        $query = DB::table('eap_edp_sessions');
        $dateCol = Schema::hasColumn('eap_edp_sessions', 'session_date') ? 'session_date' : 'created_at';
        $this->applyFyDateFilter($query, $dateCol);

        return (int) $query->count();
    }

    private function bstSessionsCount(): int
    {
        if (Schema::hasTable('training_package_month_sessions')) {
            $query = DB::table('training_package_month_sessions');
            $this->applyFyCalendarFilter($query);

            return (int) $query->count();
        }

        if (Schema::hasTable('training_packages')) {
            $query = DB::table('training_packages');
            $this->applyFyDateFilter($query, 'created_at');

            return (int) $query->count();
        }

        return 0;
    }

    private function bstParticipantsCount(): int
    {
        if (! Schema::hasTable('training_packages')) {
            return 0;
        }

        $query = DB::table('training_packages');
        $this->applyFyDateFilter($query, 'created_at');

        if (Schema::hasColumn('training_packages', 'participants_total')) {
            return (int) (clone $query)->sum('participants_total');
        }

        if (Schema::hasColumn('training_packages', 'male_participants') && Schema::hasColumn('training_packages', 'female_participants')) {
            return (int) (clone $query)->sum(DB::raw('COALESCE(male_participants,0) + COALESCE(female_participants,0)'));
        }

        return (int) $query->count();
    }

    private function technicalTrainingSessionsCount(): int
    {
        if (Schema::hasTable('technical_training_sessions')) {
            $query = DB::table('technical_training_sessions');
            $dateCol = Schema::hasColumn('technical_training_sessions', 'session_date') ? 'session_date' : 'created_at';
            $this->applyFyDateFilter($query, $dateCol);

            return (int) $query->count();
        }

        if (Schema::hasTable('technical_trainings')) {
            $query = DB::table('technical_trainings');
            $this->applyFyDateFilter($query, 'created_at');

            return (int) $query->count();
        }

        return 0;
    }

    private ?FiscalYear $activeFiscalYear = null;

    private function fiscalYear(): ?FiscalYear
    {
        return $this->activeFiscalYear;
    }

    /**
     * @param  \Illuminate\Database\Query\Builder  $query
     */
    private function applyFyDateFilter($query, string $column): void
    {
        $fy = $this->fiscalYear();
        if (! $fy) {
            return;
        }

        $start = $fy->starts_on?->toDateString();
        $end = $fy->ends_on?->toDateString();
        if ($start && $end) {
            $query->whereBetween($column, [$start, $end.' 23:59:59']);
        }
    }

    /**
     * @param  \Illuminate\Database\Eloquent\Builder<\App\Models\FieldCoordinatorAttendanceReport>  $query
     */
    private function applyFyDateFilterOnModel($query, string $column): void
    {
        $fy = $this->fiscalYear();
        if (! $fy) {
            return;
        }

        $start = $fy->starts_on?->toDateString();
        $end = $fy->ends_on?->toDateString();
        if ($start && $end) {
            $query->whereBetween($column, [$start, $end]);
        }
    }

    /**
     * @param  \Illuminate\Database\Query\Builder  $query
     */
    private function applyFyCalendarFilter($query): void
    {
        $fy = $this->fiscalYear();
        if (! $fy || ! $fy->starts_on || ! $fy->ends_on) {
            return;
        }

        $start = $fy->starts_on;
        $end = $fy->ends_on;
        $query->where(function ($q) use ($start, $end): void {
            $cursor = $start->copy()->startOfMonth();
            while ($cursor->lte($end)) {
                $q->orWhere(function ($q2) use ($cursor): void {
                    $q2->where('calendar_year', $cursor->year)
                        ->where('calendar_month', $cursor->month);
                });
                $cursor->addMonth();
            }
        });
    }

    /**
     * @param  list<array{target: ?int, achievement: int}>  $metricsList
     * @return array{target: ?int, achievement: int}
     */
    private function aggregateMetrics(array $metricsList): array
    {
        $achievement = 0;
        $targetSum = 0;
        $hasTarget = false;

        foreach ($metricsList as $m) {
            $achievement += (int) $m['achievement'];
            if ($m['target'] !== null) {
                $targetSum += (int) $m['target'];
                $hasTarget = true;
            }
        }

        return [
            'target' => $hasTarget ? $targetSum : null,
            'achievement' => $achievement,
        ];
    }

    /**
     * @param  array{target: ?int, achievement: int}  $a
     * @param  array{target: ?int, achievement: int}  $b
     * @return array{target: ?int, achievement: int}
     */
    private function mergeMetrics(array $a, array $b): array
    {
        $target = null;
        if ($a['target'] !== null || $b['target'] !== null) {
            $target = (int) ($a['target'] ?? 0) + (int) ($b['target'] ?? 0);
        }

        return [
            'target' => $target,
            'achievement' => (int) $a['achievement'] + (int) $b['achievement'],
        ];
    }

    /**
     * @param  array<string, mixed>  $node
     * @param  array{target: ?int, achievement: int}  $metrics
     * @return array<string, mixed>
     */
    private function formatRow(array $node, string $serial, array $metrics): array
    {
        $target = $metrics['target'];
        $achievement = $metrics['achievement'];

        return [
            'row_type' => (string) ($node['row_type'] ?? 'leaf'),
            'serial' => $serial,
            'name' => (string) ($node['name'] ?? ''),
            'indicator_type' => (string) ($node['indicator_type'] ?? ''),
            'level' => (string) ($node['level'] ?? ''),
            'target' => $target,
            'achievement' => $achievement,
            'achievement_pct' => $this->percent($target, $achievement),
        ];
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

    private function percent(?int $target, int $achievement): ?int
    {
        if ($target === null || $target <= 0) {
            return null;
        }

        return (int) round(($achievement / $target) * 100);
    }
}
