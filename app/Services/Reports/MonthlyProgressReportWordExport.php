<?php

namespace App\Services\Reports;

use PhpOffice\PhpWord\Element\Cell;
use PhpOffice\PhpWord\Element\Section;
use PhpOffice\PhpWord\PhpWord;
use PhpOffice\PhpWord\Shared\Converter;
use PhpOffice\PhpWord\Settings;
use PhpOffice\PhpWord\SimpleType\Jc;
use PhpOffice\PhpWord\SimpleType\TblWidth;
use PhpOffice\PhpWord\SimpleType\VerticalJc;
use PhpOffice\PhpWord\Style\Section as SectionStyle;
use PhpOffice\PhpWord\Style\Table as TableStyle;
use PhpOffice\PhpWord\Writer\Word2007;
use Symfony\Component\HttpFoundation\BinaryFileResponse;
use Throwable;

class MonthlyProgressReportWordExport
{
    use ProgressReportWordSections;

    private const ORANGE = 'D04A02';

    private const DEEP_ORANGE = 'B53A00';

    private const PALE_ORANGE = 'FCE4D6';

    private const LIGHT_ORANGE = 'FFF6F0';

    private const INK = '111111';

    public function isAvailable(): bool
    {
        return class_exists(PhpWord::class) && class_exists(Word2007::class);
    }

    public function download(ProgressReportContext $context): BinaryFileResponse
    {
        if (! $this->isAvailable()) {
            return $this->downloadCompatibilityDocument($context);
        }

        $path = tempnam(sys_get_temp_dir(), 'muy-mpr-');
        abort_if($path === false, 500, 'Could not prepare the monthly report.');

        $docxPath = $path.'.docx';
        @unlink($path);
        try {
            $this->save($docxPath, $context);
        } catch (Throwable $exception) {
            @unlink($docxPath);
            report($exception);

            return $this->downloadCompatibilityDocument($context);
        }

        $filename = $context->filePrefix.'-'.str_replace([' ', '–'], '-', $context->periodLabel).'.docx';

        return response()->download(
            $docxPath,
            $filename,
            ['Content-Type' => 'application/vnd.openxmlformats-officedocument.wordprocessingml.document'],
        )->deleteFileAfterSend(true);
    }

    public function save(string $path, ProgressReportContext $context): void
    {
        (new Word2007($this->build($context)))->save($path);
    }

    public function build(ProgressReportContext $context): PhpWord
    {
        Settings::setOutputEscapingEnabled(true);
        $word = new PhpWord;
        $word->setDefaultFontName('Arial');
        $word->setDefaultFontSize(10);
        $word->getSettings()->setZoom(100);
        $word->getDocInfo()
            ->setCreator('Mukhyamantri Udyamshala Yojana')
            ->setCompany('Mukhyamantri Udyamshala Yojana')
            ->setTitle($context->reportKindLabel.' - '.$context->periodLabel)
            ->setSubject('Automatically generated MIS progress report');

        $this->registerStyles($word);
        $this->addCover($word, $context);
        $this->addExpandedContents($word, $context);
        $this->addProgressSummaryExpanded($word, $context, $context->rows);
        $this->addQuantitativeProgress($word, $context, $context->rows);
        $this->addMobilizationSection($word, $context);
        $this->addScreeningOnboardingSection($word, $context);
        $this->addTrainingSection($word, $context);
        $this->addPillarSections($word, $context, $context->rows);
        $this->addLineDepartmentSection($word, $context);
        $this->addFieldVisitsExpanded($word, $context);
        $this->addManualTemplateSections($word, $context);
        $this->addMediaCoverageSection($word, $context);
        $this->addTeamStructureSection($word, $context, $context->teamRoster);
        $this->addMethodology($word, $context);
        $this->addClosingPage($word, $context);

        return $word;
    }

