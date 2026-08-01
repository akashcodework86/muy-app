<?php

namespace App\Services;

use App\Models\CfaHubChoiceState;
use App\Models\CfaSubmission;
use App\Models\District;
use App\Models\Document;
use App\Models\DocumentCategory;
use App\Models\DocumentVersion;
use App\Models\FiscalYear;
use App\Models\OnboardingBatch;
use App\Models\OnboardingBatchCfa;
use App\Models\OnboardingBatchDocument;
use App\Models\OnboardingBatchDraftCfa;
use App\Models\OnboardingBatchEditRequest;
use App\Models\User;
use App\Notifications\HubBatchUnlockRequestedNotification;
use App\Services\LegacyPhase1\LegacyPhase1DistrictResolver;
use App\Support\TodayOnlyDate;
use Carbon\Carbon;
use Illuminate\Http\Request;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;

class HubBatchService
{
    public const DOC_CDO = 'cdo_signed';

    public function __construct(
        private AdminAuditLogger $auditLogger
    ) {}

    /**
     * @return list<int>
     */
    /**
     * Normalized stage for onboarding mix (seed / early / growth).
     */
    public function stageKeyFromCfa(?CfaSubmission $c): string
    {
        if (! $c) {
            return 'unknown';
        }
        $raw = strtolower(trim((string) ($c->payload['form_stage'] ?? $c->payload['stage'] ?? '')));

        return match ($raw) {
            'seed' => 'seed',
            'early' => 'early',
            'growth' => 'growth',
            default => 'unknown',
        };
    }

    private function applicantCategoryFromPayload(array $payload): string
    {
        $raw = strtolower(trim((string) (
            $payload['category']
            ?? $payload['applicant_category']
            ?? $payload['applicant_type']
            ?? $payload['applicant_category_type']
            ?? ''
        )));

        return match ($raw) {
            'individual', 'vyaktigat' => 'Individual',
            'shg', 'self_help_group' => 'SHG',
            'cbo' => 'CBO',
            default => 'Not specified',
        };
    }

    /**
     * @param  list<string>  $keys
     */
    private function yesNoFromPayload(array $payload, array $keys): string
    {
        $value = null;
        foreach ($keys as $key) {
            if (array_key_exists($key, $payload)) {
                $value = $payload[$key];
                break;
            }
        }
        if ($value === null) {
            return 'Not specified';
        }
        if (is_bool($value)) {
            return $value ? 'Yes' : 'No';
        }
        $raw = strtolower(trim((string) $value));
        if (in_array($raw, ['1', 'yes', 'y', 'true'], true)) {
            return 'Yes';
        }
        if (in_array($raw, ['0', 'no', 'n', 'false'], true)) {
            return 'No';
        }

        return 'Not specified';
    }

    public function districtIdsForHub(int $hubId): array
    {
        return District::query()->where('hub_id', $hubId)->pluck('id')->map(fn ($id) => (int) $id)->all();
    }

    public function activeFiscalYearId(): ?int
    {
        $fy = FiscalYear::query()->where('is_active', true)->orderByDesc('starts_on')->first();

        return $fy ? (int) $fy->id : null;
    }

    public function generateBatchName(int $hubId, int $districtId, ?Carbon $now = null): string
    {
        $now = $now ?? Carbon::now();
        $district = District::query()->findOrFail($districtId);
        $y = (int) $now->format('Y');
        $m = (int) $now->format('n');
        $mon = $now->format('M');

        $count = OnboardingBatch::query()
            ->where('hub_id', $hubId)
            ->where('district_id', $districtId)
            ->whereYear('created_at', $y)
            ->whereMonth('created_at', $m)
            ->count();

        $title = $district->name;

        return $title.'-batch'.($count + 1).'-'.$mon.'-'.$y;
    }

    public function hasCdoPdf(OnboardingBatch $batch): bool
    {
        return OnboardingBatchDocument::query()
            ->where('onboarding_batch_id', $batch->id)
            ->where('doc_type', self::DOC_CDO)
            ->exists();
    }

    public function effectiveDeadline(OnboardingBatch $batch): ?Carbon
    {
        if (! $batch->locked_at) {
            return null;
        }
        if ($batch->pdf_deadline_extended_until) {
            return Carbon::parse($batch->pdf_deadline_extended_until);
        }

        return $batch->locked_at->copy()->addDays(7);
    }

    public function cdoIsOverdue(OnboardingBatch $batch): bool
    {
        if (! $batch->locked_at || ! $batch->isLocked()) {
            return false;
        }
        if ($batch->pdf_compliance_waived) {
            return false;
        }
        if ($this->hasCdoPdf($batch)) {
            return false;
        }
        $dl = $this->effectiveDeadline($batch);
        if (! $dl) {
            return false;
        }

        return Carbon::now()->greaterThan($dl);
    }

    public function cdoIsPendingWithinWindow(OnboardingBatch $batch): bool
    {
        if (! $batch->locked_at || ! $batch->isLocked()) {
            return false;
        }
        if ($this->hasCdoPdf($batch) || $batch->pdf_compliance_waived) {
            return false;
        }
        $dl = $this->effectiveDeadline($batch);
        if (! $dl) {
            return false;
        }

        return Carbon::now()->lessThanOrEqualTo($dl);
    }

    public function hubWriteBlocked(int $hubId): bool
    {
        $batches = OnboardingBatch::query()
            ->where('hub_id', $hubId)
            ->where('status', 'locked')
            ->whereNotNull('locked_at')
            ->get();

        foreach ($batches as $b) {
            if ($this->cdoIsOverdue($b)) {
                return true;
            }
        }

        return false;
    }

    public function countOverdueBatches(int $hubId): int
    {
        return OnboardingBatch::query()
            ->where('hub_id', $hubId)
            ->where('status', 'locked')
            ->whereNotNull('locked_at')
            ->get()
            ->filter(fn (OnboardingBatch $b) => $this->cdoIsOverdue($b))
            ->count();
    }

    public function countPendingCdo(int $hubId): int
    {
        return OnboardingBatch::query()
            ->where('hub_id', $hubId)
            ->where('status', 'locked')
            ->whereNotNull('locked_at')
            ->get()
            ->filter(fn (OnboardingBatch $b) => $this->cdoIsPendingWithinWindow($b))
            ->count();
    }

    public function getDraftForDistrict(int $hubId, int $districtId): ?OnboardingBatch
    {
        return OnboardingBatch::query()
            ->where('hub_id', $hubId)
            ->where('district_id', $districtId)
            ->where('status', 'draft')
            ->first();
    }

    public function draftMemberCount(int $batchId): int
    {
        return OnboardingBatchDraftCfa::query()->where('onboarding_batch_id', $batchId)->count();
    }

    public function cfaIsOnboardedLocked(int $cfaSubmissionId): bool
    {
        return OnboardingBatchCfa::query()->where('cfa_submission_id', $cfaSubmissionId)->exists();
    }

    public function cfaInOtherDraft(int $cfaSubmissionId, int $exceptBatchId): bool
    {
        return OnboardingBatchDraftCfa::query()
            ->where('cfa_submission_id', $cfaSubmissionId)
            ->where('onboarding_batch_id', '!=', $exceptBatchId)
            ->exists();
    }

