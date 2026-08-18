<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\Deliverable;
use App\Models\District;
use App\Models\DistrictDeliverableTarget;
use App\Models\DistrictServiceSpoc;
use App\Models\FiscalYear;
use App\Models\Hub;
use App\Models\Service;
use App\Models\ServiceCase;
use App\Models\StateDeliverableTarget;
use App\Services\CfaBusinessStageService;
use App\Services\Cfa\CfaSubmissionListQuery;
use Carbon\Carbon;
use Illuminate\Http\Request;
use Illuminate\Pagination\LengthAwarePaginator;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Illuminate\View\View;
use Symfony\Component\HttpFoundation\StreamedResponse;

class Phase2OnboardedApplicantController extends Controller
{
    private const POTENTIAL_LAKHPATI_LABEL = 'Potential Lakhpati Didi/ SHG Members/ CBOs';

    private const FY_CODE = '2025-26';

    private const P2_START = '2025-04-02';

    private const P2_END = '2026-04-01';

    private const BUSINESS_STAGES = ['early' => 'Early', 'seed' => 'Seed', 'growth' => 'Growth'];

    private const APPLICANT_CATEGORIES = ['Individual' => 'Individual', 'SHG' => 'SHG', 'CBO' => 'CBO'];

    /** @var array<string, string> */
    private const INCOME_SLABS = [
        '0_2l' => '0-2 lakh',
        '2_5l' => '2-5 lakh',
        '5_8l' => '5-8 lakh',
        '8_10l' => '8-10 lakh',
        '10_12l' => '10-12 lakh',
        '12_14l' => '12-14 lakh',
        '14_16l' => '14-16 lakh',
        '16_18l' => '16-18 lakh',
        '18_20l' => '18-20 lakh',
        '20l_plus' => '20+ lakh',
    ];

    public function index(Request $request): View
    {
        $scope = $this->resolveScope($request);
        $listFilters = $this->extractFilters($request, $scope);
        $hubId = $listFilters['hub'];
        $districtId = $listFilters['district'];

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
        $allDistrictRows = $this->buildRows(array_merge($listFilters, ['district' => null]), $scope);
        $mergedRows = $districtId
            ? $allDistrictRows->filter(fn (array $row) => (int) ($row['district_id'] ?? 0) === (int) $districtId)->values()
            : $allDistrictRows;
        $rows = $this->paginateMergedRows($mergedRows, $request, 30);
        $overview = $this->overviewStats($mergedRows);
        $districtSummaries = $this->districtSummaries($allDistrictRows);
        $targetProgress = $this->targetProgress((int) $overview['total'], $hubId, $districtId, $scope);
        $sectorBreakdown = $this->sectorBreakdown($mergedRows);
        $insights = $this->buildInsights($overview, $districtSummaries, $targetProgress, $sectorBreakdown, $districtId);

        return view('admin.onboarded.index', [
            'pageTitle' => '2025-26 onboarding',
            'pageHeading' => '2025-26 onboarding',
            'pageVariant' => 'phase2',
            'sourceBadgeLabel' => 'FY 2025-26',
            'rows' => $rows,
            'hubs' => $hubs,
            'districts' => $districts,
            'services' => $this->filterServices(),
            'commonColumns' => $commonColumns,
            'overview' => $overview,
            'districtSummaries' => $districtSummaries,
            'targetProgress' => $targetProgress,
            'sectorBreakdown' => $sectorBreakdown,
            'insights' => $insights,
            'filters' => $listFilters,
            'businessStages' => self::BUSINESS_STAGES,
            'applicantCategories' => self::APPLICANT_CATEGORIES,
            'casteOptions' => CfaSubmissionListQuery::casteFilterOptions(),
            'incomeSlabs' => self::INCOME_SLABS,
            'routeIndex' => $this->routeNameFor($request, 'index'),
            'routeExport' => $this->routeNameFor($request, 'export'),
        ]);
    }

    public function export(Request $request): StreamedResponse
    {
        $scope = $this->resolveScope($request);
        $listFilters = $this->extractFilters($request, $scope);
        $commonColumns = $this->commonColumnMap();
        $rows = $this->buildRows($listFilters, $scope);

        $headers = ['Sr No', 'Application No', 'Source', 'District', 'Hub', 'Batch', 'Onboarded At', 'Services Taken'];
        foreach ($commonColumns as $key => $meta) {
            if ($key === 'application_no') {
                continue;
            }
            $headers[] = (string) ($meta['label'] ?? $key);
        }

        $filename = 'onboarded-applicants-2025-26-'.now()->format('Ymd_His').'.csv';

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
                    'FY 2025-26',
                    (string) ($row['district'] ?? ''),
                    (string) ($row['hub_name'] ?? ''),
                    (string) ($row['onboarding_batch_name'] ?? ''),
                    (string) ($row['onboarded_at'] ?? ''),
                    (string) ($row['services_taken_label'] ?? ''),
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

    /** @return array<string, mixed> */
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
        $stage = mb_strtolower(trim((string) $request->string('stage')->toString()));
        if (! array_key_exists($stage, self::BUSINESS_STAGES)) {
            $stage = '';
        }

        $category = trim((string) $request->string('category')->toString());
        if (! array_key_exists($category, self::APPLICANT_CATEGORIES)) {
            $category = '';
        }

        $caste = CfaSubmissionListQuery::normalizeCasteParam($request);

        $income = trim((string) $request->string('income')->toString());
        if (! array_key_exists($income, self::INCOME_SLABS)) {
            $income = '';
        }

        $service = trim((string) $request->string('service')->toString());
        if ($service !== '__none__' && ($service === '' || ! ctype_digit($service))) {
            $service = '';
        }

        return [
            'hub' => $hubId,
            'district' => $districtId,
            'q' => $q,
            'stage' => $stage,
            'category' => $category,
            'caste' => $caste,
            'income' => $income,
            'service' => $service,
        ];
    }

