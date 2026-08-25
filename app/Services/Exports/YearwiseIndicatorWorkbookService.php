<?php

namespace App\Services\Exports;

use App\Models\FiscalYear;
use App\Services\Deliverables\ProgramDeliverablesAchievementBreakdownService;
use App\Services\Deliverables\ProgramDeliverablesFilter;
use App\Services\Deliverables\ProgramDeliverablesScope;
use Carbon\Carbon;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * Year-wise CFA / onboarding / Udyam / FSSAI / GST / market linkage / convergence.
 *
 * ukrbiin_rbi = FY 2020-21 … 2024-25 (date-split).
 * rbiphase2   = FY 2025-26 (april/24.php Achieved FY window).
 * muy         = FY 2026-27 (Admin Deliverables breakdown serials).
 */
final class YearwiseIndicatorWorkbookService
{
    /** @var list<string> */
    public const YEARS = [
        '2020-21', '2021-22', '2022-23', '2023-24', '2024-25', '2025-26', '2026-27',
    ];

    /** @var array<string, string> */
    private const DISTRICT_ALIASES = [
        'almora' => 'Almora',
        'bageshwar' => 'Bageshwar',
        'chamoli' => 'Chamoli',
        'champawat' => 'Champawat',
        'dehradun' => 'Dehradun',
        'doon' => 'Dehradun',
        'haridwar' => 'Haridwar',
        'hardwar' => 'Haridwar',
        'nainital' => 'Nainital',
        'pauri' => 'Pauri Garhwal',
        'pauri garhwal' => 'Pauri Garhwal',
        'pithoragarh' => 'Pithoragarh',
        'rudraprayag' => 'Rudraprayag',
        'tehri' => 'Tehri Garhwal',
        'tehri garhwal' => 'Tehri Garhwal',
        'udham singh nagar' => 'Udham Singh Nagar',
        'udham singh nagr' => 'Udham Singh Nagar',
        'us nagar' => 'Udham Singh Nagar',
        'u s nagar' => 'Udham Singh Nagar',
        'u.s. nagar' => 'Udham Singh Nagar',
        'uttarkashi' => 'Uttarkashi',
    ];

    /**
     * @return array<string, mixed>
     */
    public function build(): array
    {
        $p1Apps = $this->loadPhase1Applications();
        $p1ByAppNo = [];
        foreach ($p1Apps as $app) {
            $no = $app['application_no'];
            if ($no !== '') {
                $p1ByAppNo[$no] = $app;
            }
        }

        $cfa = [];
        $onboarding = [];
        $udyam = [];
        $fssai = [];
        $gst = [];
        $market = [];
        $convergence = [];
        $unmapped = [];

        $this->appendPhase1CfaAndOnboarding($p1Apps, $cfa, $onboarding, $unmapped);
        $this->appendPhase1Services($p1ByAppNo, $udyam, $fssai, $gst, $convergence, $market, $unmapped);
        $this->appendPhase1MarketFromPartners($p1Apps, $market);

        // Phase 1 must not spill into 2025-26 / 2026-27 (those come from 24.php + Deliverables).
        foreach ([&$cfa, &$onboarding, &$udyam, &$fssai, &$gst, &$market, &$convergence] as &$list) {
            $list = array_values(array_filter(
                $list,
                static fn (array $row): bool => ! in_array((string) ($row['year'] ?? ''), ['2025-26', '2026-27'], true),
            ));
            unset($list);
        }

        $this->appendPhase2From24Php($cfa, $onboarding, $udyam, $fssai, $gst, $market, $convergence);
        $this->appendPhase3FromDeliverables($cfa, $onboarding, $udyam, $fssai, $gst, $market, $convergence);

        $this->sortByYear($cfa);
        $this->sortByYear($onboarding);
        $this->sortByYear($udyam);
        $this->sortByYear($fssai);
        $this->sortByYear($gst);
        $this->sortByYear($market);
        $this->sortByYear($convergence);

        return [
            'generated_at' => now()->timezone('Asia/Kolkata')->format('d M Y, g:i A').' IST',
            'years' => self::YEARS,
            'summary' => $this->summaryTable($cfa, $onboarding, $udyam, $fssai, $gst, $market, $convergence),
            'cfa' => $cfa,
            'onboarding' => $onboarding,
            'udyam' => $udyam,
            'fssai' => $fssai,
            'gst' => $gst,
            'market_linkage' => $market,
            'convergence' => $convergence,
            'unmapped' => $unmapped,
        ];
    }

    /**
     * Data Centre section: year-wise matrix with optional FY + district filters.
     *
     * @return array{
     *     generated_at: string,
     *     years: list<string>,
     *     fy_filter: ?string,
     *     district_filter: ?string,
     *     rows: list<array{
     *         year: string,
     *         cfa: int,
     *         onboarding: int,
     *         udyam: int,
     *         fssai: int,
     *         gst: int,
     *         market_linkage: int,
     *         convergence: int
     *     }>,
     *     totals: array{
     *         cfa: int,
     *         onboarding: int,
     *         udyam: int,
     *         fssai: int,
     *         gst: int,
     *         market_linkage: int,
     *         convergence: int
     *     },
     *     note: string
     * }
     */
    public function dataCentreMatrix(?string $fyCode = null, ?string $districtName = null): array
    {
        $fyCode = $fyCode !== null && $fyCode !== '' ? trim($fyCode) : null;
        if ($fyCode !== null && ! in_array($fyCode, self::YEARS, true)) {
            $fyCode = null;
        }
        $districtName = $districtName !== null && trim($districtName) !== ''
            ? $this->canonicalDistrict($districtName)
            : null;

        $agg = Cache::remember('yearwise_indicators_agg_v1', 300, function (): array {
            $this->raiseMemoryLimit('512M');

            return $this->buildDataCentreAggregate();
        });

        $years = $fyCode !== null ? [$fyCode] : self::YEARS;
        $metrics = ['cfa', 'onboarding', 'udyam', 'fssai', 'gst', 'market_linkage', 'convergence'];

        $rows = [];
        $totals = array_fill_keys($metrics, 0);

        foreach ($years as $fy) {
            $line = ['year' => $fy];
            foreach ($metrics as $key) {
                if ($districtName !== null) {
                    $count = (int) ($agg['by_year_district'][$fy][$districtName][$key] ?? 0);
                } else {
                    $count = (int) ($agg['by_year'][$fy][$key] ?? 0);
                }
                $line[$key] = $count;
                $totals[$key] += $count;
            }
            $rows[] = $line;
        }

        $noteParts = [
            '2020-21 to 2024-25 from ukrbiin_rbi (blank CFA date uses onboard_date).',
            '2025-26 from rbiphase2 via april/24.php Achieved (FY) window (2025-04-01 to 2026-03-31).',
            '2026-27 from MIS Admin Deliverables (serials 1.1, 2.1, 4.1.1 Udyam, 4.2.2, 4.2.4, 6.3, 8.1).',
        ];
        if ($fyCode !== null) {
            $noteParts[] = 'FY filter: '.$fyCode.'.';
        }
        if ($districtName !== null) {
            $noteParts[] = 'District filter: '.$districtName.'.';
        }

        return [
            'generated_at' => (string) ($agg['generated_at'] ?? now()->timezone('Asia/Kolkata')->format('d M Y, g:i A').' IST'),
            'years' => self::YEARS,
            'fy_filter' => $fyCode,
            'district_filter' => $districtName,
            'rows' => $rows,
            'totals' => $totals,
            'note' => implode(' ', $noteParts),
        ];
    }

    /**
     * Compact year × district counts for Data Centre (avoids caching full row lists).
     *
     * @return array{
     *     generated_at: string,
     *     by_year: array<string, array<string, int>>,
     *     by_year_district: array<string, array<string, array<string, int>>>
     * }
     */
    private function buildDataCentreAggregate(): array
    {
        $payload = $this->build();
        $metrics = [
            'cfa' => 'cfa',
            'onboarding' => 'onboarding',
            'udyam' => 'udyam',
            'fssai' => 'fssai',
            'gst' => 'gst',
            'market_linkage' => 'market_linkage',
            'convergence' => 'convergence',
        ];

        $byYear = [];
        $byYearDistrict = [];
        foreach (self::YEARS as $fy) {
            $byYear[$fy] = array_fill_keys(array_keys($metrics), 0);
            $byYearDistrict[$fy] = [];
        }

        foreach ($metrics as $key => $listKey) {
            $list = is_array($payload[$listKey] ?? null) ? $payload[$listKey] : [];
            foreach ($list as $item) {
                $fy = (string) ($item['year'] ?? '');
                if (! isset($byYear[$fy])) {
                    continue;
                }
                $district = $this->canonicalDistrict((string) ($item['district'] ?? ''));
                if ($district === '') {
                    $district = 'Unknown';
                }
                $byYear[$fy][$key]++;
                if (! isset($byYearDistrict[$fy][$district])) {
                    $byYearDistrict[$fy][$district] = array_fill_keys(array_keys($metrics), 0);
                }
                $byYearDistrict[$fy][$district][$key]++;
            }
        }

        return [
            'generated_at' => (string) ($payload['generated_at'] ?? now()->timezone('Asia/Kolkata')->format('d M Y, g:i A').' IST'),
            'by_year' => $byYear,
            'by_year_district' => $byYearDistrict,
        ];
    }

    public function bustDataCentreCache(): void
    {
        Cache::forget('yearwise_indicators_agg_v1');
        Cache::forget('yearwise_indicators_payload_v2');
    }

