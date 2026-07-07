@extends('layouts.admin')

@section('title', 'Acceleration session detail')
@section('heading', 'Acceleration session detail')

@include('acceleration-services.partials.styles')

@push('styles')
<style>
    .accel-show { display:flex; flex-direction:column; gap:1.15rem; max-width:56rem; }
    .accel-meta { display:grid; grid-template-columns:repeat(auto-fit,minmax(180px,1fr)); gap:0.75rem; font-size:0.86rem; }
    .accel-meta dt { font-weight:700; color:#64748b; font-size:0.72rem; text-transform:uppercase; letter-spacing:0.04em; }
    .accel-meta dd { margin:0.15rem 0 0; color:#0f172a; font-weight:500; }
    .accel-item-card { border:1px solid #e2e8f0; border-radius:12px; padding:0.8rem 0.9rem; margin-bottom:0.55rem; background:#f8fafc; }
    .accel-media-list { display:flex; flex-wrap:wrap; gap:0.45rem; margin-top:0.45rem; }
    .accel-media-list a { font-size:0.8rem; color:#0f766e; font-weight:700; text-decoration:none; background:#fff; border:1px solid #e2e8f0; border-radius:8px; padding:0.32rem 0.55rem; }
    .accel-media-list a:hover { background:#f0fdfa; border-color:#99f6e4; }
    .accel-danger-btn { background:#fff; border:1px solid #fecaca; color:#b91c1c; padding:0.5rem 0.85rem; border-radius:10px; font-weight:700; cursor:pointer; font-family:inherit; }
    .accel-danger-btn:hover { background:#fef2f2; }
</style>
@endpush

@section('content')
<div class="accel-show">
    <p><a href="{{ route($dashboardRoute) }}" class="accel-link">← Back to dashboard</a></p>

    <div class="accel-card">
        <div style="display:flex; justify-content:space-between; gap:0.65rem; flex-wrap:wrap; margin-bottom:0.85rem;">
            <h3 style="margin:0; font-size:1.05rem;">{{ $session->applicant_name }}</h3>
            @if ($session->counts_for_7_2)
                <span class="accel-badge accel-badge--init">7.2 Initiation</span>
            @else
                <span class="accel-badge accel-badge--follow">Follow-up session</span>
            @endif
        </div>

        <dl class="accel-meta">
            <div><dt>Service date</dt><dd>{{ $session->service_date?->format('d M Y') }}</dd></div>
            <div><dt>Application no</dt><dd>{{ $session->application_no ?: '—' }}</dd></div>
            <div><dt>Phone</dt><dd>{{ $session->phone ?: '—' }}</dd></div>
            <div><dt>District</dt><dd>{{ $session->district_name ?: '—' }}</dd></div>
            <div><dt>Onboarding</dt><dd>{{ $session->onboard_label ?: '—' }}</dd></div>
            <div><dt>Submitted by</dt><dd>{{ $session->submitted_by_name }}</dd></div>
            <div><dt>Logged at</dt><dd>{{ $session->created_at?->format('d M Y H:i') }}</dd></div>
        </dl>
    </div>

    <div class="accel-card">
        <h4 style="margin:0 0 0.75rem; font-size:0.95rem;">Services recorded</h4>
        @forelse ($session->items as $item)
            <div class="accel-item-card">
                <strong>{{ $item->item_label }}</strong>
                <div style="font-size:0.76rem; color:#64748b; margin-top:0.15rem;">{{ \App\Support\AccelerationServicesOptions::sectionLabel($item->section) }}</div>
                @if ($item->remarks)
                    <p style="margin:0.45rem 0 0; font-size:0.84rem; color:#334155;">{{ $item->remarks }}</p>
                @endif
                @if ($item->media->isNotEmpty())
                    <div class="accel-media-list">
                        @foreach ($item->media as $media)
                            <a href="{{ route($mediaRoute, $media) }}" target="_blank" rel="noopener">{{ $media->original_name }}</a>
                        @endforeach
                    </div>
                @endif
            </div>
        @empty
            <p style="color:#64748b; margin:0;">No items.</p>
        @endforelse
    </div>

    <div style="display:flex; flex-wrap:wrap; gap:0.65rem;">
        @if (!empty($addServicesRoute))
            <a href="{{ route($addServicesRoute, ['from_session' => $session->id]) }}#accel-form" class="accel-link">+ Add more services</a>
        @endif
        @if (!empty($canDelete) && !empty($destroyRoute))
            <form method="post" action="{{ route($destroyRoute, $session) }}" onsubmit="return confirm('Delete this session?');">
                @csrf
                @method('DELETE')
                <button type="submit" class="accel-danger-btn">Delete session</button>
            </form>
        @endif
    </div>
</div>
@endsection
