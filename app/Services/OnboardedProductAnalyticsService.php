<?php

namespace App\Services;

use Carbon\Carbon;
use Illuminate\Pagination\LengthAwarePaginator;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;

class OnboardedProductAnalyticsService
{
    /**
     * @param  array{q?: string, district?: int|null, sector?: string, product?: string}  $filters
     * @return array<string, mixed>
     */
    public function analyze(array $filters): array
    {
        $records = $this->records(Carbon::create(2026, 4, 1)->startOfDay());
        $allTotal = $records->count();
        $missing = $records->where('product_key', '__not_specified__')->count();

        $districts = $records->pluck('district')->filter()->unique()->sort()->values()->all();
        $sectors = $records->pluck('sector')->filter()->unique()->sort()->values()->all();

        $filtered = $records;
        $districtId = (int) ($filters['district'] ?? 0);
        if ($districtId > 0) {
            $filtered = $filtered->where('district_id', $districtId);
        }
        $sector = trim((string) ($filters['sector'] ?? ''));
        if ($sector !== '') {
            $filtered = $filtered->where('sector', $sector);
        }
        $q = mb_strtolower(trim((string) ($filters['q'] ?? '')));
        if ($q !== '') {
            $filtered = $filtered->filter(function (array $row) use ($q): bool {
                return str_contains(mb_strtolower(implode(' ', [
                    $row['product'], $row['applicant_name'], $row['application_no'],
                    $row['district'], $row['block'], $row['sector'],
                ])), $q);
            });
        }
        $filtered = $filtered->values();

        $productRows = $filtered
            ->groupBy('product_key')
            ->map(function (Collection $rows): array {
                return [
                    'key' => (string) $rows->first()['product_key'],
                    'product' => (string) $rows->first()['product'],
                    'count' => $rows->count(),
                    'districts' => $rows->pluck('district')->filter()->unique()->count(),
                    'sectors' => $rows->pluck('sector')->filter()->unique()->sort()->values()->all(),
                ];
            })
            ->sortByDesc('count')
            ->values()
            ->map(function (array $row) use ($filtered): array {
                $row['pct'] = $filtered->isNotEmpty() ? round(($row['count'] / $filtered->count()) * 100, 1) : 0.0;

                return $row;
            });

        $selectedKey = trim((string) ($filters['product'] ?? ''));
        if ($selectedKey === '' || ! $productRows->contains('key', $selectedKey)) {
            $selectedKey = '';
        }
        $selectedProduct = $selectedKey !== ''
            ? $productRows->firstWhere('key', $selectedKey)
            : null;
        $selectedRecords = $selectedKey !== ''
            ? $filtered->where('product_key', $selectedKey)->values()
            : collect();

        $districtBreakdown = $selectedRecords->groupBy('district')->map->count()->sortDesc();
        $sectorBreakdown = $selectedRecords->groupBy('sector')->map->count()->sortDesc();
        $stageBreakdown = $selectedRecords->groupBy('stage')->map->count()->sortDesc();

        return [
            'summary' => [
                'total' => $allTotal,
                'specified' => max(0, $allTotal - $missing),
                'missing' => $missing,
                'distinct' => $records->where('product_key', '!=', '__not_specified__')->pluck('product_key')->unique()->count(),
                'filtered_total' => $filtered->count(),
            ],
            'products' => $this->paginate($productRows, 50, 'products_page'),
            'selectedProduct' => $selectedProduct,
            'selectedRecords' => $this->paginate($selectedRecords, 25, 'records_page'),
            'districtBreakdown' => $districtBreakdown,
            'sectorBreakdown' => $sectorBreakdown,
            'stageBreakdown' => $stageBreakdown,
            'districts' => $districts,
            'sectors' => $sectors,
        ];
    }

