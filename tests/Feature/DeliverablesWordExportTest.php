<?php

namespace Tests\Feature;

use App\Services\Deliverables\Exports\DeliverablesProgramWordExport;
use App\Services\Deliverables\ProgramDeliverablesFilter;
use Tests\TestCase;
use ZipArchive;

class DeliverablesWordExportTest extends TestCase
{
    public function test_word_export_contains_combined_period_and_cumulative_table_without_level_column(): void
    {
        $path = storage_path('framework/testing/deliverables-word-export.docx');
        @mkdir(dirname($path), 0777, true);

        try {
            app(DeliverablesProgramWordExport::class)->save(
                $path,
                $this->rows(),
                $this->filter(),
                'Your district',
                'July 2026 (01 Jul - 31 Jul 2026)',
                'FY 2026-27',
                'till Jul 2026',
            );

            $this->assertFileExists($path);
            $this->assertGreaterThan(10000, filesize($path));

            $zip = new ZipArchive;
            $this->assertTrue($zip->open($path) === true);
            $documentXml = (string) $zip->getFromName('word/document.xml');
            $headerXml = (string) $zip->getFromName('word/header1.xml');
            $footerXml = (string) $zip->getFromName('word/footer1.xml');
            $zip->close();

            $this->assertStringContainsString('Target', $documentXml);
            $this->assertStringContainsString('till Jul 2026', $documentXml);
            $this->assertStringContainsString('1,820', $documentXml);
            $this->assertStringContainsString('17,010', $documentXml);
            $this->assertStringContainsString('126%', $documentXml);
            $this->assertStringNotContainsString('Spoke/ Hub/ State', $documentXml);
            $this->assertStringContainsString('Monthly progress report for the month of - July 2026', $headerXml);
            $this->assertStringContainsString('PAGE', $footerXml);
            $this->assertStringContainsString('NUMPAGES', $footerXml);
        } finally {
            if (is_file($path)) {
                @unlink($path);
            }
        }
    }

    public function test_dependency_free_word_fallback_keeps_the_full_report_layout(): void
    {
        $path = storage_path('framework/testing/deliverables-word-fallback.rtf');
        @mkdir(dirname($path), 0777, true);

        try {
            app(DeliverablesProgramWordExport::class)->saveCompatibilityDocument(
                $path,
                $this->rows(),
                $this->filter(),
                'Your district',
                'July 2026 (01 Jul - 31 Jul 2026)',
                'FY 2026-27',
                'till Jul 2026',
            );

            $rtf = (string) file_get_contents($path);
            $this->assertStringStartsWith('{\rtf1', $rtf);
            $this->assertStringContainsString('\landscape\paperw16838\paperh11906', $rtf);
            $this->assertStringContainsString('Monthly progress report for the month of - July 2026', $rtf);
            $this->assertStringContainsString('Achievement (%)\line (till Jul 2026)', $rtf);
            $this->assertStringContainsString('17,010', $rtf);
            $this->assertStringContainsString('126%', $rtf);
            $this->assertStringNotContainsString('Spoke/ Hub/ State', $rtf);
        } finally {
            if (is_file($path)) {
                @unlink($path);
            }
        }
    }

    private function filter(): ProgramDeliverablesFilter
    {
        return new ProgramDeliverablesFilter(
            fiscalYearId: 1,
            districtId: 2,
            month: 7,
            year: 2026,
            dateFrom: '2026-07-01',
            dateTo: '2026-07-31',
        );
    }

    /** @return list<array<string, mixed>> */
    private function rows(): array
    {
        return [
            [
                'row_type' => 'pillar',
                'serial' => '1',
                'name' => 'Outreach and Mobilisation',
            ],
            [
                'row_type' => 'indicator',
                'serial' => '1.1',
                'name' => 'Call for Application',
                'indicator_type' => 'Key Indicator',
                'level' => 'Spoke & Hub',
                'target' => 1820,
                'target_label' => null,
                'achievement' => 2413,
                'achievement_pct' => 133,
                'performance_tone' => 'good',
                'cumul_target' => 17010,
                'cumul_target_label' => null,
                'cumul_achievement' => 21407,
                'cumul_achievement_pct' => 126,
                'cumul_performance_tone' => 'good',
            ],
        ];
    }
}
