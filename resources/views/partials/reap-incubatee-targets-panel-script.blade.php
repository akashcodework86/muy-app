<script>
    (function () {
        function sectorAmountFromForm(form) {
            const sector = form.querySelector('[name="payload[reap_sector]"]');
            const amount = form.querySelector('[name="payload[reap_amount]"]');
            return {
                sector: sector ? String(sector.value || '') : '',
                amount: amount ? String(amount.value || '') : '',
            };
        }

        function updateReapTargetHighlights(form) {
            const panel = form.querySelector('[data-reap-targets-panel][data-reap-targets-interactive]');
            if (!panel) return;

            const selected = sectorAmountFromForm(form);
            const cells = panel.querySelectorAll('[data-reap-target-cell]');
            const hint = panel.querySelector('[data-reap-targets-hint]');
            let matched = false;

            cells.forEach(function (cell) {
                const inner = cell.querySelector('.reap-target-cell');
                if (!inner) return;
                const match = selected.sector !== ''
                    && selected.amount !== ''
                    && cell.dataset.reapSector === selected.sector
                    && cell.dataset.reapAmount === selected.amount;
                inner.style.outline = match ? '2px solid #c2410c' : '';
                inner.style.boxShadow = match ? '0 0 0 2px rgba(234,88,12,0.15)' : '';
                if (match && hint) {
                    matched = true;
                    const text = inner.textContent.trim();
                    const parts = text.split('/');
                    const done = parseInt(parts[0] || '0', 10);
                    const target = parseInt(parts[1] || '0', 10);
                    const remaining = Math.max(0, target - done);
                    const label = selected.sector === 'farm' ? 'Farm' : 'Non-farm';
                    const amountLabel = selected.amount === '3_lakh' ? '3 Lakh' : '1 Lakh';
                    hint.style.display = 'block';
                    hint.textContent = 'Adding to ' + label + ' · ' + amountLabel + ' — '
                        + done + ' done, ' + remaining + ' remaining in your district.';
                }
            });

            if (hint && !matched) {
                hint.style.display = 'none';
                hint.textContent = '';
            }
        }

        window.bindReapTargetPanels = function bindReapTargetPanels(root) {
            const scope = root || document;
            scope.querySelectorAll('form').forEach(function (form) {
                if (form.dataset.reapTargetsBound === '1') return;
                form.dataset.reapTargetsBound = '1';
                form.addEventListener('change', function (event) {
                    const name = event.target && event.target.name ? String(event.target.name) : '';
                    if (name === 'payload[reap_sector]' || name === 'payload[reap_amount]' || name === 'payload[through_reap]') {
                        updateReapTargetHighlights(form);
                    }
                });
                updateReapTargetHighlights(form);
            });
        };

        window.bindReapTargetPanels(document);

        const observer = new MutationObserver(function () {
            window.bindReapTargetPanels(document);
        });
        observer.observe(document.body, { childList: true, subtree: true });
    })();
</script>
