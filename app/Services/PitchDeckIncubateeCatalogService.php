<?php

namespace App\Services;

use App\Models\CfaSubmission;
use App\Models\District;
use App\Models\PitchDeckPreparation;
use App\Services\LegacyPhase1\LegacyPhase1DistrictResolver;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

class PitchDeckIncubateeCatalogService
{
    /** @var array<string, int>|null */
    private ?array $districtIdsByName = null;

    public function __construct(
        private readonly LegacyApplicationServiceCaseSupport $legacyApplications,
    ) {}

    /**
     * @return list<array<string, mixed>>
     */
    public function listOnboarded(int $limit = 2000): array
    {
        $results = [];
        $seen = [];

        foreach ($this->listCfaOnboarded($limit) as $row) {
            $cfaId = (int) ($row['cfa_submission_id'] ?? 0);
            if ($cfaId < 1 || isset($seen['cfa:'.$cfaId])) {
                continue;
            }
            $seen['cfa:'.$cfaId] = true;
            $results[] = $row;
        }

        foreach ($this->listLegacyOnboarded($limit) as $row) {
            $legacyId = (int) ($row['legacy_application_id'] ?? 0);
            if ($legacyId < 1 || isset($seen['legacy:'.$legacyId])) {
                continue;
            }
            $seen['legacy:'.$legacyId] = true;
            $results[] = $row;
        }

        foreach ($this->listPhase1Onboarded($limit) as $row) {
            $phase1Id = (int) ($row['legacy_phase1_application_id'] ?? 0);
            if ($phase1Id < 1 || isset($seen['phase1:'.$phase1Id])) {
                continue;
            }
            $seen['phase1:'.$phase1Id] = true;
            $results[] = $row;
        }

        usort($results, fn (array $a, array $b): int => strcasecmp((string) ($a['name'] ?? ''), (string) ($b['name'] ?? '')));

        return array_slice($results, 0, $limit);
    }

    /**
     * @return list<array<string, mixed>>
     */
    public function search(string $term, int $limit = 15): array
    {
        $term = trim($term);
        if (mb_strlen($term) < 2) {
            return [];
        }

        $existingCfa = $this->existingCfaIds();
        $existingLegacy = $this->existingLegacyIds();
        $existingPhase1 = $this->existingPhase1Ids();

        $buckets = [
            $this->markRecorded($this->searchCfa($term, $limit), 'cfa_submission_id', $existingCfa),
            $this->markRecorded($this->searchLegacy($term, $limit), 'legacy_application_id', $existingLegacy),
            $this->markRecorded($this->searchPhase1($term, $limit), 'legacy_phase1_application_id', $existingPhase1),
        ];

        $results = [];
        $seen = [];
        $max = 0;
        foreach ($buckets as $bucket) {
            $max = max($max, count($bucket));
        }

        for ($i = 0; $i < $max; $i++) {
            foreach ($buckets as $bucket) {
                if (! isset($bucket[$i])) {
                    continue;
                }
                $row = $bucket[$i];
                $key = (string) ($row['key'] ?? '');
                if ($key === '' || isset($seen[$key])) {
                    continue;
                }
                $seen[$key] = true;
                $results[] = $row;
                if (count($results) >= $limit) {
                    return $results;
                }
            }
        }

        return $results;
    }

