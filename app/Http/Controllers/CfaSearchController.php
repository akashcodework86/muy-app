<?php

namespace App\Http\Controllers;

use App\Models\AuditLog;
use App\Models\CfaSubmission;
use App\Services\Cfa\CfaUniversalSearchService;
use App\Services\CfaSubmissionAuditSnapshot;
use App\Services\LegacyPhase1ApplicationDetailService;
use App\Services\LegacyPhase2ApplicationDetailService;
use Illuminate\Http\Request;
use Illuminate\View\View;

class CfaSearchController extends Controller
{
    /** @var list<string> */
    public const ALLOWED_ROLES = ['state_admin', 'hub_admin', 'district_staff', 'state_staff'];

    public function index(Request $request, CfaUniversalSearchService $search): View
    {
        $this->assertCanSearch($request);

        $q = trim((string) $request->query('q', ''));
        $tooShort = $q !== '' && mb_strlen($q) < CfaUniversalSearchService::MIN_QUERY_LENGTH;
        $results = (! $tooShort && $q !== '') ? $search->search($q) : [];

        $counts = [
            'total' => count($results),
            'current' => 0,
            'phase1' => 0,
            'phase2' => 0,
        ];
        foreach ($results as $row) {
            $source = (string) ($row['source'] ?? '');
            if (isset($counts[$source])) {
                $counts[$source]++;
            }
        }

        return view('cfa-search.index', [
            'q' => $q,
            'tooShort' => $tooShort,
            'minQueryLength' => CfaUniversalSearchService::MIN_QUERY_LENGTH,
            'results' => $results,
            'counts' => $counts,
        ]);
    }

    public function showCurrent(Request $request, CfaSubmission $cfa_submission): View
    {
        $this->assertCanSearch($request);

        $cfa_submission->load(['district', 'referralUser', 'fiscalYear']);
        $backUrl = $this->backToSearchUrl($request);

        $cfaEditLogs = AuditLog::query()
            ->where('subject_type', CfaSubmission::class)
            ->where('subject_id', $cfa_submission->id)
            ->where('action', CfaSubmissionAuditSnapshot::ACTION_UPDATED)
            ->with('user')
            ->orderByDesc('created_at')
            ->get();

        $phase1Detail = app(LegacyPhase1ApplicationDetailService::class)->tryBuild($cfa_submission);
        if (is_array($phase1Detail) && isset($phase1Detail['viewRow'])) {
            return view('admin.cfa.phase1-legacy-detail', [
                'submission' => $cfa_submission,
                'legacyDetail' => $phase1Detail,
                'cfaIndexUrl' => $backUrl,
            ]);
        }

        $legacyDetail = app(LegacyPhase2ApplicationDetailService::class)->tryBuild($cfa_submission);
        if (is_array($legacyDetail) && isset($legacyDetail['viewRow'])) {
            return view('admin.cfa.legacy-detail', [
                'submission' => $cfa_submission,
                'legacyDetail' => $legacyDetail,
                'cfaIndexUrl' => $backUrl,
            ]);
        }

        return view('admin.cfa.show', [
            'submission' => $cfa_submission,
            'cfaIndexUrl' => $backUrl,
            'cfaEditUrl' => null,
            'cfaEditLogs' => $cfaEditLogs,
        ]);
    }

    public function showPhase1(Request $request, int $legacyId): View
    {
        $this->assertCanSearch($request);

        $legacyDetail = app(LegacyPhase1ApplicationDetailService::class)->tryBuildFromLegacyId($legacyId);
        abort_if($legacyDetail === null, 404);

        return view('cfa-search.phase1-detail', [
            'legacyDetail' => $legacyDetail,
            'cfaIndexUrl' => $this->backToSearchUrl($request),
        ]);
    }

    public function showPhase2(Request $request, int $legacyId): View
    {
        $this->assertCanSearch($request);

        $legacyDetail = app(LegacyPhase2ApplicationDetailService::class)->tryBuildFromLegacyId($legacyId);
        abort_if($legacyDetail === null, 404);

        return view('cfa-search.phase2-detail', [
            'legacyDetail' => $legacyDetail,
            'cfaIndexUrl' => $this->backToSearchUrl($request),
        ]);
    }

    private function assertCanSearch(Request $request): void
    {
        $role = (string) ($request->user()?->role ?? '');
        abort_unless(in_array($role, self::ALLOWED_ROLES, true), 403);
    }

    private function backToSearchUrl(Request $request): string
    {
        $q = trim((string) $request->query('q', ''));

        return route('cfa.search', $q !== '' ? ['q' => $q] : []);
    }
}
