<?php

namespace App\Services;

use App\Models\CaseStudyShortlist;
use App\Models\CfaSubmission;
use App\Models\District;
use App\Models\User;
use App\Services\Cfa\CfaSubmissionListQuery;
use App\Services\LegacyPhase1\LegacyPhase1DistrictResolver;
use App\Services\LegacyPhase2\LegacyPhase2DistrictResolver;
use App\Support\CaseStudyShortlistAccess;
use Illuminate\Database\Eloquent\Builder as EloquentBuilder;
use Illuminate\Database\Query\Builder;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

class CaseStudyShortlistCandidateCatalog
{
    public const YEARS = [
        '2026-27' => ['source' => 'phase3', 'label' => 'FY 2026-27 · Phase 3'],
        '2025-26' => ['source' => 'phase2', 'label' => 'FY 2025-26 · Phase 2'],
        '2024-25' => ['source' => 'phase1', 'label' => 'FY 2024-25 · Phase 1'],
    ];

    /**
     * @param  array{program_year:string,q:string,block:string,gender:string,category:string,stage:string,district_id:int}  $filters
     * @return Collection<int, array<string, mixed>>
     */
    public function search(User $user, array $filters): Collection
    {
        $programYear = array_key_exists($filters['program_year'], self::YEARS)
            ? $filters['program_year']
            : '2026-27';
        $district = $this->resolveDistrict($user, (int) $filters['district_id']);
        if (! $district) {
            return collect();
        }

        $source = self::YEARS[$programYear]['source'];
        $rows = match ($source) {
            'phase3' => $this->phase3Candidates($district, $filters),
            'phase2' => $this->phase2Candidates($district, $filters),
            'phase1' => $this->phase1Candidates($district, $filters),
        };

        $usedPersonKeys = CaseStudyShortlist::query()->pluck('person_key')->flip();

        return $rows
            ->map(fn (array $row): array => $this->normalizeCandidate($row, $programYear, $district))
            ->reject(fn (array $row): bool => $usedPersonKeys->has($row['person_key']))
            ->sortBy(fn (array $row): string => mb_strtolower($row['applicant_name']))
            ->values();
    }

    /** @return array<string, mixed>|null */
    public function resolve(User $user, string $source, int $sourceId): ?array
    {
        if ($sourceId < 1 || ! in_array($source, ['phase1', 'phase2', 'phase3'], true)) {
            return null;
        }

        $programYear = collect(self::YEARS)->search(fn (array $meta): bool => $meta['source'] === $source);
        if (! is_string($programYear)) {
            return null;
        }

        $districts = $this->accessibleDistricts($user);
        foreach ($districts as $district) {
            $filters = [
                'program_year' => $programYear, 'q' => '', 'block' => '', 'gender' => '',
                'category' => '', 'stage' => '', 'district_id' => (int) $district->id,
            ];
            $rows = match ($source) {
                'phase3' => $this->phase3Candidates($district, $filters, $sourceId),
                'phase2' => $this->phase2Candidates($district, $filters, $sourceId),
                'phase1' => $this->phase1Candidates($district, $filters, $sourceId),
            };
            if ($rows->isNotEmpty()) {
                return $this->normalizeCandidate($rows->first(), $programYear, $district);
            }
        }

        return null;
    }

    /** @return Collection<int, District> */
    public function accessibleDistricts(User $user): Collection
    {
        $query = District::query()->orderBy('sort_order')->orderBy('name');
        if ($user->role === 'district_staff') {
            $query->whereKey((int) $user->district_id);
        } elseif ($user->role === 'hub_admin') {
            $query->where('hub_id', (int) $user->hub_id);
        } elseif ($user->role !== 'state_admin') {
            $query->whereRaw('1 = 0');
        }

        return $query->get(['id', 'hub_id', 'name']);
    }

