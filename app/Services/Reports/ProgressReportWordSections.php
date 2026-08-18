<?php

namespace App\Services\Reports;

use PhpOffice\PhpWord\Element\Section;
use PhpOffice\PhpWord\PhpWord;
use PhpOffice\PhpWord\SimpleType\Jc;
use PhpOffice\PhpWord\SimpleType\TblWidth;
use PhpOffice\PhpWord\SimpleType\VerticalJc;
use PhpOffice\PhpWord\Style\Table as TableStyle;

trait ProgressReportWordSections
{
    private const TEAM_YELLOW = 'FFFF00';

    private function addTeamPlaceholder(Section $section, string $key, ?string $override = null): void
    {
        $prompts = ProgressReportSectionCatalog::yellowPrompts();
        $text = $override ?? ($prompts[$key] ?? '[TEAM: Add content for this section.]');
        $section->addText($text, [
            'name' => 'Arial',
            'size' => 10,
            'italic' => true,
            'color' => self::INK,
            'bgColor' => self::TEAM_YELLOW,
        ], ['alignment' => Jc::BOTH, 'spaceAfter' => 140, 'lineHeight' => 1.15]);
    }

    private function addExpandedContents(PhpWord $word, ProgressReportContext $context): void
    {
        $section = $this->contentSection($word, $context);
        $section->addTitle('Contents', 1);
        $table = $section->addTable([
            'width' => 100 * 50,
            'unit' => TblWidth::PERCENT,
            'layout' => TableStyle::LAYOUT_FIXED,
            'borderSize' => 1,
            'borderColor' => 'FFFFFF',
            'cellMarginTop' => 24,
            'cellMarginRight' => 35,
            'cellMarginBottom' => 24,
            'cellMarginLeft' => 35,
        ]);

        foreach (ProgressReportSectionCatalog::tableOfContents() as $item) {
            $table->addRow(360, ['cantSplit' => true]);
            $indent = str_contains((string) $item['number'], '.') && ! str_ends_with((string) $item['number'], '.') ? 420 : 620;
            $this->cell($table->addCell($indent, ['borderSize' => 1, 'borderColor' => 'FFFFFF']), (string) $item['number'], true, self::INK, Jc::LEFT, 10);
            $labelCell = $table->addCell(8200 - $indent + 620, ['borderSize' => 1, 'borderColor' => 'FFFFFF']);
            $run = $labelCell->addTextRun(['spaceBefore' => 0, 'spaceAfter' => 0]);
            $run->addText((string) $item['title'], ['name' => 'Arial', 'size' => 10, 'color' => self::INK]);
            if (! $item['auto']) {
                $run->addText(' (Add content)', ['name' => 'Arial', 'size' => 8.5, 'color' => 'B45309', 'italic' => true]);
            }
            $run->addText(' ................................................................', ['name' => 'Arial', 'size' => 8, 'color' => '64748B']);
        }
    }

