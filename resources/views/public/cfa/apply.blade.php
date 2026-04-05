@extends('public.cfa.layout-cfa')

@section('title', 'Call for Application')

@section('content')
@php
    $cfaEditingSubmission = $cfaEditingSubmission ?? null;
@endphp
@if ($errors->any())
    <div class="main-layout" style="padding-top:1rem;">
        <div class="cfa-error-banner" style="max-width:56rem;margin:0 auto;">
            Please correct the errors below and try again. {{ $errors->first() }}
        </div>
    </div>
@endif

<header class="form-header">
    <div class="header-inner">
        <div class="header-text">
            <h1>मुख्यमंत्री उद्यमशाला योजना</h1>
            <p>Rural Business Incubator — Call For Application</p>
            @if (! empty($cfaEditingSubmission))
                <p style="font-size:0.9rem;opacity:0.95;margin-top:0.5rem;padding:0.5rem 0.75rem;background:rgba(0,0,0,0.08);border-radius:8px;">
                    <strong>Editing</strong> application <strong>{{ $cfaEditingSubmission->application_no ?? '—' }}</strong>
                    · <a href="{{ route('staff.applications.show', $cfaEditingSubmission) }}" style="color:inherit;font-weight:600;">View printable record</a>
                </p>
            @endif
            <p style="font-size:0.9rem;opacity:0.9;margin-top:0.5rem;">
                Referred by <strong>{{ $staff->name }}</strong>
                @if($staff->designationRecord) ({{ $staff->designationRecord->name }}) @endif
                · {{ $staff->district?->name ?? 'District' }}
            </p>
        </div>
    </div>
</header>

@if ($districtName === '' || count($blocks) === 0)
    <div class="main-layout">
        <div class="form-section">
            <p>This referral link is not ready: district or block list is missing. Please contact support.</p>
        </div>
    </div>
@else
<div class="main-layout">
    <div class="form-column">
        <div class="progress-wrapper">
            <div class="progress-steps">
                <div class="progress-step active" id="progressStep1"></div>
                <div class="progress-step" id="progressStep2"></div>
            </div>
            <div class="progress-bar">
                <div class="progress-fill" id="progressFill" style="width: 50%"></div>
            </div>
        </div>

        <form method="post"
            action="{{ ! empty($cfaEditingSubmission) ? route('staff.applications.update', $cfaEditingSubmission) : route('cfa.apply.store', ['token' => $token]) }}"
            id="cfaMainForm"
            novalidate>
            @csrf
            @if (! empty($cfaEditingSubmission))
                @method('PUT')
            @endif
            @include('public.cfa.partials.section-a')
            @include('public.cfa.partials.section-b')
        </form>
    </div>

    <aside class="form-sidebar">
        <div class="sidebar-card">
            <h3 class="sidebar-title">Progress</h3>
            <div class="question-counter">
                <span id="filledCount">0</span> filled · <span id="remainingCount">0</span> remaining
                <span class="counter-hint">(this step — all visible fields)</span>
            </div>
            <div class="counter-bar">
                <div class="counter-fill" id="counterFill" style="width: 0%"></div>
            </div>
            <div class="required-progress" id="requiredProgressWrap">
                <strong>Required on this step:</strong>
                <span id="requiredProgressText">—</span>
            </div>
        </div>
        <div class="sidebar-card sidebar-card--missing" id="missingRequiredCard">
            <h3 class="sidebar-title">Still empty (required) / अनिवार्य बाकी</h3>
            <p class="missing-hint">Click any item to scroll to that field.</p>
            <ul class="missing-required-list" id="missingRequiredList" aria-live="polite"></ul>
            <p class="missing-all-clear" id="missingAllClear" hidden>✓ All required fields on this step are filled.</p>
        </div>
        <div class="sidebar-card" id="profileCard">
            <h3 class="sidebar-title">Applicant profile</h3>
            <div class="profile-content" id="profileContent">
                <p class="profile-placeholder" id="profilePlaceholder">Enter DOB, gender, education to build your profile.</p>
                <ul class="profile-list" id="profileList"></ul>
            </div>
        </div>
        <div class="sidebar-card">
            <h3 class="sidebar-title">Guide</h3>
            <div class="guide-content" id="guideContent">
                <p class="guide-text">Select applicant category to begin. Required fields have a red <span style="color:#dc2626">*</span>. Use the sidebar list <strong>Still empty (required)</strong> to jump to what is left.</p>
            </div>
        </div>
    </aside>
</div>

<div class="modal" id="stageLogicModal" style="display: none;">
    <div class="modal-content">
        <button class="modal-close" type="button" onclick="closeStageModal()" aria-label="Close">×</button>
        <h3 class="section-header" style="margin-bottom: 1rem;">Stage calculation</h3>
        <div class="stage-logic" id="stageLogic"></div>
        <div style="margin-top:1.5rem;text-align:center;">
            <button type="button" class="btn-primary" onclick="closeStageModal()">OK</button>
        </div>
    </div>
</div>

@push('scripts')
<script>
window.CFA_PRODUCTS = @json($productsByCategory);
window.CFA_START_SECTION_B = @json((bool) old('form_stage'));
window.CFA_OLD_PRODUCT = @json(old('product'));
window.CFA_OLD_BUSINESS_CATEGORY = @json(old('business_category'));
window.CFA_CHECK_PHONE_URL = @json(
    ! empty($cfaEditingSubmission)
        ? route('staff.applications.check-phone', $cfaEditingSubmission)
        : route('cfa.apply.check-phone', ['token' => $token])
);
</script>
<script src="{{ asset('js/cfa-form.js') }}"></script>
@endpush
@endif
@endsection
