@php
    $maleInputId = $maleInputId ?? 'attendance_male_count';
    $femaleInputId = $femaleInputId ?? 'attendance_female_count';
    $formId = $formId ?? null;
    $gramPanchayatsUrl = $gramPanchayatsUrl ?? '';
    $districtLabel = (string) ($districtLabel ?? '—');
    $initialRows = $initialRows ?? [];
    $defaultBlockId = (int) ($defaultBlockId ?? 0);
    $defaultGpId = (int) ($defaultGpId ?? 0);
@endphp
@push('scripts')
<script>
(function () {
    const gramPanchayatsUrl = @json($gramPanchayatsUrl);
    const districtLabel = @json($districtLabel);
    const initialRows = @json($initialRows);
    const defaultBlockId = @json($defaultBlockId);
    const defaultGpId = @json($defaultGpId);
    const formId = @json($formId);

    const blockSelect = document.getElementById('wsBlockSelect');
    const gpSelect = document.getElementById('wsGpSelect');
    const gpSearch = document.getElementById('wsGpSearch');
    const gpHint = document.getElementById('wsGpHint');
    const maleInput = document.getElementById(@json($maleInputId));
    const femaleInput = document.getElementById(@json($femaleInputId));
    const participantSection = document.getElementById('wsParticipantSection');
    const participantBody = document.getElementById('wsParticipantBody');
    const participantEmpty = document.getElementById('wsParticipantEmpty');
    const workshopForm = formId ? document.getElementById(formId) : document.querySelector('form[method="post"]');

    let gpOptions = [];
    let rows = Array.isArray(initialRows) ? initialRows.slice() : [];

    function blockName() {
        if (!blockSelect) return '';
        const opt = blockSelect.selectedOptions[0];
        return opt && opt.value ? opt.textContent.trim() : '';
    }

    function defaultGpIdVal() {
        return gpSelect?.value ? Number(gpSelect.value) : null;
    }

    function defaultGpName() {
        if (!gpSelect) return '';
        const opt = gpSelect.selectedOptions[0];
        return opt && opt.value ? opt.textContent.trim() : '';
    }

    function participantTotal() {
        const m = parseInt(maleInput?.value || '0', 10) || 0;
        const f = parseInt(femaleInput?.value || '0', 10) || 0;
        return m + f;
    }

    function escapeHtml(s) {
        return String(s).replace(/&/g,'&amp;').replace(/</g,'&lt;').replace(/"/g,'&quot;');
    }

    function buildDefaultRows() {
        const m = parseInt(maleInput?.value || '0', 10) || 0;
        const f = parseInt(femaleInput?.value || '0', 10) || 0;
        const total = m + f;
        const out = [];
        const gpId = defaultGpIdVal();
        const gpName = defaultGpName();
        const blk = blockName();
        for (let i = 0; i < total; i++) {
            const prev = rows[i] || {};
            let gender = (prev.gender || '').toUpperCase();
            if (gender !== 'M' && gender !== 'F') {
                gender = i < m ? 'M' : (i < m + f ? 'F' : '');
            }
            out.push({
                sr: i + 1,
                name: prev.name || '',
                mobile: prev.mobile || '',
                gender,
                district_name: prev.district_name || districtLabel,
                block_name: prev.block_name || blk,
                gram_panchayat_id: prev.gram_panchayat_id || gpId,
                gram_panchayat_name: prev.gram_panchayat_name || gpName,
            });
        }
        return out;
    }

    function gpSelectHtml(selectedId, selectedName, rowIndex) {
        let html = '<select class="ws-part-input ws-row-gp" data-row="' + rowIndex + '">';
        html += '<option value="">—</option>';
        const sid = selectedId ? Number(selectedId) : 0;
        let found = false;
        gpOptions.forEach(function (gp) {
            const sel = sid && Number(gp.id) === sid ? ' selected' : '';
            if (sel) found = true;
            html += '<option value="' + gp.id + '"' + sel + '>' + escapeHtml(gp.name) + '</option>';
        });
        if (sid && !found && selectedName) {
            html += '<option value="' + sid + '" selected>' + escapeHtml(selectedName) + '</option>';
        }
        html += '</select>';
        return html;
    }

    function renderParticipantTable() {
        const total = participantTotal();
        if (!participantSection || !participantBody) return;

        if (total <= 0) {
            participantSection.style.display = 'none';
            if (participantEmpty) participantEmpty.style.display = 'block';
            rows = [];
            return;
        }

        participantSection.style.display = 'block';
        if (participantEmpty) participantEmpty.style.display = 'none';

        rows = buildDefaultRows();
        participantBody.innerHTML = '';

        rows.forEach(function (row, idx) {
            const tr = document.createElement('tr');
            const gM = row.gender === 'M' ? ' checked' : '';
            const gF = row.gender === 'F' ? ' checked' : '';
            tr.innerHTML =
                '<td class="ws-participants__sr">' + (idx + 1) + '</td>' +
                '<td><input type="text" class="ws-part-input ws-row-name" maxlength="191" value="' + escapeHtml(row.name) + '" placeholder="Name"></td>' +
                '<td><input type="tel" class="ws-part-input ws-row-mobile" maxlength="15" value="' + escapeHtml(row.mobile) + '" placeholder="10-digit"></td>' +
                '<td><div class="ws-gender-pills">' +
                    '<label class="ws-gender-pill"><input type="radio" name="ws_gender_' + idx + '" value="M"' + gM + '> M</label>' +
                    '<label class="ws-gender-pill ws-gender-pill--f"><input type="radio" name="ws_gender_' + idx + '" value="F"' + gF + '> F</label>' +
                '</div></td>' +
                '<td><span class="ws-participants__readonly ws-row-district">' + escapeHtml(row.district_name || districtLabel) + '</span></td>' +
                '<td><span class="ws-participants__readonly ws-row-block">' + escapeHtml(row.block_name || blockName()) + '</span></td>' +
                '<td>' + gpSelectHtml(row.gram_panchayat_id, row.gram_panchayat_name, idx) + '</td>';
            participantBody.appendChild(tr);
        });
    }

    function collectRowsFromDom() {
        if (!participantBody) return [];
        const out = [];
        participantBody.querySelectorAll('tr').forEach(function (tr, idx) {
            const genderEl = tr.querySelector('input[type=radio]:checked');
            const gpEl = tr.querySelector('.ws-row-gp');
            const gpOpt = gpEl?.selectedOptions[0];
            out.push({
                sr: idx + 1,
                name: tr.querySelector('.ws-row-name')?.value?.trim() || '',
                mobile: tr.querySelector('.ws-row-mobile')?.value?.trim() || '',
                gender: genderEl ? genderEl.value : '',
                district_name: tr.querySelector('.ws-row-district')?.textContent?.trim() || districtLabel,
                block_name: tr.querySelector('.ws-row-block')?.textContent?.trim() || blockName(),
                gram_panchayat_id: gpEl?.value ? Number(gpEl.value) : null,
                gram_panchayat_name: gpOpt && gpOpt.value ? gpOpt.textContent.trim() : '',
            });
        });
        rows = out;
        return out;
    }

    function syncParticipantHiddenInputs() {
        if (!workshopForm) return;
        workshopForm.querySelectorAll('.js-ws-participant-hidden').forEach(function (el) { el.remove(); });
        const collected = collectRowsFromDom();
        collected.forEach(function (row, idx) {
            const fields = {
                name: row.name,
                mobile: row.mobile,
                gender: row.gender,
                district_name: row.district_name,
                block_name: row.block_name,
                gram_panchayat_id: row.gram_panchayat_id || '',
                gram_panchayat_name: row.gram_panchayat_name,
            };
            Object.keys(fields).forEach(function (key) {
                const input = document.createElement('input');
                input.type = 'hidden';
                input.className = 'js-ws-participant-hidden';
                input.name = 'participants[' + idx + '][' + key + ']';
                input.value = fields[key] === null || fields[key] === undefined ? '' : String(fields[key]);
                workshopForm.appendChild(input);
            });
        });
    }

    async function loadGramPanchayats(blockId, preselectGpId) {
        if (!gramPanchayatsUrl || !gpSelect) return;
        gpSelect.disabled = true;
        gpSelect.innerHTML = '<option value="">Loading…</option>';
        if (gpSearch) gpSearch.disabled = true;
        if (gpHint) gpHint.textContent = '';

        try {
            const url = gramPanchayatsUrl + '?district_block_id=' + encodeURIComponent(String(blockId));
            const res = await fetch(url, { headers: { 'Accept': 'application/json', 'X-Requested-With': 'XMLHttpRequest' } });
            const data = await res.json();
            if (!res.ok) throw new Error('Failed');
            gpOptions = Array.isArray(data.items) ? data.items : [];
            gpSelect.innerHTML = '<option value="">— Select gram panchayat —</option>';
            gpOptions.forEach(function (gp) {
                const opt = document.createElement('option');
                opt.value = String(gp.id);
                opt.textContent = gp.name;
                if (preselectGpId && Number(gp.id) === Number(preselectGpId)) opt.selected = true;
                gpSelect.appendChild(opt);
            });
            gpSelect.disabled = gpOptions.length === 0;
            if (gpSearch) gpSearch.disabled = gpOptions.length === 0;
            if (gpHint) {
                gpHint.textContent = gpOptions.length ? gpOptions.length + ' gram panchayat(s)' : 'No gram panchayats for this block';
            }
            renderParticipantTable();
        } catch (e) {
            gpSelect.innerHTML = '<option value="">Could not load</option>';
            if (gpHint) gpHint.textContent = 'Could not load gram panchayats';
        }
    }

    if (blockSelect) {
        blockSelect.addEventListener('change', function () {
            const blockId = blockSelect.value ? Number(blockSelect.value) : 0;
            if (blockId > 0) {
                loadGramPanchayats(blockId, null);
            } else {
                gpOptions = [];
                if (gpSelect) {
                    gpSelect.innerHTML = '<option value="">— Select block first —</option>';
                    gpSelect.disabled = true;
                }
                if (gpSearch) gpSearch.disabled = true;
            }
            renderParticipantTable();
        });
    }

    if (gpSelect) {
        gpSelect.addEventListener('change', function () {
            renderParticipantTable();
        });
    }

    if (maleInput) maleInput.addEventListener('input', renderParticipantTable);
    if (femaleInput) femaleInput.addEventListener('input', renderParticipantTable);

    if (workshopForm) {
        workshopForm.addEventListener('submit', function () {
            syncParticipantHiddenInputs();
        });
    }

    if (defaultBlockId > 0 && blockSelect) {
        blockSelect.value = String(defaultBlockId);
        loadGramPanchayats(defaultBlockId, defaultGpId > 0 ? defaultGpId : null);
    }

    renderParticipantTable();
}());
</script>
@endpush
