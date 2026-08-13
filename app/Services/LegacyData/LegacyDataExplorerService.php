<?php

namespace App\Services\LegacyData;

use App\Services\Exports\OnboardedShgCboDistrictPackService;
use Carbon\Carbon;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Cache;

final class LegacyDataExplorerService
{
    private const CACHE_VERSION_KEY = 'legacy-data-explorer:version';

    private const CACHE_SCHEMA_VERSION = 6;

    public function __construct(
        private readonly OnboardedShgCboDistrictPackService $pack,
        private readonly LegacyServiceNameNormalizer $serviceNames,
    ) {}

    /** @return array{rows: Collection<int,array<string,mixed>>, options: array<string,list<string>>, kpis: array<string,int>, summary: Collection<int,array<string,mixed>>, year_summary: Collection<int,array<string,mixed>>, district_summary: Collection<int,array<string,mixed>>, service_rows: Collection<int,array<string,mixed>>} */
    public function build(array $filters): array
    {
        $all = $this->dataset();
        $scopeDistricts = $filters['scope_districts'] ?? null;
        if (is_array($scopeDistricts)) {
            $scopeKeys = collect($scopeDistricts)
                ->map(fn (string $district): string => $this->districtKey($district))
                ->filter()
                ->unique();
            $all = $all->filter(fn (array $row): bool => $scopeKeys->contains(
                $this->districtKey((string) ($row['district'] ?? '')),
            ))->values();
        }
        $serviceContext = trim((string) ($filters['service'] ?? '')) !== '';
        $rowFilters = $filters;
        if ($serviceContext) {
            // In a service report, FY and Phase describe the delivery/event,
            // not the year/source in which the beneficiary was onboarded.
            $rowFilters['fy'] = '';
            $rowFilters['phase'] = '';
            $rowFilters['service'] = '';
            $rowFilters['service_status'] = '__all__';
        }

        $rows = $all->filter(fn (array $row): bool => $this->matches($row, $rowFilters))->values();
        $serviceFilters = $filters;
        if (! $serviceContext) {
            $serviceFilters['fy'] = '';
            $serviceFilters['phase'] = '';
        }
        $serviceRows = $this->serviceRows($rows)
            ->filter(fn (array $row): bool => $this->serviceRowMatches($row, $serviceFilters))
            ->values();

        if ($serviceContext) {
            $served = $serviceRows->pluck('identity')->unique()->flip();
            $rows = $rows->filter(fn (array $row): bool => $served->has($this->identityKey($row)))->values();
        }

        $deliveryCounts = $serviceRows->countBy('identity');
        $servedIdentities = $deliveryCounts->keys();
        $rows = $rows->map(function (array $row) use ($deliveryCounts): array {
            $row['filtered_services_count'] = (int) $deliveryCounts->get($this->identityKey($row), 0);

            return $row;
        });

        return [
            'rows' => $rows,
            'options' => $this->options($all),
            'kpis' => [
                'onboarded' => $rows->count(),
                'served' => $servedIdentities->count(),
                'deliveries' => $serviceRows->count(),
                'without_service' => max(0, $rows->count() - $servedIdentities->count()),
            ],
            'summary' => $this->summary(
                $rows,
                $serviceRows,
                (string) ($filters['group'] ?? 'district'),
                $serviceContext,
            ),
            'year_summary' => $this->summary($rows, $serviceRows, 'fy', $serviceContext),
            'district_summary' => $this->summary($rows, $serviceRows, 'district', $serviceContext),
            'service_rows' => $serviceRows,
        ];
    }

    public function refresh(): void
    {
        Cache::forever(self::CACHE_VERSION_KEY, ((int) Cache::get(self::CACHE_VERSION_KEY, 1)) + 1);
        $this->serviceNames->clearRuntimeCache();
    }

    /** @return Collection<int,array<string,mixed>> */
    public function mappingInventory(): Collection
    {
        return $this->dataset()
            ->flatMap(function (array $row): array {
                return array_map(fn (array $service): array => [
                    'source_phase' => $this->serviceNames->sourcePhase((string) $service['phase']),
                    'phase' => $service['phase'],
                    'original_name' => $service['original_label'] ?? $service['label'],
                    'standard_name' => $service['label'],
                    'service_id' => $service['service_id'] ?? null,
                    'mapped' => (bool) ($service['mapped'] ?? false),
                    'mapping_source' => $service['mapping_source'] ?? 'unmapped',
                    'identity' => $this->identityKey($row),
                ], array_values(array_filter(
                    $row['service_items'],
                    fn (array $service): bool => ($service['phase'] ?? '') !== 'Phase 3'
                )));
            })
            ->groupBy(fn (array $row): string => $row['source_phase'].'|'.$this->serviceNames->normalizeKey($row['original_name']))
            ->map(function (Collection $items): array {
                $first = $items->first();

                return array_merge($first, [
                    'records' => $items->count(),
                    'beneficiaries' => $items->unique('identity')->count(),
                ]);
            })
            ->sortBy([
                fn (array $a, array $b): int => ((int) $a['mapped']) <=> ((int) $b['mapped']),
                fn (array $a, array $b): int => $b['records'] <=> $a['records'],
            ])
            ->values();
    }

