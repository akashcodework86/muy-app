<?php

namespace App\Http\Controllers\Incubatee;

use App\Http\Controllers\Controller;
use App\Models\CfaSubmission;
use App\Models\MentorshipRequest;
use App\Models\ServiceCase;
use App\Support\UdmitaKoshCatalog;
use Illuminate\View\View;

class IncubateeDashboardController extends Controller
{
    public function index(): View
    {
        $user = auth()->user()->load([
            'cfaSubmission.district',
            'cfaSubmission.fiscalYear',
            'cfaSubmission.onboardingBatchMembership.batch.hub',
            'cfaSubmission.serviceCases.service',
        ]);

        /** @var CfaSubmission|null $submission */
        $submission = $user->cfaSubmission;
        if ($submission === null) {
            abort(404, 'No CFA profile is linked to this account.');
        }

        $cases = $submission->serviceCases;
        $approvedCases = $cases->where('status', ServiceCase::STATUS_APPROVED);
        $completed = $approvedCases->count();
        $open = $cases->whereIn('status', [
            ServiceCase::STATUS_DRAFT,
            ServiceCase::STATUS_PENDING_APPROVAL,
            ServiceCase::STATUS_SENT_BACK,
        ])->count();

        $payload = is_array($submission->payload) ? $submission->payload : [];
        $batch = $submission->onboardingBatchMembership?->batch;
        $hubName = $batch?->hub?->name;

        $scalar = static function ($value, string $fallback = '—'): string {
            if ($value === null || $value === '') {
                return $fallback;
            }
            if (is_scalar($value)) {
                return (string) $value;
            }

            return (string) json_encode($value, JSON_UNESCAPED_UNICODE);
        };

        $emailFromPayload = $payload['email'] ?? null;
        $displayEmail = (is_scalar($emailFromPayload) && trim((string) $emailFromPayload) !== '')
            ? (string) $emailFromPayload
            : ($user->email ?? '—');

        $membership = $submission->onboardingBatchMembership;
        $mentorshipRequests = MentorshipRequest::query()
            ->where('cfa_submission_id', $submission->id)
            ->with('session')
            ->latest('id')
            ->limit(20)
            ->get();
        $firstMentorshipAt = $mentorshipRequests->min('created_at');
        $mentorshipCount = MentorshipRequest::query()
            ->where('cfa_submission_id', $submission->id)
            ->count();

        $firstCompletedCase = $approvedCases
            ->sortBy(fn ($c) => $c->delivered_on ?? $c->approved_at ?? $c->updated_at)
            ->first();

        $journey = [
            [
                'key' => 'cfa',
                'icon' => '📝',
                'title' => 'CFA application submitted',
                'at' => $submission->created_at,
                'detail' => $submission->application_no
                    ? 'Application '.$submission->application_no.' filed.'
                    : 'Your CFA application was filed.',
                'status' => 'done',
            ],
            [
                'key' => 'onboarded',
                'icon' => '🎉',
                'title' => 'Onboarded into batch',
                'at' => $batch?->onboarding_date ?? $membership?->created_at,
                'detail' => $batch?->name
                    ? 'Joined '.$batch->name.($hubName ? ' · '.$hubName : '').'.'
                    : 'Waiting to be onboarded into a hub batch.',
                'status' => $batch ? 'done' : 'upcoming',
            ],
            [
                'key' => 'mentorship',
                'icon' => '🤝',
                'title' => 'First mentorship requested',
                'at' => $firstMentorshipAt,
                'detail' => $firstMentorshipAt
                    ? $mentorshipCount.' mentorship '.($mentorshipCount === 1 ? 'request' : 'requests').' sent so far.'
                    : 'Ask your hub for help whenever you need guidance.',
                'status' => $firstMentorshipAt ? 'done' : 'current',
            ],
            [
                'key' => 'first-service',
                'icon' => '🛠️',
                'title' => 'First service delivered',
                'at' => $firstCompletedCase?->delivered_on ?? $firstCompletedCase?->approved_at,
                'detail' => $firstCompletedCase
                    ? ($firstCompletedCase->service?->name ?? 'A service').' delivered by your hub team.'
                    : 'Your hub will log your first supported service here.',
                'status' => $firstCompletedCase ? 'done' : 'upcoming',
            ],
            [
                'key' => 'milestones',
                'icon' => '🏆',
                'title' => 'Milestones & growth',
                'at' => null,
                'detail' => 'Product launches, revenue wins and pitch milestones — coming soon.',
                'status' => 'upcoming',
            ],
        ];

        return view('incubatee.dashboard', [
            'user' => $user,
            'submission' => $submission,
            'payload' => $payload,
            'displayEmail' => $displayEmail,
            'displayFormStage' => $scalar($payload['form_stage'] ?? null),
            'displayProduct' => $scalar($payload['product'] ?? ($payload['business_category'] ?? null)),
            'batch' => $batch,
            'hubName' => $hubName,
            'serviceCases' => $approvedCases->values(),
            'servicesCompletedCount' => $completed,
            'servicesOpenCount' => $open,
            'serviceCasesTotalCount' => $cases->count(),
            'journey' => $journey,
            'mentorshipCount' => $mentorshipCount,
            'mentorshipRequests' => $mentorshipRequests,
        ]);
    }

    public function udmitaKosh(): View
    {
        return view('incubatee.udmita-kosh', [
            'user' => auth()->user(),
            'categories' => UdmitaKoshCatalog::categories(),
            'documents' => UdmitaKoshCatalog::resourceDocuments(),
        ]);
    }
}
