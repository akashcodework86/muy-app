<?php

namespace Tests\Feature;

use App\Models\District;
use App\Models\DistrictBlock;
use App\Models\GramPanchayat;
use App\Models\Hub;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Tests\TestCase;

class GramPanchayatWebImportTest extends TestCase
{
    use RefreshDatabase;

    public function test_state_admin_can_import_gram_panchayats_via_upload(): void
    {
        $admin = User::factory()->create(['role' => 'state_admin', 'is_active' => true]);
        $district = $this->createDistrict('almora-import', 'Almora');
        DistrictBlock::query()->create([
            'district_id' => $district->id,
            'name' => 'Bhaisiya Chhana',
        ]);

        $csv = "Sr.No,State/UT,District,Block,Gram Panchayat\n"
            ."1,UTTARAKHAND,Almora,Bhaisiya Chhana,Dungerlekh\n"
            ."2,UTTARAKHAND,Almora,Bhaisiya Chhana,Belwal Gaon\n";

        $response = $this->actingAs($admin)->post(route('admin.gram-panchayats.import.store'), [
            'csv' => UploadedFile::fake()->createWithContent('gram-panchayats.csv', $csv, 'text/csv'),
        ]);

        $response->assertRedirect(route('admin.gram-panchayats.import'));
        $response->assertSessionHas('gram_panchayat_import_report');

        $this->assertSame(2, GramPanchayat::query()->count());
    }

    private function createDistrict(string $slug, string $name): District
    {
        $hub = Hub::query()->firstOrCreate(
            ['slug' => 'gp-import-hub'],
            ['name' => 'GP Import Hub', 'sort_order' => 1]
        );

        return District::query()->create([
            'hub_id' => $hub->id,
            'slug' => $slug,
            'name' => $name,
            'sort_order' => 1,
        ]);
    }
}
