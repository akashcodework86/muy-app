<?php

namespace App\Http\Controllers;

use App\Models\IncubateeMeeting;
use App\Support\IncubateeMeetingAccess;
use App\Support\Ist;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Storage;
use Illuminate\Validation\Rule;
use Illuminate\View\View;
use Symfony\Component\HttpFoundation\StreamedResponse;

class IncubateeMeetingStaffController extends Controller
{
    public function dashboard(Request $request): View
    {
        $user = $request->user();
        abort_unless(IncubateeMeetingAccess::canViewDashboard($user), 403);

        $filters = $this->filtersFromRequest($request);
        $query = $this->filteredQuery($filters);
        $totals = $this->totals();

        $rows = $query
            ->with('createdBy')
            ->orderByRaw("CASE WHEN status = ? AND scheduled_at >= ? THEN 0 ELSE 1 END", [
                IncubateeMeeting::STATUS_SCHEDULED,
                now(),
            ])
            ->orderBy('scheduled_at')
            ->paginate(25)
            ->withQueryString();

        return view('incubatee-meetings.dashboard', [
            'rows' => $rows,
            'filters' => $filters,
            'totals' => $totals,
            'prefix' => IncubateeMeetingAccess::routePrefixForUser($user),
            'user' => $user,
        ]);
    }

    public function create(Request $request): View
    {
        $user = $request->user();
        abort_unless(IncubateeMeetingAccess::canCreate($user), 403);

        return view('incubatee-meetings.form', [
            'row' => null,
            'prefix' => IncubateeMeetingAccess::routePrefixForUser($user),
            'user' => $user,
        ]);
    }

    public function store(Request $request): RedirectResponse
    {
        $user = $request->user();
        abort_unless(IncubateeMeetingAccess::canCreate($user), 403);
        $prefix = IncubateeMeetingAccess::routePrefixForUser($user);
        $validated = $this->validatedPayload($request);

        IncubateeMeeting::query()->create([
            ...$validated,
            'status' => IncubateeMeeting::STATUS_SCHEDULED,
            'created_by_user_id' => (int) $user->id,
        ]);

        return redirect()
            ->route($prefix.'incubatee-meetings.dashboard')
            ->with('status', 'Meeting scheduled. It is now visible on every incubatee dashboard.');
    }

    public function edit(Request $request, IncubateeMeeting $incubateeMeeting): View
    {
        $user = $request->user();
        abort_unless(IncubateeMeetingAccess::canManage($user, $incubateeMeeting), 403);

        return view('incubatee-meetings.form', [
            'row' => $incubateeMeeting,
            'prefix' => IncubateeMeetingAccess::routePrefixForUser($user),
            'user' => $user,
        ]);
    }

    public function update(Request $request, IncubateeMeeting $incubateeMeeting): RedirectResponse
    {
        $user = $request->user();
        abort_unless(IncubateeMeetingAccess::canManage($user, $incubateeMeeting), 403);
        $prefix = IncubateeMeetingAccess::routePrefixForUser($user);
        $validated = $this->validatedPayload($request);

        $incubateeMeeting->fill($validated);
        $incubateeMeeting->save();

        return redirect()
            ->route($prefix.'incubatee-meetings.dashboard')
            ->with('status', 'Meeting updated.');
    }

    public function cancel(Request $request, IncubateeMeeting $incubateeMeeting): RedirectResponse
    {
        $user = $request->user();
        abort_unless(IncubateeMeetingAccess::canManage($user, $incubateeMeeting), 403);
        $prefix = IncubateeMeetingAccess::routePrefixForUser($user);

        $incubateeMeeting->status = IncubateeMeeting::STATUS_CANCELLED;
        $incubateeMeeting->cancelled_at = now();
        $incubateeMeeting->cancelled_by_user_id = (int) $user->id;
        $incubateeMeeting->save();

        return redirect()
            ->route($prefix.'incubatee-meetings.dashboard')
            ->with('status', 'Meeting cancelled.');
    }

    public function completeForm(Request $request, IncubateeMeeting $incubateeMeeting): View
    {
        $user = $request->user();
        abort_unless(IncubateeMeetingAccess::canManage($user, $incubateeMeeting), 403);

        return view('incubatee-meetings.complete', [
            'row' => $incubateeMeeting,
            'prefix' => IncubateeMeetingAccess::routePrefixForUser($user),
        ]);
    }

    public function completeStore(Request $request, IncubateeMeeting $incubateeMeeting): RedirectResponse
    {
        $user = $request->user();
        abort_unless(IncubateeMeetingAccess::canManage($user, $incubateeMeeting), 403);
        $prefix = IncubateeMeetingAccess::routePrefixForUser($user);

        $request->validate([
            'proof' => ['required', 'file', 'mimes:jpg,jpeg,png,webp,pdf', 'max:10240'],
        ], [
            'proof.required' => 'Upload a PDF or image as proof before marking this meeting done.',
        ]);

        $file = $request->file('proof');
        $path = $file->store('incubatee-meeting-proofs');

        $incubateeMeeting->proof_path = $path;
        $incubateeMeeting->proof_original_name = $file->getClientOriginalName();
        $incubateeMeeting->status = IncubateeMeeting::STATUS_DONE;
        $incubateeMeeting->done_at = now();
        $incubateeMeeting->done_by_user_id = (int) $user->id;
        $incubateeMeeting->save();

        return redirect()
            ->route($prefix.'incubatee-meetings.dashboard')
            ->with('status', 'Meeting marked done.');
    }

