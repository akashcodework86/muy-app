<?php

namespace App\Notifications;

use App\Models\MentorshipRequest;
use Illuminate\Bus\Queueable;
use Illuminate\Notifications\Notification;

class MentorshipRequestedNotification extends Notification
{
    use Queueable;

    public function __construct(public MentorshipRequest $mentorshipRequest) {}

    /**
     * @return list<string>
     */
    public function via(object $notifiable): array
    {
        return ['database'];
    }

    /**
     * @return array<string, mixed>
     */
    public function toDatabase(object $notifiable): array
    {
        $this->mentorshipRequest->loadMissing(['cfaSubmission.district.hub', 'requestedBy']);

        $cat = $this->mentorshipRequest->category;
        $label = (string) (config('mentorship.categories.'.$cat.'.label') ?? $cat);

        return [
            'title' => 'Mentorship request',
            'body' => ($this->mentorshipRequest->requestedBy->name ?? 'An incubatee').' requested '.$label.' mentorship.',
            'cfa_submission_id' => $this->mentorshipRequest->cfa_submission_id,
            'mentorship_request_id' => $this->mentorshipRequest->id,
            'category' => $cat,
            'category_label' => $label,
            'comment' => $this->mentorshipRequest->comment,
            'incubatee_name' => $this->mentorshipRequest->requestedBy->name,
            'incubatee_email' => $this->mentorshipRequest->requestedBy->email,
            'application_no' => $this->mentorshipRequest->cfaSubmission->application_no,
            'district_name' => $this->mentorshipRequest->cfaSubmission->district?->name,
            'hub_name' => $this->mentorshipRequest->cfaSubmission->district?->hub?->name,
        ];
    }
}