    /**
     * Full export payload: summary matrix + detailed row lists (optionally filtered).
     *
     * @return array{
     *     generated_at: string,
     *     years: list<string>,
     *     fy_filter: ?string,
     *     district_filter: ?string,
     *     rows: list<array<string, int|string>>,
     *     totals: array<string, int>,
     *     unknown: array<string, int>,
     *     note: string,
     *     cfa: list<array<string, string>>,
     *     onboarding: list<array<string, string>>,
     *     udyam: list<array<string, string>>,
     *     fssai: list<array<string, string>>,
     *     gst: list<array<string, string>>,
     *     market_linkage: list<array<string, string>>,
     *     convergence: list<array<string, string>>,
     *     unmapped: list<array<string, string>>
     * }
     */
    public function buildExportPayload(?string $fyCode = null, ?string $districtName = null): array
    {
        $fyCode = $fyCode !== null && $fyCode !== '' ? trim($fyCode) : null;
        if ($fyCode !== null && ! in_array($fyCode, self::YEARS, true)) {
            $fyCode = null;
        }
        $districtName = $districtName !== null && trim($districtName) !== ''
            ? $this->canonicalDistrict($districtName)
            : null;

        $this->raiseMemoryLimit('512M');
        $payload = $this->build();

        $metrics = [
            'cfa' => 'cfa',
            'onboarding' => 'onboarding',
            'udyam' => 'udyam',
            'fssai' => 'fssai',
            'gst' => 'gst',
            'market_linkage' => 'market_linkage',
            'convergence' => 'convergence',
        ];

        $filtered = [];
        $unknown = array_fill_keys(array_keys($metrics), 0);
        foreach ($metrics as $key => $listKey) {
            $all = is_array($payload[$listKey] ?? null) ? $payload[$listKey] : [];
            $filtered[$key] = $this->filterExportList($all, $fyCode, $districtName);
            foreach ($all as $item) {
                $fy = (string) ($item['year'] ?? '');
                if ($fy === '' || ! in_array($fy, self::YEARS, true)) {
                    if ($this->matchesExportFilters($item, $fyCode, $districtName)) {
                        $unknown[$key]++;
                    }
                }
            }
        }

        $years = $fyCode !== null ? [$fyCode] : self::YEARS;
        $rows = [];
        $totals = array_fill_keys(array_keys($metrics), 0);
        foreach ($years as $fy) {
            $line = ['year' => $fy];
            foreach ($metrics as $key => $listKey) {
                $count = count(array_filter(
                    $filtered[$key],
                    static fn (array $item): bool => (string) ($item['year'] ?? '') === $fy,
                ));
                $line[$key] = $count;
                $totals[$key] += $count;
            }
            $rows[] = $line;
        }

        $noteParts = [
            '2020-21 to 2024-25 from ukrbiin_rbi (blank CFA date uses onboard_date).',
            '2025-26 from rbiphase2 via april/24.php Achieved (FY) window (2025-04-01 to 2026-03-31).',
            '2026-27 from MIS Admin Deliverables (serials 1.1, 2.1, 4.1.1 Udyam, 4.2.2, 4.2.4, 6.3, 8.1).',
        ];
        if ($fyCode !== null) {
            $noteParts[] = 'FY filter: '.$fyCode.'.';
        }
        if ($districtName !== null) {
            $noteParts[] = 'District filter: '.$districtName.'.';
        }

        return [
            'generated_at' => (string) ($payload['generated_at'] ?? now()->timezone('Asia/Kolkata')->format('d M Y, g:i A').' IST'),
            'years' => self::YEARS,
            'fy_filter' => $fyCode,
            'district_filter' => $districtName,
            'rows' => $rows,
            'totals' => $totals,
            'unknown' => $unknown,
            'note' => implode(' ', $noteParts),
            'cfa' => $filtered['cfa'],
            'onboarding' => $filtered['onboarding'],
            'udyam' => $filtered['udyam'],
            'fssai' => $filtered['fssai'],
            'gst' => $filtered['gst'],
            'market_linkage' => $filtered['market_linkage'],
            'convergence' => $filtered['convergence'],
            'unmapped' => is_array($payload['unmapped'] ?? null) ? $payload['unmapped'] : [],
        ];
    }

    /**
     * @param  list<array<string, mixed>>  $list
     * @return list<array<string, mixed>>
     */
    private function filterExportList(array $list, ?string $fyCode, ?string $districtName): array
    {
        return array_values(array_filter(
            $list,
            fn (array $item): bool => $this->matchesExportFilters($item, $fyCode, $districtName),
        ));
    }

    /**
     * @param  array<string, mixed>  $item
     */
    private function matchesExportFilters(array $item, ?string $fyCode, ?string $districtName): bool
    {
        if ($fyCode !== null && (string) ($item['year'] ?? '') !== $fyCode) {
            return false;
        }
        if ($districtName !== null) {
            $itemDistrict = $this->canonicalDistrict((string) ($item['district'] ?? ''));

            return $itemDistrict === $districtName;
        }

        return true;
    }

    /**
     * @param  list<array<string, mixed>>  $p1Apps
     * @param  list<array<string, string>>  $cfa
     * @param  list<array<string, string>>  $onboarding
     * @param  list<array<string, string>>  $unmapped
     */
    private function appendPhase1CfaAndOnboarding(array $p1Apps, array &$cfa, array &$onboarding, array &$unmapped): void
    {
        foreach ($p1Apps as $app) {
            $resolved = $this->resolveCfaYear(
                $app['application_date_raw'],
                $app['onboard_date_raw'],
                $app['onboarding_date_raw'],
            );
            if ($resolved === null) {
                $unmapped[] = $this->unmappedRow('CFA', 'ukrbiin_rbi.tblapplication', $app);

                continue;
            }
            [$fy, $dateUsed, $dateSource] = $resolved;
            $cfa[] = [
                'year' => $fy,
                'source_db' => 'ukrbiin_rbi',
                'source_table' => 'tblapplication',
                'record_id' => (string) $app['id'],
                'application_no' => $app['application_no'],
                'applicant_name' => $app['name'],
                'district' => $app['district'],
                'phone' => $app['phone'],
                'application_date' => $app['application_date_raw'],
                'onboard_date' => $app['onboard_date_raw'] !== '' ? $app['onboard_date_raw'] : $app['onboarding_date_raw'],
                'date_used' => $dateUsed,
                'date_source' => $dateSource,
                'hub' => $app['hub'],
            ];

            if (! $app['onboarded']) {
                continue;
            }
            $onbResolved = $this->resolveOnboardingYear(
                $app['onboard_date_raw'],
                $app['onboarding_date_raw'],
                $app['application_date_raw'],
            );
            if ($onbResolved === null) {
                $unmapped[] = $this->unmappedRow('Onboarding', 'ukrbiin_rbi.tblapplication', $app);

                continue;
            }
            [$onbFy, $onbDate, $onbSource] = $onbResolved;
            $onboarding[] = [
                'year' => $onbFy,
                'source_db' => 'ukrbiin_rbi',
                'source_table' => 'tblapplication',
                'record_id' => (string) $app['id'],
                'application_no' => $app['application_no'],
                'applicant_name' => $app['name'],
                'district' => $app['district'],
                'phone' => $app['phone'],
                'onboard_flag' => 'yes',
                'batch_name' => '',
                'date_used' => $onbDate,
                'date_source' => $onbSource,
            ];
        }
    }

    /**
     * @param  array<string, array<string, mixed>>  $p1ByAppNo
     * @param  list<array<string, string>>  $udyam
     * @param  list<array<string, string>>  $fssai
     * @param  list<array<string, string>>  $gst
     * @param  list<array<string, string>>  $convergence
     * @param  list<array<string, string>>  $market
     * @param  list<array<string, string>>  $unmapped
     */
    private function appendPhase1Services(
        array $p1ByAppNo,
        array &$udyam,
        array &$fssai,
        array &$gst,
        array &$convergence,
        array &$market,
        array &$unmapped,
    ): void {
        if (! $this->legacyPhase1Ok() || ! Schema::connection('legacy_phase1')->hasTable('services')) {
            return;
        }

        $rows = DB::connection('legacy_phase1')
            ->table('services')
            ->select(['id', 'ApplicationNumber', 'servicename', 'description', 'other', 'service_date'])
            ->orderBy('id')
            ->get();

        foreach ($rows as $row) {
            $appNo = trim((string) ($row->ApplicationNumber ?? ''));
            $app = $p1ByAppNo[$appNo] ?? null;
            $serviceName = $this->clean((string) ($row->servicename ?? ''));
            $description = $this->clean((string) ($row->description ?? ''));
            $other = $this->clean((string) ($row->other ?? ''));
            $blob = mb_strtolower($serviceName.' '.$description.' '.$other);
            $kind = $this->classifyPhase1Service($serviceName, $description, $blob);
            if ($kind === null) {
                continue;
            }

            $fallback = $app['cfa_date_used'] ?? null;
            $resolved = $this->resolveServiceYear((string) ($row->service_date ?? ''), $fallback, $app);
            if ($resolved === null) {
                $unmapped[] = [
                    'metric' => $kind,
                    'source' => 'ukrbiin_rbi.services',
                    'record_id' => (string) ($row->id ?? ''),
                    'application_no' => $appNo,
                    'applicant_name' => (string) ($app['name'] ?? ''),
                    'district' => (string) ($app['district'] ?? ''),
                    'application_date' => (string) ($app['application_date_raw'] ?? ''),
                    'onboard_date' => (string) ($app['onboard_date_raw'] ?? ''),
                    'note' => 'Could not parse service_date='.(string) ($row->service_date ?? ''),
                ];

                continue;
            }
            [$fy, $dateUsed, $dateSource] = $resolved;
            $item = [
                'year' => $fy,
                'source_db' => 'ukrbiin_rbi',
                'source_table' => 'services',
                'record_id' => (string) ($row->id ?? ''),
                'application_no' => $appNo,
                'applicant_name' => (string) ($app['name'] ?? ''),
                'district' => (string) ($app['district'] ?? ''),
                'phone' => (string) ($app['phone'] ?? ''),
                'category' => $serviceName,
                'service_label' => $description !== '' ? $description : $serviceName,
                'detail' => $other,
                'date_used' => $dateUsed,
                'date_source' => $dateSource,
                'status' => '',
            ];
            match ($kind) {
                'Udyam' => $udyam[] = $item,
                'FSSAI' => $fssai[] = $item,
                'GST' => $gst[] = $item,
                'Convergence' => $convergence[] = $item,
                'Market linkage' => $this->rememberMarket($market, $fy, $item, $description),
                default => null,
            };
        }

        if (Schema::connection('legacy_phase1')->hasTable('rbi_services_assigned_jit')) {
            $this->appendPhase1Jit($p1ByAppNo, $udyam, $fssai, $gst, $convergence, $market, $unmapped);
        }
    }