    /**
     * @return array{ok: bool, error?: string, data?: mixed}
     */
    public function handleApi(string $action, User $user, array $input, ?Request $request = null): array
    {
        if ($user->role !== 'hub_admin' || ! $user->hub_id) {
            return ['ok' => false, 'error' => 'Unauthorized'];
        }
        $hubId = (int) $user->hub_id;
        $districtIds = $this->districtIdsForHub($hubId);
        if ($districtIds === []) {
            return ['ok' => false, 'error' => 'No districts for hub'];
        }

        $blocked = $this->hubWriteBlocked($hubId);
        $blockedExempt = in_array($action, [
            'dashboard_stats', 'pool_list', 'draft_members', 'batches_list', 'later_list',
            'lock_batch', 'cancel_draft', 'remove_from_draft', 'add_to_draft',
            'provision_incubatees', 'request_unlock', 'relock_batch', 'batch_detail',
            'edit_batch', 'delete_batch',
        ], true);

        if ($blocked && ! $blockedExempt) {
            return ['ok' => false, 'error' => 'CDO PDF overdue — upload pending documents or ask state admin to extend deadline. Creating new batches is blocked.'];
        }

        return match ($action) {
            'dashboard_stats' => ['ok' => true, 'data' => [
                'blocked' => $blocked,
                'overdue_cdo' => $this->countOverdueBatches($hubId),
                'pending_cdo' => $this->countPendingCdo($hubId),
            ]],
            'pool_list' => $this->poolList($hubId, $districtIds, $input),
            'draft_members' => $this->draftMembers($hubId, $input),
            'batches_list' => $this->batchesList($hubId),
            'batch_detail' => $this->batchDetail($hubId, $input),
            'later_list' => $this->laterList($hubId, $districtIds, $input),
            'create_draft' => $this->createDraft($hubId, $user, $districtIds, $input),
            'cancel_draft' => $this->cancelDraft($hubId, $input),
            'add_to_draft' => $this->addToDraft($hubId, $input),
            'remove_from_draft' => $this->removeFromDraft($hubId, $input),
            'edit_batch' => $this->editBatch($hubId, $user, $input, $request),
            'delete_batch' => $this->deleteBatch($hubId, $user, $input, $request),
            'lock_batch' => $this->lockBatch($hubId, $user, $input),
            'request_unlock' => $this->requestUnlock($hubId, $user, $input, $request),
            'relock_batch' => $this->relockBatch($hubId, $user, $input, $request),
            'set_choice' => $this->setChoice($hubId, $user, $districtIds, $input),
            'restore_later' => $this->restoreLater($hubId, $user, $districtIds, $input),
            'undo_reject' => $this->undoReject($hubId, $user, $districtIds, $input),
            'provision_incubatees' => $this->provisionIncubatees($hubId, $input),
            default => ['ok' => false, 'error' => 'Unknown action'],
        };
    }

    /**
     * @return array{ok: bool, error?: string, data?: array<string, mixed>}
     */
    private function provisionIncubatees(int $hubId, array $input): array
    {
        $batchId = (int) ($input['batch_id'] ?? 0);
        if ($batchId <= 0) {
            return ['ok' => false, 'error' => 'batch_id required'];
        }

        $batch = OnboardingBatch::query()->find($batchId);
        if ($batch === null || (int) $batch->hub_id !== $hubId) {
            return ['ok' => false, 'error' => 'Batch not found'];
        }

        if (! $batch->isLocked()) {
            return ['ok' => false, 'error' => 'Lock the batch first, then create incubatee portal accounts.'];
        }

        try {
            $result = app(IncubateeProvisioningService::class)->provision($batchId, dryRun: false);
        } catch (\RuntimeException $e) {
            return ['ok' => false, 'error' => $e->getMessage()];
        } catch (\InvalidArgumentException $e) {
            return ['ok' => false, 'error' => $e->getMessage()];
        }

        return ['ok' => true, 'data' => $result];
    }

    /**
     * @param  list<int>  $districtIds
     * @return array{ok: bool, error?: string, data?: mixed}
     */
    private function poolList(int $hubId, array $districtIds, array $input): array
    {
        $districtId = (int) ($input['district_id'] ?? 0);
        if (! in_array($districtId, $districtIds, true)) {
            return ['ok' => false, 'error' => 'Invalid district'];
        }
        $q = trim((string) ($input['q'] ?? ''));
        $requestedFyId = (int) ($input['fiscal_year_id'] ?? 0);
        $fyId = $requestedFyId > 0
            ? (int) FiscalYear::query()->whereKey($requestedFyId)->value('id')
            : ($this->activeFiscalYearId() ?? 0);
        if ($requestedFyId > 0 && $fyId <= 0) {
            return ['ok' => false, 'error' => 'Invalid fiscal year'];
        }
        $fy = $fyId > 0 ? FiscalYear::query()->find($fyId) : null;
        if (! $fy) {
            return ['ok' => false, 'error' => 'Fiscal year not found'];
        }
        $district = District::query()->find($districtId);
        if (! $district) {
            return ['ok' => false, 'error' => 'District not found'];
        }

        $poolFilter = trim((string) ($input['pool_filter'] ?? ''));
        if ($poolFilter === 'phase1-2021-2024') {
            return [
                'ok' => true,
                'data' => ['candidates' => $this->poolListFromLegacyPhase1(
                    $hubId,
                    $district,
                    $q,
                    '2021-01-01',
                    '2024-12-31'
                )],
            ];
        }
        if ($poolFilter === 'phase1-all') {
            return [
                'ok' => true,
                'data' => ['candidates' => $this->poolListFromLegacyPhase1($hubId, $district, $q)],
            ];
        }

        $phase1Candidates = $this->poolListFromLegacyPhase1($hubId, $district, $q);

        // Hybrid source:
        // - FY 2025-26 uses legacy Phase-2 CFA rows.
        // - Current/new FYs use local cfa_submissions.
        // Non-onboarded Phase-1 (tblapplication) rows are merged into every FY view.
        if ((string) $fy->code === '2025-26') {
            $primary = $this->poolListFromLegacy($hubId, $district, $fy, $q);
            if (! $primary['ok']) {
                if ($phase1Candidates === []) {
                    return $primary;
                }

                return ['ok' => true, 'data' => ['candidates' => array_slice($phase1Candidates, 0, 200)]];
            }

            return [
                'ok' => true,
                'data' => ['candidates' => $this->mergePoolCandidates(
                    $primary['data']['candidates'] ?? [],
                    $phase1Candidates
                )],
            ];
        }

        $primary = $this->poolListFromLocal($hubId, (int) $district->id, (int) $fy->id, $q);

        return [
            'ok' => true,
            'data' => ['candidates' => $this->mergePoolCandidates(
                $primary['data']['candidates'] ?? [],
                $phase1Candidates
            )],
        ];
    }

    private function poolListFromLocal(int $hubId, int $districtId, int $fyId, string $q): array
    {
        if ($q !== '') {
            return $this->searchLocalPoolWithEligibility($hubId, $districtId, $fyId, $q);
        }

        $query = CfaSubmission::query()
            ->where('district_id', $districtId)
            ->where('fiscal_year_id', $fyId)
            ->whereDoesntHave('onboardingBatchMembership')
            ->whereDoesntHave('draftBatchMembership', function ($q) {
                $q->whereHas('batch', fn ($b) => $b->where('status', 'draft'));
            })
            ->whereNotExists(function ($sub) use ($hubId, $districtId) {
                $sub->selectRaw('1')
                    ->from('cfa_hub_choice_states as c')
                    ->whereColumn('c.cfa_submission_id', 'cfa_submissions.id')
                    ->where('c.hub_id', $hubId)
                    ->where('c.district_id', $districtId)
                    ->whereIn('c.state', ['reject', 'later']);
            });

        $rows = $query->orderByDesc('id')->limit(200)->get()->map(fn (CfaSubmission $c) => [
            'id' => $c->id,
            'application_no' => $c->application_no ?? (string) $c->id,
            'applicant_name' => $c->applicant_name,
            'stage' => strtoupper((string) ($c->payload['form_stage'] ?? $c->payload['stage'] ?? '—')),
            'eligible' => true,
            'eligibility_status' => 'eligible',
        ])->values()->all();

        return ['ok' => true, 'data' => ['candidates' => $rows]];
    }

