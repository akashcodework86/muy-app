<?php

namespace App\Http\Controllers\Staff;

use App\Http\Controllers\Controller;
use App\Http\Controllers\Concerns\ValidatesAttendanceMediaUploads;
use App\Models\DistrictBlock;
use App\Models\FieldCoordinatorAttendanceReport;
use App\Models\GramPanchayat;
use App\Models\User;
use App\Services\FieldVisitMediaStorage;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Schema;
use Illuminate\View\View;
use Symfony\Component\HttpFoundation\StreamedResponse;

class FieldCoordinatorAttendanceController extends Controller
{
    use ValidatesAttendanceMediaUploads;

    public function __construct(
        private readonly FieldVisitMediaStorage $mediaStorage,
    ) {}

    public function index(Request $request): View
    {
        $user = $request->user()->load(['district', 'designationRecord']);
        abort_unless($this->isFieldCoordinator($user), 403);

        $districtId = (int) ($user->district_id ?: 0);
        $blockRows = $districtId > 0
            ? DistrictBlock::query()
                ->where('district_id', $districtId)
                ->orderBy('sort_order')
                ->orderBy('name')
                ->get(['id', 'name'])
            : collect();

        if (! Schema::hasTable('field_coordinator_attendance_reports')) {
            return view('staff.attendance.index', [
                'reports' => collect(),
                'user' => $user,
                'blockRows' => $blockRows,
                'gramPanchayatsEnabled' => false,
                'migrationMissing' => true,
            ]);
        }

        $reports = FieldCoordinatorAttendanceReport::query()
            ->where('field_coordinator_user_id', (int) $user->id)
            ->with(['district', 'gramPanchayat'])
            ->orderByDesc('visit_date')
            ->orderByDesc('id')
            ->paginate(20)
            ->withQueryString();

        return view('staff.attendance.index', [
            'reports' => $reports,
            'user' => $user,
            'blockRows' => $blockRows,
            'gramPanchayatsEnabled' => Schema::hasTable('gram_panchayats'),
            'migrationMissing' => false,
        ]);
    }

    public function gramPanchayats(Request $request): JsonResponse
    {
        $user = $request->user();
        abort_unless($this->isFieldCoordinator($user), 403);
        abort_unless(Schema::hasTable('gram_panchayats'), 404);

        $blockId = (int) $request->query('district_block_id', 0);
        abort_if($blockId <= 0, 422);

        $block = DistrictBlock::query()->findOrFail($blockId);
        abort_unless((int) $block->district_id === (int) ($user->district_id ?: 0), 403);

        $search = trim((string) $request->query('q', ''));

        $query = GramPanchayat::query()
            ->where('district_block_id', $blockId)
            ->orderBy('name');

        if ($search !== '') {
            $like = '%'.str_replace(['\\', '%', '_'], ['\\\\', '\\%', '\\_'], $search).'%';
            $query->where('name', 'like', $like);
        }

        $items = $query->limit(100)->get(['id', 'name']);

        return response()->json([
            'items' => $items->map(fn (GramPanchayat $gp) => [
                'id' => $gp->id,
                'name' => $gp->name,
            ])->values(),
        ]);
    }

    public function view(Request $request): View
    {
        $user = $request->user()->load(['district', 'designationRecord']);

        if (! Schema::hasTable('field_coordinator_attendance_reports')) {
            return view('staff.attendance.view', [
                'reports' => collect(),
                'user' => $user,
                'blockOptions' => [],
                'migrationMissing' => true,
            ]);
        }

        $query = FieldCoordinatorAttendanceReport::query()
            ->with(['district', 'gramPanchayat']);
        if ($this->isFieldCoordinator($user)) {
            $query->where('field_coordinator_user_id', (int) $user->id);
        } else {
            $query->where('district_id', (int) ($user->district_id ?: 0));
        }

        if ($request->filled('from')) {
            $query->whereDate('visit_date', '>=', $request->query('from'));
        }
        if ($request->filled('to')) {
            $query->whereDate('visit_date', '<=', $request->query('to'));
        }
        if ($request->filled('block')) {
            $query->where('block', $request->query('block'));
        }

        $reports = $query
            ->orderByDesc('visit_date')
            ->orderByDesc('id')
            ->paginate(25)
            ->withQueryString();

        $blockOptionsQuery = FieldCoordinatorAttendanceReport::query();
        if ($this->isFieldCoordinator($user)) {
            $blockOptionsQuery->where('field_coordinator_user_id', (int) $user->id);
        } else {
            $blockOptionsQuery->where('district_id', (int) ($user->district_id ?: 0));
        }
        $blockOptions = $blockOptionsQuery
            ->whereNotNull('block')
            ->where('block', '!=', '')
            ->distinct()
            ->orderBy('block')
            ->pluck('block')
            ->all();

        return view('staff.attendance.view', [
            'reports' => $reports,
            'user' => $user,
            'blockOptions' => $blockOptions,
            'migrationMissing' => false,
        ]);
    }

