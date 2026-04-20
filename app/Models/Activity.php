<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class Activity extends Model
{
    public const UPDATED_AT = null;

    protected $fillable = [
        'type',
        'actor_user_id',
        'actor_role',
        'actor_name',
        'subject_type',
        'subject_id',
        'district_id',
        'hub_id',
        'title',
        'meta',
    ];

    protected function casts(): array
    {
        return [
            'meta' => 'array',
            'created_at' => 'datetime',
        ];
    }
}
