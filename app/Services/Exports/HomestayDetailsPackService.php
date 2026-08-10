<?php

namespace App\Services\Exports;

use App\Models\District;
use App\Services\CfaBusinessStageService;
use App\Services\LegacyPhase1\LegacyPhase1DistrictResolver;
use App\Support\SimpleXlsxWriter;
use Carbon\Carbon;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * Homestay details Excel: Phase 1 + 2 + 3.
 * Sheets: Summary, Combined, Phase 1, Phase 2, Phase 3.
 * Filters: district + onboard scope (all | onboarded | non_onboarded).
 * Phase 1 match: strict Homestay / Home stay labels on business_desp (option A).
 */
final class HomestayDetailsPackService
{
    private const P2_START = '2025-04-02';

    private const P2_END = '2026-04-01';

    /** @var list<string> */
    private const DETAIL_KEYS = [
        'sn',
        'phase',
        'onboarded_status',
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
        'turnover',
        'turnover_display',
        'is_registered',
        'business_stage',
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
        'source_note',
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
     *     combined: list<array<string, mixed>>,
     *     phase1: list<array<string, mixed>>,
     *     phase2: list<array<string, mixed>>,
     *     phase3: list<array<string, mixed>>
     * }
     */
    public function build(
        ?int $districtId = null,
        ?string $districtSlug = null,
        string $onboardScope = 'all',
    ): array {
        $onboardScope = $this->normalizeScope($onboardScope);
        $district = $this->resolveDistrict($districtId, $districtSlug);
        $districtId = $district ? (int) $district->id : null;
        $districtLabel = $district?->name ?? 'All districts';
        $legacyDistrictNames = $this->legacyDistrictNames($district);

        $phase1 = $this->filterByScope($this->phase1Rows($legacyDistrictNames), $onboardScope);
        $phase2 = $this->filterByScope($this->phase2Rows($legacyDistrictNames), $onboardScope);
        $phase3 = $this->filterByScope($this->phase3Rows($districtId), $onboardScope);

        $this->sortByDistrictName($phase1);
        $this->sortByDistrictName($phase2);
        $this->sortByDistrictName($phase3);

        $combined = array_merge($phase1, $phase2, $phase3);
        $this->sortCombined($combined);

        $this->numberRows($phase1);
        $this->numberRows($phase2);
        $this->numberRows($phase3);
        $this->numberRows($combined);

        return [
            'meta' => [
                'title' => 'Homestay details — Phase 1 + 2 + 3',
                'district' => $districtLabel,
                'district_id' => $districtId,
                'district_slug' => $district?->slug ?? 'all',
                'onboard_scope' => $onboardScope,
                'onboard_scope_label' => $this->scopeLabel($onboardScope),
                'as_of' => now()->timezone(config('app.timezone'))->format('d M Y, g:i A T'),
                'rules' => [
                    'Sector filter: Homestay',
                    'Phase 2/3: business_category = Homestay (exact, case-insensitive)',
                    'Phase 1: business_desp is Homestay / Home stay (strict label only)',
                    'Onboard scope: '.$this->scopeLabel($onboardScope),
                    'Phase 1 onboarded = tblapplication.onboard=yes',
                    'Phase 2 onboarded = rbi_onboarded_applicants with non-empty status (FY 2025–26 submission window)',
                    'Phase 3 onboarded = locked MIS onboarding batch member',
                    'District filter: '.$districtLabel,
                ],
            ],
            'summary' => [
                'phase1_total' => count($phase1),
                'phase2_total' => count($phase2),
                'phase3_total' => count($phase3),
                'combined_total' => count($combined),
                'phase1_onboarded' => $this->countOnboarded($phase1),
                'phase2_onboarded' => $this->countOnboarded($phase2),
                'phase3_onboarded' => $this->countOnboarded($phase3),
            ],
            'combined' => $combined,
            'phase1' => $phase1,
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
            [(string) ($meta['title'] ?? 'Homestay details')],
            [],
            ['District', (string) ($meta['district'] ?? '')],
            ['Onboard filter', (string) ($meta['onboard_scope_label'] ?? '')],
            ['Generated at', (string) ($meta['as_of'] ?? '')],
            [],
            ['Rules'],
        ];
        foreach (($meta['rules'] ?? []) as $rule) {
            $summaryRows[] = ['• '.(string) $rule];
        }
        $summaryRows[] = [];
        $summaryRows[] = ['Phase', 'Total rows', 'Of which onboarded'];
        $summaryRows[] = ['Phase 1 (FY 2024–25)', (int) ($summary['phase1_total'] ?? 0), (int) ($summary['phase1_onboarded'] ?? 0)];
        $summaryRows[] = ['Phase 2 (FY 2025–26)', (int) ($summary['phase2_total'] ?? 0), (int) ($summary['phase2_onboarded'] ?? 0)];
        $summaryRows[] = ['Phase 3 (FY 2026–27)', (int) ($summary['phase3_total'] ?? 0), (int) ($summary['phase3_onboarded'] ?? 0)];
        $summaryRows[] = [
            'Combined',
            (int) ($summary['combined_total'] ?? 0),
            (int) ($summary['phase1_onboarded'] ?? 0) + (int) ($summary['phase2_onboarded'] ?? 0) + (int) ($summary['phase3_onboarded'] ?? 0),
        ];

        $writer->addSheet('Summary', $summaryRows);
        $writer->addSheet('Combined', $this->detailSheetRows($pack['combined'] ?? []));
        $writer->addSheet('Phase 1', $this->detailSheetRows($pack['phase1'] ?? []));
        $writer->addSheet('Phase 2', $this->detailSheetRows($pack['phase2'] ?? []));
        $writer->addSheet('Phase 3', $this->detailSheetRows($pack['phase3'] ?? []));
        $writer->save($absolutePath);
    }

