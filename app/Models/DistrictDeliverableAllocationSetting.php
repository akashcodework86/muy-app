<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class DistrictDeliverableAllocationSetting extends Model
{
    protected $fillable = [
        'fiscal_year_id',
        'district_id',
        'deliverable_id',
        'designation_percents',
    ];

    protected function casts(): array
    {
        return [
            'designation_percents' => 'array',
        ];
    }

    public function fiscalYear(): BelongsTo
    {
        return $this->belongsTo(FiscalYear::class);
    }

    public function district(): BelongsTo
    {
        return $this->belongsTo(District::class);
    }

    public function deliverable(): BelongsTo
    {
        return $this->belongsTo(Deliverable::class);
    }
}
