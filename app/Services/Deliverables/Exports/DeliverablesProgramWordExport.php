<?php

namespace App\Services\Deliverables\Exports;

use App\Services\Deliverables\ProgramDeliverablesFilter;
use Carbon\Carbon;
use PhpOffice\PhpWord\Element\Cell;
use PhpOffice\PhpWord\Element\Section;
use PhpOffice\PhpWord\PhpWord;
use PhpOffice\PhpWord\Shared\Converter;
use PhpOffice\PhpWord\SimpleType\Jc;
use PhpOffice\PhpWord\SimpleType\TblWidth;
use PhpOffice\PhpWord\SimpleType\VerticalJc;
use PhpOffice\PhpWord\Style\Language;
use PhpOffice\PhpWord\Style\Section as SectionStyle;
use PhpOffice\PhpWord\Style\Table as TableStyle;
use PhpOffice\PhpWord\Writer\Word2007;
use Symfony\Component\HttpFoundation\BinaryFileResponse;

class DeliverablesProgramWordExport
{
    private const HEADER_FILL = '9A3412';

    private const CUMULATIVE_HEADER_FILL = 'B45309';

    private const SECTION_FILL = 'FFEDD5';

    private const CUMULATIVE_CELL_FILL = 'FFFBEB';

    private const BORDER_COLOR = '64748B';

    /**
     * @param  list<array<string, mixed>>  $rows
     */
    public function download(
        array $rows,
        ProgramDeliverablesFilter $filter,
        string $scopeLabel,
        string $periodLabel,
        string $fiscalYearLabel,
        ?string $cumulativeThroughLabel,
    ): BinaryFileResponse {
        $path = tempnam(sys_get_temp_dir(), 'muy-deliverables-word-');
        abort_if($path === false, 500, 'Could not prepare Word export.');

        $docxPath = $path.'.docx';
        @unlink($path);

        $this->save(
            $docxPath,
            $rows,
            $filter,
            $scopeLabel,
            $periodLabel,
            $fiscalYearLabel,
            $cumulativeThroughLabel,
        );

        return response()->download(
            $docxPath,
            $this->buildFileName($fiscalYearLabel, $filter),
            ['Content-Type' => 'application/vnd.openxmlformats-officedocument.wordprocessingml.document'],
        )->deleteFileAfterSend(true);
    }

    /**
     * @param  list<array<string, mixed>>  $rows
     */
    public function save(
        string $path,
        array $rows,
        ProgramDeliverablesFilter $filter,
        string $scopeLabel,
        string $periodLabel,
        string $fiscalYearLabel,
        ?string $cumulativeThroughLabel,
    ): void {
        $phpWord = $this->build(
            $rows,
            $filter,
            $scopeLabel,
            $periodLabel,
            $fiscalYearLabel,
            $cumulativeThroughLabel,
        );

        (new Word2007($phpWord))->save($path);
    }

    /**
     * Compact-reference-guide preset with a branded A4-landscape report override.
     *
     * @param  list<array<string, mixed>>  $rows
     */
    public function build(
        array $rows,
        ProgramDeliverablesFilter $filter,
        string $scopeLabel,
        string $periodLabel,
        string $fiscalYearLabel,
        ?string $cumulativeThroughLabel,
    ): PhpWord {
        $showCumulative = $filter->hasExplicitDateFilter();
        $phpWord = new PhpWord;
        $phpWord->getSettings()->setThemeFontLang(new Language(Language::EN_US));
        $phpWord->getSettings()->setZoom(100);
        $phpWord->getDocInfo()
            ->setCreator('Mukhyamantri Udyamshala Yojana')
            ->setCompany('Mukhyamantri Udyamshala Yojana')
            ->setTitle('Monthly progress report - '.$this->reportingMonthLabel($filter))
            ->setSubject('Program deliverables report');

        $phpWord->setDefaultFontName('Calibri');
        $phpWord->setDefaultFontSize(9);

        $section = $phpWord->addSection([
            'paperSize' => 'A4',
            'orientation' => SectionStyle::ORIENTATION_LANDSCAPE,
            'pageSizeW' => 16838,
            'pageSizeH' => 11906,
            'marginTop' => (int) round(Converter::cmToTwip(1.6)),
            'marginRight' => (int) round(Converter::cmToTwip(0.75)),
            'marginBottom' => (int) round(Converter::cmToTwip(0.8)),
            'marginLeft' => (int) round(Converter::cmToTwip(0.75)),
            'headerHeight' => (int) round(Converter::cmToTwip(0.25)),
            'footerHeight' => (int) round(Converter::cmToTwip(0.35)),
        ]);

        $this->addRunningHeader($section, $filter);
        $this->addFooter($section);
        $this->addMetadata($section, $fiscalYearLabel, $scopeLabel, $periodLabel, $filter);
        $this->addReportTable($phpWord, $section, $rows, $showCumulative, $cumulativeThroughLabel);

        return $phpWord;
    }

