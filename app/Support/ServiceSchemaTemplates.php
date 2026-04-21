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
        ];
    }
}
