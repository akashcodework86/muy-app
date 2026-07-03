<?php

namespace App\Services\DataCentre;

use App\Models\FiscalYear;
use App\Services\LegacyPhase1\LegacyPhase1DistrictResolver;
use Illuminate\Database\Query\Builder;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

class ProgramDataCentreService
{
    // ─── Phase 2 FY window ────────────────────────────────────────────────
    private const P2_START = '2025-04-02';

    private const P2_END = '2026-04-01';

    /** Cache TTL in seconds (5 minutes). */
    private const CACHE_TTL = 300;

    // ─── Phase 2 district aliases (legacy spelling variants) ─────────────
    private const P2_ALIASES = [
        'Udham Singh Nagar' => ['udham singh nagar', 'udham singh nagr', 'us nagar', 'u s nagar', 'u.s. nagar', 'u s n'],
        'Pauri Garhwal' => ['pauri garhwal', 'pauri'],
        'Tehri Garhwal' => ['tehri garhwal', 'tehri'],
        'Haridwar' => ['haridwar', 'hardwar'],
        'Dehradun' => ['dehradun', 'doon'],
    ];

    private bool $legacyPhase1Ok = false;

    private bool $legacyPhase2Ok = false;

    // ─── Per-request data caches (each loaded by ONE batch query) ─────────
    /** @var array<string,int>|null */
    private ?array $p1Counts = null;

    /** @var array<string,array<string,int>>|null */
    private ?array $p1Gender = null;

    /** @var array<string,array<string,int>>|null */
    private ?array $p1Education = null;

    /** @var array<string,int>|null */
    private ?array $p2Counts = null;

    /** @var array<string,array<string,int>>|null */
    private ?array $p2Gender = null;

    /** @var array<string,array<string,int>>|null */
    private ?array $p2Education = null;

    /** @var array<string,int>|null */
    private ?array $p3Counts = null;

    /** @var array<string,array<string,int>>|null */
    private ?array $p3Gender = null;

    /** @var array<string,array<string,int>>|null */
    private ?array $p3Education = null;

    private ?bool $hasCfaTable = null;

    private string $dataScope = 'all';

    public function __construct()
    {
        $this->legacyPhase1Ok = $this->testConnection('legacy_phase1', 'tblapplication');
        $this->legacyPhase2Ok = $this->testConnection('legacy', 'rbi_applications');
    }

    /** Master build: returns all section data arrays (cached for CACHE_TTL seconds). */
    public function build(string $viewMode = 'all', string $dataScope = 'all'): array
    {
        $viewMode = $viewMode === 'rbiphase3' ? 'rbiphase3' : 'all';
        $dataScope = $dataScope === 'onboarded' ? 'onboarded' : 'all';
        $this->prepareScope($dataScope);
        $cacheKey = 'data_centre_build_v3_'.$viewMode.'_'.$dataScope;

        return Cache::remember($cacheKey, self::CACHE_TTL, function () use ($viewMode, $dataScope) {
            $districts = $this->canonicalDistricts();
            $phase3Only = $viewMode === 'rbiphase3';

            $meta = [
                'generated_at' => now()->timezone('Asia/Kolkata')->format('d M Y, g:i A \I\S\T'),
                'phase1_available' => $this->legacyPhase1Ok,
                'phase2_available' => $this->legacyPhase2Ok,
                'phase3_total' => $this->phase3Count(),
                'phase3_fy' => FiscalYear::phase3Default()?->name ?? 'FY 2026-27',
                'cache_ttl' => self::CACHE_TTL,
                'view_mode' => $viewMode,
                'data_scope' => $dataScope,
            ];

            if ($phase3Only) {
                return [
                    'meta' => $meta,
                    'view_mode' => $viewMode,
                    'data_scope' => $dataScope,
                    'summary' => $this->stateSummaryPhase3Only(),
                    'cfa_by_district' => $this->cfaByDistrictPhase3Only($districts),
                    'gender_state' => $this->genderStatePhase3Only($districts),
                    'gender_district' => $this->genderByDistrictPhase3Only($districts),
                    'education_state' => $this->educationStatePhase3Only($districts),
                    'education_district' => $this->educationByDistrictPhase3Only($districts),
                    'application_analysis' => $this->phase3ApplicationAnalysis(),
                ];
            }

            return [
                'meta' => $meta,
                'view_mode' => $viewMode,
                'data_scope' => $dataScope,
                'summary' => $this->stateSummary($districts),
                'cfa_by_district' => $this->cfaByDistrict($districts),
                'gender_state' => $this->genderState($districts),
                'gender_district' => $this->genderByDistrict($districts),
                'education_state' => $this->educationState($districts),
                'education_district' => $this->educationByDistrict($districts),
                'application_analysis' => null,
            ];
        });
    }

    /** Bust the page-level cache (used by the "Refresh Data" button). */
    public function bustCache(): void
    {
        foreach (['all', 'rbiphase3'] as $viewMode) {
            foreach (['all', 'onboarded'] as $dataScope) {
                Cache::forget('data_centre_build_v3_'.$viewMode.'_'.$dataScope);
            }
        }
    }