    /** @return array{hub_id: ?int, district_ids: ?list<int>} */
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
            'hub_admin' => 'hub.onboarded-2025-26.'.$action,
            'district_staff' => 'staff.onboarded-2025-26.'.$action,
            'state_staff' => 'spoc.onboarded-2025-26.'.$action,
            default => 'admin.onboarded-2025-26.'.$action,
        };
    }

    private function buildRows(array $filters, array $scope = []): Collection
    {
        $commonColumns = $this->commonColumnMap();

        return $this->fetchLegacyRows($filters, $scope)
            ->map(fn (array $row) => $this->mapDistrictOntoRow($row))
            ->pipe(fn (Collection $rows) => $this->applyMappedScope($rows, $filters, $scope))
            ->map(fn (array $row) => $this->enrichRowWithDetails($row))
            ->map(fn (array $row) => $this->mapCommonColumnsForRow($row, $commonColumns))
            ->pipe(fn (Collection $rows) => $this->attachServicesTaken($rows))
            ->pipe(fn (Collection $rows) => $this->applyPostFilters($rows, $filters))
            ->values();
    }

    private function fetchLegacyRows(array $filters, array $scope): Collection
    {
        if (! $this->legacyReady()) {
            return collect();
        }

        [$start, $end] = $this->fyWindow();

        $query = DB::connection('legacy')
            ->table('rbi_onboarded_applicants as oa')
            ->join('rbi_applications as a', 'a.id', '=', 'oa.application_id')
            ->leftJoin('rbi_applicant_details as d', 'd.application_id', '=', 'a.id')
            ->whereNotNull('oa.application_id')
            ->whereNotNull('oa.status')
            ->where('oa.status', '<>', '')
            ->whereNotNull('a.submission_date')
            ->whereRaw('DATE(a.submission_date) BETWEEN ? AND ?', [$start, $end]);

        if ($this->legacyHasTable('rbi_onboarding_batches')) {
            $query->leftJoin('rbi_onboarding_batches as lob', 'lob.id', '=', 'oa.onboarding_batch_id');
        }

        if ($this->legacyHasTable('rbi_enterprise_details')) {
            $query->leftJoin(DB::raw('(
                SELECT e1.application_id, e1.turnover_last_year, e1.is_registered
                FROM rbi_enterprise_details e1
                INNER JOIN (
                    SELECT application_id, MAX(id) AS max_id
                    FROM rbi_enterprise_details
                    GROUP BY application_id
                ) t ON t.application_id = e1.application_id AND t.max_id = e1.id
            ) as ed'), 'ed.application_id', '=', 'a.id');
        }

        $legacyDistrictNames = $this->legacyDistrictNamesForFilters(
            $filters['hub'] ?? null,
            $filters['district'] ?? null,
            $scope
        );
        if ($legacyDistrictNames !== null) {
            if ($legacyDistrictNames === []) {
                return collect();
            }
            $query->where(function ($q) use ($legacyDistrictNames): void {
                foreach ($legacyDistrictNames as $name) {
                    $q->orWhereRaw('LOWER(TRIM(COALESCE(d.district, \'\'))) = ?', [$name]);
                }
            });
        }

        $q = (string) ($filters['q'] ?? '');
        if ($q !== '') {
            $like = '%'.$q.'%';
            $query->where(function ($filter) use ($like, $q): void {
                $filter->where('a.application_no', 'like', $like)
                    ->orWhere('d.applicant_name', 'like', $like)
                    ->orWhere('d.phone', 'like', $like);
                if ($this->legacyHasTable('rbi_onboarding_batches')) {
                    $filter->orWhere('lob.batch_name', 'like', $like);
                }
                if (ctype_digit($q)) {
                    $id = (int) $q;
                    $filter->orWhere('a.id', $id)
                        ->orWhere('oa.id', $id)
                        ->orWhere('oa.onboarding_batch_id', $id);
                }
            });
        }

        $select = [
            'oa.id as onboarded_row_id',
            'oa.status as onboard_status',
            'a.id as application_id',
        ];
        foreach ([
            'application_no' => 'application_no',
            'form_stage' => 'form_stage',
            'business_category' => 'business_category',
            'category' => 'applicant_category',
            'product' => 'product',
            'other_product' => 'other_product',
            'submission_date' => 'submission_date',
        ] as $column => $alias) {
            if ($this->legacyHasColumn('rbi_applications', $column)) {
                $select[] = "a.{$column} as {$alias}";
            }
        }
        foreach ([
            'applicant_name' => 'applicant_name',
            'phone' => 'phone',
            'guardian_name' => 'guardian_name',
            'gender' => 'gender',
            'dob' => 'dob',
            'district' => 'district',
            'block' => 'block_name',
            'education' => 'education',
            'alt_mobile' => 'alt_mobile',
            'email' => 'email',
            'caste' => 'caste',
            'is_shg_member' => 'is_shg_member',
            'shg_name' => 'shg_name',
            'lakhpati' => 'lakhpati',
            'pincode' => 'pincode',
            'village' => 'village',
            'loan_taken' => 'loan_taken',
            'bank_loan' => 'bank_loan',
            'current_employment' => 'current_employment',
            'employed_count' => 'employed_count',
            'id_proof_type' => 'id_proof_type',
            'id_proof_number' => 'id_proof_number',
            'expectations' => 'expectations',
            'training_mode' => 'training_mode',
            'challenges' => 'challenges',
            'expectation_other' => 'expectation_other',
            'migrated_for_employment' => 'migrated_for_employment',
            'submitted_by_name' => 'submitted_by_name',
            'submitted_by_mobile' => 'submitted_by_mobile',
            'department_name' => 'department_name',
            'info_source' => 'info_source',
            'resource_name' => 'resource_name',
        ] as $column => $alias) {
            if ($this->legacyHasColumn('rbi_applicant_details', $column)) {
                $select[] = "d.{$column} as {$alias}";
            }
        }

        if ($this->legacyHasColumn('rbi_onboarded_applicants', 'onboarded_at')) {
            $select[] = 'oa.onboarded_at as oa_onboarded_at';
        }
        if ($this->legacyHasColumn('rbi_onboarded_applicants', 'created_at')) {
            $select[] = 'oa.created_at as oa_created_at';
        }
        if ($this->legacyHasTable('rbi_onboarding_batches')) {
            $select[] = 'lob.id as onboarding_batch_id';
            $select[] = 'lob.batch_name as onboarding_batch_name';
            if ($this->legacyHasColumn('rbi_onboarding_batches', 'onboarding_date')) {
                $select[] = 'lob.onboarding_date as batch_onboarding_date';
            } elseif ($this->legacyHasColumn('rbi_onboarding_batches', 'onboard_date')) {
                $select[] = 'lob.onboard_date as batch_onboarding_date';
            }
        }
        if ($this->legacyHasTable('rbi_enterprise_details')) {
            $select[] = 'ed.turnover_last_year as turnover_last_fy';
            $select[] = 'ed.is_registered as is_registered';
        }

        try {
            $rows = $query->orderByDesc('oa.id')->get($select);
        } catch (\Throwable) {
            return collect();
        }

        return $rows->map(function ($row): array {
            $arr = (array) $row;
            $arr['data_source'] = 'legacy_phase2';
            $arr['onboarded_at'] = $this->resolveOnboardedAt($arr);
            $arr['hub_name'] = '';
            $arr['hub_id'] = null;
            $arr['district_id'] = 0;
            $arr['full_details_json'] = json_encode([
                'rbi_applications' => [
                    'id' => $arr['application_id'] ?? null,
                    'application_no' => $arr['application_no'] ?? null,
                    'form_stage' => $arr['form_stage'] ?? null,
                    'business_category' => $arr['business_category'] ?? null,
                    'category' => $arr['applicant_category'] ?? null,
                    'product' => $arr['product'] ?? null,
                    'other_product' => $arr['other_product'] ?? null,
                    'submission_date' => $arr['submission_date'] ?? null,
                ],
                'rbi_applicant_details' => [
                    'applicant_name' => $arr['applicant_name'] ?? null,
                    'guardian_name' => $arr['guardian_name'] ?? null,
                    'gender' => $arr['gender'] ?? null,
                    'dob' => $arr['dob'] ?? null,
                    'education' => $arr['education'] ?? null,
                    'phone' => $arr['phone'] ?? null,
                    'alt_mobile' => $arr['alt_mobile'] ?? null,
                    'email' => $arr['email'] ?? null,
                    'caste' => $arr['caste'] ?? null,
                    'is_shg_member' => $arr['is_shg_member'] ?? null,
                    'shg_name' => $arr['shg_name'] ?? null,
                    'lakhpati' => $arr['lakhpati'] ?? null,
                    'district' => $arr['district'] ?? null,
                    'block' => $arr['block_name'] ?? null,
                    'pincode' => $arr['pincode'] ?? null,
                    'village' => $arr['village'] ?? null,
                    'loan_taken' => $arr['loan_taken'] ?? null,
                    'bank_loan' => $arr['bank_loan'] ?? null,
                    'current_employment' => $arr['current_employment'] ?? null,
                    'employed_count' => $arr['employed_count'] ?? null,
                    'id_proof_type' => $arr['id_proof_type'] ?? null,
                    'id_proof_number' => $arr['id_proof_number'] ?? null,
                    'expectations' => $arr['expectations'] ?? null,
                    'training_mode' => $arr['training_mode'] ?? null,
                    'challenges' => $arr['challenges'] ?? null,
                    'expectation_other' => $arr['expectation_other'] ?? null,
                    'migrated_for_employment' => $arr['migrated_for_employment'] ?? null,
                    'submitted_by_name' => $arr['submitted_by_name'] ?? null,
                    'submitted_by_mobile' => $arr['submitted_by_mobile'] ?? null,
                    'department_name' => $arr['department_name'] ?? null,
                    'info_source' => $arr['info_source'] ?? null,
                    'resource_name' => $arr['resource_name'] ?? null,
                    'turnover_last_year' => $arr['turnover_last_fy'] ?? null,
                    'is_registered' => $arr['is_registered'] ?? null,
                ],
            ], JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);

            return $arr;
        });
    }

    /** @param  array<string, mixed>  $row */
    private function resolveOnboardedAt(array $row): ?string
    {
        foreach (['oa_onboarded_at', 'oa_created_at', 'batch_onboarding_date'] as $key) {
            $value = trim((string) ($row[$key] ?? ''));
            if ($value !== '') {
                return $value;
            }
        }

        return null;
    }

    /** @param  array<string, mixed>  $row */
    private function mapDistrictOntoRow(array $row): array
    {
        $lookup = $this->districtLookup();
        $key = $this->normalizeDistrictKey((string) ($row['district'] ?? ''));
        $mapped = $key !== '' ? ($lookup[$key] ?? null) : null;
        if ($mapped === null) {
            return $row;
        }

        $row['district_id'] = (int) $mapped['district_id'];
        $row['district'] = (string) $mapped['district_name'];
        $row['hub_id'] = (int) $mapped['hub_id'];
        $row['hub_name'] = (string) $mapped['hub_name'];

        return $row;
    }

    private function applyMappedScope(Collection $rows, array $filters, array $scope): Collection
    {
        $hubId = $filters['hub'] ?? null;
        $districtId = $filters['district'] ?? null;
        $allowedDistrictIds = $scope['district_ids'] ?? null;

        return $rows->filter(function (array $row) use ($hubId, $districtId, $allowedDistrictIds): bool {
            $rowDistrictId = (int) ($row['district_id'] ?? 0);
            $rowHubId = (int) ($row['hub_id'] ?? 0);

            if ($hubId && $rowHubId !== (int) $hubId) {
                return false;
            }
            if ($districtId && $rowDistrictId !== (int) $districtId) {
                return false;
            }
            if (is_array($allowedDistrictIds) && ($allowedDistrictIds === [] || ! in_array($rowDistrictId, $allowedDistrictIds, true))) {
                return false;
            }

            return true;
        })->values();
    }

    private function applyPostFilters(Collection $rows, array $filters): Collection
    {
        $stage = (string) ($filters['stage'] ?? '');
        $category = (string) ($filters['category'] ?? '');
        $caste = (string) ($filters['caste'] ?? '');
        $income = (string) ($filters['income'] ?? '');
        $service = (string) ($filters['service'] ?? '');

        return $rows->filter(function (array $row) use ($stage, $category, $caste, $income, $service): bool {
            if ($stage !== '') {
                $rowStage = mb_strtolower(trim((string) ($row['common_values']['form_stage'] ?? $row['form_stage'] ?? '')));
                if ($rowStage === '' || $rowStage === '—') {
                    $rowStage = mb_strtolower($this->computedStage($row));
                }
                if ($rowStage !== $stage) {
                    return false;
                }
            }

            if ($category !== '') {
                $rowCategory = mb_strtolower(trim((string) ($row['applicant_category'] ?? $row['common_values']['category'] ?? '')));
                if ($rowCategory !== mb_strtolower($category)) {
                    return false;
                }
            }

            if ($caste !== '') {
                $allowed = array_map('strtoupper', CfaSubmissionListQuery::casteFilterValues($caste));
                $rowCaste = strtoupper(trim((string) ($row['common_values']['caste'] ?? $row['caste'] ?? '')));
                if ($rowCaste === '' || $rowCaste === '—' || ! in_array($rowCaste, $allowed, true)) {
                    return false;
                }
            }

            if ($income !== '' && array_key_exists($income, self::INCOME_SLABS)) {
                $amount = CfaBusinessStageService::parseTurnover((string) ($row['turnover_last_fy'] ?? ''));
                if (! $this->incomeMatchesSlab($amount, $income)) {
                    return false;
                }
            }

            if ($service === '__none__') {
                return ($row['services_taken'] ?? []) === [];
            }
            if ($service !== '' && ctype_digit($service)) {
                $wanted = (int) $service;
                foreach ((array) ($row['services_taken'] ?? []) as $taken) {
                    if ((int) ($taken['id'] ?? 0) === $wanted) {
                        return true;
                    }
                }

                return false;
            }

            return true;
        })->values();
    }

    private function computedStage(array $row): string
    {
        $registered = trim((string) ($row['is_registered'] ?? ''));
        $turnover = CfaBusinessStageService::parseTurnover((string) ($row['turnover_last_fy'] ?? ''));
        $computed = (new CfaBusinessStageService)->compute(
            $this->isYes($registered) ? 'Yes' : 'No',
            $turnover
        );

        return (string) ($computed['stage'] ?? '');
    }

    private function incomeMatchesSlab(float $amount, string $income): bool
    {
        return match ($income) {
            '0_2l' => $amount >= 0 && $amount < 200000,
            '2_5l' => $amount >= 200000 && $amount < 500000,
            '5_8l' => $amount >= 500000 && $amount < 800000,
            '8_10l' => $amount >= 800000 && $amount < 1000000,
            '10_12l' => $amount >= 1000000 && $amount < 1200000,
            '12_14l' => $amount >= 1200000 && $amount < 1400000,
            '14_16l' => $amount >= 1400000 && $amount < 1600000,
            '16_18l' => $amount >= 1600000 && $amount < 1800000,
            '18_20l' => $amount >= 1800000 && $amount < 2000000,
            '20l_plus' => $amount >= 2000000,
            default => true,
        };
    }

    /** @return array<string, mixed> */
    private function overviewStats(Collection $rows): array
    {
        $sevenDaysAgo = now()->subDays(7);
        $monthStart = now()->startOfMonth();
        $female = 0;
        $male = 0;
        $potentialLakhpati = 0;
        $recent = 0;
        $thisMonth = 0;
        $districtIds = [];
        $hubIds = [];

        foreach ($rows as $row) {
            $gender = mb_strtolower(trim((string) ($row['gender'] ?? $row['common_values']['gender'] ?? '')));
            if (in_array($gender, ['female', 'f', 'woman'], true)) {
                $female++;
            } elseif (in_array($gender, ['male', 'm', 'man'], true)) {
                $male++;
            }

            if ($this->isPotentialLakhpati($row)) {
                $potentialLakhpati++;
            }

            $onboardedAt = $this->parseDate($row['onboarded_at'] ?? null);
            if ($onboardedAt !== null && $onboardedAt->greaterThanOrEqualTo($sevenDaysAgo)) {
                $recent++;
            }
            if ($onboardedAt !== null && $onboardedAt->greaterThanOrEqualTo($monthStart)) {
                $thisMonth++;
            }

            $districtId = (int) ($row['district_id'] ?? 0);
            if ($districtId > 0) {
                $districtIds[$districtId] = true;
            } elseif (trim((string) ($row['district'] ?? '')) !== '') {
                $districtIds['n:'.mb_strtolower(trim((string) $row['district']))] = true;
            }

            $hubId = (int) ($row['hub_id'] ?? 0);
            if ($hubId > 0) {
                $hubIds[$hubId] = true;
            }
        }

        $total = $rows->count();
        $knownGender = $female + $male;

        return [
            'total' => $total,
            'phase3_onboarded_count' => 0,
            'legacy_onboarded_count' => $total,
            'districts_covered' => count($districtIds),
            'hubs_covered' => count($hubIds),
            'female_count' => $female,
            'male_count' => $male,
            'female_pct' => $knownGender > 0 ? (int) round(($female / $knownGender) * 100) : null,
            'potential_lakhpati_count' => $potentialLakhpati,
            'potential_lakhpati_pct' => $total > 0 ? (int) round(($potentialLakhpati / $total) * 100) : null,
            'recent_7_days' => $recent,
            'this_month' => $thisMonth,
        ];
    }

    /** @return list<array<string, mixed>> */
    private function districtSummaries(Collection $rows): array
    {
        $sevenDaysAgo = now()->subDays(7);
        $grouped = [];

        foreach ($rows as $row) {
            $districtId = (int) ($row['district_id'] ?? 0);
            $districtName = trim((string) ($row['district'] ?? '')) ?: 'Unassigned';
            $key = $districtId > 0 ? 'id:'.$districtId : 'name:'.mb_strtolower($districtName);
            $grouped[$key] ??= [
                'district_id' => $districtId,
                'district_name' => $districtName,
                'hub_name' => (string) ($row['hub_name'] ?? ''),
                'total' => 0,
                'female_count' => 0,
                'potential_lakhpati_count' => 0,
                'recent_7_days' => 0,
                'last_onboarded_at' => null,
            ];
            $grouped[$key]['total']++;
            $gender = mb_strtolower(trim((string) ($row['gender'] ?? '')));
            if (in_array($gender, ['female', 'f', 'woman'], true)) {
                $grouped[$key]['female_count']++;
            }
            if ($this->isPotentialLakhpati($row)) {
                $grouped[$key]['potential_lakhpati_count']++;
            }
            $onboardedAt = $this->parseDate($row['onboarded_at'] ?? null);
            if ($onboardedAt !== null && $onboardedAt->greaterThanOrEqualTo($sevenDaysAgo)) {
                $grouped[$key]['recent_7_days']++;
            }
            if ($onboardedAt !== null) {
                $current = $grouped[$key]['last_onboarded_at'];
                if ($current === null || $onboardedAt->greaterThan($this->parseDate($current) ?? $onboardedAt)) {
                    $grouped[$key]['last_onboarded_at'] = $onboardedAt->toDateTimeString();
                }
            }
            if ($grouped[$key]['hub_name'] === '' && ($row['hub_name'] ?? '') !== '') {
                $grouped[$key]['hub_name'] = (string) $row['hub_name'];
            }
        }

        $grandTotal = max(1, (int) array_sum(array_column($grouped, 'total')));
        $districtIds = array_values(array_filter(array_map(
            fn (array $row): int => (int) $row['district_id'],
            $grouped
        ), fn (int $id) => $id > 0));
        $targetsByDistrict = $this->onboardingTargetsByDistrictIds($districtIds);

        $summaries = array_map(function (array $row) use ($grandTotal, $targetsByDistrict): array {
            $total = (int) $row['total'];
            $female = (int) $row['female_count'];
            $potentialLakhpati = (int) $row['potential_lakhpati_count'];
            $districtId = (int) $row['district_id'];
            $target = (int) ($targetsByDistrict[$districtId] ?? 0);

            return [
                'district_id' => $districtId,
                'district_name' => (string) $row['district_name'],
                'hub_name' => (string) $row['hub_name'],
                'total' => $total,
                'phase3_onboarded_count' => 0,
                'legacy_onboarded_count' => $total,
                'female_count' => $female,
                'female_pct' => $total > 0 ? (int) round(($female / $total) * 100) : 0,
                'potential_lakhpati_count' => $potentialLakhpati,
                'potential_lakhpati_pct' => $total > 0 ? (int) round(($potentialLakhpati / $total) * 100) : 0,
                'target' => $target,
                'target_progress_pct' => $target > 0 ? (int) round(($total / $target) * 100) : null,
                'target_gap' => $target > 0 ? max(0, $target - $total) : null,
                'recent_7_days' => (int) $row['recent_7_days'],
                'share_pct' => (int) round(($total / $grandTotal) * 100),
                'last_onboarded_at' => $row['last_onboarded_at'],
            ];
        }, array_values($grouped));

        usort($summaries, fn (array $a, array $b): int => ($b['total'] <=> $a['total']));

        return $summaries;
    }

    /** @return array<string, mixed> */
    private function sectorBreakdown(Collection $rows): array
    {
        $counts = $rows
            ->map(function (array $row): string {
                $label = trim((string) ($row['common_values']['business_category'] ?? $row['business_category'] ?? ''));
                if ($label === '' || $label === '—') {
                    return 'Not recorded';
                }

                return $label;
            })
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

    private function isPotentialLakhpati(array $row): bool
    {
        $lakhpati = (string) ($row['lakhpati'] ?? $row['common_values']['lakhpati'] ?? '');
        $member = (string) ($row['is_shg_member'] ?? $row['common_values']['is_shg_member'] ?? '');

        return $this->isYes($lakhpati) && $this->isYes($member);
    }

    private function isYes(string $value): bool
    {
        return in_array(mb_strtolower(trim($value)), ['yes', 'y', '1', 'true'], true);
    }

    private function parseDate(mixed $value): ?Carbon
    {
        $text = trim((string) $value);
        if ($text === '') {
            return null;
        }

        try {
            return Carbon::parse($text);
        } catch (\Throwable) {
            return null;
        }
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

    /** @param  array<string, mixed>  $row */
    private function enrichRowWithDetails(array $row): array
    {
        $decoded = json_decode((string) ($row['full_details_json'] ?? ''), true);
        if (! is_array($decoded)) {
            $decoded = [];
        }

        $detailsRaw = [];
        if (isset($decoded['rbi_applicant_details']) && is_array($decoded['rbi_applicant_details'])) {
            foreach ($decoded['rbi_applicant_details'] as $key => $value) {
                $detailsRaw[(string) $key] = $value;
            }
            if (isset($decoded['rbi_applications']) && is_array($decoded['rbi_applications'])) {
                foreach ($decoded['rbi_applications'] as $key => $value) {
                    $detailsRaw['app_'.$key] = $value;
                }
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

    /** @return array<string, array{label: string, keys: list<string>}> */
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
            'category' => ['label' => 'Category', 'keys' => ['category', 'applicant_category', 'app_category']],
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
            'product' => ['label' => 'Product', 'keys' => ['other_product', 'app_other_product', 'product', 'app_product']],
            'form_stage' => ['label' => 'Form Stage', 'keys' => ['form_stage', 'app_form_stage']],
            'turnover_last_fy' => ['label' => 'Annual Income', 'keys' => ['turnover_last_fy', 'turnover_last_year']],
            'training_mode' => ['label' => 'Training Mode', 'keys' => ['training_mode']],
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
            if ($canonicalKey === 'turnover_last_fy') {
                $value = $this->formatAnnualIncome($value);
            }
            $mapped[$canonicalKey] = $value;
        }
        $row['common_values'] = $mapped;
        $row['annual_income'] = (string) ($mapped['turnover_last_fy'] ?? '—');
        $row['applicant_category'] = trim((string) ($mapped['category'] ?? ($row['applicant_category'] ?? '')));
        if ($row['applicant_category'] === '' || $row['applicant_category'] === '—') {
            $row['applicant_category'] = '—';
        }

        return $row;
    }

    private function attachServicesTaken(Collection $rows): Collection
    {
        if ($rows->isEmpty()) {
            return $rows;
        }

        $legacyIds = $rows
            ->pluck('application_id')
            ->map(fn ($id) => (int) $id)
            ->filter(fn (int $id) => $id > 0)
            ->unique()
            ->values()
            ->all();

        $namesByLegacy = [];
        if ($legacyIds !== [] && ServiceCase::supportsLegacyApplicationLink()) {
            $taken = DB::table('service_cases as sc')
                ->join('services as s', 's.id', '=', 'sc.service_id')
                ->whereIn('sc.legacy_application_id', $legacyIds)
                ->where('sc.status', ServiceCase::STATUS_APPROVED)
                ->orderBy('s.sort_order')
                ->orderBy('s.name')
                ->get(['sc.legacy_application_id', 'sc.service_id', 's.name']);

            foreach ($taken as $takenRow) {
                $legacyId = (int) $takenRow->legacy_application_id;
                $serviceId = (int) $takenRow->service_id;
                $name = trim((string) $takenRow->name);
                if ($legacyId <= 0 || $serviceId <= 0 || $name === '') {
                    continue;
                }
                $namesByLegacy[$legacyId] ??= [];
                $alreadyListed = false;
                foreach ($namesByLegacy[$legacyId] as $existing) {
                    if ((int) ($existing['id'] ?? 0) === $serviceId) {
                        $alreadyListed = true;
                        break;
                    }
                }
                if (! $alreadyListed) {
                    $namesByLegacy[$legacyId][] = [
                        'id' => $serviceId,
                        'name' => $name,
                    ];
                }
            }
        }

        $legacyAssigned = $this->legacyAssignedServicesByApplicationIds($legacyIds);

        return $rows->map(function (array $row) use ($namesByLegacy, $legacyAssigned): array {
            $legacyId = (int) ($row['application_id'] ?? 0);
            $items = $namesByLegacy[$legacyId] ?? [];
            foreach ($legacyAssigned[$legacyId] ?? [] as $legacyName) {
                $already = false;
                foreach ($items as $existing) {
                    if (mb_strtolower((string) ($existing['name'] ?? '')) === mb_strtolower($legacyName)) {
                        $already = true;
                        break;
                    }
                }
                if (! $already) {
                    $items[] = ['id' => 0, 'name' => $legacyName];
                }
            }
            $row['services_taken'] = $items;
            $row['services_taken_label'] = $items === []
                ? ''
                : implode(', ', array_map(fn (array $item): string => (string) ($item['name'] ?? ''), $items));

            return $row;
        });
    }

    /**
     * @param  list<int>  $applicationIds
     * @return array<int, list<string>>
     */
    private function legacyAssignedServicesByApplicationIds(array $applicationIds): array
    {
        if ($applicationIds === [] || ! $this->legacyHasTable('rbi_services_assigned')) {
            return [];
        }

        $grouped = [];
        try {
            $rows = DB::connection('legacy')
                ->table('rbi_services_assigned')
                ->whereIn('application_id', $applicationIds)
                ->orderBy('service_name')
                ->get(['application_id', 'service_name']);
        } catch (\Throwable) {
            return [];
        }

        foreach ($rows as $row) {
            $appId = (int) ($row->application_id ?? 0);
            $name = trim((string) ($row->service_name ?? ''));
            if ($appId <= 0 || $name === '') {
                continue;
            }
            $grouped[$appId] ??= [];
            if (! in_array($name, $grouped[$appId], true)) {
                $grouped[$appId][] = $name;
            }
        }

        return $grouped;
    }

    /** @return Collection<int, Service> */
    private function filterServices(): Collection
    {
        return Service::query()
            ->orderBy('name')
            ->get(['id', 'name']);
    }

    private function formatAnnualIncome(mixed $raw): string
    {
        if ($raw === null) {
            return '—';
        }
        $text = trim((string) $raw);
        if ($text === '' || $text === '—' || strtolower($text) === 'null') {
            return '—';
        }
        if (str_starts_with($text, '₹')) {
            return $text;
        }

        $amount = CfaBusinessStageService::parseTurnover($text);
        $clean = str_replace(',', '', $text);
        if (! is_numeric($clean)) {
            return $text;
        }

        return '₹'.number_format($amount, 0);
    }

    /**
     * @return array<string, array{district_id: int, district_name: string, hub_id: int, hub_name: string}>
     */
    private function districtLookup(): array
    {
        static $lookup = null;
        if (is_array($lookup)) {
            return $lookup;
        }

        $lookup = [];
        $aliasesMap = (array) config('legacy_phase2.staff_import.district_aliases', []);
        $districts = District::query()
            ->leftJoin('hubs as h', 'h.id', '=', 'districts.hub_id')
            ->orderBy('districts.name')
            ->get(['districts.id', 'districts.name', 'districts.hub_id', 'h.name as hub_name']);

        foreach ($districts as $district) {
            $canonical = trim((string) $district->name);
            if ($canonical === '') {
                continue;
            }
            $entry = [
                'district_id' => (int) $district->id,
                'district_name' => $canonical,
                'hub_id' => (int) $district->hub_id,
                'hub_name' => (string) ($district->hub_name ?? ''),
            ];
            $lookup[$this->normalizeDistrictKey($canonical)] = $entry;
            foreach ((array) ($aliasesMap[$canonical] ?? []) as $alias) {
                $aliasKey = $this->normalizeDistrictKey((string) $alias);
                if ($aliasKey !== '' && ! isset($lookup[$aliasKey])) {
                    $lookup[$aliasKey] = $entry;
                }
            }
        }

        return $lookup;
    }

    private function normalizeDistrictKey(string $name): string
    {
        return mb_strtolower(trim(preg_replace('/\s+/', ' ', $name) ?? $name));
    }

    /**
     * @return list<string>|null
     */
    private function legacyDistrictNamesForFilters(?int $hubId, ?int $districtId, array $scope): ?array
    {
        $allowedDistrictIds = $scope['district_ids'] ?? null;
        if (! $hubId && ! $districtId && ! is_array($allowedDistrictIds)) {
            return null;
        }

        $query = District::query();
        if ($districtId) {
            $query->where('id', $districtId);
        } elseif (is_array($allowedDistrictIds)) {
            $query->whereIn('id', $allowedDistrictIds);
        }
        if ($hubId && ! $districtId) {
            $query->where('hub_id', $hubId);
        }

        $districtModels = $query->get(['name']);
        $aliasesMap = (array) config('legacy_phase2.staff_import.district_aliases', []);
        $names = [];
        foreach ($districtModels as $districtModel) {
            $canonical = trim((string) $districtModel->name);
            if ($canonical === '') {
                continue;
            }
            $names[] = $this->normalizeDistrictKey($canonical);
            foreach ((array) ($aliasesMap[$canonical] ?? []) as $alias) {
                $alias = $this->normalizeDistrictKey((string) $alias);
                if ($alias !== '') {
                    $names[] = $alias;
                }
            }
        }

        return array_values(array_unique($names));
    }

    /** @return array{0: string, 1: string} */
    private function fyWindow(): array
    {
        $fy = FiscalYear::query()->where('code', self::FY_CODE)->first();
        if ($fy && $fy->starts_on && $fy->ends_on) {
            return [$fy->starts_on->toDateString(), $fy->ends_on->toDateString()];
        }

        return [self::P2_START, self::P2_END];
    }

    private function phase2FiscalYear(): ?FiscalYear
    {
        return FiscalYear::query()->where('code', self::FY_CODE)->first()
            ?? FiscalYear::query()->where('name', 'like', '%2025-26%')->first();
    }

    /** @return array<string, mixed> */
    private function targetProgress(int $achieved, ?int $hubId, ?int $districtId, array $scope): array
    {
        $districtIds = $this->resolveTargetDistrictIds($hubId, $districtId, $scope);
        $onboardingDeliverableId = Deliverable::onboardingTargetDeliverableId();
        $activeFy = $this->phase2FiscalYear();

        $empty = [
            'configured' => false,
            'achieved' => $achieved,
            'target' => null,
            'progress_pct' => null,
            'gap' => null,
            'label' => 'Onboarding target',
            'fiscal_year' => self::FY_CODE,
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

    /** @return Collection<int|string, int> */
    private function onboardingTargetsByDistrictIds(array $districtIds): Collection
    {
        if ($districtIds === []) {
            return collect();
        }

        $onboardingDeliverableId = Deliverable::onboardingTargetDeliverableId();
        $activeFy = $this->phase2FiscalYear();
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

        if ((int) ($overview['potential_lakhpati_count'] ?? 0) > 0 && ! is_null($overview['potential_lakhpati_pct'] ?? null)) {
            $insights[] = number_format((int) $overview['potential_lakhpati_count'])
                .' '.self::POTENTIAL_LAKHPATI_LABEL.' (Lakhpati Yes + SHG member Yes — '
                .(int) $overview['potential_lakhpati_pct'].'% of onboarded).';
        }

        if (count($districtSummaries) > 1) {
            $bestLakhpati = collect($districtSummaries)
                ->filter(fn (array $row) => (int) ($row['total'] ?? 0) > 0)
                ->sortByDesc(fn (array $row) => (int) ($row['potential_lakhpati_pct'] ?? 0))
                ->first();
            if ($bestLakhpati && (int) ($bestLakhpati['potential_lakhpati_pct'] ?? 0) > 0) {
                $insights[] = 'Highest '.self::POTENTIAL_LAKHPATI_LABEL.' share: '.($bestLakhpati['district_name'] ?? 'District')
                    .' at '.(int) $bestLakhpati['potential_lakhpati_pct'].'%.';
            }
        }

        $sectorRows = (array) ($sectorBreakdown['rows'] ?? []);
        if ($sectorRows !== [] && (int) ($sectorBreakdown['total'] ?? 0) > 0) {
            $topSector = $sectorRows[0];
            if (($topSector['sector'] ?? '') !== 'Not recorded') {
                $insights[] = 'Top sector: '.($topSector['sector'] ?? 'Unknown')
                    .' ('.(int) ($topSector['pct'] ?? 0).'% of all onboarded).';
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

    private function legacyReady(): bool
    {
        if ((string) config('database.connections.legacy.database', '') === '') {
            return false;
        }

        try {
            return Schema::connection('legacy')->hasTable('rbi_onboarded_applicants')
                && Schema::connection('legacy')->hasTable('rbi_applications')
                && Schema::connection('legacy')->hasTable('rbi_applicant_details');
        } catch (\Throwable) {
            return false;
        }
    }

    private function legacyHasTable(string $table): bool
    {
        try {
            return (string) config('database.connections.legacy.database', '') !== ''
                && Schema::connection('legacy')->hasTable($table);
        } catch (\Throwable) {
            return false;
        }
    }

    private function legacyHasColumn(string $table, string $column): bool
    {
        try {
            return $this->legacyHasTable($table)
                && Schema::connection('legacy')->hasColumn($table, $column);
        } catch (\Throwable) {
            return false;
        }
    }
}
