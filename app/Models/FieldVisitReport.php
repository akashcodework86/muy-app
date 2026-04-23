<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Facades\DB;

class FieldVisitReport extends Model
{
    protected $fillable = [
        'user_id',
        'district_id',
        'block_id',
        'visit_date',
        'area',
        'total_villages',
        'village_names',
        'total_participants',
        'outreach_programmes',
        'cfas_reported',
        'cfas_verified',
        'has_discrepancy',
        'attachment_path',
        'attachment_original_name',
    ];

    protected function casts(): array
    {
        return [
            'visit_date'       => 'date',
            'has_discrepancy'  => 'boolean',
        ];
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function district(): BelongsTo
    {
        return $this->belongsTo(District::class);
    }

    public function block(): BelongsTo
    {
        return $this->belongsTo(DistrictBlock::class, 'block_id');
    }

    /**
     * Count CFAs in cfa_submissions linked to this coordinator on the visit date
     * and update cfas_verified + has_discrepancy.
     */
    public function recalculateVerified(): void
    {
        $count = DB::table('cfa_submissions')
            ->where('referral_user_id', $this->user_id)
            ->whereDate('created_at', $this->visit_date)
            ->count();

        $this->cfas_verified   = $count;
        $this->has_discrepancy = ($count !== (int) $this->cfas_reported);
        $this->saveQuietly();
    }
}
