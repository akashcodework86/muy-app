<?php

namespace App\Services\DataCentre;

use App\Services\Exports\YearwiseIndicatorWorkbookService;
use Carbon\Carbon;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * Same year-wise indicators as Data Centre, with Phase 1 (FY 2023-24) onboarding
 * including justintime (JIT) + lakhpati_didi rows merged into that year only.
 * All other years keep the real tblapplication-verified onboarding count.
 * justintime service types are still mapped into Udyam / FSSAI / GST / Market /
 * Convergence by their own FY rules.
 */
final class YearwiseIndicatorsWithJitLakhpatiService
{
    private const CACHE_KEY = 'yearwise_indicators_plus_jit_ld_v4';

    private const FALLBACK_FY = '2024-25';

    /** Phase 1 FY — all JIT and Lakhpati Didi rows are counted here only. */
    private const JIT_LD_FY = '2023-24';

    /** Years shown on this page (2020-21 excluded — no material programme activity). */
    public const DISPLAY_YEARS = [
        '2021-22', '2022-23', '2023-24', '2024-25', '2025-26', '2026-27',
    ];

    /**
     * Program phase groupings for the district deck tables — mirrors the
     * "Call for applications" deck (Pilot Phase / Phase 1 / Current Phase 2),
     * with the 2024-25 extension period broken out on its own.
     *
     * @var list<array{key: string, label: string, years: list<string>}>
     */
    private const PHASE_GROUPS = [
        ['key' => 'pilot', 'label' => 'Pilot Phase (2021-22 to 2022-23)', 'years' => ['2021-22', '2022-23']],
        ['key' => 'phase1', 'label' => 'Phase 1 (2023-24)', 'years' => ['2023-24']],
        ['key' => 'extension', 'label' => 'Extension period (2024-25)', 'years' => ['2024-25']],
        ['key' => 'phase2', 'label' => 'Current Phase 2 (2025-26 + 2026-27)', 'years' => ['2025-26', '2026-27']],
    ];

    /**
     * District order + short labels, matching the "Call for applications" deck layout.
     *
     * @var array<string, string> canonical district name => short label
     */
    private const DISTRICT_ORDER = [
        'Almora' => 'Almora',
        'Nainital' => 'Nainital',
        'Pauri Garhwal' => 'Pauri',
        'Champawat' => 'Champawat',
        'Pithoragarh' => 'Pithoragarh',
        'Uttarkashi' => 'Uttarkashi',
        'Dehradun' => 'Dehradun',
        'Bageshwar' => 'Bageshwar',
        'Rudraprayag' => 'Rudraprayag',
        'Udham Singh Nagar' => 'US Nagar',
        'Tehri Garhwal' => 'Tehri',
        'Chamoli' => 'Chamoli',
        'Haridwar' => 'Haridwar',
    ];

    /** @var array<string, string> */
    private const DISTRICT_ALIASES = [
        'almora' => 'Almora',
        'bageshwar' => 'Bageshwar',
        'chamoli' => 'Chamoli',
        'champawat' => 'Champawat',
        'chamapwat' => 'Champawat',
        'dehradun' => 'Dehradun',
        'doon' => 'Dehradun',
        'haridwar' => 'Haridwar',
        'hardwar' => 'Haridwar',
        'nainital' => 'Nainital',
        'pauri' => 'Pauri Garhwal',
        'pauri garhwal' => 'Pauri Garhwal',
        'pithoragarh' => 'Pithoragarh',
        'pithrogarh' => 'Pithoragarh',
        'rudraprayag' => 'Rudraprayag',
        'tehri' => 'Tehri Garhwal',
        'tehri garhwal' => 'Tehri Garhwal',
        'tehri_garhwal' => 'Tehri Garhwal',
        'udham singh nagar' => 'Udham Singh Nagar',
        'us nagar' => 'Udham Singh Nagar',
        'us_nagar' => 'Udham Singh Nagar',
        'u s nagar' => 'Udham Singh Nagar',
        'uttarkashi' => 'Uttarkashi',
    ];

