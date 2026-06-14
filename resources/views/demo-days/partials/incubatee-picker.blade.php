@php
    $oldParticipating = collect(old('participating_incubatees', []))
        ->filter(fn ($row) => is_array($row))
        ->values()
        ->all();
@endphp
<label for="ddyIncubateeSearch">Incubatees <span class="ddy-req">*</span></label>
<input type="text" id="ddyIncubateeSearch" class="pdp-search" autocomplete="off"
    placeholder="Search by name, phone, or application number…">
<div class="pdp-picker">
    <div class="pdp-picker__col">
        <div class="pdp-picker__head">
            <span>Search results</span>
            <span id="ddyResultsCount" class="pdp-picker__count" hidden>0</span>
        </div>
        <div id="ddyResults" class="pdp-picker__body pdp-picker__body--results" role="listbox" aria-label="Incubatee search results">
            <p class="pdp-picker__empty">Type at least 2 characters to search all Phase 3 CFA and Phase 2 applicants.</p>
        </div>
    </div>
    <div class="pdp-picker__col">
        <div class="pdp-picker__head" id="ddyDetailHead">Incubatee details</div>
        <div id="ddyDetail" class="pdp-picker__body pdp-picker__body--detail">
            <p class="pdp-picker__empty">Hover a result to preview. Click or press Enter to add.</p>
        </div>
    </div>
</div>
<div class="ddy-selected">
    <div class="pdp-picker__head">
        <span>Selected incubatees</span>
        <span id="ddySelectedCount" class="pdp-picker__count">0</span>
    </div>
    <div id="ddySelectedPanel" class="pdp-picker__body pdp-picker__body--selected">
        <p class="pdp-picker__empty">No incubatees selected yet. Search and add from the results.</p>
    </div>
    <div id="ddySelectedInputs" hidden></div>
</div>
<p class="ddy-hint">Select one or more incubatees. Click a search result to add; use Remove to take one off the list.</p>
<div id="ddyParticipantCounts" class="ddy-participant-counts" hidden>
    <span><strong>Participants selected:</strong> <span id="ddyCountTotal">0</span></span>
</div>
@error('participating_incubatees')<p class="ddy-hint" style="color:#b91c1c;">{{ $message }}</p>@enderror

@once
@push('styles')
<style>
    .pdp-search { width:100%; box-sizing:border-box; border:1px solid #cbd5e1; border-radius:8px; padding:0.58rem 0.7rem; font-size:0.88rem; background:#fff; margin-bottom:0.45rem; }
    .pdp-picker { display:grid; grid-template-columns:minmax(0,1fr) minmax(0,1fr); gap:0.85rem; margin-top:0.45rem; }
    .pdp-picker__col, .ddy-selected { min-width:0; border:1px solid #e2e8f0; border-radius:12px; background:#f8fafc; display:flex; flex-direction:column; }
    .ddy-selected { margin-top:0.85rem; }
    .pdp-picker__head {
        display:flex; align-items:center; justify-content:space-between; gap:0.5rem;
        padding:0.55rem 0.75rem; border-bottom:1px solid #e2e8f0; font-size:0.76rem; font-weight:800; color:#475569;
        text-transform:uppercase; letter-spacing:0.04em;
    }
    .pdp-picker__count {
        font-size:0.72rem; font-weight:800; color:#7c3aed; background:#f5f3ff; border-radius:999px;
        padding:0.12rem 0.5rem; letter-spacing:0; text-transform:none;
    }
    .pdp-picker__body { flex:1; padding:0.35rem; min-height:0; }
    .pdp-picker__body--results, .pdp-picker__body--detail, .pdp-picker__body--selected {
        max-height:22.5rem; overflow-y:auto; overflow-x:hidden; overscroll-behavior:contain;
    }
    .pdp-picker__body--selected { max-height:14rem; }
    .pdp-picker__empty { padding:1rem 0.75rem; font-size:0.84rem; color:#64748b; line-height:1.45; }
    .pdp-result {
        display:block; width:100%; text-align:left; border:1px solid transparent; background:#fff; cursor:pointer;
        padding:0.62rem 0.68rem; border-radius:10px; margin-bottom:0.35rem;
    }
    .pdp-result:hover, .pdp-result.is-hover { border-color:#ddd6fe; background:#f5f3ff; }
    .pdp-result.is-selected { border-color:#7c3aed; background:#ede9fe; box-shadow:0 0 0 1px #7c3aed; }
    .pdp-result__top { display:flex; align-items:flex-start; justify-content:space-between; gap:0.5rem; }
    .pdp-result__name { font-size:0.86rem; font-weight:700; color:#0f172a; }
    .pdp-result__meta { margin-top:0.28rem; font-size:0.76rem; color:#64748b; line-height:1.4; }
    .pdp-pill { display:inline-flex; padding:0.12rem 0.45rem; border-radius:999px; font-size:0.68rem; font-weight:800; background:#eef2ff; color:#3730a3; margin-right:0.25rem; }
    .pdp-pill--ok { background:#dcfce7; color:#166534; }
    .pdp-pill--muted { background:#f1f5f9; color:#475569; }
    .pdp-detail { padding:0.65rem 0.75rem; }
    .pdp-detail__title { margin:0 0 0.65rem; font-size:0.95rem; font-weight:800; color:#0f172a; }
    .pdp-detail__grid { display:grid; grid-template-columns:repeat(2,minmax(0,1fr)); gap:0.55rem 0.75rem; }
    .pdp-detail__item { min-width:0; }
    .pdp-detail__label { font-size:0.72rem; font-weight:700; color:#64748b; text-transform:uppercase; letter-spacing:0.03em; }
    .pdp-detail__value { margin-top:0.15rem; font-size:0.84rem; color:#0f172a; word-break:break-word; }
    .pdp-detail__badge { margin-bottom:0.65rem; }
    .ddy-selected-item {
        display:flex; align-items:flex-start; justify-content:space-between; gap:0.65rem;
        padding:0.62rem 0.68rem; border-radius:10px; background:#fff; border:1px solid #e2e8f0; margin-bottom:0.35rem;
    }
    .ddy-selected-item__name { font-size:0.86rem; font-weight:700; color:#0f172a; }
    .ddy-selected-item__meta { margin-top:0.2rem; font-size:0.76rem; color:#64748b; }
    .ddy-participant-counts {
        display:flex; flex-wrap:wrap; gap:0.85rem; align-items:center;
        margin-top:0.55rem; padding:0.65rem 0.75rem;
        background:#f5f3ff; border:1px solid #ddd6fe; border-radius:10px; font-size:0.84rem;
    }
    .ddy-btn-remove {
        border:1px solid #fca5a5; background:#fff; color:#b91c1c; border-radius:8px;
        padding:0.28rem 0.55rem; font-size:0.74rem; font-weight:700; cursor:pointer; flex-shrink:0;
    }
    @media (max-width:720px) { .pdp-picker { grid-template-columns:1fr; } .pdp-detail__grid { grid-template-columns:1fr; } }
</style>
@endpush
@endonce

<script type="application/json" id="ddyInitialSelected">@json($oldParticipating)</script>
