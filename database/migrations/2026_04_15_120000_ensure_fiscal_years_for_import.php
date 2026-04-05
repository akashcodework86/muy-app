<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    public function up(): void
    {
        $now = now();

        $exists = DB::table('fiscal_years')->where('code', '2024-25')->exists();
        if (! $exists) {
            DB::table('fiscal_years')->insert([
                'code' => '2024-25',
                'name' => 'FY 2024-25',
                'starts_on' => '2024-04-02',
                'ends_on' => '2025-04-01',
                'is_active' => false,
                'created_at' => $now,
                'updated_at' => $now,
            ]);
        }

        DB::table('fiscal_years')->where('code', '2026-27')->update([
            'starts_on' => '2026-04-02',
            'ends_on' => '2027-04-01',
            'updated_at' => $now,
        ]);
    }

    public function down(): void
    {
        DB::table('fiscal_years')->where('code', '2024-25')->delete();
    }
};
