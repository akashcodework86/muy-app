<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\Designation;
use App\Models\District;
use App\Models\Hub;
use App\Models\User;
use App\Services\AdminAuditLogger;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Str;
use Illuminate\Validation\Rule;
use Illuminate\View\View;

class StaffController extends Controller
{
    public function __construct(
        private AdminAuditLogger $auditLogger,
    ) {}

    public function index(Request $request): View
    {
        $q = trim((string) $request->query('q', ''));

        $staffQuery = User::query()
            ->where('role', 'district_staff')
            ->with(['hub', 'district', 'designationRecord'])
            ->orderBy('district_id')
            ->orderBy('name');

        if ($q !== '') {
            $like = '%'.$q.'%';
            $staffQuery->where(function ($inner) use ($like): void {
                $inner->where('name', 'like', $like)
                    ->orWhere('email', 'like', $like)
                    ->orWhereHas('district', fn ($districtQ) => $districtQ->where('name', 'like', $like))
                    ->orWhereHas('designationRecord', fn ($designationQ) => $designationQ->where('name', 'like', $like));
            });
        }

        $staff = $staffQuery->get();

        return view('admin.staff.index', [
            'staff' => $staff,
            'filters' => ['q' => $q],
        ]);
    }

    public function create(): View
    {
        return view('admin.staff.create', [
            'hubs' => Hub::query()->orderBy('sort_order')->get(),
            'districts' => District::query()->with('hub')->orderBy('hub_id')->orderBy('sort_order')->get(),
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
            'hub_id' => ['required', 'integer', 'exists:hubs,id'],
            'district_id' => ['required', 'integer', 'exists:districts,id'],
        ]);

        $district = District::query()->findOrFail($validated['district_id']);
        if ((int) $district->hub_id !== (int) $validated['hub_id']) {
            return back()->withErrors(['district_id' => 'Selected district does not belong to the chosen hub.'])->withInput();
        }

        $token = $this->makeUniqueReferralToken();

        $user = User::query()->create([
            'name' => $validated['name'],
            'email' => $validated['email'],
            'password' => $validated['password'],
            'role' => 'district_staff',
            'designation_id' => $validated['designation_id'],
            'hub_id' => $validated['hub_id'],
            'district_id' => $validated['district_id'],
            'referral_token' => $token,
        ]);

        $this->auditLogger->record(
            $request,
            'staff.created',
            User::class,
            $user->id,
            null,
            [
                'name' => $user->name,
                'email' => $user->email,
                'designation_id' => $user->designation_id,
                'hub_id' => $user->hub_id,
                'district_id' => $user->district_id,
            ],
            'Staff user created',
        );

        return redirect()->route('admin.staff.index')->with('status', 'Staff user created. Next: allot CFA monthly targets, then share their apply link.');
    }

    public function edit(User $user): View
    {
        $this->assertDistrictStaff($user);

        return view('admin.staff.edit', [
            'user' => $user,
            'hubs' => Hub::query()->orderBy('sort_order')->get(),
            'districts' => District::query()->with('hub')->orderBy('hub_id')->orderBy('sort_order')->get(),
            'designations' => Designation::query()->orderBy('sort_order')->orderBy('name')->get(),
        ]);
    }

    public function update(Request $request, User $user): RedirectResponse
    {
        $this->assertDistrictStaff($user);

        $validated = $request->validate([
            'name' => ['required', 'string', 'max:191'],
            'email' => ['required', 'string', 'email', 'max:191', Rule::unique('users', 'email')->ignore($user->id)],
            'password' => ['nullable', 'string', 'min:8', 'max:255'],
            'designation_id' => ['required', 'integer', 'exists:designations,id'],
            'hub_id' => ['required', 'integer', 'exists:hubs,id'],
            'district_id' => ['required', 'integer', 'exists:districts,id'],
        ]);

        $district = District::query()->findOrFail($validated['district_id']);
        if ((int) $district->hub_id !== (int) $validated['hub_id']) {
            return back()->withErrors(['district_id' => 'Selected district does not belong to the chosen hub.'])->withInput();
        }

        $before = [
            'name' => $user->name,
            'email' => $user->email,
            'designation_id' => $user->designation_id,
            'hub_id' => $user->hub_id,
            'district_id' => $user->district_id,
            'is_active' => $user->is_active,
        ];

        $user->name = $validated['name'];
        $user->email = $validated['email'];
        $user->designation_id = $validated['designation_id'];
        $user->hub_id = $validated['hub_id'];
        $user->district_id = $validated['district_id'];
        if (! empty($validated['password'])) {
            $user->password = $validated['password'];
        }
        $user->save();

        $after = [
            'name' => $user->name,
            'email' => $user->email,
            'designation_id' => $user->designation_id,
            'hub_id' => $user->hub_id,
            'district_id' => $user->district_id,
            'is_active' => $user->is_active,
        ];
        if (! empty($validated['password'])) {
            $after['password_changed'] = true;
        }

        $this->auditLogger->record(
            $request,
            'staff.updated',
            User::class,
            $user->id,
            $before,
            $after,
            'Staff profile updated',
        );

        return redirect()->route('admin.staff.index')->with('status', 'Staff updated.');
    }

    public function destroy(Request $request, User $user): RedirectResponse
    {
        $this->assertDistrictStaff($user);

        $this->auditLogger->record(
            $request,
            'staff.deleted',
            User::class,
            $user->id,
            [
                'name' => $user->name,
                'email' => $user->email,
                'district_id' => $user->district_id,
                'hub_id' => $user->hub_id,
            ],
            null,
            'Staff user deleted',
        );

        $user->delete();

        return redirect()->route('admin.staff.index')->with('status', 'Staff user deleted.');
    }

    public function toggleActive(Request $request, User $user): RedirectResponse
    {
        $this->assertDistrictStaff($user);

        $before = ['is_active' => $user->is_active];
        $user->is_active = ! $user->is_active;
        $user->save();

        $this->auditLogger->record(
            $request,
            'staff.toggled_active',
            User::class,
            $user->id,
            $before,
            ['is_active' => $user->is_active],
            $user->is_active ? 'Staff enabled' : 'Staff disabled',
        );

        $msg = $user->is_active ? 'Staff enabled.' : 'Staff disabled (cannot log in; referral link stops working).';

        return redirect()->route('admin.staff.index')->with('status', $msg);
    }

    private function assertDistrictStaff(User $user): void
    {
        if ($user->role !== 'district_staff') {
            abort(404);
        }
    }

    private function makeUniqueReferralToken(): string
    {
        do {
            $token = Str::lower(Str::random(40));
        } while (User::query()->where('referral_token', $token)->exists());

        return $token;
    }
}
