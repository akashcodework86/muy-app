@extends('layouts.admin')

@section('title', 'CFA (FY 2025-26) '.($legacyDetail['viewRow']['application_no'] ?? ''))
@section('heading', 'FY 2025-26 — full record')

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
</style>
@endpush

@section('content')
    <p class="no-print" style="margin-bottom:1rem;">
        <a href="{{ $cfaIndexUrl }}">← Back to search</a>
    </p>

    <div class="cfa-legacy-banner no-print" role="status">
        <strong>Phase 2 legacy database.</strong> Full applicant record from <code>rbi_applications</code> / <code>rbi_applicant_details</code> (FY 2025–26).
    </div>

    <section class="cfa-legacy-section">
        <h2>Applicant &amp; location</h2>
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
        <h2>Application &amp; business</h2>
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
            <dt>Cohort / batch</dt><dd>{{ $vr['cohort_name'] }}</dd>
            <dt>Onboarding</dt><dd>{{ $vr['onboarding_status'] }}</dd>
            <dt>Phase 2 ID</dt><dd>{{ $legacyDetail['legacy_application_id'] }}</dd>
        </dl>
    </section>

    <section class="cfa-legacy-section">
        <h2>Services</h2>
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
@endsection
