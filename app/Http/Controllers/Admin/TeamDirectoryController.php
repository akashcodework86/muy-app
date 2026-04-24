<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\District;
use App\Models\Designation;
use App\Models\User;
use Illuminate\Http\Request;
use Illuminate\View\View;

class TeamDirectoryController extends Controller
{
    public function index(Request $request): View
    {
        $users = User::query()
            ->with([
                'district:id,name',
                'hub:id,name',
                'designationRecord:id,name',
                'spocDistrictAssignments.district:id,name',
            ])
            ->where('is_active', true)
            ->whereIn('role', ['state_admin', 'state_staff', 'hub_admin', 'district_staff'])
            ->orderBy('name')
            ->get();

        $search = strtolower(trim((string) $request->query('q', '')));
        $roleFilter = (string) $request->query('role', '');
        $designationFilter = (int) $request->integer('designation_id');
        $districtFilter = (int) $request->integer('district_id');

        $users = $users->filter(function (User $user) use ($search, $roleFilter, $designationFilter, $districtFilter): bool {
            if ($roleFilter !== '' && $user->role !== $roleFilter) {
                return false;
            }
            if ($designationFilter > 0 && (int) ($user->designation_id ?? 0) !== $designationFilter) {
                return false;
            }
            if ($districtFilter > 0) {
                $isSameDistrict = (int) ($user->district_id ?? 0) === $districtFilter;
                $spocMapped = $user->spocDistrictAssignments->contains(fn ($a) => (int) ($a->district_id ?? 0) === $districtFilter);
                if (! $isSameDistrict && ! $spocMapped) {
                    return false;
                }
            }
            if ($search === '') {
                return true;
            }

            $districtNames = $user->spocDistrictAssignments
                ->map(fn ($a) => (string) ($a->district?->name ?? ''))
                ->filter()
                ->implode(' ');
            $blob = strtolower(trim(implode(' ', [
                (string) $user->name,
                (string) $user->email,
                (string) $user->phone,
                (string) ($user->designationRecord?->name ?? ''),
                (string) ($user->district?->name ?? ''),
                (string) ($user->hub?->name ?? ''),
                $districtNames,
            ])));

            return str_contains($blob, $search);
        })->values();

        $sectioned = [];
        foreach ($users as $user) {
            [$sectionKey, $sectionTitle, $sectionRank] = $this->resolveSection($user);
            if (! isset($sectioned[$sectionKey])) {
                $sectioned[$sectionKey] = [
                    'key' => $sectionKey,
                    'title' => $sectionTitle,
                    'rank' => $sectionRank,
                    'members' => [],
                ];
            }

            $mappedDistricts = $user->spocDistrictAssignments
                ->map(fn ($a) => (string) ($a->district?->name ?? ''))
                ->filter()
                ->values()
                ->all();

            $sectioned[$sectionKey]['members'][] = [
                'id' => (int) $user->id,
                'name' => (string) $user->name,
                'email' => (string) ($user->email ?? ''),
                'phone' => (string) ($user->phone ?? ''),
                'designation' => (string) ($user->designationRecord?->name ?? 'Unassigned'),
                'district' => (string) ($user->district?->name ?? ''),
                'role' => (string) $user->role,
                'avatar_url' => $user->avatarUrl(),
                'spoc_districts' => $mappedDistricts,
            ];
        }

        usort($sectioned, function (array $a, array $b): int {
            $byRank = ((int) $a['rank']) <=> ((int) $b['rank']);
            if ($byRank !== 0) {
                return $byRank;
            }

            return strcmp((string) $a['title'], (string) $b['title']);
        });
        $designationGroups = [];
        foreach ($sectioned as $section) {
            usort($section['members'], fn ($x, $y) => strcmp((string) $x['name'], (string) $y['name']));
            $designationGroups[] = $section;
        }

        $totalMembers = $users->count();
        $totalDesignations = count($designationGroups);
        $designations = Designation::query()->orderBy('name')->get(['id', 'name']);
        $districts = District::query()->orderBy('name')->get(['id', 'name']);
        $roles = User::query()
            ->where('is_active', true)
            ->distinct()
            ->orderBy('role')
            ->pluck('role')
            ->values();

        return view('admin.team.index', [
            'designationGroups' => $designationGroups,
            'totalMembers' => $totalMembers,
            'totalDesignations' => $totalDesignations,
            'designations' => $designations,
            'districts' => $districts,
            'roles' => $roles,
            'filters' => [
                'q' => (string) $request->query('q', ''),
                'role' => $roleFilter,
                'designation_id' => $designationFilter,
                'district_id' => $districtFilter,
            ],
        ]);
    }

    /**
     * @return array{0:string,1:string,2:int}
     */
    private function resolveSection(User $user): array
    {
        $designation = strtolower(trim((string) ($user->designationRecord?->name ?? '')));

        if ($user->role === 'state_admin') {
            return ['state-admin', 'State Admin', 10];
        }
        if ($user->role === 'state_staff') {
            return ['spocs', 'SPOCs (with district mapping)', 20];
        }
        if (str_contains($designation, 'it') || str_contains($designation, 'mis')) {
            return ['it-mis', 'IT / MIS', 30];
        }
        if ($user->role === 'hub_admin') {
            return ['hub-managers', 'Hub Managers', 40];
        }
        if (str_contains($designation, 'incubation manager')) {
            return ['incubation-managers', 'Incubation Managers', 50];
        }

        $title = $user->designationRecord?->name ?: ucfirst(str_replace('_', ' ', (string) $user->role));

        return ['designation-'.($user->designation_id ?: $user->role), (string) $title, 90];
    }
}

