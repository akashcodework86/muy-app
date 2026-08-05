<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class CaseStudyShortlistNomination extends Model
{
    public const STATUS_NOMINATED = 'nominated';
    public const STATUS_IN_PROGRESS = 'in_progress';
    public const STATUS_COMPLETED = 'completed';
    public const STATUS_CANCELLED = 'cancelled';

    protected $fillable = [
        'case_study_shortlist_id', 'service_code', 'status', 'nomination_note',
        'nominated_by_user_id', 'nominated_at', 'cancelled_by_user_id', 'cancelled_at',
    ];

    protected function casts(): array
    {
        return ['nominated_at' => 'datetime', 'cancelled_at' => 'datetime'];
    }

    public function shortlist(): BelongsTo
    {
        return $this->belongsTo(CaseStudyShortlist::class, 'case_study_shortlist_id');
    }

    public function nominatedBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'nominated_by_user_id');
    }

    public function events(): HasMany
    {
        return $this->hasMany(CaseStudyShortlistNominationEvent::class)->oldest();
    }

    public function isActive(): bool
    {
        return $this->status !== self::STATUS_CANCELLED;
    }
}
