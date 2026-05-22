<?php

namespace App\Services;

use App\Models\DistrictServiceSpoc;
use App\Models\MarketLinkageSubmission;
use App\Models\ServiceCase;
use App\Models\User;
use App\Notifications\ServiceCaseWorkflowNotification;
use App\Support\BusinessDays;
use Illuminate\Support\Facades\Notification;
use Illuminate\Validation\ValidationException;

class MarketLinkageWorkflowService
{
    public function submitForApproval(MarketLinkageSubmission $submission, int $actorId): void
    {
        $districtId = (int) $submission->district_id;
        $spocUserId = $this->resolveSpocUserId($districtId);

        $submission->status = ServiceCase::STATUS_PENDING_APPROVAL;
        $submission->submitted_at = now();
        $submission->spoc_user_id = $spocUserId;
        $submission->sla_deadline_at = BusinessDays::add(now(), 3);
        $submission->approved_at = null;
        $submission->approved_by = null;
        $submission->sent_back_note = null;
        $submission->rejected_at = null;
        $submission->rejected_by = null;
        $submission->rejected_note = null;
        $submission->save();

        if ($spocUserId > 0) {
            $this->notifySpoc($submission, $spocUserId);
        }
    }

    public function approve(MarketLinkageSubmission $submission, User $spoc): void
    {
        if ($submission->status !== ServiceCase::STATUS_PENDING_APPROVAL) {
            throw ValidationException::withMessages(['status' => 'Only pending approval submissions can be approved.']);
        }

        $submission->status = ServiceCase::STATUS_APPROVED;
        $submission->approved_at = now();
        $submission->approved_by = (int) $spoc->id;
        $submission->sent_back_note = null;
        $submission->rejected_at = null;
        $submission->rejected_by = null;
        $submission->rejected_note = null;
        $submission->sla_deadline_at = null;
        $submission->save();

        $this->notifySubmitter($submission, $spoc, 'approved');
    }

    public function sendBack(MarketLinkageSubmission $submission, User $spoc, string $note): void
    {
        if ($submission->status !== ServiceCase::STATUS_PENDING_APPROVAL) {
            throw ValidationException::withMessages(['status' => 'Only pending approval submissions can be sent back.']);
        }

        $submission->status = ServiceCase::STATUS_SENT_BACK;
        $submission->sent_back_note = trim($note);
        $submission->approved_at = null;
        $submission->approved_by = null;
        $submission->rejected_at = null;
        $submission->rejected_by = null;
        $submission->rejected_note = null;
        $submission->save();

        $this->notifySubmitter($submission, $spoc, 'sent_back');
    }

    public function reject(MarketLinkageSubmission $submission, User $spoc, string $note): void
    {
        if ($submission->status !== ServiceCase::STATUS_PENDING_APPROVAL) {
            throw ValidationException::withMessages(['status' => 'Only pending approval submissions can be rejected.']);
        }

        $submission->status = ServiceCase::STATUS_REJECTED;
        $submission->rejected_at = now();
        $submission->rejected_by = (int) $spoc->id;
        $submission->rejected_note = trim($note);
        $submission->approved_at = null;
        $submission->approved_by = null;
        $submission->sent_back_note = null;
        $submission->save();

        $this->notifySubmitter($submission, $spoc, 'rejected');
    }

    private function resolveSpocUserId(int $districtId): ?int
    {
        if ($districtId <= 0) {
            return null;
        }

        $row = DistrictServiceSpoc::query()->where('district_id', $districtId)->first();

        return $row?->state_staff_user_id;
    }

    private function notifySpoc(MarketLinkageSubmission $submission, int $spocUserId): void
    {
        $spoc = User::query()
            ->whereKey($spocUserId)
            ->where('is_active', true)
            ->first();

        if (! $spoc) {
            return;
        }

        Notification::send($spoc, new ServiceCaseWorkflowNotification([
            'title' => 'Market linkage pending approval',
            'body' => trim($submission->incubatee_name.' market linkage is waiting for your review.'),
            'market_linkage_submission_id' => (int) $submission->id,
            'cfa_submission_id' => (int) ($submission->cfa_submission_id ?? 0),
            'status' => (string) $submission->status,
            'service_name' => 'Market Linkage',
            'application_no' => (string) ($submission->application_no ?? ''),
            'incubatee_name' => (string) $submission->incubatee_name,
            'action' => 'pending_approval',
        ]));
    }

    private function notifySubmitter(MarketLinkageSubmission $submission, User $spoc, string $action): void
    {
        $recipient = User::query()
            ->whereKey((int) $submission->submitted_by_user_id)
            ->where('is_active', true)
            ->first();

        if (! $recipient) {
            return;
        }

        $statusLabel = str_replace('_', ' ', (string) $submission->status);
        $note = match ($action) {
            'sent_back' => (string) ($submission->sent_back_note ?? ''),
            'rejected' => (string) ($submission->rejected_note ?? ''),
            default => '',
        };
        $title = match ($action) {
            'approved' => 'Market linkage approved',
            'sent_back' => 'Market linkage sent back',
            'rejected' => 'Market linkage rejected',
            default => 'Market linkage update',
        };

        $body = trim('Market linkage for '.$submission->incubatee_name.' is '.$statusLabel.' by '.$spoc->name.'.');
        if ($submission->application_no) {
            $body .= ' Application '.$submission->application_no.'.';
        }

        Notification::send($recipient, new ServiceCaseWorkflowNotification([
            'title' => $title,
            'body' => $body,
            'market_linkage_submission_id' => (int) $submission->id,
            'cfa_submission_id' => (int) ($submission->cfa_submission_id ?? 0),
            'status' => (string) $submission->status,
            'spoc_name' => (string) $spoc->name,
            'service_name' => 'Market Linkage',
            'application_no' => (string) ($submission->application_no ?? ''),
            'incubatee_name' => (string) $submission->incubatee_name,
            'comment' => $note !== '' ? $note : null,
            'action' => $action,
        ]));
    }
}
