<?php

namespace App\Http\Controllers;

use App\Models\FundingSchematicPartnerOutreachEntry;
use App\Models\FiscalYear;
use App\Models\User;
use App\Support\FundingSchematicConvergenceAccess;
use App\Support\FundingSchematicPartnersOutreachDeliverablesSupport;
use App\Support\FundingSchematicPartnersOutreachOptions;
use App\Support\TodayOnlyDate;
use Illuminate\Contracts\View\View;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Str;
use Illuminate\Validation\Rule;
use Symfony\Component\HttpFoundation\StreamedResponse;

class FundingSchematicPartnerOutreachController extends Controller
{
    public function create(Request $request): View
    {
        $user = $this->submitterOrAbort($request);

        return view('funding-partners-outreach.form', [
            'user' => $user,
            'migrationMissing' => ! Schema::hasTable('funding_schematic_partner_outreach_entries'),
            'storeRoute' => 'spoc.funding-partners-outreach.store',
            'dashboardRoute' => 'spoc.funding-partners-outreach.dashboard',
            'outreachModes' => FundingSchematicPartnersOutreachOptions::outreachModes(),
            'partnerTypes' => FundingSchematicPartnersOutreachOptions::partnerTypes(),
        ]);
    }

    public function store(Request $request): RedirectResponse
    {
        $user = $this->submitterOrAbort($request);

        if (! Schema::hasTable('funding_schematic_partner_outreach_entries')) {
            return redirect()
                ->route('spoc.funding-partners-outreach.create')
                ->withErrors(['outreach_date' => 'Database table is missing. Please run migrations first.']);
        }

        $validated = $request->validate([
            'outreach_date' => TodayOnlyDate::rules(),
            'outreach_mode' => ['required', 'string', Rule::in(array_keys(FundingSchematicPartnersOutreachOptions::outreachModes()))],
            'partners' => ['required', 'array', 'min:1', 'max:50'],
            'partners.*.partner_name' => ['required', 'string', 'max:255'],
            'partners.*.partner_type' => ['required', 'string', Rule::in(array_keys(FundingSchematicPartnersOutreachOptions::partnerTypes()))],
            'partners.*.partner_type_other' => ['nullable', 'string', 'max:191'],
            'partners.*.contact_name' => ['nullable', 'string', 'max:191'],
            'partners.*.designation' => ['nullable', 'string', 'max:191'],
            'partners.*.poc_phone' => ['nullable', 'string', 'regex:/^[6-9]\d{9}$/'],
            'partners.*.partner_link' => ['required', 'string', 'max:2048'],
            'partners.*.remarks' => ['nullable', 'string', 'max:5000'],
        ]);

        foreach ($validated['partners'] as $index => $partner) {
            if (($partner['partner_type'] ?? '') === 'other' && trim((string) ($partner['partner_type_other'] ?? '')) === '') {
                return back()->withInput()->withErrors([
                    'partners.'.$index.'.partner_type_other' => 'Specify partner type when Other is selected.',
                ]);
            }
        }

        $batchId = (string) Str::uuid();
        $count = 0;

        foreach ($validated['partners'] as $partner) {
            FundingSchematicPartnerOutreachEntry::query()->create([
                'batch_id' => $batchId,
                'outreach_date' => $validated['outreach_date'],
                'outreach_mode' => (string) $validated['outreach_mode'],
                'partner_name' => trim((string) $partner['partner_name']),
                'partner_type' => (string) $partner['partner_type'],
                'partner_type_other' => (string) $partner['partner_type'] === 'other'
                    ? trim((string) ($partner['partner_type_other'] ?? ''))
                    : null,
                'contact_name' => trim((string) ($partner['contact_name'] ?? '')) ?: null,
                'designation' => trim((string) ($partner['designation'] ?? '')) ?: null,
                'poc_phone' => trim((string) ($partner['poc_phone'] ?? '')) ?: null,
                'partner_link' => trim((string) ($partner['partner_link'] ?? '')) ?: null,
                'remarks' => trim((string) ($partner['remarks'] ?? '')) ?: null,
                'submitted_by_user_id' => (int) $user->id,
                'submitted_by_name' => (string) $user->name,
            ]);
            $count++;
        }

        return redirect()
            ->route('spoc.funding-partners-outreach.dashboard')
            ->with('status', $count === 1
                ? 'Partner outreach entry logged (MIS 8.5).'
                : $count.' partner outreach entries logged (MIS 8.5).');
    }

