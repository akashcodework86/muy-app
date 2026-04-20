@php
    $loUser = auth()->user();
    $showLiveOps = $loUser && in_array($loUser->role, ['state_admin', 'hub_admin'], true);
@endphp
@if ($showLiveOps)

<style>
    /* Topbar trigger */
    .lo-trigger { position: relative; display: inline-flex; align-items: center; justify-content: center; width: 38px; height: 38px; border: 1px solid rgba(148, 163, 184, 0.35); border-radius: 999px; background: rgba(255, 255, 255, 0.85); cursor: pointer; color: #4338ca; transition: background 160ms ease, transform 160ms ease, border-color 160ms ease; margin-right: 0.5rem; }
    .lo-trigger:hover { background: #eef2ff; border-color: #c7d2fe; transform: translateY(-1px); }
    .lo-trigger.is-open { background: #4338ca; color: #fff; border-color: #4338ca; }
    .lo-trigger svg { width: 20px; height: 20px; }
    .lo-trigger__badge { position: absolute; top: -4px; right: -4px; min-width: 18px; height: 18px; padding: 0 5px; border-radius: 999px; background: #16a34a; color: #fff; font-size: 0.62rem; font-weight: 800; display: inline-grid; place-items: center; border: 2px solid #fff; line-height: 1; box-shadow: 0 1px 3px rgba(0, 0, 0, 0.15); }
    .lo-trigger__badge.is-zero { background: #94a3b8; }
    .lo-trigger__dot { position: absolute; top: 7px; right: 7px; width: 8px; height: 8px; border-radius: 999px; background: #22c55e; box-shadow: 0 0 0 3px rgba(34, 197, 94, 0.25); animation: loTriggerPulse 1.8s ease-in-out infinite; }
    .lo-trigger.is-open .lo-trigger__dot { display: none; }
    @keyframes loTriggerPulse { 0%, 100% { box-shadow: 0 0 0 0 rgba(34, 197, 94, 0.6); } 50% { box-shadow: 0 0 0 5px rgba(34, 197, 94, 0); } }

    /* Backdrop */
    .lo-backdrop { position: fixed; inset: 0; background: rgba(15, 23, 42, 0.4); backdrop-filter: blur(2px); opacity: 0; pointer-events: none; transition: opacity 220ms ease; z-index: 998; }
    .lo-backdrop.is-open { opacity: 1; pointer-events: auto; }

    /* Drawer */
    .lo-drawer { position: fixed; top: 0; right: 0; bottom: 0; width: min(420px, 92vw); background: linear-gradient(145deg, #ffffff, #f8fafc); border-left: 1px solid rgba(148, 163, 184, 0.32); box-shadow: -16px 0 48px rgba(15, 23, 42, 0.18); transform: translateX(100%); transition: transform 280ms cubic-bezier(0.22, 1, 0.36, 1); z-index: 999; display: flex; flex-direction: column; font-family: 'Inter', 'DM Sans', system-ui, sans-serif; }
    .lo-drawer.is-open { transform: translateX(0); }
    .lo-drawer__head { padding: 1rem 1.1rem; border-bottom: 1px solid rgba(226, 232, 240, 0.9); display: flex; align-items: center; justify-content: space-between; gap: 0.75rem; background: linear-gradient(135deg, rgba(238, 242, 255, 0.8), rgba(224, 231, 255, 0.4)); }
    .lo-drawer__title { margin: 0; font-size: 0.92rem; font-weight: 800; color: #4338ca; letter-spacing: -0.01em; display: inline-flex; align-items: center; gap: 0.5rem; }
    .lo-drawer__title-dot { width: 10px; height: 10px; border-radius: 999px; background: #22c55e; box-shadow: 0 0 0 4px rgba(34, 197, 94, 0.2); animation: loTriggerPulse 1.8s ease-in-out infinite; }
    .lo-drawer__sub { display: block; font-size: 0.62rem; font-weight: 600; color: #64748b; margin-top: 0.15rem; letter-spacing: 0.04em; text-transform: uppercase; }
    .lo-close { appearance: none; background: #fff; border: 1px solid rgba(148, 163, 184, 0.4); color: #475569; border-radius: 10px; width: 34px; height: 34px; cursor: pointer; display: inline-grid; place-items: center; transition: background 160ms ease, color 160ms ease; }
    .lo-close:hover { background: #fee2e2; color: #b91c1c; border-color: #fecaca; }
    .lo-close svg { width: 18px; height: 18px; }

    .lo-drawer__body { flex: 1; min-height: 0; overflow-y: auto; padding: 1rem 1.1rem 1.4rem; display: flex; flex-direction: column; gap: 1rem; }

    .lo-card { background: #fff; border: 1px solid rgba(226, 232, 240, 0.92); border-radius: 14px; padding: 0.9rem 1rem; display: flex; flex-direction: column; gap: 0.7rem; box-shadow: 0 2px 10px rgba(15, 23, 42, 0.04); }
    .lo-card__head { display: flex; align-items: center; justify-content: space-between; gap: 0.5rem; flex-wrap: wrap; }
    .lo-card__title { display: inline-flex; align-items: center; gap: 0.4rem; margin: 0; font-size: 0.72rem; font-weight: 800; text-transform: uppercase; letter-spacing: 0.09em; color: #4338ca; }
    .lo-card__stamp { font-size: 0.6rem; color: #94a3b8; font-weight: 600; }
    .lo-card__stamp.is-loading { color: #4f46e5; }

    .lo-count { font-family: 'DM Sans', sans-serif; font-size: 2rem; font-weight: 800; line-height: 1; color: #0f172a; letter-spacing: -0.02em; }
    .lo-count__label { display: block; font-size: 0.62rem; font-weight: 600; text-transform: uppercase; letter-spacing: 0.08em; color: #64748b; margin-top: 0.2rem; }
    .lo-role-grid { display: grid; grid-template-columns: repeat(2, 1fr); gap: 0.45rem; }
    .lo-role { padding: 0.5rem 0.6rem; border-radius: 10px; background: #f8fafc; border: 1px solid rgba(226, 232, 240, 0.95); }
    .lo-role__name { font-size: 0.58rem; font-weight: 700; text-transform: uppercase; letter-spacing: 0.06em; color: #64748b; }
    .lo-role__val { font-family: 'DM Sans', sans-serif; font-size: 1rem; font-weight: 800; color: #0f172a; margin-top: 0.15rem; }
    .lo-role__val b { color: #16a34a; font-weight: 800; }
    .lo-role__val span { color: #94a3b8; font-weight: 600; font-size: 0.78rem; }

    .lo-avatars { display: flex; gap: 0.3rem; flex-wrap: wrap; padding-top: 0.1rem; }
    .lo-avatar { position: relative; width: 30px; height: 30px; border-radius: 999px; background: linear-gradient(135deg, #eef2ff, #ede9fe); color: #4338ca; font-size: 0.65rem; font-weight: 800; display: inline-grid; place-items: center; border: 2px solid #fff; box-shadow: 0 1px 3px rgba(15, 23, 42, 0.1); cursor: default; }
    .lo-avatar::after { content: ''; position: absolute; right: -1px; bottom: -1px; width: 9px; height: 9px; border-radius: 999px; background: #22c55e; border: 2px solid #fff; }
    .lo-avatar[data-role="state_admin"] { background: linear-gradient(135deg, #fef3c7, #fde68a); color: #92400e; }
    .lo-avatar[data-role="hub_admin"] { background: linear-gradient(135deg, #dbeafe, #bfdbfe); color: #1e40af; }
    .lo-avatar[data-role="district_staff"] { background: linear-gradient(135deg, #cffafe, #a5f3fc); color: #0e7490; }
    .lo-avatar[data-role="incubatee"] { background: linear-gradient(135deg, #fce7f3, #fbcfe8); color: #9d174d; }

    /* Activity feed */
    .lo-feed { display: flex; flex-direction: column; gap: 0.4rem; max-height: none; }
    .lo-feed__row { display: grid; grid-template-columns: 1.7rem minmax(0, 1fr) auto; gap: 0.55rem; padding: 0.55rem 0.6rem; border-radius: 10px; background: #f8fafc; border: 1px solid rgba(226, 232, 240, 0.9); transition: background 200ms ease; }
    .lo-feed__row.is-new { animation: loSlide 460ms cubic-bezier(0.22, 1, 0.36, 1); background: #eff6ff; }
    @keyframes loSlide { from { opacity: 0; transform: translateY(-5px); } to { opacity: 1; transform: translateY(0); } }
    .lo-feed__icon { width: 1.6rem; height: 1.6rem; border-radius: 8px; display: inline-grid; place-items: center; color: var(--lo-color, #4f46e5); background: color-mix(in srgb, var(--lo-color, #4f46e5) 14%, transparent); border: 1px solid color-mix(in srgb, var(--lo-color, #4f46e5) 30%, transparent); flex-shrink: 0; }
    .lo-feed__icon svg { width: 13px; height: 13px; }
    .lo-feed__body { min-width: 0; }
    .lo-feed__title { margin: 0; font-size: 0.78rem; font-weight: 600; color: #0f172a; line-height: 1.4; overflow: hidden; text-overflow: ellipsis; display: -webkit-box; -webkit-line-clamp: 3; -webkit-box-orient: vertical; }
    .lo-feed__meta { font-size: 0.58rem; color: #64748b; margin-top: 0.18rem; display: flex; gap: 0.4rem; flex-wrap: wrap; letter-spacing: 0.02em; }
    .lo-feed__meta strong { color: #475569; font-weight: 700; }
    .lo-feed__time { font-size: 0.6rem; color: #94a3b8; font-weight: 600; text-align: right; flex-shrink: 0; white-space: nowrap; align-self: start; }
    .lo-empty { text-align: center; padding: 1.5rem 1rem; color: #94a3b8; font-size: 0.82rem; }
</style>

<button type="button" class="lo-trigger" id="loTrigger" aria-label="Live operations" aria-expanded="false">
    <svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M13 2 3 14h9l-1 8 10-12h-9l1-8z"/></svg>
    <span class="lo-trigger__dot" aria-hidden="true"></span>
    <span class="lo-trigger__badge is-zero" id="loTriggerBadge" aria-hidden="true">0</span>
</button>

<div class="lo-backdrop" id="loBackdrop" aria-hidden="true"></div>

<aside class="lo-drawer" id="loDrawer" role="dialog" aria-modal="true" aria-labelledby="loDrawerTitle" tabindex="-1">
    <div class="lo-drawer__head">
        <div>
            <h2 id="loDrawerTitle" class="lo-drawer__title">
                <span class="lo-drawer__title-dot" aria-hidden="true"></span>
                Live operations
            </h2>
            <span class="lo-drawer__sub">Who's online · Live activity</span>
        </div>
        <button type="button" class="lo-close" id="loClose" aria-label="Close">
            <svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.2" stroke-linecap="round" stroke-linejoin="round"><path d="M18 6 6 18M6 6l12 12"/></svg>
        </button>
    </div>
    <div class="lo-drawer__body">
        <section class="lo-card">
            <div class="lo-card__head">
                <h3 class="lo-card__title">Online now</h3>
                <span class="lo-card__stamp" id="loPresenceStamp">Loading…</span>
            </div>
            <div>
                <span class="lo-count" id="loPresenceCount">—</span>
                <span class="lo-count__label">Active in last 3 min · <span id="loTodayCount">—</span> today</span>
            </div>
            <div class="lo-role-grid" id="loRoleGrid">
                <div class="lo-role"><div class="lo-role__name">State admin</div><div class="lo-role__val" data-role="state_admin"><b>0</b> <span>/ 0</span></div></div>
                <div class="lo-role"><div class="lo-role__name">Hub admins</div><div class="lo-role__val" data-role="hub_admin"><b>0</b> <span>/ 0</span></div></div>
                <div class="lo-role"><div class="lo-role__name">District staff</div><div class="lo-role__val" data-role="district_staff"><b>0</b> <span>/ 0</span></div></div>
                <div class="lo-role"><div class="lo-role__name">Incubatees</div><div class="lo-role__val" data-role="incubatee"><b>0</b> <span>/ 0</span></div></div>
            </div>
            <div class="lo-avatars" id="loAvatars" aria-label="Users online"></div>
        </section>

        <section class="lo-card">
            <div class="lo-card__head">
                <h3 class="lo-card__title">Live activity</h3>
                <span class="lo-card__stamp" id="loActivityStamp">Loading…</span>
            </div>
            <div class="lo-feed" id="loFeed">
                <div class="lo-empty">Waiting for activity…</div>
            </div>
        </section>
    </div>
</aside>

<script>
(function () {
    const presenceEndpoint = @json(route('live-ops.presence'));
    const activitiesEndpoint = @json(route('live-ops.activities'));

    const openPollMs = 20000;    // while drawer is open
    const closedPollMs = 60000;  // while closed (just keeps badge fresh)

    const els = {
        trigger: document.getElementById('loTrigger'),
        triggerBadge: document.getElementById('loTriggerBadge'),
        drawer: document.getElementById('loDrawer'),
        backdrop: document.getElementById('loBackdrop'),
        close: document.getElementById('loClose'),
        presenceCount: document.getElementById('loPresenceCount'),
        presenceStamp: document.getElementById('loPresenceStamp'),
        todayCount: document.getElementById('loTodayCount'),
        roleGrid: document.getElementById('loRoleGrid'),
        avatars: document.getElementById('loAvatars'),
        feed: document.getElementById('loFeed'),
        activityStamp: document.getElementById('loActivityStamp'),
    };
    if (! els.drawer || ! els.feed) return;

    const iconSvg = {
        'user.login':            '<svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M15 3h4a2 2 0 0 1 2 2v14a2 2 0 0 1-2 2h-4"/><polyline points="10 17 15 12 10 7"/><line x1="15" y1="12" x2="3" y2="12"/></svg>',
        'cfa.created':           '<svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M16 21v-2a4 4 0 0 0-4-4H5a4 4 0 0 0-4 4v2"/><circle cx="8.5" cy="7" r="4"/><line x1="20" y1="8" x2="20" y2="14"/><line x1="23" y1="11" x2="17" y2="11"/></svg>',
        'mentorship.requested':  '<svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M17 21v-2a4 4 0 0 0-4-4H5a4 4 0 0 0-4 4v2"/><circle cx="9" cy="7" r="4"/><path d="M23 21v-2a4 4 0 0 0-3-3.87"/><path d="M16 3.13a4 4 0 0 1 0 7.75"/></svg>',
        'service.completed':     '<svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M22 11.08V12a10 10 0 1 1-5.93-9.14"/><polyline points="22 4 12 14.01 9 11.01"/></svg>',
        'batch.created':         '<svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><polygon points="12 2 2 7 12 12 22 7 12 2"/><polyline points="2 17 12 22 22 17"/><polyline points="2 12 12 17 22 12"/></svg>',
        'batch.onboarded':       '<svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M17 21v-2a4 4 0 0 0-4-4H5a4 4 0 0 0-4 4v2"/><circle cx="9" cy="7" r="4"/><path d="M23 21v-2a4 4 0 0 0-3-3.87"/><path d="M16 3.13a4 4 0 0 1 0 7.75"/></svg>',
        'default':               '<svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><circle cx="12" cy="12" r="10"/><line x1="12" y1="8" x2="12" y2="12"/><line x1="12" y1="16" x2="12.01" y2="16"/></svg>',
    };

    const iconColor = {
        'user.login': '#0891b2',
        'cfa.created': '#4f46e5',
        'mentorship.requested': '#db2777',
        'service.completed': '#16a34a',
        'batch.created': '#7c3aed',
        'batch.onboarded': '#0d9488',
        'default': '#64748b',
    };

    function roleLabel(role) {
        switch (role) {
            case 'state_admin': return 'State';
            case 'hub_admin': return 'Hub';
            case 'district_staff': return 'Staff';
            case 'incubatee': return 'Incubatee';
            default: return role || 'System';
        }
    }

    function formatRelative(iso) {
        if (! iso) return '';
        const t = new Date(iso).getTime();
        const diffSec = Math.max(0, Math.round((Date.now() - t) / 1000));
        if (diffSec < 10) return 'just now';
        if (diffSec < 60) return diffSec + 's ago';
        const m = Math.round(diffSec / 60);
        if (m < 60) return m + 'm ago';
        const h = Math.round(m / 60);
        if (h < 24) return h + 'h ago';
        const d = Math.round(h / 24);
        return d + 'd ago';
    }

    function setStamp(el, text, loading) {
        if (! el) return;
        el.textContent = text;
        el.classList.toggle('is-loading', !! loading);
    }

    let lastActivityId = 0;
    let firstActivityLoad = true;
    let isOpen = false;
    let presenceTimer = null;
    let activityTimer = null;
    let tickTimer = null;

    async function fetchJson(url) {
        const res = await fetch(url, {
            credentials: 'same-origin',
            headers: { 'Accept': 'application/json', 'X-Requested-With': 'XMLHttpRequest' },
        });
        if (! res.ok) throw new Error('HTTP ' + res.status);
        return res.json();
    }

    async function pollPresence() {
        try {
            if (isOpen) setStamp(els.presenceStamp, 'Refreshing…', true);
            const data = await fetchJson(presenceEndpoint);
            const total = Number(data.total_online || 0);

            els.triggerBadge.textContent = total > 99 ? '99+' : String(total);
            els.triggerBadge.classList.toggle('is-zero', total === 0);

            if (! isOpen) return;

            els.presenceCount.textContent = total.toLocaleString('en-IN');
            els.todayCount.textContent = Number(data.active_today || 0).toLocaleString('en-IN');

            const byOnline = data.by_role_online || {};
            const byTotal = data.by_role_total || {};
            els.roleGrid.querySelectorAll('.lo-role__val').forEach(function (cell) {
                const role = cell.getAttribute('data-role');
                const on = Number(byOnline[role] || 0);
                const tot = Number(byTotal[role] || 0);
                cell.innerHTML = '<b>' + on.toLocaleString('en-IN') + '</b> <span>/ ' + tot.toLocaleString('en-IN') + '</span>';
            });

            els.avatars.innerHTML = '';
            (data.online_list || []).forEach(function (u) {
                const a = document.createElement('span');
                a.className = 'lo-avatar';
                a.setAttribute('title', (u.name || '—') + ' · ' + roleLabel(u.role));
                a.setAttribute('data-role', u.role || '');
                a.textContent = (u.initials || (u.name || '?').slice(0, 1)).toUpperCase();
                els.avatars.appendChild(a);
            });

            setStamp(els.presenceStamp, 'Updated ' + new Date().toLocaleTimeString(), false);
        } catch (e) {
            if (isOpen) setStamp(els.presenceStamp, 'Offline', false);
        }
    }

    function renderActivityRow(item, isNew) {
        const color = iconColor[item.type] || iconColor.default;
        const svg = iconSvg[item.type] || iconSvg.default;
        const row = document.createElement('div');
        row.className = 'lo-feed__row' + (isNew ? ' is-new' : '');
        row.style.setProperty('--lo-color', color);

        const icon = document.createElement('span');
        icon.className = 'lo-feed__icon';
        icon.innerHTML = svg;
        row.appendChild(icon);

        const body = document.createElement('div');
        body.className = 'lo-feed__body';
        const title = document.createElement('p');
        title.className = 'lo-feed__title';
        title.textContent = item.title || '';
        body.appendChild(title);
        const meta = document.createElement('div');
        meta.className = 'lo-feed__meta';
        const parts = [];
        if (item.actor_role) parts.push('<strong>' + roleLabel(item.actor_role) + '</strong>');
        if (item.district_name) parts.push(item.district_name);
        meta.innerHTML = parts.join(' · ') || '<strong>System</strong>';
        body.appendChild(meta);
        row.appendChild(body);

        const time = document.createElement('span');
        time.className = 'lo-feed__time';
        time.setAttribute('data-time', item.created_at || '');
        time.textContent = formatRelative(item.created_at);
        row.appendChild(time);

        return row;
    }

    async function pollActivities() {
        if (! isOpen) return;
        try {
            setStamp(els.activityStamp, 'Refreshing…', true);
            const url = activitiesEndpoint + (lastActivityId ? ('?since=' + lastActivityId) : '');
            const data = await fetchJson(url);
            const items = data.items || [];

            if (items.length > 0) {
                if (firstActivityLoad) {
                    els.feed.innerHTML = '';
                    firstActivityLoad = false;
                    items.forEach(function (it) { els.feed.appendChild(renderActivityRow(it, false)); });
                } else {
                    items.slice().reverse().forEach(function (it) {
                        els.feed.insertBefore(renderActivityRow(it, true), els.feed.firstChild);
                    });
                    while (els.feed.childElementCount > 50) {
                        els.feed.removeChild(els.feed.lastChild);
                    }
                }
                lastActivityId = Math.max(lastActivityId, Number(data.last_id || 0));
            } else if (firstActivityLoad) {
                els.feed.innerHTML = '<div class="lo-empty">No recent activity yet.</div>';
                firstActivityLoad = false;
            }
            setStamp(els.activityStamp, 'Updated ' + new Date().toLocaleTimeString(), false);
        } catch (e) {
            setStamp(els.activityStamp, 'Offline', false);
        }
    }

    function tickRelativeTimes() {
        els.feed.querySelectorAll('.lo-feed__time').forEach(function (t) {
            const iso = t.getAttribute('data-time');
            if (iso) t.textContent = formatRelative(iso);
        });
    }

    function startTimers() {
        stopTimers();
        presenceTimer = setInterval(pollPresence, isOpen ? openPollMs : closedPollMs);
        if (isOpen) {
            activityTimer = setInterval(pollActivities, openPollMs);
            tickTimer = setInterval(tickRelativeTimes, 15000);
        }
    }
    function stopTimers() {
        if (presenceTimer) clearInterval(presenceTimer);
        if (activityTimer) clearInterval(activityTimer);
        if (tickTimer) clearInterval(tickTimer);
        presenceTimer = activityTimer = tickTimer = null;
    }

    function openDrawer() {
        isOpen = true;
        els.drawer.classList.add('is-open');
        els.backdrop.classList.add('is-open');
        els.trigger.classList.add('is-open');
        els.trigger.setAttribute('aria-expanded', 'true');
        document.body.style.overflow = 'hidden';
        pollPresence();
        pollActivities();
        startTimers();
        setTimeout(() => els.drawer.focus(), 100);
    }
    function closeDrawer() {
        isOpen = false;
        els.drawer.classList.remove('is-open');
        els.backdrop.classList.remove('is-open');
        els.trigger.classList.remove('is-open');
        els.trigger.setAttribute('aria-expanded', 'false');
        document.body.style.overflow = '';
        startTimers();
    }

    els.trigger.addEventListener('click', () => (isOpen ? closeDrawer() : openDrawer()));
    els.close.addEventListener('click', closeDrawer);
    els.backdrop.addEventListener('click', closeDrawer);
    document.addEventListener('keydown', (e) => { if (e.key === 'Escape' && isOpen) closeDrawer(); });
    document.addEventListener('visibilitychange', () => {
        if (document.visibilityState === 'visible') pollPresence();
    });

    pollPresence();
    startTimers();
})();
</script>

@endif
