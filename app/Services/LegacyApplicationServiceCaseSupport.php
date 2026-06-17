<?php

namespace App\Services;

use App\Models\District;
use App\Models\Service;
use App\Models\ServiceCase;
use App\Models\User;
use App\Support\IncubateeAttendeeCounts;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * Links rbiphase2 {@see rbi_applications} / applicant rows to Phase 3 service cases (no mirrored CFA required).
 */
class LegacyApplicationServiceCaseSupport
{
    public function legacyDbAvailable(): bool
    {
        if ((string) config('database.connections.legacy.database', '') === '') {
            return false;
        }
        try {
            return Schema::connection('legacy')->hasTable('rbi_applications')
                && Schema::connection('legacy')->hasTable('rbi_applicant_details');
        } catch (\Throwable) {
            return false;
        }
    }

    /**
     * Map legacy applicant district string to a Laravel {@see District} id (name + aliases).
     */
    public function laravelDistrictIdForLegacyApplication(int $legacyApplicationId): ?int
    {
        if (! $this->legacyDbAvailable()) {
            return null;
        }

        $districtName = DB::connection('legacy')
            ->table('rbi_applicant_details')
            ->where('application_id', $legacyApplicationId)
            ->value('district');

        if ($districtName === null || trim((string) $districtName) === '') {
            return null;
        }

        return $this->laravelDistrictIdForLegacyDistrictName((string) $districtName);
    }

    public function laravelDistrictIdForLegacyDistrictName(string $legacyDistrictName): ?int
    {
        $norm = mb_strtolower(trim($legacyDistrictName));
        if ($norm === '') {
            return null;
        }

        foreach (District::query()->orderBy('id')->cursor() as $district) {
            foreach ($this->districtDisplayNames($district) as $n) {
                if (mb_strtolower(trim($n)) === $norm) {
                    return (int) $district->id;
                }
            }
        }

        return null;
    }

    /**
     * District names / aliases that may appear on legacy {@see rbi_applicant_details}.district for a Laravel district.
     *
     * @return list<string>
     */
    public function legacyDistrictNameCandidatesForLaravelDistrictId(int $laravelDistrictId): array
    {
        $district = District::query()->find($laravelDistrictId);
        if ($district === null) {
            return [];
        }

        return $this->districtDisplayNames($district);
    }

    /**
     * @return list<int>
     */
    /**
     * @param  list<int>  $laravelDistrictIds
     * @return list<int>
     */
    public function legacyApplicationIdsForLaravelDistrictIds(array $laravelDistrictIds): array
    {
        $ids = [];
        foreach ($laravelDistrictIds as $districtId) {
            $ids = array_merge($ids, $this->legacyApplicationIdsInLaravelDistrict((int) $districtId));
        }

        return array_values(array_unique(array_filter(
            array_map(fn ($id): int => (int) $id, $ids),
            fn (int $id): bool => $id > 0
        )));
    }

    /**
     * Scope approved service_cases to districts via CFA submission or Phase 2 legacy application.
     *
     * Expects {@see leftJoin} on `cfa_submissions as cs` (`cs.id` = `sc.cfa_submission_id`).
     *
     * @param  list<int>|null  $districtIds  null = statewide (no district filter)
     * @param  \Illuminate\Database\Query\Builder  $query
     */
    public function applyAchievementDistrictScopeToServiceCaseQuery($query, ?array $districtIds): void
    {
        if ($districtIds === null) {
            return;
        }

        if ($districtIds === []) {
            $query->whereRaw('0 = 1');

            return;
        }

        $legacyIds = ServiceCase::supportsLegacyApplicationLink()
            ? $this->legacyApplicationIdsForLaravelDistrictIds($districtIds)
            : [];

        $query->where(function ($outer) use ($districtIds, $legacyIds): void {
            $outer->whereIn('cs.district_id', $districtIds);

            if ($legacyIds !== []) {
                $outer->orWhere(function ($legacy) use ($legacyIds): void {
                    $legacy->whereNull('sc.cfa_submission_id')
                        ->whereNotNull('sc.legacy_application_id')
                        ->whereIn('sc.legacy_application_id', $legacyIds);
                });
            }
        });
    }

