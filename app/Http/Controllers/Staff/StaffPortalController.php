<?php

namespace App\Http\Controllers\Staff;

use App\Http\Controllers\Controller;
use App\Models\AuditLog;
use App\Models\CfaSubmission;
use App\Models\Deliverable;
use App\Models\DistrictBlock;
use App\Models\FiscalYear;
use App\Models\Service;
use App\Models\ServiceCase;
use App\Models\User;
use App\Services\AdminAuditLogger;
use App\Services\CfaBusinessStageService;
use App\Services\CfaSubmissionAuditSnapshot;
use App\Services\CfaSubmissionValidator;
use App\Services\LegacyPhase2ApplicationDetailService;
use App\Services\StaffMonthlyTargetsDashboardService;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Query\Builder as QueryBuilder;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\Validator;
use Illuminate\View\View;
use Symfony\Component\HttpFoundation\StreamedResponse;

class StaffPortalController extends Controller
{
    public function monthlyTargets(Request $request, StaffMonthlyTargetsDashboardService $dashboard): View
    {
        $user = $request->user()->load(['district', 'hub', 'designationRecord']);

        if (! $user->district_id) {
            return view('staff.monthly-targets', [
                'noDistrict' => true,
                'user' => $user,
            ]);
        }

        if (! Deliverable::query()->where('is_active', true)->exists()) {
            return view('staff.monthly-targets', [
                'missingDeliverables' => true,
                'user' => $user,
            ]);
        }

        [$fiscalYearId, $fiscalYears] = FiscalYear::resolveIdForUi(
            $request->query('fiscal_year_id') !== null && $request->query('fiscal_year_id') !== ''
                ? (int) $request->query('fiscal_year_id')
                : null
        );

        $fiscalYear = $fiscalYears->firstWhere('id', $fiscalYearId);
        if ($fiscalYear === null) {
            abort(404);
        }
        $rows = $dashboard->buildRows($user, $fiscalYear);
        $monthLabels = $dashboard->fiscalMonthLabels($fiscalYear);

        return view('staff.monthly-targets', [
            'user' => $user,
            'fiscalYears' => $fiscalYears,
            'fiscalYearId' => $fiscalYearId,
            'fiscalYear' => $fiscalYear,
            'rows' => $rows,
            'monthLabels' => $monthLabels,
            'applyUrl' => $user->referralApplyUrl(),
        ]);
    }

    public function applications(Request $request): View
    {
        $staff = $request->user()->load('district');

        $query = CfaSubmission::query()
            ->with(['district', 'referralUser']);

        [$scope, $forceMineNotice] = $this->applyStaffApplicationScope(
            $query,
            $staff,
            (string) $request->query('scope', 'mine')
        );

        if ($scope === 'district') {
            $this->applyDistrictPhase3OnlyFilters($query);
        }

        $search = trim((string) $request->query('q', ''));
        if ($search !== '') {
            $this->applyApplicationsSearch($query, $search);
        }

        $submissions = $query
            ->orderByDesc('created_at')
            ->paginate(25)
            ->withQueryString();

        $mineCount = CfaSubmission::query()
            ->where('referral_user_id', (int) $staff->id)
            ->count();
        $districtCount = 0;
        if ((int) $staff->district_id > 0) {
            $districtCountQuery = CfaSubmission::query()
                ->where('district_id', (int) $staff->district_id);
            $this->applyDistrictPhase3OnlyFilters($districtCountQuery);
            $districtCount = (int) $districtCountQuery->count();
        }

        return view('staff.applications', [
            'submissions' => $submissions,
            'scope' => $scope,
            'mineCount' => $mineCount,
            'districtCount' => $districtCount,
            'districtName' => (string) ($staff->district?->name ?? ''),
            'forceMineNotice' => $forceMineNotice,
            'searchQuery' => $search,
        ]);
    }

