<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\Deliverable;
use App\Models\District;
use App\Models\FiscalYear;
use App\Models\User;
use App\Services\AdminAuditLogger;
use App\Services\ServiceTargetAllocationService;
use App\Services\ServiceTargetDeliverableSyncService;
use App\Services\StaffDeliverableMonthlyTargetService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\Rule;
use Illuminate\View\View;

class ServiceTargetAllocationController extends Controller
{
    public function __construct(
        private readonly ServiceTargetAllocationService $allocation,
        private readonly StaffDeliverableMonthlyTargetService $monthlyTargets,
        private readonly ServiceTargetDeliverableSyncService $serviceDeliverables,
        private readonly AdminAuditLogger $auditLogger,
    ) {}

    public function index(Request $request): View
    {
        $this->serviceDeliverables->syncAllServices();

        [$fiscalYearId, $fiscalYears] = FiscalYear::resolveIdForUi(
            $request->query('fiscal_year_id') ? (int) $request->query('fiscal_year_id') : null
        );

        $districtId = $request->query('district_id') ? (int) $request->query('district_id') : null;
        $deliverableId = $request->query('deliverable_id') ? (int) $request->query('deliverable_id') : null;

        $districts = District::query()
            ->with('hub:id,name')
            ->orderBy('hub_id')
            ->orderBy('sort_order')
            ->orderBy('name')
            ->get(['id', 'name', 'hub_id']);

        $deliverables = Deliverable::query()
            ->where('is_active', true)
            ->orderBy('sort_order')
            ->orderBy('id')
            ->get(['id', 'code', 'name', 'mis_entry_label']);

        $districtTarget = null;
        $deliverable = null;
        $designationGroups = [];
        $defaultPercents = [];
        $percentValues = [];
        $previewRows = [];
        $district = $districtId ? $districts->firstWhere('id', $districtId) : null;

        if ($districtId && $deliverableId) {
            $deliverable = $deliverables->firstWhere('id', $deliverableId);
            if ($deliverable) {
                $districtTarget = $this->monthlyTargets->districtTargetTotal($fiscalYearId, $districtId, $deliverable->id);
                $designationGroups = $this->allocation->designationGroupsForDistrict($districtId);
                $defaultPercents = $this->allocation->defaultEqualPercents($designationGroups);
                $savedPercents = $this->allocation->savedDesignationPercents(
                    $fiscalYearId,
                    $districtId,
                    $deliverable->id
                );
                $hasOldInput = collect($designationGroups)->contains(function (array $group): bool {
                    $old = old('percent.'.$group['key']);

                    return $old !== null && $old !== '';
                });

                if ($hasOldInput) {
                    foreach ($designationGroups as $group) {
                        $key = $group['key'];
                        $old = old('percent.'.$key);
                        $percentValues[$key] = $old !== null && $old !== ''
                            ? (float) $old
                            : (float) ($defaultPercents[$key] ?? 0);
                    }
                } else {
                    $percentValues = $this->allocation->resolvePercentValues(
                        $designationGroups,
                        $defaultPercents,
                        $savedPercents
                    );
                }

                if ($districtTarget !== null && $districtTarget > 0 && $this->allocation->percentSum($percentValues) > 0) {
                    try {
                        $previewRows = $this->allocation->buildStaffAllocations(
                            $districtTarget,
                            $designationGroups,
                            $percentValues
                        );
                    } catch (\InvalidArgumentException) {
                        $previewRows = [];
                    }
                }
            }
        }

        return view('admin.targets.allocate-by-service', [
            'fiscalYears' => $fiscalYears,
            'fiscalYearId' => $fiscalYearId,
            'districts' => $districts,
            'districtId' => $districtId,
            'district' => $district,
            'deliverables' => $deliverables,
            'deliverableId' => $deliverableId,
            'deliverable' => $deliverable,
            'districtTarget' => $districtTarget,
            'designationGroups' => $designationGroups,
            'percentValues' => $percentValues,
            'previewRows' => $previewRows,
        ]);
    }

