<?php

namespace App\Models;

use App\Support\LineDepartmentMeetingOptions;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class LineDepartmentMeeting extends Model
{
    public const MODULE_LABEL = '12.2 Line Department Meeting';

    /** @var array<string, string> */
    public const MEETING_LEVELS = [
        'spoke' => 'Spoke',
        'hub' => 'Hub',
        'state' => 'State',
    ];

    protected $fillable = [
        'submitted_by_user_id',
        'submitted_by_name',
        'meeting_date',
        'meeting_level',
        'hub_id',
        'hub_name',
        'district_id',
        'district_name',
        'meeting_mode',
        'venue',
        'department_name',
        'department_unit',
        'official_name',
        'official_designation',
        'official_phone',
        'muy_staff_present',
        'meeting_purpose',
        'meeting_purpose_other',
        'agenda_summary',
        'outcome_decision',
        'incubatees_discussed_json',
        'proof_media_json',
        'photos_json',
    ];

    protected function casts(): array
    {
        return [
            'meeting_date' => 'date',
            'incubatees_discussed_json' => 'array',
            'proof_media_json' => 'array',
            'photos_json' => 'array',
        ];
    }

    public function meetingLevelLabel(): string
    {
        $key = strtolower(trim((string) ($this->meeting_level ?? '')));

        return self::MEETING_LEVELS[$key] ?? ucfirst($key);
    }

    public function meetingModeLabel(): string
    {
        return LineDepartmentMeetingOptions::meetingModeLabel((string) ($this->meeting_mode ?? ''));
    }

    public function meetingPurposeLabel(): string
    {
        $key = (string) ($this->meeting_purpose ?? '');
        if ($key === 'other') {
            $other = trim((string) ($this->meeting_purpose_other ?? ''));

            return $other !== '' ? 'Other: '.$other : 'Other';
        }

        return LineDepartmentMeetingOptions::meetingPurposes()[$key] ?? ucfirst(str_replace('_', ' ', $key));
    }

    public function hasProofDocument(): bool
    {
        return count((array) $this->proof_media_json) > 0;
    }

    public function submitter(): BelongsTo
    {
        return $this->belongsTo(User::class, 'submitted_by_user_id');
    }
}