    /**
     * CSV of CFA applications in the same scope as the list view, with all form fields
     * flattened from the JSON {@see CfaSubmission::$payload}.
     */
    public function applicationsExport(Request $request): StreamedResponse
    {
        $staff = $request->user()->load('district');

        $query = CfaSubmission::query()
            ->with(['district', 'referralUser', 'fiscalYear']);

        [$scope] = $this->applyStaffApplicationScope(
            $query,
            $staff,
            (string) $request->query('scope', 'mine')
        );

        if ($scope === 'district') {
            $this->applyDistrictPhase3OnlyFilters($query);
        }

        $search = trim((string) $request->query('q', ''));
        if ($search !== '') {
            $this->applyApplicationsSearch($query, $search);
        }

        $rows = $query->orderByDesc('created_at')->get();

        $allPayloadKeys = [];
        $flatRows = [];
        foreach ($rows as $row) {
            $payload = is_array($row->payload) ? $row->payload : [];
            $flat = $this->flattenCfaPayloadForExport($payload);
            foreach (array_keys($flat) as $pk) {
                $allPayloadKeys[$pk] = true;
            }
            $flatRows[] = ['model' => $row, 'payload' => $flat];
        }

        $payloadHeaderKeys = array_keys($allPayloadKeys);
        sort($payloadHeaderKeys, SORT_STRING);

        $baseHeaders = [
            'id',
            'application_no',
            'created_at',
            'updated_at',
            'fiscal_year_id',
            'fiscal_year_label',
            'district_id',
            'district_name',
            'lgd_state_code',
            'lgd_district_code',
            'lgd_block_code',
            'source',
            'applicant_name',
            'phone',
            'referral_user_id',
            'referral_user_name',
        ];

        $headers = array_merge($baseHeaders, $payloadHeaderKeys);

        $fileScope = $scope === 'district' ? 'district' : 'mine';
        $filename = 'cfa-applications-'.$fileScope.'-'.now()->format('Ymd_His').'.csv';

        $responseHeaders = [
            'Content-Type' => 'text/csv; charset=UTF-8',
            'Content-Disposition' => 'attachment; filename="'.$filename.'"',
        ];

        return response()->streamDownload(function () use ($flatRows, $payloadHeaderKeys, $headers) {
            $out = fopen('php://output', 'wb');
            if ($out === false) {
                return;
            }

            fwrite($out, "\xEF\xBB\xBF");
            fputcsv($out, $headers);

            foreach ($flatRows as $fr) {
                /** @var CfaSubmission $m */
                $m = $fr['model'];
                $flat = $fr['payload'];
                $fy = $m->fiscalYear;
                $line = [
                    $m->id,
                    $m->application_no ?? '',
                    $m->created_at?->format('Y-m-d H:i:s') ?? '',
                    $m->updated_at?->format('Y-m-d H:i:s') ?? '',
                    $m->fiscal_year_id ?? '',
                    $fy?->name ?? $fy?->code ?? '',
                    $m->district_id ?? '',
                    $m->district?->name ?? '',
                    $m->lgd_state_code ?? '',
                    $m->lgd_district_code ?? '',
                    $m->lgd_block_code ?? '',
                    $m->source ?? '',
                    $m->applicant_name ?? '',
                    $m->phone ?? '',
                    $m->referral_user_id ?? '',
                    $m->referralUser?->name ?? '',
                ];
                foreach ($payloadHeaderKeys as $pk) {
                    $line[] = $flat[$pk] ?? '';
                }
                fputcsv($out, $line);
            }

            fclose($out);
        }, $filename, $responseHeaders);
    }

    /**
     * CSV of one CFA submission (same columns as bulk {@see applicationsExport}), for staff who may view it.
     */
    public function exportSingleCfaSubmission(Request $request, CfaSubmission $cfa_submission): StreamedResponse
    {
        $this->assertOwnReferral($request, $cfa_submission);
        $cfa_submission->load(['district', 'referralUser', 'fiscalYear']);

        $payload = is_array($cfa_submission->payload) ? $cfa_submission->payload : [];
        $flat = $this->flattenCfaPayloadForExport($payload);
        $payloadHeaderKeys = array_keys($flat);
        sort($payloadHeaderKeys, SORT_STRING);

        $baseHeaders = [
            'id',
            'application_no',
            'created_at',
            'updated_at',
            'fiscal_year_id',
            'fiscal_year_label',
            'district_id',
            'district_name',
            'lgd_state_code',
            'lgd_district_code',
            'lgd_block_code',
            'source',
            'applicant_name',
            'phone',
            'referral_user_id',
            'referral_user_name',
        ];
        $headers = array_merge($baseHeaders, $payloadHeaderKeys);

        $slug = preg_replace('/[^a-z0-9_-]+/i', '-', strtolower((string) ($cfa_submission->application_no ?? 'cfa'))) ?: 'cfa';
        $filename = 'cfa-'.$slug.'-'.now()->format('Ymd_His').'.csv';
        $responseHeaders = [
            'Content-Type' => 'text/csv; charset=UTF-8',
            'Content-Disposition' => 'attachment; filename="'.$filename.'"',
        ];

        return response()->streamDownload(function () use ($cfa_submission, $flat, $payloadHeaderKeys, $headers) {
            $out = fopen('php://output', 'wb');
            if ($out === false) {
                return;
            }
            fwrite($out, "\xEF\xBB\xBF");
            fputcsv($out, $headers);
            $fy = $cfa_submission->fiscalYear;
            $line = [
                $cfa_submission->id,
                $cfa_submission->application_no ?? '',
                $cfa_submission->created_at?->format('Y-m-d H:i:s') ?? '',
                $cfa_submission->updated_at?->format('Y-m-d H:i:s') ?? '',
                $cfa_submission->fiscal_year_id ?? '',
                $fy?->name ?? $fy?->code ?? '',
                $cfa_submission->district_id ?? '',
                $cfa_submission->district?->name ?? '',
                $cfa_submission->lgd_state_code ?? '',
                $cfa_submission->lgd_district_code ?? '',
                $cfa_submission->lgd_block_code ?? '',
                $cfa_submission->source ?? '',
                $cfa_submission->applicant_name ?? '',
                $cfa_submission->phone ?? '',
                $cfa_submission->referral_user_id ?? '',
                $cfa_submission->referralUser?->name ?? '',
            ];
            foreach ($payloadHeaderKeys as $pk) {
                $line[] = $flat[$pk] ?? '';
            }
            fputcsv($out, $line);
            fclose($out);
        }, $filename, $responseHeaders);
    }

