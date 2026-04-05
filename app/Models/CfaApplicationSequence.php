<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class CfaApplicationSequence extends Model
{
    protected $fillable = [
        'district_id',
        'year',
        'month',
        'block_name',
        'last_serial',
    ];

    public function district(): BelongsTo
    {
        return $this->belongsTo(District::class);
    }
}
