<?php

namespace App\Services;

use App\Models\District;
use App\Models\Service;
use App\Models\ServiceCase;
use App\Models\User;
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

        if ($legacyApplicationId !== null && $legacyApplicationId > 0) {
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
     * @return array{applicant_name: string, application_no: string, district: string}|null
     */
    public function incubateePreview(int $legacyApplicationId): ?array
    {
        if (! $this->legacyDbAvailable()) {
            return null;
        }

        $row = DB::connection('legacy')
            ->table('rbi_applicant_details as d')
            ->join('rbi_applications as a', 'a.id', '=', 'd.application_id')
            ->where('d.application_id', $legacyApplicationId)
            ->first(['d.applicant_name', 'a.application_no', 'd.district']);

        if ($row === null) {
            return null;
        }

        return [
            'applicant_name' => (string) ($row->applicant_name ?? ''),
            'application_no' => (string) ($row->application_no ?? ''),
            'district' => (string) ($row->district ?? ''),
        ];
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
