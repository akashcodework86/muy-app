<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class MentorshipSession extends Model
{
    public const KIND_INDIVIDUAL = 'individual';

    public const KIND_BATCH = 'batch';

    public const STATUS_SCHEDULED = 'scheduled';

    public const STATUS_DONE = 'done';

    protected $fillable = [
        'district_id',
        'category',
        'kind',
        'scheduled_at',
        'meeting_link',
        'proof_path',
        'proof_original_name',
        'status',
        'created_by_user_id',
        'done_at',
        'done_by_user_id',
    ];

    protected function casts(): array
    {
        return [
            'scheduled_at' => 'datetime',
            'done_at' => 'datetime',
        ];
    }

    public function district(): BelongsTo
    {
        return $this->belongsTo(District::class);
    }

    public function createdBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by_user_id');
    }

    public function doneBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'done_by_user_id');
    }

    public function requests(): HasMany
    {
        return $this->hasMany(MentorshipRequest::class);
    }

    public function isDone(): bool
    {
        return $this->status === self::STATUS_DONE;
    }
}