    public function legacyApplicationIdsInLaravelDistrict(int $laravelDistrictId): array
    {
        if (! $this->legacyDbAvailable() || $laravelDistrictId < 1) {
            return [];
        }

        $district = District::query()->find($laravelDistrictId);
        if ($district === null) {
            return [];
        }

        $names = $this->districtDisplayNames($district);
        if ($names === []) {
            return [];
        }

        return DB::connection('legacy')
            ->table('rbi_applicant_details')
            ->whereIn('district', $names)
            ->pluck('application_id')
            ->map(fn ($id) => (int) $id)
            ->unique()
            ->values()
            ->all();
    }

    /**
     * Onboarded legacy incubatees for a Laravel district (rbiphase2), shaped for attendance pickers.
     *
     * Legacy rows use a negative {@see incubatee_id} so they do not collide with Phase 3 CFA ids.
     *
     * @return Collection<int, array{
     *     incubatee_id: int,
     *     legacy_application_id: int,
     *     source: string,
     *     name: string,
     *     application_no: string,
     *     phone: string,
     *     gender: string,
     *     village: string,
     *     block_name: string,
     *     onboarding_batch_id: int,
     *     onboarding_batch_name: string
     * }>
     */
    public function onboardedIncubateesForLaravelDistrict(int $laravelDistrictId, string $search = ''): Collection
    {
        if (! $this->legacyDbAvailable() || $laravelDistrictId < 1) {
            return collect();
        }

        if (! Schema::connection('legacy')->hasTable('rbi_onboarded_applicants')) {
            return collect();
        }

        $district = District::query()->find($laravelDistrictId);
        if ($district === null) {
            return collect();
        }

        $names = $this->districtDisplayNames($district);
        if ($names === []) {
            return collect();
        }

        $query = DB::connection('legacy')
            ->table('rbi_onboarded_applicants as oa')
            ->join('rbi_applications as a', 'a.id', '=', 'oa.application_id')
            ->join('rbi_applicant_details as d', 'd.application_id', '=', 'oa.application_id')
            ->leftJoin('rbi_onboarding_batches as ob', 'ob.id', '=', 'oa.onboarding_batch_id')
            ->whereIn('d.district', $names)
            ->select([
                'a.id as application_id',
                'd.applicant_name',
                'a.application_no',
                'd.phone',
                'd.gender',
                'd.village',
                'd.block as block_name',
                'ob.id as onboarding_batch_id',
                'ob.batch_name as onboarding_batch_name',
            ]);

        if ($search !== '') {
            $like = '%'.str_replace(['\\', '%', '_'], ['\\\\', '\\%', '\\_'], $search).'%';
            $query->where(function ($q) use ($like): void {
                $q->where('d.applicant_name', 'like', $like)
                    ->orWhere('a.application_no', 'like', $like)
                    ->orWhere('d.phone', 'like', $like)
                    ->orWhere('ob.batch_name', 'like', $like);
            });
        }

        return $query
            ->orderBy('d.applicant_name')
            ->get()
            ->map(function ($row): array {
                $legacyApplicationId = (int) ($row->application_id ?? 0);

                return [
                    'incubatee_id' => $legacyApplicationId > 0 ? -1 * $legacyApplicationId : 0,
                    'legacy_application_id' => $legacyApplicationId,
                    'source' => 'legacy_phase2',
                    'name' => (string) ($row->applicant_name ?? ''),
                    'application_no' => (string) ($row->application_no ?? ''),
                    'phone' => (string) ($row->phone ?? ''),
                    'gender' => IncubateeAttendeeCounts::normalizeGender((string) ($row->gender ?? '')),
                    'village' => (string) ($row->village ?? ''),
                    'block_name' => (string) ($row->block_name ?? ''),
                    'onboarding_batch_id' => (int) ($row->onboarding_batch_id ?? 0),
                    'onboarding_batch_name' => (string) ($row->onboarding_batch_name ?? ''),
                ];
            })
            ->filter(fn (array $row): bool => (int) ($row['incubatee_id'] ?? 0) !== 0)
            ->unique('incubatee_id')
            ->values();
    }

