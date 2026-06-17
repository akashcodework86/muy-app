<?php

namespace App\Support;

use App\Models\BlockWorkshop;
use App\Models\DistrictWorkshopSession;
use App\Models\EapEdpSession;
use App\Models\LakhpatiTechnicalTraining;
use App\Models\TechnicalTraining;
use App\Models\TrainingPackage;
use Illuminate\Support\Collection;
use Symfony\Component\HttpFoundation\StreamedResponse;

class WorkshopDashboardCsvExport
{
    /**
     * @param  list<string>  $headers
     * @param  iterable<int, list<string|int>>  $dataRows
     */
    public static function download(array $headers, iterable $dataRows, string $filename): StreamedResponse
    {
        return response()->streamDownload(function () use ($headers, $dataRows): void {
            $out = fopen('php://output', 'w');
            if ($out === false) {
                return;
            }

            fwrite($out, "\xEF\xBB\xBF");
            fputcsv($out, $headers);

            foreach ($dataRows as $row) {
                fputcsv($out, $row);
            }

            fclose($out);
        }, $filename, [
            'Content-Type' => 'text/csv; charset=UTF-8',
        ]);
    }

    /**
     * @param  Collection<int, DistrictWorkshopSession>  $rows
     */
    public static function districtWorkshopSessions(Collection $rows, string $filename): StreamedResponse
    {
        $headers = [
            'Sr. No.',
            'Date of Session',
            'Session Taken By',
            'District',
            'Block',
            'Gram panchayat',
            'Workshop',
            'Venue',
            'Notes',
            'Male',
            'Female',
            'Total',
            'Participant rows',
            'Attendance sheet',
            'Images',
        ];

        $dataRows = [];
        $sr = 0;
        foreach ($rows as $entry) {
            if (! $entry instanceof DistrictWorkshopSession) {
                continue;
            }
            $sr++;
            $dataRows[] = self::districtWorkshopRow($entry, $sr);
        }

        return self::download($headers, $dataRows, $filename);
    }

    /**
     * @return list<string>
     */
    public static function districtWorkshopRow(DistrictWorkshopSession $entry, int $sr): array
    {
        $male = (int) ($entry->male_participants ?? 0);
        $female = (int) ($entry->female_participants ?? 0);

        return [
            (string) $sr,
            (string) ($entry->event_date?->format('d M Y') ?? 'NA'),
            (string) $entry->submitted_by_name,
            (string) ($entry->district_name ?: ($entry->district?->name ?? 'NA')),
            self::sessionBlockLabel($entry),
            self::sessionGpLabel($entry),
            (string) $entry->formatted_workshop_mode,
            (string) ($entry->display_venue ?: '—'),
            (string) ($entry->notes ?? ''),
            (string) $male,
            (string) $female,
            (string) $entry->totalParticipantCount(),
            self::sessionParticipantRowsLabel($entry, $male, $female),
            self::attendanceSheetLabel($entry),
            (string) self::mediaFileCount((array) $entry->workshop_photos_json),
        ];
    }

    /**
     * @param  Collection<int, EapEdpSession>  $rows
     */
    public static function eapEdpSessions(Collection $rows, string $filename): StreamedResponse
    {
        $headers = [
            'Sr. No.',
            'Date of Session',
            'Session Taken By',
            'District',
            'Block',
            'Gram panchayat',
            'Workshop',
            'Venue',
            'Notes',
            'Male',
            'Female',
            'Total',
            'Participant rows',
            'Attendance sheet',
            'Images',
        ];

        $dataRows = [];
        $sr = 0;
        foreach ($rows as $entry) {
            if (! $entry instanceof EapEdpSession) {
                continue;
            }
            $sr++;
            $dataRows[] = self::eapEdpSessionRow($entry, $sr);
        }

        return self::download($headers, $dataRows, $filename);
    }

