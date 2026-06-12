@extends('layouts.admin')

@section('title', 'Pitch deck entry #'.$row->id)
@section('heading', 'Pitch deck entry')

@push('styles')
<style>
    .pdp-show { max-width:56rem; display:flex; flex-direction:column; gap:1rem; }
    .pdp-show__back { margin:0; }
    .pdp-show__back a { color:#4f46e5; font-weight:700; text-decoration:none; }
    .pdp-card { background:#fff; border:1px solid #e2e8f0; border-radius:14px; padding:1.25rem 1.35rem; }
    .pdp-card__title { margin:0 0 1rem; font-size:1rem; font-weight:800; color:#0f172a; }
    .pdp-dl { margin:0; display:grid; grid-template-columns:10rem 1fr; gap:0.55rem 1rem; font-size:0.88rem; }
    .pdp-dl dt { color:#64748b; font-weight:700; }
    .pdp-dl dd { margin:0; }
    .pdp-actions { margin-top:1.25rem; display:flex; flex-wrap:wrap; gap:0.55rem; }
    .pdp-btn { background:#4f46e5; color:#fff; padding:0.5rem 0.9rem; border-radius:8px; font-weight:700; text-decoration:none; font-size:0.86rem; border:none; cursor:pointer; }
    .pdp-btn--danger { background:#fff; color:#b91c1c; border:1px solid #fecaca; }
    .pdp-profile { padding:0.15rem 0; }
    .pdp-profile__badges { display:flex; flex-wrap:wrap; gap:0.35rem; margin-bottom:0.65rem; }
    .pdp-profile__title { margin:0 0 0.75rem; font-size:0.98rem; font-weight:800; color:#0f172a; }
    .pdp-profile__grid { display:grid; grid-template-columns:repeat(2,minmax(0,1fr)); gap:0.65rem 0.85rem; }
    .pdp-profile__label { font-size:0.72rem; font-weight:700; color:#64748b; text-transform:uppercase; letter-spacing:0.03em; }
    .pdp-profile__value { margin-top:0.15rem; font-size:0.86rem; color:#0f172a; word-break:break-word; }
    .pdp-pill { display:inline-flex; padding:0.12rem 0.45rem; border-radius:999px; font-size:0.68rem; font-weight:800; background:#eef2ff; color:#3730a3; }
    .pdp-pill--ok { background:#dcfce7; color:#166534; }
    .pdp-pill--muted { background:#f1f5f9; color:#475569; }
    @media (max-width:720px) { .pdp-profile__grid, .pdp-dl { grid-template-columns:1fr; } .pdp-dl dt { margin-top:0.35rem; } }
</style>
@endpush

@section('content')
@php
    $dashboardRoute = $currentRole === 'state_admin'
        ? 'admin.pitch-deck-preparations.dashboard'
        : 'spoc.pitch-deck-preparations.dashboard';
    $deckRoute = $currentRole === 'state_admin'
        ? 'admin.pitch-deck-preparations.deck'
        : 'spoc.pitch-deck-preparations.deck';
    $destroyRoute = $currentRole === 'state_admin'
        ? 'admin.pitch-deck-preparations.destroy'
        : 'spoc.pitch-deck-preparations.destroy';
@endphp
<div class="pdp-show">
    <p class="pdp-show__back"><a href="{{ route($dashboardRoute) }}">← Back to dashboard</a></p>

    <div class="pdp-card">
        <h3 class="pdp-card__title">Incubatee details</h3>
        @include('pitch-deck-preparations.partials.incubatee-profile', ['profile' => $incubateeProfile ?? []])
    </div>

    <div class="pdp-card">
        <h3 class="pdp-card__title">Pitch deck entry #{{ $row->id }}</h3>
        <dl class="pdp-dl">
            <dt>Prepared on</dt>
            <dd>{{ $row->prepared_on?->format('d M Y') ?? '—' }}</dd>
            <dt>Prepared for</dt>
            <dd>{{ $row->prepared_for ?: '—' }}</dd>
            <dt>Support mode</dt>
            <dd>{{ $row->formattedSupportMode() ?? '—' }}</dd>
            <dt>Entered by</dt>
            <dd>{{ $row->entered_by_name }}</dd>
            <dt>District (recorded)</dt>
            <dd>{{ $row->district?->name ?? '—' }}</dd>
            <dt>Remarks</dt>
            <dd style="white-space:pre-wrap;">{{ $row->remarks ?: '—' }}</dd>
            <dt>Pitch deck file</dt>
            <dd>{{ $row->deck_file_name }}</dd>
        </dl>

        <div class="pdp-actions">
            <button type="button"
                class="pdp-btn js-pdp-deck-preview"
                data-deck-preview-url="{{ route($deckRoute, ['pitchDeckPreparation' => $row, 'inline' => 1]) }}"
                data-deck-download-url="{{ route($deckRoute, $row) }}"
                data-deck-name="{{ $row->deck_file_name }}">View deck</button>
            <a href="{{ route($deckRoute, $row) }}" class="pdp-btn" style="background:#fff;color:#334155;border:1px solid #cbd5e1;">Download deck</a>
            @if (!empty($canEdit))
                <a href="{{ route('spoc.pitch-deck-preparations.edit', $row) }}" class="pdp-btn">Edit entry</a>
            @endif
            @if (!empty($canDelete))
                <form method="post" action="{{ route($destroyRoute, $row) }}" onsubmit="return confirm('Delete this pitch deck entry?');">
                    @csrf
                    @method('DELETE')
                    <button type="submit" class="pdp-btn pdp-btn--danger">Delete</button>
                </form>
            @endif
        </div>
    </div>
</div>

@include('pitch-deck-preparations.partials.deck-preview-modal')
@endsection