    /**
     * @return array{name: string, application_no: ?string, district_id: int}|null
     */
    public function resolveSelection(int $cfaSubmissionId, int $legacyApplicationId, int $legacyPhase1ApplicationId = 0): ?array
    {
        if ($cfaSubmissionId > 0) {
            $submission = CfaSubmission::query()->whereKey($cfaSubmissionId)->first();
            if ($submission === null) {
                return null;
            }

            return [
                'name' => (string) $submission->applicant_name,
                'application_no' => $submission->application_no ? (string) $submission->application_no : null,
                'district_id' => (int) $submission->district_id,
            ];
        }

        if ($legacyApplicationId > 0) {
            $row = $this->legacyApplicantById($legacyApplicationId);
            if ($row === null) {
                return null;
            }

            $districtId = $this->legacyApplications->laravelDistrictIdForLegacyApplication($legacyApplicationId);

            return [
                'name' => (string) ($row->applicant_name ?? ''),
                'application_no' => $row->application_no ? (string) $row->application_no : null,
                'district_id' => (int) ($districtId ?? 0),
            ];
        }

        if ($legacyPhase1ApplicationId > 0) {
            $row = $this->phase1ApplicantById($legacyPhase1ApplicationId);
            if ($row === null) {
                return null;
            }

            $districtName = LegacyPhase1DistrictResolver::canonicalNameForLegacyFatherName(
                isset($row->FatherName) ? (string) $row->FatherName : null
            );

            return [
                'name' => trim((string) ($row->FullName ?? '')),
                'application_no' => trim((string) ($row->ApplicationNumber ?? '')) ?: null,
                'district_id' => $this->districtIdForName($districtName),
            ];
        }

        return null;
    }

    /**
     * @return array<string, mixed>
     */
    public function profileForPreparation(PitchDeckPreparation $preparation): array
    {
        $profiles = $this->profilesForPreparations([$preparation]);

        return $profiles[(int) $preparation->id] ?? $this->fallbackProfile($preparation);
    }

    /**
     * @param  iterable<PitchDeckPreparation>  $preparations
     * @return array<int, array<string, mixed>>
     */
    public function profilesForPreparations(iterable $preparations): array
    {
        $items = collect($preparations);
        if ($items->isEmpty()) {
            return [];
        }

        $cfaIds = $items
            ->pluck('cfa_submission_id')
            ->filter()
            ->map(fn ($id) => (int) $id)
            ->unique()
            ->values()
            ->all();
        $legacyIds = $items
            ->pluck('legacy_application_id')
            ->filter()
            ->map(fn ($id) => (int) $id)
            ->unique()
            ->values()
            ->all();

        $cfaProfiles = $this->cfaProfilesById($cfaIds);
        $legacyProfiles = $this->legacyProfilesById($legacyIds);
        $phase1Ids = [];
        if (Schema::hasColumn('pitch_deck_preparations', 'legacy_phase1_application_id')) {
            $phase1Ids = $items
                ->pluck('legacy_phase1_application_id')
                ->filter()
                ->map(fn ($id) => (int) $id)
                ->unique()
                ->values()
                ->all();
        }
        $phase1Profiles = $this->phase1ProfilesById($phase1Ids);

        $profiles = [];
        foreach ($items as $preparation) {
            $prepId = (int) $preparation->id;
            $cfaId = (int) ($preparation->cfa_submission_id ?? 0);
            $legacyId = (int) ($preparation->legacy_application_id ?? 0);
            $phase1Id = (int) ($preparation->legacy_phase1_application_id ?? 0);

            if ($cfaId > 0 && isset($cfaProfiles[$cfaId])) {
                $profiles[$prepId] = $cfaProfiles[$cfaId];

                continue;
            }

            if ($legacyId > 0 && isset($legacyProfiles[$legacyId])) {
                $profiles[$prepId] = $legacyProfiles[$legacyId];

                continue;
            }

            if ($phase1Id > 0 && isset($phase1Profiles[$phase1Id])) {
                $profiles[$prepId] = $phase1Profiles[$phase1Id];

                continue;
            }

            $profiles[$prepId] = $this->fallbackProfile($preparation);
        }

        return $profiles;
    }

    /**
     * @param  list<int>  $cfaIds
     * @param  list<int>  $legacyIds
     * @return array{cfa: array<int, array<string, mixed>>, legacy: array<int, array<string, mixed>>}
     */
    public function profilesByIncubateeIds(array $cfaIds, array $legacyIds): array
    {
        return [
            'cfa' => $this->cfaProfilesById($cfaIds),
            'legacy' => $this->legacyProfilesById($legacyIds),
        ];
    }

