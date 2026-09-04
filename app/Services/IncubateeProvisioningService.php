<?php

namespace App\Services;

use App\Models\CfaSubmission;
use App\Models\OnboardingBatch;
use App\Models\OnboardingBatchCfa;
use App\Models\User;
use App\Support\IncubateeLoginPhone;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Str;

class IncubateeProvisioningService
{
    /**
     * @return array{
     *   created: int,
     *   skipped: int,
     *   converted: int,
     *   missing_phones: list<array<string, mixed>>,
     *   duplicate_phones: list<array<string, mixed>>,
     *   dry_run: bool
     * }
     */
    public function provision(?int $onboardingBatchId, bool $dryRun): array
    {
        if ($onboardingBatchId !== null && ! OnboardingBatch::query()->whereKey($onboardingBatchId)->exists()) {
            throw new \InvalidArgumentException('No onboarding batch with id '.$onboardingBatchId.'.');
        }

        $query = CfaSubmission::query()
            ->whereHas('onboardingBatchMembership', function ($q) use ($onboardingBatchId): void {
                if ($onboardingBatchId !== null) {
                    $q->where('onboarding_batch_id', $onboardingBatchId);
                }
            })
            ->orderBy('id');

        $created = 0;
        $skipped = 0;
        $converted = 0;
        $missingPhones = [];
        $duplicatePhones = [];
        $claimedInRun = [];

        $existingByPhone = User::query()
            ->where('role', 'incubatee')
            ->whereNotNull('phone')
            ->where('phone', '!=', '')
            ->get(['id', 'phone', 'cfa_submission_id', 'name'])
            ->keyBy(fn (User $u): string => (string) $u->phone);

        $query->chunkById(100, function ($submissions) use (
            $dryRun,
            &$created,
            &$skipped,
            &$converted,
            &$missingPhones,
            &$duplicatePhones,
            &$claimedInRun,
            $existingByPhone,
        ): void {
            foreach ($submissions as $submission) {
                $result = $this->provisionOne(
                    $submission,
                    $dryRun,
                    $claimedInRun,
                    $existingByPhone,
                );
                if ($result['status'] === 'created') {
                    $created++;
                } elseif ($result['status'] === 'converted') {
                    $converted++;
                } elseif ($result['status'] === 'skipped') {
                    $skipped++;
                } elseif ($result['status'] === 'missing') {
                    $missingPhones[] = $result['row'];
                } elseif ($result['status'] === 'duplicate') {
                    $duplicatePhones[] = $result['row'];
                }
            }
        });

        return [
            'created' => $created,
            'skipped' => $skipped,
            'converted' => $converted,
            'missing_phones' => $missingPhones,
            'duplicate_phones' => $duplicatePhones,
            'dry_run' => $dryRun,
        ];
    }

    /**
     * Convert every existing incubatee user that already has a unique 10-digit phone
     * (sets login phone + resets password to that phone when the account was still on email login).
     *
     * @return array{converted: int, skipped: int, missing: int, duplicate: int, dry_run: bool}
     */
    public function convertExisting(bool $dryRun): array
    {
        $converted = 0;
        $skipped = 0;
        $missing = 0;
        $duplicate = 0;
        $claimedInRun = [];

        $existingByPhone = User::query()
            ->where('role', 'incubatee')
            ->whereNotNull('phone')
            ->where('phone', '!=', '')
            ->get(['id', 'phone', 'cfa_submission_id', 'name'])
            ->keyBy(fn (User $u): string => (string) $u->phone);

        User::query()
            ->where('role', 'incubatee')
            ->orderBy('id')
            ->chunkById(100, function ($users) use (
                $dryRun,
                &$converted,
                &$skipped,
                &$missing,
                &$duplicate,
                &$claimedInRun,
                $existingByPhone,
            ): void {
                foreach ($users as $user) {
                    $submission = $user->cfa_submission_id
                        ? CfaSubmission::query()->find($user->cfa_submission_id)
                        : null;
                    $phone = IncubateeLoginPhone::normalize($user->phone)
                        ?: IncubateeLoginPhone::normalize($submission?->phone);

                    if ($phone === '') {
                        $missing++;

                        continue;
                    }

                    $owner = $existingByPhone->get($phone);
                    $claimedBy = $claimedInRun[$phone] ?? null;
                    $thisCfa = (int) ($user->cfa_submission_id ?? 0);
                    if ($claimedBy !== null && $claimedBy !== $thisCfa) {
                        $duplicate++;

                        continue;
                    }
                    if ($owner && (int) $owner->id !== (int) $user->id) {
                        $duplicate++;

                        continue;
                    }

                    $claimedInRun[$phone] = $thisCfa;
                    $alreadyPhoneLogin = IncubateeLoginPhone::normalize($user->phone) === $phone
                        && Hash::check($phone, (string) $user->getRawOriginal('password'));
                    if ($alreadyPhoneLogin) {
                        $skipped++;

                        continue;
                    }

                    if (! $dryRun) {
                        $user->phone = $phone;
                        $user->password = Hash::make($phone);
                        $user->save();
                        $existingByPhone->put($phone, $user);
                    }
                    $converted++;
                }
            });

        return [
            'converted' => $converted,
            'skipped' => $skipped,
            'missing' => $missing,
            'duplicate' => $duplicate,
            'dry_run' => $dryRun,
        ];
    }

