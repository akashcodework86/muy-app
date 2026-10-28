<?php

namespace App\Services;

use App\Models\CfaHubChoiceState;
use App\Models\CfaSubmission;
use App\Models\FiscalYear;
use App\Models\OnboardingBatch;
use App\Models\OnboardingBatchCfa;
use App\Models\OnboardingBatchDraftCfa;
use App\Models\User;
use App\Support\HubBatchImportNormalizer as Normalizer;
use Illuminate\Http\Request;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Str;
use RuntimeException;

class HubBatchImportService
{
    public function __construct(
        private HubBatchService $batches,
        private AdminAuditLogger $auditLogger,
    ) {}

    public function preview(User $user, OnboardingBatch $batch, array $rows, array $options, array $fileMeta): array
    {
        $this->guard($user, $batch);
        $result = $this->process($user, $batch, $rows, $options, false);
        $token = (string) Str::uuid();
        Cache::put($this->cacheKey($user, $token), [
            'batch_id' => $batch->id,
            'rows' => $rows,
            'options' => $options,
            'file' => $fileMeta,
        ], now()->addMinutes(30));
        $result['token'] = $token;
        $result['file'] = $fileMeta;

        return $result;
    }

    public function commit(User $user, string $token, Request $request): array
    {
        $key = $this->cacheKey($user, $token);
        $payload = Cache::pull($key);
        if (! is_array($payload)) {
            throw new RuntimeException('This preview has expired or was already imported. Upload the file again.');
        }
        $batch = OnboardingBatch::query()->findOrFail((int) $payload['batch_id']);
        $this->guard($user, $batch);
        $result = $this->process($user, $batch, $payload['rows'], $payload['options'], true);
        $this->auditLogger->record(
            $request,
            'hub_batch_excel_import',
            OnboardingBatch::class,
            (int) $batch->id,
            null,
            [
                'file_name' => $payload['file']['name'] ?? null,
                'sha256' => $payload['file']['sha256'] ?? null,
                'summary' => $result['summary'],
                'all_pool_years' => (bool) ($payload['options']['all_pool_years'] ?? true),
            ],
            'Imported CFA rows into an existing hub onboarding draft.'
        );

        return $result;
    }

    private function guard(User $user, OnboardingBatch $batch): void
    {
        if ($user->role !== 'hub_admin' || (int) $user->hub_id <= 0 || (int) $batch->hub_id !== (int) $user->hub_id || ! $batch->isDraft()) {
            throw new RuntimeException('Only an active draft belonging to your hub can be imported.');
        }
    }

    private function process(User $user, OnboardingBatch $batch, array $rows, array $options, bool $commit): array
    {
        $fyIds = $this->fiscalYearIds($options);
        $results = [];
        $scheduled = [];
        $count = OnboardingBatchDraftCfa::query()->where('onboarding_batch_id', $batch->id)->count();

        foreach ($rows as $row) {
            if ($count >= (int) $batch->target_size) {
                $results[] = $this->result($row, 'draft_full', 'Draft target is full.', null, null);

                continue;
            }

            $match = $this->match($user, $batch, $row, $fyIds);
            if (! $match['cfa']) {
                $results[] = $this->result($row, $match['status'], $match['reason'], null, $match['pool_year'] ?? null);

                continue;
            }
            /** @var CfaSubmission $cfa */
            $cfa = $match['cfa'];
            if (isset($scheduled[$cfa->id])) {
                $results[] = $this->result($row, 'skipped_duplicate_in_file', 'The same portal applicant was already matched by an earlier file row.', $cfa, $match['pool_year']);

                continue;
            }
            $eligibility = $this->eligibility($user, $batch, $cfa);
            if ($eligibility['status'] !== 'eligible') {
                $results[] = $this->result($row, $eligibility['status'], $eligibility['reason'], $cfa, $match['pool_year']);

                continue;
            }
            if (! $commit) {
                $scheduled[$cfa->id] = true;
                $count++;
                $results[] = $this->result($row, 'would_add', 'Ready to add after confirmation.', $cfa, $match['pool_year']);

                continue;
            }

            $added = $this->batches->handleApi('add_to_draft', $user, [
                'batch_id' => $batch->id,
                'cfa_submission_id' => $cfa->id,
            ], request());
            if ($added['ok']) {
                $scheduled[$cfa->id] = true;
                $count = (int) ($added['data']['count'] ?? ($count + 1));
                $results[] = $this->result($row, 'added', 'Added to draft.', $cfa, $match['pool_year']);
            } else {
                $results[] = $this->result($row, 'error', (string) ($added['error'] ?? 'Could not add applicant.'), $cfa, $match['pool_year']);
            }
        }

        return [
            'summary' => $this->summarize($results, $count, (int) $batch->target_size),
            'results' => $results,
        ];
    }

    /** @return list<int> */
    private function fiscalYearIds(array $options): array
    {
        if (! ($options['all_pool_years'] ?? true)) {
            return [(int) ($options['fiscal_year_id'] ?? 0)];
        }

        return FiscalYear::query()->forUiSelection()->orderByDesc('is_active')->orderByDesc('starts_on')->pluck('id')->map(fn ($v) => (int) $v)->all();
    }

