<?php

namespace App\Http\Controllers;

use App\Models\District;
use App\Models\MentorshipRequest;
use App\Models\MentorshipSession;
use App\Models\User;
use App\Services\ActivityLogger;
use App\Services\Exports\MentorshipRequestDashboardExcelExport;
use App\Support\MentorshipRequestAccess;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use Illuminate\View\View;
use Symfony\Component\HttpFoundation\StreamedResponse;

class MentorshipRequestStaffController extends Controller
{
    public function dashboard(Request $request): View
    {
        $user = $request->user();
        abort_unless(MentorshipRequestAccess::canViewDashboard($user), 403);

        $filters = $this->filtersFromRequest($request);
        $query = $this->filteredQuery($user, $filters);
        $totals = $this->totalsForQuery($query);
        $this->applyUniqueFilter($query, $filters);

        $rows = $query
            ->with([
                'cfaSubmission.district.hub',
                'requestedBy',
                'session',
            ])
            ->orderByDesc('id')
            ->paginate(25)
            ->withQueryString();

        $districtOptions = $this->districtOptions($user);
        $prefix = MentorshipRequestAccess::routePrefixForUser($user);

        return view('mentorship-requests.dashboard', [
            'rows' => $rows,
            'filters' => $filters,
            'totals' => $totals,
            'categories' => config('mentorship.categories', []),
            'districtOptions' => $districtOptions,
            'canHandle' => MentorshipRequestAccess::canHandle($user),
            'currentRole' => $user->role,
            'prefix' => $prefix,
            'showHub' => count($districtOptions) > 1,
            'isPaginated' => true,
        ]);
    }

    public function export(Request $request, MentorshipRequestDashboardExcelExport $excel): StreamedResponse
    {
        $user = $request->user();
        abort_unless(MentorshipRequestAccess::canViewDashboard($user), 403);

        $filters = $this->filtersFromRequest($request);
        $query = $this->filteredQuery($user, $filters);
        $totals = $this->totalsForQuery($query);
        $this->applyUniqueFilter($query, $filters);

        $rows = $query
            ->with([
                'cfaSubmission.district.hub',
                'requestedBy',
                'session',
            ])
            ->orderByDesc('id')
            ->get();

        return $excel->download($rows, $totals, $filters, config('mentorship.categories', []));
    }

    public function show(Request $request, MentorshipRequest $mentorshipRequest): View
    {
        $user = $request->user();
        abort_unless(MentorshipRequestAccess::canViewDashboard($user), 403);
        abort_unless($this->canViewRecord($user, $mentorshipRequest), 403);

        $mentorshipRequest->load([
            'cfaSubmission.district.hub',
            'requestedBy',
            'session.requests.cfaSubmission',
            'session.createdBy',
        ]);

        $sameCategoryPending = collect();
        if (MentorshipRequestAccess::canHandleRequest($user, $mentorshipRequest) && $mentorshipRequest->isPending()) {
            $sameCategoryPending = MentorshipRequest::query()
                ->where('status', MentorshipRequest::STATUS_PENDING)
                ->where('category', $mentorshipRequest->category)
                ->where('id', '!=', $mentorshipRequest->id)
                ->whereHas('cfaSubmission', fn ($q) => $q->where('district_id', (int) $user->district_id))
                ->with('cfaSubmission')
                ->orderByDesc('id')
                ->limit(50)
                ->get();
        }

        return view('mentorship-requests.show', [
            'row' => $mentorshipRequest,
            'categories' => config('mentorship.categories', []),
            'canHandle' => MentorshipRequestAccess::canHandleRequest($user, $mentorshipRequest),
            'sameCategoryPending' => $sameCategoryPending,
            'prefix' => MentorshipRequestAccess::routePrefixForUser($user),
            'currentRole' => $user->role,
        ]);
    }