    /**
     * @param  list<int>  $cfaIds
     * @return array<int, array<string, mixed>>
     */
    private function cfaProfilesById(array $cfaIds): array
    {
        if ($cfaIds === [] || ! Schema::hasTable('cfa_submissions')) {
            return [];
        }

        return CfaSubmission::query()
            ->with([
                'district:id,name,hub_id',
                'district.hub:id,name',
                'onboardingBatchMembership.batch:id,name,status,locked_at',
            ])
            ->whereIn('id', $cfaIds)
            ->get()
            ->mapWithKeys(fn (CfaSubmission $sub): array => [(int) $sub->id => $this->mapCfaRow($sub)])
            ->all();
    }

    /**
     * @param  list<int>  $legacyIds
     * @return array<int, array<string, mixed>>
     */
    private function legacyProfilesById(array $legacyIds): array
    {
        if ($legacyIds === [] || ! $this->legacyApplications->legacyDbAvailable()) {
            return [];
        }

        $legacy = Schema::connection('legacy');
        $hasOnboard = $legacy->hasTable('rbi_onboarded_applicants');
        $hasOnboardBatches = $legacy->hasTable('rbi_onboarding_batches');

        $q = DB::connection('legacy')
            ->table('rbi_applicant_details as d')
            ->join('rbi_applications as a', 'a.id', '=', 'd.application_id')
            ->whereIn('d.application_id', $legacyIds);

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
            'd.phone',
            'd.district',
            'd.block',
            'd.village',
            'd.gender',
        ];

        if ($hasOnboard) {
            $select[] = DB::raw('CASE WHEN oa.application_id IS NULL THEN 0 ELSE 1 END as is_onboarded');
            $select[] = $hasOnboardBatches
                ? DB::raw('ob.batch_name as onboarding_batch_name')
                : DB::raw("'' as onboarding_batch_name");
        } else {
            $select[] = DB::raw('0 as is_onboarded');
            $select[] = DB::raw("'' as onboarding_batch_name");
        }

        $profiles = [];
        foreach ($q->select($select)->get() as $row) {
            $legacyId = (int) ($row->application_id ?? 0);
            if ($legacyId < 1) {
                continue;
            }

            $isOnboarded = (int) ($row->is_onboarded ?? 0) === 1;
            $profiles[$legacyId] = [
                'name' => (string) ($row->applicant_name ?? ''),
                'application_no' => (string) ($row->application_no ?? ''),
                'source' => 'Phase 2 legacy',
                'phone' => (string) ($row->phone ?? ''),
                'district' => (string) ($row->district ?? ''),
                'hub' => '',
                'block' => (string) ($row->block ?? ''),
                'village' => (string) ($row->village ?? ''),
                'gender' => (string) ($row->gender ?? ''),
                'business_category' => '',
                'is_onboarded' => $isOnboarded,
                'onboarding_status' => $isOnboarded ? 'Onboarded' : 'Not onboarded',
                'onboarding_batch_name' => (string) ($row->onboarding_batch_name ?? ''),
            ];
        }

