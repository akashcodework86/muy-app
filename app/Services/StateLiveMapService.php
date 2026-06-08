<?php

namespace App\Services;

use App\Models\FiscalYear;
use App\Models\ServiceCase;
use App\Models\StaffCheckIn;
use App\Models\User;
use App\Support\StaffDailyCheckInAccess;
use Carbon\Carbon;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

class StateLiveMapService
{
    /**
     * @return array<string, mixed>
     */
    public function build(Carbon $date): array
    {
        $activeFy = FiscalYear::query()
            ->where('is_active', true)
            ->orderByDesc('starts_on')
            ->first();

        $districtRows = DB::table('districts as d')
            ->leftJoin('hubs as h', 'h.id', '=', 'd.hub_id')
            ->orderBy('d.sort_order')
            ->orderBy('d.name')
            ->get(['d.id', 'd.name', 'd.slug', 'h.name as hub_name']);

        $districtIds = $districtRows->pluck('id')->map(fn ($id) => (int) $id)->all();
        $cfaFy = $this->cfaCountsByDistrict($districtIds, $activeFy, null);
        $cfaToday = $this->cfaCountsByDistrict($districtIds, $activeFy, $date);
        $servicesFy = $this->serviceCountsByDistrict($districtIds, $activeFy, null);
        $servicesToday = $this->serviceCountsByDistrict($districtIds, $activeFy, $date);
        $staffTotals = $this->staffTotalsByDistrict($districtIds);
        $staffPresent = $this->staffPresentByDistrict($date, $districtIds);

        $districts = $districtRows->map(function ($row) use ($cfaFy, $cfaToday, $servicesFy, $servicesToday, $staffTotals, $staffPresent): array {
            $id = (int) $row->id;
            $name = (string) $row->name;

            return [
                'id' => $id,
                'name' => $name,
                'slug' => (string) $row->slug,
                'hub' => (string) ($row->hub_name ?? ''),
                'cfa_fy' => (int) ($cfaFy[$id] ?? 0),
                'cfa_today' => (int) ($cfaToday[$id] ?? 0),
                'services_fy' => (int) ($servicesFy[$id] ?? 0),
                'services_today' => (int) ($servicesToday[$id] ?? 0),
                'staff_present' => (int) ($staffPresent[$id] ?? 0),
                'staff_total' => (int) ($staffTotals[$id] ?? 0),
            ];
        })->values()->all();

        $staffPins = $this->staffPinsForDate($date);

        return [
            'date' => $date->toDateString(),
            'date_label' => $date->format('d M Y'),
            'fiscal_year' => $activeFy ? [
                'id' => (int) $activeFy->id,
                'name' => (string) $activeFy->name,
            ] : null,
            'updated_at' => now()->timezone(config('app.timezone'))->toIso8601String(),
            'districts' => $districts,
            'staff_pins' => $staffPins,
            'summary' => [
                'staff_on_map' => count($staffPins),
                'districts_with_check_ins' => collect($districts)->filter(fn (array $d) => $d['staff_present'] > 0)->count(),
                'cfa_today_state' => array_sum($cfaToday),
                'services_today_state' => array_sum($servicesToday),
            ],
        ];
    }

    /**
     * @param  list<int>  $districtIds
     * @return array<int, int>
     */
    private function cfaCountsByDistrict(array $districtIds, ?FiscalYear $activeFy, ?Carbon $day): array
    {
        if ($districtIds === [] || ! Schema::hasTable('cfa_submissions')) {
            return [];
        }

        $query = DB::table('cfa_submissions')
            ->whereIn('district_id', $districtIds);

        if ($activeFy !== null) {
            $query->where('fiscal_year_id', (int) $activeFy->id);
        }

        if ($day !== null) {
            $query->whereDate('created_at', $day->toDateString());
        }

        return $query
            ->selectRaw('district_id, COUNT(*) as total')
            ->groupBy('district_id')
            ->pluck('total', 'district_id')
            ->map(fn ($v) => (int) $v)
            ->all();
    }

