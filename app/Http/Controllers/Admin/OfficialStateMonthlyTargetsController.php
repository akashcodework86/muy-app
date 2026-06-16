<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\FiscalYear;
use App\Services\AdminAuditLogger;
use App\Services\OfficialStateMonthlyTargetService;
use App\Services\ServiceTargetDeliverableSyncService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;
use Illuminate\View\View;

class OfficialStateMonthlyTargetsController extends Controller
{
    public function __construct(
        private readonly OfficialStateMonthlyTargetService $targets,
        private readonly ServiceTargetDeliverableSyncService $serviceDeliverables,
        private readonly AdminAuditLogger $auditLogger,
    ) {}

    public function index(Request $request): View
    {
        $this->serviceDeliverables->syncAllServices();

        [$fiscalYearId, $fiscalYears] = FiscalYear::resolveIdForUi(
            $request->query('fiscal_year_id') ? (int) $request->query('fiscal_year_id') : null
        );

        $fiscalYear = $fiscalYears->firstWhere('id', $fiscalYearId);
        $grid = $this->targets->buildGrid($fiscalYearId);
        $columnTotals = $this->targets->columnTotals($grid);

        return view('admin.targets.official-state-monthly', [
            'fiscalYears' => $fiscalYears,
            'fiscalYearId' => $fiscalYearId,
            'fiscalYear' => $fiscalYear,
            'monthLabels' => $this->targets->fiscalMonthLabels($fiscalYear),
            'grid' => $grid,
            'columnTotals' => $columnTotals,
        ]);
    }

    public function applyAll(Request $request): RedirectResponse
    {
        $validated = $request->validate([
            'fiscal_year_id' => ['required', 'integer', Rule::exists('fiscal_years', 'id')->whereIn('code', FiscalYear::UI_SELECTABLE_CODES)],
            'targets' => ['nullable', 'array'],
            'targets.*' => ['array'],
            'targets.*.*' => ['nullable', 'integer', 'min:0'],
            'targets_payload' => ['nullable', 'string'],
        ]);

        $fyId = (int) $validated['fiscal_year_id'];
        $targets = $this->decodeTargetsPayload(
            $validated['targets'] ?? [],
            $validated['targets_payload'] ?? null,
        );

        if ($targets === []) {
            return redirect()
                ->route('admin.targets.official-state-monthly', ['fiscal_year_id' => $fyId])
                ->withErrors(['apply' => 'No target values were submitted. Edit cells and click Update targets again.']);
        }

        $result = $this->targets->applyFromInput($fyId, $targets);

        $this->auditLogger->record(
            $request,
            'targets.official-state-monthly.applied',
            FiscalYear::class,
            $fyId,
            null,
            $result,
            'Official state monthly targets assigned from form',
        );

        $redirect = redirect()
            ->route('admin.targets.official-state-monthly', ['fiscal_year_id' => $fyId]);

        if ($result['errors'] !== []) {
            return $redirect
                ->with('status', 'Applied '.$result['applied'].' indicators. Skipped '.$result['skipped'].'.')
                ->withErrors(['apply' => $result['errors']]);
        }

        return $redirect->with(
            'status',
            'Saved official state monthly targets ('.$result['applied'].' indicators).',
        );
    }

    /**
     * @param  array<int, array<int, int>>  $targets
     * @return array<int, array<int, int>>
     */
    private function decodeTargetsPayload(array $targets, ?string $json): array
    {
        if ($json === null || trim($json) === '') {
            return $targets;
        }

        $decoded = json_decode($json, true);
        if (! is_array($decoded)) {
            return $targets;
        }

        $out = [];
        foreach ($decoded as $deliverableId => $months) {
            if (! is_array($months)) {
                continue;
            }
            $deliverableId = (int) $deliverableId;
            if ($deliverableId <= 0) {
                continue;
            }
            $out[$deliverableId] = [];
            foreach ($months as $month => $count) {
                $out[$deliverableId][(int) $month] = max(0, (int) $count);
            }
        }

        return $out !== [] ? $out : $targets;
    }
}
