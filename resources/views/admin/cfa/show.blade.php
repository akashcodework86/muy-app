@extends('layouts.admin')

@section('title', 'CFA application '.$submission->application_no)
@section('heading', 'CFA application')

@php
    $p = is_array($submission->payload) ? $submission->payload : [];
    $dash = '—';
    $cell = function (string $key) use ($p, $dash): string {
        if (! array_key_exists($key, $p)) {
            return $dash;
        }
        $v = $p[$key];
        if ($v === null || $v === '' || $v === []) {
            return $dash;
        }
        if (is_bool($v)) {
            return $v ? 'Yes' : 'No';
        }
        if (is_scalar($v)) {
            return (string) $v;
        }

        return $dash;
    };
    $challengeLabels = [
        'Unavailability of Packaging Material' => 'Unavailability of Packaging Material',
        'Sales & Marketing' => 'Sales & Marketing',
        'Branding' => 'Branding',
        'Loan or Financial Issue' => 'Loan or Financial Issue',
        'License or Legal support' => 'License or Legal Support',
        'Lack of Government Scheme Information' => 'Lack of Government Scheme Info',
        'Lack of Technical Knowledge' => 'Lack of Technical Knowledge',
        'Lack of Training' => 'Lack of Training',
        'Unavailability of Raw material' => 'Unavailability of Raw Material',
        'Wild Animals Destroy our Crops' => 'Wild Animal Crop Damage',
        'Lack of Mentor' => 'Lack of Mentor',
        'Lack of Digital Marketing Knowledge' => 'No Digital Marketing Knowledge',
        'Networking issue to sell our Products' => 'Networking Issues for Sales',
        'Lack of teamwork' => 'Lack of Teamwork',
        'Unavailability of the Machine' => 'Machine Unavailability',
        'Connectivity Challenge for Homestay' => 'Homestay Connectivity Issues',
        'Human Resource Problem Due to Migration' => 'Workforce Migration',
        'Lack of Skills' => 'Lack of Skills',
        'Capacity Building issue' => 'Capacity Building Issue',
        'Seasonal work' => 'Seasonal Work Only',
        'Lack of Pricing and Costing' => 'Pricing & Costing Knowledge',
        'Exploitation by intermediaries' => 'Exploitation by Middlemen',
        'Machine Servicing Challenge' => 'Machine Servicing Challenge',
        'Animal attack while collecting raw Material Like Pine Leaf, Ringal, Bamboo' => 'Animal Attacks While Collecting Raw Materials',
        'Not getting enough money after selling our Products' => 'Low Returns from Sales',
        'Lack of Logistics Connectivity' => 'Logistics Connectivity Issue',
        'Insufficient Water for Farming' => 'Insufficient Farming Water',
        'Payment issue' => 'Payment Issues',
        'Diseases in Animals' => 'Animal Diseases',
        'Not getting a Trainer for Product Development' => 'No Trainer for Product Development',
        'No Update on District Level Industrial Policies' => 'No Updates on Local Policies',
        'No Idea of a Business Plan/ Road map/ vision for a business cycle.' => 'No Business Plan / Roadmap / Vision',
    ];
    $expectationLabels = [
        'Advise on ideation of the business idea' => 'Advise on ideation of the business idea',
        'Support in prototyping' => 'Support in prototyping',
        'Market testing' => 'Market testing',
        'Support for IPR and other licenses' => 'Support for IPR and other licenses',
        'Access to market' => 'Access to market',
        'Access to mentors' => 'Access to mentors',
        'Access to finance' => 'Access to finance',
        'Access to infrastructure' => 'Access to infrastructure (co-working space, makers spaces, etc.)',
        'Networking' => 'Networking',
        'Access to funders and investors' => 'Access to funders and investors',
        'Other' => 'Other',
    ];
    $challenges = $p['challenges'] ?? [];
    $challenges = is_array($challenges) ? $challenges : [];
    $challengeText = collect($challenges)->map(fn ($c) => $challengeLabels[$c] ?? $c)->filter()->implode('; ');
    $expectations = $p['expectations'] ?? [];
    $expectations = is_array($expectations) ? $expectations : [];
    $expectationText = collect($expectations)->map(fn ($c) => $expectationLabels[$c] ?? $c)->filter()->implode('; ');
    $regResolved = $cell('registration_type_resolved');
    if ($regResolved === $dash && ($cell('registration_type') !== $dash || $cell('registration_type_other') !== $dash)) {
        $regParts = array_filter([$cell('registration_type'), $cell('registration_type_other')], fn ($x) => $x !== $dash);
        $regResolved = $regParts !== [] ? implode(' — ', $regParts) : $dash;
    }
    $stageLines = $p['stage_logic_lines'] ?? [];
    $stageLines = is_array($stageLines) ? $stageLines : [];
    $submittedPayload = null;
    if (! empty($p['submitted_at'])) {
        try {
            $submittedPayload = \Illuminate\Support\Carbon::parse($p['submitted_at'])->timezone(config('app.timezone'))->format('d M Y, H:i').' IST';
        } catch (\Throwable) {
            $submittedPayload = (string) $p['submitted_at'];
        }
    }
