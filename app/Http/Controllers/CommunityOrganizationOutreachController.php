<?php

namespace App\Http\Controllers;

use App\Http\Controllers\Concerns\ValidatesAttendanceMediaUploads;
use App\Models\CommunityOrganizationOutreachVisit;
use App\Models\District;
use App\Models\Hub;
use App\Models\User;
use App\Support\CommunityOrganizationOutreachOptions;
use App\Support\CommunityOrgOutreachAccess;
use Illuminate\Contracts\View\View;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\Storage;
use Illuminate\Validation\Rule;
use Symfony\Component\HttpFoundation\BinaryFileResponse;
use Symfony\Component\HttpFoundation\StreamedResponse;

class CommunityOrganizationOutreachController extends Controller
{
    use ValidatesAttendanceMediaUploads;

    public function create(Request $request): View
    {
        $user = $this->hubAdminOrAbort($request);

        return view('community-org-outreach.form', [
            'user' => $user,
            'hub' => Hub::query()->findOrFail((int) $user->hub_id),
            'districts' => $this->hubDistricts((int) $user->hub_id),
            'migrationMissing' => ! Schema::hasTable('community_organization_outreach_visits'),
            'storeRoute' => 'hub.community-org-outreach.store',
            'dashboardRoute' => 'hub.community-org-outreach.dashboard',
            'organizationTypes' => CommunityOrganizationOutreachOptions::organizationTypes(),
            'purposes' => CommunityOrganizationOutreachOptions::purposes(),
            'meetingModes' => CommunityOrganizationOutreachOptions::meetingModes(),
        ]);
    }

    public function store(Request $request): RedirectResponse
    {
        $user = $this->hubAdminOrAbort($request);

        if (! Schema::hasTable('community_organization_outreach_visits')) {
            return redirect()
                ->route('hub.community-org-outreach.create')
                ->withErrors(['visit_date' => 'Database table is missing. Please run migrations first.']);
        }

        if ($uploadErrors = array_merge(
            $this->documentUploadErrors($request),
            $this->photoUploadErrors($request),
        )) {
            return back()->withInput()->withErrors($uploadErrors);
        }

        $hubId = (int) $user->hub_id;
        $districtIds = $this->hubDistricts($hubId)->pluck('id')->map(fn ($id) => (int) $id)->all();

        $validated = $request->validate(array_merge([
            'visit_date' => ['required', 'date'],
            'district_id' => ['required', 'integer', Rule::in($districtIds)],
            'organization_name' => ['required', 'string', 'max:255'],
            'organization_type' => ['required', 'string', Rule::in(array_keys(CommunityOrganizationOutreachOptions::organizationTypes()))],
            'organization_type_other' => ['nullable', 'string', 'max:191', 'required_if:organization_type,other'],
            'person_met_name' => ['required', 'string', 'max:191'],
            'person_met_designation' => ['nullable', 'string', 'max:191'],
            'poc_name' => ['required', 'string', 'max:191'],
            'poc_phone' => ['required', 'string', 'regex:/^[6-9]\d{9}$/'],
            'poc_email' => ['nullable', 'string', 'email', 'max:191'],
            'purpose' => ['required', 'string', Rule::in(array_keys(CommunityOrganizationOutreachOptions::purposes()))],
            'meeting_mode' => ['required', 'string', Rule::in(array_keys(CommunityOrganizationOutreachOptions::meetingModes()))],
            'remarks' => ['nullable', 'string', 'max:5000'],
        ], $this->documentValidationRules(), $this->photoValidationRules()));

        $district = District::query()->with('hub')->findOrFail((int) $validated['district_id']);
        abort_unless((int) $district->hub_id === $hubId, 422, 'District must belong to your hub.');

        $hub = $district->hub ?? Hub::query()->findOrFail($hubId);

        $payload = [
            'hub_id' => $hubId,
            'hub_name' => (string) $hub->name,
            'district_id' => (int) $district->id,
            'district_name' => (string) $district->name,
            'visit_date' => $validated['visit_date'],
            'organization_name' => trim((string) $validated['organization_name']),
            'organization_type' => (string) $validated['organization_type'],
            'organization_type_other' => (string) $validated['organization_type'] === 'other'
                ? trim((string) ($validated['organization_type_other'] ?? ''))
                : null,
            'person_met_name' => trim((string) $validated['person_met_name']),
            'person_met_designation' => trim((string) ($validated['person_met_designation'] ?? '')) ?: null,
            'poc_name' => trim((string) $validated['poc_name']),
            'poc_phone' => (string) $validated['poc_phone'],
            'poc_email' => trim((string) ($validated['poc_email'] ?? '')) ?: null,
            'purpose' => (string) $validated['purpose'],
            'meeting_mode' => (string) $validated['meeting_mode'],
            'remarks' => trim((string) ($validated['remarks'] ?? '')) ?: null,
            'submitted_by_user_id' => (int) $user->id,
            'submitted_by_name' => (string) $user->name,
        ];

        if (Schema::hasColumn('community_organization_outreach_visits', 'documents_json')) {
            $payload['documents_json'] = $this->storeUploadedDocuments((array) $request->file('documents', []));
        }
        if (Schema::hasColumn('community_organization_outreach_visits', 'photos_json')) {
            $payload['photos_json'] = $this->storeUploadedPhotos($this->photoUploads($request));
        }

        CommunityOrganizationOutreachVisit::query()->create($payload);

        return redirect()
            ->route('hub.community-org-outreach.dashboard')
            ->with('status', 'Community organization outreach visit logged.');
    }

