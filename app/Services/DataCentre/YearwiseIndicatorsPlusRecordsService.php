<?php

namespace App\Services\DataCentre;

use App\Models\CfaSubmission;
use App\Models\MarketLinkagePartner;
use App\Models\MarketLinkageSubmission;
use App\Models\ServiceCase;
use App\Models\ServiceCaseAttachment;
use App\Services\CfaBusinessStageService;
use App\Services\Exports\YearwiseIndicatorWorkbookService;
use App\Support\ServiceRegistrationNumberExtractor;
use App\Services\LegacyApplicationServiceCaseSupport;
use Illuminate\Pagination\LengthAwarePaginator;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\URL;

/**
 * Drill-down record lists for Year-wise indicators (+ JIT / Lakhpati) page.
 * Merges workbook verified rows with Plus-only justintime / lakhpati_didi extras
 * so list totals match the Plus matrix counts.
 */
final class YearwiseIndicatorsPlusRecordsService
{
    public const CACHE_KEY = 'yearwise_indicators_plus_records_v8';

    public const MISSING_LABEL = "don't have";

    /** @var list<string> */
    private const ONBOARDING_CFA_KEYS = [
        'cfa_category', 'guardian_name', 'shg_cbo_name', 'gender', 'dob', 'caste', 'education',
        'email', 'alt_mobile', 'village', 'pincode', 'is_member', 'shg_name', 'lakhpati',
        'id_proof_type', 'id_proof_number', 'is_registered', 'registration_type',
        'cfa_registration_number', 'business_age', 'turnover_last_fy', 'current_employment',
        'employed_count', 'loan_taken', 'bank_loan', 'location_type', 'enterprise_name',
        'training_received', 'training_mode', 'info_source', 'techuse', 'sustainability',
        'empwomen', 'challenges', 'expectations', 'form_stage',
    ];

    public const PER_PAGE = 50;

    public const METRICS = [
        'cfa' => 'CFA',
        'onboarding' => 'Onboarding',
        'udyam' => 'Udyam registration',
        'artisan_card' => 'Artisan card',
        'fssai' => 'FSSAI',
        'gst' => 'GST',
        'market_linkage' => 'Market linkage',
        'convergence' => 'Convergence',
    ];

    public const SOURCES = [
        'all' => 'All sources',
        'verified' => 'Verified',
        'jit' => 'JIT (justintime)',
        'lakhpati_didi' => 'Lakhpati Didi',
    ];

    public const ONBOARD_FILTERS = [
        'all' => 'All applicants',
        'onboarded' => 'Onboarded only',
        'not_onboarded' => 'Not onboarded',
    ];

    public const SCOPES = [
        'year' => 'Financial year',
        'phase' => 'Phase total',
        'till' => 'Till Phase Total (Pilot + Phase 1)',
        'grand' => 'Grand Total',
    ];

    public function __construct(
        private readonly YearwiseIndicatorWorkbookService $workbook,
        private readonly YearwiseIndicatorsWithJitLakhpatiService $plus,
    ) {}

    /**
     * @param  array{
     *     metric?: string,
     *     scope?: string,
     *     year?: string|null,
     *     phase?: string|null,
     *     district?: string|null,
     *     source?: string|null,
     *     q?: string|null,
     *     page?: int,
     *     per_page?: int,
     *     attach_docs?: bool
     * }  $filters
     * @return array{
     *     metric: string,
     *     metric_label: string,
     *     scope: string,
     *     scope_label: string,
     *     year: ?string,
     *     phase: ?string,
     *     phase_label: ?string,
     *     years: list<string>,
     *     district: ?string,
     *     source: string,
     *     q: string,
     *     total: int,
     *     records: LengthAwarePaginator,
     *     filter_options: array<string, mixed>
     * }
     */
    public function paginate(array $filters): array
    {
        $this->workbook->raiseMemoryLimitPublic('1024M');
        $normalized = $this->normalizeFilters($filters);
        $filtered = $this->filteredRows($normalized);
        $page = max(1, (int) ($filters['page'] ?? 1));
        $perPage = max(1, min(100, (int) ($filters['per_page'] ?? self::PER_PAGE)));
        $total = count($filtered);
        $slice = array_slice($filtered, ($page - 1) * $perPage, $perPage);
        unset($filtered);
        $slice = $this->hydratePageRows(
            $slice,
            $normalized['metric'],
            ($filters['attach_docs'] ?? true) === true,
        );

        $paginator = new LengthAwarePaginator(
            $slice,
            $total,
            $perPage,
            $page,
            [
                'path' => URL::current(),
                'query' => request()->query(),
            ],
        );

        return [
            'metric' => $normalized['metric'],
            'metric_label' => self::METRICS[$normalized['metric']],
            'registration_label' => $this->registrationLabel($normalized['metric']),
            'scope' => $normalized['scope'],
            'scope_label' => self::SCOPES[$normalized['scope']],
            'year' => $normalized['year'],
            'phase' => $normalized['phase'],
            'phase_label' => $this->phaseLabel($normalized['phase']),
            'years' => $normalized['years'],
            'district' => $normalized['district'],
            'source' => $normalized['source'],
            'onboard' => $normalized['onboard'],
            'q' => $normalized['q'],
            'total' => $total,
            'records' => $paginator,
            'filter_options' => $this->filterOptions(),
        ];
    }

    /**
     * Full filtered list (for CSV/Excel export). Docs omitted for speed.
     *
     * @param  array<string, mixed>  $filters
     * @return list<array<string, mixed>>
     */
    public function exportRows(array $filters): array
    {
        $this->workbook->raiseMemoryLimitPublic('2048M');
        $normalized = $this->normalizeFilters($filters);
        $rows = $this->hydratePageRows($this->filteredRows($normalized), $normalized['metric'], false);
        if (in_array($normalized['metric'], ['onboarding', 'market_linkage'], true)) {
            foreach ($rows as $i => $row) {
                $rows[$i] = $this->slimOnboardingExportRow($row);
            }
            unset($row);
            gc_collect_cycles();
        }

        return $rows;
    }

    public function bustCache(): void
    {
        foreach (array_keys(self::METRICS) as $metric) {
            Cache::store('file')->forget($this->metricCacheKey($metric));
        }
        // Legacy mega-cache keys (pre per-metric split).
        Cache::store('file')->forget('yearwise_indicators_plus_records_v1');
        Cache::store('file')->forget('yearwise_indicators_plus_records_v7');
        Cache::store('file')->forget(self::CACHE_KEY);
    }

    /**
     * @return array<string, mixed>
     */
    public function filterOptions(): array
    {
        return [
            'metrics' => self::METRICS,
            'scopes' => self::SCOPES,
            'sources' => self::SOURCES,
            'onboard' => self::ONBOARD_FILTERS,
            'years' => YearwiseIndicatorsWithJitLakhpatiService::DISPLAY_YEARS,
            'phases' => collect($this->plus->phaseGroups())
                ->mapWithKeys(fn (array $g) => [$g['key'] => $g['label']])
                ->all(),
            'districts' => array_keys($this->plus->districtOrder()),
        ];
    }

    /**
     * @param  array<string, mixed>  $filters
     * @return array{
     *     metric: string,
     *     scope: string,
     *     year: ?string,
     *     phase: ?string,
     *     years: list<string>,
     *     district: ?string,
     *     source: string,
     *     q: string
     * }
     */
    public function registrationLabel(string $metric): string
    {
        return match ($metric) {
            'udyam' => 'Udyam No.',
            'artisan_card' => 'Artisan Card No.',
            'fssai' => 'FSSAI Licence No.',
            'gst' => 'GSTIN',
            'market_linkage' => 'Partner / Ref. No.',
            'convergence' => 'Reg. / Scheme ID',
            'cfa' => 'Registration No.',
            'onboarding' => 'Reg. / Service No.',
            default => 'Service / Reg. No.',
        };
    }

    /**
     * @return list<string>
     */
    public function yearsForFilters(array $filters): array
    {
        return $this->normalizeFilters($filters)['years'];
    }

    /**
     * Combined / year-sheet columns for the onboarded-applicant Excel/CSV.
     *
     * @return list<string>
     */
    public function onboardingExportHeaders(): array
    {
        return [
            'FY', 'Source', 'Application No', 'Applicant Name', 'Guardian Name', 'SHG / CBO Name',
            'Category', 'Gender', 'DOB', 'Caste', 'Education',
            'Phone', 'Alt Mobile', 'Email',
            'District', 'Block', 'Village', 'Pincode', 'Hub',
            'SHG Member', 'SHG Name', 'Lakhpati',
            'ID Proof Type', 'ID Proof Number',
            'Sector', 'Product', 'Enterprise Name', 'Business Age', 'Turnover last FY',
            'CFA Registered', 'CFA Registration Type', 'CFA Registration No.',
            'Current Employment', 'Employed Count', 'Loan Taken', 'Bank Loan', 'Location Type',
            'Training Received', 'Training Mode', 'Info Source', 'Tech Use', 'Employs Women', 'Sustainability',
            'Challenges', 'Expectations',
            'GSTIN', 'FSSAI Licence No.',
            'Market linkage partners', 'Market linkage links',
            'Stage', 'Onboard Status',
            'Onboard Date', 'Batch / Detail', 'Status',
            'Source DB', 'Source Table', 'Record ID',
        ];
    }

    /**
     * Combined / year-sheet columns for market-linkage incubatee Excel/CSV.
     *
     * @return list<string>
     */
    public function marketLinkageExportHeaders(): array
    {
        return [
            'FY', 'Source', 'Application No', 'Applicant Name', 'Guardian Name', 'SHG / CBO Name',
            'Category', 'Gender', 'DOB', 'Caste', 'Education',
            'Phone', 'Alt Mobile', 'Email',
            'District', 'Block', 'Village', 'Pincode', 'Hub',
            'SHG Member', 'SHG Name', 'Lakhpati',
            'Sector', 'Product', 'Enterprise Name', 'Business Age', 'Turnover last FY',
            'Stage', 'Onboard Status',
            'CFA Registered', 'CFA Registration Type', 'CFA Registration No.',
            'Current Employment', 'Employed Count', 'Loan Taken', 'Bank Loan', 'Location Type',
            'Partner / Ref. No.', 'Market linkage partners', 'Market linkage links',
            'Service date', 'Service', 'Detail', 'Status',
            'Source DB', 'Source Table', 'Record ID',
        ];
    }

