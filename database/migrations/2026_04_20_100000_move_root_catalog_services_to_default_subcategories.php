<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

/**
 * Legacy seed attached services to top-level categories. The case picker and UI
 * require services under a subcategory (parent_id set). Ensure each root that
 * still has direct services gets a default subcategory and move rows there.
 */
return new class extends Migration
{
    public function up(): void
    {
        $roots = DB::table('service_categories')->whereNull('parent_id')->get();

        foreach ($roots as $root) {
            $directIds = DB::table('services')
                ->where('service_category_id', $root->id)
                ->pluck('id');

            if ($directIds->isEmpty()) {
                continue;
            }

            $baseSlug = $root->slug.'_services';
            $slug = $baseSlug;
            $i = 0;
            while (DB::table('service_categories')->where('slug', $slug)->exists()) {
                $existing = DB::table('service_categories')->where('slug', $slug)->first();
                if ($existing && (int) $existing->parent_id === (int) $root->id) {
                    $subId = (int) $existing->id;
                    DB::table('services')
                        ->whereIn('id', $directIds)
                        ->update([
                            'service_category_id' => $subId,
                            'updated_at' => now(),
                        ]);

                    continue 2;
                }
                $i++;
                $slug = $baseSlug.'_'.$i;
            }

            $subId = (int) DB::table('service_categories')->insertGetId([
                'parent_id' => $root->id,
                'slug' => $slug,
                'name' => 'Services',
                'sort_order' => 0,
                'created_at' => now(),
                'updated_at' => now(),
            ]);

            DB::table('services')
                ->whereIn('id', $directIds)
                ->update([
                    'service_category_id' => $subId,
                    'updated_at' => now(),
                ]);
        }
    }

    public function down(): void
    {
        // Not reversible without a snapshot of previous service_category_id values.
    }
};
