<?php

namespace App\Http\Controllers;

use App\Models\CfaSubmission;
use App\Services\Onboarding\OnboardingPriorityListService;
use App\Support\OnboardingPriorityAccess;
use Illuminate\Http\Request;
use Illuminate\View\View;
use Symfony\Component\HttpFoundation\StreamedResponse;

class OnboardingPriorityController extends Controller
{
    public function __construct(
        private readonly OnboardingPriorityListService $listService,
    ) {}

    public function index(Request $request): View
    {
        $user = $request->user();
        abort_unless(OnboardingPriorityAccess::canView($user), 403);

        $stage = trim((string) $request->query('stage', ''));
        $districtId = $request->integer('district_id') ?: null;
        $search = trim((string) $request->query('q', ''));

        $data = $this->listService->paginatedForUser($user, $stage, $districtId, $search);
        $routePrefix = OnboardingPriorityAccess::routePrefix($user);

        return view('onboarding-priority.index', array_merge($data, [
            'routePrefix' => $routePrefix,
            'searchQuery' => $search,
            'showUrlResolver' => fn (int $cfaId) => $this->showUrl($user, $cfaId),
        ]));
    }

    public function export(Request $request): StreamedResponse
    {
        $user = $request->user();
        abort_unless(OnboardingPriorityAccess::canView($user), 403);

        $stage = trim((string) $request->query('stage', ''));
        $districtId = $request->integer('district_id') ?: null;
        $search = trim((string) $request->query('q', ''));
        $rows = $this->listService->exportRowsForUser($user, $stage, $districtId, $search);

        $filename = 'onboarding-priority-'.now()->format('Ymd_His').'.csv';

        return response()->streamDownload(function () use ($rows): void {
            $out = fopen('php://output', 'w');
            if ($out === false) {
                return;
            }

            fputcsv($out, [
                'Rank', 'Application no', 'Applicant', 'District', 'Block', 'Stage',
                'Priority score', 'Scalability', 'Economic', 'Social', 'Vision', 'Innovation', 'Environmental',
                'Top drivers', 'Submitted',
            ]);

            foreach ($rows as $row) {
                $dims = $row['dimensions'] ?? [];
                fputcsv($out, [
                    $row['rank'] ?? '',
                    $row['application_no'] ?? '',
                    $row['applicant_name'] ?? '',
                    $row['district_name'] ?? '',
                    $row['block_name'] ?? '',
                    $row['stage'] ?? '',
                    $row['score'] ?? '',
                    $dims['scalability']['points'] ?? '',
                    $dims['economic']['points'] ?? '',
                    $dims['social']['points'] ?? '',
                    $dims['vision']['points'] ?? '',
                    $dims['innovation']['points'] ?? '',
                    $dims['environmental']['points'] ?? '',
                    implode(' · ', $row['highlights'] ?? []),
                    $row['submitted_at'] ?? '',
                ]);
            }

            fclose($out);
        }, $filename, ['Content-Type' => 'text/csv; charset=UTF-8']);
    }

    private function showUrl(?\App\Models\User $user, int $cfaId): ?string
    {
        if (! $user || ! CfaSubmission::query()->whereKey($cfaId)->exists()) {
            return null;
        }

        $submission = CfaSubmission::query()->find($cfaId);
        if ($submission === null) {
            return null;
        }

        return match ($user->role) {
            'state_admin' => route('admin.cfa.show', $submission),
            'district_staff' => route('staff.applications.show', $submission),
            default => null,
        };
    }
}
