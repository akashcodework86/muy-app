@extends('layouts.admin')

@section('title', 'Mark meeting done')
@section('heading', 'Mark meeting done')

@push('styles')
@include('mentorship-requests.partials.styles')
@endpush

@section('content')
@php
    $dashRoute = $prefix.'incubatee-meetings.dashboard';
@endphp
<div class="mr-shell">
    @if ($errors->any())<div class="ldm-list-alert ldm-list-alert--warning">{{ $errors->first() }}</div>@endif

    <a class="mr-back" href="{{ route($dashRoute) }}">← All meetings</a>

    <div class="mr-hero">
        <span class="mr-hero__kicker">Mark done</span>
        <h2 class="mr-hero__title">{{ $row->title }}</h2>
        <p class="mr-hero__sub">
            {{ \App\Support\Ist::datetime($row->scheduled_at) }}
            @if ($row->isOnline() && $row->meeting_link)
                · <a href="{{ $row->meeting_link }}" target="_blank" rel="noopener" style="color:#fde68a;font-weight:700">Open meeting</a>
            @elseif ($row->venue)
                · {{ $row->venue }}
            @endif
        </p>
    </div>

    <div class="mr-card">
        <h3 class="mr-card__h">Proof</h3>
        <p class="mr-muted" style="margin:0 0 0.85rem">Upload a PDF or photo of the meeting. Done is final.</p>
        <form method="post" action="{{ route($prefix.'incubatee-meetings.complete.store', $row) }}" enctype="multipart/form-data">
            @csrf
            <label class="mr-file" for="proof">
                <input id="proof" type="file" name="proof" accept=".pdf,.jpg,.jpeg,.png,.webp,image/*,application/pdf" required>
                <span class="mr-hint">PDF, JPG, PNG or WebP · max 10 MB</span>
            </label>
            <div class="mr-actions">
                <button type="submit" class="mr-btn mr-btn--success">Mark Done</button>
                <a href="{{ route($dashRoute) }}" class="mr-btn mr-btn--ghost">Cancel</a>
            </div>
        </form>
    </div>
</div>
@endsection
