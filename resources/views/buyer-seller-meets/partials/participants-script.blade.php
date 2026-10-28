<script>
(function () {
    const root = document.getElementById('bsmParticipantsRoot');
    const addBtn = document.getElementById('bsmAddParticipant');
    if (!root || !addBtn) return;

    const searchUrl = root.getAttribute('data-search-url') || '';
    let initial = @json($initialParticipants ?? []);
    if (!Array.isArray(initial) || initial.length === 0) {
        initial = [
            { kind: 'manual', role: 'buyer', participant_name: '', phone: '', application_no: '' },
            { kind: 'manual', role: 'seller', participant_name: '', phone: '', application_no: '' },
        ];
    }

    let rowIndex = 0;

    function esc(text) {
        const d = document.createElement('div');
        d.textContent = text == null ? '' : String(text);
        return d.innerHTML;
    }

    function rowHtml(idx, data) {
        const role = data.role === 'seller' ? 'seller' : 'buyer';
        const kind = data.kind === 'linked' ? 'linked' : 'manual';
        const name = data.participant_name || '';
        const phone = data.phone || '';
        const appNo = data.application_no || '';
        return `
        <tr data-row="${idx}">
            <td style="width:7rem;">
                <select name="participants[${idx}][role]" required>
                    <option value="buyer" ${role === 'buyer' ? 'selected' : ''}>Buyer</option>
                    <option value="seller" ${role === 'seller' ? 'selected' : ''}>Seller</option>
                </select>
            </td>
            <td>
                <input type="hidden" name="participants[${idx}][kind]" value="${kind}" class="bsm-kind">
                <input type="hidden" name="participants[${idx}][incubatee_key]" value="${esc(data.incubatee_key || '')}" class="bsm-key">
                <input type="hidden" name="participants[${idx}][cfa_submission_id]" value="${data.cfa_submission_id || ''}" class="bsm-cfa">
                <input type="hidden" name="participants[${idx}][legacy_application_id]" value="${data.legacy_application_id || ''}" class="bsm-legacy">
                <input type="hidden" name="participants[${idx}][legacy_phase1_application_id]" value="${data.legacy_phase1_application_id || ''}" class="bsm-phase1">
                <input type="text" name="participants[${idx}][participant_name]" class="bsm-name" required maxlength="255" value="${esc(name)}" placeholder="Name">
                <input type="text" class="bsm-search bc-search" placeholder="Search onboarded (optional)" style="margin-top:0.35rem;" autocomplete="off">
                <div class="bsm-search-results" hidden></div>
            </td>
            <td><input type="text" name="participants[${idx}][application_no]" maxlength="64" value="${esc(appNo)}" placeholder="App no."></td>
            <td><input type="text" name="participants[${idx}][phone]" maxlength="32" value="${esc(phone)}" placeholder="Phone"></td>
            <td style="width:3rem;"><button type="button" class="bsm-remove bc-btn bc-btn--secondary" title="Remove">×</button></td>
        </tr>`;
    }

    function renderTable() {
        root.innerHTML = `
            <table>
                <thead><tr><th>Role</th><th>Name / search</th><th>App no.</th><th>Phone</th><th></th></tr></thead>
                <tbody id="bsmParticipantBody"></tbody>
            </table>`;
        const body = document.getElementById('bsmParticipantBody');
        initial.forEach(function (data) {
            const idx = rowIndex++;
            body.insertAdjacentHTML('beforeend', rowHtml(idx, data));
        });
        bindRows();
    }

    function bindRows() {
        root.querySelectorAll('.bsm-remove').forEach(function (btn) {
            btn.onclick = function () {
                const tr = btn.closest('tr');
                if (tr && root.querySelectorAll('tbody tr').length > 2) {
                    tr.remove();
                }
            };
        });

        root.querySelectorAll('.bsm-search').forEach(function (input) {
            let timer = null;
            input.addEventListener('input', function () {
                const tr = input.closest('tr');
                const box = tr.querySelector('.bsm-search-results');
                const q = input.value.trim();
                clearTimeout(timer);
                if (q.length < 2 || !searchUrl) {
                    box.hidden = true;
                    box.innerHTML = '';
                    return;
                }
                timer = setTimeout(function () {
                    fetch(searchUrl + '?q=' + encodeURIComponent(q), { headers: { 'Accept': 'application/json' } })
                        .then(function (r) { return r.json(); })
                        .then(function (payload) {
                            const results = payload.results || [];
                            box.innerHTML = results.length
                                ? results.map(function (item, i) {
                                    return '<button type="button" data-idx="' + i + '">' +
                                        esc(item.name || '—') + ' · ' + esc(item.application_no || item.phone || '') +
                                        '</button>';
                                }).join('')
                                : '<p style="padding:0.45rem;margin:0;font-size:0.78rem;color:#64748b;">No onboarded match — use manual name.</p>';
                            box.hidden = false;
                            input._bsmResults = results;
                            box.querySelectorAll('button[data-idx]').forEach(function (b) {
                                b.onclick = function () {
                                    const item = (input._bsmResults || [])[parseInt(b.getAttribute('data-idx'), 10)] || {};
                                    tr.querySelector('.bsm-kind').value = 'linked';
                                    tr.querySelector('.bsm-name').value = item.name || '';
                                    tr.querySelector('input[name*="[application_no]"]').value = item.application_no || '';
                                    tr.querySelector('input[name*="[phone]"]').value = item.phone || '';
                                    tr.querySelector('.bsm-key').value = item.incubatee_key || item.key || '';
                                    tr.querySelector('.bsm-cfa').value = item.cfa_submission_id || '';
                                    tr.querySelector('.bsm-legacy').value = item.legacy_application_id || '';
                                    tr.querySelector('.bsm-phase1').value = item.legacy_phase1_application_id || '';
                                    input.value = '';
                                    box.hidden = true;
                                };
                            });
                        })
                        .catch(function () { box.hidden = true; });
                }, 280);
            });
        });
    }

    addBtn.addEventListener('click', function () {
        const body = root.querySelector('tbody');
        if (!body) return;
        const idx = rowIndex++;
        body.insertAdjacentHTML('beforeend', rowHtml(idx, { kind: 'manual', role: 'buyer', participant_name: '' }));
        bindRows();
    });

    renderTable();
})();
</script>