    /**
     * @param  array<string, mixed>  $row
     * @return list<string>
     */
    public function onboardingExportRow(array $row): array
    {
        $missing = self::MISSING_LABEL;
        $sector = trim((string) ($row['sector'] ?? ''));
        $product = trim((string) ($row['product'] ?? ''));
        $gst = trim((string) ($row['gst_number'] ?? ''));
        $fssai = trim((string) ($row['fssai_number'] ?? ''));
        $partners = trim((string) ($row['market_partners'] ?? ''));
        $links = trim((string) ($row['market_link_urls'] ?? ''));
        if ($partners === '' || $links === '') {
            $display = $this->marketDisplayFromLinks(is_array($row['market_links'] ?? null) ? $row['market_links'] : []);
            if ($partners === '') {
                $partners = $display['partners'];
            }
            if ($links === '') {
                $links = $display['links'];
            }
        }

        return [
            (string) ($row['year'] ?? ''),
            (string) ($row['source_label'] ?? ''),
            (string) ($row['application_no'] ?? ''),
            (string) ($row['applicant_name'] ?? ''),
            (string) ($row['guardian_name'] ?? ''),
            (string) ($row['shg_cbo_name'] ?? ''),
            (string) ($row['cfa_category'] ?? ''),
            (string) ($row['gender'] ?? ''),
            (string) ($row['dob'] ?? ''),
            (string) ($row['caste'] ?? ''),
            (string) ($row['education'] ?? ''),
            (string) ($row['phone'] ?? ''),
            (string) ($row['alt_mobile'] ?? ''),
            (string) ($row['email'] ?? ''),
            (string) ($row['district'] ?? ''),
            (string) ($row['block'] ?? ''),
            (string) ($row['village'] ?? ''),
            (string) ($row['pincode'] ?? ''),
            (string) ($row['hub'] ?? ''),
            (string) ($row['is_member'] ?? ''),
            (string) ($row['shg_name'] ?? ''),
            (string) ($row['lakhpati'] ?? ''),
            (string) ($row['id_proof_type'] ?? ''),
            (string) ($row['id_proof_number'] ?? ''),
            $sector !== '' ? $sector : $missing,
            $product !== '' ? $product : $missing,
            (string) ($row['enterprise_name'] ?? ''),
            (string) ($row['business_age'] ?? ''),
            (string) ($row['turnover_last_fy'] ?? ''),
            (string) ($row['is_registered'] ?? ''),
            (string) ($row['registration_type'] ?? ''),
            (string) ($row['cfa_registration_number'] ?? ''),
            (string) ($row['current_employment'] ?? ''),
            (string) ($row['employed_count'] ?? ''),
            (string) ($row['loan_taken'] ?? ''),
            (string) ($row['bank_loan'] ?? ''),
            (string) ($row['location_type'] ?? ''),
            (string) ($row['training_received'] ?? ''),
            (string) ($row['training_mode'] ?? ''),
            (string) ($row['info_source'] ?? ''),
            (string) ($row['techuse'] ?? ''),
            (string) ($row['empwomen'] ?? ''),
            (string) ($row['sustainability'] ?? ''),
            (string) ($row['challenges'] ?? ''),
            (string) ($row['expectations'] ?? ''),
            $gst !== '' ? $gst : $missing,
            $fssai !== '' ? $fssai : $missing,
            $partners !== '' ? $partners : $missing,
            $links !== '' ? $links : $missing,
            (string) ($row['form_stage'] ?? ''),
            (string) ($row['onboard_status'] ?? ''),
            (string) ($row['date_used'] ?? ''),
            (string) ($row['detail'] ?? ''),
            (string) ($row['status'] ?? ''),
            (string) ($row['source_db'] ?? ''),
            (string) ($row['source_table'] ?? ''),
            (string) ($row['record_id'] ?? ''),
        ];
    }

    /**
     * @param  array<string, mixed>  $row
     * @return list<string>
     */
    public function marketLinkageExportRow(array $row): array
    {
        $missing = self::MISSING_LABEL;
        $partners = trim((string) ($row['market_partners'] ?? ''));
        $links = trim((string) ($row['market_link_urls'] ?? ''));
        if ($partners === '' || $links === '') {
            $display = $this->marketDisplayFromLinks(is_array($row['market_links'] ?? null) ? $row['market_links'] : []);
            if ($partners === '') {
                $partners = $display['partners'];
            }
            if ($links === '') {
                $links = $display['links'];
            }
        }

        return [
            (string) ($row['year'] ?? ''),
            (string) ($row['source_label'] ?? ''),
            (string) ($row['application_no'] ?? ''),
            (string) ($row['applicant_name'] ?? ''),
            (string) ($row['guardian_name'] ?? ''),
            (string) ($row['shg_cbo_name'] ?? ''),
            (string) ($row['cfa_category'] ?? ''),
            (string) ($row['gender'] ?? ''),
            (string) ($row['dob'] ?? ''),
            (string) ($row['caste'] ?? ''),
            (string) ($row['education'] ?? ''),
            (string) ($row['phone'] ?? ''),
            (string) ($row['alt_mobile'] ?? ''),
            (string) ($row['email'] ?? ''),
            (string) ($row['district'] ?? ''),
            (string) ($row['block'] ?? ''),
            (string) ($row['village'] ?? ''),
            (string) ($row['pincode'] ?? ''),
            (string) ($row['hub'] ?? ''),
            (string) ($row['is_member'] ?? ''),
            (string) ($row['shg_name'] ?? ''),
            (string) ($row['lakhpati'] ?? ''),
            (string) ($row['sector'] ?? ''),
            (string) ($row['product'] ?? ''),
            (string) ($row['enterprise_name'] ?? ''),
            (string) ($row['business_age'] ?? ''),
            (string) ($row['turnover_last_fy'] ?? ''),
            (string) ($row['form_stage'] ?? ''),
            (string) ($row['onboard_status'] ?? ''),
            (string) ($row['is_registered'] ?? ''),
            (string) ($row['registration_type'] ?? ''),
            (string) ($row['cfa_registration_number'] ?? ''),
            (string) ($row['current_employment'] ?? ''),
            (string) ($row['employed_count'] ?? ''),
            (string) ($row['loan_taken'] ?? ''),
            (string) ($row['bank_loan'] ?? ''),
            (string) ($row['location_type'] ?? ''),
            (string) ($row['service_number'] ?? ''),
            $partners !== '' ? $partners : $missing,
            $links !== '' ? $links : $missing,
            (string) ($row['date_used'] ?? ''),
            (string) ($row['service_label'] ?? ''),
            (string) ($row['detail'] ?? ''),
            (string) ($row['status'] ?? ''),
            (string) ($row['source_db'] ?? ''),
            (string) ($row['source_table'] ?? ''),
            (string) ($row['record_id'] ?? ''),
        ];
    }

    /**
     * Drop bulky nested arrays after export hydration so Excel/CSV can stream.
     *
     * @param  array<string, mixed>  $row
     * @return array<string, mixed>
     */
    private function slimOnboardingExportRow(array $row): array
    {
        $display = $this->marketDisplayFromLinks(is_array($row['market_links'] ?? null) ? $row['market_links'] : []);
        $keep = [
            'year', 'source_label', 'application_no', 'applicant_name', 'guardian_name', 'shg_cbo_name',
            'cfa_category', 'gender', 'dob', 'caste', 'education', 'phone', 'alt_mobile', 'email',
            'district', 'block', 'village', 'pincode', 'hub', 'is_member', 'shg_name', 'lakhpati',
            'id_proof_type', 'id_proof_number', 'sector', 'product', 'enterprise_name', 'business_age',
            'turnover_last_fy', 'is_registered', 'registration_type', 'cfa_registration_number',
            'current_employment', 'employed_count', 'loan_taken', 'bank_loan', 'location_type',
            'training_received', 'training_mode', 'info_source', 'techuse', 'empwomen', 'sustainability',
            'challenges', 'expectations', 'gst_number', 'fssai_number', 'market_partners', 'market_link_urls',
            'form_stage', 'onboard_status', 'date_used', 'detail', 'status', 'service_label', 'service_number',
            'source_db', 'source_table', 'record_id',
        ];
        $out = [];
        foreach ($keep as $key) {
            $out[$key] = $row[$key] ?? '';
        }
        if (trim((string) $out['market_partners']) === '') {
            $out['market_partners'] = $display['partners'];
        }
        if (trim((string) $out['market_link_urls']) === '') {
            $out['market_link_urls'] = $display['links'];
        }

        return $out;
    }

    private function normalizeFilters(array $filters): array
    {
        $metric = (string) ($filters['metric'] ?? 'onboarding');
        if (! isset(self::METRICS[$metric])) {
            $metric = 'onboarding';
        }

        $scope = (string) ($filters['scope'] ?? 'grand');
        if (! isset(self::SCOPES[$scope])) {
            $scope = 'grand';
        }

        $year = trim((string) ($filters['year'] ?? ''));
        $year = $year !== '' && in_array($year, YearwiseIndicatorsWithJitLakhpatiService::DISPLAY_YEARS, true)
            ? $year
            : null;

        $phase = trim((string) ($filters['phase'] ?? ''));
        $phaseKeys = array_column($this->plus->phaseGroups(), 'key');
        $phase = $phase !== '' && in_array($phase, $phaseKeys, true) ? $phase : null;

        // A selected phase/year must actually filter the list and export, even if
        // Scope was left on Grand Total (the form default).
        if ($scope === 'grand') {
            if ($phase !== null) {
                $scope = 'phase';
            } elseif ($year !== null) {
                $scope = 'year';
            }
        }

        $years = match ($scope) {
            'year' => $year !== null ? [$year] : YearwiseIndicatorsWithJitLakhpatiService::DISPLAY_YEARS,
            'phase' => $this->yearsForPhase($phase),
            'till' => ['2021-22', '2022-23', '2023-24'],
            default => YearwiseIndicatorsWithJitLakhpatiService::DISPLAY_YEARS,
        };

        $districtRaw = trim((string) ($filters['district'] ?? ''));
        $district = $districtRaw !== ''
            ? $this->plus->resolveDistrictLabel($districtRaw)
            : null;

        $source = trim((string) ($filters['source'] ?? 'all'));
        if (! isset(self::SOURCES[$source])) {
            $source = 'all';
        }

        $q = trim((string) ($filters['q'] ?? ''));

        $onboard = trim((string) ($filters['onboard'] ?? 'all'));
        if (! isset(self::ONBOARD_FILTERS[$onboard])) {
            $onboard = 'all';
        }

        return [
            'metric' => $metric,
            'scope' => $scope,
            'year' => $year,
            'phase' => $phase,
            'years' => $years,
            'district' => $district,
            'source' => $source,
            'onboard' => $onboard,
            'q' => $q,
        ];
    }

