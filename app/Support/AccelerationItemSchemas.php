<?php

namespace App\Support;

use App\Models\District;
use App\Models\Service;
use App\Models\ServiceCategory;
use App\Support\ServiceFieldTypes as T;
use Illuminate\Support\Facades\Schema;

/**
 * Per-tick field schemas for MUY Acceleration Services (MIS 7.2).
 * Every schema starts with a required service_item_date.
 */
final class AccelerationItemSchemas
{
    /**
     * @return list<array<string, mixed>>
     */
    public static function forKey(string $itemKey, ?string $section = null): array
    {
        $map = self::all();
        $baseKey = AccelerationServicesOptions::baseItemKey($itemKey);

        if (isset($map[$baseKey])) {
            return ServiceFieldTypes::normalizeSchema($map[$baseKey]);
        }

        if ($section === AccelerationServicesOptions::SECTION_PARTNERSHIP) {
            return ServiceFieldTypes::normalizeSchema(self::partnershipSchema());
        }

        return ServiceFieldTypes::normalizeSchema(self::customFallback());
    }

    /**
     * @return array<string, list<array<string, mixed>>>
     */
    public static function allKeyed(): array
    {
        $out = [];
        foreach (array_keys(self::all()) as $key) {
            $out[$key] = self::forKey($key);
        }
        $out['_partnership'] = ServiceFieldTypes::normalizeSchema(self::partnershipSchema());
        $out['_custom'] = ServiceFieldTypes::normalizeSchema(self::customFallback());

        return $out;
    }

    /**
     * @return array<string, list<array<string, mixed>>>
     */
    private static function all(): array
    {
        return [
            'business_formalization' => self::businessFormalization(),
            'coaching_mentorship' => self::coachingMentorship(),
            'funding_investment_support' => self::fundingConvergence(),
            'business_model_refinement' => self::businessModelRefinement(),
            'market_linkage' => self::marketLinkage(),
            'industry_connections' => self::industryConnections(),
            AccelerationServicesOptions::BUYER_SELLER_MEET_KEY => self::buyerSellerMeet(),
            'tbi_graphic_era' => self::partnershipSchema(),
            'uplift_foundation' => self::partnershipSchema(),
            'sse_india' => self::partnershipSchema(),
        ];
    }

    /** @return list<array<string, mixed>> */
    private static function dateField(): array
    {
        return [
            [
                'key' => 'service_item_date',
                'label' => 'Service date',
                'type' => T::DATE,
                'required' => true,
            ],
        ];
    }

    /** @return list<array{value: string, label: string}> */
    public static function districtOptions(): array
    {
        if (! Schema::hasTable('districts')) {
            return [];
        }

        return District::query()
            ->orderBy('name')
            ->get(['name'])
            ->map(fn (District $d) => [
                'value' => (string) $d->name,
                'label' => (string) $d->name,
            ])
            ->all();
    }