    public function proof(Request $request, IncubateeMeeting $incubateeMeeting): StreamedResponse
    {
        $user = $request->user();
        abort_unless(IncubateeMeetingAccess::canViewDashboard($user), 403);
        abort_unless($incubateeMeeting->proof_path && Storage::exists($incubateeMeeting->proof_path), 404);

        $filename = $incubateeMeeting->proof_original_name ?: 'meeting-proof';
        if ($request->boolean('inline')) {
            $mime = Storage::mimeType($incubateeMeeting->proof_path) ?: 'application/octet-stream';

            return Storage::response($incubateeMeeting->proof_path, $filename, [
                'Content-Type' => $mime,
                'Cache-Control' => 'private, max-age=3600',
            ], 'inline');
        }

        return Storage::download($incubateeMeeting->proof_path, $filename);
    }

    /**
     * @return array<string, mixed>
     */
    private function validatedPayload(Request $request): array
    {
        $validated = $request->validate([
            'title' => ['required', 'string', 'max:191'],
            'scheduled_at' => ['required', 'date'],
            'duration_minutes' => ['nullable', 'integer', 'min:1', 'max:1440'],
            'mode' => ['required', 'string', Rule::in([IncubateeMeeting::MODE_ONLINE, IncubateeMeeting::MODE_IN_PERSON])],
            'meeting_link' => ['nullable', 'required_if:mode,'.IncubateeMeeting::MODE_ONLINE, 'string', 'max:500', 'url'],
            'venue' => ['nullable', 'required_if:mode,'.IncubateeMeeting::MODE_IN_PERSON, 'string', 'max:255'],
            'agenda' => ['nullable', 'string', 'max:5000'],
        ]);

        $scheduledAt = Carbon::parse((string) $validated['scheduled_at'], Ist::TZ);
        $mode = (string) $validated['mode'];

        return [
            'title' => trim((string) $validated['title']),
            'scheduled_at' => $scheduledAt,
            'duration_minutes' => isset($validated['duration_minutes']) && $validated['duration_minutes'] !== ''
                ? (int) $validated['duration_minutes']
                : null,
            'mode' => $mode,
            'meeting_link' => $mode === IncubateeMeeting::MODE_ONLINE
                ? (trim((string) ($validated['meeting_link'] ?? '')) ?: null)
                : null,
            'venue' => $mode === IncubateeMeeting::MODE_IN_PERSON
                ? (trim((string) ($validated['venue'] ?? '')) ?: null)
                : null,
            'agenda' => trim((string) ($validated['agenda'] ?? '')) ?: null,
        ];
    }

    /**
     * @return array{q: string, status: string, from: string, to: string}
     */
    private function filtersFromRequest(Request $request): array
    {
        return [
            'q' => trim((string) $request->query('q', '')),
            'status' => trim((string) $request->query('status', '')),
            'from' => trim((string) $request->query('from', '')),
            'to' => trim((string) $request->query('to', '')),
        ];
    }

    /**
     * @param  array{q: string, status: string, from: string, to: string}  $filters
     */
    private function filteredQuery(array $filters)
    {
        $query = IncubateeMeeting::query();

        if ($filters['q'] !== '') {
            $q = $filters['q'];
            $query->where(function ($inner) use ($q): void {
                $inner->where('title', 'like', '%'.$q.'%')
                    ->orWhere('agenda', 'like', '%'.$q.'%')
                    ->orWhere('venue', 'like', '%'.$q.'%');
            });
        }

        if ($filters['status'] === 'upcoming') {
            $query->where('status', IncubateeMeeting::STATUS_SCHEDULED)
                ->where('scheduled_at', '>=', now());
        } elseif (in_array($filters['status'], [
            IncubateeMeeting::STATUS_SCHEDULED,
            IncubateeMeeting::STATUS_DONE,
            IncubateeMeeting::STATUS_CANCELLED,
        ], true)) {
            $query->where('status', $filters['status']);
        }

        if ($filters['from'] !== '') {
            $from = Ist::carbon($filters['from']);
            if ($from) {
                $query->where('scheduled_at', '>=', $from->startOfDay());
            }
        }

        if ($filters['to'] !== '') {
            $to = Ist::carbon($filters['to']);
            if ($to) {
                $query->where('scheduled_at', '<=', $to->endOfDay());
            }
        }

        return $query;
    }

    /**
     * @return array{total: int, upcoming: int, done: int, cancelled: int}
     */
    private function totals(): array
    {
        $now = now();

        return [
            'total' => IncubateeMeeting::query()->count(),
            'upcoming' => IncubateeMeeting::query()
                ->where('status', IncubateeMeeting::STATUS_SCHEDULED)
                ->where('scheduled_at', '>=', $now)
                ->count(),
            'done' => IncubateeMeeting::query()->where('status', IncubateeMeeting::STATUS_DONE)->count(),
            'cancelled' => IncubateeMeeting::query()->where('status', IncubateeMeeting::STATUS_CANCELLED)->count(),
        ];
    }
}
