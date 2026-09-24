<?php

namespace App\Services\MarketLinkages;

use App\Models\District;
use App\Models\Hub;
use App\Models\User;
use App\Support\MarketLinkageUnifiedListingSupport;
use App\Support\PotentialLakhpatiOnboardingSql;
use Illuminate\Pagination\LengthAwarePaginator;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;

final class MarketLinkageCoverageService
{
    /**
     * @return array{
     *     rows: LengthAwarePaginator<int, array<string, mixed>>,
     *     summary: array{onboarded: int, linked: int, not_linked: int, pending: int},
     *     filters: array<string, mixed>,
     *     hubs: Collection<int, Hub>,
     *     districts: Collection<int, District>,
     *     sectors: list<string>,
     *     blocks: list<string>,
     *     scopeLabel: string,
     *     activeCoverage: string
     * }
     */
    public function paginatedForUser(User $user, array $filters, int $perPage = 50): array
    {
        $scope = $this->resolveScope($user);
        $filters = $this->normalizeFilters($filters, $scope);
        $districtIds = $this->effectiveDistrictIds($scope, $filters);

        $modeMap = MarketLinkageUnifiedListingSupport::approvedLinkedIncubateeModeMap($districtIds);
        $approvedKeys = array_fill_keys(array_keys($modeMap), true);
        $pendingKeys = MarketLinkageUnifiedListingSupport::pendingMarketLinkageIncubateeKeySet($districtIds, $approvedKeys);

        $allRows = $this->fetchOnboardedRows($scope, $filters, $approvedKeys, $pendingKeys, $modeMap);
        $summary = $this->summarize($allRows);

        $activeCoverage = (string) ($filters['coverage'] ?? 'all');
        $filtered = array_values(array_filter(
            $allRows,
            static fn (array $row): bool => match ($activeCoverage) {
                'linked' => $row['coverage_status'] === 'linked',
                'not_linked' => $row['coverage_status'] === 'not_linked',
                'pending' => $row['coverage_status'] === 'pending',
                default => true,
            },
        ));

        $page = max(1, (int) request()->query('page', 1));
        $total = count($filtered);
        $slice = array_slice($filtered, ($page - 1) * $perPage, $perPage);

        $paginator = new LengthAwarePaginator(
            $slice,
            $total,
            $perPage,
            $page,
            ['path' => request()->url(), 'query' => request()->query()],
        );

        return [
            'rows' => $paginator,
            'summary' => $summary,
            'filters' => $filters,
            'hubs' => $this->hubOptions($scope),
            'districts' => $this->districtOptions($scope, (int) ($filters['hub_id'] ?? 0)),
            'sectors' => $this->sectorOptions($scope, $filters),
            'blocks' => $this->blockOptions($scope, $filters),
            'scopeLabel' => $this->scopeLabel($user, $scope),
            'activeCoverage' => $activeCoverage,
        ];
    }

    /**
     * @return list<array<string, mixed>>
     */
    public function exportRowsForUser(User $user, array $filters): array
    {
        $pack = $this->paginatedForUser($user, $filters, perPage: 100000);

        return $pack['rows']->items();
    }

    /**
     * @return array{hub_id: int|null, district_ids: list<int>|null}
     */
    public function resolveScope(User $user): array
    {
        if ($user->role === 'hub_admin' && (int) ($user->hub_id ?? 0) > 0) {
            return [
                'hub_id' => (int) $user->hub_id,
                'district_ids' => District::query()
                    ->where('hub_id', (int) $user->hub_id)
                    ->pluck('id')
                    ->map(fn ($id) => (int) $id)
                    ->all(),
            ];
        }

        if ($user->role === 'district_staff' && (int) ($user->district_id ?? 0) > 0) {
            return [
                'hub_id' => null,
                'district_ids' => [(int) $user->district_id],
            ];
        }

        return ['hub_id' => null, 'district_ids' => null];
    }

