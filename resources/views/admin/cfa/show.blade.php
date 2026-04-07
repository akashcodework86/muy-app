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
    .cfa-print-wrap { max-width: 56rem; }
    .cfa-print-actions { display: flex; flex-wrap: wrap; gap: 0.75rem; align-items: center; margin-bottom: 1.25rem; }
    .cfa-print-actions a { color: #3b82f6; text-decoration: none; font-weight: 600; }
    .cfa-print-actions a:hover { text-decoration: underline; }
    .cfa-print-actions button {
        padding: 0.45rem 1rem; border: none; border-radius: 8px; background: #18181b; color: #fff;
        font-weight: 600; font-size: 0.875rem; cursor: pointer; font-family: inherit;
    }
    .cfa-print-actions button:hover { background: #27272a; }
    .cfa-print-doc {
        background: #fff; border: 1px solid #e4e4e7; border-radius: 8px; padding: 1.5rem 1.75rem;
        font-size: 0.875rem; line-height: 1.45; color: #18181b;
    }
    .cfa-print-doc h1 { font-size: 1.15rem; margin: 0 0 0.25rem; }
    .cfa-print-doc .cfa-print-sub { color: #64748b; font-size: 0.8rem; margin-bottom: 1.25rem; }
    .cfa-print-section { margin-top: 1.35rem; }
    .cfa-print-section h2 {
        font-size: 0.95rem; text-transform: uppercase; letter-spacing: 0.04em; color: #475569;
        border-bottom: 1px solid #e2e8f0; padding-bottom: 0.35rem; margin: 0 0 0.75rem;
    }
    .cfa-print-grid { display: grid; grid-template-columns: minmax(9rem, 32%) 1fr; gap: 0.35rem 1.25rem; }
    .cfa-print-grid dt { color: #64748b; font-weight: 500; margin: 0; }
    .cfa-print-grid dd { margin: 0; word-break: break-word; }
    .cfa-print-grid .full { grid-column: 1 / -1; }
    .cfa-print-note { margin-top: 0.5rem; padding: 0.65rem 0.85rem; background: #f8fafc; border-radius: 6px; font-size: 0.8rem; color: #475569; }
    .cfa-print-list { margin: 0.25rem 0 0; padding-left: 1.15rem; }
    .cfa-print-list li { margin-bottom: 0.2rem; }
    .cfa-edit-log-table { width: 100%; border-collapse: collapse; font-size: 0.8rem; margin-top: 0.5rem; }
    .cfa-edit-log-table th, .cfa-edit-log-table td { border: 1px solid #e2e8f0; padding: 0.45rem 0.55rem; text-align: left; vertical-align: top; }
    .cfa-edit-log-table th { background: #f8fafc; color: #475569; font-weight: 600; }
    .cfa-edit-log-summary { font-size: 0.78rem; color: #334155; line-height: 1.4; word-break: break-word; }
    @media print {
        body.admin-app-body { background: #fff; }
        .admin-topbar, .admin-page-head, .cfa-print-actions, .no-print-col { display: none !important; }
        .admin-main { padding: 0 !important; max-width: 100% !important; }
        .cfa-print-doc { border: none; border-radius: 0; padding: 0; box-shadow: none; }
        .cfa-print-wrap { max-width: 100%; }
    }
</style>
@endpush

@section('content')
@php
    $cfaIndexUrl = $cfaIndexUrl ?? route('admin.cfa.index');
    $cfaEditUrl = $cfaEditUrl ?? null;
    $cfaEditLogs = $cfaEditLogs ?? collect();
@endphp
@if (session('status'))
    <div class="no-print" style="margin-bottom:1rem;padding:0.75rem 1rem;background:#ecfdf5;border:1px solid #6ee7b7;border-radius:8px;color:#065f46;font-size:0.9rem;">
        {{ session('status') }}
    </div>
@endif
<div class="cfa-print-wrap">
    <div class="cfa-print-actions no-print">
        <a href="{{ $cfaIndexUrl }}">← Back to list</a>
        @if (! empty($cfaEditUrl))
            <a href="{{ $cfaEditUrl }}" style="padding:0.45rem 1rem;border-radius:8px;background:#4f46e5;color:#fff;text-decoration:none;font-weight:600;">Edit</a>
        @endif
        <button type="button" onclick="window.print()">Print / PDF</button>
    </div>

    <article class="cfa-print-doc">
        <h1>मुख्यमंत्री उद्यमशाला योजना — Call For Application</h1>
        <p class="cfa-print-sub">Complete submission record (as filed online). Times in IST.</p>

        <section class="cfa-print-section">
            <h2>Application metadata</h2>
            <dl class="cfa-print-grid">
                <dt>Application no.</dt><dd>{{ $submission->application_no ?? $dash }}</dd>
                <dt>Recorded on</dt><dd>{{ $submission->created_at?->timezone(config('app.timezone'))->format('d M Y, H:i') }} IST</dd>
                @if($submittedPayload)
                    <dt>Client submitted at</dt><dd>{{ $submittedPayload }}</dd>
                @endif
                <dt>Fiscal year</dt><dd>{{ $submission->fiscalYear?->name ?? $submission->fiscalYear?->code ?? $dash }}</dd>
                <dt>District (referral)</dt><dd>{{ $submission->district?->name ?? $dash }}</dd>
                <dt>LGD (state / dist / block)</dt>
                <dd>{{ $submission->lgd_state_code ?? $dash }} / {{ $submission->lgd_district_code ?? $dash }} / {{ $submission->lgd_block_code ?? $dash }}</dd>
                <dt>Referral staff (record)</dt><dd>{{ $submission->referralUser?->name ?? $dash }}@if($submission->referralUser?->email) ({{ $submission->referralUser->email }})@endif</dd>
                <dt>Referral staff (form snapshot)</dt><dd>{{ $cell('referral_staff_name') }} @if($cell('referral_staff_email') !== $dash) — {{ $cell('referral_staff_email') }} @endif</dd>
            </dl>
        </section>

        <section class="cfa-print-section">
            <h2>Section A — Applicant details</h2>
            <dl class="cfa-print-grid">
                <dt>Applicant category</dt><dd>{{ $cell('category') }}</dd>
                <dt>Full name</dt><dd>{{ $cell('applicant_name') }}</dd>
                <dt>SHG / CBO name</dt><dd>{{ $cell('shg_cbo_name') }}</dd>
                <dt>Father / husband name</dt><dd>{{ $cell('guardian_name') }}</dd>
                <dt>Gender</dt><dd>{{ $cell('gender') }}</dd>
                <dt>DOB / formation date</dt><dd>{{ $cell('dob') }}</dd>
                <dt>Social category</dt><dd>{{ $cell('caste') }}</dd>
                <dt>Mobile</dt><dd>{{ $submission->phone ?: $cell('phone') }}</dd>
                <dt>Alternate mobile</dt><dd>{{ $cell('alt_mobile') }}</dd>
                <dt>Email</dt><dd>{{ $cell('email') }}</dd>
                <dt>Village / address</dt><dd>{{ $cell('village') }}</dd>
                <dt>District (form)</dt><dd>{{ $cell('district') }}</dd>
                <dt>Block</dt><dd>{{ $cell('block') }}</dd>
                <dt>PIN code</dt><dd>{{ $cell('pincode') }}</dd>
                <dt>Education</dt><dd>{{ $cell('education') }}</dd>
                <dt>ID proof type</dt><dd>{{ $cell('id_proof_type') }}</dd>
                <dt>ID proof number</dt><dd>{{ $cell('id_proof_number') }}</dd>
                <dt>Member of SHG/CBO</dt><dd>{{ $cell('is_member') }}</dd>
                <dt>SHG name (if member)</dt><dd>{{ $cell('shg_name') }}</dd>
                <dt>Lakhpati Didi</dt><dd>{{ $cell('lakhpati') }}</dd>
                <dt>Enterprise registered</dt><dd>{{ $cell('is_registered') }}</dd>
                <dt>Type of registration</dt><dd>{{ $regResolved }}</dd>
                <dt>Registration number</dt><dd>{{ $cell('registration_number') }}</dd>
                <dt>Registration date</dt><dd>{{ $cell('registration_date') }}</dd>
                <dt>Training received</dt><dd>{{ $cell('training_received') }}</dd>
                <dt>Training institute</dt><dd>{{ $cell('training_institute') }}</dd>
                <dt>Turnover last FY (₹)</dt><dd>{{ $cell('turnover_last_fy') }}</dd>
                <dt>Currently generating employment</dt><dd>{{ $cell('current_employment') }}</dd>
                <dt>People employed</dt><dd>{{ $cell('employed_count') }}</dd>
                <dt>Age of business</dt><dd>{{ $cell('business_age') }}</dd>
                <dt>Bank loan taken</dt><dd>{{ $cell('loan_taken') }}</dd>
                <dt>Bank loan amount</dt><dd>{{ $cell('bank_loan') }}</dd>
                <dt>Marketing partners</dt><dd>{{ $cell('regular_buyer') }}</dd>
                <dt>Number of partners</dt><dd>{{ $cell('buyer_count') }}</dd>
            </dl>
        </section>

        <section class="cfa-print-section">
            <h2>Section B — Business &amp; expectations</h2>
            <dl class="cfa-print-grid">
                <dt>Business stage (computed)</dt><dd>{{ $cell('form_stage') }}</dd>
                <dt>Criteria matched</dt><dd>{{ $cell('criteria_matched') }}</dd>
                <dt>Business category</dt><dd>{{ $cell('business_category') }}</dd>
                <dt>Product</dt><dd>{{ $cell('product') }}</dd>
                <dt>Other product</dt><dd>{{ $cell('other_product') }}</dd>
                <dt>Financial support next year</dt><dd>{{ $cell('financial_support') }}</dd>
                <dt>Financial amount</dt><dd>{{ $cell('financial_amount') }}</dd>
                <dt>Location type</dt><dd>{{ $cell('location_type') }}</dd>
                <dt class="full">Challenges faced</dt>
                <dd class="full">{{ $challengeText !== '' ? $challengeText : $dash }}</dd>
                <dt>Migrated for employment</dt><dd>{{ $cell('migrated_for_employment') }}</dd>
                <dt class="full">Business vision (5 years)</dt>
                <dd class="full" style="white-space: pre-wrap;">{{ $cell('business_vision') }}</dd>
                <dt>Preferred training mode</dt><dd>{{ $cell('training_mode') }}</dd>
                <dt>Source of information</dt><dd>{{ $cell('info_source') }}</dd>
                <dt>Staff / resource name</dt><dd>{{ $cell('resource_name') }}</dd>
                <dt>Department name</dt><dd>{{ $cell('department_name') }}</dd>
                <dt>Technology use</dt><dd>{{ $cell('techuse') }}</dd>
                <dt>Environmental sustainability</dt><dd>{{ $cell('sustainability') }}</dd>
                <dt>Supports/empowers women, SHGs, or marginalized communities</dt><dd>{{ $cell('empwomen') }}</dd>
                <dt class="full">Expectations from MUY / RBI</dt>
                <dd class="full">{{ $expectationText !== '' ? $expectationText : $dash }}</dd>
                <dt>If Other — specify</dt><dd>{{ $cell('expectation_other_text') }}</dd>
                <dt>Declaration &amp; consent</dt><dd>{{ ($p['consent'] ?? false) ? 'Yes — applicant confirmed accuracy and consent.' : $dash }}</dd>
            </dl>
            @if ($stageLines !== [])
                <p class="cfa-print-note"><strong>Stage logic (audit trail)</strong></p>
                <ul class="cfa-print-list">
                    @foreach ($stageLines as $line)
                        <li>{{ $line }}</li>
                    @endforeach
                </ul>
            @endif
        </section>

        @if ($cfaEditLogs->isNotEmpty())
            <section class="cfa-print-section">
                <h2>Edit history (staff portal)</h2>
                <p class="cfa-print-sub" style="margin-bottom:0.75rem;">Each row is one <strong>Save</strong> after <strong>Edit</strong> on the staff portal. The <strong>What changed</strong> list uses everyday labels (not computer field names). Optional raw data is folded below for technical review; state admins also have the full <em>Audit log</em>.</p>
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
                                <td style="white-space:nowrap;">{{ $log->created_at?->timezone(config('app.timezone'))->format('d M Y, H:i') }}</td>
                                <td>
                                    {{ $log->user?->name ?? '—' }}
                                    @if ($log->user?->email)
                                        <br><span style="font-size:0.72rem;color:#64748b;">{{ $log->user->email }}</span>
                                    @endif
                                </td>
                                <td>
                                    @php
                                        $diffLines = \App\Services\CfaSubmissionAuditSnapshot::humanDiffLines($log->before ?? [], $log->after ?? []);
                                    @endphp
                                    @if (count($diffLines) > 0)
                                        <ul class="cfa-edit-log-list" style="margin:0;padding-left:1.15rem;font-size:0.82rem;line-height:1.45;color:#334155;">
                                            @foreach ($diffLines as $line)
                                                <li style="margin-bottom:0.25rem;">{{ $line }}</li>
                                            @endforeach
                                        </ul>
                                    @elseif ($log->description && ! \Illuminate\Support\Str::contains(strtolower((string) $log->description), 'json'))
                                        <div class="cfa-edit-log-summary">{{ $log->description }}</div>
                                    @else
                                        <span style="font-size:0.82rem;color:#64748b;">Save recorded. If nothing is listed above, either the tracked fields did not change or this is an older log entry—open <em>Raw data</em> only if your IT team needs it.</span>
                                    @endif
                                    @if (($log->before && count($log->before)) || ($log->after && count($log->after)))
                                        <details class="no-print" style="margin-top:0.5rem;font-size:0.72rem;">
                                            <summary style="cursor:pointer;color:#64748b;">Raw data (technical only)</summary>
                                            <pre style="margin:0.35rem 0 0;white-space:pre-wrap;word-break:break-word;background:#f8fafc;padding:0.45rem;border-radius:6px;border:1px solid #e2e8f0;max-height:10rem;overflow:auto;">@if ($log->before && count($log->before))<strong>before</strong>
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
            </section>
        @endif
    </article>
</div>
@endsection
