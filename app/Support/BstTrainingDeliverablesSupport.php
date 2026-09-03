<?php

namespace App\Support;

use Carbon\Carbon;
use Illuminate\Database\Query\Builder;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * MIS 3.1 / 3.2 — conducted BST sessions (training_packages) and module-aware participations.
 *
 * 3.2 counts each incubatee once per session when that session covers at least one module
 * they have not completed before. Multi-module sessions still count as one per person.
 */
final class BstTrainingDeliverablesSupport
{
    public static function tableReady(): bool
    {
        return Schema::hasTable('training_packages');
    }

    public static function eventDateColumn(): string
    {
        return Schema::hasColumn('training_packages', 'event_date') ? 'event_date' : 'created_at';
    }

    /**
     * @param  list<int>|null  $districtIds
     */
    public static function scopedPackagesQuery(?array $districtIds, ?Carbon $periodFrom, ?Carbon $periodTo): Builder
    {
        $query = DB::table('training_packages as tp')
            ->leftJoin('districts as d', 'd.id', '=', 'tp.district_id')
            ->leftJoin('hubs as h', 'h.id', '=', 'd.hub_id');

        if ($districtIds !== null) {
            if ($districtIds === []) {
                $query->whereRaw('0 = 1');
            } else {
                $query->whereIn('tp.district_id', $districtIds);
            }
        }

        self::applyEventDatePeriod($query, 'tp', $periodFrom, $periodTo);
        self::excludeDrafts($query);

        return $query;
    }

