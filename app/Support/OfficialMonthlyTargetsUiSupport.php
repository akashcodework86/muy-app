<?php

namespace App\Support;

use App\Models\FiscalYear;
use App\Models\User;
use App\Services\AppSettingsService;

final class OfficialMonthlyTargetsUiSupport
{
    public static function targetsAllocationEditable(?User $user, AppSettingsService $appSettings): bool
    {
        return $user?->role === 'state_admin'
            && $appSettings->isEnabled('targets.allocation_editable');
    }

    /**
     * @return array{
     *     readOnlyAudience: bool,
     *     targetsAllocationEditable: bool,
     *     pageRoute: string,
     *     exportRoute: string,
     *     applyRoute: ?string,
     *     statePageRoute: string,
     *     districtPageRoute: string,
     *     hubPageRoute: string,
     *     fyTargetsNavLabel: string,
     *     pageTitleSuffix: string,
     * }
     */
    public static function viewContext(?User $user, AppSettingsService $appSettings, ?FiscalYear $fiscalYear, string $page): array
    {
        $readOnly = $user?->role !== 'state_admin';
        $editable = self::targetsAllocationEditable($user, $appSettings);
        $fyLabel = self::fyTargetsNavLabel($fiscalYear);

        $routes = self::routesFor($user);

        $pageRoute = match ($page) {
            'state' => $routes['state'],
            'district' => $routes['district'],
            'hub' => $routes['hub'],
            default => $routes['state'],
        };

        $exportRoute = match ($page) {
            'state' => $routes['state_export'],
            'district' => $routes['district_export'],
            'hub' => $routes['hub_export'],
            default => $routes['state_export'],
        };

        $applyRoute = $editable ? match ($page) {
            'state' => $routes['state_apply'],
            'district' => $routes['district_apply'],
            'hub' => $routes['hub_apply'],
            default => $routes['state_apply'],
        } : null;

        $pageTitleSuffix = match ($page) {
            'state' => 'State',
            'district' => 'District',
            'hub' => 'Hub',
            default => 'State',
        };

        return [
            'readOnlyAudience' => $readOnly,
            'targetsAllocationEditable' => $editable,
            'pageRoute' => $pageRoute,
            'exportRoute' => $exportRoute,
            'applyRoute' => $applyRoute,
            'statePageRoute' => $routes['state'],
            'districtPageRoute' => $routes['district'],
            'hubPageRoute' => $routes['hub'],
            'fyTargetsNavLabel' => $fyLabel,
            'pageTitleSuffix' => $pageTitleSuffix,
        ];
    }

    public static function fyTargetsNavLabel(?FiscalYear $fiscalYear = null): string
    {
        $name = $fiscalYear?->name
            ?? FiscalYear::phase3Default()?->name
            ?? '2026-27';

        return $name.' FY Targets';
    }

    /**
     * @return array{
     *     state: string,
     *     state_export: string,
     *     state_apply: string,
     *     district: string,
     *     district_export: string,
     *     district_apply: string,
     *     hub: string,
     *     hub_export: string,
     *     hub_apply: string,
     * }
     */
    private static function routesFor(?User $user): array
    {
        return match ($user?->role) {
            'state_staff' => [
                'state' => 'spoc.fy-targets.state',
                'state_export' => 'spoc.fy-targets.state.export',
                'state_apply' => 'admin.targets.official-state-monthly.apply',
                'district' => 'spoc.fy-targets.district',
                'district_export' => 'spoc.fy-targets.district.export',
                'district_apply' => 'admin.targets.official-district-monthly.apply',
                'hub' => 'spoc.fy-targets.hub',
                'hub_export' => 'spoc.fy-targets.hub.export',
                'hub_apply' => 'admin.targets.official-hub-distribution-monthly.apply',
            ],
            'hub_admin' => [
                'state' => 'hub.fy-targets.state',
                'state_export' => 'hub.fy-targets.state.export',
                'state_apply' => 'admin.targets.official-state-monthly.apply',
                'district' => 'hub.fy-targets.district',
                'district_export' => 'hub.fy-targets.district.export',
                'district_apply' => 'admin.targets.official-district-monthly.apply',
                'hub' => 'hub.fy-targets.hub',
                'hub_export' => 'hub.fy-targets.hub.export',
                'hub_apply' => 'admin.targets.official-hub-distribution-monthly.apply',
            ],
            'district_staff' => [
                'state' => 'staff.fy-targets.state',
                'state_export' => 'staff.fy-targets.state.export',
                'state_apply' => 'admin.targets.official-state-monthly.apply',
                'district' => 'staff.fy-targets.district',
                'district_export' => 'staff.fy-targets.district.export',
                'district_apply' => 'admin.targets.official-district-monthly.apply',
                'hub' => 'staff.fy-targets.hub',
                'hub_export' => 'staff.fy-targets.hub.export',
                'hub_apply' => 'admin.targets.official-hub-distribution-monthly.apply',
            ],
            default => [
                'state' => 'admin.targets.official-state-monthly',
                'state_export' => 'admin.targets.official-state-monthly.export',
                'state_apply' => 'admin.targets.official-state-monthly.apply',
                'district' => 'admin.targets.official-district-monthly',
                'district_export' => 'admin.targets.official-district-monthly.export',
                'district_apply' => 'admin.targets.official-district-monthly.apply',
                'hub' => 'admin.targets.official-hub-distribution-monthly',
                'hub_export' => 'admin.targets.official-hub-distribution-monthly.export',
                'hub_apply' => 'admin.targets.official-hub-distribution-monthly.apply',
            ],
        };
    }
}
