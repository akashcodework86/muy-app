<?php

namespace App\Services\DataCentre;

use App\Models\District;
use App\Models\FiscalYear;
use App\Services\LegacyPhase1\LegacyPhase1DistrictResolver;
use Illuminate\Database\Query\Builder;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

class ApplicationAnalysisService
{
    public const PHASES = ['combined', 'phase1', 'phase2', 'phase3'];

    public const CACHE_TTL = 300;

    private const CACHE_PREFIX = 'data_centre_analysis_v3_';

    private const P2_START = '2025-04-02';

    private const P2_END = '2026-04-01';

    private const MAX_PLAUSIBLE_JOBS = 500;

    private const P2_ALIASES = [
        'Udham Singh Nagar' => ['udham singh nagar', 'udham singh nagr', 'us nagar', 'u s nagar', 'u.s. nagar', 'u s n'],
        'Pauri Garhwal' => ['pauri garhwal', 'pauri'],
        'Tehri Garhwal' => ['tehri garhwal', 'tehri'],
        'Haridwar' => ['haridwar', 'hardwar'],
        'Dehradun' => ['dehradun', 'doon'],
    ];

    private bool $legacyPhase1Ok = false;

    private bool $legacyPhase2Ok = false;

    private string $dataScope = 'all';

    private DataCentreFilter $filter;

    private ?string $districtName = null;

    public function __construct(
        private readonly ProgramDataCentreService $phase3,
    ) {
        $this->legacyPhase1Ok = $this->testConnection('legacy_phase1', 'tblapplication');
        $this->legacyPhase2Ok = $this->testConnection('legacy', 'rbi_applications');
        $this->filter = DataCentreFilter::empty();
    }

    /**
     * @return array{
     *   meta: array<string, mixed>,
     *   view_mode: string,
     *   analysis_phase: string,
     *   analysis_phase_label: string,
     *   data_scope: string,
     *   application_analysis: array<string, mixed>
     * }
     */
    public function build(string $phase, string $dataScope = 'all', ?DataCentreFilter $filter = null): array
    {
        $phase = in_array($phase, self::PHASES, true) ? $phase : 'combined';
        $dataScope = $dataScope === 'onboarded' ? 'onboarded' : 'all';
        $filter = $filter ?? DataCentreFilter::empty();
        $this->prepare($dataScope, $filter);

        $cacheKey = self::CACHE_PREFIX.$phase.'_'.$dataScope.'_'.$filter->cacheKeySuffix();

        $analysis = Cache::remember($cacheKey, self::CACHE_TTL, function () use ($phase, $dataScope, $filter) {
            $this->prepare($dataScope, $filter);

            $result = match ($phase) {
                'phase1' => $this->safeAnalysis(fn () => $this->phase1Analysis()),
                'phase2' => $this->safeAnalysis(fn () => $this->phase2Analysis()),
                'phase3' => $this->safeAnalysis(fn () => $this->phase3->applicationAnalysisForPhase3($dataScope, $filter)),
                default => $this->combinedAnalysis(),
            };

            return $this->normalizeAnalysisCard($result);
        });

        return [
            'meta' => [
                'generated_at' => now()->timezone('Asia/Kolkata')->format('d M Y, g:i A \I\S\T'),
                'phase1_available' => $this->legacyPhase1Ok,
                'phase2_available' => $this->legacyPhase2Ok,
                'phase3_fy' => FiscalYear::phase3Default()?->name ?? 'FY 2026-27',
                'cache_ttl' => self::CACHE_TTL,
                'view_mode' => 'analysis',
                'data_scope' => $dataScope,
                'filter_active' => $filter->isActive(),
            ],
            'view_mode' => 'analysis',
            'analysis_phase' => $phase,
            'analysis_phase_label' => self::phaseLabel($phase),
            'data_scope' => $dataScope,
            'application_analysis' => $analysis,
        ];
    }

    public function bustCache(): void
    {
        foreach (self::PHASES as $phase) {
            foreach (['all', 'onboarded'] as $dataScope) {
                Cache::forget(self::CACHE_PREFIX.$phase.'_'.$dataScope.'_none');
            }
        }
    }

    public static function phaseLabel(string $phase): string
    {
        return match ($phase) {
            'phase1' => 'Phase 1 (FY 2024–25)',
            'phase2' => 'Phase 2 (FY 2025–26)',
            'phase3' => 'Phase 3 (FY 2026–27)',
            default => 'All phases (Phase 1 + 2 + 3)',
        };
    }

    public function fiscalYearForPhase(string $phase): ?FiscalYear
    {
        $code = match ($phase) {
            'phase1' => '2024-25',
            'phase2' => '2025-26',
            'phase3' => '2026-27',
            default => null,
        };
        if ($code === null) {
            return null;
        }

        return FiscalYear::query()->where('code', $code)->first()
            ?? ($phase === 'phase3' ? FiscalYear::phase3Default() : null);
    }

