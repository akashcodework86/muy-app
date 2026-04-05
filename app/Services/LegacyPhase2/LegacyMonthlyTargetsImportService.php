<?php

namespace App\Services\LegacyPhase2;

use App\Models\Deliverable;
use App\Models\FiscalYear;
use App\Models\StaffMonthlyTarget;
use App\Models\User;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

class LegacyMonthlyTargetsImportService
{
    /** @var array<string, string> */
    private array $activityToCode;

    /** @var array<string, int> */
    private array $deliverableIdByCode = [];

    public function __construct()
    {
        $this->activityToCode = config('legacy_phase2.activity_type_to_deliverable_code', []);
    }

    /**
     * @return array{
     *   imported:int,
     *   skipped_no_user:int,
     *   skipped_no_deliverable:int,
     *   skipped_district:int,
     *   skipped_bad_month:int,
     *   unmapped_types:array<string,int>
     * }
     */
    public function run(string $fyCode, bool $dryRun): array
    {
        $this->ensureLegacyConfigured();
        if (! Schema::connection('legacy')->hasTable('monthly_activity_targets')) {
            throw new \RuntimeException('Legacy DB has no table monthly_activity_targets.');
        }
        if (! Schema::connection('legacy')->hasTable('users')) {
            throw new \RuntimeException('Legacy DB has no table users.');
        }

        $fy = FiscalYear::query()->where('code', $fyCode)->firstOrFail();

        Deliverable::query()->where('is_active', true)->each(function (Deliverable $d): void {
            $this->deliverableIdByCode[$d->code] = $d->id;
        });

        $stats = [
            'imported' => 0,
            'skipped_no_user' => 0,
            'skipped_no_deliverable' => 0,
            'skipped_district' => 0,
            'skipped_bad_month' => 0,
            'unmapped_types' => [],
        ];

        /** @var array<string, User|null> */
        $laravelUserByEmail = [];

        DB::connection('legacy')
            ->table('monthly_activity_targets as m')
            ->join('users as u', 'u.id', '=', 'm.user_id')
            ->orderBy('m.id')
            ->select([
                'm.id',
                'm.user_id as legacy_user_id',
                'm.district',
                'm.activity_type',
                'm.month_number',
                'm.target_count',
                'u.email as legacy_email',
            ])
            ->chunk(500, function ($rows) use ($fy, $dryRun, &$stats, &$laravelUserByEmail): void {
                foreach ($rows as $row) {
                    $this->processRow($row, $fy->id, $dryRun, $stats, $laravelUserByEmail);
                }
            });

        return $stats;
    }

    private function ensureLegacyConfigured(): void
    {
        $db = (string) config('database.connections.legacy.database', '');
        if ($db === '') {
            throw new \RuntimeException('Set LEGACY_DB_DATABASE in .env (e.g. rbiphase2). Same server is fine.');
        }
    }

    /**
     * @param  object  $row
     * @param  array<string, mixed>  $stats
     * @param  array<string, User|null>  $laravelUserByEmail
     */
    private function processRow($row, int $fiscalYearId, bool $dryRun, array &$stats, array &$laravelUserByEmail): void
    {
        $email = strtolower(trim((string) $row->legacy_email));
        if ($email === '') {
            $stats['skipped_no_user']++;

            return;
        }

        if (! array_key_exists($email, $laravelUserByEmail)) {
            $laravelUserByEmail[$email] = User::query()
                ->whereRaw('LOWER(email) = ?', [$email])
                ->where('role', 'district_staff')
                ->whereNotNull('district_id')
                ->with('district')
                ->first();
        }

        $laravelUser = $laravelUserByEmail[$email];
        if (! $laravelUser || ! $laravelUser->district) {
            $stats['skipped_no_user']++;

            return;
        }

        $userId = $laravelUser->id;

        $rowDistrict = $this->normalizeDistrictLabel((string) $row->district);
        $laravelDistrict = $this->normalizeDistrictLabel($laravelUser->district->name);
        if ($rowDistrict !== '' && $laravelDistrict !== '' && $rowDistrict !== $laravelDistrict) {
            $stats['skipped_district']++;

            return;
        }

        $normalized = strtolower(trim((string) $row->activity_type));
        $activityKey = str_replace([' ', '-'], ['_', '_'], $normalized);
        $deliverableCode = $this->activityToCode[$activityKey] ?? $this->activityToCode[$normalized] ?? null;

        if ($deliverableCode === null) {
            $raw = trim((string) $row->activity_type);
            $stats['unmapped_types'][$raw] = ($stats['unmapped_types'][$raw] ?? 0) + 1;
            $stats['skipped_no_deliverable']++;

            return;
        }

        $deliverableId = $this->deliverableIdByCode[$deliverableCode] ?? null;
        if ($deliverableId === null) {
            $stats['skipped_no_deliverable']++;

            return;
        }

        $month = (int) $row->month_number;
        if ($month < 1 || $month > 12) {
            $stats['skipped_bad_month']++;

            return;
        }

        $count = max(0, (int) $row->target_count);

        if (! $dryRun) {
            StaffMonthlyTarget::query()->updateOrCreate(
                [
                    'fiscal_year_id' => $fiscalYearId,
                    'user_id' => $userId,
                    'deliverable_id' => $deliverableId,
                    'month_number' => $month,
                ],
                ['target_count' => $count]
            );
        }

        $stats['imported']++;
    }

    private function normalizeDistrictLabel(string $s): string
    {
        $s = strtolower(trim(preg_replace('/\s+/u', ' ', $s)));

        return $s;
    }

    /**
     * @param  Collection<int, string>|array<string>  $samples
     */
    public static function formatUnmappedSummary(array $unmapped, int $maxTypes = 15): string
    {
        arsort($unmapped);
        $lines = [];
        $i = 0;
        foreach ($unmapped as $type => $cnt) {
            $lines[] = "  — {$type}: {$cnt}";
            $i++;
            if ($i >= $maxTypes) {
                break;
            }
        }

        return implode("\n", $lines);
    }
}
