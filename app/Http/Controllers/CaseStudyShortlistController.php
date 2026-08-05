<?php

namespace App\Http\Controllers;

use App\Models\CaseStudyShortlist;
use App\Models\User;
use App\Services\CaseStudyShortlistCandidateCatalog;
use App\Services\CaseStudyShortlistManager;
use App\Services\CaseStudyShortlistNominationManager;
use App\Services\CaseStudyShortlistProfileService;
use App\Support\CaseStudyShortlistAccess;
use Carbon\Carbon;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Schema;
use Illuminate\Validation\Rule;
use Illuminate\View\View;

class CaseStudyShortlistController extends Controller
{
    public function __construct(
        private readonly CaseStudyShortlistCandidateCatalog $catalog,
        private readonly CaseStudyShortlistManager $manager,
        private readonly CaseStudyShortlistProfileService $profiles,
        private readonly CaseStudyShortlistNominationManager $nominations,
    ) {}

    public function index(Request $request): View
    {
        /** @var User $user */
        $user = $request->user();
        abort_unless(CaseStudyShortlistAccess::canView($user), 403);

        $routePrefix = $this->routePrefix($user);
        $districts = $this->catalog->accessibleDistricts($user);
        $requestedDistrictId = (int) $request->integer('district_id');
        $districtId = $user->role === 'district_staff'
            ? (int) $user->district_id
            : ($districts->contains('id', $requestedDistrictId) ? $requestedDistrictId : 0);
        $month = $this->month($request->input('month'));
        $programYear = array_key_exists((string) $request->input('program_year'), CaseStudyShortlistCandidateCatalog::YEARS)
            ? (string) $request->input('program_year')
            : '2026-27';
        $filters = [
            'program_year' => $programYear,
            'q' => trim((string) $request->input('q', '')),
            'block' => trim((string) $request->input('block', '')),
            'gender' => trim((string) $request->input('gender', '')),
            'category' => trim((string) $request->input('category', '')),
            'stage' => trim((string) $request->input('stage', '')),
            'district_id' => $districtId,
            'month' => $month->format('Y-m'),
            'record_program_year' => array_key_exists((string) $request->input('record_program_year'), CaseStudyShortlistCandidateCatalog::YEARS)
                ? (string) $request->input('record_program_year') : '',
        ];

        $migrationMissing = ! Schema::hasTable('case_study_shortlists')
            || ! Schema::hasTable('case_study_shortlist_remarks')
            || ! Schema::hasTable('case_study_shortlist_nominations')
            || ! Schema::hasTable('case_study_shortlist_nomination_events');
        $rows = collect();
        $candidates = collect();
        $activeCount = 0;
        if (! $migrationMissing) {
            $query = CaseStudyShortlist::query()->with(['district:id,name', 'creator:id,name,role', 'removedBy:id,name', 'remarks.author:id,name,role', 'nominations.nominatedBy:id,name']);
            $this->scopeQuery($query, $user);
            if ($districtId > 0) {
                $query->where('district_id', $districtId);
            }
            $query->whereDate('shortlist_month', $month->toDateString());
            if ($filters['record_program_year'] !== '') {
                $query->where('program_year', $filters['record_program_year']);
            }
            if ($request->boolean('include_removed')) {
                // Retain removed nominations as an explicit audit view.
            } else {
                $query->whereNull('removed_at');
            }
            $rows = $query->latest('created_at')->paginate(25)->withQueryString();

            if ($user->role === 'district_staff') {
                $activeCount = CaseStudyShortlist::query()
                    ->where('district_id', $districtId)
                    ->whereDate('shortlist_month', now()->startOfMonth()->toDateString())
                    ->whereNull('removed_at')->count();
            }

            if (CaseStudyShortlistAccess::canCreate($user) && $month->isSameMonth(now())) {
                $candidates = $this->catalog->search($user, $filters);
            }
        }

        return view('case-study-shortlists.index', compact(
            'user', 'routePrefix', 'districts', 'filters', 'month', 'rows', 'candidates',
            'activeCount', 'migrationMissing'
        ) + [
            'programYears' => CaseStudyShortlistCandidateCatalog::YEARS,
            'monthlyLimit' => CaseStudyShortlistManager::MONTHLY_LIMIT,
        ]);
    }

