<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\Deliverable;
use App\Models\DistrictDeliverableTarget;
use App\Models\FiscalYear;
use App\Models\StaffMonthlyTarget;
use App\Models\User;
use App\Services\AdminAuditLogger;
use App\Services\StaffDeliverableMonthlyTargetService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\View\View;

class StaffDeliverableMonthlyTargetController extends Controller
{
    public function __construct(
        private StaffDeliverableMonthlyTargetService $monthlyTargets,
        private AdminAuditLogger $auditLogger,
    ) {}

    public function index(Request $request, User $user): View|RedirectResponse
    {
        if ($user->role !== 'district_staff') {
            abort(404);
        }

        if (! $user->district_id) {
            return redirect()->route('admin.staff.index')->withErrors(['user' => 'Assign a district to this staff user first.']);
        }

        $fiscalYears = FiscalYear::query()->orderByDesc('starts_on')->get();
        $fiscalYearId = (int) ($request->query('fiscal_year_id')
            ?: FiscalYear::query()->where('is_active', true)->value('id')
            ?: $fiscalYears->first()?->id);

        $deliverables = Deliverable::query()
            ->where('is_active', true)
            ->orderBy('sort_order')
            ->orderBy('id')
            ->get();

        $districtTargets = DistrictDeliverableTarget::query()
            ->where('fiscal_year_id', $fiscalYearId)
            ->where('district_id', (int) $user->district_id)
            ->whereIn('deliverable_id', $deliverables->pluck('id'))
            ->pluck('target_total', 'deliverable_id');

        return view('admin.staff.monthly-targets-index', [
            'user' => $user->load(['district', 'hub', 'designationRecord']),
            'fiscalYears' => $fiscalYears,
            'fiscalYearId' => $fiscalYearId,
            'deliverables' => $deliverables,
            'districtTargets' => $districtTargets,
        ]);
    }

    public function edit(Request $request, User $user, Deliverable $deliverable): View|RedirectResponse
    {
        if ($user->role !== 'district_staff') {
            abort(404);
        }

        if (! $deliverable->is_active) {
            abort(404);
        }

        if (! $user->district_id) {
            return redirect()->route('admin.staff.index')->withErrors(['user' => 'Assign a district to this staff user first.']);
        }

        $fiscalYears = FiscalYear::query()->orderByDesc('starts_on')->get();
        $fiscalYearId = (int) ($request->query('fiscal_year_id')
            ?: FiscalYear::query()->where('is_active', true)->value('id')
            ?: $fiscalYears->first()?->id);

        $districtTarget = $this->monthlyTargets->districtTargetTotal($fiscalYearId, (int) $user->district_id, $deliverable->id);
        $othersAnnual = $this->monthlyTargets->otherStaffDistrictTotal($fiscalYearId, (int) $user->district_id, $deliverable->id, $user->id);

        $existing = StaffMonthlyTarget::query()
            ->where('fiscal_year_id', $fiscalYearId)
            ->where('user_id', $user->id)
            ->where('deliverable_id', $deliverable->id)
            ->pluck('target_count', 'month_number')
            ->all();

        return view('admin.staff.monthly-targets-edit', [
            'user' => $user->load(['district', 'hub', 'designationRecord']),
            'deliverable' => $deliverable,
            'fiscalYears' => $fiscalYears,
            'fiscalYearId' => $fiscalYearId,
            'districtTarget' => $districtTarget,
            'othersAnnualTotal' => $othersAnnual,
            'existing' => $existing,
            'applyUrl' => $deliverable->code === 'cfa' ? $user->referralApplyUrl() : null,
        ]);
    }

