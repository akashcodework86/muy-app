@extends('layouts.admin')

@section('title', 'Additional State Admins')
@section('heading', 'Additional State Admins')

@section('content')
    <p style="font-size:0.9rem;color:#52525b;margin-top:0;max-width:58rem;">
        These accounts receive the full State Admin operational dashboard and permissions. Only the primary State Admin can create, edit, enable, or disable them. Additional admins cannot manage State Admin accounts or security-level designations.
    </p>

    @if (session('status'))
        <p style="background:#dcfce7;color:#166534;padding:0.6rem 0.8rem;border-radius:8px;margin:0.5rem 0 1rem;">{{ session('status') }}</p>
    @endif

    <p style="margin:0.8rem 0 1rem;">
        <a href="{{ route('admin.additional-state-admins.create') }}" style="display:inline-block;background:#18181b;color:#fff;padding:0.5rem 0.9rem;border-radius:7px;text-decoration:none;font-weight:600;">Add Executive Director</a>
    </p>

    <div style="overflow-x:auto;">
        <table style="width:100%;border-collapse:collapse;background:#fff;border:1px solid #e4e4e7;border-radius:8px;font-size:0.875rem;">
            <thead><tr style="background:#fafafa;text-align:left;">
                <th style="padding:0.6rem;border-bottom:1px solid #e4e4e7;">Name</th>
                <th style="padding:0.6rem;border-bottom:1px solid #e4e4e7;">Designation</th>
                <th style="padding:0.6rem;border-bottom:1px solid #e4e4e7;">Email</th>
                <th style="padding:0.6rem;border-bottom:1px solid #e4e4e7;">Status</th>
                <th style="padding:0.6rem;border-bottom:1px solid #e4e4e7;">Actions</th>
            </tr></thead>
            <tbody>
            @forelse ($users as $adminUser)
                <tr>
                    <td style="padding:0.6rem;border-bottom:1px solid #f4f4f5;font-weight:600;">{{ $adminUser->name }}</td>
                    <td style="padding:0.6rem;border-bottom:1px solid #f4f4f5;">{{ $adminUser->designationRecord?->name ?? 'Executive Director' }}</td>
                    <td style="padding:0.6rem;border-bottom:1px solid #f4f4f5;">{{ $adminUser->email }}</td>
                    <td style="padding:0.6rem;border-bottom:1px solid #f4f4f5;">
                        <span style="font-weight:700;color:{{ $adminUser->is_active ? '#047857' : '#b91c1c' }};">{{ $adminUser->is_active ? 'Active' : 'Disabled' }}</span>
                    </td>
                    <td style="padding:0.6rem;border-bottom:1px solid #f4f4f5;white-space:nowrap;">
                        <a href="{{ route('admin.additional-state-admins.edit', $adminUser) }}">Edit</a>
                        <span style="color:#d4d4d8;">|</span>
                        <form method="post" action="{{ route('admin.additional-state-admins.toggle-active', $adminUser) }}" style="display:inline;" onsubmit="return confirm('{{ $adminUser->is_active ? 'Disable this account?' : 'Enable this account?' }}');">
                            @csrf
                            <button type="submit" style="background:none;border:0;padding:0;color:#2563eb;cursor:pointer;text-decoration:underline;font:inherit;">{{ $adminUser->is_active ? 'Disable' : 'Enable' }}</button>
                        </form>
                    </td>
                </tr>
            @empty
                <tr><td colspan="5" style="padding:1rem;">No additional State Admin account has been created.</td></tr>
            @endforelse
            </tbody>
        </table>
    </div>

    {{ $users->links() }}
@endsection
