<?php

namespace App\Support;

use Carbon\Carbon;
use Illuminate\Database\Query\Builder;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

final class TechnicalTrainingPotentialLakhpatiSupport
{
    /**
     * @param  list<int>|null  $districtIds
     */
    public static function countEligibleParticipations(?array $districtIds, ?Carbon $periodFrom, ?Carbon $periodTo): int
    {
        $table = self::tableName();
        if ($table === null) {
            return 0;
        }

        $dateCol = self::eventDateColumn($table);
        $rows = self::scopedSessionsQuery($table, $dateCol, $districtIds, $periodFrom, $periodTo)
            ->get(['tt.selected_incubatee_ids']);

        $ids = BstTrainingDeliverablesSupport::uniqueIncubateeIdsFromPackageRows($rows);
        $cfaMeta = self::cfaMetaByIds($ids);

        $total = 0;
        foreach ($rows as $row) {
            foreach (BstTrainingDeliverablesSupport::parseIncubateeIds($row->selected_incubatee_ids ?? null) as $id) {
                if (self::isEligibleParticipation($cfaMeta[$id] ?? null)) {
                    $total++;
                }
            }
        }

        return $total;
    }

    /**
     * @param  list<int>|null  $districtIds
     * @return array{
     *   total: int,
     *   by_district: list<array{district: string, hub: string, count: int, share_pct: int}>,
     *   by_hub: list<array{hub: string, count: int, share_pct: int}>,
     *   by_month: list<array{month: string, month_key: string, count: int, share_pct: int}>,
     *   records: list<array<string, mixed>>
     * }
     */
    public static function eligibleParticipationsBreakdown(?array $districtIds, ?Carbon $periodFrom, ?Carbon $periodTo): array
    {
        $table = self::tableName();
        if ($table === null) {
            return [
                'total' => 0,
                'by_district' => [],
                'by_hub' => [],
                'by_month' => [],
                'records' => [],
            ];
        }

        $dateCol = self::eventDateColumn($table);
        $hasDistrict = Schema::hasColumn($table, 'district_id');
        $query = self::scopedSessionsQuery($table, $dateCol, $districtIds, $periodFrom, $periodTo);
        if ($hasDistrict) {
            $query->leftJoin('districts as d', 'd.id', '=', 'tt.district_id')
                ->leftJoin('hubs as h', 'h.id', '=', 'd.hub_id');
        }

        $sessions = $query
            ->select([
                'tt.id as session_id',
                'tt.'.$dateCol.' as event_date',
                'tt.session_name',
                'tt.training_batch_name',
                'tt.selected_incubatee_ids',
                'tt.selected_incubatees_snapshot',
                DB::raw($hasDistrict ? "COALESCE(d.name, tt.district_name, 'Unknown') as district_name" : "'Statewide' as district_name"),
                DB::raw($hasDistrict ? "COALESCE(h.name, '—') as hub_name" : "'—' as hub_name"),
            ])
            ->orderByDesc('tt.'.$dateCol)
            ->orderByDesc('tt.id')
            ->get();

        $ids = BstTrainingDeliverablesSupport::uniqueIncubateeIdsFromPackageRows($sessions);
        $cfaMeta = self::cfaMetaByIds($ids);

        $total = 0;
        $byDistrict = [];
        $hubByDistrict = [];
        $byHub = [];
        $byMonth = [];
        $records = [];

        foreach ($sessions as $session) {
            $district = trim((string) ($session->district_name ?? 'Unknown'));
            if ($district === '') {
                $district = 'Unknown';
            }
            $hub = trim((string) ($session->hub_name ?? ''));
            if ($hub === '') {
                $hub = '—';
            }

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

            foreach (BstTrainingDeliverablesSupport::parseIncubateeIds($session->selected_incubatee_ids ?? null) as $id) {
                $meta = $cfaMeta[$id] ?? null;
                if (! self::isEligibleParticipation($meta)) {
                    continue;
                }

                $total++;
                $byDistrict[$district] = ($byDistrict[$district] ?? 0) + 1;
                $hubByDistrict[$district] = $hub;
                $byHub[$hub] = ($byHub[$hub] ?? 0) + 1;
                if ($monthKey !== '') {
                    $byMonth[$monthKey] = ($byMonth[$monthKey] ?? 0) + 1;
                }

                $profile = BstTrainingDeliverablesSupport::participantProfileFromSnapshots(
                    $session->selected_incubatees_snapshot ?? null,
                    $id,
                );
                $name = $profile['name'] ?? '—';
                $applicationNo = $profile['application_no'] ?? '—';
                if ($name === '—' && $meta !== null) {
                    $name = $meta['applicant_name'];
                }
                if ($applicationNo === '—' && $meta !== null) {
                    $applicationNo = $meta['application_no'];
                }

                $sessionTitle = trim((string) ($session->session_name ?? ''));
                if ($sessionTitle === '') {
                    $sessionTitle = trim((string) ($session->training_batch_name ?? ''));
                }
                if ($sessionTitle === '') {
                    $sessionTitle = 'Technical training';
                }

                $records[] = [
                    'id' => (int) ($session->session_id ?? 0),
                    'reference' => $applicationNo !== '' ? $applicationNo : '—',
                    'applicant' => $name !== '' ? $name : '—',
                    'district' => $district,
                    'hub' => $hub,
                    'service' => $sessionTitle,
                    'status' => 'Eligible participation',
                    'date' => $displayDate,
                ];
            }
        }

        $byDistrictRows = [];
        foreach ($byDistrict as $district => $count) {
            $byDistrictRows[] = [
                'district' => (string) $district,
                'hub' => (string) ($hubByDistrict[$district] ?? '—'),
                'count' => (int) $count,
                'share_pct' => $total > 0 ? (int) round(($count / $total) * 100) : 0,
            ];
        }
        usort($byDistrictRows, fn (array $a, array $b): int => $b['count'] <=> $a['count']);

        $byHubRows = [];
        foreach ($byHub as $hub => $count) {
            $byHubRows[] = [
                'hub' => (string) $hub,
                'count' => (int) $count,
                'share_pct' => $total > 0 ? (int) round(($count / $total) * 100) : 0,
            ];
        }
        usort($byHubRows, fn (array $a, array $b): int => $b['count'] <=> $a['count']);

        ksort($byMonth);
        $byMonthRows = [];
        foreach ($byMonth as $monthKey => $count) {
            $monthLabel = $monthKey;
            try {
                $monthLabel = Carbon::createFromFormat('Y-m', (string) $monthKey)->format('M Y');
            } catch (\Throwable) {
                // keep raw month key
            }
            $byMonthRows[] = [
                'month' => $monthLabel,
                'month_key' => (string) $monthKey,
                'count' => (int) $count,
                'share_pct' => $total > 0 ? (int) round(($count / $total) * 100) : 0,
            ];
        }

        return [
            'total' => $total,
            'by_district' => $byDistrictRows,
            'by_hub' => $byHubRows,
            'by_month' => $byMonthRows,
            'records' => $records,
        ];
    }

