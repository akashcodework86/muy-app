<?php

namespace App\Services\Exports;

use App\Models\LakhpatiTechnicalTraining;
use App\Support\MisFieldActivityApproval;
use Carbon\Carbon;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * 3.3.1 Technical Trainings — SHG members audience (excludes CBO Network agency).
 * Counts sheet + session detail + every participant row.
 */
final class Phase3331ShgMembersPackDataService
{
    private const TABLE = 'potential_lakhpati_technical_trainings';

    /** CBO-only requesting agency — excluded from this SHG-members pack. */
    private const EXCLUDED_AGENCIES = ['cbo_network'];

    /**
     * @return array<string, mixed>
     */
    public function build(?Carbon $asOf = null): array
    {
        $asOf = ($asOf ?? now())->copy()->endOfDay();
        $periodFrom = Carbon::parse((string) config('program_deliverables.phase3_floor_date', '2026-04-01'))->startOfDay();
        $periodTo = $asOf->copy()->endOfDay();

        if (! Schema::hasTable(self::TABLE)) {
            return $this->emptyPack($periodFrom, $periodTo, $asOf);
        }

        $query = DB::table(self::TABLE.' as t')
            ->leftJoin('districts as d', 'd.id', '=', 't.district_id')
            ->leftJoin('hubs as h', 'h.id', '=', 'd.hub_id')
            ->leftJoin('gram_panchayats as gp', 'gp.id', '=', 't.gram_panchayat_id')
            ->whereNotIn('t.requesting_agency_type', self::EXCLUDED_AGENCIES)
            ->whereBetween('t.session_date', [$periodFrom->toDateString(), $periodTo->toDateString()]);

        MisFieldActivityApproval::applyApprovedOnlyFilter($query, self::TABLE, 't');

        $sessions = $query
            ->select([
                't.*',
                DB::raw("COALESCE(d.name, t.district_name, 'Unknown') as resolved_district"),
                DB::raw("COALESCE(h.name, '—') as hub_name"),
                DB::raw("COALESCE(gp.name, '—') as gp_name"),
            ])
            ->orderByDesc('t.session_date')
            ->orderByDesc('t.id')
            ->get();

        $sessionDetails = [];
        $participantDetails = [];
        $byDistrict = [];
        $byMonth = [];
        $totalMale = 0;
        $totalFemale = 0;
        $totalParticipants = 0;
        $sessionsWithPhotos = 0;
        $sessionsWithAttendance = 0;
        $namedParticipants = 0;

        foreach ($sessions as $session) {
            $district = trim((string) ($session->resolved_district ?? 'Unknown')) ?: 'Unknown';
            $hub = trim((string) ($session->hub_name ?? '')) ?: '—';
            $male = (int) ($session->male_participants ?? 0);
            $female = (int) ($session->female_participants ?? 0);
            $total = (int) ($session->participants_total ?? 0);
            if ($total <= 0) {
                $total = $male + $female;
            }

            $monthKey = '';
            $displayDate = '—';
            $rawDate = trim((string) ($session->session_date ?? ''));
            if ($rawDate !== '') {
                try {
                    $parsed = Carbon::parse($rawDate);
                    $monthKey = $parsed->format('Y-m');
                    $displayDate = $parsed->format('d M Y');
                } catch (\Throwable) {
                    $displayDate = $rawDate;
                }
            }

            $agencyKey = strtolower(trim((string) ($session->requesting_agency_type ?? '')));
            $agencyLabel = LakhpatiTechnicalTraining::AGENCY_TYPES[$agencyKey]
                ?? ucfirst(str_replace('_', ' ', $agencyKey));

            $mode = match (strtolower(trim((string) ($session->workshop_mode ?? '')))) {
                'virtual' => 'Virtual workshop',
                'physical' => 'Physical workshop',
                default => 'Physical workshop',
            };

            $attendanceCount = $this->mediaCount($session->attendance_media_json ?? null);
            $photoCount = $this->mediaCount($session->workshop_photos_json ?? null);
            if ($attendanceCount > 0) {
                $sessionsWithAttendance++;
            }
            if ($photoCount > 0) {
                $sessionsWithPhotos++;
            }

            $totalMale += $male;
            $totalFemale += $female;
            $totalParticipants += $total;
            $byDistrict[$district] = ($byDistrict[$district] ?? 0) + 1;
            if ($monthKey !== '') {
                $byMonth[$monthKey] = ($byMonth[$monthKey] ?? 0) + 1;
            }

            $sessionDetails[] = [
                'session_id' => (int) $session->id,
                'session_date' => $displayDate,
                'session_title' => trim((string) ($session->session_title ?? '')) ?: '—',
                'session_brief' => trim((string) ($session->session_brief ?? '')),
                'requesting_agency' => $agencyLabel,
                'workshop_mode' => $mode,
                'district' => $district,
                'hub' => $hub,
                'block' => trim((string) ($session->block ?? '')) ?: '—',
                'gram_panchayat' => trim((string) ($session->gp_name ?? '')) ?: '—',
                'venue' => trim((string) ($session->area ?? '')) ?: '—',
                'male' => $male,
                'female' => $female,
                'participants_total' => $total,
                'attendance_files' => $attendanceCount,
                'workshop_photos' => $photoCount,
                'submitted_by' => trim((string) ($session->submitted_by_name ?? '')) ?: '—',
                'status' => trim((string) ($session->status ?? '')) ?: '—',
                'approved_at' => $this->formatDateTime($session->approved_at ?? null),
            ];

            $participants = $this->decodeParticipants($session->participants_json ?? null);
            if ($participants === []) {
                // Placeholder rows so detail count can still relate to headcount when names missing.
                for ($i = 1; $i <= $total; $i++) {
                    $participantDetails[] = [
                        'session_id' => (int) $session->id,
                        'session_date' => $displayDate,
                        'session_title' => trim((string) ($session->session_title ?? '')) ?: '—',
                        'district' => $district,
                        'hub' => $hub,
                        'block' => trim((string) ($session->block ?? '')) ?: '—',
                        'venue' => trim((string) ($session->area ?? '')) ?: '—',
                        'requesting_agency' => $agencyLabel,
                        'sr' => $i,
                        'name' => '(name not recorded)',
                        'mobile' => '—',
                        'gender' => '—',
                        'participant_district' => $district,
                        'participant_block' => trim((string) ($session->block ?? '')) ?: '—',
                        'participant_gp' => trim((string) ($session->gp_name ?? '')) ?: '—',
                    ];
                }
            } else {
                foreach ($participants as $p) {
                    $name = trim((string) ($p['name'] ?? ''));
                    if ($name !== '') {
                        $namedParticipants++;
                    }
                    $gender = strtoupper(trim((string) ($p['gender'] ?? '')));
                    $genderLabel = match ($gender) {
                        'M' => 'Male',
                        'F' => 'Female',
                        default => $gender !== '' ? $gender : '—',
                    };

                    $participantDetails[] = [
                        'session_id' => (int) $session->id,
                        'session_date' => $displayDate,
                        'session_title' => trim((string) ($session->session_title ?? '')) ?: '—',
                        'district' => $district,
                        'hub' => $hub,
                        'block' => trim((string) ($session->block ?? '')) ?: '—',
                        'venue' => trim((string) ($session->area ?? '')) ?: '—',
                        'requesting_agency' => $agencyLabel,
                        'sr' => (int) ($p['sr'] ?? 0),
                        'name' => $name !== '' ? $name : '(name not recorded)',
                        'mobile' => trim((string) ($p['mobile'] ?? '')) ?: '—',
                        'gender' => $genderLabel,
                        'participant_district' => trim((string) ($p['district_name'] ?? '')) ?: $district,
                        'participant_block' => trim((string) ($p['block_name'] ?? '')) ?: (trim((string) ($session->block ?? '')) ?: '—'),
                        'participant_gp' => trim((string) ($p['gram_panchayat_name'] ?? '')) ?: '—',
                    ];
                }
            }
        }

        $sessionCount = count($sessionDetails);

        $summary = [];
        $summary[] = ['section' => '3.3.1 Overview', 'metric' => 'Approved sessions (SHG members audience)', 'count' => $sessionCount];
        $summary[] = ['section' => '3.3.1 Overview', 'metric' => 'Male participants', 'count' => $totalMale];
        $summary[] = ['section' => '3.3.1 Overview', 'metric' => 'Female participants', 'count' => $totalFemale];
        $summary[] = ['section' => '3.3.1 Overview', 'metric' => 'Total participants', 'count' => $totalParticipants];
        $summary[] = ['section' => '3.3.1 Overview', 'metric' => 'Named participant rows in detail', 'count' => $namedParticipants];
        $summary[] = ['section' => '3.3.1 Overview', 'metric' => 'Sessions with attendance files', 'count' => $sessionsWithAttendance];
        $summary[] = ['section' => '3.3.1 Overview', 'metric' => 'Sessions with workshop photos', 'count' => $sessionsWithPhotos];

        $byAgency = [];
        foreach ($sessionDetails as $s) {
            $ag = (string) ($s['requesting_agency'] ?? '—');
            $byAgency[$ag] = ($byAgency[$ag] ?? 0) + 1;
        }
        arsort($byAgency);
        foreach ($byAgency as $agency => $count) {
            $summary[] = ['section' => 'By requesting agency (sessions)', 'metric' => $agency, 'count' => (int) $count];
        }

        arsort($byDistrict);
        foreach ($byDistrict as $district => $count) {
            $summary[] = ['section' => 'By district (sessions)', 'metric' => (string) $district, 'count' => (int) $count];
        }

        ksort($byMonth);
        foreach ($byMonth as $monthKey => $count) {
            $monthLabel = (string) $monthKey;
            try {
                $monthLabel = Carbon::createFromFormat('Y-m', (string) $monthKey)->format('M Y');
            } catch (\Throwable) {
                // keep key
            }
            $summary[] = ['section' => 'By month (sessions)', 'metric' => $monthLabel, 'count' => (int) $count];
        }

        return [
            'meta' => [
                'title' => '3.3.1 Technical Trainings to Potential Lakhpati Didis / SHG Members / CBOs — SHG members only',
                'period_from' => $periodFrom->toDateString(),
                'period_to' => $periodTo->toDateString(),
                'as_of' => $asOf->timezone(config('app.timezone'))->format('d M Y, g:i A T'),
                'rules' => [
                    'Indicator: 3.3.1 Technical Trainings to Potential Lakhpati Didis/ SHG Members/ CBOs',
                    'SHG members pack: all approved 3.3.1 sessions except requesting agency = CBO Network',
                    'Includes SHG Federation, NRLM/USRLM, REAP, Line Department, UYRP, Other',
                    'Status: approved only (same as deliverables)',
                    'Period: session_date 1 Apr 2026 → as-of (till date)',
                    'Deliverables count sessions; this pack also lists every participant row',
                ],
            ],
            'summary_rows' => $summary,
            'sessions' => $sessionDetails,
            'participants' => $participantDetails,
        ];
    }

