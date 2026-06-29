<?php

namespace App\Services;

use App\Models\LineDepartmentMeeting;
use App\Models\ServiceCase;
use App\Models\User;
use App\Support\LineDepartmentMeetingAccess;
use App\Support\MisFieldActivityApproval;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Schema;

final class MisFieldActivityListService
{
    /**
     * @return list<string>
     */
    public static function listFilterValues(): array
    {
        return array_keys(MisFieldActivityApproval::modules());
    }

    public static function isListFilterValue(string $value): bool
    {
        return in_array($value, self::listFilterValues(), true);
    }

    public static function listLabel(string $moduleKey): string
    {
        $meta = MisFieldActivityApproval::module($moduleKey);

        return trim(((string) ($meta['serial'] ?? '')).' '.((string) ($meta['label'] ?? $moduleKey)));
    }

    /**
     * @return Collection<int, Model>
     */
    public function recordsForStaffList(
        int $districtId,
        int $staffUserId,
        string $scope,
        string $status,
        string $moduleFilter,
        bool $includeAllModules,
    ): Collection {
        if ($districtId <= 0) {
            return collect();
        }

        $items = collect();

        foreach ($this->moduleKeysForFilter($moduleFilter, $includeAllModules) as $moduleKey) {
            $query = $this->baseQuery($moduleKey);

            if ($scope === 'my') {
                $query->where('submitted_by_user_id', $staffUserId);
            } else {
                $this->applyDistrictStaffAllScope($query, $districtId);
            }

            $this->applyStatusFilter($query, $status);

            foreach ($query->orderByDesc('updated_at')->orderByDesc('id')->get() as $row) {
                $items->push($row);
            }
        }

        return $items;
    }

    /**
     * @return Collection<int, Model>
     */
    public function recordsForApproverList(
        User $approver,
        string $status,
        string $moduleFilter,
        bool $includeAllModules,
        string $searchQ = '',
        ?int $districtId = null,
        ?string $dateFrom = null,
        ?string $dateTo = null,
    ): Collection {
        if (! MisFieldActivityApproval::isDedicatedApprover($approver)) {
            return collect();
        }

        $items = collect();

        foreach ($this->moduleKeysForFilter($moduleFilter, $includeAllModules) as $moduleKey) {
            $meta = MisFieldActivityApproval::module($moduleKey);
            $titleCol = (string) ($meta['title_column'] ?? 'id');
            $query = $this->baseQuery($moduleKey);

            if ($districtId !== null && $districtId > 0 && Schema::hasColumn($query->getModel()->getTable(), 'district_id')) {
                $query->where('district_id', $districtId);
            }

            $this->applyStatusFilter($query, $status);
            $this->applySearchFilter($query, $searchQ, $titleCol);

            if ($dateFrom !== null && $dateFrom !== '') {
                $query->whereDate('updated_at', '>=', $dateFrom);
            }
            if ($dateTo !== null && $dateTo !== '') {
                $query->whereDate('updated_at', '<=', $dateTo);
            }

            foreach ($query->orderByDesc('updated_at')->orderByDesc('id')->get() as $row) {
                $items->push($row);
            }
        }

        return $items;
    }

    /**
     * @param  list<string>  $statuses
     */
    public function countForApprover(User $approver, array $statuses, ?int $districtId = null): int
    {
        if (! MisFieldActivityApproval::isDedicatedApprover($approver)) {
            return 0;
        }

        $total = 0;

        foreach (MisFieldActivityApproval::modules() as $moduleKey => $meta) {
            $table = (string) ($meta['table'] ?? '');
            if ($table === '' || ! MisFieldActivityApproval::supportsWorkflowOnTable($table)) {
                continue;
            }

            $class = MisFieldActivityApproval::modelClass($moduleKey);
            $query = $class::query()->whereIn('status', $statuses);

            if ($districtId !== null && $districtId > 0 && Schema::hasColumn($table, 'district_id')) {
                $query->where('district_id', $districtId);
            }

            $total += (int) $query->count();
        }

        return $total;
    }

