<?php

namespace App\Services;

use App\Models\Deliverable;
use App\Models\District;
use App\Models\DistrictDeliverableTarget;
use App\Models\DistrictMonthlyTarget;
use App\Models\FiscalYear;
use App\Models\Hub;
use App\Models\HubMonthlyTarget;
use App\Models\StateDeliverableTarget;
use Carbon\Carbon;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;

class DistrictHubMonthlyTargetsService
{
    public const SCOPE_DISTRICT = 'district';

    public const SCOPE_HUB = 'hub';

    public function __construct(
        private readonly MisMonthlyTargetIndicatorBootstrapService $indicatorBootstrap,
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

    public function resolveScopeForDeliverable(Deliverable $deliverable): string
    {
        if ($this->indicatorBootstrap->isAllowedDeliverable($deliverable)) {
            return $this->indicatorBootstrap->scopeForCode((string) $deliverable->code);
        }

        $code = strtolower(trim((string) $deliverable->code));
        $levelMap = config('program_deliverables.level_by_deliverable_code', []);

        $candidates = [$code];
        if (str_starts_with($code, 'svc_')) {
            $candidates[] = substr($code, 4);
        }

        foreach ($candidates as $candidate) {
            if ($candidate === '') {
                continue;
            }
            if (isset($levelMap[$candidate])) {
                return strcasecmp((string) $levelMap[$candidate], 'State') === 0
                    ? self::SCOPE_HUB
                    : self::SCOPE_DISTRICT;
            }
        }

        $name = strtolower(trim($deliverable->name.' '.($deliverable->mis_entry_label ?? '')));
        foreach ($this->stateIndicatorNameNeedles() as $needle) {
            if (str_contains($name, $needle)) {
                return self::SCOPE_HUB;
            }
        }

        return self::SCOPE_DISTRICT;
    }

    /**
     * @return list<string>
     */
    private function stateIndicatorNameNeedles(): array
    {
        return [
            'district level workshop',
            'demo day',
            'social media',
            'buyer-seller meet',
            'events/ seminar',
            'events seminars',
            'case studies',
            'newsletter',
            'newspaper ad',
            'stakeholder consultation',
            'partners outreach for forward',
            'marketing partners onboarded',
            'mentorship support through online',
        ];
    }

    /**
     * @return list<array{
     *     deliverable: Deliverable,
     *     scope: string,
     *     state_annual: int,
     *     allocated_annual: int,
     *     monthly_annual: int,
     *     status: string,
     *     status_label: string
     * }>
     */
    public function pendingDeliverables(int $fiscalYearId, ?string $scopeFilter = null): array
    {
        $this->indicatorBootstrap->ensureDeliverables();

        $allowedCodes = $this->indicatorBootstrap->allowedDeliverableCodes();
        $deliverables = Deliverable::query()
            ->where('is_active', true)
            ->whereIn('code', $allowedCodes)
            ->orderBy('sort_order')
            ->orderBy('id')
            ->get();

        $stateTotals = StateDeliverableTarget::query()
            ->where('fiscal_year_id', $fiscalYearId)
            ->pluck('target_total', 'deliverable_id')
            ->map(fn ($v) => (int) $v)
            ->all();

        $districtAnnualSums = DistrictDeliverableTarget::query()
            ->where('fiscal_year_id', $fiscalYearId)
            ->selectRaw('deliverable_id, SUM(target_total) as total')
            ->groupBy('deliverable_id')
            ->pluck('total', 'deliverable_id')
            ->map(fn ($v) => (int) $v)
            ->all();

        $districtMonthlySums = DistrictMonthlyTarget::query()
            ->where('fiscal_year_id', $fiscalYearId)
            ->selectRaw('deliverable_id, SUM(target_count) as total')
            ->groupBy('deliverable_id')
            ->pluck('total', 'deliverable_id')
            ->map(fn ($v) => (int) $v)
            ->all();

        $hubMonthlySums = HubMonthlyTarget::query()
            ->where('fiscal_year_id', $fiscalYearId)
            ->selectRaw('deliverable_id, SUM(target_count) as total')
            ->groupBy('deliverable_id')
            ->pluck('total', 'deliverable_id')
            ->map(fn ($v) => (int) $v)
            ->all();

        $rows = [];
        foreach ($deliverables as $deliverable) {
            $scope = $this->resolveScopeForDeliverable($deliverable);
            if ($scopeFilter !== null && $scope !== $scopeFilter) {
                continue;
            }

            $deliverableId = (int) $deliverable->id;
            $stateAnnual = (int) ($stateTotals[$deliverableId] ?? 0);
            $districtAnnual = (int) ($districtAnnualSums[$deliverableId] ?? 0);
            $districtMonthly = (int) ($districtMonthlySums[$deliverableId] ?? 0);
            $hubMonthly = (int) ($hubMonthlySums[$deliverableId] ?? 0);

            if ($scope === self::SCOPE_HUB) {
                $referenceAnnual = $stateAnnual;
                $monthlyAnnual = $hubMonthly;
            } else {
                $referenceAnnual = $districtAnnual > 0 ? $districtAnnual : $stateAnnual;
                $monthlyAnnual = $districtMonthly;
            }

            if ($referenceAnnual <= 0) {
                $rows[] = [
                    'deliverable' => $deliverable,
                    'scope' => $scope,
                    'state_annual' => $stateAnnual,
                    'allocated_annual' => 0,
                    'monthly_annual' => $monthlyAnnual,
                    'status' => 'needs_annual',
                    'status_label' => 'Set state / district annual target first',
                ];

                continue;
            }

            if ($monthlyAnnual === $referenceAnnual) {
                continue;
            }

            $status = $monthlyAnnual === 0 ? 'missing' : 'partial';
            $rows[] = [
                'deliverable' => $deliverable,
                'scope' => $scope,
                'state_annual' => $stateAnnual,
                'allocated_annual' => $referenceAnnual,
                'monthly_annual' => $monthlyAnnual,
                'status' => $status,
                'status_label' => $status === 'missing'
                    ? 'No monthly plan yet'
                    : 'Partial ('.number_format($monthlyAnnual).' / '.number_format($referenceAnnual).')',
            ];
        }

        return $rows;
    }

    /**
     * @return list<array{
     *     district: District,
     *     hub_name: string,
     *     annual: int,
     *     months: array<int, int>,
     *     row_total: int
     * }>
     */
    public function districtGrid(int $fiscalYearId, int $deliverableId): array
    {
        $districts = District::query()
            ->with('hub:id,name')
            ->orderBy('hub_id')
            ->orderBy('sort_order')
            ->orderBy('name')
            ->get();

        $annualByDistrict = DistrictDeliverableTarget::query()
            ->where('fiscal_year_id', $fiscalYearId)
            ->where('deliverable_id', $deliverableId)
            ->pluck('target_total', 'district_id')
            ->map(fn ($v) => (int) $v)
            ->all();

        $monthlyRows = DistrictMonthlyTarget::query()
            ->where('fiscal_year_id', $fiscalYearId)
            ->where('deliverable_id', $deliverableId)
            ->get(['district_id', 'month_number', 'target_count']);

        $monthlyByDistrict = [];
        foreach ($monthlyRows as $row) {
            $monthlyByDistrict[(int) $row->district_id][(int) $row->month_number] = (int) $row->target_count;
        }

        $grid = [];
        foreach ($districts as $district) {
            $months = [];
            $rowTotal = 0;
            for ($m = 1; $m <= 12; $m++) {
                $val = (int) ($monthlyByDistrict[(int) $district->id][$m] ?? 0);
                $months[$m] = $val;
                $rowTotal += $val;
            }

            $grid[] = [
                'district' => $district,
                'hub_name' => (string) ($district->hub?->name ?? '—'),
                'annual' => (int) ($annualByDistrict[(int) $district->id] ?? 0),
                'months' => $months,
                'row_total' => $rowTotal,
            ];
        }

        return $grid;
    }

    /**
     * @return list<array{
     *     hub: Hub,
     *     annual: int,
     *     months: array<int, int>,
     *     row_total: int
     * }>
     */
    public function hubGrid(int $fiscalYearId, int $deliverableId): array
    {
        $hubs = Hub::query()->orderBy('sort_order')->orderBy('name')->get();

        $stateAnnual = (int) (StateDeliverableTarget::query()
            ->where('fiscal_year_id', $fiscalYearId)
            ->where('deliverable_id', $deliverableId)
            ->value('target_total') ?? 0);

        $monthlyRows = HubMonthlyTarget::query()
            ->where('fiscal_year_id', $fiscalYearId)
            ->where('deliverable_id', $deliverableId)
            ->get(['hub_id', 'month_number', 'target_count']);

        $monthlyByHub = [];
        foreach ($monthlyRows as $row) {
            $monthlyByHub[(int) $row->hub_id][(int) $row->month_number] = (int) $row->target_count;
        }

        $grid = [];
        foreach ($hubs as $hub) {
            $months = [];
            $rowTotal = 0;
            for ($m = 1; $m <= 12; $m++) {
                $val = (int) ($monthlyByHub[(int) $hub->id][$m] ?? 0);
                $months[$m] = $val;
                $rowTotal += $val;
            }

            $grid[] = [
                'hub' => $hub,
                'annual' => $stateAnnual,
                'months' => $months,
                'row_total' => $rowTotal,
            ];
        }

        return $grid;
    }

    /**
     * @param  array<int, array<int, int|string|null>>  $districtMonths  district_id => [month => count]
     */
    public function saveDistrictGrid(int $fiscalYearId, int $deliverableId, array $districtMonths): void
    {
        DB::transaction(function () use ($fiscalYearId, $deliverableId, $districtMonths): void {
            foreach ($districtMonths as $districtId => $months) {
                $districtId = (int) $districtId;
                if ($districtId <= 0 || ! is_array($months)) {
                    continue;
                }

                for ($m = 1; $m <= 12; $m++) {
                    $raw = $months[$m] ?? $months[(string) $m] ?? 0;
                    $count = max(0, (int) $raw);

                    DistrictMonthlyTarget::query()->updateOrCreate(
                        [
                            'fiscal_year_id' => $fiscalYearId,
                            'district_id' => $districtId,
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
     * @param  array<int, array<int, int|string|null>>  $hubMonths  hub_id => [month => count]
     */
    public function saveHubGrid(int $fiscalYearId, int $deliverableId, array $hubMonths): void
    {
        DB::transaction(function () use ($fiscalYearId, $deliverableId, $hubMonths): void {
            foreach ($hubMonths as $hubId => $months) {
                $hubId = (int) $hubId;
                if ($hubId <= 0 || ! is_array($months)) {
                    continue;
                }

                for ($m = 1; $m <= 12; $m++) {
                    $raw = $months[$m] ?? $months[(string) $m] ?? 0;
                    $count = max(0, (int) $raw);

                    HubMonthlyTarget::query()->updateOrCreate(
                        [
                            'fiscal_year_id' => $fiscalYearId,
                            'hub_id' => $hubId,
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
     * @return array<int, int> month_number => column total
     */
    public function columnTotals(array $grid, string $scope): array
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

    /**
     * @return Collection<int, Deliverable>
     */
    public function deliverablesForScope(string $scope): Collection
    {
        $this->indicatorBootstrap->ensureDeliverables();

        $allowedCodes = $this->indicatorBootstrap->allowedDeliverableCodes();
        $serialByCode = collect($this->indicatorBootstrap->indicatorDefinitions())
            ->mapWithKeys(fn (array $row) => [strtolower((string) $row['code']) => (string) ($row['serial'] ?? '')]);

        return Deliverable::query()
            ->where('is_active', true)
            ->whereIn('code', $allowedCodes)
            ->orderBy('sort_order')
            ->orderBy('id')
            ->get()
            ->filter(fn (Deliverable $d) => $this->resolveScopeForDeliverable($d) === $scope)
            ->values()
            ->map(function (Deliverable $d) use ($serialByCode): Deliverable {
                $d->setAttribute('mis_serial', $serialByCode[strtolower((string) $d->code)] ?? '');

                return $d;
            });
    }

    /**
     * @return list<array{serial: string, code: string, name: string, scope: string}>
     */
    public function configuredIndicatorsForScope(string $scope): array
    {
        return $this->indicatorBootstrap->indicatorsForScope($scope);
    }
}