    /**
     * Search across this hub and explain why each matching local CFA is or is
     * not eligible for the selected district and fiscal-year pool.
     *
     * @return array{ok: true, data: array{candidates: list<array<string, mixed>>}}
     */
    private function searchLocalPoolWithEligibility(int $hubId, int $districtId, int $fyId, string $q): array
    {
        $like = '%'.$q.'%';
        $currentDraftId = (int) ($this->getDraftForDistrict($hubId, $districtId)?->id ?? 0);

        $rows = CfaSubmission::query()
            ->whereHas('district', fn ($district) => $district->where('hub_id', $hubId))
            ->where(function ($query) use ($like): void {
                $query->where('application_no', 'like', $like)
                    ->orWhere('applicant_name', 'like', $like)
                    ->orWhere('phone', 'like', $like);
            })
            ->with([
                'district:id,hub_id,name',
                'fiscalYear:id,code',
                'onboardingBatchMembership.batch:id,hub_id,district_id,name,status',
                'draftBatchMembership.batch:id,hub_id,district_id,name,status',
            ])
            ->orderByDesc('id')
            ->limit(200)
            ->get();

        $choiceStates = CfaHubChoiceState::query()
            ->where('hub_id', $hubId)
            ->whereIn('cfa_submission_id', $rows->pluck('id'))
            ->get()
            ->keyBy(fn (CfaHubChoiceState $state) => (int) $state->cfa_submission_id);

        $candidates = $rows->map(function (CfaSubmission $cfa) use (
            $choiceStates,
            $currentDraftId,
            $districtId,
            $fyId,
        ): array {
            $status = 'eligible';
            $label = 'Eligible';
            $detail = null;
            $batchId = null;
            $lockedBatch = $cfa->onboardingBatchMembership?->batch;
            $draftBatch = $cfa->draftBatchMembership?->batch;
            $choiceState = $choiceStates->get((int) $cfa->id)?->state;

            if ($lockedBatch !== null) {
                $status = 'onboarded';
                $label = 'Already onboarded';
                $detail = 'Included in '.$lockedBatch->name.'.';
                $batchId = (int) $lockedBatch->id;
            } elseif ($draftBatch !== null && $draftBatch->status === 'draft') {
                $batchId = (int) $draftBatch->id;
                if ($currentDraftId > 0 && $batchId === $currentDraftId) {
                    $status = 'current_draft';
                    $label = 'Already in current draft';
                    $detail = 'Included in '.$draftBatch->name.'.';
                } else {
                    $status = 'other_draft';
                    $label = 'Already in another draft';
                    $detail = 'Included in '.$draftBatch->name.'.';
                }
            } elseif ($choiceState === 'later') {
                $status = 'later';
                $label = 'Marked for later';
                $detail = 'Restore it from the Later list before adding.';
            } elseif ($choiceState === 'reject') {
                $status = 'rejected';
                $label = 'Rejected';
                $detail = 'Undo the rejection before adding it to a batch.';
            } elseif ((int) $cfa->district_id !== $districtId) {
                $status = 'different_district';
                $label = 'Different district';
                $detail = 'This CFA belongs to '.($cfa->district?->name ?? 'another district').'.';
            } elseif ((int) $cfa->fiscal_year_id !== $fyId) {
                $status = 'different_fiscal_year';
                $label = 'Different fiscal year';
                $detail = 'This CFA belongs to '.($cfa->fiscalYear?->code ?? 'another fiscal year').'.';
            }

            return [
                'id' => (int) $cfa->id,
                'application_no' => $cfa->application_no ?? (string) $cfa->id,
                'applicant_name' => $cfa->applicant_name,
                'stage' => strtoupper((string) ($cfa->payload['form_stage'] ?? $cfa->payload['stage'] ?? '—')),
                'eligible' => $status === 'eligible',
                'eligibility_status' => $status,
                'eligibility_label' => $label,
                'eligibility_detail' => $detail,
                'batch_id' => $batchId,
                'district_name' => $cfa->district?->name,
                'fiscal_year' => $cfa->fiscalYear?->code,
            ];
        })->values()->all();

        return ['ok' => true, 'data' => ['candidates' => $candidates]];
    }

    private function poolListFromLegacy(int $hubId, District $district, FiscalYear $fy, string $q): array
    {
        if ((string) config('database.connections.legacy.database', '') === '') {
            return ['ok' => false, 'error' => 'Legacy database is not configured'];
        }
        try {
            $hasLegacyTables = Schema::connection('legacy')->hasTable('rbi_applications')
                && Schema::connection('legacy')->hasTable('rbi_applicant_details');
        } catch (\Throwable) {
            return ['ok' => false, 'error' => 'Legacy database is unavailable'];
        }
        if (! $hasLegacyTables) {
            return ['ok' => false, 'error' => 'Legacy CFA tables are missing'];
        }

        $start = Carbon::parse($fy->starts_on)->toDateString();
        $end = Carbon::parse($fy->ends_on)->toDateString();
        $districtNames = $this->legacyDistrictNamesFor($district);

        $query = DB::connection('legacy')
            ->table('rbi_applications as a')
            ->leftJoin('rbi_applicant_details as d', 'd.application_id', '=', 'a.id')
            ->whereNotNull('a.submission_date')
            ->whereBetween(DB::raw('DATE(a.submission_date)'), [$start, $end])
            ->whereIn('d.district', $districtNames)
            ->select([
                'a.id as legacy_id',
                'a.application_no',
                'a.form_stage',
                'a.business_category',
                'a.submission_date',
                'd.applicant_name',
                'd.phone',
                'd.district',
                'd.block',
            ]);

        if ($q !== '') {
            $like = '%'.$q.'%';
            $query->where(function ($qq) use ($like) {
                $qq->where('a.application_no', 'like', $like)
                    ->orWhere('d.applicant_name', 'like', $like)
                    ->orWhere('d.phone', 'like', $like);
            });
        }

        // Exclude applicants already onboarded in legacy onboarding tables (if present).
        if (Schema::connection('legacy')->hasTable('rbi_onboarded_applicants')) {
            $query->leftJoin('rbi_onboarded_applicants as oa', 'oa.application_id', '=', 'a.id')
                ->whereNull('oa.id');
        }

        $legacyRows = $query->orderByDesc('a.submission_date')->orderByDesc('a.id')->limit(300)->get();
        if ($legacyRows->isEmpty()) {
            return ['ok' => true, 'data' => ['candidates' => []]];
        }

        $localRows = collect();
        foreach ($legacyRows as $row) {
            $localRows->push($this->syncLegacyCfaIntoLocal($row, (int) $district->id, (int) $fy->id));
        }

        return ['ok' => true, 'data' => ['candidates' => $this->filterPoolCandidates($hubId, (int) $district->id, $localRows)]];
    }

    /**
     * Non-onboarded Phase-1 CFA rows from legacy tblapplication, synced into cfa_submissions.
     *
     * @return list<array{id: int, application_no: string, applicant_name: string, stage: string}>
     */
    private function poolListFromLegacyPhase1(
        int $hubId,
        District $district,
        string $q,
        ?string $applicationDateFrom = null,
        ?string $applicationDateTo = null,
    ): array {
        if ((string) config('database.connections.legacy_phase1.database', '') === '') {
            return [];
        }

        try {
            if (! Schema::connection('legacy_phase1')->hasTable('tblapplication')) {
                return [];
            }
        } catch (\Throwable) {
            return [];
        }

        $fiscalYearId = (int) (FiscalYear::query()->where('code', '2024-25')->value('id') ?? 0);
        if ($fiscalYearId <= 0) {
            return [];
        }

        $query = DB::connection('legacy_phase1')->table('tblapplication');
        LegacyPhase1DistrictResolver::applyDistrictFilter($query, (string) $district->name);
        LegacyPhase1DistrictResolver::applyOnboardFilter($query, 'non_onboarded');

        if ($applicationDateFrom !== null && $applicationDateTo !== null) {
            $query->whereNotNull('ApplicationDate')
                ->whereBetween(DB::raw('DATE(ApplicationDate)'), [$applicationDateFrom, $applicationDateTo]);
        }

        $query->select([
            'ID as legacy_id',
            'ApplicationNumber as application_no',
            'FullName as applicant_name',
            'MobileNumber as phone',
            'ApplicationDate as application_date',
            'FatherName as legacy_district',
            'hub as legacy_region',
            'City as legacy_block',
        ]);

        if ($q !== '') {
            $like = '%'.$q.'%';
            $query->where(function ($qq) use ($like): void {
                $qq->where('ApplicationNumber', 'like', $like)
                    ->orWhere('FullName', 'like', $like)
                    ->orWhere('MobileNumber', 'like', $like);
            });
        }

        $legacyRows = $query->orderByDesc('ApplicationDate')->orderByDesc('ID')->limit(300)->get();
        if ($legacyRows->isEmpty()) {
            return [];
        }

        $localRows = collect();
        foreach ($legacyRows as $row) {
            $localRows->push($this->syncLegacyPhase1CfaIntoLocal($row, (int) $district->id, $fiscalYearId));
        }

        return $this->filterPoolCandidates($hubId, (int) $district->id, $localRows);
    }

