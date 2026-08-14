@php
    $selectedCaste = $selected ?? request('caste');
    $casteOptions = \App\Services\Cfa\CfaSubmissionListQuery::casteFilterOptions();
@endphp
<div class="p1l-field">
    <label class="p1l-label" for="{{ $id }}">Social category</label>
    <select id="{{ $id }}" name="caste" class="p1l-select">
        <option value="">All categories</option>
        @foreach ($casteOptions as $value => $label)
            <option value="{{ $value }}" @selected($selectedCaste === $value)>{{ $label }}</option>
        @endforeach
    </select>
</div>