    /**
     * @return array{
     *   total: int,
     *   entrepreneur: list<array{label: string, count: int, pct: float}>,
     *   sectors: list<array{sector: string, count: int, pct: float}>,
     *   business_stats: list<array{label: string, count: int, pct: float}>,
     *   accuracy_checks: list<array{label: string, expected: int, actual: int, pass: bool}>
     * }
     */
    public function phase3ApplicationAnalysis(): array
    {
        $total = $this->phase3Count();
        if ($total === 0) {
            return [
                'total' => 0,
                'entrepreneur' => [],
                'sectors' => [],
                'business_stats' => [],
                'accuracy_checks' => [],
            ];
        }

        $pct = fn (int $n): float => round($n * 100 / $total, 1);

        $female = (int) (clone $this->phase3BaseQuery())
            ->whereRaw("JSON_UNQUOTE(JSON_EXTRACT(cs.payload, '$.gender')) = 'Female'")
            ->count();
        $seed = (int) (clone $this->phase3BaseQuery())
            ->whereRaw("JSON_UNQUOTE(JSON_EXTRACT(cs.payload, '$.form_stage')) = 'Seed'")
            ->count();
        $early = (int) (clone $this->phase3BaseQuery())
            ->whereRaw("JSON_UNQUOTE(JSON_EXTRACT(cs.payload, '$.form_stage')) = 'Early'")
            ->count();
        $growth = (int) (clone $this->phase3BaseQuery())
            ->whereRaw("JSON_UNQUOTE(JSON_EXTRACT(cs.payload, '$.form_stage')) = 'Growth'")
            ->count();
        $cbo = (int) (clone $this->phase3BaseQuery())
            ->whereRaw("JSON_UNQUOTE(JSON_EXTRACT(cs.payload, '$.category')) = 'CBO'")
            ->count();

        $sectorRows = (clone $this->phase3BaseQuery())
            ->selectRaw("COALESCE(NULLIF(TRIM(JSON_UNQUOTE(JSON_EXTRACT(cs.payload, '$.business_category'))), ''), 'Not specified') as sector, COUNT(*) as count")
            ->groupBy('sector')
            ->orderByDesc('count')
            ->get();

        $sectors = [];
        foreach ($sectorRows as $row) {
            $count = (int) $row->count;
            $sectors[] = [
                'sector' => (string) $row->sector,
                'count' => $count,
                'pct' => $pct($count),
            ];
        }

        $noCredit = (int) (clone $this->phase3BaseQuery())
            ->whereRaw("JSON_UNQUOTE(JSON_EXTRACT(cs.payload, '$.loan_taken')) = 'No'")
            ->count();
        $unorganized = (int) (clone $this->phase3BaseQuery())
            ->whereRaw("JSON_UNQUOTE(JSON_EXTRACT(cs.payload, '$.is_registered')) = 'No'")
            ->count();
        $turnoverLt5L = (int) (clone $this->phase3BaseQuery())
            ->whereRaw("CAST(REPLACE(JSON_UNQUOTE(JSON_EXTRACT(cs.payload, '$.turnover_last_fy')), ',', '') AS DECIMAL(15,2)) < 500000")
            ->count();
        $turnoverGte5L = (int) (clone $this->phase3BaseQuery())
            ->whereRaw("CAST(REPLACE(JSON_UNQUOTE(JSON_EXTRACT(cs.payload, '$.turnover_last_fy')), ',', '') AS DECIMAL(15,2)) >= 500000")
            ->count();
        $loanYes = (int) (clone $this->phase3BaseQuery())
            ->whereRaw("JSON_UNQUOTE(JSON_EXTRACT(cs.payload, '$.loan_taken')) = 'Yes'")
            ->count();
        $registeredYes = (int) (clone $this->phase3BaseQuery())
            ->whereRaw("JSON_UNQUOTE(JSON_EXTRACT(cs.payload, '$.is_registered')) = 'Yes'")
            ->count();
        $male = (int) (clone $this->phase3BaseQuery())
            ->whereRaw("JSON_UNQUOTE(JSON_EXTRACT(cs.payload, '$.gender')) = 'Male'")
            ->count();
        $genderOther = $total - $female - $male;

        $sectorSum = array_sum(array_column($sectors, 'count'));

        $checks = [
            ['label' => 'Seed + Early + Growth', 'expected' => $total, 'actual' => $seed + $early + $growth],
            ['label' => 'All sectors sum', 'expected' => $total, 'actual' => $sectorSum],
            ['label' => 'Loan No + Yes', 'expected' => $total, 'actual' => $noCredit + $loanYes],
            ['label' => 'Registered No + Yes', 'expected' => $total, 'actual' => $unorganized + $registeredYes],
            ['label' => 'Turnover <5L + ≥5L', 'expected' => $total, 'actual' => $turnoverLt5L + $turnoverGte5L],
            ['label' => 'Female + Male + Other/NA', 'expected' => $total, 'actual' => $female + $male + $genderOther],
        ];

        $accuracyChecks = array_map(static function (array $check): array {
            return [
                'label' => $check['label'],
                'expected' => $check['expected'],
                'actual' => $check['actual'],
                'pass' => $check['expected'] === $check['actual'],
            ];
        }, $checks);

        return [
            'total' => $total,
            'entrepreneur' => [
                ['label' => 'Women Entrepreneurs', 'count' => $female, 'pct' => $pct($female)],
                ['label' => 'Seed-Stage Entrepreneurs', 'count' => $seed, 'pct' => $pct($seed)],
                ['label' => 'Early-Stage Entrepreneurs', 'count' => $early, 'pct' => $pct($early)],
                ['label' => 'Growth-Stage Entrepreneurs', 'count' => $growth, 'pct' => $pct($growth)],
                ['label' => 'CBOs', 'count' => $cbo, 'pct' => $pct($cbo)],
            ],
            'sectors' => $sectors,
            'business_stats' => [
                ['label' => 'No Credit History', 'count' => $noCredit, 'pct' => $pct($noCredit)],
                ['label' => 'Businesses are unorganized', 'count' => $unorganized, 'pct' => $pct($unorganized)],
                ['label' => 'Income < INR 5 Lakh', 'count' => $turnoverLt5L, 'pct' => $pct($turnoverLt5L)],
            ],
            'accuracy_checks' => $accuracyChecks,
        ];
    }

