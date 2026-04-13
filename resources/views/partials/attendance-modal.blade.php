{{-- Daily attendance popup (district staff + hub admin; not state admin). After 9:00 AM IST on working days. --}}
<script type="application/json" id="attendance-boot">@json([
    'csrf' => csrf_token(),
    'statusUrl' => route('attendance.status'),
    'markUrl' => route('attendance.mark'),
])</script>

<div id="attendance-modal" class="attendance-modal" hidden aria-modal="true" role="dialog" aria-labelledby="attendance-modal-title">
    <div class="attendance-modal__backdrop"></div>
    <div class="attendance-modal__panel glass-surface">
        <h2 id="attendance-modal-title" class="attendance-modal__title">Mark attendance</h2>
        <p class="attendance-modal__lead">Please confirm your location (required for attendance).</p>
        <div id="attendance-step-loc" class="attendance-modal__body">
            <p id="attendance-loc-status" class="attendance-modal__status">Detecting your location…</p>
            <div id="attendance-loc-detail" class="attendance-modal__coords" hidden></div>
            <p id="attendance-loc-error" class="attendance-modal__error" hidden></p>
        </div>
        <div class="attendance-modal__actions">
            <button type="button" id="attendance-btn-retry" class="attendance-modal__btn attendance-modal__btn--secondary" hidden>Try location again</button>
            <button type="button" id="attendance-btn-confirm" class="attendance-modal__btn attendance-modal__btn--primary" disabled>Confirm &amp; mark attendance</button>
        </div>
    </div>
</div>

