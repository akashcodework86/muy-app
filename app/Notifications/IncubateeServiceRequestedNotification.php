<?php

namespace App\Notifications;

use App\Models\IncubateeServiceRequest;
use App\Support\IncubateeServiceCatalog;
use Illuminate\Bus\Queueable;
use Illuminate\Notifications\Notification;

class IncubateeServiceRequestedNotification extends Notification
{
    use Queueable;

    public function __construct(public IncubateeServiceRequest $serviceRequest) {}

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
        $this->serviceRequest->loadMissing(['cfaSubmission.district.hub', 'requestedBy', 'service']);
        $label = $this->serviceRequest->service
            ? IncubateeServiceCatalog::serviceLabel($this->serviceRequest->service)
            : 'Service';

        return [
            'title' => 'Service request',
            'body' => ($this->serviceRequest->requestedBy->name ?? 'An incubatee').' requested '.$label.'.',
            'cfa_submission_id' => $this->serviceRequest->cfa_submission_id,
            'incubatee_service_request_id' => $this->serviceRequest->id,
            'service_id' => $this->serviceRequest->service_id,
            'service_label' => $label,
            'comment' => $this->serviceRequest->comment,
            'incubatee_name' => $this->serviceRequest->requestedBy->name,
            'incubatee_email' => $this->serviceRequest->requestedBy->email,
            'application_no' => $this->serviceRequest->cfaSubmission->application_no,
            'district_name' => $this->serviceRequest->cfaSubmission->district?->name,
            'hub_name' => $this->serviceRequest->cfaSubmission->district?->hub?->name,
        ];
    }
}
