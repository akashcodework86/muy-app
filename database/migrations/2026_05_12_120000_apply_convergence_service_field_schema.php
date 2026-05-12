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

        $legacyKeys = ['department_name', 'convergence_type', 'convergence_date', 'official_reference'];

        DB::table('services')
            ->whereIn('service_category_id', $categoryIds)
            ->orderBy('id')
            ->get(['id', 'field_schema'])
            ->each(function ($service) use ($schema, $legacyKeys): void {
                $raw = $service->field_schema;
                if ($raw === null || $raw === '' || $raw === '[]') {
                    DB::table('services')->where('id', $service->id)->update([
                        'field_schema' => $schema,
                        'updated_at' => now(),
                    ]);

                    return;
                }

                $decoded = json_decode((string) $raw, true);
                if (! is_array($decoded) || $decoded === []) {
                    DB::table('services')->where('id', $service->id)->update([
                        'field_schema' => $schema,
                        'updated_at' => now(),
                    ]);

                    return;
                }

                $keys = collect($decoded)
                    ->filter(fn ($row) => is_array($row))
                    ->map(fn (array $row) => (string) ($row['key'] ?? ''))
                    ->filter()
                    ->values()
                    ->all();

                if ($keys !== [] && count(array_diff($keys, $legacyKeys)) === 0) {
                    DB::table('services')->where('id', $service->id)->update([
                        'field_schema' => $schema,
                        'updated_at' => now(),
                    ]);
                }
            });
    }

    public function down(): void
    {
        // Intentionally no-op: prior field_schema values are not recoverable safely.
    }
};
