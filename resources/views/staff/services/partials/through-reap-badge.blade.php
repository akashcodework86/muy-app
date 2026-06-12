@php
    $caseModel = $case ?? null;
    $showBadge = $caseModel instanceof \App\Models\ServiceCase
        && $caseModel->isConvergenceServiceCase()
        && $caseModel->isMarkedThroughReap();
    $badgeStyle = 'display:inline-flex;align-items:center;margin-top:0.28rem;padding:0.16rem 0.5rem;border-radius:999px;border:1px solid #fdba74;background:linear-gradient(180deg,#fff7ed 0%,#ffedd5 100%);color:#9a3412;font-size:0.72rem;font-weight:800;letter-spacing:0.02em;white-space:nowrap;';
@endphp
@if ($showBadge)
    <span style="{{ $badgeStyle }}" title="Counts toward MIS 8.2 and 8.3 when approved">Through REAP</span>
@endif
