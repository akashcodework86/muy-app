<?php

namespace App\Console\Commands;

use App\Models\FiscalYear;
use App\Models\StaffMonthlyTarget;
use App\Services\LegacyPhase2\LegacyMonthlyTargetsImportService;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

class ReportLegacyMonthlyTargetsReconcileCommand extends Command
{
    protected $signature = 'report:legacy-monthly-targets-reconcile
                            {--fy-code=2024-25 : Fiscal year code in muy.fiscal_years}';

    protected $description = 'Compare rbiphase2.monthly_activity_targets vs muy.staff_monthly_targets + import dry-run stats';

    public function handle(LegacyMonthlyTargetsImportService $importService): int
    {
        $fyCode = (string) $this->option('fy-code');

        $legacyDb = (string) config('database.connections.legacy.database', '');
        if ($legacyDb === '') {
            $this->error('Set LEGACY_DB_DATABASE in .env (e.g. rbiphase2).');

            return self::FAILURE;
        }

        if (! Schema::connection('legacy')->hasTable('monthly_activity_targets')) {
            $this->error('Legacy DB has no table monthly_activity_targets.');

            return self::FAILURE;
        }

        $fy = FiscalYear::query()->where('code', $fyCode)->first();
        if (! $fy) {
            $this->error("No fiscal year with code `{$fyCode}` in muy.fiscal_years.");

            return self::FAILURE;
        }

        $this->info('Muy FY: '.$fy->code.' (id '.$fy->id.') — '.$fy->name);
        $this->info('Legacy DB: '.$legacyDb.' @ '.config('database.connections.legacy.host'));
        $this->newLine();

        $totalLegacy = (int) DB::connection('legacy')->table('monthly_activity_targets')->count();
        $withLegacyUser = (int) DB::connection('legacy')
            ->table('monthly_activity_targets as m')
            ->join('users as u', 'u.id', '=', 'm.user_id')
            ->count();
        $orphanTargets = (int) DB::connection('legacy')
            ->table('monthly_activity_targets as m')
            ->leftJoin('users as u', 'u.id', '=', 'm.user_id')
            ->whereNull('u.id')
            ->count();

        $stateTeamRows = 0;
        if (Schema::connection('legacy')->hasColumn('users', 'role')) {
            $stateTeamRows = (int) DB::connection('legacy')
                ->table('monthly_activity_targets as m')
                ->join('users as u', 'u.id', '=', 'm.user_id')
                ->where('u.role', 'state_team')
                ->count();
        }

        $laravelRows = (int) StaffMonthlyTarget::query()->where('fiscal_year_id', $fy->id)->count();

        $this->table(
            ['Legacy / Laravel metric', 'Count'],
            [
                ['monthly_activity_targets (all rows)', $totalLegacy],
                ['…with join to legacy users', $withLegacyUser],
                ['…orphan user_id (no legacy user row)', $orphanTargets],
                ['…where legacy user.role = state_team', $stateTeamRows],
                ['staff_monthly_targets (muy, this FY)', $laravelRows],
            ]
        );

        $this->newLine();
        $this->info('Import dry-run (same rules as import:legacy-monthly-targets):');

        try {
            $stats = $importService->run($fyCode, true);
        } catch (\Throwable $e) {
            $this->error($e->getMessage());

            return self::FAILURE;
        }

        $this->table(
            ['Dry-run metric', 'Count'],
            [
                ['Legacy rows that would import', $stats['imported']],
                ['Skipped: no Laravel district_staff (email)', $stats['skipped_no_user']],
                ['Skipped: district label mismatch', $stats['skipped_district']],
                ['Skipped: unknown activity_type', $stats['skipped_no_deliverable']],
                ['Skipped: bad month', $stats['skipped_bad_month']],
            ]
        );

        if ($stats['unmapped_types'] !== []) {
            $this->warn('Unmapped activity_type (fix config/legacy_phase2.php):');
            $this->line(LegacyMonthlyTargetsImportService::formatUnmappedSummary($stats['unmapped_types'], 25));
        }

        $sumLegacy = $stats['imported'] + $stats['skipped_no_user'] + $stats['skipped_no_deliverable']
            + $stats['skipped_district'] + $stats['skipped_bad_month'];

        $this->newLine();
        $this->line('Legacy rows processed in dry-run (sum of above): '.$sumLegacy);
        if ($withLegacyUser !== $sumLegacy) {
            $this->warn('Joinable legacy rows ('.$withLegacyUser.') ≠ dry-run sum ('.$sumLegacy.') — check for code changes or DB drift.');
        }
        if ($stats['imported'] !== $laravelRows) {
            $this->comment(
                'Would-import ('.$stats['imported'].') vs muy rows ('.$laravelRows.'): '
                .'multiple legacy rows can map to the same (user, deliverable, month) and collapse to one row; '
                .'or targets were edited manually in Laravel.'
            );
        }

        return self::SUCCESS;
    }
}
