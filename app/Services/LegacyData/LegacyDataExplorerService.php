<?php

namespace App\Services\LegacyData;

use App\Services\Exports\OnboardedShgCboDistrictPackService;
use Carbon\Carbon;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Cache;

final class LegacyDataExplorerService
{
    private const CACHE_VERSION_KEY = 'legacy-data-explorer:version';

    public function __construct(
        private readonly OnboardedShgCboDistrictPackService $pack,
    ) {}

    /** @return array{rows: Collection<int,array<string,mixed>>, options: array<string,list<string>>, kpis: array<string,int>, summary: Collection<int,array<string,mixed>>, service_rows: Collection<int,array<string,mixed>>} */
    public function build(array $filters): array
    {
        $all = $this->dataset();
        $rows = $all->filter(fn (array $row): bool => $this->matches($row, $filters))->values();
        $serviceRows = $this->serviceRows($rows)
            ->filter(fn (array $row): bool => $this->serviceRowMatches($row, $filters))
            ->values();

        return [
            'rows' => $rows,
            'options' => $this->options($all),
            'kpis' => [
                'onboarded' => $rows->count(),
                'served' => $rows->where('services_count', '>', 0)->count(),
                'deliveries' => $serviceRows->count(),
                'without_service' => $rows->where('services_count', 0)->count(),
            ],
            'summary' => $this->summary($rows, (string) ($filters['group'] ?? 'district')),
            'service_rows' => $serviceRows,
        ];
    }

    public function refresh(): void
    {
        Cache::forever(self::CACHE_VERSION_KEY, ((int) Cache::get(self::CACHE_VERSION_KEY, 1)) + 1);
    }

    /** @return Collection<int,array<string,mixed>> */
    private function dataset(): Collection
    {
        $version = (int) Cache::get(self::CACHE_VERSION_KEY, 1);

        return Cache::store('file')->remember('legacy-data-explorer:dataset:v'.$version, now()->addMinutes(5), function (): Collection {
            $pack = $this->pack->build();
            $rows = collect()
                ->concat($pack['shg']['details'] ?? [])
                ->concat($pack['cbo']['details'] ?? [])
                ->concat($pack['individual']['details'] ?? []);

            $seen = [];

            return $rows
                ->map(fn (array $row): array => $this->normalizeRow($row))
                ->filter(function (array $row) use (&$seen): bool {
                    $key = $this->identityKey($row);
                    if (isset($seen[$key])) {
                        return false;
                    }
                    $seen[$key] = true;

                    return true;
                })
                ->sortByDesc('onboarding_date_sort')
                ->values();
        });
    }

    /** @return array<string,mixed> */
    private function normalizeRow(array $row): array
    {
        $phase = $this->clean($row['phase'] ?? null, 'Phase unknown');
        $date = $this->parseDate($row['onboarding_date'] ?? null);
        if ($phase === 'Phase 1' && $date?->greaterThan(Carbon::create(2025, 3, 31, 23, 59, 59))) {
            $date = null;
        }
        $selfDeclaration = mb_strtolower(trim((string) ($row['self_declaration'] ?? '')));
        $fy = $selfDeclaration === 'yes'
            ? 'FY 2021-22'
            : ($date ? $this->fiscalYear($date) : match ($phase) {
                'Phase 2' => 'FY 2025-26',
                'Phase 3' => 'FY 2026-27',
                default => 'Date NA',
            });

        $serviceItems = collect($row['service_items'] ?? [])
            ->map(fn (array $service): array => [
                'label' => $this->clean($service['label'] ?? null),
                'detail' => $this->clean($service['detail'] ?? null, ''),
                'status' => $this->clean($service['status'] ?? null, 'Not captured'),
                'date' => $this->clean($service['date'] ?? null, 'Date NA'),
            ])
            ->filter(fn (array $service): bool => $service['label'] !== 'Not captured')
            ->values()
            ->all();

        return array_merge($row, [
            'phase' => $phase,
            'financial_year' => $fy,
            'application_no' => $this->clean($row['application_no'] ?? null),
            'applicant' => $this->clean($row['applicant'] ?? null),
            'phone' => preg_replace('/\D+/', '', (string) ($row['phone'] ?? '')) ?: '',
            'district' => $this->clean($row['district'] ?? null),
            'block' => $this->clean($row['hub'] ?? null),
            'beneficiary_type' => $this->clean($row['category'] ?? null, 'Individual'),
            'business_category' => $this->clean($row['sector'] ?? null),
            'business_stage' => $this->clean($row['business_stage'] ?? null),
            'gender' => $this->clean($row['gender'] ?? null),
            'education' => $this->clean($row['education'] ?? null),
            'onboarding_date' => $date?->format('d M Y') ?? 'Date NA',
            'onboarding_date_sort' => $date?->timestamp ?? 0,
            'service_items' => $serviceItems,
            'services_count' => count($serviceItems),
        ]);
    }