    public function dashboard(Request $request): View
    {
        $user = $request->user();
        abort_unless(FundingSchematicConvergenceAccess::canViewDashboard($user), 403);

        if (! Schema::hasTable('funding_schematic_partner_outreach_entries')) {
            return $this->dashboardView($request, collect(), true, [
                'q' => '', 'from' => '', 'to' => '', 'partner_type' => '',
            ], ['entries' => 0, 'unique_partners' => 0, 'batches' => 0]);
        }

        $query = FundingSchematicPartnerOutreachEntry::query();
        $this->scopeDashboardQuery($query, $user);

        $search = trim((string) $request->query('q', ''));
        if ($search !== '') {
            $like = '%'.str_replace(['\\', '%', '_'], ['\\\\', '\\%', '\\_'], $search).'%';
            $query->where(function ($q) use ($like): void {
                $q->where('partner_name', 'like', $like)
                    ->orWhere('contact_name', 'like', $like)
                    ->orWhere('poc_phone', 'like', $like)
                    ->orWhere('submitted_by_name', 'like', $like)
                    ->orWhere('partner_type_other', 'like', $like);
            });
        }

        if ($request->filled('from')) {
            $query->whereDate('outreach_date', '>=', (string) $request->query('from'));
        }
        if ($request->filled('to')) {
            $query->whereDate('outreach_date', '<=', (string) $request->query('to'));
        }
        if ($request->filled('partner_type')) {
            $query->where('partner_type', (string) $request->query('partner_type'));
        }

        $statsQuery = clone $query;
        $activeFy = FiscalYear::query()->where('is_active', true)->orderByDesc('starts_on')->first();
        $periodFrom = $activeFy?->starts_on;
        $periodTo = $activeFy?->ends_on;

        $totals = [
            'entries' => (int) (clone $statsQuery)->count(),
            'unique_partners' => FundingSchematicPartnersOutreachDeliverablesSupport::countUniquePartners(
                $periodFrom ? \Carbon\Carbon::parse($periodFrom) : null,
                $periodTo ? \Carbon\Carbon::parse($periodTo) : null,
            ),
            'batches' => (int) (clone $statsQuery)->distinct()->count('batch_id'),
        ];

        $rows = $query->orderByDesc('outreach_date')->orderByDesc('id')->paginate(25)->withQueryString();

        return $this->dashboardView($request, $rows, false, [
            'q' => $search,
            'from' => (string) $request->query('from', ''),
            'to' => (string) $request->query('to', ''),
            'partner_type' => (string) $request->query('partner_type', ''),
        ], $totals);
    }

    public function show(Request $request, FundingSchematicPartnerOutreachEntry $fundingPartnerOutreach): View
    {
        $user = $request->user();
        abort_unless(FundingSchematicConvergenceAccess::canViewDashboard($user), 403);
        $this->assertCanAccessRecord($user, $fundingPartnerOutreach);

        $batchRows = FundingSchematicPartnerOutreachEntry::query()
            ->where('batch_id', $fundingPartnerOutreach->batch_id)
            ->orderBy('id')
            ->get();

        $isAdmin = $user->role === 'state_admin';

        return view('funding-partners-outreach.show', [
            'batchRows' => $batchRows,
            'header' => $fundingPartnerOutreach,
            'currentRole' => (string) $user->role,
            'dashboardRoute' => $isAdmin
                ? 'admin.funding-partners-outreach.dashboard'
                : 'spoc.funding-partners-outreach.dashboard',
            'canDelete' => FundingSchematicConvergenceAccess::canDelete($user, (int) $fundingPartnerOutreach->submitted_by_user_id),
        ]);
    }

    public function destroy(Request $request, FundingSchematicPartnerOutreachEntry $fundingPartnerOutreach): RedirectResponse
    {
        $user = $request->user();
        abort_unless(
            FundingSchematicConvergenceAccess::canDelete($user, (int) $fundingPartnerOutreach->submitted_by_user_id),
            403
        );

        FundingSchematicPartnerOutreachEntry::query()
            ->where('batch_id', $fundingPartnerOutreach->batch_id)
            ->when($user->role === 'state_staff', fn ($q) => $q->where('submitted_by_user_id', (int) $user->id))
            ->delete();

        $route = $user->role === 'state_admin'
            ? 'admin.funding-partners-outreach.dashboard'
            : 'spoc.funding-partners-outreach.dashboard';

        return redirect()->route($route)->with('status', 'Partner outreach batch deleted.');
    }

