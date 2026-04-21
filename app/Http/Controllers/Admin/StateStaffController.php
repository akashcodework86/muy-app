<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\Designation;
use App\Models\User;
use App\Services\AdminAuditLogger;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;
use Illuminate\View\View;

/**
 * Manages users with role = state_staff (checker / SPOC users).
 * These users approve / send back / reject service cases raised by district staff
 * for services where `services.requires_approval` is true. District → SPOC mapping
 * lives in a separate `district_service_spocs` table (next PR).
 */
class StateStaffController extends Controller
{
    public function __construct(
        private AdminAuditLogger $auditLogger,
    ) {}

    public function index(): View
    {
        $users = User::query()
            ->where('role', 'state_staff')
            ->with(['designationRecord'])
            ->orderBy('name')
            ->paginate(20);

        return view('admin.state-staff.index', [
            'users' => $users,
        ]);
    }

    public function create(): View
    {
        return view('admin.state-staff.create', [
            'designations' => Designation::query()->orderBy('sort_order')->orderBy('name')->get(),
        ]);
    }

    public function store(Request $request): RedirectResponse
    {
        $validated = $request->validate([
            'name' => ['required', 'string', 'max:191'],
            'email' => ['required', 'string', 'email', 'max:191', 'unique:users,email'],
            'password' => ['required', 'string', 'min:8', 'max:255'],
            'designation_id' => ['required', 'integer', 'exists:designations,id'],
        ]);

        $user = User::query()->create([
            'name' => $validated['name'],
            'email' => $validated['email'],
            'password' => $validated['password'],
            'role' => 'state_staff',
            'designation_id' => $validated['designation_id'],
        ]);

        $this->auditLogger->record(
            $request,
            'state_staff.created',
            User::class,
            $user->id,
            null,
            [
                'name' => $user->name,
                'email' => $user->email,
                'designation_id' => $user->designation_id,
            ],
            'State staff (SPOC) user created',
        );

        return redirect()
            ->route('admin.state-staff.index')
            ->with('status', 'State staff user created. Next: assign districts to this SPOC on the Service SPOCs page.');
    }

    public function edit(User $user): View
    {
        $this->assertStateStaff($user);

        return view('admin.state-staff.edit', [
            'user' => $user,
            'designations' => Designation::query()->orderBy('sort_order')->orderBy('name')->get(),
        ]);
    }

    public function update(Request $request, User $user): RedirectResponse
    {
        $this->assertStateStaff($user);

        $validated = $request->validate([
            'name' => ['required', 'string', 'max:191'],
            'email' => ['required', 'string', 'email', 'max:191', Rule::unique('users', 'email')->ignore($user->id)],
            'password' => ['nullable', 'string', 'min:8', 'max:255'],
            'designation_id' => ['required', 'integer', 'exists:designations,id'],
        ]);

        $before = [
            'name' => $user->name,
            'email' => $user->email,
            'designation_id' => $user->designation_id,
            'is_active' => $user->is_active,
        ];

        $user->name = $validated['name'];
        $user->email = $validated['email'];
        $user->designation_id = $validated['designation_id'];
        if (! empty($validated['password'])) {
            $user->password = $validated['password'];
        }
        $user->save();

        $after = [
            'name' => $user->name,
            'email' => $user->email,
            'designation_id' => $user->designation_id,
            'is_active' => $user->is_active,
        ];
        if (! empty($validated['password'])) {
            $after['password_changed'] = true;
        }

        $this->auditLogger->record(
            $request,
            'state_staff.updated',
            User::class,
            $user->id,
            $before,
            $after,
            'State staff profile updated',
        );

        return redirect()->route('admin.state-staff.index')->with('status', 'State staff updated.');
    }

    public function destroy(Request $request, User $user): RedirectResponse
    {
        $this->assertStateStaff($user);

        $this->auditLogger->record(
            $request,
            'state_staff.deleted',
            User::class,
            $user->id,
            [
                'name' => $user->name,
                'email' => $user->email,
            ],
            null,
            'State staff user deleted',
        );

        $user->delete();

        return redirect()->route('admin.state-staff.index')->with('status', 'State staff user deleted.');
    }

    public function toggleActive(Request $request, User $user): RedirectResponse
    {
        $this->assertStateStaff($user);

        $before = ['is_active' => $user->is_active];
        $user->is_active = ! $user->is_active;
        $user->save();

        $this->auditLogger->record(
            $request,
            'state_staff.toggled_active',
            User::class,
            $user->id,
            $before,
            ['is_active' => $user->is_active],
            $user->is_active ? 'State staff enabled' : 'State staff disabled',
        );

        $msg = $user->is_active
            ? 'State staff enabled.'
            : 'State staff disabled (cannot log in; cannot approve pending cases).';

        return redirect()->route('admin.state-staff.index')->with('status', $msg);
    }

    private function assertStateStaff(User $user): void
    {
        if ($user->role !== 'state_staff') {
            abort(404);
        }
    }
}
