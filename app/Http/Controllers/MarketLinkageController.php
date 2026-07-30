<?php

namespace App\Http\Controllers;

use App\Models\CfaSubmission;
use App\Models\District;
use App\Models\MarketLinkagePartner;
use App\Models\MarketLinkageSubmission;
use App\Models\ServiceCase;
use App\Models\User;
use App\Services\AppSettingsService;
use App\Services\Deliverables\Exports\DeliverablesExcelSupport;
use App\Services\LegacyApplicationServiceCaseSupport;
use App\Services\MarketLinkagePartnerCatalogService;
use App\Services\MarketLinkages\MarketLinkageDashboardExcelExport;
use App\Services\MarketLinkageWorkflowService;
use App\Support\MarketLinkageAccess;
use App\Support\MarketLinkageUnifiedListingSupport;
use Illuminate\Contracts\View\View;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Http\UploadedFile;
use Illuminate\Pagination\LengthAwarePaginator;
use Illuminate\Support\Carbon;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\Storage;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;
use Symfony\Component\HttpFoundation\StreamedResponse;

class MarketLinkageController extends Controller
{
    public function __construct(
        private LegacyApplicationServiceCaseSupport $legacyApplications,
        private AppSettingsService $settings,
        private MarketLinkagePartnerCatalogService $partnerCatalog,
        private MarketLinkageWorkflowService $workflow,
    ) {}

    public function create(Request $request): View
    {
        $staff = $this->staffOrAbort($request);
        $submissions = $this->eligibleSubmissions($staff)->get();
        $legacyRows = $this->legacyRowsForStaff($staff);
        $submissionIds = $submissions->pluck('id')->map(fn ($id) => (int) $id)->all();
        $legacyIds = $legacyRows->pluck('id')->map(fn ($id) => (int) $id)->all();

        return view('market-linkages.form', [
            'submissions' => $submissions,
            'legacyRows' => $legacyRows,
            'defaultCfaSubmissionId' => (int) $request->query('cfa_submission_id', 0),
            'defaultLegacyApplicationId' => (int) $request->query('legacy_application_id', 0),
            'migrationMissing' => ! Schema::hasTable('market_linkage_submissions'),
            'storeRoute' => 'staff.market-linkages.store',
            'dashboardRoute' => 'staff.market-linkages.dashboard',
            'showRoute' => 'staff.market-linkages.show',
            'priorMarketLinkageJson' => $this->priorMarketLinkageJson($submissionIds, $legacyIds),
            'partnerNameOptions' => $this->partnerCatalog->options(),
        ]);
    }

    public function store(Request $request): RedirectResponse
    {
        $staff = $this->staffOrAbort($request);

        if (! Schema::hasTable('market_linkage_submissions')) {
            return redirect()
                ->route('staff.market-linkages.create')
                ->withErrors(['partners' => 'Market linkage tables are missing. Please run migrations first.']);
        }

        $validated = $request->validate([
            'cfa_submission_id' => ['nullable', 'integer', 'required_without:legacy_application_id'],
            'legacy_application_id' => ['nullable', 'integer', 'required_without:cfa_submission_id'],
            'partners' => ['required', 'array', 'min:1', 'max:20'],
            'partners.*.partner_name' => ['required', 'string', 'max:191'],
            'partners.*.linkage_mode' => ['required', 'string', Rule::in([
                MarketLinkageSubmission::LINKAGE_ONLINE,
                MarketLinkageSubmission::LINKAGE_OFFLINE,
            ])],
            'partners.*.linkage_date' => ['required', 'date'],
            'partners.*.link_url' => ['nullable', 'string', 'max:2048'],
            'partners.*.link' => ['nullable', 'string', 'max:2048'],
            'partners.*.bill_document' => ['nullable', 'file', 'max:5120', 'mimes:pdf,jpg,jpeg,png,webp'],
        ]);

        $validated['partners'] = $this->normalizeAndValidatePartnerLinkUrls($validated['partners']);

        $cfaId = (int) ($validated['cfa_submission_id'] ?? 0);
        $legacyId = (int) ($validated['legacy_application_id'] ?? 0);
        if ($cfaId > 0 && $legacyId > 0) {
            throw ValidationException::withMessages([
                'cfa_submission_id' => 'Choose either a Phase 3 incubatee or a legacy application, not both.',
            ]);
        }

        $incubatee = $this->resolveIncubateeSnapshot($staff, $cfaId, $legacyId);
        $districtId = (int) ($staff->district_id ?: 0);
        abort_unless($districtId > 0, 422, 'District assignment is required.');

        $submission = DB::transaction(function () use ($staff, $validated, $incubatee, $districtId, $cfaId, $legacyId, $request): MarketLinkageSubmission {
            $submission = MarketLinkageSubmission::query()->create([
                'submitted_by_user_id' => (int) $staff->id,
                'submitted_by_name' => (string) $staff->name,
                'district_id' => $districtId,
                'district_name' => (string) ($staff->district?->name ?? ''),
                'cfa_submission_id' => $cfaId > 0 ? $cfaId : null,
                'legacy_application_id' => $legacyId > 0 ? $legacyId : null,
                'incubatee_name' => $incubatee['name'],
                'application_no' => $incubatee['application_no'],
            ]);

            foreach (array_values($validated['partners']) as $index => $row) {
                $partner = MarketLinkagePartner::query()->create([
                    'market_linkage_submission_id' => (int) $submission->id,
                    'partner_name' => trim((string) $row['partner_name']),
                    'linkage_mode' => (string) $row['linkage_mode'],
                    'linkage_date' => (string) $row['linkage_date'],
                    'link_url' => $row['link_url'] ?? null,
                    'sort_order' => $index,
                ]);

                /** @var UploadedFile|null $file */
                $file = $request->file('partners.'.$index.'.bill_document');
                if ($file instanceof UploadedFile) {
                    $stored = $this->storePartnerDocument($file, $submission, $partner);
                    $partner->update($stored);
                }
            }

            return $submission->load('partners');
        });

        $this->workflow->submitForApproval($submission, (int) $staff->id);

        $this->partnerCatalog->registerNames(
            array_map(fn (array $row) => trim((string) ($row['partner_name'] ?? '')), $validated['partners']),
            (int) $staff->id,
        );

        return redirect()
            ->route('staff.services.index', ['status' => ServiceCase::STATUS_PENDING_APPROVAL])
            ->with('status', 'Market linkage submitted for SPOC approval ('.count($validated['partners']).' partner(s)).');
    }

