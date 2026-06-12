<?php

namespace App\Services;

use App\Models\PitchDeckPreparation;
use App\Models\ServiceCase;
use App\Support\PitchDeckCombinedDeliverablesSupport;
use Carbon\Carbon;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Pagination\LengthAwarePaginator as Paginator;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

class PitchDeckUnifiedDashboardService
{
    public function __construct(
        private readonly LegacyApplicationServiceCaseSupport $legacyServiceCases,
        private readonly PitchDeckIncubateeCatalogService $incubateeCatalog,
    ) {}

    /**
     * @param  array{q?: string, from?: string, to?: string, district_id?: int, filled_by?: string, state_team_user_id?: int}  $filters
     * @return array{
     *   rows: LengthAwarePaginator,
     *   totals: array{total: int, services: int, state_team: int},
     *   incubateeProfiles: array<int, array<string, mixed>>
     * }
     */
    public function paginatedDashboard(array $filters, int $perPage = 25, bool $includeServices = true): array
    {
        $rows = $this->collectRows($filters, $includeServices);
        $totals = [
            'total' => $rows->count(),
            'services' => $rows->where('source_channel', PitchDeckCombinedDeliverablesSupport::CHANNEL_SERVICES)->count(),
            'state_team' => $rows->where('source_channel', PitchDeckCombinedDeliverablesSupport::CHANNEL_STATE_TEAM)->count(),
        ];

        $filtered = $this->applyFilledByFilter($rows, (string) ($filters['filled_by'] ?? ''));
        $totalsFiltered = [
            'total' => $filtered->count(),
            'services' => $filtered->where('source_channel', PitchDeckCombinedDeliverablesSupport::CHANNEL_SERVICES)->count(),
            'state_team' => $filtered->where('source_channel', PitchDeckCombinedDeliverablesSupport::CHANNEL_STATE_TEAM)->count(),
        ];

        $page = max(1, (int) request()->query('page', 1));
        $slice = $filtered->forPage($page, $perPage)->values();

        $paginator = new Paginator(
            $slice,
            $filtered->count(),
            $perPage,
            $page,
            ['path' => request()->url(), 'query' => request()->query()],
        );

        $profiles = [];
        foreach ($slice as $row) {
            $rowId = (int) ($row['id'] ?? 0);
            if (! empty($row['incubatee_profile']) && is_array($row['incubatee_profile'])) {
                $profiles[$rowId] = $row['incubatee_profile'];
            } elseif (($row['source_channel'] ?? '') === PitchDeckCombinedDeliverablesSupport::CHANNEL_STATE_TEAM && ! empty($row['preparation_model'])) {
                $profiles[$rowId] = $this->incubateeCatalog->profileForPreparation($row['preparation_model']);
            }
        }

        return [
            'rows' => $paginator,
            'totals' => $totalsFiltered,
            'totals_unfiltered' => $totals,
            'incubateeProfiles' => $profiles,
        ];
    }

    /**
     * @param  array{q?: string, from?: string, to?: string, district_id?: int, filled_by?: string, state_team_user_id?: int}  $filters
     * @return Collection<int, array<string, mixed>>
     */
    public function exportRows(array $filters, bool $includeServices = true): Collection
    {
        $rows = $this->collectRows($filters, $includeServices);

        return $this->applyFilledByFilter($rows, (string) ($filters['filled_by'] ?? ''));
    }

    /**
     * @param  array{q?: string, from?: string, to?: string, district_id?: int, state_team_user_id?: int}  $filters
     * @return Collection<int, array<string, mixed>>
     */
    private function collectRows(array $filters, bool $includeServices = true): Collection
    {
        $serviceRows = $includeServices ? $this->serviceCaseRows($filters) : collect();
        $stateRows = $this->stateTeamRows($filters);

        return $serviceRows
            ->concat($stateRows)
            ->sortByDesc('sort_date')
            ->values();
    }

