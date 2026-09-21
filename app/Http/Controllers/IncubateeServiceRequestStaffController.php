<?php

namespace App\Http\Controllers;

use App\Models\District;
use App\Models\IncubateeServiceRequest;
use App\Models\Service;
use App\Models\User;
use App\Support\MentorshipRequestAccess;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;
use Illuminate\View\View;

class IncubateeServiceRequestStaffController extends Controller
{
    public function dashboard(Request $request): View
    {
        $user = $request->user();
        abort_unless(MentorshipRequestAccess::canViewDashboard($user), 403);

        $filters = $this->filtersFromRequest($request);
        $query = $this->filteredQuery($user, $filters);
        $totals = $this->totalsForQuery($query);
        $districtOptions = $this->districtOptions($user);

        $rows = $query
            ->with([
                'cfaSubmission.district.hub',
                'requestedBy',
                'service.category.parent',
            ])
            ->orderByDesc('id')
            ->paginate(25)
            ->withQueryString();

        return view('service-requests.dashboard', [
            'rows' => $rows,
            'filters' => $filters,
            'totals' => $totals,
            'services' => Service::query()->where('is_active', true)->orderBy('name')->get(['id', 'name', 'code']),
            'districtOptions' => $districtOptions,
            'canHandle' => MentorshipRequestAccess::canHandle($user),
            'currentRole' => $user->role,
            'prefix' => MentorshipRequestAccess::routePrefixForUser($user),
            'showHub' => count($districtOptions) > 1,
        ]);
    }

    public function show(Request $request, IncubateeServiceRequest $serviceRequest): View
    {
        $user = $request->user();
        abort_unless(MentorshipRequestAccess::canViewDashboard($user), 403);
        abort_unless($this->canViewRecord($user, $serviceRequest), 403);

        $serviceRequest->load([
            'cfaSubmission.district.hub',
            'requestedBy',
            'service.category.parent',
            'handledBy',
        ]);

        return view('service-requests.show', [
            'row' => $serviceRequest,
            'canHandle' => $this->canHandleRecord($user, $serviceRequest),
            'prefix' => MentorshipRequestAccess::routePrefixForUser($user),
            'currentRole' => $user->role,
        ]);
    }

    public function updateStatus(Request $request, IncubateeServiceRequest $serviceRequest): RedirectResponse
    {
        $user = $request->user();
        abort_unless($this->canHandleRecord($user, $serviceRequest), 403);

        $validated = $request->validate([
            'status' => ['required', 'string', Rule::in([
                IncubateeServiceRequest::STATUS_IN_PROGRESS,
                IncubateeServiceRequest::STATUS_DONE,
            ])],
            'staff_note' => ['nullable', 'string', 'max:2000'],
        ]);

        if ($serviceRequest->isCancelled() || $serviceRequest->isDone()) {
            return back()->withErrors(['status' => 'This request can no longer be updated.']);
        }

        $serviceRequest->status = $validated['status'];
        $serviceRequest->staff_note = $validated['staff_note'] ?? $serviceRequest->staff_note;
        $serviceRequest->handled_by_user_id = (int) $user->id;
        $serviceRequest->handled_at = now();
        $serviceRequest->save();

        $label = $validated['status'] === IncubateeServiceRequest::STATUS_DONE ? 'marked Done' : 'marked In progress';

        return back()->with('status', 'Request '.$label.'.');
    }

    private function canViewRecord(User $user, IncubateeServiceRequest $request): bool
    {
        $districtIds = MentorshipRequestAccess::visibleDistrictIds($user);
        $request->loadMissing('cfaSubmission');
        $districtId = (int) ($request->cfaSubmission?->district_id ?? 0);
        if ($districtIds === null) {
            return true;
        }

        return $districtId > 0 && in_array($districtId, $districtIds, true);
    }

    private function canHandleRecord(User $user, IncubateeServiceRequest $request): bool
    {
        if (! MentorshipRequestAccess::canHandle($user)) {
            return false;
        }

        $request->loadMissing('cfaSubmission');
        $districtId = (int) ($request->cfaSubmission?->district_id ?? 0);

        return $districtId > 0 && $districtId === (int) $user->district_id;
    }

