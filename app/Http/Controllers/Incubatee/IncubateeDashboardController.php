<?php

namespace App\Http\Controllers\Incubatee;

use App\Http\Controllers\Controller;
use App\Models\CfaSubmission;
use App\Models\IncubateeMeeting;
use App\Models\IncubateeServiceRequest;
use App\Models\MentorshipRequest;
use App\Models\ServiceCase;
use App\Models\ServiceCaseAttachment;
use App\Support\BmcService;
use App\Support\IncubateeLocale;
use App\Support\IncubateeServiceCatalog;
use App\Support\ServiceCaseAttachmentFile;
use App\Support\UdmitaKoshCatalog;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;
use Symfony\Component\HttpFoundation\StreamedResponse;

class IncubateeDashboardController extends Controller
{
    public function index(): View
    {
        $user = auth()->user()->load([
            'cfaSubmission.district',
            'cfaSubmission.fiscalYear',
            'cfaSubmission.onboardingBatchMembership.batch.hub',
            'cfaSubmission.serviceCases.service.deliverable',
            'cfaSubmission.serviceCases.attachments',
        ]);

        /** @var CfaSubmission|null $submission */
        $submission = $user->cfaSubmission;
        if ($submission === null) {
            abort(404, 'No CFA profile is linked to this account.');
        }

        $cases = $submission->serviceCases;
        $bmcCases = $cases
            ->filter(fn (ServiceCase $case) => BmcService::isBmc($case->service))
            ->sortByDesc(fn (ServiceCase $case) => $case->delivered_on ?? $case->approved_at ?? $case->updated_at)
            ->values();
        $bmcCase = $bmcCases->first(
            fn (ServiceCase $case) => $case->status === ServiceCase::STATUS_APPROVED
        ) ?? $bmcCases->first();

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

        $displayEmail = $submission->applicantEmail() ?? '—';

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

        $serviceRequests = IncubateeServiceRequest::query()
            ->where('cfa_submission_id', $submission->id)
            ->with('service')
            ->latest('id')
            ->limit(20)
            ->get();

        $allMeetings = IncubateeMeeting::query()
            ->with('createdBy')
            ->orderBy('scheduled_at')
            ->get();
        $upcomingMeetings = $allMeetings
            ->filter(fn (IncubateeMeeting $meeting) => $meeting->isUpcoming())
            ->values();
        $historyMeetings = $allMeetings
            ->reject(fn (IncubateeMeeting $meeting) => $meeting->isUpcoming())
            ->sortByDesc(fn (IncubateeMeeting $meeting) => $meeting->scheduled_at?->getTimestamp() ?? 0)
            ->values();

        $bmcReady = $bmcCase && $bmcCase->status === ServiceCase::STATUS_APPROVED;
        $bmcAt = $bmcCase?->delivered_on ?? $bmcCase?->approved_at ?? $bmcCase?->updated_at;
        $hubSuffix = $hubName ? ' · '.$hubName : '';

        $journey = [
            [
                'key' => 'cfa',
                'icon' => '📝',
                'title' => __('incubatee.journey.cfa_title'),
                'at' => $submission->created_at,
                'detail' => $submission->application_no
                    ? __('incubatee.journey.cfa_detail', ['no' => $submission->application_no])
                    : __('incubatee.journey.cfa_detail_plain'),
                'status' => 'done',
            ],
            [
                'key' => 'onboarded',
                'icon' => '🎉',
                'title' => __('incubatee.journey.onboarded_title'),
                'at' => $batch?->onboarding_date ?? $membership?->created_at,
                'detail' => $batch?->name
                    ? __('incubatee.journey.onboarded_detail', ['batch' => $batch->name, 'hub' => $hubSuffix])
                    : __('incubatee.journey.onboarded_waiting'),
                'status' => $batch ? 'done' : 'upcoming',
            ],
            [
                'key' => 'mentorship',
                'icon' => '🤝',
                'title' => __('incubatee.journey.mentorship_title'),
                'at' => $firstMentorshipAt,
                'detail' => $firstMentorshipAt
                    ? __('incubatee.journey.mentorship_detail', ['count' => $mentorshipCount])
                    : __('incubatee.journey.mentorship_waiting'),
                'status' => $firstMentorshipAt ? 'done' : 'current',
            ],
            [
                'key' => 'bmc',
                'icon' => '🧩',
                'title' => __('incubatee.journey.bmc_title'),
                'at' => $bmcReady ? $bmcAt : null,
                'detail' => $bmcReady
                    ? __('incubatee.journey.bmc_detail')
                    : __('incubatee.journey.bmc_waiting'),
                'status' => $bmcReady ? 'done' : 'upcoming',
            ],
            [
                'key' => 'milestones',
                'icon' => '🏆',
                'title' => __('incubatee.journey.milestones_title'),
                'at' => null,
                'detail' => __('incubatee.journey.milestones_detail'),
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
            'bmcCase' => $bmcCase,
            'bmcAttachments' => $bmcCase?->attachments ?? collect(),
            'journey' => $journey,
            'mentorshipCount' => $mentorshipCount,
            'mentorshipRequests' => $mentorshipRequests,
            'serviceRequests' => $serviceRequests,
            'upcomingMeetings' => $upcomingMeetings,
            'historyMeetings' => $historyMeetings,
            'serviceGroups' => IncubateeServiceCatalog::grouped(),
        ]);
    }

    public function switchLanguage(Request $request): RedirectResponse
    {
        $locale = $request->validate([
            'locale' => ['required', 'in:hi,en'],
        ])['locale'];

        $request->session()->put(IncubateeLocale::COOKIE, $locale);

        return back()->withCookie(cookie(
            IncubateeLocale::COOKIE,
            $locale,
            60 * 24 * 365,
            '/',
            null,
            false,
            false,
            false,
            'lax'
        ));
    }

    public function viewBmcDocument(Request $request, ServiceCaseAttachment $attachment): StreamedResponse
    {
        $this->assertOwnBmcAttachment($request, $attachment);

        return ServiceCaseAttachmentFile::respond($attachment, false);
    }

    public function downloadBmcDocument(Request $request, ServiceCaseAttachment $attachment): StreamedResponse
    {
        $this->assertOwnBmcAttachment($request, $attachment);

        return ServiceCaseAttachmentFile::respond($attachment, true);
    }

    public function udmitaKosh(): View
    {
        return view('incubatee.udmita-kosh', [
            'user' => auth()->user(),
            'categories' => UdmitaKoshCatalog::categories(),
        ]);
    }

    private function assertOwnBmcAttachment(Request $request, ServiceCaseAttachment $attachment): void
    {
        $submission = $request->user()?->cfaSubmission;
        abort_unless($submission, 404);

        $attachment->loadMissing('serviceCase.service.deliverable');
        $case = $attachment->serviceCase;
        abort_unless($case && (int) $case->cfa_submission_id === (int) $submission->id, 403);
        abort_unless(BmcService::isBmc($case->service), 403);
    }
}
