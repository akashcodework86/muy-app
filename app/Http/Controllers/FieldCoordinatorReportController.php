<?php

namespace App\Http\Controllers;

use App\Models\District;
use App\Models\FieldCoordinatorAttendanceReport;
use App\Models\Hub;
use App\Models\User;
use App\Services\FieldCoordinatorReports\FieldCoordinatorReportScope;
use App\Services\FieldVisitAttendanceSheetService;
use App\Services\FieldVisitMediaStorage;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Illuminate\View\View;
use Symfony\Component\HttpFoundation\StreamedResponse;

class FieldCoordinatorReportController extends Controller
{
    public function __construct(
        private readonly FieldVisitMediaStorage $mediaStorage,
        private readonly FieldVisitAttendanceSheetService $attendanceSheetService,
    ) {}

    public function index(Request $request): View
    {
        $user = $request->user()->load(['district', 'designationRecord']);
        $scope = FieldCoordinatorReportScope::forUser($user);
        $routePrefix = $this->routePrefixFor($user);

        if (! Schema::hasTable('field_coordinator_attendance_reports')) {
            return view('field-coordinator-reports.index', [
                'reports' => collect(),
                'user' => $user,
                'scope' => $scope,
                'overview' => $this->emptyOverview(),
                'districtSummaries' => [],
                'hubs' => collect(),
                'districts' => collect(),
                'coordinators' => collect(),
                'blockOptions' => [],
                'cfaByCoordinatorDate' => [],
                'filters' => [],
                'routeIndex' => $routePrefix.'.field-coordinator-reports.index',
                'routeAttachment' => $routePrefix.'.field-coordinator-reports.attachment',
                'routeSheet' => $routePrefix.'.field-coordinator-reports.sheet',
                'migrationMissing' => true,
            ]);
        }

        [$hubId, $districtId, $coordinatorId, $from, $to, $block, $search] = $this->extractFilters($request, $scope);

        $hubs = $scope->canFilterHub
            ? Hub::query()->orderBy('sort_order')->orderBy('name')->get(['id', 'name'])
            : collect();

        $districts = District::query()
            ->when($hubId, fn ($q) => $q->where('hub_id', $hubId))
            ->when(is_array($scope->districtIds), fn ($q) => $q->whereIn('id', $scope->districtIds))
            ->orderBy('name')
            ->get(['id', 'name', 'hub_id']);

        $baseQuery = $this->scopedQuery($scope);
        $this->applyFilters($baseQuery, $scope, $hubId, $districtId, $coordinatorId, $from, $to, $block, $search);

        $overview = $this->buildOverview(clone $baseQuery);
        $districtSummaries = $this->buildDistrictSummaries(clone $baseQuery);

        $reports = (clone $baseQuery)
            ->with(['district:id,name', 'gramPanchayat:id,name', 'coordinator:id,name'])
            ->orderByDesc('visit_date')
            ->orderByDesc('id')
            ->paginate(25)
            ->withQueryString();

        $coordinatorIds = (clone $baseQuery)
            ->distinct()
            ->pluck('field_coordinator_user_id')
            ->map(fn ($id) => (int) $id)
            ->filter(fn (int $id) => $id > 0)
            ->values()
            ->all();

        $cfaByCoordinatorDate = $this->buildCfaByCoordinatorDate($coordinatorIds, $from, $to);

        $coordinators = $scope->canFilterCoordinator
            ? User::query()
                ->where('role', 'district_staff')
                ->where('is_active', true)
                ->when(is_array($scope->districtIds), fn ($q) => $q->whereIn('district_id', $scope->districtIds))
                ->with('district:id,name')
                ->orderBy('name')
                ->get(['id', 'name', 'district_id'])
            : collect();

        $blockOptions = (clone $this->scopedQuery($scope))
            ->when($districtId, fn (Builder $q) => $q->where('district_id', $districtId))
            ->whereNotNull('block')
            ->where('block', '!=', '')
            ->distinct()
            ->orderBy('block')
            ->pluck('block')
            ->all();

        return view('field-coordinator-reports.index', [
            'reports' => $reports,
            'user' => $user,
            'scope' => $scope,
            'overview' => $overview,
            'districtSummaries' => $districtSummaries,
            'hubs' => $hubs,
            'districts' => $districts,
            'coordinators' => $coordinators,
            'blockOptions' => $blockOptions,
            'cfaByCoordinatorDate' => $cfaByCoordinatorDate,
            'filters' => [
                'hub' => $hubId,
                'district' => $districtId,
                'coordinator_id' => $coordinatorId,
                'from' => $from,
                'to' => $to,
                'block' => $block,
                'q' => $search,
            ],
            'routeIndex' => $routePrefix.'.field-coordinator-reports.index',
            'routeAttachment' => $routePrefix.'.field-coordinator-reports.attachment',
            'routeSheet' => $routePrefix.'.field-coordinator-reports.sheet',
            'migrationMissing' => false,
        ]);
    }

