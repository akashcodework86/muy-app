<?php

namespace App\Services\Exports;

use App\Models\District;
use App\Services\CfaBusinessStageService;
use App\Support\SimpleXlsxWriter;
use Carbon\Carbon;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * Onboarded-only turnover-wise Excel pack: Phase 2 (FY 2025–26) + Phase 3 (FY 2026–27).
 * Sheets: Summary, Phase 2, Phase 3.
 */
final class OnboardedTurnoverWisePackService
{
    private const P2_START = '2025-04-02';

    private const P2_END = '2026-04-01';

    /** @var list<string> */
    private const SLAB_ORDER = [
        'Zero income',
        'INR > 0 – 1 Lakh',
        'INR 1 – 5 Lakh',
        'INR 5 – 10 Lakh',
        'INR 10 – 25 Lakh',
        'INR 25 Lakh+',
        'Not specified',
    ];

    /** @var list<string> */
    private const DETAIL_KEYS = [
        'sn',
        'turnover_slab',
        'turnover',
        'turnover_display',
        'is_registered',
        'business_stage',
        'application_no',
        'applicant_name',
        'guardian_name',
        'gender',
        'dob',
        'phone',
        'alt_mobile',
        'email',
        'category',
        'caste',
        'education',
        'district',
        'hub',
        'block',
        'village',
        'pincode',
        'is_shg_member',
        'shg_name',
        'lakhpati',
        'id_proof_type',
        'id_proof_number',
        'loan_taken',
        'bank_loan',
        'current_employment',
        'employed_count',
        'business_category',
        'product',
        'form_stage',
        'techuse',
        'empwomen',
        'sustainability',
        'training_mode',
        'training_received',
        'challenges',
        'expectations',
        'expectation_other',
        'migrated_for_employment',
        'submitted_by_name',
        'submitted_by_mobile',
        'department_name',
        'info_source',
        'resource_name',
        'submission_date',
        'batch',
        'onboarded_at',
        'onboarded_status',
        'source_note',
    ];

    /** @var list<string> */
    private const PHASE2_SERVICE_KEYS = [
        'marketing_service',
        'marketing_details',
        'finance_service',
        'finance_details',
        'training_service',
        'training_details',
        'other_services_details',
        'all_services',
    ];

    /**
     * @return array{
     *     meta: array<string, mixed>,
     *     summary: array<string, mixed>,
     *     phase2: list<array<string, mixed>>,
     *     phase3: list<array<string, mixed>>
     * }
     */
    public function build(?int $districtId = null, ?string $districtSlug = null): array
    {
        $district = $this->resolveDistrict($districtId, $districtSlug);
        $districtId = $district ? (int) $district->id : null;
        $districtLabel = $district?->name ?? 'All districts';
        $legacyDistrictNames = $this->legacyDistrictNames($district);

        $phase2 = $this->sortByTurnover($this->phase2Rows($legacyDistrictNames));
        $phase3 = $this->sortByTurnover($this->phase3Rows($districtId));

        $this->numberRows($phase2);
        $this->numberRows($phase3);

        return [
            'meta' => [
                'title' => 'Onboarded turnover-wise — Phase 2 (2025–26) + Phase 3 (2026–27)',
                'district' => $districtLabel,
                'district_id' => $districtId,
                'district_slug' => $district?->slug ?? 'all',
                'as_of' => now()->timezone(config('app.timezone'))->format('d M Y, g:i A T'),
                'rules' => [
                    'ONLY onboarded records',
                    'Phase 2 (FY 2025–26): rbi_onboarded_applicants with non-empty status; submission_date between '.self::P2_START.' and '.self::P2_END,
                    'Phase 3 (FY 2026–27): locked MIS onboarding batches (includes legacy_phase2 imports onboarded via MIS)',
                    'Turnover slabs match Data Centre Income / Turnover analysis',
                    'MIS legacy_phase2 locked members appear on Phase 3 sheet only',
                    'District filter: '.$districtLabel,
                ],
            ],
            'summary' => [
                'phase2_total' => count($phase2),
                'phase3_total' => count($phase3),
                'combined_total' => count($phase2) + count($phase3),
                'phase2_slabs' => $this->slabCounts($phase2),
                'phase3_slabs' => $this->slabCounts($phase3),
                'combined_slabs' => $this->slabCounts(array_merge($phase2, $phase3)),
            ],
            'phase2' => $phase2,
            'phase3' => $phase3,
        ];
    }

