<?php

namespace App\Http\Controllers\Hub;

use App\Http\Controllers\Controller;
use App\Models\District;
use App\Models\Hub;
use App\Services\PendingActionsReportService;
use Illuminate\Http\Request;
use Illuminate\View\View;

class HubPendingActionsController extends Controller
{
    public function __construct(
        private readonly PendingActionsReportService $report,
    ) {}

    public function index(Request $request): View
    {
        $user = $request->user();
        abort_unless($user && $user->role === 'hub_admin' && $user->hub_id, 403);

        $hubId = (int) $user->hub_id;
        $hub = Hub::query()->find($hubId);
        $districtIds = District::query()
            ->where('hub_id', $hubId)
            ->orderBy('sort_order')
            ->orderBy('name')
            ->pluck('id')
            ->map(fn ($id) => (int) $id)
            ->all();

        $filterSpocId = (int) $request->query('spoc_id', 0);
        $filterDistrictId = (int) $request->query('district_id', 0);
        if ($filterDistrictId > 0 && ! in_array($filterDistrictId, $districtIds, true)) {
            $filterDistrictId = 0;
        }

        $data = $this->report->build($districtIds, $filterSpocId, $filterDistrictId);

        return view('admin.pending-actions.index', array_merge($data, [
            'pageRoute' => 'hub.pending-actions.index',
            'scopeLabel' => $hub?->name ? 'Hub: '.$hub->name : 'Your hub',
        ]));
    }
}