    public function saveLoginPhone(CfaSubmission $submission, string $rawPhone): string
    {
        $phone = IncubateeLoginPhone::normalize($rawPhone);
        if ($phone === '') {
            throw new \InvalidArgumentException('Enter a valid 10-digit Indian mobile number.');
        }

        $taken = User::query()
            ->where('role', 'incubatee')
            ->where('phone', $phone)
            ->where(function ($q) use ($submission): void {
                $q->whereNull('cfa_submission_id')
                    ->orWhere('cfa_submission_id', '!=', $submission->id);
            })
            ->first();

        if ($taken) {
            throw new \InvalidArgumentException('This mobile number is already used by '.$taken->name.'. Enter a different number.');
        }

        $otherCfa = CfaSubmission::query()
            ->where('id', '!=', $submission->id)
            ->where('phone', $phone)
            ->whereHas('onboardingBatchMembership')
            ->first();
        if ($otherCfa) {
            throw new \InvalidArgumentException('This mobile number is already used by '.($otherCfa->applicant_name ?: 'another incubatee').'. Enter a different number.');
        }

        $submission->phone = $phone;
        $payload = is_array($submission->payload) ? $submission->payload : [];
        $payload['phone'] = $phone;
        $submission->payload = $payload;
        $submission->save();

        $user = User::query()
            ->where('role', 'incubatee')
            ->where('cfa_submission_id', $submission->id)
            ->first();
        if ($user) {
            $user->phone = $phone;
            $user->password = Hash::make($phone);
            $user->save();
        }

        return $phone;
    }

    /**
     * Create one leftover incubatee login after the hub enters a number on the member list.
     *
     * @return array{status: string, row?: array<string, mixed>}
     */
    public function provisionOneInBatch(int $onboardingBatchId, int $cfaId): array
    {
        $inBatch = OnboardingBatchCfa::query()
            ->where('onboarding_batch_id', $onboardingBatchId)
            ->where('cfa_submission_id', $cfaId)
            ->exists();
        if (! $inBatch) {
            throw new \InvalidArgumentException('This incubatee is not in the locked batch.');
        }

        $submission = CfaSubmission::query()->find($cfaId);
        if ($submission === null) {
            throw new \InvalidArgumentException('CFA not found.');
        }

        $claimedInRun = [];
        $existingByPhone = User::query()
            ->where('role', 'incubatee')
            ->whereNotNull('phone')
            ->where('phone', '!=', '')
            ->get(['id', 'phone', 'cfa_submission_id', 'name'])
            ->keyBy(fn (User $u): string => (string) $u->phone);

        return $this->provisionOne($submission, false, $claimedInRun, $existingByPhone);
    }

