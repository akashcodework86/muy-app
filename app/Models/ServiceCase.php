<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class ServiceCase extends Model
{
    public const STATUS_DRAFT = 'draft';

    public const STATUS_PENDING_APPROVAL = 'pending_approval';

    public const STATUS_APPROVED = 'approved';

    public const STATUS_SENT_BACK = 'sent_back';

    public const STATUS_REJECTED = 'rejected';

    public const STATUS_CANCELLED = 'cancelled';

    /** @deprecated Legacy constant; DB rows were migrated to {@see STATUS_DRAFT}. */
    public const STATUS_OPEN = 'open';

    /** @deprecated Legacy constant; DB rows were migrated to {@see STATUS_APPROVED}. */
    public const STATUS_COMPLETED = 'completed';

    /** Statuses that block creating another case when allows_multiple is false. */
    public const BLOCKING_STATUSES = [
        self::STATUS_DRAFT,
        self::STATUS_PENDING_APPROVAL,
        self::STATUS_APPROVED,
        self::STATUS_SENT_BACK,
    ];

    protected $fillable = [
        'cfa_submission_id',
        'legacy_application_id',
        'service_id',
        'status',
        'payload',
        'reference_number',
        'delivered_on',
        'completed_at',
        'submitted_at',
        'submitted_by',
        'spoc_user_id',
        'approval_snapshot',
        'sent_back_note',
        'approved_at',
        'approved_by',
        'rejected_at',
        'rejected_by',
        'rejected_note',
        'udyam_registration_type',
        'sla_deadline_at',
        'created_by',
    ];

    protected function casts(): array
    {
        return [
            'payload' => 'array',
            'approval_snapshot' => 'array',
            'delivered_on' => 'date',
            'completed_at' => 'datetime',
            'submitted_at' => 'datetime',
            'approved_at' => 'datetime',
            'rejected_at' => 'datetime',
            'sla_deadline_at' => 'datetime',
        ];
    }

    public function cfaSubmission(): BelongsTo
    {
        return $this->belongsTo(CfaSubmission::class);
    }

    public function service(): BelongsTo
    {
        return $this->belongsTo(Service::class);
    }

    public function creator(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by');
    }

    public function submitter(): BelongsTo
    {
        return $this->belongsTo(User::class, 'submitted_by');
    }

    public function spoc(): BelongsTo
    {
        return $this->belongsTo(User::class, 'spoc_user_id');
    }

    public function approver(): BelongsTo
    {
        return $this->belongsTo(User::class, 'approved_by');
    }

    public function rejector(): BelongsTo
    {
        return $this->belongsTo(User::class, 'rejected_by');
    }

    public function events(): HasMany
    {
        return $this->hasMany(ServiceCaseEvent::class)->orderBy('id');
    }

    public function attachments(): HasMany
    {
        return $this->hasMany(ServiceCaseAttachment::class);
    }

    public function isApproved(): bool
    {
        return $this->status === self::STATUS_APPROVED;
    }

    /**
     * Backward-compatible alias — "completed" in the old schema is now "approved".
     */
    public function isCompleted(): bool
    {
        return $this->isApproved();
    }

    public function canBeDeletedByStaff(): bool
    {
        return in_array($this->status, [
            self::STATUS_DRAFT,
            self::STATUS_PENDING_APPROVAL,
            self::STATUS_SENT_BACK,
            self::STATUS_APPROVED,
            self::STATUS_REJECTED,
        ], true);
    }
}
