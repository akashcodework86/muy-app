<?php

namespace App\Http\Controllers\Hub;

use App\Http\Controllers\Controller;
use App\Services\HubAdminDashboardService;
use Illuminate\Http\Request;
use Illuminate\View\View;

class HubOnboardingInsightController extends Controller
{
    public function index(Request $request, HubAdminDashboardService $dashboardService): View
    {
        return view('hub.onboarding-insight.index', $dashboardService->onboardingDistrictInsight($request->user()));
    }
}