    /**
     * @param  array<string, array<string, mixed>>  $p1ByAppNo
     * @param  list<array<string, string>>  $udyam
     * @param  list<array<string, string>>  $fssai
     * @param  list<array<string, string>>  $gst
     * @param  list<array<string, string>>  $convergence
     * @param  list<array<string, string>>  $market
     * @param  list<array<string, string>>  $unmapped
     */
    private function appendPhase1Jit(
        array $p1ByAppNo,
        array &$udyam,
        array &$fssai,
        array &$gst,
        array &$convergence,
        array &$market,
        array &$unmapped,
    ): void {
        $byId = [];
        foreach ($p1ByAppNo as $app) {
            $byId[(int) $app['id']] = $app;
        }

        $rows = DB::connection('legacy_phase1')
            ->table('rbi_services_assigned_jit')
            ->select(['id', 'legacy_app_id', 'service_name', 'category', 'assigned_date'])
            ->orderBy('id')
            ->get();

        foreach ($rows as $row) {
            $app = $byId[(int) ($row->legacy_app_id ?? 0)] ?? null;
            $serviceName = $this->clean((string) ($row->service_name ?? ''));
            $category = $this->clean((string) ($row->category ?? ''));
            $blob = mb_strtolower($serviceName.' '.$category);
            $kind = $this->classifyPhase1Service($category !== '' ? $category : $serviceName, $serviceName, $blob);
            if ($kind === null) {
                continue;
            }
            $fallback = $app['cfa_date_used'] ?? null;
            $resolved = $this->resolveServiceYear((string) ($row->assigned_date ?? ''), $fallback, $app);
            if ($resolved === null) {
                $unmapped[] = [
                    'metric' => $kind,
                    'source' => 'ukrbiin_rbi.rbi_services_assigned_jit',
                    'record_id' => (string) ($row->id ?? ''),
                    'application_no' => (string) ($app['application_no'] ?? ''),
                    'applicant_name' => (string) ($app['name'] ?? ''),
                    'district' => (string) ($app['district'] ?? ''),
                    'application_date' => (string) ($app['application_date_raw'] ?? ''),
                    'onboard_date' => (string) ($app['onboard_date_raw'] ?? ''),
                    'note' => 'Could not parse assigned_date',
                ];

                continue;
            }
            [$fy, $dateUsed, $dateSource] = $resolved;
            $item = [
                'year' => $fy,
                'source_db' => 'ukrbiin_rbi',
                'source_table' => 'rbi_services_assigned_jit',
                'record_id' => (string) ($row->id ?? ''),
                'application_no' => (string) ($app['application_no'] ?? ''),
                'applicant_name' => (string) ($app['name'] ?? ''),
                'district' => (string) ($app['district'] ?? ''),
                'phone' => (string) ($app['phone'] ?? ''),
                'category' => $category,
                'service_label' => $serviceName,
                'detail' => 'JIT',
                'date_used' => $dateUsed,
                'date_source' => $dateSource,
                'status' => '',
            ];
            match ($kind) {
                'Udyam' => $udyam[] = $item,
                'FSSAI' => $fssai[] = $item,
                'GST' => $gst[] = $item,
                'Convergence' => $convergence[] = $item,
                'Market linkage' => $this->rememberMarket($market, $fy, $item, $serviceName),
                default => null,
            };
        }
    }

    /**
     * Unique incubatees from partner1–5 / mar_partner / partner table.
     *
     * @param  list<array<string, mixed>>  $p1Apps
     * @param  list<array<string, string>>  $market
     */
    private function appendPhase1MarketFromPartners(array $p1Apps, array &$market): void
    {
        $partnersByAppNo = [];
        if ($this->legacyPhase1Ok() && Schema::connection('legacy_phase1')->hasTable('partner')) {
            foreach (DB::connection('legacy_phase1')->table('partner')->get(['ApplicationNumber', 'partner_name']) as $row) {
                $no = trim((string) ($row->ApplicationNumber ?? ''));
                $name = $this->clean((string) ($row->partner_name ?? ''));
                if ($no === '' || $this->isNo($name)) {
                    continue;
                }
                $partnersByAppNo[$no][] = $name;
            }
        }

        foreach ($p1Apps as $app) {
            $names = $this->extractPartnerNames($app);
            foreach ($partnersByAppNo[$app['application_no']] ?? [] as $extra) {
                $names[] = $extra;
            }
            $names = array_values(array_unique(array_filter($names)));
            if ($names === []) {
                continue;
            }
            $fy = (string) ($app['cfa_year'] ?? '');
            $dateUsed = (string) ($app['cfa_date_used'] ?? '');
            if ($fy === '' || ! in_array($fy, self::YEARS, true)) {
                continue;
            }
            $this->rememberMarket($market, $fy, [
                'year' => $fy,
                'source_db' => 'ukrbiin_rbi',
                'source_table' => 'tblapplication + partner',
                'record_id' => (string) $app['id'],
                'application_no' => $app['application_no'],
                'applicant_name' => $app['name'],
                'district' => $app['district'],
                'phone' => $app['phone'],
                'category' => 'Partners',
                'service_label' => 'Market linkage',
                'detail' => implode(', ', $names),
                'date_used' => $dateUsed,
                'date_source' => (string) ($app['cfa_date_source'] ?? 'cfa_year'),
                'status' => '',
            ], implode(', ', $names));
        }
    }

    /**
     * FY 2025-26 — same Achieved (FY) logic as april/24.php (2025-04-01 … 2026-03-31).
     *
     * @param  list<array<string, string>>  $cfa
     * @param  list<array<string, string>>  $onboarding
     * @param  list<array<string, string>>  $udyam
     * @param  list<array<string, string>>  $fssai
     * @param  list<array<string, string>>  $gst
     * @param  list<array<string, string>>  $market
     * @param  list<array<string, string>>  $convergence
     */
    private function appendPhase2From24Php(
        array &$cfa,
        array &$onboarding,
        array &$udyam,
        array &$fssai,
        array &$gst,
        array &$market,
        array &$convergence,
    ): void {
        if (! $this->legacyPhase2Ok()) {
            return;
        }

        $winStart = '2025-04-01';
        $winEnd = '2026-03-31';
        $today = now()->toDateString();
        $legacy = DB::connection('legacy');
        $fy = '2025-26';

        $apps = $legacy->table('rbi_applications as a')
            ->join('rbi_applicant_details as d', 'd.application_id', '=', 'a.id')
            ->whereNotNull('a.submission_date')
            ->whereRaw('DATE(a.submission_date) BETWEEN ? AND ?', [$winStart, $winEnd])
            ->get([
                'a.id',
                'a.application_no',
                'a.submission_date',
                'd.applicant_name',
                'd.phone',
                'd.district',
            ]);

        foreach ($apps as $row) {
            $raw = (string) ($row->submission_date ?? '');
            $resolved = $this->yearFromRaw($raw);
            $cfa[] = [
                'year' => $fy,
                'source_db' => 'rbiphase2 (24.php)',
                'source_table' => 'rbi_applications',
                'record_id' => (string) ($row->id ?? ''),
                'application_no' => trim((string) ($row->application_no ?? '')),
                'applicant_name' => $this->clean((string) ($row->applicant_name ?? '')),
                'district' => $this->canonicalDistrict((string) ($row->district ?? '')),
                'phone' => $this->clean((string) ($row->phone ?? '')),
                'application_date' => $raw,
                'onboard_date' => '',
                'date_used' => $resolved[1] ?? $raw,
                'date_source' => '24.php CFA: DATE(submission_date) 2025-04-01..2026-03-31',
                'hub' => '',
            ];
        }

        if (Schema::connection('legacy')->hasTable('rbi_onboarded_applicants')) {
            $onRows = $legacy->table('rbi_onboarded_applicants as boa')
                ->join('rbi_onboarding_batches as b', 'b.id', '=', 'boa.onboarding_batch_id')
                ->join('rbi_applicant_details as d', 'd.application_id', '=', 'boa.application_id')
                ->leftJoin('rbi_applications as a', 'a.id', '=', 'boa.application_id')
                ->whereRaw('DATE(boa.onboarded_at) BETWEEN ? AND ?', [$winStart, $winEnd])
                ->get([
                    'boa.id',
                    'a.application_no',
                    'd.applicant_name',
                    'd.phone',
                    'd.district',
                    'boa.onboarded_at',
                    'b.onboarding_date',
                ]);

            foreach ($onRows as $row) {
                $raw = (string) ($row->onboarded_at ?? '');
                $resolved = $this->yearFromRaw($raw);
                $onboarding[] = [
                    'year' => $fy,
                    'source_db' => 'rbiphase2 (24.php)',
                    'source_table' => 'rbi_onboarded_applicants',
                    'record_id' => (string) ($row->id ?? ''),
                    'application_no' => trim((string) ($row->application_no ?? '')),
                    'applicant_name' => $this->clean((string) ($row->applicant_name ?? '')),
                    'district' => $this->canonicalDistrict((string) ($row->district ?? '')),
                    'phone' => $this->clean((string) ($row->phone ?? '')),
                    'onboard_flag' => 'onboarded',
                    'batch_name' => '',
                    'date_used' => $resolved[1] ?? $raw,
                    'date_source' => '24.php onboarding: DATE(boa.onboarded_at)',
                ];
            }
        }

        if (Schema::connection('legacy')->hasTable('rbi_services_assigned')) {
            $svcRows = $legacy->table('rbi_services_assigned as sa')
                ->leftJoin('rbi_applicant_details as d', 'd.application_id', '=', 'sa.application_id')
                ->leftJoin('rbi_applications as a', 'a.id', '=', 'sa.application_id')
                ->whereRaw('DATE(COALESCE(sa.assigned_date, sa.doc_date)) BETWEEN ? AND ?', [$winStart, $winEnd])
                ->whereRaw('DATE(COALESCE(sa.assigned_date, sa.doc_date)) <= ?', [$today])
                ->get([
                    'sa.id',
                    'sa.application_id',
                    'sa.service_name',
                    'sa.category',
                    'sa.assigned_date',
                    'sa.doc_date',
                    'a.application_no',
                    'd.applicant_name',
                    'd.phone',
                    'd.district',
                ]);

            foreach ($svcRows as $row) {
                $akey = $this->map24ServiceKey((string) ($row->service_name ?? ''));
                $raw = trim((string) ($row->assigned_date ?? '')) !== ''
                    ? (string) $row->assigned_date
                    : (string) ($row->doc_date ?? '');
                $resolved = $this->yearFromRaw($raw);
                $item = [
                    'year' => $fy,
                    'source_db' => 'rbiphase2 (24.php)',
                    'source_table' => 'rbi_services_assigned',
                    'record_id' => (string) ($row->id ?? ''),
                    'application_no' => trim((string) ($row->application_no ?? '')),
                    'applicant_name' => $this->clean((string) ($row->applicant_name ?? '')),
                    'district' => $this->canonicalDistrict((string) ($row->district ?? '')),
                    'phone' => $this->clean((string) ($row->phone ?? '')),
                    'category' => $this->clean((string) ($row->category ?? '')),
                    'service_label' => $this->clean((string) ($row->service_name ?? '')),
                    'detail' => $akey,
                    'date_used' => $resolved[1] ?? $raw,
                    'date_source' => '24.php: DATE(COALESCE(assigned_date, doc_date))',
                    'status' => '',
                ];

                if ($akey === 'udyam registration') {
                    $udyam[] = $item;
                } elseif ($akey === 'fssai') {
                    $fssai[] = $item;
                } elseif ($akey === 'gst') {
                    $gst[] = $item;
                }

                $cat = mb_strtolower(trim((string) ($row->category ?? '')));
                if ($cat === 'convergence' || $this->is24AtfComponent($akey)) {
                    $convergence[] = $item;
                }
            }
        }

        if (! Schema::connection('legacy')->hasTable('rbi_service_partners')) {
            return;
        }

        $mlRows = $legacy->table('rbi_service_partners as sp')
            ->leftJoin('rbi_services_assigned as sa', 'sa.id', '=', 'sp.service_assigned_id')
            ->leftJoin('rbi_applicant_details as d', 'd.application_id', '=', 'sa.application_id')
            ->leftJoin('rbi_applications as a', 'a.id', '=', 'sa.application_id')
            ->whereRaw('DATE(sp.added_at) BETWEEN ? AND ?', [$winStart, $winEnd])
            ->whereRaw('DATE(sp.added_at) <= ?', [$today])
            ->get([
                'sp.id',
                'a.application_no',
                'd.applicant_name',
                'd.phone',
                'd.district',
                'sp.partner_name',
                'sp.partner_type',
                'sp.added_at',
            ]);

        foreach ($mlRows as $row) {
            $raw = (string) ($row->added_at ?? '');
            $resolved = $this->yearFromRaw($raw);
            $mode = trim((string) ($row->partner_type ?? 'online'));
            $market[] = [
                'year' => $fy,
                'source_db' => 'rbiphase2 (24.php)',
                'source_table' => 'rbi_service_partners',
                'record_id' => (string) ($row->id ?? ''),
                'application_no' => trim((string) ($row->application_no ?? '')),
                'applicant_name' => $this->clean((string) ($row->applicant_name ?? '')),
                'district' => $this->canonicalDistrict((string) ($row->district ?? '')),
                'phone' => $this->clean((string) ($row->phone ?? '')),
                'category' => 'market_link',
                'service_label' => $this->clean((string) ($row->partner_name ?? '')),
                'detail' => $mode !== '' ? $mode : 'online',
                'date_used' => $resolved[1] ?? $raw,
                'date_source' => '24.php market_link: DATE(rbi_service_partners.added_at)',
                'status' => '',
            ];
        }
    }

