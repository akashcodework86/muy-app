<?php

namespace Tests\Feature;

use App\Models\District;
use App\Models\FiscalYear;
use App\Models\Hub;
use App\Models\User;
use App\Services\Exports\DistrictFullProgressPackService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Tests\TestCase;
use ZipArchive;

class DistrictFullProgressExportTest extends TestCase
{
    use RefreshDatabase;

    public function test_pack_counts_native_phase3_cfa_and_onboarded_for_selected_district(): void
    {
        $fy = $this->seedPhase3Fy();
        $districtA = $this->createDistrict('progress-a', 'Progress A');
        $districtB = $this->createDistrict('progress-b', 'Progress B');

        $this->seedCfa($districtA, $fy, 'FP-A-1', 'Applicant A1', 'phase3', true);
        $this->seedCfa($districtA, $fy, 'FP-A-2', 'Applicant A2', 'phase3', false);
        $this->seedCfa($districtA, $fy, 'FP-A-LEG', 'Mirrored A', 'legacy_phase2', false);
        $this->seedCfa($districtB, $fy, 'FP-B-1', 'Applicant B1', 'phase3', false);

        $svc = app(DistrictFullProgressPackService::class);
        $packA = $svc->build((int) $districtA->id);
        $packB = $svc->build((int) $districtB->id);
        $packAll = $svc->build(null);

        $this->assertSame('Progress A', $packA['meta']['district']);
        $this->assertSame(2, $packA['pipeline'][0]['phase3']);
        $this->assertSame(1, $packA['pipeline'][1]['phase3']);

        $this->assertSame(1, $packB['pipeline'][0]['phase3']);
        $this->assertSame(0, $packB['pipeline'][1]['phase3']);

        $this->assertSame('All districts', $packAll['meta']['district']);
        $this->assertSame(3, $packAll['pipeline'][0]['phase3']);
        $this->assertSame(1, $packAll['pipeline'][1]['phase3']);
    }

    public function test_pack_writes_xlsx_with_expected_sheets(): void
    {
        if (! class_exists(ZipArchive::class)) {
            $this->markTestSkipped('ext-zip required');
        }

        $fy = $this->seedPhase3Fy();
        $district = $this->createDistrict('progress-xlsx', 'Xlsx Progress');
        $this->seedCfa($district, $fy, 'FP-X-1', 'Sheet Applicant', 'phase3', false);

        $svc = app(DistrictFullProgressPackService::class);
        $pack = $svc->build((int) $district->id);
        $path = storage_path('app/temp/test-full-progress-'.uniqid('', true).'.xlsx');
        $svc->writeToPath($pack, $path);
        $this->assertFileExists($path);

        $zip = new ZipArchive;
        $this->assertTrue($zip->open($path) === true);
        $workbook = $zip->getFromName('xl/workbook.xml');
        $this->assertIsString($workbook);
        $this->assertStringContainsString('Overall', $workbook);
        $this->assertStringContainsString('Official MIS FY 2026-27', $workbook);
        $this->assertStringContainsString('Crosscheck', $workbook);

        $overall = $zip->getFromName('xl/worksheets/sheet1.xml');
        $this->assertIsString($overall);
        $this->assertStringContainsString('Xlsx Progress', $overall);
        $this->assertStringContainsString('Phase 1 (2021-25)', $overall);
        $this->assertStringContainsString('Phase 2 (2025-26)', $overall);
        $this->assertStringContainsString('Phase 3 (2026-27)', $overall);
        $zip->close();
        @unlink($path);
    }

    public function test_state_admin_can_download_full_progress_excel(): void
    {
        if (! class_exists(ZipArchive::class)) {
            $this->markTestSkipped('ext-zip required');
        }

        $admin = User::factory()->create(['role' => 'state_admin', 'is_active' => true]);
        $fy = $this->seedPhase3Fy();
        $district = $this->createDistrict('progress-dl', 'Download Progress');
        $this->seedCfa($district, $fy, 'FP-DL-1', 'Download Applicant', 'phase3', false);

        $response = $this->actingAs($admin)->get(route('admin.data-centre.export-full-progress', [
            'district_id' => $district->id,
        ]));

        $response->assertOk();
        $response->assertHeader('content-type', 'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet');
        $this->assertStringContainsString('full-progress-', (string) $response->headers->get('content-disposition'));
    }

    private function seedPhase3Fy(): FiscalYear
    {
        return FiscalYear::query()->create([
            'code' => '2026-27',
            'name' => 'FY 2026-27',
            'starts_on' => '2026-04-02',
            'ends_on' => '2027-04-01',
            'is_active' => true,
        ]);
    }

    private function seedCfa(
        District $district,
        FiscalYear $fy,
        string $appNo,
        string $name,
        string $source,
        bool $onboarded,
    ): int {
        $cfaId = (int) DB::table('cfa_submissions')->insertGetId([
            'district_id' => $district->id,
            'fiscal_year_id' => $fy->id,
            'application_no' => $appNo,
            'applicant_name' => $name,
            'phone' => '9876543210',
            'source' => $source,
            'payload' => json_encode(['business_category' => 'Agri Allied']),
            'created_at' => '2026-05-01 10:00:00',
            'updated_at' => now(),
        ]);

        if (! $onboarded) {
            return $cfaId;
        }

        $batchId = (int) DB::table('onboarding_batches')->insertGetId([
            'hub_id' => $district->hub_id,
            'district_id' => $district->id,
            'name' => $district->name.'-fp-batch',
            'target_size' => 1,
            'status' => 'locked',
            'locked_at' => now(),
            'onboarding_date' => '2026-05-10',
            'created_at' => now(),
            'updated_at' => now(),
        ]);
        DB::table('onboarding_batch_cfa')->insert([
            'onboarding_batch_id' => $batchId,
            'cfa_submission_id' => $cfaId,
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        return $cfaId;
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