        return $profiles;
    }

    /**
     * @return array<string, mixed>
     */
    private function fallbackProfile(PitchDeckPreparation $preparation): array
    {
        $preparation->loadMissing('district.hub:id,name');

        return [
            'name' => (string) $preparation->incubatee_name,
            'application_no' => (string) ($preparation->application_no ?? ''),
            'source' => $preparation->incubateeSourceLabel(),
            'phone' => '',
            'district' => (string) ($preparation->district?->name ?? ''),
            'hub' => (string) ($preparation->district?->hub?->name ?? ''),
            'block' => '',
            'village' => '',
            'gender' => '',
            'business_category' => '',
            'is_onboarded' => false,
            'onboarding_status' => '—',
            'onboarding_batch_name' => '',
        ];
    }

    /**
     * @return array<int, true>
     */
    private function existingCfaIds(): array
    {
        if (! Schema::hasTable('pitch_deck_preparations')) {
            return [];
        }

        return PitchDeckPreparation::query()
            ->whereNotNull('cfa_submission_id')
            ->pluck('cfa_submission_id')
            ->mapWithKeys(fn ($id) => [(int) $id => true])
            ->all();
    }

    /**
     * @return array<int, true>
     */
    private function existingLegacyIds(): array
    {
        if (! Schema::hasTable('pitch_deck_preparations')) {
            return [];
        }

        return PitchDeckPreparation::query()
            ->whereNotNull('legacy_application_id')
            ->pluck('legacy_application_id')
            ->mapWithKeys(fn ($id) => [(int) $id => true])
            ->all();
    }

    /**
     * @return array<int, true>
     */
    private function existingPhase1Ids(): array
    {
        if (! Schema::hasTable('pitch_deck_preparations') || ! Schema::hasColumn('pitch_deck_preparations', 'legacy_phase1_application_id')) {
            return [];
        }

        return PitchDeckPreparation::query()
            ->whereNotNull('legacy_phase1_application_id')
            ->pluck('legacy_phase1_application_id')
            ->mapWithKeys(fn ($id) => [(int) $id => true])
            ->all();
    }

    /**
     * @param  list<array<string, mixed>>  $rows
     * @param  array<int, true>  $existingIds
     * @return list<array<string, mixed>>
     */
    private function markRecorded(array $rows, string $idKey, array $existingIds): array
    {
        return array_map(function (array $row) use ($idKey, $existingIds): array {
            $id = (int) ($row[$idKey] ?? 0);

            return array_merge($row, [
                'already_recorded' => $id > 0 && isset($existingIds[$id]),
            ]);
        }, $rows);
    }

    /**
     * @return list<array<string, mixed>>
     */
    private function listCfaOnboarded(int $limit): array
    {
        if (! Schema::hasTable('cfa_submissions')) {
            return [];
        }

        return CfaSubmission::query()
            ->with([
                'district:id,name,hub_id',
                'district.hub:id,name',
                'onboardingBatchMembership.batch:id,name,status,locked_at',
            ])
            ->whereHas('onboardingBatchMembership.batch', function ($q): void {
                $q->where('status', 'locked')->whereNotNull('locked_at');
            })
            ->orderBy('applicant_name')
            ->limit($limit)
            ->get()
            ->map(fn (CfaSubmission $sub): array => $this->mapCfaRow($sub))
            ->all();
    }

    /**
     * @return list<array<string, mixed>>
     */
    private function listLegacyOnboarded(int $limit): array
    {
        if (! $this->legacyApplications->legacyDbAvailable()) {
            return [];
        }

        $legacy = Schema::connection('legacy');
        if (! $legacy->hasTable('rbi_onboarded_applicants')) {
            return [];
        }

        $hasOnboardBatches = $legacy->hasTable('rbi_onboarding_batches');

        $q = DB::connection('legacy')
            ->table('rbi_applicant_details as d')
            ->join('rbi_applications as a', 'a.id', '=', 'd.application_id')
            ->join('rbi_onboarded_applicants as oa', 'oa.application_id', '=', 'd.application_id')
            ->orderBy('d.applicant_name')
            ->limit($limit);

        if ($hasOnboardBatches) {
            $q->leftJoin('rbi_onboarding_batches as ob', 'ob.id', '=', 'oa.onboarding_batch_id');
        }

        $select = [
            'd.application_id',
            'd.applicant_name',
            'a.application_no',
            'd.phone',
            'd.district',
            'd.block',
            'd.village',
            'd.gender',
            DB::raw('1 as is_onboarded'),
            $hasOnboardBatches
                ? DB::raw('ob.batch_name as onboarding_batch_name')
                : DB::raw("'' as onboarding_batch_name"),
        ];

        $q->select($select);

        return collect($q->get())->map(function ($row): array {
            return [
                'key' => 'legacy:'.(int) $row->application_id,
                'source' => 'Phase 2 legacy',
                'cfa_submission_id' => 0,
                'legacy_application_id' => (int) $row->application_id,
                'legacy_phase1_application_id' => 0,
                'name' => (string) ($row->applicant_name ?? ''),
                'application_no' => (string) ($row->application_no ?? ''),
                'phone' => (string) ($row->phone ?? ''),
                'district' => (string) ($row->district ?? ''),
                'hub' => '',
                'block' => (string) ($row->block ?? ''),
                'village' => (string) ($row->village ?? ''),
                'gender' => (string) ($row->gender ?? ''),
                'business_category' => '',
                'is_onboarded' => true,
                'onboarding_status' => 'Onboarded',
                'onboarding_batch_name' => (string) ($row->onboarding_batch_name ?? ''),
                'already_recorded' => false,
            ];
        })->all();
    }

    /**
     * @return list<array<string, mixed>>
     */
    private function searchCfa(string $term, int $limit): array
    {
        if (! Schema::hasTable('cfa_submissions')) {
            return [];
        }

        $like = '%'.str_replace(['\\', '%', '_'], ['\\\\', '\\%', '\\_'], $term).'%';

        $rows = CfaSubmission::query()
            ->with([
                'district:id,name,hub_id',
                'district.hub:id,name',
                'onboardingBatchMembership.batch:id,name,status,locked_at',
            ])
            ->where(function ($q) use ($like): void {
                $q->where('applicant_name', 'like', $like)
                    ->orWhere('application_no', 'like', $like)
                    ->orWhere('phone', 'like', $like)
                    ->orWhereHas('district', fn ($dq) => $dq->where('name', 'like', $like));
            })
            ->orderBy('applicant_name')
            ->limit($limit)
            ->get();

        return $rows->map(fn (CfaSubmission $sub): array => $this->mapCfaRow($sub))->all();
    }

    /**
     * @return array<string, mixed>
     */
    private function mapCfaRow(CfaSubmission $sub): array
    {
        $payload = is_array($sub->payload) ? $sub->payload : [];
        $batch = $sub->onboardingBatchMembership?->batch;
        $isOnboarded = $batch !== null
            && (string) $batch->status === 'locked'
            && $batch->locked_at !== null;

        return [
            'key' => 'cfa:'.$sub->id,
            'source' => 'Phase 3 CFA',
            'cfa_submission_id' => (int) $sub->id,
            'legacy_application_id' => 0,
            'legacy_phase1_application_id' => 0,
            'name' => (string) $sub->applicant_name,
            'application_no' => (string) ($sub->application_no ?? ''),
            'phone' => (string) ($sub->phone ?? ''),
            'district' => (string) ($sub->district?->name ?? ''),
            'hub' => (string) ($sub->district?->hub?->name ?? ''),
            'block' => (string) ($payload['block_name'] ?? $payload['block'] ?? ''),
            'village' => (string) ($payload['village'] ?? ''),
            'gender' => (string) ($payload['gender'] ?? ''),
            'business_category' => (string) ($payload['business_category'] ?? $payload['category'] ?? ''),
            'is_onboarded' => $isOnboarded,
            'onboarding_status' => $isOnboarded ? 'Onboarded' : 'Not onboarded',
            'onboarding_batch_name' => $isOnboarded ? (string) ($batch->name ?? '') : '',
            'already_recorded' => false,
        ];
    }

    /**
     * @return list<array<string, mixed>>
     */
    private function searchLegacy(string $term, int $limit): array
    {
        if (! $this->legacyApplications->legacyDbAvailable()) {
            return [];
        }

        $like = '%'.str_replace(['\\', '%', '_'], ['\\\\', '\\%', '\\_'], $term).'%';
        $legacy = Schema::connection('legacy');
        $hasOnboard = $legacy->hasTable('rbi_onboarded_applicants');
        $hasOnboardBatches = $legacy->hasTable('rbi_onboarding_batches');

        $q = DB::connection('legacy')
            ->table('rbi_applicant_details as d')
            ->join('rbi_applications as a', 'a.id', '=', 'd.application_id')
            ->where(function ($query) use ($like): void {
                $query->where('d.applicant_name', 'like', $like)
                    ->orWhere('a.application_no', 'like', $like)
                    ->orWhere('d.phone', 'like', $like)
                    ->orWhere('d.district', 'like', $like)
                    ->orWhere('d.block', 'like', $like)
                    ->orWhere('d.village', 'like', $like);
            })
            ->orderBy('d.applicant_name')
            ->limit($limit);

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
            'd.phone',
            'd.district',
            'd.block',
            'd.village',
            'd.gender',
        ];

        if ($hasOnboard) {
            $select[] = DB::raw('CASE WHEN oa.application_id IS NULL THEN 0 ELSE 1 END as is_onboarded');
            $select[] = $hasOnboardBatches
                ? DB::raw('ob.batch_name as onboarding_batch_name')
                : DB::raw("'' as onboarding_batch_name");
        } else {
            $select[] = DB::raw('0 as is_onboarded');
            $select[] = DB::raw("'' as onboarding_batch_name");
        }

        $q->select($select);

        return collect($q->get())->map(function ($row): array {
            $isOnboarded = (int) ($row->is_onboarded ?? 0) === 1;

            return [
                'key' => 'legacy:'.(int) $row->application_id,
                'source' => 'Phase 2 legacy',
                'cfa_submission_id' => 0,
                'legacy_application_id' => (int) $row->application_id,
                'legacy_phase1_application_id' => 0,
                'name' => (string) ($row->applicant_name ?? ''),
                'application_no' => (string) ($row->application_no ?? ''),
                'phone' => (string) ($row->phone ?? ''),
                'district' => (string) ($row->district ?? ''),
                'hub' => '',
                'block' => (string) ($row->block ?? ''),
                'village' => (string) ($row->village ?? ''),
                'gender' => (string) ($row->gender ?? ''),
                'business_category' => '',
                'is_onboarded' => $isOnboarded,
                'onboarding_status' => $isOnboarded ? 'Onboarded' : 'Not onboarded',
                'onboarding_batch_name' => (string) ($row->onboarding_batch_name ?? ''),
                'already_recorded' => false,
            ];
        })->all();
    }

    private function legacyApplicantById(int $legacyApplicationId): ?object
    {
        if (! $this->legacyApplications->legacyDbAvailable()) {
            return null;
        }

        return DB::connection('legacy')
            ->table('rbi_applicant_details as d')
            ->join('rbi_applications as a', 'a.id', '=', 'd.application_id')
            ->where('d.application_id', $legacyApplicationId)
            ->select([
                'd.application_id',
                'd.applicant_name',
                'a.application_no',
            ])
            ->first();
    }

    /**
     * @return list<array<string, mixed>>
     */
    private function listPhase1Onboarded(int $limit): array
    {
        if (! $this->phase1Available()) {
            return [];
        }

        try {
            $rows = DB::connection('legacy_phase1')
                ->table('tblapplication')
                ->whereRaw('LOWER(TRIM(onboard)) = ?', ['yes'])
                ->orderBy('FullName')
                ->limit($limit)
                ->get($this->phase1SelectColumns());
        } catch (\Throwable) {
            return [];
        }

        return $rows
            ->map(fn (object $row): array => $this->mapPhase1Row($row))
            ->all();
    }

    /**
     * @return list<array<string, mixed>>
     */
    private function searchPhase1(string $term, int $limit): array
    {
        if (! $this->phase1Available()) {
            return [];
        }

        $like = '%'.str_replace(['\\', '%', '_'], ['\\\\', '\\%', '\\_'], $term).'%';
        $digits = preg_replace('/\D+/', '', $term) ?? '';

        try {
            $rows = DB::connection('legacy_phase1')
                ->table('tblapplication')
                ->where(function ($query) use ($like, $digits): void {
                    $query->where('FullName', 'like', $like)
                        ->orWhere('ApplicationNumber', 'like', $like)
                        ->orWhere('FatherName', 'like', $like)
                        ->orWhere('City', 'like', $like)
                        ->orWhere('State', 'like', $like)
                        ->orWhere('hub', 'like', $like)
                        ->orWhereRaw('CAST(MobileNumber AS CHAR) LIKE ?', [$like]);
                    if (strlen($digits) >= 4) {
                        $query->orWhereRaw('CAST(MobileNumber AS CHAR) LIKE ?', ['%'.$digits.'%']);
                    }
                })
                ->orderBy('FullName')
                ->limit($limit)
                ->get($this->phase1SelectColumns());
        } catch (\Throwable) {
            return [];
        }

        return $rows
            ->map(fn (object $row): array => $this->mapPhase1Row($row))
            ->all();
    }

    /**
     * @param  list<int>  $phase1Ids
     * @return array<int, array<string, mixed>>
     */
    private function phase1ProfilesById(array $phase1Ids): array
    {
        if ($phase1Ids === [] || ! $this->phase1Available()) {
            return [];
        }

        try {
            $rows = DB::connection('legacy_phase1')
                ->table('tblapplication')
                ->whereIn('ID', $phase1Ids)
                ->get($this->phase1SelectColumns());
        } catch (\Throwable) {
            return [];
        }

        $profiles = [];
        foreach ($rows as $row) {
            $mapped = $this->mapPhase1Row($row);
            $id = (int) ($mapped['legacy_phase1_application_id'] ?? 0);
            if ($id < 1) {
                continue;
            }
            $profiles[$id] = $mapped;
        }

        return $profiles;
    }

    private function phase1ApplicantById(int $legacyPhase1ApplicationId): ?object
    {
        if ($legacyPhase1ApplicationId < 1 || ! $this->phase1Available()) {
            return null;
        }

        try {
            return DB::connection('legacy_phase1')
                ->table('tblapplication')
                ->where('ID', $legacyPhase1ApplicationId)
                ->first(['ID', 'FullName', 'ApplicationNumber', 'FatherName']);
        } catch (\Throwable) {
            return null;
        }
    }

    /**
     * @return list<string>
     */
    private function phase1SelectColumns(): array
    {
        return [
            'ID',
            'ApplicationNumber',
            'FullName',
            'MobileNumber',
            'FatherName',
            'hub',
            'State',
            'City',
            'gender',
            'idea',
            'onboard',
        ];
    }

    /**
     * @return array<string, mixed>
     */
    private function mapPhase1Row(object $row): array
    {
        $id = (int) ($row->ID ?? 0);
        $district = LegacyPhase1DistrictResolver::canonicalNameForLegacyFatherName(
            isset($row->FatherName) ? (string) $row->FatherName : null
        ) ?? trim((string) ($row->FatherName ?? ''));
        $isOnboarded = LegacyPhase1DistrictResolver::isOnboardedRaw(
            isset($row->onboard) ? (string) $row->onboard : null
        );

        return [
            'key' => 'phase1:'.$id,
            'source' => 'Phase 1',
            'cfa_submission_id' => 0,
            'legacy_application_id' => 0,
            'legacy_phase1_application_id' => $id,
            'name' => trim((string) ($row->FullName ?? '')),
            'application_no' => trim((string) ($row->ApplicationNumber ?? '')),
            'phone' => trim((string) ($row->MobileNumber ?? '')),
            'district' => $district,
            'hub' => trim((string) ($row->hub ?? '')),
            'block' => trim((string) ($row->State ?? '')),
            'village' => trim((string) ($row->City ?? '')),
            'gender' => trim((string) ($row->gender ?? '')),
            'business_category' => trim((string) ($row->idea ?? '')),
            'is_onboarded' => $isOnboarded,
            'onboarding_status' => $isOnboarded ? 'Onboarded' : 'Not onboarded',
            'onboarding_batch_name' => '',
            'already_recorded' => false,
        ];
    }

    private function phase1Available(): bool
    {
        if ((string) config('database.connections.legacy_phase1.database', '') === '') {
            return false;
        }

        try {
            return Schema::connection('legacy_phase1')->hasTable('tblapplication');
        } catch (\Throwable) {
            return false;
        }
    }

    private function districtIdForName(?string $name): int
    {
        $key = mb_strtolower(trim((string) $name));
        if ($key === '' || ! Schema::hasTable('districts')) {
            return 0;
        }

        if ($this->districtIdsByName === null) {
            $this->districtIdsByName = [];
            foreach (District::query()->get(['id', 'name']) as $district) {
                $this->districtIdsByName[mb_strtolower(trim((string) $district->name))] = (int) $district->id;
            }
        }

        return (int) ($this->districtIdsByName[$key] ?? 0);
    }
}
