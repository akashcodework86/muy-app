@php
    $scriptDistrictLabel = (string) ($user->district?->name ?? '—');
    $scriptInitialRows = $activeDraft?->participantRows() ?? [];
@endphp
<script>
(function () {
    const draftWorkflow = @json($draftWorkflow ?? false);
    if (!draftWorkflow) return;

    const csrf = @json(csrf_token());
    const districtLabel = @json($scriptDistrictLabel);
    const routes = {
        createDraft: @json(route('staff.attendance.draft.create')),
        draftMeta: @json(route('staff.attendance.draft.meta', ['attendanceReport' => '__ID__'])),
        participants: @json(route('staff.attendance.participants.save', ['attendanceReport' => '__ID__'])),
    };
    const initialDraftId = @json($activeDraft?->id);
    const initialRows = @json($scriptInitialRows);
    const gramPanchayatsUrl = @json(route('staff.attendance.gram-panchayats'));

    const blockSelect = document.getElementById('attBlockSelect');
    const gpSelect = document.getElementById('attGpSelect');
    const maleInput = document.getElementById('attMaleCount');
    const femaleInput = document.getElementById('attFemaleCount');
    const participantSection = document.getElementById('attParticipantSection');
    const participantBody = document.getElementById('attParticipantBody');
    const participantEmpty = document.getElementById('attParticipantEmpty');
    const autosaveStatus = document.getElementById('attAutosaveStatus');
    const workshopForm = document.getElementById('attWorkshopForm');

    let draftId = initialDraftId ? Number(initialDraftId) : null;
    let gpOptions = [];
    let rows = Array.isArray(initialRows) ? initialRows.slice() : [];
    let metaTimer = null;
    let participantsTimer = null;
    let pendingMeta = false;
    let pendingParticipants = false;
    let ensuringDraft = null;

    function routeFor(template, id) {
        return template.replace('__ID__', String(id));
    }

    function setAutosave(state, text) {
        if (!autosaveStatus) return;
        autosaveStatus.className = 'att-autosave att-autosave--' + state;
        autosaveStatus.textContent = text;
    }

    function blockName() {
        if (!blockSelect) return '';
        const opt = blockSelect.selectedOptions[0];
        return opt && opt.value ? opt.textContent.trim() : '';
    }

    function defaultGpId() {
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

    function buildDefaultRows() {
        const m = parseInt(maleInput?.value || '0', 10) || 0;
        const f = parseInt(femaleInput?.value || '0', 10) || 0;
        const total = m + f;
        const out = [];
        const gpId = defaultGpId();
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
        let html = '<select class="att-input att-row-gp" data-row="' + rowIndex + '">';
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

    function escapeHtml(s) {
        return String(s).replace(/&/g,'&amp;').replace(/</g,'&lt;').replace(/"/g,'&quot;');
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
            tr.dataset.rowIndex = String(idx);
            const gM = row.gender === 'M' ? ' checked' : '';
            const gF = row.gender === 'F' ? ' checked' : '';
            tr.innerHTML =
                '<td class="att-participants__sr">' + (idx + 1) + '</td>' +
                '<td><input type="text" class="att-input att-row-name" maxlength="191" value="' + escapeHtml(row.name) + '" placeholder="Name"></td>' +
                '<td><input type="tel" class="att-input att-row-mobile" maxlength="15" value="' + escapeHtml(row.mobile) + '" placeholder="10-digit"></td>' +
                '<td><div class="att-gender-pills">' +
                    '<label class="att-gender-pill"><input type="radio" name="gender_' + idx + '" value="M"' + gM + '> M</label>' +
                    '<label class="att-gender-pill att-gender-pill--f"><input type="radio" name="gender_' + idx + '" value="F"' + gF + '> F</label>' +
                '</div></td>' +
                '<td><span class="att-participants__readonly att-row-district">' + escapeHtml(row.district_name || districtLabel) + '</span></td>' +
                '<td><span class="att-participants__readonly att-row-block">' + escapeHtml(row.block_name || blockName()) + '</span></td>' +
                '<td>' + gpSelectHtml(row.gram_panchayat_id, row.gram_panchayat_name, idx) + '</td>';
            participantBody.appendChild(tr);
        });

        bindRowEvents();
    }

    function collectRowsFromDom() {
        if (!participantBody) return [];
        const out = [];
        participantBody.querySelectorAll('tr').forEach(function (tr, idx) {
            const genderEl = tr.querySelector('input[type=radio]:checked');
            const gpEl = tr.querySelector('.att-row-gp');
            const gpOpt = gpEl?.selectedOptions[0];
            out.push({
                sr: idx + 1,
                name: tr.querySelector('.att-row-name')?.value?.trim() || '',
                mobile: tr.querySelector('.att-row-mobile')?.value?.trim() || '',
                gender: genderEl ? genderEl.value : '',
                district_name: tr.querySelector('.att-row-district')?.textContent?.trim() || districtLabel,
                block_name: tr.querySelector('.att-row-block')?.textContent?.trim() || blockName(),
                gram_panchayat_id: gpEl?.value ? Number(gpEl.value) : null,
                gram_panchayat_name: gpOpt && gpOpt.value ? gpOpt.textContent.trim() : '',
            });
        });
        rows = out;
        return out;
    }

    function bindRowEvents() {
        if (!participantBody) return;
        participantBody.querySelectorAll('input, select').forEach(function (el) {
            el.addEventListener('input', scheduleParticipantsSave);
            el.addEventListener('change', scheduleParticipantsSave);
        });
    }

    async function ensureDraft() {
        if (draftId) return draftId;
        if (ensuringDraft) return ensuringDraft;
        ensuringDraft = fetch(routes.createDraft, {
            method: 'POST',
            headers: {
                'Accept': 'application/json',
                'Content-Type': 'application/json',
                'X-CSRF-TOKEN': csrf,
                'X-Requested-With': 'XMLHttpRequest',
            },
            body: '{}',
        }).then(async function (res) {
            const data = await res.json();
            if (!res.ok) throw new Error('Could not start draft');
            draftId = Number(data.id);
            if (workshopForm) {
                workshopForm.action = routeFor(@json(route('staff.attendance.draft.submit', ['attendanceReport' => '__ID__'])), draftId);
            }
            return draftId;
        }).finally(function () {
            ensuringDraft = null;
        });
        return ensuringDraft;
    }

    function metaPayload() {
        return {
            visit_date: document.querySelector('[name=visit_date]')?.value || null,
            district_block_id: blockSelect?.value ? Number(blockSelect.value) : null,
            gram_panchayat_id: gpSelect?.value ? Number(gpSelect.value) : null,
            area: document.querySelector('[name=area]')?.value || null,
            participants_male_count: parseInt(maleInput?.value || '0', 10) || 0,
            participants_female_count: parseInt(femaleInput?.value || '0', 10) || 0,
            remark: document.querySelector('[name=remark]')?.value || null,
        };
    }

    function scheduleMetaSave() {
        pendingMeta = true;
        clearTimeout(metaTimer);
        setAutosave('saving', 'Saving…');
        metaTimer = setTimeout(doMetaSave, 800);
    }

    async function doMetaSave() {
        const total = participantTotal();
        if (total <= 0) {
            pendingMeta = false;
            setAutosave('idle', 'All changes save automatically');
            return;
        }
        try {
            await ensureDraft();
            const res = await fetch(routeFor(routes.draftMeta, draftId), {
                method: 'PATCH',
                headers: {
                    'Accept': 'application/json',
                    'Content-Type': 'application/json',
                    'X-CSRF-TOKEN': csrf,
                    'X-Requested-With': 'XMLHttpRequest',
                },
                body: JSON.stringify(metaPayload()),
            });
            const data = await res.json();
            if (!res.ok) throw new Error('Meta save failed');
            if (Array.isArray(data.participants)) {
                rows = data.participants;
            }
            pendingMeta = false;
            if (!pendingParticipants) setAutosave('saved', 'Saved');
        } catch (e) {
            pendingMeta = false;
            setAutosave('error', 'Save failed — retrying…');
        }
    }

    function scheduleParticipantsSave() {
        pendingParticipants = true;
        clearTimeout(participantsTimer);
        setAutosave('saving', 'Saving…');
        participantsTimer = setTimeout(doParticipantsSave, 1000);
    }

    async function doParticipantsSave() {
        const total = participantTotal();
        if (total <= 0) {
            pendingParticipants = false;
            return;
        }
        try {
            await ensureDraft();
            collectRowsFromDom();
            const res = await fetch(routeFor(routes.participants, draftId), {
                method: 'PATCH',
                headers: {
                    'Accept': 'application/json',
                    'Content-Type': 'application/json',
                    'X-CSRF-TOKEN': csrf,
                    'X-Requested-With': 'XMLHttpRequest',
                },
                body: JSON.stringify({ participants: rows }),
            });
            if (!res.ok) throw new Error('Participants save failed');
            pendingParticipants = false;
            if (!pendingMeta) setAutosave('saved', 'Saved');
        } catch (e) {
            pendingParticipants = false;
            setAutosave('error', 'Save failed');
        }
    }

    async function syncParticipantSection() {
        const total = participantTotal();
        if (total <= 0) {
            renderParticipantTable();
            return;
        }
        await ensureDraft();
        renderParticipantTable();
        scheduleMetaSave();
    }

    async function loadGpOptionsForBlock(blockId) {
        if (!blockId) {
            gpOptions = [];
            return;
        }
        try {
            const res = await fetch(gramPanchayatsUrl + '?district_block_id=' + encodeURIComponent(blockId), {
                headers: { 'Accept': 'application/json', 'X-Requested-With': 'XMLHttpRequest' },
            });
            const data = await res.json();
            gpOptions = Array.isArray(data.items) ? data.items : [];
        } catch (e) {
            gpOptions = [];
        }
    }

    maleInput?.addEventListener('input', function () {
        syncParticipantSection();
    });
    femaleInput?.addEventListener('input', function () {
        syncParticipantSection();
    });

    blockSelect?.addEventListener('change', function () {
        loadGpOptionsForBlock(blockSelect.value).then(function () {
            participantBody?.querySelectorAll('.att-row-block').forEach(function (el) {
                el.textContent = blockName();
            });
            scheduleMetaSave();
            if (participantTotal() > 0) renderParticipantTable();
        });
    });

    gpSelect?.addEventListener('change', scheduleMetaSave);

    ['visit_date', 'area', 'remark'].forEach(function (name) {
        const el = document.querySelector('[name=' + name + ']');
        el?.addEventListener('change', scheduleMetaSave);
        el?.addEventListener('blur', scheduleMetaSave);
    });

    window.addEventListener('beforeunload', function (e) {
        if (pendingMeta || pendingParticipants) {
            e.preventDefault();
            e.returnValue = '';
        }
    });

    if (workshopForm && draftId) {
        workshopForm.action = routeFor(@json(route('staff.attendance.draft.submit', ['attendanceReport' => '__ID__'])), draftId);
    }

    if (blockSelect?.value) {
        loadGpOptionsForBlock(blockSelect.value).then(function () {
            if (participantTotal() > 0 || rows.length > 0) {
                renderParticipantTable();
            }
        });
    } else if (rows.length > 0) {
        renderParticipantTable();
    }
})();
</script>