    /** @param list<array<string, mixed>> $rows */
    private function addProgressSummaryExpanded(PhpWord $word, ProgressReportContext $context, array $rows): void
    {
        $section = $this->contentSection($word, $context);
        $section->addTitle('1. Progress in Mukhyamantri Udyamshala Yojana (MUY)', 1);

        $leafRows = array_values(array_filter($rows, fn (array $row): bool => ! in_array($row['row_type'] ?? '', ['pillar', 'subcategory'], true)));
        $cfa = $this->rowBySerial($leafRows, '1.1');
        $onboarding = $this->rowBySerial($leafRows, '2.1');
        $reported = array_values(array_filter($leafRows, fn (array $row): bool => (int) ($row['achievement'] ?? 0) > 0));
        usort($reported, fn (array $a, array $b): int => ((int) ($b['achievement'] ?? 0)) <=> ((int) ($a['achievement'] ?? 0)));

        $periodWord = $context->isQuarterly() ? 'quarter' : 'month';
        $section->addText(
            'This '.$context->reportKindLabel.' presents MIS-recorded progress for '.$context->periodLabel.'. '
            .'Targets and achievements follow the approved MUY deliverables matrix for '.$context->fiscalYearLabel.'.',
            ['size' => 10.5, 'color' => self::INK],
            ['alignment' => Jc::BOTH, 'spaceAfter' => 140, 'lineHeight' => 1.15],
        );

        $this->addTeamPlaceholder($section, 'executive_summary');

        $cards = $section->addTable(['width' => 100 * 50, 'unit' => TblWidth::PERCENT, 'layout' => TableStyle::LAYOUT_FIXED, 'cellMargin' => 120]);
        $cards->addRow(980, ['cantSplit' => true]);
        $this->metricCard($cards->addCell(3000, ['bgColor' => self::LIGHT_ORANGE]), 'CFA applications', $cfa['achievement'] ?? 0);
        $this->metricCard($cards->addCell(3000, ['bgColor' => 'F0FDF4']), 'Incubatees onboarded', $onboarding['achievement'] ?? 0);
        $this->metricCard($cards->addCell(3000, ['bgColor' => 'EFF6FF']), 'Indicators reporting progress', count($reported));

        $section->addTitle('Key '.$periodWord.'ly highlights', 2);
        if ($reported === []) {
            $section->addText('No approved achievement records were available for the selected period.', ['italic' => true, 'color' => '64748B']);
        } else {
            foreach (array_slice($reported, 0, 10) as $row) {
                $target = is_numeric($row['target'] ?? null) ? number_format((float) $row['target']) : (string) ($row['target_label'] ?? '—');
                $section->addListItem(
                    (string) ($row['name'] ?? 'Indicator').': '.number_format((int) ($row['achievement'] ?? 0)).' achieved against a target of '.$target.'.',
                    0,
                    ['size' => 10, 'color' => self::INK],
                    'listStyle',
                    ['spaceAfter' => 60],
                );
            }
        }
    }

    private function addMobilizationSection(PhpWord $word, ProgressReportContext $context): void
    {
        $section = $this->contentSection($word, $context);
        $section->addTitle('3. Mobilization and Outreach', 1);
        $this->addTeamPlaceholder($section, 'mobilization_intro');

        $subsections = [
            ['3.1', 'Call for Applications', '1.1', 'mobilization_3_1', 'District', 'CFA applications'],
            ['3.2', 'District Level Workshop', '1.2', 'mobilization_3_2', 'District', 'Workshops'],
            ['3.3', 'Awareness/Outreach Activities for SHG members/CBOs', '1.3', 'mobilization_3_3', 'District', 'Activities'],
            ['3.4', 'Entrepreneurship Awareness Program / Entrepreneurship Development Program', '1.4', 'mobilization_3_4', 'District', 'Sessions'],
            ['3.5', 'Outreach through Community organizations', '1.5', 'mobilization_3_5', 'District', 'Visits'],
        ];

        foreach ($subsections as [$number, $title, $serial, $promptKey, $districtLabel, $countLabel]) {
            $section->addTitle($number.' '.$title, 2);
            $breakdown = $context->breakdowns[$serial] ?? ['total' => 0, 'by_district' => [], 'records' => []];
            $this->addBreakdownDistrictTable($section, $breakdown, $districtLabel, $countLabel);
            $this->addBreakdownRecordsTable($section, $breakdown, ['date' => 'Date', 'district' => 'District', 'service' => 'Activity', 'reference' => 'Reference']);
            $this->addTeamPlaceholder($section, $promptKey);
        }
    }

