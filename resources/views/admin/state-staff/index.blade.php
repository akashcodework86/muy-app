@extends('layouts.admin')

@section('title', 'State staff (SPOC)')
@section('heading', 'State staff (SPOC)')

@section('content')
    <p style="font-size:0.9rem; color:#52525b; margin-top:0;">
        State staff are <strong>checkers / SPOCs</strong>: they approve, send back, or reject service cases raised by district staff for services that require maker-checker verification.
        One SPOC can cover multiple districts, but a district can only have one SPOC at a time.
        Assign districts on the <a href="{{ url('/admin/service-spocs') }}">Service SPOCs</a> page (coming soon).
    </p>

    @if (session('status'))
        <p style="background:#dcfce7; color:#166534; padding:0.5rem 0.75rem; border-radius:6px; font-size:0.88rem; margin:0.5rem 0 1rem;">{{ session('status') }}</p>
    @endif

    <p style="margin:0.75rem 0 1rem;">
        <a href="{{ route('admin.state-staff.create') }}" style="display:inline-block; background:#18181b; color:#fff; padding:0.45rem 0.85rem; border-radius:6px; text-decoration:none; font-size:0.9rem;">Add state staff</a>
    </p>

    <div style="overflow-x:auto;">
        <table style="width:100%; border-collapse:collapse; background:#fff; border:1px solid #e4e4e7; border-radius:8px; font-size:0.875rem;">
            <thead>
                <tr style="background:#fafafa; text-align:left;">
                    <th style="padding:0.5rem 0.65rem; border-bottom:1px solid #e4e4e7;">Name</th>
                    <th style="padding:0.5rem 0.65rem; border-bottom:1px solid #e4e4e7;">Designation</th>
                    <th style="padding:0.5rem 0.65rem; border-bottom:1px solid #e4e4e7;">Email</th>
                    <th style="padding:0.5rem 0.65rem; border-bottom:1px solid #e4e4e7;">Districts covered</th>
                    <th style="padding:0.5rem 0.65rem; border-bottom:1px solid #e4e4e7;">Status</th>
                    <th style="padding:0.5rem 0.65rem; border-bottom:1px solid #e4e4e7;">Actions</th>
                </tr>
            </thead>
            <tbody>
                @forelse ($users as $u)
                    <tr>
                        <td style="padding:0.45rem 0.65rem; border-bottom:1px solid #f4f4f5;">{{ $u->name }}</td>
                        <td style="padding:0.45rem 0.65rem; border-bottom:1px solid #f4f4f5;">{{ $u->designationRecord?->name ?? '—' }}</td>
                        <td style="padding:0.45rem 0.65rem; border-bottom:1px solid #f4f4f5;">{{ $u->email }}</td>
                        <td style="padding:0.45rem 0.65rem; border-bottom:1px solid #f4f4f5; color:#71717a; font-size:0.82rem;">
                            <em>Pending next PR</em>
                        </td>
                        <td style="padding:0.45rem 0.65rem; border-bottom:1px solid #f4f4f5; font-size:0.8rem;">
                            @if ($u->is_active)
                                <span style="color:#047857; font-weight:600;">Active</span>
                            @else
                                <span style="color:#b91c1c; font-weight:600;">Disabled</span>
                            @endif
                        </td>
                        <td style="padding:0.45rem 0.65rem; border-bottom:1px solid #f4f4f5; font-size:0.8rem; white-space:nowrap;">
                            <a href="{{ route('admin.state-staff.edit', $u) }}">Edit</a>
                            <span style="color:#d4d4d8;">|</span>
                            <form method="post" action="{{ route('admin.state-staff.toggle-active', $u) }}" style="display:inline;">
                                @csrf
                                <button type="submit" style="background:none;border:none;padding:0;color:#2563eb;cursor:pointer;font-size:inherit;text-decoration:underline;">
                                    {{ $u->is_active ? 'Disable' : 'Enable' }}
                                </button>
                            </form>
                            <span style="color:#d4d4d8;">|</span>
                            <form method="post" action="{{ route('admin.state-staff.destroy', $u) }}" style="display:inline;" onsubmit="return confirm('Delete this SPOC user? Any existing district assignments will need reassignment.');">
                                @csrf
                                @method('DELETE')
                                <button type="submit" style="background:none;border:none;padding:0;color:#b91c1c;cursor:pointer;font-size:inherit;text-decoration:underline;">Delete</button>
                            </form>
                        </td>
                    </tr>
                @empty
                    <tr><td colspan="6" style="padding:1rem;">No state staff yet. Add one to start the maker-checker flow.</td></tr>
                @endforelse
            </tbody>
        </table>
    </div>

    {{ $users->links() }}
@endsection
