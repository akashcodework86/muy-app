<?php

namespace App\Services\Exports;

use App\Support\BstTrainingDeliverablesSupport;
use App\Support\MisFieldActivityApproval;
use App\Support\PotentialLakhpatiOnboardingSql;
use Carbon\Carbon;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * Technical trainings dashboard (/admin/technical-trainings) attendance,
 * filtered to Phase-3 SHG members only (Individual + Member of SHG/CBO = Yes).
 */
final class Phase3331ShgMembersPackDataService
{
    private const TABLE = 'technical_trainings';

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

        $query = DB::table(self::TABLE.' as tt')
            ->leftJoin('districts as d', 'd.id', '=', 'tt.district_id')
            ->leftJoin('hubs as h', 'h.id', '=', 'd.hub_id')
            ->whereBetween('tt.event_date', [$periodFrom->toDateString(), $periodTo->toDateString()]);

        MisFieldActivityApproval::applyApprovedOnlyFilter($query, self::TABLE, 'tt');

        $sessions = $query
            ->select([
                'tt.id',
                'tt.event_date',
                'tt.session_name',
                'tt.session_brief',
                'tt.training_batch_name',
                'tt.submitted_by_name',
                'tt.status',
                'tt.approved_at',
                'tt.selected_incubatee_ids',
                'tt.selected_incubatees_snapshot',
                'tt.attendance_media_json',
                DB::raw("COALESCE(d.name, tt.district_name, 'Unknown') as district_name"),
                DB::raw("COALESCE(h.name, '—') as hub_name"),
            ])
            ->orderByDesc('tt.event_date')
            ->orderByDesc('tt.id')
            ->get();

        $allIds = BstTrainingDeliverablesSupport::uniqueIncubateeIdsFromPackageRows($sessions);
        $shgMeta = $this->shgMemberMetaByIds($allIds);
        $shgIdSet = array_fill_keys(array_keys($shgMeta), true);

        $sessionDetails = [];
        $attendanceDetails = [];
        $byDistrictSessions = [];
        $byDistrictParticipations = [];
        $byMonthSessions = [];
        $byMonthParticipations = [];
        $uniqueShg = [];
        $totalShgParticipations = 0;
        $sessionsWithShg = 0;
        $totalAttendanceAll = 0;

        foreach ($sessions as $session) {
            $attendeeIds = BstTrainingDeliverablesSupport::parseIncubateeIds($session->selected_incubatee_ids ?? null);
            $totalAttendanceAll += count($attendeeIds);

            $shgIdsInSession = array_values(array_filter(
                $attendeeIds,
                static fn (int $id): bool => isset($shgIdSet[$id]),
            ));
            if ($shgIdsInSession === []) {
                continue;
            }

            $sessionsWithShg++;
            $district = trim((string) ($session->district_name ?? 'Unknown')) ?: 'Unknown';
            $hub = trim((string) ($session->hub_name ?? '')) ?: '—';

            $monthKey = '';
            $displayDate = '—';
            $rawDate = trim((string) ($session->event_date ?? ''));
            if ($rawDate !== '') {
                try {
                    $parsed = Carbon::parse($rawDate);
                    $monthKey = $parsed->format('Y-m');
                    $displayDate = $parsed->format('d M Y');
                } catch (\Throwable) {
                    $displayDate = $rawDate;
                }
            }

            $sessionTitle = trim((string) ($session->session_name ?? ''));
            if ($sessionTitle === '') {
                $sessionTitle = trim((string) ($session->training_batch_name ?? '')) ?: 'Technical training';
            }

            $shgCount = count($shgIdsInSession);
            $totalShgParticipations += $shgCount;
            $byDistrictSessions[$district] = ($byDistrictSessions[$district] ?? 0) + 1;
            $byDistrictParticipations[$district] = ($byDistrictParticipations[$district] ?? 0) + $shgCount;
            if ($monthKey !== '') {
                $byMonthSessions[$monthKey] = ($byMonthSessions[$monthKey] ?? 0) + 1;
                $byMonthParticipations[$monthKey] = ($byMonthParticipations[$monthKey] ?? 0) + $shgCount;
            }

            $mediaCount = $this->mediaCount($session->attendance_media_json ?? null);

            $sessionDetails[] = [
                'session_id' => (int) $session->id,
                'session_date' => $displayDate,
                'session_title' => $sessionTitle,
                'session_brief' => trim((string) ($session->session_brief ?? '')),
                'batch_name' => trim((string) ($session->training_batch_name ?? '')) ?: '—',
                'district' => $district,
                'hub' => $hub,
                'total_attendance' => count($attendeeIds),
                'shg_members_attendance' => $shgCount,
                'attendance_files' => $mediaCount,
                'submitted_by' => trim((string) ($session->submitted_by_name ?? '')) ?: '—',
                'status' => trim((string) ($session->status ?? '')) ?: '—',
                'approved_at' => $this->formatDateTime($session->approved_at ?? null),
            ];

            foreach ($shgIdsInSession as $cfaId) {
                $uniqueShg[$cfaId] = true;
                $meta = $shgMeta[$cfaId];
                $profile = BstTrainingDeliverablesSupport::participantProfileFromSnapshots(
                    $session->selected_incubatees_snapshot ?? null,
                    $cfaId,
                );
                $snap = $this->snapshotForId($session->selected_incubatees_snapshot ?? null, $cfaId);

                $name = $profile['name'] !== '—' ? $profile['name'] : $meta['applicant_name'];
                $appNo = $profile['application_no'] !== '—' ? $profile['application_no'] : $meta['application_no'];

                $attendanceDetails[] = [
                    'session_id' => (int) $session->id,
                    'session_date' => $displayDate,
                    'session_title' => $sessionTitle,
                    'session_district' => $district,
                    'hub' => $hub,
                    'cfa_id' => $cfaId,
                    'application_no' => $appNo,
                    'name' => $name,
                    'phone' => trim((string) ($snap['phone'] ?? $meta['phone'] ?? '')) ?: '—',
                    'gender' => trim((string) ($snap['gender'] ?? $meta['gender'] ?? '')) ?: '—',
                    'block' => trim((string) ($snap['block_name'] ?? $meta['block'] ?? '')) ?: '—',
                    'village' => trim((string) ($snap['village'] ?? $meta['village'] ?? '')) ?: '—',
                    'member_district' => $meta['district_name'] !== '' ? $meta['district_name'] : $district,
                    'onboard_batch' => trim((string) ($snap['onboarding_batch_name'] ?? '')) ?: '—',
                    'onboard_status' => $this->onboardLabel($snap, $meta),
                    'category' => $meta['category'] !== '' ? $meta['category'] : '—',
                    'member_of_shg' => 'Yes',
                ];
            }
        }