    /** @return Collection<int, array<string, mixed>> */
    private function phase3Candidates(District $district, array $filters, ?int $sourceId = null): Collection
    {
        try {
            $query = CfaSubmission::query()
                ->where('district_id', $district->id)
                ->whereHas('onboardingBatchMembership')
                ->whereNotIn('source', ['legacy_phase1', 'rbiphase1', 'legacy_phase2', 'rbiphase2']);
            CfaSubmissionListQuery::applyPhase3DashboardScope($query);
            if ($sourceId) {
                $query->whereKey($sourceId);
            }
            $this->applyPhase3Filters($query, $filters);

            if ($sourceId) {
                $query->limit(1);
            }

            return $query->orderBy('applicant_name')->get()
                ->map(function (CfaSubmission $row): array {
                    $payload = is_array($row->payload) ? $row->payload : [];

                    return [
                        'source' => 'phase3', 'source_id' => (int) $row->id,
                        'application_no' => (string) $row->application_no,
                        'applicant_name' => (string) $row->applicant_name,
                        'phone' => (string) $row->phone,
                        'block' => (string) ($payload['block'] ?? ''),
                        'gender' => (string) ($payload['gender'] ?? ''),
                        'category' => (string) ($payload['business_category'] ?? ''),
                        'stage' => (string) ($payload['form_stage'] ?? ''),
                        'applicant_type' => (string) ($payload['category'] ?? ''),
                        'village' => (string) ($payload['village'] ?? ''),
                        'product' => (string) ($payload['product'] ?? ''),
                    ];
                });
        } catch (\Throwable) {
            return collect();
        }
    }

    private function applyPhase3Filters(EloquentBuilder $query, array $filters): void
    {
        if ($filters['q'] !== '') {
            $like = '%'.$filters['q'].'%';
            $query->where(fn ($q) => $q->where('applicant_name', 'like', $like)->orWhere('application_no', 'like', $like)->orWhere('phone', 'like', $like));
        }
        foreach (['block' => 'block', 'gender' => 'gender', 'category' => 'business_category', 'stage' => 'form_stage'] as $filter => $path) {
            if ($filters[$filter] !== '') {
                $query->whereRaw('LOWER(TRIM('.CfaSubmissionListQuery::payloadJsonExpr('$.'.$path).')) = ?', [mb_strtolower($filters[$filter])]);
            }
        }
    }

    /** @return Collection<int, array<string, mixed>> */
    private function phase2Candidates(District $district, array $filters, ?int $sourceId = null): Collection
    {
        try {
            if (! Schema::connection('legacy')->hasTable('rbi_onboarded_applicants')) {
                return collect();
            }
            $query = DB::connection('legacy')->table('rbi_onboarded_applicants as oa')
                ->join('rbi_applications as a', 'a.id', '=', 'oa.application_id')
                ->join('rbi_applicant_details as d', 'd.application_id', '=', 'oa.application_id')
                ->select(['a.id', 'a.application_no', 'a.category as applicant_type', 'a.form_stage', 'a.business_category', 'a.product', 'd.applicant_name', 'd.phone', 'd.block', 'd.village', 'd.gender']);
            LegacyPhase2DistrictResolver::applyDistrictFilter($query, $district->name);
            if ($sourceId) {
                $query->where('a.id', $sourceId);
            }
            $this->applyLegacyFilters($query, $filters, 'd', 'a');

            if ($sourceId) {
                $query->limit(1);
            }

            return $query->orderByDesc('d.id')->get()->unique('id')->map(fn ($row): array => [
                'source' => 'phase2', 'source_id' => (int) $row->id,
                'application_no' => (string) $row->application_no, 'applicant_name' => (string) $row->applicant_name,
                'phone' => (string) $row->phone, 'block' => (string) $row->block, 'village' => (string) $row->village,
                'gender' => (string) $row->gender, 'category' => (string) $row->business_category,
                'stage' => (string) $row->form_stage, 'applicant_type' => (string) $row->applicant_type,
                'product' => (string) $row->product,
            ])->values();
        } catch (\Throwable) {
            return collect();
        }
    }