    /**
     * @param  array{
     *     metric: string,
     *     years: list<string>,
     *     district: ?string,
     *     source: string,
     *     q: string
     * }  $filters
     * @return list<array<string, mixed>>
     */
    private function filteredRows(array $filters): array
    {
        $bucketed = $this->cachedListForMetric($filters['metric']);
        $out = [];
        $q = mb_strtolower($filters['q']);

        foreach ($filters['years'] as $fy) {
            foreach ($bucketed[$fy] ?? [] as $row) {
                if ($filters['district'] !== null) {
                    $rowDistrict = $this->plus->resolveDistrictLabel((string) ($row['district'] ?? ''));
                    if ($rowDistrict !== $filters['district']) {
                        continue;
                    }
                }

                $rowSource = (string) ($row['plus_source'] ?? 'verified');
                if ($filters['source'] !== 'all' && $rowSource !== $filters['source']) {
                    continue;
                }

                if ($q !== '') {
                    $linkHay = '';
                    foreach (is_array($row['market_links'] ?? null) ? $row['market_links'] : [] as $link) {
                        if (! is_array($link)) {
                            continue;
                        }
                        $linkHay .= ' '.($link['label'] ?? '').' '.($link['url'] ?? '');
                    }
                    $hay = mb_strtolower(implode(' ', [
                        (string) ($row['applicant_name'] ?? ''),
                        (string) ($row['phone'] ?? ''),
                        (string) ($row['application_no'] ?? ''),
                        (string) ($row['district'] ?? ''),
                        (string) ($row['block'] ?? ''),
                        (string) ($row['sector'] ?? ''),
                        (string) ($row['product'] ?? ''),
                        (string) ($row['service_label'] ?? ''),
                        (string) ($row['service_number'] ?? ''),
                        (string) ($row['category'] ?? ''),
                        $linkHay,
                    ]));
                    if (! str_contains($hay, $q)) {
                        continue;
                    }
                }

                $out[] = $row;
            }
        }

        $onboard = (string) ($filters['onboard'] ?? 'all');
        if ($onboard !== 'all' && $out !== []) {
            $appNos = [];
            foreach ($out as $row) {
                $appNo = trim((string) ($row['application_no'] ?? ''));
                if ($appNo !== '' && $appNo !== '—') {
                    $appNos[$appNo] = true;
                }
            }
            $onboarded = $this->loadOnboardStatusByAppNos(array_keys($appNos));
            $wantOnboarded = $onboard === 'onboarded';
            $out = array_values(array_filter($out, static function (array $row) use ($onboarded, $wantOnboarded): bool {
                $appNo = trim((string) ($row['application_no'] ?? ''));
                $isOnboarded = $appNo !== '' && $appNo !== '—' && isset($onboarded[$appNo]);

                return $wantOnboarded ? $isOnboarded : ! $isOnboarded;
            }));
        }

        return $out;
    }

    /**
     * Year-bucketed rows for one metric (avoids scanning all FYs on every request).
     *
     * @return array<string, list<array<string, mixed>>>
     */
    private function cachedListForMetric(string $metric): array
    {
        /** @var array<string, list<array<string, mixed>>> $list */
        $list = Cache::store('file')->remember($this->metricCacheKey($metric), 300, function () use ($metric): array {
            return $this->buildListForMetric($metric);
        });

        return $list;
    }

    private function metricCacheKey(string $metric): string
    {
        return self::CACHE_KEY.'_'.$metric;
    }

    /**
     * @return array<string, list<array<string, mixed>>>
     */
    private function buildListForMetric(string $metric): array
    {
        $this->workbook->raiseMemoryLimitPublic('1024M');
        $payload = $this->workbook->build();
        $displayYears = array_fill_keys(YearwiseIndicatorsWithJitLakhpatiService::DISPLAY_YEARS, true);

        $base = is_array($payload[$metric] ?? null) ? $payload[$metric] : [];
        unset($payload);

        $flat = [];
        foreach ($base as $item) {
            $fy = (string) ($item['year'] ?? '');
            if (! isset($displayYears[$fy])) {
                continue;
            }
            $flat[] = $this->normalizeBaseRow($item, $metric);
        }
        unset($base);

        $this->appendJitLakhpatiExtrasForMetric($flat, $metric);

        $byYear = [];
        foreach (YearwiseIndicatorsWithJitLakhpatiService::DISPLAY_YEARS as $fy) {
            $byYear[$fy] = [];
        }
        foreach ($flat as $row) {
            $fy = (string) ($row['year'] ?? '');
            if (! isset($byYear[$fy])) {
                continue;
            }
            $byYear[$fy][] = $row;
        }
        unset($flat);

        return $byYear;
    }

    /**
     * @param  array<string, mixed>  $item
     * @return array<string, mixed>
     */
    private function normalizeBaseRow(array $item, string $metric): array
    {
        $phone = trim((string) ($item['phone'] ?? ''));
        $digits = preg_replace('/\D+/', '', $phone) ?? '';
        if (strlen($digits) < 8) {
            $phone = '';
        }

        $applicationNo = (string) ($item['application_no'] ?? '');
        $serviceNumber = ServiceRegistrationNumberExtractor::usable(
            (string) ($item['service_number'] ?? ''),
            $applicationNo,
            $metric.' '.(string) ($item['service_label'] ?? '').' '.(string) ($item['category'] ?? ''),
        );

        $marketLinks = [];
        if (is_array($item['market_links'] ?? null)) {
            foreach ($item['market_links'] as $link) {
                if (! is_array($link)) {
                    continue;
                }
                $url = trim((string) ($link['url'] ?? ''));
                $label = trim((string) ($link['label'] ?? ''));
                if ($url === '' && $label === '') {
                    continue;
                }
                $marketLinks[] = [
                    'label' => $label !== '' ? $label : ($url !== '' ? $url : 'Link'),
                    'url' => $url,
                ];
            }
        }

        return [
            'year' => (string) ($item['year'] ?? ''),
            'plus_source' => 'verified',
            'source_label' => 'Verified',
            'source_db' => (string) ($item['source_db'] ?? ''),
            'source_table' => (string) ($item['source_table'] ?? ''),
            'record_id' => (string) ($item['record_id'] ?? ''),
            'application_no' => $applicationNo,
            'applicant_name' => (string) ($item['applicant_name'] ?? ''),
            'district' => (string) ($item['district'] ?? ''),
            'hub' => (string) ($item['hub'] ?? ''),
            'phone' => $phone,
            'block' => (string) ($item['block'] ?? ''),
            'sector' => (string) ($item['sector'] ?? ''),
            'product' => (string) ($item['product'] ?? ''),
            'service_number' => $serviceNumber,
            'market_links' => $marketLinks,
            'spoc' => (string) ($item['spoc'] ?? ''),
            'date_used' => (string) ($item['date_used'] ?? ''),
            'category' => (string) ($item['category'] ?? ''),
            'service_label' => (string) ($item['service_label'] ?? ''),
            'detail' => (string) ($item['detail'] ?? ($item['batch_name'] ?? '')),
            'status' => (string) ($item['status'] ?? ($item['onboard_flag'] ?? '')),
        ];
    }

    /**
     * @param  list<array<string, mixed>>  $list
     */
    private function appendJitLakhpatiExtrasForMetric(array &$list, string $metric): void
    {
        if (! $this->plus->legacyPhase1Available()) {
            return;
        }

        $needsOnboardingJit = $metric === 'onboarding';
        $needsOnboardingLd = $metric === 'onboarding';
        $needsServiceJit = in_array($metric, ['udyam', 'artisan_card', 'fssai', 'gst', 'market_linkage', 'convergence'], true);
        if (! $needsOnboardingJit && ! $needsServiceJit && ! $needsOnboardingLd) {
            return;
        }

        $phoneFy = $needsServiceJit ? $this->plus->cfaPhoneFyMapPublic() : [];
        $jitFy = '2023-24';
        $fallbackFy = '2024-25';

        if (($needsOnboardingJit || $needsServiceJit) && Schema::connection('legacy_phase1')->hasTable('justintime')) {
            $cols = ['id', 'fullname', 'contact', 'district', 'type', 'detail', 'applied', 'sanction'];
            foreach (DB::connection('legacy_phase1')->table('justintime')->get($cols) as $row) {
                $phone = trim((string) ($row->contact ?? ''));
                $district = $this->plus->resolveDistrictLabel((string) ($row->district ?? ''));
                $type = trim((string) ($row->type ?? ''));
                $name = trim((string) ($row->fullname ?? ''));
                $detail = trim((string) ($row->detail ?? ''));

                if ($needsOnboardingJit) {
                    $list[] = [
                        'year' => $jitFy,
                        'plus_source' => 'jit',
                        'source_label' => 'JIT',
                        'source_db' => 'ukrbiin_rbi',
                        'source_table' => 'justintime',
                        'record_id' => (string) ($row->id ?? ''),
                        'application_no' => '',
                        'applicant_name' => $name,
                        'district' => $district,
                        'hub' => '',
                        'block' => '',
                        'sector' => '',
                        'product' => '',
                        'service_number' => '',
                        'phone' => $phone,
                        'date_used' => '',
                        'category' => $type,
                        'service_label' => $type !== '' ? $type : 'JIT support',
                        'detail' => $detail,
                        'status' => trim((string) (($row->applied ?? '').' / '.($row->sanction ?? '')), ' /'),
                    ];
                }

                if ($needsServiceJit) {
                    $serviceKey = $this->plus->mapJitServiceTypePublic($type);
                    if ($serviceKey === $metric) {
                        $serviceFy = $phone !== '' && isset($phoneFy[$phone])
                            ? $phoneFy[$phone]
                            : $fallbackFy;
                        if (! in_array($serviceFy, YearwiseIndicatorsWithJitLakhpatiService::DISPLAY_YEARS, true)) {
                            $serviceFy = $fallbackFy;
                        }
                        $list[] = [
                            'year' => $serviceFy,
                            'plus_source' => 'jit',
                            'source_label' => 'JIT',
                            'source_db' => 'ukrbiin_rbi',
                            'source_table' => 'justintime',
                            'record_id' => (string) ($row->id ?? ''),
                            'application_no' => '',
                            'applicant_name' => $name,
                            'district' => $district,
                            'hub' => '',
                            'block' => '',
                            'sector' => '',
                            'product' => '',
                            'service_number' => '',
                            'phone' => $phone,
                            'date_used' => '',
                            'category' => $type,
                            'service_label' => $type,
                            'detail' => $detail,
                            'status' => '',
                        ];
                    }
                }
            }
        }

        if ($needsOnboardingLd && Schema::connection('legacy_phase1')->hasTable('lakhpati_didi')) {
            $ldCols = ['id', 'name', 'contact', 'district_name', 'ApplicationNumber', 'time', 'business_cert'];
            $available = Schema::connection('legacy_phase1')->getColumnListing('lakhpati_didi');
            $select = array_values(array_intersect($ldCols, $available));
            if ($select === []) {
                $select = ['contact', 'district_name'];
            }

            foreach (DB::connection('legacy_phase1')->table('lakhpati_didi')->get($select) as $row) {
                $list[] = [
                    'year' => $jitFy,
                    'plus_source' => 'lakhpati_didi',
                    'source_label' => 'Lakhpati Didi',
                    'source_db' => 'ukrbiin_rbi',
                    'source_table' => 'lakhpati_didi',
                    'record_id' => (string) ($row->id ?? ''),
                    'application_no' => trim((string) ($row->ApplicationNumber ?? '')),
                    'applicant_name' => trim((string) ($row->name ?? '')),
                    'district' => $this->plus->resolveDistrictLabel((string) ($row->district_name ?? '')),
                    'hub' => '',
                    'block' => '',
                    'sector' => '',
                    'product' => '',
                    'service_number' => '',
                    'phone' => trim((string) ($row->contact ?? '')),
                    'date_used' => trim((string) ($row->time ?? '')),
                    'category' => 'Lakhpati Didi',
                    'service_label' => 'Lakhpati Didi',
                    'detail' => '',
                    'status' => trim((string) ($row->business_cert ?? '')),
                ];
            }
        }
    }

