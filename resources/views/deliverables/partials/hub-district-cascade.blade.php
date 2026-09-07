<script>
    (function () {
        const hubSel = document.getElementById('hub_id');
        const distSel = document.getElementById('district_id');
        if (!hubSel || !distSel) {
            return;
        }

        function applyHub() {
            const hubId = hubSel.value;
            const current = distSel.value;
            let keep = false;
            distSel.querySelectorAll('option[data-hub-id]').forEach(function (opt) {
                const show = !hubId || opt.getAttribute('data-hub-id') === hubId;
                opt.hidden = !show;
                opt.disabled = !show;
                if (show && opt.value === current) {
                    keep = true;
                }
            });
            if (!keep) {
                distSel.value = '';
            }
        }

        hubSel.addEventListener('change', applyHub);
        applyHub();
    })();
</script>