    /** @return list<array{phase: string, source: string, count: int}> */
    public function stateSummaryPhase3Only(): array
    {
        $p3 = $this->phase3Count();
        $fy = FiscalYear::phase3Default()?->name ?? 'FY 2026-27';
        $source = $this->isOnboardedScope()
            ? 'Live MIS – locked onboarding batches (rbiphase3)'
            : 'Live MIS – cfa_submissions (rbiphase3)';

        return [
            ['phase' => 'Phase 3 ('.$fy.')', 'source' => $source, 'count' => $p3],
        ];
    }

    /** @return list<array<string, mixed>> */
    public function cfaByDistrictPhase3Only(array $districts): array
    {
        $rows = [];
        $totP3 = 0;

        foreach ($districts as $name) {
            $p3 = $this->p3DistrictCount($name);
            $rows[] = ['name' => $name, 'p3' => $p3];
            $totP3 += $p3;
        }

        $rows[] = ['name' => 'Total', 'p3' => $totP3, '_is_total' => true];

        return $rows;
    }

    /** @return list<array<string, mixed>> */
    public function genderStatePhase3Only(array $districts): array
    {
        $s3 = $this->genderBuckets('p3', $districts);
        $cats = ['Male', 'Female', 'NA', 'NA/Blank', 'Other'];
        $row = ['phase' => 'Phase 3 (FY 2026–27)'];
        foreach ($cats as $g) {
            $row[$g] = $s3[$g] ?? 0;
        }
        $row['total'] = array_sum(array_intersect_key($row, array_flip($cats)));

        return [$row];
    }

    /** @return list<array<string, mixed>> */
    public function genderByDistrictPhase3Only(array $districts): array
    {
        $cats = ['Male', 'Female', 'NA', 'NA/Blank', 'Other'];
        $rows = [];
        $total = array_fill_keys($cats, 0);

        foreach ($districts as $name) {
            $b = $this->genderBucketsForDistrictPhase($name, 'p3');
            $row = ['name' => $name];
            foreach ($cats as $g) {
                $v = $b[$g] ?? 0;
                $row[$g] = $v;
                $total[$g] += $v;
            }
            $row['total'] = array_sum(array_intersect_key($row, array_flip($cats)));
            $rows[] = $row;
        }

        $totRow = ['name' => 'Total', '_is_total' => true] + $total;
        $totRow['total'] = array_sum(array_intersect_key($total, array_flip($cats)));
        $rows[] = $totRow;

        return $rows;
    }

    /** @return list<array<string, mixed>> */
    public function educationStatePhase3Only(array $districts): array
    {
        $s3 = $this->educationBuckets('p3', $districts);
        $cats = ['10th pass', 'Below 10th', 'Above 10th / Other', 'NA', 'NA/Blank'];
        $row = ['phase' => 'Phase 3 (FY 2026–27)'];
        foreach ($cats as $k) {
            $row[$k] = $s3[$k] ?? 0;
        }
        $row['total'] = array_sum(array_intersect_key($row, array_flip($cats)));

        return [$row];
    }

    /** @return list<array<string, mixed>> */
    public function educationByDistrictPhase3Only(array $districts): array
    {
        $cats = ['10th pass', 'Below 10th', 'Above 10th / Other', 'NA', 'NA/Blank'];
        $rows = [];
        $total = array_fill_keys($cats, 0);

        foreach ($districts as $name) {
            $b = $this->educationBucketsForDistrictPhase($name, 'p3');
            $row = ['name' => $name];
            foreach ($cats as $k) {
                $v = $b[$k] ?? 0;
                $row[$k] = $v;
                $total[$k] += $v;
            }
            $row['total'] = array_sum(array_intersect_key($row, array_flip($cats)));
            $rows[] = $row;
        }

        $totRow = ['name' => 'Total', '_is_total' => true] + $total;
        $totRow['total'] = array_sum(array_intersect_key($total, array_flip($cats)));
        $rows[] = $totRow;

        return $rows;
    }

    // ────────────────────────────────────────────────────────────────────────
    // SECTION 1 — State summary
    // ────────────────────────────────────────────────────────────────────────
    public function stateSummary(array $districts): array
    {
        $p1 = $this->sumP1All($districts);
        $p2 = $this->sumP2All($districts);
        $p3 = $this->phase3Count();

        if ($this->isOnboardedScope()) {
            return [
                ['phase' => 'Phase 1 (FY 2024–25)', 'source' => 'Legacy DB – tblapplication (onboard=yes)', 'count' => $p1],
                ['phase' => 'Phase 2 (FY 2025–26)', 'source' => 'Legacy DB – rbi_onboarded_applicants', 'count' => $p2],
                ['phase' => 'Phase 3 (FY 2026–27)', 'source' => 'Live MIS – locked onboarding batches', 'count' => $p3],
                ['phase' => 'Combined (no duplicate)', 'source' => 'P1 + P2 legacy + P3 MIS onboarded', 'count' => $p1 + $p2 + $p3],
            ];
        }

        return [
            ['phase' => 'Phase 1 (FY 2024–25)', 'source' => 'Legacy DB – tblapplication',    'count' => $p1],
            ['phase' => 'Phase 2 (FY 2025–26)', 'source' => 'Legacy DB – rbi_applications',  'count' => $p2],
            ['phase' => 'Phase 3 (FY 2026–27)', 'source' => 'Live MIS – cfa_submissions',    'count' => $p3],
            ['phase' => 'Combined (no duplicate)', 'source' => 'P1 + P2 + P3 new only',      'count' => $p1 + $p2 + $p3],
        ];
    }

