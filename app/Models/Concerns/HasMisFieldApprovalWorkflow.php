<?php

namespace App\Models\Concerns;

use App\Models\ServiceCase;
use App\Models\User;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Facades\Schema;

trait HasMisFieldApprovalWorkflow
{
    public static function supportsMisFieldWorkflow(): bool
    {
        static $cache = [];

        $table = (new static)->getTable();
        if (array_key_exists($table, $cache)) {
            return $cache[$table];
        }

        return $cache[$table] = Schema::hasColumn($table, 'status');
    }

    /**
     * @param  Builder<static>  $query
     * @return Builder<static>
     */
    public function scopeApprovedForMis(Builder $query): Builder
    {
        if (! static::supportsMisFieldWorkflow()) {
            return $query;
        }

        return $query->where($this->getTable().'.status', ServiceCase::STATUS_APPROVED);
    }

    public function misFieldStatus(): string
    {
        if (! static::supportsMisFieldWorkflow()) {
            return ServiceCase::STATUS_APPROVED;
        }

        return (string) ($this->status ?? ServiceCase::STATUS_APPROVED);
    }

    public function isMisFieldApproved(): bool
    {
        return $this->misFieldStatus() === ServiceCase::STATUS_APPROVED;
    }

    public function canBeEditedByMisFieldSubmitter(): bool
    {
        if (! static::supportsMisFieldWorkflow()) {
            return false;
        }

        return in_array($this->misFieldStatus(), [
            ServiceCase::STATUS_SENT_BACK,
            ServiceCase::STATUS_REJECTED,
        ], true);
    }

    public function canBeWithdrawnByMisFieldSubmitter(): bool
    {
        if (! static::supportsMisFieldWorkflow()) {
            return false;
        }

        return $this->misFieldStatus() === ServiceCase::STATUS_PENDING_APPROVAL;
    }

    public function misFieldStatusLabel(): string
    {
        return match ($this->misFieldStatus()) {
            ServiceCase::STATUS_PENDING_APPROVAL => 'Pending approval',
            ServiceCase::STATUS_APPROVED => 'Approved',
            ServiceCase::STATUS_SENT_BACK => 'Sent back',
            ServiceCase::STATUS_REJECTED => 'Rejected',
            default => ucfirst(str_replace('_', ' ', $this->misFieldStatus())),
        };
    }

    public function misFieldSpoc(): BelongsTo
    {
        return $this->belongsTo(User::class, 'spoc_user_id');
    }

    public function misFieldApprover(): BelongsTo
    {
        return $this->belongsTo(User::class, 'approved_by');
    }

    public function misFieldRejector(): BelongsTo
    {
        return $this->belongsTo(User::class, 'rejected_by');
    }
}