    public function showCfaSubmission(Request $request, CfaSubmission $cfa_submission): View
    {
        $this->assertOwnReferral($request, $cfa_submission);
        $cfa_submission->load(['district', 'referralUser', 'fiscalYear']);

        $cfaEditLogs = AuditLog::query()
            ->where('subject_type', CfaSubmission::class)
            ->where('subject_id', $cfa_submission->id)
            ->where('action', CfaSubmissionAuditSnapshot::ACTION_UPDATED)
            ->with('user')
            ->orderByDesc('created_at')
            ->get();

        $payload = is_array($cfa_submission->payload) ? $cfa_submission->payload : [];
        $legacyAppId = (int) ($payload['legacy_application_id'] ?? 0);

        $serviceCases = ServiceCase::query()
            ->where(function ($q) use ($cfa_submission, $legacyAppId): void {
                $q->where('cfa_submission_id', $cfa_submission->id);
                if (ServiceCase::supportsLegacyApplicationLink() && $legacyAppId > 0) {
                    $q->orWhere('legacy_application_id', $legacyAppId);
                }
            })
            ->with(['service.category'])
            ->orderByDesc('created_at')
            ->get();

        $pickerServices = Service::query()
            ->where('is_active', true)
            ->with(['category'])
            ->orderBy('sort_order')
            ->orderBy('name')
            ->get()
            ->filter(fn (Service $s) => $s->category !== null)
            ->values();

        $legacyDetail = app(LegacyPhase2ApplicationDetailService::class)->tryBuild($cfa_submission);
        if (is_array($legacyDetail) && isset($legacyDetail['viewRow'])) {
            return view('admin.cfa.legacy-detail', [
                'submission' => $cfa_submission,
                'legacyDetail' => $legacyDetail,
                'cfaIndexUrl' => route('staff.applications'),
                'serviceCasesUi' => [
                    'cases' => $serviceCases,
                    'pickerServices' => $pickerServices,
                    'submission' => $cfa_submission,
                ],
            ]);
        }

        return view('admin.cfa.show', [
            'submission' => $cfa_submission,
            'cfaIndexUrl' => route('staff.applications'),
            'cfaEditUrl' => route('staff.applications.edit', $cfa_submission),
            'cfaEditLogs' => $cfaEditLogs,
            'serviceCasesUi' => [
                'cases' => $serviceCases,
                'pickerServices' => $pickerServices,
                'submission' => $cfa_submission,
            ],
        ]);
    }

    public function editCfaSubmission(Request $request, CfaSubmission $cfa_submission): View
    {
        $this->assertOwnReferral($request, $cfa_submission);

        $staff = $request->user()->load(['district', 'hub', 'designationRecord']);
        $token = $staff->referral_token ?? '';
        if ($token === '') {
            abort(403, 'Referral token missing on your account.');
        }

        $districtName = $staff->district?->name ?? '';
        $districtId = (int) $staff->district_id;
        $blocks = $districtId > 0
            ? DistrictBlock::orderedNamesForDistrict($districtId)
            : [];
        if ($blocks === []) {
            $blocks = config('cfa.blocks_by_district.'.$districtName, []);
        }

        if ($districtName === '' || $blocks === []) {
            return view('public.cfa.apply', [
                'staff' => $staff,
                'token' => $token,
                'districtName' => '',
                'blocks' => [],
                'productsByCategory' => config('cfa.products_by_category'),
                'cfaEditingSubmission' => $cfa_submission,
            ]);
        }

        $prefill = $this->cfaOldInputFromSubmission($cfa_submission);
        $request->session()->now('_old_input', $prefill);

        return view('public.cfa.apply', [
            'staff' => $staff,
            'token' => $token,
            'districtName' => $districtName,
            'blocks' => $blocks,
            'productsByCategory' => config('cfa.products_by_category'),
            'cfaEditingSubmission' => $cfa_submission,
        ]);
    }

    public function updateCfaSubmission(
        Request $request,
        CfaSubmission $cfa_submission,
        CfaSubmissionValidator $cfaValidator,
        CfaBusinessStageService $stageService,
        AdminAuditLogger $auditLogger,
    ): RedirectResponse {
        $this->assertOwnReferral($request, $cfa_submission);

        $staff = $request->user()->load('district');
        $this->normalizeEmptySelects($request);

        $beforeSnapshot = CfaSubmissionAuditSnapshot::compact($cfa_submission);

        $validated = $cfaValidator->validate($request, $staff, $cfa_submission);

        $turnover = CfaBusinessStageService::parseTurnover($validated['turnover_last_fy']);
        $stageInfo = $stageService->compute($validated['is_registered'], $turnover);

        $regType = $validated['registration_type'] ?? null;
        if ($regType === 'Other' && ! empty($validated['registration_type_other'])) {
            $regType = 'Other: '.$validated['registration_type_other'];
        }

        $applicantDisplay = match ($validated['category']) {
            'Individual' => $validated['applicant_name'],
            default => $validated['shg_cbo_name'] ?? '',
        };

        $payload = array_merge($validated, [
            'form_stage' => $stageInfo['stage'],
            'criteria_matched' => $stageInfo['criteria_matched'],
            'stage_logic_lines' => $stageInfo['logic_lines'],
            'registration_type_resolved' => $validated['is_registered'] === 'Yes' ? $regType : null,
            'referral_staff_name' => $staff->name,
            'referral_staff_email' => $staff->email,
            'submitted_at' => now()->toIso8601String(),
        ]);
        $payload['consent'] = true;

        $blockRow = DistrictBlock::query()
            ->where('district_id', $staff->district_id)
            ->where('name', $validated['block'])
            ->first();

        $cfa_submission->update([
            'lgd_block_code' => $blockRow?->lgd_block_code,
            'applicant_name' => $applicantDisplay,
            'phone' => $validated['phone'],
            'payload' => $payload,
        ]);

        $cfa_submission->refresh();
        $afterSnapshot = CfaSubmissionAuditSnapshot::compact($cfa_submission);
        $auditLogger->record(
            $request,
            CfaSubmissionAuditSnapshot::ACTION_UPDATED,
            CfaSubmission::class,
            $cfa_submission->id,
            $beforeSnapshot,
            $afterSnapshot,
            CfaSubmissionAuditSnapshot::describeDiff($beforeSnapshot, $afterSnapshot),
        );

        return redirect()
            ->route('staff.applications.show', $cfa_submission)
            ->with('status', 'Application updated.');
    }