    public function edit(Request $request, MarketLinkageSubmission $marketLinkage): View|RedirectResponse
    {
        $staff = $this->staffOrAbort($request);
        MarketLinkageAccess::canAccessSubmission($staff, $marketLinkage);
        abort_unless($marketLinkage->canBeEditedByStaff(), 403, 'This submission cannot be edited now.');
        abort_unless((int) $marketLinkage->submitted_by_user_id === (int) $staff->id, 403);

        $marketLinkage->load('partners');

        return view('market-linkages.form', [
            'submissions' => collect(),
            'legacyRows' => collect(),
            'defaultCfaSubmissionId' => (int) ($marketLinkage->cfa_submission_id ?? 0),
            'defaultLegacyApplicationId' => (int) ($marketLinkage->legacy_application_id ?? 0),
            'migrationMissing' => false,
            'storeRoute' => 'staff.market-linkages.update',
            'dashboardRoute' => 'staff.services.index',
            'showRoute' => 'staff.market-linkages.show',
            'priorMarketLinkageJson' => ['cfa' => [], 'legacy' => []],
            'partnerNameOptions' => $this->partnerCatalog->options(),
            'editingSubmission' => $marketLinkage,
            'isEdit' => true,
        ]);
    }

    public function update(Request $request, MarketLinkageSubmission $marketLinkage): RedirectResponse
    {
        $staff = $this->staffOrAbort($request);
        MarketLinkageAccess::canAccessSubmission($staff, $marketLinkage);
        abort_unless($marketLinkage->canBeEditedByStaff(), 403, 'This submission cannot be edited now.');
        abort_unless((int) $marketLinkage->submitted_by_user_id === (int) $staff->id, 403);

        $validated = $request->validate([
            'partners' => ['required', 'array', 'min:1', 'max:20'],
            'partners.*.partner_name' => ['required', 'string', 'max:191'],
            'partners.*.linkage_mode' => ['required', 'string', Rule::in([
                MarketLinkageSubmission::LINKAGE_ONLINE,
                MarketLinkageSubmission::LINKAGE_OFFLINE,
            ])],
            'partners.*.linkage_date' => ['required', 'date'],
            'partners.*.link_url' => ['nullable', 'string', 'max:2048'],
            'partners.*.link' => ['nullable', 'string', 'max:2048'],
            'partners.*.bill_document' => ['nullable', 'file', 'max:5120', 'mimes:pdf,jpg,jpeg,png,webp'],
        ]);

        $validated['partners'] = $this->normalizeAndValidatePartnerLinkUrls($validated['partners']);

        DB::transaction(function () use ($marketLinkage, $validated, $request): void {
            foreach ($marketLinkage->partners as $partner) {
                if ($partner->hasDocument()) {
                    $disk = Storage::disk((string) ($partner->document_disk ?: 'local'));
                    if ($disk->exists((string) $partner->document_path)) {
                        $disk->delete((string) $partner->document_path);
                    }
                }
            }
            $marketLinkage->partners()->delete();

            foreach (array_values($validated['partners']) as $index => $row) {
                $partner = MarketLinkagePartner::query()->create([
                    'market_linkage_submission_id' => (int) $marketLinkage->id,
                    'partner_name' => trim((string) $row['partner_name']),
                    'linkage_mode' => (string) $row['linkage_mode'],
                    'linkage_date' => (string) $row['linkage_date'],
                    'link_url' => $row['link_url'] ?? null,
                    'sort_order' => $index,
                ]);

                /** @var UploadedFile|null $file */
                $file = $request->file('partners.'.$index.'.bill_document');
                if ($file instanceof UploadedFile) {
                    $stored = $this->storePartnerDocument($file, $marketLinkage, $partner);
                    $partner->update($stored);
                }
            }
        });

        $marketLinkage->refresh()->load('partners');
        $this->workflow->submitForApproval($marketLinkage, (int) $staff->id);

        $this->partnerCatalog->registerNames(
            array_map(fn (array $row) => trim((string) ($row['partner_name'] ?? '')), $validated['partners']),
            (int) $staff->id,
        );

        return redirect()
            ->route('staff.services.index', ['status' => ServiceCase::STATUS_PENDING_APPROVAL])
            ->with('status', 'Market linkage resubmitted for SPOC approval.');
    }

