@extends('layouts.admin')

@section('title', 'Acceleration session detail')
@section('heading', 'Acceleration session detail')

@push('styles')
<style>
    .accel-show { display:flex; flex-direction:column; gap:1rem; max-width:56rem; }
    .accel-card { background:#fff; border:1px solid #e2e8f0; border-radius:14px; padding:1.1rem 1.25rem; }
    .accel-meta { display:grid; grid-template-columns:repeat(auto-fit,minmax(180px,1fr)); gap:0.65rem; font-size:0.86rem; }
    .accel-meta dt { font-weight:700; color:#64748b; font-size:0.72rem; text-transform:uppercase; }
    .accel-meta dd { margin:0.15rem 0 0; color:#0f172a; }
    .accel-item-card { border:1px solid #e2e8f0; border-radius:10px; padding:0.75rem; margin-bottom:0.55rem; background:#f8fafc; }
    .accel-media-list { display:flex; flex-wrap:wrap; gap:0.45rem; margin-top:0.45rem; }
    .accel-media-list a { font-size:0.8rem; color:#0f766e; font-weight:700; text-decoration:none; background:#fff; border:1px solid #e2e8f0; border-radius:6px; padding:0.3rem 0.5rem; }
    .accel-badge { display:inline-block; font-size:0.68rem; font-weight:700; padding:0.12rem 0.4rem; border-radius:999px; }
    .accel-badge--init { background:#dcfce7; color:#166534; }
    .accel-badge--follow { background:#f1f5f9; color:#475569; }
    .accel-link { color:#0f766e; font-weight:700; text-decoration:none; }
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
                <button type="submit" style="background:#fff;border:1px solid #fecaca;color:#b91c1c;padding:0.45rem 0.75rem;border-radius:8px;font-weight:700;cursor:pointer;">Delete session</button>
            </form>
        @endif
    </div>
</div>
@endsection