    public function checkPhoneForEdit(Request $request, CfaSubmission $cfa_submission): JsonResponse
    {
        $this->assertOwnReferral($request, $cfa_submission);

        $validator = Validator::make($request->all(), [
            'phone' => ['required', 'regex:/^[6-9]\d{9}$/'],
        ]);

        if ($validator->fails()) {
            return response()->json([
                'ok' => false,
                'available' => null,
                'errors' => $validator->errors(),
            ], 422);
        }

        $phone = $validator->validated()['phone'];
        $duplicate = null;

        $newRow = CfaSubmission::query()
            ->where('phone', $phone)
            ->where('id', '!=', $cfa_submission->id)
            ->orderByDesc('id')
            ->first();

        if ($newRow) {
            $fyName = $newRow->fiscal_year_id
                ? FiscalYear::query()->whereKey($newRow->fiscal_year_id)->value('name')
                : null;
            $duplicate = [
                'name' => $newRow->applicant_name ?: null,
                'phase' => 'Current MUY',
                'fy' => $fyName,
                'source' => 'cfa_submissions',
            ];
        }

        $legacyRow = null;
        if (config('database.connections.legacy.database', '') !== '') {
            try {
                $legacyRow = DB::connection('legacy')
                    ->table('rbi_applicant_details')
                    ->where('phone', $phone)
                    ->select(['applicant_name'])
                    ->orderByDesc('application_id')
                    ->first();
            } catch (\Exception $e) {
                // Legacy DB unavailable — skip silently
            }
        }

        if ($duplicate === null && $legacyRow) {
            $duplicate = [
                'name' => $legacyRow->applicant_name ?: null,
                'phase' => 'Legacy Phase 2',
                'fy' => '2025-26',
                'source' => 'rbi_applicant_details',
            ];
        }

        $phase1Row = null;
        if (config('database.connections.legacy_phase1.database', '') !== '') {
            try {
                $phase1Row = DB::connection('legacy_phase1')
                    ->table('tblapplication')
                    ->where('MobileNumber', $phone)
                    ->select(['FullName'])
                    ->orderByDesc('ID')
                    ->first();
            } catch (\Exception $e) {
                // Phase 1 DB unavailable — skip silently
            }
        }

        if ($duplicate === null && $phase1Row) {
            $duplicate = [
                'name' => $phase1Row->FullName ?: null,
                'phase' => 'Legacy Phase 1',
                'fy' => '2024-25',
                'source' => 'tblapplication',
            ];
        }

        $exists = $duplicate !== null;

        return response()->json([
            'ok' => true,
            'available' => ! $exists,
            'message' => $exists
                ? 'This mobile number is already registered for an application. / यह मोबाइल नंबर पहले से पंजीकृत है।'
                : null,
            'duplicate' => $duplicate,
        ]);
    }

    public function phase2Data(Request $request): View
    {
        $staff = $request->user()->load(['district', 'hub', 'designationRecord']);
        $districtName = trim((string) ($staff->district?->name ?? ''));

        if (! $staff->district_id || $districtName === '') {
            return view('staff.phase2-data', [
                'staff' => $staff,
                'rows' => collect(),
                'legacyUnavailable' => false,
                'legacyMissingTables' => false,
                'noDistrict' => true,
            ]);
        }

        if ((string) config('database.connections.legacy.database', '') === '') {
            return view('staff.phase2-data', [
                'staff' => $staff,
                'rows' => collect(),
                'legacyUnavailable' => true,
                'legacyMissingTables' => false,
                'noDistrict' => false,
            ]);
        }

        if (! $this->hasLegacyPhase2Tables()) {
            return view('staff.phase2-data', [
                'staff' => $staff,
                'rows' => collect(),
                'legacyUnavailable' => false,
                'legacyMissingTables' => true,
                'noDistrict' => false,
            ]);
        }

        $query = $this->phase2BaseQueryForDistrict($districtName);
        $this->applyPhase2Filters($query, $request);

        $districtNorm = mb_strtolower($districtName);

        $categoryOptions = DB::connection('legacy')
            ->table('rbi_applicant_details as d')
            ->leftJoin('rbi_applications as a', 'a.id', '=', 'd.application_id')
            ->whereRaw('LOWER(TRIM(d.district)) = ?', [$districtNorm])
            ->whereNotNull('a.category')
            ->where('a.category', '!=', '')
            ->distinct()
            ->orderBy('a.category')
            ->pluck('a.category')
            ->values()
            ->all();

        $stageOptions = DB::connection('legacy')
            ->table('rbi_applicant_details as d')
            ->leftJoin('rbi_applications as a', 'a.id', '=', 'd.application_id')
            ->whereRaw('LOWER(TRIM(d.district)) = ?', [$districtNorm])
            ->whereNotNull('a.form_stage')
            ->where('a.form_stage', '!=', '')
            ->distinct()
            ->orderBy('a.form_stage')
            ->pluck('a.form_stage')
            ->values()
            ->all();

        $rows = $query
            ->orderByDesc('a.submission_date')
            ->orderByDesc('a.id')
            ->paginate(50)
            ->withQueryString();

        $applicationIds = collect($rows->items())
            ->pluck('application_id')
            ->filter(fn ($id) => (int) $id > 0)
            ->map(fn ($id) => (int) $id)
            ->values()
            ->all();

        $servicesByApp = $this->servicesByApplicationIds($applicationIds);

        $mappedRows = $rows->getCollection()->map(function ($row) use ($servicesByApp) {
            $serviceRows = $servicesByApp[(int) ($row->application_id ?? 0)] ?? [];

            return $this->buildPhase2ViewRow($row, $serviceRows);
        });

        $rows->setCollection($mappedRows);

        return view('staff.phase2-data', [
            'staff' => $staff,
            'rows' => $rows,
            'categoryOptions' => $categoryOptions,
            'stageOptions' => $stageOptions,
            'legacyUnavailable' => false,
            'legacyMissingTables' => false,
            'noDistrict' => false,
        ]);
    }