    /** @return list<array{value: string, label: string}> */
    public static function governmentSchemeOptions(): array
    {
        $fallback = [
            ['value' => 'MSY', 'label' => 'MSY'],
            ['value' => 'MSY NaNo', 'label' => 'MSY NaNo'],
            ['value' => 'PMEGP', 'label' => 'PMEGP'],
            ['value' => 'MSME', 'label' => 'MSME'],
            ['value' => 'MUDRA', 'label' => 'MUDRA'],
            ['value' => 'DDU Grah Awas Yojana (Homestay)', 'label' => 'DDU Grah Awas Yojana (Homestay)'],
            ['value' => 'Veer Chandra Singh Garhwali Self Empl.', 'label' => 'Veer Chandra Singh Garhwali Self Empl.'],
            ['value' => 'PMFME', 'label' => 'PMFME'],
        ];

        if (! Schema::hasTable('services') || ! Schema::hasTable('service_categories')) {
            return $fallback;
        }

        $categoryIds = ServiceCategory::query()
            ->whereIn('slug', ConvergenceReapSupport::CONVERGENCE_CATEGORY_SLUGS)
            ->pluck('id');

        if ($categoryIds->isEmpty()) {
            return $fallback;
        }

        $excludeCodes = array_merge(
            ConvergenceReapSupport::knownReapSupportServiceCodes(),
            ['support_application', 'support_muy_incubatee_reap'],
        );

        $rows = Service::query()
            ->whereIn('service_category_id', $categoryIds)
            ->where('is_active', true)
            ->whereNotIn('code', $excludeCodes)
            ->orderBy('sort_order')
            ->orderBy('name')
            ->get(['name', 'code']);

        if ($rows->isEmpty()) {
            return $fallback;
        }

        $out = [];
        $seen = [];
        foreach ($rows as $row) {
            $name = trim((string) $row->name);
            if ($name === '' || isset($seen[$name])) {
                continue;
            }
            // Not a government scheme — keep scheme list govt-only.
            if (strcasecmp($name, 'Other Convergence Support') === 0) {
                continue;
            }
            $seen[$name] = true;
            $out[] = ['value' => $name, 'label' => $name];
        }

        return $out !== [] ? $out : $fallback;
    }

    /** @return list<array{value: string, label: string}> */
    private static function durationMinutesOptions(): array
    {
        $mins = [15, 30, 45, 60, 90, 120, 150, 180, 210, 240, 300, 360];
        $out = [];
        foreach ($mins as $m) {
            $label = $m < 60
                ? $m.' min'
                : (intdiv($m, 60) === 1 && $m % 60 === 0
                    ? '60 min (1 hr)'
                    : ($m % 60 === 0 ? ($m / 60).' hrs ('.$m.' min)' : $m.' min'));
            if ($m === 30) {
                $label = '30 min';
            } elseif ($m === 60) {
                $label = '60 min';
            } elseif ($m === 120) {
                $label = '120 min';
            }
            $out[] = ['value' => (string) $m, 'label' => $label];
        }

        return $out;
    }

    /** @return list<array<string, mixed>> */
    private static function businessFormalization(): array
    {
        return array_merge(self::dateField(), [
            [
                'key' => 'registration_type',
                'label' => 'Registration type',
                'type' => T::SELECT,
                'required' => true,
                'options' => [
                    ['value' => 'udyam', 'label' => 'Udyam Registration'],
                    ['value' => 'shop_establishment', 'label' => 'Shop & Establishment'],
                    ['value' => 'utdb', 'label' => 'UTDB Registration'],
                    ['value' => 'company', 'label' => 'Company Registration'],
                    ['value' => 'uk_firm', 'label' => 'UK Firm Registration'],
                    ['value' => 'cooperative', 'label' => 'Cooperative'],
                    ['value' => 'other', 'label' => 'Other'],
                ],
            ],
            [
                'key' => 'registration_no',
                'label' => 'Registration number',
                'type' => T::TEXT,
                'required' => true,
            ],
            [
                'key' => 'registration_date',
                'label' => 'Registration / issue date',
                'type' => T::DATE,
                'required' => true,
            ],
            [
                'key' => 'enterprise_name',
                'label' => 'Enterprise name (as registered)',
                'type' => T::TEXT,
                'required' => false,
            ],
            [
                'key' => 'notes',
                'label' => 'Notes',
                'type' => T::TEXTAREA,
                'required' => false,
            ],
        ]);
    }

