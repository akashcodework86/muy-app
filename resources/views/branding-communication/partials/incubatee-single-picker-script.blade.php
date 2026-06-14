<script>
(function () {
    const searchInput = document.getElementById('bcIncubateeSearch');
    const resultsPanel = document.getElementById('bcResults');
    const resultsCount = document.getElementById('bcResultsCount');
    const detailPanel = document.getElementById('bcDetail');
    const detailHead = document.getElementById('bcDetailHead');
    const cfaInput = document.getElementById('bcCfaId');
    const legacyInput = document.getElementById('bcLegacyId');
    const keyInput = document.getElementById('bcIncubateeKey');
    const searchUrl = @json(route($searchRoute));
    let timer = null;
    let lastResults = [];
    let hoverIndex = -1;
    let selectedKey = keyInput?.value || '';

    function esc(text) {
        return String(text ?? '').replace(/&/g, '&amp;').replace(/</g, '&lt;').replace(/>/g, '&gt;').replace(/"/g, '&quot;');
    }
    function dash(v) { v = String(v ?? '').trim(); return v !== '' ? v : '—'; }
    function statusPill(item) {
        return item.is_onboarded
            ? '<span class="bc-pill bc-pill--ok">Onboarded</span>'
            : '<span class="bc-pill bc-pill--muted">Not onboarded</span>';
    }
    function detailField(label, value) {
        return '<div class="bc-detail__item"><div class="bc-detail__label">' + esc(label) +
            '</div><div class="bc-detail__value">' + esc(dash(value)) + '</div></div>';
    }
    function renderDetail(item, mode) {
        if (!item) {
            detailHead.textContent = 'Incubatee details';
            detailPanel.innerHTML = '<p class="bc-picker__empty">Hover a result to preview. Click to select.</p>';
            return;
        }
        detailHead.textContent = mode === 'selected' ? 'Selected incubatee' : 'Incubatee preview';
        detailPanel.innerHTML =
            '<div class="bc-detail"><div class="bc-detail__badge">' + statusPill(item) +
            '<span class="bc-pill">' + esc(item.source || '') + '</span></div>' +
            '<h4 class="bc-detail__title">' + esc(item.name || 'Unnamed') + '</h4><div class="bc-detail__grid">' +
            detailField('Application no.', item.application_no) + detailField('Phone', item.phone) +
            detailField('District', item.district) + detailField('Hub', item.hub) +
            detailField('Block', item.block) + detailField('Village', item.village) +
            '</div></div>';
    }
    function clearSelection() {
        selectedKey = '';
        if (cfaInput) cfaInput.value = '';
        if (legacyInput) legacyInput.value = '';
        if (keyInput) keyInput.value = '';
    }
    function updateResultsCount(total) {
        if (!resultsCount) return;
        if (!total) { resultsCount.hidden = true; resultsCount.textContent = '0'; return; }
        resultsCount.hidden = false;
        resultsCount.textContent = total === 1 ? '1 found' : total + ' found';
    }
    function highlightRows() {
        resultsPanel.querySelectorAll('.bc-result').forEach(function (btn, idx) {
            btn.classList.toggle('is-hover', idx === hoverIndex);
            btn.classList.toggle('is-selected', lastResults[idx] && lastResults[idx].key === selectedKey);
        });
    }
    function selectItem(item, idx) {
        selectedKey = item.key || '';
        hoverIndex = typeof idx === 'number' ? idx : hoverIndex;
        if (cfaInput) cfaInput.value = item.cfa_submission_id ? String(item.cfa_submission_id) : '';
        if (legacyInput) legacyInput.value = item.legacy_application_id ? String(item.legacy_application_id) : '';
        if (keyInput) keyInput.value = item.key || '';
        if (searchInput) searchInput.value = (item.name || '') + (item.application_no ? ' · ' + item.application_no : '');
        highlightRows();
        renderDetail(item, 'selected');
    }
    function renderResults(items) {
        lastResults = items;
        hoverIndex = -1;
        resultsPanel.innerHTML = '';
        updateResultsCount(items.length);
        if (!items.length) {
            resultsPanel.innerHTML = '<p class="bc-picker__empty">No matches found.</p>';
            renderDetail(null);
            return;
        }
        items.forEach(function (item, idx) {
            const btn = document.createElement('button');
            btn.type = 'button';
            btn.className = 'bc-result';
            btn.dataset.index = String(idx);
            btn.innerHTML = '<div class="bc-result__top"><div class="bc-result__name">' + esc(item.name || 'Unnamed') +
                '</div><div>' + statusPill(item) + '</div></div><div class="bc-result__meta"><span class="bc-pill">' +
                esc(item.source || '') + '</span><div>App: ' + esc(dash(item.application_no)) + ' · Phone: ' +
                esc(dash(item.phone)) + '</div></div>';
            btn.addEventListener('mouseenter', function () { hoverIndex = idx; highlightRows(); renderDetail(item, 'hover'); });
            btn.addEventListener('click', function () { selectItem(item, idx); });
            resultsPanel.appendChild(btn);
        });
        highlightRows();
    }
    function fetchResults(q) {
        fetch(searchUrl + '?q=' + encodeURIComponent(q), {
            headers: { 'Accept': 'application/json', 'X-Requested-With': 'XMLHttpRequest' },
            credentials: 'same-origin',
        }).then(function (r) { return r.json(); })
          .then(function (data) { renderResults(data.results || []); })
          .catch(function () { renderResults([]); });
    }
    searchInput?.addEventListener('input', function () {
        clearSelection();
        clearTimeout(timer);
        const q = searchInput.value.trim();
        if (q.length < 2) {
            lastResults = [];
            updateResultsCount(0);
            resultsPanel.innerHTML = '<p class="bc-picker__empty">Type at least 2 characters to search.</p>';
            renderDetail(null);
            return;
        }
        timer = setTimeout(function () { fetchResults(q); }, 220);
    });
    searchInput?.addEventListener('keydown', function (e) {
        if (!lastResults.length) return;
        if (e.key === 'ArrowDown') { e.preventDefault(); hoverIndex = Math.min(lastResults.length - 1, hoverIndex + 1); highlightRows(); renderDetail(lastResults[hoverIndex], 'hover'); }
        else if (e.key === 'ArrowUp') { e.preventDefault(); hoverIndex = Math.max(0, hoverIndex - 1); highlightRows(); renderDetail(lastResults[hoverIndex], 'hover'); }
        else if (e.key === 'Enter' && hoverIndex >= 0) { e.preventDefault(); selectItem(lastResults[hoverIndex], hoverIndex); }
    });
})();
</script>