    private function registerStyles(PhpWord $word): void
    {
        $word->addTitleStyle(1, ['name' => 'Arial', 'size' => 16, 'bold' => true, 'color' => self::DEEP_ORANGE], ['spaceBefore' => 0, 'spaceAfter' => 160, 'keepNext' => true]);
        $word->addTitleStyle(2, ['name' => 'Arial', 'size' => 12.5, 'bold' => true, 'color' => self::DEEP_ORANGE], ['spaceBefore' => 160, 'spaceAfter' => 80, 'keepNext' => true]);
        $word->addTitleStyle(3, ['name' => 'Arial', 'size' => 10.5, 'bold' => true, 'color' => self::INK], ['spaceBefore' => 100, 'spaceAfter' => 60, 'keepNext' => true]);
        $word->addTableStyle('MprTable', [
            'width' => 100 * 50,
            'unit' => TblWidth::PERCENT,
            'layout' => TableStyle::LAYOUT_FIXED,
            'borderSize' => 4,
            'borderColor' => 'F09A78',
            'cellMarginTop' => 60,
            'cellMarginRight' => 70,
            'cellMarginBottom' => 60,
            'cellMarginLeft' => 70,
        ]);
    }

    private function addCover(PhpWord $word, ProgressReportContext $context): void
    {
        $section = $word->addSection($this->portraitStyle(0.65, 0.65, 0.65, 0.65));
        $cover = $section->addTable([
            'width' => 100 * 50,
            'unit' => TblWidth::PERCENT,
            'layout' => TableStyle::LAYOUT_FIXED,
            'borderSize' => 1,
            'borderColor' => 'FBE3D6',
            'cellMarginTop' => 260,
            'cellMarginRight' => 500,
            'cellMarginBottom' => 260,
            'cellMarginLeft' => 500,
        ]);
        $cover->addRow(14800, ['exactHeight' => true, 'cantSplit' => true]);
        $cell = $cover->addCell(null, ['bgColor' => 'FBE3D6', 'valign' => VerticalJc::TOP, 'borderSize' => 1, 'borderColor' => 'FBE3D6']);

        $brand = $cell->addTextRun(['spaceAfter' => 1320]);
        $brand->addText('pwc', ['name' => 'Georgia', 'size' => 28, 'bold' => true, 'italic' => true, 'color' => '111111']);
        $brand->addText('  |  MUY', ['name' => 'Arial', 'size' => 10, 'bold' => true, 'color' => self::ORANGE]);

        $reportLabel = $context->isQuarterly() ? 'Quarterly Progress' : 'Monthly Progress';
        $cell->addText($reportLabel, [
            'name' => 'Arial',
            'size' => 34,
            'bold' => true,
            'color' => self::DEEP_ORANGE,
        ], ['alignment' => Jc::LEFT, 'spaceAfter' => 80]);
        $cell->addText('Report', [
            'name' => 'Arial',
            'size' => 34,
            'bold' => true,
            'color' => self::DEEP_ORANGE,
        ], ['alignment' => Jc::LEFT, 'spaceAfter' => 300]);
        $cell->addText('Mukhyamantri Udyamshala', [
            'name' => 'Arial',
            'size' => 19,
            'color' => self::DEEP_ORANGE,
        ], ['alignment' => Jc::LEFT, 'spaceAfter' => 40]);
        $cell->addText('Yojana', [
            'name' => 'Arial',
            'size' => 19,
            'color' => self::DEEP_ORANGE,
        ], ['alignment' => Jc::LEFT, 'spaceAfter' => 650]);
        $cell->addText($context->periodLabel, [
            'name' => 'Arial',
            'size' => 21,
            'color' => self::DEEP_ORANGE,
        ], ['alignment' => Jc::LEFT, 'spaceAfter' => 950]);

        $bars = $cell->addTable(['width' => 100 * 50, 'unit' => TblWidth::PERCENT, 'layout' => TableStyle::LAYOUT_FIXED, 'borderSize' => 1, 'borderColor' => 'FBE3D6']);
        $bars->addRow(500, ['exactHeight' => true]);
        $bars->addCell(3900, ['bgColor' => 'FF5A00', 'borderSize' => 1, 'borderColor' => 'FF5A00']);
        $bars->addCell(700, ['bgColor' => 'FBE3D6', 'borderSize' => 1, 'borderColor' => 'FBE3D6']);
        $bars->addCell(3900, ['bgColor' => 'FF5A00', 'borderSize' => 1, 'borderColor' => 'FF5A00']);
        $cell->addText('Private & Confidential', [
            'name' => 'Arial',
            'size' => 8,
            'bold' => true,
            'color' => self::INK,
        ], ['alignment' => Jc::CENTER, 'spaceBefore' => 1250, 'spaceAfter' => 0]);
    }

