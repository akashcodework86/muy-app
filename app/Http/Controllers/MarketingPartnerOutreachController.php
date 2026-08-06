<?php

namespace App\Http\Controllers;

use App\Models\MarketingPartnerOutreachEntry;
use App\Models\User;
use App\Support\MarketingPartnerOutreachDeliverablesSupport;
use App\Support\PartnerOutreachAccess;
use App\Support\PartnerOutreachOptions;
use App\Support\TodayOnlyDate;
use App\Models\MarketLinkagePartner;
use Illuminate\Contracts\View\View;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\Storage;
use Illuminate\Validation\Rule;
use Symfony\Component\HttpFoundation\StreamedResponse;

class MarketingPartnerOutreachController extends Controller
{
    public function create(Request $request): View
    {
        $user = $this->submitterOrAbort($request);

        return view('partner-outreach.form', [
            'user' => $user,
            'migrationMissing' => ! Schema::hasTable('marketing_partner_outreach_entries'),
            'storeRoute' => 'spoc.partner-outreach.store',
            'dashboardRoute' => 'spoc.partner-outreach.dashboard',
            'cohortOrSectors' => PartnerOutreachOptions::cohortOrSectors(),
        ]);
    }

    public function store(Request $request): RedirectResponse
    {
        $user = $this->submitterOrAbort($request);

        if (! Schema::hasTable('marketing_partner_outreach_entries')) {
            return redirect()
                ->route('spoc.partner-outreach.create')
                ->withErrors(['outreach_date' => 'Database table is missing. Please run migrations first.']);
        }

        $validated = $request->validate([
            'outreach_date' => TodayOnlyDate::rules(),
            'partner_name' => ['required', 'string', 'max:255'],
            'partner_designation' => ['nullable', 'string', 'max:191'],
            'partner_link' => ['nullable', 'string', 'max:2048'],
            'cohort_or_sector' => ['required', 'string', Rule::in(array_keys(PartnerOutreachOptions::cohortOrSectors()))],
            'cohort_or_sector_other' => ['nullable', 'string', 'max:191', 'required_if:cohort_or_sector,other'],
            'poc_name' => ['nullable', 'string', 'max:191'],
            'poc_contact_method' => ['nullable', 'string', Rule::in(['phone', 'email'])],
            'poc_phone' => ['nullable', 'string', 'regex:/^[6-9]\d{9}$/'],
            'poc_email' => ['nullable', 'email', 'max:191'],
            'remarks' => ['nullable', 'string', 'max:5000'],
        ]);

        $contactMethod = (string) ($validated['poc_contact_method'] ?? '');
        $pocPhone = $contactMethod === 'email'
            ? null
            : (trim((string) ($validated['poc_phone'] ?? '')) ?: null);
        $pocEmail = $contactMethod === 'phone'
            ? null
            : (trim((string) ($validated['poc_email'] ?? '')) ?: null);

        MarketingPartnerOutreachEntry::query()->create([
            'outreach_date' => $validated['outreach_date'],
            'partner_name' => trim((string) $validated['partner_name']),
            'partner_designation' => trim((string) ($validated['partner_designation'] ?? '')) ?: null,
            'partner_link' => MarketLinkagePartner::normalizeLinkForStorage($validated['partner_link'] ?? null),
            'cohort_or_sector' => (string) $validated['cohort_or_sector'],
            'cohort_or_sector_other' => (string) $validated['cohort_or_sector'] === 'other'
                ? trim((string) ($validated['cohort_or_sector_other'] ?? ''))
                : null,
            'poc_name' => trim((string) ($validated['poc_name'] ?? '')) ?: null,
            'poc_phone' => $pocPhone,
            'poc_email' => $pocEmail,
            'remarks' => trim((string) ($validated['remarks'] ?? '')) ?: null,
            'status' => MarketingPartnerOutreachEntry::STATUS_OUTREACH,
            'submitted_by_user_id' => (int) $user->id,
            'submitted_by_name' => (string) $user->name,
        ]);

        return redirect()
            ->route('spoc.partner-outreach.dashboard')
            ->with('status', 'Partner outreach entry logged.');
    }