    private function matches(array $row, array $filters): bool
    {
        foreach ([
            'fy' => 'financial_year', 'phase' => 'phase', 'district' => 'district',
            'category' => 'business_category', 'stage' => 'business_stage',
            'gender' => 'gender', 'education' => 'education', 'type' => 'beneficiary_type',
        ] as $filter => $column) {
            if (! $this->sameOrEmpty((string) ($filters[$filter] ?? ''), (string) ($row[$column] ?? ''))) {
                return false;
            }
        }

        $service = trim((string) ($filters['service'] ?? ''));
        $status = trim((string) ($filters['service_status'] ?? ''));
        if (($service !== '' || $status !== '') && ! collect($row['service_items'])->contains(
            fn (array $item): bool => ($service === '' || mb_strtolower($item['label']) === mb_strtolower($service))
                && ($status === '' || mb_strtolower($item['status']) === mb_strtolower($status))
        )) {
            return false;
        }

        $q = mb_strtolower(trim((string) ($filters['q'] ?? '')));
        if ($q !== '') {
            $haystack = mb_strtolower(implode(' ', [
                $row['application_no'], $row['applicant'], $row['phone'], $row['district'],
                $row['block'], $row['business_category'], $row['services'] ?? '',
            ]));
            if (! str_contains($haystack, $q)) {
                return false;
            }
        }

        $date = (int) ($row['onboarding_date_sort'] ?? 0);
        if (($filters['from'] ?? '') !== '' && ($date === 0 || $date < Carbon::parse($filters['from'])->startOfDay()->timestamp)) {
            return false;
        }
        if (($filters['to'] ?? '') !== '' && ($date === 0 || $date > Carbon::parse($filters['to'])->endOfDay()->timestamp)) {
            return false;
        }

        return true;
    }

    private function serviceRowMatches(array $row, array $filters): bool
    {
        return $this->sameOrEmpty((string) ($filters['service'] ?? ''), (string) $row['service'])
            && $this->sameOrEmpty((string) ($filters['service_status'] ?? ''), (string) $row['service_status']);
    }

    /** @return Collection<int,array<string,mixed>> */
    private function serviceRows(Collection $rows): Collection
    {
        return $rows->flatMap(function (array $row): array {
            return array_map(static fn (array $service): array => [
                'financial_year' => $row['financial_year'],
                'phase' => $row['phase'],
                'application_no' => $row['application_no'],
                'applicant' => $row['applicant'],
                'phone' => $row['phone'],
                'district' => $row['district'],
                'business_category' => $row['business_category'],
                'service' => $service['label'],
                'service_detail' => $service['detail'],
                'service_status' => $service['status'],
                'service_date' => $service['date'],
            ], $row['service_items']);
        })->values();
    }

    /** @return Collection<int,array<string,mixed>> */
    private function summary(Collection $rows, string $group): Collection
    {
        $column = match ($group) {
            'fy' => 'financial_year', 'phase' => 'phase', 'service' => 'service_items',
            'stage' => 'business_stage', 'gender' => 'gender', 'education' => 'education',
            'category' => 'business_category', 'type' => 'beneficiary_type', default => 'district',
        };

        $expanded = $column === 'service_items'
            ? $rows->flatMap(fn (array $row): array => array_map(
                fn (array $service): array => ['key' => $service['label'], 'row' => $row],
                $row['service_items']
            ))
            : $rows->map(fn (array $row): array => ['key' => $row[$column] ?? 'Not captured', 'row' => $row]);

        return $expanded->groupBy('key')->map(function (Collection $members, string $key): array {
            $beneficiaries = $members->pluck('row')->unique(fn (array $row): string => $this->identityKey($row));

            return [
                'label' => $key ?: 'Not captured',
                'onboarded' => $beneficiaries->count(),
                'served' => $beneficiaries->where('services_count', '>', 0)->count(),
                'deliveries' => $beneficiaries->sum('services_count'),
                'without_service' => $beneficiaries->where('services_count', 0)->count(),
            ];
        })->sortByDesc('onboarded')->values();
    }

    /** @return array<string,list<string>> */
    private function options(Collection $rows): array
    {
        $values = fn (string $key): array => $rows->pluck($key)->filter()->unique()->sort()->values()->all();

        return [
            'financial_years' => $values('financial_year'),
            'phases' => $values('phase'),
            'districts' => $values('district'),
            'categories' => $values('business_category'),
            'stages' => $values('business_stage'),
            'genders' => $values('gender'),
            'educations' => $values('education'),
            'types' => $values('beneficiary_type'),
            'services' => $rows->pluck('service_items')->flatten(1)->pluck('label')->filter()->unique()->sort()->values()->all(),
            'service_statuses' => $rows->pluck('service_items')->flatten(1)->pluck('status')->filter()->unique()->sort()->values()->all(),
        ];
    }

    private function identityKey(array $row): string
    {
        $application = mb_strtolower(trim((string) ($row['application_no'] ?? '')));
        if ($application !== '' && $application !== 'not captured' && $application !== '—') {
            return 'app:'.$application;
        }

        $phone = preg_replace('/\D+/', '', (string) ($row['phone'] ?? ''));
        if ($phone !== '') {
            return 'phone:'.$phone;
        }

        return 'row:'.sha1(implode('|', [$row['phase'] ?? '', $row['cfa_id'] ?? '', $row['applicant'] ?? '']));
    }

    private function sameOrEmpty(string $filter, string $value): bool
    {
        return $filter === '' || mb_strtolower(trim($filter)) === mb_strtolower(trim($value));
    }

    private function clean(mixed $value, string $fallback = 'Not captured'): string
    {
        $value = trim((string) $value);

        return $value === '' || in_array($value, ['—', '-'], true) ? $fallback : $value;
    }

    private function parseDate(mixed $value): ?Carbon
    {
        $value = trim((string) $value);
        if ($value === '' || in_array($value, ['—', 'Date NA'], true)) {
            return null;
        }

        try {
            $date = Carbon::parse($value);

            return $date->year >= 2021 && $date->year <= now()->year + 1 ? $date : null;
        } catch (\Throwable) {
            return null;
        }
    }

    private function fiscalYear(Carbon $date): string
    {
        $start = $date->month >= 4 ? $date->year : $date->year - 1;

        return sprintf('FY %d-%02d', $start, ($start + 1) % 100);
    }
}
