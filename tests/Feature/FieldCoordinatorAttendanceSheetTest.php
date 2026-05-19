<?php

namespace Tests\Feature;

use App\Models\Designation;
use App\Models\District;
use App\Models\DistrictBlock;
use App\Models\FieldCoordinatorAttendanceReport;
use App\Models\GramPanchayat;
use App\Models\Hub;
use App\Models\User;
use App\Services\FieldVisitAttendanceSheetService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use PhpOffice\PhpSpreadsheet\Spreadsheet;
use PhpOffice\PhpSpreadsheet\Writer\Xlsx;
use Tests\TestCase;

class FieldCoordinatorAttendanceSheetTest extends TestCase
{
    use RefreshDatabase;

    public function test_field_coordinator_can_download_attendance_template(): void
    {
        [$staff, $block, $gp] = $this->createFieldCoordinatorContext();

        $this->actingAs($staff)
            ->get(route('staff.attendance.sheet-template', [
                'district_block_id' => $block->id,
                'gram_panchayat_id' => $gp->id,
                'participants_male_count' => 2,
                'participants_female_count' => 1,
            ]))
            ->assertOk()
            ->assertHeader('content-type', 'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet');
    }

    public function test_submit_without_attendance_sheet_is_allowed_when_participants_present(): void
    {
        Storage::fake();

        [$staff, $block, $gp] = $this->createFieldCoordinatorContext();

        $this->actingAs($staff)->post(route('staff.attendance.store'), [
            'visit_date' => '2026-05-19',
            'district_block_id' => $block->id,
            'gram_panchayat_id' => $gp->id,
            'area' => 'Test village',
            'participants_male_count' => 2,
            'participants_female_count' => 1,
            'visit_media' => [UploadedFile::fake()->create('visit.jpg', 100, 'image/jpeg')],
        ])->assertRedirect(route('staff.attendance.index'));

        $report = FieldCoordinatorAttendanceReport::query()->firstOrFail();
        $this->assertFalse($report->hasAttendanceSheet());
        $this->assertSame(3, $report->participants_total);
    }

    public function test_submit_validates_attendance_sheet_when_uploaded(): void
    {
        Storage::fake();

        [$staff, $block, $gp, $district] = $this->createFieldCoordinatorContext();

        $validSheet = $this->makeAttendanceSheetFile(2, 1, $district->name, $block->name, $gp->name);
        $invalidSheet = $this->makeAttendanceSheetFile(1, 0, $district->name, $block->name, $gp->name);

        $this->actingAs($staff)->post(route('staff.attendance.store'), [
            'visit_date' => '2026-05-19',
            'district_block_id' => $block->id,
            'gram_panchayat_id' => $gp->id,
            'area' => 'Test village',
            'participants_male_count' => 2,
            'participants_female_count' => 1,
            'visit_media' => [UploadedFile::fake()->create('visit.jpg', 100, 'image/jpeg')],
            'attendance_sheet' => $invalidSheet,
        ])->assertSessionHasErrors('attendance_sheet');

        $this->actingAs($staff)->post(route('staff.attendance.store'), [
            'visit_date' => '2026-05-19',
            'district_block_id' => $block->id,
            'gram_panchayat_id' => $gp->id,
            'area' => 'Test village',
            'participants_male_count' => 2,
            'participants_female_count' => 1,
            'visit_media' => [UploadedFile::fake()->create('visit.jpg', 100, 'image/jpeg')],
            'attendance_sheet' => $validSheet,
        ])->assertRedirect(route('staff.attendance.index'));

        $report = FieldCoordinatorAttendanceReport::query()->firstOrFail();
        $this->assertTrue($report->hasAttendanceSheet());
        $this->assertSame(3, $report->participants_total);
        Storage::disk('local')->assertExists((string) $report->attendance_sheet_path);
    }

