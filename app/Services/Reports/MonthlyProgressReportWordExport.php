<?php

namespace App\Services\Reports;

use Carbon\Carbon;
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
    private const ORANGE = 'C2410C';

    private const DEEP_ORANGE = '9A3412';

    private const PALE_ORANGE = 'FFEDD5';

    private const LIGHT_ORANGE = 'FFF7ED';

    private const INK = '1F2937';

    /**
     * @param  list<array<string, mixed>>  $rows
     * @param  list<array<string, mixed>>  $districtRows
     * @param  list<array<string, string>>  $photos
     */
    public function download(
        Carbon $month,
        string $fiscalYearLabel,
        array $rows,
        array $districtRows,
        array $photos,
    ): BinaryFileResponse {
        if (! class_exists(PhpWord::class) || ! class_exists(Word2007::class)) {
            return $this->downloadCompatibilityDocument($month, $fiscalYearLabel, $rows, $districtRows, $photos);
        }

        $path = tempnam(sys_get_temp_dir(), 'muy-mpr-');
        abort_if($path === false, 500, 'Could not prepare the monthly report.');

        $docxPath = $path.'.docx';
        @unlink($path);
        try {
            $this->save($docxPath, $month, $fiscalYearLabel, $rows, $districtRows, $photos);
        } catch (Throwable $exception) {
            @unlink($docxPath);
            report($exception);

            return $this->downloadCompatibilityDocument($month, $fiscalYearLabel, $rows, $districtRows, $photos);
        }

        return response()->download(
            $docxPath,
            'MUY-MPR-'.$month->format('F-Y').'.docx',
            ['Content-Type' => 'application/vnd.openxmlformats-officedocument.wordprocessingml.document'],
        )->deleteFileAfterSend(true);
    }

    /**
     * @param  list<array<string, mixed>>  $rows
     * @param  list<array<string, mixed>>  $districtRows
     * @param  list<array<string, string>>  $photos
     */
    public function save(
        string $path,
        Carbon $month,
        string $fiscalYearLabel,
        array $rows,
        array $districtRows = [],
        array $photos = [],
    ): void {
        (new Word2007($this->build($month, $fiscalYearLabel, $rows, $districtRows, $photos)))->save($path);
    }

    /**
     * @param  list<array<string, mixed>>  $rows
     * @param  list<array<string, mixed>>  $districtRows
     * @param  list<array<string, string>>  $photos
     */
    public function build(
        Carbon $month,
        string $fiscalYearLabel,
        array $rows,
        array $districtRows = [],
        array $photos = [],
    ): PhpWord {
        Settings::setOutputEscapingEnabled(true);
        $word = new PhpWord;
        $word->setDefaultFontName('Calibri');
        $word->setDefaultFontSize(10);
        $word->getSettings()->setZoom(100);
        $word->getDocInfo()
            ->setCreator('Mukhyamantri Udyamshala Yojana')
            ->setCompany('Mukhyamantri Udyamshala Yojana')
            ->setTitle('Monthly Progress Report - '.$month->format('F Y'))
            ->setSubject('Automatically generated MIS progress report');

        $this->registerStyles($word);
        $this->addCover($word, $month);
        $this->addContents($word, $month);
        $this->addProgressSummary($word, $month, $fiscalYearLabel, $rows);
        $this->addQuantitativeProgress($word, $month, $rows);
        $this->addDistrictProgress($word, $month, $districtRows);
        $this->addWorkstreamProgress($word, $month, $rows);
        $this->addFieldHighlights($word, $month, $photos);
        $this->addMethodology($word, $month, $fiscalYearLabel);

        return $word;
    }

    private function registerStyles(PhpWord $word): void
    {
        $word->addTitleStyle(1, ['name' => 'Calibri', 'size' => 18, 'bold' => true, 'color' => self::DEEP_ORANGE], ['spaceBefore' => 0, 'spaceAfter' => 160]);
        $word->addTitleStyle(2, ['name' => 'Calibri', 'size' => 14, 'bold' => true, 'color' => self::DEEP_ORANGE], ['spaceBefore' => 140, 'spaceAfter' => 90, 'keepNext' => true]);
        $word->addTitleStyle(3, ['name' => 'Calibri', 'size' => 11, 'bold' => true, 'color' => self::INK], ['spaceBefore' => 100, 'spaceAfter' => 60, 'keepNext' => true]);
        $word->addTableStyle('MprTable', [
            'width' => 100 * 50,
            'unit' => TblWidth::PERCENT,
            'layout' => TableStyle::LAYOUT_FIXED,
            'borderSize' => 4,
            'borderColor' => '9CA3AF',
            'cellMarginTop' => 60,
            'cellMarginRight' => 70,
            'cellMarginBottom' => 60,
            'cellMarginLeft' => 70,
        ]);
    }

    private function addCover(PhpWord $word, Carbon $month): void
    {
        $section = $word->addSection($this->portraitStyle(2.2, 2.2, 2.2, 2.2));
        $section->addText('', [], ['spaceAfter' => 900]);

        $logo = public_path('images/muy.jpg');
        if (is_file($logo)) {
            $section->addImage($logo, ['width' => 92, 'height' => 92, 'alignment' => Jc::CENTER]);
        }

        $section->addText('MUKHYAMANTRI UDYAMSHALA YOJANA', [
            'size' => 13,
            'bold' => true,
            'color' => '475569',
            'allCaps' => true,
        ], ['alignment' => Jc::CENTER, 'spaceBefore' => 260, 'spaceAfter' => 200]);

        $rule = $section->addTable(['width' => 100 * 50, 'unit' => TblWidth::PERCENT]);
        $rule->addRow(110);
        $rule->addCell(null, ['bgColor' => self::ORANGE]);

        $section->addText('Monthly Progress Report', [
            'size' => 30,
            'bold' => true,
            'color' => self::DEEP_ORANGE,
        ], ['alignment' => Jc::CENTER, 'spaceBefore' => 450, 'spaceAfter' => 180]);
        $section->addText('Mukhyamantri Udyamshala Yojana', [
            'size' => 18,
            'bold' => true,
            'color' => self::INK,
        ], ['alignment' => Jc::CENTER, 'spaceAfter' => 160]);
        $section->addText($month->format('F Y'), [
            'size' => 22,
            'bold' => true,
            'color' => self::ORANGE,
        ], ['alignment' => Jc::CENTER, 'spaceAfter' => 800]);

        $section->addText('Generated from the MUY Management Information System', [
            'size' => 10,
            'color' => '64748B',
        ], ['alignment' => Jc::CENTER, 'spaceAfter' => 80]);
        $section->addText('Private & Confidential', [
            'size' => 9,
            'italic' => true,
            'color' => '64748B',
        ], ['alignment' => Jc::CENTER]);
    }

    private function addContents(PhpWord $word, Carbon $month): void
    {
        $section = $this->contentSection($word, $month);
        $section->addTitle('Table of Contents', 1);
        $items = [
            ['1.', 'Progress in Mukhyamantri Udyamshala Yojana (MUY)'],
            ['2.', 'Quantitative Progress - Plan vs Achievements'],
            ['3.', 'District-wise Progress'],
            ['4.', 'Program Workstream Progress'],
            ['5.', 'Field Highlights'],
            ['6.', 'Report Basis and Data Notes'],
        ];
        $table = $section->addTable('MprTable');
        foreach ($items as [$number, $label]) {
            $table->addRow(480, ['cantSplit' => true]);
            $this->cell($table->addCell(900, ['bgColor' => self::PALE_ORANGE]), $number, true, self::DEEP_ORANGE, Jc::CENTER, 11);
            $this->cell($table->addCell(8200), $label, false, self::INK, Jc::LEFT, 11);
        }
    }

    /** @param list<array<string, mixed>> $rows */
    private function addProgressSummary(PhpWord $word, Carbon $month, string $fiscalYearLabel, array $rows): void
    {
        $section = $this->contentSection($word, $month);
        $section->addTitle('1. Progress in Mukhyamantri Udyamshala Yojana (MUY)', 1);

        $leafRows = array_values(array_filter($rows, fn (array $row): bool => ! in_array($row['row_type'] ?? '', ['pillar', 'subcategory'], true)));
        $cfa = $this->rowBySerial($leafRows, '1.1');
        $onboarding = $this->rowBySerial($leafRows, '2.1');
        $reported = array_values(array_filter($leafRows, fn (array $row): bool => (int) ($row['achievement'] ?? 0) > 0));
        usort($reported, fn (array $a, array $b): int => ((int) ($b['achievement'] ?? 0)) <=> ((int) ($a['achievement'] ?? 0)));

        $section->addText(
            'This Monthly Progress Report presents MIS-recorded progress for '.$month->format('F Y').'. '
            .'Targets and achievements follow the approved MUY deliverables matrix for '.$fiscalYearLabel.'.',
            ['size' => 10.5, 'color' => self::INK],
            ['alignment' => Jc::BOTH, 'spaceAfter' => 180, 'lineHeight' => 1.15],
        );

        $cards = $section->addTable(['width' => 100 * 50, 'unit' => TblWidth::PERCENT, 'layout' => TableStyle::LAYOUT_FIXED, 'cellMargin' => 120]);
        $cards->addRow(980, ['cantSplit' => true]);
        $this->metricCard($cards->addCell(3000, ['bgColor' => self::LIGHT_ORANGE]), 'CFA applications', $cfa['achievement'] ?? 0);
        $this->metricCard($cards->addCell(3000, ['bgColor' => 'F0FDF4']), 'Incubatees onboarded', $onboarding['achievement'] ?? 0);
        $this->metricCard($cards->addCell(3000, ['bgColor' => 'EFF6FF']), 'Indicators reporting progress', count($reported));

        $section->addTitle('Key monthly highlights', 2);
        if ($reported === []) {
            $section->addText('No approved achievement records were available for the selected month.', ['italic' => true, 'color' => '64748B']);
            return;
        }

        foreach (array_slice($reported, 0, 8) as $row) {
            $target = is_numeric($row['target'] ?? null) ? number_format((float) $row['target']) : (string) ($row['target_label'] ?? '—');
            $section->addListItem(
                (string) ($row['name'] ?? 'Indicator').': '.number_format((int) ($row['achievement'] ?? 0)).' achieved against a monthly target of '.$target.'.',
                0,
                ['size' => 10, 'color' => self::INK],
                'listStyle',
                ['spaceAfter' => 60],
            );
        }
    }

    /** @param list<array<string, mixed>> $rows */
    private function addQuantitativeProgress(PhpWord $word, Carbon $month, array $rows): void
    {
        $section = $this->contentSection($word, $month, true);
        $section->addTitle('2. Quantitative Progress - Plan vs Achievements', 1);
        $section->addText('Monthly and fiscal-year cumulative achievements are displayed side by side.', ['color' => '64748B'], ['spaceAfter' => 120]);

        $table = $section->addTable('MprTable');
        $headers = ['S.N.', 'Indicator', 'Type', 'Monthly target', 'Monthly achievement', 'Monthly %', 'Cumulative target', 'Cumulative achievement', 'Cumulative %'];
        $widths = [650, 4200, 1200, 1300, 1400, 950, 1400, 1500, 1000];
        $table->addRow(620, ['tblHeader' => true, 'cantSplit' => true]);
        foreach ($headers as $i => $header) {
            $this->cell($table->addCell($widths[$i], ['bgColor' => self::DEEP_ORANGE, 'valign' => VerticalJc::CENTER]), $header, true, 'FFFFFF', Jc::CENTER, 7.5);
        }

        foreach ($rows as $row) {
            $heading = in_array($row['row_type'] ?? '', ['pillar', 'subcategory'], true);
            $table->addRow(null, ['cantSplit' => true]);
            $values = $heading ? [
                (string) ($row['serial'] ?? ''), (string) ($row['name'] ?? ''), '', '', '', '', '', '', '',
            ] : [
                (string) ($row['serial'] ?? ''),
                (string) ($row['name'] ?? ''),
                (string) ($row['indicator_type'] ?? '—'),
                $this->value($row['target'] ?? null, $row['target_label'] ?? null),
                $this->value($row['achievement'] ?? null),
                $this->percent($row['achievement_pct'] ?? null),
                $this->value($row['cumul_target'] ?? null, $row['cumul_target_label'] ?? null),
                $this->value($row['cumul_achievement'] ?? null),
                $this->percent($row['cumul_achievement_pct'] ?? null),
            ];

            foreach ($values as $i => $value) {
                $fill = $heading ? self::PALE_ORANGE : ($i >= 6 ? 'FFFBEB' : 'FFFFFF');
                $this->cell($table->addCell($widths[$i], ['bgColor' => $fill, 'valign' => VerticalJc::CENTER]), $value, $heading || in_array($i, [4, 7], true), self::INK, $i === 1 ? Jc::LEFT : Jc::CENTER, 7.5);
            }
        }
    }

    /** @param list<array<string, mixed>> $districtRows */
    private function addDistrictProgress(PhpWord $word, Carbon $month, array $districtRows): void
    {
        $section = $this->contentSection($word, $month);
        $section->addTitle('3. District-wise Progress', 1);
        $section->addText('District-wise CFA and onboarding achievements recorded during the selected month.', ['color' => '64748B'], ['spaceAfter' => 120]);

        if ($districtRows === []) {
            $section->addText('District-level breakup was not available for the selected month.', ['italic' => true, 'color' => '64748B']);
            return;
        }

        $table = $section->addTable('MprTable');
        $headers = ['S.N.', 'District', 'CFA applications', 'Incubatees onboarded'];
        $widths = [900, 4300, 2100, 2100];
        $table->addRow(520, ['tblHeader' => true, 'cantSplit' => true]);
        foreach ($headers as $i => $header) {
            $this->cell($table->addCell($widths[$i], ['bgColor' => self::ORANGE]), $header, true, 'FFFFFF', Jc::CENTER, 9);
        }
        foreach ($districtRows as $i => $row) {
            $table->addRow(420, ['cantSplit' => true]);
            $fill = $i % 2 === 0 ? 'FFFFFF' : self::LIGHT_ORANGE;
            $this->cell($table->addCell($widths[0], ['bgColor' => $fill]), (string) ($i + 1), false, self::INK, Jc::CENTER, 9);
            $this->cell($table->addCell($widths[1], ['bgColor' => $fill]), (string) ($row['district'] ?? 'Unknown'), true, self::INK, Jc::LEFT, 9);
            $this->cell($table->addCell($widths[2], ['bgColor' => $fill]), number_format((int) ($row['cfa'] ?? 0)), false, self::INK, Jc::CENTER, 9);
            $this->cell($table->addCell($widths[3], ['bgColor' => $fill]), number_format((int) ($row['onboarding'] ?? 0)), false, self::INK, Jc::CENTER, 9);
        }
    }

    /** @param list<array<string, mixed>> $rows */
    private function addWorkstreamProgress(PhpWord $word, Carbon $month, array $rows): void
    {
        $section = $this->contentSection($word, $month);
        $section->addTitle('4. Program Workstream Progress', 1);
        $groups = [];
        $current = 'Program activities';
        foreach ($rows as $row) {
            if (($row['row_type'] ?? '') === 'pillar') {
                $current = (string) ($row['name'] ?? 'Program activities');
                $groups[$current] ??= [];
                continue;
            }
            if (! in_array($row['row_type'] ?? '', ['subcategory'], true)) {
                $groups[$current][] = $row;
            }
        }

        foreach ($groups as $name => $items) {
            $items = array_values(array_filter($items, fn (array $item): bool => (int) ($item['achievement'] ?? 0) > 0));
            $section->addTitle($name, 2);
            if ($items === []) {
                $section->addText('No approved achievement was recorded for this workstream during '.$month->format('F Y').'.', ['italic' => true, 'color' => '64748B'], ['spaceAfter' => 80]);
                continue;
            }
            foreach ($items as $item) {
                $section->addListItem((string) ($item['name'] ?? 'Activity').': '.number_format((int) ($item['achievement'] ?? 0)), 0, ['size' => 9.5, 'color' => self::INK], 'listStyle', ['spaceAfter' => 45]);
            }
        }
    }

    /** @param list<array<string, string>> $photos */
    private function addFieldHighlights(PhpWord $word, Carbon $month, array $photos): void
    {
        $section = $this->contentSection($word, $month);
        $section->addTitle('5. Field Highlights', 1);
        $section->addText('Approved field photographs available in the MIS Media Gallery for the reporting month.', ['color' => '64748B'], ['spaceAfter' => 140]);
        if ($photos === []) {
            $section->addText('No approved field photographs were available for this month.', ['italic' => true, 'color' => '64748B']);
            return;
        }

        foreach (array_chunk($photos, 2) as $pair) {
            $table = $section->addTable(['width' => 100 * 50, 'unit' => TblWidth::PERCENT, 'layout' => TableStyle::LAYOUT_FIXED, 'cellMargin' => 80]);
            $table->addRow(null, ['cantSplit' => true]);
            foreach ([0, 1] as $index) {
                $photo = $pair[$index] ?? null;
                $cell = $table->addCell(4600, ['valign' => VerticalJc::TOP]);
                if ($photo === null) {
                    continue;
                }
                try {
                    $cell->addImage($photo['path'], ['width' => 235, 'height' => 150, 'alignment' => Jc::CENTER]);
                } catch (\Throwable) {
                    $cell->addText('Photograph could not be embedded.', ['italic' => true, 'color' => '64748B']);
                }
                $cell->addText($photo['section'].' - '.$photo['title'], ['size' => 9, 'bold' => true, 'color' => self::INK], ['alignment' => Jc::CENTER, 'spaceBefore' => 55, 'spaceAfter' => 20]);
                $cell->addText(trim($photo['district'].' | '.$photo['date'], ' |'), ['size' => 8, 'color' => '64748B'], ['alignment' => Jc::CENTER]);
            }
            $section->addText('', [], ['spaceAfter' => 100]);
        }
    }

    private function addMethodology(PhpWord $word, Carbon $month, string $fiscalYearLabel): void
    {
        $section = $this->contentSection($word, $month);
        $section->addTitle('6. Report Basis and Data Notes', 1);
        $notes = [
            'Reporting period: '.$month->copy()->startOfMonth()->format('d M Y').' to '.$month->copy()->endOfMonth()->format('d M Y').'.',
            'Fiscal year: '.$fiscalYearLabel.'.',
            'Achievements are generated from approved MIS records and the existing MUY deliverables reporting logic.',
            'Cumulative values cover the start of the fiscal year through the end of the selected month.',
            'Field photographs are selected from Media Gallery records that satisfy the module visibility and approval rules.',
            'A zero indicates that no qualifying approved record was found under the applicable MIS logic; it does not replace independent program validation.',
        ];
        foreach ($notes as $note) {
            $section->addListItem($note, 0, ['size' => 10, 'color' => self::INK], 'listStyle', ['spaceAfter' => 80]);
        }
        $section->addText('Report generated on '.now()->timezone(config('app.timezone'))->format('d M Y, h:i A').'.', ['size' => 9, 'italic' => true, 'color' => '64748B'], ['spaceBefore' => 260]);
    }

    private function contentSection(PhpWord $word, Carbon $month, bool $landscape = false): Section
    {
        $section = $word->addSection($landscape ? $this->landscapeStyle() : $this->portraitStyle());
        $this->addHeaderFooter($section, $month);
        return $section;
    }

    private function addHeaderFooter(Section $section, Carbon $month): void
    {
        $header = $section->addHeader();
        $table = $header->addTable(['width' => 100 * 50, 'unit' => TblWidth::PERCENT, 'borderBottomSize' => 10, 'borderBottomColor' => self::ORANGE]);
        $table->addRow(390);
        $logoCell = $table->addCell(700, ['valign' => VerticalJc::CENTER]);
        $logo = public_path('images/muy.jpg');
        if (is_file($logo)) {
            $logoCell->addImage($logo, ['width' => 28, 'height' => 28]);
        }
        $titleCell = $table->addCell(8200, ['valign' => VerticalJc::CENTER]);
        $titleCell->addText('Monthly Progress Report - '.$month->format('F Y'), ['size' => 9, 'bold' => true, 'color' => self::INK], ['alignment' => Jc::RIGHT]);

        $footer = $section->addFooter();
        $footerTable = $footer->addTable(['width' => 100 * 50, 'unit' => TblWidth::PERCENT, 'borderTopSize' => 6, 'borderTopColor' => 'CBD5E1']);
        $footerTable->addRow(300);
        $this->cell($footerTable->addCell(1400), 'Private & Confidential', false, '64748B', Jc::LEFT, 7.5);
        $page = $footerTable->addCell(6100);
        $page->addPreserveText('Page {PAGE} of {NUMPAGES}', ['size' => 7.5, 'color' => '64748B'], ['alignment' => Jc::CENTER]);
        $this->cell($footerTable->addCell(1400), 'MUY', true, self::ORANGE, Jc::RIGHT, 7.5);
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
        $cell->addText($text, ['name' => 'Calibri', 'size' => $size, 'bold' => $bold, 'color' => $color], ['alignment' => $alignment, 'spaceBefore' => 0, 'spaceAfter' => 0]);
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

    /**
     * Word-compatible HTML fallback for shared hosts where Composer dependencies
     * have not been refreshed. Microsoft Word opens the result as an editable
     * document while retaining the QPR-style cover, tables and page breaks.
     *
     * @param  list<array<string, mixed>>  $rows
     * @param  list<array<string, mixed>>  $districtRows
     * @param  list<array<string, string>>  $photos
     */
    private function downloadCompatibilityDocument(
        Carbon $month,
        string $fiscalYearLabel,
        array $rows,
        array $districtRows,
        array $photos,
    ): BinaryFileResponse {
        $path = tempnam(sys_get_temp_dir(), 'muy-mpr-word-');
        abort_if($path === false, 500, 'Could not prepare the monthly report.');
        $docPath = $path.'.doc';
        @unlink($path);

        $written = file_put_contents($docPath, $this->compatibilityHtml($month, $fiscalYearLabel, $rows, $districtRows, $photos));
        abort_if($written === false, 500, 'Could not write the monthly report.');

        return response()->download(
            $docPath,
            'MUY-MPR-'.$month->format('F-Y').'.doc',
            ['Content-Type' => 'application/msword'],
        )->deleteFileAfterSend(true);
    }

    /**
     * @param  list<array<string, mixed>>  $rows
     * @param  list<array<string, mixed>>  $districtRows
     * @param  list<array<string, string>>  $photos
     */
    private function compatibilityHtml(Carbon $month, string $fiscalYearLabel, array $rows, array $districtRows, array $photos): string
    {
        $e = static fn (mixed $value): string => htmlspecialchars((string) $value, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8');
        $logo = '';
        $logoPath = public_path('images/muy.jpg');
        if (is_file($logoPath)) {
            $logo = 'data:image/jpeg;base64,'.base64_encode((string) file_get_contents($logoPath));
        }

        $leafRows = array_values(array_filter($rows, fn (array $row): bool => ! in_array($row['row_type'] ?? '', ['pillar', 'subcategory'], true)));
        $cfa = $this->rowBySerial($leafRows, '1.1');
        $onboarding = $this->rowBySerial($leafRows, '2.1');
        $reported = array_values(array_filter($leafRows, fn (array $row): bool => (int) ($row['achievement'] ?? 0) > 0));
        usort($reported, fn (array $a, array $b): int => ((int) ($b['achievement'] ?? 0)) <=> ((int) ($a['achievement'] ?? 0)));

        $html = '<!DOCTYPE html><html><head><meta charset="UTF-8"><title>MUY MPR</title><style>'
            .'@page{size:A4;margin:18mm 16mm 16mm}body{font-family:Calibri,Arial,sans-serif;color:#1f2937;font-size:10pt;line-height:1.35}'
            .'.page{page-break-before:always}.cover{height:245mm;text-align:center}.cover img,.logo{width:75px;height:75px}.cover .scheme{margin-top:18px;font-weight:700;color:#475569;letter-spacing:.4px}.rule{height:8px;background:#c2410c;margin:18px 0 32px}.cover h1{font-size:30pt;color:#9a3412;margin:0 0 14px}.cover h2{font-size:18pt;margin:0 0 12px}.cover .month{font-size:22pt;font-weight:700;color:#c2410c}.muted{color:#64748b}.conf{font-style:italic;color:#64748b}'
            .'h1{font-size:18pt;color:#9a3412;border-bottom:2px solid #ea580c;padding-bottom:7px;margin:0 0 14px}h2{font-size:14pt;color:#9a3412;margin:16px 0 7px}h3{font-size:11pt;margin:12px 0 5px}'
            .'table{width:100%;border-collapse:collapse;margin:10px 0 16px}th{background:#9a3412;color:#fff;font-weight:700;text-align:center;padding:7px;border:1px solid #9ca3af}td{padding:6px;border:1px solid #9ca3af;vertical-align:middle}.heading td{background:#ffedd5;font-weight:700}.alt td{background:#fff7ed}.num{text-align:center}.cards td{text-align:center;border:0;padding:18px}.cards b{display:block;font-size:21pt;color:#9a3412}.toc td:first-child{width:45px;text-align:center;font-weight:700;color:#9a3412;background:#ffedd5}.photo{width:48%;text-align:center;vertical-align:top;border:0}.photo img{max-width:285px;max-height:190px}.footer{margin-top:25px;padding-top:6px;border-top:1px solid #cbd5e1;font-size:8pt;color:#64748b;text-align:center}'
            .'</style></head><body>';

        $html .= '<section class="cover">'.($logo !== '' ? '<img src="'.$logo.'" alt="MUY">' : '').'<div class="scheme">MUKHYAMANTRI UDYAMSHALA YOJANA</div><div class="rule"></div><h1>Monthly Progress Report</h1><h2>Mukhyamantri Udyamshala Yojana</h2><div class="month">'.$e($month->format('F Y')).'</div><p class="muted" style="margin-top:75px">Generated from the MUY Management Information System</p><p class="conf">Private &amp; Confidential</p></section>';
        $html .= '<section class="page"><h1>Table of Contents</h1><table class="toc">';
        foreach (['Progress in Mukhyamantri Udyamshala Yojana (MUY)', 'Quantitative Progress - Plan vs Achievements', 'District-wise Progress', 'Program Workstream Progress', 'Field Highlights', 'Report Basis and Data Notes'] as $i => $title) {
            $html .= '<tr><td>'.($i + 1).'.</td><td>'.$e($title).'</td></tr>';
        }
        $html .= '</table></section>';

        $html .= '<section class="page"><h1>1. Progress in Mukhyamantri Udyamshala Yojana (MUY)</h1><p>This Monthly Progress Report presents MIS-recorded progress for '.$e($month->format('F Y')).'. Targets and achievements follow the approved MUY deliverables matrix for '.$e($fiscalYearLabel).'.</p><table class="cards"><tr><td style="background:#fff7ed"><b>'.number_format((int) ($cfa['achievement'] ?? 0)).'</b>CFA applications</td><td style="background:#f0fdf4"><b>'.number_format((int) ($onboarding['achievement'] ?? 0)).'</b>Incubatees onboarded</td><td style="background:#eff6ff"><b>'.number_format(count($reported)).'</b>Indicators reporting progress</td></tr></table><h2>Key monthly highlights</h2><ul>';
        foreach (array_slice($reported, 0, 8) as $row) {
            $html .= '<li>'.$e($row['name'] ?? 'Indicator').': '.number_format((int) ($row['achievement'] ?? 0)).' achieved.</li>';
        }
        $html .= '</ul></section>';

        $html .= '<section class="page"><h1>2. Quantitative Progress - Plan vs Achievements</h1><table><thead><tr><th>S.N.</th><th>Indicator</th><th>Type</th><th>Monthly target</th><th>Monthly achievement</th><th>Monthly %</th><th>Cumulative target</th><th>Cumulative achievement</th><th>Cumulative %</th></tr></thead><tbody>';
        foreach ($rows as $row) {
            $heading = in_array($row['row_type'] ?? '', ['pillar', 'subcategory'], true);
            $html .= '<tr'.($heading ? ' class="heading"' : '').'><td class="num">'.$e($row['serial'] ?? '').'</td><td>'.$e($row['name'] ?? '').'</td>';
            if ($heading) {
                $html .= '<td colspan="7"></td>';
            } else {
                $html .= '<td class="num">'.$e($row['indicator_type'] ?? '—').'</td><td class="num">'.$e($this->value($row['target'] ?? null, $row['target_label'] ?? null)).'</td><td class="num"><b>'.$e($this->value($row['achievement'] ?? null)).'</b></td><td class="num">'.$e($this->percent($row['achievement_pct'] ?? null)).'</td><td class="num">'.$e($this->value($row['cumul_target'] ?? null, $row['cumul_target_label'] ?? null)).'</td><td class="num"><b>'.$e($this->value($row['cumul_achievement'] ?? null)).'</b></td><td class="num">'.$e($this->percent($row['cumul_achievement_pct'] ?? null)).'</td>';
            }
            $html .= '</tr>';
        }
        $html .= '</tbody></table></section>';

        $html .= '<section class="page"><h1>3. District-wise Progress</h1><table><thead><tr><th>S.N.</th><th>District</th><th>CFA applications</th><th>Incubatees onboarded</th></tr></thead><tbody>';
        foreach ($districtRows as $i => $row) {
            $html .= '<tr'.($i % 2 ? ' class="alt"' : '').'><td class="num">'.($i + 1).'</td><td><b>'.$e($row['district'] ?? 'Unknown').'</b></td><td class="num">'.number_format((int) ($row['cfa'] ?? 0)).'</td><td class="num">'.number_format((int) ($row['onboarding'] ?? 0)).'</td></tr>';
        }
        $html .= '</tbody></table></section>';

        $html .= '<section class="page"><h1>4. Program Workstream Progress</h1>';
        $current = '';
        $listOpen = false;
        foreach ($rows as $row) {
            if (($row['row_type'] ?? '') === 'pillar') {
                if ($listOpen) {
                    $html .= '</ul>';
                }
                $current = (string) ($row['name'] ?? 'Program activities');
                $html .= '<h2>'.$e($current).'</h2><ul>';
                $listOpen = true;
            } elseif (! in_array($row['row_type'] ?? '', ['subcategory'], true) && (int) ($row['achievement'] ?? 0) > 0) {
                $html .= '<li>'.$e($row['name'] ?? 'Activity').': '.number_format((int) ($row['achievement'] ?? 0)).'</li>';
            }
        }
        if ($listOpen) {
            $html .= '</ul>';
        }
        $html .= '</section><section class="page"><h1>5. Field Highlights</h1>';
        if ($photos === []) {
            $html .= '<p class="conf">No approved field photographs were available for this month.</p>';
        } else {
            $html .= '<table><tr>';
            foreach ($photos as $i => $photo) {
                if ($i > 0 && $i % 2 === 0) {
                    $html .= '</tr><tr>';
                }
                $src = '';
                if (is_file($photo['path'] ?? '')) {
                    $mime = mime_content_type($photo['path']) ?: 'image/jpeg';
                    $src = 'data:'.$mime.';base64,'.base64_encode((string) file_get_contents($photo['path']));
                }
                $html .= '<td class="photo">'.($src !== '' ? '<img src="'.$src.'" alt="Field photograph">' : '').'<p><b>'.$e(($photo['section'] ?? '').' - '.($photo['title'] ?? '')).'</b><br><span class="muted">'.$e(($photo['district'] ?? '').' | '.($photo['date'] ?? '')).'</span></p></td>';
            }
            $html .= '</tr></table>';
        }
        $html .= '</section><section class="page"><h1>6. Report Basis and Data Notes</h1><ul><li>Reporting period: '.$e($month->copy()->startOfMonth()->format('d M Y')).' to '.$e($month->copy()->endOfMonth()->format('d M Y')).'.</li><li>Fiscal year: '.$e($fiscalYearLabel).'.</li><li>Achievements are generated from approved MIS records and the existing MUY deliverables reporting logic.</li><li>Cumulative values cover the start of the fiscal year through the end of the selected month.</li><li>Field photographs follow Media Gallery visibility and approval rules.</li></ul><p class="conf">Report generated on '.$e(now()->timezone(config('app.timezone'))->format('d M Y, h:i A')).'.</p></section>';
        $html .= '</body></html>';

        return $html;
    }
}
