<?php

namespace App\Services;

use App\Models\CfaSubmission;
use App\Models\FiscalYear;
use App\Models\ServiceCase;
use App\Services\LegacyPhase1\LegacyPhase1DistrictResolver;
use App\Services\LegacyPhase1\LegacyPhase1ListQuery;
use App\Support\AccelerationServicesOptions;
use Carbon\Carbon;
use Illuminate\Http\Request;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

class AccelerationServicesIncubateeService
{
    public function __construct(
        private readonly LegacyPhase1ApplicationDetailService $phase1Details,
    ) {}

    /**
     * All services recorded for a Phase 1 applicant: legacy Phase 1 fields, MIS service cases, and acceleration 7.2 entries.
     *
     * @return list<array<string, mixed>>
     */
    public function allServicesGiven(int $legacyPhase1ApplicationId, string $incubateeKey): array
    {
        if ($legacyPhase1ApplicationId <= 0) {
            return [];
        }

        $applicationDate = $this->findPhase1Applicant($legacyPhase1ApplicationId)['application_date'] ?? '—';
        $sortBase = $this->parseSortTimestamp($applicationDate);

        $rows = array_merge(
            $this->phase1LegacyServiceRows($legacyPhase1ApplicationId, $applicationDate, $sortBase),
            $this->misServiceCaseRows($legacyPhase1ApplicationId),
            $this->accelerationServiceRows($incubateeKey),
        );

        usort($rows, function (array $a, array $b): int {
            $left = (int) ($a['sort_timestamp'] ?? 0);
            $right = (int) ($b['sort_timestamp'] ?? 0);
            if ($left !== $right) {
                return $right <=> $left;
            }

            return strcmp((string) ($a['label'] ?? ''), (string) ($b['label'] ?? ''));
        });

        return array_map(static function (array $row): array {
            unset($row['sort_timestamp']);

            return $row;
        }, $rows);
    }

    /**
     * Prior acceleration form items for an incubatee (for prefill on a new entry).
     * Latest non-draft item wins per item_key. Optionally exclude the session being edited.
     *
     * @return list<array{
     *   item_key: string,
     *   section: string,
     *   item_label: string,
     *   payload: array<string, mixed>,
     *   assigned_by: string,
     *   service_date: string|null,
     *   status: string|null,
     *   session_id: int,
     *   media: list<array{id:int,name:string}>
     * }>
     */
    public function priorAccelerationFormItems(string $incubateeKey, ?int $excludeSessionId = null): array
    {
        if ($incubateeKey === '' || ! Schema::hasTable('acceleration_service_sessions') || ! Schema::hasTable('acceleration_service_items')) {
            return [];
        }

        $sessionsQuery = DB::table('acceleration_service_sessions as s')
            ->where('s.incubatee_key', $incubateeKey)
            ->orderBy('s.service_date')
            ->orderBy('s.id');

        if ($excludeSessionId !== null && $excludeSessionId > 0) {
            $sessionsQuery->where('s.id', '!=', $excludeSessionId);
        }
        if (Schema::hasColumn('acceleration_service_sessions', 'is_draft')) {
            $sessionsQuery->where('s.is_draft', false);
        } elseif (Schema::hasColumn('acceleration_service_sessions', 'status')) {
            $sessionsQuery->where('s.status', '!=', 'draft');
        }

        $sessions = $sessionsQuery->get(['s.id', 's.service_date', 's.submitted_by_name', 's.status']);
        if ($sessions->isEmpty()) {
            return [];
        }

        $sessionIds = $sessions->pluck('id')->map(fn ($id) => (int) $id)->all();
        $sessionMeta = $sessions->keyBy('id');

        $items = DB::table('acceleration_service_items')
            ->whereIn('session_id', $sessionIds)
            ->orderBy('id')
            ->get();

        $mediaByItem = collect();
        if (Schema::hasTable('acceleration_service_item_media')) {
            $mediaByItem = DB::table('acceleration_service_item_media')
                ->whereIn('item_id', $items->pluck('id')->all())
                ->orderBy('id')
                ->get(['id', 'item_id', 'original_name'])
                ->groupBy('item_id');
        }

        /** @var array<string, array<string, mixed>> $byKey */
        $byKey = [];
        foreach ($items as $item) {
            $key = (string) ($item->item_key ?? '');
            if ($key === '' || $key === 'soft_skills') {
                continue;
            }

            $session = $sessionMeta->get($item->session_id);
            $payload = $item->payload ?? [];
            if (is_string($payload)) {
                $decoded = json_decode($payload, true);
                $payload = is_array($decoded) ? $decoded : [];
            } elseif (! is_array($payload)) {
                $payload = [];
            }

            $mediaRows = $mediaByItem->get((int) $item->id, collect());
            $byKey[$key] = [
                'item_key' => $key,
                'section' => (string) ($item->section ?? 'service_detail'),
                'item_label' => (string) ($item->item_label ?? $key),
                'payload' => $payload,
                'assigned_by' => (string) ($session->submitted_by_name ?? '—'),
                'service_date' => $session && $session->service_date
                    ? Carbon::parse($session->service_date)->format('d M Y')
                    : null,
                'status' => isset($session->status) ? (string) $session->status : null,
                'session_id' => (int) $item->session_id,
                'media' => $mediaRows->map(static fn ($m) => [
                    'id' => (int) $m->id,
                    'name' => (string) $m->original_name,
                ])->values()->all(),
            ];
        }

        return array_values($byKey);
    }

