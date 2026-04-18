<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;

class ServiceSeeder extends Seeder
{
    /**
     * Leaf services for assign-services style flows.
     * deliverable_code maps achievement roll-up to one of 24 MIS deliverables.
     */
    public function run(): void
    {
        $d = fn (string $code) => DB::table('deliverables')->where('code', $code)->value('id');
        /** Default subcatalog under each top category ({parent_slug}_services), created by ServiceCategorySeeder. */
        $sub = fn (string $parentSlug) => DB::table('service_categories')->where('slug', $parentSlug.'_services')->value('id');

        $items = [
            // Business Formalisation → deliverable 6
            [$sub('business_formalisation'), 'udyam_registration', 'Udyam Registration', 'business_registration', 1],
            [$sub('business_formalisation'), 'shop_establishment', 'Shop & Establishment', 'business_registration', 2],
            [$sub('business_formalisation'), 'utdb_registration_bf', 'UTDB Registration', 'business_registration', 3],
            [$sub('business_formalisation'), 'company_registration', 'Company Registration', 'business_registration', 4],
            [$sub('business_formalisation'), 'uk_firm_registration', 'UK Firm Registration', 'business_registration', 5],
            [$sub('business_formalisation'), 'cooperative', 'Cooperative', 'business_registration', 6],
            [$sub('business_formalisation'), 'already_registered', 'Already Registered', 'business_registration', 7],

            // Legal — mapped to MIS lines 7–12 / 23
            [$sub('legal_support'), 'fssai', 'FSSAI', 'fssai', 1],
            [$sub('legal_support'), 'gst', 'GST', 'gst', 2],
            [$sub('legal_support'), 'trademark', 'Trademark', 'trademark', 3],
            [$sub('legal_support'), 'ipr_support', 'IPR support', 'trademark', 4],
            [$sub('legal_support'), 'legal_vetting', 'Legal vetting of documents', 'events_seminars', 5],
            [$sub('legal_support'), 'gi_seller_registration', 'GI Seller Registration', 'gi_seller', 6],
            [$sub('legal_support'), 'fire_noc', 'Fire NOC', 'events_seminars', 7],
            [$sub('legal_support'), 'artisan_card', 'Artisan Card', 'artisan_card', 8],
            [$sub('legal_support'), 'ayush_licence', 'Ayush Licence', 'events_seminars', 9],
            [$sub('legal_support'), 'pan_card', 'PAN Card', 'business_registration', 10],
            [$sub('legal_support'), 'other_licensing', 'Other Licensing Support', 'business_registration', 11],

            // Convergence → 17
            [$sub('convergence'), 'msy', 'MSY', 'access_to_finance', 1],
            [$sub('convergence'), 'msy_nano', 'MSY NaNo', 'access_to_finance', 2],
            [$sub('convergence'), 'pmegp', 'PMEGP', 'access_to_finance', 3],
            [$sub('convergence'), 'msme', 'MSME', 'access_to_finance', 4],
            [$sub('convergence'), 'mudra', 'MUDRA', 'access_to_finance', 5],
            [$sub('convergence'), 'ddu_homestay', 'DDU Grah Awas Yojana (Homestay)', 'access_to_finance', 6],
            [$sub('convergence'), 'vcsg', 'Veer Chandra Singh Garhwali Self Empl.', 'access_to_finance', 7],
            [$sub('convergence'), 'support_application', 'Support in Application process', 'access_to_finance', 8],
            [$sub('convergence'), 'pmfme', 'PMFME', 'access_to_finance', 9],

            // Other support → 20 / 23 (operational add-ons)
            [$sub('other_support'), 'packaging_support', 'Packaging Support', 'acceleration_services', 1],
            [$sub('other_support'), 'packaging_designing', 'Packaging Designing', 'acceleration_services', 2],
            [$sub('other_support'), 'labelling_support', 'Labelling Support', 'acceleration_services', 3],
            [$sub('other_support'), 'logo_designing', 'Logo Designing', 'acceleration_services', 4],
            [$sub('other_support'), 'product_testing', 'Product Testing', 'acceleration_services', 5],
            [$sub('other_support'), 'catalogue_development', 'Catalogue Development', 'acceleration_services', 6],
            [$sub('other_support'), 'photoshoot', 'Photoshoot', 'acceleration_services', 7],
            [$sub('other_support'), 'product_diversification', 'Product Diversification', 'acceleration_services', 8],
            [$sub('other_support'), 'content_writing', 'Content Writing', 'acceleration_services', 9],
            [$sub('other_support'), 'business_plan', 'Business Plan', 'acceleration_services', 10],
            [$sub('other_support'), 'trade_fair', 'Trade fair Participation', 'buyer_seller_meets', 11],

            // BMC
            [$sub('business_model_canvas'), 'bmc_canvas', 'Business Model Canvas', 'bmc', 1],

            // Forward linkages → 16
            [$sub('forward_linkages'), 'market_link', 'Market Link', 'market_link', 1],
            [$sub('forward_linkages'), 'offline_connect', 'Offline Connect', 'market_link', 2],

            // Incubation
            [$sub('incubation_support'), 'pitch_deck', 'Pitch Deck', 'pitch_deck_prep', 1],
        ];

        foreach ($items as [$catId, $code, $name, $delCode, $sort]) {
            DB::table('services')->insert([
                'service_category_id' => $catId,
                'deliverable_id' => $d($delCode),
                'code' => $code,
                'name' => $name,
                'sort_order' => $sort,
                'is_active' => true,
                'created_at' => now(),
                'updated_at' => now(),
            ]);
        }
    }
}
