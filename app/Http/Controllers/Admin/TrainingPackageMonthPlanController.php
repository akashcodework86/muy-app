<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\District;
use App\Services\AdminAuditLogger;
use App\Services\TrainingPackageMonthSessionService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Schema;
use Illuminate\Validation\Rule;
use Illuminate\View\View;

class TrainingPackageMonthPlanController extends Controller
{
    public function __construct(
        private AdminAuditLogger $auditLogger,
        private TrainingPackageMonthSessionService $monthSessions,
    ) {}

    public function index(Request $request): View
    {
        $calendarYear = (int) $request->query('calendar_year', now()->year);
        $calendarMonth = (int) $request->query('calendar_month', now()->month);
        $calendarMonth = max(1, min(12, $calendarMonth));

        $migrationMissing = ! Schema::hasTable('training_package_month_sessions');

        $districtPlans = $migrationMissing
            ? collect()
            : $this->monthSessions->districtsWithSlotsForMonth($calendarYear, $calendarMonth);

        $statewideSummary = $migrationMissing
            ? ['required' => 0, 'filled' => 0, 'remaining' => 0]
            : $this->monthSessions->statewideMonthSummary($calendarYear, $calendarMonth);

        $yearOptions = range((int) now()->year + 1, 2000);
        if (! in_array($calendarYear, $yearOptions, true)) {
            $yearOptions[] = $calendarYear;
            rsort($yearOptions);
        }

        return view('admin.training-package-month-plans.index', [
            'calendarYear' => $calendarYear,
            'calendarMonth' => $calendarMonth,
            'districtPlans' => $districtPlans,
            'statewideSummary' => $statewideSummary,
            'migrationMissing' => $migrationMissing,
            'yearOptions' => $yearOptions,
            'monthOptions' => collect(range(1, 12))->mapWithKeys(fn (int $month): array => [
                $month => now()->setDate($calendarYear, $month, 1)->format('F'),
            ]),
        ]);
    }

    public function store(Request $request): RedirectResponse
    {
        abort_unless(Schema::hasTable('training_package_month_sessions'), 422, 'Training package month sessions table is missing.');

        $validated = $request->validate([
            'calendar_year' => ['required', 'integer', 'min:2000', 'max:2100'],
            'calendar_month' => ['required', 'integer', 'min:1', 'max:12'],
            'districts' => ['required', 'array'],
            'districts.*.district_id' => ['required', 'integer', Rule::exists('districts', 'id')],
            'districts.*.sessions' => ['nullable', 'array'],
            'districts.*.sessions.*.id' => ['nullable', 'integer', 'min:1'],
            'districts.*.sessions.*.session_name' => ['nullable', 'string', 'max:191'],
            'districts.*.extra_sessions' => ['nullable', 'array'],
            'districts.*.extra_sessions.*.id' => ['nullable', 'integer', 'min:1'],
            'districts.*.extra_sessions.*.session_name' => ['nullable', 'string', 'max:191'],
        ]);

        $calendarYear = (int) $validated['calendar_year'];
        $calendarMonth = (int) $validated['calendar_month'];
        $districtPlans = collect((array) $validated['districts'])
            ->map(fn (array $row): array => [
                'district_id' => (int) $row['district_id'],
                'sessions' => array_values((array) ($row['sessions'] ?? [])),
                'extra_sessions' => array_values((array) ($row['extra_sessions'] ?? [])),
            ])
            ->all();

        $this->monthSessions->syncMonthPlan(
            $calendarYear,
            $calendarMonth,
            $districtPlans,
            (int) $request->user()->id
        );

        $this->auditLogger->record(
            $request,
            'training_package_month_plan.sync',
            District::class,
            null,
            null,
            [
                'calendar_year' => $calendarYear,
                'calendar_month' => $calendarMonth,
                'district_count' => count($districtPlans),
            ],
            'Synced training package monthly session plan.'
        );

        return redirect()
            ->route('admin.training-package-month-plans.index', [
                'calendar_year' => $calendarYear,
                'calendar_month' => $calendarMonth,
            ])
            ->with('status', 'Monthly training package sessions saved.');
    }
}
