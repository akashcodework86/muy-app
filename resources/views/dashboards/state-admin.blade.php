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
        :root {
            --sad-text: #0f172a;
            --sad-muted: #64748b;
            --sad-border: #e2e8f0;
            --sad-surface: #ffffff;
            --sad-brand: #745af2;
            --sad-brand-deep: #5e48d9;
            --sad-brand-light: #ede9fe;
            --sad-accent: #eab308;
            --sad-accent-soft: #fef9c3;
            --sad-green: #22c55e;
            --sad-green-deep: #16a34a;
            --sad-teal: #745af2;
            --sad-sky: #3b82f6;
            --sad-navy: #1e3a5f;
            --sad-coral: #ef5350;
            --sad-saffron: #eab308;
            --sad-gold: #ca8a04;
            --sad-radius: 12px;
            --sad-shadow: 0 1px 2px rgba(15, 23, 42, 0.05), 0 8px 24px rgba(116, 90, 242, 0.08);
            --sad-brand-grad: linear-gradient(135deg, #5e48d9 0%, #745af2 55%, #8b72f4 100%);
        }
        .admin-app-body--state-premium {
            background: #f4f7fa !important;
        }
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
        .sad-kpi-strip {
            display: grid;
            grid-template-columns: repeat(6, minmax(0, 1fr));
            gap: 0.5rem;
            margin-bottom: 0.6rem;
        }
        @media (max-width: 1200px) {
            .sad-kpi-strip { grid-template-columns: repeat(3, minmax(0, 1fr)); }
        }
        @media (max-width: 640px) {
            .sad-kpi-strip { grid-template-columns: repeat(2, minmax(0, 1fr)); }
        }
        .sad-kpi {
            background: var(--sad-surface);
            border: 1px solid var(--sad-border);
            border-radius: var(--sad-radius);
            padding: 0.55rem 0.65rem;
            box-shadow: 0 1px 2px rgba(15, 23, 42, 0.04);
            min-width: 0;
            transition: border-color 0.15s, box-shadow 0.15s;
        }
        .sad-kpi:hover {
            border-color: #c4b5fd;
            box-shadow: 0 4px 16px rgba(116, 90, 242, 0.12);
        }
        .sad-kpi__icon {
            width: 1.65rem;
            height: 1.65rem;
            border-radius: 8px;
            display: inline-flex;
            align-items: center;
            justify-content: center;
            font-size: 0.75rem;
            margin-bottom: 0.35rem;
        }
        .sad-kpi__icon--green { background: #ecfdf5; color: var(--sad-green-deep); }
        .sad-kpi__icon--sky { background: #eff6ff; color: var(--sad-sky); }
        .sad-kpi__icon--teal { background: var(--sad-brand-light); color: var(--sad-brand-deep); }
        .sad-kpi__icon--amber { background: var(--sad-accent-soft); color: var(--sad-accent); }
        .sad-kpi__icon--rose { background: #f1f5f9; color: #64748b; }
        .sad-kpi__label {
            font-size: 0.58rem;
            font-weight: 700;
            text-transform: uppercase;
            letter-spacing: 0.06em;
            color: var(--sad-muted);
            line-height: 1.2;
        }
        .sad-kpi__value {
            font-size: 1.15rem;
            font-weight: 800;
            letter-spacing: -0.02em;
            margin-top: 0.12rem;
            line-height: 1.1;
        }
        .sad-kpi__foot {
            font-size: 0.62rem;
            color: var(--sad-muted);
            margin-top: 0.2rem;
            font-weight: 600;
        }
        .sad-kpi__foot.is-up { color: var(--sad-green-deep); }
        .sad-kpi__foot.is-down { color: #b45309; }
        .sad-kpi__foot.is-warn { color: #b45309; }
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
            background: var(--sad-brand-grad);
            color: #fff;
            box-shadow: 0 2px 8px rgba(116, 90, 242, 0.28);
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
            background: linear-gradient(90deg, #0284c7, #0ea5e9);
        }
        .sad-progress-foot {
            font-size: 0.68rem;
            color: var(--sad-muted);
            margin-top: 0.28rem;
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
            margin-bottom: 0.28rem;
        }
        .sad-stage-track {
            height: 6px;
            border-radius: 999px;
            background: #e2e8f0;
            overflow: hidden;
        }
        .sad-stage-fill--seed { background: #d97706; height: 100%; border-radius: 999px; }
        .sad-stage-fill--early { background: #0284c7; height: 100%; border-radius: 999px; }
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
        .sad-savings-tile--violet { border-color: #c4b5fd; background: var(--sad-brand-light); }
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
            border-color: #a78bfa;
        }
        .sad-dock__link i { color: var(--sad-brand); font-size: 0.8rem; }
        .sad-spark {
            height: 28px;
            margin-top: 0.25rem;
        }
        .sad-spark svg { width: 100%; height: 100%; }
        .sad-spark .line { fill: none; stroke: var(--sad-brand); stroke-width: 1.5; }
        .sad-spark .fill { fill: url(#sadSparkGrad); opacity: 0.4; }
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
<body class="admin-app-body admin-app-body--dashboard admin-app-body--state-premium">
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

        <div class="sad">
            <header class="sad-masthead">
                <div>
                    <div class="sad-masthead__eyebrow"><i class="fa-solid fa-mountain-sun" aria-hidden="true"></i> MUY State Command</div>
                    <h1>Welcome, {{ auth()->user()->name }}</h1>
                    <p class="sad-masthead__sub">
                        Unified Phase 3 intelligence across {{ number_format($districtsCount) }} districts — CFA from {{ $phaseLabel }}.
                    </p>
                </div>
                <div class="sad-masthead__meta">
                    <span class="sad-badge"><i class="fa-solid fa-calendar" aria-hidden="true"></i> {{ $fyLabel }}</span>
                    <span class="sad-badge sad-badge--live"><i class="fa-solid fa-signal" aria-hidden="true"></i> {{ number_format((int) ($heroStaffOnlineNow ?? 0)) }} online</span>
                    <span class="sad-badge"><i class="fa-solid fa-users" aria-hidden="true"></i> {{ number_format($staffActive) }}/{{ number_format($staffTotal) }} staff</span>
                </div>
            </header>

            <div class="sad-kpi-strip" role="group" aria-label="Key performance indicators">
                <div class="sad-kpi" title="Phase 3 CFA submissions (all districts)">
                    <div class="sad-kpi__icon sad-kpi__icon--green"><i class="fa-solid fa-file-circle-plus" aria-hidden="true"></i></div>
                    <div class="sad-kpi__label">CFA total</div>
                    <div class="sad-kpi__value">{{ number_format($cfaTotalN) }}</div>
                    <div class="sad-kpi__foot">{{ number_format((int) ($cfaLast30 ?? 0)) }} in last 30 days</div>
                </div>
                <div class="sad-kpi">
                    <div class="sad-kpi__icon sad-kpi__icon--teal"><i class="fa-solid fa-bullseye" aria-hidden="true"></i></div>
                    <div class="sad-kpi__label">Target progress</div>
                    <div class="sad-kpi__value">{{ $ringPct }}%</div>
                    <div class="sad-kpi__foot">
                        @if ($cfaTargetN !== null)
                            {{ number_format($cfaTotalN) }} / {{ number_format($cfaTargetN) }}
                        @else
                            No state target set
                        @endif
                    </div>
                </div>
                <div class="sad-kpi">
                    <div class="sad-kpi__icon sad-kpi__icon--sky"><i class="fa-solid fa-sun" aria-hidden="true"></i></div>
                    <div class="sad-kpi__label">CFA today</div>
                    <div class="sad-kpi__value">{{ number_format($cfaTodayCount) }}</div>
                    <div class="sad-kpi__foot @if ($todayDelta > 0) is-up @elseif ($todayDelta < 0) is-down @endif">
                        @if ($todayDelta > 0)
                            +{{ number_format($todayDelta) }} vs yesterday ({{ number_format($cfaYesterdayCount) }})
                        @elseif ($todayDelta < 0)
                            {{ number_format(abs($todayDelta)) }} fewer vs yesterday ({{ number_format($cfaYesterdayCount) }})
                        @else
                            Same as yesterday ({{ number_format($cfaYesterdayCount) }})
                        @endif
                    </div>
                </div>
                <div class="sad-kpi">
                    <div class="sad-kpi__icon sad-kpi__icon--amber"><i class="fa-solid fa-user-check" aria-hidden="true"></i></div>
                    <div class="sad-kpi__label">Onboarding</div>
                    <div class="sad-kpi__value">
                        @if ($onbTarget > 0)
                            {{ $onbPct }}%
                        @else
                            {{ number_format($onbAchieved) }}
                        @endif
                    </div>
                    <div class="sad-kpi__foot">
                        @if ($onbTarget > 0)
                            {{ number_format($onbAchieved) }} / {{ number_format($onbTarget) }} locked
                        @else
                            Locked hub members (Phase 3)
                        @endif
                    </div>
                </div>
                <div class="sad-kpi" title="Approved Phase 3 service cases (delivered) — all time">
                    <div class="sad-kpi__icon sad-kpi__icon--teal"><i class="fa-solid fa-circle-check" aria-hidden="true"></i></div>
                    <div class="sad-kpi__label">Services delivered till date</div>
                    <div class="sad-kpi__value">{{ number_format((int) ($servicesDeliveredTillDate ?? 0)) }}</div>
                    <div class="sad-kpi__foot">
                        Till date · {{ number_format((int) ($servicesDeliveredThisFy ?? 0)) }} this FY
                    </div>
                </div>
                <div class="sad-kpi">
                    <div class="sad-kpi__icon sad-kpi__icon--green"><i class="fa-solid fa-piggy-bank" aria-hidden="true"></i></div>
                    <div class="sad-kpi__label">Savings (FY est.)</div>
                    <div class="sad-kpi__value" style="font-size:0.95rem;">Rs {{ number_format($savingsTotalThisFy, 0) }}</div>
                    <div class="sad-kpi__foot">Approved services impact</div>
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
                                        <stop offset="0%" stop-color="#5e48d9"/>
                                        <stop offset="100%" stop-color="#745af2"/>
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
                                    <div class="sad-spark" title="30-day CFA volume: {{ number_format($sparkSum) }} total">
                                        <svg viewBox="0 0 {{ $sparkW }} {{ $sparkH }}" preserveAspectRatio="none">
                                            <defs>
                                                <linearGradient id="sadSparkGrad" x1="0" y1="0" x2="0" y2="1">
                                                    <stop offset="0%" stop-color="#745af2" stop-opacity="0.4"/>
                                                    <stop offset="100%" stop-color="#745af2" stop-opacity="0"/>
                                                </linearGradient>
                                            </defs>
                                            <polygon class="fill" points="{{ $sparkFill }}"/>
                                            <polyline class="line" points="{{ $sparkLine }}"/>
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
                                $bizCol = $businessMix['colors'][$idx] ?? '#0d6e4f';
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

<script>
(function () {
    const gridColor = 'rgba(148, 163, 184, 0.22)';

    document.querySelectorAll('[data-sad-tab]').forEach((btn) => {
        btn.addEventListener('click', () => {
            const id = btn.getAttribute('data-sad-tab');
            document.querySelectorAll('[data-sad-tab]').forEach((b) => {
                b.classList.toggle('is-active', b === btn);
            });
            document.querySelectorAll('[data-sad-panel]').forEach((p) => {
                p.classList.toggle('is-active', p.getAttribute('data-sad-panel') === id);
            });
        });
    });

    const trendLabels = @json($stateCfaTrend['labels'] ?? []);
    const trendValues = @json($stateCfaTrend['values'] ?? []);
    const stEl = document.getElementById('stateTrendCurveChart');
    if (stEl && trendLabels.length) {
        const cx = stEl.getContext('2d');
        const dh = stEl.parentElement?.clientHeight || 168;
        const dFill = cx.createLinearGradient(0, 0, 0, dh);
        dFill.addColorStop(0, 'rgba(116, 90, 242, 0.24)');
        dFill.addColorStop(1, 'rgba(116, 90, 242, 0.02)');
        new Chart(stEl, {
            type: 'line',
            data: {
                labels: trendLabels,
                datasets: [{
                    label: 'State CFA',
                    data: trendValues,
                    borderColor: '#745af2',
                    backgroundColor: dFill,
                    fill: true,
                    tension: 0.42,
                    borderWidth: 2,
                    pointRadius: 0,
                    pointHoverRadius: 4
                }]
            },
            options: {
                responsive: true,
                maintainAspectRatio: false,
                plugins: { legend: { display: false } },
                scales: {
                    x: { grid: { display: false }, ticks: { font: { size: 9 }, color: '#64748b', maxRotation: 0 } },
                    y: { beginAtZero: true, grid: { color: gridColor }, ticks: { stepSize: 1, font: { size: 9 } } }
                }
            }
        });
    }

    const dLabels = @json($cfaByDistrict['labels']);
    const dValues = @json($cfaByDistrict['values']);
    const districtPalette = [
        '#745af2', '#3b82f6', '#2563eb', '#fbbf24', '#eab308',
        '#1e3a5f', '#ef5354', '#22c55e', '#8b72f4', '#5e48d9',
        '#64748b', '#0891b2', '#16a34a'
    ];
    const districtValueLabelsPlugin = {
        id: 'districtValueLabelsPlugin',
        afterDatasetsDraw(chart) {
            const { ctx } = chart;
            const meta = chart.getDatasetMeta(0);
            const values = chart.data.datasets[0]?.data || [];
            ctx.save();
            ctx.font = '700 10px DM Sans, sans-serif';
            ctx.fillStyle = '#0f172a';
            ctx.textAlign = 'left';
            ctx.textBaseline = 'middle';
            meta.data.forEach((bar, i) => {
                const raw = Number(values[i] ?? 0);
                ctx.fillText(raw.toLocaleString('en-IN'), bar.x + 6, bar.y);
            });
            ctx.restore();
        }
    };

    const districtEl = document.getElementById('chartDistrictCfa');
    if (districtEl) {
        new Chart(districtEl, {
            type: 'bar',
            plugins: [districtValueLabelsPlugin],
            data: {
                labels: dLabels.length ? dLabels : ['No data'],
                datasets: [{
                    label: 'CFA',
                    data: dLabels.length ? dValues : [0],
                    backgroundColor: dLabels.length
                        ? dValues.map((_, i) => districtPalette[i % districtPalette.length])
                        : ['#e2e8f0'],
                    borderRadius: 5
                }]
            },
            options: {
                indexAxis: 'y',
                responsive: true,
                maintainAspectRatio: false,
                layout: { padding: { right: 48 } },
                plugins: { legend: { display: false } },
                scales: {
                    x: { beginAtZero: true, grid: { color: gridColor } },
                    y: { grid: { display: false }, ticks: { font: { size: 9 } } }
                }
            }
        });
    }

    const searchInput = document.getElementById('stateStaffCfaSearch');
    const districtSelect = document.getElementById('stateStaffCfaDistrictFilter');
    const staffRows = Array.from(document.querySelectorAll('#stateStaffCfaList .sad-staff-row'));
    const noResults = document.getElementById('stateStaffCfaNoResults');

    const applyStaffCfaFilters = () => {
        if (!staffRows.length) return;
        const q = (searchInput?.value || '').trim().toLowerCase();
        const district = (districtSelect?.value || '').trim().toLowerCase();
        let visible = 0;
        staffRows.forEach((row) => {
            const show = (q === '' || (row.dataset.name || '').includes(q))
                && (district === '' || (row.dataset.district || '') === district);
            row.style.display = show ? '' : 'none';
            if (show) visible += 1;
        });
        if (noResults) noResults.style.display = visible === 0 ? '' : 'none';
    };
    searchInput?.addEventListener('input', applyStaffCfaFilters);
    districtSelect?.addEventListener('change', applyStaffCfaFilters);
})();
</script>

@include('partials.app-footer')
</body>
</html>
