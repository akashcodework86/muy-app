<?php

namespace Tests\Feature;

use App\Models\District;
use App\Models\Hub;
use App\Models\User;
use App\Services\Exports\HomestayDetailsPackService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Tests\TestCase;
use ZipArchive;

class HomestayDetailsExportTest extends TestCase
{
    use RefreshDatabase;

    public function test_pack_filters_homestay_and_onboard_scope(): void
    {
        $district = $this->createDistrict('homestay-dist', 'Homestay Dist');

        $this->seedPhase3Homestay($district, 'HS-ON-1', 'Onboarded Homestay', true);
        $this->seedPhase3Homestay($district, 'HS-NON-1', 'Non Homestay Person', false);
        $this->seedPhase3Other($district, 'AG-1', 'Agri Person');

        $svc = app(HomestayDetailsPackService::class);

        $all = $svc->build((int) $district->id, null, 'all');
        $this->assertSame(2, $all['summary']['phase3_total']);
        $this->assertSame(2, $all['summary']['combined_total']);

        $on = $svc->build((int) $district->id, null, 'onboarded');
        $this->assertSame(1, $on['summary']['phase3_total']);
        $this->assertSame('Onboarded Homestay', $on['phase3'][0]['applicant_name']);

        $non = $svc->build((int) $district->id, null, 'non_onboarded');
        $this->assertSame(1, $non['summary']['phase3_total']);
        $this->assertSame('Non Homestay Person', $non['phase3'][0]['applicant_name']);
    }

    public function test_pack_writes_xlsx_with_expected_sheets(): void
    {
        if (! class_exists(ZipArchive::class)) {
            $this->markTestSkipped('ext-zip required');
        }

        $district = $this->createDistrict('homestay-xlsx', 'Xlsx Dist');
        $this->seedPhase3Homestay($district, 'HS-X-1', 'Sheet Homestay', true);

        $svc = app(HomestayDetailsPackService::class);
        $pack = $svc->build((int) $district->id, null, 'all');
        $path = storage_path('app/temp/test-homestay-'.uniqid('', true).'.xlsx');
        $svc->writeToPath($pack, $path);
        $this->assertFileExists($path);

        $zip = new ZipArchive;
        $this->assertTrue($zip->open($path) === true);
        $workbook = $zip->getFromName('xl/workbook.xml');
        $this->assertIsString($workbook);
        foreach (['Summary', 'Combined', 'Phase 1', 'Phase 2', 'Phase 3'] as $sheet) {
            $this->assertStringContainsString($sheet, $workbook);
        }
        $combined = $zip->getFromName('xl/worksheets/sheet2.xml');
        $this->assertIsString($combined);
        $this->assertStringContainsString('Sheet Homestay', $combined);
        $zip->close();
        @unlink($path);
    }

    public function test_state_admin_can_download_homestay_excel(): void
    {
        if (! class_exists(ZipArchive::class)) {
            $this->markTestSkipped('ext-zip required');
        }

        $admin = User::factory()->create(['role' => 'state_admin', 'is_active' => true]);
        $district = $this->createDistrict('homestay-dl', 'Download Dist');
        $this->seedPhase3Homestay($district, 'HS-DL-1', 'Download Homestay', false);

        $response = $this->actingAs($admin)->get(route('admin.data-centre.export-homestay-details', [
            'district_id' => $district->id,
            'onboard_scope' => 'all',
        ]));

        $response->assertOk();
        $response->assertHeader('content-type', 'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet');
        $this->assertStringContainsString('homestay-details-', (string) $response->headers->get('content-disposition'));
    }

    private function seedPhase3Homestay(District $district, string $appNo, string $name, bool $onboarded): void
    {
        $cfaId = (int) DB::table('cfa_submissions')->insertGetId([
            'district_id' => $district->id,
            'application_no' => $appNo,
            'applicant_name' => $name,
            'phone' => '9876543210',
            'source' => 'phase3',
            'payload' => json_encode([
                'business_category' => 'Homestay',
                'gender' => 'female',
                'block' => 'Block A',
                'is_registered' => 'Yes',
                'turnover_last_fy' => '120000',
            ]),
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        if (! $onboarded) {
            return;
        }

        $batchId = (int) DB::table('onboarding_batches')->insertGetId([
            'hub_id' => $district->hub_id,
            'district_id' => $district->id,
            'name' => $district->name.'-hs-batch',
            'target_size' => 1,
            'status' => 'locked',
            'locked_at' => now(),
            'onboarding_date' => now()->toDateString(),
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        DB::table('onboarding_batch_cfa')->insert([
            'onboarding_batch_id' => $batchId,
            'cfa_submission_id' => $cfaId,
            'created_at' => now(),
            'updated_at' => now(),
        ]);
    }

    private function seedPhase3Other(District $district, string $appNo, string $name): void
    {
        DB::table('cfa_submissions')->insert([
            'district_id' => $district->id,
            'application_no' => $appNo,
            'applicant_name' => $name,
            'phone' => '9876543211',
            'source' => 'phase3',
            'payload' => json_encode(['business_category' => 'Agri Allied']),
            'created_at' => now(),
            'updated_at' => now(),
        ]);
    }

    private function createDistrict(string $slug, string $name): District
    {
        $hub = Hub::query()->create([
            'slug' => 'hub-'.$slug,
            'name' => 'Hub '.$name,
            'sort_order' => 1,
        ]);

        return District::query()->create([
            'hub_id' => $hub->id,
            'slug' => $slug,
            'name' => $name,
            'sort_order' => 1,
        ]);
    }
}
