<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Support\Facades\Schema;

class MarketLinkageSubmission extends Model
{
    public const LINKAGE_ONLINE = 'online';

    public const LINKAGE_OFFLINE = 'offline';

    public const SERVICE_LIST_LABEL = 'Market Linkage';

    protected $fillable = [
        'submitted_by_user_id',
        'submitted_by_name',
        'district_id',
        'district_name',
        'cfa_submission_id',
        'legacy_application_id',
        'incubatee_name',
        'application_no',
        'status',
        'spoc_user_id',
        'submitted_at',
        'approved_at',
        'approved_by',
        'sent_back_note',
        'rejected_at',
        'rejected_by',
        'rejected_note',
        'sla_deadline_at',
    ];

    protected function casts(): array
    {
        return [
            'submitted_at' => 'datetime',
            'approved_at' => 'datetime',
            'rejected_at' => 'datetime',
            'sla_deadline_at' => 'datetime',
        ];
    }

    public function partners(): HasMany
    {
        return $this->hasMany(MarketLinkagePartner::class)->orderBy('sort_order')->orderBy('id');
    }

    public function submitter(): BelongsTo
    {
        return $this->belongsTo(User::class, 'submitted_by_user_id');
    }

    public function district(): BelongsTo
    {
        return $this->belongsTo(District::class);
    }

    public function cfaSubmission(): BelongsTo
    {
        return $this->belongsTo(CfaSubmission::class);
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

    public static function supportsWorkflow(): bool
    {
        static $cached = null;

        if ($cached !== null) {
            return $cached;
        }

        return $cached = Schema::hasColumn((new static)->getTable(), 'status');
    }

    /**
     * @param  Builder<static>  $query
     * @return Builder<static>
     */
    public function scopeApproved(Builder $query): Builder
    {
        if (! static::supportsWorkflow()) {
            return $query;
        }

        return $query->where('status', ServiceCase::STATUS_APPROVED);
    }

    public function isApproved(): bool
    {
        if (! static::supportsWorkflow()) {
            return true;
        }

        return $this->status === ServiceCase::STATUS_APPROVED;
    }

    public function canBeEditedByStaff(): bool
    {
        if (! static::supportsWorkflow()) {
            return false;
        }

        return $this->status === ServiceCase::STATUS_SENT_BACK;
    }

    public function canBeDeletedByStaff(): bool
    {
        if (! static::supportsWorkflow()) {
            return true;
        }

        return in_array($this->status, [
            ServiceCase::STATUS_PENDING_APPROVAL,
            ServiceCase::STATUS_SENT_BACK,
            ServiceCase::STATUS_APPROVED,
            ServiceCase::STATUS_REJECTED,
        ], true);
    }

    public static function linkageModeLabel(string $mode): string
    {
        return match ($mode) {
            self::LINKAGE_ONLINE => 'Online',
            self::LINKAGE_OFFLINE => 'Offline',
            default => ucfirst($mode),
        };
    }
}
