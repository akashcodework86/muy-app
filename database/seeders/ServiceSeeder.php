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
        $c = fn (string $slug) => DB::table('service_categories')->where('slug', $slug)->value('id');

        $items = [
            // Business Formalisation → deliverable 6
            [$c('business_formalisation'), 'udyam_registration', 'Udyam Registration', 'business_registration', 1],
            [$c('business_formalisation'), 'shop_establishment', 'Shop & Establishment', 'business_registration', 2],
            [$c('business_formalisation'), 'utdb_registration_bf', 'UTDB Registration', 'business_registration', 3],
            [$c('business_formalisation'), 'company_registration', 'Company Registration', 'business_registration', 4],
            [$c('business_formalisation'), 'uk_firm_registration', 'UK Firm Registration', 'business_registration', 5],
            [$c('business_formalisation'), 'cooperative', 'Cooperative', 'business_registration', 6],
            [$c('business_formalisation'), 'already_registered', 'Already Registered', 'business_registration', 7],

            // Legal — mapped to MIS lines 7–12 / 23
            [$c('legal_support'), 'fssai', 'FSSAI', 'fssai', 1],
            [$c('legal_support'), 'gst', 'GST', 'gst', 2],
            [$c('legal_support'), 'trademark', 'Trademark', 'trademark', 3],
            [$c('legal_support'), 'ipr_support', 'IPR support', 'trademark', 4],
            [$c('legal_support'), 'legal_vetting', 'Legal vetting of documents', 'events_seminars', 5],
            [$c('legal_support'), 'gi_seller_registration', 'GI Seller Registration', 'gi_seller', 6],
            [$c('legal_support'), 'fire_noc', 'Fire NOC', 'events_seminars', 7],
            [$c('legal_support'), 'artisan_card', 'Artisan Card', 'artisan_card', 8],
            [$c('legal_support'), 'ayush_licence', 'Ayush Licence', 'events_seminars', 9],
            [$c('legal_support'), 'pan_card', 'PAN Card', 'business_registration', 10],
            [$c('legal_support'), 'other_licensing', 'Other Licensing Support', 'business_registration', 11],

            // Convergence → 17
            [$c('convergence'), 'msy', 'MSY', 'access_to_finance', 1],
            [$c('convergence'), 'msy_nano', 'MSY NaNo', 'access_to_finance', 2],
            [$c('convergence'), 'pmegp', 'PMEGP', 'access_to_finance', 3],
            [$c('convergence'), 'msme', 'MSME', 'access_to_finance', 4],
            [$c('convergence'), 'mudra', 'MUDRA', 'access_to_finance', 5],
            [$c('convergence'), 'ddu_homestay', 'DDU Grah Awas Yojana (Homestay)', 'access_to_finance', 6],
            [$c('convergence'), 'vcsg', 'Veer Chandra Singh Garhwali Self Empl.', 'access_to_finance', 7],
            [$c('convergence'), 'support_application', 'Support in Application process', 'access_to_finance', 8],
            [$c('convergence'), 'pmfme', 'PMFME', 'access_to_finance', 9],

            // Other support → 20 / 23 (operational add-ons)
            [$c('other_support'), 'packaging_support', 'Packaging Support', 'acceleration_services', 1],
            [$c('other_support'), 'packaging_designing', 'Packaging Designing', 'acceleration_services', 2],
            [$c('other_support'), 'labelling_support', 'Labelling Support', 'acceleration_services', 3],
            [$c('other_support'), 'logo_designing', 'Logo Designing', 'acceleration_services', 4],
            [$c('other_support'), 'product_testing', 'Product Testing', 'acceleration_services', 5],
            [$c('other_support'), 'catalogue_development', 'Catalogue Development', 'acceleration_services', 6],
            [$c('other_support'), 'photoshoot', 'Photoshoot', 'acceleration_services', 7],
            [$c('other_support'), 'product_diversification', 'Product Diversification', 'acceleration_services', 8],
            [$c('other_support'), 'content_writing', 'Content Writing', 'acceleration_services', 9],
            [$c('other_support'), 'business_plan', 'Business Plan', 'acceleration_services', 10],
            [$c('other_support'), 'trade_fair', 'Trade fair Participation', 'buyer_seller_meets', 11],

            // BMC
            [$c('business_model_canvas'), 'bmc_canvas', 'Business Model Canvas', 'bmc', 1],

            // Forward linkages → 16
            [$c('forward_linkages'), 'market_link', 'Market Link', 'market_link', 1],
            [$c('forward_linkages'), 'offline_connect', 'Offline Connect', 'market_link', 2],

            // Incubation
            [$c('incubation_support'), 'pitch_deck', 'Pitch Deck', 'pitch_deck_prep', 1],
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
