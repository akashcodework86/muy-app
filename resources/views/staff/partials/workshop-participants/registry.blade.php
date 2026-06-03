@php
    $initialRows = $initialRows ?? [];
@endphp

<div id="wsParticipantSection" class="ws-participants" style="margin-top:1rem;display:none;">
    <p class="ws-part-section-label" style="margin:0 0 0.5rem;">Participant details</p>
    <p class="ws-part-hint">Enter male and female counts above — one row per participant. Row fields are optional unless noted.</p>
    <div class="ws-participants__scroll">
        <table class="ws-participants__table" id="wsParticipantTable">
            <thead>
                <tr>
                    <th class="ws-participants__th--sr">#</th>
                    <th>Name</th>
                    <th>Mobile</th>
                    <th>Gender</th>
                    <th>District</th>
                    <th>Block</th>
                    <th>Gram panchayat</th>
                </tr>
            </thead>
            <tbody id="wsParticipantBody"></tbody>
        </table>
    </div>
    <p id="wsParticipantEmpty" class="ws-participants__empty" style="display:none;">Set male or female count above to add participant rows.</p>
</div>

@push('styles')
<style>
    .ws-part-section-label { font-size:0.68rem;font-weight:700;letter-spacing:0.1em;text-transform:uppercase;color:#64748b;margin:0 0 0.75rem;padding-bottom:0.4rem;border-bottom:1px dashed #e2e8f0; }
    .ws-part-hint { font-size:0.8rem;color:#64748b;margin:0 0 0.75rem; }
    .ws-participants__scroll { overflow-x:auto;border:1px solid #e2e8f0;border-radius:12px;background:#fafbff; }
    .ws-participants__table { width:100%;border-collapse:collapse;font-size:0.82rem;min-width:720px; }
    .ws-participants__table th { padding:0.55rem 0.5rem;text-align:left;font-size:0.68rem;font-weight:700;text-transform:uppercase;letter-spacing:0.05em;color:#64748b;background:#f1f5f9;border-bottom:1px solid #e2e8f0;white-space:nowrap; }
    .ws-participants__table td { padding:0.4rem 0.35rem;border-bottom:1px solid #e8eef5;vertical-align:middle; }
    .ws-participants__th--sr { width:2.5rem;text-align:center; }
    .ws-participants__sr { text-align:center;font-weight:700;color:#64748b;font-size:0.78rem; }
    .ws-participants__table .ws-part-input { padding:0.45rem 0.5rem;font-size:0.82rem;min-width:0; }
    .ws-participants__table select.ws-part-input { min-width:8rem; }
    .ws-participants__readonly { font-size:0.8rem;color:#334155;padding:0.35rem 0.25rem;white-space:nowrap; }
    .ws-gender-pills { display:inline-flex;gap:0.2rem; }
    .ws-gender-pill { display:inline-flex;align-items:center;justify-content:center;min-width:2rem;padding:0.3rem 0.45rem;border:1px solid #cbd5e1;border-radius:8px;font-size:0.75rem;font-weight:700;cursor:pointer;color:#64748b;background:#fff;user-select:none; }
    .ws-gender-pill input { position:absolute;opacity:0;width:0;height:0; }
    .ws-gender-pill:has(input:checked) { border-color:#4f46e5;background:#eef2ff;color:#4f46e5; }
    .ws-gender-pill--f:has(input:checked) { border-color:#db2777;background:#fdf2f8;color:#be185d; }
    .ws-participants__empty { font-size:0.82rem;color:#64748b;margin:0.5rem 0 0; }
</style>
@endpush
