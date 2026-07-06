<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class StaffAbsenceReason extends Model
{
    protected $fillable = [
        'user_id',
        'absence_date',
        'reason',
    ];

    protected function casts(): array
    {
        return [
            'absence_date' => 'date',
        ];
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }
}
