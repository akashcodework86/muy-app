<?php

namespace App\Http\Controllers;

use App\Models\District;
use App\Models\Hub;
use App\Models\IncubateeBill;
use App\Models\User;
use App\Services\IncubateeBillDashboardExcelExport;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\Storage;
use Illuminate\View\View;
use Symfony\Component\HttpFoundation\StreamedResponse;

class IncubateeBillDashboardController extends Controller
{
    public function dashboard(Request $request): View
    {
        $user = $this->viewerOrAbort($request);
        $filters = $this->filtersFromRequest($request);
        $prefix = $this->routePrefix($user);

        if (! Schema::hasTable('incubatee_bills')) {
            return view('bills.dashboard', $this->emptyViewData($user, $prefix, $filters, true));
        }

        $query = $this->filteredQuery($user, $filters);
        $totalsQuery = clone $query;
        $totals = [
            'bills' => (int) $totalsQuery->count(),
            'amount' => (float) (clone $query)->sum('amount'),
            'incubatees' => (int) (clone $query)->distinct()->count('cfa_submission_id'),
            'districts' => (int) (clone $query)->distinct()->count('district_id'),
        ];

        $rows = $query
            ->with([
                'cfaSubmission:id,applicant_name,application_no,phone',
                'district:id,name,hub_id',
                'district.hub:id,name',
                'batch:id,name',
                'creator:id,name',
            ])
            ->orderByDesc('bill_date')
            ->orderByDesc('id')
            ->paginate(30)
            ->withQueryString();

        return view('bills.dashboard', [
            'rows' => $rows,
            'totals' => $totals,
            'filters' => $filters,
            'hubs' => $this->hubOptions($user),
            'districts' => $this->districtOptions($user),
            'currentRole' => (string) $user->role,
            'dashboardRoute' => $prefix.'bills.dashboard',
            'exportRoute' => $prefix.'bills.export',
            'documentRoute' => $prefix.'bills.document',
            'migrationMissing' => false,
            'showHubFilter' => $user->role !== 'hub_admin',
        ]);
    }

    public function export(Request $request): StreamedResponse
    {
        $user = $this->viewerOrAbort($request);
        abort_unless(Schema::hasTable('incubatee_bills'), 404);

        $filters = $this->filtersFromRequest($request);
        $rows = $this->filteredQuery($user, $filters)
            ->with([
                'cfaSubmission:id,applicant_name,application_no,phone',
                'district:id,name,hub_id',
                'district.hub:id,name',
                'batch:id,name',
                'creator:id,name',
            ])
            ->orderByDesc('bill_date')
            ->orderByDesc('id')
            ->get();

        $export = new IncubateeBillDashboardExcelExport;

        return $export->download($rows, $filters);
    }

    public function document(Request $request, IncubateeBill $bill): StreamedResponse
    {
        $user = $this->viewerOrAbort($request);
        abort_unless($this->canViewBill($user, $bill), 403);
        abort_unless($bill->hasDocument(), 404);

        $disk = Storage::disk((string) ($bill->document_disk ?: 'local'));
        $path = (string) $bill->document_path;
        abort_unless($disk->exists($path), 404);

        return $disk->download($path, (string) ($bill->document_original_name ?: 'bill-document'));
    }

    /**
     * @param  array{q: string, hub_id: int, district_id: int, from: string, to: string}  $filters
     * @return Builder<IncubateeBill>
     */
    private function filteredQuery(User $user, array $filters): Builder
    {
        $query = IncubateeBill::query();
        $this->applyScope($query, $user);

        if ($filters['hub_id'] > 0 && $user->role !== 'hub_admin') {
            $query->whereHas('district', fn (Builder $q) => $q->where('hub_id', $filters['hub_id']));
        }

        if ($filters['district_id'] > 0) {
            $query->where('district_id', $filters['district_id']);
        }

        if ($filters['from'] !== '') {
            $query->whereDate('bill_date', '>=', $filters['from']);
        }
        if ($filters['to'] !== '') {
            $query->whereDate('bill_date', '<=', $filters['to']);
        }

        $q = $filters['q'];
        if ($q !== '') {
            $like = '%'.str_replace(['%', '_'], ['\\%', '\\_'], $q).'%';
            $query->where(function (Builder $inner) use ($like): void {
                $inner->where('bill_number', 'like', $like)
                    ->orWhereHas('cfaSubmission', function (Builder $cfa) use ($like): void {
                        $cfa->where('applicant_name', 'like', $like)
                            ->orWhere('application_no', 'like', $like)
                            ->orWhere('phone', 'like', $like);
                    })
                    ->orWhereHas('batch', fn (Builder $b) => $b->where('name', 'like', $like));
            });
        }

        return $query;
    }