    public function apply(Request $request): RedirectResponse
    {
        $this->serviceDeliverables->syncAllServices();

        $deliverableIds = Deliverable::query()->where('is_active', true)->pluck('id');

        $validated = $request->validate([
            'fiscal_year_id' => ['required', 'integer', Rule::exists('fiscal_years', 'id')->whereIn('code', FiscalYear::UI_SELECTABLE_CODES)],
            'district_id' => ['required', 'integer', Rule::exists('districts', 'id')],
            'deliverable_id' => ['required', 'integer', Rule::in($deliverableIds->all())],
            'percent' => ['required', 'array'],
            'percent.*' => ['nullable', 'numeric', 'min:0', 'max:100'],
            'months' => ['nullable', 'array'],
            'months.*' => ['nullable', 'array'],
            'months.*.*' => ['nullable', 'integer', 'min:0'],
        ]);

        $fyId = (int) $validated['fiscal_year_id'];
        $districtId = (int) $validated['district_id'];
        $deliverableId = (int) $validated['deliverable_id'];

        $deliverable = Deliverable::query()->findOrFail($deliverableId);
        $districtTarget = $this->monthlyTargets->districtTargetTotal($fyId, $districtId, $deliverableId);

        if ($districtTarget === null) {
            return redirect()
                ->route('admin.targets.allocate-by-service', [
                    'fiscal_year_id' => $fyId,
                    'district_id' => $districtId,
                    'deliverable_id' => $deliverableId,
                ])
                ->withErrors(['district' => 'Set the district target for this service first (District targets).']);
        }

        $percents = $this->allocation->normalizePercentInput($validated['percent']);
        $percentSum = $this->allocation->percentSum($percents);

        if (abs($percentSum - 100) > 0.01) {
            return redirect()
                ->route('admin.targets.allocate-by-service', [
                    'fiscal_year_id' => $fyId,
                    'district_id' => $districtId,
                    'deliverable_id' => $deliverableId,
                ])
                ->withInput()
                ->withErrors(['percent' => 'Designation percentages must add up to 100% (currently '.round($percentSum, 1).'%).']);
        }

        $designationGroups = $this->allocation->designationGroupsForDistrict($districtId);

        if ($designationGroups === []) {
            return redirect()
                ->route('admin.targets.allocate-by-service', [
                    'fiscal_year_id' => $fyId,
                    'district_id' => $districtId,
                    'deliverable_id' => $deliverableId,
                ])
                ->withErrors(['district' => 'No active district staff found in this district.']);
        }

        try {
            $allocations = $this->allocation->buildStaffAllocations($districtTarget, $designationGroups, $percents);
        } catch (\InvalidArgumentException $e) {
            return redirect()
                ->route('admin.targets.allocate-by-service', [
                    'fiscal_year_id' => $fyId,
                    'district_id' => $districtId,
                    'deliverable_id' => $deliverableId,
                ])
                ->withInput()
                ->withErrors(['percent' => $e->getMessage()]);
        }

        if ($allocations === []) {
            return redirect()
                ->route('admin.targets.allocate-by-service', [
                    'fiscal_year_id' => $fyId,
                    'district_id' => $districtId,
                    'deliverable_id' => $deliverableId,
                ])
                ->withInput()
                ->withErrors(['percent' => 'Assign at least one designation a share above 0%.']);
        }

        $manualMonths = $this->allocation->normalizeMonthInput($validated['months'] ?? []);
        if ($manualMonths !== []) {
            $allowedUserIds = collect($allocations)->pluck('user_id')->map(fn ($id) => (int) $id)->all();
            foreach (array_keys($manualMonths) as $userId) {
                if (! in_array((int) $userId, $allowedUserIds, true)) {
                    return redirect()
                        ->route('admin.targets.allocate-by-service', [
                            'fiscal_year_id' => $fyId,
                            'district_id' => $districtId,
                            'deliverable_id' => $deliverableId,
                        ])
                        ->withInput()
                        ->withErrors(['months' => 'Invalid staff row in monthly targets. Reload the page and try again.']);
                }
            }

            $allocations = $this->allocation->applyManualMonths($allocations, $manualMonths);
        }

        $totalMismatch = $this->allocation->districtTotalMismatchMessage($allocations, $districtTarget);
        if ($totalMismatch !== null) {
            return redirect()
                ->route('admin.targets.allocate-by-service', [
                    'fiscal_year_id' => $fyId,
                    'district_id' => $districtId,
                    'deliverable_id' => $deliverableId,
                ])
                ->withInput()
                ->withErrors(['months' => $totalMismatch]);
        }

        DB::transaction(function () use ($fyId, $districtId, $deliverableId, $allocations, $percents): void {
            $this->allocation->applyAllocations($fyId, $deliverableId, $allocations);
            $this->allocation->saveDesignationPercents($fyId, $districtId, $deliverableId, $percents);
        });

        $district = District::query()->find($districtId);
        $summary = collect($allocations)->map(fn (array $row) => [
            'user_id' => $row['user_id'],
            'user_name' => $row['user_name'],
            'annual_total' => $row['annual_total'],
        ])->values()->all();

        $this->auditLogger->record(
            $request,
            'service_target_allocation.applied',
            Deliverable::class,
            $deliverableId,
            null,
            [
                'fiscal_year_id' => $fyId,
                'district_id' => $districtId,
                'deliverable_code' => $deliverable->code,
                'district_target' => $districtTarget,
                'percents' => $percents,
                'staff_allocations' => $summary,
            ],
            'Allocated '.$deliverable->name.' targets for '.($district?->name ?? 'district').' by designation %.',
        );

        $staffCount = count($allocations);

        return redirect()
            ->route('admin.targets.allocate-by-service', [
                'fiscal_year_id' => $fyId,
                'district_id' => $districtId,
                'deliverable_id' => $deliverableId,
            ])
            ->with('status', 'Applied '.$deliverable->name.' targets for '.number_format($districtTarget).' across '.$staffCount.' staff. Per-staff M1–M12 edits remain available from the staff list.');
    }
}