    /**
     * @param  array<string, mixed>  $filters
     * @return array<string, mixed>
     */
    public function normalizeFilters(array $filters, array $scope): array
    {
        $coverage = trim((string) ($filters['coverage'] ?? 'all'));
        if (! in_array($coverage, ['all', 'linked', 'not_linked', 'pending'], true)) {
            $coverage = 'all';
        }

        $hubId = (int) ($filters['hub_id'] ?? 0);
        $districtId = (int) ($filters['district_id'] ?? 0);

        if ($scope['district_ids'] !== null) {
            if ($districtId > 0 && ! in_array($districtId, $scope['district_ids'], true)) {
                $districtId = 0;
            }
            if ($scope['hub_id'] !== null) {
                $hubId = (int) $scope['hub_id'];
            }
        }

        return [
            'hub_id' => $hubId > 0 ? $hubId : null,
            'district_id' => $districtId > 0 ? $districtId : null,
            'sector' => trim((string) ($filters['sector'] ?? '')),
            'block' => trim((string) ($filters['block'] ?? '')),
            'q' => trim((string) ($filters['q'] ?? '')),
            'coverage' => $coverage,
        ];
    }

    /**
     * @param  array{hub_id: int|null, district_ids: list<int>|null}  $scope
     * @param  array<string, mixed>  $filters
     * @return list<int>|null
     */
    private function effectiveDistrictIds(array $scope, array $filters): ?array
    {
        $allowed = $scope['district_ids'];

        if ($allowed === []) {
            return [];
        }

        $districtId = $filters['district_id'] ?? null;
        if ($districtId !== null && (int) $districtId > 0) {
            $districtId = (int) $districtId;
            if ($allowed !== null && ! in_array($districtId, $allowed, true)) {
                return [];
            }

            return [$districtId];
        }

        $hubId = $filters['hub_id'] ?? null;
        if ($hubId !== null && (int) $hubId > 0) {
            $hubDistricts = District::query()
                ->where('hub_id', (int) $hubId)
                ->pluck('id')
                ->map(fn ($id) => (int) $id)
                ->all();

            if ($allowed !== null) {
                $hubDistricts = array_values(array_intersect($hubDistricts, $allowed));
            }

            return $hubDistricts === [] ? [] : $hubDistricts;
        }

        return $allowed;
    }

