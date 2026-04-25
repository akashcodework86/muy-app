@extends('layouts.admin')

@section('title', 'Staff')
@section('heading', 'District staff')

@section('content')
    <p style="font-size:0.9rem; color:#52525b; margin-top:0;">Flow: <strong>Add staff</strong> → <strong>District targets</strong> per MIS deliverable → <strong>Monthly targets (M1–M12)</strong> per staff per deliverable → for CFA, staff shares <strong>apply link</strong>.</p>

    <div style="display:grid;grid-template-columns:repeat(auto-fit,minmax(160px,1fr));gap:0.6rem;margin:0.75rem 0;">
        <div style="background:linear-gradient(135deg,#eef2ff,#e0e7ff);border:1px solid #c7d2fe;border-radius:10px;padding:0.65rem 0.8rem;">
            <div style="font-size:0.74rem;color:#4338ca;font-weight:700;letter-spacing:0.04em;text-transform:uppercase;">Total staff</div>
            <div style="font-size:1.3rem;font-weight:800;color:#1f2937;">{{ (int) ($stats['total'] ?? 0) }}</div>
        </div>
        <div style="background:linear-gradient(135deg,#ecfdf5,#dcfce7);border:1px solid #86efac;border-radius:10px;padding:0.65rem 0.8rem;">
            <div style="font-size:0.74rem;color:#166534;font-weight:700;letter-spacing:0.04em;text-transform:uppercase;">Active</div>
            <div style="font-size:1.3rem;font-weight:800;color:#1f2937;">{{ (int) ($stats['active'] ?? 0) }}</div>
        </div>
        <div style="background:linear-gradient(135deg,#fef2f2,#fee2e2);border:1px solid #fca5a5;border-radius:10px;padding:0.65rem 0.8rem;">
            <div style="font-size:0.74rem;color:#991b1b;font-weight:700;letter-spacing:0.04em;text-transform:uppercase;">Disabled</div>
            <div style="font-size:1.3rem;font-weight:800;color:#1f2937;">{{ (int) ($stats['disabled'] ?? 0) }}</div>
        </div>
    </div>

    <div style="display:flex;flex-wrap:wrap;gap:0.55rem;align-items:flex-start;margin:0 0 1rem;">
        <a href="{{ route('admin.staff.create') }}" style="display:inline-block; background:#18181b; color:#fff; padding:0.45rem 0.85rem; border-radius:8px; text-decoration:none; font-size:0.9rem; font-weight:600;">Add staff</a>
        <form method="get" action="{{ route('admin.staff.index') }}" style="display:flex;gap:0.5rem;align-items:center;flex-wrap:wrap;flex:1;min-width:280px;background:#fff;border:1px solid #e4e4e7;border-radius:10px;padding:0.55rem;">
            <input type="search" name="q" value="{{ $filters['q'] ?? '' }}" placeholder="Search name / email"
                style="min-width:13rem;flex:1;padding:0.45rem 0.6rem;border:1px solid #d4d4d8;border-radius:8px;">
            <select name="district_id" style="padding:0.45rem 0.55rem;border:1px solid #d4d4d8;border-radius:8px;">
                <option value="0">All districts</option>
                @foreach ($districts as $district)
                    <option value="{{ $district->id }}" @selected((int) ($filters['district_id'] ?? 0) === (int) $district->id)>{{ $district->name }}</option>
                @endforeach
            </select>
            <select name="designation_id" style="padding:0.45rem 0.55rem;border:1px solid #d4d4d8;border-radius:8px;">
                <option value="0">All designations</option>
                @foreach ($designations as $designation)
                    <option value="{{ $designation->id }}" @selected((int) ($filters['designation_id'] ?? 0) === (int) $designation->id)>{{ $designation->name }}</option>
                @endforeach
            </select>
            <select name="status" style="padding:0.45rem 0.55rem;border:1px solid #d4d4d8;border-radius:8px;">
                <option value="">All status</option>
                <option value="active" @selected(($filters['status'] ?? '') === 'active')>Active</option>
                <option value="disabled" @selected(($filters['status'] ?? '') === 'disabled')>Disabled</option>
            </select>
            <button type="submit" style="background:#18181b;color:#fff;border:none;padding:0.45rem 0.8rem;border-radius:8px;font-weight:600;">Apply</button>
            <a href="{{ route('admin.staff.index') }}" style="font-size:0.82rem;color:#2563eb;text-decoration:none;">Reset</a>
        </form>
    </div>

    <div style="overflow-x:auto;background:#fff;border:1px solid #e4e4e7;border-radius:10px;">
        <table style="width:100%; border-collapse:collapse; background:#fff; font-size:0.875rem; table-layout:fixed;">
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
                    <tr><td colspan="9" style="padding:1rem;color:#64748b;">No staff found for selected filters.</td></tr>
                @endforelse
            </tbody>
        </table>
    </div>
@endsection
