<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Services\SpocApprovalAuditReportService;
use Illuminate\Http\Request;
use Illuminate\View\View;

class SpocApprovalAuditController extends Controller
{
    public function __construct(
        private readonly SpocApprovalAuditReportService $report,
    ) {}

    public function index(Request $request): View
    {
        $filterSpocId = (int) $request->query('spoc_id', 0);
        $filterFlag = (string) $request->query('flag', '');
        $filterDays = (int) $request->query('days', 30);

        if (! in_array($filterFlag, ['', 'without_doc', 'fast'], true)) {
            $filterFlag = '';
        }

        $data = $this->report->build($filterSpocId, $filterFlag, $filterDays);

        return view('admin.spoc-approval-audit.index', $data);
    }
}
