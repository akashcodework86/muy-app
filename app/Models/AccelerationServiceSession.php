<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class AccelerationServiceSession extends Model
{
    public const MODULE_LABEL = 'MUY Acceleration Services — 7.2 Initiation';

    protected $fillable = [
        'service_date',
        'fiscal_year_id',
        'legacy_phase1_application_id',
        'incubatee_key',
        'incubatee_source',
        'applicant_name',
        'application_no',
        'phone',
        'district_name',
        'onboard_label',
        'counts_for_7_2',
        'submitted_by_user_id',
        'submitted_by_name',
    ];

    protected function casts(): array
    {
        return [
            'service_date' => 'date',
            'counts_for_7_2' => 'boolean',
        ];
    }

    public function submitter(): BelongsTo
    {
        return $this->belongsTo(User::class, 'submitted_by_user_id');
    }

    public function fiscalYear(): BelongsTo
    {
        return $this->belongsTo(FiscalYear::class, 'fiscal_year_id');
    }

    public function items(): HasMany
    {
        return $this->hasMany(AccelerationServiceItem::class, 'session_id');
    }
}
