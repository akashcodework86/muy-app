<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class StateTaskProgressLog extends Model
{
    public $timestamps = false;

    protected $fillable = [
        'state_task_assignment_id',
        'user_id',
        'action',
        'progress_value',
        'note',
        'created_at',
    ];

    protected function casts(): array
    {
        return [
            'progress_value' => 'integer',
            'created_at' => 'datetime',
        ];
    }

    public function assignment(): BelongsTo
    {
        return $this->belongsTo(StateTaskAssignment::class, 'state_task_assignment_id');
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }
}
