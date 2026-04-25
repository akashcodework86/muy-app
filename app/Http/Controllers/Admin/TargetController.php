<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\Deliverable;
use App\Models\District;
use App\Models\DistrictDeliverableTarget;
use App\Models\FiscalYear;
use App\Models\StaffMonthlyTarget;
use App\Models\StateDeliverableTarget;
use App\Services\AdminAuditLogger;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\Rule;
use Illuminate\View\View;

class TargetController extends Controller
{
    private const CORE_TARGET_DELIVERABLES = [
        'cfa' => [
            'sort_order' => 3,
            'name' => 'Number of Call for Applications filled',
            'mis_entry_label' => 'Call for Application (CFA)',
        ],
        'onboarding' => [
            'sort_order' => 4,
            'name' => 'Number of Incubatees Onboarded',
            'mis_entry_label' => 'Onboarded Incubatees',
        ],
    ];

    public function __construct(
        private AdminAuditLogger $auditLogger,
    ) {}

    public function stateForm(Request $request): View
    {
        $this->ensureCoreTargetDeliverables();

        [$fiscalYearId, $fiscalYears] = FiscalYear::resolveIdForUi(
            $request->query('fiscal_year_id') ? (int) $request->query('fiscal_year_id') : null
        );

        $fiscalYear = $fiscalYears->firstWhere('id', $fiscalYearId) ?? $fiscalYears->first();
        if ($fiscalYear) {
            $fiscalYearId = $fiscalYear->id;
        }

        $deliverables = Deliverable::query()->where('is_active', true)->orderBy('sort_order')->get();

        $existing = [];
        if ($fiscalYearId) {
            $existing = StateDeliverableTarget::query()
                ->where('fiscal_year_id', $fiscalYearId)
                ->pluck('target_total', 'deliverable_id')
                ->all();
        }

        return view('admin.targets.state', [
            'fiscalYears' => $fiscalYears,
            'fiscalYearId' => $fiscalYearId,
            'deliverables' => $deliverables,
            'existing' => $existing,
        ]);
    }

    public function stateUpdate(Request $request): RedirectResponse
    {
        $this->ensureCoreTargetDeliverables();

        $deliverableIds = Deliverable::query()->where('is_active', true)->pluck('id');

        $rules = [
            'fiscal_year_id' => ['required', 'integer', Rule::exists('fiscal_years', 'id')->whereIn('code', FiscalYear::UI_SELECTABLE_CODES)],
        ];
        foreach ($deliverableIds as $id) {
            $rules['targets.'.$id] = ['nullable', 'integer', 'min:0', 'max:999999999'];
        }

        $validated = $request->validate($rules);
        $fyId = (int) $validated['fiscal_year_id'];

        $before = StateDeliverableTarget::query()
            ->where('fiscal_year_id', $fyId)
            ->pluck('target_total', 'deliverable_id')
            ->map(fn ($v) => (int) $v)
            ->all();

        DB::transaction(function () use ($fyId, $deliverableIds, $request): void {
            foreach ($deliverableIds as $deliverableId) {
                $raw = $request->input('targets.'.$deliverableId);
                $val = $raw === null || $raw === '' ? 0 : (int) $raw;
                StateDeliverableTarget::query()->updateOrCreate(
                    [
                        'fiscal_year_id' => $fyId,
                        'deliverable_id' => $deliverableId,
                    ],
                    ['target_total' => $val]
                );
            }
        });

        $after = [];
        foreach ($deliverableIds as $deliverableId) {
            $raw = $request->input('targets.'.$deliverableId);
            $after[(int) $deliverableId] = $raw === null || $raw === '' ? 0 : (int) $raw;
        }

        $this->auditLogger->record(
            $request,
            'targets.state.updated',
            FiscalYear::class,
            $fyId,
            ['deliverable_id_to_total' => $before],
            ['deliverable_id_to_total' => $after],
            'State deliverable targets updated',
        );

        return redirect()
            ->route('admin.targets.state', ['fiscal_year_id' => $fyId])
            ->with('status', 'State targets saved.');
    }

    public function districtForm(Request $request): View
    {
        $this->ensureCoreTargetDeliverables();

        [$fiscalYearId, $fiscalYears] = FiscalYear::resolveIdForUi(
            $request->query('fiscal_year_id') ? (int) $request->query('fiscal_year_id') : null
        );

        $fiscalYear = $fiscalYears->firstWhere('id', $fiscalYearId) ?? $fiscalYears->first();
        if ($fiscalYear) {
            $fiscalYearId = $fiscalYear->id;
        }

        $deliverables = Deliverable::query()->where('is_active', true)->orderBy('sort_order')->get();
        $deliverableId = (int) ($request->query('deliverable_id') ?: $deliverables->first()?->id);

        $stateTarget = null;
        if ($fiscalYearId && $deliverableId) {
            $stateRow = $this->ensureStateDeliverableTargetRow($fiscalYearId, $deliverableId);
            $stateTarget = (int) $stateRow->target_total;
        }

        $districts = District::query()
            ->with('hub')
            ->orderBy('hub_id')
            ->orderBy('sort_order')
            ->get();

        $existing = [];
        if ($fiscalYearId && $deliverableId) {
            $existing = DistrictDeliverableTarget::query()
                ->where('fiscal_year_id', $fiscalYearId)
                ->where('deliverable_id', $deliverableId)
                ->pluck('target_total', 'district_id')
                ->all();
        }

        return view('admin.targets.district', [
            'fiscalYears' => $fiscalYears,
            'fiscalYearId' => $fiscalYearId,
            'deliverables' => $deliverables,
            'deliverableId' => $deliverableId,
            'stateTarget' => $stateTarget,
            'districts' => $districts,
            'existing' => $existing,
        ]);
    }