    public function phase1Data(Request $request): View
    {
        $staff = $request->user()->load(['district', 'hub', 'designationRecord']);
        $districtName = trim((string) ($staff->district?->name ?? ''));

        if (! $staff->district_id || $districtName === '') {
            return view('staff.phase1-data', [
                'staff' => $staff,
                'rows' => collect(),
                'phase1Unavailable' => false,
                'phase1MissingTables' => false,
                'noDistrict' => true,
            ]);
        }

        if ((string) config('database.connections.legacy_phase1.database', '') === '') {
            return view('staff.phase1-data', [
                'staff' => $staff,
                'rows' => collect(),
                'phase1Unavailable' => true,
                'phase1MissingTables' => false,
                'noDistrict' => false,
            ]);
        }

        if (! $this->hasLegacyPhase1Table()) {
            return view('staff.phase1-data', [
                'staff' => $staff,
                'rows' => collect(),
                'phase1Unavailable' => false,
                'phase1MissingTables' => true,
                'noDistrict' => false,
            ]);
        }

        $districtNorm = mb_strtolower($districtName);

        $query = DB::connection('legacy_phase1')
            ->table('tblapplication')
            ->whereRaw('LOWER(TRIM(City)) = ?', [$districtNorm])
            ->select([
                'ID as legacy_id',
                'ApplicationNumber as application_no',
                'FullName as full_name',
                'MobileNumber as mobile_number',
                'hub as hub_name',
                'City as city_name',
                'status as application_status',
                'ApplicationDate as application_date',
            ]);

        if ($request->filled('search')) {
            $search = '%'.trim((string) $request->query('search')).'%';
            $query->where(function ($q) use ($search) {
                $q->where('ApplicationNumber', 'like', $search)
                    ->orWhere('FullName', 'like', $search)
                    ->orWhere('MobileNumber', 'like', $search);
            });
        }

        $rows = $query
            ->orderByDesc('ApplicationDate')
            ->orderByDesc('ID')
            ->paginate(50)
            ->withQueryString();

        return view('staff.phase1-data', [
            'staff' => $staff,
            'rows' => $rows,
            'phase1Unavailable' => false,
            'phase1MissingTables' => false,
            'noDistrict' => false,
        ]);
    }

    public function exportPhase2Data(Request $request): StreamedResponse
    {
        $staff = $request->user()->load('district');
        $districtName = trim((string) ($staff->district?->name ?? ''));

        abort_if(! $staff->district_id || $districtName === '', 403, 'District not assigned.');
        abort_if((string) config('database.connections.legacy.database', '') === '', 422, 'Legacy database is not configured.');
        abort_if(! $this->hasLegacyPhase2Tables(), 422, 'Required legacy Phase 2 tables are missing.');

        $query = $this->phase2BaseQueryForDistrict($districtName);
        $this->applyPhase2Filters($query, $request);

        $allRows = $query->orderByDesc('a.submission_date')->orderByDesc('a.id')->get();
        $applicationIds = $allRows->pluck('application_id')
            ->filter(fn ($id) => (int) $id > 0)
            ->map(fn ($id) => (int) $id)
            ->values()
            ->all();
        $servicesByApp = $this->servicesByApplicationIds($applicationIds);

        $viewRowsForExport = $allRows->map(function ($row) use ($servicesByApp) {
            $serviceRows = $servicesByApp[(int) ($row->application_id ?? 0)] ?? [];

            return $this->buildPhase2ViewRow($row, $serviceRows);
        });

        $fileNameDistrict = preg_replace('/[^a-z0-9]+/i', '-', strtolower($districtName)) ?: 'district';
        $filename = 'phase2-data-'.$fileNameDistrict.'-'.now()->format('Ymd_His').'.csv';

        $headers = [
            'Content-Type' => 'text/csv; charset=UTF-8',
            'Content-Disposition' => 'attachment; filename="'.$filename.'"',
        ];

        return response()->streamDownload(function () use ($viewRowsForExport) {
            $out = fopen('php://output', 'wb');
            if ($out === false) {
                return;
            }

            // Excel-friendly UTF-8 BOM
            fwrite($out, "\xEF\xBB\xBF");

            fputcsv($out, [
                'Application No',
                'Applicant',
                'Phone',
                'District',
                'Block',
                'Village',
                'Product',
                'Category',
                'Form Stage',
                'Submission Date',
                'Business Category',
                'Turnover Last Year',
                'Loan Taken',
                'Bank Loan',
                'Cohort',
                'Onboarding Status',
                'Marketing Service',
                'Marketing Details',
                'Access To Finance Service',
                'Access To Finance Details',
                'Training Service',
                'Training Details',
                'Other Services Details',
                'All Services',
                'CFA',
            ]);

            foreach ($viewRowsForExport as $viewRow) {
                fputcsv($out, [
                    $viewRow['application_no'],
                    $viewRow['applicant_name'],
                    $viewRow['phone'],
                    $viewRow['district'],
                    $viewRow['block'],
                    $viewRow['village'],
                    $viewRow['product'],
                    $viewRow['app_category'],
                    $viewRow['form_stage'],
                    $viewRow['submission_date'],
                    $viewRow['business_category'],
                    $viewRow['turnover_last_year'],
                    $viewRow['loan_taken'],
                    $viewRow['bank_loan'],
                    $viewRow['cohort_name'],
                    $viewRow['onboarding_status'],
                    $viewRow['marketing_service'],
                    $viewRow['marketing_details'],
                    $viewRow['finance_service'],
                    $viewRow['finance_details'],
                    $viewRow['training_service'],
                    $viewRow['training_details'],
                    $viewRow['other_services_details'],
                    $viewRow['all_services'],
                    (string) ((int) ($viewRow['legacy_application_id'] ?? 0)),
                ]);
            }

            fclose($out);
        }, $filename, $headers);
    }

