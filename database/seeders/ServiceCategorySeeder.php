<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;

class ServiceCategorySeeder extends Seeder
{
    public function run(): void
    {
        $rows = [
            ['business_formalisation', 'Business Formalisation', 1],
            ['legal_support', 'Legal Support & Licencing', 2],
            ['convergence', 'Convergence', 3],
            ['business_acceleration', 'Business Acceleration', 4],
            ['other_support', 'Other Support Service', 5],
            ['business_model_canvas', 'Business Model Canvas', 6],
            ['forward_linkages', 'Forward Linkages', 7],
            ['incubation_support', 'Incubation Support', 8],
        ];

        foreach ($rows as [$slug, $name, $sort]) {
            DB::table('service_categories')->insert([
                'slug' => $slug,
                'name' => $name,
                'sort_order' => $sort,
                'created_at' => now(),
                'updated_at' => now(),
            ]);
        }
    }
}