    public function test_field_coordinator_can_upload_sheet_for_existing_submission(): void
    {
        Storage::fake();

        [$staff, $block, $gp, $district] = $this->createFieldCoordinatorContext();

        $report = FieldCoordinatorAttendanceReport::query()->create([
            'field_coordinator_user_id' => $staff->id,
            'field_coordinator_name' => $staff->name,
            'visit_date' => '2026-05-19',
            'entry_date' => '2026-05-19',
            'block' => $block->name,
            'district_block_id' => $block->id,
            'gram_panchayat_id' => $gp->id,
            'area' => 'Village',
            'district_id' => $district->id,
            'participants_male_count' => 1,
            'participants_female_count' => 1,
            'participants_total' => 2,
            'visit_media_json' => [[
                'path' => 'field-visit-media/test.jpg',
                'original_name' => 'test.jpg',
                'mime' => 'image/jpeg',
                'size_bytes' => 100,
                'type' => 'image',
            ]],
        ]);

        $sheet = $this->makeAttendanceSheetFile(1, 1, $district->name, $block->name, $gp->name);

        $this->actingAs($staff)
            ->post(route('staff.attendance.sheet.upload', $report), [
                'attendance_sheet' => $sheet,
            ])
            ->assertRedirect(route('staff.attendance.index'));

        $report->refresh();
        $this->assertTrue($report->hasAttendanceSheet());
    }

    /**
     * @return array{0: User, 1: DistrictBlock, 2: GramPanchayat, 3: District}
     */
    private function createFieldCoordinatorContext(): array
    {
        $district = $this->createDistrict('almora-sheet', 'Almora');
        $designation = Designation::query()->create(['name' => 'Field Coordinator', 'sort_order' => 1]);
        $staff = User::factory()->create([
            'role' => 'district_staff',
            'district_id' => $district->id,
            'designation_id' => $designation->id,
            'is_active' => true,
        ]);
        $block = DistrictBlock::query()->create([
            'district_id' => $district->id,
            'name' => 'Bhaisiya Chhana',
        ]);
        $gp = GramPanchayat::query()->create([
            'district_id' => $district->id,
            'district_block_id' => $block->id,
            'name' => 'Dungerlekh',
        ]);

        return [$staff, $block, $gp, $district];
    }

    private function makeAttendanceSheetFile(
        int $male,
        int $female,
        string $district,
        string $block,
        string $gramPanchayat,
    ): UploadedFile {
        $total = $male + $female;
        $spreadsheet = new Spreadsheet;
        $sheet = $spreadsheet->getActiveSheet();

        foreach (FieldVisitAttendanceSheetService::HEADERS as $index => $header) {
            $col = chr(ord('A') + $index);
            $sheet->setCellValue($col.'1', $header);
        }

        $rowIndex = 0;
        for ($i = 1; $i <= $total; $i++) {
            $row = $i + 1;
            $gender = $rowIndex < $male ? 'M' : 'F';
            $rowIndex++;
            $sheet->setCellValue('A'.$row, $i);
            $sheet->setCellValue('B'.$row, 'Participant '.$i);
            $sheet->setCellValue('C'.$row, $gender);
            $sheet->setCellValue('D'.$row, '9876543'.str_pad((string) $i, 3, '0', STR_PAD_LEFT));
            $sheet->setCellValue('E'.$row, $district);
            $sheet->setCellValue('F'.$row, $block);
            $sheet->setCellValue('G'.$row, $gramPanchayat);
        }

        $path = tempnam(sys_get_temp_dir(), 'att-sheet-');
        $writer = new Xlsx($spreadsheet);
        $writer->save($path);

        return new UploadedFile(
            $path,
            'attendance-sheet.xlsx',
            'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet',
            null,
            true,
        );
    }

    private function createDistrict(string $slug, string $name): District
    {
        $hub = Hub::query()->firstOrCreate(
            ['slug' => 'fc-sheet-hub'],
            ['name' => 'FC Sheet Hub', 'sort_order' => 1]
        );

        return District::query()->create([
            'hub_id' => $hub->id,
            'slug' => $slug,
            'name' => $name,
            'sort_order' => 1,
        ]);
    }
}
