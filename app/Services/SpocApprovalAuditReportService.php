<?php

namespace App\Services;

use App\Models\ServiceCaseEvent;
use App\Models\User;
use Illuminate\Support\Collection;

class SpocApprovalAuditReportService
{
    /**
     * @return array{
     *     rows: Collection<int, array<string, mixed>>,
     *     spocSummaries: Collection<int, array<string, mixed>>,
     *     totals: array<string, int|float>,
     *     spocOptions: Collection<int, User>,
     *     filterSpocId: int,
     *     filterFlag: string,
     *     filterDays: int,
     * }
     */
    public function build(int $filterSpocId = 0, string $filterFlag = '', int $filterDays = 30): array
    {
        $filterDays = max(1, min($filterDays, 365));
        $since = now()->subDays($filterDays);

        $query = ServiceCaseEvent::query()
            ->where('action', 'spoc_approved')
            ->where('created_at', '>=', $since)
            ->with([
                'user:id,name,email',
                'serviceCase:id,service_id,cfa_submission_id,reference_number,approved_at',
                'serviceCase.service:id,name',
                'serviceCase.cfaSubmission:id,applicant_name,application_no,district_id',
                'serviceCase.cfaSubmission.district:id,name',
            ])
            ->orderByDesc('created_at');

        if ($filterSpocId > 0) {
            $query->where('user_id', $filterSpocId);
        }

        $events = $query->limit(500)->get();

        if ($filterFlag === 'without_doc') {
            $events = $events->filter(function (ServiceCaseEvent $event): bool {
                $meta = is_array($event->meta) ? $event->meta : [];

                return (bool) ($meta['approved_without_document_view'] ?? false);
            })->values();
        } elseif ($filterFlag === 'fast') {
            $events = $events->filter(function (ServiceCaseEvent $event): bool {
                $meta = is_array($event->meta) ? $event->meta : [];
                $seconds = (int) ($meta['case_page_seconds'] ?? 0);

                return $seconds > 0 && $seconds < 15;
            })->values();
        }

        $rows = $events->map(function (ServiceCaseEvent $event): array {
            $meta = is_array($event->meta) ? $event->meta : [];
            $case = $event->serviceCase;
            $cfa = $case?->cfaSubmission;

            return [
                'event_id' => (int) $event->id,
                'approved_at' => $event->created_at,
                'spoc_name' => (string) ($event->user?->name ?? '—'),
                'spoc_email' => (string) ($event->user?->email ?? ''),
                'spoc_user_id' => (int) ($event->user_id ?? 0),
                'incubatee' => (string) ($cfa?->applicant_name ?? '—'),
                'application_no' => (string) ($cfa?->application_no ?? ''),
                'district' => (string) ($cfa?->district?->name ?? '—'),
                'service' => (string) ($case?->service?->name ?? '—'),
                'reference' => (string) ($case?->reference_number ?? ''),
                'service_case_id' => (int) ($case?->id ?? 0),
                'had_attachment' => (bool) ($meta['had_attachment'] ?? false),
                'document_viewed' => (bool) ($meta['document_viewed'] ?? false),
                'document_view_source' => (string) ($meta['document_view_source'] ?? ''),
                'full_page_visited' => (bool) ($meta['full_page_visited'] ?? false),
                'quick_review_opened' => (bool) ($meta['quick_review_opened'] ?? false),
                'case_page_seconds' => (int) ($meta['case_page_seconds'] ?? 0),
                'approved_without_document_view' => (bool) ($meta['approved_without_document_view'] ?? false),
                'approval_channel' => (string) ($meta['approval_channel'] ?? ''),
            ];
        });

        $allForSummary = ServiceCaseEvent::query()
            ->where('action', 'spoc_approved')
            ->where('created_at', '>=', $since)
            ->with('user:id,name,email')
            ->get();

        $spocSummaries = $allForSummary
            ->groupBy('user_id')
            ->map(function (Collection $group, $userId): array {
                $first = $group->first();
                $withoutDoc = $group->filter(function (ServiceCaseEvent $event): bool {
                    $meta = is_array($event->meta) ? $event->meta : [];

                    return (bool) ($meta['approved_without_document_view'] ?? false);
                })->count();
                $seconds = $group->map(function (ServiceCaseEvent $event): int {
                    $meta = is_array($event->meta) ? $event->meta : [];

                    return (int) ($meta['case_page_seconds'] ?? 0);
                });
                $withSeconds = $seconds->filter(fn (int $s): bool => $s > 0);

                return [
                    'spoc_user_id' => (int) $userId,
                    'spoc_name' => (string) ($first?->user?->name ?? '—'),
                    'spoc_email' => (string) ($first?->user?->email ?? ''),
                    'total_approved' => $group->count(),
                    'without_document_view' => $withoutDoc,
                    'without_document_rate' => $group->count() > 0
                        ? round(($withoutDoc / $group->count()) * 100, 1)
                        : 0.0,
                    'avg_review_seconds' => $withSeconds->isNotEmpty()
                        ? (int) round($withSeconds->avg())
                        : 0,
                ];
            })
            ->sortByDesc('without_document_view')
            ->values();

        $totals = [
            'total_approved' => $allForSummary->count(),
            'without_document_view' => $allForSummary->filter(function (ServiceCaseEvent $event): bool {
                $meta = is_array($event->meta) ? $event->meta : [];

                return (bool) ($meta['approved_without_document_view'] ?? false);
            })->count(),
        ];

        $spocOptions = User::query()
            ->whereIn('id', $allForSummary->pluck('user_id')->filter()->unique()->all())
            ->orderBy('name')
            ->get(['id', 'name', 'email']);

        return [
            'rows' => $rows,
            'spocSummaries' => $spocSummaries,
            'totals' => $totals,
            'spocOptions' => $spocOptions,
            'filterSpocId' => $filterSpocId,
            'filterFlag' => $filterFlag,
            'filterDays' => $filterDays,
        ];
    }
}
