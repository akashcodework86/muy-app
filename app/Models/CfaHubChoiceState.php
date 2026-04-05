<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class CfaHubChoiceState extends Model
{
    protected $table = 'cfa_hub_choice_states';

    protected $fillable = [
        'hub_id',
        'district_id',
        'cfa_submission_id',
        'state',
        'updated_by',
    ];

    public function hub(): BelongsTo
    {
        return $this->belongsTo(Hub::class);
    }

    public function district(): BelongsTo
    {
        return $this->belongsTo(District::class);
    }

    public function cfaSubmission(): BelongsTo
    {
        return $this->belongsTo(CfaSubmission::class);
    }
}
