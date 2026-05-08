<?php

namespace App\Http\Controllers\Staff;

use App\Http\Controllers\Controller;
use App\Models\DistrictBlock;
use App\Models\TrainingPackageAttendance;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use Illuminate\Validation\ValidationException;
use Illuminate\View\View;
use Symfony\Component\HttpFoundation\StreamedResponse;

class TrainingPackageAttendanceController extends Controller
{
    public function create(Request $request): View
    {
        $user = $request->user()->load('district');
        abort_unless($user && $user->role === 'district_staff', 403);

        $districtId = (int) ($user->district_id ?: 0);
        abort_unless($districtId > 0, 403, 'District is not assigned.');

        return view('staff.training-packages.form', [
            'entry' => null,
            'user' => $user,
            'blocks' => DistrictBlock::orderedNamesForDistrict($districtId),
            'selectedIdsInitial' => [],
        ]);
    }

    public function store(Request $request): RedirectResponse
    {
        $user = $request->user()->load('district');
        abort_unless($user && $user->role === 'district_staff', 403);

        $districtId = (int) ($user->district_id ?: 0);
        abort_unless($districtId > 0, 403, 'District is not assigned.');

        $validated = $request->validate([
            'event_date' => ['required', 'date'],
            'block' => ['required', 'string', 'max:191'],
            'training_package' => ['required', 'string', 'in:t1,t2,t3'],
            'attendance_file' => ['required', 'file', 'mimes:pdf,jpg,jpeg,png,webp', 'max:5120'],
            'manual_incubatee_ids' => ['required', 'array', 'min:1'],
            'manual_incubatee_ids.*' => ['integer', 'min:1'],
        ]);

        $selected = $this->resolveSelectedIncubatees($districtId, $validated['manual_incubatee_ids']);

        if ($selected === []) {
            throw ValidationException::withMessages([
                'manual_incubatee_ids' => 'Select at least one valid onboarded incubatee from your district.',
            ]);
        }

        $file = $request->file('attendance_file');
        $filePath = $file->store('training-package-attendance');

        TrainingPackageAttendance::query()->create([
            'event_taken_by_user_id' => (int) $user->id,
            'event_taken_by_name' => (string) $user->name,
            'event_date' => (string) $validated['event_date'],
            'district_id' => $districtId,
            'district_name' => (string) ($user->district?->name ?? ''),
            'block' => trim((string) $validated['block']),
            'training_package' => strtolower((string) $validated['training_package']),
            'attendance_file_path' => $filePath,
            'attendance_file_name' => (string) ($file->getClientOriginalName() ?? ''),
            'attendance_file_mime' => (string) ($file->getClientMimeType() ?? ''),
            'attendance_file_size' => (int) ($file->getSize() ?? 0),
            'selected_incubatees_json' => $selected,
            'selected_incubatees_count' => count($selected),
            'created_by' => (int) $user->id,
            'updated_by' => (int) $user->id,
        ]);

        return redirect()->route('staff.training-packages.index')->with('status', 'Training package attendance submitted.');
    }

    public function index(Request $request): View
    {
        $user = $request->user()->load('district');
        abort_unless($user && $user->role === 'district_staff', 403);

        $districtId = (int) ($user->district_id ?: 0);
        abort_unless($districtId > 0, 403, 'District is not assigned.');

        $query = $this->buildDashboardQuery($request, $districtId);

        $rows = $query->orderByDesc('event_date')
            ->orderByDesc('id')
            ->paginate(20)
            ->withQueryString();

        $blockOptions = TrainingPackageAttendance::query()
            ->where('district_id', $districtId)
            ->whereNotNull('block')
            ->where('block', '!=', '')
            ->distinct()
            ->orderBy('block')
            ->pluck('block')
            ->all();

        return view('staff.training-packages.index', [
            'rows' => $rows,
            'blockOptions' => $blockOptions,
        ]);
    }

