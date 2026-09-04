@extends('layouts.admin')

@section('title', 'Mentorship request')
@section('heading', 'Mentorship request')

@push('styles')
@include('mentorship-requests.partials.styles')
@endpush

@section('content')
@php
    $dashRoute = $prefix.'mentorship-requests.dashboard';
    $scheduleRoute = $prefix.'mentorship-requests.schedule';
    $completeRoute = $prefix.'mentorship-requests.complete';
    $proofRoute = $prefix.'mentorship-requests.proof';
    $cat = $categories[$row->category]['label'] ?? $row->category;
    $session = $row->session;
    $status = (string) $row->status;
    $name = $row->cfaSubmission?->applicant_name ?: 'Incubatee';
    $phone = $row->cfaSubmission?->phone ?: null;
    $hub = $row->cfaSubmission?->district?->hub?->name;
@endphp
<div class="mr-shell">
    @if (session('status'))<div class="ldm-list-alert ldm-list-alert--success">{{ session('status') }}</div>@endif
    @if ($errors->any())<div class="ldm-list-alert ldm-list-alert--warning">{{ $errors->first() }}</div>@endif

    <a class="mr-back" href="{{ route($dashRoute) }}">← All requests</a>

    <div class="mr-hero">
        <span class="mr-hero__kicker">Online portal · 5.2</span>
        <h2 class="mr-hero__title">{{ $name }}</h2>
        <p class="mr-hero__sub">{{ $cat }} mentorship · {{ $row->cfaSubmission?->district?->name ?: 'District' }}@if($hub) · {{ $hub }}@endif</p>
        <div class="mr-hero__chips">
            <span class="mr-badge mr-badge--on-dark">{{ str_replace('_', ' ', $status) }}</span>
            <span class="mr-chip">CFA {{ $row->cfaSubmission?->application_no ?: '—' }}</span>
            @if ($phone)<span class="mr-chip">{{ $phone }}</span>@endif
            <span class="mr-chip">Requested {{ $row->created_at?->format('d M Y, h:i A') }}</span>
        </div>
    </div>

    <div class="mr-grid">
        <div class="mr-card">
            <h3 class="mr-card__h">Request</h3>
            <div class="mr-facts">
                <div><span class="mr-fact__l">Incubatee</span><span class="mr-fact__v">{{ $name }}</span></div>
                <div><span class="mr-fact__l">Category</span><span class="mr-fact__v">{{ $cat }}</span></div>
                <div><span class="mr-fact__l">District</span><span class="mr-fact__v">{{ $row->cfaSubmission?->district?->name ?: '—' }}</span></div>
                <div><span class="mr-fact__l">CFA no.</span><span class="mr-fact__v">{{ $row->cfaSubmission?->application_no ?: '—' }}</span></div>
            </div>
            <div class="mr-message">{{ $row->comment ?: 'No message with this request.' }}</div>
        </div>

        <div class="mr-card">
            <h3 class="mr-card__h">Session</h3>
            <div class="mr-steps" aria-label="Status">
                <div class="mr-step @if($row->isPending() || $row->isScheduled() || $row->isDone()) is-on @endif @if($row->isScheduled() || $row->isDone()) is-done @endif">Pending</div>
                <div class="mr-step @if($row->isScheduled()) is-on @endif @if($row->isDone()) is-done @endif">Scheduled</div>
                <div class="mr-step @if($row->isDone()) is-done @endif @if($row->isCancelled()) is-stop @endif">{{ $row->isCancelled() ? 'Cancelled' : 'Done' }}</div>
            </div>

            @if ($session)
                <div class="mr-facts" style="margin-bottom:0.85rem">
                    <div><span class="mr-fact__l">Type</span><span class="mr-fact__v">{{ ucfirst($session->kind) }}</span></div>
                    <div><span class="mr-fact__l">When</span><span class="mr-fact__v">{{ $session->scheduled_at?->format('d M Y, h:i A') ?: '—' }}</span></div>
                    @if ($session->createdBy)
                        <div><span class="mr-fact__l">Scheduled by</span><span class="mr-fact__v">{{ $session->createdBy->name }}</span></div>
                    @endif
                </div>
                @if ($session->meeting_link)
                    <a class="mr-btn mr-btn--ghost" href="{{ $session->meeting_link }}" target="_blank" rel="noopener">Open meeting link</a>
                @endif
                <p class="mr-card__h" style="margin:1rem 0 0.5rem">Attendance</p>
                <ul class="mr-people">
                    @foreach ($session->requests as $att)
                        <li>
                            <span>{{ $att->cfaSubmission?->applicant_name ?: 'Incubatee' }}</span>
                            <span class="mr-people__meta">
                                {{ $att->cfaSubmission?->application_no ?: '—' }}
                                @if($att->isCancelled()) · cancelled @endif
                            </span>
                        </li>
                    @endforeach
                </ul>
                @if ($session->isDone() && $session->proof_path)
                    <div class="mr-actions">
                        <a class="mr-btn mr-btn--ghost" href="{{ route($proofRoute, $session) }}">Download screenshot</a>
                    </div>
                @endif
            @else
                <p class="mr-muted" style="margin:0">No session yet. An Incubation Manager schedules an online meeting from this page.</p>
            @endif
        </div>
    </div>

    @if (!empty($canHandle) && $row->isPending())
        <div class="mr-card">
            <h3 class="mr-card__h">Schedule this request</h3>
            <p class="mr-muted" style="margin:0 0 0.75rem">Online only. You can add other pending {{ $cat }} requests for a batch session.</p>
            <form method="get" action="{{ route($scheduleRoute) }}">
                <input type="hidden" name="ids[]" value="{{ $row->id }}">
                @if ($sameCategoryPending->isNotEmpty())
                    <p class="mr-fact__l" style="margin-bottom:0.45rem">Add others in {{ $cat }}</p>
                    <div class="mr-pick">
                        @foreach ($sameCategoryPending as $other)
                            <label>
                                <input type="checkbox" name="ids[]" value="{{ $other->id }}">
                                <span>
                                    <span class="mr-pick__name">{{ $other->cfaSubmission?->applicant_name ?: 'Incubatee' }}</span>
                                    <span class="mr-pick__meta"> · {{ $other->cfaSubmission?->application_no ?: '—' }}</span>
                                </span>
                            </label>
                        @endforeach
                    </div>
                @endif
                <button type="submit" class="mr-btn mr-btn--primary">Schedule session</button>
            </form>
        </div>
    @endif

    @if (!empty($canHandle) && $session && ! $session->isDone() && $row->isScheduled())
        <div class="mr-card">
            <h3 class="mr-card__h">After the meeting</h3>
            <p class="mr-muted" style="margin:0 0 0.75rem">Upload a screenshot of the online meeting. Done is final and counts unique incubatees toward 5.2.</p>
            <a href="{{ route($completeRoute, $session) }}" class="mr-btn mr-btn--success">Mark Done</a>
        </div>
    @endif
</div>
@endsection