    // ────────────────────────────────────────────────────────────────────────
    // SECTION 2 — CFA by district
    // ────────────────────────────────────────────────────────────────────────
    public function cfaByDistrict(array $districts): array
    {
        $rows = [];
        $totP1 = $totP2 = $totP3 = $totC = 0;

        foreach ($districts as $name) {
            $p1 = $this->p1DistrictCount($name);
            $p2 = $this->p2DistrictCount($name);
            $p3 = $this->p3DistrictCount($name);
            $c = $p1 + $p2 + $p3;

            $rows[] = compact('name', 'p1', 'p2', 'p3', 'c');
            $totP1 += $p1;
            $totP2 += $p2;
            $totP3 += $p3;
            $totC += $c;
        }

        $rows[] = ['name' => 'Total', 'p1' => $totP1, 'p2' => $totP2, 'p3' => $totP3, 'c' => $totC, '_is_total' => true];

        return $rows;
    }

    // ────────────────────────────────────────────────────────────────────────
    // SECTION 3 — Gender (state)
    // ────────────────────────────────────────────────────────────────────────
    public function genderState(array $districts): array
    {
        $s1 = $this->genderBuckets('p1', $districts);
        $s2 = $this->genderBuckets('p2', $districts);
        $s3 = $this->genderBuckets('p3', $districts);

        $cats = ['Male', 'Female', 'NA', 'NA/Blank', 'Other'];
        $rows = [];

        foreach ([
            'Phase 1 (FY 2024–25)' => $s1,
            'Phase 2 (FY 2025–26)' => $s2,
            'Phase 3 (FY 2026–27)' => $s3,
        ] as $label => $b) {
            $row = ['phase' => $label];
            foreach ($cats as $g) {
                $row[$g] = $b[$g] ?? 0;
            }
            $row['total'] = array_sum(array_intersect_key($row, array_flip($cats)));
            $rows[] = $row;
        }

        $combined = ['phase' => 'Combined'];
        foreach ($cats as $g) {
            $combined[$g] = ($s1[$g] ?? 0) + ($s2[$g] ?? 0) + ($s3[$g] ?? 0);
        }
        $combined['total'] = array_sum(array_intersect_key($combined, array_flip($cats)));
        $combined['_is_total'] = true;
        $rows[] = $combined;

        return $rows;
    }

    // ────────────────────────────────────────────────────────────────────────
    // SECTION 4 — Gender by district (combined)
    // ────────────────────────────────────────────────────────────────────────
    public function genderByDistrict(array $districts): array
    {
        $cats = ['Male', 'Female', 'NA', 'NA/Blank', 'Other'];
        $rows = [];
        $total = array_fill_keys($cats, 0);

        foreach ($districts as $name) {
            $b = $this->genderBucketsForDistrict($name);
            $row = ['name' => $name];
            foreach ($cats as $g) {
                $v = $b[$g] ?? 0;
                $row[$g] = $v;
                $total[$g] += $v;
            }
            $row['total'] = array_sum(array_intersect_key($row, array_flip($cats)));
            $rows[] = $row;
        }

        $totRow = ['name' => 'Total', '_is_total' => true] + $total;
        $totRow['total'] = array_sum(array_intersect_key($total, array_flip($cats)));
        $rows[] = $totRow;

        return $rows;
    }

    // ────────────────────────────────────────────────────────────────────────
    // SECTION 5 — Education (state)
    // ────────────────────────────────────────────────────────────────────────
    public function educationState(array $districts): array
    {
        $s1 = $this->educationBuckets('p1', $districts);
        $s2 = $this->educationBuckets('p2', $districts);
        $s3 = $this->educationBuckets('p3', $districts);

        $cats = ['10th pass', 'Below 10th', 'Above 10th / Other', 'NA', 'NA/Blank'];
        $rows = [];

        foreach ([
            'Phase 1 (FY 2024–25)' => $s1,
            'Phase 2 (FY 2025–26)' => $s2,
            'Phase 3 (FY 2026–27)' => $s3,
        ] as $label => $b) {
            $row = ['phase' => $label];
            foreach ($cats as $k) {
                $row[$k] = $b[$k] ?? 0;
            }
            $row['total'] = array_sum(array_intersect_key($row, array_flip($cats)));
            $rows[] = $row;
        }

        $combined = ['phase' => 'Combined'];
        foreach ($cats as $k) {
            $combined[$k] = ($s1[$k] ?? 0) + ($s2[$k] ?? 0) + ($s3[$k] ?? 0);
        }
        $combined['total'] = array_sum(array_intersect_key($combined, array_flip($cats)));
        $combined['_is_total'] = true;
        $rows[] = $combined;

        return $rows;
    }

