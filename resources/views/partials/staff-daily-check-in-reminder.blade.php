@if (!empty($showStaffDailyCheckInReminder))
    {{-- Marquee: stays visible until attendance is marked --}}
    <div class="sdci-marquee" role="status" aria-live="polite">
        <div class="sdci-marquee__track">
            <span class="sdci-marquee__item">
                <strong>Daily attendance required</strong> — It is after 9:00 AM. Mark your attendance with GPS at
                <a href="{{ route('staff-daily-check-in.index') }}">Daily attendance</a>.
            </span>
            <span class="sdci-marquee__item" aria-hidden="true">
                <strong>Daily attendance required</strong> — It is after 9:00 AM. Mark your attendance with GPS at
                <a href="{{ route('staff-daily-check-in.index') }}">Daily attendance</a>.
            </span>
        </div>
    </div>

    {{-- Modal popup (dismissible; marquee remains) --}}
    <div class="sdci-modal" id="sdci-reminder-modal" role="dialog" aria-modal="true" aria-labelledby="sdci-modal-title" hidden>
        <div class="sdci-modal__backdrop" data-sdci-close></div>
        <div class="sdci-modal__panel">
            <button type="button" class="sdci-modal__close" data-sdci-close aria-label="Close">&times;</button>
            <div class="sdci-modal__pulse" aria-hidden="true"></div>
            <div class="sdci-modal__icon" aria-hidden="true">
                <svg viewBox="0 0 24 24" width="32" height="32" fill="none" stroke="currentColor" stroke-width="2">
                    <path d="M12 21s-7-7.5-7-12a7 7 0 0 1 14 0c0 4.5-7 12-7 12Z"/>
                    <circle cx="12" cy="9" r="2.5"/>
                </svg>
            </div>
            <h2 id="sdci-modal-title" class="sdci-modal__title">Daily attendance required</h2>
            <p class="sdci-modal__lead">
                It is after <strong>9:00 AM</strong> — please mark your attendance with your current GPS location.
            </p>
            <div class="sdci-modal__info">
                <h3>How attendance monitoring works</h3>
                <ul>
                    <li>Mark attendance <strong>once per day</strong> from this MIS portal.</li>
                    <li>Your browser shares <strong>GPS coordinates</strong> (latitude &amp; longitude) at check-in time.</li>
                    <li>State admin can verify <strong>who marked, when, and from where</strong> for programme accountability.</li>
                    <li>Allow location permission when prompted — use mobile data or Wi‑Fi with GPS enabled outdoors or near a window for best accuracy.</li>
                </ul>
            </div>
            <div class="sdci-modal__actions">
                <a href="{{ route('staff-daily-check-in.index') }}" class="sdci-modal__btn sdci-modal__btn--primary">
                    <span class="sdci-modal__btn-shine"></span>
                    Mark attendance now
                </a>
                <button type="button" class="sdci-modal__btn sdci-modal__btn--ghost" data-sdci-close>
                    Remind me later
                </button>
            </div>
            <p class="sdci-modal__footnote">You can close this window — the reminder bar stays at the top; this popup appears again on each page until you mark attendance.</p>
        </div>
    </div>

    <style>
        .sdci-marquee {
            margin: 0 0 0.85rem;
            overflow: hidden;
            border-radius: 10px;
            border: 1px solid #f59e0b;
            background: linear-gradient(90deg, #fffbeb, #fef3c7, #fffbeb);
            box-shadow: 0 4px 14px rgba(245, 158, 11, 0.2);
        }
        .sdci-marquee__track {
            display: flex;
            width: max-content;
            animation: sdci-marquee-scroll 22s linear infinite;
        }
        .sdci-marquee__item {
            flex-shrink: 0;
            padding: 0.55rem 1.25rem;
            font-size: 0.86rem;
            color: #92400e;
            white-space: nowrap;
        }
        .sdci-marquee__item a {
            color: #b45309;
            font-weight: 700;
            text-decoration: underline;
        }
        @keyframes sdci-marquee-scroll {
            0% { transform: translateX(0); }
            100% { transform: translateX(-50%); }
        }

        .sdci-modal[hidden] { display: none; }
        .sdci-modal {
            position: fixed;
            inset: 0;
            z-index: 9999;
            display: flex;
            align-items: center;
            justify-content: center;
            padding: 1rem;
        }
        .sdci-modal.is-open {
            animation: sdci-modal-fade-in 0.35s ease;
        }
        .sdci-modal__backdrop {
            position: absolute;
            inset: 0;
            background: rgba(15, 23, 42, 0.55);
            backdrop-filter: blur(4px);
        }
        .sdci-modal__panel {
            position: relative;
            width: min(100%, 440px);
            background: #fff;
            border-radius: 20px;
            padding: 1.75rem 1.5rem 1.25rem;
            box-shadow: 0 24px 60px rgba(15, 23, 42, 0.25);
            animation: sdci-panel-pop 0.45s cubic-bezier(0.34, 1.56, 0.64, 1);
            max-height: 90vh;
            overflow-y: auto;
        }
        @keyframes sdci-modal-fade-in {
            from { opacity: 0; }
            to { opacity: 1; }
        }
        @keyframes sdci-panel-pop {
            from { opacity: 0; transform: scale(0.88) translateY(12px); }
            to { opacity: 1; transform: scale(1) translateY(0); }
        }
        .sdci-modal__close {
            position: absolute;
            top: 0.65rem;
            right: 0.75rem;
            width: 2rem;
            height: 2rem;
            border: none;
            border-radius: 999px;
            background: #f1f5f9;
            color: #475569;
            font-size: 1.35rem;
            line-height: 1;
            cursor: pointer;
            transition: background 0.2s, color 0.2s, transform 0.15s;
        }
        .sdci-modal__close:hover {
            background: #e2e8f0;
            color: #0f172a;
            transform: scale(1.05);
        }
        .sdci-modal__pulse {
            position: absolute;
            top: 1.2rem;
            left: 50%;
            width: 4rem;
            height: 4rem;
            margin-left: -2rem;
            border-radius: 999px;
            background: rgba(245, 158, 11, 0.25);
            animation: sdci-pulse 2s ease-out infinite;
            pointer-events: none;
        }
        @keyframes sdci-pulse {
            0% { transform: scale(0.8); opacity: 0.8; }
            100% { transform: scale(1.8); opacity: 0; }
        }
        .sdci-modal__icon {
            position: relative;
            width: 3.5rem;
            height: 3.5rem;
            margin: 0 auto 0.75rem;
            display: flex;
            align-items: center;
            justify-content: center;
            border-radius: 999px;
            background: linear-gradient(135deg, #fbbf24, #f59e0b);
            color: #fff;
        }
        .sdci-modal__title {
            margin: 0 0 0.5rem;
            text-align: center;
            font-size: 1.25rem;
            color: #0f172a;
        }
        .sdci-modal__lead {
            margin: 0 0 1rem;
            text-align: center;
            font-size: 0.92rem;
            color: #475569;
            line-height: 1.5;
        }
        .sdci-modal__info {
            background: #f8fafc;
            border: 1px solid #e2e8f0;
            border-radius: 12px;
            padding: 0.85rem 1rem;
            margin-bottom: 1.1rem;
        }
        .sdci-modal__info h3 {
            margin: 0 0 0.5rem;
            font-size: 0.78rem;
            text-transform: uppercase;
            letter-spacing: 0.06em;
            color: #64748b;
        }
        .sdci-modal__info ul {
            margin: 0;
            padding-left: 1.1rem;
            font-size: 0.84rem;
            color: #334155;
            line-height: 1.55;
        }
        .sdci-modal__actions {
            display: flex;
            flex-direction: column;
            gap: 0.5rem;
        }
        .sdci-modal__btn {
            display: flex;
            align-items: center;
            justify-content: center;
            padding: 0.7rem 1rem;
            border-radius: 10px;
            font-weight: 700;
            font-size: 0.9rem;
            text-decoration: none;
            border: none;
            cursor: pointer;
            font-family: inherit;
            position: relative;
            overflow: hidden;
        }
        .sdci-modal__btn--primary {
            background: linear-gradient(135deg, #4f46e5, #6366f1);
            color: #fff;
            box-shadow: 0 8px 20px rgba(79, 70, 229, 0.35);
            transition: transform 0.2s, box-shadow 0.2s;
        }
        .sdci-modal__btn--primary:hover {
            transform: translateY(-1px);
            box-shadow: 0 12px 28px rgba(79, 70, 229, 0.4);
            color: #fff;
        }
        .sdci-modal__btn-shine {
            position: absolute;
            inset: 0;
            background: linear-gradient(105deg, transparent 40%, rgba(255,255,255,0.25) 50%, transparent 60%);
            animation: sdci-shine 2.5s ease-in-out infinite;
        }
        @keyframes sdci-shine {
            0%, 100% { transform: translateX(-100%); }
            50% { transform: translateX(100%); }
        }
        .sdci-modal__btn--ghost {
            background: transparent;
            color: #64748b;
        }
        .sdci-modal__btn--ghost:hover { color: #0f172a; background: #f1f5f9; }
        .sdci-modal__footnote {
            margin: 0.85rem 0 0;
            font-size: 0.75rem;
            color: #94a3b8;
            text-align: center;
            line-height: 1.4;
        }
    </style>
    <script>
    (function () {
        const modal = document.getElementById('sdci-reminder-modal');
        if (!modal) return;

        function openModal() {
            modal.hidden = false;
            modal.classList.add('is-open');
            document.body.style.overflow = 'hidden';
        }
        function closeModal() {
            modal.classList.remove('is-open');
            modal.hidden = true;
            document.body.style.overflow = '';
        }

        // Show once after login, then repeat every 10 minutes if still not marked.
        // Prevents showing on every refresh within the 10-minute window (sessionStorage).
        const today = new Date().toISOString().slice(0, 10);
        const storageKey = 'sdci_last_modal_ts_' + today;
        const intervalMs = 10 * 60 * 1000;

        function shouldShowNow() {
            try {
                const last = parseInt(sessionStorage.getItem(storageKey) || '0', 10);
                return !last || (Date.now() - last) >= intervalMs;
            } catch (e) {
                // If storage is unavailable, degrade gracefully: show once now.
                return true;
            }
        }

        function markShown() {
            try { sessionStorage.setItem(storageKey, String(Date.now())); } catch (e) {}
        }

        function maybeShow() {
            if (shouldShowNow()) {
                markShown();
                openModal();
            }
        }

        // Run immediately on first page load after login.
        requestAnimationFrame(maybeShow);

        // Re-open every 10 minutes if reminder still applies (this partial won't render once marked).
        setInterval(function () {
            // Don't interrupt if the modal is already open.
            if (!modal.hidden) return;
            maybeShow();
        }, intervalMs);

        modal.querySelectorAll('[data-sdci-close]').forEach(function (el) {
            el.addEventListener('click', closeModal);
        });
        document.addEventListener('keydown', function (e) {
            if (e.key === 'Escape' && !modal.hidden) closeModal();
        });
    })();
    </script>
@endif