    private function addScreeningOnboardingSection(PhpWord $word, ProgressReportContext $context): void
    {
        $section = $this->contentSection($word, $context);
        $section->addTitle('4. Screening & Onboarding', 1);
        $breakdown = $context->breakdowns['2.1'] ?? ['total' => 0, 'by_district' => [], 'records' => []];
        $section->addText('Total incubatees onboarded in the reporting period: '.number_format((int) ($breakdown['total'] ?? 0)).'.', ['size' => 10.5], ['spaceAfter' => 100]);
        $this->addBreakdownDistrictTable($section, $breakdown, 'District', 'Incubatees onboarded');
        $this->addBreakdownRecordsTable($section, $breakdown, ['date' => 'Date', 'district' => 'District', 'applicant' => 'Applicant', 'reference' => 'Reference']);
        $this->addTeamPlaceholder($section, 'onboarding');
    }

    private function addTrainingSection(PhpWord $word, ProgressReportContext $context): void
    {
        $section = $this->contentSection($word, $context);
        $section->addTitle('5. Training and Capacity Building', 1);
        $trainingSerials = [
            ['5.1 Business Skills Training', '3.1', 'District', 'Sessions'],
            ['5.2 Business Modules Training', '3.2', 'District', 'Participants'],
            ['5.3 Technical Trainings', '3.3', 'District', 'Sessions'],
            ['5.4 Lakhpati/SHG Technical Trainings', '3.3.1', 'District', 'Sessions'],
            ['5.5 Capacity Building of Stakeholders', '3.4', 'District', 'Sessions'],
        ];

        foreach ($trainingSerials as [$title, $serial, $districtLabel, $countLabel]) {
            $section->addTitle($title, 2);
            $breakdown = $context->breakdowns[$serial] ?? ['total' => 0, 'by_district' => [], 'records' => []];
            $section->addText('Total: '.number_format((int) ($breakdown['total'] ?? 0)).'.', ['size' => 10], ['spaceAfter' => 80]);
            $this->addBreakdownDistrictTable($section, $breakdown, $districtLabel, $countLabel);
            $this->addBreakdownRecordsTable($section, $breakdown, ['date' => 'Date', 'district' => 'District', 'service' => 'Activity', 'reference' => 'Reference']);
        }
        $this->addTeamPlaceholder($section, 'training');
    }

    /** @param list<array<string, mixed>> $rows */
    private function addPillarSections(PhpWord $word, ProgressReportContext $context, array $rows): void
    {
        $promptKeys = [
            'Business Formalization' => 'incubation',
            'Mentorship' => 'mentorship',
            'Partnership & Forward Linkages' => 'partnership',
            'Business Acceleration Services' => 'acceleration',
            'Funding & Schematic Convergence' => 'funding',
            'Branding, Communication & Knowledge Management' => 'branding',
        ];
        $titles = ProgressReportSectionCatalog::pillarSections();

        foreach ($titles as $pillarName => $sectionTitle) {
            $section = $this->contentSection($word, $context);
            $section->addTitle($sectionTitle, 1);
            $this->addPillarIndicatorTable($section, $rows, $pillarName);
            $this->addTeamPlaceholder($section, $promptKeys[$pillarName] ?? 'executive_summary');
        }
    }

    /** @param list<array<string, mixed>> $rows */
    private function addPillarIndicatorTable(Section $section, array $rows, string $pillarName): void
    {
        $items = [];
        $current = '';
        foreach ($rows as $row) {
            if (($row['row_type'] ?? '') === 'pillar') {
                $current = (string) ($row['name'] ?? '');
                continue;
            }
            if ($current === $pillarName && ! in_array($row['row_type'] ?? '', ['subcategory'], true)) {
                $items[] = $row;
            }
        }

        if ($items === []) {
            $section->addText('No indicator rows were available for this workstream in the selected period.', ['italic' => true, 'color' => '64748B']);
            return;
        }

        $table = $section->addTable('MprTable');
        $table->addRow(420, ['tblHeader' => true, 'cantSplit' => true]);
        foreach (['S.No.', 'Indicator', 'Target', 'Achievement', 'Achievement (%)'] as $i => $header) {
            $widths = [700, 4300, 1200, 1300, 1300];
            $this->cell($table->addCell($widths[$i], ['bgColor' => self::ORANGE]), $header, true, 'FFFFFF', Jc::CENTER, 8.5);
        }

        foreach ($items as $row) {
            $table->addRow(null, ['cantSplit' => true]);
            $this->cell($table->addCell(700), (string) ($row['serial'] ?? ''), false, self::INK, Jc::CENTER, 8.5);
            $this->cell($table->addCell(4300), (string) ($row['name'] ?? ''), false, self::INK, Jc::LEFT, 8.5);
            $this->cell($table->addCell(1200), $this->value($row['target'] ?? null, $row['target_label'] ?? null), false, self::INK, Jc::CENTER, 8.5);
            $this->cell($table->addCell(1300), $this->value($row['achievement'] ?? null), true, self::INK, Jc::CENTER, 8.5);
            $this->cell($table->addCell(1300), $this->percent($row['achievement_pct'] ?? null), false, self::INK, Jc::CENTER, 8.5);
        }
    }