    // ────────────────────────────────────────────────────────────────────────
    // SECTION 6 — Education by district (combined)
    // ────────────────────────────────────────────────────────────────────────
    public function educationByDistrict(array $districts): array
    {
        $cats = ['10th pass', 'Below 10th', 'Above 10th / Other', 'NA', 'NA/Blank'];
        $rows = [];
        $total = array_fill_keys($cats, 0);

        foreach ($districts as $name) {
            $b = $this->educationBucketsForDistrict($name);
            $row = ['name' => $name];
            foreach ($cats as $k) {
                $v = $b[$k] ?? 0;
                $row[$k] = $v;
                $total[$k] += $v;
            }
            $row['total'] = array_sum(array_intersect_key($row, array_flip($cats)));
            $rows[] = $row;
        }

        $totRow = ['name' => 'Total', '_is_total' => true] + $total;
        $totRow['total'] = array_sum(array_intersect_key($total, array_flip($cats)));
        $rows[] = $totRow;

        return $rows;
    }

    // ────────────────────────────────────────────────────────────────────────
    // CSV export helpers
    // ────────────────────────────────────────────────────────────────────────

    public function csvForSection(string $section, string $dataScope = 'all'): array
    {
        $this->prepareScope($dataScope);
        $districts = $this->canonicalDistricts();

        return match ($section) {
            'summary' => $this->toCsv($this->stateSummary($districts)),
            'cfa-by-district' => $this->toCsv($this->cfaByDistrict($districts)),
            'gender-state' => $this->toCsv($this->genderState($districts)),
            'gender-district' => $this->toCsv($this->genderByDistrict($districts)),
            'education-state' => $this->toCsv($this->educationState($districts)),
            'education-district' => $this->toCsv($this->educationByDistrict($districts)),
            default => [['error' => 'Unknown section']],
        };
    }

    /** @return list<list<scalar>> */
    private function toCsv(array $rows): array
    {
        if (empty($rows)) {
            return [];
        }
        $keys = array_filter(array_keys($rows[0]), fn ($k) => $k !== '_is_total');
        $out = [array_map(fn ($k) => ucwords(str_replace(['_', '/'], [' ', '/'], (string) $k)), $keys)];
        foreach ($rows as $row) {
            $out[] = array_map(fn ($k) => $row[$k] ?? '', $keys);
        }

        return $out;
    }

    // ────────────────────────────────────────────────────────────────────────
    // ═══ BATCH LOADERS — each phase × data-type loads in ONE query ════════
    // ────────────────────────────────────────────────────────────────────────

    // ─── Phase 1 ─────────────────────────────────────────────────────────────

    /** @return array<string,int> norm_district → count */
    private function loadP1Counts(): array
    {
        if ($this->p1Counts !== null) {
            return $this->p1Counts;
        }
        $this->p1Counts = [];
        if (! $this->legacyPhase1Ok) {
            return $this->p1Counts;
        }

        foreach ($this->p1BaseQuery()
            ->selectRaw('LOWER(TRIM(FatherName)) as d, COUNT(*) as c')
            ->groupByRaw('LOWER(TRIM(FatherName))')
            ->get() as $r) {
            $this->p1Counts[(string) $r->d] = (int) $r->c;
        }

        return $this->p1Counts;
    }

    /** @return array<string,array<string,int>> norm_district → [raw_gender → count] */
    private function loadP1Gender(): array
    {
        if ($this->p1Gender !== null) {
            return $this->p1Gender;
        }
        $this->p1Gender = [];
        if (! $this->legacyPhase1Ok) {
            return $this->p1Gender;
        }

        foreach ($this->p1BaseQuery()
            ->selectRaw('LOWER(TRIM(FatherName)) as d, gender, COUNT(*) as c')
            ->groupByRaw('LOWER(TRIM(FatherName)), gender')
            ->get() as $r) {
            $this->p1Gender[(string) $r->d][(string) ($r->gender ?? '')] = (int) $r->c;
        }

        return $this->p1Gender;
    }

    /** @return array<string,array<string,int>> norm_district → [raw_education → count] */
    private function loadP1Education(): array
    {
        if ($this->p1Education !== null) {
            return $this->p1Education;
        }
        $this->p1Education = [];
        if (! $this->legacyPhase1Ok) {
            return $this->p1Education;
        }

        foreach ($this->p1BaseQuery()
            ->selectRaw('LOWER(TRIM(FatherName)) as d, education, COUNT(*) as c')
            ->groupByRaw('LOWER(TRIM(FatherName)), education')
            ->get() as $r) {
            $this->p1Education[(string) $r->d][(string) ($r->education ?? '')] = (int) $r->c;
        }

        return $this->p1Education;
    }

    // ─── Phase 2 ─────────────────────────────────────────────────────────────

    /** @return array<string,int> */
    private function loadP2Counts(): array
    {
        if ($this->p2Counts !== null) {
            return $this->p2Counts;
        }
        $this->p2Counts = [];
        if (! $this->legacyPhase2Ok) {
            return $this->p2Counts;
        }

        if ($this->isOnboardedScope() && ! $this->legacyPhase2OnboardOk()) {
            return $this->p2Counts;
        }

        foreach ($this->p2BaseQuery()
            ->selectRaw('LOWER(TRIM(d.district)) as dist, COUNT(DISTINCT a.id) as c')
            ->groupByRaw('LOWER(TRIM(d.district))')
            ->get() as $r) {
            $this->p2Counts[(string) $r->dist] = (int) $r->c;
        }

        return $this->p2Counts;
    }

