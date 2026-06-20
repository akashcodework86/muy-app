<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class ProgramDeliverableRowMetadata extends Model
{
    protected $table = 'program_deliverable_row_metadata';

    protected $fillable = [
        'serial',
        'indicator_type',
        'level',
        'updated_by_user_id',
    ];

    public function updater(): BelongsTo
    {
        return $this->belongsTo(User::class, 'updated_by_user_id');
    }
}