    /**
     * @return list<string>
     */
    public static function eapEdpSessionRow(EapEdpSession $entry, int $sr): array
    {
        $male = (int) ($entry->attendance_male_count ?? 0);
        $female = (int) ($entry->attendance_female_count ?? 0);
        $total = (int) ($entry->attendance_total_count ?? ($male + $female));

        return [
            (string) $sr,
            (string) ($entry->event_date?->format('d M Y') ?? 'NA'),
            (string) $entry->submitted_by_name,
            (string) ($entry->district_name ?: ($entry->district?->name ?? 'NA')),
            self::sessionBlockLabel($entry),
            self::sessionGpLabel($entry),
            (string) $entry->formatted_workshop_mode,
            (string) ($entry->display_venue ?: '—'),
            (string) ($entry->notes ?? ''),
            (string) $male,
            (string) $female,
            (string) $total,
            self::sessionParticipantRowsLabel($entry, $male, $female),
            self::attendanceSheetLabel($entry),
            (string) self::mediaFileCount((array) $entry->session_photos_json),
        ];
    }

    /**
     * @param  Collection<int, TechnicalTraining>  $rows
     */
    public static function technicalTrainings(Collection $rows, string $filename): StreamedResponse
    {
        $headers = [
            'Sr. No.',
            'Date of Session',
            'Session Taken By',
            'District',
            'Session Name',
            'Session Brief',
            'Male',
            'Female',
            'Other/NA',
            'Total',
        ];

        $dataRows = [];
        $sr = 0;
        foreach ($rows as $entry) {
            if (! $entry instanceof TechnicalTraining) {
                continue;
            }
            $sr++;
            $counts = IncubateeAttendeeCounts::fromTrainingRecord($entry);

            $dataRows[] = [
                (string) $sr,
                (string) ($entry->event_date?->format('d M Y') ?? 'NA'),
                (string) $entry->submitted_by_name,
                (string) ($entry->district_name ?: ($entry->district?->name ?? 'NA')),
                (string) $entry->session_name,
                (string) ($entry->session_brief ?? ''),
                (string) $counts['male'],
                (string) $counts['female'],
                (string) ($counts['other'] ?? 0),
                (string) $counts['total'],
            ];
        }

        return self::download($headers, $dataRows, $filename);
    }

    /**
     * @param  Collection<int, TrainingPackage>  $rows
     */
    public static function trainingPackages(Collection $rows, string $filename): StreamedResponse
    {
        $headers = [
            'Sr. No.',
            'Date of Session',
            'Session Taken By',
            'District',
            'Session Name',
            'Workshop',
            'Training Modules',
            'Male',
            'Female',
            'Other/NA',
            'Total',
        ];

        $dataRows = [];
        $sr = 0;
        foreach ($rows as $entry) {
            if (! $entry instanceof TrainingPackage) {
                continue;
            }
            $sr++;
            $moduleList = (array) ($entry->training_packages ?? [$entry->training_package]);
            $moduleLabel = strtoupper(implode(', ', array_values(array_filter($moduleList))));
            $sessionName = trim((string) ($entry->monthSession?->session_name ?? ''));
            if ($sessionName === '') {
                $sessionName = '—';
            }
            if ($entry->monthSession?->is_extra) {
                $sessionName .= ' (Extra)';
            }
            $counts = IncubateeAttendeeCounts::fromTrainingRecord($entry);

            $workshopLabel = match ((string) ($entry->workshop_delivery ?? '')) {
                'virtual' => 'Virtual',
                'physical' => 'Physical',
                default => '—',
            };

            $dataRows[] = [
                (string) $sr,
                (string) ($entry->event_date?->format('d M Y') ?? 'NA'),
                (string) $entry->submitted_by_name,
                (string) ($entry->district_name ?: ($entry->district?->name ?? 'NA')),
                $sessionName,
                $workshopLabel,
                $moduleLabel !== '' ? $moduleLabel : 'NA',
                (string) $counts['male'],
                (string) $counts['female'],
                (string) ($counts['other'] ?? 0),
                (string) $counts['total'],
            ];
        }

        return self::download($headers, $dataRows, $filename);
    }

