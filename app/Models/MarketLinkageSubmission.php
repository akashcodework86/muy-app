<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class MarketLinkageSubmission extends Model
{
    public const LINKAGE_ONLINE = 'online';

    public const LINKAGE_OFFLINE = 'offline';

    protected $fillable = [
        'submitted_by_user_id',
        'submitted_by_name',
        'district_id',
        'district_name',
        'cfa_submission_id',
        'legacy_application_id',
        'incubatee_name',
        'application_no',
    ];

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

    public static function linkageModeLabel(string $mode): string
    {
        return match ($mode) {
            self::LINKAGE_ONLINE => 'Online',
            self::LINKAGE_OFFLINE => 'Offline',
            default => ucfirst($mode),
        };
    }
}
