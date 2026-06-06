<style>
    .admin-app-body {
        margin: 0;
        min-height: 100vh;
        font-family: 'DM Sans', system-ui, sans-serif;
        background: #f1f5f9;
        color: #0f172a;
    }
    .admin-app-body--dashboard {
        background: #f7f5f2;
        overflow-x: clip;
        max-width: 100%;
    }
    .admin-app-body--state-premium,
    .admin-app-body--hub-premium {
        background: #f7f5f2 !important;
    }
    .admin-app-body--dashboard .admin-topbar {
        background: #d04a02;
        backdrop-filter: none;
        -webkit-backdrop-filter: none;
        border-bottom: 1px solid rgba(255, 255, 255, 0.14);
        box-shadow: 0 4px 20px rgba(208, 74, 2, 0.28);
        border-radius: 0;
    }
    .admin-app-body--dashboard .admin-topbar::before,
    .admin-app-body--dashboard .admin-topbar::after {
        display: none;
    }
    .admin-app-body--dashboard .admin-topbar__dropdown-panel {
        background: #fff;
        border-color: #e2e8f0;
        box-shadow: 0 16px 40px rgba(208, 74, 2, 0.16);
    }
    .admin-topbar {
        position: sticky;
        top: 0;
        z-index: 100;
        background: rgba(255, 255, 255, 0.76);
        backdrop-filter: blur(18px);
        -webkit-backdrop-filter: blur(18px);
        border-bottom: 1px solid rgba(255, 255, 255, 0.72);
        box-shadow: 0 16px 40px rgba(99, 102, 241, 0.08);
        border-radius: 0 0 20px 20px;
        overflow: visible;
    }
    .admin-topbar::before,
    .admin-topbar::after {
        content: '';
        position: absolute;
        pointer-events: none;
        border-radius: 999px;
        z-index: 0;
    }
    .admin-topbar::before {
        width: 280px;
        height: 280px;
        top: -220px;
        left: -80px;
        background: radial-gradient(circle, rgba(129, 140, 248, 0.22) 0%, rgba(129, 140, 248, 0) 72%);
    }
    .admin-topbar::after {
        width: 260px;
        height: 260px;
        top: -205px;
        right: -70px;
        background: radial-gradient(circle, rgba(45, 212, 191, 0.2) 0%, rgba(45, 212, 191, 0) 72%);
    }
    .admin-topbar__inner {
        max-width: 100%;
        margin: 0 auto;
        padding: 0.65rem 1.25rem 0.65rem 1.5rem;
        display: flex;
        flex-wrap: wrap;
        align-items: center;
        gap: 0.75rem 1.25rem;
        position: relative;
        z-index: 1;
    }
    .admin-brand {
        display: flex;
        align-items: center;
        gap: 0.65rem;
        text-decoration: none;
        color: #0f172a;
        flex-shrink: 0;
    }
    .admin-brand__img {
        height: 46px;
        width: auto;
        max-width: 56px;
        object-fit: contain;
        border-radius: 10px;
        box-shadow: 0 2px 8px rgba(0,0,0,0.25);
        flex-shrink: 0;
    }
    .admin-brand__text { display: flex; flex-direction: column; line-height: 1.25; }
    .admin-brand__name {
        font-weight: 700;
        font-size: 1rem;
        letter-spacing: -0.01em;
        background: linear-gradient(90deg, #0f172a 0%, #3730a3 100%);
        -webkit-background-clip: text;
        -webkit-text-fill-color: transparent;
        background-clip: text;
        white-space: nowrap;
    }
    .admin-brand__sub { font-size: 0.65rem; color: #64748b; text-transform: uppercase; letter-spacing: 0.06em; }
    .admin-topbar__nav {
        display: flex;
        flex-wrap: wrap;
        align-items: center;
        gap: 0.2rem 0.15rem;
        flex: 1;
        justify-content: center;
        min-width: 0;
    }
    @media (min-width: 1100px) {
        .admin-topbar__nav { justify-content: flex-start; margin-left: 1rem; }
    }
    @media (min-width: 1100px) {
        .admin-topbar__nav--state-admin:not(.admin-topbar__nav--staff),
        .admin-topbar__nav--hub-admin {
            flex-wrap: nowrap;
        }
    }

    /* Compact brand at mid widths so the nav never clips */
    @media (max-width: 1360px) {
        .admin-topbar__inner { gap: 0.55rem 0.8rem; padding: 0.6rem 0.9rem; }
        .admin-brand { gap: 0.55rem; }
        .admin-brand__img { height: 40px; max-width: 48px; border-radius: 9px; }
        .admin-brand__name { font-size: 0.92rem; }
        .admin-brand__sub { font-size: 0.6rem; }
        .admin-topbar__nav { gap: 0.1rem 0.1rem; }
        .admin-topbar__link { padding: 0.4rem 0.55rem; font-size: 0.82rem; }
        .admin-topbar__dropdown-trigger { padding-right: 1.4rem; }
        .admin-topbar__right { gap: 0.55rem; }
    }
    @media (max-width: 1200px) {
        .admin-brand__name {
            max-width: 12rem;
            white-space: nowrap;
            overflow: hidden;
            text-overflow: ellipsis;
        }
    }
    @media (max-width: 1100px) {
        /* Let the nav wrap below the brand on small laptops/tablets */
        .admin-topbar__nav--state-admin:not(.admin-topbar__nav--staff),
        .admin-topbar__nav--hub-admin { flex-basis: 100%; order: 3; justify-content: flex-start; margin-left: 0; }
        .admin-topbar__right { margin-left: auto; }
    }
    .admin-topbar__details {
        position: relative;
        display: inline-block;
    }
    .admin-topbar__details > summary {
        list-style: none;
        cursor: pointer;
    }
    .admin-topbar__details > summary::-webkit-details-marker {
        display: none;
    }
    .admin-topbar__dropdown-trigger {
        padding-right: 1.65rem;
        position: relative;
        user-select: none;
    }
    .admin-topbar__dropdown-trigger::after {
        content: '';
        position: absolute;
        right: 0.55rem;
        top: 50%;
        width: 0.35rem;
        height: 0.35rem;
        margin-top: -0.2rem;
        border-right: 2px solid currentColor;
        border-bottom: 2px solid currentColor;
        transform: rotate(45deg);
        opacity: 0.65;
    }
    .admin-topbar__details[open] > .admin-topbar__dropdown-trigger::after {
        margin-top: 0;
        transform: rotate(225deg);
    }
    .admin-topbar__dropdown-panel--wide {
        min-width: 16.5rem;
    }
    .admin-topbar__dropdown-panel {
        position: absolute;
        left: 0;
        top: calc(100% + 0.15rem);
        min-width: 13.5rem;
        padding: 0.4rem;
        background: rgba(255, 255, 255, 0.98);
        backdrop-filter: blur(14px);
        -webkit-backdrop-filter: blur(14px);
        border: 1px solid rgba(226, 232, 240, 0.95);
        border-radius: 18px;
        box-shadow: 0 18px 44px rgba(15, 23, 42, 0.12);
        z-index: 220;
    }
    .admin-topbar__dropdown-item {
        display: block;
        padding: 0.55rem 0.85rem;
        border-radius: 12px;
        font-size: 0.875rem;
        font-weight: 500;
        color: #334155;
        text-decoration: none;
        transition: background-color 0.18s ease, color 0.18s ease;
    }
    .admin-topbar__dropdown-item:hover {
        background: rgba(99, 102, 241, 0.16);
        color: #1e1b4b;
    }
    .admin-topbar__dropdown-item:focus-visible {
        outline: 2px solid rgba(99, 102, 241, 0.45);
        outline-offset: 2px;
        background: rgba(99, 102, 241, 0.16);
        color: #1e1b4b;
    }
    .admin-topbar__dropdown-item.is-active {
        background: linear-gradient(135deg, rgba(79, 70, 229, 0.15), rgba(124, 58, 237, 0.12));
        color: #3730a3;
        font-weight: 600;
    }
    .admin-topbar__dropdown-kicker {
        margin: 0 0.5rem 0.5rem;
        padding-bottom: 0.45rem;
        border-bottom: 1px solid rgba(226, 232, 240, 0.95);
        font-size: 0.65rem;
        letter-spacing: 0.1em;
        color: #64748b;
        font-weight: 700;
    }
    .admin-topbar__dropdown-hr {
        border: none;
        border-top: 1px solid #e2e8f0;
        margin: 0.15rem 0.35rem 0.35rem;
    }
    .admin-topbar__details--profile {
        position: relative;
    }
    .admin-topbar__profile-summary {
        list-style: none;
        cursor: pointer;
        border-radius: 999px;
        outline: none;
    }
    .admin-topbar__profile-summary::-webkit-details-marker {
        display: none;
    }
    .admin-topbar__profile-summary:focus-visible {
        box-shadow: 0 0 0 3px rgba(99, 102, 241, 0.35);
    }
    .admin-topbar__details--profile[open] .admin-topbar__profile {
        box-shadow: 0 0 0 2px rgba(99, 102, 241, 0.35);
    }
    .admin-topbar__dropdown-panel--profile {
        left: auto;
        right: 0;
        min-width: 15rem;
        padding: 0.65rem 0.45rem 0.45rem;
        z-index: 240;
    }
    .admin-topbar__dropdown-profile-name {
        margin: 0 0.5rem 0.2rem;
        font-size: 0.9rem;
        font-weight: 700;
        color: #0f172a;
    }
    .admin-topbar__dropdown-profile-email {
        margin: 0 0.5rem 0.35rem;
        font-size: 0.75rem;
        color: #64748b;
        line-height: 1.35;
        word-break: break-all;
    }
    .admin-topbar__dropdown-logout {
        margin: 0;
        padding: 0;
    }
    .admin-topbar__dropdown-item.admin-topbar__dropdown-item--button {
        width: 100%;
        border: none;
        background: transparent;
        cursor: pointer;
        font: inherit;
        text-align: left;
        color: #b91c1c;
    }
    .admin-topbar__dropdown-item.admin-topbar__dropdown-item--button:hover {
        background: rgba(248, 113, 113, 0.1);
        color: #991b1b;
    }
    .admin-topbar__link {
        color: #334155;
        text-decoration: none;
        font-size: 0.875rem;
        font-weight: 500;
        padding: 0.45rem 0.75rem;
        border-radius: 8px;
        white-space: nowrap;
        transition: background-color 0.2s ease, color 0.2s ease, box-shadow 0.2s ease;
    }
    .admin-topbar__link:hover {
        color: #1e1b4b;
        background: rgba(99, 102, 241, 0.16);
        box-shadow: inset 0 0 0 1px rgba(99, 102, 241, 0.2);
    }
    .admin-topbar__link:focus-visible {
        outline: 2px solid rgba(99, 102, 241, 0.45);
        outline-offset: 2px;
        color: #1e1b4b;
        background: rgba(99, 102, 241, 0.16);
    }
    .admin-topbar__link.is-active {
        color: #fff;
        background: linear-gradient(135deg, #4f46e5, #7c3aed);
        box-shadow: 0 10px 24px rgba(99, 102, 241, 0.22);
    }
    .admin-topbar__right {
        display: flex;
        align-items: center;
        gap: 0.75rem;
        flex-shrink: 0;
        margin-left: auto;
    }
    .admin-topbar__details--notifications {
        position: relative;
    }
    .admin-topbar__notif-summary {
        list-style: none;
        cursor: pointer;
        position: relative;
        display: inline-flex;
        align-items: center;
        justify-content: center;
        width: 2.5rem;
        height: 2.5rem;
        border-radius: 10px;
        border: 1px solid rgba(148, 163, 184, 0.45);
        background: rgba(255, 255, 255, 0.85);
        color: #334155;
        outline: none;
    }
    .admin-topbar__notif-summary::-webkit-details-marker {
        display: none;
    }
    .admin-topbar__notif-summary:focus-visible {
        box-shadow: 0 0 0 3px rgba(99, 102, 241, 0.35);
    }
    .admin-topbar__details--notifications[open] .admin-topbar__notif-summary {
        border-color: rgba(99, 102, 241, 0.55);
        color: #4338ca;
        background: rgba(99, 102, 241, 0.1);
    }
    .admin-topbar__notif-icon {
        display: inline-flex;
        align-items: center;
        justify-content: center;
    }
    .admin-topbar__notif-badge {
        position: absolute;
        top: -4px;
        right: -4px;
        min-width: 1.15rem;
        height: 1.15rem;
        padding: 0 0.28rem;
        border-radius: 999px;
        background: linear-gradient(135deg, #ef4444, #f97316);
        color: #fff;
        font-size: 0.62rem;
        font-weight: 800;
        line-height: 1.15rem;
        text-align: center;
        box-shadow: 0 2px 8px rgba(239, 68, 68, 0.45);
    }
    .admin-topbar__dropdown-panel--notifications {
        left: auto;
        right: 0;
        min-width: min(22rem, calc(100vw - 2.5rem));
        max-height: min(70vh, 22rem);
        overflow-y: auto;
        padding: 0;
        z-index: 250;
    }
    .admin-topbar__notif-head {
        display: flex;
        align-items: center;
        justify-content: space-between;
        gap: 0.5rem;
        padding: 0.65rem 0.75rem 0.45rem;
        font-size: 0.72rem;
        font-weight: 800;
        letter-spacing: 0.06em;
        text-transform: uppercase;
        color: #64748b;
        border-bottom: 1px solid #e2e8f0;
    }
    .admin-topbar__notif-markall {
        margin: 0;
    }
    .admin-topbar__notif-markall button {
        border: none;
        background: none;
        padding: 0.15rem 0.35rem;
        font-size: 0.68rem;
        font-weight: 700;
        color: #4f46e5;
        cursor: pointer;
        font-family: inherit;
        text-decoration: underline;
        text-underline-offset: 2px;
    }
    .admin-topbar__notif-markall button:hover {
        color: #3730a3;
    }
    .admin-topbar__notif-item {
        display: flex;
        flex-direction: column;
        gap: 0.2rem;
        padding: 0.65rem 0.75rem;
        border-bottom: 1px solid #f1f5f9;
        text-decoration: none;
        color: inherit;
        transition: background 0.12s ease;
    }
    .admin-topbar__notif-item:hover {
        background: rgba(99, 102, 241, 0.06);
    }
    .admin-topbar__notif-item.is-unread {
        background: rgba(99, 102, 241, 0.08);
    }
    .admin-topbar__notif-item-title {
        font-size: 0.82rem;
        font-weight: 700;
        color: #0f172a;
    }
    .admin-topbar__notif-item-body {
        font-size: 0.75rem;
        color: #64748b;
        line-height: 1.35;
    }
    .admin-topbar__notif-item-time {
        font-size: 0.65rem;
        color: #94a3b8;
    }
    .admin-topbar__notif-empty {
        margin: 0;
        padding: 1rem 0.75rem;
        font-size: 0.82rem;
        color: #64748b;
    }
    .admin-topbar__notif-footer {
        display: block;
        text-align: center;
        padding: 0.55rem 0.75rem;
        font-size: 0.78rem;
        font-weight: 700;
        color: #4f46e5;
        text-decoration: none;
        border-top: 1px solid #e2e8f0;
        background: rgba(248, 250, 252, 0.9);
    }
    .admin-topbar__notif-footer:hover {
        background: #eef2ff;
        color: #3730a3;
    }
    .admin-topbar__profile {
        display: inline-flex;
        align-items: center;
        gap: 0.65rem;
        min-width: 0;
        padding: 0.2rem 0.2rem 0.2rem 0.25rem;
        border-radius: 999px;
    }
    .admin-topbar__avatar {
        width: 2.2rem;
        height: 2.2rem;
        border-radius: 999px;
        display: inline-flex;
        align-items: center;
        justify-content: center;
        background: linear-gradient(135deg, #f59e0b, #ec4899 55%, #6366f1);
        color: #fff;
        font-size: 0.78rem;
        font-weight: 700;
        letter-spacing: 0.04em;
        box-shadow: 0 10px 24px rgba(99, 102, 241, 0.18);
        flex-shrink: 0;
    }
    .admin-topbar__avatar--photo {
        object-fit: cover;
        padding: 0;
        display: block;
        box-sizing: border-box;
    }
    .admin-topbar__settings {
        display: inline-flex;
        align-items: center;
        justify-content: center;
        padding: 0.5rem 0.95rem;
        border-radius: 999px;
        font-size: 0.8rem;
        font-family: inherit;
        font-weight: 600;
        text-decoration: none;
        border: 1px solid rgba(148, 163, 184, 0.55);
        background: rgba(255, 255, 255, 0.8);
        color: #334155;
        flex-shrink: 0;
    }
    .admin-topbar__settings:hover {
        background: #fff;
        border-color: rgba(99, 102, 241, 0.45);
        color: #1e1b4b;
    }
    .admin-topbar__settings.is-active {
        background: linear-gradient(135deg, #4f46e5, #7c3aed);
        border-color: transparent;
        color: #fff;
    }
    .admin-topbar__user-wrap {
        display: flex;
        flex-direction: column;
        min-width: 0;
    }
    .admin-topbar__user {
        font-size: 0.82rem;
        font-weight: 600;
        color: #334155;
        max-width: 12rem;
        overflow: hidden;
        text-overflow: ellipsis;
        white-space: nowrap;
    }
    .admin-topbar__user-role {
        font-size: 0.66rem;
        line-height: 1.2;
        text-transform: uppercase;
        letter-spacing: 0.08em;
        color: #64748b;
        white-space: nowrap;
    }
    .admin-topbar__logout button {
        background: rgba(255, 255, 255, 0.8);
        border: 1px solid rgba(148, 163, 184, 0.55);
        color: #334155;
        padding: 0.5rem 0.95rem;
        border-radius: 999px;
        font-size: 0.8rem;
        font-family: inherit;
        font-weight: 600;
        cursor: pointer;
    }
    .admin-topbar__logout button:hover {
        background: #fff;
    }
    @media (max-width: 720px) {
        .admin-topbar__user-wrap {
            display: none;
        }
    }
    .admin-main {
        max-width: 100%;
        width: 100%;
        padding: 1.5rem clamp(1rem, 3vw, 2.25rem) 2.5rem;
        box-sizing: border-box;
        overflow-x: clip;
        min-width: 0;
    }
    .admin-page-head {
        margin-bottom: 1.25rem;
    }
    .admin-page-head h1 {
        font-size: 1.35rem;
        font-weight: 700;
        margin: 0 0 0.35rem;
        letter-spacing: -0.02em;
    }
    .admin-page-head .admin-page-meta {
        font-size: 0.875rem;
        color: #64748b;
        margin: 0;
    }
    .admin-page-head .pill {
        display: inline-block;
        background: #e2e8f0;
        padding: 0.15rem 0.45rem;
        border-radius: 6px;
        font-size: 0.75rem;
        font-weight: 600;
        color: #475569;
    }
    .banner {
        background: #ecfdf5;
        border: 1px solid #6ee7b7;
        color: #065f46;
        padding: 0.65rem 0.85rem;
        border-radius: 8px;
        margin-bottom: 1rem;
        font-size: 0.9rem;
    }
    .banner.banner--warning {
        background: #fffbeb;
        border-color: #fcd34d;
        color: #92400e;
    }
    .error-banner {
        background: #fef2f2;
        border: 1px solid #fecaca;
        color: #991b1b;
        padding: 0.65rem 0.85rem;
        border-radius: 8px;
        margin-bottom: 1rem;
        font-size: 0.9rem;
    }

    /* Laravel pagination arrow SVGs can render oversized without utility CSS. */
    nav[aria-label="Pagination Navigation"] svg,
    nav[aria-label="pagination"] svg,
    nav[role="navigation"][aria-label*="Pagination"] svg,
    nav[role="navigation"][aria-label*="pagination"] svg {
        width: 1rem;
        height: 1rem;
        display: inline-block;
        vertical-align: middle;
        flex-shrink: 0;
    }

    /* Guest / login / public confirmation — same visual language as admin */
    .app-auth-body {
        margin: 0;
        min-height: 100vh;
        font-family: 'DM Sans', system-ui, sans-serif;
        background: linear-gradient(145deg, #f0f4ff 0%, #ecfeff 40%, #f8fafc 100%);
        color: #0f172a;
        -webkit-font-smoothing: antialiased;
    }
    .app-auth-wrap {
        min-height: 100vh;
        display: flex;
        align-items: center;
        justify-content: center;
        padding: 1.5rem;
        box-sizing: border-box;
    }
    .app-auth-card {
        width: 100%;
        max-width: 420px;
        background: #fff;
        border: 1px solid #e2e8f0;
        border-radius: 16px;
        padding: 2rem;
        box-shadow: 0 12px 40px rgba(15, 23, 42, 0.08);
        box-sizing: border-box;
    }
    .app-auth-card--wide { max-width: 480px; }
    .app-auth-card h1 {
        font-size: 1.35rem;
        font-weight: 700;
        margin: 0 0 0.35rem;
        letter-spacing: -0.02em;
    }
    .app-auth-card .app-auth-lead {
        color: #64748b;
        font-size: 0.95rem;
        line-height: 1.55;
        margin: 0 0 1.25rem;
    }
    .app-auth-card label {
        display: block;
        font-size: 0.875rem;
        margin-bottom: 0.35rem;
        font-weight: 600;
        color: #334155;
    }
    .app-auth-card input[type="email"],
    .app-auth-card input[type="password"],
    .app-auth-card input[type="text"] {
        width: 100%;
        padding: 0.55rem 0.75rem;
        border: 1px solid #cbd5e1;
        border-radius: 8px;
        font-size: 1rem;
        font-family: inherit;
        margin-bottom: 1rem;
        box-sizing: border-box;
    }
    .app-auth-card input:focus {
        outline: none;
        border-color: #6366f1;
        box-shadow: 0 0 0 3px rgba(99, 102, 241, 0.2);
    }
    .app-auth-remember {
        display: flex;
        align-items: center;
        gap: 0.5rem;
        margin-bottom: 1rem;
        font-size: 0.875rem;
        color: #475569;
    }
    .app-auth-remember label { margin: 0; font-weight: 500; }
    .app-auth-btn-primary {
        display: block;
        width: 100%;
        padding: 0.65rem 1rem;
        border: none;
        border-radius: 8px;
        font-size: 1rem;
        font-weight: 600;
        font-family: inherit;
        cursor: pointer;
        text-align: center;
        text-decoration: none;
        box-sizing: border-box;
        background: linear-gradient(135deg, #4f46e5, #0d9488);
        color: #fff;
        transition: filter 0.15s, transform 0.15s;
    }
    .app-auth-btn-primary:hover {
        filter: brightness(1.05);
        transform: translateY(-1px);
    }
    .app-auth-error {
        color: #b91c1c;
        font-size: 0.875rem;
        margin-bottom: 1rem;
    }
    .app-auth-hint {
        font-size: 0.8rem;
        color: #64748b;
        margin-top: 1.25rem;
        line-height: 1.45;
    }
    .app-auth-hint code {
        font-size: 0.78rem;
        background: #f1f5f9;
        padding: 0.1rem 0.35rem;
        border-radius: 4px;
    }

    /* ====================================================================
       FUTURISTIC TOPBAR — glass morphism + aurora + micro-interactions
       Overrides earlier base topbar styles to deliver a designer feel
       without changing any existing markup or JS behaviour.
       ==================================================================== */
    @keyframes muyAuroraShift {
        0%   { background-position: 0% 50%; }
        50%  { background-position: 100% 50%; }
        100% { background-position: 0% 50%; }
    }
    @keyframes muyScan {
        0%   { transform: translateX(-40%); opacity: 0; }
        15%  { opacity: 1; }
        85%  { opacity: 1; }
        100% { transform: translateX(140%); opacity: 0; }
    }
    @keyframes muyBreathe {
        0%, 100% { box-shadow: 0 0 0 0 rgba(20, 184, 166, 0.55); }
        50%      { box-shadow: 0 0 0 8px rgba(20, 184, 166, 0); }
    }
    @keyframes muyRingSpin {
        to { transform: rotate(1turn); }
    }
    @keyframes muyShine {
        0%   { transform: translateX(-120%) skewX(-12deg); }
        100% { transform: translateX(260%) skewX(-12deg); }
    }

    /* --- Shell ------------------------------------------------------- */
    .admin-topbar {
        position: sticky;
        top: 0;
        z-index: 100;
        overflow: visible;
        background:
            linear-gradient(135deg, rgba(255, 255, 255, 0.92) 0%, rgba(248, 250, 252, 0.82) 55%, rgba(240, 253, 250, 0.86) 100%);
        backdrop-filter: blur(22px) saturate(1.2);
        -webkit-backdrop-filter: blur(22px) saturate(1.2);
        border-bottom: 1px solid rgba(255, 255, 255, 0.75);
        border-radius: 0 0 22px 22px;
        box-shadow:
            0 20px 50px -28px rgba(79, 70, 229, 0.28),
            0 6px 18px -10px rgba(20, 184, 166, 0.18),
            inset 0 1px 0 rgba(255, 255, 255, 0.9);
    }
    /* Animated aurora line on top border */
    .admin-topbar::before {
        content: '';
        position: absolute;
        left: 0; right: 0;
        top: 0;
        height: 2px;
        width: auto;
        border-radius: 0;
        background: linear-gradient(90deg,
            transparent 0%,
            rgba(20, 184, 166, 0.9) 18%,
            rgba(99, 102, 241, 0.9) 45%,
            rgba(236, 72, 153, 0.85) 68%,
            rgba(245, 158, 11, 0.85) 85%,
            transparent 100%);
        background-size: 220% 100%;
        animation: muyAuroraShift 14s ease-in-out infinite;
        opacity: 0.85;
        z-index: 2;
    }
    /* Sweeping scan-light just below the aurora line */
    .admin-topbar::after {
        content: '';
        position: absolute;
        left: 0;
        top: 2px;
        width: 22%;
        height: 1px;
        background: linear-gradient(90deg, transparent, rgba(255, 255, 255, 0.95), transparent);
        border-radius: 0;
        animation: muyScan 9s ease-in-out infinite;
        z-index: 2;
    }

    /* --- Brand ------------------------------------------------------- */
    .admin-brand {
        position: relative;
        padding: 0.3rem 0.55rem 0.3rem 0.3rem;
        border-radius: 14px;
        transition: transform 0.2s ease, background 0.2s ease;
    }
    .admin-brand:hover {
        background: linear-gradient(135deg, rgba(20, 184, 166, 0.08), rgba(99, 102, 241, 0.08));
        transform: translateY(-1px);
    }
    .admin-brand__img {
        position: relative;
        height: 44px;
        width: 44px;
        max-width: 44px;
        padding: 2px;
        border-radius: 12px;
        background: #fff;
        box-shadow:
            0 0 0 1.5px rgba(255, 255, 255, 0.9),
            0 6px 18px -6px rgba(20, 184, 166, 0.55),
            0 10px 24px -10px rgba(99, 102, 241, 0.4);
        isolation: isolate;
    }
    /* Rotating conic halo around the logo */
    .admin-brand::before {
        content: '';
        position: absolute;
        left: 0.1rem;
        top: 50%;
        width: 52px;
        height: 52px;
        transform: translateY(-50%);
        border-radius: 50%;
        background: conic-gradient(from 0deg,
            rgba(20, 184, 166, 0.55),
            rgba(99, 102, 241, 0.55),
            rgba(236, 72, 153, 0.45),
            rgba(245, 158, 11, 0.45),
            rgba(20, 184, 166, 0.55));
        filter: blur(10px);
        opacity: 0.45;
        animation: muyRingSpin 16s linear infinite;
        pointer-events: none;
        z-index: 0;
    }
    .admin-brand > * { position: relative; z-index: 1; }
    .admin-brand__name {
        background: linear-gradient(90deg, #0f172a 0%, #0d9488 40%, #4f46e5 80%, #0f172a 100%);
        background-size: 240% 100%;
        -webkit-background-clip: text;
        -webkit-text-fill-color: transparent;
        background-clip: text;
        animation: muyAuroraShift 12s ease-in-out infinite;
    }
    .admin-brand__sub {
        display: inline-block;
        margin-top: 0.15rem;
        padding: 0.08rem 0.45rem;
        border-radius: 999px;
        background: linear-gradient(135deg, rgba(20, 184, 166, 0.14), rgba(99, 102, 241, 0.14));
        border: 1px solid rgba(20, 184, 166, 0.3);
        color: #0f766e !important;
        font-weight: 700;
        letter-spacing: 0.12em;
    }

    /* --- Nav links --------------------------------------------------- */
    .admin-topbar__nav {
        gap: 0.25rem 0.25rem;
    }
    .admin-topbar__link {
        position: relative;
        display: inline-flex;
        align-items: center;
        gap: 0.45rem;
        color: #334155;
        font-weight: 600;
        padding: 0.5rem 0.9rem 0.5rem 0.75rem;
        border-radius: 999px;
        border: 1px solid transparent;
        transition:
            background 0.2s ease,
            color 0.2s ease,
            border-color 0.2s ease,
            transform 0.2s ease,
            box-shadow 0.2s ease;
        overflow: hidden;
        isolation: isolate;
    }
    /* Shimmer sweep on hover */
    .admin-topbar__link::before {
        content: '';
        position: absolute;
        inset: 0;
        background: linear-gradient(90deg, transparent, rgba(255,255,255,0.55), transparent);
        transform: translateX(-120%) skewX(-12deg);
        pointer-events: none;
        z-index: -1;
        opacity: 0;
    }
    .admin-topbar__link:hover {
        color: #0f766e;
        background: linear-gradient(135deg, rgba(20, 184, 166, 0.1), rgba(99, 102, 241, 0.08));
        border-color: rgba(20, 184, 166, 0.22);
        transform: translateY(-1px);
        box-shadow: 0 6px 14px -8px rgba(20, 184, 166, 0.45);
    }
    .admin-topbar__link:hover::before {
        opacity: 1;
        animation: muyShine 0.9s ease-out;
    }
    .admin-topbar__link.is-active {
        color: #fff;
        background:
            linear-gradient(135deg, #0d9488 0%, #4f46e5 55%, #7c3aed 100%);
        border-color: transparent;
        box-shadow:
            0 12px 24px -10px rgba(79, 70, 229, 0.55),
            0 6px 16px -8px rgba(20, 184, 166, 0.5),
            inset 0 1px 0 rgba(255, 255, 255, 0.35);
    }
    /* Active link keeps its sheen always flowing subtly (not for dropdown triggers — they use ::after for the chevron) */
    .admin-topbar__link.is-active:not(.admin-topbar__dropdown-trigger)::after {
        content: '';
        position: absolute;
        inset: 0;
        background: linear-gradient(90deg, transparent 0%, rgba(255,255,255,0.28) 45%, transparent 100%);
        background-size: 240% 100%;
        animation: muyAuroraShift 6s ease-in-out infinite;
        pointer-events: none;
        border-radius: inherit;
    }

    /* Dropdown arrow — rotate smoothly; ensure right padding is preserved over the link shorthand */
    .admin-topbar__dropdown-trigger {
        padding-right: 1.75rem;
    }
    .admin-topbar__dropdown-trigger::after {
        transition: transform 0.2s ease, margin-top 0.2s ease;
        /* Ensure the chevron is always visible, even on active state */
        content: '';
        position: absolute;
        right: 0.6rem;
        top: 50%;
        width: 0.38rem;
        height: 0.38rem;
        margin-top: -0.22rem;
        border-right: 2px solid currentColor;
        border-bottom: 2px solid currentColor;
        transform: rotate(45deg);
        opacity: 0.75;
        z-index: 2;
        pointer-events: none;
    }
    .admin-topbar__details[open] > .admin-topbar__dropdown-trigger::after {
        margin-top: -0.1rem;
        transform: rotate(225deg);
    }

    /* Dropdown panel — glassy, with a subtle top accent */
    .admin-topbar__dropdown-panel {
        border: 1px solid rgba(226, 232, 240, 0.9);
        background: rgba(255, 255, 255, 0.98);
        backdrop-filter: blur(18px) saturate(1.15);
        -webkit-backdrop-filter: blur(18px) saturate(1.15);
        box-shadow:
            0 24px 54px -18px rgba(15, 23, 42, 0.22),
            0 8px 24px -12px rgba(20, 184, 166, 0.24);
        overflow: visible;
    }
    .admin-topbar__dropdown-panel::before {
        content: '';
        position: absolute;
        left: 0; right: 0;
        top: 0;
        height: 2px;
        background: linear-gradient(90deg, #14b8a6, #6366f1, #ec4899);
        background-size: 220% 100%;
        animation: muyAuroraShift 10s ease-in-out infinite;
    }
    .admin-topbar__dropdown-item {
        position: relative;
    }
    .admin-topbar__dropdown-item:hover {
        background: linear-gradient(135deg, rgba(20, 184, 166, 0.12), rgba(99, 102, 241, 0.12));
        color: #0f766e;
        transform: translateX(2px);
    }
    .admin-topbar__dropdown-item.is-active {
        background: linear-gradient(135deg, rgba(20, 184, 166, 0.18), rgba(99, 102, 241, 0.16));
        color: #0d9488;
    }

    /* --- Right side controls ---------------------------------------- */
    .admin-topbar__right { gap: 0.65rem; }

    .admin-topbar__notif-summary {
        border: 1px solid rgba(20, 184, 166, 0.22);
        background:
            linear-gradient(135deg, rgba(255, 255, 255, 0.95), rgba(240, 253, 250, 0.92));
        color: #0f766e;
        transition: transform 0.2s ease, box-shadow 0.2s ease, border-color 0.2s ease;
    }
    .admin-topbar__notif-summary:hover {
        transform: translateY(-1px);
        border-color: rgba(20, 184, 166, 0.5);
        box-shadow: 0 10px 22px -10px rgba(20, 184, 166, 0.45);
    }
    .admin-topbar__details--notifications[open] .admin-topbar__notif-summary {
        background: linear-gradient(135deg, rgba(20, 184, 166, 0.14), rgba(99, 102, 241, 0.12));
        border-color: rgba(20, 184, 166, 0.55);
        color: #0d9488;
    }
    .admin-topbar__notif-badge {
        background: linear-gradient(135deg, #ef4444, #f97316);
        animation: muyBreathe 2.6s ease-in-out infinite;
    }

    /* Profile pill */
    .admin-topbar__profile {
        display: inline-flex;
        align-items: center;
        gap: 0.55rem;
        padding: 0.22rem 0.7rem 0.22rem 0.25rem;
        border-radius: 999px;
        background:
            linear-gradient(135deg, rgba(255, 255, 255, 0.95), rgba(248, 250, 252, 0.9));
        border: 1px solid rgba(20, 184, 166, 0.22);
        transition: transform 0.2s ease, box-shadow 0.2s ease, border-color 0.2s ease;
    }
    .admin-topbar__profile-summary:hover .admin-topbar__profile {
        transform: translateY(-1px);
        border-color: rgba(20, 184, 166, 0.5);
        box-shadow: 0 12px 24px -12px rgba(79, 70, 229, 0.35);
    }
    .admin-topbar__details--profile[open] .admin-topbar__profile {
        background: linear-gradient(135deg, rgba(20, 184, 166, 0.12), rgba(99, 102, 241, 0.12));
        border-color: rgba(20, 184, 166, 0.55);
        box-shadow: 0 12px 28px -14px rgba(79, 70, 229, 0.4);
    }
    .admin-topbar__avatar {
        position: relative;
        background: linear-gradient(135deg, #14b8a6 0%, #6366f1 55%, #ec4899 100%);
        box-shadow:
            0 8px 20px -6px rgba(20, 184, 166, 0.55),
            0 0 0 2px #fff,
            0 0 0 3px rgba(20, 184, 166, 0.35);
    }
    /* Online pulse dot */
    .admin-topbar__avatar::after {
        content: '';
        position: absolute;
        right: -1px;
        bottom: -1px;
        width: 0.58rem;
        height: 0.58rem;
        border-radius: 50%;
        background: #10b981;
        border: 2px solid #fff;
        box-shadow: 0 0 0 0 rgba(16, 185, 129, 0.55);
        animation: muyBreathe 2.4s ease-in-out infinite;
    }

    /* Dashboard-body overrides: PwC orange masthead (wins over animated defaults below) */
    .admin-app-body--dashboard .admin-topbar {
        background: #d04a02;
        border-bottom: 1px solid rgba(255, 255, 255, 0.14);
        border-radius: 0;
    }
    .admin-app-body--dashboard .admin-topbar__inner {
        padding: 0.6rem 1.15rem;
    }
    .admin-app-body--dashboard .admin-brand::before {
        display: none;
    }
    .admin-app-body--dashboard .admin-brand:hover {
        background: transparent;
        transform: none;
    }
    .admin-app-body--dashboard .admin-brand__name {
        background: none;
        background-size: unset;
        -webkit-background-clip: unset;
        background-clip: unset;
        -webkit-text-fill-color: #fff;
        color: #fff;
        animation: none;
    }
    .admin-app-body--dashboard .admin-brand__sub {
        background: rgba(255, 255, 255, 0.16);
        border-color: rgba(255, 255, 255, 0.32);
        color: rgba(255, 255, 255, 0.95) !important;
    }
    .admin-app-body--dashboard .admin-brand__img {
        box-shadow: 0 2px 10px rgba(0, 0, 0, 0.18);
    }
    .admin-app-body--dashboard .admin-topbar__nav--state-admin,
    .admin-app-body--dashboard .admin-topbar__nav--hub-admin {
        border-top: 1px solid rgba(255, 255, 255, 0.14);
        padding-top: 0.4rem;
        margin-top: 0.1rem;
    }
    @media (min-width: 1101px) {
        .admin-app-body--dashboard .admin-topbar__nav--state-admin,
        .admin-app-body--dashboard .admin-topbar__nav--hub-admin {
            border-top: none;
            padding-top: 0;
            margin-top: 0;
        }
    }
    .admin-app-body--dashboard .admin-topbar__link {
        color: rgba(255, 255, 255, 0.94);
        border-color: transparent;
    }
    .admin-app-body--dashboard .admin-topbar__link:hover {
        color: #fff;
        background: rgba(255, 255, 255, 0.18);
        border-color: rgba(255, 255, 255, 0.22);
        transform: none;
        box-shadow: none;
    }
    .admin-app-body--dashboard .admin-topbar__link:hover::before {
        opacity: 0;
        animation: none;
    }
    .admin-app-body--dashboard .admin-topbar__link.is-active,
    .admin-app-body--dashboard .admin-topbar__link.is-active.admin-topbar__dropdown-trigger {
        color: #d04a02 !important;
        background: #fff !important;
        border-color: transparent !important;
        box-shadow: 0 4px 14px rgba(0, 0, 0, 0.12) !important;
        transform: none;
    }
    .admin-app-body--dashboard .admin-topbar__link.is-active:not(.admin-topbar__dropdown-trigger)::after {
        display: none;
    }
    .admin-app-body--dashboard .admin-topbar__notif-summary {
        border-color: rgba(255, 255, 255, 0.4);
        background: rgba(255, 255, 255, 0.95);
        color: #d04a02;
    }
    .admin-app-body--dashboard .admin-topbar__notif-summary:hover {
        transform: none;
        border-color: #fff;
        box-shadow: 0 4px 12px rgba(0, 0, 0, 0.12);
    }
    .admin-app-body--dashboard .admin-topbar__details--notifications[open] .admin-topbar__notif-summary {
        color: #d04a02;
        background: #fff;
        border-color: #fff;
    }
    .admin-app-body--dashboard .admin-topbar__profile {
        background: #fff;
        border-color: rgba(255, 255, 255, 0.45);
    }
    .admin-app-body--dashboard .admin-topbar__profile-summary:hover .admin-topbar__profile {
        transform: none;
        border-color: #fff;
        box-shadow: 0 4px 14px rgba(0, 0, 0, 0.12);
    }
    .admin-app-body--dashboard .admin-topbar__details--profile[open] .admin-topbar__profile {
        background: #fff;
        border-color: #fff;
        box-shadow: 0 4px 14px rgba(0, 0, 0, 0.14);
    }
    .admin-app-body--dashboard .admin-topbar__user {
        color: #2d2d2d;
    }
    .admin-app-body--dashboard .admin-topbar__user-role {
        color: #6b6b6b;
    }
    .admin-app-body--dashboard .admin-topbar__avatar {
        background: linear-gradient(135deg, #d04a02, #eb8c00);
        box-shadow: 0 0 0 2px #fff, 0 0 0 3px rgba(208, 74, 2, 0.35);
    }
    .admin-app-body--dashboard .admin-topbar__settings {
        background: rgba(255, 255, 255, 0.95);
        border-color: rgba(255, 255, 255, 0.4);
        color: #2d2d2d;
    }
    .admin-app-body--dashboard .admin-topbar__settings:hover,
    .admin-app-body--dashboard .admin-topbar__settings.is-active {
        background: #fff;
        color: #d04a02;
        border-color: #fff;
    }

    /* ---- Hub admin premium header -------------------------------- */
    .admin-topbar--hub {
        background: linear-gradient(115deg, #7a2f03 0%, #a63d02 22%, #d04a02 52%, #e07012 78%, #c94408 100%);
        border-bottom: 1px solid rgba(255, 255, 255, 0.2);
        box-shadow: 0 8px 32px rgba(122, 47, 3, 0.35);
    }
    .admin-topbar--hub::before {
        display: block;
        width: 420px;
        height: 420px;
        top: -280px;
        left: 8%;
        background: radial-gradient(circle, rgba(255, 255, 255, 0.14) 0%, rgba(255, 255, 255, 0) 68%);
        opacity: 1;
    }
    .admin-topbar--hub::after {
        display: block;
        width: 360px;
        height: 360px;
        top: -240px;
        right: 5%;
        background: radial-gradient(circle, rgba(255, 200, 120, 0.2) 0%, rgba(255, 200, 120, 0) 70%);
        opacity: 1;
    }
    .admin-topbar__inner--hub {
        display: grid;
        grid-template-columns: minmax(0, auto) minmax(0, 1fr) auto;
        align-items: center;
        gap: 0.85rem 1.1rem;
        padding: 0.72rem 1.35rem;
    }
    @media (max-width: 1100px) {
        .admin-topbar__inner--hub {
            grid-template-columns: minmax(0, 1fr) auto;
        }
        .admin-topbar__inner--hub .admin-topbar__nav-rail {
            grid-column: 1 / -1;
            order: 3;
        }
    }
    .admin-brand--hub {
        gap: 0.7rem;
        min-width: 0;
        max-width: 240px;
    }
    .admin-brand--hub .admin-brand__logo-wrap {
        display: flex;
        align-items: center;
        justify-content: center;
        width: 48px;
        height: 48px;
        border-radius: 14px;
        background: rgba(255, 255, 255, 0.96);
        border: 1px solid rgba(255, 255, 255, 0.55);
        box-shadow: 0 4px 16px rgba(0, 0, 0, 0.18);
        flex-shrink: 0;
    }
    .admin-brand--hub .admin-brand__img {
        height: 36px;
        max-width: 40px;
        box-shadow: none;
        border-radius: 8px;
    }
    .admin-brand--hub .admin-brand__text {
        gap: 0.1rem;
        min-width: 0;
    }
    .admin-brand--hub .admin-brand__eyebrow {
        font-size: 0.58rem;
        font-weight: 700;
        letter-spacing: 0.12em;
        text-transform: uppercase;
        color: rgba(255, 255, 255, 0.72);
        line-height: 1.2;
    }
    .admin-brand--hub .admin-brand__name {
        font-size: 1.2rem;
        font-weight: 800;
        letter-spacing: -0.03em;
        line-height: 1.05;
        color: #fff !important;
        -webkit-text-fill-color: #fff !important;
        background: none !important;
    }
    .admin-brand--hub .admin-brand__hub {
        display: -webkit-box;
        -webkit-box-orient: vertical;
        -webkit-line-clamp: 2;
        overflow: hidden;
        font-size: 0.76rem;
        font-weight: 600;
        line-height: 1.3;
        color: rgba(255, 255, 255, 0.92);
        max-width: 13.5rem;
    }
    .admin-topbar__nav-rail {
        display: flex;
        justify-content: center;
        min-width: 0;
    }
    .admin-topbar--hub .admin-topbar__nav-rail {
        padding: 0.28rem;
        background: rgba(0, 0, 0, 0.14);
        border: 1px solid rgba(255, 255, 255, 0.2);
        border-radius: 14px;
        backdrop-filter: blur(10px);
        -webkit-backdrop-filter: blur(10px);
        box-shadow: inset 0 1px 0 rgba(255, 255, 255, 0.12);
    }
    .admin-topbar--hub .admin-topbar__nav--hub-admin {
        flex: 1;
        flex-wrap: nowrap;
        gap: 0.15rem;
        min-width: 0;
        margin: 0;
        padding: 0;
        border-top: none !important;
        justify-content: center;
    }
    .admin-topbar--hub .admin-topbar__nav--hub-admin .admin-topbar__link {
        display: inline-flex;
        align-items: center;
        gap: 0.42rem;
        padding: 0.5rem 0.85rem;
        font-size: 0.84rem;
        font-weight: 600;
        border-radius: 10px;
        color: rgba(255, 255, 255, 0.95);
        white-space: nowrap;
    }
    .admin-topbar--hub .admin-topbar__link-ico {
        width: 1.15rem;
        height: 1.15rem;
        color: rgba(255, 255, 255, 0.92) !important;
    }
    .admin-topbar--hub .admin-topbar__link:hover {
        color: #fff;
        background: rgba(255, 255, 255, 0.16);
        border-color: transparent;
    }
    .admin-topbar--hub .admin-topbar__link:hover .admin-topbar__link-ico {
        color: #fff !important;
        transform: none;
    }
    .admin-topbar--hub .admin-topbar__link.is-active,
    .admin-topbar--hub .admin-topbar__link.is-active.admin-topbar__dropdown-trigger {
        color: #a63d02 !important;
        background: #fff !important;
        box-shadow: 0 4px 14px rgba(0, 0, 0, 0.14) !important;
    }
    .admin-topbar--hub .admin-topbar__link.is-active .admin-topbar__link-ico {
        color: #d04a02 !important;
    }
    .admin-topbar--hub .admin-topbar__dropdown-trigger::after {
        border-color: currentColor;
        opacity: 0.55;
    }
    .admin-topbar--hub .admin-topbar__right {
        gap: 0.45rem;
    }
    .admin-topbar--hub .admin-topbar__notif-summary,
    .admin-topbar--hub .admin-topbar__settings {
        width: 2.35rem;
        height: 2.35rem;
        border-radius: 11px;
        border-color: rgba(255, 255, 255, 0.35);
        background: rgba(255, 255, 255, 0.14);
        color: #fff;
    }
    .admin-topbar--hub .admin-topbar__notif-summary:hover,
    .admin-topbar--hub .admin-topbar__settings:hover {
        background: rgba(255, 255, 255, 0.26);
        border-color: rgba(255, 255, 255, 0.5);
        color: #fff;
    }
    .admin-topbar--hub .admin-topbar__details--notifications[open] .admin-topbar__notif-summary {
        background: #fff;
        color: #d04a02;
    }
    .admin-topbar--hub .admin-topbar__profile {
        padding: 0.3rem 0.7rem 0.3rem 0.32rem;
        border-radius: 999px;
        background: rgba(255, 255, 255, 0.98);
        border-color: rgba(255, 255, 255, 0.55);
        gap: 0.5rem;
        box-shadow: 0 4px 16px rgba(0, 0, 0, 0.12);
    }
    .admin-topbar--hub .admin-topbar__user {
        max-width: 9.5rem;
        overflow: hidden;
        text-overflow: ellipsis;
        white-space: nowrap;
        font-size: 0.8rem;
    }
    .admin-topbar--hub .admin-topbar__user-role {
        font-size: 0.62rem;
        letter-spacing: 0.04em;
        text-transform: uppercase;
    }
    .admin-topbar--hub .admin-topbar__avatar {
        width: 34px;
        height: 34px;
        font-size: 0.72rem;
    }
    @media (min-width: 1101px) {
        .admin-app-body--hub-admin .admin-topbar__nav--hub-admin {
            flex-basis: auto;
            order: unset;
        }
    }
    .admin-topbar--hub .lo-trigger {
        border-color: rgba(255, 255, 255, 0.35);
        background: rgba(255, 255, 255, 0.14);
        color: #fff;
        margin-right: 0;
    }
    .admin-topbar--hub .lo-trigger:hover {
        background: rgba(255, 255, 255, 0.26);
        border-color: rgba(255, 255, 255, 0.5);
        color: #fff;
        transform: none;
    }
    .admin-topbar--hub .lo-trigger.is-open {
        background: #fff;
        color: #d04a02;
        border-color: #fff;
    }
    .admin-topbar--hub .admin-topbar__hamburger {
        border-color: rgba(255, 255, 255, 0.35);
        background: rgba(255, 255, 255, 0.14);
        color: #fff;
    }
    .admin-topbar--hub .admin-topbar__hamburger-icon span {
        background: #fff;
    }
    @media (max-width: 768px) {
        .admin-topbar__inner--hub {
            padding: 0.65rem 0.85rem;
        }
        .admin-brand--hub {
            max-width: 100%;
        }
        .admin-brand--hub .admin-brand__hub {
            max-width: 100%;
        }
        .admin-topbar--hub .admin-topbar__nav-rail {
            overflow-x: auto;
            -webkit-overflow-scrolling: touch;
            justify-content: flex-start;
        }
        .admin-topbar--hub .admin-topbar__user-wrap {
            display: none;
        }
        .admin-topbar--hub .admin-topbar__profile {
            padding: 0.25rem;
        }
    }

    /* ---- District staff header — scrollable nav rail ---------------- */
    .admin-topbar--staff {
        overflow: visible;
    }
    .admin-topbar__inner--staff {
        display: grid;
        grid-template-columns: minmax(0, auto) minmax(0, 1fr) auto;
        align-items: center;
        gap: 0.65rem 0.85rem;
        padding: 0.65rem 1rem;
    }
    @media (max-width: 1100px) {
        .admin-topbar__inner--staff {
            grid-template-columns: minmax(0, 1fr) auto;
        }
        .admin-topbar__inner--staff .admin-topbar__nav-rail--staff {
            grid-column: 1 / -1;
            order: 3;
        }
    }
    .admin-brand--staff {
        gap: 0.65rem;
        min-width: 0;
        max-width: 11.5rem;
    }
    .admin-brand--staff .admin-brand__logo-wrap {
        display: flex;
        align-items: center;
        justify-content: center;
        width: 44px;
        height: 44px;
        border-radius: 12px;
        background: #fff;
        border: 1px solid #e8ecf1;
        box-shadow: 0 2px 10px rgba(55, 71, 79, 0.1);
        flex-shrink: 0;
    }
    .admin-brand--staff .admin-brand__img {
        height: 32px;
        max-width: 36px;
        box-shadow: none;
        border-radius: 8px;
    }
    .admin-brand--staff .admin-brand__text {
        gap: 0.08rem;
        min-width: 0;
    }
    .admin-brand--staff .admin-brand__eyebrow {
        font-size: 0.56rem;
        font-weight: 700;
        letter-spacing: 0.1em;
        text-transform: uppercase;
        color: #78909c;
        line-height: 1.2;
    }
    .admin-brand--staff .admin-brand__name {
        font-size: 1.15rem;
        font-weight: 800;
        letter-spacing: -0.03em;
        line-height: 1.05;
        color: #263238 !important;
        -webkit-text-fill-color: #263238 !important;
        background: none !important;
        max-width: none;
        overflow: visible;
        text-overflow: unset;
    }
    .admin-brand--staff .admin-brand__hub {
        display: -webkit-box;
        -webkit-box-orient: vertical;
        -webkit-line-clamp: 1;
        overflow: hidden;
        font-size: 0.72rem;
        font-weight: 600;
        line-height: 1.25;
        color: #546e7a;
        max-width: 10.5rem;
    }
    .admin-topbar__nav-rail--staff {
        position: relative;
        min-width: 0;
        max-width: 100%;
        padding: 0.24rem;
        border-radius: 14px;
        background: #eef2f6;
        border: 1px solid #dde3ea;
        box-shadow: inset 0 1px 0 rgba(255, 255, 255, 0.85);
    }
    .admin-topbar__nav-rail--staff::before,
    .admin-topbar__nav-rail--staff::after {
        content: '';
        position: absolute;
        top: 0.24rem;
        bottom: 0.24rem;
        width: 1.25rem;
        pointer-events: none;
        z-index: 2;
        opacity: 0;
        transition: opacity 0.2s ease;
    }
    .admin-topbar__nav-rail--staff::before {
        left: 0.24rem;
        border-radius: 12px 0 0 12px;
        background: linear-gradient(90deg, #eef2f6 30%, rgba(238, 242, 246, 0));
    }
    .admin-topbar__nav-rail--staff::after {
        right: 0.24rem;
        border-radius: 0 12px 12px 0;
        background: linear-gradient(270deg, #eef2f6 30%, rgba(238, 242, 246, 0));
    }
    .admin-topbar__nav-rail--staff.can-scroll-left::before,
    .admin-topbar__nav-rail--staff.can-scroll-right::after {
        opacity: 1;
    }
    .admin-topbar__nav-rail--staff {
        overflow-x: auto;
        overflow-y: hidden;
        scrollbar-width: none;
        -ms-overflow-style: none;
        -webkit-overflow-scrolling: touch;
    }
    .admin-topbar__nav-rail--staff::-webkit-scrollbar {
        display: none;
    }
    .admin-topbar__nav--staff {
        display: inline-flex;
        flex-wrap: nowrap;
        align-items: center;
        gap: 0.18rem;
        width: max-content;
        min-width: min(100%, max-content);
        margin: 0;
        padding: 0;
        flex: none;
        justify-content: flex-start;
    }
    .admin-topbar__nav--staff .admin-topbar__link,
    .admin-topbar__nav--staff .admin-topbar__details {
        flex: 0 0 auto;
        flex-shrink: 0;
    }
    .admin-topbar__nav--staff .admin-topbar__link {
        overflow: visible;
        white-space: nowrap;
        padding: 0.46rem 0.72rem;
        font-size: 0.8rem;
    }
    .admin-topbar__nav--staff .admin-topbar__link-text {
        overflow: visible;
        text-overflow: unset;
        max-width: none;
    }
    .admin-topbar--staff .admin-topbar__right {
        gap: 0.4rem;
        flex-shrink: 0;
    }
    .admin-topbar--staff .admin-topbar__theme-toggle {
        font-size: 0.68rem;
        padding: 0.32rem 0.5rem;
        white-space: nowrap;
    }
    @media (max-width: 1280px) {
        .admin-topbar--staff .admin-topbar__user-wrap {
            display: none;
        }
        .admin-topbar--staff .admin-topbar__profile {
            padding: 0.22rem;
        }
    }
    @media (min-width: 1101px) {
        .admin-app-body--staff-premium .admin-topbar__nav--staff {
            flex-basis: auto;
            order: unset;
        }
    }

    /* Reduce motion preference */
    @media (prefers-reduced-motion: reduce) {
        .admin-topbar::before,
        .admin-topbar::after,
        .admin-brand::before,
        .admin-brand__name,
        .admin-topbar__link.is-active::after,
        .admin-topbar__dropdown-panel::before,
        .admin-topbar__notif-badge,
        .admin-topbar__avatar::after {
            animation: none !important;
        }
    }

    /* --- Nav & dropdown icons --------------------------------------- */
    .admin-topbar__link-ico {
        display: inline-flex;
        align-items: center;
        justify-content: center;
        width: 1.1rem;
        height: 1.1rem;
        flex-shrink: 0;
        color: #0d9488;
        transition: color 0.2s ease, transform 0.2s ease;
    }
    .admin-topbar__link-ico svg {
        width: 100%;
        height: 100%;
        display: block;
    }
    .admin-topbar__link:hover .admin-topbar__link-ico {
        color: #0f766e;
        transform: scale(1.08);
    }
    .admin-topbar__link.is-active .admin-topbar__link-ico {
        color: #ffffff;
    }

    .admin-topbar__link-text {
        display: inline;
        line-height: 1;
    }

    /* Dropdown items: icon + text flex layout */
    .admin-topbar__dropdown-item {
        display: flex;
        align-items: center;
        gap: 0.55rem;
    }
    .admin-topbar__dropdown-item .admin-topbar__link-ico {
        width: 1rem;
        height: 1rem;
        color: #0d9488;
    }
    .admin-topbar__dropdown-item:hover .admin-topbar__link-ico {
        color: #0f766e;
    }
    .admin-topbar__dropdown-item.is-active .admin-topbar__link-ico {
        color: #0d9488;
    }
    .admin-topbar__dropdown-subgroup {
        position: relative;
    }
    /* Keep submenu hover path connected so it does not collapse while crossing over */
    .admin-topbar__dropdown-subgroup::after {
        content: '';
        position: absolute;
        top: 0;
        right: -0.45rem;
        width: 0.45rem;
        height: 100%;
    }
    .admin-topbar__dropdown-subtrigger {
        display: flex;
        align-items: center;
        gap: 0.55rem;
        width: 100%;
        padding: 0.55rem 2rem 0.55rem 0.85rem;
        border-radius: 12px;
        font-size: 0.875rem;
        font-weight: 500;
        color: #334155;
        text-decoration: none;
        transition: background 0.15s, color 0.15s;
        cursor: default;
        box-sizing: border-box;
    }
    .admin-topbar__dropdown-subtrigger::after {
        content: '';
        position: absolute;
        right: 0.95rem;
        top: 50%;
        width: 0.36rem;
        height: 0.36rem;
        margin-top: -0.22rem;
        border-right: 2px solid currentColor;
        border-bottom: 2px solid currentColor;
        transform: rotate(-45deg);
        opacity: 0.7;
    }
    .admin-topbar__dropdown-subgroup:hover > .admin-topbar__dropdown-subtrigger,
    .admin-topbar__dropdown-subgroup.is-active > .admin-topbar__dropdown-subtrigger {
        background: linear-gradient(135deg, rgba(20, 184, 166, 0.12), rgba(99, 102, 241, 0.12));
        color: #0f766e;
    }
    .admin-topbar__dropdown-subpanel {
        display: none;
        position: absolute;
        left: calc(100% - 0.08rem);
        top: 0;
        min-width: 15rem;
        padding: 0.4rem;
        background: rgba(255, 255, 255, 0.98);
        border: 1px solid rgba(226, 232, 240, 0.95);
        border-radius: 14px;
        box-shadow: 0 18px 40px rgba(15, 23, 42, 0.14);
        z-index: 260;
    }
    .admin-topbar__dropdown-subgroup:hover > .admin-topbar__dropdown-subpanel,
    .admin-topbar__dropdown-subgroup:focus-within > .admin-topbar__dropdown-subpanel {
        display: block;
    }
    .admin-topbar__dropdown-item--button {
        /* ensure the logout button aligns like other items */
        display: flex;
        align-items: center;
        gap: 0.55rem;
    }
    .admin-topbar__dropdown-item--button .admin-topbar__link-ico {
        color: #b91c1c;
    }
    .admin-topbar__dropdown-item--button:hover .admin-topbar__link-ico {
        color: #991b1b;
    }

    /* Compact icons at mid widths so the nav never clips */
    @media (max-width: 1360px) {
        .admin-topbar__link-ico { width: 1rem; height: 1rem; }
        .admin-topbar__link { padding-left: 0.6rem; padding-right: 0.7rem; gap: 0.38rem; }
    }
    @media (max-width: 900px) {
        .admin-topbar__link-ico { width: 0.95rem; height: 0.95rem; }
    }

    /* ── Hamburger button (hidden on desktop) ──────────────────────── */
    .admin-topbar__hamburger {
        display: none;
        align-items: center;
        justify-content: center;
        width: 2.4rem;
        height: 2.4rem;
        border-radius: 10px;
        border: 1px solid rgba(20, 184, 166, 0.25);
        background: linear-gradient(135deg, rgba(255,255,255,0.95), rgba(240,253,250,0.92));
        color: #0f766e;
        cursor: pointer;
        flex-shrink: 0;
        outline: none;
        order: 5;
        transition: background 0.2s ease, border-color 0.2s ease, transform 0.2s ease;
    }
    .admin-topbar__hamburger:hover {
        border-color: rgba(20, 184, 166, 0.52);
        background: linear-gradient(135deg, rgba(20,184,166,0.12), rgba(99,102,241,0.10));
        transform: translateY(-1px);
    }
    .admin-topbar__hamburger[aria-expanded="true"] {
        background: linear-gradient(135deg, rgba(20,184,166,0.14), rgba(99,102,241,0.12));
        border-color: rgba(20, 184, 166, 0.58);
    }
    .admin-topbar__hamburger-icon {
        display: flex;
        flex-direction: column;
        justify-content: center;
        gap: 4px;
        width: 18px;
    }
    .admin-topbar__hamburger-icon span {
        display: block;
        height: 2px;
        border-radius: 2px;
        background: currentColor;
        transform-origin: center;
        transition: transform 0.22s ease, opacity 0.22s ease, width 0.22s ease;
    }
    .admin-topbar__hamburger-icon span:nth-child(1) { width: 18px; }
    .admin-topbar__hamburger-icon span:nth-child(2) { width: 13px; }
    .admin-topbar__hamburger-icon span:nth-child(3) { width: 18px; }
    .admin-topbar__hamburger[aria-expanded="true"] .admin-topbar__hamburger-icon span:nth-child(1) {
        transform: translateY(6px) rotate(45deg);
    }
    .admin-topbar__hamburger[aria-expanded="true"] .admin-topbar__hamburger-icon span:nth-child(2) {
        opacity: 0;
        width: 0;
    }
    .admin-topbar__hamburger[aria-expanded="true"] .admin-topbar__hamburger-icon span:nth-child(3) {
        transform: translateY(-6px) rotate(-45deg);
    }

    /* ── Mobile layout (≤ 768 px) ───────────────────────────────────── */
    @media (max-width: 768px) {
        /* Show hamburger */
        .admin-topbar__hamburger { display: inline-flex; }

        /* Tighter header padding */
        .admin-topbar__inner {
            padding: 0.55rem 0.85rem;
            gap: 0.4rem;
            flex-wrap: wrap;
        }

        /* Hide all navs by default; show when toggled */
        .admin-topbar__nav {
            display: none;
            flex-direction: column;
            align-items: stretch;
            width: 100%;
            flex-basis: 100%;
            order: 10;
            margin-top: 0.35rem;
            padding: 0.5rem 0 0.6rem;
            border-top: 1px solid rgba(20, 184, 166, 0.18);
            gap: 0.15rem;
            /* override any tablet-width flex-wrap: nowrap */
            flex-wrap: wrap !important;
        }
        .admin-topbar__nav.is-open { display: flex; }

        .admin-topbar__nav-rail--staff {
            width: 100%;
            flex-basis: 100%;
            order: 6;
            overflow: visible;
            background: transparent;
            border: none;
            box-shadow: none;
            padding: 0;
        }
        .admin-topbar__nav-rail--staff::before,
        .admin-topbar__nav-rail--staff::after {
            display: none;
        }
        .admin-topbar__nav--staff {
            width: 100%;
            flex-wrap: wrap !important;
        }

        /* Full-width pill links in mobile drawer */
        .admin-topbar__link {
            border-radius: 10px;
            width: 100%;
            box-sizing: border-box;
            justify-content: flex-start;
            white-space: normal;
        }

        /* Details dropdown — inline (not floating) on mobile */
        .admin-topbar__details {
            display: block;
            position: static;
            width: 100%;
        }
        .admin-topbar__dropdown-panel {
            position: static !important;
            min-width: 0;
            width: 100%;
            box-shadow: none !important;
            border: none !important;
            border-radius: 10px;
            background: rgba(241, 245, 249, 0.55);
            backdrop-filter: none;
            -webkit-backdrop-filter: none;
            padding: 0.3rem 0 0.3rem 0.85rem;
            margin-top: 0.2rem;
        }
        /* Hide the aurora stripe inside mobile dropdown panel */
        .admin-topbar__dropdown-panel::before { display: none; }
        .admin-topbar__dropdown-subtrigger {
            cursor: pointer;
            padding-right: 0.85rem;
        }
        .admin-topbar__dropdown-subtrigger::after {
            display: none;
        }
        .admin-topbar__dropdown-subpanel {
            display: block;
            position: static;
            min-width: 0;
            width: 100%;
            border: none;
            box-shadow: none;
            background: transparent;
            padding: 0.2rem 0 0.2rem 0.6rem;
        }

        /* Compact right-side strip */
        .admin-topbar__right {
            gap: 0.45rem;
            margin-left: auto;
        }

        /* Smaller notification bell */
        .admin-topbar__notif-summary {
            width: 2.2rem;
            height: 2.2rem;
        }

        /* Dropdown panels that anchor right (notifications, profile) –
           clamp to viewport so they never overflow off-screen */
        .admin-topbar__dropdown-panel--notifications,
        .admin-topbar__dropdown-panel--profile {
            position: absolute !important;
            right: 0;
            left: auto;
            min-width: min(17rem, calc(100vw - 1.5rem));
            max-width: calc(100vw - 1.5rem);
        }

        /* Brand: shrink on small screens */
        .admin-brand__name {
            font-size: 0.82rem;
            max-width: 9rem;
            white-space: nowrap;
            overflow: hidden;
            text-overflow: ellipsis;
        }
        .admin-brand__img { height: 36px; width: 36px; max-width: 36px; }
        .admin-brand { gap: 0.45rem; }
    }

    /* ── Extra-small phones (≤ 420 px) ─────────────────────────────── */
    @media (max-width: 420px) {
        .admin-brand__name { display: none; }
        .admin-brand__sub  { display: none; }
        .admin-topbar__inner { padding: 0.5rem 0.65rem; }
    }
</style>
