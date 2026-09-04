@extends('layouts.admin')

@section('title', 'Mark mentorship Done')
@section('heading', 'Mark mentorship Done')

@push('styles')
@include('mentorship-requests.partials.styles')
@endpush

@section('content')
@php
    $cat = $categories[$session->category]['label'] ?? $session->category;
    $dashRoute = $prefix.'mentorship-requests.dashboard';
@endphp
<div class="mr-shell">
    @if ($errors->any())<div class="ldm-list-alert ldm-list-alert--warning">{{ $errors->first() }}</div>@endif

    <a class="mr-back" href="{{ route($dashRoute) }}">← All requests</a>

    <div class="mr-hero">
        <span class="mr-hero__kicker">Mark Done · 5.2</span>
        <h2 class="mr-hero__title">{{ ucfirst($session->kind) }} · {{ $cat }}</h2>
        <p class="mr-hero__sub">
            {{ $session->scheduled_at?->format('d M Y, h:i A') }}
            @if ($session->meeting_link)
                · <a href="{{ $session->meeting_link }}" target="_blank" rel="noopener" style="color:#fde68a;font-weight:700">Open meeting</a>
            @endif
        </p>
    </div>

    <div class="mr-grid">
        <div class="mr-card">
            <h3 class="mr-card__h">Screenshot</h3>
            <p class="mr-muted" style="margin:0 0 0.85rem">Upload a screenshot of the online meeting. Done is final. Each unique incubatee counts as 1 toward 5.2.</p>
            <form method="post" action="{{ route($prefix.'mentorship-requests.complete.store', $session) }}" enctype="multipart/form-data">
                @csrf
                <label class="mr-file" for="proof">
                    <input id="proof" type="file" name="proof" accept="image/jpeg,image/png,image/webp" required>
                    <span class="mr-hint">JPG, PNG or WebP · max 10 MB</span>
                </label>
                <div class="mr-actions">
                    <button type="submit" class="mr-btn mr-btn--success">Mark Done</button>
                    <a href="{{ route($dashRoute) }}" class="mr-btn mr-btn--ghost">Cancel</a>
                </div>
            </form>
        </div>
        <div class="mr-card">
            <h3 class="mr-card__h">Attendance</h3>
            <ul class="mr-people">
                @foreach ($session->requests as $r)
                    @if (! $r->isCancelled())
                        <li>
                            <span>{{ $r->cfaSubmission?->applicant_name ?: 'Incubatee' }}</span>
                            <span class="mr-people__meta">{{ $r->cfaSubmission?->application_no ?: '—' }}</span>
                        </li>
                    @endif
                @endforeach
            </ul>
        </div>
    </div>
</div>
@endsection
