<?php

namespace App\Services\Deliverables\Exports;

use App\Services\Deliverables\ProgramDeliverablesFilter;
use Symfony\Component\HttpFoundation\StreamedResponse;

class DeliverablesProgramCsvExport
{
    /**
     * UTF-8 CSV with BOM — opens correctly in Excel when PhpSpreadsheet is unavailable.
     *
     * @param  list<array<string, mixed>>  $rows
     */
    public function download(
        array $rows,
        ProgramDeliverablesFilter $filter,
        string $scopeLabel,
        string $periodLabel,
        string $fiscalYearLabel,
    ): StreamedResponse {
        $fileName = $this->buildFileName($fiscalYearLabel, $filter);
        $tz = config('app.timezone');
        $showCumulative = $filter->hasExplicitDateFilter();
        $cumulLabel = $showCumulative
            ? ($filter->cumulativeThroughLabel(null) ?? 'cumulative')
            : '';

        return response()->streamDownload(function () use ($rows, $scopeLabel, $periodLabel, $fiscalYearLabel, $tz, $showCumulative, $cumulLabel): void {
            $out = fopen('php://output', 'w');
            if ($out === false) {
                return;
            }

            fwrite($out, "\xEF\xBB\xBF");

            fputcsv($out, ['Program deliverables report']);
            fputcsv($out, ['Fiscal year', $fiscalYearLabel]);
            fputcsv($out, ['Scope', $scopeLabel]);
            fputcsv($out, ['Period', $periodLabel]);
            fputcsv($out, ['Generated at', now()->timezone($tz)->format('d M Y, g:i A T')]);
            fputcsv($out, []);

            fputcsv($out, $showCumulative
                ? [
                    'S.N.',
                    'Indicator',
                    'Type of Indicator',
                    'Spoke/ Hub/ State',
                    'Target (period)',
                    'Achievement (period)',
                    'Achievement % (period)',
                    'Target ('.$cumulLabel.')',
                    'Achievement ('.$cumulLabel.')',
                    'Achievement % ('.$cumulLabel.')',
                ]
                : [
                    'S.N.',
                    'Indicator',
                    'Type of Indicator',
                    'Spoke/ Hub/ State',
                    'Targets',
                    'Achievement',
                    'Achievement (%)',
                ]);

            foreach ($rows as $row) {
                $isHeading = in_array($row['row_type'] ?? '', ['pillar', 'subcategory'], true);
                $pct = $row['achievement_pct'] ?? null;

                $cells = [
                    DeliverablesExcelSupport::sanitizeCell($row['serial'] ?? ''),
                    DeliverablesExcelSupport::sanitizeCell($row['name'] ?? ''),
                    $isHeading ? '' : DeliverablesExcelSupport::sanitizeCell($row['indicator_type'] ?? ''),
                    $isHeading ? '' : DeliverablesExcelSupport::sanitizeCell($row['level'] ?? ''),
                    $isHeading ? '' : DeliverablesExcelSupport::formatTargetCell($row),
                    $isHeading ? '' : ($row['achievement'] ?? ''),
                    $isHeading ? '' : ($pct !== null ? $pct.'%' : ''),
                ];

                if ($showCumulative) {
                    $cumulPct = $row['cumul_achievement_pct'] ?? null;
                    $cells[] = $isHeading ? '' : DeliverablesExcelSupport::formatTargetCell($row, 'cumul_target', 'cumul_target_label');
                    $cells[] = $isHeading ? '' : ($row['cumul_achievement'] ?? '');
                    $cells[] = $isHeading ? '' : ($cumulPct !== null ? $cumulPct.'%' : '');
                }

                fputcsv($out, $cells);
            }

            fclose($out);
        }, $fileName, [
            'Content-Type' => 'text/csv; charset=UTF-8',
        ]);
    }

    private function buildFileName(string $fiscalYearLabel, ProgramDeliverablesFilter $filter): string
    {
        $suffix = $filter->districtId ? '-d'.$filter->districtId : '';
        if ($filter->month) {
            $suffix .= '-m'.$filter->month;
        }

        $fySlug = str_replace([' ', '/'], '-', $fiscalYearLabel);

        return 'deliverables-'.$fySlug.$suffix.'-'.now()->format('Ymd-His').'.csv';
    }
}