    /** @return Collection<int,array<string,mixed>> */
    private function dataset(): Collection
    {
        $version = (int) Cache::get(self::CACHE_VERSION_KEY, 1);

        return Cache::store('file')->remember('legacy-data-explorer:dataset:s'.self::CACHE_SCHEMA_VERSION.':v'.$version, now()->addMinutes(5), function (): Collection {
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
            ->map(function (array $service) use ($phase): array {
                $original = $this->clean($service['label'] ?? null);
                $detail = $this->clean($service['detail'] ?? null, '');
                $servicePhase = $this->servicePhase((string) ($service['phase'] ?? ''), $phase);
                $resolved = $this->serviceNames->resolve($original, $servicePhase, $detail);

                return array_merge($resolved, [
                    'detail' => $detail,
                    'phase' => $servicePhase,
                    'status' => $servicePhase === 'Phase 3'
                        ? $this->clean($service['status'] ?? null, 'Not captured')
                        : 'Approved',
                    'date' => $this->clean($service['date'] ?? null, 'Date NA'),
                ]);
            })
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
            'services' => collect($serviceItems)->pluck('label')->unique()->implode('; '),
            'original_services' => collect($serviceItems)->pluck('original_label')->unique()->implode('; '),
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
        $requestedStatus = trim((string) ($filters['service_status'] ?? ''));
        if (($service !== '' || ! in_array($requestedStatus, ['', '__all__'], true)) && ! collect($row['service_items'])->contains(
            fn (array $item): bool => ($service === '' || $this->same($item['label'], $service))
                && $this->statusMatches((string) $item['status'], $requestedStatus)
        )) {
            return false;
        }

        $q = mb_strtolower(trim((string) ($filters['q'] ?? '')));
        if ($q !== '') {
            $haystack = mb_strtolower(implode(' ', [
                $row['application_no'], $row['applicant'], $row['phone'], $row['district'],
                $row['block'], $row['business_category'], $row['services'] ?? '',
                collect($row['service_items'])->pluck('original_label')->implode(' '),
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
        return $this->sameOrEmpty((string) ($filters['fy'] ?? ''), (string) $row['financial_year'])
            && $this->sameOrEmpty((string) ($filters['phase'] ?? ''), (string) $row['phase'])
            && $this->sameOrEmpty((string) ($filters['service'] ?? ''), (string) $row['service'])
            && $this->statusMatches((string) $row['service_status'], (string) ($filters['service_status'] ?? ''));
    }

    /** @return Collection<int,array<string,mixed>> */
    private function serviceRows(Collection $rows): Collection
    {
        return $rows->flatMap(function (array $row): array {
            return array_map(fn (array $service): array => [
                'financial_year' => $this->serviceFinancialYear($service['date'] ?? null),
                'phase' => $service['phase'] ?? $row['phase'],
                'application_no' => $row['application_no'],
                'applicant' => $row['applicant'],
                'phone' => $row['phone'],
                'district' => $row['district'],
                'block' => $row['block'],
                'beneficiary_type' => $row['beneficiary_type'],
                'business_category' => $row['business_category'],
                'business_stage' => $row['business_stage'],
                'gender' => $row['gender'],
                'education' => $row['education'],
                'identity' => $this->identityKey($row),
                'service' => $service['label'],
                'original_service' => $service['original_label'] ?? $service['label'],
                'service_mapped' => (bool) ($service['mapped'] ?? false),
                'mapping_source' => $service['mapping_source'] ?? 'unmapped',
                'service_detail' => $service['detail'],
                'service_status' => $service['status'],
                'service_date' => $service['date'],
            ], $row['service_items']);
        })->values();
    }

    /** @return Collection<int,array<string,mixed>> */
    private function summary(Collection $rows, Collection $serviceRows, string $group, bool $serviceContext): Collection
    {
        $column = match ($group) {
            'fy' => 'financial_year', 'phase' => 'phase', 'service' => 'service',
            'stage' => 'business_stage', 'gender' => 'gender', 'education' => 'education',
            'category' => 'business_category', 'type' => 'beneficiary_type', default => 'district',
        };

        if ($group === 'service' || ($serviceContext && in_array($group, ['fy', 'phase'], true))) {
            $serviceColumn = match ($group) {
                'fy' => 'financial_year', 'phase' => 'phase', default => 'service',
            };

            return $serviceRows->groupBy($serviceColumn)->map(function (Collection $deliveries, string $key): array {
                $beneficiaries = $deliveries->unique('identity')->count();

                return [
                    'label' => $key ?: 'Not captured',
                    'onboarded' => $beneficiaries,
                    'served' => $beneficiaries,
                    'deliveries' => $deliveries->count(),
                    'without_service' => 0,
                ];
            })->sortByDesc('onboarded')->values();
        }

        return $rows->groupBy(fn (array $row): string => (string) ($row[$column] ?? 'Not captured'))
            ->map(function (Collection $beneficiaries, string $key) use ($serviceRows): array {
                $identities = $beneficiaries->map(fn (array $row): string => $this->identityKey($row))->unique();
                $deliveries = $serviceRows->whereIn('identity', $identities);
                $served = $deliveries->pluck('identity')->unique()->count();

                return [
                    'label' => $key ?: 'Not captured',
                    'onboarded' => $beneficiaries->count(),
                    'served' => $served,
                    'deliveries' => $deliveries->count(),
                    'without_service' => max(0, $beneficiaries->count() - $served),
                ];
            })->sortByDesc('onboarded')->values();
    }

    /** @return array<string,list<string>> */
    private function options(Collection $rows): array
    {
        $values = fn (string $key): array => $rows->pluck($key)->filter()->unique()->sort()->values()->all();
        $serviceRows = $this->serviceRows($rows);

        return [
            'financial_years' => collect($values('financial_year'))
                ->merge($serviceRows->pluck('financial_year'))->filter()->unique()->sort()->values()->all(),
            'phases' => collect($values('phase'))
                ->merge($serviceRows->pluck('phase'))->filter()->unique()->sort()->values()->all(),
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

    private function districtKey(string $district): string
    {
        $key = preg_replace('/[^a-z0-9]+/', '', mb_strtolower(trim($district))) ?? '';

        return match ($key) {
            'pauri' => 'paurigarhwal',
            'usnagar' => 'udhamsinghnagar',
            default => $key,
        };
    }

    private function servicePhase(string $raw, string $fallback): string
    {
        $key = preg_replace('/[^a-z0-9]+/', '', mb_strtolower(trim($raw))) ?: '';

        return match ($key) {
            'phase1', 'legacyphase1' => 'Phase 1',
            'phase2', 'legacyphase2' => 'Phase 2',
            'phase3' => 'Phase 3',
            default => $fallback,
        };
    }

    private function sameOrEmpty(string $filter, string $value): bool
    {
        return $filter === '' || $this->same($filter, $value);
    }

    private function same(string $left, string $right): bool
    {
        return mb_strtolower(trim($left)) === mb_strtolower(trim($right));
    }

    private function statusMatches(string $actual, string $requested): bool
    {
        $requested = trim($requested);
        if ($requested === '__all__') {
            return true;
        }

        return $this->same($actual, $requested === '' ? 'Approved' : $requested);
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

    private function parseServiceDate(mixed $value): ?Carbon
    {
        $value = trim((string) $value);
        if ($value === '' || in_array($value, ['â€”', 'Date NA'], true)) {
            return null;
        }

        try {
            $date = Carbon::parse($value);

            // Service delivery dates are preserved even when they pre-date the
            // programme window, because those outliers must remain auditable.
            return $date->year >= 2000 && $date->year <= now()->year + 1 ? $date : null;
        } catch (\Throwable) {
            return null;
        }
    }

    private function serviceFinancialYear(mixed $value): string
    {
        $date = $this->parseServiceDate($value);
        if (! $date) {
            return 'Date NA';
        }

        $startYear = $date->month >= 4 ? $date->year : $date->year - 1;

        return $startYear < 2021 ? 'Outside programme period' : $this->fiscalYear($date);
    }

    private function fiscalYear(Carbon $date): string
    {
        $start = $date->month >= 4 ? $date->year : $date->year - 1;

        return sprintf('FY %d-%02d', $start, ($start + 1) % 100);
    }
}
