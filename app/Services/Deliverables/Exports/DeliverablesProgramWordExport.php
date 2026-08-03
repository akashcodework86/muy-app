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
use Throwable;

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
        if (! class_exists(PhpWord::class) || ! class_exists(Word2007::class)) {
            return $this->downloadCompatibilityDocument(
                $rows,
                $filter,
                $scopeLabel,
                $periodLabel,
                $fiscalYearLabel,
                $cumulativeThroughLabel,
            );
        }

        $path = tempnam(sys_get_temp_dir(), 'muy-deliverables-word-');
        abort_if($path === false, 500, 'Could not prepare Word export.');

        $docxPath = $path.'.docx';
        @unlink($path);

        try {
            $this->save(
                $docxPath,
                $rows,
                $filter,
                $scopeLabel,
                $periodLabel,
                $fiscalYearLabel,
                $cumulativeThroughLabel,
            );
        } catch (Throwable $exception) {
            @unlink($docxPath);
            report($exception);

            return $this->downloadCompatibilityDocument(
                $rows,
                $filter,
                $scopeLabel,
                $periodLabel,
                $fiscalYearLabel,
                $cumulativeThroughLabel,
            );
        }

        return response()->download(
            $docxPath,
            $this->buildFileName($fiscalYearLabel, $filter, 'docx'),
            ['Content-Type' => 'application/vnd.openxmlformats-officedocument.wordprocessingml.document'],
        )->deleteFileAfterSend(true);
    }

    /**
     * Dependency-free Word-compatible fallback for servers where PhpWord has
     * not yet been installed. Microsoft Word opens this as an editable report.
     *
     * @param  list<array<string, mixed>>  $rows
     */
    public function saveCompatibilityDocument(
        string $path,
        array $rows,
        ProgramDeliverablesFilter $filter,
        string $scopeLabel,
        string $periodLabel,
        string $fiscalYearLabel,
        ?string $cumulativeThroughLabel,
    ): void {
        $written = file_put_contents($path, $this->compatibilityDocumentRtf(
            $rows,
            $filter,
            $scopeLabel,
            $periodLabel,
            $fiscalYearLabel,
            $cumulativeThroughLabel,
        ));

        abort_if($written === false, 500, 'Could not write Word export.');
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

    /**
     * @param  list<array<string, mixed>>  $rows
     */
    private function downloadCompatibilityDocument(
        array $rows,
        ProgramDeliverablesFilter $filter,
        string $scopeLabel,
        string $periodLabel,
        string $fiscalYearLabel,
        ?string $cumulativeThroughLabel,
    ): BinaryFileResponse {
        $path = tempnam(sys_get_temp_dir(), 'muy-deliverables-word-fallback-');
        abort_if($path === false, 500, 'Could not prepare Word export.');

        $docPath = $path.'.rtf';
        @unlink($path);

        $this->saveCompatibilityDocument(
            $docPath,
            $rows,
            $filter,
            $scopeLabel,
            $periodLabel,
            $fiscalYearLabel,
            $cumulativeThroughLabel,
        );

        return response()->download(
            $docPath,
            $this->buildFileName($fiscalYearLabel, $filter, 'rtf'),
            ['Content-Type' => 'application/rtf'],
        )->deleteFileAfterSend(true);
    }

    /**
     * @param  list<array<string, mixed>>  $rows
     */
    private function compatibilityDocumentRtf(
        array $rows,
        ProgramDeliverablesFilter $filter,
        string $scopeLabel,
        string $periodLabel,
        string $fiscalYearLabel,
        ?string $cumulativeThroughLabel,
    ): string {
        $showCumulative = $filter->hasExplicitDateFilter();
        $through = $cumulativeThroughLabel ?: 'cumulative';
        $logoPath = public_path('images/muy.jpg');
        $logo = is_file($logoPath) ? bin2hex((string) file_get_contents($logoPath)) : '';

        $headers = [
            'S.N.',
            'Indicator',
            'Type of Indicator',
            "Target\n(period)",
            "Achievement\n(period)",
            "Achievement (%)\n(period)",
        ];
        if ($showCumulative) {
            array_push(
                $headers,
                "Target\n(".$through.')',
                "Achievement\n(".$through.')',
                "Achievement (%)\n(".$through.')',
            );
        }

        $widths = $showCumulative
            ? [700, 4700, 1700, 1416, 1416, 1416, 1416, 1416, 1416]
            : [700, 6500, 2200, 2066, 2066, 2068];
        $table = $this->rtfRow($headers, $widths, array_fill(0, count($headers), 2), true, true);

        foreach ($rows as $row) {
            $isHeading = in_array($row['row_type'] ?? '', ['pillar', 'subcategory'], true);
            if ($isHeading) {
                $values = array_fill(0, count($widths), '');
                $values[0] = (string) ($row['serial'] ?? '');
                $values[1] = (string) ($row['name'] ?? '');
                $table .= $this->rtfRow($values, $widths, array_fill(0, count($widths), 4), false, true);

                continue;
            }

            $values = [
                (string) ($row['serial'] ?? ''),
                (string) ($row['name'] ?? ''),
                (string) ($row['indicator_type'] ?? '-'),
                $this->targetValue($row, 'target', 'target_label'),
                $this->numberValue($row['achievement'] ?? null),
                $this->percentageValue($row['achievement_pct'] ?? null),
            ];
            if ($showCumulative) {
                array_push(
                    $values,
                    $this->targetValue($row, 'cumul_target', 'cumul_target_label'),
                    $this->numberValue($row['cumul_achievement'] ?? null),
                    $this->percentageValue($row['cumul_achievement_pct'] ?? null),
                );
            }

            $fills = array_fill(0, count($values), 0);
            $colors = array_fill(0, count($values), 1);
            $bold = array_fill(0, count($values), false);
            foreach ($values as $index => $value) {
                if ($showCumulative && $index >= 6) {
                    $fills[$index] = 5;
                }
                if (in_array($index, $showCumulative ? [5, 8] : [5], true)) {
                    $toneKey = $index === 8 ? 'cumul_performance_tone' : 'performance_tone';
                    [$fills[$index], $colors[$index]] = match ($row[$toneKey] ?? 'critical') {
                        'good' => [6, 7],
                        'warn' => [8, 3],
                        default => [9, 10],
                    };
                }
                if (in_array($index, $showCumulative ? [4, 7] : [4], true)) {
                    $bold[$index] = true;
                }
            }
            $table .= $this->rtfRow($values, $widths, $fills, false, $bold, $colors);
        }

        $title = 'Monthly progress report for the month of - '.$this->reportingMonthLabel($filter);
        $headerLogo = $logo === '' ? '' : '{\\pict\\jpegblip\\picwgoal760\\pichgoal760 '.$logo.'}';
        $metadata = 'Fiscal year: '.$fiscalYearLabel.'    |    Scope: '.$scopeLabel.'    |    Period: '.$periodLabel
            .'    |    Indicator type: '.($filter->indicatorType ?: 'All types')
            .'    |    Generated: '.now()->timezone(config('app.timezone'))->format('d M Y, H:i');

        return '{\\rtf1\\ansi\\ansicpg1252\\deff0\\uc1'
            .'{\\fonttbl{\\f0 Calibri;}}'
            .'{\\colortbl;\\red17\\green24\\blue39;\\red154\\green52\\blue18;\\red180\\green83\\blue9;'
            .'\\red255\\green237\\blue213;\\red255\\green251\\blue235;\\red209\\green250\\blue229;'
            .'\\red4\\green120\\blue87;\\red254\\green243\\blue199;\\red254\\green226\\blue226;'
            .'\\red185\\green28\\blue28;\\red100\\green116\\blue139;\\red255\\green255\\blue255;}'
            .'\\landscape\\paperw16838\\paperh11906\\margl425\\margr425\\margt900\\margb600\\headery180\\footery250'
            .'{\\header\\pard\\qc '.$headerLogo.'\\line\\f0\\fs16\\b\\cf11 '.$this->rtfText('MUKHYAMANTRI UDYAMSHALA YOJANA')
            .'\\line\\fs28\\cf1 '.$this->rtfText($title).'\\b0\\par}'
            .'{\\footer\\pard\\qr\\f0\\fs16\\cf11 Page {\\field{\\*\\fldinst PAGE}{\\fldrslt 1}} of '
            .'{\\field{\\*\\fldinst NUMPAGES}{\\fldrslt 1}}\\par}'
            .'\\viewkind4\\f0\\fs17\\cf1\\pard\\sa80\\brdrb\\brdrs\\brdrw8\\brdrcf2 '
            .'\\b '.$this->rtfText($metadata).'\\b0\\par'
            .'\\pard\\sa60\\b\\fs20\\cf2 '.$this->rtfText('Monthly / selected-period and cumulative progress').'\\b0\\par'
            .$table.'}';
    }

    /**
     * @param  list<string>  $values
     * @param  list<int>  $widths
     * @param  list<int>  $fills
     * @param  bool|list<bool>  $bold
     * @param  list<int>|null  $colors
     */
    private function rtfRow(
        array $values,
        array $widths,
        array $fills,
        bool $repeatHeader,
        bool|array $bold,
        ?array $colors = null,
    ): string {
        $row = '\\trowd\\trgaph70\\trleft0\\trkeep';
        if ($repeatHeader) {
            $row .= '\\trhdr';
        }

        $edge = 0;
        foreach ($widths as $index => $width) {
            $edge += $width;
            $fill = $fills[$index] ?? 0;
            $row .= '\\clvertalc\\clbrdrt\\brdrs\\brdrw5\\brdrcf11\\clbrdrl\\brdrs\\brdrw5\\brdrcf11'
                .'\\clbrdrb\\brdrs\\brdrw5\\brdrcf11\\clbrdrr\\brdrs\\brdrw5\\brdrcf11';
            if ($fill > 0) {
                $row .= '\\clcbpat'.$fill;
            }
            $row .= '\\cellx'.$edge;
        }

        foreach ($values as $index => $value) {
            $isBold = is_array($bold) ? ($bold[$index] ?? false) : $bold;
            $color = $colors[$index] ?? ($repeatHeader ? 12 : 1);
            $alignment = $index === 1 && ! $repeatHeader ? '\\ql' : '\\qc';
            $row .= '\\pard\\intbl'.$alignment.'\\fs'.($repeatHeader ? '15' : '17').'\\cf'.$color;
            if ($isBold) {
                $row .= '\\b';
            }
            $row .= ' '.$this->rtfText((string) $value);
            if ($isBold) {
                $row .= '\\b0';
            }
            $row .= '\\cell';
        }

        return $row.'\\row';
    }

    private function rtfText(string $value): string
    {
        $result = '';
        foreach (mb_str_split($value) as $character) {
            if ($character === "\n") {
                $result .= '\\line ';

                continue;
            }
            if (in_array($character, ['\\', '{', '}'], true)) {
                $result .= '\\'.$character;

                continue;
            }

            $codepoint = mb_ord($character, 'UTF-8');
            if ($codepoint >= 32 && $codepoint <= 126) {
                $result .= $character;

                continue;
            }

            $units = unpack('v*', mb_convert_encoding($character, 'UTF-16LE', 'UTF-8')) ?: [];
            foreach ($units as $unit) {
                $signed = $unit > 32767 ? $unit - 65536 : $unit;
                $result .= '\\u'.$signed.'?';
            }
        }

        return $result;
    }

    private function buildFileName(
        string $fiscalYearLabel,
        ProgramDeliverablesFilter $filter,
        string $extension,
    ): string {
        $suffix = $filter->districtId ? '-d'.$filter->districtId : '';
        if ($filter->month) {
            $suffix .= '-m'.$filter->month;
        }

        $fySlug = preg_replace('/[^A-Za-z0-9-]+/', '-', $fiscalYearLabel) ?: 'FY';

        return 'deliverables-'.$fySlug.$suffix.'-'.now()->format('Ymd-His').'.'.$extension;
    }
}