    /**
     * @return list<array<string, mixed>>
     */
    private function phase1LegacyServiceRows(int $legacyPhase1ApplicationId, string $applicationDate, int $sortBase): array
    {
        $services = $this->phase1Details->servicesForLegacyId($legacyPhase1ApplicationId);
        $rows = [];

        foreach ($services as $service) {
            $label = trim((string) ($service['label'] ?? ''));
            if ($label === '') {
                continue;
            }

            $detail = trim((string) ($service['detail'] ?? ''));
            $rows[] = [
                'label' => $label,
                'detail' => $detail !== '' ? $detail : null,
                'date' => $applicationDate !== '—' ? $applicationDate : '—',
                'assigned_by' => 'Phase 1 record',
                'source' => 'phase1',
                'source_label' => 'Phase 1 record',
                'media_count' => 0,
                'badge' => null,
                'status' => null,
                'sort_timestamp' => $sortBase,
            ];
        }

        return $rows;
    }

    /**
     * @return list<array<string, mixed>>
     */
    private function misServiceCaseRows(int $legacyPhase1ApplicationId): array
    {
        if (! Schema::hasTable('service_cases') || ! Schema::hasTable('cfa_submissions')) {
            return [];
        }

        $cfaIds = CfaSubmission::query()
            ->where('source', 'legacy_phase1')
            ->where(function ($query) use ($legacyPhase1ApplicationId): void {
                $query->where('payload->legacy_phase1_id', $legacyPhase1ApplicationId)
                    ->orWhere('payload->legacy_id', $legacyPhase1ApplicationId);
            })
            ->pluck('id');

        if ($cfaIds->isEmpty()) {
            return [];
        }

        $cases = ServiceCase::query()
            ->with(['service:id,name', 'submitter:id,name', 'creator:id,name'])
            ->whereIn('cfa_submission_id', $cfaIds)
            ->orderByDesc('created_at')
            ->limit(100)
            ->get();

        $rows = [];
        foreach ($cases as $case) {
            $createdAt = $case->created_at;
            $rows[] = [
                'label' => (string) ($case->service?->name ?? 'Service'),
                'detail' => null,
                'date' => $createdAt ? $createdAt->timezone(config('app.timezone'))->format('d M Y') : '—',
                'assigned_by' => (string) ($case->submitter?->name ?? $case->creator?->name ?? '—'),
                'source' => 'mis',
                'source_label' => 'MIS service case',
                'media_count' => 0,
                'badge' => null,
                'status' => (string) ($case->status ?? ''),
                'sort_timestamp' => $createdAt ? $createdAt->timestamp : 0,
            ];
        }

        return $rows;
    }

