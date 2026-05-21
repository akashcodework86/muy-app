<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\Deliverable;
use App\Models\District;
use App\Models\DistrictDeliverableTarget;
use App\Models\DistrictServiceSpoc;
use App\Models\FiscalYear;
use App\Models\Hub;
use App\Models\StateDeliverableTarget;
use Carbon\Carbon;
use Illuminate\Pagination\LengthAwarePaginator;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use Illuminate\Http\Request;
use Illuminate\View\View;
use Symfony\Component\HttpFoundation\StreamedResponse;

class OnboardedApplicantController extends Controller
{
    public function index(Request $request): View
    {
        $scope = $this->resolveScope($request);
        [$hubId, $districtId, $q] = $this->extractFilters($request, $scope);

        $hubs = Hub::query()
            ->when($scope['hub_id'] !== null, fn ($query) => $query->where('id', $scope['hub_id']))
            ->orderBy('sort_order')
            ->orderBy('name')
            ->get(['id', 'name']);

        $districts = District::query()
            ->when($hubId, fn ($query) => $query->where('hub_id', $hubId))
            ->when($scope['district_ids'] !== null, fn ($query) => $query->whereIn('id', $scope['district_ids']))
            ->orderBy('name')
            ->get(['id', 'name', 'hub_id']);

        $commonColumns = $this->commonColumnMap();
        $mergedRows = $this->buildRows($hubId, $districtId, $q, $scope);
        $rows = $this->paginateMergedRows($mergedRows, $request, 30);
        $overview = $this->overviewStats($hubId, $districtId, $q, $scope);
        $districtSummaries = $this->districtSummaries($hubId, $q, $scope);
        $targetProgress = $this->targetProgress($hubId, $districtId, $scope);
        $sectorBreakdown = $this->sectorBreakdown($hubId, $districtId, $q, $scope);
        $insights = $this->buildInsights($overview, $districtSummaries, $targetProgress, $sectorBreakdown, $districtId);

        return view('admin.onboarded.index', [
            'rows' => $rows,
            'hubs' => $hubs,
            'districts' => $districts,
            'commonColumns' => $commonColumns,
            'overview' => $overview,
            'districtSummaries' => $districtSummaries,
            'targetProgress' => $targetProgress,
            'sectorBreakdown' => $sectorBreakdown,
            'insights' => $insights,
            'filters' => [
                'hub' => $hubId,
                'district' => $districtId,
                'q' => $q,
            ],
            'routeIndex' => $this->routeNameFor($request, 'index'),
            'routeExport' => $this->routeNameFor($request, 'export'),
        ]);
    }

    public function export(Request $request): StreamedResponse
    {
        $scope = $this->resolveScope($request);
        [$hubId, $districtId, $q] = $this->extractFilters($request, $scope);
        $commonColumns = $this->commonColumnMap();
        $rows = $this->buildRows($hubId, $districtId, $q, $scope);

        $headers = ['Sr No', 'Application No', 'Source', 'District', 'Hub', 'Batch', 'Onboarded At'];
        foreach ($commonColumns as $key => $meta) {
            if ($key === 'application_no') {
                continue;
            }
            $headers[] = (string) ($meta['label'] ?? $key);
        }

        $filename = 'onboarded-applicants-'.now()->format('Ymd_His').'.csv';

        return response()->streamDownload(function () use ($rows, $headers, $commonColumns): void {
            $out = fopen('php://output', 'w');
            if ($out === false) {
                return;
            }
            fwrite($out, "\xEF\xBB\xBF");
            fputcsv($out, $headers);
            foreach ($rows->values() as $idx => $row) {
                $record = [
                    (string) ($idx + 1),
                    (string) ($row['common_values']['application_no'] ?? $row['application_no'] ?? ''),
                    (string) (($row['data_source'] ?? '') === 'legacy_phase2' ? 'Legacy Phase 2' : 'Phase 3'),
                    (string) ($row['district'] ?? ''),
                    (string) ($row['hub_name'] ?? ''),
                    (string) ($row['onboarding_batch_name'] ?? ''),
                    (string) ($row['onboarded_at'] ?? ''),
                ];
                foreach (array_keys($commonColumns) as $columnKey) {
                    if ($columnKey === 'application_no') {
                        continue;
                    }
                    $record[] = (string) ($row['common_values'][$columnKey] ?? '');
                }
                fputcsv($out, $record);
            }
            fclose($out);
        }, $filename, [
            'Content-Type' => 'text/csv; charset=UTF-8',
        ]);
    }

    private function extractFilters(Request $request, array $scope): array
    {
        $hubId = $scope['hub_id'] ?? null;
        if ($hubId === null) {
            $hubId = $request->integer('hub') ?: null;
        }

        $districtId = $request->integer('district') ?: null;
        $allowedDistrictIds = $scope['district_ids'] ?? null;
        if (is_array($allowedDistrictIds)) {
            if ($districtId !== null && ! in_array($districtId, $allowedDistrictIds, true)) {
                $districtId = null;
            }
            if ($districtId === null && count($allowedDistrictIds) === 1) {
                $districtId = (int) $allowedDistrictIds[0];
            }
        }

        $q = trim((string) $request->string('q')->toString());

        return [$hubId, $districtId, $q];
    }

    private function resolveScope(Request $request): array
    {
        $user = $request->user();
        if (! $user) {
            return ['hub_id' => null, 'district_ids' => null];
        }

        if ($user->role === 'hub_admin') {
            return ['hub_id' => (int) $user->hub_id, 'district_ids' => null];
        }

        if ($user->role === 'district_staff') {
            return [
                'hub_id' => (int) $user->hub_id,
                'district_ids' => $user->district_id ? [(int) $user->district_id] : [],
            ];
        }

        if ($user->role === 'state_staff') {
            $ids = DistrictServiceSpoc::query()
                ->where('state_staff_user_id', (int) $user->id)
                ->pluck('district_id')
                ->map(fn ($id) => (int) $id)
                ->filter(fn (int $id) => $id > 0)
                ->unique()
                ->values()
                ->all();
            return ['hub_id' => null, 'district_ids' => $ids];
        }

        return ['hub_id' => null, 'district_ids' => null];
    }

