<?php

namespace App\Services\Cfa;

use App\Models\CfaSubmission;
use App\Services\LegacyPhase1\LegacyPhase1DistrictResolver;
use App\Services\LegacyPhase1\LegacyPhase1ListQuery;
use App\Services\LegacyPhase2\LegacyPhase2DistrictResolver;
use Illuminate\Database\Query\Builder;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

class CfaUniversalSearchService
{
    public const MIN_QUERY_LENGTH = 3;

    public const PER_SOURCE_LIMIT = 25;

    /**
     * @return list<array{
     *   source: string,
     *   source_label: string,
     *   id: int,
     *   application_no: string,
     *   applicant_name: string,
     *   phone: string,
     *   district: string,
     *   url: string,
     *   onboard: string
     * }>
     */
    public function search(string $raw): array
    {
        $query = trim($raw);
        if (mb_strlen($query) < self::MIN_QUERY_LENGTH) {
            return [];
        }

        $results = array_merge(
            $this->searchCurrent($query),
            $this->searchPhase1($query),
            $this->searchPhase2($query),
        );

        usort($results, function (array $a, array $b): int {
            $name = strcasecmp($a['applicant_name'], $b['applicant_name']);
            if ($name !== 0) {
                return $name;
            }

            return strcasecmp($a['application_no'], $b['application_no']);
        });

        return $results;
    }

    /**
     * @return list<array{source: string, source_label: string, id: int, application_no: string, applicant_name: string, phone: string, district: string, url: string}>
     */
    private function searchCurrent(string $query): array
    {
        $like = $this->likeTerm($query);

        $rows = CfaSubmission::query()
            ->with(['district:id,name', 'onboardingBatchMembership:id,cfa_submission_id'])
            ->where(function ($q) use ($like, $query): void {
                $q->where('applicant_name', 'like', $like)
                    ->orWhere('phone', 'like', $like)
                    ->orWhere('application_no', 'like', $like);
                if (ctype_digit($query)) {
                    $q->orWhere('id', (int) $query);
                }
            })
            ->orderByDesc('created_at')
            ->limit(self::PER_SOURCE_LIMIT)
            ->get(['id', 'application_no', 'applicant_name', 'phone', 'district_id']);

        return $rows->map(fn (CfaSubmission $row): array => [
            'source' => 'current',
            'source_label' => 'Current MIS',
            'id' => (int) $row->id,
            'application_no' => trim((string) ($row->application_no ?? '')),
            'applicant_name' => trim((string) ($row->applicant_name ?? '')),
            'phone' => trim((string) ($row->phone ?? '')),
            'district' => trim((string) ($row->district?->name ?? '')),
            'url' => route('cfa.search.current', $row),
            'onboard' => $row->onboardingBatchMembership !== null ? 'yes' : 'no',
        ])->all();
    }

    /**
     * @return list<array{source: string, source_label: string, id: int, application_no: string, applicant_name: string, phone: string, district: string, url: string}>
     */
    private function searchPhase1(string $query): array
    {
        if (! $this->phase1Available()) {
            return [];
        }

        $like = $this->likeTerm($query);

        try {
            $builder = LegacyPhase1ListQuery::listQuery();
            $builder->where(function (Builder $q) use ($like, $query): void {
                $q->where('FullName', 'like', $like)
                    ->orWhere('MobileNumber', 'like', $like)
                    ->orWhere('ApplicationNumber', 'like', $like);
                if (ctype_digit($query)) {
                    $q->orWhere('ID', (int) $query);
                }
            });
            $rows = $builder
                ->orderByDesc('ApplicationDate')
                ->orderByDesc('ID')
                ->limit(self::PER_SOURCE_LIMIT)
                ->get();
        } catch (\Throwable) {
            return [];
        }

        return $rows->map(function (object $row): array {
            $enriched = LegacyPhase1DistrictResolver::enrichRow($row);
            $id = (int) ($enriched->legacy_id ?? $enriched->ID ?? 0);

            return [
                'source' => 'phase1',
                'source_label' => 'FY 2024-25',
                'id' => $id,
                'application_no' => trim((string) ($enriched->application_no ?? '')),
                'applicant_name' => trim((string) ($enriched->full_name ?? '')),
                'phone' => trim((string) ($enriched->mobile_number ?? '')),
                'district' => trim((string) ($enriched->district_name ?? '')),
                'url' => route('cfa.search.phase1', $id),
                'onboard' => ($enriched->onboard_status ?? '') === 'onboarded' ? 'yes' : 'no',
            ];
        })->filter(fn (array $row): bool => $row['id'] > 0)->values()->all();
    }

