<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Services\PendingActionsReportService;
use Illuminate\Http\Request;
use Illuminate\View\View;

class PendingActionsController extends Controller
{
    public function __construct(
        private readonly PendingActionsReportService $report,
    ) {}

    public function index(Request $request): View
    {
        $filterSpocId = (int) $request->query('spoc_id', 0);
        $filterDistrictId = (int) $request->query('district_id', 0);

        $data = $this->report->build(null, $filterSpocId, $filterDistrictId);

        return view('admin.pending-actions.index', array_merge($data, [
            'pageRoute' => 'admin.pending-actions.index',
            'scopeLabel' => null,
        ]));
    }
}