    /**
     * @param  array{q?: string, from?: string, to?: string, district_id?: int}  $filters
     */
    private function serviceCaseRows(array $filters): Collection
    {
        if (! Schema::hasTable('service_cases') || ! Schema::hasTable('services')) {
            return collect();
        }

        $serviceIds = PitchDeckCombinedDeliverablesSupport::pitchDeckServiceIds();
        if ($serviceIds === []) {
            return collect();
        }

        $dateExpr = $this->achievementDateExpression();

        $query = DB::table('service_cases as sc')
            ->join('services as s', 's.id', '=', 'sc.service_id')
            ->leftJoin('cfa_submissions as cs', 'cs.id', '=', 'sc.cfa_submission_id')
            ->leftJoin('districts as d', 'd.id', '=', 'cs.district_id')
            ->leftJoin('hubs as h', 'h.id', '=', 'd.hub_id')
            ->leftJoin('users as submitter', 'submitter.id', '=', 'sc.submitted_by')
            ->leftJoin('users as spoc', 'spoc.id', '=', 'sc.spoc_user_id')
            ->whereIn('sc.service_id', $serviceIds)
            ->whereIn('sc.status', [ServiceCase::STATUS_APPROVED, ServiceCase::STATUS_COMPLETED]);

        $districtId = (int) ($filters['district_id'] ?? 0);
        if ($districtId > 0) {
            $this->legacyServiceCases->applyAchievementDistrictScopeToServiceCaseQuery($query, [$districtId]);
        }

        $search = trim((string) ($filters['q'] ?? ''));
        if ($search !== '') {
            $like = '%'.str_replace(['\\', '%', '_'], ['\\\\', '\\%', '\\_'], $search).'%';
            $legacyIds = $this->legacyApplicationIdsMatchingSearch($search);
            $query->where(function ($q) use ($like, $legacyIds): void {
                $q->where('cs.applicant_name', 'like', $like)
                    ->orWhere('cs.application_no', 'like', $like)
                    ->orWhere('cs.phone', 'like', $like)
                    ->orWhere('d.name', 'like', $like)
                    ->orWhere('sc.reference_number', 'like', $like)
                    ->orWhere('submitter.name', 'like', $like)
                    ->orWhere('spoc.name', 'like', $like)
                    ->orWhere('s.name', 'like', $like);
                if ($legacyIds !== []) {
                    $q->orWhereIn('sc.legacy_application_id', $legacyIds);
                }
            });
        }

        $from = trim((string) ($filters['from'] ?? ''));
        $to = trim((string) ($filters['to'] ?? ''));
        if ($from !== '') {
            $query->whereDate(DB::raw($dateExpr), '>=', $from);
        }
        if ($to !== '') {
            $query->whereDate(DB::raw($dateExpr), '<=', $to);
        }

        $rawRows = $query
            ->select([
                'sc.id',
                'sc.cfa_submission_id',
                'sc.legacy_application_id',
                'sc.reference_number',
                'sc.status',
                'cs.applicant_name',
                'cs.application_no',
                'cs.phone as cfa_phone',
                'd.name as district_name',
                'h.name as hub_name',
                'submitter.name as submitted_by_name',
                'spoc.name as spoc_name',
                's.name as service_name',
            ])
            ->selectRaw("{$dateExpr} as achievement_date")
            ->orderByDesc(DB::raw($dateExpr))
            ->orderByDesc('sc.id')
            ->get();

        if ($rawRows->isEmpty()) {
            return collect();
        }

        $cfaIds = $rawRows
            ->pluck('cfa_submission_id')
            ->filter()
            ->map(fn ($id) => (int) $id)
            ->unique()
            ->values()
            ->all();
        $legacyIds = $rawRows
            ->pluck('legacy_application_id')
            ->filter()
            ->map(fn ($id) => (int) $id)
            ->unique()
            ->values()
            ->all();

        $profileBuckets = $this->incubateeCatalog->profilesByIncubateeIds($cfaIds, $legacyIds);

        return $rawRows->map(function ($row) use ($profileBuckets): array {
            $cfaId = (int) ($row->cfa_submission_id ?? 0);
            $legacyId = (int) ($row->legacy_application_id ?? 0);
            $profile = $cfaId > 0
                ? ($profileBuckets['cfa'][$cfaId] ?? null)
                : ($legacyId > 0 ? ($profileBuckets['legacy'][$legacyId] ?? null) : null);

            $incubateeName = trim((string) ($row->applicant_name ?? ''));
            if ($incubateeName === '' && is_array($profile)) {
                $incubateeName = trim((string) ($profile['name'] ?? ''));
            }

            $applicationNo = trim((string) ($row->application_no ?? ''));
            if ($applicationNo === '' && is_array($profile)) {
                $applicationNo = trim((string) ($profile['application_no'] ?? ''));
            }

            $districtName = trim((string) ($row->district_name ?? ''));
            if ($districtName === '' && is_array($profile)) {
                $districtName = trim((string) ($profile['district'] ?? ''));
            }

            $sourceLabel = $cfaId > 0
                ? 'Phase 3 CFA'
                : ($legacyId > 0 ? 'Phase 2 legacy' : 'Services');

            $filledByName = trim((string) ($row->submitted_by_name ?? ''));
            if ($filledByName === '') {
                $filledByName = trim((string) ($row->spoc_name ?? ''));
            }

            $dateRaw = trim((string) ($row->achievement_date ?? ''));
            $preparedOn = null;
            if ($dateRaw !== '') {
                try {
                    $preparedOn = Carbon::parse($dateRaw);
                } catch (\Throwable) {
                    $preparedOn = null;
                }
            }

            $reference = trim((string) ($row->reference_number ?? ''));

            return [
                'id' => (int) $row->id,
                'source_channel' => PitchDeckCombinedDeliverablesSupport::CHANNEL_SERVICES,
                'filled_by_label' => PitchDeckCombinedDeliverablesSupport::LABEL_SERVICES,
                'filled_by_name' => $filledByName !== '' ? $filledByName : '—',
                'incubatee_name' => $incubateeName !== '' ? $incubateeName : '—',
                'application_no' => $applicationNo,
                'reference_number' => $reference,
                'phone' => is_array($profile)
                    ? trim((string) ($profile['phone'] ?? ''))
                    : trim((string) ($row->cfa_phone ?? '')),
                'district_name' => $districtName !== '' ? $districtName : '—',
                'hub_name' => trim((string) ($row->hub_name ?? '')) ?: (is_array($profile) ? trim((string) ($profile['hub'] ?? '')) : ''),
                'prepared_on' => $preparedOn,
                'prepared_on_display' => $preparedOn?->format('d M Y') ?? '—',
                'prepared_for' => trim((string) ($row->service_name ?? '')) ?: 'Pitch deck service',
                'support_mode' => ucfirst(str_replace('_', ' ', (string) ($row->status ?? ''))),
                'entered_by_name' => $filledByName !== '' ? $filledByName : '—',
                'source_label' => $sourceLabel,
                'status_label' => ucfirst(str_replace('_', ' ', (string) ($row->status ?? ''))),
                'sort_date' => $dateRaw,
                'service_case_id' => (int) $row->id,
                'preparation_model' => null,
                'has_deck_file' => false,
                'incubatee_profile' => is_array($profile) ? $profile : [
                    'name' => $incubateeName !== '' ? $incubateeName : '—',
                    'application_no' => $applicationNo,
                    'source' => $sourceLabel,
                    'phone' => '',
                    'district' => $districtName !== '' ? $districtName : '—',
                    'hub' => '',
                    'is_onboarded' => false,
                    'onboarding_status' => '—',
                ],
            ];
        });
    }

