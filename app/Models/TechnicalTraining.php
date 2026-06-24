<?php

namespace App\Models;

use App\Models\Concerns\HasMisFieldApprovalWorkflow;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class TechnicalTraining extends Model
{
    use HasMisFieldApprovalWorkflow;

    protected $fillable = [
        'submitted_by_user_id',
        'submitted_by_name',
        'event_date',
        'district_id',
        'district_name',
        'training_batch_name',
        'session_name',
        'session_brief',
        'attendance_media_json',
        'selected_incubatee_ids',
        'selected_incubatees_snapshot',
        'status',
        'spoc_user_id',
        'submitted_at',
        'approved_at',
        'approved_by',
        'sent_back_note',
        'rejected_at',
        'rejected_by',
        'rejected_note',
    ];

    protected function casts(): array
    {
        return [
            'event_date' => 'date',
            'attendance_media_json' => 'array',
            'selected_incubatee_ids' => 'array',
            'selected_incubatees_snapshot' => 'array',
            'submitted_at' => 'datetime',
            'approved_at' => 'datetime',
            'rejected_at' => 'datetime',
        ];
    }

    public function submitter(): BelongsTo
    {
        return $this->belongsTo(User::class, 'submitted_by_user_id');
    }

    public function district(): BelongsTo
    {
        return $this->belongsTo(District::class);
    }
}
