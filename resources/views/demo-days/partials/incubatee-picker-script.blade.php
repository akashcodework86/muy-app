<script>
(function () {
    const searchInput = document.getElementById('ddyIncubateeSearch');
    const resultsPanel = document.getElementById('ddyResults');
    const resultsCount = document.getElementById('ddyResultsCount');
    const detailPanel = document.getElementById('ddyDetail');
    const detailHead = document.getElementById('ddyDetailHead');
    const selectedPanel = document.getElementById('ddySelectedPanel');
    const selectedCount = document.getElementById('ddySelectedCount');
    const selectedInputs = document.getElementById('ddySelectedInputs');
    const initialNode = document.getElementById('ddyInitialSelected');
    const countsPanel = document.getElementById('ddyParticipantCounts');
    const countTotalEl = document.getElementById('ddyCountTotal');
    const searchUrl = @json(route($searchRoute));
    let timer = null;
    let lastResults = [];
    let hoverIndex = -1;
    const selectedMap = new Map();

    function esc(text) {
        return String(text ?? '')
            .replace(/&/g, '&amp;')
            .replace(/</g, '&lt;')
            .replace(/>/g, '&gt;')
            .replace(/"/g, '&quot;');
    }

    function dash(value) {
        const v = String(value ?? '').trim();
        return v !== '' ? v : '—';
    }

    function itemKey(item) {
        if (item.key) {
            return String(item.key);
        }
        const cfa = parseInt(item.cfa_submission_id || '0', 10) || 0;
        const legacy = parseInt(item.legacy_application_id || '0', 10) || 0;
        if (cfa > 0) return 'cfa:' + cfa;
        if (legacy > 0) return 'legacy:' + legacy;
        return '';
    }

    function statusPill(item) {
        if (item.is_onboarded) {
            return '<span class="pdp-pill pdp-pill--ok">Onboarded</span>';
        }
        return '<span class="pdp-pill pdp-pill--muted">Not onboarded</span>';
    }

    function renderDetail(item, mode) {
        if (!item) {
            detailHead.textContent = 'Incubatee details';
            detailPanel.innerHTML = '<p class="pdp-picker__empty">Hover a result to preview. Click or press Enter to add.</p>';
            return;
        }

        const already = selectedMap.has(itemKey(item));
        detailHead.textContent = mode === 'selected' ? 'Selected incubatee' : 'Incubatee preview';
        detailPanel.innerHTML =
            '<div class="pdp-detail">' +
                '<div class="pdp-detail__badge">' +
                    statusPill(item) +
                    '<span class="pdp-pill">' + esc(item.source || '') + '</span>' +
                    (already ? '<span class="pdp-pill pdp-pill--ok">Added</span>' : '') +
                '</div>' +
                '<h4 class="pdp-detail__title">' + esc(item.name || 'Unnamed') + '</h4>' +
                '<div class="pdp-detail__grid">' +
                    detailField('Application no.', item.application_no) +
                    detailField('Phone', item.phone) +
                    detailField('District', item.district) +
                    detailField('Hub', item.hub) +
                    detailField('Block', item.block) +
                    detailField('Village', item.village) +
                    detailField('Gender', item.gender) +
                    detailField('Business category', item.business_category) +
                    detailField('Onboarding status', item.onboarding_status) +
                    detailField('Onboarding batch', item.onboarding_batch_name) +
                '</div>' +
            '</div>';
    }

    function detailField(label, value) {
        return '<div class="pdp-detail__item"><div class="pdp-detail__label">' + esc(label) +
            '</div><div class="pdp-detail__value">' + esc(dash(value)) + '</div></div>';
    }

    function updateResultsCount(total) {
        if (!resultsCount) return;
        if (!total) {
            resultsCount.hidden = true;
            resultsCount.textContent = '0';
            return;
        }
        resultsCount.hidden = false;
        resultsCount.textContent = total === 1 ? '1 found' : total + ' found';
    }

    function scrollToHoveredRow() {
        if (hoverIndex < 0) return;
        const row = resultsPanel.querySelector('.pdp-result[data-index="' + hoverIndex + '"]');
        if (row) row.scrollIntoView({ block: 'nearest', behavior: 'smooth' });
    }

    function highlightRows() {
        resultsPanel.querySelectorAll('.pdp-result').forEach(function (btn, idx) {
            const item = lastResults[idx];
            const key = item ? itemKey(item) : '';
            btn.classList.toggle('is-hover', idx === hoverIndex);
            btn.classList.toggle('is-selected', key !== '' && selectedMap.has(key));
        });
    }

    function updateParticipantCounts() {
        const total = selectedMap.size;
        if (countsPanel) {
            countsPanel.hidden = total === 0;
        }
        if (countTotalEl) countTotalEl.textContent = String(total);
    }

    function renderSelected() {
        if (selectedCount) selectedCount.textContent = String(selectedMap.size);
        selectedInputs.innerHTML = '';
        selectedPanel.innerHTML = '';

        if (!selectedMap.size) {
            selectedPanel.innerHTML = '<p class="pdp-picker__empty">No incubatees selected yet. Search and add from the results.</p>';
            updateParticipantCounts();
            return;
        }

        let index = 0;
        selectedMap.forEach(function (item, key) {
            const card = document.createElement('div');
            card.className = 'ddy-selected-item';
            card.innerHTML =
                '<div>' +
                    '<div class="ddy-selected-item__name">' + esc(item.name || 'Unnamed') + '</div>' +
                    '<div class="ddy-selected-item__meta">' +
                        esc(dash(item.application_no)) +
                        (item.district ? ' · ' + esc(item.district) : '') +
                    '</div>' +
                '</div>';

            const removeBtn = document.createElement('button');
            removeBtn.type = 'button';
            removeBtn.className = 'ddy-btn-remove';
            removeBtn.textContent = 'Remove';
            removeBtn.addEventListener('click', function () {
                selectedMap.delete(key);
                renderSelected();
                highlightRows();
                if (hoverIndex >= 0 && lastResults[hoverIndex]) {
                    renderDetail(lastResults[hoverIndex], 'hover');
                }
            });
            card.appendChild(removeBtn);
            selectedPanel.appendChild(card);

            const fields = {
                key: key,
                cfa_submission_id: item.cfa_submission_id ? String(item.cfa_submission_id) : '0',
                legacy_application_id: item.legacy_application_id ? String(item.legacy_application_id) : '0',
                name: item.name || '',
                application_no: item.application_no || '',
                gender: item.gender || '',
            };
            Object.keys(fields).forEach(function (field) {
                const input = document.createElement('input');
                input.type = 'hidden';
                input.name = 'participating_incubatees[' + index + '][' + field + ']';
                input.value = fields[field];
                selectedInputs.appendChild(input);
            });
            index += 1;
        });
        updateParticipantCounts();
    }

    function addItem(item) {
        const key = itemKey(item);
        if (!key || selectedMap.has(key)) {
            return;
        }
        selectedMap.set(key, item);
        renderSelected();
        highlightRows();
        renderDetail(item, 'selected');
    }

    function renderResults(items) {
        lastResults = items;
        hoverIndex = -1;
        resultsPanel.innerHTML = '';
        updateResultsCount(items.length);

        if (!items.length) {
            resultsPanel.innerHTML = '<p class="pdp-picker__empty">No matches. Try name, phone, or application number.</p>';
            renderDetail(null);
            return;
        }

        items.forEach(function (item, idx) {
            const btn = document.createElement('button');
            btn.type = 'button';
            btn.className = 'pdp-result';
            btn.dataset.index = String(idx);
            btn.innerHTML =
                '<div class="pdp-result__top">' +
                    '<div class="pdp-result__name">' + esc(item.name || 'Unnamed') + '</div>' +
                    '<div>' + statusPill(item) + '</div>' +
                '</div>' +
                '<div class="pdp-result__meta">' +
                    '<span class="pdp-pill">' + esc(item.source || '') + '</span>' +
                    '<div>App: ' + esc(dash(item.application_no)) + ' · Phone: ' + esc(dash(item.phone)) + '</div>' +
                    '<div>' + esc(dash(item.district)) + (item.block ? ' · ' + esc(item.block) : '') + '</div>' +
                '</div>';

            btn.addEventListener('mouseenter', function () {
                hoverIndex = idx;
                highlightRows();
                renderDetail(item, 'hover');
            });
            btn.addEventListener('click', function () { addItem(item); });
            resultsPanel.appendChild(btn);
        });

        highlightRows();
    }

    function fetchResults(q) {
        fetch(searchUrl + '?q=' + encodeURIComponent(q), {
            headers: { 'Accept': 'application/json', 'X-Requested-With': 'XMLHttpRequest' },
            credentials: 'same-origin',
        })
            .then(function (r) { return r.json(); })
            .then(function (data) { renderResults(data.results || []); })
            .catch(function () { renderResults([]); });
    }

    searchInput.addEventListener('input', function () {
        clearTimeout(timer);
        const q = searchInput.value.trim();
        if (q.length < 2) {
            lastResults = [];
            updateResultsCount(0);
            resultsPanel.innerHTML = '<p class="pdp-picker__empty">Type at least 2 characters to search all Phase 3 CFA and Phase 2 applicants.</p>';
            renderDetail(null);
            return;
        }
        timer = setTimeout(function () { fetchResults(q); }, 220);
    });

    searchInput.addEventListener('keydown', function (e) {
        if (!lastResults.length) return;
        if (e.key === 'ArrowDown') {
            e.preventDefault();
            hoverIndex = Math.min(lastResults.length - 1, hoverIndex + 1);
            highlightRows();
            renderDetail(lastResults[hoverIndex], 'hover');
            scrollToHoveredRow();
        } else if (e.key === 'ArrowUp') {
            e.preventDefault();
            hoverIndex = Math.max(0, hoverIndex - 1);
            highlightRows();
            renderDetail(lastResults[hoverIndex], 'hover');
            scrollToHoveredRow();
        } else if (e.key === 'Enter' && hoverIndex >= 0) {
            e.preventDefault();
            addItem(lastResults[hoverIndex]);
        }
    });

    function bootstrapInitialSelected() {
        if (!initialNode) return;
        let rows = [];
        try {
            rows = JSON.parse(initialNode.textContent || '[]');
        } catch (e) {
            rows = [];
        }
        rows.forEach(function (row) {
            if (!row || typeof row !== 'object') return;
            const key = itemKey(row);
            if (!key) return;
            selectedMap.set(key, {
                key: key,
                cfa_submission_id: parseInt(row.cfa_submission_id || '0', 10) || 0,
                legacy_application_id: parseInt(row.legacy_application_id || '0', 10) || 0,
                name: row.name || '',
                application_no: row.application_no || '',
                district: row.district || '',
                gender: row.gender || '',
            });
        });
        renderSelected();
    }

    bootstrapInitialSelected();
})();
</script>
