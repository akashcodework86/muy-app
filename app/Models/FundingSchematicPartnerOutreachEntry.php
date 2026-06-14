<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class FundingSchematicPartnerOutreachEntry extends Model
{
    public const MODULE_LABEL = '8.5 No of Partners outreach (Funding & Schematic Convergence)';

    protected $fillable = [
        'batch_id',
        'outreach_date',
        'outreach_mode',
        'partner_name',
        'partner_type',
        'partner_type_other',
        'contact_name',
        'designation',
        'poc_phone',
        'partner_link',
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