    private function addLineDepartmentSection(PhpWord $word, ProgressReportContext $context): void
    {
        $section = $this->contentSection($word, $context);
        $section->addTitle('12. Meeting with Line Departments', 1);
        $breakdown = $context->breakdowns['12.2'] ?? ['total' => 0, 'by_district' => [], 'records' => []];
        $section->addText('Total meetings recorded: '.number_format((int) ($breakdown['total'] ?? 0)).'.', ['size' => 10.5], ['spaceAfter' => 100]);
        $this->addBreakdownRecordsTable($section, $breakdown, [
            'date' => 'Date',
            'district' => 'District',
            'applicant' => 'Department',
            'service' => 'Official',
        ]);
        $this->addTeamPlaceholder($section, 'line_departments');
    }

    private function addFieldVisitsExpanded(PhpWord $word, ProgressReportContext $context): void
    {
        $section = $this->contentSection($word, $context);
        $section->addTitle('13. Important Field Visits', 1);
        $section->addText('Approved field photographs from the MIS Media Gallery for the reporting period.', ['size' => 10.5], ['spaceAfter' => 120]);

        $photosBySection = $context->photosBySection;
        if ($photosBySection === [] && $context->photos !== []) {
            $photosBySection = ['Field Highlights' => $context->photos];
        }

        if ($photosBySection === []) {
            $section->addText('No approved field photographs were available for this period.', ['italic' => true, 'color' => '64748B']);
            $this->addTeamPlaceholder($section, 'field_visits');
            return;
        }

        foreach ($photosBySection as $label => $photos) {
            $section->addTitle((string) $label, 2);
            $this->renderPhotoGrid($section, $photos);
        }
        $this->addTeamPlaceholder($section, 'field_visits');
    }

    /** @param list<array<string, string>> $photos */
    private function renderPhotoGrid(Section $section, array $photos): void
    {
        foreach (array_chunk($photos, 2) as $pair) {
            $table = $section->addTable(['width' => 100 * 50, 'unit' => TblWidth::PERCENT, 'layout' => TableStyle::LAYOUT_FIXED, 'borderSize' => 1, 'borderColor' => 'FFFFFF', 'cellMarginTop' => 55, 'cellMarginRight' => 70, 'cellMarginBottom' => 55, 'cellMarginLeft' => 70]);
            $table->addRow(null, ['cantSplit' => true]);
            foreach ([0, 1] as $index) {
                $photo = $pair[$index] ?? null;
                $cell = $table->addCell(4700, ['valign' => VerticalJc::TOP, 'borderSize' => 1, 'borderColor' => 'FFFFFF']);
                if ($photo === null) {
                    continue;
                }
                try {
                    $cell->addImage($photo['path'], $this->photoImageStyle((string) $photo['path']));
                } catch (\Throwable) {
                    $cell->addText('Photograph could not be embedded.', ['italic' => true, 'color' => '64748B']);
                }
                $cell->addText($photo['section'].' - '.$photo['title'], ['name' => 'Arial', 'size' => 8.5, 'bold' => true, 'color' => self::INK], ['alignment' => Jc::CENTER, 'spaceBefore' => 45, 'spaceAfter' => 16]);
                $cell->addText(trim($photo['district'].' | '.$photo['date'], ' |'), ['name' => 'Arial', 'size' => 7.5, 'color' => '64748B'], ['alignment' => Jc::CENTER, 'spaceAfter' => 55]);
            }
        }
    }

