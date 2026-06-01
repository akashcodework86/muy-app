<?php

namespace App\Services\DataCentre;

use App\Services\LegacyPhase1\LegacyPhase1DistrictResolver;
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

    public function __construct()
    {
        $this->legacyPhase1Ok = $this->testConnection('legacy_phase1', 'tblapplication');
        $this->legacyPhase2Ok = $this->testConnection('legacy', 'rbi_applications');
    }

    /** Master build: returns all section data arrays (cached for CACHE_TTL seconds). */
    public function build(): array
    {
        return Cache::remember('data_centre_build_v1', self::CACHE_TTL, function () {
            $districts = $this->canonicalDistricts();

            return [
                'meta' => [
                    'generated_at' => now()->timezone('Asia/Kolkata')->format('d M Y, g:i A \I\S\T'),
                    'phase1_available' => $this->legacyPhase1Ok,
                    'phase2_available' => $this->legacyPhase2Ok,
                    'phase3_total' => $this->phase3Count(),
                    'cache_ttl' => self::CACHE_TTL,
                ],
                'summary' => $this->stateSummary($districts),
                'cfa_by_district' => $this->cfaByDistrict($districts),
                'gender_state' => $this->genderState($districts),
                'gender_district' => $this->genderByDistrict($districts),
                'education_state' => $this->educationState($districts),
                'education_district' => $this->educationByDistrict($districts),
            ];
        });
    }

    /** Bust the page-level cache (used by the "Refresh Data" button). */
    public function bustCache(): void
    {
        Cache::forget('data_centre_build_v1');
    }

    // ────────────────────────────────────────────────────────────────────────
    // SECTION 1 — State summary
    // ────────────────────────────────────────────────────────────────────────
    public function stateSummary(array $districts): array
    {
        $p1 = $this->sumP1All($districts);
        $p2 = $this->sumP2All($districts);
        $p3 = $this->phase3Count();

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

    public function csvForSection(string $section): array
    {
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

        foreach (DB::connection('legacy_phase1')
            ->table('tblapplication')
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

        foreach (DB::connection('legacy_phase1')
            ->table('tblapplication')
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

        foreach (DB::connection('legacy_phase1')
            ->table('tblapplication')
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

        foreach (DB::connection('legacy')
            ->table('rbi_applications as a')
            ->leftJoin('rbi_applicant_details as d', 'd.application_id', '=', 'a.id')
            ->selectRaw('LOWER(TRIM(d.district)) as dist, COUNT(DISTINCT a.id) as c')
            ->whereNotNull('a.submission_date')
            ->whereRaw('DATE(a.submission_date) BETWEEN ? AND ?', [self::P2_START, self::P2_END])
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

        foreach (DB::connection('legacy')
            ->table('rbi_applications as a')
            ->leftJoin('rbi_applicant_details as d', 'd.application_id', '=', 'a.id')
            ->selectRaw('LOWER(TRIM(d.district)) as dist, d.gender, COUNT(DISTINCT a.id) as c')
            ->whereNotNull('a.submission_date')
            ->whereRaw('DATE(a.submission_date) BETWEEN ? AND ?', [self::P2_START, self::P2_END])
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

        foreach (DB::connection('legacy')
            ->table('rbi_applications as a')
            ->leftJoin('rbi_applicant_details as d', 'd.application_id', '=', 'a.id')
            ->selectRaw('LOWER(TRIM(d.district)) as dist, d.education, COUNT(DISTINCT a.id) as c')
            ->whereNotNull('a.submission_date')
            ->whereRaw('DATE(a.submission_date) BETWEEN ? AND ?', [self::P2_START, self::P2_END])
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
        if (! $this->hasCfaTable()) {
            return $this->p3Counts;
        }

        foreach (DB::table('cfa_submissions as cs')
            ->join('districts as d', 'd.id', '=', 'cs.district_id')
            ->selectRaw('LOWER(d.name) as dist, COUNT(*) as c')
            ->where(function ($q): void {
                $q->whereNull('cs.source')->orWhere('cs.source', '<>', 'legacy_phase2');
            })
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
        if (! $this->hasCfaTable()) {
            return $this->p3Gender;
        }

        foreach (DB::table('cfa_submissions as cs')
            ->join('districts as d', 'd.id', '=', 'cs.district_id')
            ->selectRaw("LOWER(d.name) as dist, JSON_UNQUOTE(JSON_EXTRACT(cs.payload, '$.gender')) as gender, COUNT(*) as c")
            ->where(function ($q): void {
                $q->whereNull('cs.source')->orWhere('cs.source', '<>', 'legacy_phase2');
            })
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
        if (! $this->hasCfaTable()) {
            return $this->p3Education;
        }

        foreach (DB::table('cfa_submissions as cs')
            ->join('districts as d', 'd.id', '=', 'cs.district_id')
            ->selectRaw("LOWER(d.name) as dist, JSON_UNQUOTE(JSON_EXTRACT(cs.payload, '$.education')) as education, COUNT(*) as c")
            ->where(function ($q): void {
                $q->whereNull('cs.source')->orWhere('cs.source', '<>', 'legacy_phase2');
            })
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
        if (! $this->hasCfaTable()) {
            return 0;
        }

        return (int) DB::table('cfa_submissions')
            ->where(function ($q): void {
                $q->whereNull('source')->orWhere('source', '<>', 'legacy_phase2');
            })
            ->count();
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
