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
            } elseif ($sourceType === 'market_linkage_incubatees') {
                fputcsv($out, [
                    '#',
                    'Application No.',
                    'Incubatee',
                    'Phone',
                    'Block',
                    'District',
                    'Hub',
                    'Source',
                    'Status',
                    'Submitted by',
                    'Submitted on',
                    'Partner name',
                    'Linkage mode',
                    'Linkage date',
                    'Link / URL',
                    'Bill',
                    'Bill file',
                ]);
                $serial = 0;
                foreach ($breakdown['records'] ?? [] as $item) {
                    $serial++;
                    $partners = is_array($item['partner_rows'] ?? null) && $item['partner_rows'] !== []
                        ? $item['partner_rows']
                        : [[
                            'partner_name' => $item['service'] ?? '',
                            'linkage_mode_label' => $item['linkage_mode'] ?? '',
                            'linkage_date_display' => '',
                            'link_url' => '',
                            'has_document' => false,
                            'document_name' => '',
                        ]];
                    $phone = trim((string) ($item['phone'] ?? ''));
                    $source = ((string) ($item['source'] ?? '')) === 'service_case' ? 'Service case' : 'Market linkage';
                    $status = trim((string) ($item['status'] ?? ''));
                    $statusLabel = $status !== '' ? ucfirst(str_replace('_', ' ', $status)) : 'Approved';
                    foreach ($partners as $partner) {
                        $partner = is_array($partner) ? $partner : [];
                        fputcsv($out, [
                            $serial,
                            $item['reference'] ?? '',
                            $item['applicant'] ?? '',
                            $phone,
                            $item['block'] ?? '',
                            $item['district'] ?? '',
                            $item['hub'] ?? '',
                            $source,
                            $statusLabel,
                            $item['submitted_by'] ?? '',
                            $item['date'] ?? '',
                            $partner['partner_name'] ?? '',
                            $partner['linkage_mode_label'] ?? ($item['linkage_mode'] ?? ''),
                            $partner['linkage_date_display'] ?? '',
                            $partner['link_url'] ?? '',
                            ! empty($partner['has_document']) ? 'Yes' : 'No',
                            $partner['document_name'] ?? '',
                        ]);
                    }
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
