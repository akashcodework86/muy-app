<?php

namespace App\Support;

/**
 * Pulls registration / licence / service numbers from heterogeneous phase payloads.
 */
final class ServiceRegistrationNumberExtractor
{
    /**
     * @param  array<string, mixed>|null  $payload
     */
    public static function fromPayload(?array $payload, string $reference = '', string $metricHint = '', string $applicationNo = ''): string
    {
        $payload = is_array($payload) ? $payload : [];
        $hint = mb_strtolower(trim($metricHint));

        $preferred = match (true) {
            str_contains($hint, 'udyam') => ['registration_number', 'udyam_number', 'udyam_registration_number', 'r', 'u'],
            str_contains($hint, 'fssai') => ['fssai_number', 'fssai_licence_number', 'fssai_license_number', 'registration_number', 'f'],
            str_contains($hint, 'gst') => ['gstin', 'gst_number', 'gst_in', 'registration_number', 'g'],
            str_contains($hint, 'artisan') => ['card_number', 'artisan_card_number', 'artisan_number', 'registration_number', 'a'],
            str_contains($hint, 'market') => ['partner_or_buyer', 'reference_number', 'registration_number'],
            str_contains($hint, 'converg') || str_contains($hint, 'utdb') || str_contains($hint, 'firm') || str_contains($hint, 'cooperat') || str_contains($hint, 'pmfme') || str_contains($hint, 'msy') => [
                'firm_registration_number',
                'registration_number',
                'scheme_registration_number',
                'cin_or_llpin',
                'r',
                'service_number',
            ],
            default => [],
        };

        foreach ($preferred as $key) {
            $value = self::usable((string) ($payload[$key] ?? ''), $applicationNo, $hint);
            if ($value !== '') {
                return $value;
            }
        }

        foreach ([
            'firm_registration_number',
            'registration_number',
            'service_number',
            'udyam_number',
            'udyam_registration_number',
            'fssai_number',
            'fssai_licence_number',
            'gstin',
            'gst_number',
            'card_number',
            'artisan_card_number',
            'cin_or_llpin',
            'tm_application_no',
            'seller_id',
            'f',
            'g',
            'a',
            'r',
        ] as $key) {
            $value = self::usable((string) ($payload[$key] ?? ''), $applicationNo, $hint);
            if ($value !== '') {
                return $value;
            }
        }

        // Short keys like "u" are often type labels ("Udyam Registration"), not numbers.
        $u = self::usable((string) ($payload['u'] ?? ''), $applicationNo, $hint);
        if ($u !== '' && preg_match('/\d/', $u) === 1) {
            return $u;
        }

        return self::usable($reference, $applicationNo, $hint);
    }

    /**
     * Sanitize a raw service/registration number from any phase source.
     */
    public static function usable(string $value, string $applicationNo = '', string $metricHint = ''): string
    {
        $value = self::normalize($value);
        if ($value === '') {
            return '';
        }

        $app = self::normalize($applicationNo);
        if ($app !== '' && strcasecmp($value, $app) === 0) {
            return '';
        }

        // Application numbers mistakenly stored as service_number.
        if (preg_match('/^RBI\d+$/i', $value) === 1) {
            return '';
        }

        // Scheme names / channel labels, not registration IDs.
        if (preg_match('/^(PMFME|MSY(?:\s*2\.0)?|PMEGP|REAP|Convergence|Other Convergence Support|Udyam Registration|GST|FSSAI|Artisan Card)$/i', $value) === 1) {
            return '';
        }

        // Free-text notes (no dense ID).
        $wordCount = str_word_count($value);
        if ($wordCount >= 5 && preg_match('/\d/', $value) !== 1) {
            return '';
        }
        if ($wordCount >= 8) {
            return '';
        }

        $hint = mb_strtolower($metricHint);
        $isConvergence = str_contains($hint, 'converg')
            || str_contains($hint, 'utdb')
            || str_contains($hint, 'firm')
            || str_contains($hint, 'cooperat')
            || str_contains($hint, 'pmfme')
            || str_contains($hint, 'msy')
            || str_contains($hint, 'scheme');

        if (self::looksLikeRegistration($value, $isConvergence ? 'convergence' : $hint)) {
            return $value;
        }

        return '';
    }

    public static function normalize(string $value): string
    {
        $value = trim(html_entity_decode($value, ENT_QUOTES | ENT_HTML5, 'UTF-8'));
        if ($value === '' || $value === '—' || strcasecmp($value, 'null') === 0 || strcasecmp($value, 'n/a') === 0) {
            return '';
        }

        $value = preg_replace(
            '/^(application\s*(?:no|number|id)?\s*[:\-]?\s*|reg(?:istration)?\s*(?:no|number)?\s*[:\-]?\s*|gstin\s*[:\-]?\s*|udyam\s*(?:no|number)?\s*[:\-]?\s*)/iu',
            '',
            $value
        ) ?? $value;
        $value = trim($value);

        if (mb_strlen($value) > 80 || str_contains($value, "\n")) {
            return '';
        }

        return $value;
    }

    public static function looksLikeRegistration(string $value, string $metricHint = ''): bool
    {
        $v = self::normalize($value);
        if ($v === '') {
            return false;
        }

        if (preg_match('/^RBI\d+$/i', $v) === 1) {
            return false;
        }

        if (preg_match('/^(UDYAM|CRALC|ART|UKFIRM|UTHS|GSTIN|HS\/)/i', $v) === 1) {
            return true;
        }

        // Convergence / scheme application IDs are often short numeric codes.
        if (str_contains(mb_strtolower($metricHint), 'converg') && preg_match('/^\d{3,12}$/', $v) === 1) {
            return true;
        }

        // Dense alphanumerics with at least one digit (GSTIN-like, PMFME ids, etc.).
        if (preg_match('/^[A-Z0-9][A-Z0-9\\-\\/]{4,}$/i', $v) === 1 && preg_match('/\d/', $v) === 1) {
            return true;
        }

        // e.g. SBI/JD 2024-25/82
        if (preg_match('/\d/', $v) === 1 && preg_match('/^[A-Z0-9][A-Z0-9\\-\\/\\s]{3,}$/i', $v) === 1 && str_word_count($v) <= 4) {
            return true;
        }

        return false;
    }
}
