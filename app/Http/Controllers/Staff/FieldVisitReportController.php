<?php

namespace App\Http\Controllers\Staff;

use App\Http\Controllers\Controller;
use App\Models\District;
use App\Models\DistrictBlock;
use App\Models\FieldVisitReport;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;
use Illuminate\View\View;

class FieldVisitReportController extends Controller
{
    public function index(): View
    {
        $reports = FieldVisitReport::query()
            ->where('user_id', auth()->id())
            ->with(['district', 'block'])
            ->orderByDesc('visit_date')
            ->orderByDesc('created_at')
            ->paginate(20);

        return view('staff.field-reports.index', compact('reports'));
    }

    public function create(): View
    {
        $user      = auth()->user();
        $districts = District::orderBy('name')->get(['id', 'name']);

        $blocks = $user->district_id
            ? DistrictBlock::where('district_id', $user->district_id)->orderBy('name')->get()
            : collect();

        return view('staff.field-reports.create', compact('districts', 'blocks', 'user'));
    }

    public function store(Request $request): RedirectResponse
    {
        $data = $this->validatedData($request);

        $attachmentPath = null;
        $originalName   = null;

        if ($request->hasFile('attachment') && $request->file('attachment')->isValid()) {
            $file           = $request->file('attachment');
            $attachmentPath = $file->store('field-visit-attachments', 'public');
            $originalName   = $file->getClientOriginalName();
        }

        $report = FieldVisitReport::create([
            'user_id'                  => auth()->id(),
            'district_id'              => $data['district_id'],
            'block_id'                 => $data['block_id'] ?? null,
            'visit_date'               => $data['visit_date'],
            'area'                     => $data['area'] ?? null,
            'total_villages'           => $data['total_villages'],
            'village_names'            => $data['village_names'] ?? null,
            'total_participants'       => $data['total_participants'],
            'outreach_programmes'      => $data['outreach_programmes'],
            'cfas_reported'            => $data['cfas_reported'],
            'attachment_path'          => $attachmentPath,
            'attachment_original_name' => $originalName,
        ]);

        $report->recalculateVerified();

        return redirect()->route('staff.field-reports.index')
            ->with('status', 'Field visit report submitted successfully.');
    }

    public function edit(FieldVisitReport $fieldReport): View
    {
        abort_if($fieldReport->user_id !== auth()->id(), 403);

        $user      = auth()->user();
        $districts = District::orderBy('name')->get(['id', 'name']);

        $blocks = $fieldReport->district_id
            ? DistrictBlock::where('district_id', $fieldReport->district_id)->orderBy('name')->get()
            : collect();

        return view('staff.field-reports.edit', compact('fieldReport', 'districts', 'blocks', 'user'));
    }

    public function update(Request $request, FieldVisitReport $fieldReport): RedirectResponse
    {
        abort_if($fieldReport->user_id !== auth()->id(), 403);

        $data = $this->validatedData($request);

        $attachmentPath = $fieldReport->attachment_path;
        $originalName   = $fieldReport->attachment_original_name;

        if ($request->hasFile('attachment') && $request->file('attachment')->isValid()) {
            // Delete old attachment if exists
            if ($fieldReport->attachment_path) {
                Storage::disk('public')->delete($fieldReport->attachment_path);
            }
            $file           = $request->file('attachment');
            $attachmentPath = $file->store('field-visit-attachments', 'public');
            $originalName   = $file->getClientOriginalName();
        }

        if ($request->boolean('remove_attachment') && $fieldReport->attachment_path) {
            Storage::disk('public')->delete($fieldReport->attachment_path);
            $attachmentPath = null;
            $originalName   = null;
        }

        $fieldReport->update([
            'district_id'              => $data['district_id'],
            'block_id'                 => $data['block_id'] ?? null,
            'visit_date'               => $data['visit_date'],
            'area'                     => $data['area'] ?? null,
            'total_villages'           => $data['total_villages'],
            'village_names'            => $data['village_names'] ?? null,
            'total_participants'       => $data['total_participants'],
            'outreach_programmes'      => $data['outreach_programmes'],
            'cfas_reported'            => $data['cfas_reported'],
            'attachment_path'          => $attachmentPath,
            'attachment_original_name' => $originalName,
        ]);

        $fieldReport->recalculateVerified();

        return redirect()->route('staff.field-reports.index')
            ->with('status', 'Field visit report updated successfully.');
    }

    private function validatedData(Request $request): array
    {
        return $request->validate([
            'district_id'         => ['required', 'integer', 'exists:districts,id'],
            'block_id'            => ['nullable', 'integer', 'exists:district_blocks,id'],
            'visit_date'          => ['required', 'date', 'before_or_equal:today'],
            'area'                => ['nullable', 'string', 'max:191'],
            'total_villages'      => ['required', 'integer', 'min:0', 'max:500'],
            'village_names'       => ['nullable', 'string', 'max:2000'],
            'total_participants'  => ['required', 'integer', 'min:0', 'max:10000'],
            'outreach_programmes' => ['required', 'integer', 'min:0', 'max:500'],
            'cfas_reported'       => ['required', 'integer', 'min:0', 'max:500'],
            'attachment'          => ['nullable', 'file', 'mimes:jpg,jpeg,png,pdf', 'max:5120'],
        ]);
    }
}
