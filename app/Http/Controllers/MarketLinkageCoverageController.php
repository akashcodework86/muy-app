<?php

namespace App\Http\Controllers;

use App\Models\CfaSubmission;
use App\Services\MarketLinkages\MarketLinkageCoverageService;
use App\Support\MarketLinkageAccess;
use Illuminate\Http\Request;
use Illuminate\View\View;
use Symfony\Component\HttpFoundation\StreamedResponse;

class MarketLinkageCoverageController extends Controller
{
    public function __construct(
        private readonly MarketLinkageCoverageService $coverageService,
    ) {}

    public function index(Request $request): View
    {
        $user = $request->user();
        abort_unless(MarketLinkageAccess::canViewDashboard($user), 403);

        $filters = [
            'hub_id' => $request->integer('hub_id') ?: null,
            'district_id' => $request->integer('district_id') ?: null,
            'sector' => trim((string) $request->query('sector', '')),
            'block' => trim((string) $request->query('block', '')),
            'q' => trim((string) $request->query('q', '')),
            'coverage' => trim((string) $request->query('coverage', 'all')),
            'fiscal_year' => trim((string) $request->query('fiscal_year', 'all')),
        ];

        $data = $this->coverageService->paginatedForUser($user, $filters);

        return view('market-linkages.coverage', array_merge($data, [
            'routePrefix' => $this->routePrefix($user),
            'showUrlResolver' => fn (int $cfaId) => $this->showUrl($user, $cfaId),
        ]));
    }

    public function export(Request $request): StreamedResponse
    {
        $user = $request->user();
        abort_unless(MarketLinkageAccess::canViewDashboard($user), 403);

        $filters = [
            'hub_id' => $request->integer('hub_id') ?: null,
            'district_id' => $request->integer('district_id') ?: null,
            'sector' => trim((string) $request->query('sector', '')),
            'block' => trim((string) $request->query('block', '')),
            'q' => trim((string) $request->query('q', '')),
            'coverage' => trim((string) $request->query('coverage', 'all')),
            'fiscal_year' => trim((string) $request->query('fiscal_year', 'all')),
        ];

        $rows = $this->coverageService->exportRowsForUser($user, $filters);
        $filename = 'market-linkage-coverage-'.now()->format('Ymd_His').'.csv';

        return response()->streamDownload(function () use ($rows): void {
            $out = fopen('php://output', 'w');
            if ($out === false) {
                return;
            }

            fputcsv($out, [
                'Application no', 'Applicant', 'Phone', 'District', 'Hub', 'Block', 'Sector', 'Batch', 'FY',
                'Market linkage status', 'Linkage mode',
            ]);

            foreach ($rows as $row) {
                fputcsv($out, [
                    $row['application_no'] ?? '',
                    $row['applicant_name'] ?? '',
                    $row['phone'] ?? '',
                    $row['district_name'] ?? '',
                    $row['hub_name'] ?? '',
                    $row['block_name'] ?? '',
                    $row['sector'] ?? '',
                    $row['batch_name'] ?? '',
                    $row['fy_code'] ?? '',
                    $row['coverage_label'] ?? '',
                    $row['linkage_mode'] ?? '',
                ]);
            }

            fclose($out);
        }, $filename, ['Content-Type' => 'text/csv; charset=UTF-8']);
    }

    private function routePrefix(?\App\Models\User $user): string
    {
        return match ($user?->role) {
            'hub_admin' => 'hub.market-linkages.coverage',
            'district_staff' => 'staff.market-linkages.coverage',
            default => 'admin.market-linkages.coverage',
        };
    }

    private function showUrl(?\App\Models\User $user, int $cfaId): ?string
    {
        if ($cfaId < 1 || ! $user) {
            return null;
        }

        if (! CfaSubmission::query()->whereKey($cfaId)->exists()) {
            return null;
        }

        $submission = CfaSubmission::query()->find($cfaId);
        if ($submission === null) {
            return null;
        }

        return match ($user->role) {
            'state_admin' => route('admin.cfa.show', $submission),
            'hub_admin' => route('hub.batches.cfa.show', $submission),
            'district_staff' => route('staff.applications.show', $submission),
            default => null,
        };
    }
}
