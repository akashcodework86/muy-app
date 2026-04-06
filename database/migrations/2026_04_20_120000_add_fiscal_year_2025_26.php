<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    public function up(): void
    {
        $now = now();

        if (! DB::table('fiscal_years')->where('code', '2025-26')->exists()) {
            DB::table('fiscal_years')->insert([
                'code' => '2025-26',
                'name' => 'FY 2025-26',
                'starts_on' => '2025-04-02',
                'ends_on' => '2026-04-01',
                'is_active' => false,
                'created_at' => $now,
                'updated_at' => $now,
            ]);
        }
    }

    public function down(): void
    {
        DB::table('fiscal_years')->where('code', '2025-26')->delete();
    }
};