    private function routeNameFor(Request $request, string $action): string
    {
        $role = $request->user()?->role;

        return match ($role) {
            'hub_admin' => 'hub.onboarded.'.$action,
            'district_staff' => 'staff.onboarded.'.$action,
            'state_staff' => 'spoc.onboarded.'.$action,
            default => 'admin.onboarded.'.$action,
        };
    }

    private function buildRows(?int $hubId, ?int $districtId, string $q, array $scope = []): Collection
    {
        $phase3Rows = $this->phase3Rows($hubId, $districtId, $q, $scope);
        $commonColumns = $this->commonColumnMap();

        return $phase3Rows
            ->map(fn (array $row) => $this->enrichRowWithDetails($row))
            ->map(fn (array $row) => $this->mapCommonColumnsForRow($row, $commonColumns))
            ->values();
    }

    private function phase3Rows(?int $hubId, ?int $districtId, string $q, array $scope = []): Collection
    {
        $query = $this->phase3BaseQuery($scope);
        $this->applyPhase3Filters($query, $hubId, $districtId, $q);

        $guardianJson = $this->payloadJson('$.guardian_name');
        $genderJson = $this->payloadJson('$.gender');
        $dobJson = $this->payloadJson('$.dob');
        $districtJson = $this->payloadJson('$.district');
        $blockJson = $this->payloadJson('$.block');

        return $query
            ->orderByDesc('obc.created_at')
            ->selectRaw("
                'phase3' as data_source,
                obc.id as onboarded_row_id,
                obc.created_at as onboarded_at,
                cs.id as application_id,
                cs.application_no as application_no,
                cs.source as source,
                cs.applicant_name as applicant_name,
                cs.phone as phone,
                cs.district_id as district_id,
                {$guardianJson} as guardian_name,
                {$genderJson} as gender,
                {$dobJson} as dob,
                COALESCE({$districtJson}, d.name) as district,
                {$blockJson} as block_name,
                h.name as hub_name,
                h.id as hub_id,
                ob.id as onboarding_batch_id,
                ob.name as onboarding_batch_name,
                cs.payload as full_details_json
            ")
            ->get()
            ->map(fn ($row) => (array) $row)
            ->pipe(fn (Collection $rows) => $this->replacePhase3LegacySourceDetails($rows));
    }

    private function phase3BaseQuery(array $scope = [])
    {
        $query = DB::table('onboarding_batch_cfa as obc')
            ->join('onboarding_batches as ob', 'ob.id', '=', 'obc.onboarding_batch_id')
            ->join('cfa_submissions as cs', 'cs.id', '=', 'obc.cfa_submission_id')
            ->leftJoin('districts as d', 'd.id', '=', 'cs.district_id')
            ->leftJoin('hubs as h', 'h.id', '=', 'ob.hub_id')
            ->leftJoin('users as u', 'u.id', '=', 'cs.referral_user_id')
            ->where('ob.status', 'locked')
            ->whereNotNull('ob.locked_at')
            ->whereNotNull('obc.cfa_submission_id');

        $this->applyScopeDistrictFilter($query, $scope);

        return $query;
    }

    private function applyScopeDistrictFilter($query, array $scope): void
    {
        $allowedDistrictIds = $scope['district_ids'] ?? null;
        if (! is_array($allowedDistrictIds)) {
            return;
        }

        if ($allowedDistrictIds === []) {
            $query->whereRaw('1 = 0');

            return;
        }

        $query->whereIn('cs.district_id', $allowedDistrictIds);
    }

    private function applyPhase3Filters($query, ?int $hubId, ?int $districtId, string $q): void
    {
        if ($hubId) {
            $query->where('ob.hub_id', $hubId);
        }
        if ($districtId) {
            $query->where('cs.district_id', $districtId);
        }
        if ($q !== '') {
            $like = '%'.$q.'%';
            $query->where(function ($filter) use ($like, $q): void {
                $filter->where('cs.application_no', 'like', $like)
                    ->orWhere('cs.applicant_name', 'like', $like)
                    ->orWhere('cs.phone', 'like', $like)
                    ->orWhere('ob.name', 'like', $like);
                if (ctype_digit($q)) {
                    $id = (int) $q;
                    $filter->orWhere('cs.id', $id)
                        ->orWhere('ob.id', $id)
                        ->orWhere('obc.id', $id);
                }
            });
        }
    }

    private function overviewStats(?int $hubId, ?int $districtId, string $q, array $scope): array
    {
        $sevenDaysAgo = now()->subDays(7)->toDateTimeString();
        $monthStart = now()->startOfMonth()->toDateTimeString();

        $query = $this->phase3BaseQuery($scope);
        $this->applyPhase3Filters($query, $hubId, $districtId, $q);

        $genderJson = $this->payloadJson('$.gender');
        $lakhpatiYesSql = $this->lakhpatiYesCountSql();
        $potentialLakhpatiSql = $this->potentialLakhpatiCountSql();

        $row = (array) $query
            ->selectRaw("
                COUNT(*) as total,
                COUNT(DISTINCT cs.district_id) as districts_covered,
                COUNT(DISTINCT ob.hub_id) as hubs_covered,
                SUM(CASE
                    WHEN LOWER(TRIM(COALESCE({$genderJson}, ''))) IN ('female', 'f', 'woman')
                    THEN 1 ELSE 0
                END) as female_count,
                SUM(CASE
                    WHEN LOWER(TRIM(COALESCE({$genderJson}, ''))) IN ('male', 'm', 'man')
                    THEN 1 ELSE 0
                END) as male_count,
                {$lakhpatiYesSql} as lakhpati_yes_count,
                {$potentialLakhpatiSql} as potential_lakhpati_count,
                SUM(CASE WHEN obc.created_at >= ? THEN 1 ELSE 0 END) as recent_7_days,
                SUM(CASE WHEN obc.created_at >= ? THEN 1 ELSE 0 END) as this_month
            ", [$sevenDaysAgo, $monthStart])
            ->first();

        $total = (int) ($row['total'] ?? 0);
        $female = (int) ($row['female_count'] ?? 0);
        $male = (int) ($row['male_count'] ?? 0);
        $knownGender = $female + $male;
        $lakhpatiYes = (int) ($row['lakhpati_yes_count'] ?? 0);
        $potentialLakhpati = (int) ($row['potential_lakhpati_count'] ?? 0);

        return [
            'total' => $total,
            'districts_covered' => (int) ($row['districts_covered'] ?? 0),
            'hubs_covered' => (int) ($row['hubs_covered'] ?? 0),
            'female_count' => $female,
            'male_count' => $male,
            'female_pct' => $knownGender > 0 ? (int) round(($female / $knownGender) * 100) : null,
            'lakhpati_yes_count' => $lakhpatiYes,
            'lakhpati_yes_pct' => $total > 0 ? (int) round(($lakhpatiYes / $total) * 100) : null,
            'potential_lakhpati_count' => $potentialLakhpati,
            'potential_lakhpati_pct' => $total > 0 ? (int) round(($potentialLakhpati / $total) * 100) : null,
            'recent_7_days' => (int) ($row['recent_7_days'] ?? 0),
            'this_month' => (int) ($row['this_month'] ?? 0),
        ];
    }

    /** @return list<array<string, mixed>> */
    private function districtSummaries(?int $hubId, string $q, array $scope): array
    {
        $sevenDaysAgo = now()->subDays(7)->toDateTimeString();

        $query = $this->phase3BaseQuery($scope);
        $this->applyPhase3Filters($query, $hubId, null, $q);

        $genderJson = $this->payloadJson('$.gender');
        $districtJson = $this->payloadJson('$.district');
        $lakhpatiYesSql = $this->lakhpatiYesCountSql();
        $potentialLakhpatiSql = $this->potentialLakhpatiCountSql();

        $rows = $query
            ->selectRaw("
                cs.district_id as district_id,
                MAX(COALESCE(d.name, {$districtJson}, 'Unassigned')) as district_name,
                MAX(h.name) as hub_name,
                COUNT(*) as total,
                SUM(CASE
                    WHEN LOWER(TRIM(COALESCE({$genderJson}, ''))) IN ('female', 'f', 'woman')
                    THEN 1 ELSE 0
                END) as female_count,
                {$lakhpatiYesSql} as lakhpati_yes_count,
                {$potentialLakhpatiSql} as potential_lakhpati_count,
                SUM(CASE WHEN obc.created_at >= ? THEN 1 ELSE 0 END) as recent_7_days,
                MAX(obc.created_at) as last_onboarded_at
            ", [$sevenDaysAgo])
            ->groupBy('cs.district_id')
            ->orderByDesc('total')
            ->get();

        $grandTotal = max(1, (int) $rows->sum('total'));
        $districtIds = $rows
            ->pluck('district_id')
            ->map(fn ($id) => (int) $id)
            ->filter(fn (int $id) => $id > 0)
            ->values()
            ->all();
        $targetsByDistrict = $this->onboardingTargetsByDistrictIds($districtIds);

        return $rows
            ->map(function ($row) use ($grandTotal, $targetsByDistrict): array {
                $total = (int) $row->total;
                $female = (int) $row->female_count;
                $lakhpatiYes = (int) ($row->lakhpati_yes_count ?? 0);
                $potentialLakhpati = (int) ($row->potential_lakhpati_count ?? 0);
                $districtId = (int) ($row->district_id ?? 0);
                $target = (int) ($targetsByDistrict[$districtId] ?? 0);

                return [
                    'district_id' => $districtId,
                    'district_name' => (string) $row->district_name,
                    'hub_name' => (string) ($row->hub_name ?? ''),
                    'total' => $total,
                    'female_count' => $female,
                    'female_pct' => $total > 0 ? (int) round(($female / $total) * 100) : 0,
                    'lakhpati_yes_count' => $lakhpatiYes,
                    'lakhpati_yes_pct' => $total > 0 ? (int) round(($lakhpatiYes / $total) * 100) : 0,
                    'potential_lakhpati_count' => $potentialLakhpati,
                    'potential_lakhpati_pct' => $total > 0 ? (int) round(($potentialLakhpati / $total) * 100) : 0,
                    'target' => $target,
                    'target_progress_pct' => $target > 0 ? (int) round(($total / $target) * 100) : null,
                    'target_gap' => $target > 0 ? max(0, $target - $total) : null,
                    'recent_7_days' => (int) ($row->recent_7_days ?? 0),
                    'share_pct' => (int) round(($total / $grandTotal) * 100),
                    'last_onboarded_at' => $row->last_onboarded_at,
                ];
            })
            ->values()
            ->all();
    }

    private function legacyRows(?int $hubId, ?int $districtId, string $q): Collection
    {
        $legacyDbConfigured = (string) config('database.connections.legacy.database', '') !== '';
        if (! $legacyDbConfigured) {
            return collect();
        }

        try {
            $hasTables = DB::connection('legacy')->getSchemaBuilder()->hasTable('rbi_onboarded_applicants')
                && DB::connection('legacy')->getSchemaBuilder()->hasTable('rbi_applications')
                && DB::connection('legacy')->getSchemaBuilder()->hasTable('rbi_applicant_details');
        } catch (\Throwable) {
            return collect();
        }

        if (! $hasTables) {
            return collect();
        }

        $query = DB::connection('legacy')
            ->table('rbi_onboarded_applicants as oa')
            ->leftJoin('rbi_onboarding_batches as ob', 'ob.id', '=', 'oa.onboarding_batch_id')
            ->leftJoin('rbi_applications as a', 'a.id', '=', 'oa.application_id')
            ->leftJoin('rbi_applicant_details as d', 'd.application_id', '=', 'oa.application_id')
            ->whereNotNull('oa.application_id');

        $legacyDistrictNames = $this->legacyDistrictNamesForFilters($hubId, $districtId);
        if ($legacyDistrictNames !== null) {
            if ($legacyDistrictNames === []) {
                return collect();
            }
            $query->whereIn('d.district', $legacyDistrictNames);
        }

        if ($q !== '') {
            $like = '%'.$q.'%';
            $query->where(function ($filter) use ($like, $q): void {
                $filter->where('a.application_no', 'like', $like)
                    ->orWhere('d.applicant_name', 'like', $like)
                    ->orWhere('d.phone', 'like', $like)
                    ->orWhere('ob.batch_name', 'like', $like);
                if (ctype_digit($q)) {
                    $id = (int) $q;
                    $filter->orWhere('a.id', $id)
                        ->orWhere('oa.id', $id)
                        ->orWhere('oa.onboarding_batch_id', $id);
                }
            });
        }

        return $query
            ->orderByDesc('oa.id')
            ->selectRaw("
                'legacy_phase2' as data_source,
                oa.id as onboarded_row_id,
                a.submission_date as onboarded_at,
                a.id as application_id,
                a.application_no as application_no,
                d.applicant_name as applicant_name,
                d.phone as phone,
                d.guardian_name as guardian_name,
                d.gender as gender,
                d.dob as dob,
                d.district as district,
                d.block as block_name,
                NULL as hub_name,
                ob.id as onboarding_batch_id,
                ob.batch_name as onboarding_batch_name,
                JSON_OBJECT(
                    'rbi_applications', JSON_OBJECT(
                        'id', a.id,
                        'application_no', a.application_no,
                        'form_stage', a.form_stage,
                        'business_category', a.business_category,
                        'submission_date', a.submission_date
                    ),
                    'rbi_applicant_details', JSON_OBJECT(
                        'id', d.id,
                        'application_id', d.application_id,
                        'applicant_name', d.applicant_name,
                        'guardian_name', d.guardian_name,
                        'gender', d.gender,
                        'dob', d.dob,
                        'education', d.education,
                        'phone', d.phone,
                        'alt_mobile', d.alt_mobile,
                        'email', d.email,
                        'caste', d.caste,
                        'is_shg_member', d.is_shg_member,
                        'shg_name', d.shg_name,
                        'lakhpati', d.lakhpati,
                        'district', d.district,
                        'block', d.block,
                        'pincode', d.pincode,
                        'village', d.village,
                        'loan_taken', d.loan_taken,
                        'bank_loan', d.bank_loan,
                        'current_employment', d.current_employment,
                        'employed_count', d.employed_count,
                        'id_proof_type', d.id_proof_type,
                        'id_proof_number', d.id_proof_number,
                        'expectations', d.expectations,
                        'training_mode', d.training_mode,
                        'challenges', d.challenges,
                        'expectation_other', d.expectation_other,
                        'migrated_for_employment', d.migrated_for_employment,
                        'submitted_by_name', d.submitted_by_name,
                        'submitted_by_mobile', d.submitted_by_mobile,
                        'department_name', d.department_name,
                        'info_source', d.info_source
                    )
                ) as full_details_json
            ")
            ->get()
            ->map(fn ($row) => (array) $row);
    }

    private function legacyDistrictNamesForFilters(?int $hubId, ?int $districtId): ?array
    {
        if (! $hubId && ! $districtId) {
            return null;
        }

        $districtModels = District::query()
            ->when($districtId, fn ($query) => $query->where('id', $districtId))
            ->when($hubId && ! $districtId, fn ($query) => $query->where('hub_id', $hubId))
            ->get(['name']);

        $aliasesMap = (array) config('legacy_phase2.staff_import.district_aliases', []);
        $names = [];
        foreach ($districtModels as $districtModel) {
            $canonical = trim((string) $districtModel->name);
            if ($canonical === '') {
                continue;
            }
            $names[] = $canonical;
            foreach ((array) ($aliasesMap[$canonical] ?? []) as $alias) {
                $alias = trim((string) $alias);
                if ($alias !== '') {
                    $names[] = $alias;
                }
            }
        }

        return array_values(array_unique($names));
    }

    private function paginateMergedRows(Collection $rows, Request $request, int $perPage): LengthAwarePaginator
    {
        $sorted = $rows->sortByDesc(function (array $row) {
            $value = (string) ($row['onboarded_at'] ?? '');
            return strtotime($value) ?: 0;
        })->values();

        $page = max(1, (int) $request->query('page', 1));
        $items = $sorted->slice(($page - 1) * $perPage, $perPage)->values();

        return new LengthAwarePaginator(
            $items,
            $sorted->count(),
            $perPage,
            $page,
            [
                'path' => $request->url(),
                'query' => $request->query(),
            ]
        );
    }

    private function enrichRowWithDetails(array $row): array
    {
        $detailsRaw = [];
        $decoded = json_decode((string) ($row['full_details_json'] ?? ''), true);
        if (! is_array($decoded)) {
            $decoded = [];
        }

        if (isset($decoded['rbi_applicant_details']) && is_array($decoded['rbi_applicant_details'])) {
            foreach ($decoded['rbi_applicant_details'] as $key => $value) {
                $detailsRaw[(string) $key] = $value;
            }
            if (isset($decoded['rbi_applications']) && is_array($decoded['rbi_applications'])) {
                foreach ($decoded['rbi_applications'] as $key => $value) {
                    $detailsRaw['app_'.$key] = $value;
                }
            }
        } else {
            foreach ($decoded as $key => $value) {
                $detailsRaw[(string) $key] = $value;
            }
        }

        $details = [];
        foreach ($detailsRaw as $key => $value) {
            $cleanKey = trim((string) $key);
            if ($cleanKey === '') {
                continue;
            }
            $details[$cleanKey] = $this->normalizeDetailValue($value);
        }

        ksort($details);
        $row['detail_values'] = $details;

        return $row;
    }

    private function normalizeDetailValue(mixed $value): string
    {
        if ($value === null) {
            return '—';
        }
        if (is_bool($value)) {
            return $value ? 'Yes' : 'No';
        }
        if (is_scalar($value)) {
            $text = trim((string) $value);
            return $text !== '' ? $text : '—';
        }

        $encoded = json_encode($value, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
        if (! is_string($encoded) || $encoded === '') {
            return '—';
        }

        return $encoded;
    }

    private function commonColumnMap(): array
    {
        return [
            'application_no' => ['label' => 'Application No', 'keys' => ['application_no', 'app_application_no']],
            'applicant_name' => ['label' => 'Applicant Name', 'keys' => ['applicant_name']],
            'guardian_name' => ['label' => 'Guardian Name', 'keys' => ['guardian_name']],
            'gender' => ['label' => 'Gender', 'keys' => ['gender']],
            'dob' => ['label' => 'DOB', 'keys' => ['dob']],
            'phone' => ['label' => 'Phone', 'keys' => ['phone']],
            'alt_mobile' => ['label' => 'Alt Mobile', 'keys' => ['alt_mobile']],
            'email' => ['label' => 'Email', 'keys' => ['email']],
            'category' => ['label' => 'Category', 'keys' => ['category']],
            'caste' => ['label' => 'Caste', 'keys' => ['caste']],
            'education' => ['label' => 'Education', 'keys' => ['education']],
            'district' => ['label' => 'District', 'keys' => ['district']],
            'block' => ['label' => 'Block', 'keys' => ['block', 'block_name']],
            'village' => ['label' => 'Village', 'keys' => ['village']],
            'pincode' => ['label' => 'Pincode', 'keys' => ['pincode']],
            'is_shg_member' => ['label' => 'Is SHG Member', 'keys' => ['is_shg_member', 'is_member']],
            'shg_name' => ['label' => 'SHG Name', 'keys' => ['shg_name']],
            'lakhpati' => ['label' => 'Lakhpati', 'keys' => ['lakhpati']],
            'id_proof_type' => ['label' => 'ID Proof Type', 'keys' => ['id_proof_type']],
            'id_proof_number' => ['label' => 'ID Proof Number', 'keys' => ['id_proof_number']],
            'loan_taken' => ['label' => 'Loan Taken', 'keys' => ['loan_taken']],
            'bank_loan' => ['label' => 'Bank Loan', 'keys' => ['bank_loan']],
            'current_employment' => ['label' => 'Current Employment', 'keys' => ['current_employment']],
            'employed_count' => ['label' => 'Employed Count', 'keys' => ['employed_count']],
            'business_category' => ['label' => 'Business Category', 'keys' => ['business_category', 'app_business_category']],
            'product' => ['label' => 'Product', 'keys' => ['product']],
            'form_stage' => ['label' => 'Form Stage', 'keys' => ['form_stage', 'app_form_stage']],
            'techuse' => ['label' => 'Tech Use', 'keys' => ['techuse']],
            'empwomen' => ['label' => 'Emp Women', 'keys' => ['empwomen']],
            'sustainability' => ['label' => 'Sustainability', 'keys' => ['sustainability']],
            'training_mode' => ['label' => 'Training Mode', 'keys' => ['training_mode']],
            'training_received' => ['label' => 'Training Received', 'keys' => ['training_received']],
            'challenges' => ['label' => 'Challenges', 'keys' => ['challenges']],
            'expectations' => ['label' => 'Expectations', 'keys' => ['expectations']],
            'expectation_other' => ['label' => 'Expectation Other', 'keys' => ['expectation_other', 'expectation_other_text']],
            'migrated_for_employment' => ['label' => 'Migrated For Employment', 'keys' => ['migrated_for_employment']],
            'submitted_by_name' => ['label' => 'Submitted By Name', 'keys' => ['submitted_by_name', 'referral_staff_name']],
            'submitted_by_mobile' => ['label' => 'Submitted By Mobile', 'keys' => ['submitted_by_mobile']],
            'department_name' => ['label' => 'Department Name', 'keys' => ['department_name']],
            'info_source' => ['label' => 'Info Source', 'keys' => ['info_source']],
            'resource_name' => ['label' => 'Resource Name', 'keys' => ['resource_name']],
            'submission_date' => ['label' => 'Submission Date', 'keys' => ['submission_date', 'submitted_at', 'app_submission_date']],
        ];
    }

    private function mapCommonColumnsForRow(array $row, array $commonColumns): array
    {
        $detailValues = (array) ($row['detail_values'] ?? []);
        $mapped = [];
        foreach ($commonColumns as $canonicalKey => $meta) {
            $value = '—';
            foreach ((array) ($meta['keys'] ?? []) as $candidate) {
                if (array_key_exists($candidate, $detailValues) && $detailValues[$candidate] !== '—') {
                    $value = (string) $detailValues[$candidate];
                    break;
                }
                if (array_key_exists($candidate, $row) && (string) $row[$candidate] !== '') {
                    $value = (string) $row[$candidate];
                    break;
                }
            }
            $mapped[$canonicalKey] = $value;
        }
        $row['common_values'] = $mapped;

        return $row;
    }

    private function replacePhase3LegacySourceDetails(Collection $rows): Collection
    {
        $legacyDbConfigured = (string) config('database.connections.legacy.database', '') !== '';
        if (! $legacyDbConfigured || $rows->isEmpty()) {
            return $rows;
        }

        try {
            $schema = DB::connection('legacy')->getSchemaBuilder();
            $hasTables = $schema->hasTable('rbi_applications') && $schema->hasTable('rbi_applicant_details');
        } catch (\Throwable) {
            return $rows;
        }

        if (! $hasTables) {
            return $rows;
        }

        $targets = $rows->filter(function (array $row): bool {
            $source = strtolower(trim((string) ($row['source'] ?? '')));
            return in_array($source, ['legacy_phase2', 'rbiphase2'], true);
        })->values();

        if ($targets->isEmpty()) {
            return $rows;
        }

        $legacyIds = [];
        $applicationNos = [];
        foreach ($targets as $row) {
            $payload = json_decode((string) ($row['full_details_json'] ?? ''), true);
            if (is_array($payload)) {
                $legacyId = (int) ($payload['legacy_application_id'] ?? 0);
                if ($legacyId > 0) {
                    $legacyIds[] = $legacyId;
                }
            }
            $appNo = trim((string) ($row['application_no'] ?? ''));
            if ($appNo !== '') {
                $applicationNos[] = $appNo;
            }
        }
        $legacyIds = array_values(array_unique($legacyIds));
        $applicationNos = array_values(array_unique($applicationNos));

        $legacyById = collect();
        $legacyByAppNo = collect();

        if ($legacyIds !== []) {
            $legacyById = DB::connection('legacy')
                ->table('rbi_applicant_details as d')
                ->leftJoin('rbi_applications as a', 'a.id', '=', 'd.application_id')
                ->whereIn('d.application_id', $legacyIds)
                ->select([
                    'd.*',
                    'a.id as app_id',
                    'a.application_no',
                    'a.form_stage',
                    'a.business_category',
                    'a.submission_date',
                ])
                ->get()
                ->keyBy('application_id');
        }

        if ($applicationNos !== []) {
            $legacyByAppNo = DB::connection('legacy')
                ->table('rbi_applicant_details as d')
                ->leftJoin('rbi_applications as a', 'a.id', '=', 'd.application_id')
                ->whereIn('a.application_no', $applicationNos)
                ->select([
                    'd.*',
                    'a.id as app_id',
                    'a.application_no',
                    'a.form_stage',
                    'a.business_category',
                    'a.submission_date',
                ])
                ->get()
                ->keyBy('application_no');
        }

        return $rows->map(function (array $row) use ($legacyById, $legacyByAppNo): array {
            $source = strtolower(trim((string) ($row['source'] ?? '')));
            if (! in_array($source, ['legacy_phase2', 'rbiphase2'], true)) {
                return $row;
            }

            $payload = json_decode((string) ($row['full_details_json'] ?? ''), true);
            $legacyId = is_array($payload) ? (int) ($payload['legacy_application_id'] ?? 0) : 0;
            $appNo = trim((string) ($row['application_no'] ?? ''));

            $legacy = null;
            if ($legacyId > 0) {
                $legacy = $legacyById->get($legacyId);
            }
            if ($legacy === null && $appNo !== '') {
                $legacy = $legacyByAppNo->get($appNo);
            }
            if ($legacy === null) {
                return $row;
            }

            $legacyArr = (array) $legacy;
            $row['applicant_name'] = (string) ($legacyArr['applicant_name'] ?? $row['applicant_name'] ?? '');
            $row['phone'] = (string) ($legacyArr['phone'] ?? $row['phone'] ?? '');
            $row['guardian_name'] = (string) ($legacyArr['guardian_name'] ?? $row['guardian_name'] ?? '');
            $row['gender'] = (string) ($legacyArr['gender'] ?? $row['gender'] ?? '');
            $row['dob'] = (string) ($legacyArr['dob'] ?? $row['dob'] ?? '');
            $row['district'] = (string) ($legacyArr['district'] ?? $row['district'] ?? '');
            $row['block_name'] = (string) ($legacyArr['block'] ?? $row['block_name'] ?? '');
            $row['onboarded_at'] = (string) ($legacyArr['submission_date'] ?? $row['onboarded_at'] ?? '');

            $row['full_details_json'] = json_encode([
                'rbi_applications' => [
                    'id' => $legacyArr['app_id'] ?? null,
                    'application_no' => $legacyArr['application_no'] ?? null,
                    'form_stage' => $legacyArr['form_stage'] ?? null,
                    'business_category' => $legacyArr['business_category'] ?? null,
                    'submission_date' => $legacyArr['submission_date'] ?? null,
                ],
                'rbi_applicant_details' => [
                    'id' => $legacyArr['id'] ?? null,
                    'application_id' => $legacyArr['application_id'] ?? null,
                    'applicant_name' => $legacyArr['applicant_name'] ?? null,
                    'guardian_name' => $legacyArr['guardian_name'] ?? null,
                    'gender' => $legacyArr['gender'] ?? null,
                    'dob' => $legacyArr['dob'] ?? null,
                    'education' => $legacyArr['education'] ?? null,
                    'phone' => $legacyArr['phone'] ?? null,
                    'alt_mobile' => $legacyArr['alt_mobile'] ?? null,
                    'email' => $legacyArr['email'] ?? null,
                    'caste' => $legacyArr['caste'] ?? null,
                    'is_shg_member' => $legacyArr['is_shg_member'] ?? null,
                    'shg_name' => $legacyArr['shg_name'] ?? null,
                    'lakhpati' => $legacyArr['lakhpati'] ?? null,
                    'district' => $legacyArr['district'] ?? null,
                    'block' => $legacyArr['block'] ?? null,
                    'pincode' => $legacyArr['pincode'] ?? null,
                    'village' => $legacyArr['village'] ?? null,
                    'loan_taken' => $legacyArr['loan_taken'] ?? null,
                    'bank_loan' => $legacyArr['bank_loan'] ?? null,
                    'current_employment' => $legacyArr['current_employment'] ?? null,
                    'employed_count' => $legacyArr['employed_count'] ?? null,
                    'id_proof_type' => $legacyArr['id_proof_type'] ?? null,
                    'id_proof_number' => $legacyArr['id_proof_number'] ?? null,
                    'expectations' => $legacyArr['expectations'] ?? null,
                    'training_mode' => $legacyArr['training_mode'] ?? null,
                    'challenges' => $legacyArr['challenges'] ?? null,
                    'expectation_other' => $legacyArr['expectation_other'] ?? null,
                    'migrated_for_employment' => $legacyArr['migrated_for_employment'] ?? null,
                    'submitted_by_name' => $legacyArr['submitted_by_name'] ?? null,
                    'submitted_by_mobile' => $legacyArr['submitted_by_mobile'] ?? null,
                    'department_name' => $legacyArr['department_name'] ?? null,
                    'info_source' => $legacyArr['info_source'] ?? null,
                    'resource_name' => $legacyArr['resource_name'] ?? null,
                ],
            ], JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);

            return $row;
        });
    }

    private function payloadJson(string $path): string
    {
        if (DB::connection()->getDriverName() === 'sqlite') {
            return "json_extract(cs.payload, '{$path}')";
        }

        return "JSON_UNQUOTE(JSON_EXTRACT(cs.payload, '{$path}'))";
    }

    private function lakhpatiYesCountSql(): string
    {
        $lakhpatiJson = $this->payloadJson('$.lakhpati');

        return "SUM(CASE
            WHEN {$this->phase3CfaSourceSql()}
                AND LOWER(TRIM(COALESCE({$lakhpatiJson}, ''))) = 'yes'
            THEN 1 ELSE 0
        END)";
    }

    private function potentialLakhpatiCountSql(): string
    {
        $categoryJson = $this->payloadJson('$.category');
        $appCategoryJson = $this->payloadJson('$.app_category');
        $lakhpatiJson = $this->payloadJson('$.lakhpati');
        $isMemberJson = $this->payloadJson('$.is_member');
        $isShgMemberJson = $this->payloadJson('$.is_shg_member');

        return "SUM(CASE
            WHEN {$this->phase3CfaSourceSql()}
                AND (
                    LOWER(TRIM(COALESCE({$categoryJson}, ''))) IN ('shg', 'cbo')
                    OR LOWER(TRIM(COALESCE({$appCategoryJson}, ''))) IN ('shg', 'cbo')
                    OR LOWER(TRIM(COALESCE({$lakhpatiJson}, ''))) = 'yes'
                    OR LOWER(TRIM(COALESCE({$isMemberJson}, ''))) = 'yes'
                    OR LOWER(TRIM(COALESCE({$isShgMemberJson}, ''))) = 'yes'
                )
            THEN 1 ELSE 0
        END)";
    }

    private function phase3CfaSourceSql(): string
    {
        return "LOWER(TRIM(COALESCE(cs.source, ''))) NOT IN ('legacy_phase2', 'rbiphase2')";
    }

    private function onboardingAchievedCount(?int $hubId, ?int $districtId, array $scope): int
    {
        $query = $this->phase3BaseQuery($scope);
        $this->applyPhase3Filters($query, $hubId, $districtId, '');

        return (int) $query->count();
    }

    /** @return list<int> */
    private function resolveTargetDistrictIds(?int $hubId, ?int $districtId, array $scope): array
    {
        if ($districtId) {
            return [$districtId];
        }

        $query = District::query()->orderBy('id');
        if ($hubId) {
            $query->where('hub_id', $hubId);
        }
        $allowedDistrictIds = $scope['district_ids'] ?? null;
        if (is_array($allowedDistrictIds)) {
            $query->whereIn('id', $allowedDistrictIds);
        }

        return $query
            ->pluck('id')
            ->map(fn ($id) => (int) $id)
            ->filter(fn (int $id) => $id > 0)
            ->values()
            ->all();
    }

    /** @return array<string, mixed> */
    private function targetProgress(?int $hubId, ?int $districtId, array $scope): array
    {
        $achieved = $this->onboardingAchievedCount($hubId, $districtId, $scope);
        $districtIds = $this->resolveTargetDistrictIds($hubId, $districtId, $scope);
        $onboardingDeliverableId = Deliverable::onboardingTargetDeliverableId();
        $activeFy = FiscalYear::phase3Default();

        $empty = [
            'configured' => false,
            'achieved' => $achieved,
            'target' => null,
            'progress_pct' => null,
            'gap' => null,
            'label' => 'Onboarding target',
            'fiscal_year' => null,
            'expected_pct_by_now' => null,
            'pace_delta' => null,
        ];

        if ($onboardingDeliverableId === null || ! $activeFy || $districtIds === []) {
            return $empty;
        }

        $target = 0;
        $label = 'Onboarding target';

        if ($districtId && count($districtIds) === 1) {
            $target = (int) ($this->onboardingTargetsByDistrictIds($districtIds)[$districtId] ?? 0);
            $districtName = District::query()->whereKey($districtId)->value('name');
            $label = ($districtName ? $districtName.' — ' : '').'district onboarding target';
        } elseif ($hubId) {
            $target = (int) $this->onboardingTargetsByDistrictIds($districtIds)->sum();
            $hubName = Hub::query()->whereKey($hubId)->value('name');
            $label = ($hubName ? $hubName.' — ' : '').'hub onboarding target';
        } elseif (is_array($scope['district_ids'] ?? null)) {
            $target = (int) $this->onboardingTargetsByDistrictIds($districtIds)->sum();
            $label = 'Scoped districts onboarding target';
        } else {
            $stateTarget = (int) StateDeliverableTarget::query()
                ->where('fiscal_year_id', (int) $activeFy->id)
                ->where('deliverable_id', $onboardingDeliverableId)
                ->value('target_total');
            $districtSum = (int) $this->onboardingTargetsByDistrictIds(
                District::query()->pluck('id')->map(fn ($id) => (int) $id)->all()
            )->sum();
            $target = $stateTarget > 0 ? $stateTarget : $districtSum;
            $label = 'State onboarding target';
        }

        if ($target <= 0) {
            return array_merge($empty, [
                'label' => $label,
                'fiscal_year' => (string) ($activeFy->code ?? $activeFy->name),
            ]);
        }

        $expectedPctByNow = $this->expectedFyProgressPctByNow($activeFy);
        $expectedAchievedByNow = $expectedPctByNow !== null
            ? (int) round(($target * $expectedPctByNow) / 100)
            : null;
        $paceDelta = $expectedAchievedByNow !== null ? ($achieved - $expectedAchievedByNow) : null;

        return [
            'configured' => true,
            'achieved' => $achieved,
            'target' => $target,
            'progress_pct' => (int) round(($achieved / $target) * 100),
            'gap' => max(0, $target - $achieved),
            'label' => $label,
            'fiscal_year' => (string) ($activeFy->code ?? $activeFy->name),
            'expected_pct_by_now' => $expectedPctByNow,
            'pace_delta' => $paceDelta,
        ];
    }

    /** @return \Illuminate\Support\Collection<int|string, int> */
    private function onboardingTargetsByDistrictIds(array $districtIds): Collection
    {
        if ($districtIds === []) {
            return collect();
        }

        $onboardingDeliverableId = Deliverable::onboardingTargetDeliverableId();
        $activeFy = FiscalYear::phase3Default();
        if ($onboardingDeliverableId === null || ! $activeFy) {
            return collect();
        }

        return DistrictDeliverableTarget::query()
            ->where('fiscal_year_id', (int) $activeFy->id)
            ->where('deliverable_id', $onboardingDeliverableId)
            ->whereIn('district_id', $districtIds)
            ->selectRaw('district_id, SUM(target_total) as target_total')
            ->groupBy('district_id')
            ->pluck('target_total', 'district_id')
            ->map(fn ($total) => (int) $total);
    }

    /** @return array<string, mixed> */
    private function sectorBreakdown(?int $hubId, ?int $districtId, string $q, array $scope): array
    {
        $query = $this->phase3BaseQuery($scope);
        $this->applyPhase3Filters($query, $hubId, $districtId, $q);
        $query->whereRaw($this->phase3CfaSourceSql());

        $category = $this->payloadJson('$.business_category');
        $appCategory = $this->payloadJson('$.app_business_category');

        $counts = $query
            ->selectRaw("{$category} as sector_category, {$appCategory} as sector_app_category")
            ->get()
            ->map(fn ($row) => $this->resolveSectorLabel($row))
            ->countBy()
            ->sortDesc();

        $grandTotal = max(1, (int) $counts->sum());
        $topLimit = 6;
        $topRows = $counts->take($topLimit);
        $otherTotal = (int) $counts->slice($topLimit)->sum();

        $mapped = $topRows
            ->map(fn (int $total, string $sector) => [
                'sector' => $sector,
                'count' => $total,
                'pct' => (int) round(($total / $grandTotal) * 100),
            ])
            ->values()
            ->all();

        if ($otherTotal > 0) {
            $mapped[] = [
                'sector' => 'Other sectors',
                'count' => $otherTotal,
                'pct' => (int) round(($otherTotal / $grandTotal) * 100),
            ];
        }

        return [
            'total' => (int) $counts->sum(),
            'rows' => $mapped,
        ];
    }

    private function resolveSectorLabel(object $row): string
    {
        $label = trim((string) ($row->sector_category ?? ''));
        if ($label === '') {
            $label = trim((string) ($row->sector_app_category ?? ''));
        }

        return $label !== '' ? $label : 'Not recorded';
    }

    /**
     * @param  list<array<string, mixed>>  $districtSummaries
     * @param  array<string, mixed>  $targetProgress
     * @param  array<string, mixed>  $sectorBreakdown
     * @return list<string>
     */
    private function buildInsights(
        array $overview,
        array $districtSummaries,
        array $targetProgress,
        array $sectorBreakdown,
        ?int $districtId,
    ): array {
        $insights = [];

        if (($targetProgress['configured'] ?? false) && (int) ($targetProgress['target'] ?? 0) > 0) {
            $achieved = (int) ($targetProgress['achieved'] ?? 0);
            $target = (int) $targetProgress['target'];
            $progressPct = (int) ($targetProgress['progress_pct'] ?? 0);
            $gap = (int) ($targetProgress['gap'] ?? 0);
            $paceDelta = $targetProgress['pace_delta'] ?? null;

            if ($progressPct >= 100) {
                $insights[] = "FY onboarding target achieved ({$achieved} of {$target}).";
            } elseif ($gap > 0) {
                $insights[] = "{$gap} more onboardings needed to reach the FY target ({$progressPct}% complete).";
            }

            if ($paceDelta !== null) {
                if ($paceDelta >= 0 && $progressPct < 100) {
                    $insights[] = 'On track for FY timeline — achieved count meets expected pace for this point in the year.';
                } elseif ($paceDelta < 0) {
                    $behind = abs((int) $paceDelta);
                    $insights[] = "Behind FY pace by about {$behind} onboardings compared with expected progress today.";
                }
            }
        } elseif (! ($targetProgress['configured'] ?? false)) {
            $insights[] = 'Onboarding target is not configured for this view — set state/district targets to track progress.';
        }

        if ($districtId === null && $districtSummaries !== []) {
            $top = $districtSummaries[0];
            if ((int) ($top['total'] ?? 0) > 0) {
                $insights[] = ($top['district_name'] ?? 'Top district').' leads with '
                    .number_format((int) $top['total']).' onboarded ('.(int) ($top['share_pct'] ?? 0).'% share).';
            }
        }

        if (! is_null($overview['female_pct'] ?? null) && (int) ($overview['total'] ?? 0) > 0) {
            $insights[] = 'Women represent '.(int) $overview['female_pct'].'% of recorded gender in the current scope.';
        }

        if ((int) ($overview['lakhpati_yes_count'] ?? 0) > 0 && ! is_null($overview['lakhpati_yes_pct'] ?? null)) {
            $insights[] = number_format((int) $overview['lakhpati_yes_count']).' Lakhpati Didis marked Yes ('
                .(int) $overview['lakhpati_yes_pct'].'% of onboarded).';
        }

        if ((int) ($overview['potential_lakhpati_count'] ?? 0) > 0 && ! is_null($overview['potential_lakhpati_pct'] ?? null)) {
            $insights[] = number_format((int) $overview['potential_lakhpati_count'])
                .' potential Lakhpati Didis (SHG/CBO, member Yes, or Lakhpati Yes — '
                .(int) $overview['potential_lakhpati_pct'].'% of onboarded).';
        }

        if (count($districtSummaries) > 1) {
            $bestLakhpati = collect($districtSummaries)
                ->filter(fn (array $row) => (int) ($row['total'] ?? 0) > 0)
                ->sortByDesc(fn (array $row) => (int) ($row['lakhpati_yes_pct'] ?? 0))
                ->first();
            if ($bestLakhpati && (int) ($bestLakhpati['lakhpati_yes_pct'] ?? 0) > 0) {
                $insights[] = 'Highest Lakhpati rate: '.($bestLakhpati['district_name'] ?? 'District')
                    .' at '.(int) $bestLakhpati['lakhpati_yes_pct'].'%.';
            }

            $inactive = collect($districtSummaries)
                ->filter(fn (array $row) => (int) ($row['total'] ?? 0) > 0 && (int) ($row['recent_7_days'] ?? 0) === 0)
                ->count();
            if ($inactive > 0) {
                $insights[] = "{$inactive} district(s) had no onboarding in the last 7 days.";
            }
        }

        $sectorRows = (array) ($sectorBreakdown['rows'] ?? []);
        if ($sectorRows !== [] && (int) ($sectorBreakdown['total'] ?? 0) > 0) {
            $topSector = $sectorRows[0];
            if (($topSector['sector'] ?? '') !== 'Not recorded') {
                $insights[] = 'Top sector: '.($topSector['sector'] ?? 'Unknown')
                    .' ('.(int) ($topSector['pct'] ?? 0).'% of Phase 3 onboarded).';
            }
        }

        if ((int) ($overview['this_month'] ?? 0) > 0) {
            $insights[] = number_format((int) $overview['this_month']).' onboarded this month in the current filter scope.';
        }

        return array_values(array_slice($insights, 0, 6));
    }

    private function expectedFyProgressPctByNow(FiscalYear $activeFy): ?int
    {
        $fyStart = $activeFy->starts_on ? Carbon::parse($activeFy->starts_on)->startOfDay() : null;
        $fyEnd = $activeFy->ends_on ? Carbon::parse($activeFy->ends_on)->endOfDay() : null;
        if (! $fyStart || ! $fyEnd || ! $fyEnd->greaterThan($fyStart)) {
            return null;
        }

        $today = now()->startOfDay();
        if ($today->lt($fyStart)) {
            return 0;
        }
        if ($today->gt($fyEnd)) {
            return 100;
        }

        $totalDays = max(1, $fyStart->diffInDays($fyEnd));
        $elapsedDays = max(0, $fyStart->diffInDays($today));

        return (int) round(min(100, max(0, ($elapsedDays / $totalDays) * 100)));
    }
}