        $summary = [];
        $summary[] = ['section' => 'Overview', 'metric' => 'Approved sessions on technical-trainings dashboard (period)', 'count' => $sessions->count()];
        $summary[] = ['section' => 'Overview', 'metric' => 'Sessions with ≥1 SHG member', 'count' => $sessionsWithShg];
        $summary[] = ['section' => 'Overview', 'metric' => 'Total attendance (all incubatees in those sessions)', 'count' => $totalAttendanceAll];
        $summary[] = ['section' => 'Overview', 'metric' => 'SHG member participations (attendance rows)', 'count' => $totalShgParticipations];
        $summary[] = ['section' => 'Overview', 'metric' => 'Unique SHG members trained', 'count' => count($uniqueShg)];

        arsort($byDistrictParticipations);
        foreach ($byDistrictParticipations as $district => $count) {
            $summary[] = [
                'section' => 'By district (SHG participations)',
                'metric' => (string) $district.' ('.$byDistrictSessions[$district].' sessions)',
                'count' => (int) $count,
            ];
        }

        ksort($byMonthParticipations);
        foreach ($byMonthParticipations as $monthKey => $count) {
            $monthLabel = (string) $monthKey;
            try {
                $monthLabel = Carbon::createFromFormat('Y-m', (string) $monthKey)->format('M Y');
            } catch (\Throwable) {
            }
            $summary[] = [
                'section' => 'By month (SHG participations)',
                'metric' => $monthLabel.' ('.$byMonthSessions[$monthKey].' sessions)',
                'count' => (int) $count,
            ];
        }

        foreach ($sessionDetails as $s) {
            $summary[] = [
                'section' => 'By session (SHG attendance)',
                'metric' => '#'.$s['session_id'].' · '.$s['session_date'].' · '.$s['district'].' · '.$s['session_title'],
                'count' => (int) $s['shg_members_attendance'],
            ];
        }