    /**
     * FY 2026-27 — same records as Admin Deliverables Achieved breakdown.
     *
     * @param  list<array<string, string>>  $cfa
     * @param  list<array<string, string>>  $onboarding
     * @param  list<array<string, string>>  $udyam
     * @param  list<array<string, string>>  $fssai
     * @param  list<array<string, string>>  $gst
     * @param  list<array<string, string>>  $market
     * @param  list<array<string, string>>  $convergence
     */
    private function appendPhase3FromDeliverables(
        array &$cfa,
        array &$onboarding,
        array &$udyam,
        array &$fssai,
        array &$gst,
        array &$market,
        array &$convergence,
    ): void {
        $fyModel = FiscalYear::query()->where('code', '2026-27')->first();
        if (! $fyModel) {
            return;
        }

        $filter = new ProgramDeliverablesFilter((int) $fyModel->id, null, null, null, null, null);
        $scope = new ProgramDeliverablesScope('state_admin', null, null, true);
        $breakdown = app(ProgramDeliverablesAchievementBreakdownService::class);
        $fy = '2026-27';

        $map = [
            '1.1' => 'cfa',
            '2.1' => 'onboarding',
            '4.2.2' => 'fssai',
            '4.2.4' => 'gst',
            '6.3' => 'market',
            '8.1' => 'convergence',
        ];

        foreach ($map as $serial => $bucket) {
            $payload = $breakdown->build($filter, $scope, $serial);
            foreach ($payload['records'] ?? [] as $rec) {
                $raw = (string) ($rec['date'] ?? '');
                $resolved = $this->yearFromRaw($raw);
                $base = [
                    'year' => $fy,
                    'source_db' => 'muy (deliverables '.$serial.')',
                    'source_table' => 'deliverables',
                    'record_id' => '',
                    'application_no' => trim((string) ($rec['reference'] ?? '')),
                    'applicant_name' => $this->clean((string) ($rec['applicant'] ?? '')),
                    'district' => $this->canonicalDistrict((string) ($rec['district'] ?? '')),
                    'phone' => $this->clean((string) ($rec['spoc'] ?? '')),
                    'date_used' => $resolved[1] ?? $raw,
                ];

                if ($bucket === 'cfa') {
                    $cfa[] = $base + [
                        'application_date' => $raw,
                        'onboard_date' => '',
                        'date_source' => 'Deliverables 1.1 CFA',
                        'hub' => '',
                    ];
                } elseif ($bucket === 'onboarding') {
                    $onboarding[] = $base + [
                        'onboard_flag' => (string) ($rec['status'] ?? 'locked'),
                        'batch_name' => '',
                        'date_source' => 'Deliverables 2.1 Onboarding',
                    ];
                } elseif ($bucket === 'fssai') {
                    $fssai[] = $base + [
                        'category' => (string) ($rec['service'] ?? 'FSSAI'),
                        'service_label' => (string) ($rec['service'] ?? 'FSSAI'),
                        'detail' => (string) ($rec['status'] ?? ''),
                        'date_source' => 'Deliverables 4.2.2 FSSAI',
                        'status' => (string) ($rec['status'] ?? ''),
                    ];
                } elseif ($bucket === 'gst') {
                    $gst[] = $base + [
                        'category' => (string) ($rec['service'] ?? 'GST'),
                        'service_label' => (string) ($rec['service'] ?? 'GST'),
                        'detail' => (string) ($rec['status'] ?? ''),
                        'date_source' => 'Deliverables 4.2.4 GST',
                        'status' => (string) ($rec['status'] ?? ''),
                    ];
                } elseif ($bucket === 'market') {
                    $market[] = $base + [
                        'category' => 'market_link',
                        'service_label' => (string) ($rec['service'] ?? ''),
                        'detail' => (string) ($rec['service'] ?? ''),
                        'date_source' => 'Deliverables 6.3 Market linkage',
                        'status' => '',
                    ];
                } else {
                    $convergence[] = $base + [
                        'category' => (string) ($rec['service'] ?? 'Convergence'),
                        'service_label' => (string) ($rec['service'] ?? 'Convergence'),
                        'detail' => (string) ($rec['status'] ?? ''),
                        'date_source' => 'Deliverables 8.1 Schematic Convergence',
                        'status' => (string) ($rec['status'] ?? ''),
                    ];
                }
            }
        }

        $br = $breakdown->build($filter, $scope, '4.1.1');
        foreach ($br['records'] ?? [] as $rec) {
            $svc = mb_strtolower((string) ($rec['service'] ?? ''));
            if (! str_contains($svc, 'udyam')) {
                continue;
            }
            $raw = (string) ($rec['date'] ?? '');
            $resolved = $this->yearFromRaw($raw);
            $udyam[] = [
                'year' => $fy,
                'source_db' => 'muy (deliverables 4.1.1 Udyam)',
                'source_table' => 'deliverables',
                'record_id' => '',
                'application_no' => trim((string) ($rec['reference'] ?? '')),
                'applicant_name' => $this->clean((string) ($rec['applicant'] ?? '')),
                'district' => $this->canonicalDistrict((string) ($rec['district'] ?? '')),
                'phone' => $this->clean((string) ($rec['spoc'] ?? '')),
                'category' => 'business-formalization',
                'service_label' => (string) ($rec['service'] ?? 'Udyam Registration'),
                'detail' => (string) ($rec['status'] ?? ''),
                'date_used' => $resolved[1] ?? $raw,
                'date_source' => 'Deliverables 4.1.1 Udyam bifurcation',
                'status' => (string) ($rec['status'] ?? ''),
            ];
        }
    }

    private function map24ServiceKey(string $serviceName): string
    {
        $s = mb_strtolower(trim($serviceName));
        $s = str_replace(['_', '-', '/', '.', '(', ')'], ' ', $s);
        $s = (string) preg_replace('/\s+/', ' ', $s);

        if (str_contains($s, 'fssai')) {
            return 'fssai';
        }
        if (str_contains($s, 'already registered')) {
            return 'udyam registration';
        }
        if (str_contains($s, 'udyam')) {
            return 'udyam registration';
        }
        if (str_contains($s, 'gst')) {
            return 'gst';
        }
        if (str_contains($s, 'msy nano')) {
            return 'msy nano';
        }
        if ($s === 'msy') {
            return 'msy';
        }
        if ($s === 'pmegp') {
            return 'pmegp';
        }
        if ($s === 'msme' || str_contains($s, 'msme loan')) {
            return 'msme';
        }
        if ($s === 'mudra') {
            return 'mudra';
        }
        if (str_contains($s, 'veer chandra singh garhwali')) {
            return 'veer chandra singh garhwali self empl.';
        }
        if (str_contains($s, 'ddu grah awas')) {
            return 'ddu grah awas yojana (homestay)';
        }
        if ($s === 'support in process' || (str_contains($s, 'support in') && (str_contains($s, 'process') || str_contains($s, 'application')))) {
            return 'support in process';
        }
        if ($s === 'other support service') {
            return 'other support service';
        }
        if (str_contains($s, 'pmfme')) {
            return 'pmfme';
        }
        if (str_contains($s, 'access to finance')) {
            return 'access to finance';
        }

        return $s;
    }

