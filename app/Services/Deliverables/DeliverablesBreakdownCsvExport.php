<?php

namespace App\Services\Deliverables;

use Symfony\Component\HttpFoundation\StreamedResponse;

class DeliverablesBreakdownCsvExport
{
    /**
     * @param  array<string, mixed>  $breakdown
     * @param  array<string, mixed>|null  $row
     * @param  array<string, mixed>  $meta
     */
    public function download(array $breakdown, ?array $row, array $meta, string $serial): StreamedResponse
    {
        $slug = str_replace('.', '-', $serial);
        $fileName = 'deliverables-breakdown-'.$slug.'-'.now()->format('Ymd').'.csv';

        $target = is_array($row) ? ($row['target'] ?? null) : null;
        $achievementPct = is_array($row) ? ($row['achievement_pct'] ?? null) : null;

        return response()->streamDownload(function () use ($breakdown, $meta, $serial, $target, $achievementPct): void {
            $out = fopen('php://output', 'w');
            if ($out === false) {
                return;
            }

            fputcsv($out, ['Indicator', $breakdown['name'] ?? '']);
            fputcsv($out, ['S.N.', $serial]);
            fputcsv($out, ['Scope', $meta['scope_label'] ?? '']);
            fputcsv($out, ['Period', $meta['period_label'] ?? '']);
            fputcsv($out, ['Target', $target !== null ? $target : '-']);
            fputcsv($out, ['Achievement', (int) ($breakdown['total'] ?? 0)]);
            fputcsv($out, ['Achievement %', $achievementPct !== null ? $achievementPct.'%' : '-']);
            fputcsv($out, ['Source', $breakdown['source_type_label'] ?? '']);
            fputcsv($out, []);

            fputcsv($out, ['District', 'Hub', 'Count', 'Share %']);
            foreach ($breakdown['by_district'] ?? [] as $item) {
                fputcsv($out, [
                    $item['district'] ?? '',
                    $item['hub'] ?? '',
                    (int) ($item['count'] ?? 0),
                    ((int) ($item['share_pct'] ?? 0)).'%',
                ]);
            }
            fputcsv($out, []);

            fputcsv($out, ['Month', 'Count', 'Share %']);
            foreach ($breakdown['by_month'] ?? [] as $item) {
                fputcsv($out, [
                    $item['month'] ?? '',
                    (int) ($item['count'] ?? 0),
                    ((int) ($item['share_pct'] ?? 0)).'%',
                ]);
            }
            fputcsv($out, []);

            if (($breakdown['by_service'] ?? []) !== []) {
                fputcsv($out, ['Service', 'Count', 'Share %']);
                foreach ($breakdown['by_service'] as $item) {
                    fputcsv($out, [
                        $item['service'] ?? '',
                        (int) ($item['count'] ?? 0),
                        ((int) ($item['share_pct'] ?? 0)).'%',
                    ]);
                }
                fputcsv($out, []);
            }

            fputcsv($out, ['Reference', 'Applicant', 'District', 'Hub', 'Service', 'Status', 'Date']);
            foreach ($breakdown['records'] ?? [] as $item) {
                fputcsv($out, [
                    $item['reference'] ?? '',
                    $item['applicant'] ?? '',
                    $item['district'] ?? '',
                    $item['hub'] ?? '',
                    $item['service'] ?? '',
                    $item['status'] ?? '',
                    $item['date'] ?? '',
                ]);
            }

            fclose($out);
        }, $fileName, [
            'Content-Type' => 'text/csv; charset=UTF-8',
        ]);
    }
}
