<?php

namespace App\Services;

use App\Models\CfaSubmission;
use Illuminate\Support\Collection;

class ApplicantOnboardingEnrichmentService
{
    /**
     * @param  list<array<string, mixed>>  $snapshots
     * @return list<array<string, mixed>>
     */
    public function enrichSnapshots(array $snapshots): array
    {
        $collection = collect($snapshots)
            ->map(fn ($snap): array => is_array($snap) ? $snap : []);

        return $this->enrichOnboardingStatusForSnapshots($collection);
    }

    /**
     * @return list<array<string, mixed>>
     */
    private function enrichOnboardingStatusForSnapshots(Collection $snapshots): array
    {
        $phase3Ids = $snapshots
            ->filter(fn (array $snap): bool => ($snap['source'] ?? '') !== 'legacy_phase2' && (int) ($snap['incubatee_id'] ?? 0) > 0)
            ->map(fn (array $snap): int => (int) $snap['incubatee_id'])
            ->unique()
            ->values();

        /** @var array<int, array<string, mixed>> $onboardingById */
        $onboardingById = [];

        if ($phase3Ids->isNotEmpty()) {
            CfaSubmission::query()
                ->whereIn('id', $phase3Ids->all())
                ->with(['onboardingBatchMembership.batch'])
                ->get()
                ->each(function (CfaSubmission $row) use (&$onboardingById): void {
                    $batch = $row->onboardingBatchMembership?->batch;
                    $isOnboarded = $batch !== null
                        && $batch->status === 'locked'
                        && $batch->locked_at !== null;

                    $onboardingById[(int) $row->id] = [
                        'onboard_status' => $isOnboarded ? 'onboarded' : 'non_onboarded',
                        'onboard_label' => $isOnboarded ? 'Onboarded' : 'Not onboarded',
                        'onboarding_batch_id' => $isOnboarded ? (int) $batch->id : 0,
                        'onboarding_batch_name' => $isOnboarded ? (string) ($batch->name ?? '') : '',
                    ];
                });
        }

        return $snapshots
            ->map(function (array $snap) use ($onboardingById): array {
                $incubateeId = (int) ($snap['incubatee_id'] ?? 0);
                if ($incubateeId > 0 && isset($onboardingById[$incubateeId])) {
                    return array_merge($snap, $onboardingById[$incubateeId]);
                }

                return $this->applyOnboardingStatusFallback($snap);
            })
            ->all();
    }

    /**
     * @param  array<string, mixed>  $snap
     * @return array<string, mixed>
     */
    private function applyOnboardingStatusFallback(array $snap): array
    {
        if (trim((string) ($snap['onboard_label'] ?? '')) !== '') {
            if (! isset($snap['onboard_status'])) {
                $snap['onboard_status'] = trim((string) ($snap['onboarding_batch_name'] ?? '')) !== ''
                    || (int) ($snap['onboarding_batch_id'] ?? 0) > 0
                    ? 'onboarded'
                    : 'non_onboarded';
            }

            return $snap;
        }

        $isOnboarded = trim((string) ($snap['onboarding_batch_name'] ?? '')) !== ''
            || (int) ($snap['onboarding_batch_id'] ?? 0) > 0;

        $snap['onboard_status'] = $isOnboarded ? 'onboarded' : 'non_onboarded';
        $snap['onboard_label'] = $isOnboarded ? 'Onboarded' : 'Not onboarded';

        return $snap;
    }
}
