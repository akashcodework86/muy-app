@extends('layouts.admin')

@section('title', 'Schedule mentorship')
@section('heading', 'Schedule mentorship')

@push('styles')
@include('mentorship-requests.partials.styles')
@endpush

@section('content')
@php
    $cat = $categories[$category]['label'] ?? $category;
    $kind = $requests->count() === 1 ? 'Individual' : 'Batch ('.$requests->count().')';
    $dashRoute = $prefix.'mentorship-requests.dashboard';
@endphp
<div class="mr-shell">
    @if ($errors->any())<div class="ldm-list-alert ldm-list-alert--warning">{{ $errors->first() }}</div>@endif

    <a class="mr-back" href="{{ route($dashRoute) }}">← All requests</a>

    <div class="mr-hero">
        <span class="mr-hero__kicker">Schedule session</span>
        <h2 class="mr-hero__title">{{ $kind }} · {{ $cat }}</h2>
        <p class="mr-hero__sub">Online only. Date and time are required. Meeting link is optional. Attendance is this list.</p>
    </div>

    <div class="mr-grid">
        <div class="mr-card">
            <h3 class="mr-card__h">When</h3>
            <form method="post" action="{{ route($prefix.'mentorship-requests.schedule.store') }}">
                @csrf
                @foreach ($requests as $r)
                    <input type="hidden" name="ids[]" value="{{ $r->id }}">
                @endforeach
                <div class="ldm-list-filter-field" style="margin-bottom:0.85rem">
                    <label for="scheduled_at">Date &amp; time</label>
                    <input id="scheduled_at" type="datetime-local" name="scheduled_at" value="{{ old('scheduled_at') }}" required>
                </div>
                <div class="ldm-list-filter-field" style="margin-bottom:1rem">
                    <label for="meeting_link">Meeting link (optional)</label>
                    <input id="meeting_link" type="url" name="meeting_link" value="{{ old('meeting_link') }}" placeholder="https://…">
                </div>
                <div class="mr-actions" style="margin-top:0">
                    <button type="submit" class="mr-btn mr-btn--primary">Save schedule</button>
                    <a href="{{ route($dashRoute) }}" class="mr-btn mr-btn--ghost">Cancel</a>
                </div>
            </form>
        </div>
        <div class="mr-card">
            <h3 class="mr-card__h">Attendees</h3>
            <ul class="mr-people">
                @foreach ($requests as $r)
                    <li>
                        <span>{{ $r->cfaSubmission?->applicant_name ?: 'Incubatee' }}</span>
                        <span class="mr-people__meta">{{ $r->cfaSubmission?->application_no ?: '—' }}</span>
                    </li>
                @endforeach
            </ul>
        </div>
    </div>
</div>
@endsection
