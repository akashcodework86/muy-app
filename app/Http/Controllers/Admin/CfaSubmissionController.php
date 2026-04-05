<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\AuditLog;
use App\Models\CfaSubmission;
use App\Services\CfaSubmissionAuditSnapshot;
use Illuminate\View\View;

class CfaSubmissionController extends Controller
{
    public function index(): View
    {
        $submissions = CfaSubmission::query()
            ->with(['district', 'referralUser', 'fiscalYear'])
            ->orderByDesc('created_at')
            ->paginate(25);

        return view('admin.cfa.index', [
            'submissions' => $submissions,
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
            'cfaIndexUrl' => route('admin.cfa.index'),
            'cfaEditUrl' => null,
            'cfaEditLogs' => $cfaEditLogs,
        ]);
    }
}
