<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class MarketLinkagePartner extends Model
{
    protected $fillable = [
        'market_linkage_submission_id',
        'partner_name',
        'linkage_mode',
        'linkage_date',
        'link_url',
        'document_disk',
        'document_path',
        'document_original_name',
        'sort_order',
    ];

    protected function casts(): array
    {
        return [
            'linkage_date' => 'date',
            'sort_order' => 'integer',
        ];
    }

    public function submission(): BelongsTo
    {
        return $this->belongsTo(MarketLinkageSubmission::class, 'market_linkage_submission_id');
    }

    public function hasDocument(): bool
    {
        return is_string($this->document_path) && $this->document_path !== '';
    }

    /**
     * Read link/URL from a partner row (form may send link_url or link).
     */
    public static function linkInputFromRow(array $row): string
    {
        $url = trim((string) ($row['link_url'] ?? ''));
        if ($url !== '') {
            return $url;
        }

        return trim((string) ($row['link'] ?? ''));
    }

    /**
     * Normalize user input: full URLs, domain-style links, or plain text references.
     */
    public static function normalizeLinkForStorage(?string $value): ?string
    {
        $value = trim((string) $value);
        if ($value === '') {
            return null;
        }

        if (filter_var($value, FILTER_VALIDATE_URL) !== false) {
            return $value;
        }

        if (preg_match('#^https?://#i', $value)) {
            return $value;
        }

        if (preg_match('~^([\w-]+\.)+[a-z]{2,}([\w./?&=%+-]*)?$~i', $value)) {
            $candidate = 'https://'.$value;
            if (filter_var($candidate, FILTER_VALIDATE_URL) !== false) {
                return $candidate;
            }
        }

        return $value;
    }

    /**
     * Href for anchor tags; null when stored value is plain text (not a web URL).
     */
    public static function clickableHref(?string $stored): ?string
    {
        $stored = trim((string) $stored);
        if ($stored === '') {
            return null;
        }

        if (filter_var($stored, FILTER_VALIDATE_URL) !== false) {
            return $stored;
        }

        if (preg_match('#^https?://#i', $stored)) {
            return $stored;
        }

        if (preg_match('~^([\w-]+\.)+[a-z]{2,}([\w./?&=%+-]*)?$~i', $stored)) {
            $candidate = 'https://'.$stored;
            if (filter_var($candidate, FILTER_VALIDATE_URL) !== false) {
                return $candidate;
            }
        }

        return null;
    }

    public function linkHref(): ?string
    {
        return self::clickableHref($this->link_url);
    }
}
