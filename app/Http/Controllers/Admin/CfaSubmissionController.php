<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\AuditLog;
use App\Models\CfaSubmission;
use App\Models\District;
use App\Models\FiscalYear;
use App\Services\CfaSubmissionAuditSnapshot;
use App\Services\LegacyPhase2ApplicationDetailService;
use Illuminate\Http\Request;
use Illuminate\View\View;

class CfaSubmissionController extends Controller
{
    public function index(Request $request): View
    {
        [$fiscalYearId, $fiscalYears] = FiscalYear::resolveIdForUi(
            $request->query('fiscal_year_id') ? (int) $request->query('fiscal_year_id') : null
        );

        $districts = District::orderBy('name')->get(['id', 'name']);

        if ($fiscalYears->isEmpty()) {
            return view('admin.cfa.index', [
                'submissions'  => CfaSubmission::query()->whereRaw('1 = 0')->paginate(25),
                'fiscalYears'  => $fiscalYears,
                'fiscalYearId' => 0,
                'districts'    => $districts,
                'sectors'      => config('cfa.business_categories'),
                'filters'      => ['name' => '', 'district_id' => '', 'sector' => ''],
            ]);
        }

        $name       = trim((string) $request->query('name', ''));
        $districtId = $request->query('district_id');
        $sector     = trim((string) $request->query('sector', ''));

        $submissions = CfaSubmission::query()
            ->where('fiscal_year_id', $fiscalYearId)
            ->when($name !== '', fn ($q) => $q->where('applicant_name', 'like', "%{$name}%"))
            ->when($districtId, fn ($q) => $q->where('district_id', (int) $districtId))
            ->when($sector !== '', fn ($q) => $q->whereRaw(
                "JSON_UNQUOTE(JSON_EXTRACT(payload, '$.business_category')) = ?",
                [$sector]
            ))
            ->with(['district', 'referralUser', 'fiscalYear'])
            ->orderByDesc('created_at')
            ->paginate(25)
            ->withQueryString();

        return view('admin.cfa.index', [
            'submissions'  => $submissions,
            'fiscalYears'  => $fiscalYears,
            'fiscalYearId' => $fiscalYearId,
            'districts'    => $districts,
            'sectors'      => config('cfa.business_categories'),
            'filters'      => ['name' => $name, 'district_id' => $districtId, 'sector' => $sector],
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

        $legacyDetail = app(LegacyPhase2ApplicationDetailService::class)->tryBuild($cfa_submission);
        if (is_array($legacyDetail) && isset($legacyDetail['viewRow'])) {
            return view('admin.cfa.legacy-detail', [
                'submission' => $cfa_submission,
                'legacyDetail' => $legacyDetail,
                'cfaIndexUrl' => route('admin.cfa.index', array_filter([
                    'fiscal_year_id' => $cfa_submission->fiscal_year_id,
                ])),
            ]);
        }

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
