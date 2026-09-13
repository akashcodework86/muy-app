<?php

namespace App\Http\Controllers\Api\Incubatee;

use App\Http\Controllers\Controller;
use App\Models\MentorshipRequest;
use App\Models\MentorshipSession;
use App\Services\ActivityLogger;
use App\Services\IncubateeAppPayloadService;
use App\Services\MentorshipRequestNotifier;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;

class MentorshipController extends Controller
{
    public function index(Request $request, IncubateeAppPayloadService $payload): JsonResponse
    {
        return response()->json($payload->mentorshipPage($request->user()));
    }

    public function store(
        Request $request,
        MentorshipRequestNotifier $notifier,
        ActivityLogger $activity,
        IncubateeAppPayloadService $payload,
    ): JsonResponse {
        $slugs = array_keys(config('mentorship.categories', []));
        $validated = $request->validate([
            'category' => ['required', 'string', Rule::in($slugs)],
            'comment' => ['nullable', 'string', 'max:2000'],
        ]);

        $user = $request->user();
        $submission = $payload->requireSubmission($user);

        $mr = MentorshipRequest::query()->create([
            'cfa_submission_id' => $submission->id,
            'requested_by_user_id' => $user->id,
            'category' => $validated['category'],
            'comment' => $validated['comment'] ?? null,
            'status' => MentorshipRequest::STATUS_PENDING,
        ]);
        $mr->load('session');

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
                'source' => 'incubatee-app',
            ],
        );

        $message = $count > 0
            ? 'Mentorship request sent to '.$count.' team member(s) (state, hub, and district staff).'
            : 'Your request was saved. No recipients were found in the system — please contact your hub.';

        return response()->json([
            'message' => $message,
            'message_hi' => $count > 0
                ? 'मेंटरशिप अनुरोध आपकी हब टीम को भेज दिया गया।'
                : 'आपका अनुरोध सहेज लिया गया। सिस्टम में कोई प्राप्तकर्ता नहीं मिला — कृपया अपने हब से संपर्क करें।',
            'request' => $payload->mentorshipRequest($mr),
        ], 201);
    }

    public function cancel(Request $request, MentorshipRequest $mentorshipRequest, IncubateeAppPayloadService $payload): JsonResponse
    {
        $user = $request->user();
        $submission = $payload->requireSubmission($user);
        if ((int) $mentorshipRequest->cfa_submission_id !== (int) $submission->id) {
            return response()->json(['message' => 'Forbidden.'], 403);
        }
        if (! $mentorshipRequest->incubateeCanCancel()) {
            return response()->json([
                'message' => 'This request can no longer be cancelled.',
            ], 422);
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

        $mentorshipRequest->unsetRelation('session');

        return response()->json([
            'message' => 'Your mentorship request was cancelled.',
            'message_hi' => 'आपका मेंटरशिप अनुरोध रद्द कर दिया गया।',
            'request' => $payload->mentorshipRequest($mentorshipRequest->fresh()),
        ]);
    }
}