    /**
     * @param  Collection<int, CfaSubmission>  $localRows
     * @return list<array{id: int, application_no: string, applicant_name: string, stage: string}>
     */
    private function filterPoolCandidates(int $hubId, int $districtId, Collection $localRows, int $limit = 200): array
    {
        $ids = $localRows->pluck('id')->map(fn ($v) => (int) $v)->all();
        if ($ids === []) {
            return [];
        }

        $lockedIds = OnboardingBatchCfa::query()
            ->whereIn('cfa_submission_id', $ids)
            ->pluck('cfa_submission_id')
            ->map(fn ($v) => (int) $v)
            ->all();
        $draftIds = OnboardingBatchDraftCfa::query()
            ->whereIn('cfa_submission_id', $ids)
            ->whereNotIn('cfa_submission_id', $lockedIds)
            ->pluck('cfa_submission_id')
            ->map(fn ($v) => (int) $v)
            ->all();
        $choiceBlocked = CfaHubChoiceState::query()
            ->where('hub_id', $hubId)
            ->where('district_id', $districtId)
            ->whereIn('cfa_submission_id', $ids)
            ->whereIn('state', ['reject', 'later'])
            ->pluck('cfa_submission_id')
            ->map(fn ($v) => (int) $v)
            ->all();

        $exclude = array_flip(array_unique(array_merge($lockedIds, $draftIds, $choiceBlocked)));

        return $localRows
            ->reject(fn (CfaSubmission $c) => isset($exclude[(int) $c->id]))
            ->take($limit)
            ->map(fn (CfaSubmission $c) => [
                'id' => $c->id,
                'application_no' => $c->application_no ?? (string) $c->id,
                'applicant_name' => $c->applicant_name,
                'stage' => strtoupper((string) ($c->payload['form_stage'] ?? $c->payload['stage'] ?? '—')),
            ])
            ->values()
            ->all();
    }

    /**
     * @param  list<array{id: int, application_no: string, applicant_name: string, stage: string}>  $primaryCandidates
     * @param  list<array{id: int, application_no: string, applicant_name: string, stage: string}>  $phase1Candidates
     * @return list<array{id: int, application_no: string, applicant_name: string, stage: string}>
     */
    private function mergePoolCandidates(array $primaryCandidates, array $phase1Candidates, int $limit = 200): array
    {
        $seen = [];
        $merged = [];

        foreach (array_merge($primaryCandidates, $phase1Candidates) as $row) {
            $id = (int) ($row['id'] ?? 0);
            if ($id <= 0 || isset($seen[$id])) {
                continue;
            }
            $seen[$id] = true;
            $merged[] = $row;
            if (count($merged) >= $limit) {
                break;
            }
        }

        return $merged;
    }

    /**
     * @return list<string>
     */
    private function legacyDistrictNamesFor(District $district): array
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

    private function syncLegacyCfaIntoLocal(object $legacyRow, int $districtId, int $fiscalYearId): CfaSubmission
    {
        $applicationNo = trim((string) ($legacyRow->application_no ?? ''));
        $legacyId = (int) ($legacyRow->legacy_id ?? 0);
        $lookupNo = $applicationNo !== '' ? $applicationNo : ('legacy-'.$legacyId);

        /** @var CfaSubmission $submission */
        $submission = CfaSubmission::query()->firstOrCreate(
            [
                'source' => 'legacy_phase2',
                'application_no' => $lookupNo,
            ],
            [
                'fiscal_year_id' => $fiscalYearId,
                'district_id' => $districtId,
                'referral_user_id' => null,
                'applicant_name' => (string) ($legacyRow->applicant_name ?? 'Unknown'),
                'phone' => (string) ($legacyRow->phone ?? ''),
                'payload' => [
                    'legacy_application_id' => $legacyId,
                    'form_stage' => strtolower(trim((string) ($legacyRow->form_stage ?? ''))),
                    'business_category' => trim((string) ($legacyRow->business_category ?? '')),
                    'legacy_district' => (string) ($legacyRow->district ?? ''),
                    'legacy_block' => (string) ($legacyRow->block ?? ''),
                    'legacy_submission_date' => (string) ($legacyRow->submission_date ?? ''),
                ],
            ]
        );

        // Keep local mirror fresh without changing identity.
        $submission->fill([
            'fiscal_year_id' => $fiscalYearId,
            'district_id' => $districtId,
            'applicant_name' => (string) ($legacyRow->applicant_name ?? $submission->applicant_name),
            'phone' => (string) ($legacyRow->phone ?? $submission->phone),
            'payload' => [
                'legacy_application_id' => $legacyId,
                'form_stage' => strtolower(trim((string) ($legacyRow->form_stage ?? ''))),
                'business_category' => trim((string) ($legacyRow->business_category ?? '')),
                'legacy_district' => (string) ($legacyRow->district ?? ''),
                'legacy_block' => (string) ($legacyRow->block ?? ''),
                'legacy_submission_date' => (string) ($legacyRow->submission_date ?? ''),
            ],
        ]);
        $submission->save();

        return $submission;
    }

    private function syncLegacyPhase1CfaIntoLocal(object $legacyRow, int $districtId, int $fiscalYearId): CfaSubmission
    {
        $applicationNo = trim((string) ($legacyRow->application_no ?? ''));
        $legacyId = (int) ($legacyRow->legacy_id ?? 0);
        $lookupNo = $applicationNo !== '' ? $applicationNo : ('legacy-p1-'.$legacyId);

        /** @var CfaSubmission $submission */
        $submission = CfaSubmission::query()->firstOrCreate(
            [
                'source' => 'legacy_phase1',
                'application_no' => $lookupNo,
            ],
            [
                'fiscal_year_id' => $fiscalYearId,
                'district_id' => $districtId,
                'referral_user_id' => null,
                'applicant_name' => (string) ($legacyRow->applicant_name ?? 'Unknown'),
                'phone' => (string) ($legacyRow->phone ?? ''),
                'payload' => [
                    'legacy_phase1_id' => $legacyId,
                    'legacy_district' => (string) ($legacyRow->legacy_district ?? ''),
                    'legacy_region' => (string) ($legacyRow->legacy_region ?? ''),
                    'legacy_block' => (string) ($legacyRow->legacy_block ?? ''),
                    'legacy_application_date' => (string) ($legacyRow->application_date ?? ''),
                ],
            ]
        );

        $submission->fill([
            'fiscal_year_id' => $fiscalYearId,
            'district_id' => $districtId,
            'applicant_name' => (string) ($legacyRow->applicant_name ?? $submission->applicant_name),
            'phone' => (string) ($legacyRow->phone ?? $submission->phone),
            'payload' => [
                'legacy_phase1_id' => $legacyId,
                'legacy_district' => (string) ($legacyRow->legacy_district ?? ''),
                'legacy_region' => (string) ($legacyRow->legacy_region ?? ''),
                'legacy_block' => (string) ($legacyRow->legacy_block ?? ''),
                'legacy_application_date' => (string) ($legacyRow->application_date ?? ''),
            ],
        ]);
        $submission->save();

        return $submission;
    }

    private function draftMembers(int $hubId, array $input): array
    {
        $batchId = (int) ($input['batch_id'] ?? 0);
        $batch = OnboardingBatch::query()->find($batchId);
        if (! $batch || $batch->hub_id !== $hubId || ! $this->canMutateBatchByHub($batch, $hubId)) {
            return ['ok' => false, 'error' => 'Not a draft batch'];
        }

        $membersRows = $batch->isDraft()
            ? $batch->draftCfas()->with('cfaSubmission')->orderBy('onboarding_batch_draft_cfa.id')->get()
            : $batch->batchCfas()->with('cfaSubmission')->orderBy('onboarding_batch_cfa.id')->get();

        $members = $membersRows->map(fn (OnboardingBatchDraftCfa|OnboardingBatchCfa $r) => [
            'id' => $r->cfa_submission_id,
            'application_no' => $r->cfaSubmission->application_no ?? (string) $r->cfa_submission_id,
            'applicant_name' => $r->cfaSubmission->applicant_name,
            'stage_key' => $this->stageKeyFromCfa($r->cfaSubmission),
        ]);

        return ['ok' => true, 'data' => [
            'batch' => [
                'id' => $batch->id,
                'name' => $batch->name,
                'target_size' => $batch->target_size,
                'district_id' => $batch->district_id,
                'status' => $batch->status,
            ],
            'members' => $members,
            'count' => $members->count(),
        ]];
    }