    public function dashboard(Request $request): View
    {
        $user = $request->user();
        abort_unless(MarketLinkageAccess::canViewDashboard($user), 403);

        if (! Schema::hasTable('market_linkage_submissions')) {
            return $this->dashboardView($request, collect(), true, $this->defaultFilters($request), [], $this->emptyDashboardStats());
        }

        $filters = $this->validatedFilters($request);
        $submissions = $this->dashboardSubmissionsQuery($user, $filters)->get();

        $grouped = $this->groupSubmissionsByIncubatee($submissions, $filters, $user);
        $grouped = $this->mergeOrphanServiceCaseGroups($grouped, $filters, $user);
        $stats = $this->computeDashboardStatsFromGroups($grouped);
        $rows = $this->paginateGroupedIncubatees($request, $grouped, 25);

        $districtCounts = $this->districtCounts($user, $filters);

        return $this->dashboardView($request, $rows, false, $filters, $districtCounts, $stats);
    }

    public function show(Request $request, MarketLinkageSubmission $marketLinkage): View
    {
        $user = $request->user();
        abort_unless(MarketLinkageAccess::canViewDashboard($user), 403);
        MarketLinkageAccess::canAccessSubmission($user, $marketLinkage);

        $marketLinkage->load(['partners', 'submitter:id,name', 'district:id,name', 'spoc:id,name', 'approver:id,name', 'rejector:id,name']);

        $isAdmin = $user->role === 'state_admin';
        $staffListRoute = $user->role === 'district_staff' ? 'staff.services.index' : null;
        $routes = $this->marketLinkageRoutes($user);

        return view('market-linkages.show', [
            'submission' => $marketLinkage,
            'currentRole' => (string) $user->role,
            'dashboardRoute' => $routes['dashboard'],
            'createRoute' => MarketLinkageAccess::canSubmit($user) ? 'staff.market-linkages.create' : null,
            'documentRoutePrefix' => $routes['document'],
            'staffListRoute' => $staffListRoute,
            'editRoute' => MarketLinkageAccess::canSubmit($user) && $marketLinkage->canBeEditedByStaff()
                && (int) $marketLinkage->submitted_by_user_id === (int) $user->id
                ? 'staff.market-linkages.edit'
                : null,
            'canDelete' => $this->settings->isEnabled('service_module.staff_delete_enabled')
                && MarketLinkageAccess::canSubmit($user)
                && (int) $marketLinkage->submitted_by_user_id === (int) $user->id
                && $marketLinkage->canBeDeletedByStaff(),
            'deleteRoute' => 'staff.market-linkages.destroy',
        ]);
    }

    public function destroy(Request $request, MarketLinkageSubmission $marketLinkage): RedirectResponse
    {
        abort_unless(
            $this->settings->isEnabled('service_module.staff_delete_enabled'),
            403,
            'Deleting service records is turned off. Ask your state admin to enable it under Service module settings.'
        );
        $staff = $this->staffOrAbort($request);
        MarketLinkageAccess::canAccessSubmission($staff, $marketLinkage);
        abort_unless((int) $marketLinkage->submitted_by_user_id === (int) $staff->id, 403);
        abort_unless($marketLinkage->canBeDeletedByStaff(), 403, 'This market linkage cannot be deleted in its current state.');

        $marketLinkage->load('partners');

        DB::transaction(function () use ($marketLinkage): void {
            foreach ($marketLinkage->partners as $partner) {
                if ($partner->hasDocument()) {
                    $disk = Storage::disk((string) ($partner->document_disk ?: 'local'));
                    $path = (string) $partner->document_path;
                    if ($path !== '' && $disk->exists($path)) {
                        $disk->delete($path);
                    }
                }
            }

            $marketLinkage->delete();
        });

        if ($request->input('redirect_to') === 'dashboard') {
            return redirect()
                ->route('staff.market-linkages.dashboard')
                ->with('status', 'Market linkage deleted.');
        }

        return redirect()
            ->route('staff.services.index')
            ->with('status', 'Market linkage deleted.');
    }

    public function export(Request $request): StreamedResponse
    {
        $user = $request->user();
        abort_unless(MarketLinkageAccess::canViewDashboard($user), 403);

        $filters = $this->validatedFilters($request);

        if (! Schema::hasTable('market_linkage_submissions')) {
            $groups = collect();
            $stats = $this->emptyDashboardStats();
        } else {
            // Build from the exact same filtered/grouped dataset the dashboard renders,
            // so the export matches the view (incl. partner-level date/type filtering + orphans).
            $submissions = $this->dashboardSubmissionsQuery($user, $filters)->get();
            $groups = $this->groupSubmissionsByIncubatee($submissions, $filters, $user);
            $groups = $this->mergeOrphanServiceCaseGroups($groups, $filters, $user);
            $stats = $this->computeDashboardStatsFromGroups($groups);
        }

        $showDistrict = in_array($user->role, ['state_admin', 'hub_admin'], true);
        $districtLabel = $this->exportDistrictLabel($filters);

        if (DeliverablesExcelSupport::isAvailable()) {
            return app(MarketLinkageDashboardExcelExport::class)
                ->download($groups, $stats, $filters, $showDistrict, $districtLabel);
        }

        return $this->exportGroupedCsv($groups, $showDistrict);
    }

    /**
     * @param  array{district_id: int}  $filters
     */
    private function exportDistrictLabel(array $filters): string
    {
        if (($filters['district_id'] ?? 0) > 0) {
            return (string) (District::query()->whereKey($filters['district_id'])->value('name') ?? 'All districts');
        }

        return 'All districts';
    }

