<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class MarketLinkagePartnerName extends Model
{
    protected $fillable = [
        'name',
        'normalized_key',
        'created_by_user_id',
    ];
}
