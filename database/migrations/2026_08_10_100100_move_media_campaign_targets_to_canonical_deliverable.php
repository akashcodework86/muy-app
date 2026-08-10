<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (! Schema::hasTable('deliverables') || ! Schema::hasTable('official_state_monthly_targets')) {
            return;
        }

        $canonicalId = DB::table('deliverables')
            ->where('code', 'media_campaigns')
            ->value('id');

        if ($canonicalId === null) {
            return;
        }

        $legacyIds = DB::table('deliverables')
            ->where('id', '!=', $canonicalId)
            ->where(function ($query): void {
                $query->where('code', 'newspaper_ads_and_radio_promotion_campaigns')
                    ->orWhere('name', 'Newspaper Ads and Radio promotion campaigns')
                    ->orWhere('mis_entry_label', 'Newspaper / Radio campaigns');
            })
            ->pluck('id')
            ->map(fn ($id) => (int) $id)
            ->all();

        if ($legacyIds === []) {
            return;
        }

        DB::transaction(function () use ($canonicalId, $legacyIds): void {
            $legacyTargets = DB::table('official_state_monthly_targets')
                ->whereIn('deliverable_id', $legacyIds)
                ->get();

            foreach ($legacyTargets as $legacyTarget) {
                $key = [
                    'fiscal_year_id' => $legacyTarget->fiscal_year_id,
                    'deliverable_id' => $canonicalId,
                    'month_number' => $legacyTarget->month_number,
                ];

                $existing = DB::table('official_state_monthly_targets')->where($key)->first();
                $targetCount = max(
                    (int) ($existing->target_count ?? 0),
                    (int) $legacyTarget->target_count,
                );

                if ($existing) {
                    DB::table('official_state_monthly_targets')
                        ->where('id', $existing->id)
                        ->update([
                            'target_count' => $targetCount,
                            'updated_at' => now(),
                        ]);
                } else {
                    DB::table('official_state_monthly_targets')->insert($key + [
                        'target_count' => $targetCount,
                        'created_at' => $legacyTarget->created_at ?? now(),
                        'updated_at' => now(),
                    ]);
                }
            }

            DB::table('official_state_monthly_targets')
                ->whereIn('deliverable_id', $legacyIds)
                ->delete();
        });
    }

    public function down(): void
    {
        // Historical targets remain on the canonical 10.4 deliverable.
    }
};
