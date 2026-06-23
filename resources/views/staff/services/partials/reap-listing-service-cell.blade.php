@php
    use App\Support\ConvergenceReapSupport;

    $caseModel = $case ?? null;
    $showUnifiedLabel = $caseModel instanceof \App\Models\ServiceCase
        && $caseModel->displaysReapSupportRoute();
@endphp
@if ($showUnifiedLabel)
    <strong>{{ ConvergenceReapSupport::MIS_8_2_LIST_LABEL }}</strong>
    @if ($caseModel->isConvergenceServiceCase() && ! $caseModel->isReapSupportServiceCase())
        <div class="svc-muted">via {{ $caseModel->service?->name ?? 'convergence' }}</div>
    @endif
@else
    {{ $caseModel?->service?->name ?? '—' }}
@endif