    private function addRunningHeader(Section $section, ProgramDeliverablesFilter $filter): void
    {
        $header = $section->addHeader();
        $headerTable = $header->addTable([
            'width' => 100 * 50,
            'unit' => TblWidth::PERCENT,
            'layout' => TableStyle::LAYOUT_FIXED,
            'cellMargin' => 40,
            'borderBottomSize' => 10,
            'borderBottomColor' => self::HEADER_FILL,
        ]);
        $headerTable->addRow(null, ['cantSplit' => true]);

        $logoCell = $headerTable->addCell(1100, ['valign' => VerticalJc::CENTER]);
        $logoPath = public_path('images/muy.jpg');
        if (is_file($logoPath)) {
            $logoCell->addImage($logoPath, ['width' => 38, 'height' => 38, 'alignment' => Jc::CENTER]);
        }

        $titleCell = $headerTable->addCell(14500, ['valign' => VerticalJc::CENTER]);
        $scheme = $titleCell->addTextRun(['alignment' => Jc::CENTER, 'spaceAfter' => 0]);
        $scheme->addText('MUKHYAMANTRI UDYAMSHALA YOJANA', [
            'name' => 'Calibri',
            'size' => 8,
            'bold' => true,
            'color' => '475569',
            'allCaps' => true,
        ]);
        $title = $titleCell->addTextRun(['alignment' => Jc::CENTER, 'spaceAfter' => 0]);
        $title->addText(
            'Monthly progress report for the month of - '.$this->reportingMonthLabel($filter),
            ['name' => 'Calibri', 'size' => 14, 'bold' => true, 'color' => '111827'],
        );
    }

    private function addFooter(Section $section): void
    {
        $footer = $section->addFooter();
        $footer->addPreserveText(
            'Page {PAGE} of {NUMPAGES}',
            ['name' => 'Calibri', 'size' => 8, 'color' => '64748B'],
            ['alignment' => Jc::RIGHT, 'spaceBefore' => 0, 'spaceAfter' => 0],
        );
    }

    private function addMetadata(
        Section $section,
        string $fiscalYearLabel,
        string $scopeLabel,
        string $periodLabel,
        ProgramDeliverablesFilter $filter,
    ): void {
        $meta = $section->addTable([
            'width' => 100 * 50,
            'unit' => TblWidth::PERCENT,
            'layout' => TableStyle::LAYOUT_FIXED,
            'borderSize' => 4,
            'borderColor' => 'CBD5E1',
            'cellMargin' => 55,
        ]);
        $meta->addRow(null, ['cantSplit' => true]);

        $values = [
            ['Fiscal year', $fiscalYearLabel],
            ['Scope', $scopeLabel],
            ['Period', $periodLabel],
            ['Indicator type', $filter->indicatorType ?: 'All types'],
            ['Generated', now()->timezone(config('app.timezone'))->format('d M Y, H:i')],
        ];

        foreach ($values as [$label, $value]) {
            $cell = $meta->addCell(3120, ['bgColor' => 'F8FAFC', 'valign' => VerticalJc::CENTER]);
            $run = $cell->addTextRun(['spaceAfter' => 0]);
            $run->addText($label.': ', ['name' => 'Calibri', 'size' => 7.5, 'bold' => true, 'color' => '334155']);
            $run->addText($value, ['name' => 'Calibri', 'size' => 7.5, 'color' => '334155']);
        }

        $section->addText(
            'Monthly / selected-period and cumulative progress',
            ['name' => 'Calibri', 'size' => 10, 'bold' => true, 'color' => '7C2D12'],
            ['spaceBefore' => 80, 'spaceAfter' => 60, 'keepNext' => true],
        );
    }

