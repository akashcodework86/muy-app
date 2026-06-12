@extends('layouts.admin')

@section('title', 'Partner outreach #'.$row->id)
@section('heading', 'Partner outreach')

@push('styles')
<style>
    .mpo-shell { display:flex; flex-direction:column; gap:1.25rem; max-width:52rem; }
    .mpo-alert { border-radius:12px; padding:0.85rem 1rem; font-size:0.88rem; }
    .mpo-alert--success { background:#f0fdf4; border:1px solid #86efac; color:#166534; }
    .mpo-alert--error { background:#fef2f2; border:1px solid #fca5a5; color:#991b1b; }
    .mpo-alert--error ul { margin:0.35rem 0 0 1rem; }
    .mpo-card { background:#fff; border:1px solid #e2e8f0; border-radius:14px; padding:1.25rem 1.35rem; }
    .mpo-card__title { margin:0 0 1rem; font-size:1rem; font-weight:700; color:#0f172a; }
    .mpo-dl { display:grid; grid-template-columns:minmax(9rem, 34%) 1fr; gap:0.55rem 1rem; font-size:0.88rem; }
    .mpo-dl dt { color:#64748b; font-weight:600; margin:0; }
    .mpo-dl dd { margin:0; color:#0f172a; }
    .mpo-badge { display:inline-flex; align-items:center; padding:0.18rem 0.5rem; border-radius:999px; font-size:0.72rem; font-weight:700; }
    .mpo-badge--outreach { background:#ede9fe; color:#5b21b6; }
    .mpo-badge--discussion { background:#fef3c7; color:#92400e; }
    .mpo-badge--onboarded { background:#dcfce7; color:#166534; }
    .mpo-badge--declined { background:#f1f5f9; color:#475569; }
    .mpo-actions { display:flex; flex-wrap:wrap; gap:0.65rem; align-items:center; margin-top:1.25rem; }
    .mpo-link { color:#7c3aed; font-weight:700; text-decoration:none; font-size:0.88rem; }
    .mpo-btn--delete { border:1px solid #fecaca; background:#fff; color:#b91c1c; padding:0.45rem 0.8rem; font-size:0.84rem; font-weight:700; border-radius:8px; cursor:pointer; }
    .mpo-field { display:flex; flex-direction:column; gap:0.35rem; margin-bottom:0.85rem; }
    .mpo-field label { font-size:0.82rem; font-weight:700; color:#0f172a; }
    .mpo-field input, .mpo-field select { border:1px solid #cbd5e1; border-radius:8px; padding:0.58rem 0.7rem; font-size:0.88rem; }
    .mpo-submit { border:none; border-radius:8px; background:#7c3aed; color:#fff; padding:0.62rem 1rem; font-weight:700; cursor:pointer; font-size:0.88rem; }
    .mpo-hint { margin:0; color:#64748b; font-size:0.76rem; line-height:1.4; }
    .mpo-onboard-fields { display:none; margin-top:0.5rem; padding-top:0.75rem; border-top:1px dashed #e2e8f0; }
    .mpo-onboard-fields.is-visible { display:block; }
    .mpo-doc-link { color:#7c3aed; font-weight:700; text-decoration:none; }
</style>
@endpush

@section('content')
@php
    $onboardedStatuses = \App\Models\MarketingPartnerOutreachEntry::ONBOARDED_STATUSES;
    $linkHref = \App\Models\MarketLinkagePartner::clickableHref($row->partner_link);
@endphp
<div class="mpo-shell">
    @if (session('status'))
        <div class="mpo-alert mpo-alert--success">{{ session('status') }}</div>
    @endif

    @if ($errors->any())
        <div class="mpo-alert mpo-alert--error">
            <strong>Please fix:</strong>
            <ul>@foreach ($errors->all() as $e)<li>{{ $e }}</li>@endforeach</ul>
        </div>
    @endif

    <div class="mpo-card">
        <h3 class="mpo-card__title">Outreach details</h3>
        <dl class="mpo-dl">
            <dt>Outreach date</dt><dd>{{ $row->outreach_date?->format('d M Y') }}</dd>
            <dt>Partner name</dt><dd>{{ $row->partner_name }}</dd>
            <dt>Designation</dt><dd>{{ $row->partner_designation }}</dd>
            <dt>Link</dt>
            <dd>
                @if ($linkHref)
                    <a href="{{ $linkHref }}" class="mpo-doc-link" target="_blank" rel="noopener">{{ $row->partner_link }}</a>
                @elseif ($row->partner_link)
                    {{ $row->partner_link }}
                @else
                    —
                @endif
            </dd>
            <dt>Cohort / sector</dt>
            <dd>{{ \App\Support\PartnerOutreachOptions::cohortOrSectorDisplay((string) $row->cohort_or_sector, $row->cohort_or_sector_other) }}</dd>
            <dt>POC name</dt><dd>{{ $row->poc_name ?: '—' }}</dd>
            <dt>POC phone</dt><dd>{{ $row->poc_phone }}</dd>
            <dt>Remark</dt><dd>{{ $row->remarks ?: '—' }}</dd>
            <dt>Status</dt>
            <dd>
                <span class="mpo-badge {{ \App\Support\PartnerOutreachOptions::statusBadgeClass((string) $row->status) }}">
                    {{ \App\Support\PartnerOutreachOptions::statusLabel((string) $row->status) }}
                </span>
            </dd>
            @if ($row->onboarding_date)
                <dt>Onboarding date</dt><dd>{{ $row->onboarding_date->format('d M Y') }}</dd>
            @endif
            @if ($row->hasAgreementDocument())
                <dt>Signed document</dt>
                <dd><a href="{{ route($documentRoute, $row) }}" class="mpo-doc-link">{{ $row->agreement_document_original_name ?: 'Download document' }}</a></dd>
            @endif
            <dt>Submitted by</dt><dd>{{ $row->submitted_by_name }} · {{ $row->created_at?->format('d M Y H:i') }}</dd>
            @if ($row->status_updated_at)
                <dt>Status updated</dt>
                <dd>{{ $row->status_updated_by_name ?: '—' }} · {{ $row->status_updated_at->format('d M Y H:i') }}</dd>
            @endif
        </dl>

        <div class="mpo-actions">
            <a href="{{ route($dashboardRoute) }}" class="mpo-link">← Back to dashboard</a>
            @if (!empty($canDelete) && $destroyRoute)
                <form method="post" action="{{ route($destroyRoute, $row) }}" onsubmit="return confirm('Delete this partner outreach entry?');">
                    @csrf
                    @method('DELETE')
                    <button type="submit" class="mpo-btn--delete">Delete entry</button>
                </form>
            @endif
        </div>
    </div>

    @if (!empty($canChangeStatus) && $updateStatusRoute && ! $row->isOnboarded())
        <div class="mpo-card">
            <h3 class="mpo-card__title">Update onboarding status</h3>
            <p class="mpo-hint" style="margin-bottom:1rem;">
                Move this partner through the pipeline. Onboarded statuses (LoA / LoI / MoU) count toward MIS <strong>6.2</strong>.
                Once onboarded, status cannot be changed.
            </p>

            <form method="post" action="{{ route($updateStatusRoute, $row) }}" enctype="multipart/form-data">
                @csrf
                @method('PATCH')

                <div class="mpo-field">
                    <label for="status">Status <span style="color:#b91c1c;">*</span></label>
                    <select id="status" name="status" required>
                        @foreach ($statuses as $value => $label)
                            <option value="{{ $value }}" @selected(old('status', $row->status) === $value)>{{ $label }}</option>
                        @endforeach
                    </select>
                </div>

                <div class="mpo-onboard-fields @if(in_array(old('status', $row->status), $onboardedStatuses, true)) is-visible @endif" id="onboardFields">
                    <div class="mpo-field">
                        <label for="onboarding_date">Onboarding date <span style="color:#b91c1c;">*</span></label>
                        <input type="date" id="onboarding_date" name="onboarding_date" value="{{ old('onboarding_date', $row->onboarding_date?->toDateString()) }}">
                    </div>
                    <div class="mpo-field">
                        <label for="agreement_document">Signed document (PDF / image)</label>
                        <input type="file" id="agreement_document" name="agreement_document" accept=".pdf,.jpg,.jpeg,.png,.webp">
                        <p class="mpo-hint">Required when status is <strong>Onboarded — MoU</strong>. Optional for LoA / LoI.</p>
                    </div>
                </div>

                <button type="submit" class="mpo-submit">Save status</button>
            </form>
        </div>
    @elseif ($row->isOnboarded())
        <div class="mpo-card">
            <p class="mpo-hint" style="margin:0;">This partner is onboarded. Status is locked.</p>
        </div>
    @endif
</div>
@endsection

@push('scripts')
<script>
    (function () {
        const onboarded = @json($onboardedStatuses);
        const statusSelect = document.getElementById('status');
        const onboardFields = document.getElementById('onboardFields');
        if (!statusSelect || !onboardFields) return;

        function syncOnboardFields() {
            onboardFields.classList.toggle('is-visible', onboarded.includes(statusSelect.value));
        }

        statusSelect.addEventListener('change', syncOnboardFields);
        syncOnboardFields();
    })();
</script>
@endpush
