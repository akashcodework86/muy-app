@extends('layouts.admin')

@section('title', 'Staff')
@section('heading', 'District staff')

@section('content')
    <p style="font-size:0.9rem; color:#52525b; margin-top:0;">Flow: <strong>Add staff</strong> → <strong>District targets</strong> per MIS deliverable → <strong>Monthly targets (M1–M12)</strong> per staff per deliverable → for CFA, staff shares <strong>apply link</strong>.</p>
    <div style="display:flex;flex-wrap:wrap;gap:0.55rem;align-items:center;margin:0.75rem 0 1rem;">
        <a href="{{ route('admin.staff.create') }}" style="display:inline-block; background:#18181b; color:#fff; padding:0.45rem 0.85rem; border-radius:6px; text-decoration:none; font-size:0.9rem;">Add staff</a>
        <form method="get" action="{{ route('admin.staff.index') }}" style="display:flex;gap:0.5rem;align-items:center;flex-wrap:wrap;margin-left:auto;">
            <input
                type="search"
                name="q"
                value="{{ $filters['q'] ?? '' }}"
                placeholder="Search name, email, district, designation"
                style="min-width:20rem;max-width:32rem;width:100%;padding:0.45rem 0.6rem;border:1px solid #d4d4d8;border-radius:8px;"
            >
            <button type="submit" style="background:#18181b;color:#fff;border:none;padding:0.45rem 0.8rem;border-radius:8px;font-weight:600;">Search</button>
            @if (! empty($filters['q'] ?? ''))
                <a href="{{ route('admin.staff.index') }}" style="font-size:0.85rem;">Clear</a>
            @endif
        </form>
    </div>

    <div style="overflow-x:auto;">
        <table style="width:100%; border-collapse:collapse; background:#fff; border:1px solid #e4e4e7; border-radius:8px; font-size:0.875rem; table-layout:fixed;">
            <thead>
                <tr style="background:#fafafa; text-align:left;">
                    <th style="width:3.5rem;padding:0.5rem 0.65rem; border-bottom:1px solid #e4e4e7;">#</th>
                    <th style="width:15%;padding:0.5rem 0.65rem; border-bottom:1px solid #e4e4e7;">Name</th>
                    <th style="width:16%;padding:0.5rem 0.65rem; border-bottom:1px solid #e4e4e7;">Designation</th>
                    <th style="width:17%;padding:0.5rem 0.65rem; border-bottom:1px solid #e4e4e7;">Email</th>
                    <th style="width:10%;padding:0.5rem 0.65rem; border-bottom:1px solid #e4e4e7;">District</th>
                    <th style="width:17%;padding:0.5rem 0.65rem; border-bottom:1px solid #e4e4e7;">Apply link</th>
                    <th style="width:10%;padding:0.5rem 0.65rem; border-bottom:1px solid #e4e4e7;">Monthly targets</th>
                    <th style="width:6.5rem;padding:0.5rem 0.65rem; border-bottom:1px solid #e4e4e7;">Status</th>
                    <th style="width:9.5rem;padding:0.5rem 0.65rem; border-bottom:1px solid #e4e4e7;">Actions</th>
                </tr>
            </thead>
            <tbody>
                @forelse ($staff as $s)
                    <tr>
                        <td style="padding:0.45rem 0.65rem; border-bottom:1px solid #f4f4f5;color:#71717a;">{{ $loop->iteration }}</td>
                        <td style="padding:0.45rem 0.65rem; border-bottom:1px solid #f4f4f5;font-weight:600;word-break:break-word;">{{ $s->name }}</td>
                        <td style="padding:0.45rem 0.65rem; border-bottom:1px solid #f4f4f5;word-break:break-word;">{{ $s->designationRecord?->name ?? '—' }}</td>
                        <td style="padding:0.45rem 0.65rem; border-bottom:1px solid #f4f4f5;word-break:break-word;">{{ $s->email }}</td>
                        <td style="padding:0.45rem 0.65rem; border-bottom:1px solid #f4f4f5;">{{ $s->district?->name ?? '—' }}</td>
                        <td style="padding:0.45rem 0.65rem; border-bottom:1px solid #f4f4f5; max-width:14rem;">
                            @if ($s->referral_token)
                                <input type="text" readonly value="{{ $s->referralApplyUrl() }}" onclick="this.select()" style="width:100%; font-size:0.75rem; padding:0.35rem;border:1px solid #d4d4d8;border-radius:6px;">
                            @else
                                —
                            @endif
                        </td>
                        <td style="padding:0.45rem 0.65rem; border-bottom:1px solid #f4f4f5;">
                            <a href="{{ route('admin.staff.monthly-targets.index', $s) }}">M1–M12 (all MIS)</a>
                        </td>
                        <td style="padding:0.45rem 0.65rem; border-bottom:1px solid #f4f4f5; font-size:0.8rem;white-space:nowrap;">
                            @if ($s->is_active)
                                <span style="color:#047857; font-weight:600;background:#ecfdf5;border:1px solid #a7f3d0;padding:0.16rem 0.45rem;border-radius:999px;">Active</span>
                            @else
                                <span style="color:#b91c1c; font-weight:600;background:#fef2f2;border:1px solid #fecaca;padding:0.16rem 0.45rem;border-radius:999px;">Disabled</span>
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
                    <tr><td colspan="9" style="padding:1rem;">No staff found.</td></tr>
                @endforelse
            </tbody>
        </table>
    </div>
@endsection