    public function scheduleForm(Request $request): View|RedirectResponse
    {
        $user = $request->user();
        abort_unless(MentorshipRequestAccess::canHandle($user), 403);

        $ids = $this->normalizeIds($request->input('ids', $request->query('ids', [])));
        if ($ids === []) {
            return redirect()
                ->route(MentorshipRequestAccess::routePrefixForUser($user).'mentorship-requests.dashboard')
                ->withErrors(['ids' => 'Select at least one pending request.']);
        }

        $requests = $this->pendingRequestsForIm($user, $ids);
        if ($requests->count() !== count($ids)) {
            abort(403, 'One or more requests cannot be scheduled.');
        }

        $categories = $requests->pluck('category')->unique()->values();
        if ($categories->count() !== 1) {
            return back()->withErrors(['ids' => 'A batch session can only include the same mentorship category.']);
        }

        return view('mentorship-requests.schedule', [
            'requests' => $requests,
            'category' => (string) $categories->first(),
            'categories' => config('mentorship.categories', []),
            'prefix' => MentorshipRequestAccess::routePrefixForUser($user),
        ]);
    }

    public function scheduleStore(Request $request, ActivityLogger $activity): RedirectResponse
    {
        $user = $request->user();
        abort_unless(MentorshipRequestAccess::canHandle($user), 403);
        $prefix = MentorshipRequestAccess::routePrefixForUser($user);

        $validated = $request->validate([
            'ids' => ['required', 'array', 'min:1'],
            'ids.*' => ['integer'],
            'scheduled_at' => ['required', 'date'],
            'meeting_link' => ['nullable', 'string', 'max:500', 'url'],
        ]);

        $ids = $this->normalizeIds($validated['ids']);
        $requests = $this->pendingRequestsForIm($user, $ids);
        if ($requests->count() !== count($ids)) {
            return back()->withInput()->withErrors(['ids' => 'One or more requests are not pending in your district.']);
        }

        $categories = $requests->pluck('category')->unique()->values();
        if ($categories->count() !== 1) {
            return back()->withInput()->withErrors(['ids' => 'A batch session can only include the same mentorship category.']);
        }

        $session = DB::transaction(function () use ($user, $requests, $categories, $validated): MentorshipSession {
            $session = MentorshipSession::query()->create([
                'district_id' => (int) $user->district_id,
                'category' => (string) $categories->first(),
                'kind' => $requests->count() === 1 ? MentorshipSession::KIND_INDIVIDUAL : MentorshipSession::KIND_BATCH,
                'scheduled_at' => $validated['scheduled_at'],
                'meeting_link' => trim((string) ($validated['meeting_link'] ?? '')) ?: null,
                'status' => MentorshipSession::STATUS_SCHEDULED,
                'created_by_user_id' => (int) $user->id,
            ]);

            foreach ($requests as $row) {
                $row->mentorship_session_id = $session->id;
                $row->status = MentorshipRequest::STATUS_SCHEDULED;
                $row->save();
            }

            return $session;
        });

        $activity->log(
            type: 'mentorship.scheduled',
            title: ($user->name ?? 'IM').' scheduled a mentorship session ('.$requests->count().' incubatee'.($requests->count() === 1 ? '' : 's').')',
            actor: $user,
            subject: $session,
            districtId: (int) $user->district_id,
            meta: [
                'category' => $session->category,
                'kind' => $session->kind,
                'count' => $requests->count(),
            ],
        );

        return redirect()
            ->route($prefix.'mentorship-requests.show', $requests->first())
            ->with('status', 'Session scheduled. Mark it Done after the online meeting and upload a screenshot.');
    }

    public function completeForm(Request $request, MentorshipSession $mentorshipSession): View
    {
        $user = $request->user();
        abort_unless(MentorshipRequestAccess::canHandle($user), 403);
        abort_unless((int) $mentorshipSession->district_id === (int) $user->district_id, 403);
        abort_if($mentorshipSession->isDone(), 403, 'This session is already done.');

        $mentorshipSession->load(['requests.cfaSubmission']);

        return view('mentorship-requests.complete', [
            'session' => $mentorshipSession,
            'categories' => config('mentorship.categories', []),
            'prefix' => MentorshipRequestAccess::routePrefixForUser($user),
        ]);
    }