    public function update(Request $request, User $user, Deliverable $deliverable): RedirectResponse
    {
        if ($user->role !== 'district_staff') {
            abort(404);
        }

        if (! $deliverable->is_active) {
            abort(404);
        }

        if (! $user->district_id) {
            return redirect()->route('admin.staff.index')->withErrors(['user' => 'Assign a district to this staff user first.']);
        }

        $rules = [
            'fiscal_year_id' => ['required', 'integer', 'exists:fiscal_years,id'],
        ];
        foreach (range(1, 12) as $m) {
            $rules['months.'.$m] = ['nullable', 'integer', 'min:0', 'max:999999999'];
        }

        $request->validate($rules);

        $fyId = (int) $request->input('fiscal_year_id');

        $districtTarget = $this->monthlyTargets->districtTargetTotal($fyId, (int) $user->district_id, $deliverable->id);
        if ($districtTarget === null) {
            return redirect()
                ->route('admin.staff.monthly-targets.edit', ['user' => $user, 'deliverable' => $deliverable->code, 'fiscal_year_id' => $fyId])
                ->withErrors(['district' => 'Set the district target for this deliverable first (Admin → District targets).']);
        }

        $myTotal = 0;
        foreach (range(1, 12) as $m) {
            $raw = $request->input('months.'.$m);
            $myTotal += $raw === null || $raw === '' ? 0 : (int) $raw;
        }

        $others = $this->monthlyTargets->otherStaffDistrictTotal($fyId, (int) $user->district_id, $deliverable->id, $user->id);
        $districtWideAnnual = $myTotal + $others;

        $beforeMonths = StaffMonthlyTarget::query()
            ->where('fiscal_year_id', $fyId)
            ->where('user_id', $user->id)
            ->where('deliverable_id', $deliverable->id)
            ->pluck('target_count', 'month_number')
            ->all();
        $before = ['fiscal_year_id' => $fyId, 'deliverable_id' => $deliverable->id, 'deliverable_code' => $deliverable->code, 'months' => []];
        foreach (range(1, 12) as $m) {
            $before['months']['M'.$m] = (int) ($beforeMonths[$m] ?? 0);
        }

        DB::transaction(function () use ($fyId, $user, $deliverable, $request): void {
            foreach (range(1, 12) as $month) {
                $raw = $request->input('months.'.$month);
                $val = $raw === null || $raw === '' ? 0 : (int) $raw;
                StaffMonthlyTarget::query()->updateOrCreate(
                    [
                        'fiscal_year_id' => $fyId,
                        'user_id' => $user->id,
                        'deliverable_id' => $deliverable->id,
                        'month_number' => $month,
                    ],
                    ['target_count' => $val]
                );
            }
        });

        $after = ['fiscal_year_id' => $fyId, 'deliverable_id' => $deliverable->id, 'deliverable_code' => $deliverable->code, 'months' => []];
        foreach (range(1, 12) as $m) {
            $raw = $request->input('months.'.$m);
            $after['months']['M'.$m] = $raw === null || $raw === '' ? 0 : (int) $raw;
        }

        $this->auditLogger->record(
            $request,
            'staff_deliverable_monthly_targets.updated',
            User::class,
            $user->id,
            $before,
            $after,
            'Monthly targets ('.$deliverable->name.') saved for '.$user->name,
        );

        $dName = $deliverable->name;
        $status = match (true) {
            $districtWideAnnual === $districtTarget => 'Monthly targets saved for '.$dName.'. All staff in this district match the district target.',
            $districtWideAnnual < $districtTarget => 'Monthly targets saved for '.$dName.'. District still short by '.number_format($districtTarget - $districtWideAnnual).' across all staff in this district.',
            default => 'Monthly targets saved for '.$dName.'. District is over target by '.number_format($districtWideAnnual - $districtTarget).' — adjust when ready.',
        };

        return redirect()
            ->route('admin.staff.monthly-targets.edit', ['user' => $user, 'deliverable' => $deliverable->code, 'fiscal_year_id' => $fyId])
            ->with('status', $status);
    }

    /**
     * Backward compatibility: old CFA-only POST URL.
     */
    public function updateCfaLegacy(Request $request, User $user): RedirectResponse
    {
        $deliverable = Deliverable::query()->where('code', 'cfa')->firstOrFail();

        return $this->update($request, $user, $deliverable);
    }
}