    private function batchesList(int $hubId): array
    {
        $list = OnboardingBatch::query()
            ->where('hub_id', $hubId)
            ->with('district')
            ->orderByDesc('id')
            ->limit(100)
            ->get()
            ->map(function (OnboardingBatch $b) {
                $memberCount = $b->isDraft()
                    ? $this->draftMemberCount($b->id)
                    : $b->batchCfas()->count();

                return [
                    'id' => $b->id,
                    'name' => $b->name,
                    'district_id' => $b->district_id,
                    'district_name' => $b->district?->name ?? '',
                    'status' => $b->status,
                    'target_size' => $b->target_size,
                    'onboarding_date' => optional($b->onboarding_date)->toDateString(),
                    'locked_at' => $b->locked_at?->toIso8601String(),
                    'member_count' => $memberCount,
                    'has_cdo_pdf' => $this->hasCdoPdf($b),
                    'cdo_overdue' => $this->cdoIsOverdue($b),
                    'cdo_pending' => $this->cdoIsPendingWithinWindow($b),
                    'edit_unlocked' => $b->isEditUnlocked(),
                    'pending_unlock_requests' => OnboardingBatchEditRequest::query()
                        ->where('onboarding_batch_id', $b->id)
                        ->where('status', 'pending')
                        ->count(),
                ];
            });

        return ['ok' => true, 'data' => ['batches' => $list]];
    }

    private function batchDetail(int $hubId, array $input): array
    {
        $batchId = (int) ($input['batch_id'] ?? 0);
        $batch = OnboardingBatch::query()->with('district')->find($batchId);
        if (! $batch || (int) $batch->hub_id !== $hubId) {
            return ['ok' => false, 'error' => 'Batch not found'];
        }

        $members = ($batch->isDraft()
            ? $batch->draftCfas()->with('cfaSubmission')
            : $batch->batchCfas()->with('cfaSubmission'))
            ->orderBy('id')
            ->get()
            ->map(function (OnboardingBatchDraftCfa|OnboardingBatchCfa $row) {
                $cfa = $row->cfaSubmission;
                $payload = is_array($cfa?->payload) ? $cfa->payload : [];
                $stageKey = $this->stageKeyFromCfa($cfa);
                $stageLabel = $stageKey !== 'unknown' ? strtoupper($stageKey) : 'UNKNOWN';
                $bizCategory = trim((string) ($payload['business_category'] ?? ''));

                return [
                    'id' => (int) ($cfa?->id ?? 0),
                    'application_no' => (string) ($cfa?->application_no ?? $row->cfa_submission_id),
                    'applicant_name' => (string) ($cfa?->applicant_name ?? 'N/A'),
                    'phone' => (string) ($cfa?->phone ?? ''),
                    'profile_url' => $cfa ? route('hub.batches.cfa.show', ['cfa_submission' => $cfa->id]) : null,
                    'source' => (string) ($cfa?->source ?? ''),
                    'stage_key' => $stageKey,
                    'stage_label' => $stageLabel,
                    'business_category' => $bizCategory,
                    'applicant_category' => $this->applicantCategoryFromPayload($payload),
                    'member_of_shg' => $this->yesNoFromPayload($payload, ['member_of_shg_cbo', 'member_of_shg', 'is_shg_member']),
                    'lakhpati_didi' => $this->yesNoFromPayload($payload, ['lakhpati_didi', 'is_lakhpati_didi']),
                ];
            })
            ->values();

        // Backfill missing business category from legacy DB for legacy-sourced members.
        $legacyCategoryByAppNo = [];
        $missingLegacyAppNos = $members
            ->filter(function (array $member): bool {
                return trim((string) ($member['business_category'] ?? '')) === ''
                    && (string) ($member['source'] ?? '') === 'legacy_phase2'
                    && trim((string) ($member['application_no'] ?? '')) !== '';
            })
            ->pluck('application_no')
            ->map(fn ($v) => trim((string) $v))
            ->unique()
            ->values()
            ->all();

        if ($missingLegacyAppNos !== [] && (string) config('database.connections.legacy.database', '') !== '') {
            try {
                if (Schema::connection('legacy')->hasTable('rbi_applications')) {
                    $legacyRows = DB::connection('legacy')
                        ->table('rbi_applications')
                        ->whereIn('application_no', $missingLegacyAppNos)
                        ->select(['application_no', 'business_category'])
                        ->get();
                    foreach ($legacyRows as $legacyRow) {
                        $appNo = trim((string) ($legacyRow->application_no ?? ''));
                        $cat = trim((string) ($legacyRow->business_category ?? ''));
                        if ($appNo !== '' && $cat !== '') {
                            $legacyCategoryByAppNo[$appNo] = $cat;
                        }
                    }
                }
            } catch (\Throwable) {
                // Ignore legacy lookup failures; keep fallback category label.
            }
        }

        $members = $members->map(function (array $member) use ($legacyCategoryByAppNo): array {
            $bizCategory = trim((string) ($member['business_category'] ?? ''));
            if ($bizCategory === '') {
                $appNo = trim((string) ($member['application_no'] ?? ''));
                $bizCategory = $legacyCategoryByAppNo[$appNo] ?? 'Not specified';
            }
            $member['business_category'] = $bizCategory;

            return $member;
        })->values();

        $cfaIds = $members->pluck('id')->filter()->unique()->values()->all();
        $cfaModels = $cfaIds === [] ? collect() : CfaSubmission::query()
            ->whereIn('id', $cfaIds)
            ->get()
            ->keyBy('id');
        $userEmails = $cfaIds === [] ? collect() : User::query()
            ->where('role', 'incubatee')
            ->whereIn('cfa_submission_id', $cfaIds)
            ->pluck('email', 'cfa_submission_id');

        $members = $members->map(function (array $member) use ($userEmails, $cfaModels): array {
            $cid = (int) $member['id'];
            $email = $userEmails[$cid] ?? null;
            $hasAccount = is_string($email) && $email !== '';
            $member['incubatee_account_ready'] = $hasAccount;
            if ($hasAccount) {
                $member['portal_username'] = $email;
            } else {
                $cfa = $cfaModels->get($cid);
                $member['portal_username'] = $cfa ? IncubateeLoginEmailResolver::forSubmission($cfa) : '';
            }

            return $member;
        })->values();

        $stageMix = [
            'seed' => 0,
            'early' => 0,
            'growth' => 0,
            'unknown' => 0,
        ];
        $categoryMix = [];
        $applicantCategoryMix = [];
        $memberOfShgMix = [];
        $lakhpatiDidiMix = [];
        foreach ($members as $member) {
            $stageKey = (string) ($member['stage_key'] ?? 'unknown');
            if (! array_key_exists($stageKey, $stageMix)) {
                $stageKey = 'unknown';
            }
            $stageMix[$stageKey]++;
            $biz = (string) ($member['business_category'] ?? 'Unspecified');
            $categoryMix[$biz] = (int) ($categoryMix[$biz] ?? 0) + 1;
            $appCat = (string) ($member['applicant_category'] ?? 'Not specified');
            $applicantCategoryMix[$appCat] = (int) ($applicantCategoryMix[$appCat] ?? 0) + 1;
            $shg = (string) ($member['member_of_shg'] ?? 'Not specified');
            $memberOfShgMix[$shg] = (int) ($memberOfShgMix[$shg] ?? 0) + 1;
            $lakhpati = (string) ($member['lakhpati_didi'] ?? 'Not specified');
            $lakhpatiDidiMix[$lakhpati] = (int) ($lakhpatiDidiMix[$lakhpati] ?? 0) + 1;
        }
        arsort($categoryMix);
        arsort($applicantCategoryMix);
        arsort($memberOfShgMix);
        arsort($lakhpatiDidiMix);

        return ['ok' => true, 'data' => [
            'batch' => [
                'id' => (int) $batch->id,
                'name' => (string) $batch->name,
                'district_name' => (string) ($batch->district?->name ?? ''),
                'status' => (string) $batch->status,
                'target_size' => (int) $batch->target_size,
                'onboarding_date' => optional($batch->onboarding_date)->toDateString(),
                'locked_at' => $batch->locked_at?->toIso8601String(),
                'incubatee_default_password' => (string) config('incubatee.default_password', ''),
            ],
            'summary' => [
                'members_count' => $members->count(),
                'stage_mix' => $stageMix,
                'business_category_mix' => $categoryMix,
                'applicant_category_mix' => $applicantCategoryMix,
                'member_of_shg_mix' => $memberOfShgMix,
                'lakhpati_didi_mix' => $lakhpatiDidiMix,
            ],
            'members' => $members,
        ]];
    }