@endphp

@push('styles')
<style>
/* ═══════════════════════════════════════════════
   ACTION BAR  (screen only)
═══════════════════════════════════════════════ */
.cfa-action-bar {
    display: flex; flex-wrap: wrap; gap: .75rem; align-items: center;
    padding: .85rem 1.1rem; margin-bottom: 0;
    background: #1e293b; border-radius: 10px 10px 0 0;
}
.cfa-action-bar a {
    color: #93c5fd; text-decoration: none; font-weight: 600; font-size: .875rem;
}
.cfa-action-bar a:hover { color: #bfdbfe; text-decoration: underline; }
.btn-print {
    margin-left: auto;
    padding: .5rem 1.4rem; border: none; border-radius: 6px;
    background: #db2627; color: #fff; font-weight: 700;
    font-size: .875rem; cursor: pointer; font-family: inherit; letter-spacing: .04em;
    display: inline-flex; align-items: center; gap: .4rem;
}
.btn-print:hover { background: #b91c1c; }
.btn-edit-link {
    padding: .45rem 1rem; border-radius: 6px; background: #4f46e5;
    color: #fff !important; text-decoration: none !important; font-weight: 700 !important;
    font-size: .875rem;
}

/* ═══════════════════════════════════════════════
   PRINT-PREVIEW VIEWPORT  (screen only)
   Grey board on which A4 pages sit
═══════════════════════════════════════════════ */
.cfa-preview-board {
    background: #4b5563;
    padding: 2.5rem 1.5rem 3rem;
    border-radius: 0 0 10px 10px;
}
.cfa-page-label {
    text-align: center; color: #d1d5db;
    font-size: .7rem; font-weight: 700; letter-spacing: .12em;
    text-transform: uppercase; margin-bottom: .6rem;
    font-family: Arial, sans-serif;
}

/* ═══════════════════════════════════════════════
   A4 PAGE CARD
   On screen: white paper with shadow, fixed A4 width
   On print: full bleed, no shadow
═══════════════════════════════════════════════ */
.gov-page {
    width: 210mm;
    margin: 0 auto 3rem;
    background: #fff;
    box-shadow: 0 8px 40px rgba(0,0,0,.45);
    border: 2.5px solid #db2627;
    font-family: Arial, Helvetica, sans-serif;
    font-size: 10.5pt;
    line-height: 1.4;
    color: #111;
    overflow: hidden;
}
.gov-page:last-of-type { margin-bottom: 0; }

/* ── Header ── */
.gov-header {
    display: flex; align-items: center; justify-content: space-between;
    padding: 10px 14px 8px; border-bottom: 3px solid #db2627;
    background: #fff;
}
.gov-header-logo { width: 64px; height: 64px; object-fit: contain; flex-shrink: 0; }
.gov-header-center { text-align: center; flex: 1; padding: 0 12px; }
.gov-header-title-hi  { font-size: 17pt; font-weight: 900; color: #db2627; line-height: 1.15; }
.gov-header-title-en  { font-size: 11pt; font-weight: 700; color: #111; margin-top: 1px; }
.gov-header-subtitle  { font-size: 8.5pt; color: #555; margin-top: 3px; }
.gov-header-badge {
    display: inline-block; margin-top: 5px;
    background: #db2627; color: #fff;
    font-size: 8.5pt; font-weight: 700; padding: 3px 16px; border-radius: 2px;
    letter-spacing: .07em; text-transform: uppercase;
}

/* ── App-no bar ── */
.gov-appno-bar {
    display: flex; justify-content: space-between; align-items: center;
    background: #fef2f2; border-bottom: 1px solid #fca5a5;
    padding: 5px 14px;
}
.gov-appno-bar .appno   { font-weight: 900; color: #db2627; font-size: 12pt; }
.gov-appno-bar .appmeta { color: #555; font-size: 8.5pt; }

/* ── Section heading ── */
.gov-sec-head {
    background: #db2627; color: #fff; font-weight: 700;
    font-size: 9pt; padding: 5px 12px;
    text-transform: uppercase; letter-spacing: .07em; margin: 0;
}

/* ── Fields grid ── */
.gov-fields       { display: grid; padding: 7px 10px; gap: 6px 10px; border-bottom: 1px solid #e5e7eb; }
.gov-fields.c3    { grid-template-columns: repeat(3, 1fr); }
.gov-fields.c2    { grid-template-columns: repeat(2, 1fr); }
.gov-fields.c1    { grid-template-columns: 1fr; }
.gf               { min-width: 0; }
.gf.s2            { grid-column: span 2; }
.gf.s3            { grid-column: span 3; }
.gf label {
    display: block; font-size: 7pt; font-weight: 700;
    color: #db2627; text-transform: uppercase; letter-spacing: .05em; margin-bottom: 2px;
}
.gf .v {
    border-bottom: 1.5px solid #999;
    min-height: 16px; padding: 2px 3px;
    font-size: 10.5pt; color: #111; word-break: break-word;
}
.gf .v.tall {
    border: 1px solid #bbb; min-height: 44px;
    padding: 4px 6px; background: #fafafa;
    white-space: pre-wrap;
}

/* ── Page 2 mini-header ── */
.gov-page2-minihead {
    display: flex; justify-content: space-between; align-items: center;
    padding: 5px 12px; border-bottom: 1px solid #fca5a5; background: #fef2f2;
    font-size: 8pt; color: #666;
}
.gov-page2-minihead strong { color: #db2627; }

/* ── Declaration ── */
.gov-declaration {
    margin: 8px 10px; border: 1.5px solid #db2627; border-radius: 4px;
    padding: 8px 10px; font-size: 8.5pt; color: #333; background: #fff8f8;
    line-height: 1.55;
}
.gov-declaration strong { color: #db2627; }

/* ── Signature row ── */
.gov-sig-row {
    display: grid; grid-template-columns: 1fr 1fr 1fr;
    gap: 10px; padding: 8px 14px 12px;
}
.gov-sig-box {
    text-align: center; padding-top: 3px;
    font-size: 8pt; color: #555; margin-top: 36px;
    border-top: 1px solid #555;
}

/* ── Office use only ── */
.gov-office-use {
    border-top: 2px dashed #db2627;
    margin: 6px 10px 10px; padding-top: 6px;
}
.gov-office-title {
    font-size: 8pt; font-weight: 700; color: #db2627;
    text-transform: uppercase; letter-spacing: .07em; margin-bottom: 5px;
}

/* ═══════════════════════════════════════════════
   EDIT HISTORY  (screen only, below preview board)
═══════════════════════════════════════════════ */
.cfa-edit-history {
    margin-top: 2.5rem;
    background: #fff; border: 1px solid #e2e8f0; border-radius: 10px;
    padding: 1.25rem 1.5rem;
}
.cfa-edit-log-table { width: 100%; border-collapse: collapse; font-size: .8rem; margin-top: .5rem; }
.cfa-edit-log-table th,
.cfa-edit-log-table td { border: 1px solid #e2e8f0; padding: .45rem .55rem; text-align: left; vertical-align: top; }
.cfa-edit-log-table th { background: #fef2f2; color: #db2627; font-weight: 700; }

/* ═══════════════════════════════════════════════
   PRINT  —  two full A4 pages, content fills page
═══════════════════════════════════════════════ */
@media print {
    @page { size: A4 portrait; margin: .6cm; }

    /* Hide every piece of admin chrome */
    body.admin-app-body  { background: #fff !important; }
    .admin-topbar,
    .admin-page-head,
    .admin-page-meta,
    .muy-footer,
    .banner,
    .error-banner,
    .cfa-action-bar,
    .cfa-page-label,
    .cfa-edit-history,
    .no-print            { display: none !important; }

    /* Strip admin layout padding & all backgrounds/shadows */
    html, body, body.admin-app-body,
    .admin-main, .cfa-print-wrap,
    .cfa-preview-board   { background: #fff !important; background-color: #fff !important;
                           box-shadow: none !important; padding: 0 !important;
                           margin: 0 !important; border-radius: 0 !important;
                           max-width: 100% !important; }
    /* Nuke every shadow on every element — prevents any bleed into PDF */
    * { box-shadow: none !important; text-shadow: none !important; }

    /* Each .gov-page = one full A4 sheet, content stretched to fill */
    .gov-page {
        width: 100% !important;
        box-sizing: border-box !important;  /* border included in width — prevents right border clipping */
        min-height: 277mm !important;   /* A4 height minus margins = fills the sheet */
        display: flex !important;
        flex-direction: column !important;
        margin: 0 !important;
        box-shadow: none !important;
        border: 2.5px solid #db2627 !important;
        font-size: 9pt !important;
        page-break-after: always !important;
        break-after: page !important;
        overflow: hidden !important;
    }
    /* Last page: no trailing blank sheet */
    .gov-page:last-of-type {
        page-break-after: auto !important;
        break-after: auto !important;
    }
    /* Stretch the last fields section of each page to fill remaining space */
    .gov-page > .gov-fields:last-of-type,
    .gov-page > .gov-office-use {
        flex: 1 !important;
    }

    /* Header — generous, fills top of page */
    .gov-header           { padding: 10px 14px 8px !important; }
    .gov-header-logo      { width: 62px !important; height: 62px !important; }
    .gov-header-title-hi  { font-size: 15pt !important; }
    .gov-header-title-en  { font-size: 10pt !important; }
    .gov-header-subtitle  { font-size: 8pt !important; }
    .gov-header-badge     { font-size: 8pt !important; padding: 3px 14px !important; margin-top: 5px !important; }

    /* App-no bar */
    .gov-appno-bar        { padding: 5px 14px !important; }
    .gov-appno-bar .appno { font-size: 11pt !important; }
    .gov-appno-bar .appmeta { font-size: 8pt !important; }

    /* Section headings */
    .gov-sec-head         { font-size: 8.5pt !important; padding: 5px 12px !important; }

    /* Field rows — taller, more padding, more breathing room */
    .gov-fields           { padding: 7px 12px !important; gap: 7px 12px !important; }
    .gf label             { font-size: 6.5pt !important; margin-bottom: 2px !important; }
    .gf .v                { font-size: 9pt !important; min-height: 18px !important; padding: 2px 4px !important; border-bottom-width: 1.5px !important; }
    .gf .v.tall           { min-height: 48px !important; padding: 4px 6px !important; }

    /* Page 2 mini-header */
    .gov-page2-minihead   { font-size: 8pt !important; padding: 5px 12px !important; }

    /* Declaration */
    .gov-declaration      { font-size: 8pt !important; padding: 8px 12px !important; margin: 8px 12px !important; line-height: 1.6 !important; }

    /* Signatures — taller space */
    .gov-sig-row          { padding: 10px 16px 14px !important; }
    .gov-sig-box          { font-size: 8pt !important; margin-top: 42px !important; }

    /* Office use */
    .gov-office-use       { margin: 8px 12px 12px !important; padding-top: 8px !important; }
    .gov-office-title     { font-size: 8pt !important; margin-bottom: 6px !important; }

    /* Colour fidelity */
    .gov-sec-head       { background: #db2627 !important; color: #fff !important; }
    .gov-appno-bar      { background: #fef2f2 !important; }
    .gov-page2-minihead { background: #fef2f2 !important; }
    .gov-declaration    { background: #fff8f8 !important; border-color: #db2627 !important; }
    .gov-header-badge   { background: #db2627 !important; color: #fff !important; }
    * { -webkit-print-color-adjust: exact !important; print-color-adjust: exact !important; }
    }
</style>
@endpush

@section('content')
@php
    $cfaIndexUrl = $cfaIndexUrl ?? route('admin.cfa.index');
    $cfaEditUrl  = $cfaEditUrl  ?? null;
    $cfaEditLogs = $cfaEditLogs ?? collect();
@endphp

@if (session('status'))
    <div class="no-print" style="margin-bottom:1rem;padding:.75rem 1rem;background:#ecfdf5;border:1px solid #6ee7b7;border-radius:8px;color:#065f46;font-size:.9rem;">
        {{ session('status') }}
    </div>
@endif

<div class="cfa-print-wrap">

    {{-- ── Action bar (screen only) ── --}}
    <div class="cfa-action-bar no-print">
        <a href="{{ $cfaIndexUrl }}">← Back to list</a>
        @if (! empty($cfaEditUrl))
            <a href="{{ $cfaEditUrl }}" class="btn-edit-link">Edit</a>
        @endif
        <button type="button" class="btn-print" onclick="window.print()">
            <svg width="15" height="15" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5"><polyline points="6 9 6 2 18 2 18 9"/><path d="M6 18H4a2 2 0 0 1-2-2v-5a2 2 0 0 1 2-2h16a2 2 0 0 1 2 2v5a2 2 0 0 1-2 2h-2"/><rect x="6" y="14" width="12" height="8"/></svg>
            Print / Save PDF
        </button>
    </div>

    {{-- ════════════════════════════════════════════════════════
         PRINT PREVIEW BOARD  — two A4 pages shown as paper cards
         ════════════════════════════════════════════════════════ --}}
    <div class="cfa-preview-board">

        {{-- ══════════════ PAGE 1 ══════════════ --}}
        <p class="cfa-page-label no-print">— Page 1 of 2 —</p>
        <div class="gov-page">

            {{-- HEADER --}}
            <div class="gov-header">
                <img src="{{ asset('images/Seal_of_Uttarakhand.svg') }}" alt="Uttarakhand Seal" class="gov-header-logo">
                <div class="gov-header-center">
                    <div class="gov-header-title-hi">मुख्यमंत्री उद्यमशाला योजना</div>
                    <div class="gov-header-title-en">Mukhyamantri Udyamshala Yojana (MUY)</div>
                    <div class="gov-header-subtitle">Government of Uttarakhand &nbsp;|&nbsp; Rural Business Incubation (RBI)</div>
                    <div class="gov-header-badge">Call For Application — Submission Record</div>
                </div>
                <img src="{{ asset('images/muy.jpg') }}" alt="MUY Logo" class="gov-header-logo">
            </div>

            {{-- APPLICATION NUMBER BAR --}}
            <div class="gov-appno-bar">
                <span>Application No:&nbsp;<span class="appno">{{ $submission->application_no ?? '—' }}</span></span>
                <span class="appmeta">
                    Fiscal Year:&nbsp;<strong>{{ $submission->fiscalYear?->name ?? $submission->fiscalYear?->code ?? '—' }}</strong>
                    &nbsp;|&nbsp; Recorded:&nbsp;<strong>{{ $submission->created_at?->timezone(config('app.timezone'))->format('d M Y, H:i') }} IST</strong>
                    @if ($submittedPayload)&nbsp;|&nbsp; Submitted:&nbsp;<strong>{{ $submittedPayload }}</strong>@endif
                </span>
            </div>

            {{-- Administrative Details --}}
            <div class="gov-sec-head">Administrative Details</div>
            <div class="gov-fields c3">
                <div class="gf">
                    <label>District (Referral)</label>
                    <div class="v">{{ $submission->district?->name ?? '—' }}</div>
                </div>
                <div class="gf">
                    <label>Referral Staff</label>
                    <div class="v">{{ $submission->referralUser?->name ?? $cell('referral_staff_name') }}</div>
                </div>
                <div class="gf">
                    <label>Block</label>
                    <div class="v">{{ $cell('block') }}</div>
                </div>
            </div>

            {{-- Section A: Personal Details --}}
            <div class="gov-sec-head">Section A — Applicant Personal Details</div>

            <div class="gov-fields c3">
                <div class="gf">
                    <label>Applicant Category</label>
                    <div class="v">{{ $cell('category') }}</div>
                </div>
                <div class="gf s2">
                    <label>Full Name of Applicant / SHG / CBO Name</label>
                    <div class="v">{{ $cell('applicant_name') }}@if($cell('shg_cbo_name') !== $dash) &nbsp;/&nbsp; {{ $cell('shg_cbo_name') }}@endif</div>
                </div>
            </div>

            <div class="gov-fields c3">
                <div class="gf s2">
                    <label>Father's / Husband's Name</label>
                    <div class="v">{{ $cell('guardian_name') }}</div>
                </div>
                <div class="gf">
                    <label>Gender</label>
                    <div class="v">{{ $cell('gender') }}</div>
                </div>
            </div>

            <div class="gov-fields c3">
                <div class="gf">
                    <label>Date of Birth / Formation Date</label>
                    <div class="v">{{ $cell('dob') }}</div>
                </div>
                <div class="gf">
                    <label>Social Category (Caste)</label>
                    <div class="v">{{ $cell('caste') }}</div>
                </div>
                <div class="gf">
                    <label>Educational Qualification</label>
                    <div class="v">{{ $cell('education') }}</div>
                </div>
            </div>

            <div class="gov-fields c3">
                <div class="gf">
                    <label>Mobile Number</label>
                    <div class="v">{{ $submission->phone ?: $cell('phone') }}</div>
                </div>
                <div class="gf">
                    <label>Alternate Mobile</label>
                    <div class="v">{{ $cell('alt_mobile') }}</div>
                </div>
                <div class="gf">
                    <label>Email Address</label>
                    <div class="v">{{ $cell('email') }}</div>
                </div>
            </div>

            <div class="gov-fields c3">
                <div class="gf s3">
                    <label>Village / Address</label>
                    <div class="v">{{ $cell('village') }}</div>
                </div>
            </div>

            <div class="gov-fields c3">
                <div class="gf">
                    <label>District (as per form)</label>
                    <div class="v">{{ $cell('district') }}</div>
                </div>
                <div class="gf">
                    <label>Block</label>
                    <div class="v">{{ $cell('block') }}</div>
                </div>
                <div class="gf">
                    <label>PIN Code</label>
                    <div class="v">{{ $cell('pincode') }}</div>
                </div>
            </div>

            {{-- Identity & SHG --}}
            <div class="gov-sec-head">Identity &amp; SHG / CBO Details</div>

            <div class="gov-fields c3">
                <div class="gf">
                    <label>ID Proof Type</label>
                    <div class="v">{{ $cell('id_proof_type') }}</div>
                </div>
                <div class="gf s2">
                    <label>ID Proof Number</label>
                    <div class="v">{{ $cell('id_proof_number') }}</div>
                </div>
            </div>

            <div class="gov-fields c3">
                <div class="gf">
                    <label>Member of SHG / CBO</label>
                    <div class="v">{{ $cell('is_member') }}</div>
                </div>
                <div class="gf">
                    <label>SHG Name (if member)</label>
                    <div class="v">{{ $cell('shg_name') }}</div>
                </div>
                <div class="gf">
                    <label>Lakhpati Didi</label>
                    <div class="v">{{ $cell('lakhpati') }}</div>
                </div>
            </div>

            {{-- Enterprise Registration --}}
            <div class="gov-sec-head">Enterprise &amp; Registration Details</div>

            <div class="gov-fields c3">
                <div class="gf">
                    <label>Enterprise Registered</label>
                    <div class="v">{{ $cell('is_registered') }}</div>
                </div>
                <div class="gf">
                    <label>Type of Registration</label>
                    <div class="v">{{ $regResolved }}</div>
                </div>
                <div class="gf">
                    <label>Registration Number</label>
                    <div class="v">{{ $cell('registration_number') }}</div>
                </div>
            </div>

            <div class="gov-fields c3">
                <div class="gf">
                    <label>Registration Date</label>
                    <div class="v">{{ $cell('registration_date') }}</div>
                </div>
                <div class="gf">
                    <label>Training Received</label>
                    <div class="v">{{ $cell('training_received') }}</div>
                </div>
                <div class="gf">
                    <label>Training Institute</label>
                    <div class="v">{{ $cell('training_institute') }}</div>
                </div>
            </div>

            {{-- Business Details (on Page 1) --}}
            <div class="gov-sec-head">Business Details</div>

            <div class="gov-fields c3">
                <div class="gf">
                    <label>Turnover Last FY (₹)</label>
                    <div class="v">{{ $cell('turnover_last_fy') }}</div>
                </div>
                <div class="gf">
                    <label>Currently Generating Employment</label>
                    <div class="v">{{ $cell('current_employment') }}</div>
                </div>
                <div class="gf">
                    <label>No. of People Employed</label>
                    <div class="v">{{ $cell('employed_count') }}</div>
                </div>
            </div>

            <div class="gov-fields c3">
                <div class="gf">
                    <label>Age of Business</label>
                    <div class="v">{{ $cell('business_age') }}</div>
                </div>
                <div class="gf">
                    <label>Bank Loan Taken</label>
                    <div class="v">{{ $cell('loan_taken') }}</div>
                </div>
                <div class="gf">
                    <label>Bank Loan Amount (₹)</label>
                    <div class="v">{{ $cell('bank_loan') }}</div>
                </div>
            </div>

            <div class="gov-fields c3">
                <div class="gf">
                    <label>Has Regular Marketing Partners</label>
                    <div class="v">{{ $cell('regular_buyer') }}</div>
                </div>
                <div class="gf">
                    <label>No. of Marketing Partners</label>
                    <div class="v">{{ $cell('buyer_count') }}</div>
                </div>
                <div class="gf">
                    <label>Migrated for Employment</label>
                    <div class="v">{{ $cell('migrated_for_employment') }}</div>
                </div>
            </div>

        </div>{{-- end Page 1 --}}

        {{-- ══════════════ PAGE 2 ══════════════ --}}
        <p class="cfa-page-label no-print" style="margin-top:2.5rem;">— Page 2 of 2 —</p>
        <div class="gov-page">

            {{-- Mini-header for page 2 --}}
            <div class="gov-page2-minihead">
                <span>
                    <img src="{{ asset('images/Seal_of_Uttarakhand.svg') }}" style="height:20px;vertical-align:middle;margin-right:5px;" alt="">
                    मुख्यमंत्री उद्यमशाला योजना — CFA
                </span>
                <span>Application No:&nbsp;<strong>{{ $submission->application_no ?? '—' }}</strong></span>
                <span>
                    <img src="{{ asset('images/muy.jpg') }}" style="height:20px;vertical-align:middle;margin-left:5px;" alt="">
                </span>
            </div>

            {{-- Section B --}}
            <div class="gov-sec-head">Section B — Business Stage &amp; Expectations</div>

            <div class="gov-fields c3">
                <div class="gf">
                    <label>Business Stage (Computed)</label>
                    <div class="v">{{ $cell('form_stage') }}</div>
                </div>
                <div class="gf">
                    <label>Criteria Matched</label>
                    <div class="v">{{ $cell('criteria_matched') }}</div>
                </div>
                <div class="gf">
                    <label>Business Category</label>
                    <div class="v">{{ $cell('business_category') }}</div>
                </div>
            </div>

            <div class="gov-fields c3">
                <div class="gf">
                    <label>Product</label>
                    <div class="v">{{ $cell('product') }}</div>
                </div>
                <div class="gf">
                    <label>Other Product (if any)</label>
                    <div class="v">{{ $cell('other_product') }}</div>
                </div>
                <div class="gf">
                    <label>Location Type</label>
                    <div class="v">{{ $cell('location_type') }}</div>
                </div>
            </div>

            <div class="gov-fields c3">
                <div class="gf">
                    <label>Financial Support Needed (Next Year)</label>
                    <div class="v">{{ $cell('financial_support') }}</div>
                </div>
                <div class="gf">
                    <label>Financial Amount Required (₹)</label>
                    <div class="v">{{ $cell('financial_amount') }}</div>
                </div>
                <div class="gf">
                    <label>Preferred Training Mode</label>
                    <div class="v">{{ $cell('training_mode') }}</div>
                </div>
            </div>

            <div class="gov-fields c3">
                <div class="gf">
                    <label>Source of Information</label>
                    <div class="v">{{ $cell('info_source') }}</div>
                </div>
                <div class="gf">
                    <label>Staff / Resource Name</label>
                    <div class="v">{{ $cell('resource_name') }}</div>
                </div>
                <div class="gf">
                    <label>Department Name</label>
                    <div class="v">{{ $cell('department_name') }}</div>
                </div>
            </div>

            <div class="gov-fields c3">
                <div class="gf">
                    <label>Technology Use</label>
                    <div class="v">{{ $cell('techuse') }}</div>
                </div>
                <div class="gf">
                    <label>Environmental Sustainability</label>
                    <div class="v">{{ $cell('sustainability') }}</div>
                </div>
                <div class="gf">
                    <label>Empowers Women / SHGs / Marginalised</label>
                    <div class="v">{{ $cell('empwomen') }}</div>
                </div>
            </div>

            <div class="gov-fields c1">
                <div class="gf">
                    <label>Challenges Faced by Business</label>
                    <div class="v">{{ $challengeText !== '' ? $challengeText : '—' }}</div>
                </div>
            </div>

            <div class="gov-fields c1">
                <div class="gf">
                    <label>Expectations from MUY / RBI</label>
                    <div class="v">{{ $expectationText !== '' ? $expectationText : '—' }}</div>
                </div>
            </div>

            @if ($cell('expectation_other_text') !== $dash)
            <div class="gov-fields c1">
                <div class="gf">
                    <label>If Other Expectation — Please Specify</label>
                    <div class="v">{{ $cell('expectation_other_text') }}</div>
                </div>
            </div>
                @endif

            <div class="gov-fields c1">
                <div class="gf">
                    <label>Business Vision (Next 5 Years)</label>
                    <div class="v tall">{{ $cell('business_vision') }}</div>
                </div>
            </div>

            @if ($stageLines !== [])
            <div class="gov-fields c1" style="border-bottom:none;">
                <div class="gf">
                    <label>Stage Logic (Audit Trail)</label>
                    <div class="v">{{ implode(' | ', $stageLines) }}</div>
                </div>
            </div>
            @endif

            {{-- Declaration --}}
            <div class="gov-declaration">
                <strong>Declaration &amp; Consent:</strong>
                I hereby declare that all information provided in this application is true, complete and correct to the best of my knowledge and belief.
                I consent to the use of this data for the purposes of the Mukhyamantri Udyamshala Yojana scheme.
                &nbsp;&mdash;&nbsp;
                <strong>Consent confirmed by applicant:</strong>
                {{ ($p['consent'] ?? false) ? '&#10003; Yes — Applicant confirmed accuracy and consent.' : 'Not confirmed' }}
            </div>

            {{-- Signatures --}}
            <div class="gov-sig-row">
                <div class="gov-sig-box">Signature / Thumb Impression of Applicant</div>
                <div class="gov-sig-box">
                    Signature of Referral Staff<br>
                    <span style="font-size:7pt;">{{ $submission->referralUser?->name ?? '—' }}</span>
                </div>
                <div class="gov-sig-box">Office Stamp &amp; Authorised Signature</div>
            </div>

            {{-- Office use only --}}
            <div class="gov-office-use">
                <div class="gov-office-title">For Office Use Only</div>
                <div class="gov-fields c3" style="border:none;padding:2px 0 0;">
                    <div class="gf"><label>Received By</label><div class="v">&nbsp;</div></div>
                    <div class="gf"><label>Verified By</label><div class="v">&nbsp;</div></div>
                    <div class="gf"><label>Approved By</label><div class="v">&nbsp;</div></div>
                </div>
            </div>

        </div>{{-- end Page 2 --}}

    </div>{{-- end .cfa-preview-board --}}

    {{-- ── Legacy service case assignment (feature-flagged) ── --}}
    @if (config('features.service_case_assignment'))
        @isset($serviceCasesUi)
            @include('partials.incubatee-service-cases', ['serviceCasesUi' => $serviceCasesUi])
        @endisset
    @endif

    {{-- ── Edit history (screen only) ── --}}
        @if ($cfaEditLogs->isNotEmpty())
        <div class="cfa-edit-history no-print">
            <h3 style="font-size:.95rem;font-weight:700;color:#db2627;border-bottom:2px solid #db2627;padding-bottom:.35rem;margin-bottom:.75rem;">
                Edit History (Staff Portal)
            </h3>
            <p style="font-size:.8rem;color:#64748b;margin-bottom:.75rem;">
                Each row is one <strong>Save</strong> after an <strong>Edit</strong> on the staff portal.
                The <strong>What changed</strong> list uses everyday labels (not computer field names).
                State admins also have the full <em>Audit log</em>.
            </p>
                <table class="cfa-edit-log-table">
                    <thead>
                        <tr>
                            <th>When (IST)</th>
                            <th>Edited by</th>
                            <th>What changed (plain language)</th>
                        </tr>
                    </thead>
                    <tbody>
                        @foreach ($cfaEditLogs as $log)
                            <tr>
                            <td style="white-space:nowrap;vertical-align:top;">
                                {{ $log->created_at?->timezone(config('app.timezone'))->format('d M Y, H:i') }}
                            </td>
                            <td style="vertical-align:top;">
                                    {{ $log->user?->name ?? '—' }}
                                    @if ($log->user?->email)
                                    <br><span style="font-size:.72rem;color:#64748b;">{{ $log->user->email }}</span>
                                    @endif
                                </td>
                            <td style="vertical-align:top;">
                                    @php
                                        $diffLines = \App\Services\CfaSubmissionAuditSnapshot::humanDiffLines($log->before ?? [], $log->after ?? []);
                                    @endphp
                                    @if (count($diffLines) > 0)
                                    <ul style="margin:0;padding-left:1.15rem;font-size:.82rem;line-height:1.45;color:#334155;">
                                            @foreach ($diffLines as $line)
                                            <li style="margin-bottom:.25rem;">{{ $line }}</li>
                                            @endforeach
                                        </ul>
                                    @elseif ($log->description && ! \Illuminate\Support\Str::contains(strtolower((string) $log->description), 'json'))
                                    <div style="font-size:.78rem;color:#334155;">{{ $log->description }}</div>
                                    @else
                                    <span style="font-size:.78rem;color:#64748b;">Save recorded. If nothing is listed above, either the tracked fields did not change or this is an older log entry — open <em>Raw data</em> only if your IT team needs it.</span>
                                    @endif
                                    @if (($log->before && count($log->before)) || ($log->after && count($log->after)))
                                    <details style="margin-top:.5rem;font-size:.72rem;">
                                            <summary style="cursor:pointer;color:#64748b;">Raw data (technical only)</summary>
                                        <pre style="margin:.35rem 0 0;white-space:pre-wrap;word-break:break-word;background:#f8fafc;padding:.45rem;border-radius:6px;border:1px solid #e2e8f0;max-height:10rem;overflow:auto;">@if ($log->before && count($log->before))<strong>before</strong>
{{ json_encode($log->before, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE) }}

@endif
@if ($log->after && count($log->after))<strong>after</strong>
{{ json_encode($log->after, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE) }}
@endif</pre>
                                        </details>
                                    @endif
                                </td>
                            </tr>
                        @endforeach
                    </tbody>
                </table>
        </div>
        @endif

</div>
@endsection
