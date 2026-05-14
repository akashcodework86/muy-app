<?php

namespace App\Http\Middleware;

use App\Services\AppSettingsService;
use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

/**
 * When a Phase 3 attendance module is turned off for district staff, hide URLs too
 * (not only the top bar). State admin toggles live in AppSettingsService.
 */
class EnsureDistrictStaffPhase3AttendanceNavVisible
{
    private const MODULE_TO_KEY = [
        'training_package' => 'staff_nav.training_package.visible',
        'technical_training' => 'staff_nav.technical_training.visible',
        'eap_edp_session' => 'staff_nav.eap_edp_session.visible',
        'district_workshop' => 'staff_nav.district_workshop.visible',
    ];

    public function handle(Request $request, Closure $next, string $module): Response
    {
        $user = $request->user();
        if (! $user || $user->role !== 'district_staff') {
            return $next($request);
        }

        $key = self::MODULE_TO_KEY[$module] ?? null;
        if (! $key) {
            return $next($request);
        }

        if (! app(AppSettingsService::class)->isEnabled($key)) {
            return redirect()
                ->route('dashboard')
                ->with('status', 'This section is turned off by the state administrator.');
        }

        return $next($request);
    }
}