    /** @return array<string,array<string,int>> */
    private function loadP2Gender(): array
    {
        if ($this->p2Gender !== null) {
            return $this->p2Gender;
        }
        $this->p2Gender = [];
        if (! $this->legacyPhase2Ok) {
            return $this->p2Gender;
        }

        if ($this->isOnboardedScope() && ! $this->legacyPhase2OnboardOk()) {
            return $this->p2Gender;
        }

        foreach ($this->p2BaseQuery()
            ->selectRaw('LOWER(TRIM(d.district)) as dist, d.gender, COUNT(DISTINCT a.id) as c')
            ->groupByRaw('LOWER(TRIM(d.district)), d.gender')
            ->get() as $r) {
            $this->p2Gender[(string) $r->dist][(string) ($r->gender ?? '')] = (int) $r->c;
        }

        return $this->p2Gender;
    }

    /** @return array<string,array<string,int>> */
    private function loadP2Education(): array
    {
        if ($this->p2Education !== null) {
            return $this->p2Education;
        }
        $this->p2Education = [];
        if (! $this->legacyPhase2Ok) {
            return $this->p2Education;
        }

        if ($this->isOnboardedScope() && ! $this->legacyPhase2OnboardOk()) {
            return $this->p2Education;
        }

        foreach ($this->p2BaseQuery()
            ->selectRaw('LOWER(TRIM(d.district)) as dist, d.education, COUNT(DISTINCT a.id) as c')
            ->groupByRaw('LOWER(TRIM(d.district)), d.education')
            ->get() as $r) {
            $this->p2Education[(string) $r->dist][(string) ($r->education ?? '')] = (int) $r->c;
        }

        return $this->p2Education;
    }

    // ─── Phase 3 ─────────────────────────────────────────────────────────────

    /** @return array<string,int> */
    private function loadP3Counts(): array
    {
        if ($this->p3Counts !== null) {
            return $this->p3Counts;
        }
        $this->p3Counts = [];
        if ($this->isOnboardedScope()) {
            if (! $this->hasOnboardingTables()) {
                return $this->p3Counts;
            }
        } elseif (! $this->hasCfaTable()) {
            return $this->p3Counts;
        }

        foreach (DB::table('cfa_submissions as cs')
            ->join('districts as d', 'd.id', '=', 'cs.district_id')
            ->selectRaw('LOWER(d.name) as dist, COUNT(*) as c')
            ->whereIn('cs.id', $this->phase3BaseQuery()->select('cs.id'))
            ->groupByRaw('LOWER(d.name)')
            ->get() as $r) {
            $this->p3Counts[(string) $r->dist] = (int) $r->c;
        }

        return $this->p3Counts;
    }

    /** @return array<string,array<string,int>> */
    private function loadP3Gender(): array
    {
        if ($this->p3Gender !== null) {
            return $this->p3Gender;
        }
        $this->p3Gender = [];
        if ($this->isOnboardedScope()) {
            if (! $this->hasOnboardingTables()) {
                return $this->p3Gender;
            }
        } elseif (! $this->hasCfaTable()) {
            return $this->p3Gender;
        }

        foreach (DB::table('cfa_submissions as cs')
            ->join('districts as d', 'd.id', '=', 'cs.district_id')
            ->selectRaw("LOWER(d.name) as dist, JSON_UNQUOTE(JSON_EXTRACT(cs.payload, '$.gender')) as gender, COUNT(*) as c")
            ->whereIn('cs.id', $this->phase3BaseQuery()->select('cs.id'))
            ->groupByRaw("LOWER(d.name), JSON_UNQUOTE(JSON_EXTRACT(cs.payload, '$.gender'))")
            ->get() as $r) {
            $g = ($r->gender === 'null' || $r->gender === null) ? '' : (string) $r->gender;
            $this->p3Gender[(string) $r->dist][$g] = (int) $r->c;
        }

        return $this->p3Gender;
    }

    /** @return array<string,array<string,int>> */
    private function loadP3Education(): array
    {
        if ($this->p3Education !== null) {
            return $this->p3Education;
        }
        $this->p3Education = [];
        if ($this->isOnboardedScope()) {
            if (! $this->hasOnboardingTables()) {
                return $this->p3Education;
            }
        } elseif (! $this->hasCfaTable()) {
            return $this->p3Education;
        }

        foreach (DB::table('cfa_submissions as cs')
            ->join('districts as d', 'd.id', '=', 'cs.district_id')
            ->selectRaw("LOWER(d.name) as dist, JSON_UNQUOTE(JSON_EXTRACT(cs.payload, '$.education')) as education, COUNT(*) as c")
            ->whereIn('cs.id', $this->phase3BaseQuery()->select('cs.id'))
            ->groupByRaw("LOWER(d.name), JSON_UNQUOTE(JSON_EXTRACT(cs.payload, '$.education'))")
            ->get() as $r) {
            $e = ($r->education === 'null' || $r->education === null) ? '' : (string) $r->education;
            $this->p3Education[(string) $r->dist][$e] = (int) $r->c;
        }

        return $this->p3Education;
    }

    // ────────────────────────────────────────────────────────────────────────
    // Per-district lookup helpers (use the batch-loaded arrays)
    // ────────────────────────────────────────────────────────────────────────

    private function p1DistrictCount(string $name): int
    {
        $data = $this->loadP1Counts();
        $total = 0;
        foreach (LegacyPhase1DistrictResolver::legacyKeysForDistrict($name) as $norm) {
            $total += $data[$norm] ?? 0;
        }

        return $total;
    }