    private function is24AtfComponent(string $activityKey): bool
    {
        $k = mb_strtolower(trim($activityKey));
        $atf = [
            'msy', 'msy nano', 'pmegp', 'msme', 'mudra', 'pmfme',
            'ddu grah awas yojana (homestay)',
            'veer chandra singh garhwali self empl.',
            'other support service',
            'support in process',
            'support in application process',
            'access to finance',
        ];
        if (str_contains($k, 'support in') && (str_contains($k, 'process') || str_contains($k, 'application'))) {
            return true;
        }

        return in_array($k, $atf, true);
    }

    /**
     * @param  list<array<string, string>>  $cfa
     * @param  list<array<string, string>>  $onboarding
     * @param  list<array<string, string>>  $udyam
     * @param  list<array<string, string>>  $fssai
     * @param  list<array<string, string>>  $gst
     * @param  list<array<string, string>>  $market
     * @param  list<array<string, string>>  $convergence
     * @param  list<array<string, string>>  $unmapped
     */
    private function appendPhase2(
        array &$cfa,
        array &$onboarding,
        array &$udyam,
        array &$fssai,
        array &$gst,
        array &$market,
        array &$convergence,
        array &$unmapped,
    ): void {
        if (! $this->legacyPhase2Ok()) {
            return;
        }

        $apps = DB::connection('legacy')
            ->table('rbi_applications as a')
            ->leftJoin('rbi_applicant_details as d', 'd.application_id', '=', 'a.id')
            ->select([
                'a.id',
                'a.application_no',
                'a.submission_date',
                'd.applicant_name',
                'd.phone',
                'd.district',
            ])
            ->orderBy('a.id')
            ->get();

        $p2ById = [];
        foreach ($apps as $row) {
            $resolved = $this->yearFromRaw((string) ($row->submission_date ?? ''));
            $id = (int) $row->id;
            $meta = [
                'id' => $id,
                'application_no' => trim((string) ($row->application_no ?? '')),
                'name' => $this->clean((string) ($row->applicant_name ?? '')),
                'district' => $this->canonicalDistrict((string) ($row->district ?? '')),
                'phone' => $this->clean((string) ($row->phone ?? '')),
                'submission_date' => (string) ($row->submission_date ?? ''),
            ];
            $p2ById[$id] = $meta;
            if ($resolved === null) {
                $unmapped[] = [
                    'metric' => 'CFA',
                    'source' => 'rbiphase2.rbi_applications',
                    'record_id' => (string) $id,
                    'application_no' => $meta['application_no'],
                    'applicant_name' => $meta['name'],
                    'district' => $meta['district'],
                    'application_date' => $meta['submission_date'],
                    'onboard_date' => '',
                    'note' => 'Could not parse submission_date',
                ];

                continue;
            }
            [$fy, $dateUsed] = $resolved;
            $cfa[] = [
                'year' => $fy,
                'source_db' => 'rbiphase2',
                'source_table' => 'rbi_applications',
                'record_id' => (string) $id,
                'application_no' => $meta['application_no'],
                'applicant_name' => $meta['name'],
                'district' => $meta['district'],
                'phone' => $meta['phone'],
                'application_date' => $meta['submission_date'],
                'onboard_date' => '',
                'date_used' => $dateUsed,
                'date_source' => 'submission_date',
                'hub' => '',
            ];
        }

        if (Schema::connection('legacy')->hasTable('rbi_onboarded_applicants')) {
            $onbQuery = DB::connection('legacy')
                ->table('rbi_onboarded_applicants as oa')
                ->leftJoin('rbi_onboarding_batches as ob', 'ob.id', '=', 'oa.onboarding_batch_id')
                ->leftJoin('rbi_applications as a', 'a.id', '=', 'oa.application_id')
                ->leftJoin('rbi_applicant_details as d', 'd.application_id', '=', 'a.id')
                ->whereNotNull('oa.status')
                ->where('oa.status', '!=', '')
                ->select([
                    'oa.id',
                    'oa.application_id',
                    'oa.status',
                    'oa.onboarded_at',
                    'ob.batch_name',
                    'ob.onboarding_date',
                    'a.application_no',
                    'd.applicant_name',
                    'd.phone',
                    'd.district',
                ]);
            foreach ($onbQuery->orderBy('oa.id')->get() as $row) {
                $raw = trim((string) ($row->onboarding_date ?? '')) !== ''
                    ? (string) $row->onboarding_date
                    : (string) ($row->onboarded_at ?? '');
                $source = trim((string) ($row->onboarding_date ?? '')) !== '' ? 'rbi_onboarding_batches.onboarding_date' : 'onboarded_at';
                $resolved = $this->yearFromRaw($raw);
                if ($resolved === null) {
                    $unmapped[] = [
                        'metric' => 'Onboarding',
                        'source' => 'rbiphase2.rbi_onboarded_applicants',
                        'record_id' => (string) ($row->id ?? ''),
                        'application_no' => trim((string) ($row->application_no ?? '')),
                        'applicant_name' => $this->clean((string) ($row->applicant_name ?? '')),
                        'district' => $this->canonicalDistrict((string) ($row->district ?? '')),
                        'application_date' => '',
                        'onboard_date' => $raw,
                        'note' => 'Could not parse onboard date',
                    ];

                    continue;
                }
                [$fy, $dateUsed] = $resolved;
                $onboarding[] = [
                    'year' => $fy,
                    'source_db' => 'rbiphase2',
                    'source_table' => 'rbi_onboarded_applicants',
                    'record_id' => (string) ($row->id ?? ''),
                    'application_no' => trim((string) ($row->application_no ?? '')),
                    'applicant_name' => $this->clean((string) ($row->applicant_name ?? '')),
                    'district' => $this->canonicalDistrict((string) ($row->district ?? '')),
                    'phone' => $this->clean((string) ($row->phone ?? '')),
                    'onboard_flag' => (string) ($row->status ?? ''),
                    'batch_name' => $this->clean((string) ($row->batch_name ?? '')),
                    'date_used' => $dateUsed,
                    'date_source' => $source,
                ];
            }
        }

        if (! Schema::connection('legacy')->hasTable('rbi_services_assigned')) {
            return;
        }

        $svcRows = DB::connection('legacy')
            ->table('rbi_services_assigned')
            ->select(['id', 'application_id', 'category', 'service_name', 'assigned_date', 'partner_name'])
            ->orderBy('id')
            ->get();

        foreach ($svcRows as $row) {
            $category = $this->clean((string) ($row->category ?? ''));
            $name = $this->clean((string) ($row->service_name ?? ''));
            $kind = $this->classifyPhase2Service($category, $name);
            if ($kind === null) {
                continue;
            }
            $app = $p2ById[(int) ($row->application_id ?? 0)] ?? null;
            $fallbackDate = (string) ($app['submission_date'] ?? '');
            $resolved = $this->yearFromRaw((string) ($row->assigned_date ?? ''))
                ?? $this->yearFromRaw($fallbackDate);
            if ($resolved === null) {
                $unmapped[] = [
                    'metric' => $kind,
                    'source' => 'rbiphase2.rbi_services_assigned',
                    'record_id' => (string) ($row->id ?? ''),
                    'application_no' => (string) ($app['application_no'] ?? ''),
                    'applicant_name' => (string) ($app['name'] ?? ''),
                    'district' => (string) ($app['district'] ?? ''),
                    'application_date' => $fallbackDate,
                    'onboard_date' => '',
                    'note' => 'Could not parse assigned_date',
                ];

                continue;
            }
            [$fy, $dateUsed] = $resolved;
            $item = [
                'year' => $fy,
                'source_db' => 'rbiphase2',
                'source_table' => 'rbi_services_assigned',
                'record_id' => (string) ($row->id ?? ''),
                'application_no' => (string) ($app['application_no'] ?? ''),
                'applicant_name' => (string) ($app['name'] ?? ''),
                'district' => (string) ($app['district'] ?? ''),
                'phone' => (string) ($app['phone'] ?? ''),
                'category' => $category,
                'service_label' => $name,
                'detail' => $this->clean((string) ($row->partner_name ?? '')),
                'date_used' => $dateUsed,
                'date_source' => trim((string) ($row->assigned_date ?? '')) !== '' ? 'assigned_date' : 'submission_date',
                'status' => '',
            ];
            match ($kind) {
                'Udyam' => $udyam[] = $item,
                'FSSAI' => $fssai[] = $item,
                'GST' => $gst[] = $item,
                'Convergence' => $convergence[] = $item,
                'Market linkage' => $this->rememberMarket($market, $fy, $item, $name.(trim($item['detail']) !== '' ? ' | '.$item['detail'] : '')),
                default => null,
            };
        }
    }