    /**
     * @return array<string, mixed>
     */
    private function filtersFromRequest(Request $request): array
    {
        return [
            'q' => trim((string) $request->query('q', '')),
            'status' => trim((string) $request->query('status', '')),
            'service_id' => (int) $request->query('service_id', 0),
            'district_id' => (int) $request->query('district_id', 0),
            'from' => trim((string) $request->query('from', '')),
            'to' => trim((string) $request->query('to', '')),
        ];
    }

    /**
     * @param  array<string, mixed>  $filters
     */
    private function filteredQuery(User $user, array $filters): Builder
    {
        $query = IncubateeServiceRequest::query();
        $districtIds = MentorshipRequestAccess::visibleDistrictIds($user);
        if ($districtIds === []) {
            $query->whereRaw('1 = 0');
        } elseif ($districtIds !== null) {
            $query->whereHas('cfaSubmission', fn ($q) => $q->whereIn('district_id', $districtIds));
        }

        $status = (string) ($filters['status'] ?? '');
        if (in_array($status, [
            IncubateeServiceRequest::STATUS_PENDING,
            IncubateeServiceRequest::STATUS_IN_PROGRESS,
            IncubateeServiceRequest::STATUS_DONE,
            IncubateeServiceRequest::STATUS_CANCELLED,
        ], true)) {
            $query->where('status', $status);
        }

        $serviceId = (int) ($filters['service_id'] ?? 0);
        if ($serviceId > 0) {
            $query->where('service_id', $serviceId);
        }

        $districtId = (int) ($filters['district_id'] ?? 0);
        if ($districtId > 0) {
            $query->whereHas('cfaSubmission', fn ($q) => $q->where('district_id', $districtId));
        }

        $q = (string) ($filters['q'] ?? '');
        if ($q !== '') {
            $like = '%'.$q.'%';
            $query->where(function (Builder $inner) use ($like): void {
                $inner->where('comment', 'like', $like)
                    ->orWhereHas('cfaSubmission', function ($cfa) use ($like): void {
                        $cfa->where('applicant_name', 'like', $like)
                            ->orWhere('application_no', 'like', $like)
                            ->orWhere('phone', 'like', $like);
                    })
                    ->orWhereHas('service', fn ($s) => $s->where('name', 'like', $like));
            });
        }

        $from = (string) ($filters['from'] ?? '');
        if ($from !== '') {
            $query->whereDate('created_at', '>=', $from);
        }
        $to = (string) ($filters['to'] ?? '');
        if ($to !== '') {
            $query->whereDate('created_at', '<=', $to);
        }

        return $query;
    }

    /**
     * @return array<string, int>
     */
    private function totalsForQuery(Builder $query): array
    {
        $countBase = clone $query;

        return [
            'total' => (int) (clone $countBase)->count(),
            'pending' => (int) (clone $countBase)->where('status', IncubateeServiceRequest::STATUS_PENDING)->count(),
            'in_progress' => (int) (clone $countBase)->where('status', IncubateeServiceRequest::STATUS_IN_PROGRESS)->count(),
            'done' => (int) (clone $countBase)->where('status', IncubateeServiceRequest::STATUS_DONE)->count(),
            'cancelled' => (int) (clone $countBase)->where('status', IncubateeServiceRequest::STATUS_CANCELLED)->count(),
        ];
    }

    /**
     * @return list<array{id: int, name: string}>
     */
    private function districtOptions(User $user): array
    {
        $ids = MentorshipRequestAccess::visibleDistrictIds($user);
        $query = District::query()->orderBy('name');
        if ($ids !== null) {
            if ($ids === []) {
                return [];
            }
            $query->whereIn('id', $ids);
        }

        return $query->get(['id', 'name'])
            ->map(fn (District $d): array => ['id' => (int) $d->id, 'name' => (string) $d->name])
            ->all();
    }
}
