<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class DistrictBlock extends Model
{
    protected $fillable = [
        'district_id',
        'name',
        'lgd_block_code',
        'sort_order',
    ];

    public function district(): BelongsTo
    {
        return $this->belongsTo(District::class);
    }

    /**
     * Ordered block names for CFA dropdown + encoding (LGD order when codes exist).
     *
     * @return list<string>
     */
    public static function orderedNamesForDistrict(int $districtId): array
    {
        return static::query()
            ->where('district_id', $districtId)
            ->orderByRaw('(lgd_block_code IS NULL OR lgd_block_code = ?) ASC', [''])
            ->orderBy('lgd_block_code')
            ->orderBy('sort_order')
            ->orderBy('name')
            ->pluck('name')
            ->all();
    }
}
