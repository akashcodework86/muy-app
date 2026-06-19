@php
    $filterOptions = $filterOptions ?? [];
    $showDistrictFilter = $showDistrictFilter ?? false;
    $districts = $districts ?? [];
    $formAction = $formAction ?? url()->current();
    $clearUrl = $clearUrl ?? $formAction;
    $includeDistrictInClear = $showDistrictFilter;
@endphp

<div class="p1l-filters">
    <form method="get" action="{{ $formAction }}" class="p1l-filters__grid">
        @if ($showDistrictFilter)
            <div class="p1l-field">
                <label class="p1l-label" for="p1-district">District</label>
                <select id="p1-district" name="district" class="p1l-select">
                    <option value="">All districts</option>
                    @foreach ($districts as $district)
                        <option value="{{ $district }}" @selected(request('district') === $district)>{{ $district }}</option>
                    @endforeach
                </select>
            </div>
        @endif

        <div class="p1l-field">
            <label class="p1l-label" for="p1-region">Legacy region</label>
            <select id="p1-region" name="region" class="p1l-select">
                <option value="">All regions</option>
                @foreach (($filterOptions['legacyRegions'] ?? []) as $region)
                    <option value="{{ $region }}" @selected(request('region') === $region)>{{ $region }}</option>
                @endforeach
            </select>
        </div>

        <div class="p1l-field">
            <label class="p1l-label" for="p1-onboard">Onboard status</label>
            <select id="p1-onboard" name="onboard" class="p1l-select">
                <option value="">All</option>
                <option value="onboarded" @selected(request('onboard') === 'onboarded')>Onboarded</option>
                <option value="non_onboarded" @selected(request('onboard') === 'non_onboarded')>Non onboarded</option>
            </select>
        </div>

        <div class="p1l-field">
            <label class="p1l-label" for="p1-application-status">Loan / scheme status</label>
            <select id="p1-application-status" name="application_status" class="p1l-select">
                <option value="">All</option>
                <option value="{{ \App\Services\LegacyPhase1\LegacyPhase1ListQuery::BLANK }}" @selected(request('application_status') === \App\Services\LegacyPhase1\LegacyPhase1ListQuery::BLANK)>(Blank)</option>
                @foreach (($filterOptions['applicationStatuses'] ?? []) as $opt)
                    <option value="{{ $opt }}" @selected(request('application_status') === $opt)>{{ $opt }}</option>
                @endforeach
            </select>
        </div>

        <div class="p1l-field">
            <label class="p1l-label" for="p1-gender">Gender</label>
            <select id="p1-gender" name="gender" class="p1l-select">
                <option value="">All</option>
                <option value="{{ \App\Services\LegacyPhase1\LegacyPhase1ListQuery::BLANK }}" @selected(request('gender') === \App\Services\LegacyPhase1\LegacyPhase1ListQuery::BLANK)>(Blank)</option>
                @foreach (($filterOptions['genders'] ?? []) as $opt)
                    <option value="{{ $opt }}" @selected(request('gender') === $opt)>{{ $opt }}</option>
                @endforeach
            </select>
        </div>

        <div class="p1l-field">
            <label class="p1l-label" for="p1-education">Education</label>
            <select id="p1-education" name="education" class="p1l-select">
                <option value="">All</option>
                <option value="{{ \App\Services\LegacyPhase1\LegacyPhase1ListQuery::BLANK }}" @selected(request('education') === \App\Services\LegacyPhase1\LegacyPhase1ListQuery::BLANK)>(Blank)</option>
                @foreach (($filterOptions['educations'] ?? []) as $opt)
                    <option value="{{ $opt }}" @selected(request('education') === $opt)>{{ $opt }}</option>
                @endforeach
            </select>
        </div>

        <div class="p1l-field p1l-field--wide">
            <label class="p1l-label" for="p1-search">Search</label>
            <input id="p1-search" class="p1l-input" type="text" name="search" value="{{ request('search') }}"
                   placeholder="Application no., applicant name, or mobile">
        </div>

        <div class="p1l-filters__actions">
            <button type="submit" class="p1l-btn p1l-btn--primary">Apply filters</button>
            @if (\App\Services\LegacyPhase1\LegacyPhase1ListQuery::hasActiveFilters(request(), $includeDistrictInClear))
                <a href="{{ $clearUrl }}" class="p1l-btn p1l-btn--ghost">Clear all</a>
            @endif
            @if (! empty($exportUrl))
                <a href="{{ $exportUrl }}" class="p1l-btn p1l-btn--ghost" style="background:#ecfdf5;border-color:#6ee7b7;color:#047857;">Export Excel</a>
            @endif
        </div>
    </form>
</div>