    public function export(Request $request): StreamedResponse
    {
        $user = $request->user()->load('district');
        abort_unless($user && $user->role === 'district_staff', 403);

        $districtId = (int) ($user->district_id ?: 0);
        abort_unless($districtId > 0, 403, 'District is not assigned.');

        $rows = $this->buildDashboardQuery($request, $districtId)
            ->orderByDesc('event_date')
            ->orderByDesc('id')
            ->get();

        $filename = 'training-package-attendance-'.now()->format('Ymd_His').'.csv';
        $headers = [
            'Event Date',
            'Training Package',
            'Event Taken By',
            'District',
            'Block',
            'Attendance File',
            'Applicant Name',
            'Application No',
            'Phone',
            'Batch',
            'Applicant Block',
            'Selected Count (Entry)',
            'Submitted At',
        ];

        return response()->streamDownload(function () use ($rows, $headers): void {
            $out = fopen('php://output', 'w');
            if ($out === false) {
                return;
            }
            fwrite($out, "\xEF\xBB\xBF");
            fputcsv($out, $headers);

            foreach ($rows as $entry) {
                $selected = (array) ($entry->selected_incubatees_json ?? []);
                if ($selected === []) {
                    fputcsv($out, [
                        (string) ($entry->event_date?->format('Y-m-d') ?? ''),
                        strtoupper((string) $entry->training_package),
                        (string) $entry->event_taken_by_name,
                        (string) ($entry->district_name ?: ($entry->district?->name ?? '')),
                        (string) $entry->block,
                        (string) ($entry->attendance_file_name ?: ''),
                        '',
                        '',
                        '',
                        '',
                        '',
                        (string) ((int) $entry->selected_incubatees_count),
                        (string) ($entry->created_at?->format('Y-m-d H:i:s') ?? ''),
                    ]);
                    continue;
                }

                foreach ($selected as $applicant) {
                    $applicant = is_array($applicant) ? $applicant : [];
                    fputcsv($out, [
                        (string) ($entry->event_date?->format('Y-m-d') ?? ''),
                        strtoupper((string) $entry->training_package),
                        (string) $entry->event_taken_by_name,
                        (string) ($entry->district_name ?: ($entry->district?->name ?? '')),
                        (string) $entry->block,
                        (string) ($entry->attendance_file_name ?: ''),
                        (string) ($applicant['name'] ?? ''),
                        (string) ($applicant['application_no'] ?? ''),
                        (string) ($applicant['phone'] ?? ''),
                        (string) ($applicant['batch_name'] ?? ''),
                        (string) ($applicant['block'] ?? ''),
                        (string) ((int) $entry->selected_incubatees_count),
                        (string) ($entry->created_at?->format('Y-m-d H:i:s') ?? ''),
                    ]);
                }
            }

            fclose($out);
        }, $filename, [
            'Content-Type' => 'text/csv; charset=UTF-8',
        ]);
    }

    public function show(Request $request, TrainingPackageAttendance $trainingPackageAttendance): View
    {
        $user = $request->user();
        abort_unless($user && $user->role === 'district_staff', 403);
        abort_unless((int) $trainingPackageAttendance->district_id === (int) ($user->district_id ?: 0), 403);

        $trainingPackageAttendance->load(['eventTakenBy:id,name', 'district:id,name']);
        $search = trim((string) $request->query('q', ''));
        $applicants = collect((array) ($trainingPackageAttendance->selected_incubatees_json ?? []))
            ->map(fn ($row) => is_array($row) ? $row : [])
            ->filter(function (array $row) use ($search): bool {
                if ($search === '') {
                    return true;
                }
                $needle = mb_strtolower($search);
                $haystack = mb_strtolower(implode(' ', [
                    (string) ($row['name'] ?? ''),
                    (string) ($row['application_no'] ?? ''),
                    (string) ($row['phone'] ?? ''),
                    (string) ($row['batch_name'] ?? ''),
                    (string) ($row['block'] ?? ''),
                    (string) ($row['district'] ?? ''),
                ]));

                return str_contains($haystack, $needle);
            })
            ->values();

        return view('staff.training-packages.show', [
            'entry' => $trainingPackageAttendance,
            'applicants' => $applicants,
            'applicantSearch' => $search,
        ]);
    }

