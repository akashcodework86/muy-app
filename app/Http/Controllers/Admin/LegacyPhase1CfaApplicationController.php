<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use Illuminate\Pagination\LengthAwarePaginator;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Illuminate\View\View;

class LegacyPhase1CfaApplicationController extends Controller
{
    /**
     * Read-only list of CFA rows from the legacy Phase 1 DB (`tblapplication`).
     */
    public function index(Request $request): View
    {
        if ((string) config('database.connections.legacy_phase1.database', '') === '') {
            return view('admin.phase1-cfa.index', [
                'rows'                => $this->emptyPaginator(),
                'phase1Unavailable'   => true,
                'phase1MissingTables' => false,
            ]);
        }

        try {
            $hasTable = Schema::connection('legacy_phase1')->hasTable('tblapplication');
        } catch (\Exception $e) {
            return view('admin.phase1-cfa.index', [
                'rows'                => $this->emptyPaginator(),
                'phase1Unavailable'   => true,
                'phase1MissingTables' => false,
            ]);
        }

        if (! $hasTable) {
            return view('admin.phase1-cfa.index', [
                'rows'                => $this->emptyPaginator(),
                'phase1Unavailable'   => false,
                'phase1MissingTables' => true,
            ]);
        }

        $query = DB::connection('legacy_phase1')
            ->table('tblapplication')
            ->select([
                'ID as legacy_id',
                'ApplicationNumber as application_no',
                'FullName as full_name',
                'MobileNumber as mobile_number',
                'hub as hub_name',
                'City as city_name',
                'status as application_status',
                'ApplicationDate as application_date',
            ]);

        if ($request->filled('search')) {
            $search = '%'.$request->input('search').'%';
            $query->where(function ($q) use ($search) {
                $q->where('FullName', 'like', $search)
                    ->orWhere('MobileNumber', 'like', $search)
                    ->orWhere('ApplicationNumber', 'like', $search);
            });
        }

        if ($request->filled('hub')) {
            $query->where('hub', $request->input('hub'));
        }

        $query->orderByDesc('ApplicationDate')->orderByDesc('ID');

        $rows = $query->paginate(100)->withQueryString();

        $hubs = DB::connection('legacy_phase1')
            ->table('tblapplication')
            ->whereNotNull('hub')
            ->where('hub', '!=', '')
            ->distinct()
            ->orderBy('hub')
            ->pluck('hub')
            ->values()
            ->all();

        return view('admin.phase1-cfa.index', [
            'rows'                => $rows,
            'hubs'                => $hubs,
            'phase1Unavailable'   => false,
            'phase1MissingTables' => false,
        ]);
    }

    private function emptyPaginator(): LengthAwarePaginator
    {
        return new LengthAwarePaginator([], 0, 100, 1, [
            'path' => request()->url(),
            'query' => request()->query(),
        ]);
    }
}
