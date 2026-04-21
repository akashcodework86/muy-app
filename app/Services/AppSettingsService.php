<?php

namespace App\Services;

use App\Models\AppSetting;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * Thin wrapper over the `app_settings` key/value store.
 *
 * Reads are request-memoized (so repeated `get('foo')` calls in the same
 * request hit the DB at most once). Writes bust the cache.
 *
 * Defaults are held in code: if a key is missing in DB, the default is
 * returned — this keeps the app usable on a fresh install before the
 * settings page is ever opened.
 */
class AppSettingsService
{
    private const CACHE_KEY = 'app_settings_all_v1';

    private const CACHE_TTL_SECONDS = 3600;

    /**
     * Code-level defaults. Kept here (not in config) so tests/seeders
     * don't silently drift from production values.
     *
     * @var array<string, mixed>
     */
    private const DEFAULTS = [
        // Service module (maker-checker workflow)
        'service_module.enabled' => false,
        'service_module.eligibility' => 'onboarded_only', // 'all' | 'onboarded_only'
    ];

    public function get(string $key, mixed $fallback = null): mixed
    {
        $all = $this->all();
        if (array_key_exists($key, $all)) {
            return $all[$key];
        }

        return $fallback ?? self::DEFAULTS[$key] ?? null;
    }

    public function isEnabled(string $key): bool
    {
        return (bool) $this->get($key, false);
    }

    /**
     * @param  array<string, mixed>  $changes
     */
    public function setMany(array $changes, ?int $actorUserId = null): void
    {
        if (! Schema::hasTable('app_settings')) {
            return;
        }

        DB::transaction(function () use ($changes, $actorUserId): void {
            foreach ($changes as $key => $value) {
                AppSetting::query()->updateOrCreate(
                    ['key' => $key],
                    [
                        'value' => $value,
                        'updated_by' => $actorUserId,
                    ]
                );
            }
        });

        $this->flush();
    }

    /**
     * @return array<string, mixed>
     */
    public function all(): array
    {
        // Graceful degradation during migrations — if the table doesn't yet
        // exist, fall back entirely to defaults instead of crashing the app.
        if (! Schema::hasTable('app_settings')) {
            return self::DEFAULTS;
        }

        return Cache::remember(self::CACHE_KEY, self::CACHE_TTL_SECONDS, function (): array {
            $rows = AppSetting::query()->pluck('value', 'key')->all();

            return array_merge(self::DEFAULTS, $rows);
        });
    }

    public function flush(): void
    {
        Cache::forget(self::CACHE_KEY);
    }
}
