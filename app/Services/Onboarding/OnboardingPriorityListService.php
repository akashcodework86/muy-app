<?php

namespace App\Services\Onboarding;

use App\Models\CfaSubmission;
use App\Models\District;
use App\Models\FiscalYear;
use App\Models\User;
use App\Services\Cfa\CfaSubmissionListQuery;
use App\Support\OnboardingPriorityAccess;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Pagination\LengthAwarePaginator;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\File;

final class OnboardingPriorityListService
{
    public const PER_PAGE = 100;

    /** File index TTL — pagination reuses this instead of re-scoring ~20k rows. */
    private const INDEX_TTL_SECONDS = 900;

    public function __construct(
        private readonly OnboardingPriorityMatrixScorer $scorer,
    ) {}

    /**
     * @return array{
     *     rows: LengthAwarePaginator<int, array<string, mixed>>,
     *     stageCounts: array{seed: int, early: int, growth: int, unknown: int, all: int},
     *     districts: Collection<int, District>,
     *     fiscalYear: FiscalYear|null,
     *     scopeLabel: string,
     *     activeStage: string,
     *     districtFilter: int|null,
     *     showDistrictFilter: bool
     * }
     */
    public function paginatedForUser(
        User $user,
        string $stageFilter = '',
        ?int $districtFilter = null,
        string $search = '',
        int $perPage = self::PER_PAGE,
    ): array {
        $districtIds = OnboardingPriorityAccess::visibleDistrictIds($user);
        $activeStage = match (true) {
            $stageFilter === 'all' => 'all',
            in_array($stageFilter, ['seed', 'early', 'growth'], true) => $stageFilter,
            default => 'seed',
        };
        $districtFilter = $this->resolveDistrictFilter($districtFilter, $districtIds);
        $fiscalYear = FiscalYear::phase3Default();
        $districts = $this->districtOptions($districtIds);

        $pack = $this->loadOrBuildIndex($user, $districtIds, $districtFilter, $fiscalYear, $search);
        $index = $pack['rows'];
        $stageCounts = $pack['stageCounts'];

        $stageRankMeta = $this->stageRankMeta($index);

        $ranked = [];
        foreach ($index as $row) {
            if ($activeStage !== 'all' && $row['stage_key'] !== $activeStage) {
                continue;
            }
            $meta = $stageRankMeta[$row['id']] ?? ['rank' => null, 'label' => '—'];
            $ranked[] = [
                'id' => $row['id'],
                'score' => $row['score'],
                'stage_key' => $row['stage_key'],
                'district_id' => $row['district_id'],
                'application_no' => $row['application_no'],
                'stage_rank' => $meta['rank'],
                'rank_label' => $meta['label'],
                'rank' => $meta['rank'],
            ];
        }

        usort($ranked, static function (array $a, array $b) use ($activeStage): int {
            if ($activeStage === 'all') {
                $stageOrder = ['seed' => 0, 'early' => 1, 'growth' => 2];
                $aStage = $stageOrder[$a['stage_key']] ?? 99;
                $bStage = $stageOrder[$b['stage_key']] ?? 99;
                if ($aStage !== $bStage) {
                    return $aStage <=> $bStage;
                }

                $byStageRank = ($a['stage_rank'] ?? 999) <=> ($b['stage_rank'] ?? 999);
                if ($byStageRank !== 0) {
                    return $byStageRank;
                }
            } else {
                $byScore = $b['score'] <=> $a['score'];
                if ($byScore !== 0) {
                    return $byScore;
                }
            }

            return strcmp((string) $a['application_no'], (string) $b['application_no']);
        });

        $page = max(1, (int) request()->query('page', 1));
        $total = count($ranked);
        $slice = array_slice($ranked, ($page - 1) * $perPage, $perPage);
        $slice = $this->hydratePageDetails($slice);

        $paginator = new LengthAwarePaginator(
            $slice,
            $total,
            $perPage,
            $page,
            ['path' => request()->url(), 'query' => request()->query()],
        );

        return [
            'rows' => $paginator,
            'stageCounts' => $stageCounts,
            'districts' => $districts,
            'fiscalYear' => $fiscalYear,
            'scopeLabel' => OnboardingPriorityAccess::scopeLabel($user),
            'activeStage' => $activeStage,
            'districtFilter' => $districtFilter,
            'showDistrictFilter' => $districtIds === null || $districts->count() > 1,
        ];
    }

