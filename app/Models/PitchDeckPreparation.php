<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class PitchDeckPreparation extends Model
{
    public const MODULE_LABEL = '8.3 Incubatees Pitch Deck Preparation';

    protected $fillable = [
        'cfa_submission_id',
        'legacy_application_id',
        'district_id',
        'incubatee_name',
        'application_no',
        'entered_by_user_id',
        'entered_by_name',
        'prepared_on',
        'prepared_for',
        'support_mode',
        'remarks',
        'deck_file_disk',
        'deck_file_path',
        'deck_file_name',
    ];

    protected function casts(): array
    {
        return [
            'prepared_on' => 'date',
        ];
    }

    public function formattedSupportMode(): ?string
    {
        $key = strtolower(trim((string) ($this->support_mode ?? '')));
        if ($key === '') {
            return null;
        }

        $modes = config('pitch_deck_preparations.support_modes', []);

        return is_array($modes) ? ($modes[$key] ?? ucfirst($key)) : ucfirst($key);
    }

    public function incubateeSourceLabel(): string
    {
        if ($this->cfa_submission_id) {
            return 'Phase 3 CFA';
        }

        if ($this->legacy_application_id) {
            return 'Phase 2 legacy';
        }

        return '—';
    }

    public function district(): BelongsTo
    {
        return $this->belongsTo(District::class);
    }

    public function enteredBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'entered_by_user_id');
    }

    public function cfaSubmission(): BelongsTo
    {
        return $this->belongsTo(CfaSubmission::class, 'cfa_submission_id');
    }
}