    private function p2DistrictCount(string $name): int
    {
        $data = $this->loadP2Counts();
        $total = 0;
        foreach ($this->p2Norms($name) as $norm) {
            $total += $data[$norm] ?? 0;
        }

        return $total;
    }

    private function p3DistrictCount(string $name): int
    {
        return $this->loadP3Counts()[mb_strtolower($name)] ?? 0;
    }

    // ─── Gender bucket helpers ────────────────────────────────────────────────

    /** @return array<string,int> */
    private function genderBuckets(string $phase, array $districts): array
    {
        $b = [];
        foreach ($districts as $name) {
            foreach ($this->genderBucketsForDistrictPhase($name, $phase) as $k => $v) {
                $b[$k] = ($b[$k] ?? 0) + $v;
            }
        }

        return $b;
    }

    /** @return array<string,int> */
    private function genderBucketsForDistrict(string $name): array
    {
        $b = [];
        foreach (['p1', 'p2', 'p3'] as $phase) {
            foreach ($this->genderBucketsForDistrictPhase($name, $phase) as $k => $v) {
                $b[$k] = ($b[$k] ?? 0) + $v;
            }
        }

        return $b;
    }

    /** @return array<string,int> */
    private function genderBucketsForDistrictPhase(string $name, string $phase): array
    {
        $b = [];

        if ($phase === 'p1') {
            $data = $this->loadP1Gender();
            foreach (LegacyPhase1DistrictResolver::legacyKeysForDistrict($name) as $norm) {
                foreach ($data[$norm] ?? [] as $raw => $cnt) {
                    $key = $this->normGender((string) $raw);
                    $b[$key] = ($b[$key] ?? 0) + $cnt;
                }
            }
        } elseif ($phase === 'p2') {
            $data = $this->loadP2Gender();
            foreach ($this->p2Norms($name) as $norm) {
                foreach ($data[$norm] ?? [] as $raw => $cnt) {
                    $key = $this->normGender((string) $raw);
                    $b[$key] = ($b[$key] ?? 0) + $cnt;
                }
            }
        } else {
            $data = $this->loadP3Gender();
            foreach ($data[mb_strtolower($name)] ?? [] as $raw => $cnt) {
                $key = $this->normGender((string) $raw);
                $b[$key] = ($b[$key] ?? 0) + $cnt;
            }
        }

        return $b;
    }

    // ─── Education bucket helpers ─────────────────────────────────────────────

    /** @return array<string,int> */
    private function educationBuckets(string $phase, array $districts): array
    {
        $b = [];
        foreach ($districts as $name) {
            foreach ($this->educationBucketsForDistrictPhase($name, $phase) as $k => $v) {
                $b[$k] = ($b[$k] ?? 0) + $v;
            }
        }

        return $b;
    }

    /** @return array<string,int> */
    private function educationBucketsForDistrict(string $name): array
    {
        $b = [];
        foreach (['p1', 'p2', 'p3'] as $phase) {
            foreach ($this->educationBucketsForDistrictPhase($name, $phase) as $k => $v) {
                $b[$k] = ($b[$k] ?? 0) + $v;
            }
        }

        return $b;
    }

    /** @return array<string,int> */
    private function educationBucketsForDistrictPhase(string $name, string $phase): array
    {
        $b = [];

        if ($phase === 'p1') {
            $data = $this->loadP1Education();
            foreach (LegacyPhase1DistrictResolver::legacyKeysForDistrict($name) as $norm) {
                foreach ($data[$norm] ?? [] as $raw => $cnt) {
                    $key = $this->normEducation((string) $raw);
                    $b[$key] = ($b[$key] ?? 0) + $cnt;
                }
            }
        } elseif ($phase === 'p2') {
            $data = $this->loadP2Education();
            foreach ($this->p2Norms($name) as $norm) {
                foreach ($data[$norm] ?? [] as $raw => $cnt) {
                    $key = $this->normEducation((string) $raw);
                    $b[$key] = ($b[$key] ?? 0) + $cnt;
                }
            }
        } else {
            $data = $this->loadP3Education();
            foreach ($data[mb_strtolower($name)] ?? [] as $raw => $cnt) {
                $key = $this->normEducation((string) $raw);
                $b[$key] = ($b[$key] ?? 0) + $cnt;
            }
        }

        return $b;
    }

    // ────────────────────────────────────────────────────────────────────────
    // State total helpers
    // ────────────────────────────────────────────────────────────────────────

    private function sumP1All(array $districts): int
    {
        if (! $this->legacyPhase1Ok) {
            return 0;
        }
        $total = 0;
        foreach ($districts as $name) {
            $total += $this->p1DistrictCount($name);
        }

        return $total;
    }

    private function sumP2All(array $districts): int
    {
        if (! $this->legacyPhase2Ok) {
            return 0;
        }
        $total = 0;
        foreach ($districts as $name) {
            $total += $this->p2DistrictCount($name);
        }

        return $total;
    }

    private function phase3Count(): int
    {
        if ($this->isOnboardedScope()) {
            if (! $this->hasOnboardingTables()) {
                return 0;
            }
        } elseif (! $this->hasCfaTable()) {
            return 0;
        }

        return (int) $this->phase3BaseQuery()->count();
    }

    private function phase3FiscalYearId(): int
    {
        return (int) (FiscalYear::phase3Default()?->id ?? 0);
    }

