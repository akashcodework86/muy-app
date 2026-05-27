<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\Hub;
use App\Models\District;
use App\Services\StaffCheckInExcelExportService;
use App\Services\StaffCheckInService;
use Carbon\Carbon;
use Illuminate\Http\Request;
use Illuminate\View\View;
use Symfony\Component\HttpFoundation\StreamedResponse;

class StaffCheckInAdminController extends Controller
{
    public function __construct(
        private readonly StaffCheckInService $checkInService,
        private readonly StaffCheckInExcelExportService $excelExport,
    ) {}

    public function index(Request $request): View
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

        return view('admin.staff-check-ins.index', [
            'date' => $date,
            'summary' => $summary,
            'roleFilter' => $roleFilter,
            'hubId' => $hubId,
            'districtId' => $districtId,
            'statusFilter' => $statusFilter,
            'hubs' => Hub::query()->orderBy('name')->get(['id', 'name']),
            'districts' => District::query()->orderBy('name')->get(['id', 'name', 'hub_id']),
            'roleOptions' => [
                'district_staff' => 'District staff',
                'hub_admin' => 'Hub admin',
                'state_staff' => 'State staff (SPOC)',
            ],
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

        $roleOptions = [
            'district_staff' => 'District staff',
            'hub_admin' => 'Hub admin',
            'state_staff' => 'State staff (SPOC)',
        ];

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
            $roleOptions,
            $roleFilter,
            $hubId,
            $districtId,
            $statusFilter,
        );
    }
}