    public function downloadAttachment(
        FieldCoordinatorAttendanceReport $attendanceReport,
        Request $request,
    ): StreamedResponse {
        abort_unless($this->scopeFor($request)->canViewReport($attendanceReport), 403);

        $index = $request->query('index');
        if ($index !== null && $index !== '') {
            return $this->mediaStorage->download(
                $attendanceReport,
                (int) $index,
                $request->boolean('inline'),
            );
        }

        return $this->mediaStorage->legacyDownload($attendanceReport);
    }

    public function downloadAttendanceSheet(
        FieldCoordinatorAttendanceReport $attendanceReport,
        Request $request,
    ): StreamedResponse {
        abort_unless($this->scopeFor($request)->canViewReport($attendanceReport), 403);
        abort_unless($attendanceReport->hasAttendanceSheet(), 404);

        return $this->attendanceSheetService->downloadStored(
            (string) $attendanceReport->attendance_sheet_path,
            (string) ($attendanceReport->attendance_sheet_original_name ?: 'attendance-sheet.xlsx'),
        );
    }

    private function scopeFor(Request $request): FieldCoordinatorReportScope
    {
        return FieldCoordinatorReportScope::forUser($request->user());
    }

    private function routePrefixFor(?User $user): string
    {
        return match ($user?->role) {
            'hub_admin' => 'hub',
            'district_staff' => 'staff',
            'state_staff' => 'spoc',
            default => 'admin',
        };
    }

    private function scopedQuery(FieldCoordinatorReportScope $scope): Builder
    {
        $query = FieldCoordinatorAttendanceReport::query();
        $scope->applyToQuery($query);

        return $query;
    }

    /**
     * @return array{hub: ?int, district: ?int, coordinator_id: ?int, from: ?string, to: ?string, block: ?string, q: string}
     */
    private function extractFilters(Request $request, FieldCoordinatorReportScope $scope): array
    {
        $hubId = $scope->canFilterHub ? ($request->integer('hub') ?: null) : null;
        $districtId = $request->integer('district') ?: null;
        $coordinatorId = $scope->canFilterCoordinator ? ($request->integer('coordinator_id') ?: null) : null;

        if ($districtId !== null && ! in_array($districtId, $scope->effectiveDistrictIds(null), true) && $scope->districtIds !== null) {
            $districtId = null;
        }

        if ($coordinatorId !== null && is_array($scope->districtIds)) {
            $allowed = User::query()
                ->where('id', $coordinatorId)
                ->whereIn('district_id', $scope->districtIds)
                ->exists();
            if (! $allowed) {
                $coordinatorId = null;
            }
        }

        return [
            $hubId,
            $districtId,
            $coordinatorId,
            $request->filled('from') ? (string) $request->query('from') : null,
            $request->filled('to') ? (string) $request->query('to') : null,
            $request->filled('block') ? trim((string) $request->query('block')) : null,
            trim((string) $request->query('q', '')),
        ];
    }

