<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasOne;

class TrainingPackageMonthSession extends Model
{
    protected $fillable = [
        'district_id',
        'calendar_year',
        'calendar_month',
        'sort_order',
        'session_name',
        'is_extra',
        'created_by_user_id',
        'updated_by_user_id',
    ];

    protected function casts(): array
    {
        return [
            'calendar_year' => 'integer',
            'calendar_month' => 'integer',
            'sort_order' => 'integer',
            'is_extra' => 'boolean',
        ];
    }

    public function district(): BelongsTo
    {
        return $this->belongsTo(District::class);
    }

    public function trainingPackage(): HasOne
    {
        return $this->hasOne(TrainingPackage::class, 'month_session_id');
    }

    public function createdBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by_user_id');
    }

    public function updatedBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'updated_by_user_id');
    }

    public function isOccupied(): bool
    {
        return $this->relationLoaded('trainingPackage')
            ? $this->trainingPackage !== null
            : $this->trainingPackage()->exists();
    }

    public function isFilled(): bool
    {
        $package = $this->relationLoaded('trainingPackage')
            ? $this->trainingPackage
            : $this->trainingPackage()->first();

        return $package !== null && ! (bool) ($package->is_draft ?? false);
    }
}