    /**
     * @return list<array{source: string, source_label: string, id: int, application_no: string, applicant_name: string, phone: string, district: string, url: string}>
     */
    private function searchPhase2(string $query): array
    {
        if (! $this->phase2Available()) {
            return [];
        }

        $like = $this->likeTerm($query);

        try {
            $builder = DB::connection('legacy')
                ->table('rbi_applicant_details as d')
                ->join(DB::raw('(
                    SELECT application_id, MAX(id) AS max_id
                    FROM rbi_applicant_details
                    GROUP BY application_id
                ) as d_pick'), 'd_pick.max_id', '=', 'd.id')
                ->join('rbi_applications as a', 'a.id', '=', 'd.application_id')
                ->leftJoin(DB::raw('(
                    SELECT application_id, MAX(id) AS max_id
                    FROM rbi_onboarded_applicants
                    GROUP BY application_id
                ) as oa_pick'), 'oa_pick.application_id', '=', 'a.id')
                ->leftJoin('rbi_onboarded_applicants as oa', 'oa.id', '=', 'oa_pick.max_id')
                ->select([
                    'a.id as legacy_id',
                    'a.application_no',
                    'd.applicant_name',
                    'd.phone',
                    'd.district',
                    'oa.status as onboard_status_db',
                ])
                ->where(function (Builder $q) use ($like, $query): void {
                    $q->where('d.applicant_name', 'like', $like)
                        ->orWhere('d.phone', 'like', $like)
                        ->orWhere('a.application_no', 'like', $like);
                    if (ctype_digit($query)) {
                        $id = (int) $query;
                        $q->orWhere('a.id', $id)->orWhere('d.application_id', $id);
                    }
                })
                ->orderByDesc('a.submission_date')
                ->orderByDesc('a.id')
                ->limit(self::PER_SOURCE_LIMIT);

            $rows = $builder->get();
        } catch (\Throwable) {
            return [];
        }

        return $rows->map(function (object $row): array {
            $id = (int) ($row->legacy_id ?? 0);

            return [
                'source' => 'phase2',
                'source_label' => 'FY 2025-26',
                'id' => $id,
                'application_no' => trim((string) ($row->application_no ?? '')),
                'applicant_name' => trim((string) ($row->applicant_name ?? '')),
                'phone' => trim((string) ($row->phone ?? '')),
                'district' => trim((string) ($row->district ?? '')),
                'url' => route('cfa.search.phase2', $id),
                'onboard' => LegacyPhase2DistrictResolver::isOnboardedFromStatus(
                    is_string($row->onboard_status_db ?? null) ? (string) $row->onboard_status_db : null
                ) ? 'yes' : 'no',
            ];
        })->filter(fn (array $row): bool => $row['id'] > 0)->values()->all();
    }

    private function phase1Available(): bool
    {
        if ((string) config('database.connections.legacy_phase1.database', '') === '') {
            return false;
        }

        try {
            return Schema::connection('legacy_phase1')->hasTable('tblapplication');
        } catch (\Throwable) {
            return false;
        }
    }

    private function phase2Available(): bool
    {
        if ((string) config('database.connections.legacy.database', '') === '') {
            return false;
        }

        try {
            return Schema::connection('legacy')->hasTable('rbi_applications')
                && Schema::connection('legacy')->hasTable('rbi_applicant_details');
        } catch (\Throwable) {
            return false;
        }
    }

    private function likeTerm(string $query): string
    {
        $escaped = str_replace(['\\', '%', '_'], ['\\\\', '\\%', '\\_'], $query);

        return '%'.$escaped.'%';
    }
}
