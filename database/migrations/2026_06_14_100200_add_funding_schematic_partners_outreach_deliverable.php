<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    public function up(): void
    {
        $exists = DB::table('deliverables')->where('code', 'funding_schematic_partners_outreach')->exists();
        if ($exists) {
            return;
        }

        $maxSort = (int) DB::table('deliverables')->max('sort_order');

        DB::table('deliverables')->insert([
            'sort_order' => $maxSort + 1,
            'code' => 'funding_schematic_partners_outreach',
            'name' => 'No of Partners outreach (Funding & Schematic Convergence)',
            'mis_entry_label' => 'Funding partners outreach',
            'is_active' => true,
            'created_at' => now(),
            'updated_at' => now(),
        ]);
    }

    public function down(): void
    {
        DB::table('deliverables')->where('code', 'funding_schematic_partners_outreach')->delete();
    }
};
