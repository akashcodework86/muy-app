<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class BuyerSellerMeetEntry extends Model
{
    public const MODULE_LABEL = 'Buyer-Seller Meet (10.5)';

    protected $fillable = [
        'meet_date',
        'district_id',
        'fiscal_year_id',
        'title',
        'venue',
        'total_participants',
        'remarks',
        'submitted_by_user_id',
        'submitted_by_name',
    ];

    protected function casts(): array
    {
        return [
            'meet_date' => 'date',
            'total_participants' => 'integer',
        ];
    }

    public function district(): BelongsTo
    {
        return $this->belongsTo(District::class);
    }

    public function fiscalYear(): BelongsTo
    {
        return $this->belongsTo(FiscalYear::class);
    }

    public function submitter(): BelongsTo
    {
        return $this->belongsTo(User::class, 'submitted_by_user_id');
    }

    public function photos(): HasMany
    {
        return $this->hasMany(BuyerSellerMeetPhoto::class)->orderBy('sort_order');
    }

    public function attendanceFiles(): HasMany
    {
        return $this->hasMany(BuyerSellerMeetAttendanceFile::class)->orderBy('sort_order');
    }

    public function participants(): HasMany
    {
        return $this->hasMany(BuyerSellerMeetParticipant::class)->orderBy('sort_order');
    }
}
