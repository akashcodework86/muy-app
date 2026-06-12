<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\FiscalYear;
use App\Services\AdminAuditLogger;
use App\Services\ServiceTargetDeliverableSyncService;
use App\Services\StateMonthlyTargetIndicatorBootstrapService;
use App\Services\StateMonthlyTargetsService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;
use Illuminate\View\View;

class StateMonthlyTargetsController extends Controller
{
    public function __construct(
        private readonly StateMonthlyTargetsService $monthlyTargets,
        private readonly StateMonthlyTargetIndicatorBootstrapService $indicatorBootstrap,
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

        $fiscalYear = $fiscalYears->firstWhere('id', $fiscalYearId);
        $monthLabels = $this->monthlyTargets->fiscalMonthLabels($fiscalYear);
        $grid = $this->monthlyTargets->buildGrid($fiscalYearId);
        $columnTotals = $this->monthlyTargets->columnTotals($grid);

        return view('admin.targets.state-monthly', [
            'fiscalYears' => $fiscalYears,
            'fiscalYearId' => $fiscalYearId,
            'fiscalYear' => $fiscalYear,
            'monthLabels' => $monthLabels,
            'grid' => $grid,
            'columnTotals' => $columnTotals,
            'configuredIndicators' => $this->indicatorBootstrap->indicatorDefinitions(),
        ]);
    }

    public function update(Request $request): RedirectResponse
    {
        $this->indicatorBootstrap->ensureDeliverables();
        $allowedIds = $this->monthlyTargets->deliverables()->pluck('id')->map(fn ($id) => (int) $id)->all();

        $validated = $request->validate([
            'fiscal_year_id' => ['required', 'integer', Rule::exists('fiscal_years', 'id')->whereIn('code', FiscalYear::UI_SELECTABLE_CODES)],
            'deliverables' => ['required', 'array'],
            'deliverables.*' => ['array'],
            'deliverables.*.*' => ['nullable', 'integer', 'min:0', 'max:999999999'],
        ]);

        $fyId = (int) $validated['fiscal_year_id'];
        $input = (array) $validated['deliverables'];

        foreach (array_keys($input) as $deliverableId) {
            if (! in_array((int) $deliverableId, $allowedIds, true)) {
                return redirect()
                    ->route('admin.targets.state-monthly', ['fiscal_year_id' => $fyId])
                    ->withInput()
                    ->withErrors(['deliverables' => 'Invalid service row. Reload the page and try again.']);
            }
        }

        $this->monthlyTargets->saveGrid($fyId, $input);

        $this->auditLogger->record(
            $request,
            'targets.state-monthly.updated',
            FiscalYear::class,
            $fyId,
            null,
            ['deliverable_ids' => array_map('intval', array_keys($input))],
            'State monthly targets saved',
        );

        return redirect()
            ->route('admin.targets.state-monthly', ['fiscal_year_id' => $fyId])
            ->with('status', 'State monthly targets saved.');
    }
}
