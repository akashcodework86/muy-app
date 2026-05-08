<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class TrainingPackageAttendance extends Model
{
    protected $fillable = [
        'event_taken_by_user_id',
        'event_taken_by_name',
        'event_date',
        'district_id',
        'district_name',
        'block',
        'training_package',
        'attendance_file_path',
        'attendance_file_name',
        'attendance_file_mime',
        'attendance_file_size',
        'selected_incubatees_json',
        'selected_incubatees_count',
        'created_by',
        'updated_by',
    ];

    protected function casts(): array
    {
        return [
            'event_date' => 'date',
            'selected_incubatees_json' => 'array',
        ];
    }

    public function eventTakenBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'event_taken_by_user_id');
    }

    public function district(): BelongsTo
    {
        return $this->belongsTo(District::class);
    }

    public function creator(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by');
    }

    public function updater(): BelongsTo
    {
        return $this->belongsTo(User::class, 'updated_by');
    }
}
