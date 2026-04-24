<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class FieldCoordinatorAttendanceReport extends Model
{
    protected $fillable = [
        'field_coordinator_user_id',
        'field_coordinator_name',
        'visit_date',
        'entry_date',
        'area',
        'block',
        'district_id',
        'villages_visited_total',
        'villages_covered',
        'participants_total',
        'cfas_filled_total',
        'outreach_programmes_total',
        'attachment_path',
        'attachment_original_name',
        'attachment_mime',
        'attachment_size_bytes',
    ];

    protected function casts(): array
    {
        return [
            'visit_date' => 'date',
            'entry_date' => 'date',
            'villages_covered' => 'array',
        ];
    }

    public function coordinator(): BelongsTo
    {
        return $this->belongsTo(User::class, 'field_coordinator_user_id');
    }

    public function district(): BelongsTo
    {
        return $this->belongsTo(District::class);
    }
}
