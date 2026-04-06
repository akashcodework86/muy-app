<?php

namespace App\Console\Commands;

use App\Models\DistrictDeliverableTarget;
use App\Models\FiscalYear;
use App\Models\StaffMonthlyTarget;
use App\Models\StateDeliverableTarget;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\DB;

class ConsolidateFiscalYearDataCommand extends Command
{
    protected $signature = 'data:consolidate-fiscal-year-data
                            {--to=2025-26 : Target fiscal year code (all merged rows land here)}
                            {--from=* : Source fiscal year codes to merge into --to (default: 2024-25)}
                            {--from-all : Merge every fiscal year except --to into the target (overrides --from)}
                            {--cfa : Also set cfa_submissions.fiscal_year_id from sources to target}
                            {--sync-from-staff : After merge, run targets:sync-from-staff-monthlies for --to (state + district from staff sums)}
                            {--dry-run : Show what would change; no writes}';

    protected $description = 'Merge state/district/staff monthly target rows (and optionally CFA) from older FY rows into FY 2025-26 — no date logic, DB keys only';

    public function handle(): int
    {
        $toCode = (string) $this->option('to');
        $dry = (bool) $this->option('dry-run');
        $fromAll = (bool) $this->option('from-all');
        $withCfa = (bool) $this->option('cfa');
        $syncFromStaff = (bool) $this->option('sync-from-staff');

        $targetFy = FiscalYear::query()->where('code', $toCode)->first();
        if ($targetFy === null) {
            $this->error("Fiscal year `{$toCode}` not found in fiscal_years.");

            return self::FAILURE;
        }

        $targetId = (int) $targetFy->id;

        $sourceQuery = FiscalYear::query()->where('id', '!=', $targetId);
        if ($fromAll) {
            $this->warn('Using --from-all: every FY except the target will be merged in.');
        } else {
            $fromCodes = $this->option('from');
            if ($fromCodes === [] || $fromCodes === ['']) {
                $fromCodes = ['2024-25'];
            }
            $sourceQuery->whereIn('code', $fromCodes);
        }

        $sourceIds = $sourceQuery->pluck('id')->map(fn ($id) => (int) $id)->all();
        $sourceIds = array_values(array_filter($sourceIds, fn (int $id) => $id !== $targetId));

        if ($sourceIds === []) {
            $this->warn('No source fiscal years to merge.');

            return self::SUCCESS;
        }

        $this->info("Target: {$targetFy->name} (id {$targetId})");
        $this->info('Sources (ids): '.implode(', ', array_map(static fn (int $id): string => (string) $id, $sourceIds)));
        $this->newLine();

        if ($dry) {
            $this->dryRunSummary($targetId, $sourceIds, $withCfa);

            return self::SUCCESS;
        }

        DB::transaction(function () use ($targetId, $sourceIds, $withCfa): void {
            $this->mergeStaffMonthlyTargets($targetId, $sourceIds);
            $this->mergeStateTargets($targetId, $sourceIds);
            $this->mergeDistrictTargets($targetId, $sourceIds);

            if ($withCfa) {
                $n = DB::table('cfa_submissions')
                    ->whereIn('fiscal_year_id', $sourceIds)
                    ->update(['fiscal_year_id' => $targetId]);
                $this->info("cfa_submissions: updated {$n} row(s) to target FY.");
            }
        });

        $this->newLine();
        $this->info('Merge complete.');

        if ($syncFromStaff) {
            $this->newLine();
            $this->comment('Running targets:sync-from-staff-monthlies…');
            Artisan::call('targets:sync-from-staff-monthlies', [
                '--fy-code' => $toCode,
                '--districts' => true,
                '--force' => true,
            ]);
            $this->output->write(Artisan::output());
        } else {
            $this->newLine();
            $this->comment('Tip: if district/state should match staff monthlies, run:');
            $this->line('  php artisan targets:sync-from-staff-monthlies --fy-code='.$toCode.' --districts --force');
        }

        return self::SUCCESS;
    }

    /**
     * @param  list<int>  $sourceIds
     */
    private function dryRunSummary(int $targetId, array $sourceIds, bool $withCfa): void
    {
        $staff = StaffMonthlyTarget::query()->whereIn('fiscal_year_id', $sourceIds)->count();
        $state = StateDeliverableTarget::query()->whereIn('fiscal_year_id', $sourceIds)->count();
        $dist = DistrictDeliverableTarget::query()->whereIn('fiscal_year_id', $sourceIds)->count();
        $cfa = $withCfa ? DB::table('cfa_submissions')->whereIn('fiscal_year_id', $sourceIds)->count() : 0;

        $this->table(
            ['Table', 'Rows in source FY(s)'],
            [
                ['staff_monthly_targets', (string) $staff],
                ['state_deliverable_targets', (string) $state],
                ['district_deliverable_targets', (string) $dist],
                ...($withCfa ? [['cfa_submissions (with --cfa)', (string) $cfa]] : []),
            ]
        );
        $this->info('Dry-run: no changes. Run without --dry-run to merge into target FY.');
    }

