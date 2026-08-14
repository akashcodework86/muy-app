@extends('layouts.admin')

@section('title', 'Survey response #'.$response->id)
@section('heading', 'Homestay Survey Response')

@push('styles')
<style>
    .hs-show {
        --hs-brand: #26a69a;
        --hs-brand-deep: #00897b;
        --hs-brand-light: #e0f2f1;
        --hs-border: #e8ecf1;
        --hs-muted: #78909c;
        --hs-navy: #263238;
        font-family: 'DM Sans', system-ui, sans-serif;
        max-width: 920px;
        color: #37474f;
    }
    .hs-show__meta {
        display: flex;
        flex-wrap: wrap;
        gap: .5rem 1rem;
        margin-bottom: 1rem;
        padding: 1rem;
        background: #fff;
        border: 1px solid var(--hs-border);
        border-radius: 16px;
        font-size: .88rem;
        box-shadow: 0 2px 12px rgba(55, 71, 79, .05);
    }
    .hs-show__meta strong {
        display: block;
        font-size: .68rem;
        text-transform: uppercase;
        letter-spacing: .05em;
        color: var(--hs-muted);
    }
    .hs-show__card {
        background: #fff;
        border: 1px solid var(--hs-border);
        border-radius: 16px;
        padding: 1rem 1.1rem;
        margin-bottom: 1rem;
        box-shadow: 0 2px 12px rgba(55, 71, 79, .05);
    }
    .hs-show__card h2 {
        margin: 0 0 .75rem;
        font-size: 1rem;
        color: var(--hs-brand-deep);
    }
    .hs-show__dl {
        display: grid;
        grid-template-columns: minmax(140px, 220px) 1fr;
        gap: .35rem .75rem;
        font-size: .88rem;
    }
    .hs-show__dl dt { color: var(--hs-muted); }
    .hs-show__dl dd { margin: 0; font-weight: 600; color: var(--hs-navy); word-break: break-word; }
    .hs-show__back {
        display: inline-block;
        margin-bottom: 1rem;
        color: var(--hs-brand-deep);
        font-weight: 700;
        text-decoration: none;
        font-size: .9rem;
    }
</style>
@endpush

