@extends('layouts.admin')

@section('title', 'CFA (Phase 2 legacy) '.$submission->application_no)
@section('heading', 'Phase 2 legacy — full record')

@php
    $vr = $legacyDetail['viewRow'];
    $dash = '—';
    $fmt = function ($v) use ($dash): string {
        if ($v === null || $v === '') {
            return $dash;
        }
        if (is_bool($v)) {
            return $v ? 'Yes' : 'No';
        }
        if (is_scalar($v)) {
            return (string) $v;
        }
        return json_encode($v, JSON_UNESCAPED_UNICODE);
    };
@endphp

@push('styles')
<style>
    .cfa-legacy-banner {
        background: linear-gradient(135deg, #ecfdf5 0%, #e0f2fe 100%);
        border: 1px solid #99f6e4;
        border-radius: 10px;
        padding: 0.85rem 1rem;
        margin-bottom: 1rem;
        font-size: 0.9rem;
        color: #0f766e;
    }
    .cfa-legacy-grid {
        display: grid;
        grid-template-columns: minmax(10rem, 28%) 1fr;
        gap: 0.4rem 1rem;
        font-size: 0.88rem;
    }
    .cfa-legacy-grid dt { color: #64748b; font-weight: 600; margin: 0; }
    .cfa-legacy-grid dd { margin: 0; word-break: break-word; }
    .cfa-legacy-section { margin-bottom: 1.35rem; }
    .cfa-legacy-section h2 {
        font-size: 1rem;
        margin: 0 0 0.65rem;
        padding-bottom: 0.35rem;
        border-bottom: 1px solid #e2e8f0;
        color: #0f172a;
    }
    .cfa-legacy-raw {
        max-height: 22rem;
        overflow: auto;
        background: #f8fafc;
        border: 1px solid #e2e8f0;
        border-radius: 8px;
        padding: 0.65rem 0.85rem;
        font-size: 0.78rem;
        font-family: ui-monospace, monospace;
    }
</style>
@endpush

@section('content')
    <p class="no-print" style="margin-bottom:1rem;">
        <a href="{{ $cfaIndexUrl }}">← Back</a>
    </p>

    <div class="cfa-legacy-banner no-print" role="status">
        <strong>Phase 2 legacy database.</strong> This screen loads the full applicant record from <code>rbi_applications</code> / <code>rbi_applicant_details</code> (same source as district staff “FY 2025-26 Data”). Laravel row is a mirror — details below are read-only from legacy.
        <span style="display:block;margin-top:0.5rem;font-weight:500">Many old records have <strong>no email</strong> in legacy — that is why you may see “—” in the raw columns. Incubatee portal login does not depend on that field; use <strong>Incubatee portal login</strong> in the reference block below.</span>
    </div>

    @if (! empty($legacyDetail['district_mismatch_warning'] ?? null))
        <div class="no-print" style="margin-bottom:1rem;padding:0.75rem 1rem;border-radius:8px;border:1px solid #fbbf24;background:#fffbeb;color:#92400e;font-size:0.9rem;" role="alert">
            <strong>District note.</strong> {{ $legacyDetail['district_mismatch_warning'] }}
        </div>
    @endif

    @php
        $incubateePortalUser = \App\Models\User::query()
            ->where('cfa_submission_id', $submission->id)
            ->where('role', 'incubatee')
            ->first();
        $portalLoginEmail = $incubateePortalUser?->email
            ?? \App\Services\IncubateeLoginEmailResolver::forSubmission($submission);
    @endphp

    <section class="cfa-legacy-section">
        <h2>Laravel mirror (reference)</h2>
        <dl class="cfa-legacy-grid">
            <dt>Local ID</dt><dd>{{ $submission->id }}</dd>
            <dt>Application no.</dt><dd>{{ $submission->application_no ?? $dash }}</dd>
            <dt>District (Laravel)</dt><dd>{{ $submission->district?->name ?? $dash }}</dd>
            <dt>Fiscal year</dt><dd>{{ $submission->fiscalYear?->name ?? $submission->fiscalYear?->code ?? $dash }}</dd>
            <dt>Legacy application id</dt><dd>{{ $legacyDetail['legacy_application_id'] }}</dd>
            <dt>Incubatee portal login</dt>
            <dd>
                <code style="font-size:0.95em">{{ $portalLoginEmail }}</code>
                @if ($incubateePortalUser === null)
                    <span style="color:#64748b;font-size:0.85rem"> — created when you run <code style="font-size:0.85em">php artisan incubatees:provision-users</code> (same ID).</span>
                @endif
            </dd>
        </dl>
    </section>

    <section class="cfa-legacy-section">
        <h2>Applicant & location (Phase 2)</h2>
        <dl class="cfa-legacy-grid">
            <dt>Applicant</dt><dd>{{ $vr['applicant_name'] }}</dd>
            <dt>Phone</dt><dd>{{ $vr['phone'] }}</dd>
            <dt>Gender</dt><dd>{{ $vr['gender'] }}</dd>
            <dt>Social category</dt><dd>{{ $vr['caste'] }}</dd>
            <dt>SHG member</dt><dd>{{ $vr['is_shg_member'] }}</dd>
            <dt>District</dt><dd>{{ $vr['district'] }}</dd>
            <dt>Block</dt><dd>{{ $vr['block'] }}</dd>
            <dt>Village</dt><dd>{{ $vr['village'] }}</dd>
        </dl>
    </section>

    <section class="cfa-legacy-section">
        <h2>Application & business</h2>
        <dl class="cfa-legacy-grid">
            <dt>Application no.</dt><dd>{{ $vr['application_no'] }}</dd>
            <dt>Category</dt><dd>{{ $vr['app_category'] }}</dd>
            <dt>Form stage</dt><dd>{{ $vr['form_stage'] }}</dd>
            <dt>Submission date</dt><dd>{{ $vr['submission_date'] }}</dd>
            <dt>Product</dt><dd>{{ $vr['product'] }}</dd>
            <dt>Business category</dt><dd>{{ $vr['business_category'] }}</dd>
            <dt>Turnover (last year)</dt><dd>{{ $vr['turnover_last_year'] }}</dd>
            <dt>Loan taken</dt><dd>{{ $vr['loan_taken'] }}</dd>
            <dt>Bank loan</dt><dd>{{ $vr['bank_loan'] }}</dd>
            <dt>Cohort / batch (legacy)</dt><dd>{{ $vr['cohort_name'] }}</dd>
            <dt>Onboarding (legacy flag)</dt><dd>{{ $vr['onboarding_status'] }}</dd>
        </dl>
    </section>

    <section class="cfa-legacy-section">
        <h2>Services (Phase 2)</h2>
        <dl class="cfa-legacy-grid">
            <dt>Marketing</dt><dd>{{ $vr['marketing_service'] }} — {{ $vr['marketing_details'] }}</dd>
            <dt>Finance</dt><dd>{{ $vr['finance_service'] }} — {{ $vr['finance_details'] }}</dd>
            <dt>Training</dt><dd>{{ $vr['training_service'] }} — {{ $vr['training_details'] }}</dd>
            <dt>Other</dt><dd>{{ $vr['other_services_details'] }}</dd>
            <dt>All services</dt><dd>{{ $vr['all_services'] }}</dd>
        </dl>
    </section>

    @if (! empty($legacyDetail['rbi_applications']))
        <section class="cfa-legacy-section">
            <h2>All columns — rbi_applications</h2>
            <dl class="cfa-legacy-grid">
                @foreach ($legacyDetail['rbi_applications'] as $key => $val)
                    <dt>{{ $key }}</dt>
                    <dd>{{ $fmt($val) }}</dd>
                @endforeach
            </dl>
        </section>
    @endif

    @if (! empty($legacyDetail['rbi_applicant_details']))
        <section class="cfa-legacy-section">
            <h2>All columns — rbi_applicant_details</h2>
            <dl class="cfa-legacy-grid">
                @foreach ($legacyDetail['rbi_applicant_details'] as $key => $val)
                    <dt>{{ $key }}</dt>
                    <dd>{{ $fmt($val) }}</dd>
                @endforeach
            </dl>
        </section>
    @endif

    {{-- Legacy service case assignment UI — hidden while maker-checker redesign is in progress. --}}
    {{-- Re-enable via FEATURE_SERVICE_CASE_ASSIGNMENT=true in .env (see config/features.php). --}}
    @if (config('features.service_case_assignment'))
        @isset($serviceCasesUi)
            @include('partials.incubatee-service-cases', ['serviceCasesUi' => $serviceCasesUi])
        @endisset
    @endif
@endsection
