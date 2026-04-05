<?php

namespace App\Services;

use App\Models\CfaHubChoiceState;
use App\Models\CfaSubmission;
use App\Models\District;
use App\Models\Hub;
use App\Models\OnboardingBatch;
use App\Models\OnboardingBatchCfa;
use App\Models\OnboardingBatchDocument;
use App\Models\OnboardingBatchDraftCfa;
use App\Models\FiscalYear;
use App\Models\User;
use Carbon\Carbon;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

class HubBatchService
{
    public const DOC_CDO = 'cdo_signed';

    /**
     * @return list<int>
     */
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
    public function handleApi(string $action, User $user, array $input): array
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
            'later_list' => $this->laterList($hubId, $districtIds),
            'create_draft' => $this->createDraft($hubId, $user, $districtIds, $input),
            'cancel_draft' => $this->cancelDraft($hubId, $input),
            'add_to_draft' => $this->addToDraft($hubId, $input),
            'remove_from_draft' => $this->removeFromDraft($hubId, $input),
            'lock_batch' => $this->lockBatch($hubId, $user, $input),
            'set_choice' => $this->setChoice($hubId, $user, $districtIds, $input),
            'restore_later' => $this->restoreLater($hubId, $user, $districtIds, $input),
            'undo_reject' => $this->undoReject($hubId, $user, $districtIds, $input),
            default => ['ok' => false, 'error' => 'Unknown action'],
        };
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
        $fyId = $this->activeFiscalYearId();

        $query = CfaSubmission::query()
            ->where('district_id', $districtId)
            ->when($fyId, fn ($qq) => $qq->where('fiscal_year_id', $fyId))
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

        if ($q !== '') {
            $like = '%'.$q.'%';
            $query->where(function ($qq) use ($like) {
                $qq->where('application_no', 'like', $like)
                    ->orWhere('applicant_name', 'like', $like)
                    ->orWhere('phone', 'like', $like);
            });
        }

        $rows = $query->orderByDesc('id')->limit(200)->get()->map(fn (CfaSubmission $c) => [
            'id' => $c->id,
            'application_no' => $c->application_no ?? (string) $c->id,
            'applicant_name' => $c->applicant_name,
            'stage' => strtoupper((string) ($c->payload['form_stage'] ?? $c->payload['stage'] ?? '—')),
        ]);

        return ['ok' => true, 'data' => ['candidates' => $rows]];
    }

    private function draftMembers(int $hubId, array $input): array
    {
        $batchId = (int) ($input['batch_id'] ?? 0);
        $batch = OnboardingBatch::query()->find($batchId);
        if (! $batch || $batch->hub_id !== $hubId || ! $batch->isDraft()) {
            return ['ok' => false, 'error' => 'Not a draft batch'];
        }

        $members = $batch->draftCfas()->with('cfaSubmission')->orderBy('onboarding_batch_draft_cfa.id')->get()->map(fn (OnboardingBatchDraftCfa $r) => [
            'id' => $r->cfa_submission_id,
            'application_no' => $r->cfaSubmission->application_no ?? (string) $r->cfa_submission_id,
            'applicant_name' => $r->cfaSubmission->applicant_name,
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
                    'locked_at' => $b->locked_at?->toIso8601String(),
                    'member_count' => $memberCount,
                    'has_cdo_pdf' => $this->hasCdoPdf($b),
                    'cdo_overdue' => $this->cdoIsOverdue($b),
                    'cdo_pending' => $this->cdoIsPendingWithinWindow($b),
                ];
            });

        return ['ok' => true, 'data' => ['batches' => $list]];
    }

    /**
     * @param  list<int>  $districtIds
     */
    private function laterList(int $hubId, array $districtIds): array
    {
        $rows = CfaHubChoiceState::query()
            ->where('hub_id', $hubId)
            ->whereIn('district_id', $districtIds)
            ->where('state', 'later')
            ->with(['cfaSubmission'])
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

        $onboardingDate = $input['onboarding_date'] ?? now()->toDateString();
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
        if (! $batch || $batch->hub_id !== $hubId || ! $batch->isDraft()) {
            return ['ok' => false, 'error' => 'Invalid draft batch'];
        }
        if ($this->draftMemberCount($batchId) >= $batch->target_size) {
            return ['ok' => false, 'error' => 'Batch is full. Lock the batch.'];
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
            OnboardingBatchDraftCfa::query()->create([
                'onboarding_batch_id' => $batchId,
                'cfa_submission_id' => $cfaId,
            ]);
        } catch (\Throwable) {
            return ['ok' => false, 'error' => 'Could not add (duplicate?)'];
        }

        $newc = $this->draftMemberCount($batchId);

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
        if (! $batch || $batch->hub_id !== $hubId || ! $batch->isDraft()) {
            return ['ok' => false, 'error' => 'Invalid draft'];
        }
        OnboardingBatchDraftCfa::query()
            ->where('onboarding_batch_id', $batchId)
            ->where('cfa_submission_id', $cfaId)
            ->delete();

        return ['ok' => true, 'data' => ['count' => $this->draftMemberCount($batchId)]];
    }

    private function lockBatch(int $hubId, User $user, array $input): array
    {
        if (empty($input['confirm'])) {
            return ['ok' => false, 'error' => 'Confirmation required'];
        }
        $batchId = (int) ($input['batch_id'] ?? 0);
        $batch = OnboardingBatch::query()->find($batchId);
        if (! $batch || $batch->hub_id !== $hubId || ! $batch->isDraft()) {
            return ['ok' => false, 'error' => 'Invalid draft'];
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

    public function storeCdoDocument(OnboardingBatch $batch, User $user, \Illuminate\Http\UploadedFile $file): void
    {
        $path = $file->store('onboarding_batch_docs/'.$batch->id, 'local');
        OnboardingBatchDocument::query()->updateOrCreate(
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
