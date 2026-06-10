<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\Deliverable;
use App\Models\FiscalYear;
use App\Models\StateDeliverableTarget;
use App\Services\AdminAuditLogger;
use App\Services\DistrictHubMonthlyTargetPresetService;
use App\Services\DistrictHubMonthlyTargetsService;
use App\Services\MisMonthlyTargetIndicatorBootstrapService;
use App\Services\ServiceTargetDeliverableSyncService;
use InvalidArgumentException;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;
use Illuminate\View\View;

class DistrictHubMonthlyTargetsController extends Controller
{
    public function __construct(
        private readonly DistrictHubMonthlyTargetsService $monthlyTargets,
        private readonly DistrictHubMonthlyTargetPresetService $presetService,
        private readonly MisMonthlyTargetIndicatorBootstrapService $indicatorBootstrap,
        private readonly ServiceTargetDeliverableSyncService $serviceDeliverables,
        private readonly AdminAuditLogger $auditLogger,
    ) {}

    public function index(Request $request): View
    {
        $this->serviceDeliverables->syncAllServices();
        $this->indicatorBootstrap->ensureDeliverables();

        [$fiscalYearId, $fiscalYears] = FiscalYear::resolveIdForUi(
            $request->query('fiscal_year_id') ? (int) $request->query('fiscal_year_id') : null
        );

        $tab = $request->query('tab', DistrictHubMonthlyTargetsService::SCOPE_DISTRICT);
        if (! in_array($tab, [DistrictHubMonthlyTargetsService::SCOPE_DISTRICT, DistrictHubMonthlyTargetsService::SCOPE_HUB], true)) {
            $tab = DistrictHubMonthlyTargetsService::SCOPE_DISTRICT;
        }

        $deliverableId = $request->query('deliverable_id') ? (int) $request->query('deliverable_id') : null;
        $fiscalYear = $fiscalYears->firstWhere('id', $fiscalYearId);
        $monthLabels = $this->monthlyTargets->fiscalMonthLabels($fiscalYear);

        $pending = $this->monthlyTargets->pendingDeliverables($fiscalYearId, $tab);
        $scopeDeliverables = $this->monthlyTargets->deliverablesForScope($tab);

        if (! $deliverableId && $pending !== []) {
            $deliverableId = (int) $pending[0]['deliverable']->id;
        } elseif (! $deliverableId) {
            $deliverableId = (int) ($scopeDeliverables->first()?->id ?? 0);
        }

        $deliverable = $deliverableId > 0
            ? Deliverable::query()->where('is_active', true)->find($deliverableId)
            : null;

        if ($deliverable && $this->monthlyTargets->resolveScopeForDeliverable($deliverable) !== $tab) {
            $tab = $this->monthlyTargets->resolveScopeForDeliverable($deliverable);
            $scopeDeliverables = $this->monthlyTargets->deliverablesForScope($tab);
            $pending = $this->monthlyTargets->pendingDeliverables($fiscalYearId, $tab);
        }

        $grid = [];
        $columnTotals = array_fill(1, 12, 0);
        $columnTotals['grand'] = 0;
        $referenceAnnual = 0;
        $stateAnnual = 0;

        if ($deliverable) {
            $stateAnnual = (int) (StateDeliverableTarget::query()
                ->where('fiscal_year_id', $fiscalYearId)
                ->where('deliverable_id', $deliverable->id)
                ->value('target_total') ?? 0);

            if ($tab === DistrictHubMonthlyTargetsService::SCOPE_HUB) {
                $grid = $this->monthlyTargets->hubGrid($fiscalYearId, (int) $deliverable->id);
                $referenceAnnual = $stateAnnual;
            } else {
                $grid = $this->monthlyTargets->districtGrid($fiscalYearId, (int) $deliverable->id);
                $referenceAnnual = (int) collect($grid)->sum('annual');
                if ($referenceAnnual <= 0) {
                    $referenceAnnual = $stateAnnual;
                }
            }

            $columnTotals = $this->monthlyTargets->columnTotals($grid, $tab);
        }

        return view('admin.targets.district-hub-monthly', [
            'fiscalYears' => $fiscalYears,
            'fiscalYearId' => $fiscalYearId,
            'fiscalYear' => $fiscalYear,
            'tab' => $tab,
            'monthLabels' => $monthLabels,
            'deliverableId' => $deliverableId,
            'deliverable' => $deliverable,
            'scopeDeliverables' => $scopeDeliverables,
            'pending' => $pending,
            'grid' => $grid,
            'columnTotals' => $columnTotals,
            'referenceAnnual' => $referenceAnnual,
            'stateAnnual' => $stateAnnual,
            'configuredIndicators' => $this->monthlyTargets->configuredIndicatorsForScope($tab),
            'districtPresets' => $tab === DistrictHubMonthlyTargetsService::SCOPE_DISTRICT
                ? $this->presetService->districtPresets()
                : [],
        ]);
    }

