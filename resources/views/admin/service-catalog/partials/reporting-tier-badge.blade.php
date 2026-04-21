@php
    $tier = $svc->reporting_tier ?? 'unset';
@endphp
@if ($tier === 'key')
    <span title="Reporting: Key" style="background:#ecfdf5; color:#047857; border:1px solid #a7f3d0; padding:0 0.4rem; border-radius:999px; font-size:0.72rem; font-weight:600; margin-left:0.2rem;">Key</span>
@elseif ($tier === 'non_key')
    <span title="Reporting: Non-Key" style="background:#f1f5f9; color:#475569; border:1px solid #e2e8f0; padding:0 0.4rem; border-radius:999px; font-size:0.72rem; font-weight:600; margin-left:0.2rem;">Non-Key</span>
@else
    <span title="Reporting: Unset (rolled up with Non-Key in metrics)" style="background:#fafafa; color:#71717a; border:1px solid #e4e4e7; padding:0 0.4rem; border-radius:999px; font-size:0.72rem; font-weight:600; margin-left:0.2rem;">Unset</span>
@endif