    /**
     * @return array<string, mixed>
     */
    private function emptyPack(Carbon $periodFrom, Carbon $periodTo, Carbon $asOf): array
    {
        return [
            'meta' => [
                'title' => '3.3.1 Technical Trainings — SHG members only',
                'period_from' => $periodFrom->toDateString(),
                'period_to' => $periodTo->toDateString(),
                'as_of' => $asOf->timezone(config('app.timezone'))->format('d M Y, g:i A T'),
                'rules' => ['Table potential_lakhpati_technical_trainings is missing — run migrations.'],
            ],
            'summary_rows' => [
                ['section' => '3.3.1 Overview', 'metric' => 'Approved sessions (SHG members audience)', 'count' => 0],
            ],
            'sessions' => [],
            'participants' => [],
        ];
    }

    /**
     * @return list<array<string, mixed>>
     */
    private function decodeParticipants(mixed $json): array
    {
        if (is_array($json)) {
            return array_values(array_filter($json, 'is_array'));
        }

        $raw = trim((string) $json);
        if ($raw === '' || $raw === 'null' || $raw === '[]') {
            return [];
        }

        $decoded = json_decode($raw, true);

        return is_array($decoded) ? array_values(array_filter($decoded, 'is_array')) : [];
    }

    private function mediaCount(mixed $json): int
    {
        if (is_array($json)) {
            $items = $json;
        } else {
            $raw = trim((string) $json);
            if ($raw === '' || $raw === 'null' || $raw === '[]') {
                return 0;
            }
            $decoded = json_decode($raw, true);
            $items = is_array($decoded) ? $decoded : [];
        }

        return count(array_filter(
            $items,
            static fn ($item): bool => is_array($item) && (string) ($item['path'] ?? '') !== '',
        ));
    }

    private function formatDateTime(mixed $value): string
    {
        $raw = trim((string) $value);
        if ($raw === '') {
            return '—';
        }

        try {
            return Carbon::parse($raw)->timezone(config('app.timezone'))->format('d M Y, g:i A');
        } catch (\Throwable) {
            return $raw;
        }
    }
}
