<?php

namespace App\Services;

use App\Models\CaseStudyShortlist;
use App\Models\CaseStudyShortlistNomination;
use App\Models\User;
use App\Support\CaseStudyShortlistAccess;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

class CaseStudyShortlistNominationManager
{
    /**
     * @param list<string> $selectedCodes
     * @param array<string, bool> $alreadyReceived
     */
    public function sync(
        User $user,
        CaseStudyShortlist $shortlist,
        array $selectedCodes,
        array $alreadyReceived,
        ?string $note,
        ?string $customServiceName = null,
    ): void
    {
        abort_unless($user->role === 'state_admin' && CaseStudyShortlistAccess::canAccessDistrict($user, (int) $shortlist->district_id), 403);

        $this->syncSelection($user, $shortlist, $selectedCodes, $alreadyReceived, $note, $customServiceName);
    }

    /**
     * Save the initial service proposals selected by District Staff while the
     * incubatee is being shortlisted. This does not mark a service delivered.
     *
     * @param list<string> $selectedCodes
     * @param array<string, bool> $alreadyReceived
     */
    public function proposeAtShortlisting(
        User $user,
        CaseStudyShortlist $shortlist,
        array $selectedCodes,
        array $alreadyReceived,
        ?string $customServiceName = null,
    ): void {
        abort_unless(
            $user->role === 'district_staff'
            && (int) $shortlist->district_id === (int) $user->district_id
            && (int) $shortlist->created_by_user_id === (int) $user->id
            && $shortlist->removed_at === null,
            403,
        );

        $this->syncSelection($user, $shortlist, $selectedCodes, $alreadyReceived, null, $customServiceName);
    }

    /**
     * @param list<string> $selectedCodes
     * @param array<string, bool> $alreadyReceived
     */
    private function syncSelection(
        User $user,
        CaseStudyShortlist $shortlist,
        array $selectedCodes,
        array $alreadyReceived,
        ?string $note,
        ?string $customServiceName,
    ): void {

        $options = (array) config('case_study_shortlists.nomination_services', []);
        $selectedCodes = array_values(array_unique(array_filter($selectedCodes, fn ($code) => isset($options[$code]))));
        $customServiceName = trim((string) $customServiceName);
        if (in_array('other', $selectedCodes, true) && $customServiceName === '') {
            throw ValidationException::withMessages(['other_service' => 'Please enter the proposed service name for Other.']);
        }
        foreach ($selectedCodes as $code) {
            if ($alreadyReceived[$code] ?? false) {
                throw ValidationException::withMessages(['services' => $options[$code]['label'].' has already been received.']);
            }
        }

        DB::transaction(function () use ($user, $shortlist, $selectedCodes, $options, $note, $customServiceName): void {
            CaseStudyShortlist::query()->whereKey($shortlist->id)->lockForUpdate()->firstOrFail();
            $existing = CaseStudyShortlistNomination::query()
                ->where('case_study_shortlist_id', $shortlist->id)
                ->get()->keyBy('service_code');

            foreach (array_keys($options) as $code) {
                /** @var CaseStudyShortlistNomination|null $nomination */
                $nomination = $existing->get($code);
                $selected = in_array($code, $selectedCodes, true);
                if ($selected && (! $nomination || ! $nomination->isActive())) {
                    $from = $nomination?->status;
                    $nomination ??= new CaseStudyShortlistNomination([
                        'case_study_shortlist_id' => $shortlist->id,
                        'service_code' => $code,
                    ]);
                    $nomination->fill([
                        'status' => CaseStudyShortlistNomination::STATUS_NOMINATED,
                        'nomination_note' => trim((string) $note) ?: null,
                        'custom_service_name' => $code === 'other' ? $customServiceName : null,
                        'nominated_by_user_id' => $user->id,
                        'nominated_at' => now(),
                        'cancelled_by_user_id' => null,
                        'cancelled_at' => null,
                    ])->save();
                    $nomination->events()->create([
                        'action' => $from ? 'renominated' : 'nominated', 'from_status' => $from,
                        'to_status' => CaseStudyShortlistNomination::STATUS_NOMINATED,
                        'note' => trim((string) $note) ?: null, 'actor_user_id' => $user->id,
                    ]);
                } elseif ($selected && $nomination?->isActive() && $code === 'other'
                    && $nomination->custom_service_name !== $customServiceName) {
                    $nomination->update(['custom_service_name' => $customServiceName]);
                    $nomination->events()->create([
                        'action' => 'updated',
                        'from_status' => $nomination->status,
                        'to_status' => $nomination->status,
                        'note' => 'Other service updated to: '.$customServiceName,
                        'actor_user_id' => $user->id,
                    ]);
                } elseif (! $selected && $nomination?->isActive()) {
                    $from = $nomination->status;
                    $nomination->update([
                        'status' => CaseStudyShortlistNomination::STATUS_CANCELLED,
                        'cancelled_by_user_id' => $user->id, 'cancelled_at' => now(),
                    ]);
                    $nomination->events()->create([
                        'action' => 'cancelled', 'from_status' => $from,
                        'to_status' => CaseStudyShortlistNomination::STATUS_CANCELLED,
                        'note' => trim((string) $note) ?: null, 'actor_user_id' => $user->id,
                    ]);
                }
            }
        }, 3);
    }
}
