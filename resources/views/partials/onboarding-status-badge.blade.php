@php
    $status = $status ?? ($row['onboard_status'] ?? '');
    $label = $label ?? ($row['onboard_label'] ?? '');
@endphp
@if ($status === 'onboarded')
    <span class="fma-onboard fma-onboard--yes">{{ $label !== '' ? $label : 'Onboarded' }}</span>
@elseif ($label !== '')
    <span class="fma-onboard fma-onboard--no">{{ $label }}</span>
@else
    —
@endif