    /**
     * @param  list<array<string, string>>  $cfa
     * @param  list<array<string, string>>  $onboarding
     * @param  list<array<string, string>>  $udyam
     * @param  list<array<string, string>>  $fssai
     * @param  list<array<string, string>>  $gst
     * @param  list<array<string, string>>  $market
     * @param  list<array<string, string>>  $convergence
     * @param  list<array<string, string>>  $unmapped
     */
    private function appendPhase3(
        array &$cfa,
        array &$onboarding,
        array &$udyam,
        array &$fssai,
        array &$gst,
        array &$market,
        array &$convergence,
        array &$unmapped,
    ): void {
        if (! Schema::hasTable('cfa_submissions')) {
            return;
        }

        $cfaRows = DB::table('cfa_submissions as cs')
            ->leftJoin('districts as d', 'd.id', '=', 'cs.district_id')
            ->leftJoin('fiscal_years as fy', 'fy.id', '=', 'cs.fiscal_year_id')
            ->where(function ($q): void {
                $q->whereNull('cs.source')->orWhereNotIn('cs.source', ['legacy_phase1', 'legacy_phase2']);
            })
            ->select([
                'cs.id',
                'cs.application_no',
                'cs.applicant_name',
                'cs.phone',
                'cs.source',
                'cs.created_at',
                'd.name as district_name',
                'fy.code as fy_code',
            ])
            ->orderBy('cs.id')
            ->get();

        foreach ($cfaRows as $row) {
            $resolved = $this->yearFromRaw((string) ($row->created_at ?? ''));
            if ($resolved === null) {
                $unmapped[] = [
                    'metric' => 'CFA',
                    'source' => 'muy.cfa_submissions',
                    'record_id' => (string) ($row->id ?? ''),
                    'application_no' => (string) ($row->application_no ?? ''),
                    'applicant_name' => $this->clean((string) ($row->applicant_name ?? '')),
                    'district' => $this->clean((string) ($row->district_name ?? '')),
                    'application_date' => (string) ($row->created_at ?? ''),
                    'onboard_date' => '',
                    'note' => 'Could not parse created_at',
                ];

                continue;
            }
            [$fy, $dateUsed] = $resolved;
            $cfa[] = [
                'year' => $fy,
                'source_db' => 'muy',
                'source_table' => 'cfa_submissions',
                'record_id' => (string) ($row->id ?? ''),
                'application_no' => (string) ($row->application_no ?? ''),
                'applicant_name' => $this->clean((string) ($row->applicant_name ?? '')),
                'district' => $this->clean((string) ($row->district_name ?? '')),
                'phone' => $this->clean((string) ($row->phone ?? '')),
                'application_date' => (string) ($row->created_at ?? ''),
                'onboard_date' => '',
                'date_used' => $dateUsed,
                'date_source' => 'created_at (source='.(string) ($row->source ?? '').', fy_tag='.(string) ($row->fy_code ?? '').')',
                'hub' => '',
            ];
        }

        if (Schema::hasTable('onboarding_batch_cfa') && Schema::hasTable('onboarding_batches')) {
            $onbRows = DB::table('onboarding_batch_cfa as obc')
                ->join('onboarding_batches as ob', 'ob.id', '=', 'obc.onboarding_batch_id')
                ->join('cfa_submissions as cs', 'cs.id', '=', 'obc.cfa_submission_id')
                ->leftJoin('districts as d', 'd.id', '=', 'cs.district_id')
                ->where('ob.status', 'locked')
                ->whereNotNull('ob.locked_at')
                ->select([
                    'obc.id',
                    'cs.application_no',
                    'cs.applicant_name',
                    'cs.phone',
                    'd.name as district_name',
                    'ob.name as batch_name',
                    'ob.onboarding_date',
                    'ob.locked_at',
                ])
                ->orderBy('obc.id')
                ->get();
            foreach ($onbRows as $row) {
                $raw = trim((string) ($row->onboarding_date ?? '')) !== ''
                    ? (string) $row->onboarding_date
                    : (string) ($row->locked_at ?? '');
                $source = trim((string) ($row->onboarding_date ?? '')) !== '' ? 'onboarding_date' : 'locked_at';
                $resolved = $this->yearFromRaw($raw);
                if ($resolved === null) {
                    $unmapped[] = [
                        'metric' => 'Onboarding',
                        'source' => 'muy.onboarding_batch_cfa',
                        'record_id' => (string) ($row->id ?? ''),
                        'application_no' => (string) ($row->application_no ?? ''),
                        'applicant_name' => $this->clean((string) ($row->applicant_name ?? '')),
                        'district' => $this->clean((string) ($row->district_name ?? '')),
                        'application_date' => '',
                        'onboard_date' => $raw,
                        'note' => 'Could not parse onboarding_date/locked_at',
                    ];

                    continue;
                }
                [$fy, $dateUsed] = $resolved;
                $onboarding[] = [
                    'year' => $fy,
                    'source_db' => 'muy',
                    'source_table' => 'onboarding_batch_cfa',
                    'record_id' => (string) ($row->id ?? ''),
                    'application_no' => (string) ($row->application_no ?? ''),
                    'applicant_name' => $this->clean((string) ($row->applicant_name ?? '')),
                    'district' => $this->clean((string) ($row->district_name ?? '')),
                    'phone' => $this->clean((string) ($row->phone ?? '')),
                    'onboard_flag' => 'locked',
                    'batch_name' => $this->clean((string) ($row->batch_name ?? '')),
                    'date_used' => $dateUsed,
                    'date_source' => $source,
                ];
            }
        }

        $this->appendPhase3ServiceCases($udyam, $fssai, $gst, $convergence, $market, $unmapped);
        $this->appendPhase3MarketModule($market, $unmapped);
    }

    /**
     * @param  list<array<string, string>>  $udyam
     * @param  list<array<string, string>>  $fssai
     * @param  list<array<string, string>>  $gst
     * @param  list<array<string, string>>  $convergence
     * @param  list<array<string, string>>  $market
     * @param  list<array<string, string>>  $unmapped
     */
    private function appendPhase3ServiceCases(
        array &$udyam,
        array &$fssai,
        array &$gst,
        array &$convergence,
        array &$market,
        array &$unmapped,
    ): void {
        if (! Schema::hasTable('service_cases') || ! Schema::hasTable('services')) {
            return;
        }

        $dateExpr = $this->serviceCaseDateSql();
        $rows = DB::table('service_cases as sc')
            ->join('services as s', 's.id', '=', 'sc.service_id')
            ->leftJoin('service_categories as cat', 'cat.id', '=', 's.service_category_id')
            ->leftJoin('cfa_submissions as cs', 'cs.id', '=', 'sc.cfa_submission_id')
            ->leftJoin('districts as d', 'd.id', '=', 'cs.district_id')
            ->whereIn('sc.status', ['approved', 'completed'])
            ->select([
                'sc.id',
                'sc.status',
                'sc.legacy_application_id',
                's.code as service_code',
                's.name as service_name',
                'cat.slug as category_slug',
                'cs.application_no',
                'cs.applicant_name',
                'cs.phone',
                'd.name as district_name',
                DB::raw($dateExpr.' as achievement_date'),
            ])
            ->orderBy('sc.id')
            ->get();

        foreach ($rows as $row) {
            $kind = $this->classifyPhase3Service(
                (string) ($row->service_code ?? ''),
                (string) ($row->service_name ?? ''),
                (string) ($row->category_slug ?? ''),
            );
            if ($kind === null) {
                continue;
            }
            $resolved = $this->yearFromRaw((string) ($row->achievement_date ?? ''));
            if ($resolved === null) {
                $unmapped[] = [
                    'metric' => $kind,
                    'source' => 'muy.service_cases',
                    'record_id' => (string) ($row->id ?? ''),
                    'application_no' => (string) ($row->application_no ?? ''),
                    'applicant_name' => $this->clean((string) ($row->applicant_name ?? '')),
                    'district' => $this->clean((string) ($row->district_name ?? '')),
                    'application_date' => (string) ($row->achievement_date ?? ''),
                    'onboard_date' => '',
                    'note' => 'Could not parse achievement date',
                ];

                continue;
            }
            [$fy, $dateUsed] = $resolved;
            $item = [
                'year' => $fy,
                'source_db' => 'muy',
                'source_table' => 'service_cases',
                'record_id' => (string) ($row->id ?? ''),
                'application_no' => (string) ($row->application_no ?? ''),
                'applicant_name' => $this->clean((string) ($row->applicant_name ?? '')),
                'district' => $this->clean((string) ($row->district_name ?? '')),
                'phone' => $this->clean((string) ($row->phone ?? '')),
                'category' => (string) ($row->category_slug ?? ''),
                'service_label' => $this->clean((string) ($row->service_name ?? '')),
                'detail' => (string) ($row->service_code ?? ''),
                'date_used' => $dateUsed,
                'date_source' => 'approved_at/coalesce',
                'status' => (string) ($row->status ?? ''),
            ];
            match ($kind) {
                'Udyam' => $udyam[] = $item,
                'FSSAI' => $fssai[] = $item,
                'GST' => $gst[] = $item,
                'Convergence' => $convergence[] = $item,
                'Market linkage' => $this->rememberMarket(
                    $market,
                    $fy,
                    $item,
                    (string) ($row->service_name ?? ''),
                    'c:'.(int) ($row->id ?? 0),
                ),
                default => null,
            };
        }
    }

