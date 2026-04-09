<style>
    .admin-app-body {
        margin: 0;
        min-height: 100vh;
        font-family: 'DM Sans', system-ui, sans-serif;
        background: #f1f5f9;
        color: #0f172a;
    }
    .admin-app-body--dashboard {
        background: linear-gradient(145deg, #f0f4ff 0%, #ecfeff 40%, #f8fafc 100%);
    }
    .admin-topbar {
        position: sticky;
        top: 0;
        z-index: 100;
        background: linear-gradient(90deg, #0b1220 0%, #151f32 55%, #1e293b 100%);
        border-bottom: 1px solid rgba(148, 163, 184, 0.2);
        box-shadow: 0 4px 24px rgba(15, 23, 42, 0.25);
    }
    .admin-topbar__inner {
        max-width: 100%;
        margin: 0 auto;
        padding: 0.65rem 1.25rem 0.65rem 1.5rem;
        display: flex;
        flex-wrap: wrap;
        align-items: center;
        gap: 0.75rem 1.25rem;
    }
    .admin-brand {
        display: flex;
        align-items: center;
        gap: 0.65rem;
        text-decoration: none;
        color: #f8fafc;
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
        background: linear-gradient(90deg, #ffffff 0%, #c7d2fe 100%);
        -webkit-background-clip: text;
        -webkit-text-fill-color: transparent;
        background-clip: text;
        white-space: nowrap;
    }
    .admin-brand__sub { font-size: 0.65rem; color: #94a3b8; text-transform: uppercase; letter-spacing: 0.06em; }
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
    .admin-topbar__link {
        color: #cbd5e1;
        text-decoration: none;
        font-size: 0.875rem;
        font-weight: 500;
        padding: 0.45rem 0.75rem;
        border-radius: 8px;
        white-space: nowrap;
        transition: background 0.15s, color 0.15s;
    }
    .admin-topbar__link:hover {
        color: #fff;
        background: rgba(255, 255, 255, 0.08);
    }
    .admin-topbar__link.is-active {
        color: #fff;
        background: rgba(79, 70, 229, 0.35);
        box-shadow: inset 0 0 0 1px rgba(129, 140, 248, 0.35);
    }
    .admin-topbar__right {
        display: flex;
        align-items: center;
        gap: 0.75rem;
        flex-shrink: 0;
        margin-left: auto;
    }
    .admin-topbar__user {
        font-size: 0.8rem;
        color: #e2e8f0;
        max-width: 12rem;
        overflow: hidden;
        text-overflow: ellipsis;
        white-space: nowrap;
    }
    .admin-topbar__logout button {
        background: rgba(255, 255, 255, 0.08);
        border: 1px solid rgba(148, 163, 184, 0.35);
        color: #e2e8f0;
        padding: 0.4rem 0.85rem;
        border-radius: 8px;
        font-size: 0.8rem;
        font-family: inherit;
        cursor: pointer;
    }
    .admin-topbar__logout button:hover {
        background: rgba(255, 255, 255, 0.12);
    }
    .admin-main {
        max-width: none;
        width: 100%;
        padding: 1.5rem clamp(1rem, 3vw, 2.25rem) 2.5rem;
        box-sizing: border-box;
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
    .error-banner {
        background: #fef2f2;
        border: 1px solid #fecaca;
        color: #991b1b;
        padding: 0.65rem 0.85rem;
        border-radius: 8px;
        margin-bottom: 1rem;
        font-size: 0.9rem;
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
