<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\FiscalYear;
use App\Services\AdminAuditLogger;
use App\Services\OfficialDistrictMonthlyTargetService;
use App\Services\ServiceTargetDeliverableSyncService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;
use Illuminate\View\View;

class OfficialDistrictMonthlyTargetsController extends Controller
{
    public function __construct(
        private readonly OfficialDistrictMonthlyTargetService $targets,
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
        $viewData = $this->targets->buildViewData($fiscalYearId);

        return view('admin.targets.official-district-monthly', [
            'fiscalYears' => $fiscalYears,
            'fiscalYearId' => $fiscalYearId,
            'fiscalYear' => $fiscalYear,
            'monthLabels' => $this->targets->fiscalMonthLabels($fiscalYear),
            'districtBlocks' => $viewData['district_blocks'],
            'hubDistributionBlocks' => [],
            'stateOnlyRows' => $viewData['state_only_rows'],
            'hubOnlyPage' => false,
        ]);
    }

    public function hubDistribution(Request $request): View
    {
        $this->serviceDeliverables->syncAllServices();

        [$fiscalYearId, $fiscalYears] = FiscalYear::resolveIdForUi(
            $request->query('fiscal_year_id') ? (int) $request->query('fiscal_year_id') : null
        );

        $fiscalYear = $fiscalYears->firstWhere('id', $fiscalYearId);
        $viewData = $this->targets->buildViewData($fiscalYearId);

        return view('admin.targets.official-district-monthly', [
            'fiscalYears' => $fiscalYears,
            'fiscalYearId' => $fiscalYearId,
            'fiscalYear' => $fiscalYear,
            'monthLabels' => $this->targets->fiscalMonthLabels($fiscalYear),
            'districtBlocks' => [],
            'hubDistributionBlocks' => $viewData['hub_distribution_blocks'],
            'stateOnlyRows' => [],
            'hubOnlyPage' => true,
        ]);
    }

    public function applyAll(Request $request): RedirectResponse
    {
        return $this->applyTargetsFromRequest(
            $request,
            'admin.targets.official-district-monthly',
            'targets.official-district-monthly.applied',
            'Official district monthly targets assigned from form',
        );
    }

    public function hubDistributionApply(Request $request): RedirectResponse
    {
        return $this->applyTargetsFromRequest(
            $request,
            'admin.targets.official-hub-distribution-monthly',
            'targets.official-hub-distribution-monthly.applied',
            'Official hub distribution monthly targets assigned from form',
        );
    }

    private function applyTargetsFromRequest(
        Request $request,
        string $redirectRoute,
        string $auditAction,
        string $auditDescription,
    ): RedirectResponse {
        $validated = $request->validate([
            'fiscal_year_id' => ['required', 'integer', Rule::exists('fiscal_years', 'id')->whereIn('code', FiscalYear::UI_SELECTABLE_CODES)],
            'blocks' => ['nullable', 'array'],
            'blocks.*.districts' => ['nullable', 'array'],
            'blocks.*.districts.*' => ['nullable', 'array'],
            'blocks.*.districts.*.*' => ['nullable', 'integer', 'min:0'],
            'blocks.*.hubs' => ['nullable', 'array'],
            'blocks.*.hubs.*' => ['nullable', 'array'],
            'blocks.*.hubs.*.*' => ['nullable', 'integer', 'min:0'],
            'state_only' => ['nullable', 'array'],
            'state_only.*' => ['nullable', 'array'],
            'state_only.*.*' => ['nullable', 'integer', 'min:0'],
            'district_payload' => ['nullable', 'string'],
        ]);

        $fyId = (int) $validated['fiscal_year_id'];
        $input = $this->decodeDistrictPayload(
            [
                'blocks' => $validated['blocks'] ?? [],
                'state_only' => $validated['state_only'] ?? [],
                'unresolved_blocks' => [],
                'unresolved_state_only' => [],
            ],
            $validated['district_payload'] ?? null,
        );

        if (
            ($input['blocks'] ?? []) === []
            && ($input['state_only'] ?? []) === []
            && ($input['unresolved_blocks'] ?? []) === []
            && ($input['unresolved_state_only'] ?? []) === []
        ) {
            return redirect()
                ->route($redirectRoute, ['fiscal_year_id' => $fyId])
                ->withErrors(['apply' => 'No target values were submitted. Edit cells and click Update targets again.']);
        }

        $result = $this->targets->applyFromInput($fyId, $input);

        $this->auditLogger->record(
            $request,
            $auditAction,
            FiscalYear::class,
            $fyId,
            null,
            $result,
            $auditDescription,
        );

        $redirect = redirect()
            ->route($redirectRoute, ['fiscal_year_id' => $fyId]);

        if ($result['errors'] !== []) {
            return $redirect
                ->with('status', 'Applied '.$result['applied'].' blocks. Skipped '.$result['skipped'].'.')
                ->withErrors(['apply' => $result['errors']]);
        }

        return $redirect->with(
            'status',
            'Saved official district monthly targets ('.$result['applied'].' blocks/rows).',
        );
    }

    /**
     * @param  array{
     *     blocks: array<int, array<string, mixed>>,
     *     state_only: array<int, array<int, int>>,
     *     unresolved_blocks: array<int, array<string, mixed>>,
     *     unresolved_state_only: array<int, array<string, mixed>>
     * }  $input
     * @return array{
     *     blocks: array<int, array<string, mixed>>,
     *     state_only: array<int, array<int, int>>,
     *     unresolved_blocks: array<int, array<string, mixed>>,
     *     unresolved_state_only: array<int, array<string, mixed>>
     * }
     */
    private function decodeDistrictPayload(array $input, ?string $json): array
    {
        if ($json === null || trim($json) === '') {
            return $input;
        }

        $decoded = json_decode($json, true);
        if (! is_array($decoded)) {
            return $input;
        }

        return [
            'blocks' => is_array($decoded['blocks'] ?? null) ? $decoded['blocks'] : ($input['blocks'] ?? []),
            'state_only' => is_array($decoded['state_only'] ?? null) ? $decoded['state_only'] : ($input['state_only'] ?? []),
            'unresolved_blocks' => is_array($decoded['unresolved_blocks'] ?? null) ? array_values($decoded['unresolved_blocks']) : ($input['unresolved_blocks'] ?? []),
            'unresolved_state_only' => is_array($decoded['unresolved_state_only'] ?? null) ? array_values($decoded['unresolved_state_only']) : ($input['unresolved_state_only'] ?? []),
        ];
    }
}