    /**
     * @return list<array<string, mixed>>
     */
    public function exportRowsForUser(
        User $user,
        string $stageFilter = '',
        ?int $districtFilter = null,
        string $search = '',
    ): array {
        $districtIds = OnboardingPriorityAccess::visibleDistrictIds($user);
        $activeStage = match (true) {
            $stageFilter === 'all' => 'all',
            in_array($stageFilter, ['seed', 'early', 'growth'], true) => $stageFilter,
            default => 'seed',
        };
        $districtFilter = $this->resolveDistrictFilter($districtFilter, $districtIds);
        $fiscalYear = FiscalYear::phase3Default();

        $pack = $this->loadOrBuildIndex($user, $districtIds, $districtFilter, $fiscalYear, $search);
        $index = $pack['rows'];
        $stageRankMeta = $this->stageRankMeta($index);

        $ranked = [];
        foreach ($index as $row) {
            if ($activeStage !== 'all' && $row['stage_key'] !== $activeStage) {
                continue;
            }
            $meta = $stageRankMeta[$row['id']] ?? ['rank' => null, 'label' => '—'];
            $ranked[] = [
                'id' => $row['id'],
                'score' => $row['score'],
                'stage_key' => $row['stage_key'],
                'district_id' => $row['district_id'],
                'application_no' => $row['application_no'],
                'stage_rank' => $meta['rank'],
                'rank_label' => $meta['label'],
                'rank' => $meta['rank'],
            ];
        }

        usort($ranked, static function (array $a, array $b) use ($activeStage): int {
            if ($activeStage === 'all') {
                $stageOrder = ['seed' => 0, 'early' => 1, 'growth' => 2];
                $aStage = $stageOrder[$a['stage_key']] ?? 99;
                $bStage = $stageOrder[$b['stage_key']] ?? 99;
                if ($aStage !== $bStage) {
                    return $aStage <=> $bStage;
                }

                return ($a['stage_rank'] ?? 999) <=> ($b['stage_rank'] ?? 999);
            }

            $byScore = $b['score'] <=> $a['score'];
            if ($byScore !== 0) {
                return $byScore;
            }

            return strcmp((string) $a['application_no'], (string) $b['application_no']);
        });

        $hydrated = [];
        foreach (array_chunk($ranked, self::PER_PAGE) as $chunk) {
            foreach ($this->hydratePageDetails($chunk) as $row) {
                $hydrated[] = $row;
            }
        }

        return $hydrated;
    }

    /**
     * @param  list<int>|null  $districtIds
     * @return array{rows: list<array{id: int, score: float, stage_key: string, district_id: int, application_no: string}>, stageCounts: array{seed: int, early: int, growth: int, unknown: int, all: int}}
     */
    private function loadOrBuildIndex(
        User $user,
        ?array $districtIds,
        ?int $districtFilter,
        ?FiscalYear $fiscalYear,
        string $search,
    ): array {
        if (app()->environment('testing')) {
            return $this->buildCompactIndex($districtIds, $districtFilter, $fiscalYear, $search);
        }

        $fingerprint = $this->scopeFingerprint($districtIds, $districtFilter, $fiscalYear, $search);
        $hash = hash('xxh3', json_encode([
            'role' => $user->role,
            'user' => (int) $user->id,
            'hub' => (int) ($user->hub_id ?? 0),
            'districts' => $districtIds,
            'district' => $districtFilter,
            'search' => trim($search),
            'fy' => $fiscalYear?->id,
            'fp' => $fingerprint,
            'v' => 3,
        ], JSON_THROW_ON_ERROR));

        $dir = storage_path('app/onboarding-priority');
        File::ensureDirectoryExists($dir);
        $path = $dir.DIRECTORY_SEPARATOR.$hash.'.idx';

        if (is_file($path) && (time() - (int) filemtime($path)) < self::INDEX_TTL_SECONDS) {
            $cached = @unserialize((string) file_get_contents($path), ['allowed_classes' => false]);
            if (is_array($cached) && isset($cached['rows'], $cached['stageCounts'])) {
                /** @var array{rows: list<array{id: int, score: float, stage_key: string, district_id: int, application_no: string}>, stageCounts: array{seed: int, early: int, growth: int, unknown: int, all: int}} $cached */
                return $cached;
            }
        }

        $pack = $this->buildCompactIndex($districtIds, $districtFilter, $fiscalYear, $search);
        file_put_contents($path, serialize($pack), LOCK_EX);

        return $pack;
    }

