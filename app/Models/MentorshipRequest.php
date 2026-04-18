<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class MentorshipRequest extends Model
{
    public const STATUS_PENDING = 'pending';

    protected $fillable = [
        'cfa_submission_id',
        'requested_by_user_id',
        'category',
        'comment',
        'status',
    ];

    public function cfaSubmission(): BelongsTo
    {
        return $this->belongsTo(CfaSubmission::class);
    }

    public function requestedBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'requested_by_user_id');
    }
}