    /**
     * Enrich incubatee fields (+ optional docs) in one pass for the current page/export slice.
     *
     * @param  list<array<string, mixed>>  $rows
     * @return list<array<string, mixed>>
     */
    private function hydratePageRows(array $rows, string $metric, bool $attachDocs): array
    {
        if ($rows === []) {
            return $rows;
        }

        $appNos = [];
        $caseIds = [];
        $legacyIds = [];
        foreach ($rows as $row) {
            $appNo = trim((string) ($row['application_no'] ?? ''));
            if ($appNo !== '' && $appNo !== '—') {
                $appNos[$appNo] = true;
            }
            $rid = trim((string) ($row['record_id'] ?? ''));
            $sourceDb = (string) ($row['source_db'] ?? '');
            $sourceTable = (string) ($row['source_table'] ?? '');
            $isPhase3 = str_contains($sourceDb, 'deliverables')
                || str_contains($sourceDb, 'muy')
                || $sourceTable === 'deliverables'
                || $sourceTable === 'service_cases';
            if ($rid !== '' && ctype_digit($rid)) {
                if ($isPhase3) {
                    $caseIds[(int) $rid] = true;
                } else {
                    $legacyIds[(int) $rid] = true;
                }
            }
        }

        $byApp = [];
        $appByCaseId = [];
        $serviceNumberByCaseId = [];
        $docsByCaseId = [];
        $docsByAppNo = [];
        $docsByLegacyId = [];

        if (
            ($caseIds !== [] || ($attachDocs && ($appNos !== [] || $legacyIds !== [])))
            && class_exists(ServiceCase::class)
            && Schema::hasTable('service_cases')
        ) {
            $with = ['cfaSubmission:id,application_no,phone,payload'];
            if ($attachDocs && Schema::hasTable('service_case_attachments')) {
                $with[] = 'attachments';
            }

            $query = ServiceCase::query()->with($with);
            $query->where(function ($q) use ($caseIds, $appNos, $legacyIds, $attachDocs): void {
                if ($caseIds !== []) {
                    $q->orWhereIn('id', array_keys($caseIds));
                }
                if ($attachDocs && $appNos !== []) {
                    $q->orWhereIn('reference_number', array_keys($appNos));
                    $q->orWhereHas('cfaSubmission', function ($cq) use ($appNos): void {
                        $cq->whereIn('application_no', array_keys($appNos));
                    });
                }
                if ($attachDocs && $legacyIds !== [] && ServiceCase::supportsLegacyApplicationLink()) {
                    $q->orWhereIn('legacy_application_id', array_keys($legacyIds));
                }
            });

            foreach ($query->limit(800)->get(['id', 'cfa_submission_id', 'reference_number', 'legacy_application_id', 'payload', 'service_id']) as $case) {
                /** @var ServiceCase $case */
                $cfa = $case->cfaSubmission;
                $appNo = trim((string) ($cfa?->application_no ?: ''));
                $ref = trim((string) ($case->reference_number ?? ''));
                if ($appNo === '' && $ref !== '' && ! ServiceRegistrationNumberExtractor::looksLikeRegistration($ref)) {
                    $appNo = $ref;
                }
                if ($appNo !== '') {
                    $appByCaseId[(int) $case->id] = $appNo;
                    $appNos[$appNo] = true;
                }

                $payload = is_array($case->payload) ? $case->payload : [];
                $serviceNumberByCaseId[(int) $case->id] = ServiceRegistrationNumberExtractor::fromPayload(
                    $payload,
                    $ref,
                    $metric,
                    $appNo,
                );

                if ($attachDocs) {
                    $links = [];
                    foreach ($case->attachments ?? [] as $att) {
                        /** @var ServiceCaseAttachment $att */
                        $links[] = [
                            'label' => $att->original_name !== '' ? $att->original_name : 'Document',
                            'url' => route('admin.phase3-services.attachments.view', [
                                'service_case' => $case->id,
                                'attachment' => $att->id,
                            ]),
                        ];
                    }
                    if ($links !== []) {
                        $docsByCaseId[(int) $case->id] = array_merge($docsByCaseId[(int) $case->id] ?? [], $links);
                        if ($ref !== '') {
                            $docsByAppNo[$ref] = array_merge($docsByAppNo[$ref] ?? [], $links);
                        }
                        if ($appNo !== '') {
                            $docsByAppNo[$appNo] = array_merge($docsByAppNo[$appNo] ?? [], $links);
                        }
                        $leg = (int) ($case->legacy_application_id ?? 0);
                        if ($leg > 0) {
                            $docsByLegacyId[$leg] = array_merge($docsByLegacyId[$leg] ?? [], $links);
                        }
                    }
                }
            }
        }

        if ($appNos !== [] && Schema::hasTable('cfa_submissions')) {
            foreach (array_chunk(array_keys($appNos), 400) as $chunk) {
                $subs = CfaSubmission::query()
                    ->whereIn('application_no', $chunk)
                    ->get(['application_no', 'phone', 'payload', 'applicant_name']);
                foreach ($subs as $sub) {
                    $payload = is_array($sub->payload) ? $sub->payload : [];
                    $appNo = trim((string) $sub->application_no);
                    if ($appNo === '') {
                        continue;
                    }
                    $profile = $this->profileFromCfaPayload(
                        $payload,
                        trim((string) ($sub->phone ?: ($payload['phone'] ?? ''))),
                        trim((string) ($sub->applicant_name ?? '')),
                    );
                    if (in_array($metric, ['convergence', 'market_linkage', 'onboarding', 'cfa'], true)) {
                        $profile['service_number'] = '';
                    } else {
                        $profile['service_number'] = ServiceRegistrationNumberExtractor::fromPayload(
                            $payload,
                            '',
                            $metric,
                            $appNo,
                        );
                    }
                    $byApp[$appNo] = $profile;
                }
            }
        }

        if ($appNos !== []) {
            try {
                /** @var LegacyApplicationServiceCaseSupport $legacy */
                $legacy = app(LegacyApplicationServiceCaseSupport::class);
                $snapshots = $legacy->applicantSnapshotsByLegacyApplicationNumbers(array_keys($appNos));
                foreach ($snapshots as $snap) {
                    $appNo = trim((string) ($snap['application_no'] ?? ''));
                    if ($appNo === '') {
                        continue;
                    }
                    $existing = $byApp[$appNo] ?? $this->emptyOnboardingProfile();
                    if (($existing['phone'] ?? '') === '') {
                        $existing['phone'] = trim((string) ($snap['phone'] ?? ''));
                    }
                    if (($existing['block'] ?? '') === '') {
                        $existing['block'] = trim((string) ($snap['block_name'] ?? ''));
                    }
                    $byApp[$appNo] = $existing;
                }
            } catch (\Throwable) {
                // Phase-2 DB may be unavailable.
            }

            try {
                if (Schema::connection('legacy')->hasTable('rbi_applications')) {
                    $p2Apps = DB::connection('legacy')
                        ->table('rbi_applications as a')
                        ->leftJoin('rbi_applicant_details as d', 'd.application_id', '=', 'a.id')
                        ->whereIn('a.application_no', array_keys($appNos))
                        ->get([
                            'a.application_no',
                            'a.business_category',
                            'a.product',
                            'a.other_product',
                            'd.block',
                            'd.phone',
                        ]);
                    foreach ($p2Apps as $p2) {
                        $appNo = trim((string) ($p2->application_no ?? ''));
                        if ($appNo === '') {
                            continue;
                        }
                        $existing = $byApp[$appNo] ?? $this->emptyOnboardingProfile();
                        if (($existing['phone'] ?? '') === '') {
                            $existing['phone'] = trim((string) ($p2->phone ?? ''));
                        }
                        if (($existing['block'] ?? '') === '') {
                            $existing['block'] = trim((string) ($p2->block ?? ''));
                        }
                        if (($existing['sector'] ?? '') === '') {
                            $existing['sector'] = trim((string) ($p2->business_category ?? ''));
                        }
                        if (($existing['product'] ?? '') === '') {
                            $product = trim((string) ($p2->product ?? ''));
                            $other = trim((string) ($p2->other_product ?? ''));
                            if ($product === '' || strcasecmp($product, 'Others') === 0) {
                                $product = $other !== '' ? $other : $product;
                            }
                            $existing['product'] = $product;
                        }
                        $byApp[$appNo] = $existing;
                    }
                }
            } catch (\Throwable) {
                // ignore
            }
        }

        if (in_array($metric, ['onboarding', 'market_linkage'], true) && $appNos !== []) {
            $this->mergePhase1OnboardingProfiles($byApp, array_keys($appNos));
            $this->mergePhase2OnboardingProfiles($byApp, array_keys($appNos));
            $this->mergePhase2EnterpriseDetails($byApp, array_keys($appNos));
        }

        if ($attachDocs && $appNos !== [] && Schema::hasTable('market_linkage_submissions') && Schema::hasTable('market_linkage_partners')) {
            $subs = MarketLinkageSubmission::query()
                ->with('partners')
                ->whereIn('application_no', array_keys($appNos))
                ->limit(500)
                ->get();
            foreach ($subs as $sub) {
                $appNo = trim((string) ($sub->application_no ?? ''));
                if ($appNo === '') {
                    continue;
                }
                foreach ($sub->partners as $partner) {
                    /** @var MarketLinkagePartner $partner */
                    if (! $partner->hasDocument()) {
                        continue;
                    }
                    $docsByAppNo[$appNo][] = [
                        'label' => $partner->document_original_name
                            ?: ($partner->partner_name ?: 'Market linkage doc'),
                        'url' => route('admin.market-linkages.document', [
                            'market_linkage' => $sub->id,
                            'partner' => $partner->id,
                        ]),
                    ];
                }
            }
        }

        $phase2Docs = $attachDocs ? $this->phase2ServiceDocNames(array_keys($legacyIds)) : [];

        $linksBySubmissionId = [];
        $linksByPartnerId = [];
        $linksByAppNo = [];
        $gstByApp = [];
        $fssaiByApp = [];
        $onboardByApp = [];
        if ($metric === 'market_linkage' || $metric === 'onboarding') {
            [$linksBySubmissionId, $linksByPartnerId, $linksByAppNo] = $this->loadMarketLinksForRows($rows, $appNos);
        }
        if ($metric === 'onboarding' && $appNos !== []) {
            [$gstByApp, $fssaiByApp] = $this->loadGstFssaiNumbersByAppNos(array_keys($appNos));
        }
        if (in_array($metric, ['onboarding', 'market_linkage'], true) && $appNos !== []) {
            $onboardByApp = $this->loadOnboardStatusByAppNos(array_keys($appNos));
        }

        foreach ($rows as &$row) {
            $rid = trim((string) ($row['record_id'] ?? ''));
            if (
                (trim((string) ($row['application_no'] ?? '')) === '' || trim((string) ($row['application_no'] ?? '')) === '—')
                && $rid !== ''
                && ctype_digit($rid)
                && isset($appByCaseId[(int) $rid])
            ) {
                $row['application_no'] = $appByCaseId[(int) $rid];
            }

            $appNo = trim((string) ($row['application_no'] ?? ''));
            $extra = $appNo !== '' && $appNo !== '—' ? ($byApp[$appNo] ?? null) : null;

            $phone = trim((string) ($row['phone'] ?? ''));
            $digits = preg_replace('/\D+/', '', $phone) ?? '';
            if (strlen($digits) < 8) {
                $phone = '';
            }
            if ($phone === '' && $extra) {
                $phone = trim((string) ($extra['phone'] ?? ''));
            }

            $row['phone'] = $phone;
            if (trim((string) ($row['block'] ?? '')) === '' && $extra) {
                $row['block'] = (string) ($extra['block'] ?? '');
            }
            if (trim((string) ($row['sector'] ?? '')) === '' && $extra) {
                $row['sector'] = (string) ($extra['sector'] ?? '');
            }
            if (trim((string) ($row['product'] ?? '')) === '' && $extra) {
                $row['product'] = (string) ($extra['product'] ?? '');
            }

            $hint = $metric.' '.(string) ($row['service_label'] ?? '').' '.(string) ($row['category'] ?? '');
            $serviceNumber = ServiceRegistrationNumberExtractor::usable(
                (string) ($row['service_number'] ?? ''),
                $appNo,
                $hint,
            );
            if ($serviceNumber === '' && $rid !== '' && ctype_digit($rid) && isset($serviceNumberByCaseId[(int) $rid])) {
                $serviceNumber = ServiceRegistrationNumberExtractor::usable(
                    (string) $serviceNumberByCaseId[(int) $rid],
                    $appNo,
                    $hint,
                );
            }
            if ($serviceNumber === '' && $extra) {
                $serviceNumber = ServiceRegistrationNumberExtractor::usable(
                    (string) ($extra['service_number'] ?? ''),
                    $appNo,
                    $hint,
                );
            }
            $row['service_number'] = $serviceNumber;

            $marketLinks = is_array($row['market_links'] ?? null) ? $row['market_links'] : [];
            if (($metric === 'market_linkage' || $metric === 'onboarding') && $marketLinks === []) {
                $sourceTable = (string) ($row['source_table'] ?? '');
                if ($rid !== '' && ctype_digit($rid) && $sourceTable === 'market_linkage_submissions' && isset($linksBySubmissionId[(int) $rid])) {
                    $marketLinks = $linksBySubmissionId[(int) $rid];
                } elseif ($rid !== '' && ctype_digit($rid) && $sourceTable === 'rbi_service_partners' && isset($linksByPartnerId[(int) $rid])) {
                    $marketLinks = $linksByPartnerId[(int) $rid];
                } elseif ($appNo !== '' && $appNo !== '—' && isset($linksByAppNo[$appNo])) {
                    $marketLinks = $linksByAppNo[$appNo];
                }
            }
            $row['market_links'] = $this->uniqueMarketLinks($marketLinks);

            $row['block'] = (string) ($row['block'] ?? '');
            $row['sector'] = (string) ($row['sector'] ?? '');
            $row['product'] = (string) ($row['product'] ?? '');
            $row['hub'] = (string) ($row['hub'] ?? '');

            $copyApplicant = in_array($metric, ['onboarding', 'market_linkage'], true);
            if ($copyApplicant && $extra) {
                foreach (self::ONBOARDING_CFA_KEYS as $key) {
                    if (trim((string) ($row[$key] ?? '')) === '' && trim((string) ($extra[$key] ?? '')) !== '') {
                        $row[$key] = (string) $extra[$key];
                    }
                }
                if (trim((string) ($row['applicant_name'] ?? '')) === '' && trim((string) ($extra['cfa_name'] ?? '')) !== '') {
                    $row['applicant_name'] = (string) $extra['cfa_name'];
                }
                if (trim((string) ($row['hub'] ?? '')) === '' && trim((string) ($extra['hub'] ?? '')) !== '') {
                    $row['hub'] = (string) $extra['hub'];
                }
                if (trim((string) ($row['email'] ?? '')) === '' && trim((string) ($extra['email'] ?? '')) !== '') {
                    $row['email'] = (string) $extra['email'];
                }
            }

            if ($metric === 'onboarding') {
                $gst = '';
                $fssai = '';
                if ($appNo !== '' && $appNo !== '—') {
                    $gst = $this->joinUniqueNumbers($gstByApp[$appNo] ?? []);
                    $fssai = $this->joinUniqueNumbers($fssaiByApp[$appNo] ?? []);
                }
                if ($gst === '' && $extra) {
                    $gst = $this->cfaFallbackRegistration((string) ($extra['registration_type'] ?? ''), (string) ($extra['cfa_registration_number'] ?? ''), 'gst');
                }
                if ($fssai === '' && $extra) {
                    $fssai = $this->cfaFallbackRegistration((string) ($extra['registration_type'] ?? ''), (string) ($extra['cfa_registration_number'] ?? ''), 'fssai');
                }
                $row['gst_number'] = $gst !== '' ? $gst : self::MISSING_LABEL;
                $row['fssai_number'] = $fssai !== '' ? $fssai : self::MISSING_LABEL;

                $marketDisplay = $this->marketDisplayFromLinks($row['market_links']);
                $row['market_partners'] = $marketDisplay['partners'];
                $row['market_link_urls'] = $marketDisplay['links'];

                if (trim($row['sector']) === '') {
                    $row['sector'] = self::MISSING_LABEL;
                }
                if (trim($row['product']) === '') {
                    $row['product'] = self::MISSING_LABEL;
                }
            }

            if ($copyApplicant) {
                $isOnboarded = $appNo !== '' && $appNo !== '—' && isset($onboardByApp[$appNo]);
                $row['onboard_status'] = $isOnboarded ? 'Onboarded' : 'Not onboarded';
                $row['form_stage'] = $this->resolveFormStage($row);
                if ($metric === 'market_linkage') {
                    $marketDisplay = $this->marketDisplayFromLinks($row['market_links']);
                    $row['market_partners'] = $marketDisplay['partners'];
                    $row['market_link_urls'] = $marketDisplay['links'];
                }
            }

            if (! $attachDocs) {
                $row['documents'] = [];
                continue;
            }

            $docs = [];
            $sourceDb = (string) ($row['source_db'] ?? '');
            $sourceTable = (string) ($row['source_table'] ?? '');
            $isPhase3 = str_contains($sourceDb, 'deliverables')
                || str_contains($sourceDb, 'muy')
                || $sourceTable === 'deliverables'
                || $sourceTable === 'service_cases';

            if ($rid !== '' && ctype_digit($rid) && $isPhase3 && isset($docsByCaseId[(int) $rid])) {
                $docs = array_merge($docs, $docsByCaseId[(int) $rid]);
            }
            if ($appNo !== '' && $appNo !== '—' && isset($docsByAppNo[$appNo])) {
                $docs = array_merge($docs, $docsByAppNo[$appNo]);
            }
            if ($rid !== '' && ctype_digit($rid) && ! $isPhase3 && isset($docsByLegacyId[(int) $rid])) {
                $docs = array_merge($docs, $docsByLegacyId[(int) $rid]);
            }
            if (
                $rid !== ''
                && ctype_digit($rid)
                && isset($phase2Docs[(int) $rid])
                && (string) ($row['source_table'] ?? '') === 'rbi_services_assigned'
            ) {
                $docs[] = [
                    'label' => $phase2Docs[(int) $rid],
                    'url' => null,
                    'note' => 'Legacy file (not linked)',
                ];
            }

            $seen = [];
            $unique = [];
            foreach ($docs as $doc) {
                $key = ($doc['url'] ?? '').'|'.($doc['label'] ?? '');
                if (isset($seen[$key])) {
                    continue;
                }
                $seen[$key] = true;
                $unique[] = $doc;
            }
            $row['documents'] = $unique;
        }
        unset($row);

        return $rows;
    }