    public function dashboard(Request $request): View
    {
        $user = $request->user();
        abort_unless(CommunityOrgOutreachAccess::canViewDashboard($user), 403);

        if (! Schema::hasTable('community_organization_outreach_visits')) {
            return $this->dashboardView($request, collect(), true, [
                'q' => '',
                'from' => '',
                'to' => '',
                'district_id' => '',
                'hub_id' => '',
            ], ['total' => 0]);
        }

        $query = CommunityOrganizationOutreachVisit::query()
            ->with(['district:id,name', 'hub:id,name', 'submitter:id,name']);

        $this->scopeDashboardQuery($query, $user);

        $search = trim((string) $request->query('q', ''));
        if ($search !== '') {
            $like = '%'.str_replace(['\\', '%', '_'], ['\\\\', '\\%', '\\_'], $search).'%';
            $query->where(function ($q) use ($like): void {
                $q->where('organization_name', 'like', $like)
                    ->orWhere('person_met_name', 'like', $like)
                    ->orWhere('poc_name', 'like', $like)
                    ->orWhere('poc_phone', 'like', $like)
                    ->orWhere('district_name', 'like', $like)
                    ->orWhere('hub_name', 'like', $like)
                    ->orWhere('submitted_by_name', 'like', $like)
                    ->orWhere('remarks', 'like', $like)
                    ->orWhere('organization_type_other', 'like', $like);
            });
        }

        if ($request->filled('from')) {
            $query->whereDate('visit_date', '>=', (string) $request->query('from'));
        }
        if ($request->filled('to')) {
            $query->whereDate('visit_date', '<=', (string) $request->query('to'));
        }

        $districtFilter = (int) $request->query('district_id', 0);
        if ($districtFilter > 0) {
            $query->where('district_id', $districtFilter);
        }

        $hubFilter = (int) $request->query('hub_id', 0);
        if ($user->role === 'state_admin' && $hubFilter > 0) {
            $query->where('hub_id', $hubFilter);
        }

        $totals = ['total' => (int) (clone $query)->count()];

        $rows = $query
            ->orderByDesc('visit_date')
            ->orderByDesc('id')
            ->paginate(25)
            ->withQueryString();

        return $this->dashboardView($request, $rows, false, [
            'q' => $search,
            'from' => (string) $request->query('from', ''),
            'to' => (string) $request->query('to', ''),
            'district_id' => $districtFilter > 0 ? (string) $districtFilter : '',
            'hub_id' => $hubFilter > 0 ? (string) $hubFilter : '',
        ], $totals);
    }

