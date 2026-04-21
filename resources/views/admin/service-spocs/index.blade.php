@extends('layouts.admin')

@section('title', 'Service SPOCs')
@section('heading', 'Service SPOCs — district assignment')

@section('content')
    @php
        $totalDistricts = $hubs->sum(fn ($h) => $h->districts->count());
        $assignedDistricts = $hubs->sum(fn ($h) => $h->districts->filter(fn ($d) => $d->serviceSpoc)->count());
        $unassignedDistricts = $totalDistricts - $assignedDistricts;
    @endphp

    <p style="font-size:0.9rem; color:#52525b; margin:0 0 0.75rem;">
        Map each district to exactly one <strong>State Staff (SPOC)</strong>. SPOCs approve / send back / reject service cases raised by district staff for services that require maker–checker verification.
        A SPOC can cover multiple districts, but a district can only have one SPOC at a time.
    </p>
    <p style="font-size:0.85rem; color:#52525b; margin:0 0 1rem;">
        Don't see the right names?
        <a href="{{ route('admin.state-staff.index') }}">Manage State Staff (SPOC) users</a>.
    </p>

    @if (session('status'))
        <p style="background:#dcfce7; color:#166534; padding:0.5rem 0.75rem; border-radius:6px; font-size:0.88rem; margin:0.5rem 0 1rem;">{{ session('status') }}</p>
    @endif
    @if ($errors->any())
        <ul style="color:#b91c1c; margin:0 0 1rem; padding-left:1.2rem; font-size:0.88rem;">
            @foreach ($errors->all() as $e)
                <li>{{ $e }}</li>
            @endforeach
        </ul>
    @endif

    <div style="display:flex; flex-wrap:wrap; gap:0.5rem; margin-bottom:1rem;">
        <span style="background:#eef2ff; color:#3730a3; border:1px solid #c7d2fe; padding:0.25rem 0.55rem; border-radius:999px; font-size:0.78rem; font-weight:600;">Districts: {{ $totalDistricts }}</span>
        <span style="background:#dcfce7; color:#166534; border:1px solid #bbf7d0; padding:0.25rem 0.55rem; border-radius:999px; font-size:0.78rem; font-weight:600;">Assigned: {{ $assignedDistricts }}</span>
        <span style="background:{{ $unassignedDistricts > 0 ? '#fef3c7' : '#f1f5f9' }}; color:{{ $unassignedDistricts > 0 ? '#92400e' : '#475569' }}; border:1px solid {{ $unassignedDistricts > 0 ? '#fde68a' : '#e2e8f0' }}; padding:0.25rem 0.55rem; border-radius:999px; font-size:0.78rem; font-weight:600;">Unassigned: {{ $unassignedDistricts }}</span>
        <span style="background:#fff; color:#475569; border:1px solid #e2e8f0; padding:0.25rem 0.55rem; border-radius:999px; font-size:0.78rem; font-weight:600;">Active SPOCs: {{ $spocs->count() }}</span>
    </div>

    @if ($spocs->isEmpty())
        <div style="background:#fff7ed; border:1px solid #fed7aa; color:#9a3412; border-radius:8px; padding:0.85rem 1rem; margin-bottom:1rem; font-size:0.88rem;">
            No active State Staff (SPOC) users yet.
            <a href="{{ route('admin.state-staff.create') }}">Add a SPOC user</a> first, then come back here to assign districts.
        </div>
    @endif

    <form method="post" action="{{ route('admin.service-spocs.update') }}">
        @csrf
        @method('PUT')

        @foreach ($hubs as $hub)
            <div style="background:#fff; border:1px solid #e4e4e7; border-radius:10px; padding:1rem 1.15rem; margin-bottom:1rem;">
                <div style="display:flex; flex-wrap:wrap; align-items:center; gap:0.5rem; margin-bottom:0.65rem;">
                    <strong style="font-size:1rem; color:#0f172a;">{{ $hub->name }} Hub</strong>
                    <span style="font-size:0.78rem; color:#71717a;">{{ $hub->districts->count() }} districts</span>
                </div>

                @if ($hub->districts->isEmpty())
                    <p style="margin:0; font-size:0.85rem; color:#71717a;">No districts under this hub.</p>
                @else
                    <div style="overflow-x:auto;">
                        <table style="width:100%; border-collapse:collapse; font-size:0.875rem;">
                            <thead>
                                <tr style="text-align:left; background:#fafafa;">
                                    <th style="padding:0.45rem 0.65rem; border-bottom:1px solid #e4e4e7; width:40%;">District</th>
                                    <th style="padding:0.45rem 0.65rem; border-bottom:1px solid #e4e4e7; width:45%;">SPOC</th>
                                    <th style="padding:0.45rem 0.65rem; border-bottom:1px solid #e4e4e7;">Assigned</th>
                                </tr>
                            </thead>
                            <tbody>
                                @foreach ($hub->districts as $d)
                                    @php
                                        $currentSpocId = $d->serviceSpoc?->state_staff_user_id;
                                        $currentSpocName = $d->serviceSpoc?->stateStaff?->name;
                                    @endphp
                                    <tr>
                                        <td style="padding:0.45rem 0.65rem; border-bottom:1px solid #f4f4f5;">
                                            {{ $d->name }}
                                        </td>
                                        <td style="padding:0.45rem 0.65rem; border-bottom:1px solid #f4f4f5;">
                                            <select name="assignments[{{ $d->id }}]" style="width:100%; max-width:22rem; padding:0.35rem 0.5rem; border:1px solid #d4d4d8; border-radius:6px; background:#fff;">
                                                <option value="">— Unassigned —</option>
                                                @foreach ($spocs as $s)
                                                    <option value="{{ $s->id }}" @selected((int) $currentSpocId === (int) $s->id)>
                                                        {{ $s->name }}@if (isset($counts[$s->id])) ({{ $counts[$s->id] }} distr.)@endif
                                                    </option>
                                                @endforeach
                                            </select>
                                        </td>
                                        <td style="padding:0.45rem 0.65rem; border-bottom:1px solid #f4f4f5; font-size:0.78rem; color:#64748b;">
                                            @if ($d->serviceSpoc)
                                                {{ $d->serviceSpoc->assigned_at?->diffForHumans() ?? '—' }}
                                            @else
                                                <em style="color:#b45309;">No SPOC</em>
                                            @endif
                                        </td>
                                    </tr>
                                @endforeach
                            </tbody>
                        </table>
                    </div>
                @endif
            </div>
        @endforeach

        <div style="position:sticky; bottom:0.5rem; background:rgba(255,255,255,0.92); backdrop-filter:blur(6px); border:1px solid #e4e4e7; border-radius:10px; padding:0.65rem 0.9rem; display:flex; gap:0.65rem; align-items:center; flex-wrap:wrap;">
            <button type="submit" style="background:#18181b; color:#fff; border:none; padding:0.55rem 1.1rem; border-radius:6px; font-weight:600; font-size:0.9rem; cursor:pointer;">
                Save assignments
            </button>
            <span style="font-size:0.8rem; color:#64748b;">
                Changes take effect immediately. In-flight cases that are already pending with the previous SPOC stay with them — new submissions route to the new SPOC.
            </span>
        </div>
    </form>
@endsection
