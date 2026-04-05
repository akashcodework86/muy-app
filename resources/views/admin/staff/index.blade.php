@extends('layouts.admin')

@section('title', 'Staff')
@section('heading', 'District staff')

@section('content')
    <p style="font-size:0.9rem; color:#52525b; margin-top:0;">Flow: <strong>Add staff</strong> → <strong>District targets</strong> per MIS deliverable → <strong>Monthly targets (M1–M12)</strong> per staff per deliverable → for CFA, staff shares <strong>apply link</strong>.</p>
    <p style="margin:0.75rem 0 1rem;">
        <a href="{{ route('admin.staff.create') }}" style="display:inline-block; background:#18181b; color:#fff; padding:0.45rem 0.85rem; border-radius:6px; text-decoration:none; font-size:0.9rem;">Add staff</a>
    </p>

    <div style="overflow-x:auto;">
        <table style="width:100%; border-collapse:collapse; background:#fff; border:1px solid #e4e4e7; border-radius:8px; font-size:0.875rem;">
            <thead>
                <tr style="background:#fafafa; text-align:left;">
                    <th style="padding:0.5rem 0.65rem; border-bottom:1px solid #e4e4e7;">Name</th>
                    <th style="padding:0.5rem 0.65rem; border-bottom:1px solid #e4e4e7;">Designation</th>
                    <th style="padding:0.5rem 0.65rem; border-bottom:1px solid #e4e4e7;">Email</th>
                    <th style="padding:0.5rem 0.65rem; border-bottom:1px solid #e4e4e7;">District</th>
                    <th style="padding:0.5rem 0.65rem; border-bottom:1px solid #e4e4e7;">Apply link</th>
                    <th style="padding:0.5rem 0.65rem; border-bottom:1px solid #e4e4e7;">Monthly targets</th>
                    <th style="padding:0.5rem 0.65rem; border-bottom:1px solid #e4e4e7;">Status</th>
                    <th style="padding:0.5rem 0.65rem; border-bottom:1px solid #e4e4e7;">Actions</th>
                </tr>
            </thead>
            <tbody>
                @forelse ($staff as $s)
                    <tr>
                        <td style="padding:0.45rem 0.65rem; border-bottom:1px solid #f4f4f5;">{{ $s->name }}</td>
                        <td style="padding:0.45rem 0.65rem; border-bottom:1px solid #f4f4f5;">{{ $s->designationRecord?->name ?? '—' }}</td>
                        <td style="padding:0.45rem 0.65rem; border-bottom:1px solid #f4f4f5;">{{ $s->email }}</td>
                        <td style="padding:0.45rem 0.65rem; border-bottom:1px solid #f4f4f5;">{{ $s->district?->name ?? '—' }}</td>
                        <td style="padding:0.45rem 0.65rem; border-bottom:1px solid #f4f4f5; max-width:14rem;">
                            @if ($s->referral_token)
                                <input type="text" readonly value="{{ $s->referralApplyUrl() }}" onclick="this.select()" style="width:100%; font-size:0.75rem; padding:0.25rem;">
                            @else
                                —
                            @endif
                        </td>
                        <td style="padding:0.45rem 0.65rem; border-bottom:1px solid #f4f4f5;">
                            <a href="{{ route('admin.staff.monthly-targets.index', $s) }}">M1–M12 (all MIS)</a>
                        </td>
                        <td style="padding:0.45rem 0.65rem; border-bottom:1px solid #f4f4f5; font-size:0.8rem;">
                            @if ($s->is_active)
                                <span style="color:#047857; font-weight:600;">Active</span>
                            @else
                                <span style="color:#b91c1c; font-weight:600;">Disabled</span>
                            @endif
                        </td>
                        <td style="padding:0.45rem 0.65rem; border-bottom:1px solid #f4f4f5; font-size:0.8rem; white-space:nowrap;">
                            <a href="{{ route('admin.staff.edit', $s) }}">Edit</a>
                            <span style="color:#d4d4d8;">|</span>
                            <form method="post" action="{{ route('admin.staff.toggle-active', $s) }}" style="display:inline;">
                                @csrf
                                <button type="submit" style="background:none;border:none;padding:0;color:#2563eb;cursor:pointer;font-size:inherit;text-decoration:underline;">
                                    {{ $s->is_active ? 'Disable' : 'Enable' }}
                                </button>
                            </form>
                            <span style="color:#d4d4d8;">|</span>
                            <form method="post" action="{{ route('admin.staff.destroy', $s) }}" style="display:inline;" onsubmit="return confirm('Delete this staff user? This cannot be undone.');">
                                @csrf
                                @method('DELETE')
                                <button type="submit" style="background:none;border:none;padding:0;color:#b91c1c;cursor:pointer;font-size:inherit;text-decoration:underline;">Delete</button>
                            </form>
                        </td>
                    </tr>
                @empty
                    <tr><td colspan="8" style="padding:1rem;">No staff yet. Add one to start.</td></tr>
                @endforelse
            </tbody>
        </table>
    </div>

    {{ $staff->links() }}
@endsection
