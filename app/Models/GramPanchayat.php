<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class GramPanchayat extends Model
{
    protected $fillable = [
        'district_id',
        'district_block_id',
        'name',
        'sort_order',
    ];

    public function district(): BelongsTo
    {
        return $this->belongsTo(District::class);
    }

    public function districtBlock(): BelongsTo
    {
        return $this->belongsTo(DistrictBlock::class);
    }

    /**
     * @return \Illuminate\Support\Collection<int, array{id: int, name: string}>
     */
    public static function listForBlock(int $blockId, string $search = ''): \Illuminate\Support\Collection
    {
        $query = static::query()
            ->where('district_block_id', $blockId)
            ->orderBy('name');

        $search = trim($search);
        if ($search !== '') {
            $like = '%'.str_replace(['\\', '%', '_'], ['\\\\', '\\%', '\\_'], $search).'%';
            $query->where('name', 'like', $like);
        }

        return $query
            ->get(['id', 'name'])
            ->map(fn (self $gp) => ['id' => $gp->id, 'name' => $gp->name])
            ->values();
    }
}