    /**
     * @param  array{q?: string, from?: string, to?: string, district_id?: int}  $filters
     */
    private function stateTeamRows(array $filters): Collection
    {
        if (! Schema::hasTable('pitch_deck_preparations')) {
            return collect();
        }

        $query = PitchDeckPreparation::query()->with('district:id,name');

        $stateTeamUserId = (int) ($filters['state_team_user_id'] ?? 0);
        if ($stateTeamUserId > 0) {
            $query->where('entered_by_user_id', $stateTeamUserId);
        }

        $districtId = (int) ($filters['district_id'] ?? 0);
        if ($districtId > 0) {
            $query->where('district_id', $districtId);
        }

        $search = trim((string) ($filters['q'] ?? ''));
        if ($search !== '') {
            $like = '%'.str_replace(['\\', '%', '_'], ['\\\\', '\\%', '\\_'], $search).'%';
            $query->where(function ($q) use ($like): void {
                $q->where('incubatee_name', 'like', $like)
                    ->orWhere('application_no', 'like', $like)
                    ->orWhere('entered_by_name', 'like', $like)
                    ->orWhereHas('district', fn ($dq) => $dq->where('name', 'like', $like));
            });
        }

        $from = trim((string) ($filters['from'] ?? ''));
        $to = trim((string) ($filters['to'] ?? ''));
        if ($from !== '') {
            $query->whereDate('prepared_on', '>=', $from);
        }
        if ($to !== '') {
            $query->whereDate('prepared_on', '<=', $to);
        }

        return $query
            ->orderByDesc('prepared_on')
            ->orderByDesc('id')
            ->get()
            ->map(function (PitchDeckPreparation $row): array {
                $profile = $this->incubateeCatalog->profileForPreparation($row);

                return [
                    'id' => (int) $row->id,
                    'source_channel' => PitchDeckCombinedDeliverablesSupport::CHANNEL_STATE_TEAM,
                    'filled_by_label' => PitchDeckCombinedDeliverablesSupport::LABEL_STATE_TEAM,
                    'filled_by_name' => (string) $row->entered_by_name,
                    'incubatee_name' => (string) $row->incubatee_name,
                    'application_no' => (string) ($row->application_no ?? ''),
                    'reference_number' => (string) ($row->application_no ?: 'Entry #'.$row->id),
                    'phone' => (string) ($profile['phone'] ?? ''),
                    'district_name' => (string) ($row->district?->name ?? '—'),
                    'hub_name' => (string) ($profile['hub'] ?? ''),
                    'prepared_on' => $row->prepared_on,
                    'prepared_on_display' => $row->prepared_on?->format('d M Y') ?? '—',
                    'prepared_for' => (string) ($row->prepared_for ?? '—'),
                    'support_mode' => (string) ($row->formattedSupportMode() ?? '—'),
                    'entered_by_name' => (string) $row->entered_by_name,
                    'source_label' => $row->incubateeSourceLabel(),
                    'status_label' => 'State team entry',
                    'sort_date' => $row->prepared_on?->format('Y-m-d') ?? '',
                    'service_case_id' => null,
                    'preparation_model' => $row,
                    'has_deck_file' => (string) ($row->deck_file_path ?? '') !== '',
                    'incubatee_profile' => $profile,
                ];
            });
    }