    /**
     * @return array<string, mixed>
     */
    public function mergeAnalyses(array $parts): array
    {
        $parts = array_values(array_filter($parts, fn ($part) => is_array($part)));
        $total = (int) array_sum(array_map(fn (array $part) => (int) ($part['total'] ?? 0), $parts));
        $fullParts = array_filter($parts, fn (array $part) => isset($part['full_total']) && $part['full_total'] !== null);
        $fullTotal = $fullParts === []
            ? null
            : (int) array_sum(array_map(fn (array $part) => (int) $part['full_total'], $fullParts));

        if ($total === 0) {
            return $this->emptyAnalysis();
        }

        $pct = fn (int $n): float => round($n * 100 / $total, 1);

        $entrepreneur = $this->sumKeyedRows($parts, 'entrepreneur', 'label');
        foreach ($entrepreneur as &$row) {
            $row['pct'] = ! empty($row['is_jobs_total']) ? null : $pct((int) $row['count']);
        }
        unset($row);

        $sectors = $this->collapseSectorRows($this->sumKeyedRows($parts, 'sectors', 'sector'), $pct);

        $otherBreakdown = [];
        foreach ($parts as $part) {
            foreach ($part['other_breakdown'] ?? [] as $row) {
                $key = mb_strtolower(trim((string) ($row['product'] ?? '')));
                if ($key === '') {
                    $key = 'not specified';
                }
                if (! isset($otherBreakdown[$key])) {
                    $otherBreakdown[$key] = ['product' => (string) ($row['product'] ?? 'Not specified'), 'count' => 0];
                }
                $otherBreakdown[$key]['count'] += (int) ($row['count'] ?? 0);
            }
        }
        $otherBreakdown = array_values($otherBreakdown);
        usort($otherBreakdown, fn ($a, $b) => $b['count'] <=> $a['count']);

        $businessStats = $this->sumKeyedRows($parts, 'business_stats', 'label');
        foreach ($businessStats as &$row) {
            $row['pct'] = $pct((int) $row['count']);
        }
        unset($row);

        $incomeSlabs = $this->sumKeyedRows($parts, 'income_slabs', 'label');
        foreach ($incomeSlabs as &$row) {
            $row['pct'] = $pct((int) $row['count']);
        }
        unset($row);

        return $this->withAccuracyChecks([
            'total' => $total,
            'full_total' => $fullTotal !== null && $fullTotal !== $total ? $fullTotal : null,
            'entrepreneur' => $this->orderEntrepreneur($entrepreneur),
            'sectors' => $sectors,
            'other_breakdown' => $otherBreakdown,
            'business_stats' => $businessStats,
            'income_slabs' => $incomeSlabs,
            'onboarding_breakdown' => [],
        ]);
    }

    /**
     * @param  callable(): array<string, mixed>  $fn
     * @return array<string, mixed>
     */
    private function safeAnalysis(callable $fn): array
    {
        try {
            return $fn();
        } catch (\Throwable $e) {
            report($e);

            return $this->emptyAnalysis();
        }
    }

    private function prepare(string $dataScope, DataCentreFilter $filter): void
    {
        $this->dataScope = $dataScope === 'onboarded' ? 'onboarded' : 'all';
        $this->filter = $filter;
        $this->districtName = null;
        if ($filter->districtId) {
            $name = District::query()->where('id', $filter->districtId)->value('name');
            $this->districtName = is_string($name) && $name !== '' ? $name : null;
        }
    }

    /**
     * @return array<string, mixed>
     */
    private function combinedAnalysis(): array
    {
        return $this->mergeAnalyses([
            $this->safeAnalysis(fn () => $this->phase1Analysis()),
            $this->safeAnalysis(fn () => $this->phase2Analysis()),
            $this->safeAnalysis(fn () => $this->phase3->applicationAnalysisForPhase3($this->dataScope, $this->filter)),
        ]);
    }