    public function exportSingle(Request $request, TrainingPackageAttendance $trainingPackageAttendance): StreamedResponse
    {
        $user = $request->user();
        abort_unless($user && $user->role === 'district_staff', 403);
        abort_unless((int) $trainingPackageAttendance->district_id === (int) ($user->district_id ?: 0), 403);

        $search = trim((string) $request->query('q', ''));
        $applicants = collect((array) ($trainingPackageAttendance->selected_incubatees_json ?? []))
            ->map(fn ($row) => is_array($row) ? $row : [])
            ->filter(function (array $row) use ($search): bool {
                if ($search === '') {
                    return true;
                }
                $needle = mb_strtolower($search);
                $haystack = mb_strtolower(implode(' ', [
                    (string) ($row['name'] ?? ''),
                    (string) ($row['application_no'] ?? ''),
                    (string) ($row['phone'] ?? ''),
                    (string) ($row['batch_name'] ?? ''),
                    (string) ($row['block'] ?? ''),
                    (string) ($row['district'] ?? ''),
                ]));

                return str_contains($haystack, $needle);
            })
            ->values();

        $filename = 'training-package-attendance-'.$trainingPackageAttendance->id.'-'.now()->format('Ymd_His').'.csv';

        return response()->streamDownload(function () use ($trainingPackageAttendance, $applicants): void {
            $out = fopen('php://output', 'w');
            if ($out === false) {
                return;
            }
            fwrite($out, "\xEF\xBB\xBF");
            fputcsv($out, [
                'Entry ID',
                'Event Date',
                'Training Package',
                'Event Taken By',
                'District',
                'Block',
                'Applicant Name',
                'Application No',
                'Phone',
                'Batch',
                'Applicant Block',
                'Applicant District',
                'Selected Count (Entry)',
                'Submitted At',
            ]);

            foreach ($applicants as $applicant) {
                fputcsv($out, [
                    (string) $trainingPackageAttendance->id,
                    (string) ($trainingPackageAttendance->event_date?->format('Y-m-d') ?? ''),
                    strtoupper((string) $trainingPackageAttendance->training_package),
                    (string) $trainingPackageAttendance->event_taken_by_name,
                    (string) ($trainingPackageAttendance->district_name ?: ($trainingPackageAttendance->district?->name ?? '')),
                    (string) $trainingPackageAttendance->block,
                    (string) ($applicant['name'] ?? ''),
                    (string) ($applicant['application_no'] ?? ''),
                    (string) ($applicant['phone'] ?? ''),
                    (string) ($applicant['batch_name'] ?? ''),
                    (string) ($applicant['block'] ?? ''),
                    (string) ($applicant['district'] ?? ''),
                    (string) ((int) $trainingPackageAttendance->selected_incubatees_count),
                    (string) ($trainingPackageAttendance->created_at?->format('Y-m-d H:i:s') ?? ''),
                ]);
            }
            fclose($out);
        }, $filename, [
            'Content-Type' => 'text/csv; charset=UTF-8',
        ]);
    }

    public function edit(Request $request, TrainingPackageAttendance $trainingPackageAttendance): View
    {
        $user = $request->user()->load('district');
        abort_unless($user && $user->role === 'district_staff', 403);
        abort_unless((int) $trainingPackageAttendance->district_id === (int) ($user->district_id ?: 0), 403);

        $districtId = (int) ($user->district_id ?: 0);
        $selectedIds = collect((array) ($trainingPackageAttendance->selected_incubatees_json ?? []))
            ->pluck('cfa_submission_id')
            ->filter(fn ($id) => is_numeric($id))
            ->map(fn ($id) => (int) $id)
            ->values()
            ->all();

        return view('staff.training-packages.form', [
            'entry' => $trainingPackageAttendance,
            'user' => $user,
            'blocks' => DistrictBlock::orderedNamesForDistrict($districtId),
            'selectedIdsInitial' => $selectedIds,
        ]);
    }