    public function onboardedIncubateeCountForLaravelDistrict(int $laravelDistrictId): int
    {
        return $this->onboardedIncubateesForLaravelDistrict($laravelDistrictId)->count();
    }

    /**
     * @param  list<int>  $legacyApplicationIds
     * @return array<int, array{
     *     name: string,
     *     application_no: string,
     *     phone: string,
     *     gender: string,
     *     village: string,
     *     block_name: string
     * }>
     */
    public function applicantSnapshotsByLegacyApplicationIds(array $legacyApplicationIds): array
    {
        $legacyApplicationIds = array_values(array_unique(array_filter(
            array_map(fn ($id): int => (int) $id, $legacyApplicationIds),
            fn (int $id): bool => $id > 0
        )));

        if (! $this->legacyDbAvailable() || $legacyApplicationIds === []) {
            return [];
        }

        return DB::connection('legacy')
            ->table('rbi_applicant_details as d')
            ->join('rbi_applications as a', 'a.id', '=', 'd.application_id')
            ->whereIn('d.application_id', $legacyApplicationIds)
            ->get([
                'd.application_id',
                'd.applicant_name',
                'a.application_no',
                'd.phone',
                'd.gender',
                'd.village',
                'd.block',
            ])
            ->mapWithKeys(function ($row): array {
                $legacyApplicationId = (int) ($row->application_id ?? 0);
                if ($legacyApplicationId <= 0) {
                    return [];
                }

                return [
                    $legacyApplicationId => [
                        'name' => (string) ($row->applicant_name ?? ''),
                        'application_no' => (string) ($row->application_no ?? ''),
                        'phone' => (string) ($row->phone ?? ''),
                        'gender' => IncubateeAttendeeCounts::normalizeGender((string) ($row->gender ?? '')),
                        'village' => (string) ($row->village ?? ''),
                        'block_name' => (string) ($row->block ?? ''),
                    ],
                ];
            })
            ->all();
    }

    /**
     * @param  list<string>  $applicationNumbers
     * @return array<string, array{
     *     legacy_application_id: int,
     *     name: string,
     *     application_no: string,
     *     phone: string,
     *     gender: string,
     *     village: string,
     *     block_name: string
     * }>
     */
    public function applicantSnapshotsByLegacyApplicationNumbers(array $applicationNumbers): array
    {
        $applicationNumbers = array_values(array_unique(array_filter(array_map(
            fn ($number): string => trim((string) $number),
            $applicationNumbers
        ))));

        if (! $this->legacyDbAvailable() || $applicationNumbers === []) {
            return [];
        }

        return DB::connection('legacy')
            ->table('rbi_applicant_details as d')
            ->join('rbi_applications as a', 'a.id', '=', 'd.application_id')
            ->whereIn('a.application_no', $applicationNumbers)
            ->get([
                'd.application_id',
                'd.applicant_name',
                'a.application_no',
                'd.phone',
                'd.gender',
                'd.village',
                'd.block',
            ])
            ->mapWithKeys(function ($row): array {
                $applicationNo = trim((string) ($row->application_no ?? ''));
                if ($applicationNo === '') {
                    return [];
                }

                return [
                    mb_strtolower($applicationNo) => [
                        'legacy_application_id' => (int) ($row->application_id ?? 0),
                        'name' => (string) ($row->applicant_name ?? ''),
                        'application_no' => $applicationNo,
                        'phone' => (string) ($row->phone ?? ''),
                        'gender' => IncubateeAttendeeCounts::normalizeGender((string) ($row->gender ?? '')),
                        'village' => (string) ($row->village ?? ''),
                        'block_name' => (string) ($row->block ?? ''),
                    ],
                ];
            })
            ->all();
    }

