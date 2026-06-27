<?php

namespace App\Services\Admin;

use App\Models\MarketLinkageSubmission;
use App\Models\ServiceCase;
use App\Support\MarketLinkageUnifiedListingSupport;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Pagination\LengthAwarePaginator;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Schema;

class Phase3UnifiedMarketLinkageListBuilder
{
    /**
     * @param  array<string, mixed>  $filters
     * @return array{
     *   items: LengthAwarePaginator,
     *   summary: array{total: int, approved: int, pending_approval: int, sent_back: int, rejected: int, offline_rows: int, online_rows: int},
     *   unified: bool
     * }
     */
    public function build(array $filters, callable $buildServiceCaseQuery, callable $applyServiceCaseFilters): array
    {
        $serviceId = is_numeric($filters['service_id'] ?? '') ? (int) $filters['service_id'] : 0;
        if (! MarketLinkageUnifiedListingSupport::isMarketLinkServiceId($serviceId)) {
            $query = $buildServiceCaseQuery($filters);
            $applyServiceCaseFilters($query, $filters);

            return [
                'items' => $query->orderByDesc('service_cases.created_at')->paginate(20)->withQueryString(),
                'summary' => $this->emptySummary(),
                'unified' => false,
            ];
        }

        if (! Schema::hasTable('market_linkage_submissions') || ! MarketLinkageSubmission::supportsWorkflow()) {
            $query = $buildServiceCaseQuery($filters);
            $applyServiceCaseFilters($query, $filters);

            return [
                'items' => $query->orderByDesc('service_cases.created_at')->paginate(20)->withQueryString(),
                'summary' => $this->emptySummary(),
                'unified' => false,
            ];
        }

        $caseQuery = $buildServiceCaseQuery($filters);
        $applyServiceCaseFilters($caseQuery, $filters);
        $serviceCases = $caseQuery->orderByDesc('service_cases.created_at')->get();

        $mlQuery = MarketLinkageSubmission::query()
            ->with(['partners', 'spoc:id,name', 'submitter:id,name', 'approver:id,name', 'cfaSubmission.district:id,name']);
        $this->applyMarketLinkageFilters($mlQuery, $filters);
        $marketLinkages = $mlQuery->orderByDesc('updated_at')->get();

        $mlIncubateeKeys = MarketLinkageUnifiedListingSupport::incubateeKeysFromSubmissions($marketLinkages);
        $items = collect();
        $uniqueOnly = ! empty($filters['unique_incubatees']);

        foreach ($marketLinkages as $submission) {
            if ($uniqueOnly) {
                $modes = [];
                $partnerNames = [];
                foreach ($submission->partners as $partner) {
                    $modeLabel = MarketLinkageUnifiedListingSupport::linkageModeLabelFromPartnerMode((string) $partner->linkage_mode);
                    if ($modeLabel !== '' && ! in_array($modeLabel, $modes, true)) {
                        $modes[] = $modeLabel;
                    }
                    $name = trim((string) $partner->partner_name);
                    if ($name !== '') {
                        $partnerNames[] = $name;
                    }
                }

                $partnerCount = count($partnerNames);
                $partnerSummary = $partnerCount === 0
                    ? '—'
                    : ($partnerCount === 1
                        ? $partnerNames[0]
                        : $partnerCount.' partners');

                $items->push([
                    'type' => 'market_linkage_incubatee',
                    'service_case' => null,
                    'market_linkage' => $submission,
                    'partner' => null,
                    'linkage_mode' => $modes !== [] ? implode(', ', $modes) : '—',
                    'partner_name' => $partnerSummary,
                    'partner_count' => $partnerCount,
                    'updated_at' => $submission->updated_at ?? $submission->created_at,
                ]);

                continue;
            }

            foreach ($submission->partners as $partner) {
                $items->push([
                    'type' => 'market_linkage_partner',
                    'service_case' => null,
                    'market_linkage' => $submission,
                    'partner' => $partner,
                    'linkage_mode' => MarketLinkageUnifiedListingSupport::linkageModeLabelFromPartnerMode((string) $partner->linkage_mode),
                    'partner_name' => (string) $partner->partner_name,
                    'updated_at' => $submission->updated_at ?? $submission->created_at,
                ]);
            }
        }

        foreach ($serviceCases as $case) {
            $incubateeKey = MarketLinkageUnifiedListingSupport::incubateeKeyForServiceCase($case);
            if ($incubateeKey !== null && in_array($incubateeKey, $mlIncubateeKeys, true)) {
                continue;
            }

            $payload = is_array($case->payload) ? $case->payload : [];
            $modes = MarketLinkageUnifiedListingSupport::linkageModeLabelsFromServiceCasePayload($payload);
            $partnerName = trim((string) ($payload['p'] ?? ''));

            $items->push([
                'type' => 'service_case',
                'service_case' => $case,
                'market_linkage' => null,
                'partner' => null,
                'linkage_mode' => $modes !== [] ? implode(', ', $modes) : '—',
                'partner_name' => $partnerName !== '' ? $partnerName : '—',
                'updated_at' => $case->updated_at ?? $case->created_at,
            ]);
        }

        $sorted = $items->sortByDesc(fn (array $row) => $row['updated_at']?->timestamp ?? 0)->values();
        $page = max(1, (int) request()->query('page', 1));
        $perPage = 20;
        $total = $sorted->count();
        $slice = $sorted->slice(($page - 1) * $perPage, $perPage)->values();

        $paginator = new LengthAwarePaginator(
            $slice,
            $total,
            $perPage,
            $page,
            ['path' => request()->url(), 'query' => request()->query()],
        );

        $summary = $this->summarizeUnifiedRows($sorted, $filters);

        return [
            'items' => $paginator,
            'summary' => $summary,
            'unified' => true,
            'unique_incubatees' => $uniqueOnly,
        ];
    }