    public function __construct(
        private readonly YearwiseIndicatorWorkbookService $base,
    ) {}

    /**
     * @return array{
     *     generated_at: string,
     *     years: list<string>,
     *     fy_filter: ?string,
     *     district_filter: ?string,
     *     rows: list<array<string, int|string>>,
     *     totals: array<string, int>,
     *     extras: array<string, int>,
     *     note: string
     * }
     */
    public function matrix(?string $fyCode = null, ?string $districtName = null): array
    {
        $fyCode = $fyCode !== null && $fyCode !== '' ? trim($fyCode) : null;
        if ($fyCode !== null && ! in_array($fyCode, self::DISPLAY_YEARS, true)) {
            $fyCode = null;
        }
        $districtName = $districtName !== null && trim($districtName) !== ''
            ? $this->canonicalDistrict($districtName)
            : null;

        $base = $this->base->dataCentreMatrix($fyCode, $districtName);
        $extrasAgg = Cache::remember(self::CACHE_KEY, 300, function (): array {
            return $this->buildExtrasAggregate();
        });

        // FY 2023-24 onboarding = verified tblapplication count + all JIT + Lakhpati Didi.
        // All other years: verified onboarding only. Service columns still fold in JIT types.
        $metrics = ['cfa', 'onboarding', 'udyam', 'artisan_card', 'fssai', 'gst', 'market_linkage', 'convergence'];
        $serviceMetricsWithJit = ['udyam', 'artisan_card', 'fssai', 'gst', 'market_linkage', 'convergence'];

        $years = $fyCode !== null ? [$fyCode] : self::DISPLAY_YEARS;
        $rows = [];
        $totals = array_fill_keys($metrics, 0);
        $extrasShown = array_fill_keys(array_merge(['jit', 'lakhpati_didi'], $serviceMetricsWithJit), 0);

        foreach ($years as $fy) {
            $baseRow = collect($base['rows'])->firstWhere('year', $fy) ?? [];
            $line = ['year' => $fy];

            foreach ($metrics as $key) {
                $baseCount = (int) ($baseRow[$key] ?? 0);
                $extra = 0;

                if ($key === 'onboarding' && $fy === self::JIT_LD_FY) {
                    $jitExtra = $districtName !== null
                        ? (int) ($extrasAgg['by_year_district'][$fy][$districtName]['jit'] ?? 0)
                        : (int) ($extrasAgg['by_year'][$fy]['jit'] ?? 0);
                    $ldExtra = $districtName !== null
                        ? (int) ($extrasAgg['by_year_district'][$fy][$districtName]['lakhpati_didi'] ?? 0)
                        : (int) ($extrasAgg['by_year'][$fy]['lakhpati_didi'] ?? 0);
                    $extrasShown['jit'] += $jitExtra;
                    $extrasShown['lakhpati_didi'] += $ldExtra;
                    $line[$key] = $baseCount + $jitExtra + $ldExtra;
                } elseif (in_array($key, $serviceMetricsWithJit, true)) {
                    $extra = $districtName !== null
                        ? (int) ($extrasAgg['by_year_district'][$fy][$districtName][$key] ?? 0)
                        : (int) ($extrasAgg['by_year'][$fy][$key] ?? 0);
                    $extrasShown[$key] += $extra;
                    $line[$key] = $baseCount + $extra;
                } else {
                    $line[$key] = $baseCount;
                }

                $totals[$key] += $line[$key];
            }

            $rows[] = $line;
        }

        $noteParts = [
            (string) ($base['note'] ?? ''),
            'Onboarding for FY '.self::JIT_LD_FY.' includes verified tblapplication count plus all JIT ('.($extrasShown['jit'] ?? 0).') and Lakhpati Didi ('.($extrasShown['lakhpati_didi'] ?? 0).') rows.',
            'All other years show verified onboarding only.',
            'justintime service types are still mapped into Udyam/FSSAI/GST/Market/Convergence by their own FY rules.',
            'Udyam +'.($extrasShown['udyam'] ?? 0)
                .' · Artisan Card +'.($extrasShown['artisan_card'] ?? 0)
                .' · FSSAI +'.($extrasShown['fssai'] ?? 0)
                .' · GST +'.($extrasShown['gst'] ?? 0)
                .' · Market +'.($extrasShown['market_linkage'] ?? 0)
                .' · Convergence +'.($extrasShown['convergence'] ?? 0).'.',
        ];

        return [
            'generated_at' => now()->timezone('Asia/Kolkata')->format('d M Y, g:i A').' IST',
            'years' => self::DISPLAY_YEARS,
            'fy_filter' => $fyCode,
            'district_filter' => $districtName,
            'rows' => $rows,
            'totals' => $totals,
            'extras' => $extrasShown,
            'note' => implode(' ', array_filter($noteParts)),
        ];
    }