    /**
     * Approved services attributed to district via linked CFA submission.
     *
     * @param  list<int>  $districtIds
     * @return array<int, int>
     */
    private function serviceCountsByDistrict(array $districtIds, ?FiscalYear $activeFy, ?Carbon $day): array
    {
        if (
            $districtIds === []
            || ! Schema::hasTable('service_cases')
            || ! Schema::hasTable('cfa_submissions')
            || ! Schema::hasColumn('service_cases', 'status')
        ) {
            return [];
        }

        $query = DB::table('service_cases as sc')
            ->join('cfa_submissions as cs', 'cs.id', '=', 'sc.cfa_submission_id')
            ->whereIn('cs.district_id', $districtIds)
            ->where('sc.status', ServiceCase::STATUS_APPROVED);

        if ($day !== null) {
            $approvedAtExpr = Schema::hasColumn('service_cases', 'approved_at')
                ? 'COALESCE(sc.approved_at, sc.completed_at, sc.created_at)'
                : (Schema::hasColumn('service_cases', 'completed_at') ? 'COALESCE(sc.completed_at, sc.created_at)' : 'sc.created_at');
            $query->whereDate(DB::raw($approvedAtExpr), $day->toDateString());
        } elseif ($activeFy !== null && $activeFy->starts_on && $activeFy->ends_on) {
            $approvedAtExpr = Schema::hasColumn('service_cases', 'approved_at')
                ? 'COALESCE(sc.approved_at, sc.completed_at, sc.created_at)'
                : (Schema::hasColumn('service_cases', 'completed_at') ? 'COALESCE(sc.completed_at, sc.created_at)' : 'sc.created_at');
            $query->whereBetween(DB::raw($approvedAtExpr), [
                Carbon::parse($activeFy->starts_on)->startOfDay(),
                Carbon::parse($activeFy->ends_on)->endOfDay(),
            ]);
        }

        return $query
            ->selectRaw('cs.district_id as district_id, COUNT(sc.id) as total')
            ->groupBy('cs.district_id')
            ->pluck('total', 'district_id')
            ->map(fn ($v) => (int) $v)
            ->all();
    }

    /**
     * Active staff who must check in, grouped by assigned district.
     *
     * @param  list<int>  $districtIds
     * @return array<int, int>
     */
    private function staffTotalsByDistrict(array $districtIds): array
    {
        if ($districtIds === []) {
            return [];
        }

        return User::query()
            ->where('is_active', true)
            ->whereIn('district_id', $districtIds)
            ->whereNotIn('role', ['state_admin', 'incubatee'])
            ->selectRaw('district_id, COUNT(*) as total')
            ->groupBy('district_id')
            ->pluck('total', 'district_id')
            ->map(fn ($v) => (int) $v)
            ->all();
    }

    /**
     * @param  list<int>  $districtIds
     * @return array<int, int>
     */
    private function staffPresentByDistrict(Carbon $date, array $districtIds): array
    {
        if ($districtIds === [] || ! Schema::hasTable('staff_check_ins')) {
            return [];
        }

        return DB::table('staff_check_ins as sci')
            ->join('users as u', 'u.id', '=', 'sci.user_id')
            ->whereDate('sci.check_in_date', $date->toDateString())
            ->whereIn('u.district_id', $districtIds)
            ->where('u.is_active', true)
            ->selectRaw('u.district_id as district_id, COUNT(DISTINCT sci.user_id) as total')
            ->groupBy('u.district_id')
            ->pluck('total', 'district_id')
            ->map(fn ($v) => (int) $v)
            ->all();
    }

    /**
     * @return list<array<string, mixed>>
     */
    private function staffPinsForDate(Carbon $date): array
    {
        if (! Schema::hasTable('staff_check_ins')) {
            return [];
        }

        $roleLabels = [
            'district_staff' => 'District staff',
            'hub_admin' => 'Hub admin',
            'state_staff' => 'State staff (SPOC)',
        ];

        $checkIns = StaffCheckIn::query()
            ->whereDate('check_in_date', $date->toDateString())
            ->with([
                'user.designationRecord:id,name',
                'user.hub:id,name',
                'user.district:id,name',
            ])
            ->orderBy('marked_at')
            ->get();

        return $checkIns
            ->filter(function (StaffCheckIn $checkIn): bool {
                return $checkIn->user !== null && StaffDailyCheckInAccess::isRequired($checkIn->user);
            })
            ->map(function (StaffCheckIn $checkIn) use ($roleLabels): array {
                $user = $checkIn->user;
                $designation = (string) ($user->designationRecord?->name ?? '');
                $isFieldCoordinator = str_contains(strtolower($designation), 'field coordinator');

                return [
                    'user_id' => (int) $user->id,
                    'name' => (string) $user->name,
                    'role' => (string) $user->role,
                    'role_label' => $roleLabels[$user->role] ?? (string) $user->role,
                    'designation' => $designation !== '' ? $designation : '—',
                    'is_field_coordinator' => $isFieldCoordinator,
                    'hub' => (string) ($user->hub?->name ?? '—'),
                    'district' => (string) ($user->district?->name ?? '—'),
                    'lat' => (float) $checkIn->latitude,
                    'lng' => (float) $checkIn->longitude,
                    'accuracy_m' => $checkIn->accuracy_m !== null ? (float) $checkIn->accuracy_m : null,
                    'marked_at' => $checkIn->marked_at->timezone(config('app.timezone'))->format('g:i A'),
                    'maps_url' => $checkIn->googleMapsUrl(),
                ];
            })
            ->values()
            ->all();
    }
}
