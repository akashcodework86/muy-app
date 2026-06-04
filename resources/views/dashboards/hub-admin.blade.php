<!DOCTYPE html>
<html lang="{{ str_replace('_', '-', app()->getLocale()) }}">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>Hub ? {{ $hub->name }} ? {{ config('app.name') }}</title>
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
            --had-text: #0f172a;
            --had-muted: #64748b;
            --had-border: #e2e8f0;
            --had-surface: #ffffff;
            --had-brand: #d04a02;
            --had-brand-deep: #a63d02;
            --had-brand-light: #fdeee6;
            --had-brand-grad: linear-gradient(135deg, #a63d02 0%, #d04a02 55%, #eb8c00 100%);
            --had-green: #22c55e;
            --had-green-deep: #16a34a;
            --had-teal: #d04a02;
            --had-sky: #464646;
            --had-saffron: #ffb600;
            --had-gold: #b45309;
            --had-radius: 12px;
            --had-shadow: 0 1px 2px rgba(15, 23, 42, 0.05), 0 8px 24px rgba(208, 74, 2, 0.08);
        }
        .admin-app-body--hub-premium {
            background: #f7f5f2 !important;
        }
        .had {
            font-family: 'DM Sans', system-ui, sans-serif;
            color: var(--had-text);
            max-width: 100%;
        }
        .had-masthead {
            display: flex;
            flex-wrap: wrap;
            align-items: flex-end;
            justify-content: space-between;
            gap: 0.75rem 1.25rem;
            padding: 1rem 1.15rem;
            border-radius: var(--had-radius);
            background: var(--had-surface);
            color: var(--had-text);
            border: 1px solid var(--had-border);
            border-top: 3px solid var(--had-brand);
            box-shadow: var(--had-shadow);
            margin-bottom: 0.65rem;
        }
        .had-masthead__eyebrow {
            display: inline-flex;
            align-items: center;
            gap: 0.4rem;
            font-size: 0.68rem;
            font-weight: 700;
            letter-spacing: 0.08em;
            text-transform: uppercase;
            color: var(--had-brand-deep);
            margin-bottom: 0.35rem;
        }
        .had-masthead h1 {
            margin: 0;
            font-size: clamp(1.25rem, 2.5vw, 1.75rem);
            font-weight: 800;
            letter-spacing: -0.03em;
            line-height: 1.1;
        }
        .had-masthead__sub {
            margin: 0.35rem 0 0;
            font-size: 0.82rem;
            color: var(--had-muted);
            max-width: 36rem;
            line-height: 1.4;
        }
        .had-masthead__meta {
            display: flex;
            flex-wrap: wrap;
            gap: 0.45rem;
            justify-content: flex-end;
        }
        .had-badge {
            display: inline-flex;
            align-items: center;
            gap: 0.35rem;
            padding: 0.38rem 0.65rem;
            border-radius: 999px;
            background: #f8fafc;
            border: 1px solid var(--had-border);
            font-size: 0.72rem;
            font-weight: 600;
            color: #334155;
            white-space: nowrap;
        }
        .had-badge--live::before {
            content: '';
            width: 7px;
            height: 7px;
            border-radius: 50%;
            background: #4ade80;
            box-shadow: 0 0 0 3px rgba(74, 222, 128, 0.35);
            animation: hadPulse 1.6s ease-in-out infinite;
        }
        @keyframes hadPulse {
            0%, 100% { box-shadow: 0 0 0 0 rgba(74, 222, 128, 0.5); }
            50% { box-shadow: 0 0 0 6px rgba(74, 222, 128, 0); }
        }
        .had-kpi-strip {
            display: grid;
            grid-template-columns: repeat(6, minmax(0, 1fr));
            gap: 0.5rem;
            margin-bottom: 0.6rem;
        }
        @media (max-width: 1200px) {
            .had-kpi-strip { grid-template-columns: repeat(3, minmax(0, 1fr)); }
        }
        @media (max-width: 640px) {
            .had-kpi-strip { grid-template-columns: repeat(2, minmax(0, 1fr)); }
        }
        .had-kpi {
            background: var(--had-surface);
            border: 1px solid var(--had-border);
            border-radius: var(--had-radius);
            padding: 0.55rem 0.65rem;
            box-shadow: 0 1px 3px rgba(15, 23, 42, 0.04);
            min-width: 0;
            transition: border-color 0.15s, box-shadow 0.15s;
        }
        .had-kpi:hover {
            border-color: #f5c4a8;
            box-shadow: 0 4px 16px rgba(208, 74, 2, 0.12);
        }
        .had-kpi__icon {
            width: 1.65rem;
            height: 1.65rem;
            border-radius: 8px;
            display: inline-flex;
            align-items: center;
            justify-content: center;
            font-size: 0.75rem;
            margin-bottom: 0.35rem;
        }
        .had-kpi__icon--green { background: #ecfdf5; color: var(--had-green-deep); }
        .had-kpi__icon--sky { background: #eff6ff; color: var(--had-sky); }
        .had-kpi__icon--teal { background: var(--had-brand-light); color: var(--had-brand-deep); }
        .had-kpi__icon--amber { background: #fef9c3; color: var(--had-gold); }
        .had-kpi__icon--rose { background: rgba(190, 24, 93, 0.1); color: #be185d; }
        .had-kpi__label {
            font-size: 0.58rem;
            font-weight: 700;
            text-transform: uppercase;
            letter-spacing: 0.06em;
            color: var(--had-muted);
            line-height: 1.2;
        }
        .had-kpi__value {
            font-size: 1.15rem;
            font-weight: 800;
            letter-spacing: -0.02em;
            margin-top: 0.12rem;
            line-height: 1.1;
        }
        .had-kpi__foot {
            font-size: 0.62rem;
            color: var(--had-muted);
            margin-top: 0.2rem;
            font-weight: 600;
        }
        .had-kpi__foot.is-up { color: #15803d; }
        .had-kpi__foot.is-down { color: #b45309; }
        .had-kpi__foot.is-warn { color: #92400e; }
        .had-alerts {
            display: flex;
            flex-wrap: wrap;
            gap: 0.4rem;
            margin-bottom: 0.55rem;
        }
        .had-alert {
            display: inline-flex;
            align-items: center;
            gap: 0.35rem;
            padding: 0.32rem 0.6rem;
            border-radius: 999px;
            font-size: 0.7rem;
            font-weight: 600;
            border: 1px solid transparent;
        }
        .had-alert--warn {
            background: #fffbeb;
            border-color: #fcd34d;
            color: #92400e;
        }
        .had-alert--info {
            background: #eff6ff;
            border-color: #93c5fd;
            color: #1d4ed8;
        }
        .had-alert--ok {
            background: #ecfdf5;
            border-color: #6ee7b7;
            color: var(--had-green-deep);
        }
        .had-nav {
            display: flex;
            flex-wrap: wrap;
            gap: 0.35rem;
            margin-bottom: 0.6rem;
            padding: 0.35rem;
            background: var(--had-surface);
            border: 1px solid var(--had-border);
            border-radius: 12px;
            position: sticky;
            top: 0.5rem;
            z-index: 20;
            box-shadow: 0 8px 24px rgba(15, 23, 42, 0.06);
        }
        .had-nav__btn {
            flex: 1;
            min-width: 7rem;
            border: none;
            background: transparent;
            font-family: inherit;
            font-size: 0.78rem;
            font-weight: 700;
            color: var(--had-muted);
            padding: 0.5rem 0.65rem;
            border-radius: 8px;
            cursor: pointer;
            display: inline-flex;
            align-items: center;
            justify-content: center;
            gap: 0.4rem;
            transition: background 0.15s, color 0.15s;
        }
        .had-nav__btn:hover { background: #f1f5f9; color: var(--had-text); }
        .had-nav__btn.is-active {
            background: var(--had-brand-grad);
            color: #fff;
            box-shadow: 0 2px 8px rgba(208, 74, 2, 0.28);
        }
        .had-panel { display: none; animation: hadFade 0.25s ease; }
        .had-panel.is-active { display: block; }
        @keyframes hadFade {
            from { opacity: 0; transform: translateY(4px); }
            to { opacity: 1; transform: translateY(0); }
        }
        .had-grid {
            display: grid;
            gap: 0.55rem;
        }
        .had-grid--2 { grid-template-columns: 1fr 1fr; }
        .had-grid--3 { grid-template-columns: 1.1fr 1fr 1fr; }
        @media (max-width: 1100px) {
            .had-grid--2, .had-grid--3 { grid-template-columns: 1fr; }
        }
        .had-card {
            background: var(--had-surface);
            border: 1px solid var(--had-border);
            border-radius: var(--had-radius);
            padding: 0.75rem 0.85rem;
            box-shadow: 0 1px 3px rgba(15, 23, 42, 0.04);
            min-width: 0;
        }
        .had-card__head {
            display: flex;
            align-items: center;
            justify-content: space-between;
            gap: 0.5rem;
            margin-bottom: 0.55rem;
        }
        .had-card__title {
            font-size: 0.82rem;
            font-weight: 800;
            margin: 0;
            display: flex;
            align-items: center;
            gap: 0.4rem;
        }
        .had-card__title i { color: var(--had-brand); font-size: 0.85rem; }
        .had-card__hint {
            font-size: 0.68rem;
            color: var(--had-muted);
            margin: -0.35rem 0 0.5rem;
            line-height: 1.35;
        }
        .had-card__tag {
            font-size: 0.62rem;
            font-weight: 700;
            padding: 0.2rem 0.45rem;
            border-radius: 6px;
            background: var(--had-brand-light);
            color: var(--had-brand-deep);
        }
        .had-progress-block { margin-bottom: 0.65rem; }
        .had-progress-block:last-child { margin-bottom: 0; }
        .had-progress-top {
            display: flex;
            justify-content: space-between;
            align-items: baseline;
            gap: 0.5rem;
            font-size: 0.75rem;
            font-weight: 600;
            margin-bottom: 0.3rem;
        }
        .had-progress-top strong { font-size: 0.95rem; font-weight: 800; }
        .had-progress-track {
            height: 8px;
            border-radius: 999px;
            background: #e2e8f0;
            overflow: hidden;
        }
        .had-progress-fill {
            height: 100%;
            border-radius: 999px;
            background: var(--had-brand-grad);
            transition: width 0.6s ease;
        }
        .had-progress-fill--sky {
            background: linear-gradient(90deg, #eb8c00, #ffb600);
        }
        .had-progress-foot {
            font-size: 0.68rem;
            color: var(--had-muted);
            margin-top: 0.28rem;
            line-height: 1.35;
        }
        .had-signals {
            display: grid;
            grid-template-columns: 1fr 1fr;
            gap: 0.4rem;
        }
        .had-signal {
            padding: 0.45rem 0.5rem;
            border-radius: 10px;
            background: #f8fafc;
            border: 1px solid #e2e8f0;
        }
        .had-signal span {
            display: block;
            font-size: 0.62rem;
            color: var(--had-muted);
            font-weight: 600;
        }
        .had-signal strong {
            font-size: 0.88rem;
            margin-top: 0.15rem;
            display: block;
        }
        .had-chip {
            display: inline-block;
            font-size: 0.68rem;
            font-weight: 700;
            padding: 0.15rem 0.4rem;
            border-radius: 6px;
        }
        .had-chip.up { background: rgba(34, 197, 94, 0.12); color: #15803d; }
        .had-chip.down { background: rgba(251, 191, 36, 0.15); color: #b45309; }
        .had-chip.flat { background: #f1f5f9; color: #64748b; }
        .had-ring-wrap {
            display: grid;
            grid-template-columns: auto 1fr;
            gap: 0.75rem;
            align-items: center;
        }
        .had-ring-svg { width: 88px; height: 88px; }
        .had-ring-svg .track { fill: none; stroke: #e2e8f0; stroke-width: 8; }
        .had-ring-svg .bar {
            fill: none;
            stroke: url(#hadRingGrad);
            stroke-width: 8;
            stroke-linecap: round;
            transform: rotate(-90deg);
            transform-origin: 50% 50%;
        }
        .had-ring-svg .pct {
            font-family: 'DM Sans', sans-serif;
            font-weight: 800;
            fill: var(--had-text);
            font-size: 18px;
        }
        .had-ring-meta__big {
            font-size: 1.35rem;
            font-weight: 800;
            letter-spacing: -0.02em;
            line-height: 1;
        }
        .had-ring-meta__lbl {
            font-size: 0.68rem;
            color: var(--had-muted);
            margin-top: 0.2rem;
            font-weight: 600;
        }
        .had-chart-box { height: 168px; position: relative; }
        .had-chart-box--tall { height: min(420px, 55vh); }
        .had-stage-row {
            display: grid;
            grid-template-columns: 1.4rem 1fr 2.2rem;
            gap: 0.35rem;
            align-items: center;
            font-size: 0.68rem;
            font-weight: 700;
            margin-bottom: 0.28rem;
        }
        .had-stage-track {
            height: 6px;
            border-radius: 999px;
            background: #e2e8f0;
            overflow: hidden;
        }
        .had-stage-fill--seed { background: #ca8a04; height: 100%; border-radius: 999px; }
        .had-stage-fill--early { background: #eb8c00; height: 100%; border-radius: 999px; }
        .had-stage-fill--growth { background: var(--had-brand); height: 100%; border-radius: 999px; }
        .had-biz-row {
            display: grid;
            grid-template-columns: auto 1fr auto;
            gap: 0.45rem;
            align-items: center;
            margin-bottom: 0.35rem;
            font-size: 0.72rem;
        }
        .had-biz-row__rank {
            width: 1.35rem;
            height: 1.35rem;
            border-radius: 6px;
            background: #f1f5f9;
            font-size: 0.62rem;
            font-weight: 800;
            display: flex;
            align-items: center;
            justify-content: center;
            color: var(--had-muted);
        }
        .had-biz-row__track {
            height: 5px;
            border-radius: 999px;
            background: #e2e8f0;
            overflow: hidden;
        }
        .had-biz-row__fill { height: 100%; border-radius: 999px; }
        .had-biz-row__nums { font-weight: 700; white-space: nowrap; }
        .had-district-cards {
            display: grid;
            grid-template-columns: repeat(auto-fill, minmax(9rem, 1fr));
            gap: 0.45rem;
            margin-bottom: 0.55rem;
        }
        .had-district-card {
            padding: 0.5rem 0.55rem;
            border-radius: 10px;
            border: 1px solid var(--had-border);
            background: #f8fafc;
        }
        .had-district-card.is-top {
            border-color: #f5c4a8;
            background: #fff;
        }
        .had-district-card__name {
            font-size: 0.68rem;
            font-weight: 700;
            color: var(--had-muted);
            white-space: nowrap;
            overflow: hidden;
            text-overflow: ellipsis;
        }
        .had-district-card__val {
            font-size: 1.1rem;
            font-weight: 800;
            margin-top: 0.15rem;
        }
        .had-split-table {
            max-height: 14rem;
            overflow-y: auto;
            border: 1px solid var(--had-border);
            border-radius: 10px;
        }
        .had-split-row {
            display: flex;
            justify-content: space-between;
            padding: 0.38rem 0.55rem;
            font-size: 0.72rem;
            border-bottom: 1px solid #f1f5f9;
        }
        .had-split-row:last-child { border-bottom: none; }
        .had-split-row.is-zero { opacity: 0.5; }
        .had-staff-controls {
            display: flex;
            gap: 0.4rem;
            margin-bottom: 0.5rem;
            flex-wrap: wrap;
        }
        .had-staff-controls input,
        .had-staff-controls select {
            flex: 1;
            min-width: 8rem;
            padding: 0.42rem 0.55rem;
            border: 1px solid var(--had-border);
            border-radius: 8px;
            font-family: inherit;
            font-size: 0.78rem;
        }
        .had-staff-list { max-height: min(480px, 52vh); overflow-y: auto; }
        .had-staff-row {
            display: grid;
            grid-template-columns: 2rem 1fr auto;
            gap: 0.45rem;
            align-items: center;
            padding: 0.42rem 0.35rem;
            border-bottom: 1px solid #f1f5f9;
            font-size: 0.78rem;
        }
        .had-staff-row:last-child { border-bottom: none; }
        .had-staff-rank {
            font-size: 0.68rem;
            font-weight: 800;
            color: var(--had-muted);
        }
        .had-staff-rank.is-medal { color: var(--had-gold); }
        .had-staff-main { display: flex; align-items: center; gap: 0.45rem; min-width: 0; }
        .had-staff-avatar, .had-staff-fallback {
            width: 28px;
            height: 28px;
            border-radius: 8px;
            flex-shrink: 0;
            object-fit: cover;
        }
        .had-staff-fallback {
            display: inline-flex;
            align-items: center;
            justify-content: center;
            background: var(--had-brand-grad);
            color: #fff;
            font-size: 0.7rem;
            font-weight: 800;
        }
        .had-staff-name { font-weight: 700; white-space: nowrap; overflow: hidden; text-overflow: ellipsis; }
        .had-staff-district { font-size: 0.65rem; color: var(--had-muted); }
        .had-staff-val { font-weight: 800; font-size: 0.9rem; }
        .had-savings-grid {
            display: grid;
            grid-template-columns: repeat(3, 1fr);
            gap: 0.45rem;
            margin-bottom: 0.55rem;
        }
        @media (max-width: 720px) {
            .had-savings-grid { grid-template-columns: 1fr; }
        }
        .had-savings-tile {
            padding: 0.55rem 0.65rem;
            border-radius: 10px;
            border: 1px solid;
        }
        .had-savings-tile--green { border-color: rgba(34, 197, 94, 0.35); background: #ecfdf5; }
        .had-savings-tile--blue { border-color: rgba(59, 130, 246, 0.35); background: #eff6ff; }
        .had-savings-tile--violet { border-color: #f5c4a8; background: var(--had-brand-light); }
        .had-savings-tile__lbl {
            font-size: 0.6rem;
            font-weight: 700;
            text-transform: uppercase;
            letter-spacing: 0.05em;
        }
        .had-savings-tile__val {
            font-size: 1.15rem;
            font-weight: 800;
            margin-top: 0.2rem;
        }
        .had-table-wrap {
            border: 1px solid var(--had-border);
            border-radius: 10px;
            overflow: hidden;
        }
        .had-table {
            width: 100%;
            border-collapse: collapse;
            font-size: 0.76rem;
        }
        .had-table th {
            text-align: left;
            padding: 0.45rem 0.55rem;
            background: #f8fafc;
            font-weight: 700;
            color: var(--had-muted);
        }
        .had-table th:not(:first-child),
        .had-table td:not(:first-child) { text-align: right; }
        .had-table td {
            padding: 0.42rem 0.55rem;
            border-top: 1px solid #f1f5f9;
        }
        .had-dock {
            margin-top: 0.65rem;
            padding: 0.55rem 0.65rem;
            background: var(--had-surface);
            border: 1px solid var(--had-border);
            border-radius: var(--had-radius);
        }
        .had-dock__title {
            font-size: 0.68rem;
            font-weight: 800;
            text-transform: uppercase;
            letter-spacing: 0.08em;
            color: var(--had-muted);
            margin: 0 0 0.45rem;
        }
        .had-dock__links {
            display: flex;
            flex-wrap: wrap;
            gap: 0.35rem;
        }
        .had-dock__link {
            display: inline-flex;
            align-items: center;
            gap: 0.35rem;
            padding: 0.38rem 0.55rem;
            border-radius: 8px;
            border: 1px solid var(--had-border);
            background: #f8fafc;
            color: var(--had-text);
            text-decoration: none;
            font-size: 0.72rem;
            font-weight: 600;
            transition: background 0.12s, border-color 0.12s, transform 0.12s;
        }
        .had-dock__link:hover {
            background: var(--had-brand-light);
            border-color: #f0b48a;
        }
        .had-dock__link i { color: var(--had-brand); font-size: 0.8rem; }
        .had-spark {
            height: 28px;
            margin-top: 0.25rem;
        }
        .had-spark svg { width: 100%; height: 100%; }
        .had-spark .line { fill: none; stroke: var(--had-teal); stroke-width: 1.5; }
        .had-spark .fill { fill: url(#hadSparkGrad); opacity: 0.4; }
        .had-empty {
            text-align: center;
            padding: 1.25rem;
            color: #94a3b8;
            font-size: 0.8rem;
        }
        details.had-details summary {
            cursor: pointer;
            font-size: 0.72rem;
            font-weight: 700;
            color: var(--had-sky);
            list-style: none;
        }
        details.had-details summary::-webkit-details-marker { display: none; }
        .had-align-status {
            display: flex;
            flex-wrap: wrap;
            gap: 0.35rem;
            margin-top: 0.35rem;
        }
        .had-align-pill {
            font-size: 0.65rem;
            font-weight: 700;
            padding: 0.2rem 0.45rem;
            border-radius: 6px;
        }
        .had-align-pill--ok { background: #ecfdf5; color: var(--had-green-deep); }
        .had-align-pill--bad { background: #fffbeb; color: #92400e; }
        .had-align-gaps {
            margin-top: 0.4rem;
            padding: 0.45rem 0.55rem;
            border-radius: 8px;
            background: #f8fafc;
            border: 1px solid var(--had-border);
            font-size: 0.68rem;
            max-height: 8rem;
            overflow-y: auto;
        }
        .had-align-gaps li { margin: 0.2rem 0; line-height: 1.35; }
        .had-att-subnav {
            display: flex;
            flex-wrap: wrap;
            gap: 0.3rem;
            margin-bottom: 0.55rem;
            padding: 0.25rem;
            background: #f8fafc;
            border: 1px solid var(--had-border);
            border-radius: 10px;
        }
        .had-att-subnav__btn {
            flex: 1;
            min-width: 6.5rem;
            border: none;
            background: transparent;
            font-family: inherit;
            font-size: 0.72rem;
            font-weight: 700;
            color: var(--had-muted);
            padding: 0.42rem 0.55rem;
            border-radius: 7px;
            cursor: pointer;
            display: inline-flex;
            align-items: center;
            justify-content: center;
            gap: 0.35rem;
        }
        .had-att-subnav__btn.is-active {
            background: var(--had-surface);
            color: var(--had-brand-deep);
            box-shadow: 0 1px 4px rgba(15, 23, 42, 0.08);
        }
        .had-att-panel { display: none; }
        .had-att-panel.is-active { display: block; animation: hadFade 0.2s ease; }
        .had-att-status {
            display: inline-flex;
            align-items: center;
            gap: 0.25rem;
            font-size: 0.65rem;
            font-weight: 700;
            padding: 0.18rem 0.4rem;
            border-radius: 6px;
        }
        .had-att-status--ok { background: #ecfdf5; color: var(--had-green-deep); }
        .had-att-status--miss { background: #fef2f2; color: #b91c1c; }
        .had-insight-list {
            margin: 0;
            padding: 0;
            list-style: none;
        }
        .had-insight-list li {
            display: flex;
            gap: 0.45rem;
            align-items: flex-start;
            padding: 0.42rem 0;
            border-bottom: 1px solid #f1f5f9;
            font-size: 0.74rem;
            line-height: 1.4;
        }
        .had-insight-list li:last-child { border-bottom: none; }
        .had-insight-list i {
            color: var(--had-brand);
            margin-top: 0.15rem;
            flex-shrink: 0;
        }
        .had-chart-box { height: 200px; position: relative; }
        .had-chart-box--tall { height: 240px; }
        .had-team-grid {
            display: grid;
            grid-template-columns: repeat(auto-fill, minmax(168px, 1fr));
            gap: 0.55rem;
        }
        @media (min-width: 900px) {
            .had-team-grid { grid-template-columns: repeat(auto-fill, minmax(188px, 1fr)); }
        }
        .had-team-card {
            position: relative;
            border: 1px solid var(--had-border);
            border-radius: 12px;
            background: var(--had-surface);
            padding: 0.65rem 0.7rem 0.55rem;
            min-height: 7.5rem;
            transition: border-color 0.15s, box-shadow 0.15s, z-index 0s;
            z-index: 1;
        }
        .had-team-card:hover,
        .had-team-card:focus-within {
            border-color: #f0b48a;
            box-shadow: 0 10px 28px rgba(208, 74, 2, 0.14);
            z-index: 40;
        }
        .had-team-card__rank {
            position: absolute;
            top: 0.45rem;
            right: 0.5rem;
            font-size: 0.62rem;
            font-weight: 800;
            color: var(--had-muted);
        }
        .had-team-card__rank.is-medal { color: var(--had-gold); }
        .had-team-card__head {
            display: flex;
            align-items: center;
            gap: 0.45rem;
            min-width: 0;
        }
        .had-team-card__meta { min-width: 0; flex: 1; }
        .had-team-card__name {
            font-size: 0.76rem;
            font-weight: 800;
            line-height: 1.2;
            white-space: nowrap;
            overflow: hidden;
            text-overflow: ellipsis;
        }
        .had-team-card__district {
            font-size: 0.64rem;
            color: var(--had-muted);
            white-space: nowrap;
            overflow: hidden;
            text-overflow: ellipsis;
        }
        .had-team-card__score {
            margin-top: 0.45rem;
            display: flex;
            align-items: center;
            justify-content: space-between;
            gap: 0.35rem;
            font-size: 0.68rem;
        }
        .had-team-card__score strong {
            font-size: 1rem;
            font-weight: 800;
            color: var(--had-brand-deep);
        }
        .had-team-card__chips {
            display: flex;
            flex-wrap: wrap;
            gap: 0.25rem;
            margin-top: 0.35rem;
        }
        .had-team-chip {
            font-size: 0.58rem;
            font-weight: 700;
            padding: 0.15rem 0.35rem;
            border-radius: 5px;
            background: #f1f5f9;
            color: #475569;
        }
        .had-team-chip--svc { background: var(--had-brand-light); color: var(--had-brand-deep); }
        .had-team-card__detail {
            position: absolute;
            left: -1px;
            right: -1px;
            top: calc(100% - 2px);
            padding: 0.55rem 0.65rem 0.65rem;
            background: var(--had-surface);
            border: 1px solid #f0b48a;
            border-top: none;
            border-radius: 0 0 12px 12px;
            box-shadow: 0 14px 28px rgba(15, 23, 42, 0.12);
            opacity: 0;
            visibility: hidden;
            transform: translateY(-4px);
            transition: opacity 0.18s, transform 0.18s, visibility 0.18s;
            max-height: min(320px, 50vh);
            overflow-y: auto;
            pointer-events: none;
        }
        .had-team-card:hover .had-team-card__detail,
        .had-team-card:focus-within .had-team-card__detail {
            opacity: 1;
            visibility: visible;
            transform: translateY(0);
            pointer-events: auto;
        }
        .had-team-card__detail-title {
            font-size: 0.62rem;
            font-weight: 800;
            text-transform: uppercase;
            letter-spacing: 0.05em;
            color: var(--had-muted);
            margin-bottom: 0.4rem;
        }
        .had-del-row {
            margin-bottom: 0.42rem;
        }
        .had-del-row:last-child { margin-bottom: 0; }
        .had-del-row__top {
            display: flex;
            justify-content: space-between;
            align-items: baseline;
            gap: 0.35rem;
            font-size: 0.66rem;
            margin-bottom: 0.2rem;
        }
        .had-del-row__name {
            font-weight: 700;
            min-width: 0;
            white-space: nowrap;
            overflow: hidden;
            text-overflow: ellipsis;
        }
        .had-del-row__nums {
            font-weight: 700;
            color: var(--had-brand-deep);
            flex-shrink: 0;
        }
        .had-del-row__bar {
            height: 5px;
            border-radius: 999px;
            background: #e2e8f0;
            overflow: hidden;
        }
        .had-del-row__fill {
            height: 100%;
            border-radius: 999px;
            background: var(--had-brand-grad);
        }
        .had-del-row__fill.is-warn { background: linear-gradient(90deg, #f59e0b, #d97706); }
        .had-del-row__fill.is-low { background: linear-gradient(90deg, #f87171, #dc2626); }
        .had-team-card__foot {
            margin-top: 0.35rem;
            padding-top: 0.35rem;
            border-top: 1px dashed var(--had-border);
            font-size: 0.62rem;
            color: var(--had-muted);
            line-height: 1.35;
        }
        .had-team-card__foot a {
            color: var(--had-brand-deep);
            font-weight: 700;
            text-decoration: none;
        }
        .had-team-card__foot a:hover { text-decoration: underline; }
        @media (hover: none) {
            .had-team-card__detail {
                position: static;
                opacity: 1;
                visibility: visible;
                transform: none;
                border: none;
                box-shadow: none;
                max-height: none;
                padding: 0.45rem 0 0;
                pointer-events: auto;
            }
        }
    </style>
</head>
<body class="admin-app-body admin-app-body--dashboard admin-app-body--hub-premium">
    @include('partials.admin-topbar')
    <main class="admin-main">
        @include('partials.staff-daily-check-in-reminder')
        @if (session('status'))
            <div class="banner">{{ session('status') }}</div>
        @endif

        @php
            $fyLabel = $activeFy?->name ?? ($activeFy?->code ?? 'FY');
            $cfaTotalN = (int) ($cfaTotal ?? 0);
            $cfaTargetN = $hubCfaTargetSum !== null ? (int) $hubCfaTargetSum : null;
            $cfaFyN = (int) ($hubCfaThisFy ?? 0);
            $achPct = ($cfaTargetN !== null && $cfaTargetN > 0)
                ? (int) round(($cfaFyN / $cfaTargetN) * 100)
                : null;
            $ringPct = $heroProgressPct !== null ? (int) min(100, max(0, $heroProgressPct)) : 0;
            $ringCirc = 2 * M_PI * 38;
            $ringOffset = $ringCirc * (1 - $ringPct / 100);

            $sStageSum = (int) ($seedCount ?? 0) + (int) ($earlyCount ?? 0) + (int) ($growthCount ?? 0);
            $sStagePct = [
                'SEED' => $sStageSum > 0 ? (int) round(((int) ($seedCount ?? 0) / $sStageSum) * 100) : 0,
                'EARLY' => $sStageSum > 0 ? (int) round(((int) ($earlyCount ?? 0) / $sStageSum) * 100) : 0,
                'GROWTH' => $sStageSum > 0 ? (int) round(((int) ($growthCount ?? 0) / $sStageSum) * 100) : 0,
            ];

            $onbTarget = (int) ($hubOnboardingTarget ?? 0);
            $onbAchieved = (int) ($hubOnboardingAchieved ?? 0);
            $onbPct = $hubOnboardingProgressPct !== null ? (int) $hubOnboardingProgressPct : 0;
            $onbGap = max(0, $onbTarget - $onbAchieved);
            $onbDistrictRows = collect($hubOnboardingByDistrict ?? []);

            $insGap = $cfaTargetN !== null ? max(0, $cfaTargetN - $cfaFyN) : 0;
            $svcTargetN = $hubServicesTargetSum !== null ? (int) $hubServicesTargetSum : null;
            $svcFyN = (int) ($servicesDeliveredThisFy ?? 0);
            $svcAchPct = ($svcTargetN !== null && $svcTargetN > 0)
                ? (int) round(($svcFyN / $svcTargetN) * 100)
                : null;
            $plan = $hubTargetPlan ?? [];
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

            $staffCfaRows = $staffCfaByStaff ?? [];
            $teamCards = $staffPerformanceCards ?? [];
            $staffDistrictOptions = collect($teamCards)->pluck('district')->filter()->unique()->sort()->values()->all();

            $planPct = $plan['pct'] ?? null;
            $planCfa = $plan['cfa'] ?? [];
            $planSvc = $plan['services'] ?? [];
            $planMisaligned = $plan['misaligned'] ?? [];

            $att = $attendance ?? [];
            $attEnabled = (bool) ($att['enabled'] ?? false);
            $attToday = $att['today'] ?? ['total' => 0, 'present' => 0, 'absent' => 0, 'rate_pct' => null];
            $attTodayRows = $att['today_rows'] ?? [];
            $attDistrictToday = $att['district_today'] ?? [];
            $attTrend = $att['trend_14d'] ?? ['labels' => [], 'rates' => [], 'present' => [], 'total' => []];
            $attRate7d = $att['rate_7d'] ?? null;
            $attRate30d = $att['rate_30d'] ?? null;
            $attRateMtd = $att['rate_mtd'] ?? null;
            $attStaff30d = $att['staff_30d'] ?? [];
            $attWeekday = $att['weekday'] ?? ['labels' => [], 'rates' => []];
            $attInsights = $att['insights'] ?? [];
            $attDateLabel = (string) ($att['date_label'] ?? now()->format('d M Y'));
        @endphp

        <div class="had">
            <header class="had-masthead">
                <div>
                    <div class="had-masthead__eyebrow"><i class="fa-solid fa-building-user" aria-hidden="true"></i> {{ $hub->name }} Hub Command</div>
                    <h1>Welcome, {{ auth()->user()->name }}</h1>
                    <p class="had-masthead__sub">
                        Phase 3 oversight for <strong>{{ $districtsInHub }}</strong> district{{ $districtsInHub === 1 ? '' : 's' }} in this hub ? CFA and services in one view.
                    </p>
                </div>
                <div class="had-masthead__meta">
                    <span class="had-badge"><i class="fa-solid fa-calendar" aria-hidden="true"></i> {{ $fyLabel }}</span>
                    <span class="had-badge had-badge--live"><i class="fa-solid fa-signal" aria-hidden="true"></i> {{ number_format((int) ($heroStaffOnlineNow ?? 0)) }} online</span>
                    <span class="had-badge"><i class="fa-solid fa-users" aria-hidden="true"></i> {{ number_format($staffActive) }}/{{ number_format($staffTotal) }} staff</span>
                    <a href="{{ route('hub.batches.index') }}" class="had-badge" style="text-decoration:none;color:inherit;"><i class="fa-solid fa-layer-group"></i> Batch manager</a>
                </div>
            </header>

            <div class="had-kpi-strip" role="group" aria-label="Key performance indicators">
                <div class="had-kpi" title="All-time CFA in hub districts">
                    <div class="had-kpi__icon had-kpi__icon--green"><i class="fa-solid fa-file-circle-plus" aria-hidden="true"></i></div>
                    <div class="had-kpi__label">CFA in hub</div>
                    <div class="had-kpi__value">{{ number_format($cfaTotalN) }}</div>
                    <div class="had-kpi__foot">{{ number_format((int) ($cfaLast30 ?? 0)) }} last 30 days</div>
                </div>
                <div class="had-kpi">
                    <div class="had-kpi__icon had-kpi__icon--teal"><i class="fa-solid fa-bullseye" aria-hidden="true"></i></div>
                    <div class="had-kpi__label">CFA FY progress</div>
                    <div class="had-kpi__value">{{ $ringPct }}%</div>
                    <div class="had-kpi__foot">
                        @if ($cfaTargetN !== null)
                            {{ number_format($cfaFyN) }} / {{ number_format($cfaTargetN) }} hub target
                        @else
                            Hub CFA target not set
                        @endif
                    </div>
                </div>
                <div class="had-kpi">
                    <div class="had-kpi__icon had-kpi__icon--sky"><i class="fa-solid fa-sun" aria-hidden="true"></i></div>
                    <div class="had-kpi__label">CFA today</div>
                    <div class="had-kpi__value">{{ number_format($cfaTodayCount) }}</div>
                    <div class="had-kpi__foot @if ($todayDelta > 0) is-up @elseif ($todayDelta < 0) is-down @endif">
                        @if ($todayDelta > 0)
                            +{{ number_format($todayDelta) }} vs yesterday ({{ number_format($cfaYesterdayCount) }})
                        @elseif ($todayDelta < 0)
                            {{ number_format(abs($todayDelta)) }} fewer vs yesterday ({{ number_format($cfaYesterdayCount) }})
                        @else
                            Same as yesterday ({{ number_format($cfaYesterdayCount) }})
                        @endif
                    </div>
                </div>
                <div class="had-kpi">
                    <div class="had-kpi__icon had-kpi__icon--amber"><i class="fa-solid fa-user-check" aria-hidden="true"></i></div>
                    <div class="had-kpi__label">Onboarding</div>
                    <div class="had-kpi__value">
                        @if ($onbTarget > 0)
                            {{ $onbPct }}%
                        @else
                            {{ number_format($onbAchieved) }}
                        @endif
                    </div>
                    <div class="had-kpi__foot">
                        @if ($onbTarget > 0)
                            {{ number_format($onbAchieved) }} / {{ number_format($onbTarget) }} locked
                        @else
                            Locked hub members (Phase 3)
                        @endif
                    </div>
                </div>
                <div class="had-kpi" title="Approved services for incubatees in hub districts">
                    <div class="had-kpi__icon had-kpi__icon--teal"><i class="fa-solid fa-circle-check" aria-hidden="true"></i></div>
                    <div class="had-kpi__label">Services delivered</div>
                    <div class="had-kpi__value">{{ number_format((int) ($servicesDeliveredTillDate ?? 0)) }}</div>
                    <div class="had-kpi__foot">
                        Till date ? {{ number_format((int) ($servicesDeliveredThisFy ?? 0)) }} this FY
                    </div>
                </div>
                <div class="had-kpi">
                    <div class="had-kpi__icon had-kpi__icon--sky"><i class="fa-solid fa-screwdriver-wrench" aria-hidden="true"></i></div>
                    <div class="had-kpi__label">Services FY target</div>
                    <div class="had-kpi__value">@if ($svcAchPct !== null){{ $svcAchPct }}%@else?@endif</div>
                    <div class="had-kpi__foot">
                        @if ($svcTargetN !== null)
                            {{ number_format($svcFyN) }} / {{ number_format($svcTargetN) }} hub plan
                        @else
                            Service targets not set
                        @endif
                    </div>
                </div>
            </div>

            <div class="had-alerts" role="status">
                @if (($todayZeroDistricts ?? 0) > 0)
                    <span class="had-alert had-alert--warn">
                        <i class="fa-solid fa-triangle-exclamation" aria-hidden="true"></i>
                        {{ number_format((int) $todayZeroDistricts) }} district(s) with zero CFA today
                    </span>
                @endif
                @if ($todayTopDistrict)
                    <span class="had-alert had-alert--ok">
                        <i class="fa-solid fa-trophy" aria-hidden="true"></i>
                        Top today: {{ $todayTopDistrict['name'] }} ({{ number_format((int) $todayTopDistrict['count']) }})
                    </span>
                @endif
                @if (($cfaWoWDeltaPct ?? 0) !== 0)
                    <span class="had-alert had-alert--info">
                        <i class="fa-solid fa-chart-line" aria-hidden="true"></i>
                        7-day CFA {{ ($cfaWoWDeltaPct ?? 0) > 0 ? 'up' : 'down' }} {{ abs((int) ($cfaWoWDeltaPct ?? 0)) }}% vs prior week
                    </span>
                @endif
                @if ($planPct !== null && ! ($plan['all_aligned'] ?? false))
                    <span class="had-alert had-alert--warn">
                        <i class="fa-solid fa-scale-balanced" aria-hidden="true"></i>
                        Staff monthly vs district targets: {{ (int) $planPct }}% aligned in hub
                    </span>
                @endif
            </div>

            <nav class="had-nav" aria-label="Dashboard sections">
                <button type="button" class="had-nav__btn is-active" data-had-tab="overview">
                    <i class="fa-solid fa-gauge-high" aria-hidden="true"></i> Overview
                </button>
                <button type="button" class="had-nav__btn" data-had-tab="districts">
                    <i class="fa-solid fa-map" aria-hidden="true"></i> Districts
                </button>
                <button type="button" class="had-nav__btn" data-had-tab="team">
                    <i class="fa-solid fa-users-gear" aria-hidden="true"></i> Team performance
                </button>
                <button type="button" class="had-nav__btn" data-had-tab="attendance">
                    <i class="fa-solid fa-clipboard-user" aria-hidden="true"></i> Attendance
                </button>
            </nav>

            {{-- OVERVIEW --}}
            <section class="had-panel is-active" data-had-panel="overview">
                <div class="had-grid had-grid--2">
                    <div class="had-card">
                        <div class="had-card__head">
                            <h2 class="had-card__title"><i class="fa-solid fa-lightbulb" aria-hidden="true"></i> Program intelligence</h2>
                            @if ($achPct !== null)
                                <span class="had-card__tag">{{ $achPct }}% achieved</span>
                            @endif
                        </div>
                        @if ($cfaTargetN !== null && $cfaTargetN > 0)
                            <div class="had-progress-block">
                                <div class="had-progress-top">
                                    <span>CFA vs hub district target</span>
                                    <strong>{{ number_format($cfaFyN) }} / {{ number_format($cfaTargetN) }}</strong>
                                </div>
                                <div class="had-progress-track">
                                    <div class="had-progress-fill" style="width: {{ min(100, max(0, $achPct ?? 0)) }}%;"></div>
                                </div>
                                <p class="had-progress-foot">FY gap: <strong>{{ number_format($insGap) }}</strong> applications. Contact state admin to change targets.</p>
                            </div>
                        @else
                            <p class="had-progress-foot">Hub CFA district targets not configured yet (state admin).</p>
                        @endif

                        @if ($svcTargetN !== null && $svcTargetN > 0)
                            <div class="had-progress-block">
                                <div class="had-progress-top">
                                    <span>Services vs hub district target</span>
                                    <strong>{{ number_format($svcFyN) }} / {{ number_format($svcTargetN) }}</strong>
                                </div>
                                <div class="had-progress-track">
                                    <div class="had-progress-fill had-progress-fill--sky" style="width: {{ min(100, max(0, $svcAchPct ?? 0)) }}%;"></div>
                                </div>
                                <p class="had-progress-foot">Approved service cases this FY in hub districts.</p>
                            </div>
                        @endif

                        @if ($planPct !== null)
                            <div class="had-progress-block">
                                <div class="had-progress-top">
                                    <span>Target roll-up (CFA + services)</span>
                                    <strong>{{ (int) $planPct }}%</strong>
                                </div>
                                <div class="had-progress-track">
                                    <div class="had-progress-fill had-progress-fill--sky" style="width: {{ min(100, (int) $planPct) }}%;"></div>
                                </div>
                                <p class="had-progress-foot">
                                    <strong>{{ (int) ($plan['aligned_count'] ?? 0) }} of {{ (int) ($plan['tracked_count'] ?? 0) }}</strong> district?deliverable cells where staff monthly sum matches district target.
                                </p>
                                <div class="had-align-status">
                                    @if ($planCfa['tracked'] ?? false)
                                        <span class="had-align-pill {{ ($planCfa['aligned'] ?? false) ? 'had-align-pill--ok' : 'had-align-pill--bad' }}">
                                            CFA staff roll-up {{ ($planCfa['aligned'] ?? false) ? 'OK' : 'gap' }}
                                            ({{ number_format((int) ($planCfa['staff_sum'] ?? 0)) }}/{{ number_format((int) ($planCfa['district_target'] ?? 0)) }})
                                        </span>
                                    @endif
                                    @if (($planSvc['tracked_count'] ?? 0) > 0)
                                        <span class="had-align-pill {{ ($planSvc['all_aligned'] ?? false) ? 'had-align-pill--ok' : 'had-align-pill--bad' }}">
                                            Services {{ (int) ($planSvc['aligned_count'] ?? 0) }}/{{ (int) ($planSvc['tracked_count'] ?? 0) }} cells OK
                                        </span>
                                    @endif
                                </div>
                                @if (count($planMisaligned) > 0)
                                    <details class="had-details" style="margin-top:0.35rem;">
                                        <summary>{{ count($planMisaligned) }} mismatch(es) in hub</summary>
                                        <ul class="had-align-gaps">
                                            @foreach ($planMisaligned as $gap)
                                                <li>
                                                    <strong>{{ $gap['district'] }}</strong> ? {{ $gap['name'] }}:
                                                    staff {{ number_format((int) $gap['staff_sum']) }}
                                                    vs district {{ number_format((int) $gap['district_target']) }}
                                                    (gap {{ number_format((int) $gap['gap']) }})
                                                </li>
                                            @endforeach
                                        </ul>
                                    </details>
                                @elseif ($plan['all_aligned'] ?? false)
                                    <p class="had-progress-foot" style="color:#16a34a;margin-top:0.35rem;">
                                        <i class="fa-solid fa-circle-check" aria-hidden="true"></i> Staff monthly targets match district plan for CFA + services.
                                    </p>
                                @endif
                            </div>
                        @endif

                        @if ($onbTarget > 0)
                            <div class="had-progress-block">
                                <div class="had-progress-top">
                                    <span>Onboarding (locked batches)</span>
                                    <strong>{{ number_format($onbAchieved) }} / {{ number_format($onbTarget) }}</strong>
                                </div>
                                <div class="had-progress-track">
                                    <div class="had-progress-fill had-progress-fill--sky" style="width: {{ min(100, $onbPct) }}%;"></div>
                                </div>
                                <p class="had-progress-foot">
                                    Remaining gap: <strong>{{ number_format($onbGap) }}</strong>.
                                    <a href="{{ route('hub.onboarding-insight.index') }}">Onboarding insight</a>
                                </p>
                            </div>
                        @endif

                        <div class="had-signals">
                            <div class="had-signal">
                                <span>Last 7 days CFA</span>
                                <strong>{{ number_format((int) ($cfaLast7 ?? 0)) }}</strong>
                            </div>
                            <div class="had-signal">
                                <span>Week-over-week</span>
                                <span class="had-chip {{ ($cfaWoWDeltaPct ?? 0) > 0 ? 'up' : (($cfaWoWDeltaPct ?? 0) < 0 ? 'down' : 'flat') }}">
                                    {{ ($cfaWoWDeltaPct ?? 0) > 0 ? '+' : '' }}{{ (int) ($cfaWoWDeltaPct ?? 0) }}%
                                </span>
                            </div>
                            <div class="had-signal">
                                <span>This month</span>
                                <strong>{{ number_format((int) ($cfaThisMonth ?? 0)) }}</strong>
                            </div>
                            <div class="had-signal">
                                <span>Districts in hub</span>
                                <strong>{{ number_format($districtsInHub) }}</strong>
                            </div>
                        </div>
                    </div>

                    <div class="had-card">
                        <div class="had-card__head">
                            <h2 class="had-card__title"><i class="fa-solid fa-wave-square" aria-hidden="true"></i> Hub pulse</h2>
                            <span class="had-card__tag">14-day intake</span>
                        </div>
                        <div class="had-ring-wrap">
                            <svg class="had-ring-svg" viewBox="0 0 100 100" aria-hidden="true">
                                <defs>
                                    <linearGradient id="hadRingGrad" x1="0%" y1="0%" x2="100%" y2="100%">
                                        <stop offset="0%" stop-color="#a63d02"/>
                                        <stop offset="100%" stop-color="#d04a02"/>
                                    </linearGradient>
                                </defs>
                                <circle class="track" cx="50" cy="50" r="38"/>
                                <circle class="bar" cx="50" cy="50" r="38"
                                    stroke-dasharray="{{ round($ringCirc, 3) }}"
                                    stroke-dashoffset="{{ round($ringOffset, 3) }}"/>
                                <text class="pct" x="50" y="52" text-anchor="middle" dominant-baseline="middle">{{ $ringPct }}%</text>
                            </svg>
                            <div>
                                <div class="had-ring-meta__big">{{ number_format($cfaFyN) }}</div>
                                <div class="had-ring-meta__lbl">CFA this FY ? {{ $fyLabel }}</div>
                                @if ($sparkLine)
                                    <div class="had-spark" title="30-day CFA volume: {{ number_format($sparkSum) }} total">
                                        <svg viewBox="0 0 {{ $sparkW }} {{ $sparkH }}" preserveAspectRatio="none">
                                            <defs>
                                                <linearGradient id="hadSparkGrad" x1="0" y1="0" x2="0" y2="1">
                                                    <stop offset="0%" stop-color="#d04a02" stop-opacity="0.4"/>
                                                    <stop offset="100%" stop-color="#d04a02" stop-opacity="0"/>
                                                </linearGradient>
                                            </defs>
                                            <polygon class="fill" points="{{ $sparkFill }}"/>
                                            <polyline class="line" points="{{ $sparkLine }}"/>
                                        </svg>
                                    </div>
                                @endif
                            </div>
                        </div>
                        <div class="had-chart-box" style="margin-top:0.5rem;">
                            <canvas id="hubTrendCurveChart" aria-label="CFA per day in hub"></canvas>
                        </div>
                        <p class="had-card__hint" style="margin-top:0.45rem;margin-bottom:0.35rem;">Stage mix (saved forms)</p>
                        <div class="had-stage-row">
                            <span>Seed</span>
                            <div class="had-stage-track"><div class="had-stage-fill--seed" style="width: {{ $sStagePct['SEED'] }}%;"></div></div>
                            <span>{{ $sStagePct['SEED'] }}%</span>
                        </div>
                        <div class="had-stage-row">
                            <span>Early</span>
                            <div class="had-stage-track"><div class="had-stage-fill--early" style="width: {{ $sStagePct['EARLY'] }}%;"></div></div>
                            <span>{{ $sStagePct['EARLY'] }}%</span>
                        </div>
                        <div class="had-stage-row">
                            <span>Growth</span>
                            <div class="had-stage-track"><div class="had-stage-fill--growth" style="width: {{ $sStagePct['GROWTH'] }}%;"></div></div>
                            <span>{{ $sStagePct['GROWTH'] }}%</span>
                        </div>
                    </div>
                </div>

                <div class="had-card" style="margin-top:0.55rem;">
                    <div class="had-card__head">
                        <h2 class="had-card__title"><i class="fa-solid fa-chart-pie" aria-hidden="true"></i> Business category mix</h2>
                        <span class="had-card__tag">{{ number_format($bizMixTotal) }} apps</span>
                    </div>
                    @if (count($businessMix['labels'] ?? []) === 0)
                        <div class="had-empty">No category data yet</div>
                    @else
                        @foreach ($businessMix['labels'] as $idx => $label)
                            @php
                                $bizV = (int) ($businessMix['values'][$idx] ?? 0);
                                $bizPct = $bizMixTotal > 0 ? (int) round(100 * $bizV / $bizMixTotal) : 0;
                                $bizCol = $businessMix['colors'][$idx] ?? '#d04a02';
                            @endphp
                            <div class="had-biz-row">
                                <span class="had-biz-row__rank">#{{ $idx + 1 }}</span>
                                <div>
                                    <div style="font-weight:700;font-size:0.72rem;margin-bottom:0.2rem;">
                                        <i class="fa-solid {{ $bizIconFor((string) $label) }}" aria-hidden="true" style="color:{{ $bizCol }};margin-right:0.25rem;"></i>
                                        {{ $label }}
                                    </div>
                                    <div class="had-biz-row__track">
                                        <div class="had-biz-row__fill" style="width:{{ $bizPct }}%;background:{{ $bizCol }};"></div>
                                    </div>
                                </div>
                                <span class="had-biz-row__nums">{{ $bizPct }}% ? {{ number_format($bizV) }}</span>
                            </div>
                        @endforeach
                    @endif
                </div>
            </section>

            {{-- DISTRICTS --}}
            <section class="had-panel" data-had-panel="districts">
                <div class="had-district-cards">
                    @foreach ($topDistricts as $i => $d)
                        <div class="had-district-card @if ($i === 0) is-top @endif">
                            <div class="had-district-card__name">
                                @if ($i === 0)<i class="fa-solid fa-crown" style="color:var(--had-gold);margin-right:0.2rem;" aria-hidden="true"></i>@endif
                                {{ $d['name'] }}
                            </div>
                            <div class="had-district-card__val">{{ number_format($d['total']) }}</div>
                        </div>
                    @endforeach
                </div>
                <div class="had-grid had-grid--2">
                    <div class="had-card">
                        <h2 class="had-card__title"><i class="fa-solid fa-chart-bar" aria-hidden="true"></i> Applications by district</h2>
                        <p class="had-card__hint">CFA in hub districts ? {{ $fyLabel }}</p>
                        <div class="had-chart-box had-chart-box--tall">
                            <canvas id="chartDistrictCfa"></canvas>
                        </div>
                    </div>
                    <div class="had-card">
                        <h2 class="had-card__title"><i class="fa-solid fa-map-pin" aria-hidden="true"></i> District signals</h2>
                        <div class="had-signals" style="margin-bottom:0.55rem;">
                            <div class="had-signal">
                                <span>Top district today</span>
                                <strong>{{ $todayTopDistrict['name'] ?? '?' }} @if(isset($todayTopDistrict['count'])) ({{ number_format((int) $todayTopDistrict['count']) }}) @endif</strong>
                            </div>
                            <div class="had-signal">
                                <span>Zero CFA districts today</span>
                                <strong>{{ number_format((int) ($todayZeroDistricts ?? 0)) }}</strong>
                            </div>
                        </div>
                        <h3 class="had-card__title" style="font-size:0.78rem;margin-bottom:0.35rem;">
                            <i class="fa-solid fa-user-group" aria-hidden="true"></i> Onboarding by district
                        </h3>
                        <div class="had-split-table">
                            @forelse ($onbDistrictRows as $row)
                                @php $rowCount = (int) ($row['count'] ?? 0); @endphp
                                <div class="had-split-row @if ($rowCount === 0) is-zero @endif">
                                    <span>{{ $row['district'] }}</span>
                                    <strong>{{ number_format($rowCount) }}</strong>
                                </div>
                            @empty
                                <div class="had-empty">No district onboarding data</div>
                            @endforelse
                        </div>
                    </div>
                </div>
            </section>

            {{-- TEAM --}}
            <section class="had-panel" data-had-panel="team">
                <div class="had-card">
                    <div class="had-card__head">
                        <h2 class="had-card__title"><i class="fa-solid fa-ranking-star" aria-hidden="true"></i> Team performance</h2>
                        <span class="had-card__tag">{{ count($teamCards) }} staff</span>
                    </div>
                    <p class="had-card__hint">
                        Hover a card for deliverable-wise target vs achievement (CFA + services) for {{ $fyLabel }}.
                        <a href="{{ route('hub.staff-performance.index') }}" style="color:var(--had-brand-deep);font-weight:700;">Full report</a>
                    </p>
                    <div class="had-staff-controls">
                        <input type="text" id="hubStaffCfaSearch" placeholder="Search staff name?" autocomplete="off">
                        <select id="hubStaffCfaDistrictFilter">
                            <option value="">All districts</option>
                            @foreach ($staffDistrictOptions as $districtName)
                                <option value="{{ strtolower($districtName) }}">{{ $districtName }}</option>
                            @endforeach
                        </select>
                    </div>
                    <div class="had-team-grid" id="hubTeamGrid">
                        @forelse ($teamCards as $index => $card)
                            @php
                                $perfPct = $card['performance_pct'] ?? null;
                                $deliverables = $card['deliverables'] ?? [];
                            @endphp
                            <article class="had-team-card hub-team-card"
                                tabindex="0"
                                data-name="{{ strtolower($card['name']) }}"
                                data-district="{{ strtolower($card['district']) }}">
                                <span class="had-team-card__rank @if ($index < 3) is-medal @endif">#{{ $index + 1 }}</span>
                                <div class="had-team-card__head">
                                    @if (!empty($card['avatar_url']))
                                        <img src="{{ $card['avatar_url'] }}" alt="" class="had-staff-avatar">
                                    @else
                                        <span class="had-staff-fallback">{{ strtoupper(substr(trim((string) $card['name']), 0, 1)) ?: '?' }}</span>
                                    @endif
                                    <div class="had-team-card__meta">
                                        <div class="had-team-card__name" title="{{ $card['name'] }}">{{ $card['name'] }}</div>
                                        <div class="had-team-card__district">{{ $card['district'] }}</div>
                                    </div>
                                </div>
                                <div class="had-team-card__score">
                                    <span>FY performance</span>
                                    <strong>@if ($perfPct !== null){{ (int) $perfPct }}%@else&mdash;@endif</strong>
                                </div>
                                <div class="had-team-card__chips">
                                    <span class="had-team-chip">CFA {{ number_format((int) ($card['cfa_total'] ?? 0)) }}</span>
                                    @if ((int) ($card['services_active'] ?? 0) > 0)
                                        <span class="had-team-chip had-team-chip--svc">{{ (int) $card['services_active'] }} services</span>
                                    @endif
                                </div>
                                <div class="had-team-card__detail" role="tooltip">
                                    <div class="had-team-card__detail-title">Target vs achievement</div>
                                    @forelse ($deliverables as $del)
                                        @php
                                            $pct = $del['pct'] ?? null;
                                            $barPct = $pct !== null ? min(100, (int) $pct) : ($del['achieved'] > 0 ? 100 : 0);
                                            $barClass = $pct === null ? '' : ($pct >= 75 ? '' : ($pct >= 40 ? 'is-warn' : 'is-low'));
                                        @endphp
                                        <div class="had-del-row">
                                            <div class="had-del-row__top">
                                                <span class="had-del-row__name" title="{{ $del['name'] }}">{{ $del['name'] }}</span>
                                                <span class="had-del-row__nums">
                                                    {{ number_format((int) $del['achieved']) }}
                                                    @if ((int) $del['target'] > 0)
                                                        / {{ number_format((int) $del['target']) }}
                                                    @endif
                                                </span>
                                            </div>
                                            <div class="had-del-row__bar">
                                                <div class="had-del-row__fill {{ $barClass }}" style="width:{{ $barPct }}%;"></div>
                                            </div>
                                        </div>
                                    @empty
                                        <p style="margin:0;font-size:0.68rem;color:var(--had-muted);">No targets or achievements recorded for this FY yet.</p>
                                    @endforelse
                                    <div class="had-team-card__foot">
                                        Weighted score across deliverables with staff targets.
                                        <a href="{{ route('hub.staff-performance.index', array_filter(['staff_id' => $card['id'], 'fy' => $activeFy?->id])) }}">Open staff report</a>
                                    </div>
                                </div>
                            </article>
                        @empty
                            <div class="had-empty" style="grid-column:1/-1;">No active district staff in this hub</div>
                        @endforelse
                    </div>
                    <div class="had-empty" id="hubStaffCfaNoResults" style="display:none;">No matches for this filter</div>
                </div>
            </section>

            {{-- ATTENDANCE --}}
            <section class="had-panel" data-had-panel="attendance">
                @if (! $attEnabled)
                    <div class="had-card">
                        <div class="had-empty">
                            <i class="fa-solid fa-clipboard-user" aria-hidden="true"></i>
                            Daily staff attendance is not available yet. Run migrations to enable <code>staff_check_ins</code>.
                        </div>
                    </div>
                @else
                    <nav class="had-att-subnav" aria-label="Attendance views">
                        <button type="button" class="had-att-subnav__btn is-active" data-had-att-sub="today">
                            <i class="fa-solid fa-calendar-day" aria-hidden="true"></i> Today
                        </button>
                        <button type="button" class="had-att-subnav__btn" data-had-att-sub="overall">
                            <i class="fa-solid fa-calendar-week" aria-hidden="true"></i> Overall
                        </button>
                        <button type="button" class="had-att-subnav__btn" data-had-att-sub="analysis">
                            <i class="fa-solid fa-chart-line" aria-hidden="true"></i> Analysis
                        </button>
                    </nav>

                    <div class="had-att-panel is-active" data-had-att-panel="today">
                        <div class="had-savings-grid" style="margin-bottom:0.55rem;">
                            <div class="had-savings-tile had-savings-tile--green">
                                <div class="had-savings-tile__lbl">Present today</div>
                                <div class="had-savings-tile__val">{{ number_format((int) $attToday['present']) }}</div>
                            </div>
                            <div class="had-savings-tile had-savings-tile--blue">
                                <div class="had-savings-tile__lbl">Absent today</div>
                                <div class="had-savings-tile__val">{{ number_format((int) $attToday['absent']) }}</div>
                            </div>
                            <div class="had-savings-tile had-savings-tile--violet">
                                <div class="had-savings-tile__lbl">Mark rate</div>
                                <div class="had-savings-tile__val">
                                    @if ($attToday['rate_pct'] !== null){{ (int) $attToday['rate_pct'] }}%@else&mdash;@endif
                                </div>
                            </div>
                        </div>
                        <div class="had-grid had-grid--2">
                            <div class="had-card">
                                <div class="had-card__head">
                                    <h2 class="had-card__title"><i class="fa-solid fa-users" aria-hidden="true"></i> District staff today</h2>
                                    <span class="had-card__tag">{{ $attDateLabel }}</span>
                                </div>
                                <p class="had-card__hint">{{ (int) $attToday['present'] }} of {{ (int) $attToday['total'] }} active district staff marked daily attendance.</p>
                                <div class="had-staff-controls">
                                    <input type="text" id="hubAttTodaySearch" placeholder="Search staff name?" autocomplete="off">
                                    <select id="hubAttTodayFilter">
                                        <option value="">All</option>
                                        <option value="present">Present only</option>
                                        <option value="absent">Absent only</option>
                                    </select>
                                </div>
                                <div class="had-staff-list" id="hubAttTodayList">
                                    @forelse ($attTodayRows as $row)
                                        <div class="had-staff-row hub-att-today-row"
                                            data-name="{{ strtolower($row['name']) }}"
                                            data-status="{{ $row['present'] ? 'present' : 'absent' }}">
                                            <span class="had-staff-rank">
                                                @if ($row['present'])
                                                    <i class="fa-solid fa-circle-check" style="color:var(--had-green-deep);" aria-hidden="true"></i>
                                                @else
                                                    <i class="fa-regular fa-circle" style="color:#cbd5e1;" aria-hidden="true"></i>
                                                @endif
                                            </span>
                                            <div class="had-staff-main">
                                                <span class="had-staff-fallback">{{ strtoupper(substr(trim((string) $row['name']), 0, 1)) ?: '?' }}</span>
                                                <div style="min-width:0;">
                                                    <div class="had-staff-name">{{ $row['name'] }}</div>
                                                    <div class="had-staff-district">{{ $row['district'] }}</div>
                                                </div>
                                            </div>
                                            <span>
                                                @if ($row['present'])
                                                    <span class="had-att-status had-att-status--ok">Present @if (!empty($row['marked_at'])){{ $row['marked_at'] }}@endif</span>
                                                @else
                                                    <span class="had-att-status had-att-status--miss">Absent</span>
                                                @endif
                                            </span>
                                        </div>
                                    @empty
                                        <div class="had-empty">No active district staff in this hub</div>
                                    @endforelse
                                    <div class="had-empty" id="hubAttTodayNoResults" style="display:none;">No matches for this filter</div>
                                </div>
                            </div>
                            <div class="had-card">
                                <div class="had-card__head">
                                    <h2 class="had-card__title"><i class="fa-solid fa-map-location-dot" aria-hidden="true"></i> By district today</h2>
                                </div>
                                <div class="had-table-wrap">
                                    <table class="had-table">
                                        <thead>
                                            <tr>
                                                <th>District</th>
                                                <th>Present</th>
                                                <th>Total</th>
                                                <th>Rate</th>
                                            </tr>
                                        </thead>
                                        <tbody>
                                            @forelse ($attDistrictToday as $dRow)
                                                <tr>
                                                    <td>{{ $dRow['district'] }}</td>
                                                    <td>{{ (int) $dRow['present'] }}</td>
                                                    <td>{{ (int) $dRow['total'] }}</td>
                                                    <td>@if ($dRow['rate_pct'] !== null){{ (int) $dRow['rate_pct'] }}%@else&mdash;@endif</td>
                                                </tr>
                                            @empty
                                                <tr><td colspan="4" class="had-empty">No district breakdown for today</td></tr>
                                            @endforelse
                                        </tbody>
                                    </table>
                                </div>
                            </div>
                        </div>
                    </div>

                    <div class="had-att-panel" data-had-att-panel="overall">
                        <div class="had-savings-grid" style="margin-bottom:0.55rem;">
                            <div class="had-savings-tile had-savings-tile--green">
                                <div class="had-savings-tile__lbl">7-day average</div>
                                <div class="had-savings-tile__val">@if ($attRate7d !== null){{ (int) $attRate7d }}%@else&mdash;@endif</div>
                            </div>
                            <div class="had-savings-tile had-savings-tile--blue">
                                <div class="had-savings-tile__lbl">30-day mark rate</div>
                                <div class="had-savings-tile__val">@if ($attRate30d !== null){{ (int) $attRate30d }}%@else&mdash;@endif</div>
                            </div>
                            <div class="had-savings-tile had-savings-tile--violet">
                                <div class="had-savings-tile__lbl">Month to date</div>
                                <div class="had-savings-tile__val">@if ($attRateMtd !== null){{ (int) $attRateMtd }}%@else&mdash;@endif</div>
                            </div>
                        </div>
                        <div class="had-grid had-grid--2">
                            <div class="had-card">
                                <div class="had-card__head">
                                    <h2 class="had-card__title"><i class="fa-solid fa-chart-area" aria-hidden="true"></i> 14-day attendance trend</h2>
                                    <span class="had-card__tag">Daily mark %</span>
                                </div>
                                <div class="had-chart-box had-chart-box--tall">
                                    <canvas id="hubAttTrendChart" aria-label="14 day attendance trend"></canvas>
                                </div>
                            </div>
                            <div class="had-card">
                                <div class="had-card__head">
                                    <h2 class="had-card__title"><i class="fa-solid fa-ranking-star" aria-hidden="true"></i> Staff consistency (30 days)</h2>
                                </div>
                                <p class="had-card__hint">Days marked out of 30 for each active district staff member.</p>
                                <div class="had-staff-list" style="max-height:min(420px,48vh);">
                                    @forelse ($attStaff30d as $index => $sRow)
                                        <div class="had-staff-row">
                                            <span class="had-staff-rank @if ($index < 3) is-medal @endif">#{{ $index + 1 }}</span>
                                            <div class="had-staff-main">
                                                <span class="had-staff-fallback">{{ strtoupper(substr(trim((string) $sRow['name']), 0, 1)) ?: '?' }}</span>
                                                <div style="min-width:0;">
                                                    <div class="had-staff-name">{{ $sRow['name'] }}</div>
                                                    <div class="had-staff-district">{{ $sRow['district'] }}</div>
                                                </div>
                                            </div>
                                            <span class="had-staff-val">{{ (int) $sRow['days_present'] }}/30</span>
                                        </div>
                                    @empty
                                        <div class="had-empty">No attendance marks in the last 30 days</div>
                                    @endforelse
                                </div>
                            </div>
                        </div>
                    </div>

                    <div class="had-att-panel" data-had-att-panel="analysis">
                        <div class="had-grid had-grid--2">
                            <div class="had-card">
                                <div class="had-card__head">
                                    <h2 class="had-card__title"><i class="fa-solid fa-calendar-days" aria-hidden="true"></i> Weekday pattern</h2>
                                    <span class="had-card__tag">Last 4 weeks</span>
                                </div>
                                <p class="had-card__hint">Average daily mark rate by weekday across the hub.</p>
                                <div class="had-chart-box">
                                    <canvas id="hubAttWeekdayChart" aria-label="Weekday attendance pattern"></canvas>
                                </div>
                            </div>
                            <div class="had-card">
                                <div class="had-card__head">
                                    <h2 class="had-card__title"><i class="fa-solid fa-lightbulb" aria-hidden="true"></i> Attendance insights</h2>
                                </div>
                                <ul class="had-insight-list">
                                    @forelse ($attInsights as $insight)
                                        <li><i class="fa-solid fa-circle-info" aria-hidden="true"></i><span>{{ $insight }}</span></li>
                                    @empty
                                        <li><i class="fa-solid fa-circle-info" aria-hidden="true"></i><span>Mark rates will appear here once staff begin daily check-ins.</span></li>
                                    @endforelse
                                </ul>
                            </div>
                        </div>
                        <div class="had-card" style="margin-top:0.55rem;">
                            <div class="had-card__head">
                                <h2 class="had-card__title"><i class="fa-solid fa-table" aria-hidden="true"></i> District comparison today</h2>
                            </div>
                            <div class="had-chart-box had-chart-box--tall">
                                <canvas id="hubAttDistrictChart" aria-label="District attendance today"></canvas>
                            </div>
                        </div>
                    </div>
                @endif
            </section>

            <p class="had-progress-foot" style="margin-top:0.5rem;">
                Targets are set by <strong>state admin</strong>. This dashboard is read-only hub oversight.
            </p>

            <div class="had-dock">
                <p class="had-dock__title">Hub quick links</p>
                <div class="had-dock__links">
                    <a class="had-dock__link" href="{{ route('hub.applications.index') }}"><i class="fa-solid fa-clipboard-list"></i> Applications</a>
                    <a class="had-dock__link" href="{{ route('hub.batches.index') }}"><i class="fa-solid fa-layer-group"></i> Batches</a>
                    <a class="had-dock__link" href="{{ route('hub.onboarded.index') }}"><i class="fa-solid fa-user-check"></i> Onboarded</a>
                    <a class="had-dock__link" href="{{ route('hub.onboarding-insight.index') }}"><i class="fa-solid fa-chart-pie"></i> Onboarding insight</a>
                    <a class="had-dock__link" href="{{ route('hub.deliverables.index') }}"><i class="fa-solid fa-chart-column"></i> Deliverables</a>
                    <a class="had-dock__link" href="{{ route('hub.staff-performance.index') }}"><i class="fa-solid fa-ranking-star"></i> Staff performance</a>
                    <a class="had-dock__link" href="{{ route('hub.pending-actions.index') }}"><i class="fa-solid fa-clock"></i> Pending actions</a>
                    <a class="had-dock__link" href="{{ route('hub.field-coordinator-reports.index') }}"><i class="fa-solid fa-map-location-dot"></i> FC reports</a>
                    <a class="had-dock__link" href="{{ route('library.documents.index') }}"><i class="fa-solid fa-folder-open"></i> Documents</a>
                </div>
            </div>
        </div>
    </main>

<script>
(function () {
    const gridColor = 'rgba(148, 163, 184, 0.22)';

    document.querySelectorAll('[data-had-tab]').forEach((btn) => {
        btn.addEventListener('click', () => {
            const id = btn.getAttribute('data-had-tab');
            document.querySelectorAll('[data-had-tab]').forEach((b) => {
                b.classList.toggle('is-active', b === btn);
            });
            document.querySelectorAll('[data-had-panel]').forEach((p) => {
                p.classList.toggle('is-active', p.getAttribute('data-had-panel') === id);
            });
        });
    });

    const trendLabels = @json($cfaTrend['labels'] ?? []);
    const trendValues = @json($cfaTrend['values'] ?? []);
    const stEl = document.getElementById('hubTrendCurveChart');
    if (stEl && trendLabels.length) {
        const cx = stEl.getContext('2d');
        const dh = stEl.parentElement?.clientHeight || 168;
        const dFill = cx.createLinearGradient(0, 0, 0, dh);
        dFill.addColorStop(0, 'rgba(208, 74, 2, 0.24)');
        dFill.addColorStop(1, 'rgba(208, 74, 2, 0.02)');
        new Chart(stEl, {
            type: 'line',
            data: {
                labels: trendLabels,
                datasets: [{
                    label: 'State CFA',
                    data: trendValues,
                    borderColor: '#d04a02',
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
        '#d04a02', '#eb8c00', '#ffb600', '#2d2d2d', '#464646',
        '#a63d02', '#6b6b6b', '#22c55e', '#c75b12', '#b83d02',
        '#8b8b8b', '#d97706', '#16a34a'
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

    const searchInput = document.getElementById('hubStaffCfaSearch');
    const districtSelect = document.getElementById('hubStaffCfaDistrictFilter');
    const staffRows = Array.from(document.querySelectorAll('#hubTeamGrid .hub-team-card'));
    const noResults = document.getElementById('hubStaffCfaNoResults');

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
        if (noResults) noResults.style.display = visible === 0 && staffRows.length > 0 ? '' : 'none';
    };
    searchInput?.addEventListener('input', applyStaffCfaFilters);
    districtSelect?.addEventListener('change', applyStaffCfaFilters);

    document.querySelectorAll('[data-had-att-sub]').forEach((btn) => {
        btn.addEventListener('click', () => {
            const id = btn.getAttribute('data-had-att-sub');
            document.querySelectorAll('[data-had-att-sub]').forEach((b) => {
                b.classList.toggle('is-active', b === btn);
            });
            document.querySelectorAll('[data-had-att-panel]').forEach((p) => {
                p.classList.toggle('is-active', p.getAttribute('data-had-att-panel') === id);
            });
        });
    });

    const attSearch = document.getElementById('hubAttTodaySearch');
    const attFilter = document.getElementById('hubAttTodayFilter');
    const attRows = Array.from(document.querySelectorAll('#hubAttTodayList .hub-att-today-row'));
    const attNoResults = document.getElementById('hubAttTodayNoResults');
    const applyAttTodayFilters = () => {
        if (!attRows.length) return;
        const q = (attSearch?.value || '').trim().toLowerCase();
        const status = (attFilter?.value || '').trim().toLowerCase();
        let visible = 0;
        attRows.forEach((row) => {
            const show = (q === '' || (row.dataset.name || '').includes(q))
                && (status === '' || (row.dataset.status || '') === status);
            row.style.display = show ? '' : 'none';
            if (show) visible += 1;
        });
        if (attNoResults) attNoResults.style.display = visible === 0 ? '' : 'none';
    };
    attSearch?.addEventListener('input', applyAttTodayFilters);
    attFilter?.addEventListener('change', applyAttTodayFilters);

    const attTrendLabels = @json($attTrend['labels'] ?? []);
    const attTrendRates = @json($attTrend['rates'] ?? []);
    const attTrendEl = document.getElementById('hubAttTrendChart');
    if (attTrendEl && attTrendLabels.length) {
        new Chart(attTrendEl, {
            type: 'line',
            data: {
                labels: attTrendLabels,
                datasets: [{
                    label: 'Mark %',
                    data: attTrendRates,
                    borderColor: '#d04a02',
                    backgroundColor: 'rgba(208, 74, 2, 0.12)',
                    fill: true,
                    tension: 0.35,
                    pointRadius: 2,
                    pointHoverRadius: 4
                }]
            },
            options: {
                responsive: true,
                maintainAspectRatio: false,
                plugins: { legend: { display: false } },
                scales: {
                    y: { beginAtZero: true, max: 100, grid: { color: gridColor }, ticks: { callback: (v) => v + '%' } },
                    x: { grid: { display: false }, ticks: { font: { size: 9 }, maxRotation: 45 } }
                }
            }
        });
    }

    const attWeekdayLabels = @json($attWeekday['labels'] ?? []);
    const attWeekdayRates = @json($attWeekday['rates'] ?? []);
    const attWeekdayEl = document.getElementById('hubAttWeekdayChart');
    if (attWeekdayEl && attWeekdayLabels.length) {
        new Chart(attWeekdayEl, {
            type: 'bar',
            data: {
                labels: attWeekdayLabels,
                datasets: [{
                    label: 'Avg %',
                    data: attWeekdayRates,
                    backgroundColor: 'rgba(208, 74, 2, 0.75)',
                    borderRadius: 6
                }]
            },
            options: {
                responsive: true,
                maintainAspectRatio: false,
                plugins: { legend: { display: false } },
                scales: {
                    y: { beginAtZero: true, max: 100, grid: { color: gridColor }, ticks: { callback: (v) => v + '%' } },
                    x: { grid: { display: false } }
                }
            }
        });
    }

    const attDistrictLabels = @json(collect($attDistrictToday)->pluck('district')->values()->all());
    const attDistrictRates = @json(collect($attDistrictToday)->pluck('rate_pct')->map(fn ($v) => (int) ($v ?? 0))->values()->all());
    const attDistrictEl = document.getElementById('hubAttDistrictChart');
    if (attDistrictEl && attDistrictLabels.length) {
        new Chart(attDistrictEl, {
            type: 'bar',
            data: {
                labels: attDistrictLabels,
                datasets: [{
                    label: 'Today %',
                    data: attDistrictRates,
                    backgroundColor: attDistrictRates.map((_, i) => ['#d04a02', '#eb8c00', '#a63d02', '#f59e0b', '#fb923c', '#fdba74'][i % 6]),
                    borderRadius: 6
                }]
            },
            options: {
                indexAxis: 'y',
                responsive: true,
                maintainAspectRatio: false,
                plugins: { legend: { display: false } },
                scales: {
                    x: { beginAtZero: true, max: 100, grid: { color: gridColor }, ticks: { callback: (v) => v + '%' } },
                    y: { grid: { display: false }, ticks: { font: { size: 9 } } }
                }
            }
        });
    }
})();
</script>

@include('partials.app-footer')
</body>
</html>
