<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\HasOne;

class District extends Model
{
    protected $fillable = ['hub_id', 'slug', 'name', 'sort_order', 'lgd_district_code'];

    public function hub(): BelongsTo
    {
        return $this->belongsTo(Hub::class);
    }

    public function blocks(): HasMany
    {
        return $this->hasMany(DistrictBlock::class);
    }

    public function serviceSpoc(): HasOne
    {
        return $this->hasOne(DistrictServiceSpoc::class);
    }
}