    /**
     * @param  list<int>  $districtIds
     */
    private function laterList(int $hubId, array $districtIds, array $input): array
    {
        $requestedDistrictId = (int) ($input['district_id'] ?? 0);
        $rows = CfaHubChoiceState::query()
            ->where('hub_id', $hubId)
            ->where('state', 'later')
            ->with(['cfaSubmission'])
            ->when(
                $requestedDistrictId > 0 && in_array($requestedDistrictId, $districtIds, true),
                fn ($q) => $q->where('district_id', $requestedDistrictId),
                fn ($q) => $q->whereIn('district_id', $districtIds)
            )
            ->orderByDesc('updated_at')
            ->limit(200)
            ->get()
            ->map(fn (CfaHubChoiceState $r) => [
                'application_id' => $r->cfa_submission_id,
                'district_id' => $r->district_id,
                'application_no' => $r->cfaSubmission->application_no ?? (string) $r->cfa_submission_id,
                'applicant_name' => $r->cfaSubmission->applicant_name,
            ]);

        return ['ok' => true, 'data' => ['rows' => $rows]];
    }

    /**
     * @param  list<int>  $districtIds
     */
    private function createDraft(int $hubId, User $user, array $districtIds, array $input): array
    {
        $districtId = (int) ($input['district_id'] ?? 0);
        if (! in_array($districtId, $districtIds, true)) {
            return ['ok' => false, 'error' => 'Invalid district'];
        }
        $targetSize = (int) ($input['target_size'] ?? 0);
        if ($targetSize < 1 || $targetSize > 500) {
            return ['ok' => false, 'error' => 'Target size 1–500'];
        }
        if ($this->getDraftForDistrict($hubId, $districtId)) {
            return ['ok' => false, 'error' => 'A draft batch already exists for this district.'];
        }

        $onboardingDate = (string) ($input['onboarding_date'] ?? now()->toDateString());
        if (! TodayOnlyDate::isInCurrentMonth(substr($onboardingDate, 0, 10))) {
            return ['ok' => false, 'error' => 'Onboarding date must be in the current month.'];
        }
        $name = $this->generateBatchName($hubId, $districtId);

        $batch = OnboardingBatch::query()->create([
            'hub_id' => $hubId,
            'district_id' => $districtId,
            'name' => $name,
            'target_size' => $targetSize,
            'status' => 'draft',
            'onboarding_date' => $onboardingDate,
            'created_by' => $user->id,
        ]);

        return ['ok' => true, 'data' => ['batch_id' => $batch->id, 'batch_name' => $batch->name]];
    }

    private function cancelDraft(int $hubId, array $input): array
    {
        $batchId = (int) ($input['batch_id'] ?? 0);
        $batch = OnboardingBatch::query()->find($batchId);
        if (! $batch || $batch->hub_id !== $hubId || ! $batch->isDraft()) {
            return ['ok' => false, 'error' => 'Invalid draft'];
        }
        DB::transaction(function () use ($batch): void {
            $batch->draftCfas()->delete();
            $batch->delete();
        });

        return ['ok' => true, 'data' => []];
    }

    private function addToDraft(int $hubId, array $input): array
    {
        $batchId = (int) ($input['batch_id'] ?? 0);
        $cfaId = (int) ($input['cfa_submission_id'] ?? 0);
        $batch = OnboardingBatch::query()->find($batchId);
        if (! $batch || $batch->hub_id !== $hubId || ! $this->canMutateBatchByHub($batch, $hubId)) {
            return ['ok' => false, 'error' => 'Batch is not editable.'];
        }
        $currentCount = $batch->isDraft() ? $this->draftMemberCount($batchId) : $batch->batchCfas()->count();
        if ($currentCount >= $batch->target_size) {
            return ['ok' => false, 'error' => 'Batch is full. Increase target size from Edit batch, or lock this batch.'];
        }
        if ($this->cfaIsOnboardedLocked($cfaId)) {
            return ['ok' => false, 'error' => 'Already in a locked batch'];
        }
        if ($this->cfaInOtherDraft($cfaId, $batchId)) {
            return ['ok' => false, 'error' => 'In another draft batch'];
        }
        $cfa = CfaSubmission::query()->find($cfaId);
        if (! $cfa || (int) $cfa->district_id !== (int) $batch->district_id) {
            return ['ok' => false, 'error' => 'Applicant district does not match batch'];
        }

        try {
            if ($batch->isDraft()) {
                OnboardingBatchDraftCfa::query()->create([
                    'onboarding_batch_id' => $batchId,
                    'cfa_submission_id' => $cfaId,
                ]);
            } else {
                OnboardingBatchCfa::query()->create([
                    'onboarding_batch_id' => $batchId,
                    'cfa_submission_id' => $cfaId,
                ]);
            }
        } catch (\Throwable) {
            return ['ok' => false, 'error' => 'Could not add (duplicate?)'];
        }

        $newc = $batch->isDraft() ? $this->draftMemberCount($batchId) : $batch->batchCfas()->count();

        return ['ok' => true, 'data' => [
            'count' => $newc,
            'ready_lock' => $newc >= $batch->target_size,
        ]];
    }

    private function removeFromDraft(int $hubId, array $input): array
    {
        $batchId = (int) ($input['batch_id'] ?? 0);
        $cfaId = (int) ($input['cfa_submission_id'] ?? 0);
        $batch = OnboardingBatch::query()->find($batchId);
        if (! $batch || $batch->hub_id !== $hubId || ! $this->canMutateBatchByHub($batch, $hubId)) {
            return ['ok' => false, 'error' => 'Batch is not editable.'];
        }
        if ($batch->isDraft()) {
            OnboardingBatchDraftCfa::query()
                ->where('onboarding_batch_id', $batchId)
                ->where('cfa_submission_id', $cfaId)
                ->delete();
        } else {
            OnboardingBatchCfa::query()
                ->where('onboarding_batch_id', $batchId)
                ->where('cfa_submission_id', $cfaId)
                ->delete();
        }

        $count = $batch->isDraft() ? $this->draftMemberCount($batchId) : $batch->batchCfas()->count();

        return ['ok' => true, 'data' => ['count' => $count]];
    }

    private function editBatch(int $hubId, User $user, array $input, ?Request $request): array
    {
        $batchId = (int) ($input['batch_id'] ?? 0);
        $batch = OnboardingBatch::query()->find($batchId);
        if (! $batch || $batch->hub_id !== $hubId || ! $this->canMutateBatchByHub($batch, $hubId)) {
            return ['ok' => false, 'error' => 'Batch is not editable'];
        }

        $name = trim((string) ($input['name'] ?? $batch->name));
        $targetSize = (int) ($input['target_size'] ?? $batch->target_size);
        $onboardingDate = (string) ($input['onboarding_date'] ?? $batch->onboarding_date?->toDateString() ?? now()->toDateString());
        $existingOnboardingDate = $batch->onboarding_date?->toDateString();
        $normalizedOnboardingDate = substr($onboardingDate, 0, 10);
        if (! TodayOnlyDate::isInCurrentMonth($normalizedOnboardingDate)
            && $normalizedOnboardingDate !== $existingOnboardingDate) {
            return ['ok' => false, 'error' => 'Onboarding date must be in the current month.'];
        }

        if ($name === '' || mb_strlen($name) > 120) {
            return ['ok' => false, 'error' => 'Batch name is required (max 120 chars)'];
        }
        if ($targetSize < 1 || $targetSize > 500) {
            return ['ok' => false, 'error' => 'Target size 1–500'];
        }
        $currentCount = $batch->isDraft() ? $this->draftMemberCount($batch->id) : $batch->batchCfas()->count();
        if ($targetSize < $currentCount) {
            return ['ok' => false, 'error' => 'Target size cannot be less than current members ('.$currentCount.').'];
        }

        $before = [
            'name' => (string) $batch->name,
            'target_size' => (int) $batch->target_size,
            'onboarding_date' => optional($batch->onboarding_date)->toDateString(),
            'member_count' => $currentCount,
        ];

        $batch->update([
            'name' => $name,
            'target_size' => $targetSize,
            'onboarding_date' => $onboardingDate,
            'updated_by' => $user->id,
        ]);

        $after = [
            'name' => (string) $batch->name,
            'target_size' => (int) $batch->target_size,
            'onboarding_date' => optional($batch->onboarding_date)->toDateString(),
            'member_count' => $currentCount,
        ];

        if ($request) {
            $this->auditLogger->record(
                $request,
                'hub_batch.updated',
                OnboardingBatch::class,
                (int) $batch->id,
                $before,
                $after,
                'Hub admin updated batch settings'
            );
        }

        return ['ok' => true, 'data' => ['batch_id' => $batch->id]];
    }

