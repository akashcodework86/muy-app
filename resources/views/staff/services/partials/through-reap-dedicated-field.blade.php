@php
    use App\Support\ConvergenceReapSupport;
@endphp
<div style="margin-top:0.85rem;padding:0.65rem 0.75rem;border:1px solid #fed7aa;border-radius:8px;background:#fff7ed;margin-bottom:0.65rem;">
    <input type="hidden" name="payload[through_reap]" value="1">
    <p style="margin:0 0 0.65rem;font-size:0.84rem;color:#7c2d12;">
        <strong>REAP support (MIS 8.2)</strong>
        <span style="display:block;font-size:0.76rem;color:#9a3412;margin-top:0.12rem;">Complete the REAP details below. Counts toward MIS 8.2 after SPOC approval.</span>
    </p>
    @include('staff.services.partials.through-reap-details-field', [
        'payload' => $payload ?? [],
        'throughReapChecked' => true,
    ])
</div>
