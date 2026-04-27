<?php

namespace App\Http\Controllers\Hub;

use App\Http\Controllers\Controller;
use App\Models\CfaSubmission;
use App\Models\District;
use App\Models\User;
use Illuminate\Http\Request;
use Illuminate\View\View;

class HubApplicationsController extends Controller
{
    public function index(Request $request): View
    {
        $user = $request->user();
        abort_unless($user && $user->role === 'hub_admin' && $user->hub_id, 403);

        $hubId = (int) $user->hub_id;
        $districts = District::query()
            ->where('hub_id', $hubId)
            ->orderBy('sort_order')
            ->orderBy('name')
            ->get(['id', 'name']);
        $districtIds = $districts->pluck('id')->map(fn ($id) => (int) $id)->all();

        $staff = User::query()
            ->where('role', 'district_staff')
            ->where('hub_id', $hubId)
            ->orderBy('name')
            ->get(['id', 'name', 'district_id', 'referral_token']);

        $staffId = $request->integer('staff_id') ?: null;
        $districtId = $request->integer('district_id') ?: null;
        $source = trim((string) $request->query('source', ''));
        $q = trim((string) $request->query('q', ''));
        $from = trim((string) $request->query('from', ''));
        $to = trim((string) $request->query('to', ''));

        $allowedSources = ['referral', 'walk_in', 'admin', 'import', 'other'];
        if (! in_array($source, $allowedSources, true)) {
            $source = '';
        }

        $query = CfaSubmission::query()
            ->with(['district:id,name', 'referralUser:id,name,referral_token'])
            ->when($districtIds !== [], fn ($q) => $q->whereIn('district_id', $districtIds), fn ($q) => $q->whereRaw('1 = 0'))
            ->when($districtId, fn ($q) => $q->where('district_id', (int) $districtId))
            ->when($staffId, fn ($q) => $q->where('referral_user_id', (int) $staffId))
            ->when($source !== '', fn ($q) => $q->where('source', $source))
            ->when($q !== '', function ($qBuilder) use ($q): void {
                $qBuilder->where(function ($inner) use ($q): void {
                    $inner->where('application_no', 'like', "%{$q}%")
                        ->orWhere('applicant_name', 'like', "%{$q}%")
                        ->orWhere('phone', 'like', "%{$q}%");
                });
            })
            ->when($from !== '', fn ($q) => $q->whereDate('created_at', '>=', $from))
            ->when($to !== '', fn ($q) => $q->whereDate('created_at', '<=', $to))
            ->orderByDesc('created_at');

        $applications = $query->paginate(40)->withQueryString();

        $sourceCounts = CfaSubmission::query()
            ->when($districtIds !== [], fn ($q) => $q->whereIn('district_id', $districtIds), fn ($q) => $q->whereRaw('1 = 0'))
            ->selectRaw('COALESCE(source, "unknown") as src, COUNT(*) as total')
            ->groupBy('src')
            ->pluck('total', 'src');

        return view('hub.applications.index', [
            'applications' => $applications,
            'districts' => $districts,
            'staff' => $staff,
            'sourceCounts' => $sourceCounts,
            'filters' => [
                'staff_id' => $staffId,
                'district_id' => $districtId,
                'source' => $source,
                'q' => $q,
                'from' => $from,
                'to' => $to,
            ],
        ]);
    }
}