@section('content')
@php
    $a = is_array($response->answers) ? $response->answers : [];
    $fmt = function ($v) {
        if (is_array($v)) {
            $flat = [];
            foreach ($v as $k => $item) {
                if (is_int($k)) {
                    $flat[] = (string) $item;
                } elseif ($item !== null && $item !== '') {
                    $flat[] = $k.': '.$item;
                }
            }
            return $flat === [] ? '—' : implode('; ', $flat);
        }
        if ($v === null || $v === '') return '—';
        if (is_bool($v)) return $v ? 'Yes' : 'No';
        return (string) $v;
    };
    $rows = [
        'Respondent name' => $a['respondent_name'] ?? null,
        'Gender' => $a['gender'] ?? null,
        'Age group' => $a['age_group'] ?? null,
        'Caste' => $a['caste'] ?? null,
        'Enterprise' => $a['enterprise_name'] ?? null,
        'District' => $a['district'] ?? null,
        'Block' => $a['block'] ?? null,
        'Village' => $a['village'] ?? null,
        'Pin' => $a['pincode'] ?? null,
        'Location type' => $a['location_type'] ?? null,
        'Phone' => $response->phone,
        'Email' => $a['email'] ?? null,
        'Website' => $a['website'] ?? null,
        'Role' => trim(($a['role'] ?? '').' '.($a['role_other'] ?? '')),
        'Enrolment year' => $a['enrolment_year'] ?? null,
        'Info source' => $a['info_source'] ?? null,
        'Incubation center' => $a['incubation_center'] ?? null,
        'Venture type' => $a['venture_type'] ?? null,
        'Stage at enrolment' => $a['stage_at_enrolment'] ?? null,
        'UTDB registered' => $a['utdb_registered'] ?? null,
        'UTDB number' => $a['utdb_reg_number'] ?? null,
        'Rooms' => trim(($a['room_count'] ?? '').' '.($a['room_count_other'] ?? '')),
        'Homestay type' => $a['homestay_type'] ?? null,
        'Facilities' => $a['facilities'] ?? null,
        'Peak season' => $a['peak_season'] ?? null,
        'Tariff' => $a['tariff'] ?? null,
        'Initial investment' => $a['initial_investment'] ?? null,
        'Funding sources' => $a['funding_sources'] ?? null,
        'MUY financial assistance' => $a['muy_financial_assistance'] ?? null,
        'MUY amount / year' => trim(($a['muy_financial_amount'] ?? '').' / '.($a['muy_financial_year'] ?? '')),
        'Bank loan MUY' => $a['bank_loan_muy'] ?? null,
        'Loan amount / subvention' => trim(($a['bank_loan_amount'] ?? '').' / '.($a['interest_subvention'] ?? '')),
        'Revenue status' => $a['revenue_status'] ?? null,
        'Revenue during / current' => trim(($a['revenue_during'] ?? '').' / '.($a['revenue_current'] ?? '')),
        'Occupancy during / current' => trim(($a['occupancy_during'] ?? '').' / '.($a['occupancy_current'] ?? '')),
        'Guests during / current' => trim(($a['guests_during'] ?? '').' / '.($a['guests_current'] ?? '')),
        'Employed Q31 during / current' => trim(($a['employed_count_during_q31'] ?? '').' / '.($a['employed_count_current_q31'] ?? '')),
        'Other income' => $a['other_income'] ?? null,
        'Occupancy band' => $a['occupancy_band'] ?? null,
        'Booking sources' => $a['booking_sources'] ?? null,
        'Listed OTA' => $a['listed_ota'] ?? null,
        'OTA platforms' => $a['ota_platforms'] ?? null,
        'Tourism linkage' => $a['tourism_linkage'] ?? null,
        'Employed during / current' => trim(($a['employed_during'] ?? '').' / '.($a['employed_current'] ?? '')),
        'Women/Youth/Local during' => trim(($a['women_during'] ?? '').' / '.($a['youth_during'] ?? '').' / '.($a['local_during'] ?? '')),
        'Women/Youth/Local current' => trim(($a['women_current'] ?? '').' / '.($a['youth_current'] ?? '').' / '.($a['local_current'] ?? '')),
        'Local sourcing' => $a['local_sourcing'] ?? null,
        'Encouraged others' => $a['encouraged_others'] ?? null,
        'Support services' => $a['support_services'] ?? null,
        'Training usefulness' => $a['training_usefulness'] ?? null,
        'Follow-up' => $a['followup_frequency'] ?? null,
        'Certification' => trim(($a['certification'] ?? '').' '.($a['certification_detail'] ?? '')),
        'Challenge ranks' => $a['challenge_ranks'] ?? null,
        'COVID impact' => trim(($a['covid_impact'] ?? '').' '.($a['covid_recovery'] ?? '')),
        'Digital support' => $a['digital_support'] ?? null,
        'Digital comfort' => $a['digital_comfort'] ?? null,
        'Progress rating' => $a['progress_rating'] ?? null,
        'Income confidence' => $a['income_confidence'] ?? null,
        'Recommend MUY' => $a['recommend_muy'] ?? null,
        'Expansion plans' => $a['expansion_plans'] ?? null,
        'Future support ranks' => $a['future_support'] ?? null,
        'Willing to take MUY acceleration support services' => $a['acceleration_support'] ?? ($a['other_support'] ?? null),
        'Consent' => !empty($a['consent']) ? 'Yes' : 'No',
    ];
@endphp

<div class="hs-show">
    <a class="hs-show__back" href="{{ route('admin.homestay-survey.index') }}">← Back to list</a>

    <div class="hs-show__meta">
        <div><strong>ID</strong>#{{ $response->id }}</div>
        <div><strong>Submitted</strong>{{ optional($response->submitted_at)->timezone(config('app.timezone'))->format('d M Y, g:i A') }}</div>
        <div><strong>Phase</strong>{{ $response->phase ?: '—' }}</div>
        <div><strong>Application no</strong>{{ $response->application_no ?: '—' }}</div>
        <div><strong>Source ID</strong>{{ $response->source_id ?: '—' }}</div>
    </div>

    <div class="hs-show__card">
        <h2>Answers</h2>
        <dl class="hs-show__dl">
            @foreach ($rows as $label => $value)
                <dt>{{ $label }}</dt>
                <dd>{{ $fmt($value) }}</dd>
            @endforeach
        </dl>
    </div>
</div>
@endsection
