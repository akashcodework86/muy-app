<?php

namespace App\Services;

use App\Models\CfaSubmission;
use Illuminate\Support\Str;

/**
 * Incubatee portal login uses the users.email field. Legacy rows often omit email;
 * we generate a deterministic placeholder address so Auth::attempt still works.
 */
class IncubateeLoginEmailResolver
{
    public static function forSubmission(CfaSubmission $submission): string
    {
        $payload = is_array($submission->payload) ? $submission->payload : [];
        $emailRaw = $payload['email'] ?? null;
        if (is_string($emailRaw) && trim($emailRaw) !== '') {
            return Str::lower(trim($emailRaw));
        }

        return 'incubatee-'.$submission->id.'@'.self::syntheticDomain();
    }

    public static function syntheticDomain(): string
    {
        $fromEnv = trim((string) config('incubatee.synthetic_email_domain', ''));
        if ($fromEnv !== '') {
            return $fromEnv;
        }

        $host = parse_url((string) config('app.url'), PHP_URL_HOST);

        return is_string($host) && $host !== '' ? $host : 'localhost';
    }
}