    public function applyDistrictPreset(Request $request): RedirectResponse
    {
        $validated = $request->validate([
            'fiscal_year_id' => ['required', 'integer', Rule::exists('fiscal_years', 'id')->whereIn('code', FiscalYear::UI_SELECTABLE_CODES)],
            'deliverable_code' => ['required', 'string', 'max:120'],
        ]);

        $fyId = (int) $validated['fiscal_year_id'];
        $code = strtolower(trim((string) $validated['deliverable_code']));

        try {
            $result = $this->presetService->applyDistrictPreset($fyId, $code);
        } catch (InvalidArgumentException $e) {
            return redirect()
                ->route('admin.targets.district-hub-monthly', [
                    'fiscal_year_id' => $fyId,
                    'tab' => DistrictHubMonthlyTargetsService::SCOPE_DISTRICT,
                ])
                ->withErrors(['preset' => $e->getMessage()]);
        }

        $deliverableId = (int) (Deliverable::query()->where('code', $code)->value('id') ?? 0);

        $this->auditLogger->record(
            $request,
            'targets.district-monthly.preset-applied',
            Deliverable::class,
            $deliverableId > 0 ? $deliverableId : null,
            null,
            [
                'fiscal_year_id' => $fyId,
                'deliverable_code' => $code,
                'districts' => $result['districts'],
                'state_total' => $result['state_total'],
            ],
            'Official district monthly preset applied',
        );

        return redirect()
            ->route('admin.targets.district-hub-monthly', [
                'fiscal_year_id' => $fyId,
                'deliverable_id' => $deliverableId > 0 ? $deliverableId : null,
                'tab' => DistrictHubMonthlyTargetsService::SCOPE_DISTRICT,
            ])
            ->with('status', sprintf(
                'Official allocation loaded for %s (%d districts, state total %s).',
                $code,
                $result['districts'],
                number_format($result['state_total']),
            ));
    }

    public function updateDistrict(Request $request): RedirectResponse
    {
        $validated = $request->validate([
            'fiscal_year_id' => ['required', 'integer', Rule::exists('fiscal_years', 'id')->whereIn('code', FiscalYear::UI_SELECTABLE_CODES)],
            'deliverable_id' => ['required', 'integer', 'exists:deliverables,id'],
            'districts' => ['required', 'array'],
            'districts.*' => ['array'],
            'districts.*.*' => ['nullable', 'integer', 'min:0', 'max:999999999'],
        ]);

        $fyId = (int) $validated['fiscal_year_id'];
        $deliverableId = (int) $validated['deliverable_id'];

        $this->monthlyTargets->saveDistrictGrid($fyId, $deliverableId, (array) $validated['districts']);

        $this->auditLogger->record(
            $request,
            'targets.district-monthly.updated',
            Deliverable::class,
            $deliverableId,
            null,
            ['fiscal_year_id' => $fyId],
            'District monthly targets saved',
        );

        return redirect()
            ->route('admin.targets.district-hub-monthly', [
                'fiscal_year_id' => $fyId,
                'deliverable_id' => $deliverableId,
                'tab' => DistrictHubMonthlyTargetsService::SCOPE_DISTRICT,
            ])
            ->with('status', 'District monthly targets saved.');
    }

    public function updateHub(Request $request): RedirectResponse
    {
        $validated = $request->validate([
            'fiscal_year_id' => ['required', 'integer', Rule::exists('fiscal_years', 'id')->whereIn('code', FiscalYear::UI_SELECTABLE_CODES)],
            'deliverable_id' => ['required', 'integer', 'exists:deliverables,id'],
            'hubs' => ['required', 'array'],
            'hubs.*' => ['array'],
            'hubs.*.*' => ['nullable', 'integer', 'min:0', 'max:999999999'],
        ]);

        $fyId = (int) $validated['fiscal_year_id'];
        $deliverableId = (int) $validated['deliverable_id'];

        $this->monthlyTargets->saveHubGrid($fyId, $deliverableId, (array) $validated['hubs']);

        $this->auditLogger->record(
            $request,
            'targets.hub-monthly.updated',
            Deliverable::class,
            $deliverableId,
            null,
            ['fiscal_year_id' => $fyId],
            'Hub monthly targets saved',
        );

        return redirect()
            ->route('admin.targets.district-hub-monthly', [
                'fiscal_year_id' => $fyId,
                'deliverable_id' => $deliverableId,
                'tab' => DistrictHubMonthlyTargetsService::SCOPE_HUB,
            ])
            ->with('status', 'Hub monthly targets saved.');
    }
}