<style>
    .attendance-modal { position: fixed; inset: 0; z-index: 100000; display: flex; align-items: center; justify-content: center; padding: 1rem; box-sizing: border-box; }
    .attendance-modal[hidden] { display: none !important; }
    .attendance-modal__backdrop { position: absolute; inset: 0; background: rgba(15, 23, 42, 0.45); backdrop-filter: blur(6px); }
    .attendance-modal__panel {
        position: relative;
        max-width: 22rem;
        width: 100%;
        padding: 1.35rem 1.4rem 1.4rem;
        border-radius: 18px;
        z-index: 1;
        border: 1px solid rgba(255, 255, 255, 0.2);
        box-shadow: 0 24px 48px rgba(15, 23, 42, 0.2);
    }
    .attendance-modal__title { margin: 0 0 0.35rem; font-size: 1.15rem; font-weight: 800; font-family: 'DM Sans', system-ui, sans-serif; color: #0f172a; letter-spacing: -0.02em; }
    .attendance-modal__lead { margin: 0 0 0.85rem; font-size: 0.88rem; color: #64748b; line-height: 1.45; }
    .attendance-modal__status { font-size: 0.88rem; color: #475569; margin: 0; }
    .attendance-modal__coords { font-size: 0.78rem; color: #0f172a; margin-top: 0.65rem; line-height: 1.45; word-break: break-all; font-family: ui-monospace, monospace; }
    .attendance-modal__error { font-size: 0.82rem; color: #b91c1c; margin: 0.5rem 0 0; }
    .attendance-modal__actions { display: flex; flex-wrap: wrap; gap: 0.5rem; margin-top: 1rem; }
    .attendance-modal__btn {
        flex: 1 1 auto;
        min-width: 8rem;
        padding: 0.55rem 0.9rem;
        border-radius: 10px;
        font-size: 0.88rem;
        font-weight: 700;
        font-family: inherit;
        cursor: pointer;
        border: none;
    }
    .attendance-modal__btn--primary { background: linear-gradient(135deg, #4f46e5, #6366f1); color: #fff; }
    .attendance-modal__btn--primary:disabled { opacity: 0.45; cursor: not-allowed; }
    .attendance-modal__btn--secondary { background: #f1f5f9; color: #334155; border: 1px solid #e2e8f0; }
</style>

<script>
(function () {
    const boot = document.getElementById('attendance-boot');
    if (!boot) return;
    let cfg;
    try { cfg = JSON.parse(boot.textContent); } catch (e) { return; }
    const modal = document.getElementById('attendance-modal');
    const locStatus = document.getElementById('attendance-loc-status');
    const locDetail = document.getElementById('attendance-loc-detail');
    const locErr = document.getElementById('attendance-loc-error');
    const btnConfirm = document.getElementById('attendance-btn-confirm');
    const btnRetry = document.getElementById('attendance-btn-retry');
    let lat = null;
    let lng = null;
    let acc = null;
    let pollTimer = null;
    let cutoffTimer = null;

    function headersJson() {
        return {
            'Content-Type': 'application/json',
            'Accept': 'application/json',
            'X-CSRF-TOKEN': cfg.csrf,
            'X-Requested-With': 'XMLHttpRequest'
        };
    }

    function showModal() {
        modal.hidden = false;
        locStatus.textContent = 'Detecting your location…';
        locDetail.hidden = true;
        locErr.hidden = true;
        btnRetry.hidden = true;
        btnConfirm.disabled = true;
        lat = lng = acc = null;
        if (!navigator.geolocation) {
            locStatus.textContent = 'Location is not supported by this browser.';
            locErr.hidden = false;
            locErr.textContent = 'Use a device with location services, or try Chrome / Edge.';
            btnRetry.hidden = true;
            return;
        }
        navigator.geolocation.getCurrentPosition(
            function (pos) {
                lat = pos.coords.latitude;
                lng = pos.coords.longitude;
                acc = pos.coords.accuracy != null ? pos.coords.accuracy : null;
                locStatus.textContent = 'Location captured. Please confirm below.';
                locDetail.hidden = false;
                locDetail.innerHTML =
                    '<strong>Latitude:</strong> ' + lat.toFixed(6) + '<br>' +
                    '<strong>Longitude:</strong> ' + lng.toFixed(6) +
                    (acc != null ? ('<br><strong>Accuracy:</strong> ~' + Math.round(acc) + ' m') : '');
                btnConfirm.disabled = false;
            },
            function (err) {
                locStatus.textContent = 'Could not read location.';
                locErr.hidden = false;
                locErr.textContent = err && err.message ? err.message : 'Permission denied or unavailable.';
                btnRetry.hidden = false;
            },
            { enableHighAccuracy: true, timeout: 20000, maximumAge: 0 }
        );
    }

    function hideModal() {
        modal.hidden = true;
    }

    async function pollStatus() {
        try {
            const r = await fetch(cfg.statusUrl, { headers: { Accept: 'application/json', 'X-Requested-With': 'XMLHttpRequest' }, credentials: 'same-origin' });
            if (!r.ok) return;
            const j = await r.json();
            if (!j.eligible) return;
            if (j.need_popup && modal.hidden) showModal();
            if (typeof j.seconds_until_cutoff === 'number' && j.seconds_until_cutoff > 0) {
                if (cutoffTimer) clearTimeout(cutoffTimer);
                cutoffTimer = setTimeout(pollStatus, Math.min(j.seconds_until_cutoff * 1000, 2147483647));
            }
        } catch (e) { /* ignore */ }
    }

    btnRetry.addEventListener('click', function () { showModal(); });

    btnConfirm.addEventListener('click', async function () {
        if (lat == null || lng == null) return;
        btnConfirm.disabled = true;
        try {
            const body = { latitude: lat, longitude: lng };
            if (acc != null) body.accuracy_m = acc;
            const r = await fetch(cfg.markUrl, {
                method: 'POST',
                headers: headersJson(),
                credentials: 'same-origin',
                body: JSON.stringify(body),
            });
            const j = await r.json().catch(function () { return {}; });
            if (r.ok && j.ok) {
                hideModal();
                return;
            }
            locErr.hidden = false;
            locErr.textContent = j.message || 'Could not save attendance.';
            btnConfirm.disabled = false;
        } catch (e) {
            locErr.hidden = false;
            locErr.textContent = 'Network error. Try again.';
            btnConfirm.disabled = false;
        }
    });

    pollStatus();
    pollTimer = setInterval(pollStatus, 60000);
})();
</script>
