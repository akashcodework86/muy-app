<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\District;
use App\Models\Hub;
use App\Services\StaffCheckInExcelExportService;
use App\Services\StaffCheckInMonthlyExportService;
use App\Services\StaffCheckInService;
use Carbon\Carbon;
use Illuminate\Http\Request;
use Illuminate\View\View;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpFoundation\StreamedResponse;

class StaffCheckInAdminController extends Controller
{
    private const ROLE_OPTIONS = [
        'district_staff' => 'District staff',
        'hub_admin' => 'Hub admin',
        'state_staff' => 'State staff (SPOC)',
    ];

    public function __construct(
        private readonly StaffCheckInService $checkInService,
        private readonly StaffCheckInExcelExportService $excelExport,
        private readonly StaffCheckInMonthlyExportService $monthlyExport,
    ) {}

    public function index(Request $request): View
    {
        $viewMode = (string) $request->query('view', 'monthly');
        if (! in_array($viewMode, ['monthly', 'daily'], true)) {
            $viewMode = 'monthly';
        }

        $roleFilter = (string) $request->query('role', '');
        $hubId = (int) $request->query('hub_id', 0);
        $districtId = (int) $request->query('district_id', 0);
        $statusFilter = (string) $request->query('status', '');

        $monthInput = (string) $request->query('month', now()->format('Y-m'));
        try {
            $month = Carbon::createFromFormat('Y-m', $monthInput)->startOfMonth();
        } catch (\Throwable) {
            $month = now()->startOfMonth();
        }

        $monthlyGrid = $this->checkInService->adminMonthlyGrid(
            $month,
            $roleFilter !== '' ? $roleFilter : null,
            $hubId > 0 ? $hubId : null,
            $districtId > 0 ? $districtId : null,
        );

        $dateInput = (string) $request->query('date', now()->toDateString());
        try {
            $date = Carbon::parse($dateInput)->startOfDay();
        } catch (\Throwable) {
            $date = now()->startOfDay();
        }

        $summary = $this->checkInService->adminSummaryForDate(
            $date,
            $roleFilter !== '' ? $roleFilter : null,
            $hubId > 0 ? $hubId : null,
            $districtId > 0 ? $districtId : null,
            in_array($statusFilter, ['present', 'absent'], true) ? $statusFilter : null,
        );

        return view('admin.staff-check-ins.index', [
            'viewMode' => $viewMode,
            'month' => $month,
            'monthlyGrid' => $monthlyGrid,
            'date' => $date,
            'summary' => $summary,
            'roleFilter' => $roleFilter,
            'hubId' => $hubId,
            'districtId' => $districtId,
            'statusFilter' => $statusFilter,
            'hubs' => Hub::query()->orderBy('name')->get(['id', 'name']),
            'districts' => District::query()->orderBy('name')->get(['id', 'name', 'hub_id']),
            'roleOptions' => self::ROLE_OPTIONS,
        ]);
    }

    public function export(Request $request): StreamedResponse
    {
        $dateInput = (string) $request->query('date', now()->toDateString());
        try {
            $date = Carbon::parse($dateInput)->startOfDay();
        } catch (\Throwable) {
            $date = now()->startOfDay();
        }

        $roleFilter = (string) $request->query('role', '');
        $hubId = (int) $request->query('hub_id', 0);
        $districtId = (int) $request->query('district_id', 0);
        $statusFilter = (string) $request->query('status', '');

        $summary = $this->checkInService->adminSummaryForDate(
            $date,
            $roleFilter !== '' ? $roleFilter : null,
            $hubId > 0 ? $hubId : null,
            $districtId > 0 ? $districtId : null,
            in_array($statusFilter, ['present', 'absent'], true) ? $statusFilter : null,
        );

        return $this->excelExport->download(
            $summary,
            $date,
            self::ROLE_OPTIONS,
            $roleFilter,
            $hubId,
            $districtId,
            $statusFilter,
        );
    }

    public function monthlyExportExcel(Request $request): StreamedResponse
    {
        [$grid, $roleFilter, $hubId, $districtId, $hubName, $districtName] = $this->monthlyGridFromRequest($request);

        return $this->monthlyExport->downloadExcel(
            $grid,
            self::ROLE_OPTIONS,
            $roleFilter,
            $hubId,
            $districtId,
            $hubName,
            $districtName,
        );
    }

    public function monthlyExportPdf(Request $request): Response
    {
        [$grid, $roleFilter, $hubId, $districtId, $hubName, $districtName] = $this->monthlyGridFromRequest($request);

        return $this->monthlyExport->downloadPdf(
            $grid,
            self::ROLE_OPTIONS,
            $roleFilter,
            $hubName,
            $districtName,
        );
    }

    /**
     * @return array{0: array<string, mixed>, 1: string, 2: int, 3: int, 4: ?string, 5: ?string}
     */
    private function monthlyGridFromRequest(Request $request): array
    {
        $monthInput = (string) $request->query('month', now()->format('Y-m'));
        try {
            $month = Carbon::createFromFormat('Y-m', $monthInput)->startOfMonth();
        } catch (\Throwable) {
            $month = now()->startOfMonth();
        }

        $roleFilter = (string) $request->query('role', '');
        $hubId = (int) $request->query('hub_id', 0);
        $districtId = (int) $request->query('district_id', 0);

        $hubName = $hubId > 0
            ? Hub::query()->whereKey($hubId)->value('name')
            : null;
        $districtName = $districtId > 0
            ? District::query()->whereKey($districtId)->value('name')
            : null;

        $grid = $this->checkInService->adminMonthlyGrid(
            $month,
            $roleFilter !== '' ? $roleFilter : null,
            $hubId > 0 ? $hubId : null,
            $districtId > 0 ? $districtId : null,
        );

        return [$grid, $roleFilter, $hubId, $districtId, $hubName, $districtName];
    }
}
