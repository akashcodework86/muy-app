@extends('layouts.admin')
@php
    $rp = $routePrefix ?? 'staff.attendance';
    $mp = $modelParam ?? 'attendanceReport';
    $pageTitle = $pageTitle ?? 'Edit field visit';
    $cancelUrl = $cancelUrl ?? route('staff.attendance.index');
@endphp

@section('title', $pageTitle)
@section('heading', $pageTitle)

@push('styles')
<style>
    .att-shell { display:flex;flex-direction:column;gap:1.25rem;padding-bottom:3rem;font-family:'DM Sans',sans-serif;max-width:900px; }
    .att-card { background:#fff;border:1px solid #e2e8f0;border-radius:18px;box-shadow:0 4px 20px rgba(15,23,42,0.05);overflow:hidden; }
    .att-card__body { padding:1.4rem; }
    .att-grid { display:grid;grid-template-columns:repeat(auto-fit,minmax(200px,1fr));gap:1rem; }
    .att-field label { font-size:0.78rem;font-weight:600;color:#334155;display:block;margin-bottom:0.35rem; }
    .att-input { width:100%;padding:0.6rem 0.7rem;border:1px solid #cbd5e1;border-radius:9px;font-size:0.88rem;box-sizing:border-box; }
    .att-req { color:#e11d48; }
    .att-btn { display:inline-flex;align-items:center;gap:0.4rem;padding:0.62rem 1.2rem;background:linear-gradient(135deg,#4f46e5,#7c3aed);color:#fff;border:none;border-radius:10px;font-size:0.88rem;font-weight:700;cursor:pointer;text-decoration:none; }
    .att-btn--ghost { background:#fff;color:#4f46e5;border:1px solid #4f46e5; }
    .att-btn--danger { background:#dc2626;border:none; }
    .att-hint { font-size:0.8rem;color:#64748b;margin:0 0 1rem; }
</style>
@endpush

@section('content')
<div class="att-shell">
    <p class="att-hint">
        Fix participant counts (e.g. Male / Female), block, gram panchayat, or village.
        If counts or location change, you must upload the attendance sheet again.
    </p>

    @if ($errors->any())
        <div style="background:#fef2f2;border:1px solid #fca5a5;color:#991b1b;border-radius:12px;padding:0.85rem 1rem;font-size:0.88rem;">
            <ul style="margin:0.4rem 0 0 1.1rem;">@foreach ($errors->all() as $e)<li>{{ $e }}</li>@endforeach</ul>
        </div>
    @endif

    <div class="att-card">
        <div class="att-card__body">
            <form method="post" action="{{ route($rp.'.update', [$mp => $report]) }}">
                @csrf
                @method('PUT')

                <div class="att-grid">
                    <div class="att-field">
                        <label>Visit date <span class="att-req">*</span></label>
                        <input type="date" name="visit_date" value="{{ old('visit_date', $report->visit_date?->toDateString()) }}" required class="att-input" max="{{ now()->toDateString() }}">
                    </div>
                    <div class="att-field">
                        <label>Block <span class="att-req">*</span></label>
                        <select name="district_block_id" id="attBlockSelect" class="att-input" required>
                            <option value="">— Select block —</option>
                            @foreach ($blockRows as $block)
                                <option value="{{ $block->id }}" @selected((int) old('district_block_id', $report->district_block_id) === (int) $block->id)>{{ $block->name }}</option>
                            @endforeach
                        </select>
                    </div>
                    <div class="att-field">
                        <label>Gram panchayat <span class="att-req">*</span></label>
                        <input type="search" id="attGpSearch" class="att-input" placeholder="Filter…" style="margin-bottom:0.35rem;">
                        <select name="gram_panchayat_id" id="attGpSelect" class="att-input" required>
                            <option value="">Loading…</option>
                        </select>
                    </div>
                    <div class="att-field">
                        <label>Area / village <span class="att-req">*</span></label>
                        <input type="text" name="area" value="{{ old('area', $report->area) }}" required class="att-input">
                    </div>
                    <div class="att-field">
                        <label>Male <span class="att-req">*</span></label>
                        <input type="number" name="participants_male_count" id="attMaleCount" value="{{ old('participants_male_count', $report->participants_male_count) }}" min="0" required class="att-input">
                    </div>
                    <div class="att-field">
                        <label>Female <span class="att-req">*</span></label>
                        <input type="number" name="participants_female_count" id="attFemaleCount" value="{{ old('participants_female_count', $report->participants_female_count) }}" min="0" required class="att-input">
                    </div>
                    <div class="att-field">
                        <label>Total participants</label>
                        <input type="number" id="attTotalParticipants" readonly class="att-input" style="background:#f8fafc;">
                    </div>
                </div>

                <div class="att-field" style="margin-top:1rem;">
                    <label>Remark</label>
                    <textarea name="remark" class="att-input" rows="3">{{ old('remark', $report->remark) }}</textarea>
                </div>

                <div style="margin-top:1.25rem;display:flex;flex-wrap:wrap;gap:0.65rem;">
                    <button type="submit" class="att-btn"><i class="fa-solid fa-check"></i> Save changes</button>
                    <a href="{{ $cancelUrl }}" class="att-btn att-btn--ghost">Cancel</a>
                </div>
            </form>
        </div>
    </div>
</div>
@endsection

@push('scripts')
<script>
(function () {
    const blockSelect = document.getElementById('attBlockSelect');
    const gpSelect = document.getElementById('attGpSelect');
    const gpSearch = document.getElementById('attGpSearch');
    const gpUrl = @json(route($rp.'.gram-panchayats'));
    const selectedGpId = @json((int) old('gram_panchayat_id', $report->gram_panchayat_id));
    let allItems = [];

    async function loadGramPanchayats(blockId) {
        if (!blockId) return;
        gpSelect.innerHTML = '<option value="">Loading…</option>';
        const res = await fetch(gpUrl + '?district_block_id=' + encodeURIComponent(blockId), {
            headers: { 'Accept': 'application/json', 'X-Requested-With': 'XMLHttpRequest' },
        });
        const data = await res.json();
        allItems = Array.isArray(data.items) ? data.items : [];
        renderGpOptions(allItems);
    }

    function renderGpOptions(items) {
        const q = (gpSearch?.value || '').trim().toLowerCase();
        const filtered = q === '' ? items : items.filter(i => i.name.toLowerCase().includes(q));
        gpSelect.innerHTML = '<option value="">— Select gram panchayat —</option>';
        filtered.forEach(i => {
            const opt = document.createElement('option');
            opt.value = String(i.id);
            opt.textContent = i.name;
            if (selectedGpId && Number(i.id) === selectedGpId) opt.selected = true;
            gpSelect.appendChild(opt);
        });
    }

    blockSelect?.addEventListener('change', () => loadGramPanchayats(blockSelect.value));
    gpSearch?.addEventListener('input', () => renderGpOptions(allItems));
    if (blockSelect?.value) loadGramPanchayats(blockSelect.value);

    const maleInput = document.getElementById('attMaleCount');
    const femaleInput = document.getElementById('attFemaleCount');
    const totalInput = document.getElementById('attTotalParticipants');
    function updateTotal() {
        const m = parseInt(maleInput?.value || '0', 10) || 0;
        const f = parseInt(femaleInput?.value || '0', 10) || 0;
        if (totalInput) totalInput.value = String(m + f);
    }
    maleInput?.addEventListener('input', updateTotal);
    femaleInput?.addEventListener('input', updateTotal);
    updateTotal();
})();
</script>
@endpush
