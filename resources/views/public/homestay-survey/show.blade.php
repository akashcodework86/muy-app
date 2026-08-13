@extends('public.homestay-survey.layout')

@section('title', 'MUY Homestay Progress Survey')

@section('content')
@php
    $muyLogo = 'https://ukrbi.in/new/admin/muy.png';
@endphp
<header class="hs-header">
    <div class="hs-header__inner">
        <img class="hs-header__logo" src="{{ $muyLogo }}" alt="MUY logo" width="48" height="48">
        <div class="hs-header__text">
            <h1>Homestay Progress Survey</h1>
            <p>Mukhyamantri Udyamshala Yojana</p>
        </div>
    </div>
</header>

<div class="hs-wrap">
    @if ($errors->any())
        <div class="hs-alert hs-alert--error" role="alert">
            {{ $errors->first() }}
        </div>
    @endif

    {{-- Step 1: phone lookup --}}
    <div class="hs-card hs-card--lookup" id="hs-lookup-card">
        <div class="hs-section__head">
            <span class="hs-section__letter">1</span>
            <div>
                <h2>Enter mobile number</h2>
            </div>
        </div>
        <div class="hs-phone-row">
            <div class="hs-field">
                <label class="hs-label" for="lookup-phone">Contact number <span class="hs-req">*</span></label>
                <input class="hs-input" id="lookup-phone" type="tel" inputmode="numeric" maxlength="10" placeholder="10-digit mobile" autocomplete="tel" value="{{ old('phone') }}">
            </div>
            <button type="button" class="hs-btn hs-btn--lg" id="hs-lookup-btn">Continue</button>
        </div>
        <div id="hs-lookup-msg" class="hs-alert hs-hidden" style="margin-top:.85rem;" role="status"></div>
    </div>

    {{-- Step 2: full form (hidden until lookup ok) --}}
    <form method="post" action="{{ route('homestay-survey.store') }}" id="hs-survey-form" class="hs-hidden" novalidate>
        @csrf
        <input type="hidden" name="phone" id="form-phone" value="{{ old('phone') }}">
        <input type="hidden" name="answers[phase]" id="form-phase" value="">
        <input type="hidden" name="answers[source_id]" id="form-source-id" value="">
        <input type="hidden" name="answers[application_no]" id="form-application-no" value="">

        <div class="hs-progress hs-hidden" id="hs-progress">
            <div class="hs-progress__row">
                <span id="hs-progress-label">Progress</span>
                <span id="hs-progress-pct">0%</span>
            </div>
            <div class="hs-progress__track" aria-hidden="true">
                <div class="hs-progress__bar" id="hs-progress-bar"></div>
            </div>
        </div>

        <div class="hs-card hs-card--match" id="hs-profile-banner">
            <dl class="hs-match-list" id="hs-match-list">
                <div class="hs-match-row"><dt>Name</dt><dd id="hs-sum-name">—</dd></div>
                <div class="hs-match-row"><dt>Application no.</dt><dd id="hs-sum-app">—</dd></div>
                <div class="hs-match-row"><dt>District</dt><dd id="hs-sum-district">—</dd></div>
                <div class="hs-match-row"><dt>Block</dt><dd id="hs-sum-block">—</dd></div>
                <div class="hs-match-row"><dt>Sector</dt><dd id="hs-sum-sector">—</dd></div>
                <div class="hs-match-row"><dt>Product</dt><dd id="hs-sum-product">—</dd></div>
            </dl>
        </div>

        {{-- A --}}
        <div class="hs-card hs-section" data-section="A">
            <div class="hs-section__head">
                <span class="hs-section__letter">A</span>
                <div>
                    <h2>Respondent &amp; Enterprise</h2>
                </div>
            </div>
            <div class="hs-grid-2">
                <div class="hs-field">
                    <label class="hs-label" for="respondent_name">1. Name of respondent <span class="hs-req">*</span></label>
                    <input class="hs-input hs-prefill" data-prefill="respondent_name" id="respondent_name" name="answers[respondent_name]" type="text" required value="{{ old('answers.respondent_name') }}">
                </div>
                <div class="hs-field">
                    <label class="hs-label">2. Gender <span class="hs-req">*</span></label>
                    @include('public.homestay-survey.partials.options', ['name' => 'gender', 'items' => $options['genders'], 'type' => 'radio'])
                </div>
            </div>
            <div class="hs-field">
                <label class="hs-label">3. Age group</label>
                @include('public.homestay-survey.partials.options', ['name' => 'age_group', 'items' => $options['age_groups'], 'type' => 'radio'])
            </div>
            <div class="hs-field">
                <label class="hs-label">4. Category (caste)</label>
                @include('public.homestay-survey.partials.options', ['name' => 'caste', 'items' => $options['castes'], 'type' => 'radio'])
            </div>
            <div class="hs-field">
                <label class="hs-label" for="enterprise_name">5. Name of homestay / enterprise <span class="hs-req">*</span></label>
                <input class="hs-input hs-prefill" data-prefill="enterprise_name" id="enterprise_name" name="answers[enterprise_name]" type="text" required value="{{ old('answers.enterprise_name') }}">
            </div>
            <div class="hs-grid-2">
                <div class="hs-field">
                    <label class="hs-label" for="district">6. District</label>
                    <input class="hs-input hs-prefill" data-prefill="district" id="district" name="answers[district]" type="text" value="{{ old('answers.district') }}">
                </div>
                <div class="hs-field">
                    <label class="hs-label" for="block">7. Block</label>
                    <input class="hs-input hs-prefill" data-prefill="block" id="block" name="answers[block]" type="text" value="{{ old('answers.block') }}">
                </div>
                <div class="hs-field">
                    <label class="hs-label" for="village">8. Village / Town</label>
                    <input class="hs-input hs-prefill" data-prefill="village" id="village" name="answers[village]" type="text" value="{{ old('answers.village') }}">
                </div>
                <div class="hs-field">
                    <label class="hs-label" for="pincode">9. Pin</label>
                    <input class="hs-input hs-prefill" data-prefill="pincode" id="pincode" name="answers[pincode]" type="text" maxlength="6" value="{{ old('answers.pincode') }}">
                </div>
            </div>
            <div class="hs-field">
                <label class="hs-label">10. Is the location</label>
                @include('public.homestay-survey.partials.options', ['name' => 'location_type', 'items' => $options['location_types'], 'type' => 'radio'])
            </div>
            <div class="hs-grid-2">
                <div class="hs-field">
                    <label class="hs-label" for="display_phone">11. Contact number</label>
                    <input class="hs-input" id="display_phone" type="text" readonly value="{{ old('phone') }}">
                </div>
                <div class="hs-field">
                    <label class="hs-label" for="email">12. Email (if any)</label>
                    <input class="hs-input hs-prefill" data-prefill="email" id="email" name="answers[email]" type="email" value="{{ old('answers.email') }}">
                </div>
            </div>
            <div class="hs-grid-2">
                <div class="hs-field">
                    <label class="hs-label" for="website">13. Website (if any)</label>
                    <input class="hs-input" id="website" name="answers[website]" type="text" value="{{ old('answers.website') }}">
                </div>
                <div class="hs-field">
                    <label class="hs-label">14. Role in the enterprise</label>
                    @include('public.homestay-survey.partials.options', ['name' => 'role', 'items' => $options['roles'], 'type' => 'radio'])
                    <input class="hs-input" style="margin-top:.45rem;" name="answers[role_other]" type="text" placeholder="If Other, specify" value="{{ old('answers.role_other') }}">
                </div>
            </div>
        </div>

        {{-- B --}}
        <div class="hs-card hs-section" data-section="B">
            <div class="hs-section__head">
                <span class="hs-section__letter">B</span>
                <div>
                    <h2>MUY Association</h2>
                </div>
            </div>
            <div class="hs-field">
                <label class="hs-label">15. Year of enrolment / incubation under MUY</label>
                @include('public.homestay-survey.partials.options', ['name' => 'enrolment_year', 'items' => $options['enrolment_years'], 'type' => 'radio'])
            </div>
            <div class="hs-field">
                <label class="hs-label">16. How did you learn about MUY? (multiple allowed)</label>
                @include('public.homestay-survey.partials.options', ['name' => 'info_source', 'items' => $options['info_sources'], 'type' => 'checkbox'])
                <input class="hs-input" style="margin-top:.45rem;" name="answers[info_source_other]" type="text" placeholder="If Other, specify" value="{{ old('answers.info_source_other') }}">
            </div>
            <div class="hs-field">
                <label class="hs-label" for="incubation_center">17. Which incubation center / implementing partner supported you?</label>
                <input class="hs-input hs-prefill" data-prefill="incubation_center" id="incubation_center" name="answers[incubation_center]" type="text" value="{{ old('answers.incubation_center') }}">
            </div>
            <div class="hs-field">
                <label class="hs-label">18. New venture under MUY or existing that scaled up?</label>
                @include('public.homestay-survey.partials.options', ['name' => 'venture_type', 'items' => $options['venture_types'], 'type' => 'radio'])
            </div>
            <div class="hs-field">
                <label class="hs-label">19. Stage at enrolment</label>
                @include('public.homestay-survey.partials.options', ['name' => 'stage_at_enrolment', 'items' => $options['stages'], 'type' => 'radio'])
            </div>
            <div class="hs-field">
                <label class="hs-label">20. Registered under Uttarakhand Homestay Policy (UTDB)?</label>
                @include('public.homestay-survey.partials.options', ['name' => 'utdb_registered', 'items' => $options['yes_no_process'], 'type' => 'radio'])
                <input class="hs-input hs-prefill" data-prefill="utdb_reg_number" style="margin-top:.45rem;" name="answers[utdb_reg_number]" type="text" placeholder="Registration / certificate number (optional)" value="{{ old('answers.utdb_reg_number') }}">
            </div>
        </div>

        {{-- C --}}
        <div class="hs-card hs-section" data-section="C">
            <div class="hs-section__head">
                <span class="hs-section__letter">C</span>
                <div>
                    <h2>Business Details</h2>
                </div>
            </div>
            <div class="hs-field">
                <label class="hs-label">21. Number of rooms available for guests</label>
                @include('public.homestay-survey.partials.options', ['name' => 'room_count', 'items' => $options['room_counts'], 'type' => 'radio'])
                <input class="hs-input" style="margin-top:.45rem;" name="answers[room_count_other]" type="text" placeholder="If 5+, specify" value="{{ old('answers.room_count_other') }}">
            </div>
            <div class="hs-field">
                <label class="hs-label">22. Type of homestay</label>
                @include('public.homestay-survey.partials.options', ['name' => 'homestay_type', 'items' => $options['homestay_types'], 'type' => 'radio'])
            </div>
            <div class="hs-field">
                <label class="hs-label">23. Facilities offered (multiple allowed)</label>
                @include('public.homestay-survey.partials.options', ['name' => 'facilities', 'items' => $options['facilities'], 'type' => 'checkbox'])
            </div>
            <div class="hs-field">
                <label class="hs-label">24. Peak operating season</label>
                @include('public.homestay-survey.partials.options', ['name' => 'peak_season', 'items' => $options['peak_seasons'], 'type' => 'radio'])
            </div>
            <div class="hs-field">
                <label class="hs-label">25. Average tariff per room per night (₹)</label>
                @include('public.homestay-survey.partials.options', ['name' => 'tariff', 'items' => $options['tariffs'], 'type' => 'radio'])
            </div>
        </div>

        {{-- D --}}
        <div class="hs-card hs-section" data-section="D">
            <div class="hs-section__head">
                <span class="hs-section__letter">D</span>
                <div>
                    <h2>Financial Details</h2>
                </div>
            </div>
            <div class="hs-field">
                <label class="hs-label" for="initial_investment">26. Total initial investment to set up / upgrade (₹)</label>
                <input class="hs-input" id="initial_investment" name="answers[initial_investment]" type="text" value="{{ old('answers.initial_investment') }}">
            </div>
            <div class="hs-field">
                <label class="hs-label">27. Source of funding (multiple allowed)</label>
                @include('public.homestay-survey.partials.options', ['name' => 'funding_sources', 'items' => $options['funding_sources'], 'type' => 'checkbox'])
                <input class="hs-input" style="margin-top:.45rem;" name="answers[bank_loan_scheme]" type="text" placeholder="If bank loan, specify scheme" value="{{ old('answers.bank_loan_scheme') }}">
            </div>
            <div class="hs-field">
                <label class="hs-label">28. Financial assistance / seed capital through MUY?</label>
                @include('public.homestay-survey.partials.options', ['name' => 'muy_financial_assistance', 'items' => ['Yes', 'No'], 'type' => 'radio'])
                <div class="hs-grid-2" style="margin-top:.45rem;">
                    <input class="hs-input hs-prefill" data-prefill="muy_financial_amount" name="answers[muy_financial_amount]" type="text" placeholder="If yes, amount (₹)" value="{{ old('answers.muy_financial_amount') }}">
                    <input class="hs-input" name="answers[muy_financial_year]" type="text" placeholder="Year received" value="{{ old('answers.muy_financial_year') }}">
                </div>
            </div>
            <div class="hs-field">
                <label class="hs-label">29. Bank loan facilitated through MUY?</label>
                @include('public.homestay-survey.partials.options', ['name' => 'bank_loan_muy', 'items' => ['Yes', 'No'], 'type' => 'radio'])
                <div class="hs-grid-2" style="margin-top:.45rem;">
                    <input class="hs-input" name="answers[bank_loan_amount]" type="text" placeholder="If yes, amount sanctioned (₹)" value="{{ old('answers.bank_loan_amount') }}">
                    <div>
                        <label class="hs-label">Interest subvention received?</label>
                        @include('public.homestay-survey.partials.options', ['name' => 'interest_subvention', 'items' => ['Yes', 'No'], 'type' => 'radio'])
                    </div>
                </div>
            </div>
            <div class="hs-field">
                <label class="hs-label">30. Monthly business revenue vs first year</label>
                @include('public.homestay-survey.partials.options', ['name' => 'revenue_status', 'items' => $options['revenue_status'], 'type' => 'radio'])
            </div>
            <div class="hs-field">
                <label class="hs-label">31. Financial indicators</label>
                <table class="hs-table-lite">
                    <thead>
                        <tr>
                            <th>Indicator</th>
                            <th>During MUY incubation</th>
                            <th>Current status</th>
                        </tr>
                    </thead>
                    <tbody>
                        <tr>
                            <td>31.1 Average monthly revenue (₹)</td>
                            <td><input class="hs-input" name="answers[revenue_during]" type="text" value="{{ old('answers.revenue_during') }}"></td>
                            <td><input class="hs-input" name="answers[revenue_current]" type="text" value="{{ old('answers.revenue_current') }}"></td>
                        </tr>
                        <tr>
                            <td>31.2 Average monthly occupancy</td>
                            <td><input class="hs-input" name="answers[occupancy_during]" type="text" value="{{ old('answers.occupancy_during') }}"></td>
                            <td><input class="hs-input" name="answers[occupancy_current]" type="text" value="{{ old('answers.occupancy_current') }}"></td>
                        </tr>
                        <tr>
                            <td>31.3 Number of guests / year</td>
                            <td><input class="hs-input" name="answers[guests_during]" type="text" value="{{ old('answers.guests_during') }}"></td>
                            <td><input class="hs-input" name="answers[guests_current]" type="text" value="{{ old('answers.guests_current') }}"></td>
                        </tr>
                        <tr>
                            <td>31.4 Number of persons employed</td>
                            <td><input class="hs-input hs-prefill" data-prefill="employed_count_during" name="answers[employed_count_during_q31]" type="text" value="{{ old('answers.employed_count_during_q31') }}"></td>
                            <td><input class="hs-input" name="answers[employed_count_current_q31]" type="text" value="{{ old('answers.employed_count_current_q31') }}"></td>
                        </tr>
                    </tbody>
                </table>
            </div>
            <div class="hs-field">
                <label class="hs-label">31.5 Average monthly income from other sources</label>
                @foreach ($options['other_income_sources'] as $i => $src)
                    <div class="hs-grid-2" style="margin-bottom:.4rem;">
                        <span style="font-size:.88rem; align-self:center;">{{ chr(97 + $i) }}. {{ $src }}</span>
                        <input class="hs-input" name="answers[other_income][{{ $src }}]" type="text" placeholder="₹ / month" value="{{ old('answers.other_income.'.$src) }}">
                    </div>
                @endforeach
            </div>
        </div>

        {{-- E --}}
        <div class="hs-card hs-section" data-section="E">
            <div class="hs-section__head">
                <span class="hs-section__letter">E</span>
                <div>
                    <h2>Occupancy &amp; Market</h2>
                </div>
            </div>
            <div class="hs-field">
                <label class="hs-label">32. Average room occupancy during a year</label>
                @include('public.homestay-survey.partials.options', ['name' => 'occupancy_band', 'items' => $options['occupancy_bands'], 'type' => 'radio'])
            </div>
            <div class="hs-field">
                <label class="hs-label">33. Primary sources of bookings (multiple allowed)</label>
                @include('public.homestay-survey.partials.options', ['name' => 'booking_sources', 'items' => $options['booking_sources'], 'type' => 'checkbox'])
            </div>
            <div class="hs-field">
                <label class="hs-label">34. Listed on any online booking platform?</label>
                @include('public.homestay-survey.partials.options', ['name' => 'listed_ota', 'items' => ['Yes', 'No'], 'type' => 'radio'])
                <div style="margin-top:.45rem;">
                    @include('public.homestay-survey.partials.options', ['name' => 'ota_platforms', 'items' => $options['ota_platforms'], 'type' => 'checkbox'])
                    <input class="hs-input" style="margin-top:.45rem;" name="answers[ota_other]" type="text" placeholder="If Other, specify" value="{{ old('answers.ota_other') }}">
                </div>
            </div>
            <div class="hs-field">
                <label class="hs-label">35. Linked to any tourism circuit / cluster / aggregator through MUY?</label>
                @include('public.homestay-survey.partials.options', ['name' => 'tourism_linkage', 'items' => ['Yes', 'No'], 'type' => 'radio'])
            </div>
        </div>

        {{-- F --}}
        <div class="hs-card hs-section" data-section="F">
            <div class="hs-section__head">
                <span class="hs-section__letter">F</span>
                <div>
                    <h2>Employment &amp; Impact</h2>
                </div>
            </div>
            <div class="hs-field">
                <label class="hs-label">36. Total people employed (including family)</label>
                <table class="hs-table-lite">
                    <thead><tr><th></th><th>During MUY incubation</th><th>Current status</th></tr></thead>
                    <tbody>
                        <tr>
                            <td>Band</td>
                            <td>@include('public.homestay-survey.partials.options', ['name' => 'employed_during', 'items' => $options['employment_bands'], 'type' => 'radio'])</td>
                            <td>@include('public.homestay-survey.partials.options', ['name' => 'employed_current', 'items' => $options['employment_bands'], 'type' => 'radio'])</td>
                        </tr>
                    </tbody>
                </table>
            </div>
            <div class="hs-field">
                <label class="hs-label">37. Of these — Women / Youth (18–35) / Local villagers</label>
                <div class="hs-grid-2">
                    <div>
                        <strong style="font-size:.8rem;">During incubation</strong>
                        <input class="hs-input hs-prefill" data-prefill="empwomen_during" style="margin-top:.35rem;" name="answers[women_during]" type="text" placeholder="Women" value="{{ old('answers.women_during') }}">
                        <input class="hs-input" style="margin-top:.35rem;" name="answers[youth_during]" type="text" placeholder="Youth" value="{{ old('answers.youth_during') }}">
                        <input class="hs-input" style="margin-top:.35rem;" name="answers[local_during]" type="text" placeholder="Local villagers" value="{{ old('answers.local_during') }}">
                    </div>
                    <div>
                        <strong style="font-size:.8rem;">Current</strong>
                        <input class="hs-input" style="margin-top:.35rem;" name="answers[women_current]" type="text" placeholder="Women" value="{{ old('answers.women_current') }}">
                        <input class="hs-input" style="margin-top:.35rem;" name="answers[youth_current]" type="text" placeholder="Youth" value="{{ old('answers.youth_current') }}">
                        <input class="hs-input" style="margin-top:.35rem;" name="answers[local_current]" type="text" placeholder="Local villagers" value="{{ old('answers.local_current') }}">
                    </div>
                </div>
            </div>
            <div class="hs-field">
                <label class="hs-label">38. Do you source goods / services locally? (multiple)</label>
                @include('public.homestay-survey.partials.options', ['name' => 'local_sourcing', 'items' => $options['local_sourcing'], 'type' => 'checkbox'])
            </div>
            <div class="hs-field">
                <label class="hs-label">39. Has your homestay encouraged others in the village?</label>
                @include('public.homestay-survey.partials.options', ['name' => 'encouraged_others', 'items' => $options['yes_no_unsure'], 'type' => 'radio'])
            </div>
        </div>

        {{-- G --}}
        <div class="hs-card hs-section" data-section="G">
            <div class="hs-section__head">
                <span class="hs-section__letter">G</span>
                <div>
                    <h2>Training &amp; Support</h2>
                </div>
            </div>
            <div class="hs-field">
                <label class="hs-label">40. Support services received (multiple)</label>
                @include('public.homestay-survey.partials.options', ['name' => 'support_services', 'items' => $options['support_services'], 'type' => 'checkbox'])
            </div>
            <div class="hs-field">
                <label class="hs-label">41. Usefulness of training / mentoring</label>
                @include('public.homestay-survey.partials.options', ['name' => 'training_usefulness', 'items' => $options['usefulness'], 'type' => 'radio'])
            </div>
            <div class="hs-field">
                <label class="hs-label">42. Frequency of follow-up support</label>
                @include('public.homestay-survey.partials.options', ['name' => 'followup_frequency', 'items' => $options['followup_frequency'], 'type' => 'radio'])
            </div>
            <div class="hs-field">
                <label class="hs-label">43. Did MUY help obtain certification / quality grading?</label>
                @include('public.homestay-survey.partials.options', ['name' => 'certification', 'items' => ['Yes', 'No'], 'type' => 'radio'])
                <input class="hs-input" style="margin-top:.45rem;" name="answers[certification_detail]" type="text" placeholder="If yes, specify" value="{{ old('answers.certification_detail') }}">
            </div>
        </div>

        {{-- H --}}
        <div class="hs-card hs-section" data-section="H">
            <div class="hs-section__head">
                <span class="hs-section__letter">H</span>
                <div>
                    <h2>Challenges</h2>
                </div>
            </div>
            <div class="hs-field">
                <label class="hs-label">44. Top challenges (rank top 3 — enter 1, 2, 3)</label>
                @foreach ($options['challenge_rank_options'] as $chal)
                    <div class="hs-grid-2" style="margin-bottom:.35rem; align-items:center;">
                        <span style="font-size:.88rem;">{{ $chal }}</span>
                        <input class="hs-input" name="answers[challenge_ranks][{{ $chal }}]" type="number" min="1" max="3" placeholder="Rank" value="{{ old('answers.challenge_ranks.'.$chal) }}">
                    </div>
                @endforeach
                <input class="hs-input" style="margin-top:.45rem;" name="answers[challenge_other]" type="text" placeholder="If Other, specify" value="{{ old('answers.challenge_other') }}">
            </div>
            <div class="hs-field">
                <label class="hs-label">45. Did COVID or natural events (floods/landslides) affect your business?</label>
                @include('public.homestay-survey.partials.options', ['name' => 'covid_impact', 'items' => ['Yes', 'No'], 'type' => 'radio'])
                <textarea class="hs-textarea" style="margin-top:.45rem;" name="answers[covid_recovery]" placeholder="If yes, how did you recover?">{{ old('answers.covid_recovery') }}</textarea>
            </div>
        </div>

        {{-- I --}}
        <div class="hs-card hs-section" data-section="I">
            <div class="hs-section__head">
                <span class="hs-section__letter">I</span>
                <div>
                    <h2>Digital &amp; Marketing</h2>
                </div>
            </div>
            <div class="hs-field">
                <label class="hs-label">46. Online market linkage support from MUY (multiple)</label>
                @include('public.homestay-survey.partials.options', ['name' => 'digital_support', 'items' => $options['digital_support'], 'type' => 'checkbox'])
            </div>
            <div class="hs-field">
                <label class="hs-label">47. Comfort managing online bookings &amp; marketing</label>
                @include('public.homestay-survey.partials.options', ['name' => 'digital_comfort', 'items' => $options['digital_comfort'], 'type' => 'radio'])
            </div>
        </div>

        {{-- J --}}
        <div class="hs-card hs-section" data-section="J">
            <div class="hs-section__head">
                <span class="hs-section__letter">J</span>
                <div>
                    <h2>Overall Progress</h2>
                </div>
            </div>
            <div class="hs-field">
                <label class="hs-label">48. Enterprise progress since joining MUY (1–5)</label>
                @include('public.homestay-survey.partials.options', ['name' => 'progress_rating', 'items' => $options['progress_rating'], 'type' => 'radio'])
            </div>
            <div class="hs-field">
                <label class="hs-label">49. MUY improved household income &amp; entrepreneurial confidence (1–5)</label>
                @include('public.homestay-survey.partials.options', ['name' => 'income_confidence', 'items' => $options['agree_rating'], 'type' => 'radio'])
            </div>
            <div class="hs-field">
                <label class="hs-label">50. Would you recommend MUY to other aspiring homestay entrepreneurs? (1–5)</label>
                @include('public.homestay-survey.partials.options', ['name' => 'recommend_muy', 'items' => $options['recommend_rating'], 'type' => 'radio'])
            </div>
        </div>

        {{-- K --}}
        <div class="hs-card hs-section" data-section="K">
            <div class="hs-section__head">
                <span class="hs-section__letter">K</span>
                <div>
                    <h2>Future Support Needs</h2>
                </div>
            </div>
            <div class="hs-field">
                <label class="hs-label">51. Expansion plans in next 2 years (multiple)</label>
                @include('public.homestay-survey.partials.options', ['name' => 'expansion_plans', 'items' => $options['expansion_plans'], 'type' => 'checkbox'])
            </div>
            <div class="hs-field">
                <label class="hs-label">52. Further support needed from MUY (rank top 3 — enter 1, 2, 3)</label>
                @foreach ($options['future_support'] as $item)
                    <div class="hs-grid-2" style="margin-bottom:.35rem; align-items:center;">
                        <span style="font-size:.88rem;">{{ $item }}</span>
                        <input class="hs-input" name="answers[future_support][{{ $item }}]" type="number" min="1" max="3" placeholder="Rank" value="{{ old('answers.future_support.'.$item) }}">
                    </div>
                @endforeach
            </div>
            <div class="hs-field">
                <label class="hs-label" for="other_support">53. Any other support required from MUY</label>
                <textarea class="hs-textarea" id="other_support" name="answers[other_support]">{{ old('answers.other_support') }}</textarea>
            </div>

            <div class="hs-field hs-consent" style="margin-top:1.15rem;">
                <label class="hs-tick">
                    <input type="checkbox" name="consent" value="1" @checked(old('consent')) required>
                    <span class="hs-tick__box" aria-hidden="true"></span>
                    <span class="hs-tick__text">I consent to this data being used for MUY monitoring &amp; evaluation purposes. <span class="hs-req">*</span></span>
                </label>
            </div>

            <div class="hs-submit-bar">
                <span class="hs-submit-bar__hint">One submission per mobile number</span>
                <button type="submit" class="hs-btn hs-btn--lg">Submit survey</button>
            </div>
        </div>
    </form>
