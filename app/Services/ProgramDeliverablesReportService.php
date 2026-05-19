<?php

namespace App\Services;

use App\Models\Deliverable;
use App\Models\DistrictDeliverableTarget;
use App\Models\FiscalYear;
use App\Models\FieldCoordinatorAttendanceReport;
use App\Models\Service;
use App\Models\ServiceCase;
use App\Models\StateDeliverableTarget;
use App\Services\Deliverables\ProgramDeliverablesFilter;
use App\Services\Deliverables\ProgramDeliverablesScope;
use Carbon\Carbon;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

class ProgramDeliverablesReportService
{
    public function __construct(
        private readonly ServiceTargetDeliverableSyncService $serviceTargetDeliverables,
    ) {}

    /** @var array<string, int> */
    private array $deliverableIdsByCode = [];

    /** @var array<int, int> */
    private array $achievementByServiceId = [];

    /** @var array<int, int> */
    private array $targetsByDeliverableId = [];

    /** @var array<string, int> */
    private array $serviceIdsByCode = [];

    /** @var list<int>|null */
    private ?array $districtIds = null;

    private ?Carbon $periodFrom = null;

    private ?Carbon $periodTo = null;

    private bool $useStateTargets = true;

    private ?ProgramDeliverablesFilter $filter = null;

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
    public function build(ProgramDeliverablesFilter $filter, ProgramDeliverablesScope $scope): array
    {
        $fiscalYears = FiscalYear::forUiDropdown();
        [$resolvedFyId] = FiscalYear::resolveIdForUi($filter->fiscalYearId);
        $fiscalYear = $fiscalYears->firstWhere('id', $resolvedFyId);

        $this->filter = $filter;
        $this->activeFiscalYear = $fiscalYear;
        $this->districtIds = $scope->effectiveDistrictIds($filter->districtId);
        $this->useStateTargets = $scope->usesStateTargets && $this->districtIds === null;
        [$this->periodFrom, $this->periodTo] = $filter->resolvePeriod($fiscalYear);

        $this->targetsByDeliverableId = $this->loadTargets($fiscalYear);
        $this->achievementByServiceId = $this->achievementCountsByServiceId();
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
            $this->appendIndicatorLeaves($rows, $pillar, [(string) $pillarIndex]);
        }