    public function completeStore(Request $request, MentorshipSession $mentorshipSession, ActivityLogger $activity): RedirectResponse
    {
        $user = $request->user();
        abort_unless(MentorshipRequestAccess::canHandle($user), 403);
        abort_unless((int) $mentorshipSession->district_id === (int) $user->district_id, 403);
        abort_if($mentorshipSession->isDone(), 403, 'This session is already done.');

        $prefix = MentorshipRequestAccess::routePrefixForUser($user);

        $request->validate([
            'proof' => ['required', 'file', 'mimes:jpg,jpeg,png,webp', 'max:10240'],
        ], [
            'proof.required' => 'Upload a screenshot of the online meeting.',
        ]);

        $file = $request->file('proof');
        $path = $file->store('mentorship-session-proofs');

        DB::transaction(function () use ($mentorshipSession, $user, $path, $file): void {
            $mentorshipSession->proof_path = $path;
            $mentorshipSession->proof_original_name = $file->getClientOriginalName();
            $mentorshipSession->status = MentorshipSession::STATUS_DONE;
            $mentorshipSession->done_at = now();
            $mentorshipSession->done_by_user_id = (int) $user->id;
            $mentorshipSession->save();

            MentorshipRequest::query()
                ->where('mentorship_session_id', $mentorshipSession->id)
                ->where('status', MentorshipRequest::STATUS_SCHEDULED)
                ->update([
                    'status' => MentorshipRequest::STATUS_DONE,
                    'done_at' => $mentorshipSession->done_at,
                ]);
        });

        $activity->log(
            type: 'mentorship.done',
            title: ($user->name ?? 'IM').' completed an online mentorship session',
            actor: $user,
            subject: $mentorshipSession,
            districtId: (int) $user->district_id,
        );

        $first = MentorshipRequest::query()->where('mentorship_session_id', $mentorshipSession->id)->orderBy('id')->first();

        if ($first) {
            return redirect()
                ->route($prefix.'mentorship-requests.show', $first)
                ->with('status', 'Session marked Done. Unique incubatees count toward 5.2 on the Deliverables page.');
        }

        return redirect()
            ->route($prefix.'mentorship-requests.dashboard')
            ->with('status', 'Session marked Done. Unique incubatees count toward 5.2 on the Deliverables page.');
    }

    public function proof(Request $request, MentorshipSession $mentorshipSession): StreamedResponse
    {
        $user = $request->user();
        abort_unless(MentorshipRequestAccess::canViewDashboard($user), 403);
        abort_unless($this->canViewSession($user, $mentorshipSession), 403);
        abort_if($user->role === 'incubatee', 403);

        abort_unless($mentorshipSession->proof_path && Storage::exists($mentorshipSession->proof_path), 404);

        return Storage::download(
            $mentorshipSession->proof_path,
            $mentorshipSession->proof_original_name ?: 'meeting-screenshot.jpg'
        );
    }

    private function scopedQuery(User $user): Builder
    {
        $query = MentorshipRequest::query();
        $districtIds = MentorshipRequestAccess::visibleDistrictIds($user);
        if ($districtIds === []) {
            $query->whereRaw('1 = 0');

            return $query;
        }
        if ($districtIds !== null) {
            $query->whereHas('cfaSubmission', fn ($q) => $q->whereIn('district_id', $districtIds));
        }

        return $query;
    }

    /**
     * @return array<string, mixed>
     */
    private function filtersFromRequest(Request $request): array
    {
        return [
            'q' => trim((string) $request->query('q', '')),
            'status' => trim((string) $request->query('status', '')),
            'category' => trim((string) $request->query('category', '')),
            'district_id' => (int) $request->query('district_id', 0),
            'from' => trim((string) $request->query('from', '')),
            'to' => trim((string) $request->query('to', '')),
            'unique' => $request->boolean('unique'),
        ];
    }