    /**
     * Onboarded legacy incubatees in the staff district (for service picker).
     *
     * @return Collection<int, object{id: int, applicant_name: string, application_no: string, district: string}>
     */
    public function eligibleLegacyApplicationsForStaff(User $staff): Collection
    {
        if (! $this->legacyDbAvailable() || (int) $staff->district_id < 1) {
            return collect();
        }

        $district = District::query()->find((int) $staff->district_id);
        if ($district === null) {
            return collect();
        }

        $names = $this->districtDisplayNames($district);
        if ($names === []) {
            return collect();
        }

        $hasOnboard = Schema::connection('legacy')->hasTable('rbi_onboarded_applicants');

        $q = DB::connection('legacy')
            ->table('rbi_applicant_details as d')
            ->join('rbi_applications as a', 'a.id', '=', 'd.application_id')
            ->whereIn('d.district', $names)
            ->orderBy('d.applicant_name')
            ->select([
                'd.application_id as id',
                'd.applicant_name',
                'a.application_no',
                'd.district',
            ]);

        if ($hasOnboard) {
            $q->join('rbi_onboarded_applicants as oa', 'oa.application_id', '=', 'd.application_id');
        }

        return $q->get();
    }

    /**
     * @return list<array{service_name: string, category: string, assigned_date: ?string, doc_date: ?string}>
     */
    public function legacyAssignedServicesForDisplay(int $legacyApplicationId): array
    {
        if (! $this->legacyDbAvailable() || ! Schema::connection('legacy')->hasTable('rbi_services_assigned')) {
            return [];
        }

        return DB::connection('legacy')
            ->table('rbi_services_assigned')
            ->where('application_id', $legacyApplicationId)
            ->orderByDesc('id')
            ->get(['service_name', 'category', 'assigned_date', 'doc_date'])
            ->map(fn ($row) => [
                'service_name' => (string) ($row->service_name ?? ''),
                'category' => (string) ($row->category ?? ''),
                'assigned_date' => $row->assigned_date !== null ? (string) $row->assigned_date : null,
                'doc_date' => $row->doc_date !== null ? (string) $row->doc_date : null,
            ])
            ->all();
    }

    /**
     * Service catalog ids that should be disabled (already assigned in MIS or legacy by name match).
     *
     * @return list<int>
     */
    public function blockedServiceIds(?int $legacyApplicationId, ?int $cfaSubmissionId): array
    {
        $ids = [];

        if ($cfaSubmissionId !== null && $cfaSubmissionId > 0) {
            $local = ServiceCase::query()
                ->where('cfa_submission_id', $cfaSubmissionId)
                ->whereIn('status', ServiceCase::BLOCKING_STATUSES)
                ->pluck('service_id')
                ->map(fn ($id) => (int) $id)
                ->all();
            $ids = array_merge($ids, $local);
        }

        if ($legacyApplicationId !== null && $legacyApplicationId > 0 && ServiceCase::supportsLegacyApplicationLink()) {
            $localLegacy = ServiceCase::query()
                ->where('legacy_application_id', $legacyApplicationId)
                ->whereIn('status', ServiceCase::BLOCKING_STATUSES)
                ->pluck('service_id')
                ->map(fn ($id) => (int) $id)
                ->all();
            $ids = array_merge($ids, $localLegacy);

            foreach ($this->legacyAssignedServicesForDisplay($legacyApplicationId) as $row) {
                $sid = $this->matchCatalogServiceIdByLegacyName($row['service_name']);
                if ($sid !== null) {
                    $ids[] = $sid;
                }
            }
        }

        return array_values(array_unique(array_filter($ids, fn (int $id) => $id > 0)));
    }