    /**
     * @param  array<string, mixed>  $pack
     */
    public function writeToPath(array $pack, string $absolutePath): void
    {
        $dir = dirname($absolutePath);
        if (! is_dir($dir)) {
            mkdir($dir, 0755, true);
        }

        $meta = $pack['meta'] ?? [];
        $summary = $pack['summary'] ?? [];
        $writer = new SimpleXlsxWriter;

        $summaryRows = [
            [(string) ($meta['title'] ?? 'Onboarded turnover-wise')],
            [],
            ['District', (string) ($meta['district'] ?? '')],
            ['Generated at', (string) ($meta['as_of'] ?? '')],
            [],
            ['Rules'],
        ];
        foreach (($meta['rules'] ?? []) as $rule) {
            $summaryRows[] = ['• '.(string) $rule];
        }
        $summaryRows[] = [];
        $summaryRows[] = ['Totals'];
        $summaryRows[] = ['Phase', 'Count'];
        $summaryRows[] = ['Phase 2 (FY 2025–26)', (int) ($summary['phase2_total'] ?? 0)];
        $summaryRows[] = ['Phase 3 (FY 2026–27)', (int) ($summary['phase3_total'] ?? 0)];
        $summaryRows[] = ['Combined', (int) ($summary['combined_total'] ?? 0)];
        $summaryRows[] = [];
        $summaryRows[] = ['Turnover slab', 'Phase 2', 'Phase 3', 'Combined'];

        $p2Slabs = (array) ($summary['phase2_slabs'] ?? []);
        $p3Slabs = (array) ($summary['phase3_slabs'] ?? []);
        $combinedSlabs = (array) ($summary['combined_slabs'] ?? []);
        foreach (self::SLAB_ORDER as $slab) {
            $summaryRows[] = [
                $slab,
                (int) ($p2Slabs[$slab] ?? 0),
                (int) ($p3Slabs[$slab] ?? 0),
                (int) ($combinedSlabs[$slab] ?? 0),
            ];
        }

        $writer->addSheet('Summary', $summaryRows);
        $writer->addSheet('Phase 2', $this->detailSheetRows($pack['phase2'] ?? [], true));
        $writer->addSheet('Phase 3', $this->detailSheetRows($pack['phase3'] ?? [], false));
        $writer->save($absolutePath);
    }

