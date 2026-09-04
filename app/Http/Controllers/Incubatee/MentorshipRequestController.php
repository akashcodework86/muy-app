<?php

namespace App\Http\Controllers\Incubatee;

use App\Http\Controllers\Controller;
use App\Models\MentorshipRequest;
use App\Models\MentorshipSession;
use App\Services\ActivityLogger;
use App\Services\MentorshipRequestNotifier;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;
use Illuminate\View\View;

class MentorshipRequestController extends Controller
{
    public function index(Request $request): View
    {
        $user = $request->user()->load(['cfaSubmission.district']);
        $submission = $user->cfaSubmission;
        if ($submission === null) {
            abort(404, 'No CFA profile is linked to this account.');
        }

        $requests = MentorshipRequest::query()
            ->where('cfa_submission_id', $submission->id)
            ->with('session')
            ->latest('id')
            ->limit(50)
            ->get();

        return view('incubatee.mentorship', [
            'user' => $user,
            'submission' => $submission,
            'requests' => $requests,
            'categories' => config('mentorship.categories', []),
        ]);
    }

    public function store(Request $request, MentorshipRequestNotifier $notifier, ActivityLogger $activity): RedirectResponse
    {
        $slugs = array_keys(config('mentorship.categories', []));

        $validated = $request->validate([
            'category' => ['required', 'string', Rule::in($slugs)],
            'comment' => ['nullable', 'string', 'max:2000'],
        ]);

        $user = $request->user();
        $submission = $user->cfaSubmission;
        if ($submission === null) {
            abort(404, 'No CFA profile is linked to this account.');
        }

        $mr = MentorshipRequest::query()->create([
            'cfa_submission_id' => $submission->id,
            'requested_by_user_id' => $user->id,
            'category' => $validated['category'],
            'comment' => $validated['comment'] ?? null,
            'status' => MentorshipRequest::STATUS_PENDING,
        ]);

        $count = $notifier->notify($mr);

        $categoryLabel = config('mentorship.categories.'.$validated['category'].'.label', ucfirst(str_replace('_', ' ', $validated['category'])));
        $activity->log(
            type: 'mentorship.requested',
            title: ($user->name ?? 'An incubatee').' requested '.$categoryLabel.' mentorship',
            actor: $user,
            subject: $mr,
            districtId: $submission->district_id ? (int) $submission->district_id : null,
            meta: [
                'category' => $validated['category'],
                'category_label' => $categoryLabel,
                'application_no' => $submission->application_no,
            ],
        );

        if ($count > 0) {
            return back()->with('status', 'Mentorship request sent to '.$count.' team member(s) (state, hub, and district staff).');
        }

        return back()->with('status', 'Your request was saved. No recipients were found in the system — please contact your hub.');
    }

    public function cancel(Request $request, MentorshipRequest $mentorshipRequest): RedirectResponse
    {
        $user = $request->user();
        $submission = $user->cfaSubmission;
        if ($submission === null || (int) $mentorshipRequest->cfa_submission_id !== (int) $submission->id) {
            abort(403);
        }
        if (! $mentorshipRequest->incubateeCanCancel()) {
            return back()->withErrors(['cancel' => 'This request can no longer be cancelled.']);
        }

        $sessionId = $mentorshipRequest->mentorship_session_id;

        $mentorshipRequest->status = MentorshipRequest::STATUS_CANCELLED;
        $mentorshipRequest->cancelled_at = now();
        $mentorshipRequest->cancelled_by_user_id = (int) $user->id;
        $mentorshipRequest->mentorship_session_id = null;
        $mentorshipRequest->save();

        if ($sessionId) {
            $remaining = MentorshipRequest::query()
                ->where('mentorship_session_id', $sessionId)
                ->where('status', MentorshipRequest::STATUS_SCHEDULED)
                ->count();
            if ($remaining === 0) {
                MentorshipSession::query()
                    ->whereKey($sessionId)
                    ->where('status', MentorshipSession::STATUS_SCHEDULED)
                    ->delete();
            }
        }

        return back()->with('status', 'Your mentorship request was cancelled.');
    }
}
