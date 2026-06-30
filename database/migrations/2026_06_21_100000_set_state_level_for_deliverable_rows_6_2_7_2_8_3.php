<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (! Schema::hasTable('program_deliverable_row_metadata')) {
            return;
        }

        foreach (['6.2', '7.2', '8.3'] as $serial) {
            $updated = DB::table('program_deliverable_row_metadata')
                ->where('serial', $serial)
                ->update([
                    'level' => 'State',
                    'updated_at' => now(),
                ]);

            if ($updated === 0) {
                continue;
            }
        }
    }

    public function down(): void
    {
        // Non-reversible: prior Spoke/Hub overrides are unknown.
    }
};