    /**
     * @param  Collection<int, BlockWorkshop>  $rows
     */
    public static function blockWorkshopsAdmin(Collection $rows, string $filename): StreamedResponse
    {
        $headers = [
            'Sr. No.',
            'Date',
            'Submitted by',
            'District',
            'Location (block)',
            'Gram panchayat (participants)',
            'Male',
            'Female',
            'Total',
            'Participant rows',
            'Photos',
        ];

        $dataRows = [];
        $sr = 0;
        foreach ($rows as $entry) {
            if (! $entry instanceof BlockWorkshop) {
                continue;
            }
            $sr++;
            $dataRows[] = self::blockWorkshopAdminRow($entry, $sr);
        }

        return self::download($headers, $dataRows, $filename);
    }

    /**
     * @return list<string>
     */
    public static function blockWorkshopAdminRow(BlockWorkshop $entry, int $sr): array
    {
        return [
            (string) $sr,
            (string) ($entry->visit_date?->format('d M Y') ?? 'NA'),
            (string) ($entry->field_coordinator_name ?: ($entry->coordinator?->name ?? '—')),
            (string) ($entry->district?->name ?? '—'),
            self::blockWorkshopLocationLabel($entry),
            self::blockWorkshopGpLabel($entry),
            (string) (int) ($entry->participants_male_count ?? 0),
            (string) (int) ($entry->participants_female_count ?? 0),
            (string) (int) ($entry->participants_total ?? 0),
            self::blockWorkshopParticipantRowsLabel($entry),
            (string) count($entry->visitMediaItems()),
        ];
    }

    /**
     * @param  Collection<int, BlockWorkshop>  $rows
     */
    public static function blockWorkshopsStaff(Collection $rows, string $filename): StreamedResponse
    {
        $headers = [
            'Sr. No.',
            'Date',
            'Submitted by',
            'Location',
            'Gram panchayat (participants)',
            'Male',
            'Female',
            'Total',
            'Participants',
            'Photos',
            'Sheet',
        ];

        $dataRows = [];
        $sr = 0;
        foreach ($rows as $entry) {
            if (! $entry instanceof BlockWorkshop) {
                continue;
            }
            $sr++;
            $dataRows[] = self::blockWorkshopStaffRow($entry, $sr);
        }

        return self::download($headers, $dataRows, $filename);
    }

    /**
     * @return list<string>
     */
    public static function blockWorkshopStaffRow(BlockWorkshop $entry, int $sr): array
    {
        $location = self::blockWorkshopLocationLabel($entry);
        $districtName = trim((string) ($entry->district?->name ?? ''));
        if ($districtName !== '' && $location !== '—') {
            $location .= ' | '.$districtName;
        } elseif ($districtName !== '') {
            $location = $districtName;
        }

        return [
            (string) $sr,
            (string) ($entry->visit_date?->format('d M Y') ?? 'NA'),
            (string) ($entry->field_coordinator_name ?: ($entry->coordinator?->name ?? '—')),
            $location,
            self::blockWorkshopGpLabel($entry),
            (string) (int) ($entry->participants_male_count ?? 0),
            (string) (int) ($entry->participants_female_count ?? 0),
            (string) (int) ($entry->participants_total ?? 0),
            self::blockWorkshopParticipantRowsLabel($entry),
            (string) count($entry->visitMediaItems()),
            $entry->hasAttendanceSheet() ? 'Uploaded' : 'Pending',
        ];
    }

    private static function sessionBlockLabel(DistrictWorkshopSession|EapEdpSession $entry): string
    {
        $first = ($entry->participantRows()[0] ?? []);
        $block = trim((string) ($first['block_name'] ?? ''));

        return $block !== '' ? $block : '—';
    }

    private static function sessionGpLabel(DistrictWorkshopSession|EapEdpSession $entry): string
    {
        $first = ($entry->participantRows()[0] ?? []);
        $gp = trim((string) ($first['gram_panchayat_name'] ?? ''));

        return $gp !== '' ? $gp : '—';
    }

    private static function sessionParticipantRowsLabel(
        DistrictWorkshopSession|EapEdpSession $entry,
        int $male,
        int $female,
    ): string {
        $participantRows = $entry->participantRows();
        $rowCount = count($participantRows);
        $filledRows = collect($participantRows)->filter(fn ($p) => ! empty($p['name']))->count();

        if ($rowCount > 0) {
            return $filledRows.'/'.$rowCount.' filled';
        }

        $headcount = $male + $female;
        if ($headcount > 0) {
            return $headcount.' rows pending';
        }

        return '—';
    }