    /**
     * @param  Builder<IncubateeBill>  $query
     */
    private function applyScope(Builder $query, User $user): void
    {
        if ($user->role === 'hub_admin') {
            $hubId = (int) ($user->hub_id ?: 0);
            if ($hubId <= 0) {
                $query->whereRaw('1 = 0');

                return;
            }
            $query->whereHas('district', fn (Builder $q) => $q->where('hub_id', $hubId));
        }
    }

    private function canViewBill(User $user, IncubateeBill $bill): bool
    {
        if (in_array($user->role, ['state_admin', 'state_staff'], true)) {
            return true;
        }

        if ($user->role === 'hub_admin') {
            $hubId = (int) ($user->hub_id ?: 0);
            $bill->loadMissing('district:id,hub_id');

            return $hubId > 0 && (int) ($bill->district?->hub_id ?: 0) === $hubId;
        }

        return false;
    }

    private function viewerOrAbort(Request $request): User
    {
        $user = $request->user();
        abort_unless($user && in_array($user->role, ['state_admin', 'state_staff', 'hub_admin'], true), 403);

        return $user;
    }

    /**
     * @return array{q: string, hub_id: int, district_id: int, from: string, to: string}
     */
    private function filtersFromRequest(Request $request): array
    {
        return [
            'q' => trim((string) $request->query('q', '')),
            'hub_id' => $request->integer('hub'),
            'district_id' => $request->integer('district'),
            'from' => trim((string) $request->query('from', '')),
            'to' => trim((string) $request->query('to', '')),
        ];
    }

    private function routePrefix(User $user): string
    {
        return match ($user->role) {
            'state_admin' => 'admin.',
            'hub_admin' => 'hub.',
            default => 'spoc.',
        };
    }

    /**
     * @return \Illuminate\Support\Collection<int, Hub>
     */
    private function hubOptions(User $user)
    {
        if ($user->role === 'hub_admin') {
            return Hub::query()
                ->whereKey((int) ($user->hub_id ?: 0))
                ->orderBy('name')
                ->get(['id', 'name']);
        }

        return Hub::query()->orderBy('sort_order')->orderBy('name')->get(['id', 'name']);
    }

    /**
     * @return \Illuminate\Support\Collection<int, District>
     */
    private function districtOptions(User $user)
    {
        $query = District::query()->orderBy('name');
        if ($user->role === 'hub_admin') {
            $query->where('hub_id', (int) ($user->hub_id ?: 0));
        }

        return $query->get(['id', 'name', 'hub_id']);
    }

    /**
     * @param  array{q: string, hub_id: int, district_id: int, from: string, to: string}  $filters
     * @return array<string, mixed>
     */
    private function emptyViewData(User $user, string $prefix, array $filters, bool $migrationMissing): array
    {
        return [
            'rows' => collect(),
            'totals' => ['bills' => 0, 'amount' => 0.0, 'incubatees' => 0, 'districts' => 0],
            'filters' => $filters,
            'hubs' => $this->hubOptions($user),
            'districts' => $this->districtOptions($user),
            'currentRole' => (string) $user->role,
            'dashboardRoute' => $prefix.'bills.dashboard',
            'exportRoute' => $prefix.'bills.export',
            'documentRoute' => $prefix.'bills.document',
            'migrationMissing' => $migrationMissing,
            'showHubFilter' => $user->role !== 'hub_admin',
        ];
    }
}
