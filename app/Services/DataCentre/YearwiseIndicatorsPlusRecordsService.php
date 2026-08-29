<?php

namespace App\Services\DataCentre;

use App\Models\CfaSubmission;
use App\Models\MarketLinkagePartner;
use App\Models\MarketLinkageSubmission;
use App\Models\ServiceCase;
use App\Models\ServiceCaseAttachment;
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
    public const CACHE_KEY = 'yearwise_indicators_plus_records_v7';

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
        $this->workbook->raiseMemoryLimitPublic('1024M');
        $normalized = $this->normalizeFilters($filters);

        return $this->hydratePageRows($this->filteredRows($normalized), $normalized['metric'], false);
    }

    public function bustCache(): void
    {
        foreach (array_keys(self::METRICS) as $metric) {
            Cache::store('file')->forget($this->metricCacheKey($metric));
        }
        // Legacy mega-cache keys (pre per-metric split).
        Cache::store('file')->forget('yearwise_indicators_plus_records_v1');
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

        return [
            'metric' => $metric,
            'scope' => $scope,
            'year' => $year,
            'phase' => $phase,
            'years' => $years,
            'district' => $district,
            'source' => $source,
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
            $subs = CfaSubmission::query()
                ->whereIn('application_no', array_keys($appNos))
                ->get(['application_no', 'phone', 'payload']);
            foreach ($subs as $sub) {
                $payload = is_array($sub->payload) ? $sub->payload : [];
                $product = trim((string) ($payload['product'] ?? ''));
                if ($product === 'Others' || $product === '') {
                    $other = trim((string) ($payload['other_product'] ?? ''));
                    if ($other !== '') {
                        $product = $other;
                    }
                }
                $byApp[trim((string) $sub->application_no)] = [
                    'phone' => trim((string) ($sub->phone ?: ($payload['phone'] ?? ''))),
                    'block' => trim((string) ($payload['block'] ?? '')),
                    'sector' => trim((string) ($payload['business_category'] ?? ($payload['sector'] ?? ''))),
                    'product' => $product,
                    // Never borrow CFA enterprise registration for convergence /
                    // service metrics — those numbers belong to other services.
                    'service_number' => in_array($metric, ['convergence', 'market_linkage', 'onboarding', 'cfa'], true)
                        ? ''
                        : ServiceRegistrationNumberExtractor::fromPayload(
                            $payload,
                            '',
                            $metric,
                            trim((string) $sub->application_no),
                        ),
                ];
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
                    $existing = $byApp[$appNo] ?? ['phone' => '', 'block' => '', 'sector' => '', 'product' => '', 'service_number' => ''];
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
                        $existing = $byApp[$appNo] ?? ['phone' => '', 'block' => '', 'sector' => '', 'product' => '', 'service_number' => ''];
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
        if ($metric === 'market_linkage') {
            [$linksBySubmissionId, $linksByPartnerId, $linksByAppNo] = $this->loadMarketLinksForRows($rows, $appNos);
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
            if ($metric === 'market_linkage' && $marketLinks === []) {
                $sourceTable = (string) ($row['source_table'] ?? '');
                if ($rid !== '' && ctype_digit($rid) && $sourceTable === 'market_linkage_submissions' && isset($linksBySubmissionId[(int) $rid])) {
                    $marketLinks = $linksBySubmissionId[(int) $rid];
                } elseif ($rid !== '' && ctype_digit($rid) && $sourceTable === 'rbi_service_partners' && isset($linksByPartnerId[(int) $rid])) {
                    $marketLinks = $linksByPartnerId[(int) $rid];
                } elseif ($appNo !== '' && $appNo !== '—' && isset($linksByAppNo[$appNo])) {
                    $marketLinks = $linksByAppNo[$appNo];
                }
            }
            $row['market_links'] = $marketLinks;

            $row['block'] = (string) ($row['block'] ?? '');
            $row['sector'] = (string) ($row['sector'] ?? '');
            $row['product'] = (string) ($row['product'] ?? '');
            $row['hub'] = (string) ($row['hub'] ?? '');

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
            $query = DB::table('market_linkage_partners as mlp')
                ->join('market_linkage_submissions as mls', 'mls.id', '=', 'mlp.market_linkage_submission_id')
                ->whereIn('mls.application_no', array_keys($appNos))
                ->orderBy('mlp.sort_order')
                ->orderBy('mlp.id');
            foreach ($query->limit(2000)->get(['mls.id', 'mls.application_no', 'mlp.partner_name', 'mlp.link_url']) as $p) {
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

        if ($partnerIds !== [] || $appNos !== []) {
            try {
                if (Schema::connection('legacy')->hasTable('rbi_service_partners')) {
                    $q = DB::connection('legacy')->table('rbi_service_partners as sp')
                        ->leftJoin('rbi_applications as a', 'a.id', '=', 'sp.application_id');
                    $q->where(function ($inner) use ($partnerIds, $appNos): void {
                        if ($partnerIds !== []) {
                            $inner->orWhereIn('sp.id', array_keys($partnerIds));
                        }
                        if ($appNos !== []) {
                            $inner->orWhereIn('a.application_no', array_keys($appNos));
                        }
                    });
                    foreach ($q->limit(2000)->get(['sp.id', 'sp.partner_name', 'sp.partner_link', 'a.application_no']) as $p) {
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
            } catch (\Throwable) {
                // Phase-2 DB may be unavailable.
            }
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