    /**
     * State admin may delete any Phase 3 batch (draft or locked).
     */
    public function deleteBatchForStateAdmin(OnboardingBatch $batch, User $user, Request $request): void
    {
        $batchId = (int) $batch->id;
        $before = $this->batchDeletionSnapshot($batch);

        $this->purgeBatchRecords($batch);

        $this->auditLogger->record(
            $request,
            'state_admin_batch.deleted',
            OnboardingBatch::class,
            $batchId,
            $before,
            null,
            'State admin deleted onboarding batch'
        );
    }

    private function deleteBatch(int $hubId, User $user, array $input, ?Request $request): array
    {
        $batchId = (int) ($input['batch_id'] ?? 0);
        $batch = OnboardingBatch::query()->find($batchId);
        if (! $batch || $batch->hub_id !== $hubId || ! $this->canMutateBatchByHub($batch, $hubId)) {
            return ['ok' => false, 'error' => 'Batch is not editable'];
        }

        $before = $this->batchDeletionSnapshot($batch);
        $this->purgeBatchRecords($batch);

        if ($request) {
            $this->auditLogger->record(
                $request,
                'hub_batch.deleted',
                OnboardingBatch::class,
                $batchId,
                $before,
                null,
                'Hub admin deleted editable batch'
            );
        }

        return ['ok' => true, 'data' => ['batch_id' => $batchId]];
    }

    /**
     * @return array<string, mixed>
     */
    private function batchDeletionSnapshot(OnboardingBatch $batch): array
    {
        $memberCount = $batch->isDraft()
            ? $this->draftMemberCount($batch->id)
            : $batch->batchCfas()->count();

        return [
            'name' => (string) $batch->name,
            'hub_id' => (int) $batch->hub_id,
            'district_id' => (int) $batch->district_id,
            'target_size' => (int) $batch->target_size,
            'onboarding_date' => optional($batch->onboarding_date)->toDateString(),
            'member_count' => $memberCount,
            'status' => (string) $batch->status,
        ];
    }

    private function purgeBatchRecords(OnboardingBatch $batch): void
    {
        DB::transaction(function () use ($batch): void {
            $batch->editRequests()->delete();

            foreach ($batch->documents()->get() as $doc) {
                $path = trim((string) $doc->path);
                if ($path !== '' && Storage::disk('local')->exists($path)) {
                    Storage::disk('local')->delete($path);
                }
            }
            $batch->documents()->delete();

            if ($batch->isDraft()) {
                $batch->draftCfas()->delete();
            } else {
                $batch->batchCfas()->delete();
            }

            $batch->delete();
        });
    }

    private function lockBatch(int $hubId, User $user, array $input): array
    {
        if (empty($input['confirm'])) {
            return ['ok' => false, 'error' => 'Confirmation required'];
        }
        $batchId = (int) ($input['batch_id'] ?? 0);
        $batch = OnboardingBatch::query()->find($batchId);
        if (! $batch || $batch->hub_id !== $hubId) {
            return ['ok' => false, 'error' => 'Invalid batch'];
        }

        $isDraftLock = $batch->isDraft();
        $isUnlockedRelock = $batch->isLocked() && $batch->isEditUnlocked();
        if (! $isDraftLock && ! $isUnlockedRelock) {
            return ['ok' => false, 'error' => 'Batch is not editable for lock'];
        }

        if ($isUnlockedRelock) {
            $cur = $batch->batchCfas()->count();
            if ($cur !== (int) $batch->target_size) {
                return ['ok' => false, 'error' => 'Set exactly '.$batch->target_size.' members before re-locking (currently '.$cur.').'];
            }

            $batch->update([
                'edit_unlocked_at' => null,
                'edit_unlocked_by_request_id' => null,
                'updated_by' => $user->id,
            ]);

            return ['ok' => true, 'data' => ['batch_id' => $batchId]];
        }

        $cur = $this->draftMemberCount($batchId);
        if ($cur !== (int) $batch->target_size) {
            return ['ok' => false, 'error' => 'Add exactly '.$batch->target_size.' CFA before locking (currently '.$cur.').'];
        }

        try {
            DB::transaction(function () use ($batch): void {
                $ids = $batch->draftCfas()->pluck('cfa_submission_id')->all();
                foreach ($ids as $cfaId) {
                    if ($this->cfaIsOnboardedLocked((int) $cfaId)) {
                        throw ValidationException::withMessages(['batch' => 'CFA '.$cfaId.' already onboarded']);
                    }
                }
                foreach ($ids as $cfaId) {
                    OnboardingBatchCfa::query()->create([
                        'onboarding_batch_id' => $batch->id,
                        'cfa_submission_id' => (int) $cfaId,
                    ]);
                }
                $batch->draftCfas()->delete();
                $batch->update([
                    'status' => 'locked',
                    'locked_at' => now(),
                ]);
            });
        } catch (\Throwable $e) {
            return ['ok' => false, 'error' => $e->getMessage()];
        }

        return ['ok' => true, 'data' => ['batch_id' => $batchId]];
    }

    private function requestUnlock(int $hubId, User $user, array $input, ?Request $request): array
    {
        $batchId = (int) ($input['batch_id'] ?? 0);
        $reason = trim((string) ($input['reason'] ?? ''));
        $expected = trim((string) ($input['expected_changes'] ?? ''));
        $batch = OnboardingBatch::query()->find($batchId);

        if (! $batch || (int) $batch->hub_id !== $hubId || ! $batch->isLocked()) {
            return ['ok' => false, 'error' => 'Only locked batches can request unlock.'];
        }
        if ($reason === '' || $expected === '') {
            return ['ok' => false, 'error' => 'Reason and expected changes are required.'];
        }

        $row = OnboardingBatchEditRequest::query()->create([
            'onboarding_batch_id' => $batch->id,
            'hub_id' => (int) $batch->hub_id,
            'district_id' => (int) $batch->district_id,
            'requested_by' => (int) $user->id,
            'reason' => $reason,
            'expected_changes' => $expected,
            'status' => 'pending',
        ]);

        User::query()
            ->where('role', 'state_admin')
            ->where('is_active', true)
            ->get()
            ->each(fn (User $admin) => $admin->notify(new HubBatchUnlockRequestedNotification($row)));

        if ($request) {
            $this->auditLogger->record(
                $request,
                'hub_batch.unlock_requested',
                OnboardingBatch::class,
                (int) $batch->id,
                null,
                ['request_id' => (int) $row->id, 'reason' => $reason, 'expected_changes' => $expected],
                'Hub admin requested locked-batch edit approval'
            );
        }

        return ['ok' => true, 'data' => ['request_id' => (int) $row->id]];
    }

    private function relockBatch(int $hubId, User $user, array $input, ?Request $request): array
    {
        $batchId = (int) ($input['batch_id'] ?? 0);
        $batch = OnboardingBatch::query()->find($batchId);
        if (! $batch || (int) $batch->hub_id !== $hubId || ! $batch->isEditUnlocked()) {
            return ['ok' => false, 'error' => 'Batch is not in edit-unlocked state.'];
        }

        $requestId = (int) ($batch->edit_unlocked_by_request_id ?? 0);
        DB::transaction(function () use ($batch, $requestId, $user): void {
            $batch->update([
                'edit_unlocked_at' => null,
                'edit_unlocked_by_request_id' => null,
            ]);

            if ($requestId > 0) {
                OnboardingBatchEditRequest::query()
                    ->where('id', $requestId)
                    ->where('status', 'approved')
                    ->update([
                        'relocked_by' => (int) $user->id,
                        'relocked_at' => now(),
                    ]);
            }
        });

        if ($request) {
            $this->auditLogger->record(
                $request,
                'hub_batch.relocked',
                OnboardingBatch::class,
                (int) $batch->id,
                ['edit_unlocked' => true, 'request_id' => $requestId],
                ['edit_unlocked' => false],
                'Hub admin manually re-locked batch after approved edits'
            );
        }

        return ['ok' => true, 'data' => ['batch_id' => $batchId]];
    }