    /** @param list<array<string, mixed>> $rows */
    private function addQuantitativeProgress(PhpWord $word, ProgressReportContext $context, array $rows): void
    {
        $section = $this->contentSection($word, $context);
        $section->addTitle('2. Quantitative Progress - Plan vs Achievements', 1);
        $section->addText('The table below presents the approved target and MIS-recorded achievement for '.$context->periodLabel.'.', ['name' => 'Times New Roman', 'size' => 10.5, 'color' => self::INK], ['spaceAfter' => 120]);

        $table = $section->addTable('MprTable');
        $headers = ['S.No.', 'Indicator', 'Type of Indicator', 'Target', 'Achievement', 'Achievement (%)'];
        $widths = [700, 3650, 1550, 1150, 1350, 1450];
        $table->addRow(620, ['tblHeader' => true, 'cantSplit' => true]);
        foreach ($headers as $i => $header) {
            $this->cell($table->addCell($widths[$i], ['bgColor' => 'FF5A00', 'valign' => VerticalJc::CENTER]), $header, true, 'FFFFFF', Jc::CENTER, 8.5);
        }

        foreach ($rows as $row) {
            $heading = in_array($row['row_type'] ?? '', ['pillar', 'subcategory'], true);
            $table->addRow(null, ['cantSplit' => true]);
            $values = $heading ? [
                (string) ($row['serial'] ?? ''), (string) ($row['name'] ?? ''), '', '', '', '',
            ] : [
                (string) ($row['serial'] ?? ''),
                (string) ($row['name'] ?? ''),
                (string) ($row['indicator_type'] ?? '—'),
                $this->value($row['target'] ?? null, $row['target_label'] ?? null),
                $this->value($row['achievement'] ?? null),
                $this->percent($row['achievement_pct'] ?? null),
            ];

            foreach ($values as $i => $value) {
                $fill = $heading ? self::PALE_ORANGE : 'FFFFFF';
                $this->cell($table->addCell($widths[$i], ['bgColor' => $fill, 'valign' => VerticalJc::CENTER]), $value, $heading || $i === 4, self::INK, $i === 1 ? Jc::LEFT : Jc::CENTER, 8.5);
            }
        }

        $section->addText(
            'The above-mentioned progress is based on the IT/MIS system developed under the Mukhyamantri Udyamshala Yojana and the applicable maker-checker approval workflow.',
            ['name' => 'Times New Roman', 'size' => 9.5, 'color' => self::INK],
            ['alignment' => Jc::BOTH, 'spaceBefore' => 120, 'spaceAfter' => 0],
        );
    }

    /** @return array<string, int|string> */
    private function photoImageStyle(string $path): array
    {
        $maxWidth = 230;
        $maxHeight = 150;
        $width = $maxWidth;
        $height = $maxHeight;
        $dimensions = @getimagesize($path);
        if (is_array($dimensions) && ($dimensions[0] ?? 0) > 0 && ($dimensions[1] ?? 0) > 0) {
            $scale = min($maxWidth / $dimensions[0], $maxHeight / $dimensions[1]);
            $width = max(1, (int) round($dimensions[0] * $scale));
            $height = max(1, (int) round($dimensions[1] * $scale));
        }

        return ['width' => $width, 'height' => $height, 'alignment' => Jc::CENTER];
    }

