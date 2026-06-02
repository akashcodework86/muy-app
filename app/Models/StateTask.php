<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Support\Carbon;

class StateTask extends Model
{
    public const STATUS_DRAFT = 'draft';

    public const STATUS_PUBLISHED = 'published';

    public const STATUS_CLOSED = 'closed';

    public const STATUS_CANCELLED = 'cancelled';

    /** @var list<string> */
    public const STATUSES = [
        self::STATUS_DRAFT,
        self::STATUS_PUBLISHED,
        self::STATUS_CLOSED,
        self::STATUS_CANCELLED,
    ];

    protected $fillable = [
        'title',
        'description',
        'start_date',
        'due_date',
        'target_value',
        'status',
        'created_by',
        'published_at',
        'closed_at',
    ];

    protected function casts(): array
    {
        return [
            'start_date' => 'date',
            'due_date' => 'date',
            'target_value' => 'integer',
            'published_at' => 'datetime',
            'closed_at' => 'datetime',
        ];
    }

    public function creator(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by');
    }

    public function assignments(): HasMany
    {
        return $this->hasMany(StateTaskAssignment::class);
    }

    public function attachments(): HasMany
    {
        return $this->hasMany(StateTaskAttachment::class);
    }

    public function isEditable(): bool
    {
        return in_array($this->status, [self::STATUS_DRAFT, self::STATUS_PUBLISHED], true);
    }

    public function isOverdue(): bool
    {
        if ($this->due_date === null || $this->status !== self::STATUS_PUBLISHED) {
            return false;
        }

        return Carbon::parse($this->due_date)->endOfDay()->isPast();
    }

    public function progressPercentForAssignment(StateTaskAssignment $assignment): ?int
    {
        if ($this->target_value === null || $this->target_value <= 0) {
            return null;
        }

        return (int) min(100, round(($assignment->progress_value / $this->target_value) * 100));
    }
}
