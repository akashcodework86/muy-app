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

        $stateTeam = $users->where('role', 'state_admin')->values();
        $hubManagers = $users->where('role', 'hub_admin')->values();
        $districtTeam = $users->where('role', 'district_staff')->values();

        return view('admin.team.index', [
            'stateTeam' => $stateTeam,
            'hubManagers' => $hubManagers,
            'districtTeam' => $districtTeam,
        ]);
    }
}