    /**
     * @param  list<array<string, mixed>>  $rows
     * @param  array<string, true>  $appNos
     * @return array{
     *     0: array<int, list<array{label: string, url: string}>>,
     *     1: array<int, list<array{label: string, url: string}>>,
     *     2: array<string, list<array{label: string, url: string}>>
     * }
     */
    private function loadMarketLinksForRows(array $rows, array $appNos): array
    {
        $linksBySubmissionId = [];
        $linksByPartnerId = [];
        $linksByAppNo = [];

        $submissionIds = [];
        $partnerIds = [];
        foreach ($rows as $row) {
            $rid = trim((string) ($row['record_id'] ?? ''));
            $table = (string) ($row['source_table'] ?? '');
            if ($rid === '' || ! ctype_digit($rid)) {
                continue;
            }
            if ($table === 'market_linkage_submissions') {
                $submissionIds[(int) $rid] = true;
            } elseif ($table === 'rbi_service_partners') {
                $partnerIds[(int) $rid] = true;
            }
        }

        if ($submissionIds !== [] && Schema::hasTable('market_linkage_partners')) {
            $partners = DB::table('market_linkage_partners')
                ->whereIn('market_linkage_submission_id', array_keys($submissionIds))
                ->orderBy('sort_order')
                ->orderBy('id')
                ->get(['market_linkage_submission_id', 'partner_name', 'link_url']);
            foreach ($partners as $p) {
                $sid = (int) ($p->market_linkage_submission_id ?? 0);
                $label = trim((string) ($p->partner_name ?? ''));
                $url = trim((string) ($p->link_url ?? ''));
                if ($sid <= 0 || ($label === '' && $url === '')) {
                    continue;
                }
                $linksBySubmissionId[$sid][] = [
                    'label' => $label !== '' ? $label : 'Link',
                    'url' => $url,
                ];
            }
        }

        if ($appNos !== [] && Schema::hasTable('market_linkage_submissions') && Schema::hasTable('market_linkage_partners')) {
            foreach (array_chunk(array_keys($appNos), 400) as $chunk) {
                $query = DB::table('market_linkage_partners as mlp')
                    ->join('market_linkage_submissions as mls', 'mls.id', '=', 'mlp.market_linkage_submission_id')
                    ->whereIn('mls.application_no', $chunk)
                    ->orderBy('mlp.sort_order')
                    ->orderBy('mlp.id');
                foreach ($query->get(['mls.id', 'mls.application_no', 'mlp.partner_name', 'mlp.link_url']) as $p) {
                    $appNo = trim((string) ($p->application_no ?? ''));
                    $label = trim((string) ($p->partner_name ?? ''));
                    $url = trim((string) ($p->link_url ?? ''));
                    if (($label === '' && $url === '') || $appNo === '') {
                        continue;
                    }
                    $entry = [
                        'label' => $label !== '' ? $label : 'Link',
                        'url' => $url,
                    ];
                    $linksByAppNo[$appNo][] = $entry;
                    $sid = (int) ($p->id ?? 0);
                    if ($sid > 0) {
                        $linksBySubmissionId[$sid][] = $entry;
                    }
                }
            }
        }

        if ($partnerIds !== [] || $appNos !== []) {
            try {
                if (Schema::connection('legacy')->hasTable('rbi_service_partners')) {
                    $appChunks = array_keys($appNos);
                    if ($appChunks === [] && $partnerIds !== []) {
                        $q = DB::connection('legacy')->table('rbi_service_partners as sp')
                            ->leftJoin('rbi_applications as a', 'a.id', '=', 'sp.application_id')
                            ->whereIn('sp.id', array_keys($partnerIds));
                        $this->appendLegacyPartnerLinks($q->get(['sp.id', 'sp.partner_name', 'sp.partner_link', 'a.application_no']), $linksByPartnerId, $linksByAppNo);
                    } else {
                        foreach (array_chunk($appChunks, 400) as $chunk) {
                            $q = DB::connection('legacy')->table('rbi_service_partners as sp')
                                ->leftJoin('rbi_applications as a', 'a.id', '=', 'sp.application_id')
                                ->where(function ($inner) use ($partnerIds, $chunk): void {
                                    if ($partnerIds !== []) {
                                        $inner->orWhereIn('sp.id', array_keys($partnerIds));
                                    }
                                    $inner->orWhereIn('a.application_no', $chunk);
                                });
                            $this->appendLegacyPartnerLinks($q->get(['sp.id', 'sp.partner_name', 'sp.partner_link', 'a.application_no']), $linksByPartnerId, $linksByAppNo);
                        }
                    }
                }
            } catch (\Throwable) {
                // Phase-2 DB may be unavailable.
            }
        }

        if ($appNos !== []) {
            $this->appendPhase1MarketPartners(array_keys($appNos), $linksByAppNo);
        }

        return [$linksBySubmissionId, $linksByPartnerId, $linksByAppNo];
    }