    /**
     * @param  list<int>|null  $districtIds
     * @return array{count: int, max_id: int, apps_hash: string}
     */
    private function scopeFingerprint(
        ?array $districtIds,
        ?int $districtFilter,
        ?FiscalYear $fiscalYear,
        string $search,
    ): array {
        $row = $this->baseQuery($districtIds, $districtFilter, $fiscalYear, $search)
            ->toBase()
            ->selectRaw('count(*) as aggregate_count, coalesce(max(id), 0) as max_id, md5(coalesce(group_concat(application_no order by id separator "|"), "")) as apps_hash')
            ->first();

        return [
            'count' => (int) ($row->aggregate_count ?? 0),
            'max_id' => (int) ($row->max_id ?? 0),
            'apps_hash' => (string) ($row->apps_hash ?? ''),
        ];
    }

    /**
     * @param  list<int>|null  $districtIds
     * @return array{rows: list<array{id: int, score: float, stage_key: string, district_id: int, application_no: string}>, stageCounts: array{seed: int, early: int, growth: int, unknown: int, all: int}}
     */
    private function buildCompactIndex(
        ?array $districtIds,
        ?int $districtFilter,
        ?FiscalYear $fiscalYear,
        string $search,
    ): array {
        @set_time_limit(300);
        @ini_set('memory_limit', '512M');

        $index = [];
        $stageCounts = ['seed' => 0, 'early' => 0, 'growth' => 0, 'unknown' => 0, 'all' => 0];

        // Base query builder avoids Eloquent cast of huge JSON payloads into PHP arrays in bulk.
        $this->baseQuery($districtIds, $districtFilter, $fiscalYear, $search)
            ->toBase()
            ->select(['id', 'district_id', 'application_no', 'payload'])
            ->orderBy('id')
            ->chunkById(75, function (Collection $rows) use (&$index, &$stageCounts): void {
                foreach ($rows as $row) {
                    $payloadRaw = $row->payload ?? '[]';
                    $payload = is_string($payloadRaw)
                        ? (json_decode($payloadRaw, true) ?: [])
                        : (is_array($payloadRaw) ? $payloadRaw : []);

                    $result = $this->scorer->score($payload);
                    unset($payload, $payloadRaw);

                    $stage = $result['stage'];
                    if (isset($stageCounts[$stage])) {
                        $stageCounts[$stage]++;
                    } else {
                        $stageCounts['unknown']++;
                    }
                    $stageCounts['all']++;

                    $index[] = [
                        'id' => (int) $row->id,
                        'score' => (float) $result['total'],
                        'stage_key' => $stage,
                        'district_id' => (int) $row->district_id,
                        'application_no' => (string) $row->application_no,
                    ];
                }
            });

        return [
            'rows' => $index,
            'stageCounts' => $stageCounts,
        ];
    }

