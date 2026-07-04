<?php

namespace App\Services\MarketLinkages;

use App\Services\Deliverables\Exports\DeliverablesExcelSupport as XL;
use Illuminate\Support\Collection;
use PhpOffice\PhpSpreadsheet\Spreadsheet;
use PhpOffice\PhpSpreadsheet\Style\Alignment;
use PhpOffice\PhpSpreadsheet\Style\Fill;
use PhpOffice\PhpSpreadsheet\Worksheet\Worksheet;
use Symfony\Component\HttpFoundation\StreamedResponse;

/**
 * Builds the Market Linkage dashboard Excel export so it mirrors exactly what the
 * dashboard shows (same filtered incubatees, same partner details), one row per
 * incubatee plus a detailed per-partner sheet.
 */
class MarketLinkageDashboardExcelExport
{
    /** @var list<string> */
    private array $usedSheetTitles = [];

    /**
     * @param  Collection<int, object>  $groups  grouped incubatees (from groupSubmissionsByIncubatee)
     * @param  array{unique_partners:int, linked_incubatees:int, partner_records:int, online_partners:int, offline_partners:int}  $stats
     * @param  array{q:string, from:string, to:string, district_id:int, linkage_mode:string}  $filters
     */
    public function download(Collection $groups, array $stats, array $filters, bool $showDistrict, string $districtLabel): StreamedResponse
    {
        $this->usedSheetTitles = [];

        $spreadsheet = new Spreadsheet;
        $this->buildSummarySheet($spreadsheet->getActiveSheet(), $stats, $filters, $districtLabel);
        $this->buildIncubateesSheet($spreadsheet, $groups, $showDistrict);
        $this->buildPartnersSheet($spreadsheet, $groups, $showDistrict);

        $fileName = 'market-linkages-'.now()->format('Ymd-His').'.xlsx';

        return XL::streamDownload($spreadsheet, $fileName);
    }

    /**
     * @param  array{unique_partners:int, linked_incubatees:int, partner_records:int, online_partners:int, offline_partners:int}  $stats
     * @param  array{q:string, from:string, to:string, district_id:int, linkage_mode:string}  $filters
     */
    private function buildSummarySheet(Worksheet $sheet, array $stats, array $filters, string $districtLabel): void
    {
        $sheet->setTitle(XL::uniqueSheetTitle('Summary', $this->usedSheetTitles));

        $sheet->setCellValue('A1', 'Market Linkage — Dashboard export');
        $sheet->mergeCells('A1:B1');
        $sheet->getStyle('A1')->applyFromArray([
            'font' => ['bold' => true, 'size' => 14, 'color' => ['rgb' => 'FFFFFF']],
            'fill' => ['fillType' => Fill::FILL_SOLID, 'startColor' => ['rgb' => XL::HEADER_FILL]],
            'alignment' => ['horizontal' => Alignment::HORIZONTAL_LEFT],
        ]);
        $sheet->getRowDimension(1)->setRowHeight(26);

        $modeLabel = match ($filters['linkage_mode'] ?? '') {
            'online' => 'Online',
            'offline' => 'Offline',
            default => 'All',
        };

        $next = XL::writeMetaBlock($sheet, 3, [
            ['Generated at', now()->timezone(config('app.timezone'))->format('d M Y, g:i A T')],
            ['Search', ($filters['q'] ?? '') !== '' ? $filters['q'] : '—'],
            ['District', $districtLabel],
            ['Linkage type', $modeLabel],
            ['From date', ($filters['from'] ?? '') !== '' ? $filters['from'] : '—'],
            ['To date', ($filters['to'] ?? '') !== '' ? $filters['to'] : '—'],
        ]);

        $next++;
        $sheet->setCellValue('A'.$next, 'Totals (reflect current filters)');
        $sheet->getStyle('A'.$next)->getFont()->setBold(true);
        $next++;

        XL::writeMetaBlock($sheet, $next, [
            ['Unique partners', (string) (int) ($stats['unique_partners'] ?? 0)],
            ['Linked incubatees', (string) (int) ($stats['linked_incubatees'] ?? 0)],
            ['Partner entries', (string) (int) ($stats['partner_records'] ?? 0)],
            ['Online linkages', (string) (int) ($stats['online_partners'] ?? 0)],
            ['Offline linkages', (string) (int) ($stats['offline_partners'] ?? 0)],
        ]);

        XL::autoSizeColumns($sheet, 'A', 'A');
        $sheet->getColumnDimension('B')->setWidth(42);
    }