    /**
     * @param  list<array<string, string>>  $market
     * @param  list<array<string, string>>  $unmapped
     */
    private function appendPhase3MarketModule(array &$market, array &$unmapped): void
    {
        if (! Schema::hasTable('market_linkage_submissions')) {
            return;
        }

        $query = DB::table('market_linkage_submissions as mls')
            ->leftJoin('cfa_submissions as cs', 'cs.id', '=', 'mls.cfa_submission_id')
            ->leftJoin('districts as d', 'd.id', '=', 'mls.district_id');
        if (Schema::hasColumn('market_linkage_submissions', 'status')) {
            $query->where('mls.status', 'approved');
        }
        $rows = $query->select([
            'mls.id',
            'mls.application_no',
            'mls.incubatee_name',
            'mls.cfa_submission_id',
            'mls.legacy_application_id',
            'mls.submitted_at',
            'mls.approved_at',
            'mls.created_at',
            'd.name as district_name',
            'cs.phone',
        ])->orderBy('mls.id')->get();

        $partnersBySubmission = [];
        if (Schema::hasTable('market_linkage_partners')) {
            foreach (DB::table('market_linkage_partners')->orderBy('sort_order')->orderBy('id')->get(['market_linkage_submission_id', 'partner_name', 'linkage_mode', 'linkage_date']) as $p) {
                $sid = (int) $p->market_linkage_submission_id;
                $partnersBySubmission[$sid][] = $p;
            }
        }

        foreach ($rows as $row) {
            $sid = (int) $row->id;
            $partners = $partnersBySubmission[$sid] ?? [];
            $names = [];
            $modes = [];
            $linkDate = '';
            foreach ($partners as $p) {
                $names[] = $this->clean((string) ($p->partner_name ?? ''));
                $modes[] = $this->clean((string) ($p->linkage_mode ?? ''));
                if ($linkDate === '' && trim((string) ($p->linkage_date ?? '')) !== '') {
                    $linkDate = (string) $p->linkage_date;
                }
            }
            $raw = $linkDate !== '' ? $linkDate : (string) ($row->approved_at ?? $row->submitted_at ?? $row->created_at ?? '');
            $resolved = $this->yearFromRaw($raw);
            if ($resolved === null) {
                $unmapped[] = [
                    'metric' => 'Market linkage',
                    'source' => 'muy.market_linkage_submissions',
                    'record_id' => (string) $sid,
                    'application_no' => (string) ($row->application_no ?? ''),
                    'applicant_name' => $this->clean((string) ($row->incubatee_name ?? '')),
                    'district' => $this->clean((string) ($row->district_name ?? '')),
                    'application_date' => $raw,
                    'onboard_date' => '',
                    'note' => 'Could not parse linkage/approved date',
                ];

                continue;
            }
            [$fy, $dateUsed] = $resolved;
            $cfaId = (int) ($row->cfa_submission_id ?? 0);
            $legacyId = (int) ($row->legacy_application_id ?? 0);
            $uniqueKey = $cfaId > 0 ? 'c:'.$cfaId : ($legacyId > 0 ? 'l:'.$legacyId : 's:'.$sid);
            $this->rememberMarket($market, $fy, [
                'year' => $fy,
                'source_db' => 'muy',
                'source_table' => 'market_linkage_submissions',
                'record_id' => (string) $sid,
                'application_no' => (string) ($row->application_no ?? ''),
                'applicant_name' => $this->clean((string) ($row->incubatee_name ?? '')),
                'district' => $this->clean((string) ($row->district_name ?? '')),
                'phone' => $this->clean((string) ($row->phone ?? '')),
                'category' => implode(', ', array_unique(array_filter($modes))),
                'service_label' => 'Market linkage',
                'detail' => implode(', ', array_unique(array_filter($names))),
                'date_used' => $dateUsed,
                'date_source' => $linkDate !== '' ? 'linkage_date' : 'approved_at',
                'status' => 'approved',
            ], implode(', ', array_unique(array_filter($names))), $uniqueKey);
        }
    }

    /**
     * @return list<array<string, mixed>>
     */
    private function loadPhase1Applications(): array
    {
        if (! $this->legacyPhase1Ok()) {
            return [];
        }

        $rows = DB::connection('legacy_phase1')
            ->table('tblapplication')
            ->select([
                'ID', 'ApplicationNumber', 'FullName', 'FatherName', 'MobileNumber', 'hub',
                'ApplicationDate', 'onboard', 'onboard_date', 'onboarding_date',
                'partner1', 'partner2', 'partner3', 'partner4', 'partner5', 'mar_partner',
            ])
            ->orderBy('ID')
            ->get();

        $out = [];
        foreach ($rows as $row) {
            $resolved = $this->resolveCfaYear(
                (string) ($row->ApplicationDate ?? ''),
                (string) ($row->onboard_date ?? ''),
                (string) ($row->onboarding_date ?? ''),
            );
            $out[] = [
                'id' => (int) $row->ID,
                'application_no' => trim((string) ($row->ApplicationNumber ?? '')),
                'name' => $this->clean((string) ($row->FullName ?? '')),
                'district' => $this->canonicalDistrict((string) ($row->FatherName ?? '')),
                'phone' => trim((string) ($row->MobileNumber ?? '')),
                'hub' => $this->clean((string) ($row->hub ?? '')),
                'application_date_raw' => (string) ($row->ApplicationDate ?? ''),
                'onboard_date_raw' => (string) ($row->onboard_date ?? ''),
                'onboarding_date_raw' => (string) ($row->onboarding_date ?? ''),
                'onboarded' => mb_strtolower(trim((string) ($row->onboard ?? ''))) === 'yes',
                'partner1' => (string) ($row->partner1 ?? ''),
                'partner2' => (string) ($row->partner2 ?? ''),
                'partner3' => (string) ($row->partner3 ?? ''),
                'partner4' => (string) ($row->partner4 ?? ''),
                'partner5' => (string) ($row->partner5 ?? ''),
                'mar_partner' => (string) ($row->mar_partner ?? ''),
                'cfa_year' => $resolved[0] ?? '',
                'cfa_date_used' => $resolved[1] ?? '',
                'cfa_date_source' => $resolved[2] ?? '',
            ];
        }

        return $out;
    }

    /**
     * @return array{0: string, 1: string, 2: string}|null
     */
    private function resolveCfaYear(string $applicationDate, string $onboardDate, string $onboardingDate): ?array
    {
        $app = $this->parseDate($applicationDate);
        if ($app !== null) {
            $fy = $this->fyLabel($app);
            if (in_array($fy, self::YEARS, true)) {
                return [$fy, $app->toDateString(), 'ApplicationDate'];
            }
        }

        foreach ([['onboard_date', $onboardDate], ['onboarding_date', $onboardingDate]] as [$source, $raw]) {
            $d = $this->parseDate($raw);
            if ($d === null) {
                continue;
            }
            $fy = $this->fyLabel($d);
            if (in_array($fy, self::YEARS, true)) {
                return [$fy, $d->toDateString(), $source.' (CFA year fallback)'];
            }
        }

        return null;
    }

    /**
     * @return array{0: string, 1: string, 2: string}|null
     */
    private function resolveOnboardingYear(string $onboardDate, string $onboardingDate, string $applicationDate): ?array
    {
        foreach ([['onboard_date', $onboardDate], ['onboarding_date', $onboardingDate], ['ApplicationDate', $applicationDate]] as [$source, $raw]) {
            $d = $this->parseDate($raw);
            if ($d === null) {
                continue;
            }
            $fy = $this->fyLabel($d);
            if (in_array($fy, self::YEARS, true)) {
                return [$fy, $d->toDateString(), $source];
            }
        }

        return null;
    }

    /**
     * @param  array<string, mixed>|null  $app
     * @return array{0: string, 1: string, 2: string}|null
     */
    private function resolveServiceYear(string $serviceDate, ?string $fallbackIso, ?array $app): ?array
    {
        $d = $this->parseDate($serviceDate);
        if ($d !== null) {
            $fy = $this->fyLabel($d);
            if (in_array($fy, self::YEARS, true)) {
                return [$fy, $d->toDateString(), 'service_date'];
            }
        }
        if ($fallbackIso) {
            $d2 = $this->parseDate($fallbackIso);
            if ($d2 !== null) {
                $fy = $this->fyLabel($d2);
                if (in_array($fy, self::YEARS, true)) {
                    return [$fy, $d2->toDateString(), 'applicant CFA year'];
                }
            }
        }
        if (is_array($app)) {
            return $this->resolveCfaYear(
                (string) ($app['application_date_raw'] ?? ''),
                (string) ($app['onboard_date_raw'] ?? ''),
                (string) ($app['onboarding_date_raw'] ?? ''),
            );
        }

        return null;
    }

    /**
     * @return array{0: string, 1: string}|null
     */
    private function yearFromRaw(string $raw): ?array
    {
        $d = $this->parseDate($raw);
        if ($d === null) {
            return null;
        }
        $fy = $this->fyLabel($d);
        if (! in_array($fy, self::YEARS, true)) {
            return null;
        }

        return [$fy, $d->toDateString()];
    }

    private function parseDate(?string $raw): ?Carbon
    {
        $raw = trim((string) $raw);
        if ($raw === '' || str_starts_with($raw, '0000-00-00')) {
            return null;
        }

        if (preg_match('/^(\d{4})-(\d{2})-(\d{2})/', $raw, $m) === 1) {
            $y = (int) $m[1];
            $mo = (int) $m[2];
            $d = (int) $m[3];
            if ($y < 2020 || $y > 2027 || ! checkdate($mo, $d, $y)) {
                return null;
            }

            return Carbon::create($y, $mo, $d, 0, 0, 0, 'Asia/Kolkata');
        }

        if (preg_match('/^(\d{1,2})\/(\d{1,2})\/(\d{4})/', $raw, $m) === 1) {
            $a = (int) $m[1];
            $b = (int) $m[2];
            $y = (int) $m[3];
            // Slash dates: US m/d/Y unless first part > 12 (then d/m/Y).
            if ($a > 12) {
                $d = $a;
                $mo = $b;
            } else {
                $mo = $a;
                $d = $b;
            }
            if ($y < 2020 || $y > 2027 || ! checkdate($mo, $d, $y)) {
                return null;
            }

            return Carbon::create($y, $mo, $d, 0, 0, 0, 'Asia/Kolkata');
        }

        if (preg_match('/^(\d{1,2})-(\d{1,2})-(\d{4})$/', $raw, $m) === 1) {
            $a = (int) $m[1];
            $b = (int) $m[2];
            $y = (int) $m[3];
            // Dash dates: d-m-Y (app convention) unless second part > 12 (then m-d-Y).
            if ($b > 12) {
                $mo = $a;
                $d = $b;
            } else {
                $d = $a;
                $mo = $b;
            }
            if ($y < 2020 || $y > 2027 || ! checkdate($mo, $d, $y)) {
                return null;
            }

            return Carbon::create($y, $mo, $d, 0, 0, 0, 'Asia/Kolkata');
        }

        try {
            $d = Carbon::parse($raw, 'Asia/Kolkata');
            if ($d->year < 2020 || $d->year > 2027) {
                return null;
            }

            return $d->startOfDay();
        } catch (\Throwable) {
            return null;
        }
    }

    private function fyLabel(Carbon $date): string
    {
        $start = $date->month >= 4 ? $date->year : $date->year - 1;

        return sprintf('%d-%02d', $start, ($start + 1) % 100);
    }

    private function classifyPhase1Service(string $serviceName, string $description, string $blob): ?string
    {
        if (str_contains($blob, 'm8') && str_contains($blob, 'converg')) {
            return null;
        }
        if (str_contains($blob, 'business skills training')) {
            return null;
        }
        if (str_contains($blob, 'fssai')) {
            return 'FSSAI';
        }
        if (preg_match('/\bgst\b/', $blob) === 1) {
            return 'GST';
        }
        if (str_contains($blob, 'udyam') || preg_match('/udyam-uk-/i', $description.$serviceName) === 1) {
            return 'Udyam';
        }
        $sn = mb_strtolower($serviceName);
        if ($sn === 'convergence' || str_contains($blob, 'prepared scheme based detailed project report')) {
            return 'Convergence';
        }
        if (str_contains($blob, 'market link') || str_contains($blob, 'offline connect') || $sn === 'forward linkages') {
            return 'Market linkage';
        }

        return null;
    }