    public function show(Request $request, CommunityOrganizationOutreachVisit $communityOrgOutreach): View
    {
        $user = $request->user();
        abort_unless(CommunityOrgOutreachAccess::canViewDashboard($user), 403);
        $this->assertCanAccessRecord($user, $communityOrgOutreach);

        $communityOrgOutreach->loadMissing(['district:id,name', 'hub:id,name', 'submitter:id,name']);
        $isAdmin = $user->role === 'state_admin';
        $routePrefix = $isAdmin ? 'admin' : 'hub';

        return view('community-org-outreach.show', [
            'row' => $communityOrgOutreach,
            'currentRole' => (string) $user->role,
            'dashboardRoute' => $isAdmin ? 'admin.community-org-outreach.dashboard' : 'hub.community-org-outreach.dashboard',
            'destroyRoute' => $isAdmin ? 'admin.community-org-outreach.destroy' : 'hub.community-org-outreach.destroy',
            'documentRoute' => $routePrefix.'.community-org-outreach.document',
            'photoRoute' => $routePrefix.'.community-org-outreach.photo',
            'canDelete' => CommunityOrgOutreachAccess::canDelete($user, $communityOrgOutreach),
            'purposes' => CommunityOrganizationOutreachOptions::purposes(),
            'meetingModes' => CommunityOrganizationOutreachOptions::meetingModes(),
        ]);
    }

    public function downloadDocument(Request $request, CommunityOrganizationOutreachVisit $communityOrgOutreach): StreamedResponse|BinaryFileResponse
    {
        $user = $request->user();
        abort_unless(CommunityOrgOutreachAccess::canViewDashboard($user), 403);
        $this->assertCanAccessRecord($user, $communityOrgOutreach);

        return $this->downloadStoredFile($communityOrgOutreach, 'documents_json', (int) $request->query('index', 0), $request);
    }

    public function downloadPhoto(Request $request, CommunityOrganizationOutreachVisit $communityOrgOutreach): StreamedResponse|BinaryFileResponse
    {
        $user = $request->user();
        abort_unless(CommunityOrgOutreachAccess::canViewDashboard($user), 403);
        $this->assertCanAccessRecord($user, $communityOrgOutreach);

        return $this->downloadStoredFile($communityOrgOutreach, 'photos_json', (int) $request->query('index', 0), $request, inlineByDefault: true);
    }

    public function destroy(Request $request, CommunityOrganizationOutreachVisit $communityOrgOutreach): RedirectResponse
    {
        $user = $request->user();
        abort_unless(CommunityOrgOutreachAccess::canDelete($user, $communityOrgOutreach), 403);

        $dashboardRoute = $user->role === 'state_admin'
            ? 'admin.community-org-outreach.dashboard'
            : 'hub.community-org-outreach.dashboard';

        $communityOrgOutreach->delete();

        return redirect()
            ->route($dashboardRoute)
            ->with('status', 'Outreach visit entry deleted.');
    }

    public function export(Request $request): StreamedResponse
    {
        $user = $request->user();
        abort_unless(CommunityOrgOutreachAccess::canViewDashboard($user), 403);

        $query = CommunityOrganizationOutreachVisit::query();
        $this->scopeDashboardQuery($query, $user);

        $search = trim((string) $request->query('q', ''));
        if ($search !== '') {
            $like = '%'.str_replace(['\\', '%', '_'], ['\\\\', '\\%', '\\_'], $search).'%';
            $query->where(function ($q) use ($like): void {
                $q->where('organization_name', 'like', $like)
                    ->orWhere('person_met_name', 'like', $like)
                    ->orWhere('poc_name', 'like', $like)
                    ->orWhere('district_name', 'like', $like)
                    ->orWhere('hub_name', 'like', $like);
            });
        }

        if ($request->filled('from')) {
            $query->whereDate('visit_date', '>=', (string) $request->query('from'));
        }
        if ($request->filled('to')) {
            $query->whereDate('visit_date', '<=', (string) $request->query('to'));
        }

        $districtFilter = (int) $request->query('district_id', 0);
        if ($districtFilter > 0) {
            $query->where('district_id', $districtFilter);
        }

        $hubFilter = (int) $request->query('hub_id', 0);
        if ($user->role === 'state_admin' && $hubFilter > 0) {
            $query->where('hub_id', $hubFilter);
        }

        $rows = $query->orderByDesc('visit_date')->orderByDesc('id')->get();

        $filename = 'community-org-outreach-'.now()->format('Ymd_His').'.csv';

        return response()->streamDownload(function () use ($rows): void {
            $out = fopen('php://output', 'w');
            if ($out === false) {
                return;
            }

            fputcsv($out, [
                'ID',
                'Visit date',
                'Hub',
                'District',
                'Organization',
                'Organization type',
                'Person met',
                'Designation',
                'POC name',
                'POC phone',
                'POC email',
                'Purpose',
                'Meeting mode',
                'Remarks',
                'Documents',
                'Photos',
                'Submitted by',
                'Submitted at',
            ]);

            foreach ($rows as $row) {
                fputcsv($out, [
                    $row->id,
                    $row->visit_date?->format('Y-m-d'),
                    $row->hub_name,
                    $row->district_name,
                    $row->organization_name,
                    CommunityOrganizationOutreachOptions::organizationTypeDisplay(
                        (string) $row->organization_type,
                        $row->organization_type_other
                    ),
                    $row->person_met_name,
                    $row->person_met_designation,
                    $row->poc_name,
                    $row->poc_phone,
                    $row->poc_email,
                    CommunityOrganizationOutreachOptions::labelFor('purpose', (string) $row->purpose),
                    CommunityOrganizationOutreachOptions::labelFor('meeting_mode', (string) $row->meeting_mode),
                    $row->remarks,
                    count((array) $row->documents_json),
                    count((array) $row->photos_json),
                    $row->submitted_by_name,
                    $row->created_at?->format('Y-m-d H:i'),
                ]);
            }

            fclose($out);
        }, $filename, [
            'Content-Type' => 'text/csv; charset=UTF-8',
        ]);
    }