    public function bustCache(): void
    {
        Cache::forget(self::CACHE_KEY);
        Cache::store('file')->forget('yearwise_indicators_plus_records_v1');
        foreach (['v2', 'v3', 'v4', 'v5', 'v6', 'v7'] as $ver) {
            foreach (array_keys(YearwiseIndicatorsPlusRecordsService::METRICS) as $metric) {
                Cache::store('file')->forget('yearwise_indicators_plus_records_'.$ver.'_'.$metric);
            }
        }
        $this->base->bustDataCentreCache();
    }

    /**
     * @return list<array{key: string, label: string, years: list<string>}>
     */
    public function phaseGroups(): array
    {
        return self::PHASE_GROUPS;
    }

    /**
     * @return array<string, string> canonical => short label
     */
    public function districtOrder(): array
    {
        return self::DISTRICT_ORDER;
    }

    /** Resolve short deck labels / aliases to a canonical district name. */
    public function resolveDistrictLabel(string $raw): string
    {
        $trimmed = trim($raw);
        if ($trimmed === '') {
            return '';
        }
        foreach (self::DISTRICT_ORDER as $canonical => $short) {
            if (strcasecmp($trimmed, $short) === 0 || strcasecmp($trimmed, $canonical) === 0) {
                return $canonical;
            }
        }

        return $this->canonicalDistrict($trimmed);
    }

    public function legacyPhase1Available(): bool
    {
        return $this->legacyPhase1Ok();
    }

    /**
     * @return array<string, string> phone => FY
     */
    public function cfaPhoneFyMapPublic(): array
    {
        return $this->cfaPhoneFyMap();
    }

    public function mapJitServiceTypePublic(string $type): ?string
    {
        return $this->mapJitServiceType($type);
    }

