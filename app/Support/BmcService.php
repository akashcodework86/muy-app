<?php

namespace App\Support;

use App\Models\Service;

final class BmcService
{
    public static function isBmc(?Service $service): bool
    {
        if ($service === null) {
            return false;
        }

        $code = strtolower(trim((string) $service->code));
        $name = strtolower(trim((string) $service->name));
        $deliverable = strtolower(trim((string) ($service->deliverable?->code ?? '')));

        if (in_array($code, ['bmc_canvas', 'bmc', 'business_model_canvas'], true)) {
            return true;
        }
        if ($deliverable === 'bmc') {
            return true;
        }

        return str_contains($name, 'business model canvas')
            || (bool) preg_match('/\bbmc\b/', $name);
    }
}