    /**
     * @return array<string, list<string>>
     */
    private function documentValidationRules(): array
    {
        return [
            'documents' => ['nullable', 'array', 'max:10'],
            'documents.*' => ['file', 'mimes:pdf,doc,docx,xls,xlsx,jpg,jpeg,png,webp', 'max:10240'],
        ];
    }

    /**
     * @return array<string, list<string>>
     */
    private function photoValidationRules(): array
    {
        return [
            'photos' => ['nullable', 'array', 'max:25'],
            'photos.*' => ['file', 'mimes:jpg,jpeg,png,webp,gif,heic,heif', 'max:10240'],
        ];
    }

    /**
     * @return array<string, string>
     */
    private function documentUploadErrors(Request $request): array
    {
        $errors = [];

        foreach ((array) $request->file('documents', []) as $index => $file) {
            if (! $file instanceof UploadedFile || $file->isValid()) {
                continue;
            }

            $errors['documents.'.$index] = $this->describeFailedUpload($file);
        }

        return $errors;
    }

    /**
     * @return array<string, string>
     */
    private function photoUploadErrors(Request $request): array
    {
        $errors = [];

        foreach ($this->photoUploads($request) as $index => $file) {
            if (! $file instanceof UploadedFile || $file->isValid()) {
                continue;
            }

            $errors['photos.'.$index] = $this->describeFailedUpload($file);
        }

        return $errors;
    }

    /**
     * @param  list<UploadedFile|null>  $files
     * @return list<array<string, mixed>>
     */
    private function storeUploadedDocuments(array $files): array
    {
        $items = [];

        foreach ($files as $file) {
            if (! $file instanceof UploadedFile || ! $file->isValid()) {
                continue;
            }

            $path = $file->store('community-org-outreach-documents');
            $mime = (string) ($file->getClientMimeType() ?? '');
            $items[] = [
                'path' => $path,
                'original_name' => (string) $file->getClientOriginalName(),
                'mime' => $mime,
                'size_bytes' => (int) ($file->getSize() ?? 0),
                'type' => 'document',
            ];
        }

        return $items;
    }

    /**
     * @return list<UploadedFile>
     */
    private function photoUploads(Request $request): array
    {
        $files = $request->file('photos');
        if ($files === null) {
            return [];
        }

        return array_values(array_filter(
            is_array($files) ? $files : [$files],
            fn ($file): bool => $file instanceof UploadedFile
        ));
    }

    /**
     * @param  list<UploadedFile>  $files
     * @return list<array<string, mixed>>
     */
    private function storeUploadedPhotos(array $files): array
    {
        $items = [];

        foreach ($files as $file) {
            if (! $file instanceof UploadedFile || ! $file->isValid()) {
                continue;
            }

            $path = $file->store('community-org-outreach-photos');
            $mime = (string) ($file->getClientMimeType() ?? 'image/jpeg');
            $items[] = [
                'path' => $path,
                'original_name' => (string) $file->getClientOriginalName(),
                'mime' => $mime,
                'size_bytes' => (int) ($file->getSize() ?? 0),
                'type' => 'image',
            ];
        }

        return $items;
    }

