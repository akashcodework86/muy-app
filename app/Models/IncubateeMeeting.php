<?php

namespace App\Models;

use App\Support\Ist;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Carbon;

class IncubateeMeeting extends Model
{
    public const STATUS_SCHEDULED = 'scheduled';

    public const STATUS_DONE = 'done';

    public const STATUS_CANCELLED = 'cancelled';

    public const MODE_ONLINE = 'online';

    public const MODE_IN_PERSON = 'in_person';

    protected $fillable = [
        'title',
        'scheduled_at',
        'duration_minutes',
        'mode',
        'meeting_link',
        'venue',
        'agenda',
        'status',
        'created_by_user_id',
        'cancelled_at',
        'cancelled_by_user_id',
        'done_at',
        'done_by_user_id',
        'proof_path',
        'proof_original_name',
    ];

    protected function casts(): array
    {
        return [
            'scheduled_at' => 'datetime',
            'cancelled_at' => 'datetime',
            'done_at' => 'datetime',
            'duration_minutes' => 'integer',
        ];
    }

    public function createdBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by_user_id');
    }

    public function cancelledBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'cancelled_by_user_id');
    }

    public function doneBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'done_by_user_id');
    }

    public function isScheduled(): bool
    {
        return $this->status === self::STATUS_SCHEDULED;
    }

    public function isDone(): bool
    {
        return $this->status === self::STATUS_DONE;
    }

    public function isCancelled(): bool
    {
        return $this->status === self::STATUS_CANCELLED;
    }

    public function isOnline(): bool
    {
        return $this->mode === self::MODE_ONLINE;
    }

    public function isUpcoming(?Carbon $now = null): bool
    {
        if (! $this->isScheduled() || $this->scheduled_at === null) {
            return false;
        }

        $now ??= Carbon::now(Ist::TZ);

        return $this->scheduled_at->gte($now);
    }

    public function datetimeLocalValue(): string
    {
        $dt = Ist::carbon($this->scheduled_at);

        return $dt ? $dt->format('Y-m-d\TH:i') : '';
    }
}