    public function dashboard(Request $request): View
    {
        $user = $request->user();
        abort_unless(PartnerOutreachAccess::canViewDashboard($user), 403);

        if (! Schema::hasTable('marketing_partner_outreach_entries')) {
            return $this->dashboardView($request, collect(), true, [
                'q' => '',
                'from' => '',
                'to' => '',
                'status' => '',
                'cohort_or_sector' => '',
            ], [
                'total' => 0,
                'outreach' => 0,
                'onboarded' => 0,
                'in_discussion' => 0,
                'declined' => 0,
            ]);
        }

        $query = MarketingPartnerOutreachEntry::query()->with(['submitter:id,name']);
        $this->scopeDashboardQuery($query, $user);

        $search = trim((string) $request->query('q', ''));
        if ($search !== '') {
            $like = '%'.str_replace(['\\', '%', '_'], ['\\\\', '\\%', '\\_'], $search).'%';
            $query->where(function ($q) use ($like): void {
                $q->where('partner_name', 'like', $like)
                    ->orWhere('partner_designation', 'like', $like)
                    ->orWhere('poc_name', 'like', $like)
                    ->orWhere('poc_phone', 'like', $like)
                    ->orWhere('poc_email', 'like', $like)
                    ->orWhere('remarks', 'like', $like)
                    ->orWhere('submitted_by_name', 'like', $like)
                    ->orWhere('cohort_or_sector_other', 'like', $like);
            });
        }

        if ($request->filled('from')) {
            $query->whereDate('outreach_date', '>=', (string) $request->query('from'));
        }
        if ($request->filled('to')) {
            $query->whereDate('outreach_date', '<=', (string) $request->query('to'));
        }

        $statusFilter = trim((string) $request->query('status', ''));
        if ($statusFilter !== '' && in_array($statusFilter, MarketingPartnerOutreachEntry::STATUSES, true)) {
            $query->where('status', $statusFilter);
        }

        $cohortFilter = trim((string) $request->query('cohort_or_sector', ''));
        if ($cohortFilter !== '' && array_key_exists($cohortFilter, PartnerOutreachOptions::cohortOrSectors())) {
            $query->where('cohort_or_sector', $cohortFilter);
        }

        $statsQuery = clone $query;
        $totals = [
            'total' => (int) (clone $statsQuery)->count(),
            'outreach' => (int) (clone $statsQuery)->where('status', MarketingPartnerOutreachEntry::STATUS_OUTREACH)->count(),
            'onboarded' => (int) (clone $statsQuery)->whereIn('status', MarketingPartnerOutreachEntry::ONBOARDED_STATUSES)->count(),
            'in_discussion' => (int) (clone $statsQuery)->where('status', MarketingPartnerOutreachEntry::STATUS_IN_DISCUSSION)->count(),
            'declined' => (int) (clone $statsQuery)->where('status', MarketingPartnerOutreachEntry::STATUS_DECLINED)->count(),
        ];

        $rows = $query
            ->orderByDesc('outreach_date')
            ->orderByDesc('id')
            ->paginate(25)
            ->withQueryString();

        return $this->dashboardView($request, $rows, false, [
            'q' => $search,
            'from' => (string) $request->query('from', ''),
            'to' => (string) $request->query('to', ''),
            'status' => $statusFilter,
            'cohort_or_sector' => $cohortFilter,
        ], $totals);
    }

    public function show(Request $request, MarketingPartnerOutreachEntry $partnerOutreach): View
    {
        $user = $request->user();
        abort_unless(PartnerOutreachAccess::canViewDashboard($user), 403);
        $this->assertCanAccessRecord($user, $partnerOutreach);

        $partnerOutreach->loadMissing(['submitter:id,name', 'statusUpdater:id,name']);
        $isAdmin = $user->role === 'state_admin';
        $routePrefix = $isAdmin ? 'admin' : 'spoc';

        return view('partner-outreach.show', [
            'row' => $partnerOutreach,
            'currentRole' => (string) $user->role,
            'dashboardRoute' => $isAdmin ? 'admin.partner-outreach.dashboard' : 'spoc.partner-outreach.dashboard',
            'destroyRoute' => $isAdmin ? null : 'spoc.partner-outreach.destroy',
            'documentRoute' => $routePrefix.'.partner-outreach.document',
            'updateStatusRoute' => PartnerOutreachAccess::canChangeStatus($user)
                ? 'spoc.partner-outreach.update-status'
                : null,
            'canDelete' => PartnerOutreachAccess::canDelete($user, $partnerOutreach),
            'canChangeStatus' => PartnerOutreachAccess::canChangeStatus($user),
            'statuses' => PartnerOutreachOptions::statuses(),
            'cohortOrSectors' => PartnerOutreachOptions::cohortOrSectors(),
        ]);
    }