    private function applyFilters(
        Builder $query,
        FieldCoordinatorReportScope $scope,
        ?int $hubId,
        ?int $districtId,
        ?int $coordinatorId,
        ?string $from,
        ?string $to,
        ?string $block,
        string $search,
    ): void {
        if ($hubId) {
            $query->whereHas('district', fn (Builder $d) => $d->where('hub_id', $hubId));
        }

        if ($districtId) {
            $query->where('district_id', $districtId);
        }

        if ($coordinatorId) {
            $query->where('field_coordinator_user_id', $coordinatorId);
        }

        if ($from) {
            $query->whereDate('visit_date', '>=', $from);
        }
        if ($to) {
            $query->whereDate('visit_date', '<=', $to);
        }
        if ($block !== null && $block !== '') {
            $query->where('block', $block);
        }
        if ($search !== '') {
            $like = '%'.str_replace(['\\', '%', '_'], ['\\\\', '\\%', '\\_'], $search).'%';
            $query->where(function (Builder $q) use ($like): void {
                $q->where('field_coordinator_name', 'like', $like)
                    ->orWhere('block', 'like', $like)
                    ->orWhere('area', 'like', $like)
                    ->orWhere('remark', 'like', $like)
                    ->orWhereHas('gramPanchayat', fn (Builder $gp) => $gp->where('name', 'like', $like))
                    ->orWhereHas('district', fn (Builder $d) => $d->where('name', 'like', $like));
            });
        }
    }

    /** @return array<string, int|float|null> */
    private function buildOverview(Builder $query): array
    {
        $row = (array) $query
            ->selectRaw('
                COUNT(*) as reports,
                COALESCE(SUM(participants_total), 0) as participants,
                COALESCE(SUM(villages_visited_total), 0) as villages,
                COALESCE(SUM(cfas_filled_total), 0) as cfas,
                COALESCE(SUM(outreach_programmes_total), 0) as outreach,
                COUNT(DISTINCT field_coordinator_user_id) as coordinators,
                COUNT(DISTINCT district_id) as districts
            ')
            ->first();

        return [
            'reports' => (int) ($row['reports'] ?? 0),
            'participants' => (int) ($row['participants'] ?? 0),
            'villages' => (int) ($row['villages'] ?? 0),
            'cfas' => (int) ($row['cfas'] ?? 0),
            'outreach' => (int) ($row['outreach'] ?? 0),
            'coordinators' => (int) ($row['coordinators'] ?? 0),
            'districts' => (int) ($row['districts'] ?? 0),
        ];
    }

    /** @return list<array<string, mixed>> */
    private function buildDistrictSummaries(Builder $query): array
    {
        $rows = $query
            ->selectRaw('
                district_id,
                COUNT(*) as reports,
                COALESCE(SUM(participants_total), 0) as participants,
                COUNT(DISTINCT field_coordinator_user_id) as coordinators
            ')
            ->groupBy('district_id')
            ->orderByDesc('reports')
            ->get();

        if ($rows->isEmpty()) {
            return [];
        }

        $districtNames = District::query()
            ->whereIn('id', $rows->pluck('district_id')->filter()->all())
            ->pluck('name', 'id');

        $grandTotal = max(1, (int) $rows->sum('reports'));

        return $rows->map(function ($row) use ($districtNames, $grandTotal): array {
            $districtId = (int) ($row->district_id ?? 0);
            $reports = (int) $row->reports;

            return [
                'district_id' => $districtId,
                'district_name' => (string) ($districtNames[$districtId] ?? 'Unassigned'),
                'reports' => $reports,
                'participants' => (int) $row->participants,
                'coordinators' => (int) $row->coordinators,
                'share_pct' => (int) round(($reports / $grandTotal) * 100),
            ];
        })->values()->all();
    }

    /**
     * @param  list<int>  $coordinatorIds
     * @return array<string, int>
     */
    private function buildCfaByCoordinatorDate(array $coordinatorIds, ?string $from, ?string $to): array
    {
        if ($coordinatorIds === [] || ! Schema::hasTable('cfa_submissions')) {
            return [];
        }

        $query = DB::table('cfa_submissions')
            ->whereIn('referral_user_id', $coordinatorIds)
            ->selectRaw('referral_user_id, DATE(created_at) as visit_day, COUNT(*) as total')
            ->groupBy('referral_user_id', 'visit_day');

        if ($from) {
            $query->whereDate('created_at', '>=', $from);
        }
        if ($to) {
            $query->whereDate('created_at', '<=', $to);
        }

        $map = [];
        foreach ($query->get() as $row) {
            $key = (int) $row->referral_user_id.'|'.(string) $row->visit_day;
            $map[$key] = (int) $row->total;
        }

        return $map;
    }

    /** @return array<string, int> */
    private function emptyOverview(): array
    {
        return [
            'reports' => 0,
            'participants' => 0,
            'villages' => 0,
            'cfas' => 0,
            'outreach' => 0,
            'coordinators' => 0,
            'districts' => 0,
        ];
    }
}