    private function hasLegacyPhase2Tables(): bool
    {
        try {
            return Schema::connection('legacy')->hasTable('rbi_applications')
                && Schema::connection('legacy')->hasTable('rbi_applicant_details')
                && Schema::connection('legacy')->hasTable('rbi_services_assigned');
        } catch (\Throwable) {
            return false;
        }
    }

    private function hasLegacyPhase1Table(): bool
    {
        try {
            return Schema::connection('legacy_phase1')->hasTable('tblapplication');
        } catch (\Throwable) {
            return false;
        }
    }

    private function applyPhase2Filters(QueryBuilder $query, Request $request): void
    {
        if ($request->filled('search')) {
            $search = '%'.trim((string) $request->query('search')).'%';
            $query->where(function ($q) use ($search) {
                $q->where('a.application_no', 'like', $search)
                    ->orWhere('d.applicant_name', 'like', $search)
                    ->orWhere('d.phone', 'like', $search);
            });
        }

        if ($request->filled('category')) {
            $query->where('a.category', (string) $request->query('category'));
        }

        if ($request->filled('form_stage')) {
            $query->where('a.form_stage', (string) $request->query('form_stage'));
        }

        $onboard = (string) $request->query('onboarding_status', '');
        if ($onboard === 'yes') {
            $query->whereNotNull('oa.status')->where('oa.status', '!=', '');
        } elseif ($onboard === 'no') {
            $query->where(function ($q) {
                $q->whereNull('oa.status')->orWhere('oa.status', '');
            });
        }
    }