    public function moduleKeyForRecord(Model $record): ?string
    {
        return MisFieldActivityApproval::moduleKeyForModel($record);
    }

    public function displayTitle(Model $record, string $moduleKey): string
    {
        $meta = MisFieldActivityApproval::module($moduleKey);
        $titleCol = (string) ($meta['title_column'] ?? 'id');
        $title = trim((string) ($record->{$titleCol} ?? ''));

        return $title !== '' ? $title : 'Entry #'.$record->getKey();
    }

    /**
     * @return list<string>
     */
    private function moduleKeysForFilter(string $moduleFilter, bool $includeAllModules): array
    {
        if ($moduleFilter !== '') {
            return isset(MisFieldActivityApproval::modules()[$moduleFilter]) ? [$moduleFilter] : [];
        }

        return $includeAllModules ? self::listFilterValues() : [];
    }

    /**
     * @return Builder<Model>
     */
    private function baseQuery(string $moduleKey): Builder
    {
        $meta = MisFieldActivityApproval::module($moduleKey);
        $table = (string) ($meta['table'] ?? '');
        if ($table === '' || ! MisFieldActivityApproval::supportsWorkflowOnTable($table)) {
            $class = MisFieldActivityApproval::modelClass($moduleKey);

            return $class::query()->whereRaw('1 = 0');
        }

        $class = MisFieldActivityApproval::modelClass($moduleKey);

        return $class::query()->with(['misFieldSpoc:id,name', 'submitter:id,name']);
    }

    /**
     * Hub-level line department meetings may omit district_id; include district staff submitters.
     *
     * @param  Builder<Model>  $query
     */
    private function applyDistrictStaffAllScope(Builder $query, int $districtId): void
    {
        if ($districtId <= 0) {
            return;
        }

        $table = $query->getModel()->getTable();
        if (! Schema::hasColumn($table, 'district_id')) {
            return;
        }

        if ($query->getModel() instanceof LineDepartmentMeeting) {
            $submitterIds = LineDepartmentMeetingAccess::districtStaffSubmitterIds($districtId);
            $query->where(function (Builder $q) use ($districtId, $submitterIds): void {
                $q->where('district_id', $districtId);
                if ($submitterIds->isNotEmpty()) {
                    $q->orWhereIn('submitted_by_user_id', $submitterIds);
                }
            });

            return;
        }

        $query->where('district_id', $districtId);
    }

    /**
     * @param  Builder<Model>  $query
     */
    private function applyStatusFilter(Builder $query, string $status): void
    {
        if ($status === '') {
            $query->whereIn('status', [
                ServiceCase::STATUS_PENDING_APPROVAL,
                ServiceCase::STATUS_SENT_BACK,
                ServiceCase::STATUS_APPROVED,
                ServiceCase::STATUS_REJECTED,
            ]);

            return;
        }

        if ($status === ServiceCase::STATUS_DRAFT) {
            $query->whereRaw('1 = 0');

            return;
        }

        $query->where('status', $status);
    }

    /**
     * @param  Builder<Model>  $query
     */
    private function applySearchFilter(Builder $query, string $searchQ, string $titleCol): void
    {
        if ($searchQ === '') {
            return;
        }

        $like = '%'.str_replace(['\\', '%', '_'], ['\\\\', '\\%', '\\_'], $searchQ).'%';
        $query->where(function (Builder $q) use ($like, $titleCol): void {
            $q->where('submitted_by_name', 'like', $like);
            if ($titleCol !== 'id') {
                $q->orWhere($titleCol, 'like', $like);
            }
            if (Schema::hasColumn($q->getModel()->getTable(), 'district_name')) {
                $q->orWhere('district_name', 'like', $like);
            }
        });
    }
}