    private function phase3BaseQuery(): Builder
    {
        $fyId = $this->phase3FiscalYearId();

        if ($this->isOnboardedScope()) {
            if (! $this->hasOnboardingTables()) {
                return DB::table('cfa_submissions as cs')->whereRaw('1 = 0');
            }

            // Match Admin → Onboarded: all locked batch members (no fiscal_year_id filter).
            return DB::table('onboarding_batch_cfa as obc')
                ->join('onboarding_batches as ob', 'ob.id', '=', 'obc.onboarding_batch_id')
                ->join('cfa_submissions as cs', 'cs.id', '=', 'obc.cfa_submission_id')
                ->where('ob.status', 'locked')
                ->whereNotNull('ob.locked_at')
                ->whereNotNull('obc.cfa_submission_id');
        }

        return DB::table('cfa_submissions as cs')
            ->when($fyId > 0, fn (Builder $q) => $q->where('cs.fiscal_year_id', $fyId))
            ->where(function (Builder $q): void {
                $q->whereNull('cs.source')->orWhere('cs.source', '<>', 'legacy_phase2');
            });
    }

    private function prepareScope(string $dataScope): void
    {
        $this->dataScope = $dataScope === 'onboarded' ? 'onboarded' : 'all';
        $this->p1Counts = null;
        $this->p1Gender = null;
        $this->p1Education = null;
        $this->p2Counts = null;
        $this->p2Gender = null;
        $this->p2Education = null;
        $this->p3Counts = null;
        $this->p3Gender = null;
        $this->p3Education = null;
    }

    private function isOnboardedScope(): bool
    {
        return $this->dataScope === 'onboarded';
    }

    private function p1BaseQuery(): Builder
    {
        $query = DB::connection('legacy_phase1')->table('tblapplication');
        if ($this->isOnboardedScope()) {
            $query->whereRaw('LOWER(TRIM(onboard)) = ?', ['yes']);
        }

        return $query;
    }

    private function p2BaseQuery(): Builder
    {
        $query = DB::connection('legacy')
            ->table('rbi_applications as a')
            ->leftJoin('rbi_applicant_details as d', 'd.application_id', '=', 'a.id')
            ->whereNotNull('a.submission_date')
            ->whereRaw('DATE(a.submission_date) BETWEEN ? AND ?', [self::P2_START, self::P2_END]);

        if ($this->isOnboardedScope()) {
            $query->join('rbi_onboarded_applicants as oa', 'oa.application_id', '=', 'a.id')
                ->whereNotNull('oa.status')
                ->where('oa.status', '!=', '');
        }

        return $query;
    }

    private function legacyPhase2OnboardOk(): bool
    {
        if (! $this->legacyPhase2Ok) {
            return false;
        }

        try {
            return DB::connection('legacy')->getSchemaBuilder()->hasTable('rbi_onboarded_applicants');
        } catch (\Throwable) {
            return false;
        }
    }

    private function hasOnboardingTables(): bool
    {
        return $this->hasCfaTable()
            && Schema::hasTable('onboarding_batch_cfa')
            && Schema::hasTable('onboarding_batches');
    }

    // ────────────────────────────────────────────────────────────────────────
    // Normalizers
    // ────────────────────────────────────────────────────────────────────────

    private function normGender(string $raw): string
    {
        $g = trim($raw);
        if ($g === '') {
            return 'NA/Blank';
        }
        $l = strtolower(preg_replace('/\s+/', ' ', $g) ?? $g);
        if (in_array($l, ['male', 'm'], true)) {
            return 'Male';
        }
        if (in_array($l, ['female', 'f'], true)) {
            return 'Female';
        }
        if (in_array($l, ['na', 'n/a', 'not applicable', 'none', 'null'], true)) {
            return 'NA';
        }

        return 'Other';
    }

    private function normEducation(string $raw): string
    {
        $e = trim($raw);
        if ($e === '') {
            return 'NA/Blank';
        }
        $l = strtolower(preg_replace('/\s+/', ' ', $e) ?? $e);
        if (in_array($l, ['na', 'n/a'], true)) {
            return 'NA';
        }
        if (in_array($l, ['10th pass', '10th', '10'], true) || str_contains($l, '10th pass')) {
            return '10th pass';
        }
        if (in_array($l, ['below 8th', '8th pass', '8th', '5th', '9th', 'non matric'], true)
            || str_contains($l, 'below 8')
            || str_contains($l, 'below 10')
        ) {
            return 'Below 10th';
        }

        return 'Above 10th / Other';
    }

    // ────────────────────────────────────────────────────────────────────────
    // Utilities
    // ────────────────────────────────────────────────────────────────────────

    /** @return list<string> */
    private function canonicalDistricts(): array
    {
        return LegacyPhase1DistrictResolver::canonicalDistricts();
    }

    /** @return list<string> */
    private function p2Norms(string $name): array
    {
        $base = [mb_strtolower(trim($name))];

        return array_unique(array_merge($base, self::P2_ALIASES[$name] ?? []));
    }

    private function hasCfaTable(): bool
    {
        if ($this->hasCfaTable === null) {
            $this->hasCfaTable = Schema::hasTable('cfa_submissions');
        }

        return $this->hasCfaTable;
    }

    private function testConnection(string $connection, string $table): bool
    {
        try {
            $db = config("database.connections.{$connection}.database", '');
            if ((string) $db === '') {
                return false;
            }

            return DB::connection($connection)->getSchemaBuilder()->hasTable($table);
        } catch (\Throwable) {
            return false;
        }
    }
}