    private function downloadStoredFile(
        CommunityOrganizationOutreachVisit $visit,
        string $column,
        int $index,
        Request $request,
        bool $inlineByDefault = false,
    ): StreamedResponse|BinaryFileResponse {
        $media = collect((array) $visit->{$column})->get(max(0, $index));
        abort_if(! is_array($media), 404);

        $path = (string) ($media['path'] ?? '');
        abort_if($path === '', 404);
        abort_unless(Storage::exists($path), 404);

        $filename = (string) ($media['original_name'] ?? basename($path));
        $mime = (string) ($media['mime'] ?? '');
        $inline = $request->boolean('inline', $inlineByDefault) && $this->canServeInline($mime, $filename);

        if ($inline) {
            return response()->file(Storage::path($path), [
                'Content-Type' => $mime !== '' ? $mime : 'application/octet-stream',
                'Content-Disposition' => 'inline; filename="'.str_replace('"', '', $filename).'"',
            ]);
        }

        return Storage::download($path, $filename);
    }

    private function canServeInline(string $mime, string $filename): bool
    {
        if (str_starts_with($mime, 'image/')) {
            return true;
        }

        return $mime === 'application/pdf' || str_ends_with(strtolower($filename), '.pdf');
    }

    private function hubAdminOrAbort(Request $request): User
    {
        $user = $request->user();
        abort_unless(CommunityOrgOutreachAccess::canSubmit($user), 403);

        return $user;
    }

    /** @return Collection<int, District> */
    private function hubDistricts(int $hubId): Collection
    {
        return District::query()
            ->where('hub_id', $hubId)
            ->orderBy('sort_order')
            ->orderBy('name')
            ->get(['id', 'name', 'hub_id']);
    }

    private function scopeDashboardQuery($query, User $user): void
    {
        if ($user->role === 'hub_admin') {
            $query->where('hub_id', (int) ($user->hub_id ?: 0));
        }
    }

    private function assertCanAccessRecord(User $user, CommunityOrganizationOutreachVisit $row): void
    {
        if ($user->role === 'state_admin') {
            return;
        }

        if ($user->role === 'hub_admin' && (int) $row->hub_id === (int) ($user->hub_id ?: 0)) {
            return;
        }

        abort(403);
    }

    private function dashboardView(Request $request, mixed $rows, bool $migrationMissing, array $filters, array $totals): View
    {
        $user = $request->user();
        $isAdmin = $user->role === 'state_admin';
        $hubId = (int) ($user->hub_id ?: 0);

        return view('community-org-outreach.dashboard', [
            'rows' => $rows,
            'migrationMissing' => $migrationMissing,
            'isPaginated' => ! $migrationMissing,
            'currentRole' => (string) $user->role,
            'isAdminView' => $isAdmin,
            'filters' => $filters,
            'totals' => $totals,
            'hubs' => $isAdmin ? Hub::query()->orderBy('sort_order')->orderBy('name')->get(['id', 'name']) : collect(),
            'districts' => $isAdmin
                ? District::query()->with('hub')->orderBy('hub_id')->orderBy('sort_order')->get(['id', 'name', 'hub_id'])
                : $this->hubDistricts($hubId),
            'dashboardRoute' => $isAdmin ? 'admin.community-org-outreach.dashboard' : 'hub.community-org-outreach.dashboard',
            'exportRoute' => $isAdmin ? 'admin.community-org-outreach.export' : 'hub.community-org-outreach.export',
            'showRoute' => $isAdmin ? 'admin.community-org-outreach.show' : 'hub.community-org-outreach.show',
            'documentRoute' => $isAdmin ? 'admin.community-org-outreach.document' : 'hub.community-org-outreach.document',
            'photoRoute' => $isAdmin ? 'admin.community-org-outreach.photo' : 'hub.community-org-outreach.photo',
            'createRoute' => CommunityOrgOutreachAccess::canSubmit($user) ? 'hub.community-org-outreach.create' : null,
            'destroyRoute' => $isAdmin ? 'admin.community-org-outreach.destroy' : 'hub.community-org-outreach.destroy',
        ]);
    }
}