    /**
     * District × year breakdown for every metric (CFA, Onboarding, Udyam, FSSAI, GST,
     * Market linkage, Convergence) — replica of the "Call for applications" deck layout,
     * but with every FY as its own column instead of grouped phases, plus the phase-group
     * totals (Pilot Phase / Phase 1 / Extension / Current Phase 2) shown alongside them.
     *
     * @return array{
     *     years: list<string>,
     *     metrics: list<string>,
     *     phase_groups: list<array{key: string, label: string, years: list<string>}>,
     *     tables: array<string, list<array<string, int|string>>>
     * }
     */
    public function allDistrictsMatrix(?string $fyCode = null): array
    {
        $fyCode = $fyCode !== null && $fyCode !== '' ? trim($fyCode) : null;
        if ($fyCode !== null && ! in_array($fyCode, self::DISPLAY_YEARS, true)) {
            $fyCode = null;
        }

        $years = $fyCode !== null ? [$fyCode] : self::DISPLAY_YEARS;
        $metrics = ['cfa', 'onboarding', 'udyam', 'artisan_card', 'fssai', 'gst', 'market_linkage', 'convergence'];
        $phaseGroups = $this->phaseGroupsForYears($years);

        $state = $this->matrix($fyCode, null);
        $stateByYear = [];
        foreach ($state['rows'] as $row) {
            $stateByYear[(string) $row['year']] = $row;
        }

        $tables = [];
        foreach ($metrics as $metric) {
            $tables[$metric] = [];
        }

        $knownSum = [];
        foreach ($metrics as $metric) {
            $knownSum[$metric] = array_fill_keys($years, 0);
        }

        $addPhaseTotals = function (array &$row, array $years, array $phaseGroups): void {
            foreach ($phaseGroups as $group) {
                $sum = 0;
                foreach ($group['years'] as $fy) {
                    $sum += (int) ($row[$fy] ?? 0);
                }
                $row['phase_'.$group['key']] = $sum;
            }
        };

        foreach (self::DISTRICT_ORDER as $canonical => $label) {
            $dm = $this->matrix($fyCode, $canonical);
            $dmByYear = [];
            foreach ($dm['rows'] as $row) {
                $dmByYear[(string) $row['year']] = $row;
            }

            foreach ($metrics as $metric) {
                $row = ['district' => $label];
                $total = 0;
                foreach ($years as $fy) {
                    $val = (int) ($dmByYear[$fy][$metric] ?? 0);
                    $row[$fy] = $val;
                    $total += $val;
                    $knownSum[$metric][$fy] += $val;
                }
                $addPhaseTotals($row, $years, $phaseGroups);
                $row['total'] = $total;
                $tables[$metric][] = $row;
            }
        }

        foreach ($metrics as $metric) {
            $otherRow = ['district' => 'Other / Unmapped'];
            $otherTotal = 0;
            $hasOther = false;
            foreach ($years as $fy) {
                $stateVal = (int) ($stateByYear[$fy][$metric] ?? 0);
                $diff = max(0, $stateVal - ($knownSum[$metric][$fy] ?? 0));
                $otherRow[$fy] = $diff;
                $otherTotal += $diff;
                if ($diff > 0) {
                    $hasOther = true;
                }
            }
            $addPhaseTotals($otherRow, $years, $phaseGroups);
            $otherRow['total'] = $otherTotal;
            if ($hasOther) {
                $tables[$metric][] = $otherRow;
            }

            $grandRow = ['district' => 'Grand Total', '_is_total' => true];
            $grandTotal = 0;
            foreach ($years as $fy) {
                $stateVal = (int) ($stateByYear[$fy][$metric] ?? 0);
                $grandRow[$fy] = $stateVal;
                $grandTotal += $stateVal;
            }
            $addPhaseTotals($grandRow, $years, $phaseGroups);
            $grandRow['total'] = $grandTotal;
            $tables[$metric][] = $grandRow;
        }

        return [
            'years' => $years,
            'metrics' => $metrics,
            'phase_groups' => $phaseGroups,
            'tables' => $tables,
        ];
    }

    /**
     * @param  list<string>  $years
     * @return list<array{key: string, label: string, years: list<string>}>
     */
    private function phaseGroupsForYears(array $years): array
    {
        $out = [];
        foreach (self::PHASE_GROUPS as $group) {
            $matchYears = array_values(array_intersect($group['years'], $years));
            if ($matchYears === []) {
                continue;
            }
            $group['years'] = $matchYears;
            $out[] = $group;
        }

        return $out;
    }

