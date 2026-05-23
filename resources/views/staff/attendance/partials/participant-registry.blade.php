@php
    $districtLabel = (string) ($user->district?->name ?? '—');
    $initialRows = $activeDraft?->participantRows() ?? [];
    $initialMale = (int) old('participants_male_count', $activeDraft?->participants_male_count ?? 0);
    $initialFemale = (int) old('participants_female_count', $activeDraft?->participants_female_count ?? 0);
@endphp

<div id="attParticipantSection" class="att-participants" style="margin-top:1.4rem;display:none;">
    <div class="att-participants__head">
        <p class="att-section-label" style="margin:0;border:none;padding:0;">Participant details</p>
        <span id="attAutosaveStatus" class="att-autosave att-autosave--idle" aria-live="polite">All changes save automatically</span>
    </div>
    <p class="att-participants__hint">
        Enter male and female counts above — one row appears per participant. Every field is optional; fill what you have and return later.
    </p>
    <div class="att-participants__scroll">
        <table class="att-participants__table" id="attParticipantTable">
            <thead>
                <tr>
                    <th class="att-participants__th--sr">#</th>
                    <th>Name</th>
                    <th>Mobile</th>
                    <th>Gender</th>
                    <th>District</th>
                    <th>Block</th>
                    <th>Gram panchayat</th>
                </tr>
            </thead>
            <tbody id="attParticipantBody"></tbody>
        </table>
    </div>
    <p id="attParticipantEmpty" class="att-participants__empty" style="display:none;">Set male or female count above to add participant rows.</p>
</div>

@push('styles')
<style>
    .att-participants__head { display:flex;align-items:center;justify-content:space-between;gap:0.75rem;flex-wrap:wrap;margin-bottom:0.5rem; }
    .att-participants__hint { font-size:0.8rem;color:var(--att-muted);margin:0 0 0.75rem; }
    .att-participants__scroll { overflow-x:auto;border:1px solid var(--att-border);border-radius:12px;background:#fafbff; }
    .att-participants__table { width:100%;border-collapse:collapse;font-size:0.82rem;min-width:720px; }
    .att-participants__table th { padding:0.55rem 0.5rem;text-align:left;font-size:0.68rem;font-weight:700;text-transform:uppercase;letter-spacing:0.05em;color:var(--att-muted);background:#f1f5f9;border-bottom:1px solid var(--att-border);white-space:nowrap; }
    .att-participants__table td { padding:0.4rem 0.35rem;border-bottom:1px solid #e8eef5;vertical-align:middle; }
    .att-participants__th--sr { width:2.5rem;text-align:center; }
    .att-participants__sr { text-align:center;font-weight:700;color:var(--att-muted);font-size:0.78rem; }
    .att-participants__table .att-input { padding:0.45rem 0.5rem;font-size:0.82rem;min-width:0; }
    .att-participants__table select.att-input { min-width:8rem; }
    .att-participants__readonly { font-size:0.8rem;color:var(--att-ink);padding:0.35rem 0.25rem;white-space:nowrap; }
    .att-gender-pills { display:inline-flex;gap:0.2rem; }
    .att-gender-pill { display:inline-flex;align-items:center;justify-content:center;min-width:2rem;padding:0.3rem 0.45rem;border:1px solid #cbd5e1;border-radius:8px;font-size:0.75rem;font-weight:700;cursor:pointer;color:var(--att-muted);background:#fff;user-select:none; }
    .att-gender-pill input { position:absolute;opacity:0;width:0;height:0; }
    .att-gender-pill:has(input:checked) { border-color:var(--att-indigo);background:#eef2ff;color:var(--att-indigo); }
    .att-gender-pill--f:has(input:checked) { border-color:#db2777;background:#fdf2f8;color:#be185d; }
    .att-autosave { font-size:0.72rem;font-weight:600;padding:0.2rem 0.55rem;border-radius:999px;background:#f1f5f9;color:var(--att-muted); }
    .att-autosave--saving { background:#fef9c3;color:#a16207; }
    .att-autosave--saved { background:#dcfce7;color:#166534; }
    .att-autosave--error { background:#fee2e2;color:#b91c1c; }
    .att-draft-banner { background:linear-gradient(135deg,#fffbeb,#fef3c7);border:1px solid #fcd34d;border-radius:14px;padding:0.9rem 1.1rem;display:flex;align-items:center;justify-content:space-between;gap:0.75rem;flex-wrap:wrap; }
    .att-draft-banner strong { color:#92400e;font-size:0.88rem; }
    .att-btn--ghost { background:#fff;color:var(--att-indigo);border:1px solid #c7d2fe; }
    .att-btn--ghost:hover { background:#eef2ff; }
</style>
@endpush