    private function addMethodology(PhpWord $word, ProgressReportContext $context): void
    {
        $section = $this->contentSection($word, $context);
        $section->addTitle('20. Report Basis and Data Notes', 1);
        $notes = [
            'Reporting period: '.$context->periodFrom->format('d M Y').' to '.$context->periodTo->format('d M Y').'.',
            'Fiscal year: '.$context->fiscalYearLabel.'.',
            'Achievements are generated from approved MIS records and the existing MUY deliverables reporting logic.',
            'Cumulative values cover the start of the fiscal year through the end of the selected reporting period.',
            'Field photographs are selected from Media Gallery records that satisfy the module visibility and approval rules.',
            'Yellow highlighted [TEAM: …] blocks indicate sections where narrative content must be added manually before circulation.',
            'A zero indicates that no qualifying approved record was found under the applicable MIS logic; it does not replace independent program validation.',
        ];
        foreach ($notes as $note) {
            $section->addListItem($note, 0, ['size' => 10, 'color' => self::INK], 'listStyle', ['spaceAfter' => 80]);
        }
        $section->addText('Report generated on '.now()->timezone(config('app.timezone'))->format('d M Y, h:i A').'.', ['size' => 9, 'italic' => true, 'color' => '64748B'], ['spaceBefore' => 260]);
    }

    private function addClosingPage(PhpWord $word, ProgressReportContext $context): void
    {
        $section = $word->addSection($this->portraitStyle(0.65, 0.65, 0.65, 0.65));
        $table = $section->addTable([
            'width' => 100 * 50,
            'unit' => TblWidth::PERCENT,
            'layout' => TableStyle::LAYOUT_FIXED,
            'borderSize' => 1,
            'borderColor' => 'D94F70',
            'cellMarginTop' => 300,
            'cellMarginRight' => 450,
            'cellMarginBottom' => 300,
            'cellMarginLeft' => 450,
        ]);
        $table->addRow(14800, ['exactHeight' => true, 'cantSplit' => true]);
        $cell = $table->addCell(null, ['bgColor' => 'D94F70', 'valign' => VerticalJc::CENTER, 'borderSize' => 1, 'borderColor' => 'D94F70']);
        $cell->addText('Thank You', ['name' => 'Georgia', 'size' => 43, 'bold' => true, 'color' => 'FFFFFF'], ['alignment' => Jc::LEFT, 'spaceAfter' => 2100]);
        $notes = [
            'This report is generated from the MUY MIS for '.$context->periodLabel.' and is intended for programme review.',
            'The document should be reviewed by the State Project Management Unit before external circulation.',
            'The report may contain forward-looking actions and operational observations that remain subject to validation.',
        ];
        foreach ($notes as $note) {
            $cell->addListItem($note, 0, ['name' => 'Arial', 'size' => 8.5, 'color' => 'FFFFFF'], 'listStyle', ['spaceAfter' => 75]);
        }
        $section->addHeader()->addText('');
        $section->addFooter()->addText('');
    }

    private function contentSection(PhpWord $word, ProgressReportContext $context, bool $landscape = false): Section
    {
        $section = $word->addSection($landscape ? $this->landscapeStyle() : $this->portraitStyle());
        $this->addHeaderFooter($section, $context);

        return $section;
    }

