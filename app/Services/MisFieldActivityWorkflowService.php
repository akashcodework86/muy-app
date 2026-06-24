<?php

namespace App\Services;

use App\Models\ServiceCase;
use App\Models\User;
use App\Notifications\ServiceCaseWorkflowNotification;
use App\Support\MisFieldActivityApproval;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\Notification;
use Illuminate\Validation\ValidationException;

class MisFieldActivityWorkflowService
{
    public function submitForApproval(Model $record, int $actorId): void
    {
        $this->assertWorkflowEnabled($record);

        $approverId = MisFieldActivityApproval::resolveApproverUserId();

        $record->submitted_at = now();
        $record->spoc_user_id = $approverId;
        $record->sent_back_note = null;
        $record->rejected_at = null;
        $record->rejected_by = null;
        $record->rejected_note = null;

        if ($approverId > 0 && $approverId === $actorId) {
            $record->status = ServiceCase::STATUS_APPROVED;
            $record->approved_at = now();
            $record->approved_by = $actorId;
            $record->save();

            return;
        }

        $record->status = ServiceCase::STATUS_PENDING_APPROVAL;
        $record->approved_at = null;
        $record->approved_by = null;
        $record->save();

        if ($approverId > 0) {
            $this->notifyApprover($record, $approverId);
        }
    }

    public function resubmitForApproval(Model $record, int $actorId): void
    {
        $this->assertWorkflowEnabled($record);
        abort_unless($record->canBeEditedByMisFieldSubmitter(), 422, 'This entry cannot be resubmitted in its current status.');

        $this->submitForApproval($record, $actorId);
    }

    public function approve(Model $record, User $approver): void
    {
        $this->assertWorkflowEnabled($record);
        $this->assertDedicatedApprover($approver);
        $this->assertCanReview($record, $approver);

        if ($record->status !== ServiceCase::STATUS_PENDING_APPROVAL) {
            throw ValidationException::withMessages(['status' => 'Only pending entries can be approved.']);
        }

        $record->status = ServiceCase::STATUS_APPROVED;
        $record->approved_at = now();
        $record->approved_by = (int) $approver->id;
        $record->sent_back_note = null;
        $record->rejected_at = null;
        $record->rejected_by = null;
        $record->rejected_note = null;
        $record->save();

        $this->notifySubmitter($record, $approver, 'approved');
    }

    public function sendBack(Model $record, User $approver, string $note): void
    {
        $this->assertWorkflowEnabled($record);
        $this->assertDedicatedApprover($approver);
        $this->assertCanReview($record, $approver);

        if ($record->status !== ServiceCase::STATUS_PENDING_APPROVAL) {
            throw ValidationException::withMessages(['status' => 'Only pending entries can be sent back.']);
        }

        $record->status = ServiceCase::STATUS_SENT_BACK;
        $record->sent_back_note = trim($note);
        $record->approved_at = null;
        $record->approved_by = null;
        $record->rejected_at = null;
        $record->rejected_by = null;
        $record->rejected_note = null;
        $record->save();

        $this->notifySubmitter($record, $approver, 'sent_back');
    }

    public function reject(Model $record, User $approver, string $note): void
    {
        $this->assertWorkflowEnabled($record);
        $this->assertDedicatedApprover($approver);
        $this->assertCanReview($record, $approver);

        if ($record->status !== ServiceCase::STATUS_PENDING_APPROVAL) {
            throw ValidationException::withMessages(['status' => 'Only pending entries can be rejected.']);
        }

        $record->status = ServiceCase::STATUS_REJECTED;
        $record->rejected_at = now();
        $record->rejected_by = (int) $approver->id;
        $record->rejected_note = trim($note);
        $record->approved_at = null;
        $record->approved_by = null;
        $record->sent_back_note = null;
        $record->save();

        $this->notifySubmitter($record, $approver, 'rejected');
    }

    private function assertWorkflowEnabled(Model $record): void
    {
        if (! method_exists($record, 'supportsMisFieldWorkflow') || ! $record::supportsMisFieldWorkflow()) {
            throw ValidationException::withMessages(['workflow' => 'Approval workflow is not enabled for this entry.']);
        }
    }

    private function assertDedicatedApprover(User $approver): void
    {
        abort_unless(MisFieldActivityApproval::isDedicatedApprover($approver), 403);
    }

    private function assertCanReview(Model $record, User $approver): void
    {
        abort_if((int) $record->submitted_by_user_id === (int) $approver->id, 422, 'You cannot review your own submission.');
    }

    private function notifyApprover(Model $record, int $approverUserId): void
    {
        $approver = User::query()->whereKey($approverUserId)->where('is_active', true)->first();
        if (! $approver) {
            return;
        }

        $moduleKey = MisFieldActivityApproval::moduleKeyForModel($record);
        $label = $moduleKey ? (string) (MisFieldActivityApproval::module($moduleKey)['label'] ?? 'Field MIS entry') : 'Field MIS entry';

        Notification::send($approver, new ServiceCaseWorkflowNotification([
            'title' => 'Field MIS entry pending approval',
            'body' => $label.' is waiting for your review.',
            'mis_field_module' => $moduleKey,
            'mis_field_record_id' => (int) $record->getKey(),
            'status' => ServiceCase::STATUS_PENDING_APPROVAL,
            'service_name' => $label,
            'action' => 'pending_approval',
        ]));
    }

    private function notifySubmitter(Model $record, User $approver, string $action): void
    {
        $submitterId = (int) ($record->submitted_by_user_id ?? 0);
        if ($submitterId <= 0) {
            return;
        }

        $recipient = User::query()->whereKey($submitterId)->where('is_active', true)->first();
        if (! $recipient) {
            return;
        }

        $moduleKey = MisFieldActivityApproval::moduleKeyForModel($record);
        $label = $moduleKey ? (string) (MisFieldActivityApproval::module($moduleKey)['label'] ?? 'Field MIS entry') : 'Field MIS entry';
        $statusLabel = str_replace('_', ' ', (string) $record->status);

        $title = match ($action) {
            'approved' => 'Field MIS entry approved',
            'sent_back' => 'Field MIS entry sent back',
            'rejected' => 'Field MIS entry rejected',
            default => 'Field MIS entry update',
        };

        $body = trim($label.' is '.$statusLabel.' by '.$approver->name.'.');
        $note = match ($action) {
            'sent_back' => (string) ($record->sent_back_note ?? ''),
            'rejected' => (string) ($record->rejected_note ?? ''),
            default => '',
        };

        Notification::send($recipient, new ServiceCaseWorkflowNotification([
            'title' => $title,
            'body' => $body,
            'mis_field_module' => $moduleKey,
            'mis_field_record_id' => (int) $record->getKey(),
            'status' => (string) $record->status,
            'spoc_name' => (string) $approver->name,
            'service_name' => $label,
            'comment' => $note !== '' ? $note : null,
            'action' => $action,
        ]));
    }
}
