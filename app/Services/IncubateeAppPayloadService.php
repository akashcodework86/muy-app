<?php

namespace App\Services;

use App\Models\CfaSubmission;
use App\Models\MentorshipRequest;
use App\Models\ServiceCase;
use App\Models\User;
use App\Support\PartnerOutreachOptions;
use DateTimeInterface;
use Illuminate\Http\Exceptions\HttpResponseException;

class IncubateeAppPayloadService
{
    /**
     * @return array<string, mixed>
     */
    public function dashboard(User $user): array
    {
        $user->load([
            'cfaSubmission.district',
            'cfaSubmission.onboardingBatchMembership.batch.hub',
            'cfaSubmission.serviceCases.service',
        ]);

        $submission = $this->requireSubmission($user);
        $cases = $submission->serviceCases;
        $approvedCases = $cases->where('status', ServiceCase::STATUS_APPROVED);
        $open = $cases->whereIn('status', [
            ServiceCase::STATUS_DRAFT,
            ServiceCase::STATUS_PENDING_APPROVAL,
            ServiceCase::STATUS_SENT_BACK,
        ])->count();

        $payload = is_array($submission->payload) ? $submission->payload : [];
        $batch = $submission->onboardingBatchMembership?->batch;
        $hubName = $batch?->hub?->name;
        $membership = $submission->onboardingBatchMembership;

        $emailFromPayload = $payload['email'] ?? null;
        $displayEmail = (is_scalar($emailFromPayload) && trim((string) $emailFromPayload) !== '')
            ? (string) $emailFromPayload
            : ($user->email ?? '—');

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
                'status' => 'done',
                'at' => $this->iso($submission->created_at),
                'detail' => $submission->application_no
                    ? 'Application '.$submission->application_no.' filed.'
                    : 'Your CFA application was filed.',
            ],
            [
                'key' => 'onboarded',
                'status' => $batch ? 'done' : 'upcoming',
                'at' => $this->iso($batch?->onboarding_date ?? $membership?->created_at),
                'detail' => $batch?->name
                    ? 'Joined '.$batch->name.($hubName ? ' · '.$hubName : '').'.'
                    : 'Waiting to be onboarded into a hub batch.',
            ],
            [
                'key' => 'mentorship',
                'status' => $firstMentorshipAt ? 'done' : 'current',
                'at' => $this->iso($firstMentorshipAt),
                'detail' => $firstMentorshipAt
                    ? $mentorshipCount.' mentorship '.($mentorshipCount === 1 ? 'request' : 'requests').' sent so far.'
                    : 'Ask your hub for help whenever you need guidance.',
            ],
            [
                'key' => 'first-service',
                'status' => $firstCompletedCase ? 'done' : 'upcoming',
                'at' => $this->iso($firstCompletedCase?->delivered_on ?? $firstCompletedCase?->approved_at),
                'detail' => $firstCompletedCase
                    ? ($firstCompletedCase->service?->name ?? 'A service').' delivered by your hub team.'
                    : 'Your hub will log your first supported service here.',
            ],
            [
                'key' => 'milestones',
                'status' => 'upcoming',
                'at' => null,
                'detail' => 'Product launches, revenue wins and pitch milestones — coming soon.',
            ],
        ];

        return [
            'user' => $this->userSummary($user),
            'profile' => [
                'applicant_name' => $submission->applicant_name ?: '—',
                'phone' => $submission->phone ?: ($user->phone ?: '—'),
                'email' => $displayEmail,
                'business_stage' => $this->scalar($payload['form_stage'] ?? null),
                'product' => $this->scalar($payload['product'] ?? ($payload['business_category'] ?? null)),
                'application_no' => $submission->application_no ?: '—',
                'district' => $submission->district?->name,
                'batch' => $batch?->name,
                'hub' => $hubName,
                'business' => $this->businessLabel($payload),
            ],
            'stats' => [
                'services_received' => $approvedCases->count(),
                'in_progress' => $open,
                'total_tracked' => $cases->count(),
                'mentorship_count' => $mentorshipCount,
            ],
            'journey' => $journey,
            'services' => $approvedCases->values()->map(fn (ServiceCase $case) => [
                'id' => $case->id,
                'name' => $case->service?->name ?? '—',
                'status' => $case->status,
                'status_label' => $case->status === ServiceCase::STATUS_APPROVED ? 'Delivered' : str_replace('_', ' ', (string) $case->status),
                'reference' => $case->reference_number ?: '—',
            ])->all(),
            'mentorship_requests' => $mentorshipRequests->map(fn (MentorshipRequest $mr) => $this->mentorshipRequest($mr))->all(),
        ];
    }

    /**
     * @return array<string, mixed>
     */
    public function mentorshipPage(User $user): array
    {
        $submission = $this->requireSubmission($user);
        $requests = MentorshipRequest::query()
            ->where('cfa_submission_id', $submission->id)
            ->with('session')
            ->latest('id')
            ->limit(50)
            ->get();

        $categories = [];
        foreach (config('mentorship.categories', []) as $slug => $meta) {
            $categories[] = [
                'slug' => $slug,
                'label' => $meta['label'] ?? $slug,
                'label_hi' => $meta['label_hi'] ?? ($meta['label'] ?? $slug),
                'hint' => $meta['hint'] ?? '',
                'hint_hi' => $meta['hint_hi'] ?? ($meta['hint'] ?? ''),
            ];
        }

        return [
            'user' => $this->userSummary($user),
            'application_no' => $submission->application_no,
            'district' => $submission->district?->name,
            'categories' => $categories,
            'requests' => $requests->map(fn (MentorshipRequest $mr) => $this->mentorshipRequest($mr))->all(),
        ];
    }

    /**
     * @return array<string, mixed>
     */
    public function mentorshipRequest(MentorshipRequest $mr): array
    {
        $meta = config('mentorship.categories.'.$mr->category, []);

        [$statusLabel, $statusLabelHi] = $this->mentorshipStatusLabels((string) $mr->status);
        $session = $mr->session;

        return [
            'id' => $mr->id,
            'category' => $mr->category,
            'category_label' => $meta['label'] ?? $mr->category,
            'category_label_hi' => $meta['label_hi'] ?? ($meta['label'] ?? $mr->category),
            'category_hint' => $meta['hint'] ?? '',
            'category_hint_hi' => $meta['hint_hi'] ?? ($meta['hint'] ?? ''),
            'comment' => $mr->comment,
            'status' => $mr->status,
            'status_label' => $statusLabel,
            'status_label_hi' => $statusLabelHi,
            'created_at' => $this->iso($mr->created_at),
            'cancelled_at' => $this->iso($mr->cancelled_at),
            'done_at' => $this->iso($mr->done_at),
            'can_cancel' => $mr->incubateeCanCancel(),
            'session' => $session ? [
                'kind' => $session->kind,
                'status' => $session->status,
                'scheduled_at' => $this->iso($session->scheduled_at),
                'done_at' => $this->iso($session->done_at),
                'meeting_link' => $mr->isScheduled() ? ($session->meeting_link ?: null) : null,
            ] : null,
        ];
    }

    /**
     * @return array{0: string, 1: string}
     */
    private function mentorshipStatusLabels(string $status): array
    {
        return match ($status) {
            MentorshipRequest::STATUS_PENDING => ['Pending', 'लंबित'],
            MentorshipRequest::STATUS_SCHEDULED => ['Scheduled', 'निर्धारित'],
            MentorshipRequest::STATUS_DONE => ['Completed', 'पूर्ण'],
            MentorshipRequest::STATUS_CANCELLED => ['Cancelled', 'रद्द'],
            default => [ucfirst(str_replace('_', ' ', $status)), $status],
        };
    }

    /**
     * @return array{id:int,name:string,phone:?string,first_name:string}
     */
    public function userSummary(User $user): array
    {
        $name = trim((string) ($user->name ?? ''));
        $first = trim((string) strtok($name !== '' ? $name : 'there', ' ')) ?: 'there';

        return [
            'id' => $user->id,
            'name' => $name !== '' ? $name : 'Incubatee',
            'phone' => $user->phone,
            'first_name' => $first,
        ];
    }

    public function requireSubmission(User $user): CfaSubmission
    {
        $submission = $user->cfaSubmission;
        if ($submission === null) {
            throw new HttpResponseException(response()->json([
                'message' => 'No CFA profile is linked to this account.',
            ], 404));
        }

        return $submission;
    }

    /**
     * Sector / cohort from CFA payload (not product — that is a separate field).
     */
    private function businessLabel(array $payload): string
    {
        $other = trim((string) (
            $payload['business_category_other']
            ?? $payload['cohort_or_sector_other']
            ?? $payload['sector_other']
            ?? $payload['other_business_category']
            ?? ''
        ));

        $candidates = [
            $payload['business_category'] ?? null,
            $payload['app_business_category'] ?? null,
            $payload['sector'] ?? null,
            $payload['cohort_or_sector'] ?? null,
        ];

        foreach ($candidates as $raw) {
            $value = trim($this->scalar($raw, ''));
            if ($value === '' || $value === '—') {
                continue;
            }

            $normalized = strtolower($value);
            if (in_array($normalized, ['other', 'others'], true) && $other !== '') {
                return $other;
            }

            $mapped = PartnerOutreachOptions::cohortOrSectorDisplay($normalized, $other !== '' ? $other : null);
            if ($mapped !== $normalized) {
                return $mapped;
            }

            if (str_contains($value, '_')) {
                return ucwords(str_replace('_', ' ', $value));
            }

            return $value;
        }

        return $other !== '' ? $other : '—';
    }

    private function scalar(mixed $value, string $fallback = '—'): string
    {
        if ($value === null || $value === '') {
            return $fallback;
        }
        if (is_scalar($value)) {
            return (string) $value;
        }

        return (string) json_encode($value, JSON_UNESCAPED_UNICODE);
    }

    private function iso(mixed $value): ?string
    {
        if ($value === null || $value === '') {
            return null;
        }
        if ($value instanceof DateTimeInterface) {
            return $value->format(DateTimeInterface::ATOM);
        }

        return (string) $value;
    }
}