    /**
     * @param  list<int>  $legacyApplicationIds
     * @return array<int, array{applicant_name: string, application_no: string, district: string, onboarding_batch_name: string}>
     */
    public function incubateePreviewMap(array $legacyApplicationIds): array
    {
        $ids = array_values(array_unique(array_filter(
            array_map('intval', $legacyApplicationIds),
            fn (int $id): bool => $id > 0
        )));

        if ($ids === [] || ! $this->legacyDbAvailable()) {
            return [];
        }

        $legacy = Schema::connection('legacy');
        $hasOnboard = $legacy->hasTable('rbi_onboarded_applicants');
        $hasOnboardBatches = $legacy->hasTable('rbi_onboarding_batches');

        $q = DB::connection('legacy')
            ->table('rbi_applicant_details as d')
            ->join('rbi_applications as a', 'a.id', '=', 'd.application_id')
            ->whereIn('d.application_id', $ids);

        if ($hasOnboard) {
            $q->leftJoin('rbi_onboarded_applicants as oa', 'oa.application_id', '=', 'd.application_id');
            if ($hasOnboardBatches) {
                $q->leftJoin('rbi_onboarding_batches as ob', 'ob.id', '=', 'oa.onboarding_batch_id');
            }
        }

        $select = [
            'd.application_id',
            'd.applicant_name',
            'a.application_no',
            'd.district',
        ];
        $select[] = ($hasOnboard && $hasOnboardBatches)
            ? DB::raw('ob.batch_name as onboarding_batch_name')
            : DB::raw("'' as onboarding_batch_name");

        $out = [];
        foreach ($q->select($select)->get() as $row) {
            $id = (int) ($row->application_id ?? 0);
            if ($id < 1) {
                continue;
            }

            $out[$id] = [
                'applicant_name' => (string) ($row->applicant_name ?? ''),
                'application_no' => (string) ($row->application_no ?? ''),
                'district' => (string) ($row->district ?? ''),
                'onboarding_batch_name' => (string) ($row->onboarding_batch_name ?? ''),
            ];
        }

        return $out;
    }

    /**
     * @return array{applicant_name: string, application_no: string, district: string, onboarding_batch_name: string}|null
     */
    public function incubateePreview(int $legacyApplicationId): ?array
    {
        if ($legacyApplicationId < 1) {
            return null;
        }

        return $this->incubateePreviewMap([$legacyApplicationId])[$legacyApplicationId] ?? null;
    }

    public function assertLegacyApplicationInStaffDistrict(User $staff, int $legacyApplicationId): void
    {
        $allowed = $this->legacyApplicationIdsInLaravelDistrict((int) $staff->district_id);
        if (! in_array($legacyApplicationId, $allowed, true)) {
            abort(403, 'This legacy application is not in your district.');
        }
    }

    /**
     * @return list<string>
     */
    private function districtDisplayNames(District $district): array
    {
        $canonical = trim((string) $district->name);
        $aliasesMap = (array) config('legacy_phase2.staff_import.district_aliases', []);
        $rawAliases = (array) ($aliasesMap[$canonical] ?? []);
        $names = [$canonical];
        foreach ($rawAliases as $alias) {
            $a = trim((string) $alias);
            if ($a !== '') {
                $names[] = $a;
            }
        }

        return array_values(array_unique($names));
    }

    private function matchCatalogServiceIdByLegacyName(string $legacyServiceName): ?int
    {
        $t = mb_strtolower(trim($legacyServiceName));
        if ($t === '') {
            return null;
        }

        $id = Service::query()
            ->where('is_active', true)
            ->whereRaw('LOWER(TRIM(name)) = ?', [$t])
            ->value('id');

        return $id !== null ? (int) $id : null;
    }
}