    private static function tableName(): ?string
    {
        if (Schema::hasTable('technical_training_sessions')) {
            return 'technical_training_sessions';
        }
        if (Schema::hasTable('technical_trainings')) {
            return 'technical_trainings';
        }

        return null;
    }

    private static function eventDateColumn(string $table): string
    {
        $candidates = $table === 'technical_training_sessions'
            ? ['session_date', 'event_date', 'created_at']
            : ['event_date', 'session_date', 'created_at'];

        foreach ($candidates as $col) {
            if (Schema::hasColumn($table, $col)) {
                return $col;
            }
        }

        return 'created_at';
    }

    /**
     * @param  list<int>|null  $districtIds
     */
    private static function scopedSessionsQuery(string $table, string $dateCol, ?array $districtIds, ?Carbon $periodFrom, ?Carbon $periodTo): Builder
    {
        $query = DB::table($table.' as tt');

        if (Schema::hasColumn($table, 'district_id') && $districtIds !== null) {
            if ($districtIds === []) {
                $query->whereRaw('0 = 1');
            } else {
                $query->whereIn('tt.district_id', $districtIds);
            }
        }

        if ($periodFrom && $periodTo) {
            $query->whereBetween('tt.'.$dateCol, [
                $periodFrom->toDateString(),
                $periodTo->toDateString(),
            ]);
        }

        return $query;
    }

    /**
     * @param  list<int>  $ids
     * @return array<int, array{
     *   source: string,
     *   category: string,
     *   app_category: string,
     *   is_member: string,
     *   is_shg_member: string,
     *   applicant_name: string,
     *   application_no: string
     * }>
     */
    private static function cfaMetaByIds(array $ids): array
    {
        $ids = array_values(array_unique(array_filter(array_map('intval', $ids), fn (int $id): bool => $id > 0)));
        if ($ids === [] || ! Schema::hasTable('cfa_submissions')) {
            return [];
        }

        $rows = DB::table('cfa_submissions')
            ->whereIn('id', $ids)
            ->get(['id', 'source', 'payload', 'applicant_name', 'application_no']);

        $meta = [];
        foreach ($rows as $row) {
            $payload = is_array($row->payload) ? $row->payload : (json_decode((string) ($row->payload ?? ''), true) ?: []);
            $meta[(int) $row->id] = [
                'source' => self::norm($row->source ?? ''),
                'category' => self::norm($payload['category'] ?? ''),
                'app_category' => self::norm($payload['app_category'] ?? ''),
                'is_member' => self::norm($payload['is_member'] ?? ''),
                'is_shg_member' => self::norm($payload['is_shg_member'] ?? ''),
                'applicant_name' => trim((string) ($row->applicant_name ?? '')) ?: '—',
                'application_no' => trim((string) ($row->application_no ?? '')) ?: '—',
            ];
        }

        return $meta;
    }

    /**
     * @param  array<string, string>|null  $meta
     */
    private static function isEligibleParticipation(?array $meta): bool
    {
        if ($meta === null) {
            return false;
        }

        if (in_array($meta['source'] ?? '', ['legacy_phase2', 'rbiphase2'], true)) {
            return false;
        }

        $category = $meta['category'] ?? '';
        $appCategory = $meta['app_category'] ?? '';
        $isMemberYes = ($meta['is_member'] ?? '') === 'yes' || ($meta['is_shg_member'] ?? '') === 'yes';

        if (in_array($category, ['shg', 'cbo'], true) || in_array($appCategory, ['shg', 'cbo'], true)) {
            return true;
        }

        $isIndividual = $category === 'individual' || $appCategory === 'individual';

        return $isIndividual && $isMemberYes;
    }

    private static function norm(mixed $value): string
    {
        return mb_strtolower(trim((string) $value));
    }
}