    /** @return Collection<int, array<string, mixed>> */
    private function records(Carbon $from): Collection
    {
        $canonical = [];
        foreach ((array) config('cfa.products_by_category', []) as $products) {
            foreach ((array) $products as $product) {
                if (! is_string($product) || trim($product) === '' || in_array(strtolower(trim($product)), ['other', 'others'], true)) {
                    continue;
                }
                $canonical[$this->groupingKey($product)] = trim($product);
            }
        }

        return DB::table('onboarding_batch_cfa as obc')
            ->join('onboarding_batches as ob', 'ob.id', '=', 'obc.onboarding_batch_id')
            ->join('cfa_submissions as cs', 'cs.id', '=', 'obc.cfa_submission_id')
            ->leftJoin('districts as d', 'd.id', '=', 'cs.district_id')
            ->where('ob.status', 'locked')
            ->whereNotNull('ob.locked_at')
            ->where('ob.locked_at', '>=', $from)
            ->select('cs.id', 'cs.application_no', 'cs.applicant_name', 'cs.phone', 'cs.district_id', 'cs.payload', 'ob.name as batch_name', 'ob.locked_at', 'd.name as district_name')
            ->orderByDesc('ob.locked_at')
            ->get()
            ->map(function ($row) use ($canonical): array {
                $payload = is_array($row->payload ?? null) ? $row->payload : json_decode((string) ($row->payload ?? ''), true);
                $payload = is_array($payload) ? $payload : [];
                $product = is_scalar($payload['product'] ?? null) ? trim((string) $payload['product']) : '';
                if (in_array(strtolower($product), ['other', 'others'], true)) {
                    $product = is_scalar($payload['other_product'] ?? null) ? trim((string) $payload['other_product']) : '';
                }
                $key = $this->groupingKey($product);
                if ($key === '' || in_array($key, ['other', 'others', 'na', 'none', 'null', 'not specified'], true)) {
                    $key = '__not_specified__';
                    $product = 'Not specified';
                } else {
                    $product = $canonical[$key] ?? $product;
                }

                return [
                    'id' => (int) $row->id,
                    'application_no' => (string) ($row->application_no ?? ''),
                    'applicant_name' => (string) ($row->applicant_name ?? ''),
                    'phone' => (string) ($row->phone ?? ''),
                    'district_id' => (int) ($row->district_id ?? 0),
                    'district' => (string) ($row->district_name ?? ($payload['district'] ?? 'Not specified')),
                    'block' => trim((string) ($payload['block'] ?? '')) ?: 'Not specified',
                    'sector' => trim((string) ($payload['business_category'] ?? '')) ?: 'Not specified',
                    'stage' => ucfirst(mb_strtolower(trim((string) ($payload['form_stage'] ?? '')) ?: 'Not specified')),
                    'gender' => trim((string) ($payload['gender'] ?? '')) ?: 'Not specified',
                    'product' => $product,
                    'product_key' => $key,
                    'batch_name' => (string) ($row->batch_name ?? ''),
                    'onboarded_at' => $row->locked_at ? Carbon::parse($row->locked_at)->format('d M Y') : '—',
                ];
            });
    }

    private function groupingKey(string $value): string
    {
        $value = html_entity_decode(trim($value), ENT_QUOTES | ENT_HTML5, 'UTF-8');
        $value = mb_strtolower($value, 'UTF-8');
        $value = str_replace(['&', '–', '—', '−'], [' and ', '-', '-', '-'], $value);
        $value = preg_replace('/[^\pL\pN]+/u', ' ', $value) ?? $value;

        return trim(preg_replace('/\s+/u', ' ', $value) ?? $value);
    }

    private function paginate(Collection $items, int $perPage, string $pageName): LengthAwarePaginator
    {
        $page = max(1, LengthAwarePaginator::resolveCurrentPage($pageName));

        return new LengthAwarePaginator(
            $items->forPage($page, $perPage)->values(),
            $items->count(),
            $perPage,
            $page,
            ['path' => request()->url(), 'query' => request()->query(), 'pageName' => $pageName],
        );
    }
}