    public function store(Request $request): RedirectResponse
    {
        /** @var User $user */
        $user = $request->user();
        $validated = $request->validate([
            'source' => ['required', 'in:phase1,phase2,phase3'],
            'source_application_id' => ['required', 'integer', 'min:1'],
        ]);
        $this->manager->create($user, $validated['source'], (int) $validated['source_application_id']);

        return back()->with('status', 'Incubatee shortlisted successfully. It is now visible to the hub and state admins.');
    }

    public function show(Request $request, CaseStudyShortlist $caseStudyShortlist): View
    {
        /** @var User $user */
        $user = $request->user();
        abort_unless(CaseStudyShortlistAccess::canAccessDistrict($user, (int) $caseStudyShortlist->district_id), 403);
        $caseStudyShortlist->load([
            'district:id,name,hub_id', 'creator:id,name,role', 'remarks.author:id,name,role',
            'nominations.nominatedBy:id,name', 'nominations.events.actor:id,name,role',
        ]);

        return view('case-study-shortlists.profile', [
            'shortlist' => $caseStudyShortlist,
            'profile' => $this->profiles->build($caseStudyShortlist),
            'nominationServices' => (array) config('case_study_shortlists.nomination_services', []),
            'activeNominations' => $caseStudyShortlist->nominations->filter->isActive()->keyBy('service_code'),
            'canNominate' => $user->role === 'state_admin',
            'routePrefix' => $this->routePrefix($user),
        ]);
    }

    public function updateNominations(Request $request, CaseStudyShortlist $caseStudyShortlist): RedirectResponse
    {
        /** @var User $user */
        $user = $request->user();
        $serviceCodes = array_keys((array) config('case_study_shortlists.nomination_services', []));
        $validated = $request->validate([
            'services' => ['nullable', 'array'],
            'services.*' => ['string', 'max:64', Rule::in($serviceCodes)],
            'nomination_note' => ['nullable', 'string', 'max:2000'],
        ]);
        $profile = $this->profiles->build($caseStudyShortlist);
        $this->nominations->sync(
            $user,
            $caseStudyShortlist,
            array_values($validated['services'] ?? []),
            (array) ($profile['received'] ?? []),
            $validated['nomination_note'] ?? null,
        );

        return back()->with('status', 'Service nominations updated successfully.');
    }

    public function remark(Request $request, CaseStudyShortlist $caseStudyShortlist): RedirectResponse
    {
        /** @var User $user */
        $user = $request->user();
        $validated = $request->validate(['remark' => ['required', 'string', 'max:2000']]);
        $this->manager->addRemark($user, $caseStudyShortlist, $validated['remark']);

        return back()->with('status', 'Remark added.');
    }

    public function destroy(Request $request, CaseStudyShortlist $caseStudyShortlist): RedirectResponse
    {
        /** @var User $user */
        $user = $request->user();
        $validated = $request->validate(['removal_reason' => ['nullable', 'string', 'max:1000']]);
        $this->manager->remove($user, $caseStudyShortlist, $validated['removal_reason'] ?? null);

        return back()->with('status', 'Shortlist entry removed. Its audit history has been retained.');
    }

    private function scopeQuery(Builder $query, User $user): void
    {
        if ($user->role === 'district_staff') {
            $query->where('district_id', (int) $user->district_id);
        } elseif ($user->role === 'hub_admin') {
            $query->whereIn('district_id', $this->catalog->accessibleDistricts($user)->pluck('id'));
        }
    }

    private function routePrefix(User $user): string
    {
        return match ($user->role) {
            'state_admin' => 'admin.case-study-shortlists',
            'hub_admin' => 'hub.case-study-shortlists',
            default => 'staff.case-study-shortlists',
        };
    }

    private function month(mixed $value): Carbon
    {
        try {
            return Carbon::createFromFormat('Y-m', (string) $value)->startOfMonth();
        } catch (\Throwable) {
            return now()->startOfMonth();
        }
    }
}
