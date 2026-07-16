<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class AccelerationServiceSessionEvent extends Model
{
    protected $fillable = [
        'session_id',
        'actor_user_id',
        'actor_name',
        'actor_role',
        'action',
        'remarks',
        'meta',
    ];

    protected function casts(): array
    {
        return [
            'meta' => 'array',
        ];
    }

    public function session(): BelongsTo
    {
        return $this->belongsTo(AccelerationServiceSession::class, 'session_id');
    }

    public function actor(): BelongsTo
    {
        return $this->belongsTo(User::class, 'actor_user_id');
    }
}