    public function update(Request $request, TrainingPackageAttendance $trainingPackageAttendance): RedirectResponse
    {
        $user = $request->user()->load('district');
        abort_unless($user && $user->role === 'district_staff', 403);
        abort_unless((int) $trainingPackageAttendance->district_id === (int) ($user->district_id ?: 0), 403);

        $districtId = (int) ($user->district_id ?: 0);
        abort_unless($districtId > 0, 403, 'District is not assigned.');

        $validated = $request->validate([
            'event_date' => ['required', 'date'],
            'block' => ['required', 'string', 'max:191'],
            'training_package' => ['required', 'string', 'in:t1,t2,t3'],
            'attendance_file' => ['nullable', 'file', 'mimes:pdf,jpg,jpeg,png,webp', 'max:5120'],
            'manual_incubatee_ids' => ['required', 'array', 'min:1'],
            'manual_incubatee_ids.*' => ['integer', 'min:1'],
        ]);

        $selected = $this->resolveSelectedIncubatees($districtId, $validated['manual_incubatee_ids']);
        if ($selected === []) {
            throw ValidationException::withMessages([
                'manual_incubatee_ids' => 'Select at least one valid onboarded incubatee from your district.',
            ]);
        }

        $filePath = (string) $trainingPackageAttendance->attendance_file_path;
        $fileName = (string) ($trainingPackageAttendance->attendance_file_name ?? '');
        $fileMime = (string) ($trainingPackageAttendance->attendance_file_mime ?? '');
        $fileSize = (int) ($trainingPackageAttendance->attendance_file_size ?? 0);

        if ($request->hasFile('attendance_file')) {
            $file = $request->file('attendance_file');
            $newPath = $file->store('training-package-attendance');
            if ($filePath !== '' && Storage::exists($filePath)) {
                Storage::delete($filePath);
            }
            $filePath = $newPath;
            $fileName = (string) ($file->getClientOriginalName() ?? '');
            $fileMime = (string) ($file->getClientMimeType() ?? '');
            $fileSize = (int) ($file->getSize() ?? 0);
        }

        if ($filePath === '') {
            throw ValidationException::withMessages([
                'attendance_file' => 'Attendance file is required.',
            ]);
        }

        $trainingPackageAttendance->update([
            'event_taken_by_user_id' => (int) $user->id,
            'event_taken_by_name' => (string) $user->name,
            'event_date' => (string) $validated['event_date'],
            'district_id' => $districtId,
            'district_name' => (string) ($user->district?->name ?? ''),
            'block' => trim((string) $validated['block']),
            'training_package' => strtolower((string) $validated['training_package']),
            'attendance_file_path' => $filePath,
            'attendance_file_name' => $fileName,
            'attendance_file_mime' => $fileMime,
            'attendance_file_size' => $fileSize,
            'selected_incubatees_json' => $selected,
            'selected_incubatees_count' => count($selected),
            'updated_by' => (int) $user->id,
        ]);

        return redirect()->route('staff.training-packages.index')->with('status', 'Training package attendance updated.');
    }

    public function downloadAttachment(Request $request, TrainingPackageAttendance $trainingPackageAttendance): StreamedResponse
    {
        $user = $request->user();
        abort_unless($user && $user->role === 'district_staff', 403);
        abort_unless((int) $trainingPackageAttendance->district_id === (int) ($user->district_id ?: 0), 403);
        abort_if(! $trainingPackageAttendance->attendance_file_path, 404);
        abort_unless(Storage::exists((string) $trainingPackageAttendance->attendance_file_path), 404);

        return Storage::download(
            (string) $trainingPackageAttendance->attendance_file_path,
            (string) ($trainingPackageAttendance->attendance_file_name ?: basename((string) $trainingPackageAttendance->attendance_file_path))
        );
    }