    private function addManualTemplateSections(PhpWord $word, ProgressReportContext $context): void
    {
        $manual = [
            ['14. Bootcamps and Marketing Drive', 'bootcamps'],
            ['15. Additional Activities', 'additional'],
            ['16. IT & MIS', 'it_mis'],
            ['17. Project Risk Assessment and Mitigation Measures', 'risk'],
        ];

        foreach ($manual as [$title, $promptKey]) {
            $section = $this->contentSection($word, $context);
            $section->addTitle($title, 1);
            if ($promptKey === 'risk') {
                $this->addRiskTemplateTable($section);
            }
            $this->addTeamPlaceholder($section, $promptKey);
        }
    }

    private function addRiskTemplateTable(Section $section): void
    {
        $table = $section->addTable('MprTable');
        $headers = ['S.No.', 'Risk', 'Impact', 'Mitigation', 'Status'];
        $widths = [600, 2800, 1500, 2800, 1200];
        $table->addRow(420, ['tblHeader' => true]);
        foreach ($headers as $i => $header) {
            $this->cell($table->addCell($widths[$i], ['bgColor' => self::ORANGE]), $header, true, 'FFFFFF', Jc::CENTER, 8.5);
        }
        for ($i = 1; $i <= 3; $i++) {
            $table->addRow(null, ['cantSplit' => true]);
            $this->cell($table->addCell(600), (string) $i, false, self::INK, Jc::CENTER, 8.5);
            foreach ([1, 2, 3, 4] as $col) {
                $this->cell($table->addCell($widths[$col]), '', false, self::INK, Jc::LEFT, 8.5);
            }
        }
    }

    private function addMediaCoverageSection(PhpWord $word, ProgressReportContext $context): void
    {
        $section = $this->contentSection($word, $context);
        $section->addTitle('18. Media Coverages', 1);
        $breakdown = $context->breakdowns['10.4'] ?? ['total' => 0, 'records' => []];
        $section->addText('MIS-logged IEC and promotional campaigns: '.number_format((int) ($breakdown['total'] ?? 0)).'.', ['size' => 10.5], ['spaceAfter' => 100]);
        $this->addBreakdownRecordsTable($section, $breakdown, [
            'date' => 'Date',
            'applicant' => 'Title',
            'hub' => 'Media type',
            'service' => 'Channel',
            'district' => 'Coverage area',
        ]);
        $this->addTeamPlaceholder($section, 'media');
    }

    /** @param list<array{name: string, designation: string, district: string}> $teamRoster */
    private function addTeamStructureSection(PhpWord $word, ProgressReportContext $context, array $teamRoster): void
    {
        $section = $this->contentSection($word, $context);
        $section->addTitle('19. MUY Team Structure', 1);
        $section->addText('Active team members recorded in the MUY MIS user directory.', ['size' => 10.5], ['spaceAfter' => 100]);

        if ($teamRoster === []) {
            $section->addText('No active team members were found.', ['italic' => true, 'color' => '64748B']);
        } else {
            $table = $section->addTable('MprTable');
            $table->addRow(420, ['tblHeader' => true]);
            foreach (['S.No.', 'Designation', 'Resource Name', 'District'] as $i => $header) {
                $widths = [700, 2600, 3200, 2400];
                $this->cell($table->addCell($widths[$i], ['bgColor' => self::ORANGE]), $header, true, 'FFFFFF', Jc::CENTER, 8.5);
            }
            foreach ($teamRoster as $i => $member) {
                $table->addRow(null, ['cantSplit' => true]);
                $fill = $i % 2 === 0 ? 'FFFFFF' : self::LIGHT_ORANGE;
                $this->cell($table->addCell(700, ['bgColor' => $fill]), (string) ($i + 1), false, self::INK, Jc::CENTER, 8.5);
                $this->cell($table->addCell(2600, ['bgColor' => $fill]), (string) $member['designation'], false, self::INK, Jc::LEFT, 8.5);
                $this->cell($table->addCell(3200, ['bgColor' => $fill]), (string) $member['name'], true, self::INK, Jc::LEFT, 8.5);
                $this->cell($table->addCell(2400, ['bgColor' => $fill]), (string) $member['district'], false, self::INK, Jc::LEFT, 8.5);
            }
        }
        $this->addTeamPlaceholder($section, 'team');
    }

