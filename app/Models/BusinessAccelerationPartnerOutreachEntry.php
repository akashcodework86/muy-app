<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class BusinessAccelerationPartnerOutreachEntry extends Model
{
    public const MODULE_LABEL = 'Business Acceleration Services — 7.1 Partners outreach';

    protected $fillable = [
        'batch_id',
        'outreach_date',
        'outreach_mode',
        'partner_name',
        'partner_type',
        'partner_type_other',
        'poc_name',
        'poc_phone',
        'remarks',
        'submitted_by_user_id',
        'submitted_by_name',
    ];

    protected function casts(): array
    {
        return [
            'outreach_date' => 'date',
        ];
    }

    public function submitter(): BelongsTo
    {
        return $this->belongsTo(User::class, 'submitted_by_user_id');
    }
}