    private function addHeaderFooter(Section $section, ProgressReportContext $context): void
    {
        $header = $section->addHeader();
        $table = $header->addTable(['width' => 100 * 50, 'unit' => TblWidth::PERCENT, 'borderSize' => 1, 'borderColor' => 'FFFFFF']);
        $table->addRow(300);
        $brand = $table->addCell(1200, ['valign' => VerticalJc::CENTER, 'borderSize' => 1, 'borderColor' => 'FFFFFF']);
        $brand->addText('pwc  |  MUY', ['name' => 'Arial', 'size' => 7.5, 'bold' => true, 'color' => self::ORANGE]);
        $titleCell = $table->addCell(8000, ['valign' => VerticalJc::CENTER, 'borderSize' => 1, 'borderColor' => 'FFFFFF']);
        $titleCell->addText($context->reportKindLabel.' - '.$context->periodLabel, ['name' => 'Arial', 'size' => 8, 'bold' => true, 'color' => self::INK], ['alignment' => Jc::RIGHT]);

        $footer = $section->addFooter();
        $footerTable = $footer->addTable(['width' => 100 * 50, 'unit' => TblWidth::PERCENT, 'borderSize' => 1, 'borderColor' => 'FFFFFF']);
        $footerTable->addRow(300);
        $page = $footerTable->addCell(900, ['borderSize' => 1, 'borderColor' => 'FFFFFF']);
        $page->addPreserveText('{PAGE}', ['name' => 'Arial', 'size' => 7.5, 'color' => self::INK], ['alignment' => Jc::LEFT]);
        $this->cell($footerTable->addCell(5500, ['borderSize' => 1, 'borderColor' => 'FFFFFF']), $context->reportKindLabel.' - '.$context->periodLabel, true, self::INK, Jc::CENTER, 7.5);
        $this->cell($footerTable->addCell(2800, ['borderSize' => 1, 'borderColor' => 'FFFFFF']), 'Private & Confidential', false, self::INK, Jc::RIGHT, 7.5);
    }

    /** @return array<string, int|string> */
    private function portraitStyle(float $top = 1.45, float $right = 1.55, float $bottom = 1.4, float $left = 1.55): array
    {
        return [
            'paperSize' => 'A4',
            'marginTop' => (int) round(Converter::cmToTwip($top)),
            'marginRight' => (int) round(Converter::cmToTwip($right)),
            'marginBottom' => (int) round(Converter::cmToTwip($bottom)),
            'marginLeft' => (int) round(Converter::cmToTwip($left)),
            'headerHeight' => (int) round(Converter::cmToTwip(0.45)),
            'footerHeight' => (int) round(Converter::cmToTwip(0.45)),
        ];
    }

    /** @return array<string, int|string> */
    private function landscapeStyle(): array
    {
        return [
            ...$this->portraitStyle(1.35, 0.75, 1.25, 0.75),
            'orientation' => SectionStyle::ORIENTATION_LANDSCAPE,
            'pageSizeW' => 16838,
            'pageSizeH' => 11906,
        ];
    }

    private function cell(Cell $cell, string $text, bool $bold = false, string $color = self::INK, string $alignment = Jc::LEFT, float $size = 9): void
    {
        $cell->addText($text, ['name' => 'Arial', 'size' => $size, 'bold' => $bold, 'color' => $color], ['alignment' => $alignment, 'spaceBefore' => 0, 'spaceAfter' => 0]);
    }

    private function metricCard(Cell $cell, string $label, mixed $value): void
    {
        $cell->addText(number_format((int) $value), ['size' => 20, 'bold' => true, 'color' => self::DEEP_ORANGE], ['alignment' => Jc::CENTER, 'spaceAfter' => 40]);
        $cell->addText($label, ['size' => 9, 'bold' => true, 'color' => self::INK], ['alignment' => Jc::CENTER]);
    }

    /** @param list<array<string, mixed>> $rows */
    private function rowBySerial(array $rows, string $serial): array
    {
        foreach ($rows as $row) {
            if ((string) ($row['serial'] ?? '') === $serial) {
                return $row;
            }
        }
        return [];
    }

    private function value(mixed $value, mixed $label = null): string
    {
        if (is_string($label) && trim($label) !== '') {
            return $label;
        }
        return is_numeric($value) ? number_format((float) $value, 0) : '—';
    }

    private function percent(mixed $value): string
    {
        return is_numeric($value) ? number_format((float) $value, 0).'%' : '—';
    }

