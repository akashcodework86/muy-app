<?php

namespace App\Services\Deliverables;

use App\Models\Service;
use Illuminate\Support\Facades\Schema;

/**
 * Resolves Key vs Non-Key labels for program deliverables matrix rows
 * from the service catalog reporting_tier (source of truth).
 */
final class ProgramDeliverableReportingTier
{
    private static bool $loaded = false;

    /** @var array<string, string> */
    private static array $byDeliverableCode = [];

    /** @var array<string, string> */
    private static array $byServiceCode = [];

    /**
     * @param  array<string, mixed>  $node  Matrix leaf or node with source + indicator_type
     */
    public static function indicatorTypeLabel(array $node): string
    {
        self::ensureLoaded();

        $fallback = (string) ($node['indicator_type'] ?? '');
        $source = $node['source'] ?? [];
        $type = (string) ($source['type'] ?? 'none');

        $tier = match ($type) {
            'deliverable' => self::$byDeliverableCode[strtolower(trim((string) ($source['code'] ?? '')))] ?? null,
            'service' => self::$byServiceCode[strtolower(trim((string) ($source['code'] ?? '')))] ?? null,
            'services' => self::tierForServiceCodes((array) ($source['codes'] ?? [])),
            default => null,
        };

        return match ($tier) {
            Service::REPORTING_KEY => 'Key Indicator',
            Service::REPORTING_NON_KEY => 'Non-Key',
            default => $fallback,
        };
    }

    /**
     * @param  list<string>  $codes
     */
    private static function tierForServiceCodes(array $codes): ?string
    {
        $tiers = [];
        foreach ($codes as $code) {
            $normalized = strtolower(trim((string) $code));
            if ($normalized === '') {
                continue;
            }
            if (isset(self::$byServiceCode[$normalized])) {
                $tiers[] = self::$byServiceCode[$normalized];
            }
        }

        if ($tiers === []) {
            return null;
        }

        return in_array(Service::REPORTING_KEY, $tiers, true)
            ? Service::REPORTING_KEY
            : Service::REPORTING_NON_KEY;
    }

    private static function ensureLoaded(): void
    {
        if (self::$loaded) {
            return;
        }

        self::$loaded = true;
        self::$byDeliverableCode = [];
        self::$byServiceCode = [];

        if (! Schema::hasTable('services') || ! Schema::hasColumn('services', 'reporting_tier')) {
            return;
        }

        $byDeliverable = [];

        Service::query()
            ->where('is_active', true)
            ->with('deliverable:id,code')
            ->get(['code', 'reporting_tier', 'deliverable_id'])
            ->each(function (Service $service) use (&$byDeliverable): void {
                $tier = trim((string) ($service->reporting_tier ?? ''));
                if ($tier === '' || $tier === Service::REPORTING_UNSET) {
                    return;
                }

                $serviceCode = strtolower(trim((string) $service->code));
                if ($serviceCode !== '') {
                    self::$byServiceCode[$serviceCode] = $tier;
                }

                $deliverableCode = strtolower(trim((string) ($service->deliverable?->code ?? '')));
                if ($deliverableCode !== '') {
                    $byDeliverable[$deliverableCode][] = $tier;
                }
            });

        foreach ($byDeliverable as $deliverableCode => $tiers) {
            self::$byDeliverableCode[$deliverableCode] = in_array(Service::REPORTING_KEY, $tiers, true)
                ? Service::REPORTING_KEY
                : Service::REPORTING_NON_KEY;
        }
    }

    public static function resetForTesting(): void
    {
        self::$loaded = false;
        self::$byDeliverableCode = [];
        self::$byServiceCode = [];
    }
}