    /**
     * @param  list<int>  $ids
     * @return array<int, string>
     */
    private function phase2ServiceDocNames(array $ids): array
    {
        if ($ids === []) {
            return [];
        }
        try {
            if (! Schema::connection('legacy')->hasTable('rbi_services_assigned')) {
                return [];
            }
            if (! Schema::connection('legacy')->hasColumn('rbi_services_assigned', 'service_doc')) {
                return [];
            }
        } catch (\Throwable) {
            return [];
        }

        $out = [];
        $rows = DB::connection('legacy')
            ->table('rbi_services_assigned')
            ->whereIn('id', $ids)
            ->whereNotNull('service_doc')
            ->where('service_doc', '!=', '')
            ->get(['id', 'service_doc']);
        foreach ($rows as $row) {
            $path = trim((string) ($row->service_doc ?? ''));
            if ($path === '') {
                continue;
            }
            $out[(int) $row->id] = basename($path);
        }

        return $out;
    }

    /**
     * @param  array<string, mixed>  $payload
     * @return array<string, string>
     */
    private function profileFromCfaPayload(array $payload, string $phone = '', string $applicantName = ''): array
    {
        $product = trim((string) ($payload['product'] ?? ''));
        if ($product === '' || strcasecmp($product, 'Others') === 0) {
            $other = trim((string) ($payload['other_product'] ?? ''));
            if ($other !== '') {
                $product = $other;
            }
        }

        $name = trim((string) ($payload['applicant_name'] ?? ''));
        if ($name === '') {
            $name = trim((string) ($payload['shg_cbo_name'] ?? ''));
        }
        if ($name === '') {
            $name = $applicantName;
        }

        $profile = $this->emptyOnboardingProfile();
        $profile['phone'] = $phone !== '' ? $phone : trim((string) ($payload['phone'] ?? ''));
        $profile['block'] = trim((string) ($payload['block'] ?? ($payload['block_name'] ?? '')));
        $profile['sector'] = trim((string) ($payload['business_category'] ?? ($payload['sector'] ?? '')));
        $profile['product'] = $product;
        $profile['cfa_name'] = $name;
        $profile['hub'] = trim((string) ($payload['hub'] ?? ''));
        $profile['cfa_category'] = trim((string) ($payload['category'] ?? ''));
        $profile['guardian_name'] = trim((string) ($payload['guardian_name'] ?? ''));
        $profile['shg_cbo_name'] = trim((string) ($payload['shg_cbo_name'] ?? ''));
        $profile['gender'] = trim((string) ($payload['gender'] ?? ''));
        $profile['dob'] = trim((string) ($payload['dob'] ?? ''));
        $profile['caste'] = trim((string) ($payload['caste'] ?? ''));
        $profile['education'] = trim((string) ($payload['education'] ?? ''));
        $profile['email'] = trim((string) ($payload['email'] ?? ''));
        $profile['alt_mobile'] = trim((string) ($payload['alt_mobile'] ?? ''));
        $profile['village'] = trim((string) ($payload['village'] ?? ''));
        $profile['pincode'] = trim((string) ($payload['pincode'] ?? ''));
        $profile['is_member'] = trim((string) ($payload['is_member'] ?? ($payload['is_shg_member'] ?? '')));
        $profile['shg_name'] = trim((string) ($payload['shg_name'] ?? ''));
        $profile['lakhpati'] = trim((string) ($payload['lakhpati'] ?? ''));
        $profile['id_proof_type'] = trim((string) ($payload['id_proof_type'] ?? ''));
        $profile['id_proof_number'] = trim((string) ($payload['id_proof_number'] ?? ''));
        $profile['is_registered'] = trim((string) ($payload['is_registered'] ?? ''));
        $profile['registration_type'] = trim((string) ($payload['registration_type'] ?? ''));
        $profile['cfa_registration_number'] = trim((string) ($payload['registration_number'] ?? ''));
        $profile['business_age'] = trim((string) ($payload['business_age'] ?? ''));
        $profile['turnover_last_fy'] = trim((string) ($payload['turnover_last_fy'] ?? ($payload['turnover_last_year'] ?? '')));
        $profile['current_employment'] = trim((string) ($payload['current_employment'] ?? ''));
        $profile['employed_count'] = trim((string) ($payload['employed_count'] ?? ''));
        $profile['loan_taken'] = trim((string) ($payload['loan_taken'] ?? ''));
        $profile['bank_loan'] = trim((string) ($payload['bank_loan'] ?? ''));
        $profile['location_type'] = trim((string) ($payload['location_type'] ?? ''));
        $profile['enterprise_name'] = trim((string) ($payload['enterprise_name'] ?? ''));
        $profile['training_received'] = trim((string) ($payload['training_received'] ?? ''));
        $profile['training_mode'] = trim((string) ($payload['training_mode'] ?? ''));
        $profile['info_source'] = trim((string) ($payload['info_source'] ?? ''));
        $profile['techuse'] = $this->flattenPayloadValue($payload['techuse'] ?? '');
        $profile['sustainability'] = trim((string) ($payload['sustainability'] ?? ''));
        $profile['empwomen'] = trim((string) ($payload['empwomen'] ?? ''));
        $profile['challenges'] = $this->flattenPayloadValue($payload['challenges'] ?? '');
        $profile['expectations'] = $this->flattenPayloadValue($payload['expectations'] ?? '');
        $profile['form_stage'] = trim((string) ($payload['form_stage'] ?? ($payload['business_stage'] ?? ($payload['stage'] ?? ''))));
        if ($profile['form_stage'] === '') {
            $nested = $payload['rbi_applications'] ?? null;
            if (is_array($nested)) {
                $profile['form_stage'] = trim((string) ($nested['form_stage'] ?? ''));
            }
        }

        return $profile;
    }

    /**
     * @return array<string, string>
     */
    private function emptyOnboardingProfile(): array
    {
        $profile = [
            'phone' => '',
            'block' => '',
            'sector' => '',
            'product' => '',
            'service_number' => '',
            'cfa_name' => '',
            'hub' => '',
        ];
        foreach (self::ONBOARDING_CFA_KEYS as $key) {
            $profile[$key] = '';
        }

        return $profile;
    }

    /**
     * @param  array<string, array<string, string>>  $byApp
     * @param  list<string>  $appNos
     */
    private function mergePhase1OnboardingProfiles(array &$byApp, array $appNos): void
    {
        try {
            if ((string) config('database.connections.legacy_phase1.database', '') === ''
                || ! Schema::connection('legacy_phase1')->hasTable('tblapplication')) {
                return;
            }
        } catch (\Throwable) {
            return;
        }

        $available = Schema::connection('legacy_phase1')->getColumnListing('tblapplication');
        $wanted = [
            'ApplicationNumber', 'FullName', 'MobileNumber', 'gender', 'dob', 'cast', 'education',
            'Email', 'FatherName', 'City', 'Pincode', 'Address', 'hub', 'business_desp',
            'idea', 'idea2', 'other_idea', 'enterprise_name', 'registered', 'loan', 'loan_amount',
            'current_emp', 'job_count', 'ApplicationDate',
        ];
        $select = array_values(array_intersect($wanted, $available));
        if ($select === [] || ! in_array('ApplicationNumber', $select, true)) {
            return;
        }

        foreach (array_chunk($appNos, 400) as $chunk) {
            $rows = DB::connection('legacy_phase1')
                ->table('tblapplication')
                ->whereIn('ApplicationNumber', $chunk)
                ->get($select);
            foreach ($rows as $row) {
                $appNo = trim((string) ($row->ApplicationNumber ?? ''));
                if ($appNo === '') {
                    continue;
                }
                $existing = $byApp[$appNo] ?? $this->emptyOnboardingProfile();
                $sector = $this->objStr($row, 'idea');
                if ($sector === '') {
                    $sector = $this->objStr($row, 'idea2');
                }
                if ($sector === '') {
                    $sector = $this->objStr($row, 'other_idea');
                }
                $product = $this->objStr($row, 'business_desp');
                if ($product === '') {
                    $product = $this->objStr($row, 'enterprise_name');
                }
                $incoming = [
                    'phone' => $this->objStr($row, 'MobileNumber'),
                    'block' => $this->objStr($row, 'City'),
                    'sector' => $sector,
                    'product' => $product,
                    'cfa_name' => $this->objStr($row, 'FullName'),
                    'hub' => $this->objStr($row, 'hub'),
                    'gender' => $this->objStr($row, 'gender'),
                    'dob' => $this->objStr($row, 'dob'),
                    'caste' => $this->objStr($row, 'cast'),
                    'education' => $this->objStr($row, 'education'),
                    'email' => $this->objStr($row, 'Email'),
                    'village' => $this->objStr($row, 'Address'),
                    'pincode' => $this->objStr($row, 'Pincode'),
                    'enterprise_name' => $this->objStr($row, 'enterprise_name'),
                    'is_registered' => $this->objStr($row, 'registered'),
                    'loan_taken' => $this->objStr($row, 'loan'),
                    'bank_loan' => $this->objStr($row, 'loan_amount'),
                    'current_employment' => $this->objStr($row, 'current_emp'),
                    'employed_count' => $this->objStr($row, 'job_count'),
                ];
                $this->fillEmptyProfile($existing, $incoming);
                $byApp[$appNo] = $existing;
            }
        }
    }