    /**
     * @return list<int>
     */
    private function legacyApplicationIdsMatchingSearch(string $search): array
    {
        if (! $this->legacyServiceCases->legacyDbAvailable() || mb_strlen($search) < 2) {
            return [];
        }

        $like = '%'.str_replace(['\\', '%', '_'], ['\\\\', '\\%', '\\_'], $search).'%';

        try {
            return DB::connection('legacy')
                ->table('rbi_applicant_details as d')
                ->join('rbi_applications as a', 'a.id', '=', 'd.application_id')
                ->where(function ($q) use ($like): void {
                    $q->where('d.applicant_name', 'like', $like)
                        ->orWhere('a.application_no', 'like', $like)
                        ->orWhere('d.phone', 'like', $like)
                        ->orWhere('d.district', 'like', $like);
                })
                ->pluck('d.application_id')
                ->map(fn ($id) => (int) $id)
                ->filter(fn (int $id): bool => $id > 0)
                ->unique()
                ->values()
                ->all();
        } catch (\Throwable) {
            return [];
        }
    }

    /**
     * @param  Collection<int, array<string, mixed>>  $rows
     * @return Collection<int, array<string, mixed>>
     */
    private function applyFilledByFilter(Collection $rows, string $filledBy): Collection
    {
        return match ($filledBy) {
            PitchDeckCombinedDeliverablesSupport::CHANNEL_SERVICES => $rows
                ->where('source_channel', PitchDeckCombinedDeliverablesSupport::CHANNEL_SERVICES)
                ->values(),
            PitchDeckCombinedDeliverablesSupport::CHANNEL_STATE_TEAM => $rows
                ->where('source_channel', PitchDeckCombinedDeliverablesSupport::CHANNEL_STATE_TEAM)
                ->values(),
            default => $rows->values(),
        };
    }

    private function achievementDateExpression(): string
    {
        $parts = [];
        foreach (['approved_at', 'completed_at', 'delivered_on', 'submitted_at', 'created_at'] as $column) {
            if (Schema::hasColumn('service_cases', $column)) {
                $parts[] = 'sc.'.$column;
            }
        }

        if ($parts === []) {
            return 'sc.created_at';
        }

        return count($parts) === 1 ? $parts[0] : 'COALESCE('.implode(', ', $parts).')';
    }
}
