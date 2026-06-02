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

            $sourceType = (string) ($breakdown['source_type'] ?? '');
            $isFemaleParticipants = in_array($sourceType, ['field_work_participants', 'field_visit_participants'], true);
            $isFieldWorkshops = in_array($sourceType, ['field_work_workshops', 'field_visit_sessions'], true);
            $isBstParticipants = $sourceType === 'bst_participants';

            if ($isBstParticipants) {
                fputcsv($out, ['#', 'Incubatee Name', 'Application No.', 'District', 'Hub', 'Sessions Attended', 'Session Count']);
                $idx = 1;
                foreach ($breakdown['records'] ?? [] as $item) {
                    fputcsv($out, [
                        $idx++,
                        $item['applicant'] ?? '',
                        $item['reference'] ?? '',
                        $item['district'] ?? '',
                        $item['hub'] ?? '',
                        $item['service'] ?? '',
                        (int) ($item['session_count'] ?? 0),
                    ]);
                }
            } elseif ($isFemaleParticipants) {
                fputcsv($out, ['#', 'Participant Name', 'Gender', 'District', 'Hub', 'Gram Panchayat / Mobile', 'Workshop Ref', 'Visit Date']);
                $idx = 1;
                foreach ($breakdown['records'] ?? [] as $item) {
                    fputcsv($out, [
                        $idx++,
                        $item['applicant'] ?? '',
                        'Female',
                        $item['district'] ?? '',
                        $item['hub'] ?? '',
                        $item['service'] ?? '',
                        $item['reference'] ?? '',
                        $item['date'] ?? '',
                    ]);
                }
            } elseif ($isFieldWorkshops) {
                fputcsv($out, ['#', 'Reference', 'Type', 'Area / Block', 'District', 'Hub', 'Date']);
                $idx = 1;
                foreach ($breakdown['records'] ?? [] as $item) {
                    fputcsv($out, [
                        $idx++,
                        $item['reference'] ?? '',
                        $item['service'] ?? '',
                        $item['applicant'] ?? '',
                        $item['district'] ?? '',
                        $item['hub'] ?? '',
                        $item['date'] ?? '',
                    ]);
                }
            } else {
                fputcsv($out, ['#', 'Reference', 'Applicant', 'District', 'Hub', 'Service', 'Status', 'Date']);
                $idx = 1;
                foreach ($breakdown['records'] ?? [] as $item) {
                    fputcsv($out, [
                        $idx++,
                        $item['reference'] ?? '',
                        $item['applicant'] ?? '',
                        $item['district'] ?? '',
                        $item['hub'] ?? '',
                        $item['service'] ?? '',
                        $item['status'] ?? '',
                        $item['date'] ?? '',
                    ]);
                }
            }

            fclose($out);
        }, $fileName, [
            'Content-Type' => 'text/csv; charset=UTF-8',
        ]);
    }
}