    /**
     * Reference-only figures for the side card: verified onboarding for Phase 1
     * (FY 2023-24) shown alongside — but never merged with — every justintime +
     * lakhpati_didi row (also attributed to FY 2023-24). `total` is purely
     * informational and is NOT what appears in the Onboarding column.
     *
     * @return array{
     *     phase1_fy: string,
     *     onboarded_proper: int,
     *     jit_rows: int,
     *     ld_rows: int,
     *     jit_ld_combined: int,
     *     total: int
     * }
     */
    public function onboardingJitLakhpatiBreakdown(): array
    {
        $base = $this->base->dataCentreMatrix(self::JIT_LD_FY, null);
        $baseRow = collect($base['rows'])->firstWhere('year', self::JIT_LD_FY) ?? [];
        $onboardedProper = (int) ($baseRow['onboarding'] ?? 0);

        $jitRows = 0;
        $ldRows = 0;
        if ($this->legacyPhase1Ok()) {
            if (Schema::connection('legacy_phase1')->hasTable('justintime')) {
                $jitRows = (int) DB::connection('legacy_phase1')->table('justintime')->count();
            }
            if (Schema::connection('legacy_phase1')->hasTable('lakhpati_didi')) {
                $ldRows = (int) DB::connection('legacy_phase1')->table('lakhpati_didi')->count();
            }
        }

        $combined = $jitRows + $ldRows;

        return [
            'phase1_fy' => self::JIT_LD_FY,
            'onboarded_proper' => $onboardedProper,
            'jit_rows' => $jitRows,
            'ld_rows' => $ldRows,
            'jit_ld_combined' => $combined,
            'total' => $onboardedProper + $combined,
        ];
    }

    /**
     * @return array{
     *     by_year: array<string, array<string, int>>,
     *     by_year_district: array<string, array<string, array<string, int>>>
     * }
     */
    private function buildExtrasAggregate(): array
    {
        $metrics = ['jit', 'lakhpati_didi', 'udyam', 'artisan_card', 'fssai', 'gst', 'market_linkage', 'convergence'];
        $byYear = [];
        $byYearDistrict = [];
        foreach (self::DISPLAY_YEARS as $fy) {
            $byYear[$fy] = array_fill_keys($metrics, 0);
            $byYearDistrict[$fy] = [];
        }

        $bump = function (string $fy, string $district, string $metric) use (&$byYear, &$byYearDistrict, $metrics): void {
            if (! isset($byYear[$fy]) || ! in_array($metric, $metrics, true)) {
                return;
            }
            $byYear[$fy][$metric]++;
            if ($district === '') {
                $district = 'Unknown';
            }
            if (! isset($byYearDistrict[$fy][$district])) {
                $byYearDistrict[$fy][$district] = array_fill_keys($metrics, 0);
            }
            $byYearDistrict[$fy][$district][$metric]++;
        };

        $phoneFy = $this->cfaPhoneFyMap();

        if ($this->legacyPhase1Ok() && Schema::connection('legacy_phase1')->hasTable('justintime')) {
            foreach (DB::connection('legacy_phase1')->table('justintime')->get(['contact', 'district', 'type']) as $row) {
                $phone = trim((string) ($row->contact ?? ''));
                $district = $this->canonicalDistrict((string) ($row->district ?? ''));
                $bump(self::JIT_LD_FY, $district, 'jit');

                $serviceFy = $phone !== '' && isset($phoneFy[$phone])
                    ? $phoneFy[$phone]
                    : self::FALLBACK_FY;
                $serviceKey = $this->mapJitServiceType((string) ($row->type ?? ''));
                if ($serviceKey !== null) {
                    $bump($serviceFy, $district, $serviceKey);
                }
            }
        }

        if ($this->legacyPhase1Ok() && Schema::connection('legacy_phase1')->hasTable('lakhpati_didi')) {
            foreach (DB::connection('legacy_phase1')->table('lakhpati_didi')->get(['contact', 'district_name', 'time']) as $row) {
                $district = $this->canonicalDistrict((string) ($row->district_name ?? ''));
                $bump(self::JIT_LD_FY, $district, 'lakhpati_didi');
            }
        }

        return [
            'by_year' => $byYear,
            'by_year_district' => $byYearDistrict,
        ];
    }

