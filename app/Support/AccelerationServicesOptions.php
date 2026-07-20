<?php

namespace App\Support;

use App\Models\AccelerationServiceItemCatalog;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Str;

final class AccelerationServicesOptions
{
    public const SECTION_SERVICE_DETAIL = 'service_detail';

    public const SECTION_CROSS_CUTTING = 'cross_cutting';

    public const SECTION_PARTNERSHIP = 'partnership';

    public const BUYER_SELLER_MEET_KEY = 'buyer_seller_meet';

    /** @var array<string, list<array{key: string, label: string}>> */
    private const SYSTEM_ITEMS = [
        self::SECTION_SERVICE_DETAIL => [
            ['key' => 'business_formalization', 'label' => 'Business Formalization'],
            ['key' => 'coaching_mentorship', 'label' => 'One to One Coaching & Mentorship (Specialized Mentorship Support)'],
            ['key' => 'funding_investment_support', 'label' => 'Convergence — Funding and Investment Support'],
            ['key' => 'business_model_refinement', 'label' => 'Business Model Refinement'],
            ['key' => 'market_linkage', 'label' => 'Market Linkage'],
            ['key' => 'industry_connections', 'label' => 'Building Industry Connections'],
        ],
        self::SECTION_CROSS_CUTTING => [
            ['key' => self::BUYER_SELLER_MEET_KEY, 'label' => 'Buyer Seller Meet'],
        ],
        self::SECTION_PARTNERSHIP => [
            ['key' => 'tbi_graphic_era', 'label' => 'TBI (Graphic Era)'],
            ['key' => 'uplift_foundation', 'label' => 'Uplift Foundation'],
            ['key' => 'sse_india', 'label' => 'SSE India'],
        ],
    ];

    /**
     * @return list<array{key: string, label: string, is_custom: bool}>
     */
    public static function itemsForSection(string $section): array
    {
        $items = [];
        $seen = [];

        foreach (self::SYSTEM_ITEMS[$section] ?? [] as $row) {
            $key = (string) $row['key'];
            if ($key === 'soft_skills') {
                continue;
            }
            $items[] = ['key' => $key, 'label' => (string) $row['label'], 'is_custom' => false];
            $seen[$key] = true;
        }

        if (Schema::hasTable('acceleration_service_item_catalog')) {
            $custom = AccelerationServiceItemCatalog::query()
                ->where('section', $section)
                ->where('is_active', true)
                ->orderBy('item_label')
                ->get(['item_key', 'item_label']);

            foreach ($custom as $row) {
                $key = (string) $row->item_key;
                if ($key === 'soft_skills' || str_contains(strtolower((string) $row->item_label), 'soft skill')) {
                    continue;
                }
                if (isset($seen[$key])) {
                    continue;
                }
                $items[] = ['key' => $key, 'label' => (string) $row->item_label, 'is_custom' => true];
                $seen[$key] = true;
            }
        }

        return $items;
    }

    /**
     * @return array<string, list<array{key: string, label: string}>>
     */
    public static function systemCatalogRows(): array
    {
        return self::SYSTEM_ITEMS;
    }

    /**
     * @return array<string, list<array{key: string, label: string, is_custom: bool}>>
     */
    public static function allSections(): array
    {
        return [
            self::SECTION_SERVICE_DETAIL => self::itemsForSection(self::SECTION_SERVICE_DETAIL),
            self::SECTION_CROSS_CUTTING => self::itemsForSection(self::SECTION_CROSS_CUTTING),
            self::SECTION_PARTNERSHIP => self::itemsForSection(self::SECTION_PARTNERSHIP),
        ];
    }

    public static function labelForKey(string $section, string $key): string
    {
        $baseKey = self::baseItemKey($key);
        $repeatNumber = self::repeatNumber($key);

        foreach (self::itemsForSection($section) as $item) {
            if ($item['key'] === $baseKey) {
                return $item['label'].($repeatNumber > 1 ? ' #'.$repeatNumber : '');
            }
        }

        return $key;
    }

    public static function isMarketLinkageKey(string $key): bool
    {
        return self::baseItemKey($key) === 'market_linkage';
    }

    /**
     * Resolve schema/catalog base key (e.g. market_linkage_2 → market_linkage).
     */
    public static function baseItemKey(string $key): string
    {
        if (preg_match('/^(market_linkage)_\d+$/', $key, $m)) {
            return $m[1];
        }

        if (preg_match('/^(.+)__(\d+)$/', $key, $m)) {
            return $m[1];
        }

        return $key;
    }

    public static function repeatNumber(string $key): int
    {
        if (preg_match('/^market_linkage_(\d+)$/', $key, $m)
            || preg_match('/^.+__(\d+)$/', $key, $m)) {
            return max(2, (int) $m[1]);
        }

        return 1;
    }

    public static function repeatedItemKey(string $baseKey, int $number): string
    {
        if ($number <= 1) {
            return $baseKey;
        }

        return $baseKey === 'market_linkage'
            ? $baseKey.'_'.$number
            : $baseKey.'__'.$number;
    }

    public static function isValidSection(string $section): bool
    {
        return in_array($section, [
            self::SECTION_SERVICE_DETAIL,
            self::SECTION_CROSS_CUTTING,
            self::SECTION_PARTNERSHIP,
        ], true);
    }

    public static function incubateeKey(int $legacyPhase1ApplicationId): string
    {
        return 'p1:'.$legacyPhase1ApplicationId;
    }

    public static function catalogKeyFromLabel(string $label): string
    {
        $slug = Str::slug($label, '_');

        return $slug !== '' ? 'custom_'.$slug : 'custom_'.Str::random(8);
    }

    public static function sectionLabel(string $section): string
    {
        return match ($section) {
            self::SECTION_SERVICE_DETAIL => 'In-house service details',
            self::SECTION_CROSS_CUTTING => 'Cross-cutting initiative',
            self::SECTION_PARTNERSHIP => 'Co-incubation partners',
            default => $section,
        };
    }
}
