@php
    $filters = $filters ?? [];
@endphp

<div class="p1l-filters">
    <form method="get" action="{{ route('admin.cfa.index') }}" class="p1l-filters__grid">
        <div class="p1l-field p1l-field--wide">
            <label class="p1l-label" for="cfa-name">Search by name</label>
            <input id="cfa-name" class="p1l-input" type="text" name="name" value="{{ $filters['name'] ?? '' }}" placeholder="Applicant name">
        </div>

        <div class="p1l-field">
            <label class="p1l-label" for="cfa-application-no">Application no.</label>
            <input id="cfa-application-no" class="p1l-input" type="search" name="application_no" value="{{ $filters['application_no'] ?? '' }}" placeholder="e.g. 5040602D">
        </div>

        <div class="p1l-field">
            <label class="p1l-label" for="cfa-district">District</label>
            <select id="cfa-district" name="district_id" class="p1l-select">
                <option value="">All districts</option>
                @foreach ($districts as $dist)
                    <option value="{{ $dist->id }}" @selected((int) ($filters['district_id'] ?? 0) === (int) $dist->id)>{{ $dist->name }}</option>
                @endforeach
            </select>
        </div>

        <div class="p1l-field">
            <label class="p1l-label" for="cfa-block">Block</label>
            <select id="cfa-block" name="block" class="p1l-select">
                <option value="">All blocks</option>
                @foreach ($blocks ?? [] as $blockName)
                    <option value="{{ $blockName }}" @selected(($filters['block'] ?? '') === $blockName)>{{ $blockName }}</option>
                @endforeach
            </select>
        </div>

        <div class="p1l-field">
            <label class="p1l-label" for="cfa-sector">Sector</label>
            <select id="cfa-sector" name="sector" class="p1l-select">
                <option value="">All sectors</option>
                @foreach ($sectors as $sec)
                    <option value="{{ $sec }}" @selected(($filters['sector'] ?? '') === $sec)>{{ $sec }}</option>
                @endforeach
            </select>
        </div>

        <div class="p1l-field">
            <label class="p1l-label" for="cfa-onboard">Onboard status</label>
            <select id="cfa-onboard" name="onboard" class="p1l-select">
                <option value="">All</option>
                <option value="onboarded" @selected(($filters['onboard'] ?? '') === 'onboarded')>Onboarded</option>
                <option value="non_onboarded" @selected(($filters['onboard'] ?? '') === 'non_onboarded')>Non onboarded</option>
            </select>
        </div>

        <div class="p1l-field">
            <label class="p1l-label" for="cfa-from">From date</label>
            <input id="cfa-from" class="p1l-input" type="date" name="from" value="{{ $filters['from'] ?? '' }}">
        </div>

        <div class="p1l-field">
            <label class="p1l-label" for="cfa-to">To date</label>
            <input id="cfa-to" class="p1l-input" type="date" name="to" value="{{ $filters['to'] ?? '' }}">
        </div>

        <div class="p1l-filters__actions">
            <button type="submit" class="p1l-btn p1l-btn--primary">Apply filters</button>
            <a href="{{ route('admin.cfa.export', request()->query()) }}" class="p1l-btn p1l-btn--ghost" style="background:#ecfdf5;border-color:#6ee7b7;color:#047857;">Download CSV</a>
            @if (\App\Services\Cfa\CfaSubmissionListQuery::hasActiveFilters(request()))
                <a href="{{ route('admin.cfa.index') }}" class="p1l-btn p1l-btn--ghost">Clear all</a>
            @endif
        </div>
    </form>
</div>