</div>
@endsection

@push('scripts')
<script>
(function () {
  const lookupUrl = @json(route('homestay-survey.lookup'));
  const csrf = document.querySelector('meta[name="csrf-token"]').getAttribute('content');
  const prefillKeys = @json($options['prefill_keys'] ?? []);
  let prefillLocked = @json((bool) $prefillLocked);

  const phoneInput = document.getElementById('lookup-phone');
  const btn = document.getElementById('hs-lookup-btn');
  const msg = document.getElementById('hs-lookup-msg');
  const form = document.getElementById('hs-survey-form');
  const lookupCard = document.getElementById('hs-lookup-card');
  const progress = document.getElementById('hs-progress');
  const progressBar = document.getElementById('hs-progress-bar');
  const progressPct = document.getElementById('hs-progress-pct');
  const progressLabel = document.getElementById('hs-progress-label');

  function showMsg(text, type) {
    msg.classList.remove('hs-hidden', 'hs-alert--error', 'hs-alert--warn', 'hs-alert--ok');
    msg.classList.add(type === 'ok' ? 'hs-alert--ok' : (type === 'warn' ? 'hs-alert--warn' : 'hs-alert--error'));
    msg.textContent = text;
  }

  function setRadioOrCheckbox(name, value) {
    if (value == null || value === '') return;
    if (Array.isArray(value)) {
      value.forEach(function (v) {
        const el = form.querySelector('input[name="answers[' + name + '][]"][value="' + CSS.escape(String(v)) + '"]');
        if (el) el.checked = true;
      });
      return;
    }
    const el = form.querySelector('input[name="answers[' + name + ']"][value="' + CSS.escape(String(value)) + '"]');
    if (el) el.checked = true;
  }

  function sectionFilled(section) {
    const fields = section.querySelectorAll('input, textarea, select');
    if (!fields.length) return false;
    let answered = 0;
    const names = {};
    fields.forEach(function (el) {
      if (!el.name || el.type === 'hidden') return;
      names[el.name] = names[el.name] || false;
      if ((el.type === 'radio' || el.type === 'checkbox') && el.checked) names[el.name] = true;
      if ((el.type === 'text' || el.type === 'email' || el.type === 'tel' || el.type === 'number' || el.tagName === 'TEXTAREA' || el.tagName === 'SELECT') && String(el.value || '').trim() !== '') {
        names[el.name] = true;
      }
    });
    const keys = Object.keys(names);
    if (!keys.length) return false;
    keys.forEach(function (k) { if (names[k]) answered += 1; });
    return answered / keys.length >= 0.25;
  }

  function updateProgress() {
    if (!form || form.classList.contains('hs-hidden')) return;
    const sections = form.querySelectorAll('.hs-section');
    let done = 0;
    sections.forEach(function (s) { if (sectionFilled(s)) done += 1; });
    const pct = sections.length ? Math.round((done / sections.length) * 100) : 0;
    progressBar.style.width = pct + '%';
    progressPct.textContent = pct + '%';
    progressLabel.textContent = done + ' of ' + sections.length + ' sections started';
  }

  function applyPrefill(prefill, locked) {
    prefillLocked = !!locked;
    Object.keys(prefill || {}).forEach(function (key) {
      const val = prefill[key];
      const textEl = form.querySelector('[data-prefill="' + key + '"]');
      if (textEl) {
        if (val != null && String(val) !== '') textEl.value = val;
        if (prefillKeys.indexOf(key) !== -1) {
          textEl.readOnly = prefillLocked && textEl.value !== '';
        }
      }
      if (['gender','age_group','caste','location_type','enrolment_year','venture_type','stage_at_enrolment','utdb_registered','muy_financial_assistance','bank_loan_muy'].indexOf(key) !== -1) {
        setRadioOrCheckbox(key, val);
      }
      if (key === 'info_source') {
        setRadioOrCheckbox('info_source', Array.isArray(val) ? val : (val ? [val] : []));
      }
      if (key === 'support_services') {
        setRadioOrCheckbox('support_services', Array.isArray(val) ? val : []);
      }
    });

    if (prefillLocked) {
      ['gender','age_group','caste','location_type','enrolment_year','venture_type','stage_at_enrolment','utdb_registered','muy_financial_assistance','bank_loan_muy','info_source','support_services'].forEach(function (name) {
        const checked = form.querySelectorAll('input[name="answers[' + name + ']"]:checked, input[name="answers[' + name + '][]"]:checked');
        if (!checked.length) return;
        form.querySelectorAll('input[name="answers[' + name + ']"], input[name="answers[' + name + '][]"]').forEach(function (el) {
          el.disabled = true;
        });
        checked.forEach(function (el) {
          const hidden = document.createElement('input');
          hidden.type = 'hidden';
          hidden.name = el.name;
          hidden.value = el.value;
          form.appendChild(hidden);
        });
      });
    }
    updateProgress();
  }

  async function doLookup() {
    const phone = (phoneInput.value || '').replace(/\D+/g, '');
    if (!/^[6-9]\d{9}$/.test(phone)) {
      showMsg('Enter a valid 10-digit Indian mobile number starting with 6–9.', 'error');
      return;
    }
    btn.disabled = true;
    btn.textContent = 'Checking…';
    showMsg('Looking up your Homestay profile…', 'warn');

    try {
      const res = await fetch(lookupUrl, {
        method: 'POST',
        headers: {
          'Content-Type': 'application/json',
          'Accept': 'application/json',
          'X-CSRF-TOKEN': csrf,
          'X-Requested-With': 'XMLHttpRequest'
        },
        body: JSON.stringify({ phone: phone })
      });
      const data = await res.json();

      if (data.status === 'already_submitted') {
        if (data.redirect) {
          window.location.href = data.redirect;
          return;
        }
        showMsg(data.message || 'Already submitted.', 'warn');
        btn.disabled = false;
        btn.textContent = 'Continue';
        return;
      }
      if (!data.ok || data.status !== 'ok') {
        showMsg(data.message || 'Number not found as a Homestay incubatee.', 'error');
        btn.disabled = false;
        btn.textContent = 'Continue';
        return;
      }

      document.getElementById('form-phone').value = phone;
      document.getElementById('display_phone').value = phone;
      document.getElementById('form-phase').value = (data.profile && data.profile.phase) || '';
      document.getElementById('form-source-id').value = (data.profile && data.profile.source_id) || '';
      document.getElementById('form-application-no').value = (data.profile && data.profile.application_no) || '';

      var summary = (data.profile && data.profile.summary) || {};
      function setSum(id, value) {
        var el = document.getElementById(id);
        if (!el) return;
        el.textContent = value && String(value).trim() !== '' ? String(value) : '—';
      }
      setSum('hs-sum-name', summary.name);
      setSum('hs-sum-app', summary.application_no);
      setSum('hs-sum-district', summary.district);
      setSum('hs-sum-block', summary.block);
      setSum('hs-sum-sector', summary.sector);
      setSum('hs-sum-product', summary.product);

      applyPrefill(data.prefill || {}, data.prefill_locked);
      lookupCard.classList.add('hs-hidden');
      form.classList.remove('hs-hidden');
      progress.classList.remove('hs-hidden');
      updateProgress();
      form.scrollIntoView({ behavior: 'smooth', block: 'start' });
    } catch (e) {
      showMsg('Could not reach the server. Please try again.', 'error');
      btn.disabled = false;
      btn.textContent = 'Continue';
    }
  }

  btn.addEventListener('click', doLookup);
  phoneInput.addEventListener('keydown', function (e) {
    if (e.key === 'Enter') { e.preventDefault(); doLookup(); }
  });
  form.addEventListener('input', updateProgress);
  form.addEventListener('change', updateProgress);

  @if (old('phone') && !$errors->has('phone'))
  (async function () {
    phoneInput.value = @json(old('phone'));
    await doLookup();
  })();
  @endif
})();
</script>
@endpush
