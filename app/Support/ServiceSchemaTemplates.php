<?php

namespace App\Support;

use App\Support\ServiceFieldTypes as T;

/**
 * Pre-built field schemas for the admin "Load template" dropdown.
 *
 * @return array<string, list<array<string, mixed>>>
 */
class ServiceSchemaTemplates
{
    /**
     * @return list<array<string, mixed>>
     */
    public static function convergenceWithLineDepartments(): array
    {
        return [
            ['key' => 'scheme_name', 'label' => 'Name of scheme', 'type' => T::TEXT, 'required' => true, 'help' => 'Defaults from the selected service; edit if needed.'],
            ['key' => 'scheme_registration_date', 'label' => 'Date of scheme registration', 'type' => T::DATE, 'required' => true],
            ['key' => 'applied_amount', 'label' => 'Applied amount (₹)', 'type' => T::AMOUNT, 'required' => false],
            ['key' => 'sanctioned_amount', 'label' => 'Sanctioned amount (₹)', 'type' => T::AMOUNT, 'required' => false],
        ];
    }

    public static function all(): array
    {
        return [
            'Blank (no extra fields)' => [],

            'Udyam / MSME registration' => [
                ['key' => 'udyam_number', 'label' => 'Udyam registration number', 'type' => T::TEXT, 'required' => true, 'help' => '19-character URN as on certificate'],
                ['key' => 'enterprise_name', 'label' => 'Enterprise name (as registered)', 'type' => T::TEXT, 'required' => true],
                ['key' => 'date_of_issue', 'label' => 'Date of issue', 'type' => T::DATE, 'required' => true],
                ['key' => 'certificate_url', 'label' => 'Certificate / acknowledgement URL', 'type' => T::URL, 'required' => false],
            ],

            'GST registration' => [
                ['key' => 'gstin', 'label' => 'GSTIN', 'type' => T::TEXT, 'required' => true],
                ['key' => 'trade_name', 'label' => 'Trade name', 'type' => T::TEXT, 'required' => false],
                ['key' => 'registration_type', 'label' => 'Registration type', 'type' => T::SELECT, 'required' => true, 'options' => [
                    ['value' => 'regular', 'label' => 'Regular'],
                    ['value' => 'composition', 'label' => 'Composition'],
                ]],
                ['key' => 'effective_from', 'label' => 'Effective from', 'type' => T::DATE, 'required' => true],
            ],

            'Bank / loan linkage' => [
                ['key' => 'bank_name', 'label' => 'Bank name', 'type' => T::TEXT, 'required' => true],
                ['key' => 'branch', 'label' => 'Branch', 'type' => T::TEXT, 'required' => false],
                ['key' => 'sanctioned_amount', 'label' => 'Sanctioned amount (₹)', 'type' => T::AMOUNT, 'required' => false],
                ['key' => 'account_number_last4', 'label' => 'Account number (last 4 digits)', 'type' => T::TEXT, 'required' => false, 'max' => 4],
            ],

            'Market linkage / order' => [
                ['key' => 'buyer_name', 'label' => 'Buyer / platform name', 'type' => T::TEXT, 'required' => true],
                ['key' => 'order_value', 'label' => 'Order value (₹)', 'type' => T::AMOUNT, 'required' => false],
                ['key' => 'order_date', 'label' => 'Order / linkage date', 'type' => T::DATE, 'required' => true],
                ['key' => 'brief', 'label' => 'Brief description', 'type' => T::TEXTAREA, 'required' => false],
            ],

            'Training / capacity building' => [
                ['key' => 'program_name', 'label' => 'Program / training name', 'type' => T::TEXT, 'required' => true],
                ['key' => 'provider', 'label' => 'Training provider', 'type' => T::TEXT, 'required' => false],
                ['key' => 'completed_on', 'label' => 'Completed on', 'type' => T::DATE, 'required' => true],
                ['key' => 'topics', 'label' => 'Topics covered', 'type' => T::MULTISELECT, 'required' => false, 'options' => [
                    ['value' => 'bookkeeping', 'label' => 'Bookkeeping'],
                    ['value' => 'digital', 'label' => 'Digital marketing'],
                    ['value' => 'compliance', 'label' => 'Compliance'],
                    ['value' => 'pitch', 'label' => 'Pitch / presentation'],
                ]],
            ],

            'Subsidy / scheme benefit' => [
                ['key' => 'scheme_name', 'label' => 'Scheme name', 'type' => T::TEXT, 'required' => true],
                ['key' => 'benefit_amount', 'label' => 'Benefit amount (₹)', 'type' => T::AMOUNT, 'required' => false],
                ['key' => 'disbursed_on', 'label' => 'Disbursed / approved on', 'type' => T::DATE, 'required' => false],
                ['key' => 'reference_id', 'label' => 'Reference / sanction ID', 'type' => T::TEXT, 'required' => false],
            ],

            'Branding / packaging support' => [
                ['key' => 'deliverable', 'label' => 'What was delivered', 'type' => T::SELECT, 'required' => true, 'options' => [
                    ['value' => 'logo', 'label' => 'Logo'],
                    ['value' => 'packaging', 'label' => 'Packaging design'],
                    ['value' => 'photography', 'label' => 'Product photography'],
                    ['value' => 'other', 'label' => 'Other'],
                ]],
                ['key' => 'vendor', 'label' => 'Vendor / agency', 'type' => T::TEXT, 'required' => false],
                ['key' => 'completion_notes', 'label' => 'Notes', 'type' => T::TEXTAREA, 'required' => false],
            ],

            'General — contact & consent' => [
                ['key' => 'contact_phone', 'label' => 'Alternate contact (10-digit)', 'type' => T::PHONE, 'required' => false],
                ['key' => 'contact_email', 'label' => 'Contact email', 'type' => T::EMAIL, 'required' => false],
                ['key' => 'consent_recorded', 'label' => 'Incubatee consent recorded', 'type' => T::CHECKBOX, 'required' => true],
                ['key' => 'remarks', 'label' => 'Remarks', 'type' => T::TEXTAREA, 'required' => false],
            ],

            'Minimal — reference only' => [
                ['key' => 'reference_note', 'label' => 'Reference / notes', 'type' => T::TEXTAREA, 'required' => false],
            ],

            'District level workshops' => [
                ['key' => 'workshop_title', 'label' => 'Workshop title', 'type' => T::TEXT, 'required' => true],
                ['key' => 'workshop_date', 'label' => 'Workshop date', 'type' => T::DATE, 'required' => true],
                ['key' => 'participants_count', 'label' => 'Participants count', 'type' => T::NUMBER, 'required' => false],
                ['key' => 'session_notes', 'label' => 'Session notes', 'type' => T::TEXTAREA, 'required' => false],
            ],

            'Support to Lakhpati Didis' => [
                ['key' => 'support_type', 'label' => 'Support type', 'type' => T::SELECT, 'required' => true, 'options' => [
                    ['value' => 'proposed_didi', 'label' => 'Proposed Lakhpati Didi'],
                    ['value' => 'potential_didi', 'label' => 'Potential Lakhpati Didi/SHG Member'],
                    ['value' => 'other', 'label' => 'Other'],
                ]],
                ['key' => 'beneficiary_count', 'label' => 'Beneficiary count', 'type' => T::NUMBER, 'required' => false],
                ['key' => 'support_date', 'label' => 'Support date', 'type' => T::DATE, 'required' => true],
                ['key' => 'remarks', 'label' => 'Remarks', 'type' => T::TEXTAREA, 'required' => false],
            ],

            'Call for Applications filled' => [
                ['key' => 'application_no', 'label' => 'Application number', 'type' => T::TEXT, 'required' => true],
                ['key' => 'submitted_on', 'label' => 'Submitted on', 'type' => T::DATE, 'required' => true],
                ['key' => 'contact_phone', 'label' => 'Contact phone (10-digit)', 'type' => T::PHONE, 'required' => false],
                ['key' => 'reference_file', 'label' => 'Reference file', 'type' => T::FILE, 'required' => false],
            ],

            'Incubatees onboarded' => [
                ['key' => 'batch_name', 'label' => 'Batch name', 'type' => T::TEXT, 'required' => true],
                ['key' => 'onboarded_on', 'label' => 'Onboarded on', 'type' => T::DATE, 'required' => true],
                ['key' => 'incubatee_count', 'label' => 'Incubatee count', 'type' => T::NUMBER, 'required' => false],
                ['key' => 'notes', 'label' => 'Notes', 'type' => T::TEXTAREA, 'required' => false],
            ],

            'Business Model Canvas' => [
                ['key' => 'session_date', 'label' => 'Session date', 'type' => T::DATE, 'required' => true],
                ['key' => 'canvas_completed', 'label' => 'Canvas completed', 'type' => T::CHECKBOX, 'required' => true],
                ['key' => 'mentor_name', 'label' => 'Mentor name', 'type' => T::TEXT, 'required' => false],
                ['key' => 'canvas_file', 'label' => 'Canvas file upload', 'type' => T::FILE, 'required' => false],
            ],

            'Business Registration' => [
                ['key' => 'registration_type', 'label' => 'Registration type', 'type' => T::SELECT, 'required' => true, 'options' => [
                    ['value' => 'udyam', 'label' => 'Udyam/MSME'],
                    ['value' => 'trade_license', 'label' => 'Trade License'],
                    ['value' => 'other', 'label' => 'Other'],
                ]],
                ['key' => 'registration_no', 'label' => 'Registration number', 'type' => T::TEXT, 'required' => true],
                ['key' => 'registration_date', 'label' => 'Registration date', 'type' => T::DATE, 'required' => true],
                ['key' => 'certificate_upload', 'label' => 'Certificate upload', 'type' => T::FILE, 'required' => false],
            ],

            'FSSAI registration / renewal' => [
                ['key' => 'fssai_number', 'label' => 'FSSAI number', 'type' => T::TEXT, 'required' => true],
                ['key' => 'application_type', 'label' => 'Application type', 'type' => T::SELECT, 'required' => true, 'options' => [
                    ['value' => 'new', 'label' => 'New registration'],
                    ['value' => 'renewal', 'label' => 'Renewal'],
                ]],
                ['key' => 'valid_till', 'label' => 'Valid till', 'type' => T::DATE, 'required' => false],
                ['key' => 'license_upload', 'label' => 'License upload', 'type' => T::FILE, 'required' => false],
            ],

            'GI Seller Registration' => [
                ['key' => 'platform_name', 'label' => 'Platform / portal name', 'type' => T::TEXT, 'required' => true],
                ['key' => 'seller_id', 'label' => 'Seller ID', 'type' => T::TEXT, 'required' => false],
                ['key' => 'registered_on', 'label' => 'Registered on', 'type' => T::DATE, 'required' => true],
                ['key' => 'proof_upload', 'label' => 'Proof upload', 'type' => T::FILE, 'required' => false],
            ],

            'Artisan Card' => [
                ['key' => 'card_number', 'label' => 'Artisan card number', 'type' => T::TEXT, 'required' => true],
                ['key' => 'issued_on', 'label' => 'Issued on', 'type' => T::DATE, 'required' => false],
                ['key' => 'issuing_authority', 'label' => 'Issuing authority', 'type' => T::TEXT, 'required' => false],
                ['key' => 'card_upload', 'label' => 'Card upload', 'type' => T::FILE, 'required' => false],
            ],

            'Trademark application filing' => [
                ['key' => 'tm_application_no', 'label' => 'Trademark application number', 'type' => T::TEXT, 'required' => true],
                ['key' => 'class_of_goods', 'label' => 'Class of goods/services', 'type' => T::TEXT, 'required' => false],
                ['key' => 'filed_on', 'label' => 'Filed on', 'type' => T::DATE, 'required' => true],
                ['key' => 'filing_receipt', 'label' => 'Filing receipt upload', 'type' => T::FILE, 'required' => false],
            ],

            'UTDB Registration' => [
                ['key' => 'utdb_registration_no', 'label' => 'UTDB registration number', 'type' => T::TEXT, 'required' => true],
                ['key' => 'registered_on', 'label' => 'Registered on', 'type' => T::DATE, 'required' => true],
                ['key' => 'status', 'label' => 'Status', 'type' => T::SELECT, 'required' => false, 'options' => [
                    ['value' => 'pending', 'label' => 'Pending'],
                    ['value' => 'approved', 'label' => 'Approved'],
                    ['value' => 'rejected', 'label' => 'Rejected'],
                ]],
                ['key' => 'certificate_upload', 'label' => 'Certificate upload', 'type' => T::FILE, 'required' => false],
            ],

            'Entrepreneurship sessions' => [
                ['key' => 'session_name', 'label' => 'Session name', 'type' => T::TEXT, 'required' => true],
                ['key' => 'session_date', 'label' => 'Session date', 'type' => T::DATE, 'required' => true],
                ['key' => 'attendance_count', 'label' => 'Attendance count', 'type' => T::NUMBER, 'required' => false],
                ['key' => 'topics_covered', 'label' => 'Topics covered', 'type' => T::TEXTAREA, 'required' => false],
            ],

            'Business Skills Sessions participation' => [
                ['key' => 'session_topic', 'label' => 'Session topic', 'type' => T::TEXT, 'required' => true],
                ['key' => 'session_date', 'label' => 'Session date', 'type' => T::DATE, 'required' => true],
                ['key' => 'participation_mode', 'label' => 'Participation mode', 'type' => T::SELECT, 'required' => false, 'options' => [
                    ['value' => 'online', 'label' => 'Online'],
                    ['value' => 'offline', 'label' => 'Offline'],
                    ['value' => 'hybrid', 'label' => 'Hybrid'],
                ]],
                ['key' => 'attendance_proof', 'label' => 'Attendance proof', 'type' => T::FILE, 'required' => false],
            ],

            'Incubatee Linked to Markets' => [
                ['key' => 'market_type', 'label' => 'Market type', 'type' => T::SELECT, 'required' => true, 'options' => [
                    ['value' => 'online', 'label' => 'Online'],
                    ['value' => 'offline', 'label' => 'Offline'],
                    ['value' => 'both', 'label' => 'Both'],
                ]],
                ['key' => 'platform_or_buyer', 'label' => 'Platform / buyer', 'type' => T::TEXT, 'required' => true],
                ['key' => 'linkage_date', 'label' => 'Linkage date', 'type' => T::DATE, 'required' => true],
                ['key' => 'order_value', 'label' => 'Order value (₹)', 'type' => T::AMOUNT, 'required' => false],
            ],

            'Convergence with Line Departments' => self::convergenceWithLineDepartments(),

            'Incubatees Pitch deck preparations' => [
                ['key' => 'deck_stage', 'label' => 'Deck stage', 'type' => T::SELECT, 'required' => true, 'options' => [
                    ['value' => 'draft', 'label' => 'Draft prepared'],
                    ['value' => 'reviewed', 'label' => 'Reviewed'],
                    ['value' => 'finalized', 'label' => 'Finalized'],
                ]],
                ['key' => 'prepared_on', 'label' => 'Prepared on', 'type' => T::DATE, 'required' => true],
                ['key' => 'mentor_feedback', 'label' => 'Mentor feedback', 'type' => T::TEXTAREA, 'required' => false],
                ['key' => 'deck_file', 'label' => 'Pitch deck file', 'type' => T::FILE, 'required' => false],
            ],

            'Pitchathon / Demo Day' => [
                ['key' => 'event_name', 'label' => 'Event name', 'type' => T::TEXT, 'required' => true],
                ['key' => 'event_date', 'label' => 'Event date', 'type' => T::DATE, 'required' => true],
                ['key' => 'result', 'label' => 'Result', 'type' => T::SELECT, 'required' => false, 'options' => [
                    ['value' => 'participated', 'label' => 'Participated'],
                    ['value' => 'shortlisted', 'label' => 'Shortlisted'],
                    ['value' => 'winner', 'label' => 'Winner'],
                ]],
                ['key' => 'event_proof', 'label' => 'Event proof upload', 'type' => T::FILE, 'required' => false],
            ],

            'Acceleration / co-incubation initiation' => [
                ['key' => 'partner_name', 'label' => 'Partner incubator/accelerator', 'type' => T::TEXT, 'required' => true],
                ['key' => 'initiated_on', 'label' => 'Initiated on', 'type' => T::DATE, 'required' => true],
                ['key' => 'program_name', 'label' => 'Program name', 'type' => T::TEXT, 'required' => false],
                ['key' => 'status', 'label' => 'Current status', 'type' => T::SELECT, 'required' => false, 'options' => [
                    ['value' => 'initiated', 'label' => 'Initiated'],
                    ['value' => 'ongoing', 'label' => 'Ongoing'],
                    ['value' => 'completed', 'label' => 'Completed'],
                ]],
            ],

            'Case Studies and Testimonials' => [
                ['key' => 'story_title', 'label' => 'Story title', 'type' => T::TEXT, 'required' => true],
                ['key' => 'story_type', 'label' => 'Story type', 'type' => T::SELECT, 'required' => true, 'options' => [
                    ['value' => 'case_study', 'label' => 'Case study'],
                    ['value' => 'testimonial', 'label' => 'Testimonial'],
                ]],
                ['key' => 'captured_on', 'label' => 'Captured on', 'type' => T::DATE, 'required' => false],
                ['key' => 'story_document', 'label' => 'Story document upload', 'type' => T::FILE, 'required' => false],
            ],

            'Social Media Post' => [
                ['key' => 'platform', 'label' => 'Platform', 'type' => T::SELECT, 'required' => true, 'options' => [
                    ['value' => 'facebook', 'label' => 'Facebook'],
                    ['value' => 'instagram', 'label' => 'Instagram'],
                    ['value' => 'youtube', 'label' => 'YouTube'],
                    ['value' => 'x', 'label' => 'X / Twitter'],
                    ['value' => 'other', 'label' => 'Other'],
                ]],
                ['key' => 'post_link', 'label' => 'Post link', 'type' => T::URL, 'required' => false],
                ['key' => 'posted_on', 'label' => 'Posted on', 'type' => T::DATE, 'required' => true],
                ['key' => 'creative_upload', 'label' => 'Creative upload', 'type' => T::FILE, 'required' => false],
            ],

            'Events / Seminars / Workshops' => [
                ['key' => 'event_title', 'label' => 'Event title', 'type' => T::TEXT, 'required' => true],
                ['key' => 'event_category', 'label' => 'Event category', 'type' => T::SELECT, 'required' => false, 'options' => [
                    ['value' => 'event', 'label' => 'Event'],
                    ['value' => 'seminar', 'label' => 'Seminar'],
                    ['value' => 'workshop', 'label' => 'Workshop'],
                ]],
                ['key' => 'event_date', 'label' => 'Event date', 'type' => T::DATE, 'required' => true],
                ['key' => 'participants_count', 'label' => 'Participants count', 'type' => T::NUMBER, 'required' => false],
            ],

            'Buyer Seller Meets' => [
                ['key' => 'meet_name', 'label' => 'Meet name', 'type' => T::TEXT, 'required' => true],
                ['key' => 'meet_date', 'label' => 'Meet date', 'type' => T::DATE, 'required' => true],
                ['key' => 'buyers_connected', 'label' => 'Buyers connected', 'type' => T::NUMBER, 'required' => false],
                ['key' => 'outcome_summary', 'label' => 'Outcome summary', 'type' => T::TEXTAREA, 'required' => false],
            ],
        ];
    }
}
