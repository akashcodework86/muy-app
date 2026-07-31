@extends('layouts.admin')

@section('title', 'Log partner outreach')
@section('heading', 'Partner outreach (MIS 6.1)')

@push('styles')
<style>
    .mpo-shell { display:flex; flex-direction:column; gap:1.25rem; }
    .mpo-alert { border-radius:12px; padding:0.85rem 1rem; font-size:0.88rem; }
    .mpo-alert--warning { background:#fffbeb; border:1px solid #fde68a; color:#92400e; }
    .mpo-alert--success { background:#f0fdf4; border:1px solid #86efac; color:#166534; }
    .mpo-alert--error { background:#fef2f2; border:1px solid #fca5a5; color:#991b1b; }
    .mpo-alert--error ul { margin:0.35rem 0 0 1rem; }
    .mpo-card { background:#fff; border:1px solid #e2e8f0; border-radius:14px; padding:1.25rem 1.35rem; }
    .mpo-card__title { margin:0 0 0.35rem; font-size:1rem; font-weight:700; color:#0f172a; }
    .mpo-card__sub { margin:0 0 1rem; font-size:0.82rem; color:#64748b; line-height:1.45; }
    .mpo-grid { display:grid; grid-template-columns:repeat(auto-fit, minmax(220px, 1fr)); gap:0.85rem 1rem; }
    .mpo-field { display:flex; flex-direction:column; gap:0.35rem; margin-bottom:0.85rem; }
    .mpo-field--full { grid-column:1 / -1; }
    .mpo-field label { font-size:0.82rem; font-weight:700; color:#0f172a; }
    .mpo-field input, .mpo-field select, .mpo-field textarea {
        width:100%; box-sizing:border-box; border:1px solid #cbd5e1; border-radius:8px;
        padding:0.58rem 0.7rem; font-size:0.88rem;
    }
    .mpo-field textarea { min-height:4.5rem; resize:vertical; }
    .mpo-hint { margin:0; color:#64748b; font-size:0.76rem; line-height:1.4; }
    .mpo-actions { display:flex; flex-wrap:wrap; gap:0.65rem; align-items:center; margin-top:0.5rem; }
    .mpo-submit { border:none; border-radius:8px; background:#7c3aed; color:#fff; padding:0.62rem 1rem; font-weight:700; cursor:pointer; font-size:0.88rem; }
    .mpo-link { color:#7c3aed; font-weight:700; text-decoration:none; font-size:0.88rem; }
    .mpo-other-wrap { display:none; }
    .mpo-other-wrap.is-visible { display:flex; }
</style>
@endpush

@section('content')
<div class="mpo-shell">
    @if (!empty($migrationMissing))
        <div class="mpo-alert mpo-alert--warning">
            <strong>Database update required.</strong> Run <code>php artisan migrate</code> for the partner outreach table.
        </div>
    @endif

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
        <h3 class="mpo-card__title">New partner outreach</h3>
        <p class="mpo-card__sub">
            Log one marketing / forward-linkage partner Sanjna has reached out to.
            Each saved entry counts toward MIS indicator <strong>6.1 — No of Partners outreach</strong>.
            Status starts as <strong>Outreach logged</strong>; update to onboarded (LoA/LoI/MoU) from the detail page when signed.
        </p>

        <form method="post" action="{{ route($storeRoute) }}">
            @csrf
            <div class="mpo-grid">
                <div class="mpo-field">
                    <label for="outreach_date">Date <span style="color:#b91c1c;">*</span></label>
                    <input type="date" id="outreach_date" name="outreach_date" value="{{ old('outreach_date', now()->toDateString()) }}" required>
                </div>
                <div class="mpo-field mpo-field--full">
                    <label for="partner_name">Name of partner <span style="color:#b91c1c;">*</span></label>
                    <input type="text" id="partner_name" name="partner_name" value="{{ old('partner_name') }}" maxlength="255" required>
                </div>
                <div class="mpo-field">
                    <label for="partner_designation">Designation</label>
                    <input type="text" id="partner_designation" name="partner_designation" value="{{ old('partner_designation') }}" maxlength="191">
                </div>
                <div class="mpo-field">
                    <label for="partner_link">Link</label>
                    <input type="text" id="partner_link" name="partner_link" value="{{ old('partner_link') }}" maxlength="2048" placeholder="Website or LinkedIn URL">
                </div>
                <div class="mpo-field">
                    <label for="cohort_or_sector">Cohort or sector <span style="color:#b91c1c;">*</span></label>
                    <select id="cohort_or_sector" name="cohort_or_sector" required>
                        <option value="">Select</option>
                        @foreach ($cohortOrSectors as $value => $label)
                            <option value="{{ $value }}" @selected(old('cohort_or_sector') === $value)>{{ $label }}</option>
                        @endforeach
                    </select>
                </div>
                <div class="mpo-field mpo-other-wrap @if(old('cohort_or_sector') === 'other') is-visible @endif" id="cohortOtherWrap">
                    <label for="cohort_or_sector_other">Specify other cohort / sector <span style="color:#b91c1c;">*</span></label>
                    <input type="text" id="cohort_or_sector_other" name="cohort_or_sector_other" value="{{ old('cohort_or_sector_other') }}" maxlength="191">
                </div>
                <div class="mpo-field">
                    <label for="poc_name">Name of POC</label>
                    <input type="text" id="poc_name" name="poc_name" value="{{ old('poc_name') }}" maxlength="191">
                </div>
                <div class="mpo-field">
                    <label for="poc_contact_method">POC contact <span style="color:#b91c1c;">*</span></label>
                    <select id="poc_contact_method" name="poc_contact_method" required>
                        <option value="phone" @selected(old('poc_contact_method', 'phone') === 'phone')>Contact no.</option>
                        <option value="email" @selected(old('poc_contact_method') === 'email')>Mail</option>
                    </select>
                    <p class="mpo-hint">Choose phone or email for the POC.</p>
                </div>
                <div class="mpo-field" id="pocPhoneWrap">
                    <label for="poc_phone">Contact no. of POC <span style="color:#b91c1c;">*</span></label>
                    <input type="tel" id="poc_phone" name="poc_phone" value="{{ old('poc_phone') }}" maxlength="10" pattern="[6-9][0-9]{9}" inputmode="numeric">
                    <p class="mpo-hint">10-digit mobile number.</p>
                </div>
                <div class="mpo-field" id="pocEmailWrap" style="display:none;">
                    <label for="poc_email">Mail of POC <span style="color:#b91c1c;">*</span></label>
                    <input type="email" id="poc_email" name="poc_email" value="{{ old('poc_email') }}" maxlength="191">
                    <p class="mpo-hint">Valid email address.</p>
                </div>
                <div class="mpo-field mpo-field--full">
                    <label for="remarks">Remark</label>
                    <textarea id="remarks" name="remarks" maxlength="5000">{{ old('remarks') }}</textarea>
                </div>
            </div>

            <div class="mpo-actions">
                <button type="submit" class="mpo-submit">Save outreach entry</button>
                <a href="{{ route($dashboardRoute) }}" class="mpo-link">View dashboard</a>
            </div>
        </form>
    </div>
</div>
@endsection

@push('scripts')
<script>
    (function () {
        const select = document.getElementById('cohort_or_sector');
        const wrap = document.getElementById('cohortOtherWrap');
        if (select && wrap) {
            select.addEventListener('change', function () {
                wrap.classList.toggle('is-visible', select.value === 'other');
            });
        }
    })();

    (function () {
        const method = document.getElementById('poc_contact_method');
        const phoneWrap = document.getElementById('pocPhoneWrap');
        const emailWrap = document.getElementById('pocEmailWrap');
        const phoneInput = document.getElementById('poc_phone');
        const emailInput = document.getElementById('poc_email');
        if (!method || !phoneWrap || !emailWrap || !phoneInput || !emailInput) return;

        function syncContactFields(clearHidden) {
            const usePhone = method.value === 'phone';
            phoneWrap.style.display = usePhone ? '' : 'none';
            emailWrap.style.display = usePhone ? 'none' : '';
            phoneInput.required = usePhone;
            emailInput.required = !usePhone;
            if (clearHidden) {
                if (usePhone) {
                    emailInput.value = '';
                } else {
                    phoneInput.value = '';
                }
            }
        }

        method.addEventListener('change', function () {
            syncContactFields(true);
        });
        syncContactFields(false);
    })();
</script>
@endpush
