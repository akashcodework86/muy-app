<?php

use App\Support\ServiceSchemaTemplates;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    public function up(): void
    {
        $categoryIds = DB::table('service_categories')
            ->whereIn('slug', [
                'convergence-with-line-departments',
                'convergence',
                'convergence_services',
            ])
            ->pluck('id');

        if ($categoryIds->isEmpty()) {
            return;
        }

        $schema = json_encode(ServiceSchemaTemplates::convergenceWithLineDepartments(), JSON_UNESCAPED_UNICODE);
        if ($schema === false) {
            return;
        }

        DB::table('services')
            ->whereIn('service_category_id', $categoryIds)
            ->update([
                'field_schema' => $schema,
                'updated_at' => now(),
            ]);
    }

    public function down(): void
    {
        // Intentionally no-op: prior field_schema values are not recoverable safely.
    }
};
