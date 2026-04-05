<?php

namespace App\Services\LegacyPhase2;

use App\Models\District;
use App\Models\User;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Str;

class LegacyStaffImportService
{
    /** @var array<string, string> normalized alias → canonical district name (muy.districts.name) */
    private array $districtAliasToCanonical = [];

    /** @var array<string, District> normalized muy district name → model */
    private array $districtByNormName = [];

    /**
     * @return array{
     *   created:int,
     *   updated:int,
     *   skipped_no_email:int,
     *   skipped_bad_email:int,
     *   skipped_no_district:int,
     *   skipped_email_conflict:int,
     *   samples_no_email: list<string>,
     *   samples_bad_email: list<string>,
     *   samples_no_district: list<string>,
     *   samples_email_conflict: list<string>,
     * }
     */
    public function run(bool $dryRun, ?int $limit = null): array
    {
        $this->ensureLegacyConfigured();
        if (! Schema::connection('legacy')->hasTable('users')) {
            throw new \RuntimeException('Legacy DB has no table users.');
        }

        $roles = config('legacy_phase2.staff_import.roles', []);
        if ($roles === []) {
            throw new \RuntimeException('legacy_phase2.staff_import.roles is empty.');
        }

        $this->buildDistrictLookup();
        $defaultPassword = (string) config('legacy_phase2.staff_import.default_password', 'password@123');

        $stats = [
            'created' => 0,
            'updated' => 0,
            'skipped_no_email' => 0,
            'skipped_bad_email' => 0,
            'skipped_no_district' => 0,
            'skipped_email_conflict' => 0,
            'samples_no_email' => [],
            'samples_bad_email' => [],
            'samples_no_district' => [],
            'samples_email_conflict' => [],
        ];

        $q = DB::connection('legacy')
            ->table('users')
            ->whereIn('role', $roles)
            ->orderBy('id');

        $processed = 0;
        foreach ($q->cursor() as $row) {
            if ($limit !== null && $processed >= $limit) {
                break;
            }
            $processed++;
            $this->processLegacyUser($row, $dryRun, $defaultPassword, $stats);
        }

        return $stats;
    }

    private function ensureLegacyConfigured(): void
    {
        $db = (string) config('database.connections.legacy.database', '');
        if ($db === '') {
            throw new \RuntimeException('Set LEGACY_DB_DATABASE in .env (e.g. rbiphase2).');
        }
    }

    private function buildDistrictLookup(): void
    {
        $this->districtAliasToCanonical = [];
        $aliases = config('legacy_phase2.staff_import.district_aliases', []);
        foreach ($aliases as $canonicalName => $variants) {
            $this->districtAliasToCanonical[$this->normalize((string) $canonicalName)] = (string) $canonicalName;
            foreach ($variants as $v) {
                $this->districtAliasToCanonical[$this->normalize((string) $v)] = (string) $canonicalName;
            }
        }

        $this->districtByNormName = [];
        foreach (District::query()->with('hub')->get() as $d) {
            $this->districtByNormName[$this->normalize($d->name)] = $d;
        }
    }

