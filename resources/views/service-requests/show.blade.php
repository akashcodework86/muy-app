@extends('layouts.admin')

@section('title', 'Service request')
@section('heading', 'Service request')

@push('styles')
@include('mentorship-requests.partials.styles')
@endpush

@section('content')
@php
    $dashRoute = $prefix.'service-requests.dashboard';
    $statusRoute = $prefix.'service-requests.status';
    $name = $row->cfaSubmission?->applicant_name ?: 'Incubatee';
    $phone = $row->cfaSubmission?->phone ?: null;
    $hub = $row->cfaSubmission?->district?->hub?->name;
    $serviceName = $row->service?->name ?: 'Service';
@endphp
<div class="mr-shell">
    @if (session('status'))<div class="ldm-list-alert ldm-list-alert--success">{{ session('status') }}</div>@endif
    @if ($errors->any())<div class="ldm-list-alert ldm-list-alert--warning">{{ $errors->first() }}</div>@endif

    <a class="mr-back" href="{{ route($dashRoute) }}">← All requests</a>

    <div class="mr-hero">
        <span class="mr-hero__kicker">Service request</span>
        <h2 class="mr-hero__title">{{ $name }}</h2>
        <p class="mr-hero__sub">{{ $serviceName }} · {{ $row->cfaSubmission?->district?->name ?: 'District' }}@if($hub) · {{ $hub }}@endif</p>
        <div class="mr-hero__chips">
            <span class="mr-badge mr-badge--on-dark">{{ str_replace('_', ' ', (string) $row->status) }}</span>
            <span class="mr-chip">CFA {{ $row->cfaSubmission?->application_no ?: '—' }}</span>
            @if ($phone)<span class="mr-chip">{{ $phone }}</span>@endif
            <span class="mr-chip">Requested {{ \App\Support\Ist::datetime($row->created_at) }}</span>
        </div>
    </div>

    <div class="mr-grid">
        <div class="mr-card">
            <h3 class="mr-card__h">Request</h3>
            <div class="mr-facts">
                <div><span class="mr-fact__l">Incubatee</span><span class="mr-fact__v">{{ $name }}</span></div>
                <div><span class="mr-fact__l">Service</span><span class="mr-fact__v">{{ $serviceName }}</span></div>
                <div><span class="mr-fact__l">District</span><span class="mr-fact__v">{{ $row->cfaSubmission?->district?->name ?: '—' }}</span></div>
                <div><span class="mr-fact__l">CFA no.</span><span class="mr-fact__v">{{ $row->cfaSubmission?->application_no ?: '—' }}</span></div>
            </div>
            <div class="mr-message">{{ $row->comment ?: 'No message with this request.' }}</div>
            @if ($row->staff_note)
                <p class="mr-card__h" style="margin:1rem 0 0.4rem">Staff note</p>
                <div class="mr-message">{{ $row->staff_note }}</div>
            @endif
            @if ($row->handledBy)
                <p class="mr-muted" style="margin:0.75rem 0 0">Updated by {{ $row->handledBy->name }} · {{ \App\Support\Ist::datetime($row->handled_at) }}</p>
            @endif
        </div>
    </div>

    @if (!empty($canHandle) && ($row->isPending() || $row->isInProgress()))
        <div class="mr-card">
            <h3 class="mr-card__h">Update status</h3>
            <form method="post" action="{{ route($statusRoute, $row) }}">
                @csrf
                <div class="ldm-list-filter-field" style="margin-bottom:0.75rem">
                    <label>Note (optional)</label>
                    <textarea name="staff_note" rows="3" style="width:100%;padding:0.55rem;border:1px solid #cbd5e1;border-radius:8px;font-family:inherit">{{ $row->staff_note }}</textarea>
                </div>
                <div class="mr-actions">
                    @if ($row->isPending())
                        <button type="submit" name="status" value="in_progress" class="mr-btn mr-btn--ghost">Mark in progress</button>
                    @endif
                    <button type="submit" name="status" value="done" class="mr-btn mr-btn--success">Mark Done</button>
                </div>
            </form>
        </div>
    @endif
</div>
@endsection