    /**
     * @return list<array<string, mixed>>
     */
    private function accelerationServiceRows(string $incubateeKey): array
    {
        if ($incubateeKey === '' || ! Schema::hasTable('acceleration_service_sessions')) {
            return [];
        }

        $sessions = DB::table('acceleration_service_sessions as s')
            ->where('s.incubatee_key', $incubateeKey)
            ->orderByDesc('s.service_date')
            ->orderByDesc('s.id')
            ->limit(50)
            ->get();

        if ($sessions->isEmpty()) {
            return [];
        }

        $sessionIds = $sessions->pluck('id')->map(fn ($id) => (int) $id)->all();
        $items = collect();

        if (Schema::hasTable('acceleration_service_items')) {
            $items = DB::table('acceleration_service_items')
                ->whereIn('session_id', $sessionIds)
                ->orderBy('id')
                ->get()
                ->groupBy('session_id');
        }

        $mediaCounts = collect();
        if (Schema::hasTable('acceleration_service_item_media') && Schema::hasTable('acceleration_service_items')) {
            $mediaCounts = DB::table('acceleration_service_item_media as m')
                ->join('acceleration_service_items as i', 'i.id', '=', 'm.item_id')
                ->whereIn('i.session_id', $sessionIds)
                ->selectRaw('i.id as item_id, COUNT(*) as media_count')
                ->groupBy('i.id')
                ->pluck('media_count', 'item_id');
        }

        $rows = [];
        foreach ($sessions as $session) {
            $sid = (int) $session->id;
            $sessionItems = $items->get($sid, collect());
            $serviceDate = $session->service_date ? Carbon::parse($session->service_date)->format('d M Y') : '—';
            $sortTimestamp = $session->service_date ? Carbon::parse($session->service_date)->timestamp : 0;
            $badge = (bool) ($session->counts_for_7_2 ?? false) ? 'initiation' : 'follow-up';

            if ($sessionItems->isEmpty()) {
                $rows[] = [
                    'label' => 'Acceleration services',
                    'detail' => null,
                    'date' => $serviceDate,
                    'assigned_by' => (string) ($session->submitted_by_name ?? '—'),
                    'source' => 'acceleration',
                    'source_label' => 'Acceleration 7.2',
                    'media_count' => 0,
                    'badge' => $badge,
                    'status' => null,
                    'sort_timestamp' => $sortTimestamp,
                ];

                continue;
            }

            foreach ($sessionItems as $item) {
                $itemId = (int) $item->id;
                $remarks = trim((string) ($item->remarks ?? ''));
                $rows[] = [
                    'label' => trim((string) ($item->item_label ?? 'Service')),
                    'detail' => $remarks !== '' ? $remarks : null,
                    'date' => $serviceDate,
                    'assigned_by' => (string) ($session->submitted_by_name ?? '—'),
                    'source' => 'acceleration',
                    'source_label' => 'Acceleration 7.2',
                    'media_count' => (int) ($mediaCounts[$itemId] ?? 0),
                    'badge' => $badge,
                    'status' => null,
                    'sort_timestamp' => $sortTimestamp,
                ];
            }
        }

        return $rows;
    }

