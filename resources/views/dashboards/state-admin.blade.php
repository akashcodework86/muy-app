<!DOCTYPE html>
<html lang="{{ str_replace('_', '-', app()->getLocale()) }}">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>State Command — {{ config('app.name') }}</title>
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=DM+Sans:ital,opsz,wght@0,9..40,400;0,9..40,500;0,9..40,600;0,9..40,700;0,9..40,800&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.1/css/all.min.css" crossorigin="anonymous" referrerpolicy="no-referrer">
    <script src="https://cdn.jsdelivr.net/npm/chart.js@4.4.1/dist/chart.umd.min.js" crossorigin="anonymous"></script>
    @include('partials.admin-shell-styles')
    <style>
        .admin-app-body--dashboard .admin-main {
            padding: 0.65rem clamp(0.75rem, 2vw, 1.35rem) 1.25rem;
        }
        .admin-app-body--dash-unified .admin-main {
            padding: 0 clamp(0.75rem, 2vw, 1.35rem) 1.25rem;
        }
        .sad-unified-strip {
            display: flex;
            flex-wrap: nowrap;
            align-items: center;
            justify-content: flex-start;
            gap: 0.65rem 1rem;
            margin: 0 calc(-1 * clamp(0.75rem, 2vw, 1.35rem)) 0.65rem;
            padding: 0.55rem clamp(0.75rem, 2vw, 1.35rem);
        }
        .sad-unified-strip__left {
            display: flex;
            flex-wrap: nowrap;
            align-items: baseline;
            gap: 0.35rem 0.65rem;
            flex: 0 0 auto;
            min-width: 0;
            white-space: nowrap;
        }
        .sad-unified-strip__title {
            margin: 0;
            font-size: 1.05rem;
            font-weight: 800;
            letter-spacing: -0.02em;
            line-height: 1.2;
            white-space: nowrap;
            flex-shrink: 0;
        }
        .sad-unified-strip__sub {
            margin: 0;
            font-size: 0.72rem;
            line-height: 1.35;
            opacity: 0.88;
            white-space: nowrap;
        }
        .sad-unified-strip__sub strong {
            font-weight: 700;
        }
        .sad-unified-strip__meta {
            display: flex;
            flex: 1 1 auto;
            min-width: 0;
            align-items: center;
        }
        .sad-ground-ticker {
            width: 100%;
            min-width: 0;
            display: flex;
            align-items: center;
            gap: 0.55rem;
            padding: 0.42rem 0.9rem;
            border-radius: 10px;
            background: rgba(255, 255, 255, 0.12);
            border: 1px solid rgba(255, 255, 255, 0.18);
            font-size: 0.95rem;
            font-weight: 500;
            line-height: 1.45;
            overflow: hidden;
        }
        .sad-ground-ticker__icon {
            flex-shrink: 0;
            opacity: 0.9;
            font-size: 0.88rem;
        }
        .sad-ground-ticker__text {
            flex: 1;
            min-width: 0;
            white-space: nowrap;
            overflow: hidden;
            text-overflow: ellipsis;
            transition: opacity 0.35s ease;
        }
        .sad-ground-ticker__text.is-fading {
            opacity: 0;
        }
        @include('dashboards.state-admin._theme-styles')
        .sad {
            font-family: 'DM Sans', system-ui, sans-serif;
            color: var(--sad-text);
            max-width: 100%;
        }
        .sad-masthead {
            display: flex;
            flex-wrap: wrap;
            align-items: flex-end;
            justify-content: space-between;
            gap: 0.75rem 1.25rem;
            padding: 1rem 1.15rem;
            border-radius: var(--sad-radius);
            background: var(--sad-surface);
            color: var(--sad-text);
            border: 1px solid var(--sad-border);
            border-top: 3px solid var(--sad-brand);
            box-shadow: var(--sad-shadow);
            margin-bottom: 0.65rem;
        }
        .sad-masthead__eyebrow {
            display: inline-flex;
            align-items: center;
            gap: 0.4rem;
            font-size: 0.68rem;
            font-weight: 700;
            letter-spacing: 0.08em;
            text-transform: uppercase;
            color: var(--sad-brand-deep);
            margin-bottom: 0.35rem;
        }
        .sad-masthead h1 {
            margin: 0;
            font-size: clamp(1.25rem, 2.5vw, 1.75rem);
            font-weight: 800;
            letter-spacing: -0.03em;
            line-height: 1.1;
        }
        .sad-masthead__sub {
            margin: 0.35rem 0 0;
            font-size: 0.82rem;
            color: var(--sad-muted);
            max-width: 36rem;
            line-height: 1.4;
        }
        .sad-masthead__meta {
            display: flex;
            flex-wrap: wrap;
            gap: 0.45rem;
            justify-content: flex-end;
        }
        .sad-badge {
            display: inline-flex;
            align-items: center;
            gap: 0.35rem;
            padding: 0.38rem 0.65rem;
            border-radius: 999px;
            background: #f8fafc;
            border: 1px solid var(--sad-border);
            font-size: 0.72rem;
            font-weight: 600;
            color: #334155;
            white-space: nowrap;
        }
        .sad-badge--live::before {
            content: '';
            width: 7px;
            height: 7px;
            border-radius: 50%;
            background: #22c55e;
            box-shadow: 0 0 0 3px rgba(34, 197, 94, 0.25);
            animation: sadPulse 1.6s ease-in-out infinite;
        }
        @keyframes sadPulse {
            0%, 100% { box-shadow: 0 0 0 0 rgba(34, 197, 94, 0.45); }
            50% { box-shadow: 0 0 0 5px rgba(34, 197, 94, 0); }
        }
        .sad-stat-chips {
            display: grid;
            grid-template-columns: repeat(8, minmax(0, 1fr));
            gap: 0.28rem;
            width: 100%;
            margin-bottom: 0.6rem;
        }
        @media (max-width: 900px) {
            .sad-stat-chips {
                display: flex;
                flex-wrap: nowrap;
                overflow-x: auto;
                gap: 0.28rem;
                padding-bottom: 0.15rem;
                -webkit-overflow-scrolling: touch;
                scrollbar-width: thin;
            }
            .sad-stat-chip {
                flex: 0 0 calc(25% - 0.3rem);
                min-width: 6.5rem;
            }
        }
        .sad-stat-chip {
            display: flex;
            flex-direction: column;
            align-items: flex-start;
            justify-content: center;
            gap: 0.2rem;
            padding: 0.5rem 0.55rem;
            min-width: 0;
            border-radius: 10px;
            background: var(--sad-surface);
            border: 1px solid var(--sad-border);
            box-shadow: 0 2px 8px rgba(15, 23, 42, 0.06), 0 1px 2px rgba(15, 23, 42, 0.04);
            position: relative;
            overflow: hidden;
            transition: transform 0.16s ease, box-shadow 0.16s ease;
        }
        .sad-stat-chip::before {
            content: '';
            position: absolute;
            top: 0;
            left: 0;
            right: 0;
            height: 3px;
            background: var(--sad-border);
        }
        .sad-stat-chip:hover {
            transform: translateY(-1px);
            box-shadow: 0 6px 16px rgba(15, 23, 42, 0.09), 0 2px 4px rgba(15, 23, 42, 0.05);
        }
        .sad-stat-chip__ico {
            display: inline-flex;
            align-items: center;
            justify-content: center;
            width: 1.5rem;
            height: 1.5rem;
            border-radius: 7px;
            font-size: 0.68rem;
            margin-top: 0.15rem;
        }
        .sad-stat-chip__label {
            font-size: 0.58rem;
            font-weight: 700;
            text-transform: uppercase;
            letter-spacing: 0.06em;
            color: var(--sad-muted);
            line-height: 1.15;
        }
        .sad-stat-chip__val {
            font-size: clamp(0.78rem, 0.95vw, 0.92rem);
            font-weight: 800;
            letter-spacing: -0.02em;
            color: var(--sad-text);
            line-height: 1.1;
            max-width: 100%;
            overflow: hidden;
            text-overflow: ellipsis;
            white-space: nowrap;
        }
        .sad-stat-chip--cfa::before { background: linear-gradient(90deg, #26a69a, #4db6ac); }
        .sad-stat-chip--cfa .sad-stat-chip__ico { background: #e0f2f1; color: #00897b; }
        .sad-stat-chip--target::before { background: linear-gradient(90deg, #ff8a65, #ffab91); }
        .sad-stat-chip--target .sad-stat-chip__ico { background: #fff3e0; color: #ef6c00; }
        .sad-stat-chip--today::before { background: linear-gradient(90deg, #ffb300, #ffca28); }
        .sad-stat-chip--today .sad-stat-chip__ico { background: #fff8e1; color: #f9a825; }
        .sad-stat-chip--onboard::before { background: linear-gradient(90deg, #f06292, #f48fb1); }
        .sad-stat-chip--onboard .sad-stat-chip__ico { background: #fce4ec; color: #d81b60; }
        .sad-stat-chip--services::before { background: linear-gradient(90deg, #42a5f5, #90caf9); }
        .sad-stat-chip--services .sad-stat-chip__ico { background: #e3f2fd; color: #1e88e5; }
        .sad-stat-chip--districts::before { background: linear-gradient(90deg, #78909c, #b0bec5); }
        .sad-stat-chip--districts .sad-stat-chip__ico { background: #eceff1; color: #546e7a; }
        .sad-stat-chip--blocks::before { background: linear-gradient(90deg, #66bb6a, #a5d6a7); }
        .sad-stat-chip--blocks .sad-stat-chip__ico { background: #e8f5e9; color: #43a047; }
        .sad-stat-chip--savings::before { background: linear-gradient(90deg, #ab47bc, #ce93d8); }
        .sad-stat-chip--savings .sad-stat-chip__ico { background: #f3e5f5; color: #8e24aa; }
        .sad-stat-chip.is-up .sad-stat-chip__val { color: var(--sad-green-deep); }
        .sad-stat-chip.is-down .sad-stat-chip__val { color: #b45309; }
        .sad-alerts {
            display: flex;
            flex-wrap: wrap;
            gap: 0.4rem;
            margin-bottom: 0.55rem;
        }
        .sad-alert {
            display: inline-flex;
            align-items: center;
            gap: 0.35rem;
            padding: 0.32rem 0.6rem;
            border-radius: 999px;
            font-size: 0.7rem;
            font-weight: 600;
            border: 1px solid transparent;
        }
        .sad-alert--warn {
            background: #fffbeb;
            border-color: #fcd34d;
            color: #92400e;
        }
        .sad-alert--info {
            background: #f0f9ff;
            border-color: #bae6fd;
            color: #0369a1;
        }
        .sad-alert--ok {
            background: #ecfdf5;
            border-color: #6ee7b7;
            color: var(--sad-green-deep);
        }
        .sad-nav {
            display: flex;
            flex-wrap: wrap;
            gap: 0.35rem;
            margin-bottom: 0.6rem;
            padding: 0.35rem;
            background: var(--sad-surface);
            border: 1px solid var(--sad-border);
            border-radius: var(--sad-radius);
            position: sticky;
            top: 0.5rem;
            z-index: 20;
            box-shadow: var(--sad-shadow);
        }
        .sad-nav__btn {
            flex: 1;
            min-width: 7rem;
            border: none;
            background: transparent;
            font-family: inherit;
            font-size: 0.78rem;
            font-weight: 700;
            color: var(--sad-muted);
            padding: 0.5rem 0.65rem;
            border-radius: 8px;
            cursor: pointer;
            display: inline-flex;
            align-items: center;
            justify-content: center;
            gap: 0.4rem;
            transition: background 0.15s, color 0.15s;
        }
        .sad-nav__btn:hover { background: #f1f5f9; color: var(--sad-text); }
        .sad-nav__btn.is-active {
            background: var(--sad-brand);
            color: #fff;
            box-shadow: var(--sad-nav-shadow, 0 2px 8px rgba(15, 23, 42, 0.12));
        }
        .sad-panel { display: none; animation: sadFade 0.25s ease; }
        .sad-panel.is-active { display: block; }
        @keyframes sadFade {
            from { opacity: 0; transform: translateY(4px); }
            to { opacity: 1; transform: translateY(0); }
        }
        .sad-grid {
            display: grid;
            gap: 0.55rem;
        }
        .sad-grid--2 { grid-template-columns: 1fr 1fr; }
        .sad-grid--3 { grid-template-columns: 1.1fr 1fr 1fr; }
        @media (max-width: 1100px) {
            .sad-grid--2, .sad-grid--3 { grid-template-columns: 1fr; }
        }
        .sad-card {
            background: var(--sad-surface);
            border: 1px solid var(--sad-border);
            border-radius: var(--sad-radius);
            padding: 0.75rem 0.85rem;
            box-shadow: 0 1px 2px rgba(15, 23, 42, 0.04);
            min-width: 0;
        }
        .sad-card__head {
            display: flex;
            align-items: center;
            justify-content: space-between;
            gap: 0.5rem;
            margin-bottom: 0.55rem;
        }
        .sad-card__title {
            font-size: 0.82rem;
            font-weight: 800;
            margin: 0;
            display: flex;
            align-items: center;
            gap: 0.4rem;
        }
        .sad-card__title i { color: var(--sad-brand); font-size: 0.85rem; }
        .sad-card__hint {
            font-size: 0.68rem;
            color: var(--sad-muted);
            margin: -0.35rem 0 0.5rem;
            line-height: 1.35;
        }
        .sad-card__tag {
            font-size: 0.62rem;
            font-weight: 700;
            padding: 0.2rem 0.45rem;
            border-radius: 6px;
            background: var(--sad-brand-light);
            color: var(--sad-brand-deep);
        }
        .sad-progress-block { margin-bottom: 0.65rem; }
        .sad-progress-block:last-child { margin-bottom: 0; }
        .sad-progress-top {
            display: flex;
            justify-content: space-between;
            align-items: baseline;
            gap: 0.5rem;
            font-size: 0.75rem;
            font-weight: 600;
            margin-bottom: 0.3rem;
        }
        .sad-progress-top strong { font-size: 0.95rem; font-weight: 800; }
        .sad-progress-track {
            height: 8px;
            border-radius: 999px;
            background: #e2e8f0;
            overflow: hidden;
        }
        .sad-progress-fill {
            height: 100%;
            border-radius: 999px;
            background: var(--sad-brand-grad);
            transition: width 0.6s ease;
        }
        .sad-progress-fill--sky {
            background: linear-gradient(90deg, #eb8c00, #ffb600);
        }
        .sad-progress-foot {
            font-size: 0.68rem;
            color: var(--sad-muted);
            margin-top: 0.35rem;
            line-height: 1.35;
        }
        .sad-signals {
            display: grid;
            grid-template-columns: 1fr 1fr;
            gap: 0.4rem;
        }
        .sad-signal {
            padding: 0.45rem 0.5rem;
            border-radius: 10px;
            background: #f8fafc;
            border: 1px solid var(--sad-border);
        }
        .sad-signal span {
            display: block;
            font-size: 0.62rem;
            color: var(--sad-muted);
            font-weight: 600;
        }
        .sad-signal strong {
            font-size: 0.88rem;
            margin-top: 0.15rem;
            display: block;
        }
        .sad-chip {
            display: inline-block;
            font-size: 0.68rem;
            font-weight: 700;
            padding: 0.15rem 0.4rem;
            border-radius: 6px;
        }
        .sad-chip.up { background: #ecfdf5; color: var(--sad-green-deep); }
        .sad-chip.down { background: #fffbeb; color: #b45309; }
        .sad-chip.flat { background: #f1f5f9; color: #64748b; }
        .sad-ring-wrap {
            display: grid;
            grid-template-columns: auto 1fr;
            gap: 0.75rem;
            align-items: center;
        }
        .sad-ring-svg { width: 88px; height: 88px; }
        .sad-ring-svg .track { fill: none; stroke: #e2e8f0; stroke-width: 8; }
        .sad-ring-svg .bar {
            fill: none;
            stroke: url(#sadRingGrad);
            stroke-width: 8;
            stroke-linecap: round;
            transform: rotate(-90deg);
            transform-origin: 50% 50%;
        }
        .sad-ring-svg .pct {
            font-family: 'DM Sans', sans-serif;
            font-weight: 800;
            fill: var(--sad-text);
            font-size: 18px;
        }
        .sad-ring-meta__big {
            font-size: 1.35rem;
            font-weight: 800;
            letter-spacing: -0.02em;
            line-height: 1;
        }
        .sad-ring-meta__lbl {
            font-size: 0.68rem;
            color: var(--sad-muted);
            margin-top: 0.2rem;
            font-weight: 600;
        }
        .sad-chart-box { height: 168px; position: relative; }
        .sad-chart-box--tall { height: min(420px, 55vh); }
        .sad-stage-row {
            display: grid;
            grid-template-columns: 1.4rem 1fr 2.2rem;
            gap: 0.35rem;
            align-items: center;
            font-size: 0.68rem;
            font-weight: 700;
            margin-bottom: 0.35rem;
        }
        .sad-stage-track {
            height: 6px;
            border-radius: 999px;
            background: #e2e8f0;
            overflow: hidden;
        }
        .sad-stage-fill--seed { background: #d97706; height: 100%; border-radius: 999px; }
        .sad-stage-fill--early { background: #eb8c00; height: 100%; border-radius: 999px; }
        .sad-stage-fill--growth { background: var(--sad-brand); height: 100%; border-radius: 999px; }
        .sad-biz-row {
            display: grid;
            grid-template-columns: auto 1fr auto;
            gap: 0.45rem;
            align-items: center;
            margin-bottom: 0.35rem;
            font-size: 0.72rem;
        }
        .sad-biz-row__rank {
            width: 1.35rem;
            height: 1.35rem;
            border-radius: 6px;
            background: #f1f5f9;
            font-size: 0.62rem;
            font-weight: 800;
            display: flex;
            align-items: center;
            justify-content: center;
            color: var(--sad-muted);
        }
        .sad-biz-row__track {
            height: 5px;
            border-radius: 999px;
            background: #e2e8f0;
            overflow: hidden;
        }
        .sad-biz-row__fill { height: 100%; border-radius: 999px; }
        .sad-biz-row__nums { font-weight: 700; white-space: nowrap; }
        .sad-district-cards {
            display: grid;
            grid-template-columns: repeat(auto-fill, minmax(9rem, 1fr));
            gap: 0.45rem;
            margin-bottom: 0.55rem;
        }
        .sad-district-card {
            padding: 0.5rem 0.55rem;
            border-radius: 10px;
            border: 1px solid var(--sad-border);
            background: #f8fafc;
        }
        .sad-district-card.is-top {
            border-color: #fcd34d;
            background: #fffbeb;
        }
        .sad-district-card__name {
            font-size: 0.68rem;
            font-weight: 700;
            color: var(--sad-muted);
            white-space: nowrap;
            overflow: hidden;
            text-overflow: ellipsis;
        }
        .sad-district-card__val {
            font-size: 1.1rem;
            font-weight: 800;
            margin-top: 0.15rem;
        }
        .sad-split-table {
            max-height: 14rem;
            overflow-y: auto;
            border: 1px solid var(--sad-border);
            border-radius: 10px;
        }
        .sad-split-row {
            display: flex;
            justify-content: space-between;
            padding: 0.38rem 0.55rem;
            font-size: 0.72rem;
            border-bottom: 1px solid #f1f5f9;
        }
        .sad-split-row:last-child { border-bottom: none; }
        .sad-split-row.is-zero { opacity: 0.5; }
        .sad-staff-controls {
            display: flex;
            gap: 0.4rem;
            margin-bottom: 0.5rem;
            flex-wrap: wrap;
        }
        .sad-staff-controls input,
        .sad-staff-controls select {
            flex: 1;
            min-width: 8rem;
            padding: 0.42rem 0.55rem;
            border: 1px solid var(--sad-border);
            border-radius: 8px;
            font-family: inherit;
            font-size: 0.78rem;
        }
        .sad-staff-list { max-height: min(480px, 52vh); overflow-y: auto; }
        .sad-staff-row {
            display: grid;
            grid-template-columns: 2rem 1fr auto;
            gap: 0.45rem;
            align-items: center;
            padding: 0.42rem 0.35rem;
            border-bottom: 1px solid #f1f5f9;
            font-size: 0.78rem;
        }
        .sad-staff-row:last-child { border-bottom: none; }
        .sad-staff-rank {
            font-size: 0.68rem;
            font-weight: 800;
            color: var(--sad-muted);
        }
        .sad-staff-rank.is-medal { color: var(--sad-gold); }
        .sad-staff-main { display: flex; align-items: center; gap: 0.45rem; min-width: 0; }
        .sad-staff-avatar, .sad-staff-fallback {
            width: 28px;
            height: 28px;
            border-radius: 8px;
            flex-shrink: 0;
            object-fit: cover;
        }
        .sad-staff-fallback {
            display: inline-flex;
            align-items: center;
            justify-content: center;
            background: var(--sad-brand-grad);
            color: #fff;
            font-size: 0.7rem;
            font-weight: 800;
        }
        .sad-staff-name { font-weight: 700; white-space: nowrap; overflow: hidden; text-overflow: ellipsis; }
        .sad-staff-district { font-size: 0.65rem; color: var(--sad-muted); }
        .sad-staff-val { font-weight: 800; font-size: 0.9rem; }
        .sad-savings-grid {
            display: grid;
            grid-template-columns: repeat(3, 1fr);
            gap: 0.45rem;
            margin-bottom: 0.55rem;
        }
        @media (max-width: 720px) {
            .sad-savings-grid { grid-template-columns: 1fr; }
        }
        .sad-savings-tile {
            padding: 0.55rem 0.65rem;
            border-radius: 10px;
            border: 1px solid;
        }
        .sad-savings-tile--green { border-color: #a7f3d0; background: #ecfdf5; }
        .sad-savings-tile--blue { border-color: #bae6fd; background: #f0f9ff; }
        .sad-savings-tile--violet { border-color: #f5c4a8; background: var(--sad-brand-light); }
        .sad-savings-tile__lbl {
            font-size: 0.6rem;
            font-weight: 700;
            text-transform: uppercase;
            letter-spacing: 0.05em;
        }
        .sad-savings-tile__val {
            font-size: 1.15rem;
            font-weight: 800;
            margin-top: 0.2rem;
        }
        .sad-table-wrap {
            border: 1px solid var(--sad-border);
            border-radius: 10px;
            overflow: hidden;
        }
        .sad-table {
            width: 100%;
            border-collapse: collapse;
            font-size: 0.76rem;
        }
        .sad-table th {
            text-align: left;
            padding: 0.45rem 0.55rem;
            background: #f8fafc;
            font-weight: 700;
            color: var(--sad-muted);
        }
        .sad-table th:not(:first-child),
        .sad-table td:not(:first-child) { text-align: right; }
        .sad-table td {
            padding: 0.42rem 0.55rem;
            border-top: 1px solid #f1f5f9;
        }
        .sad-dock {
            margin-top: 0.65rem;
            padding: 0.55rem 0.65rem;
            background: var(--sad-surface);
            border: 1px solid var(--sad-border);
            border-radius: var(--sad-radius);
        }
        .sad-dock__title {
            font-size: 0.68rem;
            font-weight: 800;
            text-transform: uppercase;
            letter-spacing: 0.08em;
            color: var(--sad-muted);
            margin: 0 0 0.45rem;
        }
        .sad-dock__links {
            display: flex;
            flex-wrap: wrap;
            gap: 0.35rem;
        }
        .sad-dock__link {
            display: inline-flex;
            align-items: center;
            gap: 0.35rem;
            padding: 0.38rem 0.55rem;
            border-radius: 8px;
            border: 1px solid var(--sad-border);
            background: #f8fafc;
            color: var(--sad-text);
            text-decoration: none;
            font-size: 0.72rem;
            font-weight: 600;
            transition: background 0.12s, border-color 0.12s, transform 0.12s;
        }
        .sad-dock__link:hover {
            background: var(--sad-brand-light);
            border-color: #f0b48a;
        }
        .sad-dock__link i { color: var(--sad-brand); font-size: 0.8rem; }
        .sad-spark {
            height: 28px;
            margin-top: 0.25rem;
        }
        .sad-spark svg {
            width: 100%;
            height: 100%;
            display: block;
            overflow: visible;
        }
        .sad-spark--live .sad-spark__fill {
            fill: url(#sadSparkGrad);
            opacity: 0;
            animation: sadSparkFadeIn 0.55s ease-out 0.1s 1 forwards;
        }
        .sad-spark--live .sad-spark__line {
            fill: none;
            stroke: var(--sad-brand);
            stroke-width: 1.5;
            stroke-linecap: round;
            stroke-linejoin: round;
            stroke-dasharray: 140;
            stroke-dashoffset: 140;
            animation: sadSparkDraw 0.75s ease-out 1 forwards;
        }
        .sad-spark--live .sad-spark__dot {
            fill: var(--sad-brand);
            opacity: 0;
            animation: sadSparkDotIn 0.35s ease-out 0.55s 1 forwards;
        }
        @keyframes sadSparkFadeIn {
            to { opacity: 0.4; }
        }
        @keyframes sadSparkDraw {
            to { stroke-dashoffset: 0; }
        }
        @keyframes sadSparkDotIn {
            to { opacity: 1; }
        }
        @media (prefers-reduced-motion: reduce) {
            .sad-spark--live .sad-spark__fill,
            .sad-spark--live .sad-spark__line,
            .sad-spark--live .sad-spark__dot {
                animation: none !important;
                opacity: 1;
                stroke-dashoffset: 0;
            }
            .sad-spark--live .sad-spark__fill { opacity: 0.4; }
        }
        .sad-empty {
            text-align: center;
            padding: 1.25rem;
            color: #94a3b8;
            font-size: 0.8rem;
        }
        details.sad-details summary {
            cursor: pointer;
            font-size: 0.72rem;
            font-weight: 700;
            color: var(--sad-sky);
            list-style: none;
        }
        details.sad-details summary::-webkit-details-marker { display: none; }
        .sad-align-status {
            display: flex;
            flex-wrap: wrap;
            gap: 0.35rem;
            margin-top: 0.35rem;
        }
        .sad-align-pill {
            font-size: 0.65rem;
            font-weight: 700;
            padding: 0.2rem 0.45rem;
            border-radius: 6px;
        }
        .sad-align-pill--ok { background: #ecfdf5; color: var(--sad-green-deep); }
        .sad-align-pill--bad { background: #fffbeb; color: #92400e; }
        .sad-align-gaps {
            margin-top: 0.4rem;
            padding: 0.45rem 0.55rem;
            border-radius: 8px;
            background: #f8fafc;
            border: 1px solid var(--sad-border);
            font-size: 0.68rem;
            max-height: 8rem;
            overflow-y: auto;
        }
        .sad-align-gaps li { margin: 0.2rem 0; line-height: 1.35; }
    </style>
</head>
<body class="admin-app-body admin-app-body--dashboard admin-app-body--dash-unified admin-app-body--state-premium admin-app-body--state-theme-{{ $dashboardTheme ?? 'revamp' }}">
    @include('partials.admin-topbar')
    <main class="admin-main">
        @if (session('status'))
            <div class="banner">{{ session('status') }}</div>
        @endif

        @php
            $phaseLabel = $phase3FloorDateLabel ?? '01 Apr 2026';
            $cfaTotalN = (int) ($cfaTotal ?? 0);
            $cfaTargetN = $stateCfaTarget !== null ? (int) $stateCfaTarget : null;
            $achPct = ($cfaTargetN !== null && $cfaTargetN > 0)
                ? (int) round(($cfaTotalN / $cfaTargetN) * 100)
                : null;
            $ringPct = $stateProgressPct !== null ? (int) min(100, max(0, $stateProgressPct)) : 0;
            $ringCirc = 2 * M_PI * 38;
            $ringOffset = $ringCirc * (1 - $ringPct / 100);

            $sStageTotals = ['SEED' => 0, 'EARLY' => 0, 'GROWTH' => 0];
            foreach ($stateBusinessStageMix['labels'] ?? [] as $dIdx => $dLabel) {
                $u = strtoupper(trim((string) $dLabel));
                if (isset($sStageTotals[$u])) {
                    $sStageTotals[$u] = (int) ($stateBusinessStageMix['values'][$dIdx] ?? 0);
                }
            }
            $sStageSum = array_sum($sStageTotals);
            $sStagePct = [
                'SEED' => $sStageSum > 0 ? (int) round(($sStageTotals['SEED'] / $sStageSum) * 100) : 0,
                'EARLY' => $sStageSum > 0 ? (int) round(($sStageTotals['EARLY'] / $sStageSum) * 100) : 0,
                'GROWTH' => $sStageSum > 0 ? (int) round(($sStageTotals['GROWTH'] / $sStageSum) * 100) : 0,
            ];

            $onbTarget = (int) ($stateOnboardingTarget ?? 0);
            $onbAchieved = (int) ($stateOnboardingAchieved ?? 0);
            $onbPct = $stateOnboardingProgressPct !== null ? (int) $stateOnboardingProgressPct : 0;
            $onbGap = max(0, $onbTarget - $onbAchieved);
            $onbDistrictRows = collect($stateOnboardingByDistrict ?? []);

            $insGap = $cfaTargetN !== null ? max(0, $cfaTargetN - $cfaTotalN) : 0;
            $todayDelta = (int) ($heroCfaTodayDelta ?? 0);
            $cfaTodayCount = (int) ($heroCfaToday ?? 0);
            $cfaYesterdayCount = (int) ($heroCfaYesterday ?? 0);

            $sparkVals = $heroSparkline30['values'] ?? [];
            $sparkSum = (int) array_sum($sparkVals);
            $sparkMax = ! empty($sparkVals) ? max(max($sparkVals), 1) : 1;
            $sparkW = 120;
            $sparkH = 28;
            $sparkPts = [];
            $sparkCount = count($sparkVals);
            if ($sparkCount > 1) {
                foreach ($sparkVals as $i => $v) {
                    $x = round(($i / ($sparkCount - 1)) * $sparkW, 2);
                    $y = round($sparkH - (($v / $sparkMax) * ($sparkH - 4)) - 2, 2);
                    $sparkPts[] = $x . ',' . $y;
                }
            }
            $sparkLine = implode(' ', $sparkPts);
            $sparkFill = $sparkPts ? ('0,' . $sparkH . ' ' . $sparkLine . ' ' . $sparkW . ',' . $sparkH) : '';
            $sparkDotX = null;
            $sparkDotY = null;
            if ($sparkCount > 0) {
                $lastSparkPt = $sparkPts[$sparkCount - 1];
                if (str_contains($lastSparkPt, ',')) {
                    [$sparkDotX, $sparkDotY] = array_map(static fn ($v) => (float) $v, explode(',', $lastSparkPt, 2));
                }
            }

            $districtLabels = $cfaByDistrict['labels'] ?? [];
            $districtValues = $cfaByDistrict['values'] ?? [];
            $topDistricts = collect($districtLabels)
                ->map(fn ($name, $i) => ['name' => $name, 'total' => (int) ($districtValues[$i] ?? 0)])
                ->sortByDesc('total')
                ->take(6)
                ->values();

            $bizMixTotal = (int) array_sum($businessMix['values'] ?? []);
            $bizIconMap = [
                'agri allied' => 'fa-wheat-awn',
                'food processing' => 'fa-utensils',
                'handloom & handicraft' => 'fa-shirt',
                'handloom and handicraft' => 'fa-shirt',
                'herbal and aromatic' => 'fa-leaf',
                'herbal & aromatic' => 'fa-leaf',
                'homestay' => 'fa-house-chimney',
                'others' => 'fa-shapes',
                'other' => 'fa-shapes',
                'not specified' => 'fa-circle-question',
            ];
            $bizIconFor = function (string $label) use ($bizIconMap): string {
                $key = strtolower(trim($label));
                return $bizIconMap[$key] ?? 'fa-briefcase';
            };

            $savingsTotalTillDate = (float) ($estimatedSavings['total_till_date'] ?? 0);
            $savingsTotalThisFy = (float) ($estimatedSavings['total_this_fy'] ?? 0);
            $topSavingsServices = $estimatedSavings['top_services'] ?? [];
            $staffCfaRows = $staffCfaByStaff ?? [];
            $staffDistrictOptions = collect($staffCfaRows)->pluck('district')->filter()->unique()->sort()->values()->all();

            $fyLabel = $activeFy?->name ?? ($activeFy?->code ?? 'Phase 3');
            $plan = $districtPlanAlignment ?? [];
            $planPct = $plan['pct'] ?? null;
            $planCfa = $plan['cfa'] ?? [];
            $planSvc = $plan['services'] ?? [];
            $planMisaligned = $plan['misaligned'] ?? [];
        @endphp

        <header class="sad-unified-strip" aria-label="Dashboard context">
            <div class="sad-unified-strip__left">
                <h1 class="sad-unified-strip__title">Welcome, {{ auth()->user()->name }}</h1>
            </div>
            <div class="sad-unified-strip__meta">
                <div class="sad-ground-ticker" id="sadGroundTicker" aria-live="polite" aria-atomic="true">
                    <i class="fa-solid fa-bullhorn sad-ground-ticker__icon" aria-hidden="true"></i>
                    <span class="sad-ground-ticker__text" id="sadGroundTickerText"></span>
                </div>
            </div>
        </header>

        <script>
        (function () {
            var messages = @json($groundActivityTicker ?? []);
            var el = document.getElementById('sadGroundTickerText');
            if (!el || !messages.length) return;

            for (var i = messages.length - 1; i > 0; i--) {
                var j = Math.floor(Math.random() * (i + 1));
                var tmp = messages[i];
                messages[i] = messages[j];
                messages[j] = tmp;
            }

            var idx = 0;
            function showNext() {
                el.classList.add('is-fading');
                setTimeout(function () {
                    el.textContent = messages[idx];
                    el.classList.remove('is-fading');
                    idx = (idx + 1) % messages.length;
                }, 320);
            }

            el.textContent = messages[0];
            idx = 1;
            setInterval(showNext, 7000);
        })();
        </script>

        <div class="sad">
            <div class="sad-stat-chips" role="group" aria-label="Key performance indicators">
                <div class="sad-stat-chip sad-stat-chip--cfa" title="Phase 3 CFA submissions — {{ number_format((int) ($cfaLast30 ?? 0)) }} in last 30 days">
                    <span class="sad-stat-chip__ico" aria-hidden="true"><i class="fa-solid fa-file-circle-plus"></i></span>
                    <span class="sad-stat-chip__label">CFA total</span>
                    <span class="sad-stat-chip__val">{{ number_format($cfaTotalN) }}</span>
                </div>
                <div class="sad-stat-chip sad-stat-chip--target" title="@if ($cfaTargetN !== null){{ number_format($cfaTotalN) }} / {{ number_format($cfaTargetN) }} toward state target@else No state CFA target set @endif">
                    <span class="sad-stat-chip__ico" aria-hidden="true"><i class="fa-solid fa-bullseye"></i></span>
                    <span class="sad-stat-chip__label">Target</span>
                    <span class="sad-stat-chip__val">{{ $ringPct }}%</span>
                </div>
                <div class="sad-stat-chip sad-stat-chip--today @if ($todayDelta > 0) is-up @elseif ($todayDelta < 0) is-down @endif" title="@if ($todayDelta > 0)+{{ number_format($todayDelta) }} vs yesterday ({{ number_format($cfaYesterdayCount) }})@elseif ($todayDelta < 0){{ number_format(abs($todayDelta)) }} fewer vs yesterday ({{ number_format($cfaYesterdayCount) }})@else Same as yesterday ({{ number_format($cfaYesterdayCount) }})@endif">
                    <span class="sad-stat-chip__ico" aria-hidden="true"><i class="fa-solid fa-sun"></i></span>
                    <span class="sad-stat-chip__label">CFA today</span>
                    <span class="sad-stat-chip__val">{{ number_format($cfaTodayCount) }}</span>
                </div>
                <div class="sad-stat-chip sad-stat-chip--onboard" title="@if ($onbTarget > 0){{ number_format($onbAchieved) }} / {{ number_format($onbTarget) }} locked hub members@else Locked hub members (Phase 3)@endif">
                    <span class="sad-stat-chip__ico" aria-hidden="true"><i class="fa-solid fa-user-check"></i></span>
                    <span class="sad-stat-chip__label">Onboarding</span>
                    <span class="sad-stat-chip__val">@if ($onbTarget > 0){{ $onbPct }}%@else{{ number_format($onbAchieved) }}@endif</span>
                </div>
                <div class="sad-stat-chip sad-stat-chip--services" title="Approved services till date — {{ number_format((int) ($servicesDeliveredThisFy ?? 0)) }} this FY">
                    <span class="sad-stat-chip__ico" aria-hidden="true"><i class="fa-solid fa-circle-check"></i></span>
                    <span class="sad-stat-chip__label">Services</span>
                    <span class="sad-stat-chip__val">{{ number_format((int) ($servicesDeliveredTillDate ?? 0)) }}</span>
                </div>
                <div class="sad-stat-chip sad-stat-chip--districts" title="{{ number_format((int) ($insights['geo']['districts'] ?? 0)) }} of {{ number_format($districtsCount) }} districts with CFA">
                    <span class="sad-stat-chip__ico" aria-hidden="true"><i class="fa-solid fa-map"></i></span>
                    <span class="sad-stat-chip__label">Districts</span>
                    <span class="sad-stat-chip__val">{{ number_format((int) ($insights['geo']['districts'] ?? 0)) }}/{{ number_format($districtsCount) }}</span>
                </div>
                <div class="sad-stat-chip sad-stat-chip--blocks" title="{{ number_format((int) ($insights['geo']['blocks'] ?? 0)) }} unique blocks with CFA in scope">
                    <span class="sad-stat-chip__ico" aria-hidden="true"><i class="fa-solid fa-map-pin"></i></span>
                    <span class="sad-stat-chip__label">Blocks</span>
                    <span class="sad-stat-chip__val">{{ number_format((int) ($insights['geo']['blocks'] ?? 0)) }}</span>
                </div>
                <div class="sad-stat-chip sad-stat-chip--savings" title="Estimated savings from approved services this FY">
                    <span class="sad-stat-chip__ico" aria-hidden="true"><i class="fa-solid fa-piggy-bank"></i></span>
                    <span class="sad-stat-chip__label">Savings FY</span>
                    <span class="sad-stat-chip__val">Rs {{ number_format($savingsTotalThisFy, 0) }}</span>
                </div>
            </div>

            <div class="sad-alerts" role="status">
                @if (($todayZeroDistricts ?? 0) > 0)
                    <span class="sad-alert sad-alert--warn">
                        <i class="fa-solid fa-triangle-exclamation" aria-hidden="true"></i>
                        {{ number_format((int) $todayZeroDistricts) }} district(s) with zero CFA today
                    </span>
                @endif
                @if ($todayTopDistrict)
                    <span class="sad-alert sad-alert--ok">
                        <i class="fa-solid fa-trophy" aria-hidden="true"></i>
                        Top today: {{ $todayTopDistrict['name'] }} ({{ number_format((int) $todayTopDistrict['count']) }})
                    </span>
                @endif
                @if (($cfaWoWDeltaPct ?? 0) !== 0)
                    <span class="sad-alert sad-alert--info">
                        <i class="fa-solid fa-chart-line" aria-hidden="true"></i>
                        7-day CFA {{ ($cfaWoWDeltaPct ?? 0) > 0 ? 'up' : 'down' }} {{ abs((int) ($cfaWoWDeltaPct ?? 0)) }}% vs prior week
                    </span>
                @endif
                @if ($planPct !== null && ! ($plan['all_aligned'] ?? false))
                    <span class="sad-alert sad-alert--warn">
                        <i class="fa-solid fa-scale-balanced" aria-hidden="true"></i>
                        CFA + services: {{ (int) $planPct }}% deliverables aligned ({{ (int) ($plan['aligned_count'] ?? 0) }}/{{ (int) ($plan['tracked_count'] ?? 0) }})
                    </span>
                @endif
            </div>

            <nav class="sad-nav" aria-label="Dashboard sections">
                <button type="button" class="sad-nav__btn is-active" data-sad-tab="overview">
                    <i class="fa-solid fa-gauge-high" aria-hidden="true"></i> Overview
                </button>
                <button type="button" class="sad-nav__btn" data-sad-tab="insights">
                    <i class="fa-solid fa-chart-pie" aria-hidden="true"></i> Insights
                </button>
                <button type="button" class="sad-nav__btn" data-sad-tab="districts">
                    <i class="fa-solid fa-map" aria-hidden="true"></i> Districts
                </button>
                <button type="button" class="sad-nav__btn" data-sad-tab="team">
                    <i class="fa-solid fa-users-gear" aria-hidden="true"></i> Team performance
                </button>
                <button type="button" class="sad-nav__btn" data-sad-tab="impact">
                    <i class="fa-solid fa-seedling" aria-hidden="true"></i> Impact &amp; savings
                </button>
            </nav>

            {{-- OVERVIEW --}}
            <section class="sad-panel is-active" data-sad-panel="overview">
                <div class="sad-grid sad-grid--2">
                    <div class="sad-card">
                        <div class="sad-card__head">
                            <h2 class="sad-card__title"><i class="fa-solid fa-lightbulb" aria-hidden="true"></i> Program intelligence</h2>
                            @if ($achPct !== null)
                                <span class="sad-card__tag">{{ $achPct }}% achieved</span>
                            @endif
                        </div>
                        @if ($cfaTargetN !== null && $cfaTargetN > 0)
                            <div class="sad-progress-block">
                                <div class="sad-progress-top">
                                    <span>CFA vs state target</span>
                                    <strong>{{ number_format($cfaTotalN) }} / {{ number_format($cfaTargetN) }}</strong>
                                </div>
                                <div class="sad-progress-track">
                                    <div class="sad-progress-fill" style="width: {{ min(100, max(0, $achPct ?? 0)) }}%;"></div>
                                </div>
                                <p class="sad-progress-foot">Gap to target: <strong>{{ number_format($insGap) }}</strong> applications.</p>
                            </div>
                            @if ($planPct !== null)
                                <div class="sad-progress-block">
                                    <div class="sad-progress-top">
                                        <span>District plan alignment (CFA + services)</span>
                                        <strong>{{ (int) $planPct }}%</strong>
                                    </div>
                                    <div class="sad-progress-track">
                                        <div class="sad-progress-fill sad-progress-fill--sky" style="width: {{ min(100, (int) $planPct) }}%;"></div>
                                    </div>
                                    <p class="sad-progress-foot">
                                        <strong>{{ (int) ($plan['aligned_count'] ?? 0) }} of {{ (int) ($plan['tracked_count'] ?? 0) }}</strong> deliverables with state target have matching district totals.
                                        Combined district {{ number_format((int) ($plan['district_total'] ?? 0)) }} vs state {{ number_format((int) ($plan['state_total'] ?? 0)) }}.
                                        @if ($activeFy)
                                            <a href="{{ route('admin.targets.district', ['fiscal_year_id' => $activeFy->id]) }}">District targets</a>
                                        @endif
                                    </p>
                                    <div class="sad-align-status">
                                        @if ($planCfa['tracked'] ?? false)
                                            <span class="sad-align-pill {{ ($planCfa['aligned'] ?? false) ? 'sad-align-pill--ok' : 'sad-align-pill--bad' }}">
                                                CFA {{ ($planCfa['aligned'] ?? false) ? 'aligned' : 'mismatch' }}
                                                ({{ number_format((int) ($planCfa['district'] ?? 0)) }}/{{ number_format((int) ($planCfa['state'] ?? 0)) }})
                                            </span>
                                        @else
                                            <span class="sad-align-pill sad-align-pill--bad">CFA target not set</span>
                                        @endif
                                        @if (($planSvc['tracked_count'] ?? 0) > 0)
                                            <span class="sad-align-pill {{ ($planSvc['all_aligned'] ?? false) ? 'sad-align-pill--ok' : 'sad-align-pill--bad' }}">
                                                Services {{ (int) ($planSvc['aligned_count'] ?? 0) }}/{{ (int) ($planSvc['tracked_count'] ?? 0) }} aligned
                                                ({{ number_format((int) ($planSvc['district'] ?? 0)) }}/{{ number_format((int) ($planSvc['state'] ?? 0)) }})
                                            </span>
                                        @else
                                            <span class="sad-align-pill sad-align-pill--bad">No service targets set</span>
                                        @endif
                                    </div>
                                    @if (count($planMisaligned) > 0)
                                        <details class="sad-details" style="margin-top:0.35rem;">
                                            <summary>{{ count($planMisaligned) }} deliverable(s) need district fix</summary>
                                            <ul class="sad-align-gaps">
                                                @foreach ($planMisaligned as $gap)
                                                    <li>
                                                        <strong>{{ $gap['name'] }}</strong>
                                                        ({{ $gap['kind'] === 'cfa' ? 'CFA' : 'Service' }}):
                                                        district {{ number_format((int) $gap['district']) }}
                                                        vs state {{ number_format((int) $gap['state']) }}
                                                        — gap {{ number_format((int) $gap['gap']) }}
                                                    </li>
                                                @endforeach
                                            </ul>
                                        </details>
                                    @elseif ($plan['all_aligned'] ?? false)
                                        <p class="sad-progress-foot" style="color:#047857;margin-top:0.35rem;">
                                            <i class="fa-solid fa-circle-check" aria-hidden="true"></i> All CFA and service district targets match state plan.
                                        </p>
                                    @endif
                                </div>
                            @elseif ($cfaTargetN !== null && $cfaTargetN > 0)
                                <p class="sad-progress-foot">Set service state targets in <a href="{{ route('admin.targets.state') }}">State targets</a>, then split by district for full alignment view.</p>
                            @endif
                        @else
                            <p class="sad-progress-foot">Configure CFA state target in <a href="{{ route('admin.targets.state') }}">State targets</a> to unlock progress tracking.</p>
                        @endif

                        @if ($onbTarget > 0)
                            <div class="sad-progress-block">
                                <div class="sad-progress-top">
                                    <span>Onboarding (locked batches)</span>
                                    <strong>{{ number_format($onbAchieved) }} / {{ number_format($onbTarget) }}</strong>
                                </div>
                                <div class="sad-progress-track">
                                    <div class="sad-progress-fill sad-progress-fill--sky" style="width: {{ min(100, $onbPct) }}%;"></div>
                                </div>
                                <p class="sad-progress-foot">Remaining gap: <strong>{{ number_format($onbGap) }}</strong>.</p>
                            </div>
                        @endif

                        <div class="sad-signals">
                            <div class="sad-signal">
                                <span>Last 7 days CFA</span>
                                <strong>{{ number_format((int) ($cfaLast7 ?? 0)) }}</strong>
                            </div>
                            <div class="sad-signal">
                                <span>Week-over-week</span>
                                <span class="sad-chip {{ ($cfaWoWDeltaPct ?? 0) > 0 ? 'up' : (($cfaWoWDeltaPct ?? 0) < 0 ? 'down' : 'flat') }}">
                                    {{ ($cfaWoWDeltaPct ?? 0) > 0 ? '+' : '' }}{{ (int) ($cfaWoWDeltaPct ?? 0) }}%
                                </span>
                            </div>
                            <div class="sad-signal">
                                <span>This month</span>
                                <strong>{{ number_format((int) ($cfaThisMonth ?? 0)) }}</strong>
                            </div>
                            <div class="sad-signal">
                                <span>Hubs / deliverables</span>
                                <strong>{{ number_format($hubsCount) }} / {{ number_format($deliverablesCount) }}</strong>
                            </div>
                        </div>
                    </div>

                    <div class="sad-card">
                        <div class="sad-card__head">
                            <h2 class="sad-card__title"><i class="fa-solid fa-wave-square" aria-hidden="true"></i> State pulse</h2>
                            <span class="sad-card__tag">14-day intake</span>
                        </div>
                        <div class="sad-ring-wrap">
                            <svg class="sad-ring-svg" viewBox="0 0 100 100" aria-hidden="true">
                                <defs>
                                    <linearGradient id="sadRingGrad" x1="0%" y1="0%" x2="100%" y2="100%">
                                        <stop offset="0%" stop-color="var(--sad-brand-deep)"/>
                                        <stop offset="100%" stop-color="var(--sad-brand)"/>
                                    </linearGradient>
                                </defs>
                                <circle class="track" cx="50" cy="50" r="38"/>
                                <circle class="bar" cx="50" cy="50" r="38"
                                    stroke-dasharray="{{ round($ringCirc, 3) }}"
                                    stroke-dashoffset="{{ round($ringOffset, 3) }}"/>
                                <text class="pct" x="50" y="52" text-anchor="middle" dominant-baseline="middle">{{ $ringPct }}%</text>
                            </svg>
                            <div>
                                <div class="sad-ring-meta__big">{{ number_format($cfaTotalN) }}</div>
                                <div class="sad-ring-meta__lbl">Phase 3 CFA · {{ $phaseLabel }} onward</div>
                                @if ($sparkLine)
                                    <div class="sad-spark sad-spark--live" title="30-day CFA volume: {{ number_format($sparkSum) }} total" aria-hidden="true">
                                        <svg viewBox="0 0 {{ $sparkW }} {{ $sparkH }}" preserveAspectRatio="none">
                                            <defs>
                                                <linearGradient id="sadSparkGrad" x1="0" y1="0" x2="0" y2="1">
                                                    <stop offset="0%" stop-color="var(--sad-brand)" stop-opacity="0.45"/>
                                                    <stop offset="100%" stop-color="var(--sad-brand)" stop-opacity="0"/>
                                                </linearGradient>
                                            </defs>
                                            <polygon class="sad-spark__fill" points="{{ $sparkFill }}"/>
                                            <polyline class="sad-spark__line" points="{{ $sparkLine }}"/>
                                            @if ($sparkDotX !== null && $sparkDotY !== null)
                                                <circle class="sad-spark__dot" cx="{{ $sparkDotX }}" cy="{{ $sparkDotY }}" r="2"/>
                                            @endif
                                        </svg>
                                    </div>
                                @endif
                            </div>
                        </div>
                        <div class="sad-chart-box" style="margin-top:0.5rem;">
                            <canvas id="stateTrendCurveChart" aria-label="CFA per day, all districts"></canvas>
                        </div>
                        <p class="sad-card__hint" style="margin-top:0.45rem;margin-bottom:0.35rem;">Stage mix (saved forms)</p>
                        <div class="sad-stage-row">
                            <span>Seed</span>
                            <div class="sad-stage-track"><div class="sad-stage-fill--seed" style="width: {{ $sStagePct['SEED'] }}%;"></div></div>
                            <span>{{ $sStagePct['SEED'] }}%</span>
                        </div>
                        <div class="sad-stage-row">
                            <span>Early</span>
                            <div class="sad-stage-track"><div class="sad-stage-fill--early" style="width: {{ $sStagePct['EARLY'] }}%;"></div></div>
                            <span>{{ $sStagePct['EARLY'] }}%</span>
                        </div>
                        <div class="sad-stage-row">
                            <span>Growth</span>
                            <div class="sad-stage-track"><div class="sad-stage-fill--growth" style="width: {{ $sStagePct['GROWTH'] }}%;"></div></div>
                            <span>{{ $sStagePct['GROWTH'] }}%</span>
                        </div>
                    </div>
                </div>

                <div class="sad-card" style="margin-top:0.55rem;">
                    <div class="sad-card__head">
                        <h2 class="sad-card__title"><i class="fa-solid fa-chart-pie" aria-hidden="true"></i> Business category mix</h2>
                        <span class="sad-card__tag">{{ number_format($bizMixTotal) }} apps</span>
                    </div>
                    @if (count($businessMix['labels'] ?? []) === 0)
                        <div class="sad-empty">No category data yet</div>
                    @else
                        @foreach ($businessMix['labels'] as $idx => $label)
                            @php
                                $bizV = (int) ($businessMix['values'][$idx] ?? 0);
                                $bizPct = $bizMixTotal > 0 ? (int) round(100 * $bizV / $bizMixTotal) : 0;
                                $bizCol = $businessMix['colors'][$idx] ?? '#d04a02';
                            @endphp
                            <div class="sad-biz-row">
                                <span class="sad-biz-row__rank">#{{ $idx + 1 }}</span>
                                <div>
                                    <div style="font-weight:700;font-size:0.72rem;margin-bottom:0.2rem;">
                                        <i class="fa-solid {{ $bizIconFor((string) $label) }}" aria-hidden="true" style="color:{{ $bizCol }};margin-right:0.25rem;"></i>
                                        {{ $label }}
                                    </div>
                                    <div class="sad-biz-row__track">
                                        <div class="sad-biz-row__fill" style="width:{{ $bizPct }}%;background:{{ $bizCol }};"></div>
                                    </div>
                                </div>
                                <span class="sad-biz-row__nums">{{ $bizPct }}% · {{ number_format($bizV) }}</span>
                            </div>
                        @endforeach
                    @endif
                </div>
            </section>

            @include('dashboards.state-admin._insights-panel')

            {{-- DISTRICTS --}}
            <section class="sad-panel" data-sad-panel="districts">
                <div class="sad-district-cards">
                    @foreach ($topDistricts as $i => $d)
                        <div class="sad-district-card @if ($i === 0) is-top @endif">
                            <div class="sad-district-card__name">
                                @if ($i === 0)<i class="fa-solid fa-crown" style="color:var(--sad-gold);margin-right:0.2rem;" aria-hidden="true"></i>@endif
                                {{ $d['name'] }}
                            </div>
                            <div class="sad-district-card__val">{{ number_format($d['total']) }}</div>
                        </div>
                    @endforeach
                </div>
                <div class="sad-grid sad-grid--2" style="margin-bottom:0.55rem;">
                    @include('dashboards.state-admin._district-target-chart', ['insights' => $insights ?? []])
                    <div class="sad-card">
                        <h2 class="sad-card__title"><i class="fa-solid fa-layer-group" aria-hidden="true"></i> Top blocks by CFA</h2>
                        <p class="sad-card__hint">Top 12 blocks in Phase 3 scope</p>
                        <div class="sad-chart-box sad-chart-box--tall">
                            <canvas id="chartTopBlocks"></canvas>
                        </div>
                    </div>
                </div>
                <div class="sad-grid sad-grid--2">
                    <div class="sad-card">
                        <h2 class="sad-card__title"><i class="fa-solid fa-chart-bar" aria-hidden="true"></i> Applications by district</h2>
                        <p class="sad-card__hint">Phase 3 CFA from {{ $phaseLabel }}</p>
                        <div class="sad-chart-box sad-chart-box--tall">
                            <canvas id="chartDistrictCfa"></canvas>
                        </div>
                    </div>
                    <div class="sad-card">
                        <h2 class="sad-card__title"><i class="fa-solid fa-map-pin" aria-hidden="true"></i> District signals</h2>
                        <div class="sad-signals" style="margin-bottom:0.55rem;">
                            <div class="sad-signal">
                                <span>Top district today</span>
                                <strong>{{ $todayTopDistrict['name'] ?? '—' }} @if(isset($todayTopDistrict['count'])) ({{ number_format((int) $todayTopDistrict['count']) }}) @endif</strong>
                            </div>
                            <div class="sad-signal">
                                <span>Lowest active today</span>
                                <strong>
                                    @if ($todayLowestActiveDistrict)
                                        {{ $todayLowestActiveDistrict['name'] }} ({{ number_format((int) $todayLowestActiveDistrict['count']) }})
                                    @else
                                        —
                                    @endif
                                </strong>
                            </div>
                        </div>
                        <h3 class="sad-card__title" style="font-size:0.78rem;margin-bottom:0.35rem;">
                            <i class="fa-solid fa-user-group" aria-hidden="true"></i> Onboarding by district
                        </h3>
                        <div class="sad-split-table">
                            @forelse ($onbDistrictRows as $row)
                                @php $rowCount = (int) ($row['count'] ?? 0); @endphp
                                <div class="sad-split-row @if ($rowCount === 0) is-zero @endif">
                                    <span>{{ $row['district'] }}</span>
                                    <strong>{{ number_format($rowCount) }}</strong>
                                </div>
                            @empty
                                <div class="sad-empty">No district onboarding data</div>
                            @endforelse
                        </div>
                    </div>
                </div>
            </section>

            {{-- TEAM --}}
            <section class="sad-panel" data-sad-panel="team">
                <div class="sad-card" style="margin-bottom:0.55rem;">
                    <h2 class="sad-card__title"><i class="fa-solid fa-chart-bar" aria-hidden="true"></i> Top staff by CFA</h2>
                    <p class="sad-card__hint">Referral-linked CFA · top 10</p>
                    <div class="sad-chart-box sad-chart-box--tall">
                        <canvas id="chartStaffTop"></canvas>
                    </div>
                </div>
                <div class="sad-card">
                    <div class="sad-card__head">
                        <h2 class="sad-card__title"><i class="fa-solid fa-ranking-star" aria-hidden="true"></i> CFA by district staff</h2>
                        <span class="sad-card__tag">{{ count($staffCfaRows) }} rows</span>
                    </div>
                    <p class="sad-card__hint">Referral-linked CFA aligned to staff district · Phase 3 from {{ $phaseLabel }}</p>
                    <div class="sad-staff-controls">
                        <input type="text" id="stateStaffCfaSearch" placeholder="Search staff name…" autocomplete="off">
                        <select id="stateStaffCfaDistrictFilter">
                            <option value="">All districts</option>
                            @foreach ($staffDistrictOptions as $districtName)
                                <option value="{{ strtolower($districtName) }}">{{ $districtName }}</option>
                            @endforeach
                        </select>
                    </div>
                    <div class="sad-staff-list" id="stateStaffCfaList">
                        @forelse ($staffCfaRows as $index => $row)
                            <div class="sad-staff-row"
                                data-name="{{ strtolower($row['name']) }}"
                                data-district="{{ strtolower($row['district']) }}">
                                <span class="sad-staff-rank @if ($index < 3) is-medal @endif">#{{ $index + 1 }}</span>
                                <div class="sad-staff-main">
                                    @if (!empty($row['avatar_url']))
                                        <img src="{{ $row['avatar_url'] }}" alt="" class="sad-staff-avatar">
                                    @else
                                        <span class="sad-staff-fallback">{{ strtoupper(substr(trim((string) $row['name']), 0, 1)) ?: '?' }}</span>
                                    @endif
                                    <div style="min-width:0;">
                                        <div class="sad-staff-name">{{ $row['name'] }}</div>
                                        <div class="sad-staff-district">{{ $row['district'] }}</div>
                                    </div>
                                </div>
                                <span class="sad-staff-val">{{ number_format((int) $row['cfa_total']) }}</span>
                            </div>
                        @empty
                            <div class="sad-empty">No staff data yet</div>
                        @endforelse
                        <div class="sad-empty" id="stateStaffCfaNoResults" style="display:none;">No matches for this filter</div>
                    </div>
                </div>
            </section>

            {{-- IMPACT --}}
            <section class="sad-panel" data-sad-panel="impact">
                <div class="sad-savings-grid">
                    <div class="sad-savings-tile sad-savings-tile--green">
                        <div class="sad-savings-tile__lbl" style="color:#166534;">Total till date</div>
                        <div class="sad-savings-tile__val" style="color:#14532d;">Rs {{ number_format($savingsTotalTillDate, 2) }}</div>
                    </div>
                    <div class="sad-savings-tile sad-savings-tile--blue">
                        <div class="sad-savings-tile__lbl" style="color:#1d4ed8;">Estimated this FY</div>
                        <div class="sad-savings-tile__val" style="color:#1e3a8a;">Rs {{ number_format($savingsTotalThisFy, 2) }}</div>
                    </div>
                    <div class="sad-savings-tile sad-savings-tile--violet">
                        <div class="sad-savings-tile__lbl" style="color:var(--sad-green);">Active deliverables</div>
                        <div class="sad-savings-tile__val" style="color:var(--sad-green-deep);">{{ number_format($deliverablesCount) }}</div>
                    </div>
                </div>
                <div class="sad-card">
                    <h2 class="sad-card__title"><i class="fa-solid fa-hand-holding-heart" aria-hidden="true"></i> Top services by estimated savings</h2>
                    <p class="sad-card__hint">Approved service cases × configured average market price</p>
                    <div class="sad-table-wrap">
                        <table class="sad-table">
                            <thead>
                                <tr>
                                    <th>Service</th>
                                    <th>Approved</th>
                                    <th>Avg price</th>
                                    <th>Savings</th>
                                </tr>
                            </thead>
                            <tbody>
                                @forelse ($topSavingsServices as $svc)
                                    <tr>
                                        <td>{{ $svc['name'] }}</td>
                                        <td>{{ number_format((int) $svc['approved_count']) }}</td>
                                        <td>Rs {{ number_format((float) $svc['avg_price'], 2) }}</td>
                                        <td><strong>Rs {{ number_format((float) $svc['savings'], 2) }}</strong></td>
                                    </tr>
                                @empty
                                    <tr>
                                        <td colspan="4" class="sad-empty">No savings data yet — configure service market prices.</td>
                                    </tr>
                                @endforelse
                            </tbody>
                        </table>
                    </div>
                    <p class="sad-card__hint" style="margin-top:0.5rem;">
                        <a href="{{ route('admin.phase3-services.index') }}">View Phase 3 service cases</a>
                        · <a href="{{ route('admin.deliverables.index') }}">Deliverables report</a>
                    </p>
                </div>
            </section>

            <div class="sad-dock">
                <p class="sad-dock__title">Quick command links</p>
                <div class="sad-dock__links">
                    <a class="sad-dock__link" href="{{ route('admin.cfa.index') }}"><i class="fa-solid fa-clipboard-list"></i> CFA</a>
                    <a class="sad-dock__link" href="{{ route('admin.targets.state') }}"><i class="fa-solid fa-bullseye"></i> State targets</a>
                    <a class="sad-dock__link" href="{{ route('admin.targets.district') }}"><i class="fa-solid fa-map-location-dot"></i> District targets</a>
                    <a class="sad-dock__link" href="{{ route('admin.targets.allocate-by-service') }}"><i class="fa-solid fa-sliders"></i> Allocate by service</a>
                    <a class="sad-dock__link" href="{{ route('admin.state-tasks.index') }}"><i class="fa-solid fa-list-check"></i> State tasks</a>
                    <a class="sad-dock__link" href="{{ route('admin.staff.index') }}"><i class="fa-solid fa-user-tie"></i> Staff</a>
                    <a class="sad-dock__link" href="{{ route('admin.attendance.index') }}"><i class="fa-solid fa-calendar-check"></i> Field attendance</a>
                    <a class="sad-dock__link" href="{{ route('admin.staff-check-ins.index') }}"><i class="fa-solid fa-location-dot"></i> Staff check-ins</a>
                    <a class="sad-dock__link" href="{{ route('admin.live-map.index') }}"><i class="fa-solid fa-map-location-dot"></i> Live map</a>
                    <a class="sad-dock__link" href="{{ route('admin.data-centre.index') }}"><i class="fa-solid fa-database"></i> Data centre</a>
                    <a class="sad-dock__link" href="{{ route('admin.deliverables.index') }}"><i class="fa-solid fa-chart-column"></i> Deliverables</a>
                    <a class="sad-dock__link" href="{{ route('admin.onboarded.index') }}"><i class="fa-solid fa-user-check"></i> Onboarded</a>
                    <a class="sad-dock__link" href="{{ route('team.index') }}"><i class="fa-solid fa-people-group"></i> Team</a>
                    <a class="sad-dock__link" href="{{ route('library.documents.index') }}"><i class="fa-solid fa-folder-open"></i> Documents</a>
                    <a class="sad-dock__link" href="{{ route('admin.audit.index') }}"><i class="fa-solid fa-scroll"></i> Audit</a>
                </div>
            </div>
        </div>
    </main>

@include('dashboards.state-admin._chart-scripts')

@include('partials.app-footer')
</body>
</html>