    /**
     * One row per incubatee — matches the dashboard table (partner details combined in the row).
     *
     * @param  Collection<int, object>  $groups
     */
    private function buildIncubateesSheet(Spreadsheet $spreadsheet, Collection $groups, bool $showDistrict): void
    {
        $sheet = $spreadsheet->createSheet();
        $sheet->setTitle(XL::uniqueSheetTitle('Linked incubatees', $this->usedSheetTitles));

        $headers = ['#'];
        if ($showDistrict) {
            $headers[] = 'District';
        }
        $headers = array_merge($headers, [
            'Incubatee', 'Application no', 'Submissions', 'Partners', 'Online', 'Offline', 'Partner details', 'Last recorded',
        ]);

        $lastCol = XL::writeTableHeader($sheet, 1, $headers);

        $rows = [];
        $serial = 0;
        foreach ($groups as $group) {
            $serial++;
            $partners = is_array($group->partners ?? null) ? $group->partners : [];
            $online = 0;
            $offline = 0;
            $lines = [];
            $i = 0;
            foreach ($partners as $p) {
                $i++;
                $mode = (string) ($p['linkage_mode'] ?? '');
                if ($mode === 'online') {
                    $online++;
                } elseif ($mode === 'offline') {
                    $offline++;
                }
                $lines[] = $i.'. '.$this->partnerLine($p);
            }

            $row = [$serial];
            if ($showDistrict) {
                $row[] = XL::sanitizeCell($group->district_name ?? '—');
            }
            $row[] = XL::sanitizeCell($group->incubatee_name ?? '');
            $row[] = XL::sanitizeCell($group->application_no ?? '');
            $row[] = (int) ($group->submission_count ?? 0);
            $row[] = (int) ($group->partner_count ?? count($partners));
            $row[] = $online;
            $row[] = $offline;
            $row[] = XL::sanitizeCell(implode("\n", $lines));
            $row[] = $group->last_recorded_at
                ? $group->last_recorded_at->timezone(config('app.timezone'))->format('d M Y')
                : '—';

            $rows[] = $row;
        }

        $this->writeRows($sheet, $rows, $lastCol);

        // Widen the incubatee name and partner-details columns for readability.
        $partnerDetailsCol = XL::columnLetter(count($headers) - 2);
        $sheet->getColumnDimension($partnerDetailsCol)->setAutoSize(false);
        $sheet->getColumnDimension($partnerDetailsCol)->setWidth(60);
    }

    /**
     * One row per partner — every linkage detail shown in the dashboard, in analysable columns.
     *
     * @param  Collection<int, object>  $groups
     */
    private function buildPartnersSheet(Spreadsheet $spreadsheet, Collection $groups, bool $showDistrict): void
    {
        $sheet = $spreadsheet->createSheet();
        $sheet->setTitle(XL::uniqueSheetTitle('Partner entries', $this->usedSheetTitles));

        $headers = ['#'];
        if ($showDistrict) {
            $headers[] = 'District';
        }
        $headers = array_merge($headers, [
            'Incubatee', 'Application no', 'Partner name', 'Linkage type', 'Linkage date', 'Link / URL', 'Bill', 'Recorded at', 'Recorded by', 'Submission ID',
        ]);

        $lastCol = XL::writeTableHeader($sheet, 1, $headers);

        $rows = [];
        $serial = 0;
        foreach ($groups as $group) {
            $serial++;
            $partners = is_array($group->partners ?? null) ? $group->partners : [];
            foreach ($partners as $p) {
                $row = [$serial];
                if ($showDistrict) {
                    $row[] = XL::sanitizeCell($group->district_name ?? '—');
                }
                $row[] = XL::sanitizeCell($group->incubatee_name ?? '');
                $row[] = XL::sanitizeCell($group->application_no ?? '');
                $row[] = XL::sanitizeCell($p['partner_name'] ?? '');
                $row[] = XL::sanitizeCell($p['linkage_mode_label'] ?? '');
                $row[] = XL::sanitizeCell($p['linkage_date_display'] ?? ($p['linkage_date'] ?? ''));
                $row[] = XL::sanitizeCell($p['link_url'] ?? '');
                $row[] = ! empty($p['has_document']) ? 'Yes' : 'No';
                $row[] = XL::sanitizeCell($p['recorded_at'] ?? '');
                $row[] = XL::sanitizeCell($p['recorded_by'] ?? '');
                $row[] = (int) ($p['submission_id'] ?? 0);
                $rows[] = $row;
            }
        }

        $this->writeRows($sheet, $rows, $lastCol);
    }

    /**
     * @param  array<string, mixed>  $p
     */
    private function partnerLine(array $p): string
    {
        $name = trim((string) ($p['partner_name'] ?? '')) !== '' ? (string) $p['partner_name'] : '—';
        $mode = trim((string) ($p['linkage_mode_label'] ?? '')) !== '' ? (string) $p['linkage_mode_label'] : '—';
        $date = trim((string) ($p['linkage_date_display'] ?? '')) !== '' ? (string) $p['linkage_date_display'] : '—';
        $link = trim((string) ($p['link_url'] ?? '')) !== '' ? (string) $p['link_url'] : 'No link';
        $bill = ! empty($p['has_document']) ? 'Bill: Yes' : 'Bill: No';

        return $name.' | '.$mode.' | '.$date.' | '.$link.' | '.$bill;
    }

    /**
     * @param  list<list<mixed>>  $rows
     */
    private function writeRows(Worksheet $sheet, array $rows, string $lastCol): void
    {
        if ($rows === []) {
            XL::autoSizeColumns($sheet, 'A', $lastCol);

            return;
        }

        $sheet->fromArray($rows, null, 'A2');
        $lastRow = count($rows) + 1;
        XL::applyDataRowBorders($sheet, 'A2:'.$lastCol.$lastRow);
        XL::autoSizeColumns($sheet, 'A', $lastCol);
    }
}