    /**
     * @param  array<string, mixed>  $filters
     */
    private function filteredQuery(User $user, array $filters): Builder
    {
        $query = $this->scopedQuery($user);
        $this->applyListFilters($query, $filters);

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
            'unique' => (int) (clone $countBase)->toBase()->distinct()->count('cfa_submission_id'),
            'pending' => (int) (clone $countBase)->where('status', MentorshipRequest::STATUS_PENDING)->count(),
            'scheduled' => (int) (clone $countBase)->where('status', MentorshipRequest::STATUS_SCHEDULED)->count(),
            'done' => (int) (clone $countBase)->where('status', MentorshipRequest::STATUS_DONE)->count(),
            'cancelled' => (int) (clone $countBase)->where('status', MentorshipRequest::STATUS_CANCELLED)->count(),
        ];
    }

    /**
     * @param  array<string, mixed>  $filters
     */
    private function applyUniqueFilter(Builder $query, array $filters): void
    {
        if (empty($filters['unique'])) {
            return;
        }

        $latestIds = (clone $query)
            ->select('cfa_submission_id')
            ->selectRaw('MAX(id) as id')
            ->groupBy('cfa_submission_id')
            ->pluck('id');
        $query->whereIn('id', $latestIds);
    }

    /**
     * @param  array<string, mixed>  $filters
     */
    private function applyListFilters(Builder $query, array $filters): void
    {
        $status = (string) ($filters['status'] ?? '');
        if (in_array($status, [
            MentorshipRequest::STATUS_PENDING,
            MentorshipRequest::STATUS_SCHEDULED,
            MentorshipRequest::STATUS_DONE,
            MentorshipRequest::STATUS_CANCELLED,
        ], true)) {
            $query->where('status', $status);
        }

        $category = (string) ($filters['category'] ?? '');
        if ($category !== '') {
            $query->where('category', $category);
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
                    });
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
    }

    private function canViewRecord(User $user, MentorshipRequest $request): bool
    {
        $districtIds = MentorshipRequestAccess::visibleDistrictIds($user);
        $request->loadMissing('cfaSubmission');
        $districtId = (int) ($request->cfaSubmission?->district_id ?? 0);
        if ($districtIds === null) {
            return true;
        }

        return $districtId > 0 && in_array($districtId, $districtIds, true);
    }

    private function canViewSession(User $user, MentorshipSession $session): bool
    {
        $districtIds = MentorshipRequestAccess::visibleDistrictIds($user);
        if ($districtIds === null) {
            return true;
        }

        return in_array((int) $session->district_id, $districtIds, true);
    }

    /**
     * @param  list<int>  $ids
     * @return \Illuminate\Support\Collection<int, MentorshipRequest>
     */
    private function pendingRequestsForIm(User $user, array $ids)
    {
        return MentorshipRequest::query()
            ->whereIn('id', $ids)
            ->where('status', MentorshipRequest::STATUS_PENDING)
            ->whereHas('cfaSubmission', fn ($q) => $q->where('district_id', (int) $user->district_id))
            ->with('cfaSubmission')
            ->orderBy('id')
            ->get();
    }

    /**
     * @param  mixed  $raw
     * @return list<int>
     */
    private function normalizeIds(mixed $raw): array
    {
        if (is_string($raw) && str_contains($raw, ',')) {
            $raw = explode(',', $raw);
        }
        if (! is_array($raw)) {
            $raw = $raw !== null && $raw !== '' ? [$raw] : [];
        }

        return collect($raw)
            ->map(fn ($v): int => (int) $v)
            ->filter(fn (int $id): bool => $id > 0)
            ->unique()
            ->values()
            ->all();
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