    /**
     * @param  list<array<string, mixed>>  $slice
     * @return list<array<string, mixed>>
     */
    private function hydratePageDetails(array $slice): array
    {
        if ($slice === []) {
            return $slice;
        }

        $ids = array_map(static fn (array $row): int => (int) $row['id'], $slice);
        $districtNames = District::query()->pluck('name', 'id');

        $submissions = CfaSubmission::query()
            ->whereIn('id', $ids)
            ->get(['id', 'application_no', 'applicant_name', 'phone', 'district_id', 'payload', 'created_at'])
            ->keyBy('id');

        foreach ($slice as &$row) {
            $submission = $submissions->get((int) $row['id']);
            $payload = is_array($submission?->payload) ? $submission->payload : [];
            $result = $this->scorer->score($payload);

            $row['application_no'] = (string) ($submission?->application_no ?? $row['application_no'] ?? '');
            $row['applicant_name'] = (string) ($submission?->applicant_name ?? '—');
            $row['phone'] = (string) ($submission?->phone ?? '');
            $row['district_name'] = (string) ($districtNames[(int) ($row['district_id'] ?? 0)] ?? '—');
            $row['block_name'] = trim((string) ($payload['block'] ?? '')) ?: '—';
            $row['stage'] = ucfirst((string) ($row['stage_key'] ?? $result['stage']));
            $row['score'] = $result['total'];
            $row['dimensions'] = $result['dimensions'];
            $row['highlights'] = $result['highlights'];
            $row['submitted_at'] = optional($submission?->created_at)->format('d M Y');
        }
        unset($row);

        return $slice;
    }

    /**
     * @param  list<int>|null  $districtIds
     */
    private function baseQuery(?array $districtIds, ?int $districtFilter, ?FiscalYear $fiscalYear, string $search): Builder
    {
        $query = CfaSubmission::query();

        CfaSubmissionListQuery::applyPhase3DashboardScope($query);
        CfaSubmissionListQuery::applyOnboardFilter($query, 'non_onboarded');

        if ($districtIds === []) {
            $query->whereRaw('1 = 0');
        } elseif ($districtIds !== null) {
            $query->whereIn('district_id', $districtIds);
        }

        if ($districtFilter !== null && $districtFilter > 0) {
            $query->where('district_id', $districtFilter);
        }

        if ($fiscalYear !== null) {
            $query->where('fiscal_year_id', (int) $fiscalYear->id);
        }

        $search = trim($search);
        if ($search !== '') {
            $query->where(function (Builder $inner) use ($search): void {
                $inner->where('application_no', 'like', '%'.$search.'%')
                    ->orWhere('applicant_name', 'like', '%'.$search.'%')
                    ->orWhere('phone', 'like', '%'.$search.'%');
            });
        }

        return $query;
    }

    /**
     * @param  list<int>|null  $districtIds
     */
    private function resolveDistrictFilter(?int $districtFilter, ?array $districtIds): ?int
    {
        if ($districtFilter === null || $districtFilter <= 0) {
            return null;
        }

        if ($districtIds === null) {
            return $districtFilter;
        }

        return in_array($districtFilter, $districtIds, true) ? $districtFilter : null;
    }

    /**
     * @param  list<int>|null  $districtIds
     * @return Collection<int, District>
     */
    private function districtOptions(?array $districtIds): Collection
    {
        $query = District::query()->orderBy('name');

        if ($districtIds === []) {
            return collect();
        }

        if ($districtIds !== null) {
            $query->whereIn('id', $districtIds);
        }

        return $query->get(['id', 'name']);
    }

    /**
     * @param  list<array{id: int, score: float, stage_key: string, application_no: string}>  $rows
     * @return array<int, array{rank: int|null, label: string}>
     */
    private function stageRankMeta(array $rows): array
    {
        $grouped = [];
        foreach ($rows as $row) {
            $grouped[(string) ($row['stage_key'] ?? 'unknown')][] = $row;
        }

        $meta = [];
        foreach ($grouped as $stageKey => $stageRows) {
            usort($stageRows, static function (array $a, array $b): int {
                $byScore = $b['score'] <=> $a['score'];
                if ($byScore !== 0) {
                    return $byScore;
                }

                return strcmp((string) $a['application_no'], (string) $b['application_no']);
            });

            $labelPrefix = ucfirst($stageKey);
            foreach ($stageRows as $index => $row) {
                $rank = $index + 1;
                $meta[(int) $row['id']] = [
                    'rank' => $rank,
                    'label' => $labelPrefix.' #'.$rank,
                ];
            }
        }

        return $meta;
    }
}
