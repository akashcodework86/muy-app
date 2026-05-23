@extends('layouts.admin')

@section('title', 'Field visits')
@section('heading', 'Field visit photos')

@push('styles')
<style>
    :root {
        --att-indigo: #4f46e5;
        --att-teal:   #0d9488;
        --att-text:   #0f172a;
        --att-muted:  #64748b;
        --att-ink:    #334155;
        --att-border: #e2e8f0;
        --att-bg:     #f8fafc;
    }
    .att-shell { display:flex;flex-direction:column;gap:1.5rem;padding-bottom:3rem;font-family:'DM Sans',sans-serif;width:100%; }
    .att-banner { background:linear-gradient(135deg,rgba(79,70,229,0.08),rgba(13,148,136,0.06));border:1px solid rgba(99,102,241,0.18);border-radius:18px;padding:1.1rem 1.4rem;display:flex;align-items:center;gap:1rem;flex-wrap:wrap; }
    .att-banner__icon { width:2.8rem;height:2.8rem;background:linear-gradient(135deg,var(--att-indigo),#7c3aed);border-radius:12px;display:flex;align-items:center;justify-content:center;color:#fff;font-size:1.2rem;flex-shrink:0;box-shadow:0 4px 14px rgba(79,70,229,0.3); }
    .att-banner__body h2 { margin:0 0 0.2rem;font-size:1rem;font-weight:800;color:var(--att-text); }
    .att-banner__body p { margin:0;font-size:0.82rem;color:var(--att-muted); }
    .att-card { background:#fff;border:1px solid var(--att-border);border-radius:18px;box-shadow:0 4px 20px rgba(15,23,42,0.05);overflow:hidden; }
    .att-card__head { padding:1rem 1.4rem 0.85rem;border-bottom:1px solid var(--att-border);display:flex;align-items:center;gap:0.6rem; }
    .att-card__head-icon { width:1.9rem;height:1.9rem;background:linear-gradient(135deg,#eef2ff,#e0e7ff);border-radius:8px;display:flex;align-items:center;justify-content:center;color:var(--att-indigo);font-size:0.85rem; }
    .att-card__head h3 { margin:0;font-size:0.95rem;font-weight:700;color:var(--att-text); }
    .att-card__body { padding:1.4rem; }
    .att-section-label { font-size:0.68rem;font-weight:700;letter-spacing:0.1em;text-transform:uppercase;color:var(--att-muted);margin:0 0 0.75rem;padding-bottom:0.4rem;border-bottom:1px dashed var(--att-border); }
    .att-grid { display:grid;grid-template-columns:repeat(auto-fit,minmax(220px,1fr));gap:1.25rem 1rem; }
    .att-field { display:flex;flex-direction:column;gap:0.38rem; }
    .att-field label { font-size:0.78rem;font-weight:600;color:var(--att-ink); }
    .att-req { color:#e11d48;margin-left:2px; }
    .att-input,.att-textarea { width:100%;padding:0.6rem 0.7rem;border:1px solid #cbd5e1;border-radius:9px;font-size:0.88rem;color:var(--att-text);background:#fff;box-sizing:border-box;font-family:inherit; }
    .att-input:focus,.att-textarea:focus { outline:none;border-color:var(--att-indigo);box-shadow:0 0 0 3px rgba(79,70,229,0.12); }
    .att-input--readonly { background:var(--att-bg);color:var(--att-muted); }
    .att-textarea { resize:vertical;min-height:80px; }
    .att-file-wrap { border:1.5px dashed #c7d2fe;border-radius:10px;background:#f5f3ff;padding:0.9rem 1rem;display:flex;align-items:center;gap:0.65rem;flex-wrap:wrap; }
    .att-file-wrap i { color:var(--att-indigo);font-size:1.1rem; }
    .att-file-wrap span { font-size:0.8rem;color:var(--att-muted);flex:1;min-width:0; }
    .att-submit-row { margin-top:1.4rem;display:flex;align-items:center;gap:0.75rem; }
    .att-btn { display:inline-flex;align-items:center;gap:0.4rem;padding:0.62rem 1.2rem;background:linear-gradient(135deg,var(--att-indigo),#7c3aed);color:#fff;border:none;border-radius:10px;font-size:0.88rem;font-weight:700;cursor:pointer;font-family:inherit; }
    .att-btn:disabled { opacity:0.5;cursor:not-allowed; }
    .att-table-wrap { background:#fff;border:1px solid var(--att-border);border-radius:18px;box-shadow:0 4px 20px rgba(15,23,42,0.05);overflow:hidden; }
    .att-table-head { padding:1rem 1.4rem 0.85rem;border-bottom:1px solid var(--att-border);display:flex;align-items:center;gap:0.6rem; }
    .att-table { width:100%;border-collapse:collapse;font-size:0.855rem; }
    .att-table th { padding:0.7rem 1rem;text-align:left;font-size:0.7rem;font-weight:700;text-transform:uppercase;letter-spacing:0.06em;color:var(--att-muted);border-bottom:1px solid var(--att-border); }
    .att-table td { padding:0.7rem 1rem;color:var(--att-ink);border-bottom:1px solid #f1f5f9;vertical-align:top; }
    .att-badge { display:inline-block;padding:0.2rem 0.5rem;border-radius:999px;font-size:0.72rem;font-weight:700;background:#eef2ff;color:var(--att-indigo); }
    .att-photo-count { font-size:0.78rem;font-weight:700;color:var(--att-teal); }
    .att-remark { font-size:0.8rem;color:var(--att-muted);max-width:16rem; }
    .att-thumb { width:40px;height:40px;object-fit:cover;border-radius:6px;border:1px solid var(--att-border); }
    .att-empty { padding:2.5rem 1rem;text-align:center;color:var(--att-muted); }
    @media (max-width:640px) { .att-grid { grid-template-columns:1fr; } }
</style>
@endpush

@section('content')
<div class="att-shell">

    @if (!empty($migrationMissing))
        <div style="background:#fffbeb;border:1px solid #fde68a;color:#92400e;border-radius:12px;padding:0.85rem 1rem;font-size:0.88rem;">
            <strong>Table not found.</strong> Run <code>php artisan migrate</code>.
        </div>
    @endif

    @if (session('status'))
        <div style="background:#f0fdf4;border:1px solid #86efac;color:#166534;border-radius:12px;padding:0.85rem 1rem;font-size:0.88rem;">
            <i class="fa-solid fa-circle-check"></i> {{ session('status') }}
        </div>
    @endif
    @if ($errors->any())
        <div style="background:#fef2f2;border:1px solid #fca5a5;color:#991b1b;border-radius:12px;padding:0.85rem 1rem;font-size:0.88rem;">
            <ul style="margin:0.4rem 0 0 1.1rem;padding:0;">@foreach ($errors->all() as $e)<li>{{ $e }}</li>@endforeach</ul>
        </div>
    @endif

    <div class="att-banner">
        <div class="att-banner__icon"><i class="fa-solid fa-camera"></i></div>
        <div class="att-banner__body">
            <h2>Field visit photos</h2>
            <p>Visit details, photos, and a filled attendance Excel sheet when participants are reported.</p>
        </div>
    </div>

    @if (empty($gramPanchayatsEnabled))
        <div style="background:#fffbeb;border:1px solid #fde68a;color:#92400e;border-radius:12px;padding:0.85rem 1rem;font-size:0.88rem;">
            Import gram panchayats: <code>php artisan gram-panchayats:import path/to/your.csv</code>
        </div>
    @endif

    <div class="att-card">
        <div class="att-card__head">
            <div class="att-card__head-icon"><i class="fa-solid fa-cloud-arrow-up"></i></div>
            <h3>Upload visit photos</h3>
        </div>
        <div class="att-card__body">
            <form method="post" action="{{ route('staff.attendance.store') }}" enctype="multipart/form-data">
                @csrf
                <p class="att-section-label">Visit details</p>
                <div class="att-grid">
                    <div class="att-field">
                        <label>Field coordinator</label>
                        <input type="text" value="{{ auth()->user()->name }}" readonly class="att-input att-input--readonly">
                    </div>
                    <div class="att-field">
                        <label>District</label>
                        <input type="text" value="{{ auth()->user()->district?->name ?? 'Not assigned' }}" readonly class="att-input att-input--readonly">
                    </div>
                    <div class="att-field">
                        <label>Visit date <span class="att-req">*</span></label>
                        <input type="date" name="visit_date" value="{{ old('visit_date', now()->toDateString()) }}" required class="att-input" max="{{ now()->toDateString() }}">
                    </div>
                </div>

                <p class="att-section-label" style="margin-top:1.4rem;">Location</p>
                <div class="att-grid">
                    <div class="att-field">
                        <label>Block <span class="att-req">*</span></label>
                        <select name="district_block_id" id="attBlockSelect" class="att-input" required @if($blockRows->isEmpty()) disabled @endif>
                            <option value="">— Select block —</option>
                            @foreach ($blockRows as $block)
                                <option value="{{ $block->id }}" @selected((int) old('district_block_id') === (int) $block->id)>{{ $block->name }}</option>
                            @endforeach
                        </select>
                    </div>
                    <div class="att-field">
                        <label>Gram panchayat <span class="att-req">*</span></label>
                        <input type="search" id="attGpSearch" class="att-input" placeholder="Filter list…" disabled style="margin-bottom:0.35rem;">
                        <select name="gram_panchayat_id" id="attGpSelect" class="att-input" required disabled>
                            <option value="">— Select block first —</option>
                        </select>
                        <span id="attGpHint" style="font-size:0.72rem;color:var(--att-muted);"></span>
                    </div>
                    <div class="att-field">
                        <label>Area / village <span class="att-req">*</span></label>
                        <input type="text" name="area" value="{{ old('area') }}" required class="att-input" placeholder="Village or area name visited">
                    </div>
                </div>

                <p class="att-section-label" style="margin-top:1.4rem;">Participants</p>
                <div class="att-grid">
                    <div class="att-field">
                        <label>Male <span class="att-req">*</span></label>
                        <input type="number" name="participants_male_count" id="attMaleCount" value="{{ old('participants_male_count', 0) }}" min="0" required class="att-input">
                    </div>
                    <div class="att-field">
                        <label>Female <span class="att-req">*</span></label>
                        <input type="number" name="participants_female_count" id="attFemaleCount" value="{{ old('participants_female_count', 0) }}" min="0" required class="att-input">
                    </div>
                    <div class="att-field">
                        <label>Total participants</label>
                        <input type="number" id="attTotalParticipants" value="{{ (int) old('participants_male_count', 0) + (int) old('participants_female_count', 0) }}" readonly class="att-input att-input--readonly">
                    </div>
                </div>

                <div class="att-field" style="margin-top:1.2rem;">
                    <label>Remark</label>
                    <textarea name="remark" class="att-textarea" rows="3" placeholder="Optional note about this visit">{{ old('remark') }}</textarea>
                </div>

                <div style="margin-top:1.2rem;">
                    <label style="font-size:0.78rem;font-weight:600;">Photos <span class="att-req">*</span> <span style="font-weight:400;color:var(--att-muted);">(up to 15, 5 MB each)</span></label>
                    <div class="att-file-wrap" style="margin-top:0.4rem;">
                        <i class="fa-solid fa-images"></i>
                        <span>Choose photos from this gram panchayat visit</span>
                        <input type="file" name="visit_media[]" accept=".jpg,.jpeg,.png,.webp,image/*" multiple required>
                    </div>
                </div>

                <div id="attSheetSection" style="margin-top:1.2rem;display:none;">
                    <p class="att-section-label">Attendance sheet (Excel) <span style="font-weight:400;text-transform:none;letter-spacing:0;color:var(--att-muted);">— optional</span></p>
                    <p style="font-size:0.8rem;color:var(--att-muted);margin:0 0 0.75rem;">
                        Not required now — submit the visit with photos first. Download the template (one row per participant) and upload the filled sheet anytime from <strong>My submissions</strong> below.
                    </p>
                    <div style="display:flex;flex-wrap:wrap;gap:0.65rem;align-items:center;margin-bottom:0.75rem;">
                        <a href="#" id="attDownloadTemplate" class="att-btn" style="text-decoration:none;background:linear-gradient(135deg,var(--att-teal),#0f766e);">
                            <i class="fa-solid fa-file-excel"></i> <span id="attDownloadTemplateLabel">Download template</span>
                        </a>
                    </div>
                    <label style="font-size:0.78rem;font-weight:600;">Upload filled sheet now <span style="font-weight:400;color:var(--att-muted);">(optional)</span></label>
                    <div class="att-file-wrap" style="margin-top:0.4rem;">
                        <i class="fa-solid fa-file-excel"></i>
                        <span>If uploading now: .xlsx must match total participants exactly</span>
                        <input type="file" name="attendance_sheet" id="attSheetInput" accept=".xlsx,.xls,.csv,application/vnd.openxmlformats-officedocument.spreadsheetml.sheet,text/csv">
                    </div>
                </div>

                <div class="att-submit-row">
                    <button type="submit" class="att-btn" @if(empty($gramPanchayatsEnabled) || $blockRows->isEmpty()) disabled @endif>
                        <i class="fa-solid fa-cloud-arrow-up"></i> Submit
                    </button>
                </div>
            </form>
        </div>
    </div>

    <div class="att-table-wrap">
        <p style="font-size:0.82rem;color:var(--att-muted);margin:0 0 0.5rem 1.4rem;">
            <strong>New visit?</strong> Download the template in the form above. For past visits, use <strong>Download template</strong> in the table.
        </p>
        <div class="att-table-head">
            <div class="att-card__head-icon" style="background:linear-gradient(135deg,#ccfbf1,#a7f3d0);color:var(--att-teal);"><i class="fa-solid fa-clock-rotate-left"></i></div>
            <h3 style="margin:0;font-size:0.95rem;font-weight:700;">My submissions</h3>
        </div>
        <div style="overflow-x:auto;">
            <table class="att-table">
                <thead>
                    <tr>
                        <th>Date</th>
                        <th>District</th>
                        <th>Block</th>
                        <th>Gram panchayat</th>
                        <th>Area / village</th>
                        <th>Participants</th>
                        <th>Attendance sheet</th>
                        <th>Photos</th>
                        <th>Remark</th>
                        <th>Actions</th>
                    </tr>
                </thead>
                <tbody>
                    @forelse ($reports as $row)
                        @php $media = $row->visitMediaItems(); @endphp
                        <tr>
                            <td><span class="att-badge">{{ $row->visit_date?->format('d M Y') }}</span></td>
                            <td>{{ $row->district?->name ?? '—' }}</td>
                            <td>{{ $row->block ?: '—' }}</td>
                            <td>{{ $row->gramPanchayat?->name ?? ($row->isLegacyNumericReport() ? '— (legacy)' : '—') }}</td>
                            <td>{{ $row->area ?: '—' }}</td>
                            <td style="font-size:0.82rem;">
                                @if ((int) $row->participants_total > 0 || (int) $row->participants_male_count > 0 || (int) $row->participants_female_count > 0)
                                    <strong>{{ number_format((int) $row->participants_total) }}</strong> total<br>
                                    <span style="color:var(--att-muted);">M {{ number_format((int) $row->participants_male_count) }} · F {{ number_format((int) $row->participants_female_count) }}</span>
                                @else
                                    —
                                @endif
                            </td>
                            <td style="font-size:0.82rem;">
                                @if ((int) $row->participants_total > 0)
                                    @if ($row->hasAttendanceSheet())
                                        <a href="{{ route('staff.attendance.sheet.download', $row) }}" style="color:var(--att-teal);font-weight:700;">
                                            <i class="fa-solid fa-file-excel"></i> View sheet
                                        </a>
                                    @else
                                        <span style="display:inline-block;padding:0.15rem 0.45rem;border-radius:999px;background:#fffbeb;color:#b45309;font-size:0.68rem;font-weight:700;margin-bottom:0.35rem;">Sheet pending</span><br>
                                        <a href="{{ route('staff.attendance.sheet-template.report', $row) }}" class="att-btn" style="padding:0.35rem 0.65rem;font-size:0.75rem;text-decoration:none;background:linear-gradient(135deg,var(--att-teal),#0f766e);margin-bottom:0.35rem;">
                                            <i class="fa-solid fa-download"></i> Download template ({{ number_format((int) $row->participants_total) }} rows)
                                        </a>
                                        <form method="post" action="{{ route('staff.attendance.sheet.upload', $row) }}" enctype="multipart/form-data" style="display:flex;flex-direction:column;gap:0.35rem;min-width:11rem;">
                                            @csrf
                                            <input type="file" name="attendance_sheet" accept=".xlsx,.xls,.csv" required style="font-size:0.75rem;">
                                            <button type="submit" class="att-btn" style="padding:0.35rem 0.6rem;font-size:0.75rem;">Upload filled sheet</button>
                                        </form>
                                    @endif
                                @else
                                    <span style="color:var(--att-muted);">N/A</span>
                                @endif
                            </td>
                            <td>
                                @if ($media !== [])
                                    <span class="att-photo-count">{{ count($media) }} photo(s)</span>
                                    <div style="display:flex;gap:0.25rem;margin-top:0.35rem;flex-wrap:wrap;">
                                        @foreach (array_slice($media, 0, 4) as $idx => $item)
                                            <a href="{{ route('staff.attendance.attachment', ['attendanceReport' => $row, 'index' => $idx, 'inline' => 1]) }}" target="_blank" rel="noopener">
                                                <img class="att-thumb" src="{{ route('staff.attendance.attachment', ['attendanceReport' => $row, 'index' => $idx, 'inline' => 1]) }}" alt="">
                                            </a>
                                        @endforeach
                                    </div>
                                @elseif ($row->attachment_path)
                                    <a href="{{ route('staff.attendance.attachment', $row) }}">Legacy file</a>
                                @else
                                    —
                                @endif
                            </td>
                            <td><span class="att-remark">{{ $row->remark ?: '—' }}</span></td>
                            <td style="white-space:nowrap;">
                                <a href="{{ route('staff.attendance.edit', $row) }}" style="display:inline-flex;align-items:center;gap:0.25rem;font-size:0.78rem;font-weight:700;color:#4f46e5;text-decoration:none;margin-right:0.5rem;">
                                    <i class="fa-solid fa-pen"></i> Edit
                                </a>
                                <form method="post" action="{{ route('staff.attendance.destroy', $row) }}" style="display:inline;" onsubmit="return confirm('Delete this visit submission?');">
                                    @csrf
                                    @method('DELETE')
                                    <button type="submit" style="background:none;border:none;padding:0;font-size:0.78rem;font-weight:700;color:#dc2626;cursor:pointer;display:inline-flex;align-items:center;gap:0.25rem;">
                                        <i class="fa-solid fa-trash"></i> Delete
                                    </button>
                                </form>
                            </td>
                        </tr>
                    @empty
                        <tr><td colspan="10"><div class="att-empty">No submissions yet.</div></td></tr>
                    @endforelse
                </tbody>
            </table>
        </div>
    </div>

    @if ($reports instanceof \Illuminate\Contracts\Pagination\Paginator && $reports->hasPages())
        <div>{{ $reports->links() }}</div>
    @endif
</div>
@endsection

@push('scripts')
<script>
(function () {
    const blockSelect = document.getElementById('attBlockSelect');
    const gpSelect = document.getElementById('attGpSelect');
    const gpSearch = document.getElementById('attGpSearch');
    const gpHint = document.getElementById('attGpHint');
    const gpUrl = @json(route('staff.attendance.gram-panchayats'));
    const oldGpId = @json((int) old('gram_panchayat_id'));
    let allItems = [];

    if (!blockSelect || !gpSelect) return;

    function renderGpOptions(items) {
        const q = (gpSearch?.value || '').trim().toLowerCase();
        const filtered = q === '' ? items : items.filter(i => i.name.toLowerCase().includes(q));
        gpSelect.innerHTML = '<option value="">— Select gram panchayat —</option>';
        filtered.forEach(i => {
            const opt = document.createElement('option');
            opt.value = String(i.id);
            opt.textContent = i.name;
            if (oldGpId && Number(i.id) === oldGpId) opt.selected = true;
            gpSelect.appendChild(opt);
        });
        if (gpHint) gpHint.textContent = filtered.length ? filtered.length + ' gram panchayat(s)' : 'No match — try another search';
    }

    async function loadGramPanchayats(blockId) {
        gpSelect.disabled = true;
        gpSearch.disabled = true;
        gpSelect.innerHTML = '<option value="">Loading…</option>';
        if (!blockId) {
            gpSelect.innerHTML = '<option value="">— Select block first —</option>';
            if (gpHint) gpHint.textContent = '';
            return;
        }
        try {
            const res = await fetch(gpUrl + '?district_block_id=' + encodeURIComponent(blockId), {
                headers: { 'Accept': 'application/json', 'X-Requested-With': 'XMLHttpRequest' },
            });
            const data = await res.json();
            allItems = Array.isArray(data.items) ? data.items : [];
            gpSelect.disabled = allItems.length === 0;
            gpSearch.disabled = allItems.length === 0;
            renderGpOptions(allItems);
        } catch (e) {
            gpSelect.innerHTML = '<option value="">Could not load list</option>';
            if (gpHint) gpHint.textContent = 'Network error — try again';
        }
    }

    blockSelect.addEventListener('change', () => loadGramPanchayats(blockSelect.value));
    gpSearch?.addEventListener('input', () => renderGpOptions(allItems));

    if (blockSelect.value) loadGramPanchayats(blockSelect.value);

    const maleInput = document.getElementById('attMaleCount');
    const femaleInput = document.getElementById('attFemaleCount');
    const totalInput = document.getElementById('attTotalParticipants');
    const sheetSection = document.getElementById('attSheetSection');
    const sheetInput = document.getElementById('attSheetInput');
    const downloadBtn = document.getElementById('attDownloadTemplate');
    const downloadLabel = document.getElementById('attDownloadTemplateLabel');
    const templateBaseUrl = @json(route('staff.attendance.sheet-template'));

    function updateParticipantTotal() {
        if (!totalInput) return;
        const male = parseInt(maleInput?.value || '0', 10) || 0;
        const female = parseInt(femaleInput?.value || '0', 10) || 0;
        const total = male + female;
        totalInput.value = String(total);

        if (sheetSection) {
            sheetSection.style.display = total > 0 ? 'block' : 'none';
        }
        if (downloadLabel) {
            downloadLabel.textContent = total > 0
                ? 'Download template (' + total + ' rows)'
                : 'Download template';
        }
    }

    function buildTemplateUrl() {
        const blockId = blockSelect?.value || '';
        const gpId = gpSelect?.value || '';
        const male = parseInt(maleInput?.value || '0', 10) || 0;
        const female = parseInt(femaleInput?.value || '0', 10) || 0;
        if (!blockId || !gpId || (male + female) <= 0) return null;
        const params = new URLSearchParams({
            district_block_id: blockId,
            gram_panchayat_id: gpId,
            participants_male_count: String(male),
            participants_female_count: String(female),
        });
        return templateBaseUrl + '?' + params.toString();
    }

    downloadBtn?.addEventListener('click', (e) => {
        e.preventDefault();
        const url = buildTemplateUrl();
        if (!url) {
            alert('Select block, gram panchayat, and enter participant counts first.');
            return;
        }
        window.location.href = url;
    });

    maleInput?.addEventListener('input', updateParticipantTotal);
    femaleInput?.addEventListener('input', updateParticipantTotal);
    blockSelect?.addEventListener('change', updateParticipantTotal);
    gpSelect?.addEventListener('change', updateParticipantTotal);
    updateParticipantTotal();
})();
</script>
@endpush
