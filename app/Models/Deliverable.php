<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Deliverable extends Model
{
    protected $fillable = ['sort_order', 'code', 'name', 'mis_entry_label', 'is_active'];

    /**
     * MIS onboarding row for targets (`code` onboarding). Avoids summing other deliverables
     * whose label/name accidentally matches broad "Onboard" patterns.
     */
    public static function onboardingTargetDeliverableId(): ?int
    {
        $id = static::query()
            ->whereRaw('LOWER(code) = ?', ['onboarding'])
            ->where('is_active', true)
            ->orderByDesc('id')
            ->value('id');

        if ($id !== null) {
            return (int) $id;
        }

        $legacyId = static::query()
            ->where('is_active', true)
            ->where('sort_order', 4)
            ->where(function ($q): void {
                $q->where('mis_entry_label', 'like', '%Onboarded Incubatees%')
                    ->orWhere('name', 'like', '%Incubatees Onboarded%');
            })
            ->orderByDesc('id')
            ->value('id');

        return $legacyId !== null ? (int) $legacyId : null;
    }

    protected function casts(): array
    {
        return [
            'is_active' => 'boolean',
        ];
    }

    public function services(): HasMany
    {
        return $this->hasMany(Service::class);
    }
}