    /** @return list<array<string, mixed>> */
    private static function coachingMentorship(): array
    {
        return array_merge(self::dateField(), [
            [
                'key' => 'mentor_name',
                'label' => 'Mentor name',
                'type' => T::TEXT,
                'required' => true,
            ],
            [
                'key' => 'session_mode',
                'label' => 'Session mode',
                'type' => T::SELECT,
                'required' => true,
                'options' => [
                    ['value' => 'one_to_one', 'label' => 'One to one'],
                    ['value' => 'group', 'label' => 'Group'],
                    ['value' => 'online', 'label' => 'Online'],
                    ['value' => 'offline', 'label' => 'Offline'],
                ],
            ],
            [
                'key' => 'focus_area',
                'label' => 'Focus area',
                'type' => T::TEXT,
                'required' => false,
                'help' => 'e.g. product, finance, marketing, operations',
            ],
            [
                'key' => 'duration_minutes',
                'label' => 'Duration (minutes)',
                'type' => T::SELECT,
                'required' => true,
                'options' => self::durationMinutesOptions(),
            ],
            [
                'key' => 'guidance_summary',
                'label' => 'Key guidance / outcomes',
                'type' => T::TEXTAREA,
                'required' => false,
            ],
        ]);
    }

    /** @return list<array<string, mixed>> */
    private static function fundingConvergence(): array
    {
        return array_merge(self::dateField(), [
            [
                'key' => 'scheme_name',
                'label' => 'Name of scheme',
                'type' => T::SELECT,
                'required' => true,
                'help' => 'Government schemes only (MSY, PMEGP, etc.).',
                'options' => self::governmentSchemeOptions(),
            ],
            [
                'key' => 'scheme_registration_date',
                'label' => 'Date of scheme registration',
                'type' => T::DATE,
                'required' => true,
            ],
            [
                'key' => 'applied_amount',
                'label' => 'Applied amount (₹)',
                'type' => T::AMOUNT,
                'required' => true,
            ],
            [
                'key' => 'sanctioned_amount',
                'label' => 'Sanctioned amount (₹)',
                'type' => T::AMOUNT,
                'required' => false,
            ],
            [
                'key' => 'notes',
                'label' => 'Notes',
                'type' => T::TEXTAREA,
                'required' => false,
            ],
        ]);
    }

    /** @return list<array<string, mixed>> */
    private static function businessModelRefinement(): array
    {
        return array_merge(self::dateField(), [
            [
                'key' => 'refinement_areas',
                'label' => 'Areas refined',
                'type' => T::MULTISELECT,
                'required' => true,
                'help' => 'Tick every BMC / model area you worked on in this session.',
                'options' => [
                    ['value' => 'value_proposition', 'label' => 'Value proposition'],
                    ['value' => 'revenue_model', 'label' => 'Revenue model'],
                    ['value' => 'pricing', 'label' => 'Pricing'],
                    ['value' => 'customer_segment', 'label' => 'Customer segment'],
                    ['value' => 'channels', 'label' => 'Channels'],
                    ['value' => 'cost_structure', 'label' => 'Cost structure'],
                    ['value' => 'other', 'label' => 'Other'],
                ],
            ],
            [
                'key' => 'facilitator',
                'label' => 'Facilitator / mentor',
                'type' => T::TEXT,
                'required' => false,
            ],
            [
                'key' => 'summary',
                'label' => 'Summary of changes',
                'type' => T::TEXTAREA,
                'required' => false,
            ],
        ]);
    }

    /** @return list<array<string, mixed>> */
    private static function marketLinkage(): array
    {
        return array_merge(self::dateField(), [
            [
                'key' => 'market_type',
                'label' => 'Market type',
                'type' => T::SELECT,
                'required' => true,
                'options' => [
                    ['value' => 'online', 'label' => 'Online'],
                    ['value' => 'offline', 'label' => 'Offline'],
                    ['value' => 'both', 'label' => 'Both'],
                ],
            ],
            [
                'key' => 'partner_or_buyer',
                'label' => 'Partner / buyer / platform',
                'type' => T::TEXT,
                'required' => true,
            ],
            [
                'key' => 'order_value',
                'label' => 'Order value',
                'type' => T::AMOUNT,
                'required' => false,
                'help' => 'If an order value is entered, upload proof of order documents below (mandatory).',
            ],
            [
                'key' => 'link_url',
                'label' => 'Link / URL (if online)',
                'type' => T::URL,
                'required' => false,
                'help' => 'Storefront, catalogue, or order link',
            ],
            [
                'key' => 'brief',
                'label' => 'Brief description',
                'type' => T::TEXTAREA,
                'required' => false,
            ],
        ]);
    }