    /**
     * @return array<string, mixed>
     */
    private function phase1Analysis(): array
    {
        if (! $this->legacyPhase1Ok) {
            return $this->emptyAnalysis();
        }

        $total = (int) $this->phase1Query()->count();
        if ($total === 0) {
            $empty = $this->emptyAnalysis();
            $empty['full_total'] = $this->filter->isActive() ? (int) $this->phase1BaseQuery()->count() : null;

            return $empty;
        }

        $pct = fn (int $n): float => round($n * 100 / $total, 1);
        $stageExpr = $this->phase1StageExpr();
        $stages = ['seed' => 0, 'early' => 0, 'growth' => 0, 'unknown' => 0];
        foreach ((clone $this->phase1Query())
            ->selectRaw("{$stageExpr} as stage_key, COUNT(*) as aggregate_count")
            ->groupBy('stage_key')
            ->get() as $row) {
            $key = (string) ($row->stage_key ?? 'unknown');
            $stages[array_key_exists($key, $stages) ? $key : 'unknown'] += (int) $row->aggregate_count;
        }

        $genderExpr = $this->phase1GenderExpr();
        $female = (int) (clone $this->phase1Query())->whereRaw("{$genderExpr} = 'Female'")->count();
        $male = (int) (clone $this->phase1Query())->whereRaw("{$genderExpr} = 'Male'")->count();
        $cbo = (int) (clone $this->phase1Query())->whereRaw($this->phase1IsCboExpr())->count();

        $employment = $this->phase1Employment();
        $sectorExpr = $this->phase1SectorExpr();
        $sectors = [];
        foreach ((clone $this->phase1Query())
            ->selectRaw("{$sectorExpr} as sector, COUNT(*) as count")
            ->groupBy('sector')
            ->orderByDesc('count')
            ->get() as $row) {
            $count = (int) $row->count;
            $sectors[] = ['sector' => (string) $row->sector, 'count' => $count, 'pct' => $pct($count)];
        }
        $sectors = $this->collapseSectorRows($sectors, $pct);

        $otherProduct = "COALESCE(NULLIF(TRIM(idea2), ''), NULLIF(TRIM(other_idea), ''), 'Not specified')";
        $othersSector = collect($sectors)->first(fn ($row) => ($row['sector'] ?? '') === 'Others');
        $otherBreakdown = $othersSector ? (clone $this->phase1Query())
            ->whereRaw("LOWER(TRIM(COALESCE(idea, ''))) IN ('other', 'others')")
            ->selectRaw("MIN({$otherProduct}) as product, COUNT(*) as count")
            ->groupByRaw("LOWER(TRIM({$otherProduct}))")
            ->orderByDesc('count')
            ->get()
            ->map(fn ($row) => ['product' => (string) $row->product, 'count' => (int) $row->count])
            ->all() : [];

        $loan = $this->phase1YesNoCounts('loan');
        $registered = $this->phase1YesNoCounts('registered');

        return $this->packAnalysis(
            $total,
            $this->filter->isActive() ? (int) $this->phase1BaseQuery()->count() : null,
            $stages,
            $female,
            $male,
            $cbo,
            (int) $employment['employers'],
            (int) $employment['jobs'],
            $sectors,
            $otherBreakdown,
            $loan,
            $registered,
            $this->incomeSlabsFromExpr($this->phase1Query(), $this->phase1TurnoverExpr(), $pct),
        );
    }

    /**
     * @return array<string, mixed>
     */
    private function phase2Analysis(): array
    {
        if (! $this->legacyPhase2Ok || ($this->isOnboardedScope() && ! $this->legacyPhase2OnboardOk())) {
            return $this->emptyAnalysis();
        }

        $total = (int) $this->phase2Query()->count();
        if ($total === 0) {
            $empty = $this->emptyAnalysis();
            $empty['full_total'] = $this->filter->isActive() ? (int) $this->phase2BaseQuery()->count() : null;

            return $empty;
        }

        $pct = fn (int $n): float => round($n * 100 / $total, 1);
        $stageExpr = $this->phase2StageExpr();
        $stages = ['seed' => 0, 'early' => 0, 'growth' => 0, 'unknown' => 0];
        foreach ((clone $this->phase2Query())
            ->selectRaw("{$stageExpr} as stage_key, COUNT(*) as aggregate_count")
            ->groupBy('stage_key')
            ->get() as $row) {
            $key = (string) ($row->stage_key ?? 'unknown');
            $stages[array_key_exists($key, $stages) ? $key : 'unknown'] += (int) $row->aggregate_count;
        }

        $genderExpr = $this->phase2GenderExpr();
        $female = (int) (clone $this->phase2Query())->whereRaw("{$genderExpr} = 'Female'")->count();
        $male = (int) (clone $this->phase2Query())->whereRaw("{$genderExpr} = 'Male'")->count();
        $cbo = (int) (clone $this->phase2Query())->whereRaw("LOWER(TRIM(COALESCE(a.category, ''))) = 'cbo'")->count();

        $employment = $this->phase2Employment();
        $sectorExpr = $this->phase2SectorExpr();
        $sectors = [];
        foreach ((clone $this->phase2Query())
            ->selectRaw("{$sectorExpr} as sector, COUNT(*) as count")
            ->groupBy('sector')
            ->orderByDesc('count')
            ->get() as $row) {
            $count = (int) $row->count;
            $sectors[] = ['sector' => (string) $row->sector, 'count' => $count, 'pct' => $pct($count)];
        }
        $sectors = $this->collapseSectorRows($sectors, $pct);

        $product = "NULLIF(NULLIF(TRIM(a.product), ''), 'null')";
        $specifiedOther = "NULLIF(NULLIF(TRIM(a.other_product), ''), 'null')";
        $otherProduct = "COALESCE(CASE WHEN LOWER(TRIM(COALESCE({$product}, ''))) IN ('other', 'others') THEN {$specifiedOther} ELSE {$product} END, 'Not specified')";
        $othersSector = collect($sectors)->first(fn ($row) => ($row['sector'] ?? '') === 'Others');
        $otherBreakdown = $othersSector ? (clone $this->phase2Query())
            ->whereRaw("(LOWER(TRIM(COALESCE(a.business_category, ''))) IN ('other', 'others') OR a.business_category LIKE '%à¤%')")
            ->selectRaw("MIN({$otherProduct}) as product, COUNT(*) as count")
            ->groupByRaw("LOWER(TRIM({$otherProduct}))")
            ->orderByDesc('count')
            ->get()
            ->map(fn ($row) => ['product' => (string) $row->product, 'count' => (int) $row->count])
            ->all() : [];

        $loan = $this->phase2YesNoCounts("NULLIF(TRIM(d.loan_taken), '')");
        $registered = $this->phase2YesNoCounts($this->phase2IsRegisteredExpr());

        return $this->packAnalysis(
            $total,
            $this->filter->isActive() ? (int) $this->phase2BaseQuery()->count() : null,
            $stages,
            $female,
            $male,
            $cbo,
            (int) $employment['employers'],
            (int) $employment['jobs'],
            $sectors,
            $otherBreakdown,
            $loan,
            $registered,
            $this->incomeSlabsFromExpr($this->phase2Query(), $this->phase2TurnoverExpr(), $pct),
        );
    }

