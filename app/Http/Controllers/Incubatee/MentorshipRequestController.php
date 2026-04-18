<?php

namespace App\Http\Controllers\Incubatee;

use App\Http\Controllers\Controller;
use App\Models\MentorshipRequest;
use App\Services\MentorshipRequestNotifier;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;

class MentorshipRequestController extends Controller
{
    public function store(Request $request, MentorshipRequestNotifier $notifier): RedirectResponse
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

        if ($count > 0) {
            return back()->with('status', 'Mentorship request sent to '.$count.' team member(s) (state, hub, and district staff).');
        }

        return back()->with('status', 'Your request was saved. No recipients were found in the system — please contact your hub.');
    }
}
