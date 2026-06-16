<?php

namespace App\Services;

use App\Models\Deliverable;
use App\Models\FiscalYear;
use App\Models\StateDeliverableTarget;
use App\Models\StateMonthlyTarget;
use Carbon\Carbon;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;

class StateMonthlyTargetsService
{
    public function __construct(
        private readonly StateMonthlyTargetIndicatorBootstrapService $indicatorBootstrap,
    ) {}

    /**
     * @return array<int, string> 1..12 => M1 Apr
     */
    public function fiscalMonthLabels(?FiscalYear $fiscalYear): array
    {
        if (! $fiscalYear?->starts_on) {
            return collect(range(1, 12))
                ->mapWithKeys(fn (int $m) => [$m => 'M'.$m])
                ->all();
        }

        $start = Carbon::parse($fiscalYear->starts_on)->startOfMonth();
        $out = [];
        for ($m = 1; $m <= 12; $m++) {
            $out[$m] = 'M'.$m.' '.$start->copy()->addMonths($m - 1)->format('M');
        }

        return $out;
    }

    /**
     * @return Collection<int, Deliverable>
     */
    public function deliverables(): Collection
    {
        $this->indicatorBootstrap->ensureDeliverables();
        $codes = $this->indicatorBootstrap->allowedDeliverableCodes();

        return Deliverable::query()
            ->whereIn('code', $codes)
            ->where('is_active', true)
            ->get()
            ->sortBy(function (Deliverable $d): int {
                $codes = $this->indicatorBootstrap->allowedDeliverableCodes();
                $index = array_search(strtolower((string) $d->code), $codes, true);

                return $index === false ? 999 : (int) $index;
            })
            ->values();
    }

    /**
     * @return list<array{
     *     deliverable: Deliverable,
     *     serial: string,
     *     category_serial: string,
     *     category_name: string,
     *     state_annual: int,
     *     months: array<int, int>,
     *     row_total: int
     * }>
     */
    public function buildGrid(int $fiscalYearId): array
    {
        $metadata = $this->indicatorBootstrap->metadataByCode();
        $deliverables = $this->deliverables();

        $stateAnnualByDeliverable = StateDeliverableTarget::query()
            ->where('fiscal_year_id', $fiscalYearId)
            ->whereIn('deliverable_id', $deliverables->pluck('id'))
            ->pluck('target_total', 'deliverable_id')
            ->map(fn ($v) => (int) $v)
            ->all();

        $monthlyRows = StateMonthlyTarget::query()
            ->where('fiscal_year_id', $fiscalYearId)
            ->whereIn('deliverable_id', $deliverables->pluck('id'))
            ->get(['deliverable_id', 'month_number', 'target_count']);

        $monthlyByDeliverable = [];
        foreach ($monthlyRows as $row) {
            $monthlyByDeliverable[(int) $row->deliverable_id][(int) $row->month_number] = (int) $row->target_count;
        }

        $grid = [];
        foreach ($deliverables as $deliverable) {
            $code = strtolower((string) $deliverable->code);
            $meta = $metadata[$code] ?? ['serial' => '', 'category_serial' => '', 'category_name' => ''];
            $months = [];
            $rowTotal = 0;
            for ($m = 1; $m <= 12; $m++) {
                $val = (int) ($monthlyByDeliverable[(int) $deliverable->id][$m] ?? 0);
                $months[$m] = $val;
                $rowTotal += $val;
            }

            $grid[] = [
                'deliverable' => $deliverable,
                'serial' => (string) $meta['serial'],
                'category_serial' => (string) ($meta['category_serial'] ?? ''),
                'category_name' => (string) ($meta['category_name'] ?? ''),
                'state_annual' => (int) ($stateAnnualByDeliverable[(int) $deliverable->id] ?? 0),
                'months' => $months,
                'row_total' => $rowTotal,
            ];
        }

        return $grid;
    }

    /**
     * @param  array<int, array<int, int|string|null>>  $deliverableMonths
     */
    public function saveGrid(int $fiscalYearId, array $deliverableMonths): void
    {
        $allowedIds = $this->deliverables()->pluck('id')->map(fn ($id) => (int) $id)->all();

        DB::transaction(function () use ($fiscalYearId, $deliverableMonths, $allowedIds): void {
            foreach ($deliverableMonths as $deliverableId => $months) {
                $deliverableId = (int) $deliverableId;
                if ($deliverableId <= 0 || ! in_array($deliverableId, $allowedIds, true) || ! is_array($months)) {
                    continue;
                }

                for ($m = 1; $m <= 12; $m++) {
                    $raw = $months[$m] ?? $months[(string) $m] ?? 0;
                    $count = max(0, (int) $raw);

                    StateMonthlyTarget::query()->updateOrCreate(
                        [
                            'fiscal_year_id' => $fiscalYearId,
                            'deliverable_id' => $deliverableId,
                            'month_number' => $m,
                        ],
                        ['target_count' => $count]
                    );
                }
            }
        });
    }

    /**
     * @param  list<array{deliverable: Deliverable, state_annual: int, months: array<int, int>, row_total: int}>  $grid
     * @return array<int, int>
     */
    public function columnTotals(array $grid): array
    {
        $totals = array_fill(1, 12, 0);
        $grand = 0;

        foreach ($grid as $row) {
            foreach ($row['months'] as $m => $val) {
                $totals[(int) $m] += (int) $val;
            }
            $grand += (int) $row['row_total'];
        }

        $totals['grand'] = $grand;

        return $totals;
    }
}