    private function parseSortTimestamp(string $date): int
    {
        if ($date === '' || $date === '—') {
            return 0;
        }

        try {
            return Carbon::parse($date)->timestamp;
        } catch (\Throwable) {
            return 0;
        }
    }
    /**
     * @return list<array<string, mixed>>
     */
    public function search(Request $request): array
    {
        if ((string) config('database.connections.legacy_phase1.database', '') === '') {
            return [];
        }

        try {
            if (! Schema::connection('legacy_phase1')->hasTable('tblapplication')) {
                return [];
            }
        } catch (\Throwable) {
            return [];
        }

        $query = LegacyPhase1ListQuery::listQuery();
        LegacyPhase1ListQuery::applyFilters($query, $request, null, true);

        $rows = $query
            ->orderByDesc('ApplicationDate')
            ->orderByDesc('ID')
            ->limit(200)
            ->get();

        $out = [];
        foreach ($rows as $row) {
            $enriched = LegacyPhase1DistrictResolver::enrichRow($row);
            $legacyId = (int) ($enriched->legacy_id ?? 0);
            if ($legacyId <= 0) {
                continue;
            }

            $out[] = [
                'legacy_phase1_application_id' => $legacyId,
                'incubatee_key' => AccelerationServicesOptions::incubateeKey($legacyId),
                'applicant_name' => trim((string) ($enriched->full_name ?? '')),
                'application_no' => trim((string) ($enriched->application_no ?? '')),
                'phone' => trim((string) ($enriched->mobile_number ?? '')),
                'district_name' => trim((string) ($enriched->district_name ?? '')),
                'block_name' => trim((string) ($enriched->city_name ?? '')),
                'onboard_label' => trim((string) ($enriched->onboard_label ?? 'Non onboarded')),
                'application_date' => $this->formatDate($enriched->application_date ?? null),
            ];
        }

        return $out;
    }

    /**
     * @return array<string, mixed>|null
     */
    public function findPhase1Applicant(int $legacyPhase1ApplicationId): ?array
    {
        if ($legacyPhase1ApplicationId <= 0) {
            return null;
        }

        if ((string) config('database.connections.legacy_phase1.database', '') === '') {
            return null;
        }

        try {
            $row = LegacyPhase1ListQuery::baseTable()
                ->where('ID', $legacyPhase1ApplicationId)
                ->select(LegacyPhase1DistrictResolver::applicationSelectColumns())
                ->first();
        } catch (\Throwable) {
            return null;
        }

        if (! $row) {
            return null;
        }

        $enriched = LegacyPhase1DistrictResolver::enrichRow($row);

        return [
            'legacy_phase1_application_id' => $legacyPhase1ApplicationId,
            'incubatee_key' => AccelerationServicesOptions::incubateeKey($legacyPhase1ApplicationId),
            'applicant_name' => trim((string) ($enriched->full_name ?? '')),
            'application_no' => trim((string) ($enriched->application_no ?? '')),
            'phone' => trim((string) ($enriched->mobile_number ?? '')),
            'district_name' => trim((string) ($enriched->district_name ?? '')),
            'block_name' => trim((string) ($enriched->city_name ?? '')),
            'onboard_label' => trim((string) ($enriched->onboard_label ?? 'Non onboarded')),
            'application_date' => $this->formatDate($enriched->application_date ?? null),
        ];
    }

    public function resolveFiscalYearIdForDate(string $date): ?int
    {
        $parsed = Carbon::parse($date)->startOfDay();

        $fy = FiscalYear::query()
            ->whereDate('starts_on', '<=', $parsed)
            ->whereDate('ends_on', '>=', $parsed)
            ->orderByDesc('starts_on')
            ->first();

        return $fy ? (int) $fy->id : null;
    }

    public function shouldCountFor72(string $incubateeKey, ?int $fiscalYearId): bool
    {
        if ($incubateeKey === '' || ! $fiscalYearId || ! Schema::hasTable('acceleration_service_sessions')) {
            return true;
        }

        return ! DB::table('acceleration_service_sessions')
            ->where('incubatee_key', $incubateeKey)
            ->where('fiscal_year_id', $fiscalYearId)
            ->where('counts_for_7_2', true)
            ->exists();
    }

    private function formatDate(mixed $value): string
    {
        if ($value === null || $value === '') {
            return '—';
        }

        try {
            return Carbon::parse((string) $value)->format('d M Y');
        } catch (\Throwable) {
            return (string) $value;
        }
    }
}