    private function phase2BaseQueryForDistrict(string $districtName)
    {
        $districtNorm = mb_strtolower(trim($districtName));

        return DB::connection('legacy')
            ->table('rbi_applicant_details as d')
            ->leftJoin('rbi_applications as a', 'a.id', '=', 'd.application_id')
            ->leftJoin('rbi_onboarded_applicants as oa', 'oa.application_id', '=', 'd.application_id')
            ->leftJoin('rbi_onboarding_batches as ob', 'ob.id', '=', 'oa.onboarding_batch_id')
            ->leftJoin(DB::raw('(
                SELECT e1.application_id, e1.turnover_last_year
                FROM rbi_enterprise_details e1
                INNER JOIN (
                    SELECT application_id, MAX(id) AS max_id
                    FROM rbi_enterprise_details
                    GROUP BY application_id
                ) t ON t.application_id = e1.application_id AND t.max_id = e1.id
            ) as ed'), 'ed.application_id', '=', 'd.application_id')
            ->whereRaw('LOWER(TRIM(d.district)) = ?', [$districtNorm])
            ->select([
                'd.application_id',
                'd.applicant_name',
                'd.phone',
                'd.district',
                'd.block',
                'd.village',
                'd.gender',
                'd.is_shg_member',
                'd.caste',
                'd.loan_taken',
                'd.bank_loan',
                'a.application_no',
                'a.product',
                'a.category as app_category',
                'a.form_stage',
                'a.submission_date',
                'a.created_at',
                'a.business_category',
                'ob.batch_name as cohort_name',
                'oa.status as onboard_status_db',
                'ed.turnover_last_year as turnover_last_year',
            ]);
    }

    /**
     * @param  list<int>  $applicationIds
     * @return array<int, list<object>>
     */
    private function servicesByApplicationIds(array $applicationIds): array
    {
        if ($applicationIds === []) {
            return [];
        }

        return DB::connection('legacy')
            ->table('rbi_services_assigned')
            ->whereIn('application_id', $applicationIds)
            ->orderBy('service_name')
            ->get(['application_id', 'service_name', 'category', 'service_number'])
            ->groupBy('application_id')
            ->map(fn ($rows) => $rows->values()->all())
            ->all();
    }

    /**
     * @param  list<object>  $serviceRows
     * @return array<string, mixed>
     */
    private function buildPhase2ViewRow(object $row, array $serviceRows): array
    {
        $na = 'NA';
        $services = $this->summarizeServices($serviceRows);

        return [
            'legacy_application_id' => (int) ($row->application_id ?? 0),
            'application_no' => (string) ($row->application_no ?: $na),
            'applicant_name' => (string) ($row->applicant_name ?: $na),
            'phone' => (string) ($row->phone ?: $na),
            'gender' => (string) (($row->gender ?? '') !== '' ? $row->gender : $na),
            'caste' => (string) (($row->caste ?? '') !== '' ? $row->caste : $na),
            'is_shg_member' => (string) (($row->is_shg_member ?? '') !== '' ? $row->is_shg_member : $na),
            'district' => (string) ($row->district ?: $na),
            'block' => (string) ($row->block ?: $na),
            'village' => (string) ($row->village ?: $na),
            'product' => (string) ($row->product ?: $na),
            'app_category' => (string) ($row->app_category ?: $na),
            'form_stage' => (string) ($row->form_stage ?: $na),
            'submission_date' => $row->submission_date ? (string) $row->submission_date : $na,
            'business_category' => (string) ($row->business_category ?: $na),
            'turnover_last_year' => (string) (($row->turnover_last_year ?? '') !== '' ? $row->turnover_last_year : $na),
            'loan_taken' => (string) (($row->loan_taken ?? '') !== '' ? $row->loan_taken : $na),
            'bank_loan' => (string) (($row->bank_loan ?? '') !== '' ? $row->bank_loan : $na),
            'cohort_name' => (string) ($row->cohort_name ?: $na),
            'onboarding_status' => ! empty($row->onboard_status_db) ? 'yes' : 'no',
            'marketing_service' => $services['marketing_service'],
            'marketing_details' => $services['marketing_details'],
            'finance_service' => $services['finance_service'],
            'finance_details' => $services['finance_details'],
            'training_service' => $services['training_service'],
            'training_details' => $services['training_details'],
            'other_services_details' => $services['other_services_details'],
            'all_services' => $services['all_services'],
        ];
    }

    /**
     * @param  list<object>  $serviceRows
     * @return array<string, string>
     */
    private function summarizeServices(array $serviceRows): array
    {
        $na = 'NA';
        $allNames = [];
        $svc = [
            'marketing' => ['flag' => 'No', 'details' => []],
            'finance' => ['flag' => 'No', 'details' => []],
            'training' => ['flag' => 'No', 'details' => []],
            'other' => [],
        ];

        foreach ($serviceRows as $service) {
            $serviceName = trim((string) ($service->service_name ?? ''));
            $category = mb_strtolower(trim((string) ($service->category ?? '')));
            $nameLower = mb_strtolower($serviceName);

            if ($serviceName !== '') {
                $allNames[] = $serviceName;
            }

            $detailParts = [];
            if ($serviceName !== '') {
                $detailParts[] = $serviceName;
            }
            if (! empty($service->service_number)) {
                $detailParts[] = 'Ref: '.$service->service_number;
            }
            $detail = implode(' | ', $detailParts);

            $isMarketing = str_contains($nameLower, 'market') || str_contains($nameLower, 'brand');
            $isFinance = str_contains($nameLower, 'finance') || str_contains($nameLower, 'loan') || str_contains($category, 'finance');
            $isTraining = str_contains($nameLower, 'training') || str_contains($nameLower, 'workshop') || str_contains($nameLower, 'session') || str_contains($category, 'training');

            if ($isMarketing) {
                $svc['marketing']['flag'] = 'Yes';
                if ($detail !== '') {
                    $svc['marketing']['details'][] = $detail;
                }
            } elseif ($isFinance) {
                $svc['finance']['flag'] = 'Yes';
                if ($detail !== '') {
                    $svc['finance']['details'][] = $detail;
                }
            } elseif ($isTraining) {
                $svc['training']['flag'] = 'Yes';
                if ($detail !== '') {
                    $svc['training']['details'][] = $detail;
                }
            } elseif ($detail !== '') {
                $svc['other'][] = $detail;
            }
        }

        $allNames = array_values(array_unique($allNames));
        sort($allNames);

        return [
            'marketing_service' => $svc['marketing']['flag'],
            'marketing_details' => $svc['marketing']['details'] !== [] ? implode('; ', $svc['marketing']['details']) : $na,
            'finance_service' => $svc['finance']['flag'],
            'finance_details' => $svc['finance']['details'] !== [] ? implode('; ', $svc['finance']['details']) : $na,
            'training_service' => $svc['training']['flag'],
            'training_details' => $svc['training']['details'] !== [] ? implode('; ', $svc['training']['details']) : $na,
            'other_services_details' => $svc['other'] !== [] ? implode('; ', $svc['other']) : $na,
            'all_services' => $allNames !== [] ? implode(', ', $allNames) : $na,
        ];
    }

    /**
     * @return array{0: string, 1: bool}
     */
    private function applyStaffApplicationScope(Builder $query, User $staff, string $scopeParam): array
    {
        $scope = in_array($scopeParam, ['mine', 'district'], true) ? $scopeParam : 'mine';
        $forceMine = false;
        if ($scope === 'district' && (int) $staff->district_id > 0) {
            $query->where('district_id', (int) $staff->district_id);
        } else {
            if ($scope === 'district') {
                $forceMine = true;
            }
            $scope = 'mine';
            $query->where('referral_user_id', (int) $staff->id);
        }

        return [$scope, $forceMine];
    }

    /**
     * District-wide list: native Phase 3 CFA only (excludes mirrored legacy Phase 2 rows).
     * When a Phase 3 default fiscal year exists, scope to that FY like the staff dashboard.
     */
    private function applyDistrictPhase3OnlyFilters(Builder $query): void
    {
        $query->where(function (Builder $q): void {
            $q->whereNull('source')
                ->orWhere('source', '!=', 'legacy_phase2');
        });
        $activeFyId = (int) optional(FiscalYear::phase3Default())->id;
        if ($activeFyId > 0) {
            $query->where('fiscal_year_id', $activeFyId);
        }
    }

    private function applyApplicationsSearch(Builder $query, string $search): void
    {
        $search = str_replace(['\\', '%', '_'], ['\\\\', '\\%', '\\_'], $search);
        $like = '%'.$search.'%';
        $query->where(function (Builder $q) use ($like) {
            $q->where('application_no', 'like', $like)
                ->orWhere('applicant_name', 'like', $like)
                ->orWhere('phone', 'like', $like)
                ->orWhere('payload', 'like', $like);
        });
    }

    /**
     * Dot-path keys (e.g. business_plan.stage) for CSV; values are stringified.
     *
     * @return array<string, string>
     */
    private function flattenCfaPayloadForExport(array $data, string $prefix = ''): array
    {
        $out = [];
        foreach ($data as $key => $value) {
            $k = (string) $key;
            $path = $prefix === '' ? $k : $prefix.'.'.$k;
            if (is_array($value)) {
                if ($value === []) {
                    $out[$path] = '';
                } elseif (array_is_list($value)) {
                    $out[$path] = json_encode($value, JSON_UNESCAPED_UNICODE) ?: '[]';
                } else {
                    $out = array_merge($out, $this->flattenCfaPayloadForExport($value, $path));
                }
            } else {
                $out[$path] = $this->stringifyCsvExportCell($value);
            }
        }

        return $out;
    }

    private function stringifyCsvExportCell(mixed $v): string
    {
        if ($v === null) {
            return '';
        }
        if (is_bool($v)) {
            return $v ? '1' : '0';
        }
        if (is_scalar($v)) {
            return (string) $v;
        }

        return json_encode($v, JSON_UNESCAPED_UNICODE) ?: '';
    }

    /**
     * A district staff can view/edit either:
     *  - their own referral submissions (any district), OR
     *  - any submission whose district matches their assigned district.
     * This lets staff handle walk-ins / public-form entries from their district
     * even though the submission wasn't created via their referral link.
     */
    private function assertOwnReferral(Request $request, CfaSubmission $submission): void
    {
        $user = $request->user();
        $isOwnReferral = (int) $submission->referral_user_id === (int) $user->id;
        $sameDistrict = $user->district_id
            && (int) $submission->district_id === (int) $user->district_id;

        abort_unless(
            $isOwnReferral || $sameDistrict,
            403,
            'You can only access applications from your assigned district or your own referrals.'
        );
    }

    /**
     * Phase 2 legacy rows match CFA by {@see CfaSubmission::$application_no}. Keys are
     * `mb_strtolower(trim(application_no))` for case-insensitive lookup.
     *
     * @param  list<string>  $applicationNos
     * @return array<string, CfaSubmission>
     */
    private function cfaSubmissionsByApplicationNoKeyVisibleToStaff(User $staff, array $applicationNos): array
    {
        $normalizedKeys = collect($applicationNos)
            ->map(fn ($n) => mb_strtolower(trim((string) $n)))
            ->filter(fn ($k) => $k !== '' && $k !== 'na')
            ->unique()
            ->values()
            ->all();

        if ($normalizedKeys === []) {
            return [];
        }

        $staffId = (int) $staff->id;
        $districtId = (int) ($staff->district_id ?? 0);

        $query = CfaSubmission::query()
            ->where(function (Builder $q) use ($normalizedKeys): void {
                foreach ($normalizedKeys as $nk) {
                    $q->orWhereRaw('LOWER(TRIM(application_no)) = ?', [$nk]);
                }
            })
            ->where(function (Builder $q) use ($staffId, $districtId): void {
                $q->where('referral_user_id', $staffId);
                if ($districtId > 0) {
                    $q->orWhere('district_id', $districtId);
                }
            });

        $out = [];
        foreach ($query->cursor() as $submission) {
            $k = mb_strtolower(trim((string) ($submission->application_no ?? '')));
            if ($k !== '') {
                $out[$k] = $submission;
            }
        }

        return $out;
    }

    /**
     * @return array<string, mixed>
     */
    private function cfaOldInputFromSubmission(CfaSubmission $submission): array
    {
        $p = is_array($submission->payload) ? $submission->payload : [];

        $rt = $p['registration_type'] ?? null;
        if (is_string($rt) && str_starts_with($rt, 'Other:')) {
            $p['registration_type'] = 'Other';
            $rest = trim(substr($rt, strlen('Other:')));
            if ($rest !== '' && empty($p['registration_type_other'])) {
                $p['registration_type_other'] = $rest;
            }
        }

        $p['phone'] = $submission->phone;
        $p['consent'] = '1';

        return $p;
    }

    private function normalizeEmptySelects(Request $request): void
    {
        $merge = [];
        foreach (['regular_buyer', 'financial_support', 'id_proof_type'] as $key) {
            $v = $request->input($key);
            if ($v === '' || $v === null) {
                $merge[$key] = null;
            }
        }
        if ($merge !== []) {
            $request->merge($merge);
        }
    }
}
