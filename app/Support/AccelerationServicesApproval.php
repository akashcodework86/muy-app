<?php

namespace App\Support;

use App\Models\AccelerationServiceSession;
use App\Models\AccelerationServiceSessionEvent;
use App\Models\User;
use Illuminate\Support\Facades\Schema;

/**
 * Maker–checker workflow for MUY Acceleration Services (7.2).
 *
 * District staff entry:  pending_review (state SPOC) → pending_final → approved
 * State SPOC entry:      pending_final → approved
 * Any checker can send back to the maker (sent_back); a resubmission restarts
 * the chain for that maker. Only approved sessions count towards deliverables.
 */
final class AccelerationServicesApproval
{
    public const STATUS_DRAFT = 'draft';

    public const STATUS_PENDING_REVIEW = 'pending_review';

    public const STATUS_PENDING_FINAL = 'pending_final';

    public const STATUS_APPROVED = 'approved';

    public const STATUS_SENT_BACK = 'sent_back';

    public static function workflowReady(): bool
    {
        return Schema::hasColumn('acceleration_service_sessions', 'status');
    }

    /**
     * First-level checker (state SPOC, e.g. Ankur) — same allowlist as full-form submitters.
     */
    public static function isFirstApprover(?User $user): bool
    {
        return AccelerationServicesAccess::canSubmitFullForm($user);
    }

    /**
     * Final checker (e.g. Aadil).
     */
    public static function isFinalApprover(?User $user): bool
    {
        if (! $user || $user->role !== 'state_staff') {
            return false;
        }

        $email = strtolower(trim((string) $user->email));

        return $email !== '' && $email === (string) config('acceleration_services.final_approver_email', '');
    }

    public static function isApprover(?User $user): bool
    {
        return self::isFirstApprover($user) || self::isFinalApprover($user);
    }

    /**
     * Status a freshly submitted (non-draft) entry starts in, based on the maker.
     */
    public static function initialStatusFor(User $maker): string
    {
        return AccelerationServicesAccess::canSubmitFullForm($maker)
            ? self::STATUS_PENDING_FINAL
            : self::STATUS_PENDING_REVIEW;
    }

    /**
     * Statuses the given user is responsible for approving.
     *
     * @return list<string>
     */
    public static function pendingStatusesFor(?User $user): array
    {
        $statuses = [];
        if (self::isFirstApprover($user)) {
            $statuses[] = self::STATUS_PENDING_REVIEW;
        }
        if (self::isFinalApprover($user)) {
            $statuses[] = self::STATUS_PENDING_FINAL;
        }

        return $statuses;
    }

    public static function canApprove(?User $user, AccelerationServiceSession $session): bool
    {
        if (! $user || ! self::workflowReady()) {
            return false;
        }

        // Makers must never approve their own submission.
        if ((int) $session->submitted_by_user_id === (int) $user->id) {
            return false;
        }

        return in_array((string) $session->status, self::pendingStatusesFor($user), true);
    }

    public static function canSendBack(?User $user, AccelerationServiceSession $session): bool
    {
        return self::canApprove($user, $session);
    }

    public static function statusLabel(?string $status): string
    {
        return match ((string) $status) {
            self::STATUS_DRAFT => 'Draft',
            self::STATUS_PENDING_REVIEW => 'Pending state review',
            self::STATUS_PENDING_FINAL => 'Pending final approval',
            self::STATUS_APPROVED => 'Approved',
            self::STATUS_SENT_BACK => 'Sent back',
            default => 'Approved',
        };
    }

    public static function actionLabel(string $action): string
    {
        return match ($action) {
            'submitted' => 'Submitted for approval',
            'updated' => 'Entry updated',
            'resubmitted' => 'Resubmitted after send-back',
            'first_approved' => 'Reviewed & forwarded (state review)',
            'final_approved' => 'Final approval',
            'sent_back' => 'Sent back to maker',
            default => ucfirst(str_replace('_', ' ', $action)),
        };
    }

    /**
     * @param  array<string, mixed>  $meta
     */
    public static function log(
        AccelerationServiceSession $session,
        ?User $actor,
        string $action,
        ?string $remarks = null,
        array $meta = [],
    ): void {
        if (! Schema::hasTable('acceleration_service_session_events')) {
            return;
        }

        AccelerationServiceSessionEvent::query()->create([
            'session_id' => (int) $session->id,
            'actor_user_id' => $actor?->id,
            'actor_name' => (string) ($actor?->name ?? 'System'),
            'actor_role' => (string) ($actor?->role ?? ''),
            'action' => $action,
            'remarks' => $remarks !== null && trim($remarks) !== '' ? trim($remarks) : null,
            'meta' => $meta !== [] ? $meta : null,
        ]);
    }
}
