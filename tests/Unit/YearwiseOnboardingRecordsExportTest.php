<?php

namespace Tests\Unit;

use App\Services\DataCentre\YearwiseIndicatorsPlusRecordsService;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

class YearwiseOnboardingRecordsExportTest extends TestCase
{
    #[Test]
    public function export_row_writes_dont_have_for_missing_gst_fssai_and_market(): void
    {
        $service = app(YearwiseIndicatorsPlusRecordsService::class);
        $headers = $service->onboardingExportHeaders();
        $row = $service->onboardingExportRow([
            'year' => '2024-25',
            'source_label' => 'Verified',
            'application_no' => 'RBI123',
            'applicant_name' => 'Test Applicant',
            'district' => 'Almora',
            'sector' => '',
            'product' => '',
            'gst_number' => '',
            'fssai_number' => '',
            'market_links' => [],
        ]);

        $this->assertSame(count($headers), count($row));
        $this->assertContains('GSTIN', $headers);
        $this->assertContains('FSSAI Licence No.', $headers);
        $this->assertContains('Market linkage partners', $headers);

        $byHeader = array_combine($headers, $row);
        $this->assertSame("don't have", $byHeader['GSTIN']);
        $this->assertSame("don't have", $byHeader['FSSAI Licence No.']);
        $this->assertSame("don't have", $byHeader['Market linkage partners']);
        $this->assertSame("don't have", $byHeader['Market linkage links']);
        $this->assertSame("don't have", $byHeader['Sector']);
        $this->assertSame("don't have", $byHeader['Product']);
    }

    #[Test]
    public function export_row_keeps_register_numbers_and_partner_links(): void
    {
        $service = app(YearwiseIndicatorsPlusRecordsService::class);
        $headers = $service->onboardingExportHeaders();
        $row = $service->onboardingExportRow([
            'year' => '2025-26',
            'applicant_name' => 'With Services',
            'sector' => 'Food Processing',
            'product' => 'Pickle',
            'gst_number' => '05ABCDE1234F1Z5',
            'fssai_number' => '12224000000123',
            'market_links' => [
                ['label' => 'Amazon', 'url' => 'https://amazon.in/shop'],
                ['label' => 'Local haat', 'url' => ''],
            ],
        ]);

        $byHeader = array_combine($headers, $row);
        $this->assertSame('Food Processing', $byHeader['Sector']);
        $this->assertSame('Pickle', $byHeader['Product']);
        $this->assertSame('05ABCDE1234F1Z5', $byHeader['GSTIN']);
        $this->assertSame('12224000000123', $byHeader['FSSAI Licence No.']);
        $this->assertSame('Amazon | Local haat', $byHeader['Market linkage partners']);
        $this->assertSame('https://amazon.in/shop', $byHeader['Market linkage links']);
    }

    #[Test]
    public function streaming_xlsx_contains_summary_combined_and_year_sheets(): void
    {
        if (! class_exists(\ZipArchive::class)) {
            $this->markTestSkipped('ZipArchive is required for Excel export.');
        }

        $service = app(YearwiseIndicatorsPlusRecordsService::class);
        $export = new \App\Services\Exports\YearwiseOnboardingRecordsExcelExport;
        $path = sys_get_temp_dir().'/yi-onb-test-'.uniqid('', true).'.xlsx';
        try {
            $export->writeToPath($path, [
                [
                    'year' => '2024-25',
                    'applicant_name' => 'Asha',
                    'application_no' => 'RBI1',
                    'sector' => 'Homestay',
                    'product' => 'Rooms',
                    'gst_number' => YearwiseIndicatorsPlusRecordsService::MISSING_LABEL,
                    'fssai_number' => YearwiseIndicatorsPlusRecordsService::MISSING_LABEL,
                    'market_partners' => YearwiseIndicatorsPlusRecordsService::MISSING_LABEL,
                    'market_link_urls' => YearwiseIndicatorsPlusRecordsService::MISSING_LABEL,
                ],
                [
                    'year' => '2025-26',
                    'applicant_name' => 'Bina',
                    'application_no' => 'RBI2',
                    'sector' => 'Food Processing',
                    'product' => 'Pickle',
                    'gst_number' => '05ABCDE1234F1Z5',
                    'fssai_number' => '12224000000123',
                    'market_partners' => 'Amazon',
                    'market_link_urls' => 'https://amazon.in/shop',
                ],
            ], [
                'metric' => 'onboarding',
                'scope' => 'grand',
                'year' => null,
                'phase' => null,
                'district' => null,
                'source' => 'all',
                'q' => '',
            ], $service);

            $this->assertFileExists($path);
            $zip = new \ZipArchive;
            $this->assertTrue($zip->open($path) === true);
            $workbook = (string) $zip->getFromName('xl/workbook.xml');
            $zip->close();
            $this->assertStringContainsString('Summary', $workbook);
            $this->assertStringContainsString('Combined', $workbook);
            $this->assertStringContainsString('2024-25', $workbook);
            $this->assertStringContainsString('2025-26', $workbook);
        } finally {
            @unlink($path);
        }
    }
}