    private function downloadCompatibilityDocument(ProgressReportContext $context): BinaryFileResponse
    {
        $path = tempnam(sys_get_temp_dir(), 'muy-mpr-word-');
        abort_if($path === false, 500, 'Could not prepare the monthly report.');
        $docPath = $path.'.doc';
        @unlink($path);

        $written = file_put_contents($docPath, $this->compatibilityHtml($context));
        abort_if($written === false, 500, 'Could not write the monthly report.');

        $filename = $context->filePrefix.'-'.str_replace([' ', '–'], '-', $context->periodLabel).'.doc';

        return response()->download(
            $docPath,
            $filename,
            ['Content-Type' => 'application/msword'],
        )->deleteFileAfterSend(true);
    }

    private function compatibilityHtml(ProgressReportContext $context): string
    {
        $e = static fn (mixed $value): string => htmlspecialchars((string) $value, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8');
        $yellow = static fn (string $key): string => '<p style="background:#ffff00;padding:8px;border:1px solid #facc15">'
            .$e(ProgressReportSectionCatalog::yellowPrompts()[$key] ?? '[TEAM: Add content.]').'</p>';
        $rows = $context->rows;
        $leafRows = array_values(array_filter($rows, fn (array $row): bool => ! in_array($row['row_type'] ?? '', ['pillar', 'subcategory'], true)));
        $cfa = $this->rowBySerial($leafRows, '1.1');
        $onboarding = $this->rowBySerial($leafRows, '2.1');

        $html = '<!DOCTYPE html><html><head><meta charset="UTF-8"><title>MUY Report</title><style>'
            .'body{font-family:Calibri,Arial,sans-serif;color:#1f2937;font-size:10pt;line-height:1.35}'
            .'.page{page-break-before:always}h1{font-size:18pt;color:#9a3412;border-bottom:2px solid #ea580c}'
            .'table{width:100%;border-collapse:collapse;margin:10px 0 16px}th{background:#9a3412;color:#fff;padding:7px;border:1px solid #9ca3af}td{padding:6px;border:1px solid #9ca3af}'
            .'</style></head><body>';
        $html .= '<section><h1>'.$e($context->reportKindLabel).'</h1><h2>'.$e($context->periodLabel).'</h2></section>';
        $html .= '<section class="page"><h1>Contents</h1><ul>';
        foreach (ProgressReportSectionCatalog::tableOfContents() as $item) {
            $html .= '<li>'.$e($item['number'].' '.$item['title']).'</li>';
        }
        $html .= '</ul></section>';
        $html .= '<section class="page"><h1>1. Progress Summary</h1>'.$yellow('executive_summary')
            .'<p>CFA: '.number_format((int) ($cfa['achievement'] ?? 0)).'; Onboarded: '.number_format((int) ($onboarding['achievement'] ?? 0)).'.</p></section>';
        $html .= '<section class="page"><h1>2. Quantitative Progress</h1><table><thead><tr><th>S.No.</th><th>Indicator</th><th>Target</th><th>Achievement</th></tr></thead><tbody>';
        foreach ($leafRows as $row) {
            $html .= '<tr><td>'.$e($row['serial'] ?? '').'</td><td>'.$e($row['name'] ?? '').'</td><td>'.$e($this->value($row['target'] ?? null, $row['target_label'] ?? null)).'</td><td>'.$e($this->value($row['achievement'] ?? null)).'</td></tr>';
        }
        $html .= '</tbody></table></section>';
        foreach (['mobilization_intro', 'onboarding', 'training', 'bootcamps', 'additional', 'it_mis', 'risk', 'media', 'team'] as $promptKey) {
            $html .= '<section class="page"><h1>Manual section</h1>'.$yellow($promptKey).'</section>';
        }
        $html .= '<section class="page"><h1>20. Report Basis and Data Notes</h1><p>Reporting period: '
            .$e($context->periodFrom->format('d M Y')).' to '.$e($context->periodTo->format('d M Y')).'.</p></section>';
        $html .= '</body></html>';

        return $html;
    }
}