    public function store(Request $request): RedirectResponse
    {
        $user = $request->user()->load(['district', 'designationRecord']);
        abort_unless($this->isFieldCoordinator($user), 403);

        if (! Schema::hasTable('field_coordinator_attendance_reports')) {
            return redirect()
                ->route('staff.attendance.index')
                ->withErrors(['attendance' => 'Attendance table is missing. Please run migrations first.']);
        }

        $rules = [
            'visit_date' => ['required', 'date'],
            'district_block_id' => ['required', 'integer', 'exists:district_blocks,id'],
            'gram_panchayat_id' => ['required', 'integer', 'exists:gram_panchayats,id'],
            'remark' => ['nullable', 'string', 'max:2000'],
            'visit_media' => ['required', 'array', 'min:1', 'max:15'],
            'visit_media.*' => ['required', 'file', 'mimes:jpg,jpeg,png,webp', 'max:5120'],
        ];

        if (! Schema::hasTable('gram_panchayats')) {
            unset($rules['gram_panchayat_id']);
            $rules['gram_panchayat_id'] = ['nullable'];
        }

        $validated = $request->validate($rules);
        $uploadErrors = [];
        foreach ((array) $request->file('visit_media', []) as $index => $file) {
            if ($file instanceof \Illuminate\Http\UploadedFile && ! $file->isValid()) {
                $uploadErrors['visit_media.'.$index] = $this->describeFailedUpload($file);
            }
        }
        if ($uploadErrors !== []) {
            return back()->withErrors($uploadErrors)->withInput();
        }

        $districtId = (int) ($user->district_id ?: 0);
        $block = DistrictBlock::query()->findOrFail((int) $validated['district_block_id']);
        abort_unless((int) $block->district_id === $districtId, 422);

        $gramPanchayat = null;
        if (Schema::hasTable('gram_panchayats')) {
            $gramPanchayat = GramPanchayat::query()->findOrFail((int) $validated['gram_panchayat_id']);
            abort_unless((int) $gramPanchayat->district_block_id === (int) $block->id, 422);
        }

        $mediaItems = $this->mediaStorage->storeMany((array) $request->file('visit_media', []));
        if ($mediaItems === []) {
            return back()
                ->withErrors(['visit_media' => 'Upload at least one photo.'])
                ->withInput();
        }

        FieldCoordinatorAttendanceReport::query()->create([
            'field_coordinator_user_id' => (int) $user->id,
            'field_coordinator_name' => (string) $user->name,
            'visit_date' => $validated['visit_date'],
            'entry_date' => now()->toDateString(),
            'block' => (string) $block->name,
            'district_block_id' => (int) $block->id,
            'gram_panchayat_id' => $gramPanchayat?->id,
            'remark' => $validated['remark'] ?? null,
            'visit_media_json' => $mediaItems,
            'district_id' => $districtId > 0 ? $districtId : null,
            'villages_visited_total' => 0,
            'villages_covered' => null,
            'participants_total' => 0,
            'cfas_filled_total' => 0,
            'outreach_programmes_total' => 0,
        ]);

        return redirect()
            ->route('staff.attendance.index')
            ->with('status', 'Field visit photos submitted.');
    }

    public function downloadAttachment(
        FieldCoordinatorAttendanceReport $attendanceReport,
        Request $request,
    ): StreamedResponse {
        $user = $request->user()->load('designationRecord');
        $isOwn = (int) $attendanceReport->field_coordinator_user_id === (int) $user->id;
        $isDistrictViewer = ! $this->isFieldCoordinator($user)
            && (int) ($attendanceReport->district_id ?: 0) > 0
            && (int) ($attendanceReport->district_id ?: 0) === (int) ($user->district_id ?: 0);
        abort_unless($isOwn || $isDistrictViewer, 403);

        $index = $request->query('index');
        if ($index !== null && $index !== '') {
            return $this->mediaStorage->download(
                $attendanceReport,
                (int) $index,
                $request->boolean('inline'),
            );
        }

        return $this->mediaStorage->legacyDownload($attendanceReport);
    }

    private function isFieldCoordinator(User $user): bool
    {
        $designation = strtolower(trim((string) ($user->designationRecord?->name ?? '')));

        return str_contains($designation, 'field coordinator')
            || str_contains($designation, 'field co-ordinator');
    }
}
