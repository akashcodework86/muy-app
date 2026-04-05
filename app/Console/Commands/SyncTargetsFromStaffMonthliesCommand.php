<?php

namespace App\Console\Commands;

use App\Models\Deliverable;
use App\Models\DistrictDeliverableTarget;
use App\Models\FiscalYear;
use App\Models\StaffMonthlyTarget;
use App\Models\StateDeliverableTarget;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;

class SyncTargetsFromStaffMonthliesCommand extends Command
{
    protected $signature = 'targets:sync-from-staff-monthlies
                            {--fy-code=2024-25 : Fiscal year code}
                            {--districts : Also sync district_deliverable_targets from staff (by user district)}
                            {--dry-run : Show counts only}
                            {--force : Overwrite state/district rows even when target_total > 0}';

    protected $description = 'Fill state_deliverable_targets (and optionally district targets) from SUM(staff_monthly_targets) — import does not touch these tables';

    public function handle(): int
    {
        $fyCode = (string) $this->option('fy-code');
        $dry = (bool) $this->option('dry-run');
        $force = (bool) $this->option('force');
        $withDistricts = (bool) $this->option('districts');

        $fy = FiscalYear::query()->where('code', $fyCode)->first();
        if (! $fy) {
            $this->error("Fiscal year `{$fyCode}` not found.");

            return self::FAILURE;
        }

        $this->info("FY: {$fy->name} (id {$fy->id})");
        $this->newLine();

        $stateRows = StaffMonthlyTarget::query()
            ->where('fiscal_year_id', $fy->id)
            ->selectRaw('deliverable_id, SUM(target_count) as t')
            ->groupBy('deliverable_id')
            ->get()
            ->keyBy('deliverable_id');

        $updatedState = 0;
        $skippedState = 0;

        foreach (Deliverable::query()->where('is_active', true)->orderBy('sort_order')->get() as $d) {
            $sum = (int) ($stateRows[$d->id]->t ?? 0);
            $row = StateDeliverableTarget::query()
                ->where('fiscal_year_id', $fy->id)
                ->where('deliverable_id', $d->id)
                ->first();

            if ($row && (int) $row->target_total > 0 && ! $force) {
                $skippedState++;

                continue;
            }

            if ($dry) {
                $this->line('  [state] '.$d->code.': would set '.$sum.($row ? ' (current '.(int) $row->target_total.')' : ' (new row)'));
                $updatedState++;

                continue;
            }

            StateDeliverableTarget::query()->updateOrCreate(
                ['fiscal_year_id' => $fy->id, 'deliverable_id' => $d->id],
                ['target_total' => $sum]
            );
            $updatedState++;
        }

        $this->info('State targets: '.($dry ? 'would write ' : 'wrote ').$updatedState.' deliverable row(s); skipped (already non-zero, use --force): '.$skippedState);

        if (! $withDistricts) {
            $this->newLine();
            $this->comment('Tip: run with --districts to fill District targets from the same staff data so totals match.');

            return self::SUCCESS;
        }

        $this->newLine();
        $districtAgg = DB::table('staff_monthly_targets as smt')
            ->join('users', 'users.id', '=', 'smt.user_id')
            ->where('smt.fiscal_year_id', $fy->id)
            ->whereNotNull('users.district_id')
            ->selectRaw('users.district_id, smt.deliverable_id, SUM(smt.target_count) as t')
            ->groupBy('users.district_id', 'smt.deliverable_id')
            ->get();

        $updD = 0;
        $skipD = 0;

        foreach ($districtAgg as $agg) {
            $distId = (int) $agg->district_id;
            $delId = (int) $agg->deliverable_id;
            $sum = (int) $agg->t;

            $existing = DistrictDeliverableTarget::query()
                ->where('fiscal_year_id', $fy->id)
                ->where('district_id', $distId)
                ->where('deliverable_id', $delId)
                ->first();

            if ($existing && (int) $existing->target_total > 0 && ! $force) {
                $skipD++;

                continue;
            }

            if ($dry) {
                $updD++;

                continue;
            }

            DistrictDeliverableTarget::query()->updateOrCreate(
                [
                    'fiscal_year_id' => $fy->id,
                    'district_id' => $distId,
                    'deliverable_id' => $delId,
                ],
                ['target_total' => $sum]
            );
            $updD++;
        }

        $this->info('District targets: '.($dry ? 'would write ' : 'wrote ').$updD.' row(s); skipped (non-zero, no --force): '.$skipD);

        return self::SUCCESS;
    }
}