    /**
     * @param  array{hub_id: int|null, district_ids: list<int>|null}  $scope
     * @param  array<string, mixed>  $filters
     * @param  array<string, true>  $approvedKeys
     * @param  array<string, true>  $pendingKeys
     * @param  array<string, list<string>>  $modeMap
     * @return list<array<string, mixed>>
     */
    private function fetchOnboardedRows(array $scope, array $filters, array $approvedKeys, array $pendingKeys, array $modeMap): array
    {
        $query = $this->onboardedBaseQuery($scope);
        $this->applyFilters($query, $filters, $scope);

        $sectorPrimary = PotentialLakhpatiOnboardingSql::payloadJson('$.business_category');
        $sectorAlt = PotentialLakhpatiOnboardingSql::payloadJson('$.app_business_category');
        $sectorLegacy = PotentialLakhpatiOnboardingSql::payloadJson('$.sector');
        $blockJson = PotentialLakhpatiOnboardingSql::payloadJson('$.block');

        $rows = $query
            ->orderBy('cs.applicant_name')
            ->selectRaw("
                cs.id as cfa_submission_id,
                cs.application_no,
                cs.applicant_name,
                cs.phone,
                cs.district_id,
                d.name as district_name,
                h.name as hub_name,
                ob.name as batch_name,
                COALESCE({$sectorPrimary}, {$sectorAlt}, {$sectorLegacy}, '') as sector,
                COALESCE({$blockJson}, '') as block_name
            ")
            ->get();

        $result = [];
        foreach ($rows as $row) {
            $key = 'c:'.(int) $row->cfa_submission_id;
            $coverageStatus = isset($approvedKeys[$key])
                ? 'linked'
                : (isset($pendingKeys[$key]) ? 'pending' : 'not_linked');

            $modes = $modeMap[$key] ?? [];

            $result[] = [
                'cfa_submission_id' => (int) $row->cfa_submission_id,
                'application_no' => (string) $row->application_no,
                'applicant_name' => (string) $row->applicant_name,
                'phone' => (string) ($row->phone ?? ''),
                'district_id' => (int) $row->district_id,
                'district_name' => (string) ($row->district_name ?? '—'),
                'hub_name' => (string) ($row->hub_name ?? '—'),
                'batch_name' => (string) ($row->batch_name ?? '—'),
                'sector' => trim((string) ($row->sector ?? '')) ?: 'Not recorded',
                'block_name' => trim((string) ($row->block_name ?? '')) ?: '—',
                'coverage_status' => $coverageStatus,
                'coverage_label' => match ($coverageStatus) {
                    'linked' => 'Linked (6.3)',
                    'pending' => 'Pending approval',
                    default => 'Not linked',
                },
                'linkage_mode' => $modes !== [] ? implode(', ', $modes) : '—',
            ];
        }

        return $result;
    }

    /**
     * @param  list<array<string, mixed>>  $rows
     * @return array{onboarded: int, linked: int, not_linked: int, pending: int}
     */
    private function summarize(array $rows): array
    {
        $linked = 0;
        $notLinked = 0;
        $pending = 0;

        foreach ($rows as $row) {
            match ($row['coverage_status']) {
                'linked' => $linked++,
                'pending' => $pending++,
                default => $notLinked++,
            };
        }

        return [
            'onboarded' => count($rows),
            'linked' => $linked,
            'not_linked' => $notLinked,
            'pending' => $pending,
        ];
    }

    /**
     * @param  array{hub_id: int|null, district_ids: list<int>|null}  $scope
     */
    private function onboardedBaseQuery(array $scope)
    {
        $query = DB::table('onboarding_batch_cfa as obc')
            ->join('onboarding_batches as ob', 'ob.id', '=', 'obc.onboarding_batch_id')
            ->join('cfa_submissions as cs', 'cs.id', '=', 'obc.cfa_submission_id')
            ->leftJoin('districts as d', 'd.id', '=', 'cs.district_id')
            ->leftJoin('hubs as h', 'h.id', '=', 'ob.hub_id')
            ->where('ob.status', 'locked')
            ->whereNotNull('ob.locked_at')
            ->whereNotNull('obc.cfa_submission_id');

        $allowed = $scope['district_ids'] ?? null;
        if ($allowed === []) {
            $query->whereRaw('1 = 0');
        } elseif ($allowed !== null) {
            $query->whereIn('cs.district_id', $allowed);
        }

        return $query;
    }

    /**
     * @param  array{hub_id: int|null, district_ids: list<int>|null}  $scope
     * @param  array<string, mixed>  $filters
     */
    private function applyFilters($query, array $filters, array $scope): void
    {
        if (! empty($filters['hub_id'])) {
            $query->where('ob.hub_id', (int) $filters['hub_id']);
        }

        if (! empty($filters['district_id'])) {
            $query->where('cs.district_id', (int) $filters['district_id']);
        }

        $sectorPrimary = PotentialLakhpatiOnboardingSql::payloadJson('$.business_category');
        $sectorAlt = PotentialLakhpatiOnboardingSql::payloadJson('$.app_business_category');
        $sectorLegacy = PotentialLakhpatiOnboardingSql::payloadJson('$.sector');
        $blockJson = PotentialLakhpatiOnboardingSql::payloadJson('$.block');

        if (($filters['sector'] ?? '') !== '') {
            $sector = (string) $filters['sector'];
            $query->whereRaw(
                "LOWER(TRIM(COALESCE({$sectorPrimary}, {$sectorAlt}, {$sectorLegacy}, ''))) = ?",
                [mb_strtolower($sector)],
            );
        }

        if (($filters['block'] ?? '') !== '') {
            $query->whereRaw(
                "LOWER(TRIM(COALESCE({$blockJson}, ''))) = ?",
                [mb_strtolower((string) $filters['block'])],
            );
        }

        $q = (string) ($filters['q'] ?? '');
        if ($q !== '') {
            $like = '%'.$q.'%';
            $query->where(function ($inner) use ($like): void {
                $inner->where('cs.application_no', 'like', $like)
                    ->orWhere('cs.applicant_name', 'like', $like)
                    ->orWhere('cs.phone', 'like', $like);
            });
        }

        unset($scope);
    }

    /**
     * @param  array{hub_id: int|null, district_ids: list<int>|null}  $scope
     * @return Collection<int, Hub>
     */
    private function hubOptions(array $scope): Collection
    {
        $query = Hub::query()->orderBy('sort_order')->orderBy('name');
        if ($scope['hub_id'] !== null) {
            $query->where('id', (int) $scope['hub_id']);
        }

        return $query->get(['id', 'name']);
    }

    /**
     * @param  array{hub_id: int|null, district_ids: list<int>|null}  $scope
     * @return Collection<int, District>
     */
    private function districtOptions(array $scope, int $hubId): Collection
    {
        $query = District::query()->orderBy('name');

        if ($hubId > 0) {
            $query->where('hub_id', $hubId);
        } elseif ($scope['hub_id'] !== null) {
            $query->where('hub_id', (int) $scope['hub_id']);
        }

        if ($scope['district_ids'] !== null) {
            $query->whereIn('id', $scope['district_ids']);
        }

        return $query->get(['id', 'name', 'hub_id']);
    }

    /**
     * @param  array{hub_id: int|null, district_ids: list<int>|null}  $scope
     * @param  array<string, mixed>  $filters
     * @return list<string>
     */
    private function sectorOptions(array $scope, array $filters): array
    {
        $query = $this->onboardedBaseQuery($scope);
        $this->applyFilters($query, array_merge($filters, ['sector' => '', 'block' => '']), $scope);

        $sectorPrimary = PotentialLakhpatiOnboardingSql::payloadJson('$.business_category');
        $sectorAlt = PotentialLakhpatiOnboardingSql::payloadJson('$.app_business_category');
        $sectorLegacy = PotentialLakhpatiOnboardingSql::payloadJson('$.sector');

        return $query
            ->selectRaw("DISTINCT TRIM(COALESCE({$sectorPrimary}, {$sectorAlt}, {$sectorLegacy}, '')) as sector")
            ->orderBy('sector')
            ->pluck('sector')
            ->map(fn ($value) => trim((string) $value))
            ->filter(fn (string $value) => $value !== '')
            ->values()
            ->all();
    }

    /**
     * @param  array{hub_id: int|null, district_ids: list<int>|null}  $scope
     * @param  array<string, mixed>  $filters
     * @return list<string>
     */
    private function blockOptions(array $scope, array $filters): array
    {
        $query = $this->onboardedBaseQuery($scope);
        $this->applyFilters($query, array_merge($filters, ['block' => '']), $scope);

        $blockJson = PotentialLakhpatiOnboardingSql::payloadJson('$.block');

        return $query
            ->selectRaw("DISTINCT TRIM(COALESCE({$blockJson}, '')) as block_name")
            ->orderBy('block_name')
            ->pluck('block_name')
            ->map(fn ($value) => trim((string) $value))
            ->filter(fn (string $value) => $value !== '')
            ->values()
            ->all();
    }

    /**
     * @param  array{hub_id: int|null, district_ids: list<int>|null}  $scope
     */
    private function scopeLabel(User $user, array $scope): string
    {
        $user->loadMissing(['hub', 'district']);

        if ($user->role === 'hub_admin') {
            return trim((string) ($user->hub?->name ?? 'Hub')).' · onboarded incubatees';
        }

        if ($user->role === 'district_staff') {
            return trim((string) ($user->district?->name ?? 'District')).' · onboarded incubatees';
        }

        return 'Statewide · locked onboarding batches';
    }
}