    private function phase1Query(): Builder
    {
        return $this->applyPhase1Filters($this->phase1BaseQuery());
    }

    private function phase1BaseQuery(): Builder
    {
        $query = DB::connection('legacy_phase1')->table('tblapplication');
        if ($this->isOnboardedScope()) {
            $query->whereRaw('LOWER(TRIM(onboard)) = ?', ['yes']);
        }

        return $query;
    }

    private function applyPhase1Filters(Builder $query): Builder
    {
        if ($this->districtName) {
            LegacyPhase1DistrictResolver::applyDistrictFilter($query, $this->districtName);
        }

        $this->applyDateFilter($query, $this->isOnboardedScope()
            ? 'COALESCE(NULLIF(onboard_date, \'\'), NULLIF(onboarding_date, \'\'), ApplicationDate)'
            : 'ApplicationDate', 'phase1');

        return $query;
    }

    private function phase2Query(): Builder
    {
        return $this->applyPhase2Filters($this->phase2BaseQuery());
    }

    private function phase2BaseQuery(): Builder
    {
        $legacy = DB::connection('legacy');
        $query = $legacy->table('rbi_applications as a')
            ->leftJoin('rbi_applicant_details as d', 'd.application_id', '=', 'a.id')
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

        if ($this->isOnboardedScope()) {
            $query->join('rbi_onboarded_applicants as oa', 'oa.application_id', '=', 'a.id')
                ->whereNotNull('oa.status')
                ->where('oa.status', '!=', '');
        }

        return $query;
    }

    private function applyPhase2Filters(Builder $query): Builder
    {
        if ($this->districtName) {
            $query->whereIn(DB::raw('LOWER(TRIM(d.district))'), $this->p2Norms($this->districtName));
        }

        $dateCol = $this->isOnboardedScope() ? 'oa.onboarded_at' : 'a.submission_date';
        $this->applyDateFilter($query, $dateCol, 'phase2');

        return $query;
    }

    private function applyDateFilter(Builder $query, string $dateExpr, string $phase): void
    {
        if (! $this->filter->hasDateFilter()) {
            return;
        }

        $fy = $this->fiscalYearForPhase($phase);
        if ($this->filter->dateFrom && $this->filter->dateTo && $this->filter->quarter === null && $this->filter->fiscalMonth === null) {
            $fy = null;
        }
        [$from, $to] = $this->filter->resolveDatePeriod($fy);
        if ($from === null || $to === null) {
            return;
        }

        $query->whereRaw("DATE({$dateExpr}) BETWEEN ? AND ?", [$from->toDateString(), $to->toDateString()]);
    }

    private function phase1StageExpr(): string
    {
        $src = "LOWER(TRIM(REPLACE(REPLACE(COALESCE(Occupationtype, ''), CHAR(13), ''), CHAR(10), '')))";

        return "CASE
            WHEN {$src} LIKE '%seed%' THEN 'seed'
            WHEN {$src} LIKE '%early%' THEN 'early'
            WHEN {$src} LIKE '%growth%' THEN 'growth'
            ELSE 'unknown'
        END";
    }

    private function phase2StageExpr(): string
    {
        $src = "LOWER(TRIM(COALESCE(a.form_stage, '')))";

        return "CASE
            WHEN {$src} IN ('seed', 'early', 'growth') THEN {$src}
            ELSE 'unknown'
        END";
    }

    private function phase1GenderExpr(): string
    {
        $src = "LOWER(TRIM(REPLACE(REPLACE(COALESCE(gender, ''), CHAR(13), ''), CHAR(10), '')))";

        return "CASE
            WHEN {$src} IN ('female', 'f') THEN 'Female'
            WHEN {$src} IN ('male', 'm') THEN 'Male'
            ELSE 'Other'
        END";
    }

    private function phase2GenderExpr(): string
    {
        $src = "LOWER(TRIM(COALESCE(d.gender, '')))";

        return "CASE
            WHEN {$src} IN ('female', 'f') THEN 'Female'
            WHEN {$src} IN ('male', 'm') THEN 'Male'
            ELSE 'Other'
        END";
    }

    private function phase1IsCboExpr(): string
    {
        return "LOWER(TRIM(REPLACE(REPLACE(COALESCE(Occupationtype, ''), CHAR(13), ''), CHAR(10), ''))) LIKE '%group%'";
    }

    private function phase1SectorExpr(): string
    {
        $raw = "LOWER(TRIM(COALESCE(idea, '')))";

        return "CASE
            WHEN {$raw} IN ('agri & allied', 'agri allied') THEN 'Agri Allied'
            WHEN {$raw} IN ('herbal & aromatics', 'herbal and aromatic', 'herbal and aromatics') THEN 'Herbal and Aromatic'
            WHEN {$raw} IN ('other', 'others') THEN 'Others'
            WHEN TRIM(COALESCE(idea, '')) = '' THEN 'Not specified'
            ELSE TRIM(idea)
        END";
    }

