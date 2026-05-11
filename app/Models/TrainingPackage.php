<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class TrainingPackage extends Model
{
    protected $fillable = [
        'submitted_by_user_id',
        'submitted_by_name',
        'event_date',
        'district_id',
        'district_name',
        'month_session_id',
        'block',
        'training_batch_name',
        'training_package',
        'training_packages',
        'attendance_file_path',
        'attendance_file_name',
        'attendance_file_mime',
        'attendance_file_size_bytes',
        'attendance_media_json',
        'selected_incubatee_ids',
        'selected_incubatees_snapshot',
    ];

    protected function casts(): array
    {
        return [
            'event_date' => 'date',
            'training_packages' => 'array',
            'attendance_media_json' => 'array',
            'selected_incubatee_ids' => 'array',
            'selected_incubatees_snapshot' => 'array',
        ];
    }

    public function submitter(): BelongsTo
    {
        return $this->belongsTo(User::class, 'submitted_by_user_id');
    }

    public function district(): BelongsTo
    {
        return $this->belongsTo(District::class);
    }

    public function monthSession(): BelongsTo
    {
        return $this->belongsTo(TrainingPackageMonthSession::class, 'month_session_id');
    }

    public function getTrainingPackageAttribute(): ?string
    {
        if (array_key_exists('training_package', $this->attributes)) {
            $value = $this->attributes['training_package'];

            return $value !== null && $value !== '' ? (string) $value : null;
        }

        $modules = $this->training_packages;

        return is_array($modules) && $modules !== [] ? (string) $modules[0] : null;
    }
}
