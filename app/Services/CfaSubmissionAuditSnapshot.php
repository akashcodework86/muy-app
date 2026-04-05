<?php

namespace App\Services;

use App\Models\CfaSubmission;
use Illuminate\Support\Str;

class CfaSubmissionAuditSnapshot
{
    public const ACTION_UPDATED = 'cfa_submission.updated';

    /**
     * Keys we store in audit before/after (compact) with human-facing labels for staff.
     *
     * @var array<string, string>
     */
    public static function fieldLabels(): array
    {
        return [
            'application_no' => 'Application number',
            'applicant_name' => 'Applicant / SHG–CBO name',
            'phone' => 'Mobile number',
            'category' => 'Applicant category',
            'guardian_name' => 'Father / husband name',
            'shg_cbo_name' => 'SHG / CBO name',
            'gender' => 'Gender',
            'dob' => 'Date of birth / formation date',
            'caste' => 'Social category',
            'email' => 'Email',
            'village' => 'Village / address',
            'block' => 'Block',
            'pincode' => 'PIN code',
            'education' => 'Education',
            'is_registered' => 'Enterprise registered',
            'training_received' => 'Training received',
            'turnover_last_fy' => 'Turnover last FY (₹)',
            'form_stage' => 'Business stage (auto)',
            'business_category' => 'Business category',
            'product' => 'Product',
            'other_product' => 'Other product',
            'financial_support' => 'Financial support next year',
            'location_type' => 'Location (rural / urban)',
            'training_mode' => 'Preferred training mode',
            'info_source' => 'Source of information',
            'migrated_for_employment' => 'Migrated for employment',
            'business_vision' => 'Business vision (5 years)',
            'challenges' => 'Challenges selected',
            'expectations' => 'Expectations from MUY / RBI',
        ];
    }

    /**
     * Compact snapshot for audit before/after (not full payload).
     *
     * @return array<string, mixed>
     */
    public static function compact(CfaSubmission $submission): array
    {
        $p = is_array($submission->payload) ? $submission->payload : [];
        $out = [];

        foreach (array_keys(self::fieldLabels()) as $key) {
            $out[$key] = match ($key) {
                'application_no' => $submission->application_no,
                'applicant_name' => $submission->applicant_name,
                'phone' => $submission->phone,
                'challenges' => isset($p['challenges']) && is_array($p['challenges'])
                    ? implode(', ', $p['challenges'])
                    : null,
                'expectations' => isset($p['expectations']) && is_array($p['expectations'])
                    ? implode(', ', $p['expectations'])
                    : null,
                'business_vision' => isset($p['business_vision'])
                    ? Str::limit((string) $p['business_vision'], 220)
                    : null,
                default => $p[$key] ?? null,
            };
        }

        return $out;
    }

    /**
     * Plain-language lines: "Label: old value → new value" (for on-screen list).
     *
     * @return list<string>
     */
    public static function humanDiffLines(array $before, array $after): array
    {
        $labels = self::fieldLabels();
        $lines = [];

        foreach (array_keys($labels) as $key) {
            if ($key === 'application_no') {
                continue;
            }
            $b = $before[$key] ?? null;
            $a = $after[$key] ?? null;
            if ($b === $a) {
                continue;
            }
            $label = $labels[$key];
            $lines[] = $label.': '.self::fmt($b).' → '.self::fmt($a);
        }

        return $lines;
    }

    /**
     * Short human-readable summary for the audit log row (plain text, no JSON keys).
     */
    public static function describeDiff(array $before, array $after): string
    {
        $lines = self::humanDiffLines($before, $after);
        if ($lines === []) {
            return 'Form was saved. Some details may have been updated (same as last saved values in the summary fields we track).';
        }

        $text = implode(' · ', $lines);

        return Str::limit($text, 480, '…');
    }

    private static function fmt(mixed $v): string
    {
        if ($v === null || $v === '') {
            return '—';
        }
        if (is_bool($v)) {
            return $v ? 'Yes' : 'No';
        }
        $s = is_scalar($v) ? (string) $v : json_encode($v, JSON_UNESCAPED_UNICODE);
        $s = trim($s);

        return Str::limit($s, 100, '…');
    }
}