        return [
            'meta' => [
                'title' => 'Technical Trainings (admin/technical-trainings) — SHG members only',
                'period_from' => $periodFrom->toDateString(),
                'period_to' => $periodTo->toDateString(),
                'as_of' => $asOf->timezone(config('app.timezone'))->format('d M Y, g:i A T'),
                'rules' => [
                    'Source: /admin/technical-trainings/dashboard (table technical_trainings)',
                    'Status: approved only',
                    'Period: event_date 1 Apr 2026 → as-of (till date)',
                    'SHG member = Phase 3 Individual with Member of SHG/CBO = Yes (same as Data Centre SHG pack)',
                    'Attendance is the selected incubatee list on each session (full attendance)',
                    'Detail sheets list only SHG members — other attendees are excluded',
                ],
            ],
            'summary_rows' => $summary,
            'sessions' => $sessionDetails,
            'participants' => $attendanceDetails,
        ];
    }

    /**
     * @param  list<int>  $ids
     * @return array<int, array{
     *   applicant_name: string,
     *   application_no: string,
     *   phone: string,
     *   gender: string,
     *   village: string,
     *   block: string,
     *   district_name: string,
     *   category: string,
     *   is_onboarded: bool
     * }>
     */
    private function shgMemberMetaByIds(array $ids): array
    {
        $ids = array_values(array_unique(array_filter(array_map('intval', $ids), fn (int $id): bool => $id > 0)));
        if ($ids === [] || ! Schema::hasTable('cfa_submissions')) {
            return [];
        }

        $rows = DB::table('cfa_submissions as cs')
            ->leftJoin('districts as d', 'd.id', '=', 'cs.district_id')
            ->whereIn('cs.id', $ids)
            ->whereRaw(PotentialLakhpatiOnboardingSql::phase3ShgMembersOnboardingSql())
            ->get([
                'cs.id',
                'cs.applicant_name',
                'cs.application_no',
                'cs.phone',
                'cs.payload',
                DB::raw("COALESCE(d.name, '') as district_name"),
            ]);

        $onboardedIds = [];
        if (Schema::hasTable('onboarding_batch_cfa') && Schema::hasTable('onboarding_batches')) {
            $onboardedIds = DB::table('onboarding_batch_cfa as obc')
                ->join('onboarding_batches as ob', 'ob.id', '=', 'obc.onboarding_batch_id')
                ->whereIn('obc.cfa_submission_id', $ids)
                ->where('ob.status', 'locked')
                ->whereNotNull('ob.locked_at')
                ->pluck('obc.cfa_submission_id')
                ->map(fn ($id): int => (int) $id)
                ->all();
            $onboardedIds = array_fill_keys($onboardedIds, true);
        }

        $meta = [];
        foreach ($rows as $row) {
            $payload = is_array($row->payload) ? $row->payload : (json_decode((string) ($row->payload ?? ''), true) ?: []);
            $id = (int) $row->id;
            $meta[$id] = [
                'applicant_name' => trim((string) ($row->applicant_name ?? '')) ?: '—',
                'application_no' => trim((string) ($row->application_no ?? '')) ?: '—',
                'phone' => trim((string) ($row->phone ?? '')) ?: '—',
                'gender' => trim((string) ($payload['gender'] ?? '')),
                'village' => trim((string) ($payload['village'] ?? '')),
                'block' => trim((string) ($payload['block'] ?? '')),
                'district_name' => trim((string) ($row->district_name ?? '')),
                'category' => trim((string) ($payload['category'] ?? $payload['app_category'] ?? '')),
                'is_onboarded' => isset($onboardedIds[$id]),
            ];
        }

        return $meta;
    }

    /**
     * @return array<string, mixed>
     */
    private function snapshotForId(mixed $snapshotsRaw, int $incubateeId): array
    {
        if (is_string($snapshotsRaw)) {
            $decoded = json_decode($snapshotsRaw, true);
            $snapshotsRaw = is_array($decoded) ? $decoded : [];
        }
        if (! is_array($snapshotsRaw)) {
            return [];
        }

        foreach ($snapshotsRaw as $snap) {
            if (is_array($snap) && (int) ($snap['incubatee_id'] ?? 0) === $incubateeId) {
                return $snap;
            }
        }

        return [];
    }

    /**
     * @param  array<string, mixed>  $snap
     * @param  array<string, mixed>  $meta
     */
    private function onboardLabel(array $snap, array $meta): string
    {
        $label = trim((string) ($snap['onboard_label'] ?? ''));
        if ($label !== '') {
            return $label;
        }
        $batch = trim((string) ($snap['onboarding_batch_name'] ?? ''));
        if ($batch !== '' || ! empty($snap['onboarding_batch_id'])) {
            return 'Onboarded';
        }

        return ! empty($meta['is_onboarded']) ? 'Onboarded' : 'Not onboarded';
    }

    /**
     * @return array<string, mixed>
     */
    private function emptyPack(Carbon $periodFrom, Carbon $periodTo, Carbon $asOf): array
    {
        return [
            'meta' => [
                'title' => 'Technical Trainings — SHG members only',
                'period_from' => $periodFrom->toDateString(),
                'period_to' => $periodTo->toDateString(),
                'as_of' => $asOf->timezone(config('app.timezone'))->format('d M Y, g:i A T'),
                'rules' => ['Table technical_trainings is missing — run migrations.'],
            ],
            'summary_rows' => [
                ['section' => 'Overview', 'metric' => 'SHG member participations (attendance rows)', 'count' => 0],
            ],
            'sessions' => [],
            'participants' => [],
        ];
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