    /**
     * CSV fallback (used only when PhpSpreadsheet / ext-zip is unavailable).
     * Mirrors the dashboard: one row per matching partner, grouped by incubatee.
     *
     * @param  Collection<int, object>  $groups
     */
    private function exportGroupedCsv(Collection $groups, bool $showDistrict): StreamedResponse
    {
        $filename = 'market-linkages-'.now()->format('Ymd_His').'.csv';

        return response()->streamDownload(function () use ($groups, $showDistrict): void {
            $out = fopen('php://output', 'w');

            $header = ['#'];
            if ($showDistrict) {
                $header[] = 'District';
            }
            $header = array_merge($header, [
                'Incubatee',
                'Application no',
                'Partner name',
                'Linkage type',
                'Linkage date',
                'Link / URL',
                'Bill',
                'Recorded at',
                'Recorded by',
                'Submission ID',
            ]);
            fputcsv($out, $header);

            $serial = 0;
            foreach ($groups as $group) {
                $serial++;
                $partners = is_array($group->partners ?? null) ? $group->partners : [];
                foreach ($partners as $p) {
                    $row = [$serial];
                    if ($showDistrict) {
                        $row[] = $group->district_name ?? '';
                    }
                    $row[] = $group->incubatee_name ?? '';
                    $row[] = $group->application_no ?? '';
                    $row[] = $p['partner_name'] ?? '';
                    $row[] = $p['linkage_mode_label'] ?? '';
                    $row[] = $p['linkage_date_display'] ?? ($p['linkage_date'] ?? '');
                    $row[] = $p['link_url'] ?? '';
                    $row[] = ! empty($p['has_document']) ? 'Yes' : 'No';
                    $row[] = $p['recorded_at'] ?? '';
                    $row[] = $p['recorded_by'] ?? '';
                    $row[] = (int) ($p['submission_id'] ?? 0);
                    fputcsv($out, $row);
                }
            }

            fclose($out);
        }, $filename, [
            'Content-Type' => 'text/csv; charset=UTF-8',
        ]);
    }

    public function downloadDocument(
        Request $request,
        MarketLinkageSubmission $marketLinkage,
        MarketLinkagePartner $partner,
    ) {
        $user = $request->user();
        abort_unless(MarketLinkageAccess::canViewDashboard($user), 403);
        MarketLinkageAccess::canAccessSubmission($user, $marketLinkage);
        abort_unless((int) $partner->market_linkage_submission_id === (int) $marketLinkage->id, 404);
        abort_unless($partner->hasDocument(), 404);

        $disk = Storage::disk((string) ($partner->document_disk ?: 'local'));
        $path = (string) $partner->document_path;
        abort_unless($disk->exists($path), 404);

        return $disk->download($path, (string) ($partner->document_original_name ?: 'document'));
    }

    private function staffOrAbort(Request $request): User
    {
        $user = $request->user();
        abort_unless(MarketLinkageAccess::canSubmit($user), 403);

        return $user->load('district');
    }

    /**
     * @return Builder<CfaSubmission>
     */
    private function eligibleSubmissions(User $staff)
    {
        $q = CfaSubmission::query()
            ->where('district_id', (int) $staff->district_id)
            ->orderBy('applicant_name');

        if ($this->settings->get('service_module.eligibility', 'onboarded_only') === 'onboarded_only') {
            $q->whereHas('onboardingBatchMembership');
        }

        return $q;
    }

    private function legacyRowsForStaff(User $staff): Collection
    {
        try {
            return $this->legacyApplications->eligibleLegacyApplicationsForStaff($staff);
        } catch (\Throwable $e) {
            report($e);

            return collect();
        }
    }

    /**
     * @return array{name: string, application_no: ?string}
     */
    private function resolveIncubateeSnapshot(User $staff, int $cfaId, int $legacyId): array
    {
        if ($cfaId > 0) {
            $submission = $this->eligibleSubmissions($staff)->whereKey($cfaId)->first();
            if ($submission === null) {
                throw ValidationException::withMessages([
                    'cfa_submission_id' => 'Invalid incubatee for your district.',
                ]);
            }

            return [
                'name' => (string) $submission->applicant_name,
                'application_no' => $submission->application_no ? (string) $submission->application_no : null,
            ];
        }

        if ($legacyId > 0) {
            $legacyRows = $this->legacyRowsForStaff($staff);
            $row = $legacyRows->first(fn ($r) => (int) $r->id === $legacyId);
            if ($row === null) {
                throw ValidationException::withMessages([
                    'legacy_application_id' => 'Invalid legacy application for your district.',
                ]);
            }

            return [
                'name' => (string) $row->applicant_name,
                'application_no' => $row->application_no ? (string) $row->application_no : null,
            ];
        }

        throw ValidationException::withMessages([
            'cfa_submission_id' => 'Select an incubatee.',
        ]);
    }

    /**
     * @return array{document_disk: string, document_path: string, document_original_name: string}
     */
    private function storePartnerDocument(
        UploadedFile $file,
        MarketLinkageSubmission $submission,
        MarketLinkagePartner $partner,
    ): array {
        $dir = 'market-linkages/'.$submission->id.'/'.$partner->id;
        $path = $file->store($dir, 'local');

        return [
            'document_disk' => 'local',
            'document_path' => $path,
            'document_original_name' => $file->getClientOriginalName(),
        ];
    }

