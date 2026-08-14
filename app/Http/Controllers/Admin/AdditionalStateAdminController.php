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

final class AdditionalStateAdminController extends Controller
{
    public function __construct(
        private readonly AdminAuditLogger $auditLogger,
    ) {}

    public function index(): View
    {
        return view('admin.additional-state-admins.index', [
            'users' => User::query()
                ->where('role', 'state_admin')
                ->where('is_primary_state_admin', false)
                ->with('designationRecord')
                ->orderBy('name')
                ->paginate(20),
        ]);
    }

    public function create(): View
    {
        return view('admin.additional-state-admins.create');
    }

    public function store(Request $request): RedirectResponse
    {
        $validated = $request->validate([
            'name' => ['required', 'string', 'max:191'],
            'email' => ['required', 'string', 'email', 'max:191', 'unique:users,email'],
            'password' => ['required', 'string', 'min:8', 'max:255'],
        ]);

        $designation = Designation::query()->firstOrCreate(
            ['name' => 'Executive Director'],
            ['sort_order' => 2],
        );

        $user = User::query()->create([
            'name' => $validated['name'],
            'email' => $validated['email'],
            'password' => $validated['password'],
            'role' => 'state_admin',
            'is_primary_state_admin' => false,
            'designation_id' => $designation->id,
            'is_active' => true,
        ]);

        $this->auditLogger->record(
            $request,
            'additional_state_admin.created',
            User::class,
            $user->id,
            null,
            $this->auditState($user),
            'Additional State Admin account created',
        );

        return redirect()
            ->route('admin.additional-state-admins.index')
            ->with('status', 'Executive Director account created.');
    }

    public function edit(User $user): View
    {
        $this->assertAdditionalAdmin($user);

        return view('admin.additional-state-admins.edit', compact('user'));
    }

    public function update(Request $request, User $user): RedirectResponse
    {
        $this->assertAdditionalAdmin($user);

        $validated = $request->validate([
            'name' => ['required', 'string', 'max:191'],
            'email' => ['required', 'string', 'email', 'max:191', Rule::unique('users', 'email')->ignore($user->id)],
            'password' => ['nullable', 'string', 'min:8', 'max:255'],
        ]);

        $before = $this->auditState($user);
        $user->name = $validated['name'];
        $user->email = $validated['email'];
        if (! empty($validated['password'])) {
            $user->password = $validated['password'];
        }
        $user->save();

        $after = $this->auditState($user);
        if (! empty($validated['password'])) {
            $after['password_changed'] = true;
        }

        $this->auditLogger->record(
            $request,
            'additional_state_admin.updated',
            User::class,
            $user->id,
            $before,
            $after,
            'Additional State Admin account updated',
        );

        return redirect()
            ->route('admin.additional-state-admins.index')
            ->with('status', 'Executive Director account updated.');
    }

    public function toggleActive(Request $request, User $user): RedirectResponse
    {
        $this->assertAdditionalAdmin($user);

        $before = $this->auditState($user);
        $user->is_active = ! $user->is_active;
        $user->save();

        $this->auditLogger->record(
            $request,
            'additional_state_admin.toggled_active',
            User::class,
            $user->id,
            $before,
            $this->auditState($user),
            $user->is_active ? 'Additional State Admin enabled' : 'Additional State Admin disabled',
        );

        return redirect()
            ->route('admin.additional-state-admins.index')
            ->with('status', $user->is_active ? 'Executive Director account enabled.' : 'Executive Director account disabled.');
    }

    private function assertAdditionalAdmin(User $user): void
    {
        abort_unless($user->isAdditionalStateAdmin(), 404);
    }

    /** @return array<string, mixed> */
    private function auditState(User $user): array
    {
        return [
            'name' => $user->name,
            'email' => $user->email,
            'role' => $user->role,
            'designation_id' => $user->designation_id,
            'is_primary_state_admin' => $user->is_primary_state_admin,
            'is_active' => $user->is_active,
        ];
    }
}
