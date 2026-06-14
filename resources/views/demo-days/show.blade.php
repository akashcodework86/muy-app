@extends('layouts.admin')

@section('title', 'Demo day detail')
@section('heading', 'Demo day detail')

@section('content')
@php use App\Support\DemoDayOptions; @endphp
<div style="max-width:48rem; display:flex; flex-direction:column; gap:1rem;">
    <p><a href="{{ route($dashboardRoute) }}" style="color:#7c3aed;font-weight:700;text-decoration:none;">← Back to dashboard</a></p>

    <div style="background:#fff;border:1px solid #e2e8f0;border-radius:14px;padding:1.25rem;">
        <h3 style="margin:0 0 1rem;">{{ $row->event_name }}</h3>
        <div style="display:grid;grid-template-columns:repeat(2,minmax(0,1fr));gap:0.75rem;font-size:0.88rem;">
            <div><strong>Date</strong><br>{{ $row->event_date?->format('d M Y') }}</div>
            <div><strong>Type</strong><br>{{ DemoDayOptions::eventTypeLabel((string) $row->event_type, $row->event_type_other) }}</div>
            @if ($row->investor_name)<div><strong>Investor</strong><br>{{ $row->investor_name }}</div>@endif
            <div><strong>Mode</strong><br>{{ DemoDayOptions::modeLabel($row->mode) }}</div>
            <div><strong>Participants</strong><br>{{ $row->participantCounts()['total'] }} <span style="color:#64748b;">(selected incubatees)</span></div>
            <div style="grid-column:1/-1;">
                <strong>Participating incubatees ({{ count($participatingIncubatees ?? []) }})</strong>
                <ul style="margin:0.35rem 0 0 1.1rem; padding:0;">
                    @forelse ($participatingIncubatees ?? [] as $inc)
                        <li>{{ $inc['name'] ?? '—' }}@if(!empty($inc['application_no'])) · {{ $inc['application_no'] }}@endif</li>
                    @empty
                        <li>{{ $row->incubatee_name ?: '—' }}</li>
                    @endforelse
                </ul>
            </div>
            <div><strong>District</strong><br>{{ $row->district?->name ?? '—' }}</div>
            <div><strong>Entered by</strong><br>{{ $row->entered_by_name }}</div>
            @if ($row->venue)<div style="grid-column:1/-1;"><strong>Venue</strong><br>{{ $row->venue }}</div>@endif
            @if ($row->summary)<div style="grid-column:1/-1;"><strong>Summary</strong><br>{{ $row->summary }}</div>@endif
            @if ($row->remarks)<div style="grid-column:1/-1;"><strong>Remarks</strong><br>{{ $row->remarks }}</div>@endif
            @if ($row->hasEventPhotos())
                <div style="grid-column:1/-1;">
                    <strong>Event photos ({{ count($row->eventPhotoItems()) }})</strong>
                    <div style="margin-top:0.5rem;">
                        @include('staff.technical-trainings.partials.attendance-media-preview', [
                            'mediaItems' => $row->eventPhotoItems(),
                            'attachmentRoute' => $attachmentRoute,
                            'record' => $row,
                            'showEmptyMessage' => false,
                        ])
                    </div>
                </div>
            @endif
        </div>

        <div style="margin-top:1rem; display:flex; gap:0.5rem;">
            @if ($editRoute)<a href="{{ route($editRoute, $row) }}" style="background:#7c3aed;color:#fff;padding:0.5rem 0.85rem;border-radius:8px;font-weight:700;text-decoration:none;">Edit</a>@endif
            @if ($canDelete)
                <form method="post" action="{{ route($currentRole === 'state_admin' ? 'admin.demo-days.destroy' : 'spoc.demo-days.destroy', $row) }}" onsubmit="return confirm('Delete this demo day entry?');">
                    @csrf @method('DELETE')
                    <button type="submit" style="background:#fff;border:1px solid #fca5a5;color:#b91c1c;padding:0.5rem 0.85rem;border-radius:8px;font-weight:700;cursor:pointer;">Delete</button>
                </form>
            @endif
        </div>
    </div>
</div>
@endsection
