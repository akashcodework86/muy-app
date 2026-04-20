<style>
    .admin-app-body {
        margin: 0;
        min-height: 100vh;
        font-family: 'DM Sans', system-ui, sans-serif;
        background: #f1f5f9;
        color: #0f172a;
    }
    .admin-app-body--dashboard {
        background:
            radial-gradient(circle at top left, rgba(251, 191, 36, 0.28), transparent 22%),
            radial-gradient(circle at top right, rgba(45, 212, 191, 0.24), transparent 24%),
            radial-gradient(circle at 20% 80%, rgba(129, 140, 248, 0.18), transparent 26%),
            linear-gradient(180deg, #fff8ef 0%, #eef7ff 42%, #f8fbff 100%);
        overflow-x: clip;
        max-width: 100%;
    }
    .admin-app-body--dashboard .admin-topbar {
        background: rgba(255, 255, 255, 0.76);
        backdrop-filter: blur(18px);
        -webkit-backdrop-filter: blur(18px);
        border-bottom: 1px solid rgba(255, 255, 255, 0.72);
        box-shadow: 0 16px 40px rgba(99, 102, 241, 0.08);
    }
    .admin-app-body--dashboard .admin-brand {
        color: #0f172a;
    }
    .admin-app-body--dashboard .admin-brand__sub {
        color: #64748b;
    }
    .admin-app-body--dashboard .admin-topbar__link {
        color: #334155;
    }
    .admin-app-body--dashboard .admin-topbar__link:hover {
        color: #1e1b4b;
        background: rgba(99, 102, 241, 0.12);
    }
    .admin-app-body--dashboard .admin-topbar__link.is-active {
        color: #fff;
        background: linear-gradient(135deg, #4f46e5, #7c3aed);
        box-shadow: 0 10px 24px rgba(99, 102, 241, 0.22);
    }
    .admin-app-body--dashboard .admin-topbar__user {
        color: #334155;
    }
    .admin-app-body--dashboard .admin-topbar__user-role {
        color: #64748b;
    }
    .admin-app-body--dashboard .admin-topbar__dropdown-panel {
        background: rgba(255, 255, 255, 0.96);
        border-color: rgba(255, 255, 255, 0.85);
        box-shadow: 0 20px 48px rgba(79, 70, 229, 0.14);
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
        .admin-topbar__nav--state-admin {
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
        .admin-topbar__nav--state-admin { flex-basis: 100%; order: 3; justify-content: flex-start; margin-left: 0; }
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
    .admin-topbar__dropdown-panel {
        position: absolute;
        left: 0;
        top: calc(100% + 0.35rem);
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
        transition: background 0.15s, color 0.15s;
    }
    .admin-topbar__dropdown-item:hover {
        background: rgba(99, 102, 241, 0.12);
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
        transition: background 0.15s, color 0.15s;
    }
    .admin-topbar__link:hover {
        color: #1e1b4b;
        background: rgba(99, 102, 241, 0.12);
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
</style>
