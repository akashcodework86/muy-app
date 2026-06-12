<?php

namespace App\Services;

use App\Models\CfaSubmission;
use App\Models\PitchDeckPreparation;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

class PitchDeckIncubateeCatalogService
{
    public function __construct(
        private readonly LegacyApplicationServiceCaseSupport $legacyApplications,
    ) {}

    /**
     * @return list<array<string, mixed>>
     */
    public function search(string $term, int $limit = 5): array
    {
        $term = trim($term);
        if (mb_strlen($term) < 2) {
            return [];
        }

        $existingCfa = $this->existingCfaIds();
        $existingLegacy = $this->existingLegacyIds();

        $results = [];
        $seen = [];

        foreach ($this->searchCfa($term, $limit * 2) as $row) {
            $cfaId = (int) ($row['cfa_submission_id'] ?? 0);
            if ($cfaId < 1 || isset($seen['cfa:'.$cfaId])) {
                continue;
            }
            $seen['cfa:'.$cfaId] = true;
            $results[] = array_merge($row, [
                'already_recorded' => isset($existingCfa[$cfaId]),
            ]);
            if (count($results) >= $limit) {
                return $results;
            }
        }

        foreach ($this->searchLegacy($term, $limit * 2) as $row) {
            $legacyId = (int) ($row['legacy_application_id'] ?? 0);
            if ($legacyId < 1 || isset($seen['legacy:'.$legacyId])) {
                continue;
            }
            $seen['legacy:'.$legacyId] = true;
            $results[] = array_merge($row, [
                'already_recorded' => isset($existingLegacy[$legacyId]),
            ]);
            if (count($results) >= $limit) {
                break;
            }
        }

        return $results;
    }

    /**
     * @return array{name: string, application_no: ?string, district_id: int}|null
     */
    public function resolveSelection(int $cfaSubmissionId, int $legacyApplicationId): ?array
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

        $profiles = [];
        foreach ($items as $preparation) {
            $prepId = (int) $preparation->id;
            $cfaId = (int) ($preparation->cfa_submission_id ?? 0);
            $legacyId = (int) ($preparation->legacy_application_id ?? 0);

            if ($cfaId > 0 && isset($cfaProfiles[$cfaId])) {
                $profiles[$prepId] = $cfaProfiles[$cfaId];

                continue;
            }

            if ($legacyId > 0 && isset($legacyProfiles[$legacyId])) {
                $profiles[$prepId] = $legacyProfiles[$legacyId];

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
}