    public function searchIncubatees(Request $request)
    {
        $user = $request->user();
        abort_unless($user && $user->role === 'district_staff', 403);

        $districtId = (int) ($user->district_id ?: 0);
        abort_unless($districtId > 0, 403, 'District is not assigned.');

        $term = trim((string) $request->query('q', ''));

        $baseQuery = $this->onboardedBaseQuery($districtId);
        $totalApplicants = (clone $baseQuery)->distinct('cs.id')->count('cs.id');
        $query = clone $baseQuery;

        if ($term !== '') {
            $like = '%'.str_replace(['\\', '%', '_'], ['\\\\', '\\%', '\\_'], $term).'%';
            $query->where(function ($q) use ($like): void {
                $q->where('cs.application_no', 'like', $like)
                    ->orWhere('cs.applicant_name', 'like', $like)
                    ->orWhere('cs.phone', 'like', $like)
                    ->orWhere('ob.name', 'like', $like);
            });
        }

        $rows = $query->orderBy('cs.applicant_name')
            ->orderBy('cs.application_no')
            ->get();

        return response()->json([
            'total_applicants' => (int) $totalApplicants,
            'filtered_count' => (int) $rows->count(),
            'data' => $rows->map(fn ($row) => [
                'cfa_submission_id' => (int) $row->cfa_submission_id,
                'application_no' => (string) $row->application_no,
                'name' => (string) $row->applicant_name,
                'phone' => (string) ($row->phone ?? ''),
                'batch_id' => (int) $row->batch_id,
                'batch_name' => (string) $row->batch_name,
                'district' => (string) ($row->district_name ?? ''),
                'block' => (string) ($row->block_name ?? ''),
            ])->values()->all(),
        ]);
    }

    /**
     * @param  list<int|string>  $submittedIds
     * @return list<array<string, mixed>>
     */
    private function resolveSelectedIncubatees(int $districtId, array $submittedIds): array
    {
        $ids = collect($submittedIds)
            ->filter(fn ($id) => is_numeric($id))
            ->map(fn ($id) => (int) $id)
            ->filter(fn (int $id) => $id > 0)
            ->unique()
            ->values()
            ->all();

        if ($ids === []) {
            return [];
        }

        $rows = $this->onboardedBaseQuery($districtId)
            ->whereIn('cs.id', $ids)
            ->orderBy('cs.applicant_name')
            ->get();

        if ($rows->count() !== count($ids)) {
            return [];
        }

        return $rows->map(fn ($row) => [
            'cfa_submission_id' => (int) $row->cfa_submission_id,
            'application_no' => (string) $row->application_no,
            'name' => (string) $row->applicant_name,
            'phone' => (string) ($row->phone ?? ''),
            'batch_id' => (int) $row->batch_id,
            'batch_name' => (string) $row->batch_name,
            'district' => (string) ($row->district_name ?? ''),
            'block' => (string) ($row->block_name ?? ''),
        ])->values()->all();
    }

    private function onboardedBaseQuery(int $districtId)
    {
        return DB::table('onboarding_batch_cfa as obc')
            ->join('onboarding_batches as ob', 'ob.id', '=', 'obc.onboarding_batch_id')
            ->join('cfa_submissions as cs', 'cs.id', '=', 'obc.cfa_submission_id')
            ->leftJoin('districts as d', 'd.id', '=', 'cs.district_id')
            ->where('ob.status', 'locked')
            ->whereNotNull('ob.locked_at')
            ->where('cs.district_id', $districtId)
            ->select([
                'cs.id as cfa_submission_id',
                'cs.application_no',
                'cs.applicant_name',
                'cs.phone',
                'ob.id as batch_id',
                'ob.name as batch_name',
                'd.name as district_name',
                DB::raw("JSON_UNQUOTE(JSON_EXTRACT(cs.payload, '$.block')) as block_name"),
            ]);
    }

    private function buildDashboardQuery(Request $request, int $districtId)
    {
        $query = TrainingPackageAttendance::query()
            ->where('district_id', $districtId)
            ->with(['eventTakenBy:id,name', 'district:id,name']);

        if ($request->filled('from')) {
            $query->whereDate('event_date', '>=', (string) $request->query('from'));
        }
        if ($request->filled('to')) {
            $query->whereDate('event_date', '<=', (string) $request->query('to'));
        }
        if ($request->filled('block')) {
            $query->where('block', (string) $request->query('block'));
        }
        if ($request->filled('training_package')) {
            $query->where('training_package', strtolower((string) $request->query('training_package')));
        }

        return $query;
    }
}
