<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\AuditLog;
use App\Models\CfaSubmission;
use App\Models\FiscalYear;
use App\Services\CfaSubmissionAuditSnapshot;
use Illuminate\Http\Request;
use Illuminate\View\View;

class CfaSubmissionController extends Controller
{
    public function index(Request $request): View
    {
        [$fiscalYearId, $fiscalYears] = FiscalYear::resolveIdForUi(
            $request->query('fiscal_year_id') ? (int) $request->query('fiscal_year_id') : null
        );

        if ($fiscalYears->isEmpty()) {
            return view('admin.cfa.index', [
                'submissions' => CfaSubmission::query()->whereRaw('1 = 0')->paginate(25),
                'fiscalYears' => $fiscalYears,
                'fiscalYearId' => 0,
            ]);
        }

        $submissions = CfaSubmission::query()
            ->where('fiscal_year_id', $fiscalYearId)
            ->with(['district', 'referralUser', 'fiscalYear'])
            ->orderByDesc('created_at')
            ->paginate(25)
            ->withQueryString();

        return view('admin.cfa.index', [
            'submissions' => $submissions,
            'fiscalYears' => $fiscalYears,
            'fiscalYearId' => $fiscalYearId,
        ]);
    }

    public function show(CfaSubmission $cfa_submission): View
    {
        $cfa_submission->load(['district', 'referralUser', 'fiscalYear']);

        $cfaEditLogs = AuditLog::query()
            ->where('subject_type', CfaSubmission::class)
            ->where('subject_id', $cfa_submission->id)
            ->where('action', CfaSubmissionAuditSnapshot::ACTION_UPDATED)
            ->with('user')
            ->orderByDesc('created_at')
            ->get();

        return view('admin.cfa.show', [
            'submission' => $cfa_submission,
            'cfaIndexUrl' => route('admin.cfa.index', array_filter([
                'fiscal_year_id' => $cfa_submission->fiscal_year_id,
            ])),
            'cfaEditUrl' => null,
            'cfaEditLogs' => $cfaEditLogs,
        ]);
    }
}