    /** @return list<array<string, mixed>> */
    private static function industryConnections(): array
    {
        return array_merge(self::dateField(), [
            [
                'key' => 'connection_type',
                'label' => 'Connection type',
                'type' => T::SELECT,
                'required' => true,
                'options' => [
                    ['value' => 'buyer', 'label' => 'Buyer'],
                    ['value' => 'supplier', 'label' => 'Supplier'],
                    ['value' => 'expert', 'label' => 'Industry expert'],
                    ['value' => 'association', 'label' => 'Association / body'],
                    ['value' => 'other', 'label' => 'Other'],
                ],
            ],
            [
                'key' => 'org_or_person',
                'label' => 'Organisation / person',
                'type' => T::TEXT,
                'required' => true,
            ],
            [
                'key' => 'purpose',
                'label' => 'Purpose',
                'type' => T::TEXT,
                'required' => false,
            ],
            [
                'key' => 'outcome',
                'label' => 'Outcome',
                'type' => T::TEXTAREA,
                'required' => false,
            ],
        ]);
    }

    /** @return list<array<string, mixed>> */
    private static function buyerSellerMeet(): array
    {
        return array_merge(self::dateField(), [
            [
                'key' => 'district',
                'label' => 'District',
                'type' => T::SELECT,
                'required' => true,
                'options' => self::districtOptions(),
            ],
            [
                'key' => 'meet_name',
                'label' => 'Meet name / venue',
                'type' => T::TEXT,
                'required' => true,
            ],
            [
                'key' => 'outcome_type',
                'label' => 'Outcome type',
                'type' => T::MULTISELECT,
                'required' => true,
                'help' => 'Select all outcomes that apply.',
                'options' => [
                    ['value' => 'sales', 'label' => 'Sales'],
                    ['value' => 'po', 'label' => 'Purchase Order (PO)'],
                    ['value' => 'lead', 'label' => 'Lead / interest only'],
                    ['value' => 'mou', 'label' => 'MoU / intent letter'],
                    ['value' => 'other', 'label' => 'Other'],
                ],
            ],
            [
                'key' => 'buyer_name',
                'label' => 'Buyer name',
                'type' => T::TEXT,
                'required' => true,
            ],
            [
                'key' => 'order_value',
                'label' => 'Order / PO value',
                'type' => T::AMOUNT,
                'required' => false,
                'help' => 'If an order / PO value is entered, upload proof documents below (mandatory).',
            ],
            [
                'key' => 'po_number',
                'label' => 'PO number',
                'type' => T::TEXT,
                'required' => false,
                'help' => 'Required when outcome includes a PO',
            ],
            [
                'key' => 'outcome_summary',
                'label' => 'Outcome summary',
                'type' => T::TEXTAREA,
                'required' => false,
            ],
        ]);
    }

    /** @return list<array{value: string, label: string}> */
    private static function monthRangeOptions(): array
    {
        $out = [];
        // Cover FY window around now: 18 months back, 24 months ahead
        $start = now()->startOfMonth()->subMonths(18);
        for ($i = 0; $i < 43; $i++) {
            $m = $start->copy()->addMonths($i);
            $out[] = [
                'value' => $m->format('Y-m'),
                'label' => $m->format('M Y'),
            ];
        }

        return $out;
    }

    /** @return list<array{value: string, label: string}> */
    private static function supportTypeOptions(): array
    {
        return [
            ['value' => 'mentoring', 'label' => 'Mentoring'],
            ['value' => 'workspace', 'label' => 'Workspace / incubation space'],
            ['value' => 'market_access', 'label' => 'Market access'],
            ['value' => 'funding_connect', 'label' => 'Funding / investor connect'],
            ['value' => 'technical', 'label' => 'Technical / product support'],
            ['value' => 'compliance', 'label' => 'Compliance / legal'],
            ['value' => 'other', 'label' => 'Other'],
        ];
    }

