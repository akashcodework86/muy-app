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
        $this->assertArrayHasKey('Stage', $byHeader);
        $this->assertArrayHasKey('Onboard Status', $byHeader);
    }

    #[Test]
    public function market_linkage_export_row_includes_turnover_stage_and_onboard(): void
    {
        $service = app(YearwiseIndicatorsPlusRecordsService::class);
        $headers = $service->marketLinkageExportHeaders();
        $row = $service->marketLinkageExportRow([
            'year' => '2025-26',
            'source_label' => 'Verified',
            'application_no' => 'RBI-ML-1',
            'applicant_name' => 'Meera',
            'guardian_name' => 'Ram',
            'gender' => 'Female',
            'phone' => '9876543210',
            'district' => 'Almora',
            'block' => 'Hawalbagh',
            'sector' => 'Food Processing',
            'product' => 'Pickle',
            'enterprise_name' => 'Meera Foods',
            'turnover_last_fy' => '250000',
            'form_stage' => 'Early',
            'onboard_status' => 'Onboarded',
            'service_number' => 'Amazon',
            'market_links' => [
                ['label' => 'Amazon', 'url' => 'https://amazon.in/shop'],
            ],
        ]);

        $this->assertSame(count($headers), count($row));
        $byHeader = array_combine($headers, $row);
        $this->assertSame('250000', $byHeader['Turnover last FY']);
        $this->assertSame('Early', $byHeader['Stage']);
        $this->assertSame('Onboarded', $byHeader['Onboard Status']);
        $this->assertSame('Meera', $byHeader['Applicant Name']);
        $this->assertSame('Ram', $byHeader['Guardian Name']);
        $this->assertSame('Female', $byHeader['Gender']);
        $this->assertSame('Meera Foods', $byHeader['Enterprise Name']);
        $this->assertSame('Amazon', $byHeader['Market linkage partners']);
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

    #[Test]
    public function market_linkage_streaming_xlsx_contains_summary_and_combined(): void
    {
        if (! class_exists(\ZipArchive::class)) {
            $this->markTestSkipped('ZipArchive is required for Excel export.');
        }

        $service = app(YearwiseIndicatorsPlusRecordsService::class);
        $export = new \App\Services\Exports\YearwiseOnboardingRecordsExcelExport;
        $path = sys_get_temp_dir().'/yi-ml-test-'.uniqid('', true).'.xlsx';
        try {
            $export->writeToPath($path, [
                [
                    'year' => '2025-26',
                    'applicant_name' => 'Meera',
                    'application_no' => 'RBI-ML-1',
                    'turnover_last_fy' => '250000',
                    'form_stage' => 'Early',
                    'onboard_status' => 'Onboarded',
                    'market_partners' => 'Amazon',
                    'market_link_urls' => 'https://amazon.in/shop',
                ],
            ], [
                'metric' => 'market_linkage',
                'scope' => 'phase',
                'year' => null,
                'phase' => 'phase2',
                'district' => null,
                'source' => 'all',
                'onboard' => 'onboarded',
                'q' => '',
            ], $service);

            $this->assertFileExists($path);
            $zip = new \ZipArchive;
            $this->assertTrue($zip->open($path) === true);
            $workbook = (string) $zip->getFromName('xl/workbook.xml');
            $combined = (string) $zip->getFromName('xl/worksheets/sheet2.xml');
            $zip->close();
            $this->assertStringContainsString('Summary', $workbook);
            $this->assertStringContainsString('Combined', $workbook);
            $this->assertStringContainsString('2025-26', $workbook);
            $this->assertStringContainsString('2026-27', $workbook);
            $this->assertStringNotContainsString('2023-24', $workbook);
            $this->assertStringContainsString('Onboard Status', $combined);
            $this->assertStringContainsString('Turnover last FY', $combined);
            $this->assertStringContainsString('Meera', $combined);
        } finally {
            @unlink($path);
        }
    }
}