    /**
     * @param  list<array<string, mixed>>  $rows
     */
    private function addReportTable(
        PhpWord $phpWord,
        Section $section,
        array $rows,
        bool $showCumulative,
        ?string $cumulativeThroughLabel,
    ): void {
        $widths = $showCumulative
            ? [700, 4700, 1700, 1416, 1416, 1416, 1416, 1416, 1416]
            : [700, 6500, 2200, 2066, 2066, 2068];

        $phpWord->addTableStyle('DeliverablesWordTable', [
            'width' => array_sum($widths),
            'unit' => TblWidth::TWIP,
            'layout' => TableStyle::LAYOUT_FIXED,
            'borderSize' => 4,
            'borderColor' => self::BORDER_COLOR,
            'cellMarginTop' => 70,
            'cellMarginRight' => 70,
            'cellMarginBottom' => 70,
            'cellMarginLeft' => 70,
        ]);

        $table = $section->addTable('DeliverablesWordTable');
        $headers = ['S.N.', 'Indicator', 'Type of Indicator', "Target\n(period)", "Achievement\n(period)", "Achievement (%)\n(period)"];
        if ($showCumulative) {
            $through = $cumulativeThroughLabel ?: 'cumulative';
            array_push(
                $headers,
                "Target\n(".$through.')',
                "Achievement\n(".$through.')',
                "Achievement (%)\n(".$through.')',
            );
        }

        $table->addRow(null, ['tblHeader' => true, 'cantSplit' => true]);
        foreach ($headers as $index => $header) {
            $fill = $index >= 6 ? self::CUMULATIVE_HEADER_FILL : self::HEADER_FILL;
            $cell = $table->addCell($widths[$index], [
                'bgColor' => $fill,
                'valign' => VerticalJc::CENTER,
            ]);
            $this->addCellText($cell, $header, true, 'FFFFFF', Jc::CENTER, 7.5);
        }

        foreach ($rows as $row) {
            $isHeading = in_array($row['row_type'] ?? '', ['pillar', 'subcategory'], true);
            $table->addRow(null, ['cantSplit' => true]);

            $values = [
                (string) ($row['serial'] ?? ''),
                (string) ($row['name'] ?? ''),
                $isHeading ? '' : (string) ($row['indicator_type'] ?? '—'),
                $isHeading ? '' : $this->targetValue($row, 'target', 'target_label'),
                $isHeading ? '' : $this->numberValue($row['achievement'] ?? null),
                $isHeading ? '' : $this->percentageValue($row['achievement_pct'] ?? null),
            ];

            if ($showCumulative) {
                array_push(
                    $values,
                    $isHeading ? '' : $this->targetValue($row, 'cumul_target', 'cumul_target_label'),
                    $isHeading ? '' : $this->numberValue($row['cumul_achievement'] ?? null),
                    $isHeading ? '' : $this->percentageValue($row['cumul_achievement_pct'] ?? null),
                );
            }

            foreach ($values as $index => $value) {
                $style = ['valign' => VerticalJc::CENTER];
                if ($isHeading) {
                    $style['bgColor'] = self::SECTION_FILL;
                } elseif ($showCumulative && $index >= 6) {
                    $style['bgColor'] = self::CUMULATIVE_CELL_FILL;
                }

                $cell = $table->addCell($widths[$index], $style);
                $alignment = $index === 1 ? Jc::LEFT : Jc::CENTER;
                $color = '111827';
                if (! $isHeading && in_array($index, $showCumulative ? [5, 8] : [5], true)) {
                    $toneKey = $index === 8 ? 'cumul_performance_tone' : 'performance_tone';
                    [$style['bgColor'], $color] = $this->toneColors((string) ($row[$toneKey] ?? 'critical'));
                    $cell->getStyle()->setBgColor($style['bgColor']);
                }
                if (! $isHeading && in_array($index, $showCumulative ? [4, 7] : [4], true)) {
                    $isBold = true;
                } else {
                    $isBold = $isHeading;
                }

                $this->addCellText($cell, $value, $isBold, $color, $alignment, 8.5);
            }
        }
    }

    private function addCellText(
        Cell $cell,
        string $text,
        bool $bold,
        string $color,
        string $alignment,
        float $size,
    ): void {
        $paragraph = $cell->addTextRun([
            'alignment' => $alignment,
            'spaceBefore' => 0,
            'spaceAfter' => 0,
            'lineHeight' => 1.0,
        ]);
        $parts = explode("\n", $text);
        foreach ($parts as $index => $part) {
            if ($index > 0) {
                $paragraph->addTextBreak();
            }
            $paragraph->addText($part, [
                'name' => 'Calibri',
                'size' => $size,
                'bold' => $bold,
                'color' => $color,
            ]);
        }
    }

    /** @return array{0: string, 1: string} */
    private function toneColors(string $tone): array
    {
        return match ($tone) {
            'good' => ['D1FAE5', '047857'],
            'warn' => ['FEF3C7', 'B45309'],
            default => ['FEE2E2', 'B91C1C'],
        };
    }

    /** @param array<string, mixed> $row */
    private function targetValue(array $row, string $valueKey, string $labelKey): string
    {
        if (filled($row[$labelKey] ?? null)) {
            return (string) $row[$labelKey];
        }

        return $this->numberValue($row[$valueKey] ?? null);
    }

    private function numberValue(mixed $value): string
    {
        return is_numeric($value) ? number_format((float) $value, 0) : '';
    }

    private function percentageValue(mixed $value): string
    {
        return is_numeric($value) ? number_format((float) $value, 0).'%' : '';
    }

    private function reportingMonthLabel(ProgramDeliverablesFilter $filter): string
    {
        if ($filter->month && $filter->dateFrom) {
            return Carbon::parse($filter->dateFrom)->format('F Y');
        }

        return now()->format('F Y');
    }

    private function buildFileName(string $fiscalYearLabel, ProgramDeliverablesFilter $filter): string
    {
        $suffix = $filter->districtId ? '-d'.$filter->districtId : '';
        if ($filter->month) {
            $suffix .= '-m'.$filter->month;
        }

        $fySlug = preg_replace('/[^A-Za-z0-9-]+/', '-', $fiscalYearLabel) ?: 'FY';

        return 'deliverables-'.$fySlug.$suffix.'-'.now()->format('Ymd-His').'.docx';
    }
}