    /**
     * @param  list<int>|null  $districtIds
     */
    public static function countUniqueParticipants(?array $districtIds, ?Carbon $periodFrom, ?Carbon $periodTo): int
    {
        if (! self::tableReady()) {
            return 0;
        }

        $packages = self::participantPackagesQuery($districtIds, $periodFrom, $periodTo)->get();

        return (int) self::moduleAwareParticipationBreakdownFromPackages($packages)['total'];
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
    public static function participantBreakdown(?array $districtIds, ?Carbon $periodFrom, ?Carbon $periodTo): array
    {
        if (! self::tableReady()) {
            return [
                'total' => 0,
                'by_district' => [],
                'by_hub' => [],
                'by_month' => [],
                'records' => [],
            ];
        }

        $hasLegacyModule = Schema::hasColumn('training_packages', 'training_package');
        $moduleSelect = $hasLegacyModule ? 'tp.training_package' : 'NULL as training_package';
        $hasMonthSession = Schema::hasTable('training_package_month_sessions')
            && Schema::hasColumn('training_packages', 'month_session_id');

        $query = self::scopedPackagesQuery($districtIds, $periodFrom, $periodTo);
        if ($hasMonthSession) {
            $query->leftJoin('training_package_month_sessions as ms', 'ms.id', '=', 'tp.month_session_id');
        }

        $select = [
            'tp.id as package_id',
            'tp.selected_incubatee_ids',
            'tp.selected_incubatees_snapshot',
            'tp.training_packages',
            DB::raw($moduleSelect),
            'tp.event_date',
            'tp.training_batch_name',
            DB::raw('COALESCE(d.name, tp.district_name, \'Unknown\') as district_name'),
            DB::raw('COALESCE(h.name, \'—\') as hub_name'),
        ];
        if ($hasMonthSession) {
            $select[] = 'ms.session_name';
        }

        $packages = $query
            ->select($select)
            ->orderBy('tp.'.self::eventDateColumn())
            ->orderBy('tp.id')
            ->get();

        $participantData = self::moduleAwareParticipationBreakdownFromPackages($packages);
        $total = (int) ($participantData['total'] ?? 0);

        $byDistrict = [];
        foreach ($participantData['by_district'] as $district => $count) {
            $byDistrict[] = [
                'district' => $district,
                'hub' => (string) ($participantData['hub_by_district'][$district] ?? '—'),
                'count' => (int) $count,
                'share_pct' => $total > 0 ? (int) round(((int) $count / $total) * 100) : 0,
            ];
        }

        $hubCounts = [];
        foreach ($byDistrict as $row) {
            $hub = (string) ($row['hub'] ?? '—');
            $hubCounts[$hub] = ($hubCounts[$hub] ?? 0) + (int) $row['count'];
        }
        arsort($hubCounts);
        $byHub = [];
        foreach ($hubCounts as $hub => $count) {
            $byHub[] = [
                'hub' => $hub,
                'count' => (int) $count,
                'share_pct' => $total > 0 ? (int) round(((int) $count / $total) * 100) : 0,
            ];
        }

        $byMonth = [];
        foreach ($participantData['by_month'] as $monthKey => $count) {
            try {
                $label = Carbon::createFromFormat('Y-m', $monthKey)->format('M Y');
            } catch (\Throwable) {
                $label = $monthKey;
            }
            $byMonth[] = [
                'month' => $label,
                'month_key' => $monthKey,
                'count' => (int) $count,
                'share_pct' => $total > 0 ? (int) round(((int) $count / $total) * 100) : 0,
            ];
        }

        return [
            'total' => $total,
            'by_district' => $byDistrict,
            'by_hub' => $byHub,
            'by_month' => $byMonth,
            'records' => $participantData['records'],
        ];
    }

    /**
     * @param  \Illuminate\Database\Query\Builder  $query
     */
    public static function excludeDrafts($query): void
    {
        if (! Schema::hasColumn('training_packages', 'is_draft')) {
            return;
        }

        $query->where(function ($q): void {
            $q->where('tp.is_draft', false)->orWhereNull('tp.is_draft');
        });
    }

    public static function applyEventDatePeriod($query, string $alias, ?Carbon $periodFrom, ?Carbon $periodTo): void
    {
        if (! $periodFrom || ! $periodTo) {
            return;
        }

        $column = $alias.'.'.self::eventDateColumn();
        $query->whereBetween($column, [
            $periodFrom->toDateString(),
            $periodTo->toDateString(),
        ]);
    }

    /**
     * @return list<int>
     */
    public static function parseIncubateeIds(mixed $raw): array
    {
        if (is_array($raw)) {
            $ids = $raw;
        } elseif (is_string($raw)) {
            $decoded = json_decode($raw, true);
            $ids = is_array($decoded) ? $decoded : [];
        } else {
            $ids = [];
        }

        return collect($ids)
            ->map(fn ($id) => (int) $id)
            ->filter(fn (int $id) => $id > 0)
            ->values()
            ->all();
    }

    /**
     * @param  iterable<object|array<string, mixed>>  $rows
     * @return list<int>
     */
    public static function uniqueIncubateeIdsFromPackageRows(iterable $rows): array
    {
        $unique = [];
        foreach ($rows as $row) {
            $raw = is_array($row)
                ? ($row['selected_incubatee_ids'] ?? null)
                : ($row->selected_incubatee_ids ?? null);
            foreach (self::parseIncubateeIds($raw) as $id) {
                $unique[$id] = true;
            }
        }

        return array_map('intval', array_keys($unique));
    }

    /**
     * @return list<string>
     */
    public static function moduleCodes(mixed $trainingPackagesRaw, mixed $legacyPackage = null): array
    {
        $modules = [];
        if (is_string($trainingPackagesRaw)) {
            $decoded = json_decode($trainingPackagesRaw, true);
            $trainingPackagesRaw = is_array($decoded) ? $decoded : [];
        }
        if (is_array($trainingPackagesRaw)) {
            foreach ($trainingPackagesRaw as $code) {
                $code = strtolower(trim((string) $code));
                if (in_array($code, ['t1', 't2', 't3', 't4'], true)) {
                    $modules[] = $code;
                }
            }
        }
        if ($modules === [] && $legacyPackage !== null && trim((string) $legacyPackage) !== '') {
            $code = strtolower(trim((string) $legacyPackage));
            if (in_array($code, ['t1', 't2', 't3', 't4'], true)) {
                $modules[] = $code;
            }
        }

        return array_values(array_unique($modules));
    }

    public static function modulesLabel(mixed $trainingPackagesRaw, mixed $legacyPackage = null): string
    {
        $modules = self::moduleCodes($trainingPackagesRaw, $legacyPackage);
        if ($modules === []) {
            return 'BST';
        }

        return implode(', ', array_map('strtoupper', $modules));
    }

    /**
     * @return array{name: string, application_no: string}
     */
    public static function participantProfileFromSnapshots(mixed $snapshotsRaw, int $incubateeId): array
    {
        if (is_string($snapshotsRaw)) {
            $decoded = json_decode($snapshotsRaw, true);
            $snapshotsRaw = is_array($decoded) ? $decoded : [];
        }
        if (! is_array($snapshotsRaw)) {
            return ['name' => '—', 'application_no' => '—'];
        }

        foreach ($snapshotsRaw as $snap) {
            if (! is_array($snap)) {
                continue;
            }
            if ((int) ($snap['incubatee_id'] ?? 0) !== $incubateeId) {
                continue;
            }
            $name = trim((string) ($snap['name'] ?? $snap['applicant_name'] ?? ''));
            $applicationNo = trim((string) ($snap['application_no'] ?? ''));

            return [
                'name' => $name !== '' ? $name : '—',
                'application_no' => $applicationNo !== '' ? $applicationNo : '—',
            ];
        }

        return ['name' => '—', 'application_no' => '—'];
    }

    /**
     * @param  array<string, mixed>  $session
     */
    public static function formatSessionLabel(array $session): string
    {
        $parts = [];
        $eventDate = trim((string) ($session['event_date'] ?? ''));
        if ($eventDate !== '') {
            try {
                $parts[] = Carbon::parse($eventDate)->format('d M Y');
            } catch (\Throwable) {
                $parts[] = $eventDate;
            }
        }

        $title = trim((string) ($session['session_name'] ?? ''));
        if ($title === '') {
            $title = trim((string) ($session['training_batch_name'] ?? ''));
        }
        if ($title === '') {
            $packageId = (int) ($session['package_id'] ?? 0);
            $title = $packageId > 0 ? 'BST #'.$packageId : 'BST session';
        }
        $parts[] = $title;

        $modules = self::modulesLabel($session['training_packages'] ?? null, $session['training_package'] ?? null);
        if ($modules !== 'BST') {
            $parts[] = $modules;
        }

        return implode(' · ', $parts);
    }

    /**
     * @param  list<int>  $incubateeIds
     * @param  array<int, array{name: string, application_no: string}>  $profiles
     */
    public static function enrichProfilesFromCfa(array $incubateeIds, array &$profiles): void
    {
        $missing = [];
        foreach ($incubateeIds as $id) {
            $profile = $profiles[$id] ?? ['name' => '—', 'application_no' => '—'];
            if (($profile['name'] ?? '—') === '—' || ($profile['application_no'] ?? '—') === '—') {
                $missing[] = $id;
            }
        }
        $missing = array_values(array_unique(array_filter($missing, fn (int $id) => $id > 0)));
        if ($missing === [] || ! Schema::hasTable('cfa_submissions')) {
            return;
        }

        $rows = DB::table('cfa_submissions')
            ->whereIn('id', $missing)
            ->get(['id', 'applicant_name', 'application_no']);

        foreach ($rows as $row) {
            $id = (int) $row->id;
            if (! isset($profiles[$id])) {
                $profiles[$id] = ['name' => '—', 'application_no' => '—'];
            }
            if (($profiles[$id]['name'] ?? '—') === '—') {
                $name = trim((string) ($row->applicant_name ?? ''));
                if ($name !== '') {
                    $profiles[$id]['name'] = $name;
                }
            }
            if (($profiles[$id]['application_no'] ?? '—') === '—') {
                $appNo = trim((string) ($row->application_no ?? ''));
                if ($appNo !== '') {
                    $profiles[$id]['application_no'] = $appNo;
                }
            }
        }
    }

    /**
     * @param  list<int>|null  $districtIds
     */
    private static function participantPackagesQuery(?array $districtIds, ?Carbon $periodFrom, ?Carbon $periodTo): Builder
    {
        $hasLegacyModule = Schema::hasColumn('training_packages', 'training_package');
        $moduleSelect = $hasLegacyModule ? 'tp.training_package' : 'NULL as training_package';

        return self::scopedPackagesQuery($districtIds, $periodFrom, $periodTo)
            ->select([
                'tp.id as package_id',
                'tp.selected_incubatee_ids',
                'tp.training_packages',
                DB::raw($moduleSelect),
                'tp.event_date',
            ])
            ->orderBy('tp.'.self::eventDateColumn())
            ->orderBy('tp.id');
    }

    /**
     * @return list<string>
     */
    public static function sessionModuleKeys(mixed $trainingPackagesRaw, mixed $legacyPackage = null): array
    {
        $modules = self::moduleCodes($trainingPackagesRaw, $legacyPackage);

        return $modules !== [] ? $modules : ['bst'];
    }

    /**
     * Count participations in chronological session order.
     * Each incubatee counts once per session when at least one session module is new to them.
     *
     * @param  iterable<object>  $packages
     * @return array{
     *   total: int,
     *   by_district: array<string, int>,
     *   hub_by_district: array<string, string>,
     *   by_month: array<string, int>,
     *   records: list<array<string, mixed>>
     * }
     */
    public static function moduleAwareParticipationBreakdownFromPackages(iterable $packages): array
    {
        /** @var array<int, array<string, true>> $completedModules */
        $completedModules = [];
        $byDistrict = [];
        $hubByDistrict = [];
        $byMonth = [];
        /** @var list<array<string, mixed>> $records */
        $records = [];
        /** @var array<int, array{name: string, application_no: string}> $profiles */
        $profiles = [];

        foreach ($packages as $pkg) {
            $districtName = trim((string) ($pkg->district_name ?? 'Unknown'));
            if ($districtName === '') {
                $districtName = 'Unknown';
            }
            $hubName = trim((string) ($pkg->hub_name ?? ''));
            $hubName = $hubName !== '' ? $hubName : '—';
            $eventDate = (string) ($pkg->event_date ?? '');
            $monthKey = $eventDate !== ''
                ? Carbon::parse($eventDate)->format('Y-m')
                : '';
            $modules = self::sessionModuleKeys($pkg->training_packages ?? null, $pkg->training_package ?? null);
            $session = [
                'package_id' => (int) ($pkg->package_id ?? 0),
                'district_name' => $districtName,
                'hub_name' => $hubName,
                'event_date' => $eventDate,
                'session_name' => (string) ($pkg->session_name ?? ''),
                'training_batch_name' => (string) ($pkg->training_batch_name ?? ''),
                'training_packages' => $pkg->training_packages ?? null,
                'training_package' => $pkg->training_package ?? null,
                'snapshots' => $pkg->selected_incubatees_snapshot ?? null,
            ];
            $sessionLabel = self::formatSessionLabel($session);
            $modulesLabel = self::modulesLabel($pkg->training_packages ?? null, $pkg->training_package ?? null);

            foreach (self::parseIncubateeIds($pkg->selected_incubatee_ids ?? null) as $incubateeId) {
                $done = $completedModules[$incubateeId] ?? [];
                $hasNewModule = false;
                foreach ($modules as $module) {
                    if (! isset($done[$module])) {
                        $hasNewModule = true;
                        break;
                    }
                }
                if (! $hasNewModule) {
                    continue;
                }

                foreach ($modules as $module) {
                    $completedModules[$incubateeId][$module] = true;
                }

                $byDistrict[$districtName] = ($byDistrict[$districtName] ?? 0) + 1;
                $hubByDistrict[$districtName] = $hubName;
                if ($monthKey !== '') {
                    $byMonth[$monthKey] = ($byMonth[$monthKey] ?? 0) + 1;
                }

                if (! isset($profiles[$incubateeId])) {
                    $profiles[$incubateeId] = self::participantProfileFromSnapshots(
                        $pkg->selected_incubatees_snapshot ?? null,
                        $incubateeId,
                    );
                }

                $records[] = [
                    'id' => $incubateeId,
                    'reference' => '—',
                    'applicant' => '—',
                    'district' => $districtName,
                    'hub' => $hubName,
                    'service' => $sessionLabel !== '' ? $sessionLabel : '—',
                    'sessions' => $sessionLabel !== '' ? [$sessionLabel] : [],
                    'session_count' => 1,
                    'spoc' => $modulesLabel,
                    'status' => 'Counted participation',
                    'date' => $eventDate !== ''
                        ? Carbon::parse($eventDate)->format('d M Y')
                        : '—',
                    'sort_name' => '',
                    'sort_date' => $eventDate !== '' ? $eventDate : '9999-12-31',
                ];
            }
        }

        self::enrichProfilesFromCfa(array_keys($profiles), $profiles);

        foreach ($records as &$record) {
            $profile = $profiles[(int) ($record['id'] ?? 0)] ?? ['name' => '—', 'application_no' => '—'];
            $record['applicant'] = $profile['name'];
            $record['reference'] = $profile['application_no'];
            $record['sort_name'] = (string) $profile['name'];
        }
        unset($record);

        usort(
            $records,
            static fn (array $a, array $b): int => [$a['sort_name'], $a['sort_date'], (int) ($a['id'] ?? 0)]
                <=> [$b['sort_name'], $b['sort_date'], (int) ($b['id'] ?? 0)],
        );

        foreach ($records as &$record) {
            unset($record['sort_name'], $record['sort_date']);
        }
        unset($record);

        arsort($byDistrict);
        ksort($byMonth);

        return [
            'total' => count($records),
            'by_district' => $byDistrict,
            'hub_by_district' => $hubByDistrict,
            'by_month' => $byMonth,
            'records' => $records,
        ];
    }

    /**
     * @deprecated Use moduleAwareParticipationBreakdownFromPackages()
     *
     * @param  iterable<object>  $packages
     * @return array{
     *   total: int,
     *   by_district: array<string, int>,
     *   hub_by_district: array<string, string>,
     *   by_month: array<string, int>,
     *   records: list<array<string, mixed>>
     * }
     */
    public static function uniqueParticipantBreakdownFromPackages(iterable $packages): array
    {
        return self::moduleAwareParticipationBreakdownFromPackages($packages);
    }
}
