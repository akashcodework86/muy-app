<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class IncubateeServiceRequest extends Model
{
    public const STATUS_PENDING = 'pending';

    public const STATUS_IN_PROGRESS = 'in_progress';

    public const STATUS_DONE = 'done';

    public const STATUS_CANCELLED = 'cancelled';

    protected $fillable = [
        'cfa_submission_id',
        'requested_by_user_id',
        'service_id',
        'comment',
        'status',
        'staff_note',
        'handled_by_user_id',
        'handled_at',
        'cancelled_at',
        'cancelled_by_user_id',
    ];

    protected function casts(): array
    {
        return [
            'handled_at' => 'datetime',
            'cancelled_at' => 'datetime',
        ];
    }

    public function cfaSubmission(): BelongsTo
    {
        return $this->belongsTo(CfaSubmission::class);
    }

    public function requestedBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'requested_by_user_id');
    }

    public function service(): BelongsTo
    {
        return $this->belongsTo(Service::class);
    }

    public function handledBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'handled_by_user_id');
    }

    public function cancelledBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'cancelled_by_user_id');
    }

    public function isPending(): bool
    {
        return $this->status === self::STATUS_PENDING;
    }

    public function isInProgress(): bool
    {
        return $this->status === self::STATUS_IN_PROGRESS;
    }

    public function isDone(): bool
    {
        return $this->status === self::STATUS_DONE;
    }

    public function isCancelled(): bool
    {
        return $this->status === self::STATUS_CANCELLED;
    }

    public function incubateeCanCancel(): bool
    {
        return $this->isPending() || $this->isInProgress();
    }
}