    private function match(User $user, OnboardingBatch $batch, array $row, array $fyIds): array
    {
        $phone = Normalizer::phone($row['mobile'] ?? '');
        $app = Normalizer::applicationNo($row['application_no'] ?? '');
        $name = Normalizer::name($row['name'] ?? '');
        foreach (array_filter([$phone, $app]) as $q) {
            foreach ($fyIds as $fyId) {
                $this->batches->handleApi('pool_list', $user, ['district_id' => $batch->district_id, 'fiscal_year_id' => $fyId, 'q' => $q], request());
            }
        }

        $candidates = CfaSubmission::query()
            ->with('fiscalYear')
            ->where('district_id', $batch->district_id)
            ->where(function ($q) use ($fyIds) {
                $q->whereIn('fiscal_year_id', array_filter($fyIds))->orWhere('source', 'legacy_phase1');
            })
            ->get();

        $mobileMatches = $phone === '' ? collect() : $candidates->filter(fn (CfaSubmission $c) => Normalizer::phone($c->phone) === $phone);
        $appMatches = $app === '' ? collect() : $candidates->filter(fn (CfaSubmission $c) => Normalizer::applicationNo($c->application_no) === $app);
        if ($mobileMatches->isNotEmpty() && $appMatches->count() === 1 && ! $mobileMatches->contains('id', $appMatches->first()->id)) {
            return ['cfa' => null, 'status' => 'identifier_conflict', 'reason' => 'Mobile and Application No match different portal applicants.'];
        }
        $matches = $appMatches->count() === 1 ? $appMatches : ($mobileMatches->count() ? $mobileMatches : $appMatches);
        if ($matches->isEmpty() && $app !== '') {
            $variants = Normalizer::applicationVariants($app);
            $matches = $candidates->filter(fn (CfaSubmission $c) => in_array(Normalizer::applicationNo($c->application_no), $variants, true));
        }
        if ($matches->isEmpty() && $name !== '') {
            $matches = $candidates->filter(fn (CfaSubmission $c) => Normalizer::name($c->applicant_name) === $name);
        }
        $matches = $this->orderByFiscalYear($matches, $fyIds);
        if ($matches->isEmpty()) {
            return ['cfa' => null, 'status' => 'not_found', 'reason' => 'No exact applicant match was found in this district and selected pool years.'];
        }
        if ($matches->count() > 1) {
            return ['cfa' => null, 'status' => 'ambiguous', 'reason' => 'More than one portal applicant matches this row. Resolve it manually.'];
        }
        $cfa = $matches->first();

        return ['cfa' => $cfa, 'status' => 'matched', 'reason' => '', 'pool_year' => $cfa->fiscalYear?->code ?? 'Legacy Phase 1'];
    }

    private function orderByFiscalYear(Collection $rows, array $fyIds): Collection
    {
        $order = array_flip($fyIds);

        return $rows->sortBy(fn (CfaSubmission $c) => $order[(int) $c->fiscal_year_id] ?? 999)->values();
    }

    private function eligibility(User $user, OnboardingBatch $batch, CfaSubmission $cfa): array
    {
        if (OnboardingBatchDraftCfa::query()->where('onboarding_batch_id', $batch->id)->where('cfa_submission_id', $cfa->id)->exists()) {
            return ['status' => 'skipped_already_in_draft', 'reason' => 'Already in this draft.'];
        }
        if (OnboardingBatchCfa::query()->where('cfa_submission_id', $cfa->id)->exists()) {
            return ['status' => 'skipped_onboarded', 'reason' => 'Already onboarded in a locked batch.'];
        }
        if (OnboardingBatchDraftCfa::query()->where('cfa_submission_id', $cfa->id)->exists()) {
            return ['status' => 'skipped_other_draft', 'reason' => 'Already present in another draft.'];
        }
        $choice = CfaHubChoiceState::query()->where('hub_id', $user->hub_id)->where('district_id', $batch->district_id)->where('cfa_submission_id', $cfa->id)->value('state');
        if (in_array($choice, ['later', 'reject'], true)) {
            return ['status' => 'skipped_'.$choice, 'reason' => 'Applicant is marked '.ucfirst($choice).' in the pool.'];
        }

        return ['status' => 'eligible', 'reason' => ''];
    }

    private function result(array $row, string $status, string $reason, ?CfaSubmission $cfa, ?string $poolYear): array
    {
        return array_merge($row, [
            'status' => $status,
            'reason' => $reason,
            'pool_year' => $poolYear,
            'matched_id' => $cfa?->id,
            'matched_application_no' => $cfa?->application_no,
        ]);
    }

    private function summarize(array $results, int $count, int $target): array
    {
        $statuses = collect($results)->countBy('status')->all();

        return [
            'processed' => count($results),
            'added' => (int) ($statuses['added'] ?? 0),
            'would_add' => (int) ($statuses['would_add'] ?? 0),
            'already_in_draft' => (int) ($statuses['skipped_already_in_draft'] ?? 0),
            'not_found' => (int) ($statuses['not_found'] ?? 0),
            'errors' => collect($results)->whereIn('status', ['error', 'identifier_conflict', 'ambiguous'])->count(),
            'draft_count' => $count,
            'draft_target' => $target,
        ];
    }

    private function cacheKey(User $user, string $token): string
    {
        return 'hub_batch_import:'.$user->id.':'.$token;
    }
}