    /**
     * @param  array<string, array<string, string>>  $byApp
     * @param  list<string>  $appNos
     */
    private function mergePhase2OnboardingProfiles(array &$byApp, array $appNos): void
    {
        try {
            if (! Schema::connection('legacy')->hasTable('rbi_applications')
                || ! Schema::connection('legacy')->hasTable('rbi_applicant_details')) {
                return;
            }
        } catch (\Throwable) {
            return;
        }

        $detailCols = Schema::connection('legacy')->getColumnListing('rbi_applicant_details');
        $appCols = Schema::connection('legacy')->getColumnListing('rbi_applications');

        $select = ['a.application_no', 'a.business_category', 'a.product', 'a.other_product'];
        if (in_array('category', $appCols, true)) {
            $select[] = 'a.category';
        }
        if (in_array('form_stage', $appCols, true)) {
            $select[] = 'a.form_stage';
        }
        $detailMap = [
            'phone' => 'phone',
            'block' => 'block',
            'applicant_name' => 'cfa_name',
            'guardian_name' => 'guardian_name',
            'gender' => 'gender',
            'dob' => 'dob',
            'education' => 'education',
            'email' => 'email',
            'alt_mobile' => 'alt_mobile',
            'caste' => 'caste',
            'is_shg_member' => 'is_member',
            'shg_name' => 'shg_name',
            'lakhpati' => 'lakhpati',
            'village' => 'village',
            'pincode' => 'pincode',
            'loan_taken' => 'loan_taken',
            'bank_loan' => 'bank_loan',
            'current_employment' => 'current_employment',
            'employed_count' => 'employed_count',
            'id_proof_type' => 'id_proof_type',
            'id_proof_number' => 'id_proof_number',
            'training_mode' => 'training_mode',
            'info_source' => 'info_source',
            'challenges' => 'challenges',
            'expectations' => 'expectations',
        ];
        foreach (array_keys($detailMap) as $col) {
            if (in_array($col, $detailCols, true)) {
                $select[] = 'd.'.$col;
            }
        }

        foreach (array_chunk($appNos, 400) as $chunk) {
            $rows = DB::connection('legacy')
                ->table('rbi_applications as a')
                ->leftJoin('rbi_applicant_details as d', 'd.application_id', '=', 'a.id')
                ->whereIn('a.application_no', $chunk)
                ->get($select);
            foreach ($rows as $row) {
                $appNo = trim((string) ($row->application_no ?? ''));
                if ($appNo === '') {
                    continue;
                }
                $existing = $byApp[$appNo] ?? $this->emptyOnboardingProfile();
                $product = trim((string) ($row->product ?? ''));
                $other = trim((string) ($row->other_product ?? ''));
                if ($product === '' || strcasecmp($product, 'Others') === 0) {
                    $product = $other !== '' ? $other : $product;
                }
                $incoming = [
                    'sector' => trim((string) ($row->business_category ?? '')),
                    'product' => $product,
                    'cfa_category' => $this->objStr($row, 'category'),
                    'form_stage' => $this->objStr($row, 'form_stage'),
                ];
                foreach ($detailMap as $col => $key) {
                    if (isset($row->{$col})) {
                        $incoming[$key] = $this->flattenPayloadValue($row->{$col});
                    }
                }
                $this->fillEmptyProfile($existing, $incoming);
                $byApp[$appNo] = $existing;
            }
        }
    }

