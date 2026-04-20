<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Services\TeamPerformanceService;
use Illuminate\Http\Request;
use Illuminate\View\View;

class TeamPerformanceController extends Controller
{
    public function __construct(private TeamPerformanceService $service) {}

    public function index(Request $request): View
    {
        $fyId = $request->integer('fy') ?: null;
        $data = $this->service->buildTree($fyId);

        return view('admin.team-performance.index', $data);
    }
}