    public function updateStatus(Request $request, MarketingPartnerOutreachEntry $partnerOutreach): RedirectResponse
    {
        $user = $request->user();
        abort_unless(PartnerOutreachAccess::canChangeStatus($user), 403);
        $this->assertCanAccessRecord($user, $partnerOutreach);

        if ($partnerOutreach->isOnboarded()) {
            return back()->withErrors(['status' => 'Onboarded partners cannot be changed to another status.']);
        }

        $onboardedStatuses = MarketingPartnerOutreachEntry::ONBOARDED_STATUSES;
        $validated = $request->validate([
            'status' => ['required', 'string', Rule::in(MarketingPartnerOutreachEntry::STATUSES)],
            'onboarding_date' => array_values(array_filter([
                Rule::requiredIf(fn (): bool => in_array((string) $request->input('status'), $onboardedStatuses, true)),
                ...TodayOnlyDate::rulesAllowingExisting($partnerOutreach->onboarding_date?->toDateString(), false),
            ])),
            'agreement_document' => [
                'nullable',
                'file',
                'mimes:pdf,jpg,jpeg,png,webp',
                'max:10240',
                Rule::requiredIf(fn (): bool => $request->input('status') === MarketingPartnerOutreachEntry::STATUS_ONBOARDED_MOU
                    && ! $partnerOutreach->hasAgreementDocument()),
            ],
        ]);

        $newStatus = (string) $validated['status'];
        if ($newStatus === (string) $partnerOutreach->status && ! in_array($newStatus, $onboardedStatuses, true)) {
            return back()->with('status', 'Status unchanged.');
        }

        $payload = [
            'status' => $newStatus,
            'status_updated_by_user_id' => (int) $user->id,
            'status_updated_by_name' => (string) $user->name,
            'status_updated_at' => now(),
        ];

        if (in_array($newStatus, $onboardedStatuses, true)) {
            $payload['onboarding_date'] = $validated['onboarding_date'];
        } else {
            $payload['onboarding_date'] = null;
        }

        if ($request->hasFile('agreement_document')) {
            $file = $request->file('agreement_document');
            if ($file instanceof UploadedFile && $file->isValid()) {
                $partnerOutreach->deleteStoredAgreementDocument();
                $path = $file->store('partner-outreach-agreements');
                $payload['agreement_document_disk'] = config('filesystems.default');
                $payload['agreement_document_path'] = $path;
                $payload['agreement_document_original_name'] = (string) $file->getClientOriginalName();
            }
        } elseif (! in_array($newStatus, $onboardedStatuses, true)) {
            $partnerOutreach->deleteStoredAgreementDocument();
            $payload['agreement_document_disk'] = null;
            $payload['agreement_document_path'] = null;
            $payload['agreement_document_original_name'] = null;
        }

        $partnerOutreach->update($payload);

        return redirect()
            ->route('spoc.partner-outreach.show', $partnerOutreach)
            ->with('status', 'Partner status updated.');
    }

    public function downloadDocument(Request $request, MarketingPartnerOutreachEntry $partnerOutreach): StreamedResponse
    {
        $user = $request->user();
        abort_unless(PartnerOutreachAccess::canViewDashboard($user), 403);
        $this->assertCanAccessRecord($user, $partnerOutreach);
        abort_unless($partnerOutreach->hasAgreementDocument(), 404);

        $disk = (string) ($partnerOutreach->agreement_document_disk ?: config('filesystems.default'));
        $path = (string) $partnerOutreach->agreement_document_path;
        abort_unless(Storage::disk($disk)->exists($path), 404);

        $filename = (string) ($partnerOutreach->agreement_document_original_name ?: basename($path));

        return Storage::disk($disk)->download($path, $filename);
    }

    public function destroy(Request $request, MarketingPartnerOutreachEntry $partnerOutreach): RedirectResponse
    {
        $user = $request->user();
        abort_unless(PartnerOutreachAccess::canDelete($user, $partnerOutreach), 403);

        $partnerOutreach->delete();

        return redirect()
            ->route('spoc.partner-outreach.dashboard')
            ->with('status', 'Partner outreach entry deleted.');
    }

