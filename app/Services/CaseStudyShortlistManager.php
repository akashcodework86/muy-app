<?php

namespace App\Services;

use App\Models\CaseStudyShortlist;
use App\Models\CaseStudyShortlistRemark;
use App\Models\District;
use App\Models\User;
use App\Support\CaseStudyShortlistAccess;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

class CaseStudyShortlistManager
{
    public const MONTHLY_LIMIT = 5;

    public function __construct(private readonly CaseStudyShortlistCandidateCatalog $catalog) {}

    public function create(User $user, string $source, int $sourceId): CaseStudyShortlist
    {
        if (! CaseStudyShortlistAccess::canCreate($user)) {
            abort(403);
        }

        $candidate = $this->catalog->resolve($user, $source, $sourceId);
        if (! $candidate || (int) $candidate['district_id'] !== (int) $user->district_id) {
            throw ValidationException::withMessages(['candidate' => 'Eligible onboarded incubatee was not found in your district.']);
        }

        $month = now()->startOfMonth()->toDateString();

        return DB::transaction(function () use ($user, $candidate, $month): CaseStudyShortlist {
            // One district-row lock serializes concurrent nominations and makes the 5-person cap exact.
            District::query()->whereKey((int) $user->district_id)->lockForUpdate()->firstOrFail();
            $existing = CaseStudyShortlist::query()
                ->where(fn ($q) => $q->where('candidate_key', $candidate['candidate_key'])->orWhere('person_key', $candidate['person_key']))
                ->lockForUpdate()->first();
            if ($existing) {
                throw ValidationException::withMessages(['candidate' => 'This incubatee has already been shortlisted and cannot be selected again.']);
            }

            $count = CaseStudyShortlist::query()
                ->where('district_id', (int) $user->district_id)
                ->whereDate('shortlist_month', $month)
                ->whereNull('removed_at')
                ->lockForUpdate()->count();
            if ($count >= self::MONTHLY_LIMIT) {
                throw ValidationException::withMessages(['candidate' => 'Your district has already shortlisted the maximum 5 incubatees for this month.']);
            }

            return CaseStudyShortlist::query()->create([
                'candidate_key' => $candidate['candidate_key'], 'person_key' => $candidate['person_key'],
                'source' => $candidate['source'], 'source_application_id' => $candidate['source_id'],
                'program_year' => $candidate['program_year'], 'district_id' => $candidate['district_id'],
                'shortlist_month' => $month, 'applicant_name' => $candidate['applicant_name'],
                'application_no' => $candidate['application_no'] ?: null, 'block_name' => $candidate['block'] ?: null,
                'business_category' => $candidate['category'] ?: null, 'business_stage' => $candidate['stage'] ?: null,
                'gender' => $candidate['gender'] ?: null, 'created_by_user_id' => $user->id,
            ]);
        }, 3);
    }

    public function addRemark(User $user, CaseStudyShortlist $shortlist, string $remark): CaseStudyShortlistRemark
    {
        abort_unless(CaseStudyShortlistAccess::canRemark($user, $shortlist), 403);

        return $shortlist->remarks()->create([
            'user_id' => $user->id,
            'author_role' => $user->role,
            'remark' => trim($remark),
        ]);
    }

    public function remove(User $user, CaseStudyShortlist $shortlist, ?string $reason): void
    {
        abort_unless(CaseStudyShortlistAccess::canRemove($user, $shortlist), 403);
        if ($shortlist->removed_at !== null) {
            return;
        }
        if (in_array($user->role, ['hub_admin', 'state_admin'], true) && trim((string) $reason) === '') {
            throw ValidationException::withMessages(['removal_reason' => 'Please enter a reason for admin removal.']);
        }

        $shortlist->forceFill([
            'removed_at' => now(),
            'removed_by_user_id' => $user->id,
            'removal_reason' => trim((string) $reason) ?: null,
        ])->save();
    }
}
