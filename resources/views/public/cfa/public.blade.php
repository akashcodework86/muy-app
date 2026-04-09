@extends('public.cfa.layout-cfa')

@section('title', 'Call for Application — Public')

@push('head')
<style>
    .public-badge {
        display: inline-flex;
        align-items: center;
        gap: 0.35rem;
        background: rgba(255,255,255,0.15);
        border: 1px solid rgba(255,255,255,0.3);
        border-radius: 6px;
        padding: 0.3rem 0.7rem;
        font-size: 0.78rem;
        font-weight: 600;
        letter-spacing: 0.03em;
        margin-top: 0.6rem;
    }
</style>
@endpush

@section('content')

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
            <span class="public-badge">🌐 Public Form / सार्वजनिक आवेदन</span>
        </div>
    </div>
</header>

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
              action="{{ route('cfa.public.store') }}"
              id="cfaMainForm"
              novalidate>
            @csrf
            @include('public.cfa.partials.section-a', [
                'publicMode'   => true,
                'districts'    => $districts,
                'districtName' => '',
                'blocks'       => [],
            ])
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
        <h3 class="section-header" style="margin-bottom: 1rem;">Stage Calculation</h3>
        <div class="stage-logic" id="stageLogic"></div>
        <div style="margin-top:1.5rem;text-align:center;">
            <button type="button" class="btn-primary" onclick="closeStageModal()">Got it</button>
        </div>
    </div>
</div>

@push('scripts')
<script>
window.CFA_PRODUCTS              = @json($productsByCategory);
window.CFA_START_SECTION_B       = @json((bool) old('form_stage'));
window.CFA_OLD_PRODUCT           = @json(old('product'));
window.CFA_OLD_BUSINESS_CATEGORY = @json(old('business_category'));
window.CFA_CHECK_PHONE_URL       = @json(route('cfa.public.check-phone'));
</script>
<script src="{{ asset('js/cfa-form.js') }}?v={{ filemtime(public_path('js/cfa-form.js')) }}"></script>

{{-- Dynamic district → block loader for the public form --}}
<script>
(function () {
    var districtSelect = document.getElementById('district_select');
    var blockSelect    = document.getElementById('block');
    var loadingMsg     = document.getElementById('blockLoadingMsg');
    if (!districtSelect || !blockSelect) return;

    var oldBlock = @json(old('block'));

    function loadBlocks(districtId) {
        if (!districtId) {
            blockSelect.innerHTML = '<option value="">— select district first —</option>';
            blockSelect.disabled  = true;
            if (loadingMsg) loadingMsg.style.display = 'none';
            return;
        }
        if (loadingMsg) loadingMsg.style.display = 'block';
        blockSelect.disabled = true;

        fetch('{{ route("api.cfa.blocks") }}?district_id=' + districtId)
            .then(function (r) { return r.json(); })
            .then(function (blocks) {
                blockSelect.innerHTML = '<option value="">Select Block</option>';
                blocks.forEach(function (b) {
                    var opt         = document.createElement('option');
                    opt.value       = b;
                    opt.textContent = b;
                    if (b === oldBlock) { opt.selected = true; oldBlock = null; }
                    blockSelect.appendChild(opt);
                });
                blockSelect.disabled = false;
                // Trigger counter/guide refresh after blocks load
                blockSelect.dispatchEvent(new Event('change'));
            })
            .catch(function () {
                blockSelect.innerHTML = '<option value="">Could not load blocks — please retry.</option>';
                blockSelect.disabled  = false;
            })
            .finally(function () {
                if (loadingMsg) loadingMsg.style.display = 'none';
            });
    }

    districtSelect.addEventListener('change', function () {
        var opt = this.options[this.selectedIndex];
        loadBlocks(opt && opt.dataset.districtId ? opt.dataset.districtId : null);
    });

    // On page load restore previously selected district (after a validation error redirect)
    var preSelected = districtSelect.options[districtSelect.selectedIndex];
    if (preSelected && preSelected.dataset && preSelected.dataset.districtId && preSelected.value) {
        loadBlocks(preSelected.dataset.districtId);
    }
})();
</script>
@endpush

@endsection
