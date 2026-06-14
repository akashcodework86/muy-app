<?php

namespace App\Models;

use App\Support\StakeholderConsultationWorkshopOptions;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class StakeholderConsultationWorkshop extends Model
{
    public const MODULE_LABEL = '12.1 Stakeholder Consultation Workshop';

    /** @var array<string, string> */
    public const ORGANIZING_LEVELS = [
        'state' => 'State',
        'hub' => 'Hub',
        'spoke' => 'Spoke',
    ];

    /** @var array<string, string> */
    public const MOU_CONVERGENCE_OPTIONS = [
        'yes' => 'Yes',
        'no' => 'No',
        'already_initiated' => 'Already initiated',
    ];

    protected $fillable = [
        'submitted_by_user_id',
        'submitted_by_name',
        'workshop_date',
        'workshop_title',
        'workshop_mode',
        'venue',
        'organizing_level',
        'hub_id',
        'hub_name',
        'district_id',
        'district_name',
        'primary_departments_json',
        'other_departments',
        'stakeholder_types_json',
        'total_participants',
        'officials_count',
        'consultation_theme',
        'key_outcomes',
        'mou_convergence_planned',
        'attendance_media_json',
        'workshop_photos_json',
        'minutes_media_json',
    ];

    protected function casts(): array
    {
        return [
            'workshop_date' => 'date',
            'primary_departments_json' => 'array',
            'stakeholder_types_json' => 'array',
            'total_participants' => 'integer',
            'officials_count' => 'integer',
            'attendance_media_json' => 'array',
            'workshop_photos_json' => 'array',
            'minutes_media_json' => 'array',
        ];
    }

    public function formattedWorkshopMode(): string
    {
        return match (strtolower(trim((string) ($this->workshop_mode ?? '')))) {
            'virtual' => 'Virtual',
            'physical' => 'Physical',
            default => 'Physical',
        };
    }

    public function organizingLevelLabel(): string
    {
        $key = strtolower(trim((string) ($this->organizing_level ?? '')));

        return self::ORGANIZING_LEVELS[$key] ?? ucfirst($key);
    }

    public function primaryDepartmentsLabel(): string
    {
        $labels = [];
        foreach ((array) $this->primary_departments_json as $key) {
            $key = (string) $key;
            $labels[] = StakeholderConsultationWorkshopOptions::lineDepartmentLabel($key);
        }

        return $labels !== [] ? implode(', ', $labels) : '—';
    }

    public function stakeholderTypesLabel(): string
    {
        $labels = [];
        foreach ((array) $this->stakeholder_types_json as $key) {
            $key = (string) $key;
            $labels[] = StakeholderConsultationWorkshopOptions::stakeholderTypeLabel($key);
        }

        return $labels !== [] ? implode(', ', $labels) : '—';
    }

    public function hasAttendanceSheet(): bool
    {
        return count((array) $this->attendance_media_json) > 0;
    }

    public function submitter(): BelongsTo
    {
        return $this->belongsTo(User::class, 'submitted_by_user_id');
    }
}