    /**
     * @param  list<array<string, mixed>>  $partners
     * @return list<array<string, mixed>>
     */
    private function normalizeAndValidatePartnerLinkUrls(array $partners): array
    {
        $errors = [];
        $normalized = [];

        foreach (array_values($partners) as $index => $row) {
            $mode = (string) ($row['linkage_mode'] ?? '');
            $linkInput = MarketLinkagePartner::linkInputFromRow($row);

            if ($mode === MarketLinkageSubmission::LINKAGE_ONLINE && $linkInput === '') {
                $errors["partners.{$index}.link_url"] = 'Link or URL is required for online market linkage.';

                continue;
            }

            $row['link_url'] = MarketLinkagePartner::normalizeLinkForStorage($linkInput);
            unset($row['link']);
            $normalized[] = $row;
        }

        if ($errors !== []) {
            throw ValidationException::withMessages($errors);
        }

        return $normalized;
    }

    /**
     * Base dashboard submissions query with every dashboard filter applied.
     * Shared by dashboard() and export() so the export mirrors the view exactly
     * (same district / linkage type / search / date-range filtering and ordering).
     *
     * @param  array{q: string, from: string, to: string, district_id: int, linkage_mode: string}  $filters
     * @return Builder<MarketLinkageSubmission>
     */
    private function dashboardSubmissionsQuery(User $user, array $filters)
    {
        $query = MarketLinkageSubmission::query()
            ->approved()
            ->with(['partners', 'submitter:id,name', 'district:id,name']);

        $this->scopeDashboardQuery($query, $user);

        if ($filters['district_id'] > 0) {
            $query->where('district_id', $filters['district_id']);
        }
        if ($filters['linkage_mode'] !== '') {
            $query->whereHas('partners', fn ($q) => $q->where('linkage_mode', $filters['linkage_mode']));
        }
        if ($filters['q'] !== '') {
            $like = '%'.str_replace(['\\', '%', '_'], ['\\\\', '\\%', '\\_'], $filters['q']).'%';
            $query->where(function ($q) use ($like): void {
                $q->where('incubatee_name', 'like', $like)
                    ->orWhere('application_no', 'like', $like)
                    ->orWhere('submitted_by_name', 'like', $like)
                    ->orWhereHas('partners', fn ($pq) => $pq->where('partner_name', 'like', $like));
            });
        }
        if ($filters['from'] !== '') {
            $query->whereHas('partners', fn ($q) => $q->whereDate('linkage_date', '>=', $filters['from']));
        }
        if ($filters['to'] !== '') {
            $query->whereHas('partners', fn ($q) => $q->whereDate('linkage_date', '<=', $filters['to']));
        }

        return $query->orderByDesc('created_at')->orderByDesc('id');
    }

    private function scopeDashboardQuery($query, User $user): void
    {
        if ($user->role === 'district_staff') {
            $query->where('district_id', (int) ($user->district_id ?: 0));

            return;
        }

        if ($user->role === 'hub_admin') {
            $districtIds = $this->hubDistrictIds((int) ($user->hub_id ?: 0));
            if ($districtIds === []) {
                $query->whereRaw('1 = 0');

                return;
            }

            $query->whereIn('district_id', $districtIds);
        }
    }

    /** @return list<int> */
    private function hubDistrictIds(int $hubId): array
    {
        if ($hubId <= 0) {
            return [];
        }

        return District::query()
            ->where('hub_id', $hubId)
            ->orderBy('sort_order')
            ->orderBy('name')
            ->pluck('id')
            ->map(fn ($id) => (int) $id)
            ->all();
    }

    /** @return Collection<int, District> */
    private function hubDistricts(int $hubId): Collection
    {
        return District::query()
            ->where('hub_id', $hubId)
            ->orderBy('sort_order')
            ->orderBy('name')
            ->get(['id', 'name']);
    }

    /**
     * @return array{dashboard: string, export: string, show: string, document: string}
     */
    private function marketLinkageRoutes(User $user): array
    {
        return match ($user->role) {
            'state_admin' => [
                'dashboard' => 'admin.market-linkages.dashboard',
                'export' => 'admin.market-linkages.export',
                'show' => 'admin.market-linkages.show',
                'document' => 'admin.market-linkages.document',
            ],
            'hub_admin' => [
                'dashboard' => 'hub.market-linkages.dashboard',
                'export' => 'hub.market-linkages.export',
                'show' => 'hub.market-linkages.show',
                'document' => 'hub.market-linkages.document',
            ],
            default => [
                'dashboard' => 'staff.market-linkages.dashboard',
                'export' => 'staff.market-linkages.export',
                'show' => 'staff.market-linkages.show',
                'document' => 'staff.market-linkages.document',
            ],
        };
    }

    /**
     * @param  Builder<MarketLinkageSubmission>  $query
     */
    public static function applyApprovedScopeIfSupported($query)
    {
        if (MarketLinkageSubmission::supportsWorkflow()) {
            return $query->where('status', ServiceCase::STATUS_APPROVED);
        }

        return $query;
    }

    private function incubateeKey(MarketLinkageSubmission $submission): string
    {
        if ($submission->cfa_submission_id) {
            return 'c:'.(int) $submission->cfa_submission_id;
        }
        if ($submission->legacy_application_id) {
            return 'l:'.(int) $submission->legacy_application_id;
        }

        return 's:'.(int) $submission->id;
    }

