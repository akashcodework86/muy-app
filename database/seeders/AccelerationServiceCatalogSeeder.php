<?php

namespace Database\Seeders;

use App\Models\AccelerationServiceItemCatalog;
use App\Support\AccelerationServicesOptions;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\Schema;

class AccelerationServiceCatalogSeeder extends Seeder
{
    public function run(): void
    {
        if (! Schema::hasTable('acceleration_service_item_catalog')) {
            return;
        }

        foreach (AccelerationServicesOptions::systemCatalogRows() as $section => $items) {
            foreach ($items as $item) {
                AccelerationServiceItemCatalog::query()->updateOrCreate(
                    ['item_key' => (string) $item['key']],
                    [
                        'section' => $section,
                        'item_label' => (string) $item['label'],
                        'is_system' => true,
                        'is_active' => true,
                    ],
                );
            }
        }
    }
}
