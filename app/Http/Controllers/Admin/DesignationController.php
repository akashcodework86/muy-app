<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\Designation;
use App\Services\AdminAuditLogger;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;
use Illuminate\View\View;

class DesignationController extends Controller
{
    public function __construct(
        private AdminAuditLogger $auditLogger,
    ) {}

    public function index(): View
    {
        $designations = Designation::query()
            ->orderBy('sort_order')
            ->orderBy('name')
            ->withCount('users')
            ->get();

        return view('admin.designations.index', ['designations' => $designations]);
    }

    public function create(): View
    {
        return view('admin.designations.create');
    }

    public function store(Request $request): RedirectResponse
    {
        $validated = $request->validate([
            'name' => ['required', 'string', 'max:120', 'unique:designations,name'],
            'sort_order' => ['nullable', 'integer', 'min:0', 'max:65535'],
        ]);

        $designation = Designation::query()->create([
            'name' => $validated['name'],
            'sort_order' => (int) ($validated['sort_order'] ?? 0),
        ]);

        $this->auditLogger->record(
            $request,
            'designation.created',
            Designation::class,
            $designation->id,
            null,
            ['name' => $designation->name, 'sort_order' => $designation->sort_order],
            'Designation created',
        );

        return redirect()->route('admin.designations.index')->with('status', 'Designation created.');
    }

    public function edit(Designation $designation): View
    {
        return view('admin.designations.edit', ['designation' => $designation]);
    }

    public function update(Request $request, Designation $designation): RedirectResponse
    {
        $validated = $request->validate([
            'name' => ['required', 'string', 'max:120', Rule::unique('designations', 'name')->ignore($designation->id)],
            'sort_order' => ['nullable', 'integer', 'min:0', 'max:65535'],
        ]);

        $before = ['name' => $designation->name, 'sort_order' => $designation->sort_order];

        $designation->name = $validated['name'];
        $designation->sort_order = (int) ($validated['sort_order'] ?? 0);
        $designation->save();

        $this->auditLogger->record(
            $request,
            'designation.updated',
            Designation::class,
            $designation->id,
            $before,
            ['name' => $designation->name, 'sort_order' => $designation->sort_order],
            'Designation updated',
        );

        return redirect()->route('admin.designations.index')->with('status', 'Designation updated.');
    }

    public function destroy(Request $request, Designation $designation): RedirectResponse
    {
        if ($designation->users()->exists()) {
            return redirect()
                ->route('admin.designations.index')
                ->withErrors(['delete' => 'Cannot delete: one or more staff users use this designation. Reassign them first.']);
        }

        $this->auditLogger->record(
            $request,
            'designation.deleted',
            Designation::class,
            $designation->id,
            ['name' => $designation->name, 'sort_order' => $designation->sort_order],
            null,
            'Designation deleted',
        );

        $designation->delete();

        return redirect()->route('admin.designations.index')->with('status', 'Designation deleted.');
    }
}
