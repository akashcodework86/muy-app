@extends('layouts.admin')

@section('title', 'CFA (Phase 1 legacy) '.$submission->application_no)
@section('heading', 'Phase 1 legacy — full record')

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
        background: linear-gradient(135deg, #fff7ed 0%, #fef3c7 100%);
        border: 1px solid #fcd34d;
        border-radius: 10px;
        padding: 0.85rem 1rem;
        margin-bottom: 1rem;
        font-size: 0.9rem;
        color: #92400e;
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
        <a href="{{ $cfaIndexUrl }}">← Back</a>
    </p>

    <div class="cfa-legacy-banner no-print" role="status">
        <strong>Phase 1 legacy database.</strong> Full applicant record from <code>ukrbiin_rbi.tblapplication</code> (RBI Phase 1 / FY 2024–25 data). Laravel row is a mirror — details below are read-only from legacy.
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
            <dt>Legacy Phase 1 ID</dt><dd>{{ $legacyDetail['legacy_phase1_id'] }}</dd>
            <dt>Incubatee portal login</dt>
            <dd>
                <code style="font-size:0.95em">{{ $portalLoginEmail }}</code>
                @if ($incubateePortalUser === null)
                    <span style="color:#64748b;font-size:0.85rem"> — created when portal logins are provisioned for the locked batch.</span>
                @endif
            </dd>
        </dl>
    </section>

    <section class="cfa-legacy-section">
        <h2>Applicant & location (Phase 1)</h2>
        <dl class="cfa-legacy-grid">
            <dt>Applicant</dt><dd>{{ $vr['applicant_name'] }}</dd>
            <dt>Phone</dt><dd>{{ $vr['phone'] }}</dd>
            <dt>Gender</dt><dd>{{ $vr['gender'] }}</dd>
            <dt>Education</dt><dd>{{ $vr['education'] }}</dd>
            <dt>District</dt><dd>{{ $vr['district'] }}</dd>
            <dt>Legacy district key</dt><dd>{{ $vr['legacy_district_key'] }} <span style="color:#64748b;font-size:0.85rem">(FatherName)</span></dd>
            <dt>Legacy region</dt><dd>{{ $vr['legacy_region'] }}</dd>
            <dt>Village / locality</dt><dd>{{ $vr['village'] }}</dd>
        </dl>
    </section>

    <section class="cfa-legacy-section">
        <h2>Application & status</h2>
        <dl class="cfa-legacy-grid">
            <dt>Application no.</dt><dd>{{ $vr['application_no'] }}</dd>
            <dt>Application date</dt><dd>{{ $vr['application_date'] }}</dd>
            <dt>Onboard status</dt><dd>{{ $vr['onboard_status'] }}</dd>
            <dt>Loan / scheme status</dt><dd>{{ $vr['application_status'] }}</dd>
            <dt>Market linkage</dt><dd>{{ $vr['market_linkage'] }}</dd>
        </dl>
    </section>

    @if (! empty($legacyDetail['services']))
        <section class="cfa-legacy-section">
            <h2>Services & support (Phase 1)</h2>
            <dl class="cfa-legacy-grid">
                @foreach ($legacyDetail['services'] as $svc)
                    <dt>{{ $svc['label'] }}</dt>
                    <dd>{{ $svc['detail'] ?? 'Yes' }}</dd>
                @endforeach
            </dl>
        </section>
    @endif

    @if (! empty($legacyDetail['tblapplication']))
        <section class="cfa-legacy-section">
            <h2>All columns — tblapplication</h2>
            <dl class="cfa-legacy-grid">
                @foreach ($legacyDetail['tblapplication'] as $key => $val)
                    <dt>{{ $key }}</dt>
                    <dd>{{ $fmt($val) }}</dd>
                @endforeach
            </dl>
        </section>
    @endif

    @if (config('features.service_case_assignment'))
        @isset($serviceCasesUi)
            @include('partials.incubatee-service-cases', ['serviceCasesUi' => $serviceCasesUi])
        @endisset
    @endif
@endsection
