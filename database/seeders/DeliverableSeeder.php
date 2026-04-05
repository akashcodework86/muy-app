<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;

class DeliverableSeeder extends Seeder
{
    /** Official MIS 24 rows — sort_order = S.No. */
    public function run(): void
    {
        $rows = [
            [1, 'awareness_district', 'Project Awareness Sessions at District', 'District Level workshop'],
            [2, 'lakhpati_block', 'Support to Lakhpati Didis', 'Block Level Workshop - Female Participants'],
            [3, 'cfa', 'Number of Call for Applications filled', 'Call for Application (CFA)'],
            [4, 'onboarding', 'Number of Incubatees Onboarded', 'Onboarded Incubatees'],
            [5, 'bmc', 'Business Model Canvas', 'Business Model Canvas'],
            [6, 'business_registration', 'Business Registration', 'Udyam, Company, UK Firm, Shop & Establishment, Cooperative, UTDB, Other, Not Required'],
            [7, 'fssai', 'FSSAI registration / renewal', 'FSSAI'],
            [8, 'gst', 'GST Registration', 'GST'],
            [9, 'gi_seller', 'GI Seller Registration', 'GI Seller registration'],
            [10, 'artisan_card', 'Artisan Card', 'Artisan Card'],
            [11, 'trademark', 'Trademark application filing', 'Trademark'],
            [12, 'utdb_registration', 'UTDB Registration', 'UTDB Registration'],
            [13, 'edp_workshop', 'Entrepreneurship sessions', 'EDP Workshop'],
            [14, 'bst_sessions', 'Business Skills Sessions', 'Training Package 1,2,3 & 4'],
            [15, 'bst_participations', 'Incubatees taken Part in Business Skills', 'Incubatees Taken part'],
            [16, 'market_link', 'Incubatee Linked to Markets (Online/Offline)', 'Market Link'],
            [17, 'access_to_finance', 'Access to finance through convergence', 'MSY, MSY Nano, PMEGP, PMFME, MSME Loan, MUDRA, DDU Grah Awas, Veer Chandra Singh..., Other Scheme'],
            [18, 'pitch_deck_prep', 'Incubatees Pitch deck preparations', 'Incubatees Pitch Deck Preparation'],
            [19, 'pitchathon_demo', 'Pitchathon / Demo Day', 'Pitchathon / Demo Day'],
            [20, 'acceleration_services', 'Initiation of acceleration & co-incubation', 'Acceleration Services'],
            [21, 'case_studies', 'Preparation of Case Studies and Testimonials', 'Case Studies and Testimonials'],
            [22, 'social_media', 'Social Media Post', 'Social Media Posts'],
            [23, 'events_seminars', 'Events / Seminars / Workshops', 'Events / Seminars / Workshops'],
            [24, 'buyer_seller_meets', 'Buyer Seller Meets', 'Buyer Seller Meets'],
        ];

        foreach ($rows as [$sort, $code, $name, $mis]) {
            DB::table('deliverables')->insert([
                'sort_order' => $sort,
                'code' => $code,
                'name' => $name,
                'mis_entry_label' => $mis,
                'is_active' => true,
                'created_at' => now(),
                'updated_at' => now(),
            ]);
        }
    }
}
