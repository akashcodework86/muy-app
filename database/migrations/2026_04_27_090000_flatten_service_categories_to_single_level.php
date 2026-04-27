<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    public function up(): void
    {
        DB::table('service_categories')
            ->whereNotNull('parent_id')
            ->update(['parent_id' => null]);
    }

    public function down(): void
    {
        // Intentionally no-op: cannot infer prior parent links safely.
    }
};

