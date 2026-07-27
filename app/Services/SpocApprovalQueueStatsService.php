<?php

namespace App\Services;

use App\Models\AccelerationServiceSession;
use App\Models\DistrictServiceSpoc;
use App\Models\MarketLinkageSubmission;
use App\Models\ServiceCase;
use App\Models\User;
use App\Support\AccelerationServicesApproval;
use App\Support\MisFieldActivityApproval;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Facades\Schema;

/**
 * SPOC dashboard KPIs must match the default (unfiltered) /spoc/service-cases queue tabs.
 */
class SpocApprovalQueueStatsService
{
    public function __construct(
        private LegacyApplicationServiceCaseSupport $legacyApplications,
        private MisFieldActivityListService $fieldMisList,
    ) {}

    /**
     * @return array{pending: int, overdue: int, approved: int, district_ids: list<int>}
     */
    public function forSpoc(User $spoc): array
    {
        $districtIds = $this->districtIdsFor($spoc);

        return [
            'pending' => $this->pendingApprovalCount($spoc, $districtIds),
            'overdue' => $this->overduePendingCount($spoc, $districtIds),
            'approved' => $this->approvedCount($spoc, $districtIds),
            'district_ids' => $districtIds,
        ];
    }

    /**
     * @return list<int>
     */
    public function districtIdsFor(User $spoc): array
    {
        return DistrictServiceSpoc::query()
            ->where('state_staff_user_id', (int) $spoc->id)
            ->pluck('district_id')
            ->map(fn ($id) => (int) $id)
            ->filter(fn (int $id) => $id > 0)
            ->values()
            ->all();
    }

    /**
     * @param  list<int>  $districtIds
     */
    public function pendingApprovalCount(User $spoc, ?array $districtIds = null): int
    {
        $districtIds ??= $this->districtIdsFor($spoc);
        $total = (int) $this->serviceCaseScope($districtIds)
            ->where('status', ServiceCase::STATUS_PENDING_APPROVAL)
            ->count();

        $total += $this->marketLinkageCount($districtIds, [ServiceCase::STATUS_PENDING_APPROVAL]);

        if (MisFieldActivityApproval::isDedicatedApprover($spoc)) {
            $total += $this->fieldMisList->countForApprover(
                $spoc,
                [ServiceCase::STATUS_PENDING_APPROVAL],
                null,
            );
        }

        $total += $this->accelerationPendingCount($spoc);

        return $total;
    }

    /**
     * @param  list<int>  $districtIds
     */
    public function overduePendingCount(User $spoc, ?array $districtIds = null): int
    {
        $districtIds ??= $this->districtIdsFor($spoc);

        $total = (int) $this->serviceCaseScope($districtIds)
            ->where('status', ServiceCase::STATUS_PENDING_APPROVAL)
            ->whereNotNull('sla_deadline_at')
            ->where('sla_deadline_at', '<', now())
            ->count();

        if (Schema::hasTable('market_linkage_submissions') && MarketLinkageSubmission::supportsWorkflow() && $districtIds !== []) {
            $total += (int) MarketLinkageSubmission::query()
                ->whereIn('district_id', $districtIds)
                ->where('status', ServiceCase::STATUS_PENDING_APPROVAL)
                ->whereNotNull('sla_deadline_at')
                ->where('sla_deadline_at', '<', now())
                ->count();
        }

        return $total;
    }

    /**
     * Same total as the unfiltered "Approved" tab on /spoc/service-cases.
     *
     * @param  list<int>  $districtIds
     */
    public function approvedCount(User $spoc, ?array $districtIds = null): int
    {
        $districtIds ??= $this->districtIdsFor($spoc);

        $total = (int) $this->serviceCaseScope($districtIds)
            ->where('status', ServiceCase::STATUS_APPROVED)
            ->count();

        $total += $this->marketLinkageCount($districtIds, [ServiceCase::STATUS_APPROVED]);

        if (MisFieldActivityApproval::isDedicatedApprover($spoc)) {
            $total += $this->fieldMisList->countForApprover(
                $spoc,
                [ServiceCase::STATUS_APPROVED],
                null,
            );
        }

        $total += $this->accelerationApprovedCount($spoc);

        return $total;
    }

    /**
     * @param  list<int>  $districtIds
     * @return Builder<ServiceCase>
     */
    private function serviceCaseScope(array $districtIds): Builder
    {
        $legacyAppIds = $this->legacyApplicationIdsForDistricts($districtIds);

        return ServiceCase::query()
            ->where(function ($outer) use ($districtIds, $legacyAppIds): void {
                if ($districtIds === []) {
                    $outer->whereRaw('1 = 0');

                    return;
                }
                $outer->whereHas('cfaSubmission', fn ($qq) => $qq->whereIn('district_id', $districtIds));
                if (ServiceCase::supportsLegacyApplicationLink() && $legacyAppIds !== []) {
                    $outer->orWhere(function ($qq) use ($legacyAppIds): void {
                        $qq->whereNotNull('legacy_application_id')
                            ->whereNull('cfa_submission_id')
                            ->whereIn('legacy_application_id', $legacyAppIds);
                    });
                }
            });
    }

    /**
     * @param  list<int>  $districtIds
     * @param  list<string>  $statuses
     */
    private function marketLinkageCount(array $districtIds, array $statuses): int
    {
        if ($districtIds === [] || ! Schema::hasTable('market_linkage_submissions') || ! MarketLinkageSubmission::supportsWorkflow()) {
            return 0;
        }

        return (int) MarketLinkageSubmission::query()
            ->whereIn('district_id', $districtIds)
            ->whereIn('status', $statuses)
            ->count();
    }

    private function accelerationPendingCount(User $spoc): int
    {
        if (! AccelerationServicesApproval::isApprover($spoc) || ! AccelerationServicesApproval::workflowReady()) {
            return 0;
        }

        $statuses = AccelerationServicesApproval::pendingStatusesFor($spoc);
        if ($statuses === []) {
            return 0;
        }

        return (int) $this->accelerationQueueBase($spoc)
            ->whereIn('status', $statuses)
            ->count();
    }

    private function accelerationApprovedCount(User $spoc): int
    {
        if (! AccelerationServicesApproval::isApprover($spoc) || ! AccelerationServicesApproval::workflowReady()) {
            return 0;
        }

        return (int) $this->accelerationQueueBase($spoc)
            ->where('status', AccelerationServicesApproval::STATUS_APPROVED)
            ->count();
    }

    /**
     * @return Builder<AccelerationServiceSession>
     */
    private function accelerationQueueBase(User $spoc): Builder
    {
        return AccelerationServiceSession::query()
            ->when(
                Schema::hasColumn('acceleration_service_sessions', 'is_draft'),
                fn ($q) => $q->where('is_draft', false),
            )
            ->where('submitted_by_user_id', '!=', (int) $spoc->id);
    }

    /**
     * @param  list<int>  $districtIds
     * @return list<int>
     */
    private function legacyApplicationIdsForDistricts(array $districtIds): array
    {
        $out = [];
        foreach ($districtIds as $did) {
            foreach ($this->legacyApplications->legacyApplicationIdsInLaravelDistrict((int) $did) as $lid) {
                $out[] = $lid;
            }
        }

        return array_values(array_unique($out));
    }
}
