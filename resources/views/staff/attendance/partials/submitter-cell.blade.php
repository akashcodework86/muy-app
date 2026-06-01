@php
    $submitterName = $row->field_coordinator_name
        ?: ($row->coordinator?->name ?? ($fallbackUser->name ?? '—'));
    $submitterRole = trim((string) (
        $row->coordinator?->designationRecord?->name
        ?? ($fallbackUser->designationRecord?->name ?? '')
    ));
    $nameColor = $nameColor ?? 'var(--att-text, #0f172a)';
    $roleColor = $roleColor ?? 'var(--att-muted, #64748b)';
@endphp
<div style="font-size:0.82rem;font-weight:600;color:{{ $nameColor }};">{{ $submitterName }}</div>
@if ($submitterRole !== '')
    <div style="font-size:0.72rem;color:{{ $roleColor }};margin-top:0.15rem;">{{ $submitterRole }}</div>
@endif
