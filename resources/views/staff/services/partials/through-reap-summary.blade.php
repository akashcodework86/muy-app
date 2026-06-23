@php
    use App\Support\ConvergenceReapSupport;
    $caseModel = $case ?? null;
    $payload = is_array($payload ?? null) ? $payload : (is_array($caseModel?->payload ?? null) ? $caseModel->payload : []);
    $showReap = $caseModel instanceof \App\Models\ServiceCase
        && $caseModel->displaysReapSupportRoute();
    $reapSector = $payload[ConvergenceReapSupport::REAP_SECTOR_KEY] ?? null;
    $reapAmount = $payload[ConvergenceReapSupport::REAP_AMOUNT_KEY] ?? null;
    $reapActivity = $payload[ConvergenceReapSupport::REAP_ACTIVITY_KEY] ?? null;
@endphp
@if ($showReap)
    <div style="background:linear-gradient(180deg,#fff7ed 0%,#fffbeb 100%);border:1px solid #fdba74;border-radius:12px;padding:1rem 1.1rem;max-width:52rem;margin-bottom:1rem;box-shadow:0 8px 22px -18px rgba(234,88,12,0.35);">
        <h3 style="margin:0 0 0.65rem;font-size:0.95rem;color:#9a3412;">{{ $caseModel->isReapSupportServiceCase() ? 'Support through REAP (MIS 8.2)' : 'Through REAP — Schematic convergence' }}</h3>
        <p style="margin:0;font-size:0.88rem;">
            <span style="display:inline-flex;align-items:center;padding:0.16rem 0.5rem;border-radius:999px;border:1px solid #fdba74;background:#fff7ed;color:#9a3412;font-size:0.72rem;font-weight:800;">Through REAP</span>
            <span style="display:block;margin-top:0.45rem;color:#52525b;font-size:0.82rem;">
                Counts toward MIS <strong>8.2</strong> (Support through REAP) when approved.
            </span>
        </p>
        @if ($reapSector || $reapAmount || $reapActivity)
            <dl style="margin:0.75rem 0 0;display:grid;grid-template-columns:minmax(0,9rem) minmax(0,1fr);gap:0.35rem 0.75rem;font-size:0.84rem;">
                @if ($reapSector)
                    <dt style="color:#9a3412;">Sector</dt>
                    <dd style="margin:0;">{{ ConvergenceReapSupport::reapSectorLabel((string) $reapSector) }}</dd>
                @endif
                @if ($reapAmount)
                    <dt style="color:#9a3412;">Support amount</dt>
                    <dd style="margin:0;">{{ ConvergenceReapSupport::reapAmountLabel((string) $reapAmount) }}</dd>
                @endif
                @if ($reapActivity)
                    <dt style="color:#9a3412;">Purposed activity</dt>
                    <dd style="margin:0;white-space:pre-wrap;">{{ $reapActivity }}</dd>
                @endif
            </dl>
        @endif
    </div>
@endif