    /**
     * @param  array{q: string, from: string, to: string, district_id: int, linkage_mode: string}  $filters
     * @return Collection<int, object{
     *   key: string,
     *   incubatee_name: string,
     *   application_no: ?string,
     *   district_id: int,
     *   district_name: ?string,
     *   cfa_submission_id: ?int,
     *   legacy_application_id: ?int,
     *   partner_count: int,
     *   submission_count: int,
     *   last_recorded_at: ?Carbon,
     *   partners: list<array<string, mixed>>
     * }>
     */
    private function groupSubmissionsByIncubatee(Collection $submissions, array $filters, User $user): Collection
    {
        $routes = $this->marketLinkageRoutes($user);
        $showRoute = $routes['show'];
        $documentRoute = $routes['document'];
        $groups = [];

        foreach ($submissions as $submission) {
            $key = $this->incubateeKey($submission);
            if (! isset($groups[$key])) {
                $groups[$key] = (object) [
                    'key' => $key,
                    'incubatee_name' => (string) $submission->incubatee_name,
                    'application_no' => $submission->application_no ? (string) $submission->application_no : null,
                    'district_id' => (int) $submission->district_id,
                    'district_name' => $submission->district_name ?? $submission->district?->name,
                    'cfa_submission_id' => $submission->cfa_submission_id ? (int) $submission->cfa_submission_id : null,
                    'legacy_application_id' => $submission->legacy_application_id ? (int) $submission->legacy_application_id : null,
                    'partner_count' => 0,
                    'submission_count' => 0,
                    'last_recorded_at' => $submission->created_at,
                    'partners' => [],
                ];
            }

            $group = $groups[$key];
            $group->submission_count++;

            if ($submission->created_at && (! $group->last_recorded_at || $submission->created_at->gt($group->last_recorded_at))) {
                $group->last_recorded_at = $submission->created_at;
            }

            $recordedAt = optional($submission->created_at)->timezone(config('app.timezone'))->format('d M Y H:i') ?? '';
            $canDeleteSubmission = $this->settings->isEnabled('service_module.staff_delete_enabled')
                && MarketLinkageAccess::canSubmit($user)
                && (int) $submission->submitted_by_user_id === (int) $user->id
                && $submission->canBeDeletedByStaff();
            $deleteActionAdded = false;

            foreach ($submission->partners as $partner) {
                if (! $this->partnerMatchesFilters($partner, $filters)) {
                    continue;
                }

                $group->partners[] = [
                    'partner_name' => (string) $partner->partner_name,
                    'linkage_mode' => (string) $partner->linkage_mode,
                    'linkage_mode_label' => MarketLinkageSubmission::linkageModeLabel((string) $partner->linkage_mode),
                    'linkage_date' => $partner->linkage_date?->format('Y-m-d') ?? '',
                    'linkage_date_display' => $partner->linkage_date?->format('d M Y') ?? '',
                    'link_url' => is_string($partner->link_url) && $partner->link_url !== '' ? (string) $partner->link_url : null,
                    'link_href' => MarketLinkagePartner::clickableHref($partner->link_url),
                    'has_document' => $partner->hasDocument(),
                    'document_url' => $partner->hasDocument()
                        ? route($documentRoute, [$submission, $partner])
                        : null,
                    'recorded_at' => $recordedAt,
                    'recorded_by' => (string) $submission->submitted_by_name,
                    'submission_id' => (int) $submission->id,
                    'show_url' => route($showRoute, $submission),
                    'delete_url' => $canDeleteSubmission && ! $deleteActionAdded
                        ? route('staff.market-linkages.destroy', $submission)
                        : null,
                ];
                $deleteActionAdded = $deleteActionAdded || $canDeleteSubmission;
                $group->partner_count++;
            }
        }

        return collect($groups)
            ->filter(fn ($g) => $g->partner_count > 0)
            ->sortByDesc(fn ($g) => $g->last_recorded_at?->timestamp ?? 0)
            ->values();
    }

    /**
     * Append approved orphan market-link service cases (same incubatees shown on phase3-services
     * for market-link service filters) that are not already covered by a Market Linkage module row.
     *
     * @param  Collection<int, object>  $grouped
     * @param  array{q: string, from: string, to: string, district_id: int, linkage_mode: string}  $filters
     * @return Collection<int, object>
     */
    private function mergeOrphanServiceCaseGroups(Collection $grouped, array $filters, User $user): Collection
    {
        $districtIds = $this->dashboardDistrictScopeIds($user, $filters);
        $orphans = MarketLinkageUnifiedListingSupport::orphanDashboardGroups($districtIds, [
            'q' => $filters['q'],
            'from' => $filters['from'],
            'to' => $filters['to'],
            'linkage_mode' => $filters['linkage_mode'],
        ]);

        if ($orphans === []) {
            return $grouped;
        }

        $existingKeys = $grouped->pluck('key')->all();
        $canOpenServiceCase = $user->role === 'state_admin' && \Illuminate\Support\Facades\Route::has('admin.phase3-services.show');

        foreach ($orphans as $orphan) {
            $key = (string) ($orphan['key'] ?? '');
            if ($key === '' || in_array($key, $existingKeys, true)) {
                continue;
            }

            $partners = [];
            foreach ($orphan['partners'] as $partner) {
                if ($canOpenServiceCase && ! empty($partner['service_case_id'])) {
                    $partner['show_url'] = route('admin.phase3-services.show', (int) $partner['service_case_id']);
                }
                $partners[] = $partner;
            }

            $grouped->push((object) [
                'key' => $key,
                'incubatee_name' => (string) ($orphan['incubatee_name'] ?? '—'),
                'application_no' => $orphan['application_no'] ?? null,
                'district_id' => (int) ($orphan['district_id'] ?? 0),
                'district_name' => $orphan['district_name'] ?? null,
                'cfa_submission_id' => $orphan['cfa_submission_id'] ?? null,
                'legacy_application_id' => $orphan['legacy_application_id'] ?? null,
                'partner_count' => count($partners),
                'submission_count' => 1,
                'last_recorded_at' => $orphan['last_recorded_at'] ?? null,
                'partners' => $partners,
                'source' => 'service_case',
            ]);
            $existingKeys[] = $key;
        }

        return $grouped
            ->filter(fn ($g) => (int) ($g->partner_count ?? 0) > 0)
            ->sortByDesc(fn ($g) => $g->last_recorded_at?->timestamp ?? 0)
            ->values();
    }

