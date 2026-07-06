<?php

namespace App\Http\Controllers;

use App\Models\AccelerationServiceItemMedia;
use App\Models\AccelerationServiceSession;
use App\Models\FiscalYear;
use App\Models\User;
use App\Services\AccelerationServiceRecorder;
use App\Services\AccelerationServicesIncubateeService;
use App\Support\AccelerationServicesAccess;
use App\Support\AccelerationServicesDeliverablesSupport;
use App\Support\AccelerationServicesOptions;
use Carbon\Carbon;
use Illuminate\Contracts\View\View;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\Storage;
use Symfony\Component\HttpFoundation\StreamedResponse;

class AccelerationServiceController extends Controller
{
    public function __construct(
        private readonly AccelerationServiceRecorder $recorder,
        private readonly AccelerationServicesIncubateeService $incubatees,
    ) {}

    public function dashboard(Request $request): View
    {
        $user = $request->user();
        abort_unless(AccelerationServicesAccess::canViewDashboard($user), 403);

        $migrationMissing = ! Schema::hasTable('acceleration_service_sessions');
        $canSubmit = AccelerationServicesAccess::canSubmit($user);
        $isAdmin = $user->role === 'state_admin';

        $filters = [
            'q' => trim((string) $request->query('q', '')),
            'from' => (string) $request->query('from', ''),
            'to' => (string) $request->query('to', ''),
        ];

        $prefillApplicant = null;
        $prefillFromSessionId = (int) $request->query('from_session', 0);
        if ($prefillFromSessionId > 0 && ! $migrationMissing) {
            $fromSession = AccelerationServiceSession::query()->find($prefillFromSessionId);
            if ($fromSession) {
                $prefillApplicant = $this->incubatees->findPhase1Applicant((int) $fromSession->legacy_phase1_application_id);
            }
        }

        $rows = collect();
        $totals = ['sessions' => 0, 'initiations_fy' => 0, 'buyer_seller_ticks' => 0];

        if (! $migrationMissing) {
            $query = AccelerationServiceSession::query()->withCount('items');
            if ($user->role === 'state_staff') {
                $query->where('submitted_by_user_id', (int) $user->id);
            }

            if ($filters['q'] !== '') {
                $like = '%'.str_replace(['\\', '%', '_'], ['\\\\', '\\%', '\\_'], $filters['q']).'%';
                $query->where(function ($q) use ($like): void {
                    $q->where('applicant_name', 'like', $like)
                        ->orWhere('application_no', 'like', $like)
                        ->orWhere('phone', 'like', $like)
                        ->orWhere('district_name', 'like', $like)
                        ->orWhere('submitted_by_name', 'like', $like);
                });
            }
            if ($filters['from'] !== '') {
                $query->whereDate('service_date', '>=', $filters['from']);
            }
            if ($filters['to'] !== '') {
                $query->whereDate('service_date', '<=', $filters['to']);
            }

            $totals['sessions'] = (int) (clone $query)->count();

            $activeFy = FiscalYear::query()->where('is_active', true)->orderByDesc('starts_on')->first();
            $periodFrom = $activeFy?->starts_on ? Carbon::parse($activeFy->starts_on) : null;
            $periodTo = $activeFy?->ends_on ? Carbon::parse($activeFy->ends_on) : null;

            $totals['initiations_fy'] = AccelerationServicesDeliverablesSupport::countUniqueInitiations($periodFrom, $periodTo);
            $totals['buyer_seller_ticks'] = AccelerationServicesDeliverablesSupport::countBuyerSellerMeets($periodFrom, $periodTo);

            $rows = $query
                ->orderByDesc('service_date')
                ->orderByDesc('id')
                ->paginate(25)
                ->withQueryString();
        }

        return view('acceleration-services.dashboard', [
            'rows' => $rows,
            'migrationMissing' => $migrationMissing,
            'isAdminView' => $isAdmin,
            'canSubmit' => $canSubmit,
            'currentRole' => (string) $user->role,
            'filters' => $filters,
            'totals' => $totals,
            'catalog' => AccelerationServicesOptions::allSections(),
            'legacyPhase1Available' => (string) config('database.connections.legacy_phase1.database', '') !== '',
            'prefillApplicant' => $prefillApplicant,
            'prefillFromSessionId' => $prefillFromSessionId,
            'dashboardRoute' => $isAdmin
                ? 'admin.acceleration-services.dashboard'
                : 'spoc.acceleration-services.dashboard',
            'storeRoute' => 'spoc.acceleration-services.store',
            'exportRoute' => $isAdmin
                ? 'admin.acceleration-services.export'
                : 'spoc.acceleration-services.export',
            'showRoute' => $isAdmin
                ? 'admin.acceleration-services.show'
                : 'spoc.acceleration-services.show',
            'searchRoute' => 'spoc.acceleration-services.incubatees.search',
            'historyRoute' => 'spoc.acceleration-services.incubatees.history',
            'mediaRoute' => $isAdmin
                ? 'admin.acceleration-services.media'
                : 'spoc.acceleration-services.media',
        ]);
    }