    /**
     * @param  array<string, mixed>  $filters
     */
    private function applyMarketLinkageFilters(Builder $query, array $filters): void
    {
        if ($filters['q'] !== '') {
            $like = '%'.$filters['q'].'%';
            $query->where(function ($q) use ($like): void {
                $q->where('incubatee_name', 'like', $like)
                    ->orWhere('application_no', 'like', $like)
                    ->orWhere('submitted_by_name', 'like', $like)
                    ->orWhereHas('partners', fn ($p) => $p->where('partner_name', 'like', $like))
                    ->orWhereHas('spoc', fn ($s) => $s->where('name', 'like', $like));
            });
        }

        if ($filters['district_id'] > 0) {
            $query->where('district_id', (int) $filters['district_id']);
        }

        if ($filters['spoc_id'] === 'unassigned') {
            $query->whereNull('spoc_user_id');
        } elseif (is_numeric($filters['spoc_id']) && (int) $filters['spoc_id'] > 0) {
            $query->where('spoc_user_id', (int) $filters['spoc_id']);
        }

        if ($filters['status'] !== '') {
            $query->where('status', $filters['status']);
        }

        if ($filters['date_from'] !== '') {
            $query->whereDate('created_at', '>=', $filters['date_from']);
        }

        if ($filters['date_to'] !== '') {
            $query->whereDate('created_at', '<=', $filters['date_to']);
        }
    }

    /**
     * @param  Collection<int, array<string, mixed>>  $rows
     * @param  array<string, mixed>  $filters
     * @return array{total: int, approved: int, pending_approval: int, sent_back: int, rejected: int, offline_rows: int, online_rows: int, deliverable_incubatees: int, offline_incubatees: int, online_incubatees: int}
     */
    private function summarizeUnifiedRows(Collection $rows, array $filters): array
    {
        $statusCounts = [
            ServiceCase::STATUS_APPROVED => 0,
            ServiceCase::STATUS_PENDING_APPROVAL => 0,
            ServiceCase::STATUS_SENT_BACK => 0,
            ServiceCase::STATUS_REJECTED => 0,
        ];
        $offlineRows = 0;
        $onlineRows = 0;

        foreach ($rows as $row) {
            $mode = (string) ($row['linkage_mode'] ?? '');
            if (str_contains($mode, 'Offline')) {
                $offlineRows++;
            }
            if (str_contains($mode, 'Online')) {
                $onlineRows++;
            }

            $status = match ($row['type']) {
                'market_linkage_partner' => (string) ($row['market_linkage']?->status ?? ''),
                default => (string) ($row['service_case']?->status ?? ''),
            };

            if (isset($statusCounts[$status])) {
                $statusCounts[$status]++;
            }
        }

        $districtIds = null;
        if ((int) ($filters['district_id'] ?? 0) > 0) {
            $districtIds = [(int) $filters['district_id']];
        }

        $modeCounts = MarketLinkageUnifiedListingSupport::approvedIncubateeModeCounts(
            $districtIds,
            approvedOnly: ($filters['status'] ?? '') === '' || ($filters['status'] ?? '') === ServiceCase::STATUS_APPROVED,
        );

        if (($filters['status'] ?? '') !== '' && ($filters['status'] ?? '') !== ServiceCase::STATUS_APPROVED) {
            $modeCounts = ['offline_incubatees' => 0, 'online_incubatees' => 0, 'total_incubatees' => 0];
        }

        return [
            'total' => $rows->count(),
            'approved' => $statusCounts[ServiceCase::STATUS_APPROVED],
            'pending_approval' => $statusCounts[ServiceCase::STATUS_PENDING_APPROVAL],
            'sent_back' => $statusCounts[ServiceCase::STATUS_SENT_BACK],
            'rejected' => $statusCounts[ServiceCase::STATUS_REJECTED],
            'offline_rows' => $offlineRows,
            'online_rows' => $onlineRows,
            'deliverable_incubatees' => $modeCounts['total_incubatees'],
            'offline_incubatees' => $modeCounts['offline_incubatees'],
            'online_incubatees' => $modeCounts['online_incubatees'],
        ];
    }

    /**
     * @return array{total: int, approved: int, pending_approval: int, sent_back: int, rejected: int, offline_rows: int, online_rows: int, deliverable_incubatees: int, offline_incubatees: int, online_incubatees: int}
     */
    private function emptySummary(): array
    {
        return [
            'total' => 0,
            'approved' => 0,
            'pending_approval' => 0,
            'sent_back' => 0,
            'rejected' => 0,
            'offline_rows' => 0,
            'online_rows' => 0,
            'deliverable_incubatees' => 0,
            'offline_incubatees' => 0,
            'online_incubatees' => 0,
        ];
    }
}
