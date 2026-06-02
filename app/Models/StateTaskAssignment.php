<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class StateTaskAssignment extends Model
{
    public const STATUS_PENDING = 'pending';

    public const STATUS_IN_PROGRESS = 'in_progress';

    public const STATUS_SUBMITTED = 'submitted';

    public const STATUS_COMPLETED = 'completed';

    public const STATUS_SENT_BACK = 'sent_back';

    /** @var list<string> */
    public const STATUSES = [
        self::STATUS_PENDING,
        self::STATUS_IN_PROGRESS,
        self::STATUS_SUBMITTED,
        self::STATUS_COMPLETED,
        self::STATUS_SENT_BACK,
    ];

    protected $fillable = [
        'state_task_id',
        'assignee_user_id',
        'status',
        'progress_value',
        'staff_note',
        'admin_note',
        'submitted_at',
        'completed_at',
        'completed_by',
    ];

    protected function casts(): array
    {
        return [
            'progress_value' => 'integer',
            'submitted_at' => 'datetime',
            'completed_at' => 'datetime',
        ];
    }

    public function task(): BelongsTo
    {
        return $this->belongsTo(StateTask::class, 'state_task_id');
    }

    public function assignee(): BelongsTo
    {
        return $this->belongsTo(User::class, 'assignee_user_id');
    }

    public function completedByUser(): BelongsTo
    {
        return $this->belongsTo(User::class, 'completed_by');
    }

    public function progressLogs(): HasMany
    {
        return $this->hasMany(StateTaskProgressLog::class)->orderByDesc('id');
    }

    public function isOverdue(): bool
    {
        if ($this->status === self::STATUS_COMPLETED) {
            return false;
        }

        $task = $this->relationLoaded('task') ? $this->task : $this->task()->first();

        return $task?->isOverdue() ?? false;
    }

    public function statusLabel(): string
    {
        return match ($this->status) {
            self::STATUS_PENDING => 'Pending',
            self::STATUS_IN_PROGRESS => 'In progress',
            self::STATUS_SUBMITTED => 'Submitted',
            self::STATUS_COMPLETED => 'Completed',
            self::STATUS_SENT_BACK => 'Sent back',
            default => ucfirst(str_replace('_', ' ', $this->status)),
        };
    }
}