    /**
     * @param  list<array<string, mixed>>  $details
     * @return list<list<string|int|float>>
     */
    private function detailSheetRows(array $details, bool $includePhase2Services): array
    {
        $headers = [
            'Sr No', 'Turnover Slab', 'Turnover (₹)', 'Turnover (display)', 'Registered', 'Business Stage',
            'Application No', 'Applicant Name', 'Guardian Name', 'Gender', 'DOB',
            'Phone', 'Alt Mobile', 'Email', 'Category', 'Caste', 'Education',
            'District', 'Hub', 'Block', 'Village', 'Pincode',
            'Is SHG Member', 'SHG Name', 'Lakhpati',
            'ID Proof Type', 'ID Proof Number',
            'Loan Taken', 'Bank Loan', 'Current Employment', 'Employed Count',
            'Business Category', 'Product', 'Form Stage',
            'Tech Use', 'Emp Women', 'Sustainability',
            'Training Mode', 'Training Received', 'Challenges', 'Expectations', 'Expectation Other',
            'Migrated For Employment',
            'Submitted By Name', 'Submitted By Mobile', 'Department Name', 'Info Source', 'Resource Name',
            'Submission Date', 'Batch', 'Onboarded At', 'Onboarded Status', 'Source Note',
        ];

        $keys = self::DETAIL_KEYS;
        if ($includePhase2Services) {
            $headers = array_merge($headers, [
                'Marketing Service', 'Marketing Details',
                'Access To Finance Service', 'Access To Finance Details',
                'Training Service', 'Training Details',
                'Other Services Details', 'All Services',
            ]);
            $keys = array_merge($keys, self::PHASE2_SERVICE_KEYS);
        }

        $out = [$headers];
        foreach ($details as $row) {
            $line = [];
            foreach ($keys as $key) {
                $val = $row[$key] ?? '';
                if ($key === 'turnover' && ($val === null || $val === '')) {
                    $line[] = '';
                } elseif ($key === 'turnover' && is_numeric($val)) {
                    $line[] = 0 + $val;
                } elseif (is_numeric($val) && ! in_array($key, ['application_no', 'phone', 'alt_mobile', 'pincode', 'id_proof_number', 'submitted_by_mobile'], true)) {
                    $line[] = 0 + $val;
                } else {
                    $line[] = (string) $val;
                }
            }
            $out[] = $line;
        }

        return $out;
    }