    /** @param array<string, mixed> $breakdown */
    private function addBreakdownDistrictTable(Section $section, array $breakdown, string $districtLabel, string $countLabel): void
    {
        $rows = array_values((array) ($breakdown['by_district'] ?? []));
        if ($rows === []) {
            $section->addText('No district-level records were available for this indicator in the selected period.', ['italic' => true, 'color' => '64748B'], ['spaceAfter' => 80]);
            return;
        }

        $table = $section->addTable('MprTable');
        $table->addRow(420, ['tblHeader' => true]);
        $this->cell($table->addCell(900, ['bgColor' => self::ORANGE]), 'S.N.', true, 'FFFFFF', Jc::CENTER, 8.5);
        $this->cell($table->addCell(5200, ['bgColor' => self::ORANGE]), $districtLabel, true, 'FFFFFF', Jc::LEFT, 8.5);
        $this->cell($table->addCell(2800, ['bgColor' => self::ORANGE]), $countLabel, true, 'FFFFFF', Jc::CENTER, 8.5);
        foreach ($rows as $i => $row) {
            $table->addRow(null, ['cantSplit' => true]);
            $fill = $i % 2 === 0 ? 'FFFFFF' : self::LIGHT_ORANGE;
            $this->cell($table->addCell(900, ['bgColor' => $fill]), (string) ($i + 1), false, self::INK, Jc::CENTER, 8.5);
            $this->cell($table->addCell(5200, ['bgColor' => $fill]), (string) ($row['district'] ?? 'Unknown'), true, self::INK, Jc::LEFT, 8.5);
            $this->cell($table->addCell(2800, ['bgColor' => $fill]), number_format((int) ($row['count'] ?? 0)), false, self::INK, Jc::CENTER, 8.5);
        }
        $section->addText('', [], ['spaceAfter' => 80]);
    }

    /**
     * @param  array<string, mixed>  $breakdown
     * @param  array<string, string>  $columns
     */
    private function addBreakdownRecordsTable(Section $section, array $breakdown, array $columns, int $limit = 25): void
    {
        $records = array_values((array) ($breakdown['records'] ?? []));
        if ($records === []) {
            return;
        }

        $records = array_slice($records, 0, $limit);
        $table = $section->addTable('MprTable');
        $width = (int) floor(8800 / max(count($columns), 1));
        $table->addRow(420, ['tblHeader' => true]);
        foreach ($columns as $header) {
            $this->cell($table->addCell($width, ['bgColor' => self::LIGHT_ORANGE]), $header, true, self::INK, Jc::CENTER, 8);
        }
        foreach ($records as $record) {
            $table->addRow(null, ['cantSplit' => true]);
            foreach (array_keys($columns) as $key) {
                $value = (string) ($record[$key] ?? '—');
                $this->cell($table->addCell($width), $value !== '' ? $value : '—', false, self::INK, Jc::LEFT, 8);
            }
        }
        $section->addText('', [], ['spaceAfter' => 80]);
    }
}
