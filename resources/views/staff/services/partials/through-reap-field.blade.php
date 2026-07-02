@php
    $throughReapValue = old('payload.through_reap', $payload[\App\Support\ConvergenceReapSupport::PAYLOAD_KEY] ?? null);
    $throughReapChecked = \App\Support\ConvergenceReapSupport::payloadValueIsThroughReap($throughReapValue);
@endphp
<div style="margin-top:0.85rem;padding:0.65rem 0.75rem;border:1px solid #fed7aa;border-radius:8px;background:#fff7ed;margin-bottom:0.65rem;">
    <label style="display:flex;align-items:flex-start;gap:0.45rem;cursor:pointer;margin:0;">
        <input type="hidden" name="payload[through_reap]" value="0">
        <input
            type="checkbox"
            name="payload[through_reap]"
            value="1"
            @checked($throughReapChecked)
            style="margin-top:0.15rem;width:1rem;height:1rem;accent-color:#ea580c;"
        >
        <span>
            <strong style="font-size:0.84rem;color:#7c2d12;">Through REAP</strong>
            <span style="display:block;font-size:0.76rem;color:#9a3412;margin-top:0.12rem;">
                Tick for MIS <strong>8.2</strong> (Support through Reap) after SPOC approval.
            </span>
        </span>
    </label>
    @include('staff.services.partials.through-reap-details-field', [
        'payload' => $payload ?? [],
        'reapTargetsProgress' => $reapTargetsProgress ?? null,
    ])
</div>
