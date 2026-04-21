<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Service extends Model
{
    public const REPORTING_UNSET = 'unset';

    public const REPORTING_KEY = 'key';

    public const REPORTING_NON_KEY = 'non_key';

    protected $fillable = [
        'service_category_id',
        'deliverable_id',
        'code',
        'name',
        'sort_order',
        'is_active',
        'allows_multiple',
        'reporting_tier',
        'requires_approval',
        'requires_document',
        'allowed_document_types',
        'field_schema',
    ];

    protected function casts(): array
    {
        return [
            'is_active' => 'boolean',
            'allows_multiple' => 'boolean',
            'requires_approval' => 'boolean',
            'requires_document' => 'boolean',
            'allowed_document_types' => 'array',
            'field_schema' => 'array',
        ];
    }

    /**
     * Returns a normalized list of allowed doc type tags (pdf/image).
     * Falls back to both when requires_document is true but nothing explicit is set.
     *
     * @return list<string>
     */
    public function effectiveAllowedDocumentTypes(): array
    {
        $types = $this->allowed_document_types;
        if (! is_array($types) || $types === []) {
            return ['pdf', 'image'];
        }

        return array_values(array_intersect(['pdf', 'image'], array_map('strval', $types)));
    }

    /**
     * For dashboards / exports: Key vs Non-Key. Unset is grouped with Non-Key for counts only;
     * the stored value stays `unset` until an admin sets it explicitly.
     */
    public function reportingBucketForMetrics(): string
    {
        return $this->reporting_tier === self::REPORTING_KEY
            ? self::REPORTING_KEY
            : self::REPORTING_NON_KEY;
    }

    public function isReportingUnset(): bool
    {
        return $this->reporting_tier === self::REPORTING_UNSET
            || $this->reporting_tier === null
            || $this->reporting_tier === '';
    }

    public function cases(): HasMany
    {
        return $this->hasMany(ServiceCase::class);
    }

    public function category(): BelongsTo
    {
        return $this->belongsTo(ServiceCategory::class, 'service_category_id');
    }

    public function deliverable(): BelongsTo
    {
        return $this->belongsTo(Deliverable::class);
    }
}