        return [
            'fiscalYear' => $fiscalYear,
            'rows' => $rows,
        ];
    }

    /**
     * @return array<int, int>
     */
    private function loadTargets(?FiscalYear $fiscalYear): array
    {
        if (! $fiscalYear) {
            return [];
        }

        if ($this->useStateTargets) {
            return StateDeliverableTarget::query()
                ->where('fiscal_year_id', $fiscalYear->id)
                ->pluck('target_total', 'deliverable_id')
                ->map(fn ($v) => (int) $v)
                ->all();
        }

        $query = DistrictDeliverableTarget::query()
            ->where('fiscal_year_id', $fiscalYear->id)
            ->selectRaw('deliverable_id, SUM(target_total) as target_total')
            ->groupBy('deliverable_id');

        if ($this->districtIds !== null) {
            if ($this->districtIds === []) {
                return [];
            }
            $query->whereIn('district_id', $this->districtIds);
        }

        return $query->pluck('target_total', 'deliverable_id')
            ->map(fn ($v) => (int) $v)
            ->all();
    }

    /**
     * Output only measurable indicators (no category / subcategory summary rows).
     *
     * @param  list<string>  $serialParts
     */
    private function appendIndicatorLeaves(array &$rows, array $node, array $serialParts): void
    {
        $children = $node['children'] ?? [];
        $hasSource = isset($node['source']);

        if ($hasSource) {
            $metrics = $this->resolveNodeMetrics($node);
            $rows[] = $this->formatRow($node, implode('.', $serialParts), $metrics);
        }

        if ($children === []) {
            return;
        }

        $childIndex = 0;
        foreach ($children as $child) {
            $childIndex++;
            $this->appendIndicatorLeaves($rows, $child, [...$serialParts, (string) $childIndex]);
        }
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
            'field_work_workshops' => $this->fieldWorkWorkshopsCount(),
            'field_work_participants' => $this->fieldWorkParticipantsCount(),
            'field_visit_sessions' => $this->fieldWorkWorkshopsCount(),
            'field_visit_participants' => $this->fieldWorkParticipantsCount(),
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
        return match ($source['type'] ?? 'none') {
            'deliverable' => $this->resolveStateTargetForCodes([
                (string) ($source['code'] ?? ''),
            ]),
            'services' => $this->resolveStateTargetForCodes(
                array_map('strval', (array) ($source['codes'] ?? [])),
                sumServiceTargets: true,
            ),
            'cfa_count', 'onboarding_count', 'district_workshop_sessions', 'edp_sessions', 'bst_sessions', 'bst_participants' => $this->resolveStateTargetForCodes([
                (string) ($source['deliverable_code'] ?? ''),
            ]),
            default => null,
        };
    }

    /**
     * State targets page may save under MIS codes (fssai) or synced per-service codes (svc_fssai).
     *
     * @param  list<string>  $codes
     */
    private function resolveStateTargetForCodes(array $codes, bool $sumServiceTargets = false): ?int
    {
        $candidateCodes = [];
        foreach ($codes as $code) {
            $code = strtolower(trim($code));
            if ($code === '') {
                continue;
            }
            $candidateCodes[] = $code;
            if (! str_starts_with($code, 'svc_')) {
                $candidateCodes[] = $this->serviceTargetDeliverables->deliverableCodeForServiceCode($code);
            }
        }

        $candidateCodes = array_values(array_unique($candidateCodes));
        if ($candidateCodes === []) {
            return null;
        }

        if ($sumServiceTargets) {
            $sum = 0;
            $hasAny = false;
            foreach ($candidateCodes as $code) {
                $target = $this->stateTargetForDeliverableCode($code);
                if ($target === null) {
                    continue;
                }
                $sum += $target;
                $hasAny = true;
            }

            return $hasAny ? $sum : null;
        }

        $best = null;
        foreach ($candidateCodes as $code) {
            $target = $this->stateTargetForDeliverableCode($code);
            if ($target === null) {
                continue;
            }
            if ($best === null || $target > $best) {
                $best = $target;
            }
        }

        return $best;
    }

    private function stateTargetForDeliverableCode(string $code): ?int
    {
        $deliverableId = $this->deliverableIdsByCode[$code] ?? null;
        if ($deliverableId === null) {
            return null;
        }

        if (! array_key_exists((int) $deliverableId, $this->targetsByDeliverableId)) {
            return null;
        }

        return (int) $this->targetsByDeliverableId[(int) $deliverableId];
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
        $this->applyDistrictScope($query, 'district_id');
        $this->applyCfaAchievementScope($query);

        return (int) $query->count();
    }

    private function onboardingCount(): int
    {
        if (! Schema::hasTable('onboarding_batch_cfa') || ! Schema::hasTable('onboarding_batches')) {
            return 0;
        }

        $query = DB::table('onboarding_batch_cfa as obc')
            ->join('onboarding_batches as ob', 'ob.id', '=', 'obc.onboarding_batch_id')
            ->join('cfa_submissions as cs', 'cs.id', '=', 'obc.cfa_submission_id')
            ->where('ob.status', 'locked')
            ->whereNotNull('ob.locked_at');

        $this->applyDistrictScope($query, 'cs.district_id');
        $this->applyOnboardingAchievementScope($query);

        return (int) $query->count();
    }

    /**
     * Align with state dashboard: CFA achievement = submissions tagged to the FY (`fiscal_year_id`),
     * not every row whose `created_at` falls in the FY calendar window.
     *
     * @param  \Illuminate\Database\Query\Builder  $query
     */
    private function applyCfaAchievementScope($query): void
    {
        $fyId = (int) ($this->activeFiscalYear?->id ?? 0);

        if ($fyId > 0 && Schema::hasColumn('cfa_submissions', 'fiscal_year_id')) {
            $query->where('fiscal_year_id', $fyId);

            if ($this->filter?->hasExplicitDateFilter()) {
                $this->applyPeriodFilter($query, 'created_at');
            }

            return;
        }

        $floor = $this->phase3FloorDate();
        if ($this->periodFrom && $this->periodTo) {
            $from = $this->periodFrom->copy();
            if ($from->lt($floor)) {
                $from = $floor->copy();
            }
            $query->whereBetween('created_at', [$from->toDateTimeString(), $this->periodTo->toDateTimeString()]);
        } else {
            $query->where('created_at', '>=', $floor->toDateTimeString());
        }
    }

    /**
     * @param  \Illuminate\Database\Query\Builder  $query
     */
    private function applyOnboardingAchievementScope($query): void
    {
        $floor = $this->phase3FloorDate();
        $query->where('ob.locked_at', '>=', $floor->toDateTimeString());

        if ($this->filter?->hasExplicitDateFilter() && $this->periodFrom && $this->periodTo) {
            $from = $this->periodFrom->copy();
            if ($from->lt($floor)) {
                $from = $floor->copy();
            }
            $query->whereBetween('ob.locked_at', [$from->toDateTimeString(), $this->periodTo->toDateTimeString()]);
        }
    }

    private function phase3FloorDate(): Carbon
    {
        $raw = (string) config('program_deliverables.phase3_floor_date', '2026-04-01');

        return Carbon::parse($raw)->startOfDay();
    }

    /**
     * MIS 1.3 — each Field work visit submission (staff /my/attendance).
     */
    private function fieldWorkWorkshopsCount(): int
    {
        if (! Schema::hasTable('field_coordinator_attendance_reports')) {
            return 0;
        }

        $query = FieldCoordinatorAttendanceReport::query();
        $this->applyFieldWorkAchievementScope($query);

        return (int) $query->count();
    }

    /**
     * MIS 1.3.1 — sum of participants (M+F) on those Field work visits.
     */
    private function fieldWorkParticipantsCount(): int
    {
        if (! Schema::hasTable('field_coordinator_attendance_reports')) {
            return 0;
        }

        $query = FieldCoordinatorAttendanceReport::query();
        $this->applyFieldWorkAchievementScope($query);

        return (int) $query->sum(DB::raw(
            'COALESCE(NULLIF(participants_total, 0), COALESCE(participants_male_count, 0) + COALESCE(participants_female_count, 0), 0)'
        ));
    }

    /**
     * @param  \Illuminate\Database\Eloquent\Builder<\App\Models\FieldCoordinatorAttendanceReport>  $query
     */
    private function applyFieldWorkAchievementScope($query): void
    {
        $this->applyDistrictScopeOnModel($query, 'district_id');

        $floor = $this->phase3FloorDate();

        if ($this->filter?->hasExplicitDateFilter() && $this->periodFrom && $this->periodTo) {
            $from = $this->periodFrom->copy();
            if ($from->lt($floor)) {
                $from = $floor->copy();
            }
            $query->whereBetween('visit_date', [$from->toDateString(), $this->periodTo->toDateString()]);

            return;
        }

        $query->where('visit_date', '>=', $floor->toDateString());

        if ($this->periodTo) {
            $query->where('visit_date', '<=', $this->periodTo->toDateString());
        }
    }

    private function districtWorkshopSessionsCount(): int
    {
        if (! Schema::hasTable('district_workshop_sessions')) {
            return 0;
        }

        $query = DB::table('district_workshop_sessions');
        $dateCol = Schema::hasColumn('district_workshop_sessions', 'session_date') ? 'session_date' : 'created_at';
        $this->applyDistrictScope($query, 'district_id');
        $this->applyPeriodFilter($query, $dateCol);

        return (int) $query->count();
    }

    private function edpSessionsCount(): int
    {
        if (! Schema::hasTable('eap_edp_sessions')) {
            return 0;
        }

        $query = DB::table('eap_edp_sessions');
        $dateCol = Schema::hasColumn('eap_edp_sessions', 'session_date') ? 'session_date' : 'event_date';
        if (! Schema::hasColumn('eap_edp_sessions', $dateCol)) {
            $dateCol = 'created_at';
        }
        $this->applyDistrictScope($query, 'district_id');
        $this->applyPeriodFilter($query, $dateCol);

        return (int) $query->count();
    }

    private function bstSessionsCount(): int
    {
        if (Schema::hasTable('training_package_month_sessions')) {
            $query = DB::table('training_package_month_sessions');
            $this->applyDistrictScope($query, 'district_id');
            $this->applyBstMonthSessionPeriod($query);

            return (int) $query->count();
        }

        if (Schema::hasTable('training_packages')) {
            $query = DB::table('training_packages');
            $dateCol = Schema::hasColumn('training_packages', 'event_date') ? 'event_date' : 'created_at';
            $this->applyDistrictScope($query, 'district_id');
            $this->applyPeriodFilter($query, $dateCol);

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
        $dateCol = Schema::hasColumn('training_packages', 'event_date') ? 'event_date' : 'created_at';
        $this->applyDistrictScope($query, 'district_id');
        $this->applyPeriodFilter($query, $dateCol);

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
            if (Schema::hasColumn('technical_training_sessions', 'district_id')) {
                $this->applyDistrictScope($query, 'district_id');
            }
            $this->applyPeriodFilter($query, $dateCol);

            return (int) $query->count();
        }

        if (Schema::hasTable('technical_trainings')) {
            $query = DB::table('technical_trainings');
            $dateCol = Schema::hasColumn('technical_trainings', 'event_date') ? 'event_date' : 'created_at';
            $this->applyDistrictScope($query, 'district_id');
            $this->applyPeriodFilter($query, $dateCol);

            return (int) $query->count();
        }

        return 0;
    }

    private ?FiscalYear $activeFiscalYear = null;

    /**
     * @param  \Illuminate\Database\Query\Builder  $query
     */
    private function applyDistrictScope($query, string $column): void
    {
        if ($this->districtIds === null) {
            return;
        }

        if ($this->districtIds === []) {
            $query->whereRaw('0 = 1');

            return;
        }

        $query->whereIn($column, $this->districtIds);
    }

    /**
     * @param  \Illuminate\Database\Eloquent\Builder<\App\Models\FieldCoordinatorAttendanceReport>  $query
     */
    private function applyDistrictScopeOnModel($query, string $column): void
    {
        if ($this->districtIds === null) {
            return;
        }

        if ($this->districtIds === []) {
            $query->whereRaw('0 = 1');

            return;
        }

        $query->whereIn($column, $this->districtIds);
    }

    /**
     * @param  \Illuminate\Database\Query\Builder  $query
     */
    private function applyPeriodFilter($query, string $column): void
    {
        if (! $this->periodFrom || ! $this->periodTo) {
            return;
        }

        $query->whereBetween($column, [
            $this->periodFrom->toDateTimeString(),
            $this->periodTo->toDateTimeString(),
        ]);
    }

    /**
     * @param  \Illuminate\Database\Eloquent\Builder<\App\Models\FieldCoordinatorAttendanceReport>  $query
     */
    private function applyPeriodFilterOnModel($query, string $column): void
    {
        if (! $this->periodFrom || ! $this->periodTo) {
            return;
        }

        $query->whereBetween($column, [
            $this->periodFrom->toDateString(),
            $this->periodTo->toDateString(),
        ]);
    }

    /**
     * @param  \Illuminate\Database\Query\Builder  $query
     */
    private function applyBstMonthSessionPeriod($query): void
    {
        if (! $this->periodFrom || ! $this->periodTo) {
            return;
        }

        $query->where(function ($q): void {
            $cursor = $this->periodFrom->copy()->startOfMonth();
            $end = $this->periodTo->copy()->startOfMonth();
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
    private function achievementCountsByServiceId(): array
    {
        if ($this->districtIds === []) {
            return [];
        }

        $query = ServiceCase::query()
            ->selectRaw('service_cases.service_id, COUNT(*) as total')
            ->where('service_cases.status', ServiceCase::STATUS_APPROVED)
            ->whereNotNull('service_cases.service_id');

        if ($this->districtIds !== null) {
            $query->join('cfa_submissions as cs', 'cs.id', '=', 'service_cases.cfa_submission_id')
                ->whereIn('cs.district_id', $this->districtIds);
        }

        if ($this->periodFrom && $this->periodTo) {
            $from = $this->periodFrom->toDateString();
            $to = $this->periodTo->toDateTimeString();
            $query->where(function ($q) use ($from, $to): void {
                $q->whereBetween('service_cases.approved_at', [$from, $to])
                    ->orWhere(function ($q2) use ($from, $to): void {
                        $q2->whereNull('service_cases.approved_at')
                            ->whereBetween('service_cases.created_at', [$from, $to]);
                    });
            });
        }

        return $query->groupBy('service_cases.service_id')
            ->pluck('total', 'service_id')
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