    public function export(Request $request): StreamedResponse
    {
        $user = $request->user();
        abort_unless(FundingSchematicConvergenceAccess::canViewDashboard($user), 403);

        $query = FundingSchematicPartnerOutreachEntry::query();
        $this->scopeDashboardQuery($query, $user);

        if ($request->filled('from')) {
            $query->whereDate('outreach_date', '>=', (string) $request->query('from'));
        }
        if ($request->filled('to')) {
            $query->whereDate('outreach_date', '<=', (string) $request->query('to'));
        }

        $rows = $query->orderByDesc('outreach_date')->orderByDesc('id')->get();
        $filename = 'funding-partners-outreach-'.now()->format('Ymd_His').'.csv';

        return response()->streamDownload(function () use ($rows): void {
            $out = fopen('php://output', 'w');
            if ($out === false) {
                return;
            }

            fputcsv($out, [
                'ID', 'Batch ID', 'Outreach date', 'Mode', 'Partner name', 'Partner type',
                'Contact', 'Designation', 'Phone', 'Link', 'Remarks', 'Submitted by', 'Submitted at',
            ]);

            foreach ($rows as $row) {
                fputcsv($out, [
                    $row->id,
                    $row->batch_id,
                    $row->outreach_date?->format('Y-m-d'),
                    FundingSchematicPartnersOutreachOptions::outreachModeLabel((string) $row->outreach_mode),
                    $row->partner_name,
                    FundingSchematicPartnersOutreachOptions::partnerTypeLabel((string) $row->partner_type, $row->partner_type_other),
                    $row->contact_name,
                    $row->designation,
                    $row->poc_phone,
                    $row->partner_link,
                    $row->remarks,
                    $row->submitted_by_name,
                    $row->created_at?->format('Y-m-d H:i'),
                ]);
            }

            fclose($out);
        }, $filename, ['Content-Type' => 'text/csv; charset=UTF-8']);
    }

    private function submitterOrAbort(Request $request): User
    {
        $user = $request->user();
        abort_unless(FundingSchematicConvergenceAccess::canSubmit($user), 403);

        return $user;
    }

    private function scopeDashboardQuery($query, User $user): void
    {
        if ($user->role === 'state_staff') {
            $query->where('submitted_by_user_id', (int) $user->id);
        }
    }

    private function assertCanAccessRecord(User $user, FundingSchematicPartnerOutreachEntry $row): void
    {
        if ($user->role === 'state_admin') {
            return;
        }

        if ($user->role === 'state_staff' && (int) $row->submitted_by_user_id === (int) $user->id) {
            return;
        }

        abort(403);
    }

    private function dashboardView(Request $request, mixed $rows, bool $migrationMissing, array $filters, array $totals): View
    {
        $user = $request->user();
        $isAdmin = $user->role === 'state_admin';
        $activeFy = FiscalYear::query()->where('is_active', true)->orderByDesc('starts_on')->first();

        return view('funding-partners-outreach.dashboard', [
            'rows' => $rows,
            'migrationMissing' => $migrationMissing,
            'isPaginated' => ! $migrationMissing,
            'currentRole' => (string) $user->role,
            'isAdminView' => $isAdmin,
            'filters' => $filters,
            'totals' => $totals,
            'partnerTypes' => FundingSchematicPartnersOutreachOptions::partnerTypes(),
            'outreachModes' => FundingSchematicPartnersOutreachOptions::outreachModes(),
            'dashboardRoute' => $isAdmin
                ? 'admin.funding-partners-outreach.dashboard'
                : 'spoc.funding-partners-outreach.dashboard',
            'exportRoute' => $isAdmin
                ? 'admin.funding-partners-outreach.export'
                : 'spoc.funding-partners-outreach.export',
            'showRoute' => $isAdmin
                ? 'admin.funding-partners-outreach.show'
                : 'spoc.funding-partners-outreach.show',
            'createRoute' => FundingSchematicConvergenceAccess::canSubmit($user)
                ? 'spoc.funding-partners-outreach.create'
                : null,
            'deliverableStats' => [
                'unique_partners_fy' => FundingSchematicPartnersOutreachDeliverablesSupport::countUniquePartners(
                    $activeFy?->starts_on ? \Carbon\Carbon::parse($activeFy->starts_on) : null,
                    $activeFy?->ends_on ? \Carbon\Carbon::parse($activeFy->ends_on) : null,
                ),
            ],
        ]);
    }
}