    /**
     * @param  list<array<string, mixed>>  $details
     * @return list<list<string|int|float>>
     */
    private function detailSheetRows(array $details): array
    {
        $headers = [
            'Sr No', 'Phase', 'Onboarded Status',
            'Application No', 'Applicant Name', 'Guardian Name', 'Gender', 'DOB',
            'Phone', 'Alt Mobile', 'Email', 'Category', 'Caste', 'Education',
            'District', 'Hub', 'Block', 'Village', 'Pincode',
            'Is SHG Member', 'SHG Name', 'Lakhpati',
            'ID Proof Type', 'ID Proof Number',
            'Loan Taken', 'Bank Loan', 'Current Employment', 'Employed Count',
            'Business Category', 'Product / Description',
            'Turnover (₹)', 'Turnover (display)', 'Registered', 'Business Stage', 'Form Stage',
            'Tech Use', 'Emp Women', 'Sustainability',
            'Training Mode', 'Training Received', 'Challenges', 'Expectations', 'Expectation Other',
            'Migrated For Employment',
            'Submitted By Name', 'Submitted By Mobile', 'Department Name', 'Info Source', 'Resource Name',
            'Submission Date', 'Batch', 'Onboarded At', 'Source Note',
            'Marketing Service', 'Marketing Details',
            'Access To Finance Service', 'Access To Finance Details',
            'Training Service', 'Training Details',
            'Other Services Details', 'All Services',
        ];

        $out = [$headers];
        foreach ($details as $row) {
            $line = [];
            foreach (self::DETAIL_KEYS as $key) {
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
    private function phase1Rows(array $districtNamesLower): array
    {
        if (! $this->legacyPhase1Ready()) {
            return [];
        }

        $query = DB::connection('legacy_phase1')->table('tblapplication');

        if ($districtNamesLower !== []) {
            // Phase 1 stores district in FatherName
            $canonical = null;
            foreach (LegacyPhase1DistrictResolver::canonicalDistricts() as $name) {
                if (in_array(strtolower($name), $districtNamesLower, true)) {
                    $canonical = $name;
                    break;
                }
            }
            if ($canonical) {
                LegacyPhase1DistrictResolver::applyDistrictFilter($query, $canonical);
            } else {
                $query->where(function ($q) use ($districtNamesLower): void {
                    foreach ($districtNamesLower as $name) {
                        $q->orWhereRaw('LOWER(TRIM(COALESCE(FatherName, \'\'))) = ?', [$name])
                            ->orWhereRaw('LOWER(TRIM(COALESCE(FatherName, \'\'))) LIKE ?', ['%'.$name.'%']);
                    }
                });
            }
        }

        $rows = $query->orderBy('FullName')->get([
            'ID', 'ApplicationNumber', 'FullName', 'gender', 'dob', 'cast', 'Email', 'MobileNumber',
            'education', 'FatherName', 'City', 'Pincode', 'Address', 'hub',
            'business_desp', 'registered', 'avg_turnover', 'pre_turnover', 'current_emp', 'job_count',
            'loan', 'loan_amount', 'training', 'chal', 'tech', 'migr', 'migr2',
            'sub_by', 'sub_mob', 'sub_des', 'ApplicationDate', 'onboard', 'onboarding_date', 'onboard_date',
            'enterprise_name', 'Occupationtype', 'idea',
        ]);

        $out = [];
        foreach ($rows as $row) {
            $desp = trim((string) ($row->business_desp ?? ''));
            if (! $this->isPhase1HomestayLabel($desp)) {
                continue;
            }

            $onboardRaw = strtolower(trim((string) ($row->onboard ?? '')));
            $isOnboarded = $onboardRaw === 'yes';
            $districtLegacy = trim((string) ($row->FatherName ?? ''));
            $districtCanon = LegacyPhase1DistrictResolver::canonicalNameForLegacyFatherName($districtLegacy) ?? $districtLegacy;
            $turnoverRaw = $row->avg_turnover ?: ($row->pre_turnover ?? null);
            [$turnoverNum, $turnoverDisplay] = $this->parseTurnoverDisplay($turnoverRaw);
            $registered = $this->normalizeYesNo((string) ($row->registered ?? ''));
            $stage = $this->resolveBusinessStage($registered, $turnoverNum, '');
            $onboardedAt = $row->onboarding_date ?: ($row->onboard_date ?? null);

            $out[] = array_merge($this->emptyServiceFields(), [
                'phase' => 'Phase 1',
                'is_onboarded' => $isOnboarded,
                'onboarded_status' => $isOnboarded ? 'Onboarded (onboard=yes)' : 'Non-onboarded',
                'application_no' => $this->cell($row->ApplicationNumber ?? null),
                'applicant_name' => $this->cell($row->FullName ?? null),
                'guardian_name' => '—',
                'gender' => $this->cell($row->gender ?? null),
                'dob' => $this->cell($row->dob ?? null),
                'phone' => $this->cell($row->MobileNumber ?? null),
                'alt_mobile' => '—',
                'email' => $this->cell($row->Email ?? null),
                'category' => '—',
                'caste' => $this->cell($row->cast ?? null),
                'education' => $this->cell($row->education ?? null),
                'district' => $this->cell($districtCanon),
                'hub' => $this->cell($row->hub ?? null),
                'block' => $this->cell($row->City ?? null),
                'village' => $this->cell($row->Address ?? null),
                'pincode' => $this->cell($row->Pincode ?? null),
                'is_shg_member' => '—',
                'shg_name' => '—',
                'lakhpati' => '—',
                'id_proof_type' => '—',
                'id_proof_number' => '—',
                'loan_taken' => $this->cell($row->loan ?? null),
                'bank_loan' => $this->cell($row->loan_amount ?? null),
                'current_employment' => $this->cell($row->current_emp ?? null),
                'employed_count' => $this->cell($row->job_count ?? null),
                'business_category' => 'Homestay',
                'product' => $desp !== '' ? $desp : 'Homestay',
                'turnover' => $turnoverNum,
                'turnover_display' => $turnoverDisplay,
                'is_registered' => $registered !== '—' ? $registered : '—',
                'business_stage' => $stage,
                'form_stage' => '—',
                'techuse' => $this->cell($row->tech ?? null),
                'empwomen' => '—',
                'sustainability' => '—',
                'training_mode' => $this->cell($row->training ?? null),
                'training_received' => '—',
                'challenges' => $this->cell($row->chal ?? null),
                'expectations' => '—',
                'expectation_other' => '—',
                'migrated_for_employment' => $this->cell($row->migr ?? ($row->migr2 ?? null)),
                'submitted_by_name' => $this->cell($row->sub_by ?? null),
                'submitted_by_mobile' => $this->cell($row->sub_mob ?? null),
                'department_name' => $this->cell($row->sub_des ?? null),
                'info_source' => '—',
                'resource_name' => '—',
                'submission_date' => $this->formatDate($row->ApplicationDate ?? null),
                'batch' => '—',
                'onboarded_at' => $this->formatDate($onboardedAt),
                'source_note' => 'Legacy Phase 1 (ukrbiin_rbi.tblapplication)',
            ]);
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

        $hasOnboard = $this->legacyHasTable('rbi_onboarded_applicants');
        $query = DB::connection('legacy')
            ->table('rbi_applications as a')
            ->leftJoin('rbi_applicant_details as d', 'd.application_id', '=', 'a.id')
            ->whereNotNull('a.submission_date')
            ->whereRaw('DATE(a.submission_date) BETWEEN ? AND ?', [self::P2_START, self::P2_END])
            ->whereRaw("LOWER(TRIM(COALESCE(a.business_category, ''))) = 'homestay'");

        if ($hasOnboard) {
            $query->leftJoin('rbi_onboarded_applicants as oa', 'oa.application_id', '=', 'a.id')
                ->leftJoin('rbi_onboarding_batches as lob', 'lob.id', '=', 'oa.onboarding_batch_id');
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
        ];

        if ($hasOnboard) {
            $select[] = 'oa.status as onboard_status';
            $select[] = 'oa.onboarded_at';
            $select[] = 'lob.batch_name';
            $select[] = 'lob.onboarding_date';
        }
        if ($this->legacyHasTable('rbi_enterprise_details')) {
            $select[] = 'ed.turnover_last_year';
            $select[] = 'ed.is_registered';
        }

        $rows = $query->orderBy('d.applicant_name')->get($select);

        $appIds = $rows->pluck('application_id')->map(fn ($id) => (int) $id)->filter(fn (int $id) => $id > 0)->values()->all();
        $servicesByApp = $this->phase2ServicesByApplicationIds($appIds);

        $out = [];
        foreach ($rows as $row) {
            $appId = (int) ($row->application_id ?? 0);
            $statusRaw = $hasOnboard ? trim((string) ($row->onboard_status ?? '')) : '';
            $isOnboarded = $statusRaw !== '';
            $turnoverRaw = $this->legacyHasTable('rbi_enterprise_details') ? ($row->turnover_last_year ?? null) : null;
            $registered = $this->legacyHasTable('rbi_enterprise_details') ? trim((string) ($row->is_registered ?? '')) : '';
            [$turnoverNum, $turnoverDisplay] = $this->parseTurnoverDisplay($turnoverRaw);
            $stage = $this->resolveBusinessStage($registered, $turnoverNum, (string) ($row->form_stage ?? ''));
            $services = $servicesByApp[$appId] ?? $this->emptyServiceFields();
            $onboardedAt = $hasOnboard ? ($row->onboarded_at ?: ($row->onboarding_date ?? null)) : null;

            $out[] = array_merge($services, [
                'phase' => 'Phase 2',
                'is_onboarded' => $isOnboarded,
                'onboarded_status' => $isOnboarded
                    ? 'Onboarded'.($statusRaw !== '' ? ' ('.$statusRaw.')' : '')
                    : 'Non-onboarded',
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
                'business_category' => 'Homestay',
                'product' => $this->cell($row->product ?? null),
                'turnover' => $turnoverNum,
                'turnover_display' => $turnoverDisplay,
                'is_registered' => $registered !== '' ? $this->normalizeYesNo($registered) : '—',
                'business_stage' => $stage,
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
                'batch' => $hasOnboard ? $this->cell($row->batch_name ?? null) : '—',
                'onboarded_at' => $this->formatDate($onboardedAt),
                'source_note' => 'Legacy Phase 2 (rbiphase2)',
            ]);
        }

        return $out;
    }

    /**
     * @return list<array<string, mixed>>
     */
    private function phase3Rows(?int $districtId): array
    {
        if (! Schema::hasTable('cfa_submissions')) {
            return [];
        }

        $onboardedIds = [];
        if (Schema::hasTable('onboarding_batch_cfa') && Schema::hasTable('onboarding_batches')) {
            $onboardedIds = DB::table('onboarding_batch_cfa as obc')
                ->join('onboarding_batches as ob', 'ob.id', '=', 'obc.onboarding_batch_id')
                ->where('ob.status', 'locked')
                ->whereNotNull('ob.locked_at')
                ->whereNotNull('obc.cfa_submission_id')
                ->pluck('obc.cfa_submission_id')
                ->map(fn ($id) => (int) $id)
                ->unique()
                ->flip()
                ->all();
        }

        $query = DB::table('cfa_submissions as cs')
            ->leftJoin('districts as d', 'd.id', '=', 'cs.district_id')
            ->leftJoin('hubs as h', 'h.id', '=', 'd.hub_id');

        $defaultDriver = (string) config('database.connections.'.config('database.default').'.driver', '');
        if (in_array($defaultDriver, ['mysql', 'mariadb'], true)) {
            $query->where(function ($q): void {
                $q->whereRaw("LOWER(TRIM(COALESCE(JSON_UNQUOTE(JSON_EXTRACT(cs.payload, '$.business_category')), ''))) = 'homestay'")
                    ->orWhereRaw("LOWER(TRIM(COALESCE(JSON_UNQUOTE(JSON_EXTRACT(cs.payload, '$.app_business_category')), ''))) = 'homestay'");
            });
        }

        if ($districtId) {
            $query->where('cs.district_id', $districtId);
        }

        $legacyDb = trim((string) config('database.connections.legacy.database', ''));
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

        // Batch info for onboarded
        $hasBatch = Schema::hasTable('onboarding_batch_cfa') && Schema::hasTable('onboarding_batches');
        if ($hasBatch) {
            $query->leftJoin('onboarding_batch_cfa as obc', 'obc.cfa_submission_id', '=', 'cs.id')
                ->leftJoin('onboarding_batches as ob', function ($join): void {
                    $join->on('ob.id', '=', 'obc.onboarding_batch_id')
                        ->where('ob.status', '=', 'locked')
                        ->whereNotNull('ob.locked_at');
                })
                ->leftJoin('hubs as bh', 'bh.id', '=', 'ob.hub_id');
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
        ];
        if ($hasBatch) {
            $select[] = 'ob.name as batch_name';
            $select[] = 'ob.locked_at';
            $select[] = 'ob.onboarding_date';
            $select[] = 'bh.name as batch_hub_name';
        }
        if ($legacyEnrich) {
            $select[] = 'leg_ed.turnover_last_year as legacy_turnover';
            $select[] = 'leg_ed.is_registered as legacy_is_registered';
        }

        $rows = $query->orderBy('cs.applicant_name')->get($select);

        // Deduplicate if multiple batch joins
        $seen = [];
        $out = [];
        foreach ($rows as $row) {
            $cfaId = (int) ($row->id ?? 0);
            if ($cfaId > 0 && isset($seen[$cfaId])) {
                continue;
            }
            $seen[$cfaId] = true;

            $payload = json_decode((string) ($row->payload ?? ''), true);
            if (! is_array($payload)) {
                $payload = [];
            }

            $bc = trim((string) ($payload['business_category'] ?? ''));
            $abc = trim((string) ($payload['app_business_category'] ?? ''));
            $chosen = $bc !== '' ? $bc : $abc;
            if (mb_strtolower($chosen) !== 'homestay') {
                continue;
            }

            $isOnboarded = isset($onboardedIds[$cfaId]);
            $turnoverRaw = $payload['turnover_last_fy'] ?? null;
            if (($turnoverRaw === null || trim((string) $turnoverRaw) === '') && $legacyEnrich) {
                $turnoverRaw = $row->legacy_turnover ?? null;
            }
            $registered = trim((string) ($payload['is_registered'] ?? ''));
            if ($registered === '' && $legacyEnrich) {
                $registered = trim((string) ($row->legacy_is_registered ?? ''));
            }
            [$turnoverNum, $turnoverDisplay] = $this->parseTurnoverDisplay($turnoverRaw);
            $formStage = (string) ($payload['form_stage'] ?? $payload['business_stage'] ?? '');
            $stage = $this->resolveBusinessStage($registered, $turnoverNum, $formStage);
            $source = strtolower(trim((string) ($row->source ?? '')));
            $sourceNote = in_array($source, ['legacy_phase2', 'rbiphase2'], true)
                ? 'MIS CFA (legacy Phase 2 import)'
                : 'Phase 3 MIS CFA';
            $onboardedAt = $hasBatch ? ($row->locked_at ?: ($row->onboarding_date ?? null)) : null;
            $hub = $hasBatch && ! empty($row->batch_hub_name)
                ? (string) $row->batch_hub_name
                : (string) ($row->hub_name ?? '');

            $out[] = array_merge($this->emptyServiceFields(), [
                'phase' => 'Phase 3',
                'is_onboarded' => $isOnboarded,
                'onboarded_status' => $isOnboarded ? 'Onboarded (locked batch)' : 'Non-onboarded',
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
                'hub' => $this->cell($hub),
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
                'business_category' => 'Homestay',
                'product' => $this->payloadCell($payload, ['other_product', 'product', 'app_product']),
                'turnover' => $turnoverNum,
                'turnover_display' => $turnoverDisplay,
                'is_registered' => $registered !== '' ? $this->normalizeYesNo($registered) : '—',
                'business_stage' => $stage,
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
                'batch' => $hasBatch ? $this->cell($row->batch_name ?? null) : '—',
                'onboarded_at' => $isOnboarded ? $this->formatDate($onboardedAt) : '—',
                'source_note' => $sourceNote,
            ]);
        }

        return $out;
    }

    /**
     * Strict Phase 1 Homestay labels only (option A).
     */
    private function isPhase1HomestayLabel(string $raw): bool
    {
        $n = mb_strtolower(trim($raw));
        if ($n === '') {
            return false;
        }
        $n = str_replace(['-', '_'], ' ', $n);
        $n = preg_replace('/\s+/', ' ', $n) ?? $n;
        $n = rtrim($n, " \t\n\r\0\x0B.");

        return in_array($n, ['homestay', 'home stay'], true);
    }

    /**
     * @param  list<array<string, mixed>>  $rows
     * @return list<array<string, mixed>>
     */
    private function filterByScope(array $rows, string $scope): array
    {
        if ($scope === 'all') {
            return $rows;
        }

        return array_values(array_filter($rows, function (array $row) use ($scope): bool {
            $on = (bool) ($row['is_onboarded'] ?? false);

            return $scope === 'onboarded' ? $on : ! $on;
        }));
    }

    private function normalizeScope(string $scope): string
    {
        $scope = strtolower(trim($scope));

        return match ($scope) {
            'onboarded', 'onboard' => 'onboarded',
            'non_onboarded', 'non-onboarded', 'nononboarded', 'not_onboarded' => 'non_onboarded',
            default => 'all',
        };
    }

    private function scopeLabel(string $scope): string
    {
        return match ($scope) {
            'onboarded' => 'Onboarded only',
            'non_onboarded' => 'Non-onboarded only',
            default => 'All (onboarded + non-onboarded)',
        };
    }

    /**
     * @param  list<array<string, mixed>>  $rows
     */
    private function countOnboarded(array $rows): int
    {
        $n = 0;
        foreach ($rows as $row) {
            if (! empty($row['is_onboarded'])) {
                $n++;
            }
        }

        return $n;
    }

    /**
     * @param  list<array<string, mixed>>  $rows
     */
    private function sortByDistrictName(array &$rows): void
    {
        usort($rows, function (array $a, array $b): int {
            $d = strcmp((string) ($a['district'] ?? ''), (string) ($b['district'] ?? ''));
            if ($d !== 0) {
                return $d;
            }

            return strcmp((string) ($a['applicant_name'] ?? ''), (string) ($b['applicant_name'] ?? ''));
        });
    }

    /**
     * @param  list<array<string, mixed>>  $rows
     */
    private function sortCombined(array &$rows): void
    {
        $phaseOrder = ['Phase 1' => 1, 'Phase 2' => 2, 'Phase 3' => 3];
        usort($rows, function (array $a, array $b) use ($phaseOrder): int {
            $pa = $phaseOrder[(string) ($a['phase'] ?? '')] ?? 9;
            $pb = $phaseOrder[(string) ($b['phase'] ?? '')] ?? 9;
            if ($pa !== $pb) {
                return $pa <=> $pb;
            }
            $d = strcmp((string) ($a['district'] ?? ''), (string) ($b['district'] ?? ''));
            if ($d !== 0) {
                return $d;
            }

            return strcmp((string) ($a['applicant_name'] ?? ''), (string) ($b['applicant_name'] ?? ''));
        });
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
    private function emptyServiceFields(): array
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
     * @return array{0: ?float, 1: string}
     */
    private function parseTurnoverDisplay(mixed $raw): array
    {
        if ($raw === null) {
            return [null, '—'];
        }
        $text = trim((string) $raw);
        if ($text === '' || strtolower($text) === 'null' || strtolower($text) === 'na') {
            return [null, '—'];
        }
        $clean = str_replace(',', '', $text);
        if (! is_numeric($clean)) {
            return [null, $text];
        }
        $num = (float) $clean;
        $display = '₹'.number_format($num, $num == floor($num) ? 0 : 2, '.', ',');

        return [$num, $display];
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

        $p1Aliases = (array) config('legacy_phase1.district_aliases', []);
        foreach ((array) ($p1Aliases[$canonical] ?? []) as $alias) {
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

    private function legacyPhase1Ready(): bool
    {
        try {
            return Schema::connection('legacy_phase1')->hasTable('tblapplication');
        } catch (\Throwable) {
            return false;
        }
    }

    private function legacyPhase2Ready(): bool
    {
        try {
            return Schema::connection('legacy')->hasTable('rbi_applications')
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