    public function districtUpdate(Request $request): RedirectResponse
    {
        $this->ensureCoreTargetDeliverables();

        $districtIds = District::query()->pluck('id');

        $rules = [
            'fiscal_year_id' => ['required', 'integer', Rule::exists('fiscal_years', 'id')->whereIn('code', FiscalYear::UI_SELECTABLE_CODES)],
            'deliverable_id' => ['required', 'integer', 'exists:deliverables,id'],
        ];
        foreach ($districtIds as $id) {
            $rules['districts.'.$id] = ['nullable', 'integer', 'min:0', 'max:999999999'];
        }

        $request->validate($rules);

        $fyId = (int) $request->input('fiscal_year_id');
        $deliverableId = (int) $request->input('deliverable_id');

        $state = StateDeliverableTarget::query()
            ->where('fiscal_year_id', $fyId)
            ->where('deliverable_id', $deliverableId)
            ->first()
            ?? $this->ensureStateDeliverableTargetRow($fyId, $deliverableId);

        $sum = 0;
        foreach ($districtIds as $districtId) {
            $raw = $request->input('districts.'.$districtId);
            $sum += $raw === null || $raw === '' ? 0 : (int) $raw;
        }

        $stateTotal = (int) $state->target_total;

        $before = DistrictDeliverableTarget::query()
            ->where('fiscal_year_id', $fyId)
            ->where('deliverable_id', $deliverableId)
            ->pluck('target_total', 'district_id')
            ->map(fn ($v) => (int) $v)
            ->all();

        DB::transaction(function () use ($fyId, $deliverableId, $districtIds, $request): void {
            foreach ($districtIds as $districtId) {
                $raw = $request->input('districts.'.$districtId);
                $val = $raw === null || $raw === '' ? 0 : (int) $raw;
                DistrictDeliverableTarget::query()->updateOrCreate(
                    [
                        'fiscal_year_id' => $fyId,
                        'district_id' => $districtId,
                        'deliverable_id' => $deliverableId,
                    ],
                    ['target_total' => $val]
                );
            }
        });

        $after = [];
        foreach ($districtIds as $districtId) {
            $raw = $request->input('districts.'.$districtId);
            $after[(int) $districtId] = $raw === null || $raw === '' ? 0 : (int) $raw;
        }

        $this->auditLogger->record(
            $request,
            'targets.district.updated',
            FiscalYear::class,
            $fyId,
            [
                'deliverable_id' => $deliverableId,
                'district_id_to_total' => $before,
            ],
            [
                'deliverable_id' => $deliverableId,
                'district_id_to_total' => $after,
            ],
            'District deliverable targets updated',
        );

        $status = match (true) {
            $sum === $stateTotal => 'District targets saved. District total matches state target.',
            $sum < $stateTotal => 'District targets saved. Remaining to allocate to match state target: '.number_format($stateTotal - $sum).'.',
            default => 'District targets saved. District total is over state target by '.number_format($sum - $stateTotal).' — adjust when ready.',
        };

        return redirect()
            ->route('admin.targets.district', [
                'fiscal_year_id' => $fyId,
                'deliverable_id' => $deliverableId,
            ])
            ->with('status', $status);
    }

    /**
     * State targets are not filled by legacy staff import (that writes staff_monthly_targets only).
     * When opening district allocation, auto-create a state row from imported monthlies if missing.
     */
    private function ensureStateDeliverableTargetRow(int $fiscalYearId, int $deliverableId): StateDeliverableTarget
    {
        $existing = StateDeliverableTarget::query()
            ->where('fiscal_year_id', $fiscalYearId)
            ->where('deliverable_id', $deliverableId)
            ->first();

        if ($existing) {
            return $existing;
        }

        $fromStaff = (int) StaffMonthlyTarget::query()
            ->where('fiscal_year_id', $fiscalYearId)
            ->where('deliverable_id', $deliverableId)
            ->sum('target_count');

        return StateDeliverableTarget::query()->create([
            'fiscal_year_id' => $fiscalYearId,
            'deliverable_id' => $deliverableId,
            'target_total' => $fromStaff,
        ]);
    }

    private function ensureCoreTargetDeliverables(): void
    {
        foreach (self::CORE_TARGET_DELIVERABLES as $code => $meta) {
            Deliverable::query()->updateOrCreate(
                ['code' => $code],
                [
                    'sort_order' => (int) $meta['sort_order'],
                    'name' => (string) $meta['name'],
                    'mis_entry_label' => (string) $meta['mis_entry_label'],
                    'is_active' => true,
                ]
            );
        }
    }
}
