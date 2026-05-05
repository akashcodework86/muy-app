<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\District;
use App\Models\Hub;
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
        [$hubId, $districtId, $q] = $this->extractFilters($request);

        $hubs = Hub::query()
            ->orderBy('sort_order')
            ->orderBy('name')
            ->get(['id', 'name']);

        $districts = District::query()
            ->when($hubId, fn ($query) => $query->where('hub_id', $hubId))
            ->orderBy('name')
            ->get(['id', 'name', 'hub_id']);

        $commonColumns = $this->commonColumnMap();
        $mergedRows = $this->buildRows($hubId, $districtId, $q);
        $rows = $this->paginateMergedRows($mergedRows, $request, 30);

        return view('admin.onboarded.index', [
            'rows' => $rows,
            'hubs' => $hubs,
            'districts' => $districts,
            'commonColumns' => $commonColumns,
            'filters' => [
                'hub' => $hubId,
                'district' => $districtId,
                'q' => $q,
            ],
        ]);
    }

    public function export(Request $request): StreamedResponse
    {
        [$hubId, $districtId, $q] = $this->extractFilters($request);
        $commonColumns = $this->commonColumnMap();
        $rows = $this->buildRows($hubId, $districtId, $q);

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

    private function extractFilters(Request $request): array
    {
        $hubId = $request->integer('hub') ?: null;
        $districtId = $request->integer('district') ?: null;
        $q = trim((string) $request->string('q')->toString());

        return [$hubId, $districtId, $q];
    }

    private function buildRows(?int $hubId, ?int $districtId, string $q): Collection
    {
        $phase3Rows = $this->phase3Rows($hubId, $districtId, $q);
        $commonColumns = $this->commonColumnMap();

        return $phase3Rows
            ->map(fn (array $row) => $this->enrichRowWithDetails($row))
            ->map(fn (array $row) => $this->mapCommonColumnsForRow($row, $commonColumns))
            ->values();
    }

    private function phase3Rows(?int $hubId, ?int $districtId, string $q): Collection
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
                JSON_UNQUOTE(JSON_EXTRACT(cs.payload, '$.guardian_name')) as guardian_name,
                JSON_UNQUOTE(JSON_EXTRACT(cs.payload, '$.gender')) as gender,
                JSON_UNQUOTE(JSON_EXTRACT(cs.payload, '$.dob')) as dob,
                COALESCE(JSON_UNQUOTE(JSON_EXTRACT(cs.payload, '$.district')), d.name) as district,
                JSON_UNQUOTE(JSON_EXTRACT(cs.payload, '$.block')) as block_name,
                h.name as hub_name,
                ob.id as onboarding_batch_id,
                ob.name as onboarding_batch_name,
                cs.payload as full_details_json
            ")
            ->get()
            ->map(fn ($row) => (array) $row)
            ->pipe(fn (Collection $rows) => $this->replacePhase3LegacySourceDetails($rows));
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
}