    private function canMutateBatchByHub(OnboardingBatch $batch, int $hubId): bool
    {
        if ((int) $batch->hub_id !== $hubId) {
            return false;
        }

        return $batch->isDraft() || $batch->isEditUnlocked();
    }

    /**
     * @param  list<int>  $districtIds
     */
    private function setChoice(int $hubId, User $user, array $districtIds, array $input): array
    {
        $cfaId = (int) ($input['cfa_submission_id'] ?? 0);
        $districtId = (int) ($input['district_id'] ?? 0);
        $state = (string) ($input['state'] ?? '');
        if (! in_array($state, ['reject', 'later'], true) || ! in_array($districtId, $districtIds, true)) {
            return ['ok' => false, 'error' => 'Invalid input'];
        }
        $cfa = CfaSubmission::query()->find($cfaId);
        if (! $cfa || (int) $cfa->district_id !== $districtId) {
            return ['ok' => false, 'error' => 'Invalid CFA'];
        }

        CfaHubChoiceState::query()->updateOrCreate(
            ['hub_id' => $hubId, 'cfa_submission_id' => $cfaId],
            ['district_id' => $districtId, 'state' => $state, 'updated_by' => $user->id]
        );

        return ['ok' => true, 'data' => []];
    }

    /**
     * @param  list<int>  $districtIds
     */
    private function restoreLater(int $hubId, User $user, array $districtIds, array $input): array
    {
        $cfaId = (int) ($input['cfa_submission_id'] ?? 0);
        $districtId = (int) ($input['district_id'] ?? 0);
        if (! in_array($districtId, $districtIds, true)) {
            return ['ok' => false, 'error' => 'Invalid district'];
        }
        CfaHubChoiceState::query()
            ->where('hub_id', $hubId)
            ->where('cfa_submission_id', $cfaId)
            ->where('district_id', $districtId)
            ->where('state', 'later')
            ->update(['state' => 'open', 'updated_by' => $user->id]);

        return ['ok' => true, 'data' => []];
    }

    /**
     * @param  list<int>  $districtIds
     */
    private function undoReject(int $hubId, User $user, array $districtIds, array $input): array
    {
        $cfaId = (int) ($input['cfa_submission_id'] ?? 0);
        $districtId = (int) ($input['district_id'] ?? 0);
        if (! in_array($districtId, $districtIds, true)) {
            return ['ok' => false, 'error' => 'Invalid district'];
        }
        CfaHubChoiceState::query()
            ->where('hub_id', $hubId)
            ->where('cfa_submission_id', $cfaId)
            ->where('district_id', $districtId)
            ->where('state', 'reject')
            ->update(['state' => 'open', 'updated_by' => $user->id]);

        return ['ok' => true, 'data' => []];
    }

    public function storeCdoDocument(OnboardingBatch $batch, User $user, UploadedFile $file): void
    {
        $path = $file->store('onboarding_batch_docs/'.$batch->id, 'local');
        $docRow = OnboardingBatchDocument::query()->updateOrCreate(
            [
                'onboarding_batch_id' => $batch->id,
                'doc_type' => self::DOC_CDO,
            ],
            [
                'path' => $path,
                'original_name' => $file->getClientOriginalName(),
                'mime_type' => $file->getClientMimeType(),
                'size_bytes' => $file->getSize(),
                'uploaded_by' => $user->id,
            ]
        );

        $this->syncCdoDocumentRepository($batch, $user, $docRow);
    }

    /**
     * @return array{scanned:int,synced:int,skipped:int}
     */
    public function backfillCdoDocuments(?int $batchId = null): array
    {
        $query = OnboardingBatchDocument::query()
            ->with(['batch', 'uploader'])
            ->where('doc_type', self::DOC_CDO);

        if ($batchId !== null && $batchId > 0) {
            $query->where('onboarding_batch_id', $batchId);
        }

        $rows = $query->orderBy('id')->get();
        $synced = 0;
        $skipped = 0;

        foreach ($rows as $row) {
            $batch = $row->batch;
            if (! $batch instanceof OnboardingBatch) {
                $skipped++;

                continue;
            }

            $before = DocumentVersion::query()->count();
            $this->syncCdoDocumentRepository($batch, $row->uploader, $row);
            $after = DocumentVersion::query()->count();
            if ($after > $before) {
                $synced++;
            } else {
                $skipped++;
            }
        }

        return [
            'scanned' => $rows->count(),
            'synced' => $synced,
            'skipped' => $skipped,
        ];
    }

    private function syncCdoDocumentRepository(OnboardingBatch $batch, ?User $user, OnboardingBatchDocument $cdoDoc): void
    {
        $district = District::query()->find((int) $batch->district_id);
        if (! $district || trim((string) $cdoDoc->path) === '') {
            return;
        }
        $actorUserId = $user?->id
            ?? (is_numeric($cdoDoc->uploaded_by) ? (int) $cdoDoc->uploaded_by : null)
            ?? (is_numeric($batch->updated_by) ? (int) $batch->updated_by : null)
            ?? (is_numeric($batch->created_by) ? (int) $batch->created_by : null);

        $root = $this->firstOrCreateDocumentCategory('Onboarding Letters', null);
        $sub = $this->firstOrCreateDocumentCategory((string) $district->name, (int) $root->id);

        $title = 'CDO Signed PDF - '.$district->name.' - Batch '.$batch->id;
        $tags = [
            'cdo',
            'onboarding-letter',
            'batch:'.$batch->id,
            'district:'.($district->slug ?: $district->id),
        ];
        $allowedRoles = [
            Document::ROLE_STATE_ADMIN,
            Document::ROLE_STATE_STAFF,
            Document::ROLE_HUB_ADMIN,
            Document::ROLE_DISTRICT_STAFF,
        ];

        $doc = Document::query()
            ->where('document_category_id', (int) $sub->id)
            ->where('title', $title)
            ->first();

        if (! $doc) {
            $doc = Document::query()->create([
                'document_category_id' => (int) $sub->id,
                'title' => $title,
                'tags' => $tags,
                'allowed_roles' => $allowedRoles,
                'created_by' => $actorUserId,
                'updated_by' => $actorUserId,
            ]);
        } else {
            $doc->update([
                'document_category_id' => (int) $sub->id,
                'tags' => $tags,
                'allowed_roles' => $allowedRoles,
                'updated_by' => $actorUserId,
            ]);
        }

        $nextVersionNo = ((int) $doc->versions()->max('version_no')) + 1;
        $version = DocumentVersion::query()->create([
            'document_id' => (int) $doc->id,
            'version_no' => $nextVersionNo,
            'disk' => 'local',
            'path' => (string) $cdoDoc->path,
            'original_name' => (string) ($cdoDoc->original_name ?: ('onboarding-letter-'.$batch->id.'.pdf')),
            'mime_type' => (string) ($cdoDoc->mime_type ?: 'application/pdf'),
            'size_bytes' => (int) ($cdoDoc->size_bytes ?? 0),
            'uploaded_by' => $actorUserId,
        ]);

        $doc->update([
            'latest_version_id' => (int) $version->id,
            'updated_by' => $actorUserId,
        ]);
    }

    private function firstOrCreateDocumentCategory(string $name, ?int $parentId): DocumentCategory
    {
        $name = trim($name);
        $existing = DocumentCategory::query()
            ->where('name', $name)
            ->where('parent_id', $parentId)
            ->first();
        if ($existing) {
            return $existing;
        }

        $slug = Str::slug($name);
        if ($slug === '') {
            $slug = 'category';
        }
        $base = $slug;
        $i = 2;
        while (DocumentCategory::query()->where('slug', $slug)->exists()) {
            $slug = $base.'-'.$i;
            $i++;
        }

        return DocumentCategory::query()->create([
            'parent_id' => $parentId,
            'name' => $name,
            'slug' => $slug,
        ]);
    }

    public function stateUndoReject(int $hubId, int $districtId, int $cfaSubmissionId, User $user): void
    {
        $district = District::query()->findOrFail($districtId);
        if ((int) $district->hub_id !== $hubId) {
            abort(422, 'District not in hub');
        }

        CfaHubChoiceState::query()
            ->where('hub_id', $hubId)
            ->where('cfa_submission_id', $cfaSubmissionId)
            ->where('district_id', $districtId)
            ->where('state', 'reject')
            ->update(['state' => 'open', 'updated_by' => $user->id]);
    }
}