    public function export(Request $request): StreamedResponse
    {
        $user = $request->user();
        abort_unless(PartnerOutreachAccess::canViewDashboard($user), 403);

        $query = MarketingPartnerOutreachEntry::query();
        $this->scopeDashboardQuery($query, $user);

        $search = trim((string) $request->query('q', ''));
        if ($search !== '') {
            $like = '%'.str_replace(['\\', '%', '_'], ['\\\\', '\\%', '\\_'], $search).'%';
            $query->where(function ($q) use ($like): void {
                $q->where('partner_name', 'like', $like)
                    ->orWhere('poc_name', 'like', $like)
                    ->orWhere('poc_phone', 'like', $like)
                    ->orWhere('poc_email', 'like', $like);
            });
        }

        if ($request->filled('from')) {
            $query->whereDate('outreach_date', '>=', (string) $request->query('from'));
        }
        if ($request->filled('to')) {
            $query->whereDate('outreach_date', '<=', (string) $request->query('to'));
        }

        $statusFilter = trim((string) $request->query('status', ''));
        if ($statusFilter !== '' && in_array($statusFilter, MarketingPartnerOutreachEntry::STATUSES, true)) {
            $query->where('status', $statusFilter);
        }

        $rows = $query->orderByDesc('outreach_date')->orderByDesc('id')->get();
        $filename = 'partner-outreach-'.now()->format('Ymd_His').'.csv';

        return response()->streamDownload(function () use ($rows): void {
            $out = fopen('php://output', 'w');
            if ($out === false) {
                return;
            }

            fputcsv($out, [
                'ID',
                'Outreach date',
                'Partner name',
                'Designation',
                'Link',
                'Cohort / sector',
                'POC name',
                'POC phone',
                'POC email',
                'Status',
                'Onboarding date',
                'Remarks',
                'Submitted by',
                'Status updated by',
                'Status updated at',
                'Created at',
            ]);

            foreach ($rows as $row) {
                fputcsv($out, [
                    $row->id,
                    $row->outreach_date?->format('Y-m-d'),
                    $row->partner_name,
                    $row->partner_designation,
                    $row->partner_link,
                    PartnerOutreachOptions::cohortOrSectorDisplay((string) $row->cohort_or_sector, $row->cohort_or_sector_other),
                    $row->poc_name,
                    $row->poc_phone,
                    $row->poc_email,
                    PartnerOutreachOptions::statusLabel((string) $row->status),
                    $row->onboarding_date?->format('Y-m-d'),
                    $row->remarks,
                    $row->submitted_by_name,
                    $row->status_updated_by_name,
                    $row->status_updated_at?->format('Y-m-d H:i'),
                    $row->created_at?->format('Y-m-d H:i'),
                ]);
            }

            fclose($out);
        }, $filename, [
            'Content-Type' => 'text/csv; charset=UTF-8',
        ]);
    }

    private function submitterOrAbort(Request $request): User
    {
        $user = $request->user();
        abort_unless(PartnerOutreachAccess::canSubmit($user), 403);

        return $user;
    }

    private function scopeDashboardQuery($query, User $user): void
    {
        if ($user->role === 'state_staff' && PartnerOutreachAccess::canSubmit($user)) {
            $query->where('submitted_by_user_id', (int) $user->id);
        }
    }

    private function assertCanAccessRecord(User $user, MarketingPartnerOutreachEntry $row): void
    {
        if ($user->role === 'state_admin') {
            return;
        }

        if ($user->role === 'state_staff'
            && PartnerOutreachAccess::canSubmit($user)
            && (int) $row->submitted_by_user_id === (int) $user->id) {
            return;
        }

        abort(403);
    }

    private function dashboardView(Request $request, mixed $rows, bool $migrationMissing, array $filters, array $totals): View
    {
        $user = $request->user();
        $isAdmin = $user->role === 'state_admin';

        return view('partner-outreach.dashboard', [
            'rows' => $rows,
            'migrationMissing' => $migrationMissing,
            'isPaginated' => ! $migrationMissing,
            'currentRole' => (string) $user->role,
            'isAdminView' => $isAdmin,
            'filters' => $filters,
            'totals' => $totals,
            'deliverableTotals' => [
                'outreach_6_1' => MarketingPartnerOutreachDeliverablesSupport::countOutreach(null, null),
                'onboarded_6_2' => MarketingPartnerOutreachDeliverablesSupport::countOnboarded(null, null),
            ],
            'dashboardRoute' => $isAdmin ? 'admin.partner-outreach.dashboard' : 'spoc.partner-outreach.dashboard',
            'exportRoute' => $isAdmin ? 'admin.partner-outreach.export' : 'spoc.partner-outreach.export',
            'showRoute' => $isAdmin ? 'admin.partner-outreach.show' : 'spoc.partner-outreach.show',
            'createRoute' => PartnerOutreachAccess::canSubmit($user) ? 'spoc.partner-outreach.create' : null,
            'statuses' => PartnerOutreachOptions::statuses(),
            'cohortOrSectors' => PartnerOutreachOptions::cohortOrSectors(),
        ]);
    }
}
