<?php

namespace Tests\Feature;

use App\Models\District;
use App\Models\Hub;
use App\Models\User;
use App\Services\Exports\OnboardedTurnoverWisePackService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Tests\TestCase;
use ZipArchive;

class OnboardedTurnoverWiseExportTest extends TestCase
{
    use RefreshDatabase;

    public function test_pack_service_builds_phase3_rows_sorted_by_turnover_slab(): void
    {
        $district = $this->createDistrict('dehradun-turnover', 'Dehradun');
        $this->seedOnboardedWithTurnover($district, 'RBI-TO-001', 'High Turnover', 750000);
        $this->seedOnboardedWithTurnover($district, 'RBI-TO-002', 'Zero Turnover', 0);
        $this->seedOnboardedWithTurnover($district, 'RBI-TO-003', 'Mid Turnover', 250000);

        $pack = app(OnboardedTurnoverWisePackService::class)->build((int) $district->id);

        $this->assertSame(3, $pack['summary']['phase3_total']);
        $this->assertSame(1, $pack['summary']['phase3_slabs']['Zero income']);
        $this->assertSame(1, $pack['summary']['phase3_slabs']['INR 1 – 5 Lakh']);
        $this->assertSame(1, $pack['summary']['phase3_slabs']['INR 5 – 10 Lakh']);

        $names = array_column($pack['phase3'], 'applicant_name');
        $this->assertSame(['Zero Turnover', 'Mid Turnover', 'High Turnover'], $names);

        $this->assertSame('Zero income', $pack['phase3'][0]['turnover_slab']);
        $this->assertSame('INR 1 – 5 Lakh', $pack['phase3'][1]['turnover_slab']);
        $this->assertSame('INR 5 – 10 Lakh', $pack['phase3'][2]['turnover_slab']);
    }

    public function test_pack_service_writes_xlsx_with_three_sheets(): void
    {
        if (! class_exists(ZipArchive::class)) {
            $this->markTestSkipped('ext-zip required for Excel export.');
        }

        $district = $this->createDistrict('nainital-turnover', 'Nainital');
        $this->seedOnboardedWithTurnover($district, 'RBI-TO-010', 'Sheet Person', 150000);

        $service = app(OnboardedTurnoverWisePackService::class);
        $pack = $service->build((int) $district->id);
        $path = storage_path('app/temp/test-onboarded-turnover-'.uniqid('', true).'.xlsx');
        $service->writeToPath($pack, $path);

        $this->assertFileExists($path);

        $zip = new ZipArchive;
        $this->assertTrue($zip->open($path) === true);
        $workbook = $zip->getFromName('xl/workbook.xml');
        $this->assertIsString($workbook);
        $this->assertStringContainsString('Summary', $workbook);
        $this->assertStringContainsString('Phase 2', $workbook);
        $this->assertStringContainsString('Phase 3', $workbook);

        $phase3 = $zip->getFromName('xl/worksheets/sheet3.xml');
        $this->assertIsString($phase3);
        $this->assertStringContainsString('Sheet Person', $phase3);
        $this->assertStringContainsString('INR 1 – 5 Lakh', $phase3);
        $zip->close();
        @unlink($path);
    }

    public function test_state_admin_can_download_turnover_wise_xlsx(): void
    {
        if (! class_exists(ZipArchive::class)) {
            $this->markTestSkipped('ext-zip required for Excel export.');
        }

        $admin = User::factory()->create([
            'role' => 'state_admin',
            'is_active' => true,
        ]);

        $district = $this->createDistrict('almora-turnover', 'Almora');
        $this->seedOnboardedWithTurnover($district, 'RBI-TO-020', 'Download Person', 50000);

        $response = $this->actingAs($admin)
            ->get(route('admin.data-centre.export-onboarded-turnover-wise', [
                'district_id' => $district->id,
            ]));

        $response->assertOk();
        $response->assertHeader('content-type', 'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet');
        $this->assertStringContainsString(
            'onboarded-turnover-wise-',
            (string) $response->headers->get('content-disposition')
        );
        $this->assertStringContainsString('.xlsx', (string) $response->headers->get('content-disposition'));
    }

    private function seedOnboardedWithTurnover(District $district, string $applicationNo, string $name, float $turnover): void
    {
        $payload = [
            'gender' => 'female',
            'block' => 'Test Block',
            'guardian_name' => 'Guardian',
            'category' => 'Individual',
            'is_registered' => $turnover > 0 ? 'Yes' : 'No',
            'turnover_last_fy' => (string) (int) $turnover,
            'business_category' => 'Agri Allied',
            'form_stage' => 'Early',
        ];

        $cfaId = (int) DB::table('cfa_submissions')->insertGetId([
            'district_id' => $district->id,
            'application_no' => $applicationNo,
            'applicant_name' => $name,
            'phone' => '9876543210',
            'source' => 'phase3',
            'payload' => json_encode($payload),
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        $batchId = (int) DB::table('onboarding_batches')->insertGetId([
            'hub_id' => $district->hub_id,
            'district_id' => $district->id,
            'name' => $district->name.'-batch-to',
            'target_size' => 3,
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
