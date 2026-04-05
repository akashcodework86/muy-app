<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Hub extends Model
{
    protected $fillable = ['slug', 'name', 'sort_order'];

    public function districts(): HasMany
    {
        return $this->hasMany(District::class);
    }
}