    private function phase2SectorExpr(): string
    {
        $raw = "LOWER(TRIM(COALESCE(a.business_category, '')))";

        return "CASE
            WHEN {$raw} IN ('agri allied', 'agri & allied') THEN 'Agri Allied'
            WHEN {$raw} IN ('herbal and aromatic', 'herbal & aromatics') THEN 'Herbal and Aromatic'
            WHEN {$raw} IN ('handloom & handicraft') THEN 'Handloom & Handicraft'
            WHEN {$raw} IN ('food processing') THEN 'Food Processing'
            WHEN {$raw} IN ('homestay') THEN 'Homestay'
            WHEN {$raw} IN ('other', 'others') THEN 'Others'
            WHEN TRIM(COALESCE(a.business_category, '')) = '' THEN 'Not specified'
            ELSE TRIM(a.business_category)
        END";
    }

    private function phase1TurnoverExpr(): string
    {
        return "CASE
            WHEN avg_turnover REGEXP '^[0-9,]+(\\.[0-9]+)?$' AND TRIM(avg_turnover) <> ''
            THEN CAST(REPLACE(avg_turnover, ',', '') AS DECIMAL(15,2))
            ELSE NULL
        END";
    }

    private function phase2TurnoverExpr(): string
    {
        return $this->legacyHasTable('rbi_enterprise_details')
            ? 'ed.turnover_last_year'
            : 'NULL';
    }

    private function phase2IsRegisteredExpr(): string
    {
        if (! $this->legacyHasTable('rbi_enterprise_details')) {
            return 'NULL';
        }

        return "CASE
            WHEN ed.is_registered IN ('Yes', 'yes') THEN 'Yes'
            WHEN ed.is_registered IN ('No', 'no', '0') THEN 'No'
            ELSE NULL
        END";
    }

