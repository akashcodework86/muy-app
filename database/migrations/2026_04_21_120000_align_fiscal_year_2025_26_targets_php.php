<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

/**
 * Align FY 2025-26 bounds with admin/targets.php + admin/24.php Phase 2 window:
 * M1–M12 achievements use fiscal_years.starts_on … ends_on (see Phase2TargetsPhpAchievementService).
 * Window matches legacy quarter map (e.g. Q4 ending 1 Apr 2026) — not calendar Apr 1 / Mar 31 alone.
 */
return new class extends Migration
{
    public function up(): void
    {
        $now = now();

        $payload = [
            'name' => 'FY 2025-26',
            'starts_on' => '2025-04-02',
            'ends_on' => '2026-04-01',
            'updated_at' => $now,
        ];

        if (DB::table('fiscal_years')->where('code', '2025-26')->exists()) {
            DB::table('fiscal_years')->where('code', '2025-26')->update($payload);
        } else {
            DB::table('fiscal_years')->insert(array_merge($payload, [
                'code' => '2025-26',
                'is_active' => false,
                'created_at' => $now,
            ]));
        }
    }

    public function down(): void
    {
        // Intentionally empty: do not revert FY dates in production.
    }
};