    /**
     * @param  list<string>  $districtNamesLower
     * @return list<array<string, mixed>>
     */
    private function phase2Rows(array $districtNamesLower): array
    {
        if (! $this->legacyPhase2Ready()) {
            return [];
        }

        $query = DB::connection('legacy')
            ->table('rbi_onboarded_applicants as oa')
            ->join('rbi_applications as a', 'a.id', '=', 'oa.application_id')
            ->leftJoin('rbi_applicant_details as d', 'd.application_id', '=', 'a.id')
            ->leftJoin('rbi_onboarding_batches as lob', 'lob.id', '=', 'oa.onboarding_batch_id')
            ->whereNotNull('oa.status')
            ->where('oa.status', '<>', '')
            ->whereNotNull('a.submission_date')
            ->whereRaw('DATE(a.submission_date) BETWEEN ? AND ?', [self::P2_START, self::P2_END]);

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

        if ($districtNamesLower !== []) {
            $query->where(function ($q) use ($districtNamesLower): void {
                foreach ($districtNamesLower as $name) {
                    $q->orWhereRaw('LOWER(TRIM(COALESCE(d.district, \'\'))) = ?', [$name]);
                }
            });
        }

        $select = [
            'a.id as application_id',
            'a.application_no',
            'a.product',
            'a.category',
            'a.form_stage',
            'a.business_category',
            'a.submission_date',
            'd.applicant_name',
            'd.guardian_name',
            'd.gender',
            'd.dob',
            'd.phone',
            'd.alt_mobile',
            'd.email',
            'd.caste',
            'd.education',
            'd.district',
            'd.block',
            'd.village',
            'd.pincode',
            'd.is_shg_member',
            'd.shg_name',
            'd.lakhpati',
            'd.id_proof_type',
            'd.id_proof_number',
            'd.loan_taken',
            'd.bank_loan',
            'd.current_employment',
            'd.employed_count',
            'd.training_mode',
            'd.challenges',
            'd.expectations',
            'd.expectation_other',
            'd.migrated_for_employment',
            'd.submitted_by_name',
            'd.submitted_by_mobile',
            'd.department_name',
            'd.info_source',
            'd.resource_name',
            'lob.batch_name',
            'lob.onboarding_date',
            'oa.onboarded_at',
            'oa.status as onboard_status',
        ];

        if ($this->legacyHasTable('rbi_enterprise_details')) {
            $select[] = 'ed.turnover_last_year';
            $select[] = 'ed.is_registered';
        }

        $rows = $query->orderBy('d.applicant_name')->get($select);

        $appIds = $rows->pluck('application_id')
            ->map(fn ($id) => (int) $id)
            ->filter(fn (int $id) => $id > 0)
            ->values()
            ->all();
        $servicesByApp = $this->phase2ServicesByApplicationIds($appIds);

        $out = [];
        foreach ($rows as $row) {
            $appId = (int) ($row->application_id ?? 0);
            $turnoverRaw = $this->legacyHasTable('rbi_enterprise_details')
                ? ($row->turnover_last_year ?? null)
                : null;
            $registered = $this->legacyHasTable('rbi_enterprise_details')
                ? trim((string) ($row->is_registered ?? ''))
                : '';
            [$turnoverNum, $slab, $display] = $this->resolveTurnover($turnoverRaw);
            $stage = $this->resolveBusinessStage($registered, $turnoverNum, (string) ($row->form_stage ?? ''));
            $services = $servicesByApp[$appId] ?? $this->emptyPhase2Services();
            $statusRaw = trim((string) ($row->onboard_status ?? ''));
            $onboardedAt = $row->onboarded_at ?: ($row->onboarding_date ?? null);

            $out[] = array_merge([
                'turnover_slab' => $slab,
                'turnover' => $turnoverNum,
                'turnover_display' => $display,
                'is_registered' => $registered !== '' ? $this->normalizeYesNo($registered) : '—',
                'business_stage' => $stage,
                'application_no' => $this->cell($row->application_no ?? null),
                'applicant_name' => $this->cell($row->applicant_name ?? null),
                'guardian_name' => $this->cell($row->guardian_name ?? null),
                'gender' => $this->cell($row->gender ?? null),
                'dob' => $this->cell($row->dob ?? null),
                'phone' => $this->cell($row->phone ?? null),
                'alt_mobile' => $this->cell($row->alt_mobile ?? null),
                'email' => $this->cell($row->email ?? null),
                'category' => $this->cell($row->category ?? null),
                'caste' => $this->cell($row->caste ?? null),
                'education' => $this->cell($row->education ?? null),
                'district' => $this->cell($row->district ?? null),
                'hub' => '—',
                'block' => $this->cell($row->block ?? null),
                'village' => $this->cell($row->village ?? null),
                'pincode' => $this->cell($row->pincode ?? null),
                'is_shg_member' => $this->cell($row->is_shg_member ?? null),
                'shg_name' => $this->cell($row->shg_name ?? null),
                'lakhpati' => $this->cell($row->lakhpati ?? null),
                'id_proof_type' => $this->cell($row->id_proof_type ?? null),
                'id_proof_number' => $this->cell($row->id_proof_number ?? null),
                'loan_taken' => $this->cell($row->loan_taken ?? null),
                'bank_loan' => $this->cell($row->bank_loan ?? null),
                'current_employment' => $this->cell($row->current_employment ?? null),
                'employed_count' => $this->cell($row->employed_count ?? null),
                'business_category' => $this->cell($row->business_category ?? null),
                'product' => $this->cell($row->product ?? null),
                'form_stage' => $this->cell($row->form_stage ?? null),
                'techuse' => '—',
                'empwomen' => '—',
                'sustainability' => '—',
                'training_mode' => $this->cell($row->training_mode ?? null),
                'training_received' => '—',
                'challenges' => $this->cell($row->challenges ?? null),
                'expectations' => $this->cell($row->expectations ?? null),
                'expectation_other' => $this->cell($row->expectation_other ?? null),
                'migrated_for_employment' => $this->cell($row->migrated_for_employment ?? null),
                'submitted_by_name' => $this->cell($row->submitted_by_name ?? null),
                'submitted_by_mobile' => $this->cell($row->submitted_by_mobile ?? null),
                'department_name' => $this->cell($row->department_name ?? null),
                'info_source' => $this->cell($row->info_source ?? null),
                'resource_name' => $this->cell($row->resource_name ?? null),
                'submission_date' => $this->formatDate($row->submission_date ?? null),
                'batch' => $this->cell($row->batch_name ?? null),
                'onboarded_at' => $this->formatDate($onboardedAt),
                'onboarded_status' => $statusRaw !== '' ? 'Onboarded ('.$statusRaw.')' : 'Onboarded',
                'source_note' => 'Legacy Phase 2 (rbiphase2)',
            ], $services);
        }

        return $out;
    }

