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

final class OnboardingPriorityListService
{
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
     *     districtFilter: int|null
     * }
     */
    public function paginatedForUser(
        User $user,
        string $stageFilter = '',
        ?int $districtFilter = null,
        string $search = '',
        int $perPage = 50,
    ): array {
        $districtIds = OnboardingPriorityAccess::visibleDistrictIds($user);
        $activeStage = in_array($stageFilter, ['seed', 'early', 'growth'], true) ? $stageFilter : '';
        $districtFilter = $this->resolveDistrictFilter($districtFilter, $districtIds);
        $fiscalYear = FiscalYear::phase3Default();

        $query = $this->baseQuery($districtIds, $districtFilter, $fiscalYear, $search);
        $submissions = $query->get(['id', 'application_no', 'applicant_name', 'phone', 'district_id', 'payload', 'created_at']);

        $scored = [];
        $stageCounts = ['seed' => 0, 'early' => 0, 'growth' => 0, 'unknown' => 0, 'all' => 0];

        foreach ($submissions as $submission) {
            $payload = is_array($submission->payload) ? $submission->payload : [];
            $result = $this->scorer->score($payload);
            $stage = $result['stage'];
            if (isset($stageCounts[$stage])) {
                $stageCounts[$stage]++;
            } else {
                $stageCounts['unknown']++;
            }
            $stageCounts['all']++;

            if ($activeStage !== '' && $stage !== $activeStage) {
                continue;
            }

            $scored[] = [
                'id' => (int) $submission->id,
                'application_no' => (string) $submission->application_no,
                'applicant_name' => (string) $submission->applicant_name,
                'phone' => (string) $submission->phone,
                'district_id' => (int) $submission->district_id,
                'district_name' => (string) ($submission->district?->name ?? '—'),
                'block_name' => trim((string) ($payload['block'] ?? '')) ?: '—',
                'stage' => ucfirst($stage),
                'stage_key' => $stage,
                'score' => $result['total'],
                'dimensions' => $result['dimensions'],
                'highlights' => $result['highlights'],
                'submitted_at' => optional($submission->created_at)->format('d M Y'),
            ];
        }

        usort($scored, static function (array $a, array $b): int {
            $byScore = $b['score'] <=> $a['score'];
            if ($byScore !== 0) {
                return $byScore;
            }

            return strcmp((string) $a['application_no'], (string) $b['application_no']);
        });

        foreach ($scored as $index => &$row) {
            $row['rank'] = $index + 1;
        }
        unset($row);

        $page = max(1, (int) request()->query('page', 1));
        $total = count($scored);
        $slice = array_slice($scored, ($page - 1) * $perPage, $perPage);

        $paginator = new LengthAwarePaginator(
            $slice,
            $total,
            $perPage,
            $page,
            ['path' => request()->url(), 'query' => request()->query()],
        );

        $districts = $this->districtOptions($districtIds);

        return [
            'rows' => $paginator,
            'stageCounts' => $stageCounts,
            'districts' => $districts,
            'fiscalYear' => $fiscalYear,
            'scopeLabel' => OnboardingPriorityAccess::scopeLabel($user),
            'activeStage' => $activeStage,
            'districtFilter' => $districtFilter,
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
        $pack = $this->paginatedForUser($user, $stageFilter, $districtFilter, $search, perPage: 100000);

        return $pack['rows']->items();
    }

    /**
     * @param  list<int>|null  $districtIds
     */
    private function baseQuery(?array $districtIds, ?int $districtFilter, ?FiscalYear $fiscalYear, string $search): Builder
    {
        $query = CfaSubmission::query()
            ->with(['district:id,name']);

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

        return $query->orderBy('application_no');
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
}