    public function store(Request $request): RedirectResponse
    {
        $user = $this->submitterOrAbort($request);

        $result = $this->recorder->store($request, $user);
        $session = $result['session'];
        $count = (int) $result['item_count'];

        $message = $session->counts_for_7_2
            ? $count.' service(s) logged. Counted as new 7.2 initiation for this FY.'
            : $count.' follow-up service(s) logged (7.2 initiation already recorded this FY).';

        return redirect()
            ->route('spoc.acceleration-services.dashboard')
            ->with('status', $message);
    }

    public function show(Request $request, AccelerationServiceSession $accelerationSession): View
    {
        $user = $request->user();
        abort_unless(AccelerationServicesAccess::canViewDashboard($user), 403);
        $this->assertCanAccessSession($user, $accelerationSession);

        $accelerationSession->load(['items.media', 'submitter:id,name']);

        $isAdmin = $user->role === 'state_admin';

        return view('acceleration-services.show', [
            'session' => $accelerationSession,
            'isAdminView' => $isAdmin,
            'canSubmit' => AccelerationServicesAccess::canSubmit($user),
            'canDelete' => AccelerationServicesAccess::canDelete($user, $accelerationSession),
            'dashboardRoute' => $isAdmin
                ? 'admin.acceleration-services.dashboard'
                : 'spoc.acceleration-services.dashboard',
            'destroyRoute' => $isAdmin ? null : 'spoc.acceleration-services.destroy',
            'addServicesRoute' => AccelerationServicesAccess::canSubmit($user)
                ? 'spoc.acceleration-services.dashboard'
                : null,
            'mediaRoute' => $isAdmin
                ? 'admin.acceleration-services.media'
                : 'spoc.acceleration-services.media',
        ]);
    }

    public function destroy(Request $request, AccelerationServiceSession $accelerationSession): RedirectResponse
    {
        $user = $request->user();
        abort_unless(AccelerationServicesAccess::canDelete($user, $accelerationSession), 403);

        DB::transaction(function () use ($accelerationSession): void {
            $accelerationSession->load(['items.media']);
            foreach ($accelerationSession->items as $item) {
                foreach ($item->media as $media) {
                    Storage::disk((string) $media->disk)->delete((string) $media->path);
                    $media->delete();
                }
                $item->delete();
            }
            $accelerationSession->delete();
        });

        $this->recomputeFirstInitiationFlags($accelerationSession->incubatee_key, $accelerationSession->fiscal_year_id);

        return redirect()
            ->route('spoc.acceleration-services.dashboard')
            ->with('status', 'Acceleration session deleted.');
    }

    public function export(Request $request): StreamedResponse
    {
        $user = $request->user();
        abort_unless(AccelerationServicesAccess::canViewDashboard($user), 403);

        $query = AccelerationServiceSession::query()->with('items');
        if ($user->role === 'state_staff') {
            $query->where('submitted_by_user_id', (int) $user->id);
        }

        if ($request->filled('from')) {
            $query->whereDate('service_date', '>=', (string) $request->query('from'));
        }
        if ($request->filled('to')) {
            $query->whereDate('service_date', '<=', (string) $request->query('to'));
        }

        $rows = $query->orderByDesc('service_date')->orderByDesc('id')->get();
        $filename = 'acceleration-services-'.now()->format('Ymd_His').'.csv';

        return response()->streamDownload(function () use ($rows): void {
            $out = fopen('php://output', 'w');
            if ($out === false) {
                return;
            }

            fputcsv($out, [
                'Session ID',
                'Service date',
                'Applicant',
                'Application no',
                'District',
                'Onboarding',
                '7.2 initiation',
                'Services',
                'Submitted by',
                'Submitted at',
            ]);

            foreach ($rows as $row) {
                $labels = $row->items->pluck('item_label')->implode('; ');
                fputcsv($out, [
                    $row->id,
                    $row->service_date?->format('Y-m-d'),
                    $row->applicant_name,
                    $row->application_no,
                    $row->district_name,
                    $row->onboard_label,
                    $row->counts_for_7_2 ? 'Yes' : 'Follow-up',
                    $labels,
                    $row->submitted_by_name,
                    $row->created_at?->format('Y-m-d H:i'),
                ]);
            }

            fclose($out);
        }, $filename, ['Content-Type' => 'text/csv; charset=UTF-8']);
    }