    private static function attendanceSheetLabel(DistrictWorkshopSession|EapEdpSession $entry): string
    {
        $count = self::mediaFileCount((array) $entry->attendance_media_json);

        return $count > 0 ? 'Uploaded ('.$count.' file'.($count === 1 ? '' : 's').')' : 'Pending';
    }

    private static function mediaFileCount(array $items): int
    {
        $count = 0;
        foreach ($items as $media) {
            if (is_array($media) && (string) ($media['path'] ?? '') !== '') {
                $count++;
            }
        }

        return $count;
    }

    private static function blockWorkshopLocationLabel(BlockWorkshop $entry): string
    {
        $parts = array_values(array_filter([
            trim((string) ($entry->area ?? '')),
            trim((string) ($entry->block ?? '')),
        ]));

        return $parts !== [] ? implode(' | ', $parts) : '—';
    }

    private static function blockWorkshopGpLabel(BlockWorkshop $entry): string
    {
        $gpCounts = $entry->participantCountsByGramPanchayat();
        if ($gpCounts === []) {
            $name = trim((string) ($entry->gramPanchayat?->name ?? ''));

            return $name !== '' ? $name : '—';
        }

        $lines = [];
        $workshopGp = trim((string) ($entry->gramPanchayat?->name ?? ''));
        if ($workshopGp !== '') {
            $lines[] = 'Workshop GP: '.$workshopGp;
        }
        foreach ($gpCounts as $gp) {
            $lines[] = ($gp['name'] ?? '').': '.(int) ($gp['count'] ?? 0);
        }

        return implode('; ', $lines);
    }

    private static function blockWorkshopParticipantRowsLabel(BlockWorkshop $entry): string
    {
        $participantRows = $entry->participantRows();
        $filledRows = collect($participantRows)->filter(fn ($p) => ! empty($p['name']))->count();
        $rowCount = count($participantRows);

        if ($rowCount > 0) {
            return $filledRows.'/'.$rowCount.' filled';
        }

        if ((int) $entry->participants_total > 0) {
            return (int) $entry->participants_total.' headcount';
        }

        return '—';
    }

    /**
     * @param  Collection<int, LakhpatiTechnicalTraining>  $rows
     */
    public static function lakhpatiTechnicalTrainings(Collection $rows, string $filename): StreamedResponse
    {
        $headers = [
            'Sr. No.',
            'Date of Session',
            'Session Taken By',
            'District',
            'Block',
            'Gram panchayat',
            'Venue',
            'Workshop mode',
            'Requesting agency',
            'Title',
            'Brief',
            'Male',
            'Female',
            'Total',
            'Attendance files',
            'Workshop photos',
        ];

        $dataRows = [];
        $sr = 0;
        foreach ($rows as $entry) {
            if (! $entry instanceof LakhpatiTechnicalTraining) {
                continue;
            }
            $sr++;
            $dataRows[] = [
                (string) $sr,
                (string) ($entry->session_date?->format('d M Y') ?? 'NA'),
                (string) $entry->submitted_by_name,
                (string) ($entry->district_name ?: ($entry->district?->name ?? 'NA')),
                (string) ($entry->block ?? '—'),
                (string) ($entry->gramPanchayat?->name ?? '—'),
                (string) ($entry->area ?? '—'),
                (string) $entry->formattedWorkshopMode(),
                (string) $entry->agencyTypeLabel(),
                (string) $entry->session_title,
                (string) ($entry->session_brief ?? ''),
                (string) (int) ($entry->male_participants ?? 0),
                (string) (int) ($entry->female_participants ?? 0),
                (string) $entry->totalParticipantCount(),
                (string) self::mediaFileCount((array) $entry->attendance_media_json),
                (string) self::mediaFileCount((array) $entry->workshop_photos_json),
            ];
        }

        return self::download($headers, $dataRows, $filename);
    }
}