    /**
     * @param  array{q: string, from: string, to: string, district_id: int, linkage_mode: string}  $filters
     * @return list<int>|null
     */
    private function dashboardDistrictScopeIds(User $user, array $filters): ?array
    {
        if ($filters['district_id'] > 0) {
            return [$filters['district_id']];
        }

        if ($user->role === 'district_staff') {
            $districtId = (int) ($user->district_id ?: 0);

            return $districtId > 0 ? [$districtId] : [];
        }

        if ($user->role === 'hub_admin') {
            return $this->hubDistrictIds((int) ($user->hub_id ?: 0));
        }

        return null;
    }

    /**
     * @param  array{q: string, from: string, to: string, district_id: int, linkage_mode: string}  $filters
     */
    private function partnerMatchesFilters(MarketLinkagePartner $partner, array $filters): bool
    {
        if ($filters['linkage_mode'] !== '' && $partner->linkage_mode !== $filters['linkage_mode']) {
            return false;
        }
        if ($filters['from'] !== '' && $partner->linkage_date && $partner->linkage_date->format('Y-m-d') < $filters['from']) {
            return false;
        }
        if ($filters['to'] !== '' && $partner->linkage_date && $partner->linkage_date->format('Y-m-d') > $filters['to']) {
            return false;
        }

        return true;
    }

    /**
     * @param  Collection<int, object>  $grouped
     */
    private function paginateGroupedIncubatees(Request $request, Collection $grouped, int $perPage): LengthAwarePaginator
    {
        $page = max(1, (int) $request->query('page', 1));
        $total = $grouped->count();
        $items = $grouped->forPage($page, $perPage)->values();

        return new LengthAwarePaginator(
            $items,
            $total,
            $perPage,
            $page,
            ['path' => $request->url(), 'query' => $request->query()]
        );
    }

    /**
     * @return array{q: string, from: string, to: string, district_id: int, linkage_mode: string}
     */
    private function validatedFilters(Request $request): array
    {
        $user = $request->user();
        $districtId = (int) $request->query('district_id', 0);
        if ($user?->role === 'hub_admin' && $districtId > 0) {
            $allowed = $this->hubDistrictIds((int) ($user->hub_id ?: 0));
            if (! in_array($districtId, $allowed, true)) {
                $districtId = 0;
            }
        }
        $linkageMode = (string) $request->query('linkage_mode', '');
        if (! in_array($linkageMode, ['', MarketLinkageSubmission::LINKAGE_ONLINE, MarketLinkageSubmission::LINKAGE_OFFLINE], true)) {
            $linkageMode = '';
        }

        return [
            'q' => trim((string) $request->query('q', '')),
            'from' => (string) $request->query('from', ''),
            'to' => (string) $request->query('to', ''),
            'district_id' => $districtId,
            'linkage_mode' => $linkageMode,
        ];
    }

    /**
     * @return array{q: string, from: string, to: string, district_id: int, linkage_mode: string}
     */
    private function defaultFilters(Request $request): array
    {
        return $this->validatedFilters($request);
    }

    /**
     * @param  array{unique_partners: int, linked_incubatees: int, partner_records: int, online_partners: int, offline_partners: int}  $stats
     * @param  list<array{id: int, name: string, total: int}>  $districtCounts
     */
    private function dashboardView(
        Request $request,
        mixed $rows,
        bool $migrationMissing,
        array $filters,
        array $districtCounts = [],
        array $stats = [],
    ): View {
        $user = $request->user();
        $isAdmin = $user->role === 'state_admin';
        $isHubAdmin = $user->role === 'hub_admin';
        $showDistrictScope = $isAdmin || $isHubAdmin;
        $routes = $this->marketLinkageRoutes($user);

        return view('market-linkages.dashboard', [
            'rows' => $rows,
            'stats' => $stats !== [] ? $stats : $this->emptyDashboardStats(),
            'migrationMissing' => $migrationMissing,
            'isPaginated' => ! $migrationMissing,
            'isAdminView' => $isAdmin,
            'showDistrictScope' => $showDistrictScope,
            'filters' => $filters,
            'districtCounts' => $districtCounts,
            'districts' => $isAdmin
                ? District::query()->orderBy('name')->get(['id', 'name'])
                : ($isHubAdmin ? $this->hubDistricts((int) ($user->hub_id ?: 0)) : collect()),
            'dashboardRoute' => $routes['dashboard'],
            'exportRoute' => $routes['export'],
            'showRoute' => $routes['show'],
            'createRoute' => MarketLinkageAccess::canSubmit($user) ? 'staff.market-linkages.create' : null,
            'documentRoutePrefix' => $routes['document'],
        ]);
    }