    public function searchIncubatees(Request $request): JsonResponse
    {
        $this->submitterOrAbort($request);

        $searchRequest = Request::create('/', 'GET', ['search' => $request->query('q', '')]);

        return response()->json([
            'ok' => true,
            'applicants' => $this->incubatees->search($searchRequest),
            'legacy_available' => (string) config('database.connections.legacy_phase1.database', '') !== '',
        ]);
    }

    public function incubateeHistory(Request $request): JsonResponse
    {
        abort_unless(AccelerationServicesAccess::canViewDashboard($request->user()), 403);

        $legacyPhase1ApplicationId = (int) $request->query('legacy_phase1_application_id', 0);
        $key = trim((string) $request->query('incubatee_key', ''));

        if ($legacyPhase1ApplicationId <= 0 && preg_match('/^p1:(\d+)$/', $key, $matches)) {
            $legacyPhase1ApplicationId = (int) $matches[1];
        }

        if ($legacyPhase1ApplicationId <= 0) {
            return response()->json([
                'ok' => true,
                'services' => [],
            ]);
        }

        if ($key === '') {
            $key = \App\Support\AccelerationServicesOptions::incubateeKey($legacyPhase1ApplicationId);
        }

        return response()->json([
            'ok' => true,
            'services' => $this->incubatees->allServicesGiven($legacyPhase1ApplicationId, $key),
        ]);
    }

    public function downloadMedia(Request $request, AccelerationServiceItemMedia $accelerationMedia)
    {
        $user = $request->user();
        abort_unless(AccelerationServicesAccess::canViewDashboard($user), 403);

        $accelerationMedia->load('item.session');
        $session = $accelerationMedia->item?->session;
        if (! $session) {
            abort(404);
        }
        $this->assertCanAccessSession($user, $session);

        $disk = Storage::disk((string) $accelerationMedia->disk);
        if (! $disk->exists((string) $accelerationMedia->path)) {
            abort(404);
        }

        return $disk->response(
            (string) $accelerationMedia->path,
            (string) $accelerationMedia->original_name,
            $request->boolean('inline') ? ['Content-Disposition' => 'inline'] : []
        );
    }

    private function submitterOrAbort(Request $request): User
    {
        $user = $request->user();
        abort_unless(AccelerationServicesAccess::canSubmit($user), 403);

        return $user;
    }

    private function assertCanAccessSession(User $user, AccelerationServiceSession $session): void
    {
        if ($user->role === 'state_admin') {
            return;
        }

        if ($user->role === 'state_staff' && (int) $session->submitted_by_user_id === (int) $user->id) {
            return;
        }

        abort(403);
    }

    private function recomputeFirstInitiationFlags(string $incubateeKey, ?int $fiscalYearId): void
    {
        if ($incubateeKey === '' || ! $fiscalYearId || ! Schema::hasTable('acceleration_service_sessions')) {
            return;
        }

        DB::table('acceleration_service_sessions')
            ->where('incubatee_key', $incubateeKey)
            ->where('fiscal_year_id', $fiscalYearId)
            ->update(['counts_for_7_2' => false]);

        $firstId = DB::table('acceleration_service_sessions')
            ->where('incubatee_key', $incubateeKey)
            ->where('fiscal_year_id', $fiscalYearId)
            ->orderBy('service_date')
            ->orderBy('id')
            ->value('id');

        if ($firstId) {
            DB::table('acceleration_service_sessions')
                ->where('id', $firstId)
                ->update(['counts_for_7_2' => true]);
        }
    }
}