    /**
     * Why this member still has no portal login. Phone number only.
     *
     * @param  array<string, User>  $phoneOwnerMap
     * @param  array<string, list<int>>  $phonesInBatch
     * @return array{issue: ?string, detail: ?string}
     */
    public static function describePendingIssue(
        CfaSubmission $submission,
        ?User $account,
        array $phoneOwnerMap,
        array $phonesInBatch,
    ): array {
        $loginPhone = IncubateeLoginPhone::normalize($account?->phone)
            ?: IncubateeLoginPhone::normalize($submission->phone);
        if ($account && $loginPhone !== '') {
            return ['issue' => null, 'detail' => null];
        }

        if ($loginPhone === '') {
            return [
                'issue' => 'missing',
                'detail' => 'No 10-digit mobile on file. Enter a number on this row, then click Create login.',
            ];
        }

        $cid = (int) $submission->id;
        $owner = $phoneOwnerMap[$loginPhone] ?? null;
        if ($owner && (int) $owner->cfa_submission_id !== $cid) {
            return [
                'issue' => 'duplicate',
                'detail' => 'This mobile is already used by '.$owner->name.'. Enter a different number, then click Create login.',
            ];
        }

        $batchIds = $phonesInBatch[$loginPhone] ?? [];
        $earlier = collect($batchIds)->first(fn (int $id): bool => $id !== $cid && $id < $cid);
        if ($earlier) {
            return [
                'issue' => 'duplicate',
                'detail' => 'Same number already used in this batch. Enter a different number, then click Create login.',
            ];
        }

        return [
            'issue' => 'not_created',
            'detail' => 'This mobile is unique. Login was not created yet. Click Create login.',
        ];
    }

    /**
     * @param  array<string, int>  $claimedInRun
     * @param  Collection<string, User>  $existingByPhone
     * @return array{status: string, row?: array<string, mixed>}
     */
    private function provisionOne(
        CfaSubmission $submission,
        bool $dryRun,
        array &$claimedInRun,
        Collection $existingByPhone,
    ): array {
        $phone = IncubateeLoginPhone::normalize($submission->phone);
        $rowBase = [
            'cfa_id' => (int) $submission->id,
            'name' => (string) ($submission->applicant_name ?: 'Incubatee'),
            'application_no' => (string) ($submission->application_no ?? ''),
            'phone' => $phone,
        ];

        if ($phone === '') {
            return ['status' => 'missing', 'row' => $rowBase];
        }

        $existingForCfa = User::query()
            ->where('cfa_submission_id', $submission->id)
            ->where('role', 'incubatee')
            ->first();

        $owner = $existingByPhone->get($phone);
        $claimedBy = $claimedInRun[$phone] ?? null;
        $conflictOtherUser = $owner && (int) $owner->cfa_submission_id !== (int) $submission->id;
        $conflictInRun = $claimedBy !== null && $claimedBy !== (int) $submission->id;

        if ($conflictOtherUser || $conflictInRun) {
            $usedBy = $owner && (int) $owner->cfa_submission_id !== (int) $submission->id
                ? (string) $owner->name
                : 'another incubatee in this batch';

            return [
                'status' => 'duplicate',
                'row' => array_merge($rowBase, ['used_by' => $usedBy]),
            ];
        }

        $claimedInRun[$phone] = (int) $submission->id;

        if ($existingForCfa) {
            $already = IncubateeLoginPhone::normalize($existingForCfa->phone) === $phone;
            if ($already) {
                return ['status' => 'skipped'];
            }
            if (! $dryRun) {
                $existingForCfa->phone = $phone;
                $existingForCfa->password = Hash::make($phone);
                if (! $existingForCfa->district_id && $submission->district_id) {
                    $existingForCfa->district_id = $submission->district_id;
                }
                $existingForCfa->save();
                $existingByPhone->put($phone, $existingForCfa);
            }

            return ['status' => 'converted'];
        }

        if ($dryRun) {
            return ['status' => 'created'];
        }

        $user = User::query()->create([
            'name' => $submission->applicant_name ?: 'Incubatee',
            'email' => $this->uniqueEmailFor($submission),
            'phone' => $phone,
            'password' => Hash::make($phone),
            'role' => 'incubatee',
            'cfa_submission_id' => $submission->id,
            'is_active' => true,
            'hub_id' => null,
            'district_id' => $submission->district_id,
        ]);
        $existingByPhone->put($phone, $user);

        return ['status' => 'created'];
    }

    private function uniqueEmailFor(CfaSubmission $submission): string
    {
        $fallback = 'incubatee-'.$submission->id.'@'.IncubateeLoginEmailResolver::syntheticDomain();
        if (! User::query()->where('email', $fallback)->exists()) {
            return $fallback;
        }

        do {
            $email = 'incubatee-'.$submission->id.'-'.Str::lower(Str::random(6)).'@'.IncubateeLoginEmailResolver::syntheticDomain();
        } while (User::query()->where('email', $email)->exists());

        return $email;
    }
}