    /**
     * Latest Phase 2 enterprise row (turnover, registered, etc.).
     *
     * @param  array<string, array<string, string>>  $byApp
     * @param  list<string>  $appNos
     */
    private function mergePhase2EnterpriseDetails(array &$byApp, array $appNos): void
    {
        try {
            if (! Schema::connection('legacy')->hasTable('rbi_enterprise_details')
                || ! Schema::connection('legacy')->hasTable('rbi_applications')) {
                return;
            }
        } catch (\Throwable) {
            return;
        }

        $entCols = Schema::connection('legacy')->getColumnListing('rbi_enterprise_details');
        $selectEnt = [];
        $map = [
            'turnover_last_year' => 'turnover_last_fy',
            'is_registered' => 'is_registered',
            'business_age' => 'business_age',
            'enterprise_name' => 'enterprise_name',
            'location_type' => 'location_type',
            'current_employment' => 'current_employment',
            'employed_count' => 'employed_count',
        ];
        foreach ($map as $col => $key) {
            if (in_array($col, $entCols, true)) {
                $selectEnt[] = 'e.'.$col;
            }
        }
        if ($selectEnt === []) {
            return;
        }

        foreach (array_chunk($appNos, 400) as $chunk) {
            $rows = DB::connection('legacy')
                ->table('rbi_applications as a')
                ->join(DB::raw('(
                    SELECT application_id, MAX(id) AS max_id
                    FROM rbi_enterprise_details
                    GROUP BY application_id
                ) as latest'), 'latest.application_id', '=', 'a.id')
                ->join('rbi_enterprise_details as e', function ($join): void {
                    $join->on('e.application_id', '=', 'latest.application_id')
                        ->on('e.id', '=', 'latest.max_id');
                })
                ->whereIn('a.application_no', $chunk)
                ->get(array_merge(['a.application_no'], $selectEnt));

            foreach ($rows as $row) {
                $appNo = trim((string) ($row->application_no ?? ''));
                if ($appNo === '') {
                    continue;
                }
                $existing = $byApp[$appNo] ?? $this->emptyOnboardingProfile();
                $incoming = [];
                foreach ($map as $col => $key) {
                    if (isset($row->{$col})) {
                        $incoming[$key] = $this->flattenPayloadValue($row->{$col});
                    }
                }
                $this->fillEmptyProfile($existing, $incoming);
                $byApp[$appNo] = $existing;
            }
        }
    }

    /**
     * Application numbers that appear in any phase onboarded list.
     *
     * @param  list<string>  $appNos
     * @return array<string, true>
     */
    private function loadOnboardStatusByAppNos(array $appNos): array
    {
        $found = [];
        if ($appNos === []) {
            return $found;
        }

        foreach (array_chunk($appNos, 400) as $chunk) {
            try {
                if (Schema::hasTable('onboarding_batch_cfa')
                    && Schema::hasTable('onboarding_batches')
                    && Schema::hasTable('cfa_submissions')
                ) {
                    $rows = DB::table('onboarding_batch_cfa as obc')
                        ->join('onboarding_batches as ob', 'ob.id', '=', 'obc.onboarding_batch_id')
                        ->join('cfa_submissions as cs', 'cs.id', '=', 'obc.cfa_submission_id')
                        ->where('ob.status', 'locked')
                        ->whereNotNull('ob.locked_at')
                        ->whereIn('cs.application_no', $chunk)
                        ->pluck('cs.application_no');
                    foreach ($rows as $appNo) {
                        $appNo = trim((string) $appNo);
                        if ($appNo !== '') {
                            $found[$appNo] = true;
                        }
                    }
                }
            } catch (\Throwable) {
                // ignore
            }

            try {
                if (Schema::connection('legacy')->hasTable('rbi_onboarded_applicants')
                    && Schema::connection('legacy')->hasTable('rbi_applications')
                ) {
                    $query = DB::connection('legacy')
                        ->table('rbi_onboarded_applicants as oa')
                        ->join('rbi_applications as a', 'a.id', '=', 'oa.application_id')
                        ->whereIn('a.application_no', $chunk)
                        ->whereNotNull('oa.application_id');
                    if (Schema::connection('legacy')->hasColumn('rbi_onboarded_applicants', 'status')) {
                        $query->whereNotNull('oa.status')->where('oa.status', '<>', '');
                    }
                    foreach ($query->pluck('a.application_no') as $appNo) {
                        $appNo = trim((string) $appNo);
                        if ($appNo !== '') {
                            $found[$appNo] = true;
                        }
                    }
                }
            } catch (\Throwable) {
                // ignore
            }

            try {
                if ((string) config('database.connections.legacy_phase1.database', '') !== ''
                    && Schema::connection('legacy_phase1')->hasTable('tblapplication')
                    && Schema::connection('legacy_phase1')->hasColumn('tblapplication', 'onboard')
                ) {
                    $rows = DB::connection('legacy_phase1')
                        ->table('tblapplication')
                        ->whereIn('ApplicationNumber', $chunk)
                        ->whereRaw('LOWER(TRIM(onboard)) = ?', ['yes'])
                        ->pluck('ApplicationNumber');
                    foreach ($rows as $appNo) {
                        $appNo = trim((string) $appNo);
                        if ($appNo !== '') {
                            $found[$appNo] = true;
                        }
                    }
                }
            } catch (\Throwable) {
                // ignore
            }
        }

        return $found;
    }

    /**
     * @param  array<string, mixed>  $row
     */
    private function resolveFormStage(array $row): string
    {
        $stage = trim((string) ($row['form_stage'] ?? ''));
        if ($stage !== '') {
            return $stage;
        }

        $registered = trim((string) ($row['is_registered'] ?? ''));
        $turnoverRaw = trim((string) ($row['turnover_last_fy'] ?? ''));
        if ($registered === '' && $turnoverRaw === '') {
            return '';
        }

        return (new CfaBusinessStageService)->compute(
            $registered,
            CfaBusinessStageService::parseTurnover($turnoverRaw),
        )['stage'];
    }

    /**
     * @param  array<string, string>  $target
     * @param  array<string, string>  $source
     */
    private function fillEmptyProfile(array &$target, array $source): void
    {
        foreach ($source as $key => $value) {
            $value = trim((string) $value);
            if ($value === '') {
                continue;
            }
            if (trim((string) ($target[$key] ?? '')) === '') {
                $target[$key] = $value;
            }
        }
    }

    /**
     * @param  list<string>  $appNos
     * @return array{0: array<string, list<string>>, 1: array<string, list<string>>}
     */
    private function loadGstFssaiNumbersByAppNos(array $appNos): array
    {
        $gst = [];
        $fssai = [];

        if ($appNos !== [] && Schema::hasTable('service_cases') && Schema::hasTable('services')) {
            foreach (array_chunk($appNos, 400) as $chunk) {
                $query = DB::table('service_cases as sc')
                    ->join('services as s', 's.id', '=', 'sc.service_id')
                    ->leftJoin('cfa_submissions as cs', 'cs.id', '=', 'sc.cfa_submission_id')
                    ->whereIn('sc.status', ['approved', 'completed'])
                    ->where(function ($q) use ($chunk): void {
                        $q->whereIn('cs.application_no', $chunk)
                            ->orWhereIn('sc.reference_number', $chunk);
                    })
                    ->where(function ($q): void {
                        $q->where('s.code', 'like', '%gst%')
                            ->orWhere('s.code', 'like', '%fssai%')
                            ->orWhere('s.code', 'like', '%g_s_t%')
                            ->orWhere('s.code', 'like', '%f_s_s_a_i%')
                            ->orWhere('s.name', 'like', '%GST%')
                            ->orWhere('s.name', 'like', '%FSSAI%');
                    });
                foreach ($query->get(['cs.application_no', 'sc.reference_number', 'sc.payload', 's.code', 's.name']) as $row) {
                    $kind = $this->gstOrFssaiKind((string) ($row->code ?? ''), (string) ($row->name ?? ''));
                    if ($kind === null) {
                        continue;
                    }
                    $payload = $row->payload;
                    if (is_string($payload)) {
                        $payload = json_decode($payload, true) ?: [];
                    }
                    if (! is_array($payload)) {
                        $payload = [];
                    }
                    $appNo = trim((string) ($row->application_no ?? ''));
                    $ref = trim((string) ($row->reference_number ?? ''));
                    $number = ServiceRegistrationNumberExtractor::fromPayload($payload, $ref, $kind, $appNo);
                    if ($number === '') {
                        continue;
                    }
                    $keys = [];
                    if ($appNo !== '') {
                        $keys[] = $appNo;
                    }
                    if ($ref !== '' && $ref !== $appNo && in_array($ref, $appNos, true)) {
                        $keys[] = $ref;
                    }
                    foreach ($keys as $key) {
                        if ($kind === 'gst') {
                            $gst[$key][] = $number;
                        } else {
                            $fssai[$key][] = $number;
                        }
                    }
                }
            }
        }

        try {
            if ($appNos !== [] && Schema::connection('legacy')->hasTable('rbi_services_assigned')) {
                $hasNumber = Schema::connection('legacy')->hasColumn('rbi_services_assigned', 'service_number');
                foreach (array_chunk($appNos, 400) as $chunk) {
                    $q = DB::connection('legacy')->table('rbi_services_assigned as sa')
                        ->leftJoin('rbi_applications as a', 'a.id', '=', 'sa.application_id')
                        ->whereIn('a.application_no', $chunk);
                    $cols = ['a.application_no', 'sa.service_name', 'sa.category'];
                    if ($hasNumber) {
                        $cols[] = 'sa.service_number';
                    }
                    foreach ($q->get($cols) as $row) {
                        $kind = $this->gstOrFssaiKind('', (string) ($row->service_name ?? ''), (string) ($row->category ?? ''));
                        if ($kind === null) {
                            continue;
                        }
                        $appNo = trim((string) ($row->application_no ?? ''));
                        $number = ServiceRegistrationNumberExtractor::usable(
                            $hasNumber ? (string) ($row->service_number ?? '') : '',
                            $appNo,
                            $kind,
                        );
                        if ($appNo === '' || $number === '') {
                            continue;
                        }
                        if ($kind === 'gst') {
                            $gst[$appNo][] = $number;
                        } else {
                            $fssai[$appNo][] = $number;
                        }
                    }
                }
            }
        } catch (\Throwable) {
            // Phase-2 DB may be unavailable.
        }

        try {
            if ($appNos !== []
                && (string) config('database.connections.legacy_phase1.database', '') !== ''
                && Schema::connection('legacy_phase1')->hasTable('services')) {
                $cols = Schema::connection('legacy_phase1')->getColumnListing('services');
                $select = array_values(array_intersect(
                    ['ApplicationNumber', 'servicename', 'description', 'other', 'reg', 'enter_service'],
                    $cols,
                ));
                if (in_array('ApplicationNumber', $select, true)) {
                    foreach (array_chunk($appNos, 400) as $chunk) {
                        $rows = DB::connection('legacy_phase1')
                            ->table('services')
                            ->whereIn('ApplicationNumber', $chunk)
                            ->get($select);
                        foreach ($rows as $row) {
                            $blob = trim((string) ($row->servicename ?? '')).' '.trim((string) ($row->description ?? '')).' '.trim((string) ($row->other ?? ''));
                            $kind = $this->gstOrFssaiKind('', $blob);
                            if ($kind === null) {
                                continue;
                            }
                            $appNo = trim((string) ($row->ApplicationNumber ?? ''));
                            $number = ServiceRegistrationNumberExtractor::usable(
                                trim((string) ($row->reg ?? '')) !== ''
                                    ? (string) $row->reg
                                    : (string) ($row->enter_service ?? ''),
                                $appNo,
                                $kind,
                            );
                            if ($appNo === '' || $number === '') {
                                continue;
                            }
                            if ($kind === 'gst') {
                                $gst[$appNo][] = $number;
                            } else {
                                $fssai[$appNo][] = $number;
                            }
                        }
                    }
                }
            }
        } catch (\Throwable) {
            // Phase-1 DB may be unavailable.
        }

        return [$gst, $fssai];
    }

    private function gstOrFssaiKind(string $code, string $name, string $category = ''): ?string
    {
        $blob = mb_strtolower($code.' '.$name.' '.$category);
        if (str_contains($blob, 'fssai') || str_contains($blob, 'f_s_s_a_i') || str_contains($blob, 'fssa')) {
            return 'fssai';
        }
        if ($code === 'g_s_t' || $code === 'gst' || preg_match('/\bgst\b/', $blob) === 1) {
            return 'gst';
        }

        return null;
    }

    /**
     * @param  list<string>  $numbers
     */
    private function joinUniqueNumbers(array $numbers): string
    {
        $out = [];
        $seen = [];
        foreach ($numbers as $n) {
            $n = trim((string) $n);
            if ($n === '' || $n === self::MISSING_LABEL) {
                continue;
            }
            $key = mb_strtolower($n);
            if (isset($seen[$key])) {
                continue;
            }
            $seen[$key] = true;
            $out[] = $n;
        }

        return implode(' | ', $out);
    }

    private function cfaFallbackRegistration(string $registrationType, string $number, string $kind): string
    {
        $type = mb_strtolower($registrationType);
        $number = ServiceRegistrationNumberExtractor::usable($number, '', $kind);
        if ($number === '') {
            return '';
        }
        if ($kind === 'gst' && (str_contains($type, 'gst') || preg_match('/^[0-9]{2}[A-Z]{5}[0-9]{4}[A-Z][A-Z0-9]Z[A-Z0-9]$/i', $number) === 1)) {
            return $number;
        }
        if ($kind === 'fssai' && str_contains($type, 'fssai')) {
            return $number;
        }

        return '';
    }

    /**
     * @param  list<array{label?: string, url?: string}>  $links
     * @return list<array{label: string, url: string}>
     */
    private function uniqueMarketLinks(array $links): array
    {
        $seen = [];
        $out = [];
        foreach ($links as $link) {
            if (! is_array($link)) {
                continue;
            }
            $label = trim((string) ($link['label'] ?? ''));
            $url = trim((string) ($link['url'] ?? ''));
            if ($label === '' && $url === '') {
                continue;
            }
            $key = mb_strtolower($url.'|'.$label);
            if (isset($seen[$key])) {
                continue;
            }
            $seen[$key] = true;
            $out[] = [
                'label' => $label !== '' ? $label : ($url !== '' ? $url : 'Link'),
                'url' => $url,
            ];
        }

        return $out;
    }

    /**
     * @param  list<array{label?: string, url?: string}>  $links
     * @return array{partners: string, links: string}
     */
    public function marketDisplayFromLinks(array $links): array
    {
        $names = [];
        $urls = [];
        foreach ($links as $link) {
            if (! is_array($link)) {
                continue;
            }
            $label = trim((string) ($link['label'] ?? ''));
            $url = trim((string) ($link['url'] ?? ''));
            if ($label !== '' && strcasecmp($label, 'Link') !== 0 && ! in_array($label, $names, true)) {
                $names[] = $label;
            }
            if ($url !== '' && ! in_array($url, $urls, true)) {
                $urls[] = $url;
            }
        }

        return [
            'partners' => $names === [] ? self::MISSING_LABEL : implode(' | ', $names),
            'links' => $urls === [] ? self::MISSING_LABEL : implode(' | ', $urls),
        ];
    }

    /**
     * @param  \Illuminate\Support\Collection<int, object>|list<object>  $rows
     * @param  array<int, list<array{label: string, url: string}>>  $linksByPartnerId
     * @param  array<string, list<array{label: string, url: string}>>  $linksByAppNo
     */
    private function appendLegacyPartnerLinks($rows, array &$linksByPartnerId, array &$linksByAppNo): void
    {
        foreach ($rows as $p) {
            $label = trim((string) ($p->partner_name ?? ''));
            $url = trim((string) ($p->partner_link ?? ''));
            if ($label === '' && $url === '') {
                continue;
            }
            $entry = [
                'label' => $label !== '' ? $label : 'Link',
                'url' => $url,
            ];
            $pid = (int) ($p->id ?? 0);
            if ($pid > 0) {
                $linksByPartnerId[$pid][] = $entry;
            }
            $appNo = trim((string) ($p->application_no ?? ''));
            if ($appNo !== '') {
                $linksByAppNo[$appNo][] = $entry;
            }
        }
    }

    /**
     * @param  list<string>  $appNos
     * @param  array<string, list<array{label: string, url: string}>>  $linksByAppNo
     */
    private function appendPhase1MarketPartners(array $appNos, array &$linksByAppNo): void
    {
        try {
            if ((string) config('database.connections.legacy_phase1.database', '') === ''
                || ! Schema::connection('legacy_phase1')->hasTable('tblapplication')) {
                return;
            }
        } catch (\Throwable) {
            return;
        }

        $available = Schema::connection('legacy_phase1')->getColumnListing('tblapplication');
        $wanted = ['ApplicationNumber', 'partner1', 'partner2', 'partner3', 'partner4', 'partner5', 'mar_partner'];
        $select = array_values(array_intersect($wanted, $available));
        if ($select === [] || ! in_array('ApplicationNumber', $select, true)) {
            return;
        }

        foreach (array_chunk($appNos, 400) as $chunk) {
            $rows = DB::connection('legacy_phase1')
                ->table('tblapplication')
                ->whereIn('ApplicationNumber', $chunk)
                ->get($select);
            foreach ($rows as $row) {
                $appNo = trim((string) ($row->ApplicationNumber ?? ''));
                if ($appNo === '') {
                    continue;
                }
                foreach (['partner1', 'partner2', 'partner3', 'partner4', 'partner5', 'mar_partner'] as $col) {
                    $raw = trim((string) ($row->{$col} ?? ''));
                    if ($raw === '' || $this->isNoPartner($raw)) {
                        continue;
                    }
                    foreach (preg_split('/[,;]+/', $raw) ?: [] as $part) {
                        $part = trim($part);
                        if ($part === '' || $this->isNoPartner($part) || stripos($part, 'offline') !== false) {
                            continue;
                        }
                        $linksByAppNo[$appNo][] = ['label' => $part, 'url' => ''];
                    }
                }
            }
        }

        try {
            if (Schema::connection('legacy_phase1')->hasTable('partner')) {
                foreach (array_chunk($appNos, 400) as $chunk) {
                    $rows = DB::connection('legacy_phase1')
                        ->table('partner')
                        ->whereIn('ApplicationNumber', $chunk)
                        ->get(['ApplicationNumber', 'partner_name']);
                    foreach ($rows as $row) {
                        $appNo = trim((string) ($row->ApplicationNumber ?? ''));
                        $name = trim((string) ($row->partner_name ?? ''));
                        if ($appNo === '' || $name === '' || $this->isNoPartner($name)) {
                            continue;
                        }
                        $linksByAppNo[$appNo][] = ['label' => $name, 'url' => ''];
                    }
                }
            }
        } catch (\Throwable) {
            // optional partner table
        }
    }

    private function isNoPartner(string $value): bool
    {
        $v = mb_strtolower(trim($value));

        return in_array($v, ['no', 'n', '0', 'false', 'na', 'n/a', '#n/a', '-', 'none', 'nil'], true);
    }

    private function flattenPayloadValue(mixed $value): string
    {
        if (is_array($value)) {
            $parts = [];
            foreach ($value as $item) {
                if (is_scalar($item) && trim((string) $item) !== '') {
                    $parts[] = trim((string) $item);
                }
            }

            return implode(', ', $parts);
        }

        return trim((string) ($value ?? ''));
    }

    private function objStr(object $row, string $key): string
    {
        return isset($row->{$key}) ? trim((string) $row->{$key}) : '';
    }

    /**
     * @return list<string>
     */
    private function yearsForPhase(?string $phaseKey): array
    {
        if ($phaseKey === null) {
            return YearwiseIndicatorsWithJitLakhpatiService::DISPLAY_YEARS;
        }
        foreach ($this->plus->phaseGroups() as $group) {
            if ($group['key'] === $phaseKey) {
                return $group['years'];
            }
        }

        return YearwiseIndicatorsWithJitLakhpatiService::DISPLAY_YEARS;
    }

    private function phaseLabel(?string $phaseKey): ?string
    {
        if ($phaseKey === null) {
            return null;
        }
        foreach ($this->plus->phaseGroups() as $group) {
            if ($group['key'] === $phaseKey) {
                return $group['label'];
            }
        }

        return $phaseKey;
    }
}
