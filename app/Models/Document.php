<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Document extends Model
{
    public const ROLE_STATE_ADMIN = 'state_admin';
    public const ROLE_STATE_STAFF = 'state_staff';
    public const ROLE_DISTRICT_STAFF = 'district_staff';
    public const ROLE_HUB_ADMIN = 'hub_admin';
    public const ROLE_INCUBATEE = 'incubatee';

    /** @var list<string> */
    public const ALLOWED_ROLES = [
        self::ROLE_STATE_ADMIN,
        self::ROLE_STATE_STAFF,
        self::ROLE_DISTRICT_STAFF,
        self::ROLE_HUB_ADMIN,
        self::ROLE_INCUBATEE,
    ];

    protected $fillable = [
        'document_category_id',
        'title',
        'tags',
        'allowed_roles',
        'latest_version_id',
        'created_by',
        'updated_by',
    ];

    protected function casts(): array
    {
        return [
            'tags' => 'array',
            'allowed_roles' => 'array',
        ];
    }

    public function category(): BelongsTo
    {
        return $this->belongsTo(DocumentCategory::class, 'document_category_id');
    }

    public function versions(): HasMany
    {
        return $this->hasMany(DocumentVersion::class)->orderByDesc('version_no');
    }

    public function latestVersion(): BelongsTo
    {
        return $this->belongsTo(DocumentVersion::class, 'latest_version_id');
    }

    public function creator(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by');
    }

    public function updater(): BelongsTo
    {
        return $this->belongsTo(User::class, 'updated_by');
    }

    public function isVisibleToRole(string $role): bool
    {
        $allowed = $this->allowed_roles;
        if (! is_array($allowed) || $allowed === []) {
            return false;
        }

        return in_array($role, $allowed, true);
    }

    /**
     * @return list<string>
     */
    public function normalizedTags(): array
    {
        $tags = $this->tags;
        if (! is_array($tags)) {
            return [];
        }

        return array_values(array_filter(array_map(
            static fn ($v) => trim((string) $v),
            $tags
        )));
    }
}
