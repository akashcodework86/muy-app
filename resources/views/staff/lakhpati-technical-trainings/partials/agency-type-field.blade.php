@php
    $name = 'requesting_agency_type';
    $selected = old($name, $selected ?? '');
    $agencyTypes = $agencyTypes ?? \App\Models\LakhpatiTechnicalTraining::AGENCY_TYPES;
@endphp
<div class="tp-field tp-field--full">
    <label for="{{ $name }}">Requesting agency type <span class="tp-req">*</span></label>
    <select id="{{ $name }}" name="{{ $name }}" required>
        <option value="" disabled @selected($selected === '')>— Select agency —</option>
        @foreach ($agencyTypes as $value => $label)
            <option value="{{ $value }}" @selected($selected === $value)>{{ $label }}</option>
        @endforeach
    </select>
    <p class="tp-field-hint">Workshop requested by NRLM, REAP, USRLM or other partner agencies.</p>
</div>
