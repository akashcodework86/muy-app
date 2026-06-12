<?php

namespace App\Support;

use App\Models\Service;
use App\Models\ServiceCategory;
use Illuminate\Support\Facades\Schema;

final class ConvergenceReapSupport
{
    public const PAYLOAD_KEY = 'through_reap';

    /** @var list<string> */
    public const CONVERGENCE_CATEGORY_SLUGS = [
        'convergence-with-line-departments',
        'convergence',
        'convergence_services',
    ];

    public static function categoryIsConvergence(?ServiceCategory $category): bool
    {
        if ($category === null) {
            return false;
        }

        return in_array((string) $category->slug, self::CONVERGENCE_CATEGORY_SLUGS, true);
    }

    public static function serviceIsConvergence(?Service $service): bool
    {
        if ($service === null) {
            return false;
        }

        $service->loadMissing('category');

        return self::categoryIsConvergence($service->category);
    }

    public static function payloadValueIsThroughReap(mixed $value): bool
    {
        if (is_bool($value)) {
            return $value;
        }
        if (is_int($value) || is_float($value)) {
            return (int) $value === 1;
        }

        $normalized = strtolower(trim((string) $value));

        return in_array($normalized, ['1', 'true', 'yes', 'on'], true);
    }

    public static function throughReapPayloadSql(string $tableAlias = 'sc'): string
    {
        $key = self::PAYLOAD_KEY;
        $jsonExtract = match (\Illuminate\Support\Facades\DB::connection()->getDriverName()) {
            'sqlite' => "json_extract({$tableAlias}.payload, '$.\"{$key}\"')",
            'pgsql' => "{$tableAlias}.payload::jsonb ->> '{$key}'",
            default => "JSON_UNQUOTE(JSON_EXTRACT({$tableAlias}.payload, '$.\"{$key}\"'))",
        };

        return "LOWER(COALESCE(CAST({$jsonExtract} AS CHAR), '')) IN ('1', 'true', 'yes', 'on')";
    }

    /**
     * @param  \Illuminate\Database\Query\Builder  $query
     */
    public static function applyThroughReapPayloadScope($query, string $tableAlias = 'sc'): void
    {
        if (Schema::hasColumn('service_cases', 'through_reap')) {
            $query->where("{$tableAlias}.through_reap", true);

            return;
        }

        $query->whereRaw(self::throughReapPayloadSql($tableAlias));
    }

    public static function throughReapBoolean(mixed $value): bool
    {
        return self::payloadValueIsThroughReap($value);
    }

    public static function syncThroughReapColumn(object $case, array $payload): void
    {
        if (! Schema::hasColumn('service_cases', 'through_reap')) {
            return;
        }

        $case->through_reap = self::throughReapBoolean($payload[self::PAYLOAD_KEY] ?? null);
    }

    /**
     * Preserve Through REAP on schema-validated service case payload (not part of field_schema).
     *
     * @param  array<string, mixed>  $payload
     * @param  array<string, mixed>  $rawPayload
     * @return array<string, mixed>
     */
    public static function mergeThroughReapIntoPayload(?Service $service, array $payload, array $rawPayload): array
    {
        if (! self::serviceIsConvergence($service)) {
            unset($payload[self::PAYLOAD_KEY]);

            return $payload;
        }

        $payload[self::PAYLOAD_KEY] = self::payloadValueIsThroughReap(
            $rawPayload[self::PAYLOAD_KEY] ?? ($payload[self::PAYLOAD_KEY] ?? null)
        ) ? '1' : '0';

        return $payload;
    }
}
