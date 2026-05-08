<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\District;
use App\Models\TrainingPackageAttendance;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;
use Illuminate\View\View;
use Symfony\Component\HttpFoundation\StreamedResponse;

class TrainingPackageAttendanceAdminController extends Controller
{
    public function index(Request $request): View
    {
        $query = $this->buildDashboardQuery($request);

        $rows = $query->orderByDesc('event_date')
            ->orderByDesc('id')
            ->paginate(30)
            ->withQueryString();

        $districts = District::query()->orderBy('name')->get(['id', 'name']);
        $blockOptions = TrainingPackageAttendance::query()
            ->whereNotNull('block')
            ->where('block', '!=', '')
            ->distinct()
            ->orderBy('block')
            ->pluck('block')
            ->all();

        return view('admin.training-packages.index', [
            'rows' => $rows,
            'districts' => $districts,
            'blockOptions' => $blockOptions,
        ]);
    }

    public function export(Request $request): StreamedResponse
    {
        $rows = $this->buildDashboardQuery($request)
            ->orderByDesc('event_date')
            ->orderByDesc('id')
            ->get();

        $filename = 'training-package-attendance-admin-'.now()->format('Ymd_His').'.csv';
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

    public function show(TrainingPackageAttendance $trainingPackageAttendance): View
    {
        $trainingPackageAttendance->load(['eventTakenBy:id,name', 'district:id,name']);

        return view('admin.training-packages.show', [
            'entry' => $trainingPackageAttendance,
        ]);
    }

    public function downloadAttachment(TrainingPackageAttendance $trainingPackageAttendance): StreamedResponse
    {
        abort_if(! $trainingPackageAttendance->attendance_file_path, 404);
        abort_unless(Storage::exists((string) $trainingPackageAttendance->attendance_file_path), 404);

        return Storage::download(
            (string) $trainingPackageAttendance->attendance_file_path,
            (string) ($trainingPackageAttendance->attendance_file_name ?: basename((string) $trainingPackageAttendance->attendance_file_path))
        );
    }

    private function buildDashboardQuery(Request $request)
    {
        $query = TrainingPackageAttendance::query()
            ->with(['eventTakenBy:id,name', 'district:id,name']);

        if ($request->filled('from')) {
            $query->whereDate('event_date', '>=', (string) $request->query('from'));
        }
        if ($request->filled('to')) {
            $query->whereDate('event_date', '<=', (string) $request->query('to'));
        }
        if ($request->filled('district_id')) {
            $query->where('district_id', (int) $request->query('district_id'));
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