    /**
     * @param  list<int>  $sourceIds
     */
    private function mergeStaffMonthlyTargets(int $targetId, array $sourceIds): void
    {
        $sums = StaffMonthlyTarget::query()
            ->whereIn('fiscal_year_id', $sourceIds)
            ->selectRaw('user_id, deliverable_id, month_number, SUM(target_count) as total')
            ->groupBy('user_id', 'deliverable_id', 'month_number')
            ->get();

        $merged = 0;
        foreach ($sums as $row) {
            $add = (int) $row->total;
            if ($add === 0) {
                continue;
            }

            $existing = StaffMonthlyTarget::query()
                ->where('fiscal_year_id', $targetId)
                ->where('user_id', (int) $row->user_id)
                ->where('deliverable_id', (int) $row->deliverable_id)
                ->where('month_number', (int) $row->month_number)
                ->first();

            if ($existing !== null) {
                $existing->target_count = (int) $existing->target_count + $add;
                $existing->save();
            } else {
                StaffMonthlyTarget::query()->create([
                    'fiscal_year_id' => $targetId,
                    'user_id' => (int) $row->user_id,
                    'deliverable_id' => (int) $row->deliverable_id,
                    'month_number' => (int) $row->month_number,
                    'target_count' => $add,
                ]);
            }
            $merged++;
        }

        $deleted = StaffMonthlyTarget::query()->whereIn('fiscal_year_id', $sourceIds)->delete();
        $this->info("staff_monthly_targets: merged {$merged} key(s), deleted {$deleted} source row(s).");
    }

    /**
     * @param  list<int>  $sourceIds
     */
    private function mergeStateTargets(int $targetId, array $sourceIds): void
    {
        $sums = StateDeliverableTarget::query()
            ->whereIn('fiscal_year_id', $sourceIds)
            ->selectRaw('deliverable_id, SUM(target_total) as total')
            ->groupBy('deliverable_id')
            ->get();

        foreach ($sums as $row) {
            $add = (int) $row->total;
            $deliverableId = (int) $row->deliverable_id;
            $existing = StateDeliverableTarget::query()
                ->where('fiscal_year_id', $targetId)
                ->where('deliverable_id', $deliverableId)
                ->first();
            $new = (int) ($existing?->target_total ?? 0) + $add;
            StateDeliverableTarget::query()->updateOrCreate(
                ['fiscal_year_id' => $targetId, 'deliverable_id' => $deliverableId],
                ['target_total' => $new]
            );
        }

        $deleted = StateDeliverableTarget::query()->whereIn('fiscal_year_id', $sourceIds)->delete();
        $this->info('state_deliverable_targets: merged '.count($sums).' deliverable group(s), deleted '.$deleted.' source row(s).');
    }

    /**
     * @param  list<int>  $sourceIds
     */
    private function mergeDistrictTargets(int $targetId, array $sourceIds): void
    {
        $sums = DistrictDeliverableTarget::query()
            ->whereIn('fiscal_year_id', $sourceIds)
            ->selectRaw('district_id, deliverable_id, SUM(target_total) as total')
            ->groupBy('district_id', 'deliverable_id')
            ->get();

        foreach ($sums as $row) {
            $add = (int) $row->total;
            $districtId = (int) $row->district_id;
            $deliverableId = (int) $row->deliverable_id;
            $existing = DistrictDeliverableTarget::query()
                ->where('fiscal_year_id', $targetId)
                ->where('district_id', $districtId)
                ->where('deliverable_id', $deliverableId)
                ->first();
            $new = (int) ($existing?->target_total ?? 0) + $add;
            DistrictDeliverableTarget::query()->updateOrCreate(
                [
                    'fiscal_year_id' => $targetId,
                    'district_id' => $districtId,
                    'deliverable_id' => $deliverableId,
                ],
                ['target_total' => $new]
            );
        }

        $deleted = DistrictDeliverableTarget::query()->whereIn('fiscal_year_id', $sourceIds)->delete();
        $this->info('district_deliverable_targets: merged '.count($sums).' district×deliverable group(s), deleted '.$deleted.' source row(s).');
    }
}