    /**
     * @param  object  $row
     * @param  array<string, mixed>  $stats
     */
    private function processLegacyUser($row, bool $dryRun, string $defaultPassword, array &$stats): void
    {
        $legacyId = (int) $row->id;
        $emailRaw = trim((string) ($row->email ?? ''));
        if ($emailRaw === '') {
            $stats['skipped_no_email']++;
            $this->pushSample($stats['samples_no_email'], "legacy id {$legacyId}: empty email");

            return;
        }

        $email = strtolower($emailRaw);
        if (! filter_var($email, FILTER_VALIDATE_EMAIL)) {
            $stats['skipped_bad_email']++;
            $this->pushSample($stats['samples_bad_email'], "legacy id {$legacyId}: invalid email `{$emailRaw}`");

            return;
        }

        $district = $this->resolveDistrict((string) ($row->district ?? ''));
        if (! $district) {
            $stats['skipped_no_district']++;
            $this->pushSample($stats['samples_no_district'], "legacy id {$legacyId}: district `".trim((string) ($row->district ?? '')).'`');

            return;
        }

        $hubId = $district->hub_id;

        $byLegacy = User::query()->where('legacy_user_id', $legacyId)->first();
        $byEmail = User::query()->whereRaw('LOWER(email) = ?', [$email])->first();

        if ($byEmail && $byLegacy && $byEmail->id !== $byLegacy->id) {
            $stats['skipped_email_conflict']++;
            $this->pushSample($stats['samples_email_conflict'], "legacy id {$legacyId}: email `{$email}` belongs to user {$byEmail->id}, legacy row points elsewhere");

            return;
        }

        if ($byEmail && ! $byLegacy && $byEmail->legacy_user_id !== null && (int) $byEmail->legacy_user_id !== $legacyId) {
            $stats['skipped_email_conflict']++;
            $this->pushSample($stats['samples_email_conflict'], "legacy id {$legacyId}: email `{$email}` already linked to legacy_user_id {$byEmail->legacy_user_id}");

            return;
        }

        $name = trim((string) ($row->full_name ?? ''));
        if ($name === '') {
            $name = trim((string) ($row->username ?? ''));
        }
        if ($name === '') {
            $name = strstr($email, '@', true) ?: $email;
        }

        $isActive = (bool) ((int) ($row->is_active ?? 1));

        $wouldUpdate = $byLegacy !== null || $byEmail !== null;

        if ($dryRun) {
            if ($wouldUpdate) {
                $stats['updated']++;
            } else {
                $stats['created']++;
            }

            return;
        }

        if ($byLegacy) {
            $byLegacy->update([
                'name' => $name,
                'email' => $email,
                'role' => 'district_staff',
                'hub_id' => $hubId,
                'district_id' => $district->id,
                'is_active' => $isActive,
            ]);
            $this->ensureReferralToken($byLegacy);
            $stats['updated']++;

            return;
        }

        if ($byEmail) {
            $byEmail->update([
                'legacy_user_id' => $legacyId,
                'name' => $name,
                'email' => $email,
                'role' => 'district_staff',
                'hub_id' => $hubId,
                'district_id' => $district->id,
                'is_active' => $isActive,
                'password' => Hash::make($defaultPassword),
            ]);
            $this->ensureReferralToken($byEmail);
            $stats['updated']++;

            return;
        }

        $user = User::query()->create([
            'legacy_user_id' => $legacyId,
            'name' => $name,
            'email' => $email,
            'password' => Hash::make($defaultPassword),
            'role' => 'district_staff',
            'hub_id' => $hubId,
            'district_id' => $district->id,
            'designation_id' => null,
            'is_active' => $isActive,
            'referral_token' => $this->newReferralToken(),
        ]);
        $this->ensureReferralToken($user);
        $stats['created']++;
    }

    private function resolveDistrict(string $legacyDistrict): ?District
    {
        $norm = $this->normalize($legacyDistrict);
        if ($norm === '') {
            return null;
        }

        if (isset($this->districtAliasToCanonical[$norm])) {
            $canonical = $this->districtAliasToCanonical[$norm];

            return District::query()->where('name', $canonical)->first();
        }

        return $this->districtByNormName[$norm] ?? null;
    }

    private function normalize(string $s): string
    {
        $s = strtolower(trim(preg_replace('/\s+/u', ' ', $s)));

        return $s;
    }

    /**
     * @param  list<string>  $arr
     */
    private function pushSample(array &$arr, string $line): void
    {
        if (count($arr) >= 12) {
            return;
        }
        $arr[] = $line;
    }

    private function ensureReferralToken(User $user): void
    {
        if ($user->referral_token) {
            return;
        }
        $user->forceFill(['referral_token' => $this->newReferralToken()])->save();
    }

    private function newReferralToken(): string
    {
        do {
            $token = Str::lower(Str::random(40));
        } while (User::query()->where('referral_token', $token)->exists());

        return $token;
    }
}
