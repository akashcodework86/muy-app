<?php

namespace App\Support;

use App\Models\Service;
use App\Models\ServiceCategory;
use App\Services\SchemaValidator;
use Illuminate\Database\Query\Builder;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Illuminate\Validation\ValidationException;

final class ConvergenceReapSupport
{
    public const PAYLOAD_KEY = 'through_reap';

    public const REAP_SECTOR_KEY = 'reap_sector';

    public const REAP_AMOUNT_KEY = 'reap_amount';

    public const REAP_ACTIVITY_KEY = 'reap_activity';

    public const REAP_DOCUMENT_KEY = 'reap_document';

    /** @var list<string> */
    public const CONVERGENCE_CATEGORY_SLUGS = [
        'convergence-with-line-departments',
        'convergence',
        'convergence_services',
    ];

    /** @return list<string> */
    public static function reapDetailKeys(): array
    {
        return [
            self::REAP_SECTOR_KEY,
            self::REAP_AMOUNT_KEY,
            self::REAP_ACTIVITY_KEY,
            self::REAP_DOCUMENT_KEY,
        ];
    }

    /**
     * @return list<array<string, mixed>>
     */
    public static function reapDetailSchema(): array
    {
        return ServiceFieldTypes::normalizeSchema([
            [
                'key' => self::REAP_SECTOR_KEY,
                'label' => 'Sector',
                'type' => ServiceFieldTypes::SELECT,
                'required' => true,
                'options' => [
                    ['value' => 'farm', 'label' => 'Farm'],
                    ['value' => 'non_farm', 'label' => 'Non-farm'],
                ],
            ],
            [
                'key' => self::REAP_AMOUNT_KEY,
                'label' => 'Support amount',
                'type' => ServiceFieldTypes::SELECT,
                'required' => true,
                'options' => [
                    ['value' => '1_lakh', 'label' => '1 Lakh'],
                    ['value' => '3_lakh', 'label' => '3 Lakh'],
                ],
            ],
            [
                'key' => self::REAP_ACTIVITY_KEY,
                'label' => 'Activity',
                'type' => ServiceFieldTypes::TEXTAREA,
                'required' => true,
            ],
            [
                'key' => self::REAP_DOCUMENT_KEY,
                'label' => 'Document',
                'type' => ServiceFieldTypes::FILE,
                'required' => true,
            ],
        ]);
    }

    public static function reapSectorLabel(?string $value): string
    {
        return match ((string) $value) {
            'farm' => 'Farm',
            'non_farm' => 'Non-farm',
            default => (string) ($value ?? '—'),
        };
    }

    public static function reapAmountLabel(?string $value): string
    {
        return match ((string) $value) {
            '1_lakh' => '1 Lakh',
            '3_lakh' => '3 Lakh',
            default => (string) ($value ?? '—'),
        };
    }

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
        $jsonExtract = match (DB::connection()->getDriverName()) {
            'sqlite' => "json_extract({$tableAlias}.payload, '$.\"{$key}\"')",
            'pgsql' => "{$tableAlias}.payload::jsonb ->> '{$key}'",
            default => "JSON_UNQUOTE(JSON_EXTRACT({$tableAlias}.payload, '$.\"{$key}\"'))",
        };

        return "LOWER(COALESCE(CAST({$jsonExtract} AS CHAR), '')) IN ('1', 'true', 'yes', 'on')";
    }

    /**
     * @param  \Illuminate\Database\Eloquent\Builder|\Illuminate\Database\Query\Builder  $query
     */
    public static function applyThroughReapEloquentScope($query): void
    {
        if (Schema::hasColumn('service_cases', 'through_reap')) {
            $query->where('through_reap', true);

            return;
        }

        $query->whereRaw(self::throughReapPayloadSql('service_cases'));
    }

    /**
     * @param  Builder  $query
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
     * @param  array<string, mixed>  $payload
     * @return array<string, mixed>
     */
    public static function stripReapDetails(array $payload): array
    {
        foreach (self::reapDetailKeys() as $key) {
            unset($payload[$key]);
        }

        return $payload;
    }

    public static function assertReapDocumentUpload(UploadedFile $file): void
    {
        if ($file->getSize() > 5120 * 1024) {
            throw ValidationException::withMessages([
                'payload_files.reap_document' => 'Document must be 5 MB or smaller.',
            ]);
        }
    }

    /**
     * Preserve Through REAP and optional REAP detail fields on validated service case payload.
     *
     * @param  array<string, mixed>  $payload
     * @param  array<string, mixed>  $rawPayload
     * @return array<string, mixed>
     */
    public static function mergeThroughReapIntoPayload(
        ?Service $service,
        array $payload,
        array $rawPayload,
        bool $reapDocumentUploaded = false,
        ?string $existingReapDocument = null,
    ): array {
        if (! self::serviceIsConvergence($service)) {
            unset($payload[self::PAYLOAD_KEY]);

            return self::stripReapDetails($payload);
        }

        $throughReap = self::payloadValueIsThroughReap(
            $rawPayload[self::PAYLOAD_KEY] ?? ($payload[self::PAYLOAD_KEY] ?? null)
        );
        $payload[self::PAYLOAD_KEY] = $throughReap ? '1' : '0';

        if (! $throughReap) {
            return self::stripReapDetails($payload);
        }

        $reapDetails = app(SchemaValidator::class)->validate(
            self::reapDetailSchema(),
            $rawPayload
        );

        $documentName = trim((string) ($reapDetails[self::REAP_DOCUMENT_KEY] ?? ''));
        if ($documentName === '' && $reapDocumentUploaded) {
            $documentName = trim((string) ($rawPayload[self::REAP_DOCUMENT_KEY] ?? ''));
        }
        if ($documentName === '' && $existingReapDocument !== null && trim($existingReapDocument) !== '') {
            $documentName = trim($existingReapDocument);
        }

        if ($documentName === '') {
            throw ValidationException::withMessages([
                'payload_files.reap_document' => 'A document is required when Through REAP is selected.',
            ]);
        }

        $reapDetails[self::REAP_DOCUMENT_KEY] = $documentName;

        return array_merge($payload, $reapDetails);
    }
}
