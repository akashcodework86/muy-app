@extends('layouts.admin')

@section('title', 'Review field activity')
@section('heading', 'Review field activity')

@push('styles')
@include('spoc.field-mis-approvals.partials.styles')
@endpush

@section('content')
@php
    use App\Models\ServiceCase;
    $isPending = $record->status === ServiceCase::STATUS_PENDING_APPROVAL;
    $titleCol = (string) ($moduleMeta['title_column'] ?? 'id');
    $dateCol = (string) ($moduleMeta['date_column'] ?? 'created_at');
    $title = trim((string) ($record->{$titleCol} ?? '')) ?: 'Entry #'.$record->getKey();
    $eventDate = $record->{$dateCol} ?? null;
    $canReview = $isPending && (int) $record->submitted_by_user_id !== (int) auth()->id();
    $bannerClass = match ($record->status) {
        ServiceCase::STATUS_PENDING_APPROVAL => 'fma-review-banner--pending',
        ServiceCase::STATUS_SENT_BACK => 'fma-review-banner--sent_back',
        ServiceCase::STATUS_REJECTED => 'fma-review-banner--rejected',
        ServiceCase::STATUS_APPROVED => 'fma-review-banner--approved',
        default => 'fma-review-banner--pending',
    };
@endphp

<div class="fma-wrap">
    <p style="margin:0;">
        <a href="{{ route('spoc.service-cases.index', array_filter(['status' => $isPending ? ServiceCase::STATUS_PENDING_APPROVAL : null, 'service_id' => $moduleKey])) }}" class="fma-btn">← Back to approval queue</a>
    </p>

    @if (session('status'))
        <div class="fma-alert-ok">{{ session('status') }}</div>
    @endif
    @if ($errors->any())
        <ul style="color:#b91c1c;margin:0;padding-left:1.2rem;font-size:0.88rem;">
            @foreach ($errors->all() as $e)
                <li>{{ $e }}</li>
            @endforeach
        </ul>
    @endif

    <div class="fma-review-banner {{ $bannerClass }}">
        <strong>{{ $moduleMeta['serial'] ?? '' }} — {{ $moduleMeta['label'] ?? $moduleKey }}</strong>
        · {{ $record->misFieldStatusLabel() }}
        @if ($record->status === ServiceCase::STATUS_SENT_BACK && $record->sent_back_note)
            <div style="margin-top:0.35rem;"><strong>Sent back note:</strong> {{ $record->sent_back_note }}</div>
        @endif
        @if ($record->status === ServiceCase::STATUS_REJECTED && $record->rejected_note)
            <div style="margin-top:0.35rem;"><strong>Rejection reason:</strong> {{ $record->rejected_note }}</div>
        @endif
    </div>

    <div class="fma-detail-card" style="margin-bottom:0;">
        <div class="fma-detail-grid">
            <div><span class="fma-detail-label">Title</span><span class="fma-detail-value">{{ $title }}</span></div>
            <div><span class="fma-detail-label">District</span><span class="fma-detail-value">{{ $record->district_name ?? $record->district?->name ?? '—' }}</span></div>
            <div><span class="fma-detail-label">Event date</span><span class="fma-detail-value">{{ $eventDate?->format('d M Y') ?? '—' }}</span></div>
            <div><span class="fma-detail-label">Submitted by</span><span class="fma-detail-value">{{ $record->submitted_by_name }}</span></div>
            <div><span class="fma-detail-label">Submitted at</span><span class="fma-detail-value">{{ $record->submitted_at?->timezone(config('app.timezone'))->format('d M Y H:i') ?? '—' }}</span></div>
        </div>
        <p style="margin:0.75rem 0 0;font-size:0.8rem;color:#64748b;">Only <strong>approved</strong> entries count toward deliverables.</p>
    </div>

    <div class="fma-review-layout">
        <div>
            @include('spoc.field-mis-approvals.partials.detail', [
                'moduleKey' => $moduleKey,
                'record' => $record,
                'row' => $record,
                'currentRole' => $currentRole ?? 'state_staff',
                'applicantSnapshots' => $applicantSnapshots ?? [],
            ])
        </div>

        <aside class="fma-action-card">
            <h3>Approval actions</h3>

            @if ($canReview)
                <form method="post" action="{{ route('spoc.field-mis-approvals.approve', [$moduleKey, $record->getKey()]) }}" class="fma-action-form" onsubmit="return confirm('Approve this entry?');">
                    @csrf
                    <button type="submit" class="fma-btn fma-btn--approve">Approve</button>
                    <p style="margin:0;font-size:0.76rem;color:#64748b;">Counts toward MIS after approval.</p>
                </form>

                <form method="post" action="{{ route('spoc.field-mis-approvals.send-back', [$moduleKey, $record->getKey()]) }}" class="fma-action-form">
                    @csrf
                    <label for="send_back_note">Send back note</label>
                    <textarea id="send_back_note" name="note" rows="4" required placeholder="What should the submitter fix?"></textarea>
                    <button type="submit" class="fma-btn fma-btn--sendback">Send back</button>
                </form>

                <form method="post" action="{{ route('spoc.field-mis-approvals.reject', [$moduleKey, $record->getKey()]) }}" class="fma-action-form">
                    @csrf
                    <label for="reject_note">Rejection reason</label>
                    <textarea id="reject_note" name="note" rows="4" required placeholder="Why is this entry rejected?"></textarea>
                    <button type="submit" class="fma-btn fma-btn--reject">Reject</button>
                </form>
            @elseif ($isPending)
                <p style="margin:0;font-size:0.85rem;color:#9a3412;">You cannot approve your own submission.</p>
            @else
                <p style="margin:0;font-size:0.85rem;color:#64748b;">This entry is <strong>{{ $record->misFieldStatusLabel() }}</strong>. No further approval actions are available.</p>
            @endif
        </aside>
    </div>
</div>
@endsection