    private function classifyPhase2Service(string $category, string $name): ?string
    {
        $c = mb_strtolower($category);
        $n = mb_strtolower($name);
        if (str_contains($n, 'fssai')) {
            return 'FSSAI';
        }
        if (preg_match('/\bgst\b/', $n) === 1) {
            return 'GST';
        }
        if (str_contains($n, 'udyam')) {
            return 'Udyam';
        }
        if ($c === 'convergence') {
            return 'Convergence';
        }
        if ($c === 'forward linkages' && (str_contains($n, 'market') || str_contains($n, 'offline'))) {
            return 'Market linkage';
        }

        return null;
    }

    private function classifyPhase3Service(string $code, string $name, string $slug): ?string
    {
        $code = mb_strtolower($code);
        $name = mb_strtolower($name);
        $slug = mb_strtolower($slug);
        if (str_contains($code, 'udyam') || str_contains($name, 'udyam')) {
            return 'Udyam';
        }
        if (str_contains($code, 'fssai') || str_contains($code, 'f_s_s_a_i') || str_contains($name, 'fssai')) {
            return 'FSSAI';
        }
        if ($code === 'g_s_t' || $code === 'gst' || preg_match('/\bgst\b/', $name) === 1) {
            return 'GST';
        }
        if (str_contains($slug, 'converg')) {
            return 'Convergence';
        }
        if (in_array($code, ['market_link', 'offline_connect', 'incubatees_linked_to_online_offline_market'], true)
            || str_contains($name, 'online/offline market')) {
            return 'Market linkage';
        }

        return null;
    }

    /**
     * Unique incubatee per year for market linkage.
     *
     * @param  list<array<string, string>>  $market
     * @param  array<string, string>  $item
     */
    private function rememberMarket(array &$market, string $fy, array $item, string $partners = '', ?string $uniqueKey = null): void
    {
        $key = $uniqueKey;
        if ($key === null) {
            $appNo = trim((string) ($item['application_no'] ?? ''));
            $rec = trim((string) ($item['record_id'] ?? ''));
            $key = $appNo !== '' ? 'a:'.$appNo : 'r:'.$rec;
        }
        $bucket = $fy.'|'.$key;
        static $seen = [];
        if (isset($seen[$bucket])) {
            $idx = $seen[$bucket];
            $existing = $market[$idx]['detail'] ?? '';
            $merged = trim($existing.', '.$partners, ' ,');
            $market[$idx]['detail'] = $merged;

            return;
        }
        $item['detail'] = $partners !== '' ? $partners : (string) ($item['detail'] ?? '');
        $market[] = $item;
        $seen[$bucket] = array_key_last($market);
    }

    /**
     * @param  array<string, mixed>  $app
     * @return list<string>
     */
    private function extractPartnerNames(array $app): array
    {
        $names = [];
        foreach (['partner1', 'partner2', 'partner3', 'partner4', 'partner5', 'mar_partner'] as $col) {
            $raw = $this->clean((string) ($app[$col] ?? ''));
            if ($raw === '' || $this->isNo($raw)) {
                continue;
            }
            if (stripos($raw, 'offline') !== false && strlen($raw) < 12) {
                continue;
            }
            foreach (preg_split('/[,;]+/', $raw) ?: [] as $part) {
                $part = $this->clean($part);
                if ($part !== '' && ! $this->isNo($part)) {
                    $names[] = $part;
                }
            }
        }

        return $names;
    }

    /**
     * @param  list<array<string, string>>  $cfa
     * @param  list<array<string, string>>  $onboarding
     * @param  list<array<string, string>>  $udyam
     * @param  list<array<string, string>>  $fssai
     * @param  list<array<string, string>>  $gst
     * @param  list<array<string, string>>  $market
     * @param  list<array<string, string>>  $convergence
     * @return list<array<string, int|string>>
     */
    private function summaryTable(
        array $cfa,
        array $onboarding,
        array $udyam,
        array $fssai,
        array $gst,
        array $market,
        array $convergence,
    ): array {
        $countFy = function (array $rows): array {
            $out = array_fill_keys(self::YEARS, 0);
            foreach ($rows as $row) {
                $fy = (string) ($row['year'] ?? '');
                if (isset($out[$fy])) {
                    $out[$fy]++;
                }
            }

            return $out;
        };
        $uniqueFy = function (array $rows): array {
            $seen = [];
            $out = array_fill_keys(self::YEARS, 0);
            foreach ($rows as $row) {
                $fy = (string) ($row['year'] ?? '');
                $app = trim((string) ($row['application_no'] ?? ''));
                $id = (string) ($row['record_id'] ?? '');
                $key = $fy.'|'.($app !== '' ? $app : $id);
                if (isset($seen[$key]) || ! isset($out[$fy])) {
                    continue;
                }
                $seen[$key] = true;
                $out[$fy]++;
            }

            return $out;
        };

        $bags = [
            'CFA' => [$countFy($cfa), $uniqueFy($cfa)],
            'Onboarding' => [$countFy($onboarding), $uniqueFy($onboarding)],
            'Udyam registration' => [$countFy($udyam), $uniqueFy($udyam)],
            'FSSAI' => [$countFy($fssai), $uniqueFy($fssai)],
            'GST' => [$countFy($gst), $uniqueFy($gst)],
            'Market linkage (unique incubatees)' => [$countFy($market), $uniqueFy($market)],
            'Convergence' => [$countFy($convergence), $uniqueFy($convergence)],
        ];

        $rows = [];
        foreach ($bags as $metric => [$counts, $unique]) {
            $line = ['metric' => $metric];
            $total = 0;
            $uniqueTotal = 0;
            foreach (self::YEARS as $fy) {
                $line[$fy] = $counts[$fy];
                $line[$fy.' unique'] = $unique[$fy];
                $total += $counts[$fy];
                $uniqueTotal += $unique[$fy];
            }
            $line['total'] = $total;
            $line['total unique'] = $uniqueTotal;
            $rows[] = $line;
        }

        return $rows;
    }

    /**
     * @param  list<array<string, string>>  $rows
     */
    private function sortByYear(array &$rows): void
    {
        usort($rows, function (array $a, array $b): int {
            $ya = (string) ($a['year'] ?? '');
            $yb = (string) ($b['year'] ?? '');
            if ($ya !== $yb) {
                return strcmp($ya, $yb);
            }

            return strcmp((string) ($a['district'] ?? ''), (string) ($b['district'] ?? ''));
        });
    }

    /**
     * @param  array<string, mixed>  $app
     * @return array<string, string>
     */
    private function unmappedRow(string $metric, string $source, array $app): array
    {
        return [
            'metric' => $metric,
            'source' => $source,
            'record_id' => (string) ($app['id'] ?? ''),
            'application_no' => (string) ($app['application_no'] ?? ''),
            'applicant_name' => (string) ($app['name'] ?? ''),
            'district' => (string) ($app['district'] ?? ''),
            'application_date' => (string) ($app['application_date_raw'] ?? ''),
            'onboard_date' => (string) ($app['onboard_date_raw'] ?? ''),
            'note' => 'No usable ApplicationDate or onboard_date in 2020–2027',
        ];
    }

    private function canonicalDistrict(string $raw): string
    {
        $norm = mb_strtolower(trim(str_replace(['_', '-'], ' ', $raw)));
        $norm = trim(preg_replace('/\s+/', ' ', $norm) ?? $norm);
        if ($norm === '') {
            return '';
        }
        if (isset(self::DISTRICT_ALIASES[$norm])) {
            return self::DISTRICT_ALIASES[$norm];
        }

        return $this->clean($raw);
    }

    private function clean(string $value): string
    {
        $value = str_replace(["\r", "\n"], ' ', $value);

        return trim(preg_replace('/\s+/', ' ', $value) ?? $value);
    }

    private function isNo(string $value): bool
    {
        $v = mb_strtolower(trim($value));

        return in_array($v, ['no', 'n', '0', 'false', 'na', 'n/a', '#n/a', '-'], true);
    }

    private function serviceCaseDateSql(): string
    {
        $parts = [];
        foreach (['approved_at', 'completed_at', 'delivered_on', 'submitted_at', 'created_at'] as $column) {
            if (Schema::hasColumn('service_cases', $column)) {
                $parts[] = 'sc.'.$column;
            }
        }

        return $parts === [] ? 'sc.created_at' : 'COALESCE('.implode(', ', $parts).')';
    }

    private function legacyPhase1Ok(): bool
    {
        try {
            return Schema::connection('legacy_phase1')->hasTable('tblapplication');
        } catch (\Throwable) {
            return false;
        }
    }

    private function legacyPhase2Ok(): bool
    {
        try {
            return Schema::connection('legacy')->hasTable('rbi_applications');
        } catch (\Throwable) {
            return false;
        }
    }

    /**
     * Raise memory_limit for heavy builds. Never lower it afterwards —
     * restoring a smaller limit while usage is higher fatals on live PHP.
     */
    private function raiseMemoryLimit(string $target): void
    {
        $current = ini_get('memory_limit');
        if (! is_string($current) || $current === '' || $current === '-1') {
            return;
        }

        $currentBytes = $this->memoryLimitToBytes($current);
        $targetBytes = $this->memoryLimitToBytes($target);
        if ($currentBytes === null || $targetBytes === null || $targetBytes <= $currentBytes) {
            return;
        }

        @ini_set('memory_limit', $target);
    }

    private function memoryLimitToBytes(string $value): ?int
    {
        $value = trim($value);
        if ($value === '' || $value === '-1') {
            return null;
        }
        if (ctype_digit($value)) {
            return (int) $value;
        }
        if (preg_match('/^(\d+)\s*([KMG])B?$/i', $value, $m) !== 1) {
            return null;
        }
        $n = (int) $m[1];

        return match (strtoupper($m[2])) {
            'K' => $n * 1024,
            'M' => $n * 1024 * 1024,
            'G' => $n * 1024 * 1024 * 1024,
            default => null,
        };
    }
}