    /**
     * @param  Collection<int, object>  $groups
     * @return array{unique_partners: int, linked_incubatees: int, partner_records: int, online_partners: int, offline_partners: int}
     */
    private function computeDashboardStatsFromGroups(Collection $groups): array
    {
        $uniquePartners = [];
        $partnerRecords = 0;
        $onlinePartners = 0;
        $offlinePartners = 0;

        foreach ($groups as $group) {
            foreach ($group->partners ?? [] as $partner) {
                $partnerRecords++;
                $partnerKey = $this->partnerCatalog->normalizePartnerKey((string) ($partner['partner_name'] ?? ''));
                if ($partnerKey !== '' && $partnerKey !== '—') {
                    $uniquePartners[$partnerKey] = true;
                }

                $mode = (string) ($partner['linkage_mode'] ?? '');
                if ($mode === MarketLinkageSubmission::LINKAGE_ONLINE) {
                    $onlinePartners++;
                } elseif ($mode === MarketLinkageSubmission::LINKAGE_OFFLINE) {
                    $offlinePartners++;
                }
            }
        }

        return [
            'unique_partners' => count($uniquePartners),
            'linked_incubatees' => $groups->count(),
            'partner_records' => $partnerRecords,
            'online_partners' => $onlinePartners,
            'offline_partners' => $offlinePartners,
        ];
    }

    /**
     * @return array{unique_partners: int, linked_incubatees: int, partner_records: int, online_partners: int, offline_partners: int}
     */
    private function emptyDashboardStats(): array
    {
        return [
            'unique_partners' => 0,
            'linked_incubatees' => 0,
            'partner_records' => 0,
            'online_partners' => 0,
            'offline_partners' => 0,
        ];
    }

    /**
     * @return list<array{id: int, name: string, total: int}>
     */
    private function districtCounts(User $user, array $filters): array
    {
        if (! in_array($user->role, ['state_admin', 'hub_admin'], true)) {
            return [];
        }

        // Build the same unified incubatee set the list uses (ML module + orphans), then count by district.
        $countFilters = $filters;
        $countFilters['district_id'] = 0;
        $submissions = $this->dashboardSubmissionsQuery($user, $countFilters)->get();
        $grouped = $this->groupSubmissionsByIncubatee($submissions, $countFilters, $user);
        $grouped = $this->mergeOrphanServiceCaseGroups($grouped, $countFilters, $user);

        $totals = [];
        foreach ($grouped as $group) {
            $districtId = (int) ($group->district_id ?? 0);
            if ($districtId < 1) {
                continue;
            }
            $totals[$districtId] = ($totals[$districtId] ?? 0) + 1;
        }

        $districtQuery = District::query()->orderBy('sort_order')->orderBy('name');
        if ($user->role === 'hub_admin') {
            $districtQuery->where('hub_id', (int) ($user->hub_id ?: 0));
        }

        return $districtQuery
            ->get(['id', 'name'])
            ->map(fn (District $d) => [
                'id' => (int) $d->id,
                'name' => (string) $d->name,
                'total' => (int) ($totals[(int) $d->id] ?? 0),
            ])
            ->all();
    }

    /**
     * @param  list<int>  $submissionIds
     * @param  list<int>  $legacyIds
     * @return array{cfa: array<int, list<array<string, mixed>>>, legacy: array<int, list<array<string, mixed>>>}
     */
    private function priorMarketLinkageJson(array $submissionIds, array $legacyIds): array
    {
        $empty = ['cfa' => [], 'legacy' => []];

        if (! Schema::hasTable('market_linkage_submissions')) {
            return $empty;
        }

        $format = function (MarketLinkageSubmission $submission): array {
            return [
                'id' => (int) $submission->id,
                'staff_name' => (string) $submission->submitted_by_name,
                'created_at' => optional($submission->created_at)->timezone(config('app.timezone'))->format('d M Y H:i') ?? '',
                'show_url' => route('staff.market-linkages.show', $submission),
                'partners' => $submission->partners->map(fn ($partner) => [
                    'partner_name' => (string) $partner->partner_name,
                    'linkage_mode' => MarketLinkageSubmission::linkageModeLabel((string) $partner->linkage_mode),
                    'linkage_mode_raw' => (string) $partner->linkage_mode,
                    'linkage_date' => $partner->linkage_date?->format('Y-m-d') ?? '',
                    'linkage_date_display' => $partner->linkage_date?->format('d M Y') ?? '',
                    'link_url' => is_string($partner->link_url) && $partner->link_url !== '' ? (string) $partner->link_url : null,
                    'link_href' => MarketLinkagePartner::clickableHref($partner->link_url),
                    'has_document' => $partner->hasDocument(),
                    'document_url' => $partner->hasDocument()
                        ? route('staff.market-linkages.document', [$submission, $partner])
                        : null,
                ])->values()->all(),
            ];
        };

        $cfaRows = MarketLinkageSubmission::query()
            ->approved()
            ->with('partners')
            ->when($submissionIds !== [], fn ($q) => $q->whereIn('cfa_submission_id', $submissionIds), fn ($q) => $q->whereRaw('1 = 0'))
            ->whereNotNull('cfa_submission_id')
            ->orderByDesc('created_at')
            ->get();

        $legacyRows = MarketLinkageSubmission::query()
            ->approved()
            ->with('partners')
            ->when($legacyIds !== [], fn ($q) => $q->whereIn('legacy_application_id', $legacyIds), fn ($q) => $q->whereRaw('1 = 0'))
            ->whereNotNull('legacy_application_id')
            ->orderByDesc('created_at')
            ->get();

        return [
            'cfa' => $cfaRows
                ->groupBy(fn (MarketLinkageSubmission $row) => (int) $row->cfa_submission_id)
                ->map(fn (Collection $rows) => $rows->map($format)->values()->all())
                ->all(),
            'legacy' => $legacyRows
                ->groupBy(fn (MarketLinkageSubmission $row) => (int) $row->legacy_application_id)
                ->map(fn (Collection $rows) => $rows->map($format)->values()->all())
                ->all(),
        ];
    }
}