    private function applyLegacyFilters(Builder $query, array $filters, string $detailAlias, string $appAlias): void
    {
        if ($filters['q'] !== '') {
            $like = '%'.$filters['q'].'%';
            $query->where(fn (Builder $q) => $q->where($detailAlias.'.applicant_name', 'like', $like)->orWhere($detailAlias.'.phone', 'like', $like)->orWhere($appAlias.'.application_no', 'like', $like));
        }
        foreach (['block' => $detailAlias.'.block', 'gender' => $detailAlias.'.gender', 'category' => $appAlias.'.business_category', 'stage' => $appAlias.'.form_stage'] as $filter => $column) {
            if ($filters[$filter] !== '') {
                $query->whereRaw('LOWER(TRIM('.$column.')) = ?', [mb_strtolower($filters[$filter])]);
            }
        }
    }

    /** @return Collection<int, array<string, mixed>> */
    private function phase1Candidates(District $district, array $filters, ?int $sourceId = null): Collection
    {
        try {
            if (! Schema::connection('legacy_phase1')->hasTable('tblapplication')) {
                return collect();
            }
            $query = DB::connection('legacy_phase1')->table('tblapplication');
            LegacyPhase1DistrictResolver::applyDistrictFilter($query, $district->name);
            LegacyPhase1DistrictResolver::applyOnboardFilter($query, 'onboarded');
            if ($sourceId) {
                $query->where('ID', $sourceId);
            }
            if ($filters['q'] !== '') {
                $like = '%'.$filters['q'].'%';
                $query->where(fn (Builder $q) => $q->where('FullName', 'like', $like)->orWhere('MobileNumber', 'like', $like)->orWhere('ApplicationNumber', 'like', $like));
            }
            foreach (['block' => 'City', 'gender' => 'gender', 'category' => 'idea', 'stage' => 'current_status'] as $filter => $column) {
                if ($filters[$filter] !== '') {
                    $query->whereRaw('LOWER(TRIM(`'.$column.'`)) = ?', [mb_strtolower($filters[$filter])]);
                }
            }

            if ($sourceId) {
                $query->limit(1);
            }

            return $query->orderBy('FullName')->get()->map(fn ($row): array => [
                'source' => 'phase1', 'source_id' => (int) $row->ID,
                'application_no' => (string) $row->ApplicationNumber, 'applicant_name' => (string) $row->FullName,
                'phone' => (string) $row->MobileNumber, 'block' => (string) $row->City, 'village' => '',
                'gender' => (string) $row->gender, 'category' => (string) ($row->idea ?: $row->other_idea),
                'stage' => (string) $row->current_status, 'applicant_type' => (string) $row->Occupationtype,
                'product' => (string) $row->enterprise_name,
            ]);
        } catch (\Throwable) {
            return collect();
        }
    }

    private function resolveDistrict(User $user, int $requestedId): ?District
    {
        $id = $user->role === 'district_staff' ? (int) $user->district_id : $requestedId;
        if ($id < 1 || ! CaseStudyShortlistAccess::canAccessDistrict($user, $id)) {
            return null;
        }

        return District::query()->find($id);
    }

    /** @param array<string, mixed> $row @return array<string, mixed> */
    private function normalizeCandidate(array $row, string $programYear, District $district): array
    {
        $phone = preg_replace('/\D+/', '', (string) ($row['phone'] ?? '')) ?? '';
        $source = (string) $row['source'];
        $sourceId = (int) $row['source_id'];

        return $row + [
            'program_year' => $programYear,
            'district_id' => (int) $district->id,
            'district_name' => (string) $district->name,
            'candidate_key' => $source.':'.$sourceId,
            'person_key' => strlen($phone) >= 7 ? 'phone:'.hash('sha256', substr($phone, -10)) : $source.':'.$sourceId,
        ];
    }
}
