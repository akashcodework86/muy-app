<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\User;
use Illuminate\View\View;

class TeamDirectoryController extends Controller
{
    public function index(): View
    {
        $users = User::query()
            ->with(['district:id,name', 'hub:id,name', 'designationRecord:id,name'])
            ->where('is_active', true)
            ->whereIn('role', ['state_admin', 'hub_admin', 'district_staff'])
            ->orderBy('name')
            ->get();

        $designationGroups = $users
            ->groupBy(fn (User $user) => (string) ($user->designationRecord?->name ?: 'Unassigned'))
            ->map(fn ($group) => $group->values())
            ->sortKeys()
            ->all();

        $totalMembers = $users->count();
        $totalDesignations = count($designationGroups);

        return view('admin.team.index', [
            'designationGroups' => $designationGroups,
            'totalMembers' => $totalMembers,
            'totalDesignations' => $totalDesignations,
        ]);
    }
}

