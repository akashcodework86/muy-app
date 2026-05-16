<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class GramPanchayat extends Model
{
    protected $fillable = [
        'district_id',
        'district_block_id',
        'name',
        'sort_order',
    ];

    public function district(): BelongsTo
    {
        return $this->belongsTo(District::class);
    }

    public function districtBlock(): BelongsTo
    {
        return $this->belongsTo(DistrictBlock::class);
    }
}