    /** @return list<array<string, mixed>> */
    private static function partnershipSchema(): array
    {
        $monthOptions = self::monthRangeOptions();
        $supportOptions = self::supportTypeOptions();

        $fields = array_merge(self::dateField(), [
            [
                'key' => 'domain',
                'label' => 'Domain',
                'type' => T::SELECT,
                'required' => true,
                'options' => [
                    ['value' => 'food', 'label' => 'Food / agri'],
                    ['value' => 'handicraft', 'label' => 'Handicraft'],
                    ['value' => 'tourism', 'label' => 'Tourism / hospitality'],
                    ['value' => 'tech', 'label' => 'Tech / digital'],
                    ['value' => 'manufacturing', 'label' => 'Manufacturing'],
                    ['value' => 'services', 'label' => 'Services'],
                    ['value' => 'other', 'label' => 'Other'],
                ],
            ],
            [
                'key' => 'start_date',
                'label' => 'Start date',
                'type' => T::DATE,
                'required' => true,
            ],
            [
                'key' => 'end_date',
                'label' => 'End date',
                'type' => T::DATE,
                'required' => true,
            ],
            [
                'key' => 'duration_term',
                'label' => 'Duration period',
                'type' => T::SELECT,
                'required' => true,
                'options' => [
                    ['value' => 'short_term', 'label' => 'Short term (< 1 month)'],
                    ['value' => 'long_term', 'label' => 'Long term (≥ 1 month)'],
                ],
            ],
            [
                'key' => 'duration_days',
                'label' => 'Duration (days)',
                'type' => T::NUMBER,
                'required' => true,
                'min' => 1,
                'help' => 'Number of days for short-term support',
                'visible_if' => ['field' => 'duration_term', 'value' => 'short_term'],
            ],
            [
                'key' => 'period_from_month',
                'label' => 'From month',
                'type' => T::SELECT,
                'required' => true,
                'help' => 'Start of long-term month range',
                'options' => $monthOptions,
                'visible_if' => ['field' => 'duration_term', 'value' => 'long_term'],
            ],
            [
                'key' => 'period_to_month',
                'label' => 'To month',
                'type' => T::SELECT,
                'required' => true,
                'help' => 'End of long-term month range',
                'options' => $monthOptions,
                'visible_if' => ['field' => 'duration_term', 'value' => 'long_term'],
            ],
            [
                'key' => 'support_types',
                'label' => 'Types of support given',
                'type' => T::MULTISELECT,
                'required' => true,
                'help' => 'Tick a support type to specify what topic / what was taught next to it.',
                'options' => $supportOptions,
            ],
        ]);

        foreach ($supportOptions as $opt) {
            $fields[] = [
                'key' => 'support_topic_'.$opt['value'],
                'label' => 'Specify — '.$opt['label'].' (what topic / what taught)',
                'type' => T::TEXTAREA,
                'required' => true,
                'help' => 'Mandatory for this support type.',
                'visible_if' => ['field' => 'support_types', 'value' => $opt['value']],
            ];
        }

        $fields[] = [
            'key' => 'poc_name',
            'label' => 'Partner POC name',
            'type' => T::TEXT,
            'required' => false,
        ];
        $fields[] = [
            'key' => 'notes',
            'label' => 'Notes',
            'type' => T::TEXTAREA,
            'required' => false,
        ];

        return $fields;
    }

    /** @return list<array<string, mixed>> */
    private static function customFallback(): array
    {
        return array_merge(self::dateField(), [
            [
                'key' => 'notes',
                'label' => 'Notes',
                'type' => T::TEXTAREA,
                'required' => false,
            ],
        ]);
    }
}