    /**
     * @return list<array<string, mixed>>
     */
    private function phase3Rows(?int $districtId): array
    {
        if (! Schema::hasTable('onboarding_batch_cfa')
            || ! Schema::hasTable('onboarding_batches')
            || ! Schema::hasTable('cfa_submissions')) {
            return [];
        }

        $query = DB::table('onboarding_batch_cfa as obc')
            ->join('onboarding_batches as ob', 'ob.id', '=', 'obc.onboarding_batch_id')
            ->join('cfa_submissions as cs', 'cs.id', '=', 'obc.cfa_submission_id')
            ->leftJoin('districts as d', 'd.id', '=', 'cs.district_id')
            ->leftJoin('hubs as h', 'h.id', '=', 'ob.hub_id')
            ->where('ob.status', 'locked')
            ->whereNotNull('ob.locked_at')
            ->whereNotNull('obc.cfa_submission_id');

        if ($districtId) {
            $query->where('cs.district_id', $districtId);
        }

        $legacyDb = trim((string) config('database.connections.legacy.database', ''));
        // Cross-DB join only works when default + legacy are both MySQL (skip on sqlite tests).
        $legacyEnrich = $legacyDb !== ''
            && $this->legacyHasTable('rbi_enterprise_details')
            && $this->canCrossJoinLegacyFromDefault();
        if ($legacyEnrich) {
            $legacyIdExpr = 'CAST(JSON_UNQUOTE(JSON_EXTRACT(cs.payload, \'$.legacy_application_id\')) AS UNSIGNED)';
            $query->leftJoin("{$legacyDb}.rbi_applications as leg_a", 'leg_a.id', '=', DB::raw($legacyIdExpr));
            $query->leftJoin(DB::raw("(
                SELECT e1.application_id, e1.turnover_last_year, e1.is_registered
                FROM {$legacyDb}.rbi_enterprise_details e1
                INNER JOIN (
                    SELECT application_id, MAX(id) AS max_id
                    FROM {$legacyDb}.rbi_enterprise_details
                    GROUP BY application_id
                ) t ON t.application_id = e1.application_id AND t.max_id = e1.id
            ) as leg_ed"), 'leg_ed.application_id', '=', 'leg_a.id');
        }

        $select = [
            'cs.id',
            'cs.application_no',
            'cs.applicant_name',
            'cs.phone',
            'cs.source',
            'cs.payload',
            'cs.created_at',
            'd.name as district_name',
            'h.name as hub_name',
            'ob.name as batch_name',
            'ob.locked_at',
            'ob.onboarding_date',
        ];
        if ($legacyEnrich) {
            $select[] = 'leg_ed.turnover_last_year as legacy_turnover';
            $select[] = 'leg_ed.is_registered as legacy_is_registered';
        }

        $rows = $query
            ->orderBy('cs.applicant_name')
            ->get($select);

        $out = [];
        foreach ($rows as $row) {
            $payload = json_decode((string) ($row->payload ?? ''), true);
            if (! is_array($payload)) {
                $payload = [];
            }

            $turnoverRaw = $payload['turnover_last_fy'] ?? null;
            if (($turnoverRaw === null || trim((string) $turnoverRaw) === '') && $legacyEnrich) {
                $turnoverRaw = $row->legacy_turnover ?? null;
            }

            $registered = trim((string) ($payload['is_registered'] ?? ''));
            if ($registered === '' && $legacyEnrich) {
                $registered = trim((string) ($row->legacy_is_registered ?? ''));
            }

            [$turnoverNum, $slab, $display] = $this->resolveTurnover($turnoverRaw);
            $formStage = (string) ($payload['form_stage'] ?? $payload['business_stage'] ?? $payload['rbi_applications']['form_stage'] ?? '');
            $stage = $this->resolveBusinessStage($registered, $turnoverNum, $formStage);

            $source = strtolower(trim((string) ($row->source ?? '')));
            $sourceNote = in_array($source, ['legacy_phase2', 'rbiphase2'], true)
                ? 'MIS locked batch (legacy Phase 2 import)'
                : 'Phase 3 MIS';

            $onboardedAt = $row->locked_at ?: ($row->onboarding_date ?? null);

            $out[] = [
                'turnover_slab' => $slab,
                'turnover' => $turnoverNum,
                'turnover_display' => $display,
                'is_registered' => $registered !== '' ? $this->normalizeYesNo($registered) : '—',
                'business_stage' => $stage,
                'application_no' => $this->cell($row->application_no ?? null),
                'applicant_name' => $this->cell($row->applicant_name ?? ($payload['applicant_name'] ?? null)),
                'guardian_name' => $this->payloadCell($payload, ['guardian_name']),
                'gender' => $this->payloadCell($payload, ['gender']),
                'dob' => $this->payloadCell($payload, ['dob']),
                'phone' => $this->cell($row->phone ?? ($payload['phone'] ?? null)),
                'alt_mobile' => $this->payloadCell($payload, ['alt_mobile']),
                'email' => $this->payloadCell($payload, ['email']),
                'category' => $this->payloadCell($payload, ['category', 'app_category']),
                'caste' => $this->payloadCell($payload, ['caste']),
                'education' => $this->payloadCell($payload, ['education']),
                'district' => $this->cell($row->district_name ?? ($payload['district'] ?? null)),
                'hub' => $this->cell($row->hub_name ?? null),
                'block' => $this->payloadCell($payload, ['block', 'block_name']),
                'village' => $this->payloadCell($payload, ['village']),
                'pincode' => $this->payloadCell($payload, ['pincode']),
                'is_shg_member' => $this->payloadCell($payload, ['is_shg_member', 'is_member']),
                'shg_name' => $this->payloadCell($payload, ['shg_name', 'shg_cbo_name']),
                'lakhpati' => $this->payloadCell($payload, ['lakhpati']),
                'id_proof_type' => $this->payloadCell($payload, ['id_proof_type']),
                'id_proof_number' => $this->payloadCell($payload, ['id_proof_number']),
                'loan_taken' => $this->payloadCell($payload, ['loan_taken']),
                'bank_loan' => $this->payloadCell($payload, ['bank_loan']),
                'current_employment' => $this->payloadCell($payload, ['current_employment']),
                'employed_count' => $this->payloadCell($payload, ['employed_count']),
                'business_category' => $this->payloadCell($payload, ['business_category', 'app_business_category', 'sector']),
                'product' => $this->payloadCell($payload, ['other_product', 'app_other_product', 'product', 'app_product']),
                'form_stage' => $formStage !== '' ? $formStage : '—',
                'techuse' => $this->payloadCell($payload, ['techuse']),
                'empwomen' => $this->payloadCell($payload, ['empwomen']),
                'sustainability' => $this->payloadCell($payload, ['sustainability']),
                'training_mode' => $this->payloadCell($payload, ['training_mode']),
                'training_received' => $this->payloadCell($payload, ['training_received']),
                'challenges' => $this->payloadCell($payload, ['challenges']),
                'expectations' => $this->payloadCell($payload, ['expectations']),
                'expectation_other' => $this->payloadCell($payload, ['expectation_other', 'expectation_other_text']),
                'migrated_for_employment' => $this->payloadCell($payload, ['migrated_for_employment']),
                'submitted_by_name' => $this->payloadCell($payload, ['submitted_by_name', 'referral_staff_name']),
                'submitted_by_mobile' => $this->payloadCell($payload, ['submitted_by_mobile']),
                'department_name' => $this->payloadCell($payload, ['department_name']),
                'info_source' => $this->payloadCell($payload, ['info_source']),
                'resource_name' => $this->payloadCell($payload, ['resource_name']),
                'submission_date' => $this->formatDate($payload['submission_date'] ?? $payload['submitted_at'] ?? $row->created_at ?? null),
                'batch' => $this->cell($row->batch_name ?? null),
                'onboarded_at' => $this->formatDate($onboardedAt),
                'onboarded_status' => 'Onboarded (locked batch)',
                'source_note' => $sourceNote,
            ];
        }

        return $out;
    }

    /**
     * @param  list<int>  $applicationIds
     * @return array<int, array<string, string>>
     */
    private function phase2ServicesByApplicationIds(array $applicationIds): array
    {
        if ($applicationIds === [] || ! $this->legacyHasTable('rbi_services_assigned')) {
            return [];
        }

        $grouped = DB::connection('legacy')
            ->table('rbi_services_assigned')
            ->whereIn('application_id', $applicationIds)
            ->orderBy('service_name')
            ->get(['application_id', 'service_name', 'category', 'service_number'])
            ->groupBy('application_id');

        $out = [];
        foreach ($grouped as $appId => $serviceRows) {
            $out[(int) $appId] = $this->summarizePhase2Services($serviceRows->all());
        }

        return $out;
    }

    /**
     * @param  list<object>  $serviceRows
     * @return array<string, string>
     */
    private function summarizePhase2Services(array $serviceRows): array
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
     * @return array<string, string>
     */
    private function emptyPhase2Services(): array
    {
        return [
            'marketing_service' => 'No',
            'marketing_details' => 'NA',
            'finance_service' => 'No',
            'finance_details' => 'NA',
            'training_service' => 'No',
            'training_details' => 'NA',
            'other_services_details' => 'NA',
            'all_services' => 'NA',
        ];
    }

    /**
     * @return array{0: ?float, 1: string, 2: string}
     */
    private function resolveTurnover(mixed $raw): array
    {
        if ($raw === null) {
            return [null, 'Not specified', '—'];
        }

        $text = trim((string) $raw);
        if ($text === '' || strtolower($text) === 'null' || strtolower($text) === 'na') {
            return [null, 'Not specified', '—'];
        }

        $clean = str_replace(',', '', $text);
        if (! is_numeric($clean)) {
            return [null, 'Not specified', $text];
        }

        $num = (float) $clean;
        $display = '₹'.number_format($num, $num == floor($num) ? 0 : 2, '.', ',');

        if ($num == 0.0) {
            return [0.0, 'Zero income', $display];
        }
        if ($num > 0 && $num < 100_000) {
            return [$num, 'INR > 0 – 1 Lakh', $display];
        }
        if ($num >= 100_000 && $num < 500_000) {
            return [$num, 'INR 1 – 5 Lakh', $display];
        }
        if ($num >= 500_000 && $num < 1_000_000) {
            return [$num, 'INR 5 – 10 Lakh', $display];
        }
        if ($num >= 1_000_000 && $num < 2_500_000) {
            return [$num, 'INR 10 – 25 Lakh', $display];
        }

        return [$num, 'INR 25 Lakh+', $display];
    }

    private function resolveBusinessStage(string $registered, ?float $turnover, string $formStageFallback): string
    {
        $regNorm = $this->normalizeYesNo($registered);
        if (in_array($regNorm, ['Yes', 'No'], true) && $turnover !== null) {
            $info = (new CfaBusinessStageService)->compute($regNorm, $turnover);

            return (string) ($info['stage'] ?? '—');
        }

        $fallback = trim($formStageFallback);

        return $fallback !== '' ? $fallback : '—';
    }

    private function normalizeYesNo(string $raw): string
    {
        $l = strtolower(trim($raw));
        if (in_array($l, ['yes', 'y', '1', 'true'], true)) {
            return 'Yes';
        }
        if (in_array($l, ['no', 'n', '0', 'false'], true)) {
            return 'No';
        }

        return trim($raw) !== '' ? trim($raw) : '—';
    }

    /**
     * @param  list<array<string, mixed>>  $rows
     * @return list<array<string, mixed>>
     */
    private function sortByTurnover(array $rows): array
    {
        $order = array_flip(self::SLAB_ORDER);
        usort($rows, function (array $a, array $b) use ($order): int {
            $sa = $order[(string) ($a['turnover_slab'] ?? 'Not specified')] ?? 99;
            $sb = $order[(string) ($b['turnover_slab'] ?? 'Not specified')] ?? 99;
            if ($sa !== $sb) {
                return $sa <=> $sb;
            }
            $ta = $a['turnover'] ?? null;
            $tb = $b['turnover'] ?? null;
            if ($ta === null && $tb === null) {
                return strcmp((string) ($a['applicant_name'] ?? ''), (string) ($b['applicant_name'] ?? ''));
            }
            if ($ta === null) {
                return 1;
            }
            if ($tb === null) {
                return -1;
            }

            return $tb <=> $ta;
        });

        return $rows;
    }

    /**
     * @param  list<array<string, mixed>>  $rows
     */
    private function numberRows(array &$rows): void
    {
        foreach ($rows as $i => &$row) {
            $row['sn'] = $i + 1;
        }
        unset($row);
    }

    /**
     * @param  list<array<string, mixed>>  $rows
     * @return array<string, int>
     */
    private function slabCounts(array $rows): array
    {
        $counts = array_fill_keys(self::SLAB_ORDER, 0);
        foreach ($rows as $row) {
            $slab = (string) ($row['turnover_slab'] ?? 'Not specified');
            if (! array_key_exists($slab, $counts)) {
                $slab = 'Not specified';
            }
            $counts[$slab]++;
        }

        return $counts;
    }

    private function resolveDistrict(?int $districtId, ?string $districtSlug): ?District
    {
        if ($districtId && $districtId > 0) {
            return District::query()->find($districtId);
        }

        $slug = strtolower(trim((string) $districtSlug));
        if ($slug === '') {
            return null;
        }

        return District::query()
            ->where('slug', $slug)
            ->orWhereRaw('LOWER(name) = ?', [$slug])
            ->first();
    }

    /**
     * @return list<string>
     */
    private function legacyDistrictNames(?District $district): array
    {
        if (! $district) {
            return [];
        }

        $canonical = trim((string) $district->name);
        $names = [$canonical];

        $p2Aliases = (array) config('legacy_phase2.staff_import.district_aliases', []);
        foreach ((array) ($p2Aliases[$canonical] ?? []) as $alias) {
            $alias = trim((string) $alias);
            if ($alias !== '') {
                $names[] = $alias;
            }
        }

        return array_values(array_unique(array_map(
            static fn (string $n): string => strtolower(trim($n)),
            $names
        )));
    }

    /**
     * @param  array<string, mixed>  $payload
     * @param  list<string>  $keys
     */
    private function payloadCell(array $payload, array $keys): string
    {
        foreach ($keys as $key) {
            if (! array_key_exists($key, $payload)) {
                continue;
            }
            $val = $payload[$key];
            if (is_scalar($val) && trim((string) $val) !== '') {
                return trim((string) $val);
            }
        }

        return '—';
    }

    private function cell(mixed $value): string
    {
        if ($value === null) {
            return '—';
        }
        $text = trim((string) $value);

        return $text !== '' ? $text : '—';
    }

    private function formatDate(mixed $value): string
    {
        if ($value === null || $value === '') {
            return '—';
        }

        try {
            return Carbon::parse($value)->format('d M Y');
        } catch (\Throwable) {
            return '—';
        }
    }

    private function legacyPhase2Ready(): bool
    {
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

    private function canCrossJoinLegacyFromDefault(): bool
    {
        $default = (string) config('database.connections.'.config('database.default').'.driver', '');
        $legacy = (string) config('database.connections.legacy.driver', '');

        return in_array($default, ['mysql', 'mariadb'], true)
            && in_array($legacy, ['mysql', 'mariadb'], true);
    }
}