    /**
     * @return array<string, string> phone => FY
     */
    private function cfaPhoneFyMap(): array
    {
        if (! $this->legacyPhase1Ok() || ! Schema::connection('legacy_phase1')->hasTable('tblapplication')) {
            return [];
        }

        $map = [];
        $rows = DB::connection('legacy_phase1')
            ->table('tblapplication')
            ->select(['MobileNumber', 'ApplicationDate', 'onboard_date', 'onboarding_date'])
            ->get();

        foreach ($rows as $row) {
            $phone = trim((string) ($row->MobileNumber ?? ''));
            if ($phone === '' || isset($map[$phone])) {
                continue;
            }
            $fy = $this->fyFromDateValue($row->ApplicationDate ?? null)
                ?? $this->fyFromDateValue($row->onboard_date ?? null)
                ?? $this->fyFromDateValue($row->onboarding_date ?? null);
            if ($fy !== null) {
                $map[$phone] = $fy;
            }
        }

        return $map;
    }

    private function mapJitServiceType(string $type): ?string
    {
        $t = mb_strtolower(trim($type));
        $t = str_replace(['_', '-'], ' ', $t);
        $t = (string) preg_replace('/\s+/', ' ', $t);

        if (str_contains($t, 'udyam')) {
            return 'udyam';
        }
        if (str_contains($t, 'artisan')) {
            return 'artisan_card';
        }
        if (str_contains($t, 'fssai')) {
            return 'fssai';
        }
        if (preg_match('/\bgst\b/', $t) === 1) {
            return 'gst';
        }
        if (
            str_contains($t, 'market')
            || str_contains($t, 'mmt')
            || str_contains($t, 'online')
            || str_contains($t, 'hotelzify')
            || str_contains($t, 'e-samudaay')
            || str_contains($t, 'store is live')
            || str_contains($t, 'gi seller')
        ) {
            return 'market_linkage';
        }
        if (
            str_contains($t, 'msy')
            || str_contains($t, 'pmfme')
            || str_contains($t, 'converg')
            || $t === 'msme'
            || str_contains($t, 'msme ')
        ) {
            return 'convergence';
        }

        return null;
    }

    private function fyFromDateValue(mixed $raw): ?string
    {
        $s = trim((string) $raw);
        if ($s === '' || str_starts_with($s, '0000-00-00')) {
            return null;
        }

        try {
            $date = Carbon::parse($s, 'Asia/Kolkata');
        } catch (\Throwable) {
            if (preg_match('/^(\d{1,2})[\/\-](\d{1,2})[\/\-](\d{4})$/', $s, $m) === 1) {
                try {
                    $date = Carbon::create((int) $m[3], (int) $m[2], (int) $m[1], 0, 0, 0, 'Asia/Kolkata');
                } catch (\Throwable) {
                    return null;
                }
            } else {
                return null;
            }
        }

        if ($date === null || $date->year < 2020 || $date->year > 2027) {
            return null;
        }

        $start = $date->month >= 4 ? $date->year : $date->year - 1;
        $fy = sprintf('%d-%02d', $start, ($start + 1) % 100);

        return in_array($fy, self::DISPLAY_YEARS, true) ? $fy : null;
    }

    private function canonicalDistrict(string $raw): string
    {
        $norm = mb_strtolower(trim(str_replace(['_', '-'], ' ', $raw)));
        $norm = trim((string) preg_replace('/\s+/', ' ', $norm));
        if ($norm === '') {
            return '';
        }
        if (isset(self::DISTRICT_ALIASES[$norm])) {
            return self::DISTRICT_ALIASES[$norm];
        }

        return trim(preg_replace('/\s+/', ' ', str_replace(["\r", "\n"], ' ', $raw)) ?? $raw);
    }

    private function legacyPhase1Ok(): bool
    {
        try {
            return Schema::connection('legacy_phase1')->hasTable('tblapplication');
        } catch (\Throwable) {
            return false;
        }
    }
}