    /**
     * @return array{yes: int, no: int, unspecified: int}
     */
    private function phase1YesNoCounts(string $column): array
    {
        $expr = "LOWER(TRIM(COALESCE({$column}, '')))";
        $row = (clone $this->phase1Query())->selectRaw("
            SUM(CASE WHEN {$expr} IN ('yes', 'y', '1') THEN 1 ELSE 0 END) as yes_count,
            SUM(CASE WHEN {$expr} IN ('no', 'n', '0') THEN 1 ELSE 0 END) as no_count,
            SUM(CASE WHEN {$expr} NOT IN ('yes', 'y', '1', 'no', 'n', '0') THEN 1 ELSE 0 END) as unspecified_count
        ")->first();

        return [
            'yes' => (int) ($row->yes_count ?? 0),
            'no' => (int) ($row->no_count ?? 0),
            'unspecified' => (int) ($row->unspecified_count ?? 0),
        ];
    }

    /**
     * @return array{yes: int, no: int, unspecified: int}
     */
    private function phase2YesNoCounts(string $expr): array
    {
        $norm = "LOWER(TRIM(COALESCE({$expr}, '')))";
        $row = (clone $this->phase2Query())->selectRaw("
            SUM(CASE WHEN {$norm} IN ('yes', 'y', '1') THEN 1 ELSE 0 END) as yes_count,
            SUM(CASE WHEN {$norm} IN ('no', 'n', '0') THEN 1 ELSE 0 END) as no_count,
            SUM(CASE WHEN {$norm} NOT IN ('yes', 'y', '1', 'no', 'n', '0') THEN 1 ELSE 0 END) as unspecified_count
        ")->first();

        return [
            'yes' => (int) ($row->yes_count ?? 0),
            'no' => (int) ($row->no_count ?? 0),
            'unspecified' => (int) ($row->unspecified_count ?? 0),
        ];
    }

    /**
     * @return array{employers: int, jobs: int}
     */
    private function phase1Employment(): array
    {
        $cap = self::MAX_PLAUSIBLE_JOBS;
        $row = (clone $this->phase1Query())->selectRaw("
            SUM(LOWER(TRIM(COALESCE(job_offer, ''))) = 'yes') as employers,
            SUM(CASE WHEN job_count REGEXP '^[0-9]+$' AND CAST(job_count AS UNSIGNED) BETWEEN 1 AND {$cap}
                     THEN CAST(job_count AS UNSIGNED) ELSE 0 END) as jobs
        ")->first();

        return ['employers' => (int) ($row->employers ?? 0), 'jobs' => (int) ($row->jobs ?? 0)];
    }

    /**
     * @return array{employers: int, jobs: int}
     */
    private function phase2Employment(): array
    {
        $cap = self::MAX_PLAUSIBLE_JOBS;
        $row = (clone $this->phase2Query())->selectRaw("
            SUM(LOWER(TRIM(COALESCE(d.current_employment, ''))) = 'yes') as employers,
            SUM(CASE WHEN d.employed_count REGEXP '^[0-9]+$' AND CAST(d.employed_count AS UNSIGNED) BETWEEN 1 AND {$cap}
                     THEN CAST(d.employed_count AS UNSIGNED) ELSE 0 END) as jobs
        ")->first();

        return ['employers' => (int) ($row->employers ?? 0), 'jobs' => (int) ($row->jobs ?? 0)];
    }

    /**
     * @param  callable(int): float  $pct
     * @return list<array{label: string, count: int, pct: float}>
     */
    private function incomeSlabsFromExpr(Builder $query, string $turnover, callable $pct): array
    {
        $row = (clone $query)->selectRaw("
            SUM(CASE WHEN {$turnover} IS NOT NULL AND {$turnover} = 0 THEN 1 ELSE 0 END) as income_zero,
            SUM(CASE WHEN {$turnover} IS NOT NULL AND {$turnover} > 0 AND {$turnover} < 100000 THEN 1 ELSE 0 END) as income_0_1l,
            SUM(CASE WHEN {$turnover} IS NOT NULL AND {$turnover} >= 100000 AND {$turnover} < 500000 THEN 1 ELSE 0 END) as income_1_5l,
            SUM(CASE WHEN {$turnover} IS NOT NULL AND {$turnover} >= 500000 AND {$turnover} < 1000000 THEN 1 ELSE 0 END) as income_5_10l,
            SUM(CASE WHEN {$turnover} IS NOT NULL AND {$turnover} >= 1000000 AND {$turnover} < 2500000 THEN 1 ELSE 0 END) as income_10_25l,
            SUM(CASE WHEN {$turnover} IS NOT NULL AND {$turnover} >= 2500000 THEN 1 ELSE 0 END) as income_25l_plus,
            SUM(CASE WHEN {$turnover} IS NULL THEN 1 ELSE 0 END) as income_invalid
        ")->first();

        $counts = [
            'Zero income' => (int) ($row->income_zero ?? 0),
            'INR > 0 – 1 Lakh' => (int) ($row->income_0_1l ?? 0),
            'INR 1 – 5 Lakh' => (int) ($row->income_1_5l ?? 0),
            'INR 5 – 10 Lakh' => (int) ($row->income_5_10l ?? 0),
            'INR 10 – 25 Lakh' => (int) ($row->income_10_25l ?? 0),
            'INR 25 Lakh+' => (int) ($row->income_25l_plus ?? 0),
        ];
        $invalid = (int) ($row->income_invalid ?? 0);
        if ($invalid > 0) {
            $counts['Not specified'] = $invalid;
        }

        $slabs = [];
        foreach ($counts as $label => $count) {
            $slabs[] = ['label' => $label, 'count' => $count, 'pct' => $pct($count)];
        }

        return $slabs;
    }

    /**
     * @param  array{seed: int, early: int, growth: int, unknown: int}  $stages
     * @param  list<array{sector: string, count: int, pct: float}>  $sectors
     * @param  list<array{product: string, count: int}>  $otherBreakdown
     * @param  array{yes: int, no: int, unspecified: int}  $loan
     * @param  array{yes: int, no: int, unspecified: int}  $registered
     * @param  list<array{label: string, count: int, pct: float}>  $incomeSlabs
     * @return array<string, mixed>
     */
    private function packAnalysis(
        int $total,
        ?int $fullTotal,
        array $stages,
        int $female,
        int $male,
        int $cbo,
        int $employers,
        int $jobs,
        array $sectors,
        array $otherBreakdown,
        array $loan,
        array $registered,
        array $incomeSlabs,
    ): array {
        $pct = fn (int $n): float => $total > 0 ? round($n * 100 / $total, 1) : 0.0;
        $entrepreneur = [
            ['label' => 'Women Entrepreneurs', 'count' => $female, 'pct' => $pct($female)],
            ['label' => 'Seed-Stage Entrepreneurs', 'count' => $stages['seed'], 'pct' => $pct($stages['seed'])],
            ['label' => 'Early-Stage Entrepreneurs', 'count' => $stages['early'], 'pct' => $pct($stages['early'])],
            ['label' => 'Growth-Stage Entrepreneurs', 'count' => $stages['growth'], 'pct' => $pct($stages['growth'])],
        ];
        if ($stages['unknown'] > 0) {
            $entrepreneur[] = ['label' => 'Stage not specified', 'count' => $stages['unknown'], 'pct' => $pct($stages['unknown'])];
        }
        $entrepreneur[] = ['label' => 'CBOs', 'count' => $cbo, 'pct' => $pct($cbo)];
        $entrepreneur[] = ['label' => 'Generating Employment', 'count' => $employers, 'pct' => $pct($employers)];
        $entrepreneur[] = ['label' => 'Employment Generated', 'count' => $jobs, 'pct' => null, 'is_jobs_total' => true];

        $sectors = $this->collapseSectorRows($sectors, $pct);

        return $this->withAccuracyChecks([
            'total' => $total,
            'full_total' => $fullTotal !== null && $fullTotal !== $total ? $fullTotal : null,
            'entrepreneur' => $entrepreneur,
            'sectors' => $sectors,
            'other_breakdown' => $otherBreakdown,
            'business_stats' => [
                ['label' => 'No Credit History', 'count' => $loan['no'], 'pct' => $pct($loan['no'])],
                ['label' => 'Businesses are unorganized', 'count' => $registered['no'], 'pct' => $pct($registered['no'])],
            ],
            'income_slabs' => $incomeSlabs,
            'onboarding_breakdown' => [],
        ], $stages, $female, $male, $loan, $registered);
    }

    /**
     * @param  array<string, mixed>  $analysis
     * @param  array{seed: int, early: int, growth: int, unknown: int}|null  $stages
     * @param  array{yes: int, no: int, unspecified: int}|null  $loan
     * @param  array{yes: int, no: int, unspecified: int}|null  $registered
     * @return array<string, mixed>
     */
    private function withAccuracyChecks(
        array $analysis,
        ?array $stages = null,
        ?int $female = null,
        ?int $male = null,
        ?array $loan = null,
        ?array $registered = null,
    ): array {
        $total = (int) ($analysis['total'] ?? 0);
        if ($stages === null) {
            $byLabel = [];
            foreach ($analysis['entrepreneur'] ?? [] as $row) {
                $byLabel[$row['label'] ?? ''] = (int) ($row['count'] ?? 0);
            }
            $stageSum = ($byLabel['Seed-Stage Entrepreneurs'] ?? 0)
                + ($byLabel['Early-Stage Entrepreneurs'] ?? 0)
                + ($byLabel['Growth-Stage Entrepreneurs'] ?? 0)
                + ($byLabel['Stage not specified'] ?? 0);
            $female = $byLabel['Women Entrepreneurs'] ?? 0;
            $male = null;
            $genderOther = null;
            $loanNo = (int) (collect($analysis['business_stats'] ?? [])->firstWhere('label', 'No Credit History')['count'] ?? 0);
            $unorganized = (int) (collect($analysis['business_stats'] ?? [])->firstWhere('label', 'Businesses are unorganized')['count'] ?? 0);
            $loanSum = null;
            $regSum = null;
        } else {
            $stageSum = $stages['seed'] + $stages['early'] + $stages['growth'] + $stages['unknown'];
            $genderOther = $total - (int) $female - (int) $male;
            $loanNo = (int) ($loan['no'] ?? 0);
            $unorganized = (int) ($registered['no'] ?? 0);
            $loanSum = (int) ($loan['no'] ?? 0) + (int) ($loan['yes'] ?? 0) + (int) ($loan['unspecified'] ?? 0);
            $regSum = (int) ($registered['no'] ?? 0) + (int) ($registered['yes'] ?? 0) + (int) ($registered['unspecified'] ?? 0);
        }

        $sectorSum = (int) array_sum(array_column($analysis['sectors'] ?? [], 'count'));
        $incomeSum = (int) array_sum(array_column($analysis['income_slabs'] ?? [], 'count'));

        $checks = [
            ['label' => 'Seed + Early + Growth (+ unspecified)', 'expected' => $total, 'actual' => $stageSum],
            ['label' => 'All sectors sum', 'expected' => $total, 'actual' => $sectorSum],
            ['label' => 'Loan No + Yes + unspecified', 'expected' => $total, 'actual' => $loanSum ?? $total],
            ['label' => 'Registered No + Yes + unspecified', 'expected' => $total, 'actual' => $regSum ?? $total],
            ['label' => 'Income slabs sum', 'expected' => $total, 'actual' => $incomeSum],
            ['label' => 'Female + Male + Other/NA', 'expected' => $total, 'actual' => $genderOther === null ? $total : ((int) $female + (int) $male + $genderOther)],
        ];

        $analysis['accuracy_checks'] = array_map(static function (array $check): array {
            return [
                'label' => $check['label'],
                'expected' => $check['expected'],
                'actual' => $check['actual'],
                'pass' => $check['expected'] === $check['actual'],
            ];
        }, $checks);

        return $analysis;
    }

    /**
     * Ensure every phase filter returns the same English sector labels as the Phase 3 card.
     *
     * @param  array<string, mixed>  $analysis
     * @return array<string, mixed>
     */
    private function normalizeAnalysisCard(array $analysis): array
    {
        $total = (int) ($analysis['total'] ?? 0);
        $pct = fn (int $n): float => $total > 0 ? round($n * 100 / $total, 1) : 0.0;
        $analysis['sectors'] = $this->collapseSectorRows($analysis['sectors'] ?? [], $pct);

        return $analysis;
    }

    /**
     * Collapse sector rows onto canonical English labels (fixes Hindi / mojibake labels).
     *
     * @param  list<array{sector?: string, count?: int, pct?: float|null}>  $rows
     * @param  callable(int): float  $pct
     * @return list<array{sector: string, count: int, pct: float}>
     */
    private function collapseSectorRows(array $rows, callable $pct): array
    {
        $merged = [];
        foreach ($rows as $row) {
            $label = $this->normalizeSectorLabel((string) ($row['sector'] ?? ''));
            $merged[$label] = ($merged[$label] ?? 0) + (int) ($row['count'] ?? 0);
        }
        arsort($merged);
        $out = [];
        foreach ($merged as $sector => $count) {
            $out[] = ['sector' => $sector, 'count' => $count, 'pct' => $pct($count)];
        }

        return $out;
    }

    public function normalizeSectorLabel(string $raw): string
    {
        $s = trim($raw);
        if ($s === '' || strcasecmp($s, 'null') === 0) {
            return 'Not specified';
        }

        // Fix mojibake: UTF-8 Hindi misread as Latin-1 then re-encoded as UTF-8.
        if (str_contains($s, 'à¤') || str_contains($s, 'Ã') || str_contains($s, 'Â')) {
            $decoded = @iconv('UTF-8', 'ISO-8859-1//IGNORE', $s);
            if (is_string($decoded) && $decoded !== '' && mb_check_encoding($decoded, 'UTF-8')) {
                $s = trim($decoded);
            }
        }

        $l = mb_strtolower(preg_replace('/\s+/u', ' ', $s) ?? $s);

        if (in_array($l, ['agri allied', 'agri & allied', 'agri and allied'], true)
            || str_contains($l, 'कृषि')
            || str_contains($raw, 'à¤•à¥ƒà¤·à¤¿')) {
            return 'Agri Allied';
        }
        if (in_array($l, ['herbal and aromatic', 'herbal & aromatics', 'herbal and aromatics', 'herbal & aromatic'], true)
            || str_contains($l, 'हर्बल')
            || str_contains($l, 'सुगंधित')
            || str_contains($raw, 'à¤¹à¤°à¥à¤¬à¤²')) {
            return 'Herbal and Aromatic';
        }
        if (in_array($l, ['handloom & handicraft', 'handloom and handicraft'], true)
            || str_contains($l, 'हस्तशिल्प')
            || str_contains($l, 'हथकरघा')
            || str_contains($raw, 'à¤¹à¤¸à¥à¤¤à¤¶à¤¿à¤²à¥à¤ª')
            || str_contains($raw, 'à¤¹à¤¥à¤•à¤°à¤˜à¤¾')) {
            return 'Handloom & Handicraft';
        }
        if (in_array($l, ['food processing'], true)
            || str_contains($l, 'खाद्य')
            || str_contains($raw, 'à¤–à¤¾à¤¦à¥à¤¯')) {
            return 'Food Processing';
        }
        if (in_array($l, ['homestay', 'home stay'], true)) {
            return 'Homestay';
        }
        if (in_array($l, ['other', 'others', 'अन्य'], true)
            || str_contains($l, 'अन्य')
            || str_contains($raw, 'à¤…à¤¨à¥à¤¯')
            || str_contains($raw, 'à¤…à¤¨à¤¯')) {
            return 'Others';
        }
        if (str_contains($raw, 'à¤') || str_contains($raw, 'Ã')) {
            return 'Others';
        }

        return $s;
    }

    /**
     * @param  list<array<string, mixed>>  $parts
     * @return list<array<string, mixed>>
     */
    private function sumKeyedRows(array $parts, string $listKey, string $nameKey): array
    {
        $merged = [];
        foreach ($parts as $part) {
            foreach ($part[$listKey] ?? [] as $row) {
                $name = (string) ($row[$nameKey] ?? '');
                if ($nameKey === 'sector') {
                    $name = $this->normalizeSectorLabel($name);
                    $row[$nameKey] = $name;
                }
                if ($name === '') {
                    continue;
                }
                if (! isset($merged[$name])) {
                    $merged[$name] = $row;
                    $merged[$name]['count'] = 0;
                }
                $merged[$name]['count'] += (int) ($row['count'] ?? 0);
                if (! empty($row['is_jobs_total'])) {
                    $merged[$name]['is_jobs_total'] = true;
                    $merged[$name]['pct'] = null;
                }
            }
        }

        return array_values($merged);
    }

    /**
     * @param  list<array<string, mixed>>  $rows
     * @return list<array<string, mixed>>
     */
    private function orderEntrepreneur(array $rows): array
    {
        $order = [
            'Women Entrepreneurs',
            'Seed-Stage Entrepreneurs',
            'Early-Stage Entrepreneurs',
            'Growth-Stage Entrepreneurs',
            'Stage not specified',
            'CBOs',
            'Generating Employment',
            'Employment Generated',
        ];
        $byLabel = [];
        foreach ($rows as $row) {
            $byLabel[$row['label'] ?? ''] = $row;
        }
        $out = [];
        foreach ($order as $label) {
            if (isset($byLabel[$label])) {
                $out[] = $byLabel[$label];
            }
        }

        return $out;
    }

    /**
     * @return array<string, mixed>
     */
    private function emptyAnalysis(): array
    {
        return [
            'total' => 0,
            'full_total' => null,
            'entrepreneur' => [],
            'sectors' => [],
            'other_breakdown' => [],
            'business_stats' => [],
            'income_slabs' => [],
            'accuracy_checks' => [],
            'onboarding_breakdown' => [],
        ];
    }

    private function isOnboardedScope(): bool
    {
        return $this->dataScope === 'onboarded';
    }

    /** @return list<string> */
    private function p2Norms(string $name): array
    {
        $base = [mb_strtolower(trim($name))];

        return array_values(array_unique(array_merge($base, self::P2_ALIASES[$name] ?? [])));
    }

    private function legacyPhase2OnboardOk(): bool
    {
        return $this->legacyPhase2Ok && $this->legacyHasTable('rbi_onboarded_applicants');
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
