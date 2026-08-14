@php
    $filterOptions = $filterOptions ?? [];
    $showDistrictFilter = $showDistrictFilter ?? false;
    $districts = $districts ?? [];
    $formAction = $formAction ?? url()->current();
    $clearUrl = $clearUrl ?? $formAction;
    $preserveParams = $preserveParams ?? [];
@endphp

<div class="p1l-filters">
    <form method="get" action="{{ $formAction }}" class="p1l-filters__grid">
        @foreach ($preserveParams as $key => $value)
            @if ($value !== null && $value !== '')
                <input type="hidden" name="{{ $key }}" value="{{ $value }}">
            @endif
        @endforeach

        @if ($showDistrictFilter)
            <div class="p1l-field">
                <label class="p1l-label" for="p2-district">District</label>
                <select id="p2-district" name="district" class="p1l-select">
                    <option value="">All districts</option>
                    @foreach ($districts as $district)
                        <option value="{{ $district }}" @selected(request('district') === $district)>{{ $district }}</option>
                    @endforeach
                </select>
            </div>
        @endif

        <div class="p1l-field">
            <label class="p1l-label" for="p2-category">Category</label>
            <select id="p2-category" name="category" class="p1l-select">
                <option value="">All</option>
                @foreach (($filterOptions['categories'] ?? []) as $opt)
                    <option value="{{ $opt }}" @selected(request('category') === $opt)>{{ $opt }}</option>
                @endforeach
            </select>
        </div>

        <div class="p1l-field">
            <label class="p1l-label" for="p2-stage">Form stage</label>
            <select id="p2-stage" name="form_stage" class="p1l-select">
                <option value="">All</option>
                @foreach (($filterOptions['form_stages'] ?? []) as $opt)
                    <option value="{{ $opt }}" @selected(request('form_stage') === $opt)>{{ $opt }}</option>
                @endforeach
            </select>
        </div>

        @include('partials.cfa.caste-filter', ['id' => 'p2-caste'])

        <div class="p1l-field">
            <label class="p1l-label" for="p2-gender">Gender</label>
            <select id="p2-gender" name="gender" class="p1l-select">
                <option value="">All</option>
                <option value="{{ \App\Services\LegacyPhase2\LegacyPhase2ListQuery::BLANK }}" @selected(request('gender') === \App\Services\LegacyPhase2\LegacyPhase2ListQuery::BLANK)>(Blank)</option>
                @foreach (($filterOptions['genders'] ?? []) as $opt)
                    <option value="{{ $opt }}" @selected(request('gender') === $opt)>{{ $opt }}</option>
                @endforeach
            </select>
        </div>

        <div class="p1l-field">
            <label class="p1l-label" for="p2-onboard">Onboard status</label>
            <select id="p2-onboard" name="onboard" class="p1l-select">
                <option value="">All</option>
                <option value="onboarded" @selected(request('onboard') === 'onboarded' || request('onboarding_status') === 'yes')>Onboarded</option>
                <option value="non_onboarded" @selected(request('onboard') === 'non_onboarded' || request('onboarding_status') === 'no')>Non onboarded</option>
            </select>
        </div>

        <div class="p1l-field p1l-field--wide">
            <label class="p1l-label" for="p2-search">Search</label>
            <input id="p2-search" class="p1l-input" type="text" name="search" value="{{ request('search') }}"
                   placeholder="Application no., name, phone, or ID">
        </div>

        <div class="p1l-filters__actions">
            <button type="submit" class="p1l-btn p1l-btn--primary">Apply filters</button>
            @if (\App\Services\LegacyPhase2\LegacyPhase2ListQuery::hasActiveFilters(request(), $showDistrictFilter))
                <a href="{{ $clearUrl }}" class="p1l-btn p1l-btn--ghost">Clear all</a>
            @endif
            @if (! empty($exportUrl))
                <a href="{{ $exportUrl }}" class="p1l-btn p1l-btn--ghost" style="background:#ecfdf5;border-color:#6ee7b7;color:#047857;">Export Excel</a>
            @endif
        </div>
    </form>
</div>
