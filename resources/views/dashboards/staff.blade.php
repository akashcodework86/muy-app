<!DOCTYPE html>
<html lang="{{ str_replace('_', '-', app()->getLocale()) }}">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>Dashboard — {{ config('app.name') }}</title>
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=DM+Sans:ital,opsz,wght@0,9..40,400;0,9..40,500;0,9..40,600;0,9..40,700&display=swap" rel="stylesheet">
    @include('partials.admin-shell-styles')
    <style>
        .staff-info-card {
            background: #fff;
            border: 1px solid #e2e8f0;
            border-radius: 14px;
            padding: 1.5rem 1.75rem;
            max-width: 36rem;
            box-shadow: 0 4px 24px rgba(15, 23, 42, 0.06);
        }
        .staff-info-card p { margin: 0 0 1rem; color: #64748b; font-size: 0.95rem; line-height: 1.55; }
        .staff-info-card p:last-of-type { margin-bottom: 1.25rem; }
        .staff-info-card .admin-topbar__logout button { width: auto; margin-top: 0; }

        :root {
            --text: #0f172a;
            --text-muted: #5f6f86;
            --accent: #4f46e5;
            --accent2: #0d9488;
            --accent3: #ec4899;
            --border: rgba(255, 255, 255, 0.72);
            --radius: 24px;
            --shadow: 0 24px 60px rgba(99, 102, 241, 0.12);
            --glass: rgba(255, 255, 255, 0.66);
            --glass-strong: rgba(255, 255, 255, 0.84);
        }
        .dashboard-shell {
            position: relative;
        }
        .dashboard-shell::before {
            content: '';
            position: absolute;
            inset: 0 0 auto;
            height: 16rem;
            background:
                radial-gradient(circle at 10% 10%, rgba(251, 191, 36, 0.22), transparent 22%),
                radial-gradient(circle at 85% 10%, rgba(59, 130, 246, 0.18), transparent 20%),
                radial-gradient(circle at 55% 40%, rgba(236, 72, 153, 0.12), transparent 24%);
            pointer-events: none;
            z-index: 0;
        }
        .dashboard-shell > * {
            position: relative;
            z-index: 1;
        }
        .dashboard-intro {
            display: flex;
            flex-wrap: wrap;
            align-items: stretch;
            justify-content: space-between;
            gap: 1.5rem;
            margin-bottom: 1.5rem;
            padding: 1.75rem;
            border-radius: 32px;
            background:
                linear-gradient(135deg, rgba(255, 255, 255, 0.86), rgba(255, 255, 255, 0.58)),
                linear-gradient(120deg, rgba(79, 70, 229, 0.08), rgba(45, 212, 191, 0.04));
            border: 1px solid rgba(255, 255, 255, 0.7);
            box-shadow: var(--shadow);
            overflow: hidden;
        }
        .dashboard-intro::after {
            content: '';
            position: absolute;
            right: -3rem;
            top: -3rem;
            width: 14rem;
            height: 14rem;
            border-radius: 50%;
            background: radial-gradient(circle, rgba(129, 140, 248, 0.28), rgba(129, 140, 248, 0));
            pointer-events: none;
        }
        .dashboard-intro__content {
            flex: 1 1 34rem;
            max-width: 48rem;
        }
        .dashboard-intro__eyebrow {
            display: inline-flex;
            align-items: center;
            gap: 0.45rem;
            padding: 0.45rem 0.8rem;
            border-radius: 999px;
            background: rgba(255, 255, 255, 0.8);
            border: 1px solid rgba(99, 102, 241, 0.12);
            color: #5b21b6;
            font-size: 0.76rem;
            font-weight: 700;
            text-transform: uppercase;
            letter-spacing: 0.08em;
            margin-bottom: 0.95rem;
        }
        .dashboard-intro h2 {
            font-family: 'DM Sans', sans-serif;
            font-size: clamp(2rem, 5vw, 3.4rem);
            font-weight: 800;
            margin: 0;
            letter-spacing: -0.05em;
            color: var(--text);
            line-height: 0.98;
            max-width: 12ch;
        }
        .dashboard-intro p {
            margin: 0.8rem 0 0;
            color: var(--text-muted);
            font-size: 1.02rem;
            max-width: 44rem;
            line-height: 1.7;
        }
        .dashboard-meta-row {
            display: flex;
            flex-wrap: wrap;
            gap: 0.75rem;
            margin-top: 1.15rem;
        }
        .dashboard-meta-pill {
            display: inline-flex;
            align-items: center;
            gap: 0.45rem;
            padding: 0.62rem 0.92rem;
            border-radius: 999px;
            background: rgba(255, 255, 255, 0.9);
            border: 1px solid rgba(148, 163, 184, 0.22);
            color: #334155;
            font-size: 0.86rem;
            font-weight: 600;
            box-shadow: 0 10px 24px rgba(148, 163, 184, 0.12);
        }
        .dashboard-highlight-card {
            flex: 0 1 18rem;
            align-self: center;
            padding: 1.25rem;
            border-radius: 28px;
            background: linear-gradient(135deg, #fff 0%, #eff6ff 100%);
            border: 1px solid rgba(255, 255, 255, 0.9);
            box-shadow: 0 20px 36px rgba(56, 189, 248, 0.16);
        }
        .dashboard-highlight-card .label {
            font-size: 0.6rem;
            font-weight: 700;
            letter-spacing: 0.08em;
            text-transform: uppercase;
            color: #7c3aed;
        }
        .dashboard-highlight-card .highlight-card__header {
            display: flex;
            justify-content: space-between;
            align-items: center;
            margin-bottom: 0.35rem;
        }
        .dashboard-highlight-card .label-time {
            font-size: 0.55rem;
            color: #94a3b8;
            display: flex;
            align-items: center;
            gap: 0.2rem;
        }
        .dashboard-highlight-card .value {
            margin-top: 0.3rem;
            font-size: 1.5rem;
            font-weight: 800;
            line-height: 1;
            color: #0f172a;
            letter-spacing: -0.05em;
        }
        .dashboard-highlight-card .sub {
            margin-top: 0.3rem;
            color: var(--text-muted);
            font-size: 0.65rem;
            line-height: 1.35;
        }
        
        /* Apps Highlight Section */
        .apps-highlight {
            margin-top: 0.65rem;
        }
        .apps-highlight__main {
            text-align: center;
            padding: 1rem 0.75rem;
            background: linear-gradient(135deg, rgba(99, 102, 241, 0.08) 0%, rgba(139, 92, 246, 0.08) 100%);
            border-radius: 12px;
            margin-bottom: 0.75rem;
        }
        .apps-highlight__number {
            font-family: 'DM Sans', sans-serif;
            font-size: 2.5rem;
            font-weight: 800;
            line-height: 1;
            background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
            -webkit-background-clip: text;
            -webkit-text-fill-color: transparent;
            background-clip: text;
            margin-bottom: 0.35rem;
        }
        .apps-highlight__label {
            font-size: 0.65rem;
            font-weight: 700;
            text-transform: uppercase;
            letter-spacing: 0.05em;
            color: #64748b;
        }
        .apps-highlight__progress {
            margin-top: 0.85rem;
        }
        .apps-highlight__sub {
            margin-top: 0.5rem;
            text-align: center;
            color: var(--text-muted);
            font-size: 0.65rem;
        }
        .progress-bar-wrapper {
            background: rgba(226, 232, 240, 0.6);
            border-radius: 999px;
            height: 18px;
            overflow: hidden;
            position: relative;
            box-shadow: inset 0 1px 3px rgba(0, 0, 0, 0.05);
        }
        .progress-bar-fill {
            height: 100%;
            background: linear-gradient(90deg, #667eea 0%, #764ba2 50%, #f093fb 100%);
            border-radius: 999px;
            transition: width 0.6s ease;
            display: flex;
            align-items: center;
            justify-content: flex-end;
            padding-right: 0.5rem;
            position: relative;
            box-shadow: 0 2px 8px rgba(102, 126, 234, 0.3);
        }
        .progress-bar-label {
            font-size: 0.6rem;
            font-weight: 800;
            color: white;
            text-shadow: 0 1px 2px rgba(0, 0, 0, 0.2);
        }
        .progress-bar-meta {
            margin-top: 0.4rem;
            font-size: 0.6rem;
            color: #64748b;
            text-align: center;
            font-weight: 600;
        }
        
        /* Business Mix Compact */
        .business-mix-compact {
            margin-top: 1rem;
            padding: 1rem;
            background: rgba(255, 255, 255, 0.5);
            border: 1px solid rgba(148, 163, 184, 0.15);
            border-radius: 12px;
        }
        .business-mix-compact__header {
            margin-bottom: 0.85rem;
            text-align: center;
        }
        .business-mix-compact__title {
            font-size: 0.7rem;
            font-weight: 800;
            text-transform: uppercase;
            letter-spacing: 0.06em;
            color: #475569;
            margin-bottom: 0.2rem;
        }
        .business-mix-compact__meta {
            font-size: 0.6rem;
            color: #94a3b8;
        }
        .business-mix-compact__chart {
            position: relative;
        }
        .no-data-message {
            text-align: center;
            padding: 2rem 1rem;
            color: #94a3b8;
        }
        .no-data-message i {
            font-size: 2rem;
            margin-bottom: 0.5rem;
            display: block;
            opacity: 0.5;
        }
        .no-data-message p {
            margin: 0;
            font-size: 0.75rem;
        }
        .business-mix-legend {
            margin-top: 0.85rem;
            display: flex;
            flex-direction: column;
            gap: 0.4rem;
        }
        .legend-item {
            display: flex;
            align-items: center;
            gap: 0.5rem;
            font-size: 0.65rem;
            padding: 0.35rem 0.5rem;
            background: rgba(255, 255, 255, 0.6);
            border-radius: 6px;
            transition: all 0.2s ease;
        }
        .legend-item:hover {
            background: rgba(255, 255, 255, 0.9);
            transform: translateX(2px);
        }
        .legend-color {
            width: 12px;
            height: 12px;
            border-radius: 3px;
            flex-shrink: 0;
        }
        .legend-label {
            flex: 1;
            color: #475569;
            font-weight: 600;
        }
        .legend-value {
            color: #64748b;
            font-weight: 700;
            font-family: 'DM Sans', sans-serif;
        }
        
        /* Quick Stats Row - Remove */
        .quick-stats-row {
            display: none;
        }
        
        /* Stage Mix Compact with Arrows */
        .stage-mix-compact {
            margin-top: 0.85rem;
            padding-top: 0.85rem;
            border-top: 1px solid rgba(148, 163, 184, 0.15);
        }
        .stage-mix-compact__title {
            display: flex;
            justify-content: space-between;
            align-items: center;
            margin-bottom: 0.65rem;
        }
        .stage-mix-compact__title span:first-child {
            font-size: 0.6rem;
            font-weight: 800;
            letter-spacing: 0.08em;
            color: #667eea;
        }
        .stage-mix-compact__target {
            font-size: 0.55rem;
            color: #94a3b8;
            font-weight: 600;
        }
        .stage-arrows {
            width: 100%;
            height: 50px;
            margin-bottom: -12px;
            position: relative;
            z-index: 1;
        }
        .stage-pills-grid {
            display: grid;
            grid-template-columns: 1fr 1.4fr 1fr;
            gap: 0.45rem;
            position: relative;
            z-index: 2;
        }
        .stage-pill-compact {
            background: white;
            border-radius: 10px;
            padding: 0.6rem 0.5rem;
            text-align: center;
            transition: all 0.3s ease;
            border: 2px solid;
            box-shadow: 0 3px 10px rgba(0, 0, 0, 0.06);
        }
        .stage-pill-compact:hover {
            transform: translateY(-2px);
            box-shadow: 0 5px 18px rgba(0, 0, 0, 0.12);
        }
        .stage-pill-compact--seed {
            border-color: #fbbf24;
            background: linear-gradient(135deg, #fef3c7 0%, #fef9e7 100%);
        }
        .stage-pill-compact--early {
            border-color: #60a5fa;
            background: linear-gradient(135deg, #dbeafe 0%, #eff6ff 100%);
        }
        .stage-pill-compact--early.stage-pill-compact--large {
            padding: 0.75rem 0.6rem;
        }
        .stage-pill-compact--growth {
            border-color: #34d399;
            background: linear-gradient(135deg, #d1fae5 0%, #ecfdf5 100%);
        }
        .stage-pill-compact__label {
            font-size: 0.58rem;
            font-weight: 800;
            text-transform: uppercase;
            letter-spacing: 0.04em;
            color: #1e293b;
            margin-bottom: 0.35rem;
        }
        .stage-pill-compact__value {
            font-family: 'DM Sans', sans-serif;
            font-size: 1.35rem;
            font-weight: 800;
            line-height: 1;
            color: #0f172a;
            margin-bottom: 0.25rem;
        }
        .stage-pill-compact--large .stage-pill-compact__value {
            font-size: 1.65rem;
        }
        .stage-pill-compact__meta {
            font-size: 0.55rem;
            color: #64748b;
            margin-bottom: 0.4rem;
            line-height: 1.25;
        }
        .stage-pill-compact__gap {
            display: inline-block;
            padding: 0.2rem 0.45rem;
            border-radius: 999px;
            font-size: 0.55rem;
            font-weight: 700;
        }
        .stage-pill-compact__gap--positive {
            background: rgba(16, 185, 129, 0.15);
            color: #047857;
            border: 1px solid #10b981;
        }
        .stage-pill-compact__gap--negative {
            background: rgba(239, 68, 68, 0.15);
            color: #dc2626;
            border: 1px solid #ef4444;
        }
        .stage-insight-compact {
            margin-top: 0.65rem;
            padding: 0.6rem 0.75rem;
            background: rgba(241, 245, 249, 0.6);
            border-left: 2px solid #667eea;
            border-radius: 6px;
            font-size: 0.62rem;
            color: #475569;
            line-height: 1.4;
        }
        .stage-insight-compact strong {
            color: #0f172a;
            font-weight: 700;
        }
        
        .kpi-grid {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(210px, 1fr));
            gap: 1rem;
            margin-bottom: 1.5rem;
        }
        .kpi {
            background: var(--glass);
            border-radius: var(--radius);
            padding: 1.15rem 1.25rem 1.25rem;
            border: 1px solid var(--border);
            box-shadow: var(--shadow);
            position: relative;
            overflow: hidden;
            backdrop-filter: blur(18px);
        }
        .kpi::before {
            content: '';
            position: absolute;
            top: 0; left: 0; right: 0;
            height: 5px;
            background: linear-gradient(90deg, var(--accent), var(--accent2));
            opacity: 0.95;
        }
        .kpi:nth-child(2)::before { background: linear-gradient(90deg, #7c3aed, #4f46e5); }
        .kpi:nth-child(3)::before { background: linear-gradient(90deg, #0d9488, #14b8a6); }
        .kpi:nth-child(4)::before { background: linear-gradient(90deg, #ea580c, #f59e0b); }
        .kpi .label { font-size: 0.75rem; font-weight: 600; text-transform: uppercase; letter-spacing: 0.06em; color: var(--text-muted); margin-bottom: 0.35rem; }
        .kpi .val { font-family: 'DM Sans', sans-serif; font-size: 1.75rem; font-weight: 700; letter-spacing: -0.02em; color: var(--text); }
        .kpi .sub { font-size: 0.8rem; color: var(--text-muted); margin-top: 0.35rem; }
        .referral-card {
            background: linear-gradient(135deg, rgba(255, 255, 255, 0.88) 0%, rgba(238, 242, 255, 0.74) 100%);
            border: 1px solid var(--border);
            border-radius: var(--radius);
            padding: 1.35rem 1.45rem;
            margin-bottom: 1.5rem;
            box-shadow: var(--shadow);
            backdrop-filter: blur(16px);
        }
        .referral-card h3 { margin: 0 0 0.5rem; font-size: 1rem; font-weight: 700; color: var(--text); }
        .referral-card p { margin: 0 0 0.75rem; font-size: 0.88rem; color: var(--text-muted); line-height: 1.5; }
        .referral-row { display: flex; flex-wrap: wrap; gap: 0.5rem; align-items: stretch; }
        .referral-row input {
            flex: 1 1 16rem;
            padding: 0.55rem 0.65rem;
            border: 1px solid #cbd5e1;
            border-radius: 8px;
            font-size: 0.8rem;
            font-family: ui-monospace, monospace;
            background: #fff;
        }
        .referral-row button {
            padding: 0.7rem 1.05rem;
            border: none;
            border-radius: 12px;
            background: linear-gradient(135deg, #4f46e5, #ec4899);
            color: #fff;
            font-weight: 600;
            font-size: 0.85rem;
            cursor: pointer;
            font-family: inherit;
            box-shadow: 0 12px 24px rgba(129, 140, 248, 0.24);
        }
        .referral-row button:hover { filter: brightness(1.04); }
        .referral-row button.copied { background: #059669; }
        .warn-banner {
            background: #fffbeb;
            border: 1px solid #fcd34d;
            color: #92400e;
            padding: 0.85rem 1rem;
            border-radius: var(--radius);
            font-size: 0.9rem;
            margin-bottom: 1.25rem;
            line-height: 1.5;
        }
        .target-banner {
            background:
                radial-gradient(circle at top right, rgba(255,255,255,0.2), transparent 20%),
                linear-gradient(120deg, #2563eb 0%, #4f46e5 35%, #db2777 100%);
            color: #eff6ff;
            border-radius: var(--radius);
            padding: 1.6rem 1.6rem;
            margin-bottom: 1.5rem;
            display: flex;
            flex-wrap: wrap;
            align-items: center;
            justify-content: space-between;
            gap: 1rem;
            box-shadow: 0 24px 44px rgba(79, 70, 229, 0.25);
        }
        .target-banner h3 { margin: 0; font-size: 1rem; font-weight: 600; opacity: 0.92; }
        .target-banner .big { font-family: 'DM Sans', sans-serif; font-size: 2rem; font-weight: 700; margin: 0.25rem 0 0; color: #fff; }
        .target-banner .meta { font-size: 0.85rem; opacity: 0.8; max-width: 28rem; line-height: 1.45; }
        .progress-wrap { width: min(100%, 300px); }
        .progress-wrap .bar { height: 10px; background: rgba(255,255,255,0.18); border-radius: 999px; overflow: hidden; margin-top: 0.75rem; }
        .progress-wrap .fill { height: 100%; border-radius: 999px; background: linear-gradient(90deg, #5eead4, #99f6e4); transition: width 0.6s ease; }
        .month-strip {
            display: grid;
            grid-template-columns: repeat(auto-fill, minmax(4.5rem, 1fr));
            gap: 0.5rem;
            margin-top: 0.75rem;
        }
        .month-strip span {
            display: block;
            font-size: 0.65rem;
            font-weight: 600;
            color: var(--text-muted);
            text-transform: uppercase;
            letter-spacing: 0.04em;
            margin-bottom: 0.15rem;
        }
        .month-strip .cell {
            background: #fff;
            border: 1px solid var(--border);
            border-radius: 8px;
            padding: 0.4rem 0.45rem;
            text-align: center;
            font-weight: 700;
            font-size: 0.95rem;
            color: var(--text);
        }
        .charts-grid {
            display: grid;
            grid-template-columns: repeat(2, 1fr);
            gap: 1.25rem;
            margin-bottom: 1.25rem;
        }
        @media (max-width: 900px) { .charts-grid { grid-template-columns: 1fr; } }
        .charts-grid--3 {
            grid-template-columns: repeat(3, 1fr);
        }
        @media (max-width: 1100px) { .charts-grid--3 { grid-template-columns: 1fr; } }
        .charts-grid--2 {
            grid-template-columns: repeat(2, 1fr);
        }
        @media (max-width: 900px) { .charts-grid--2 { grid-template-columns: 1fr; } }
        .analytics-section-title {
            font-family: 'DM Sans', sans-serif;
            font-size: 1.05rem;
            font-weight: 700;
            color: var(--text);
            margin: 0.25rem 0 0.65rem;
            letter-spacing: -0.02em;
        }
        .chart-card {
            background: var(--glass-strong);
            border-radius: var(--radius);
            border: 1px solid var(--border);
            box-shadow: var(--shadow);
            padding: 1.2rem 1.3rem 1.4rem;
            backdrop-filter: blur(14px);
        }
        .chart-card h4 { margin: 0 0 0.15rem; font-size: 0.95rem; font-weight: 700; font-family: 'DM Sans', sans-serif; color: var(--text); }
        .chart-card .hint { font-size: 0.78rem; color: var(--text-muted); margin-bottom: 0.75rem; }
        .chart-card .canvas-wrap { position: relative; height: 260px; }
        .chart-card.tall .canvas-wrap { height: 280px; }
        
        /* Insights Container */
        .insights-container {
            padding: 0.5rem 0;
        }
        .insight-cards-compact {
            display: grid;
            grid-template-columns: 1fr 1fr;
            gap: 0.75rem;
            margin-bottom: 1rem;
        }
        .insight-card-mini {
            background: white;
            border-radius: 12px;
            padding: 1rem;
            display: flex;
            align-items: center;
            gap: 0.85rem;
            border: 2px solid;
            transition: all 0.3s ease;
        }
        .insight-card-mini:hover {
            transform: translateY(-2px);
            box-shadow: 0 8px 20px rgba(0, 0, 0, 0.12);
        }
        .insight-card-mini--primary {
            border-color: #6366f1;
            background: linear-gradient(135deg, #eef2ff 0%, #e0e7ff 100%);
        }
        .insight-card-mini--success {
            border-color: #10b981;
            background: linear-gradient(135deg, #ecfdf5 0%, #d1fae5 100%);
        }
        .insight-card-mini__icon {
            width: 48px;
            height: 48px;
            border-radius: 12px;
            display: flex;
            align-items: center;
            justify-content: center;
            font-size: 1.25rem;
            flex-shrink: 0;
        }
        .insight-card-mini--primary .insight-card-mini__icon {
            background: linear-gradient(135deg, #6366f1, #8b5cf6);
            color: white;
        }
        .insight-card-mini--success .insight-card-mini__icon {
            background: linear-gradient(135deg, #10b981, #059669);
            color: white;
        }
        .insight-card-mini__content {
            flex: 1;
        }
        .insight-card-mini__value {
            font-family: 'DM Sans', sans-serif;
            font-size: 1.75rem;
            font-weight: 800;
            line-height: 1;
            color: #0f172a;
            margin-bottom: 0.35rem;
        }
        .insight-card-mini__label {
            font-size: 0.75rem;
            font-weight: 700;
            text-transform: uppercase;
            letter-spacing: 0.05em;
            color: #475569;
            margin-bottom: 0.2rem;
        }
        .insight-card-mini__meta {
            font-size: 0.7rem;
            color: #64748b;
        }
        
        .insight-stats-grid {
            display: grid;
            grid-template-columns: 1fr 1fr;
            gap: 0.65rem;
        }
        .insight-stat-item {
            background: rgba(255, 255, 255, 0.6);
            border: 1px solid rgba(148, 163, 184, 0.15);
            border-radius: 10px;
            padding: 0.85rem;
            display: flex;
            align-items: center;
            gap: 0.65rem;
            transition: all 0.2s ease;
        }
        .insight-stat-item:hover {
            transform: translateY(-1px);
            box-shadow: 0 4px 12px rgba(0, 0, 0, 0.08);
            background: rgba(255, 255, 255, 0.9);
        }
        .insight-stat-item__icon {
            width: 40px;
            height: 40px;
            border-radius: 10px;
            display: flex;
            align-items: center;
            justify-content: center;
            flex-shrink: 0;
            color: white;
            font-size: 1rem;
        }
        .insight-stat-item__content {
            flex: 1;
            min-width: 0;
        }
        .insight-stat-item__value {
            font-family: 'DM Sans', sans-serif;
            font-size: 1.35rem;
            font-weight: 800;
            color: #0f172a;
            line-height: 1;
            margin-bottom: 0.25rem;
        }
        .insight-stat-item__label {
            font-size: 0.7rem;
            font-weight: 700;
            text-transform: uppercase;
            letter-spacing: 0.04em;
            color: #475569;
            margin-bottom: 0.15rem;
        }
        .insight-stat-item__meta {
            font-size: 0.65rem;
            color: #94a3b8;
        }
        
        .recent-table-wrap {
            background: var(--glass-strong);
            border: 1px solid var(--border);
            border-radius: var(--radius);
            box-shadow: var(--shadow);
            overflow-x: auto;
            margin-bottom: 1.5rem;
            backdrop-filter: blur(14px);
        }
        .recent-table-wrap h4 { margin: 0; padding: 1rem 1.15rem 0.5rem; font-size: 0.95rem; font-weight: 700; color: var(--text); }
        .recent-table-wrap table { width: 100%; border-collapse: collapse; font-size: 0.85rem; }
        .recent-table-wrap th, .recent-table-wrap td { padding: 0.55rem 1rem; text-align: left; border-bottom: 1px solid #f1f5f9; }
        .recent-table-wrap th { color: var(--text-muted); font-weight: 600; font-size: 0.72rem; text-transform: uppercase; letter-spacing: 0.05em; }
        .staff-note {
            background: linear-gradient(135deg, rgba(255,255,255,0.9), rgba(255,255,255,0.72));
            border: 1px solid var(--border);
            border-radius: var(--radius);
            padding: 1.15rem 1.3rem;
            font-size: 0.9rem;
            color: var(--text-muted);
            line-height: 1.5;
            box-shadow: var(--shadow);
        }
        .staff-portal-links {
            display: flex;
            flex-wrap: wrap;
            gap: 0.75rem;
            margin-bottom: 1.5rem;
        }
        .staff-portal-link {
            display: inline-flex;
            align-items: center;
            gap: 0.5rem;
            padding: 0.75rem 1.05rem;
            background: rgba(255, 255, 255, 0.8);
            border: 1px solid rgba(255, 255, 255, 0.92);
            border-radius: 16px;
            font-size: 0.875rem;
            font-weight: 700;
            color: var(--text);
            text-decoration: none;
            box-shadow: 0 14px 30px rgba(99, 102, 241, 0.08);
            font-family: 'DM Sans', sans-serif;
            transition: transform 0.18s ease, box-shadow 0.18s ease, border-color 0.18s ease, color 0.18s ease;
        }
        .staff-portal-link:hover {
            transform: translateY(-2px);
            border-color: rgba(99, 102, 241, 0.28);
            color: #4f46e5;
            box-shadow: 0 18px 34px rgba(99, 102, 241, 0.16);
        }
        .insight-grid {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(220px, 1fr));
            gap: 1rem;
            margin-bottom: 1.25rem;
        }
        .insight-card {
            border-radius: var(--radius);
            padding: 1.2rem 1.35rem;
            color: #fff;
            box-shadow: 0 12px 32px rgba(15, 23, 42, 0.18);
            position: relative;
            overflow: hidden;
            min-height: 7.5rem;
        }
        .insight-card::after {
            content: '';
            position: absolute;
            inset: -40% -20% auto auto;
            width: 140px;
            height: 140px;
            border-radius: 50%;
            background: rgba(255,255,255,0.12);
            pointer-events: none;
        }
        .insight-card .insight-label {
            font-size: 0.72rem;
            font-weight: 700;
            text-transform: uppercase;
            letter-spacing: 0.08em;
            opacity: 0.88;
            margin-bottom: 0.5rem;
            position: relative;
            z-index: 1;
        }
        .insight-card .insight-val {
            font-family: 'DM Sans', sans-serif;
            font-size: 2rem;
            font-weight: 800;
            letter-spacing: -0.03em;
            line-height: 1.1;
            position: relative;
            z-index: 1;
        }
        .insight-card .insight-sub {
            font-size: 0.82rem;
            opacity: 0.88;
            margin-top: 0.45rem;
            line-height: 1.4;
            position: relative;
            z-index: 1;
        }
        .insight-card--indigo { background: linear-gradient(135deg, #3730a3 0%, #6366f1 55%, #818cf8 100%); }
        .insight-card--teal { background: linear-gradient(135deg, #0f766e 0%, #14b8a6 50%, #2dd4bf 100%); }
        .insight-card--amber { background: linear-gradient(135deg, #b45309 0%, #ea580c 45%, #fbbf24 100%); }
        .insight-card--rose { background: linear-gradient(135deg, #9f1239 0%, #e11d48 50%, #fb7185 100%); }
        
        /* Stage Mix Hero Section */
        .stage-mix-hero {
            background: linear-gradient(135deg, rgba(255, 255, 255, 0.96) 0%, rgba(245, 248, 255, 0.88) 100%);
            border: 1px solid rgba(255, 255, 255, 0.8);
            border-radius: 32px;
            box-shadow: 0 24px 72px rgba(99, 102, 241, 0.14), 0 0 0 1px rgba(148, 163, 184, 0.04) inset;
            padding: 2.5rem;
            margin-bottom: 1.5rem;
            backdrop-filter: blur(20px);
            position: relative;
            overflow: hidden;
        }
        .stage-mix-hero::before {
            content: '';
            position: absolute;
            top: -60%;
            right: -15%;
            width: 500px;
            height: 500px;
            background: radial-gradient(circle, rgba(139, 92, 246, 0.12), transparent 70%);
            pointer-events: none;
            animation: floatGlow 10s ease-in-out infinite;
        }
        .stage-mix-hero::after {
            content: '';
            position: absolute;
            bottom: -40%;
            left: -10%;
            width: 400px;
            height: 400px;
            background: radial-gradient(circle, rgba(59, 130, 246, 0.1), transparent 70%);
            pointer-events: none;
            animation: floatGlow 12s ease-in-out infinite reverse;
        }
        .stage-mix-hero__header {
            display: flex;
            justify-content: space-between;
            align-items: flex-start;
            flex-wrap: wrap;
            gap: 2rem;
            margin-bottom: 2.5rem;
            position: relative;
            z-index: 1;
        }
        .stage-mix-hero__title h3 {
            font-family: 'DM Sans', sans-serif;
            font-size: 1.25rem;
            font-weight: 800;
            letter-spacing: 0.05em;
            color: #1e293b;
            margin: 0 0 0.5rem;
            display: flex;
            align-items: center;
            gap: 0.5rem;
        }
        .stage-mix-hero__title h3 i {
            color: #667eea;
            font-size: 1.1rem;
        }
        .stage-mix-hero__subtitle {
            font-size: 0.95rem;
            color: #64748b;
            font-weight: 600;
            margin: 0;
        }
        .stage-mix-hero__main-stat {
            background: rgba(255, 255, 255, 0.92);
            backdrop-filter: blur(12px);
            border: 1px solid rgba(148, 163, 184, 0.18);
            border-radius: 24px;
            padding: 1.5rem 2rem;
            box-shadow: 0 8px 28px rgba(0, 0, 0, 0.08);
            text-align: right;
            min-width: 280px;
        }
        .stage-mix-hero__stat-label {
            font-size: 0.72rem;
            font-weight: 700;
            letter-spacing: 0.08em;
            text-transform: uppercase;
            color: #667eea;
            margin-bottom: 0.5rem;
            display: flex;
            align-items: center;
            justify-content: flex-end;
            gap: 0.35rem;
        }
        .stage-mix-hero__stat-value {
            font-family: 'DM Sans', sans-serif;
            font-size: 3.5rem;
            font-weight: 800;
            color: #0f172a;
            line-height: 1;
            letter-spacing: -0.04em;
            margin-bottom: 0.5rem;
            background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
            -webkit-background-clip: text;
            -webkit-text-fill-color: transparent;
            background-clip: text;
        }
        .stage-mix-hero__stat-sub {
            font-size: 0.85rem;
            color: #64748b;
            line-height: 1.4;
        }
        .stage-bubbles-hero {
            display: flex;
            justify-content: center;
            align-items: center;
            gap: 2rem;
            flex-wrap: wrap;
            margin-bottom: 2.5rem;
            position: relative;
            z-index: 1;
        }
        .stage-bubble-hero {
            flex: 0 1 220px;
            aspect-ratio: 1;
            border-radius: 50%;
            display: flex;
            align-items: center;
            justify-content: center;
            padding: 1.75rem;
            position: relative;
            transition: all 0.4s cubic-bezier(0.4, 0, 0.2, 1);
            box-shadow: 0 12px 45px rgba(0, 0, 0, 0.12);
        }
        .stage-bubble-hero:hover {
            transform: scale(1.06) translateY(-4px);
            box-shadow: 0 20px 60px rgba(0, 0, 0, 0.18);
        }
        .stage-bubble-hero--seed {
            background: linear-gradient(135deg, #fef3c7 0%, #fde047 50%, #facc15 100%);
            border: 3px solid rgba(234, 179, 8, 0.4);
        }
        .stage-bubble-hero--early {
            background: linear-gradient(135deg, #dbeafe 0%, #93c5fd 50%, #60a5fa 100%);
            border: 3px solid rgba(59, 130, 246, 0.4);
        }
        .stage-bubble-hero--early.stage-bubble-hero--large {
            flex: 0 1 280px;
        }
        .stage-bubble-hero--growth {
            background: linear-gradient(135deg, #d1fae5 0%, #6ee7b7 50%, #34d399 100%);
            border: 3px solid rgba(16, 185, 129, 0.4);
        }
        .stage-bubble-hero__inner {
            text-align: center;
            width: 100%;
        }
        .stage-bubble-hero__label {
            font-size: 1rem;
            font-weight: 800;
            letter-spacing: 0.05em;
            text-transform: uppercase;
            color: #1e293b;
            margin-bottom: 0.75rem;
        }
        .stage-bubble-hero__percent {
            font-family: 'DM Sans', sans-serif;
            font-size: 3.2rem;
            font-weight: 800;
            line-height: 1;
            color: #0f172a;
            margin-bottom: 0.6rem;
            letter-spacing: -0.03em;
        }
        .stage-bubble-hero--large .stage-bubble-hero__percent {
            font-size: 4rem;
        }
        .stage-bubble-hero__detail {
            font-size: 0.85rem;
            color: #475569;
            margin-bottom: 0.85rem;
            font-weight: 600;
            line-height: 1.3;
        }
        .stage-bubble-hero__gap {
            display: inline-block;
            padding: 0.45rem 1rem;
            border-radius: 999px;
            font-size: 0.85rem;
            font-weight: 700;
            letter-spacing: 0.02em;
        }
        .stage-bubble-hero__gap--positive {
            background: rgba(16, 185, 129, 0.18);
            color: #047857;
            border: 2px solid #10b981;
        }
        .stage-bubble-hero__gap--negative {
            background: rgba(239, 68, 68, 0.18);
            color: #dc2626;
            border: 2px solid #ef4444;
        }
        .stage-mix-hero__insight {
            background: linear-gradient(135deg, rgba(249, 250, 251, 0.8), rgba(241, 245, 249, 0.9));
            border-left: 4px solid #667eea;
            border-radius: 16px;
            padding: 1.25rem 1.5rem;
            font-size: 0.95rem;
            color: #334155;
            line-height: 1.7;
            position: relative;
            z-index: 1;
            display: flex;
            align-items: center;
            gap: 0.75rem;
        }
        .stage-mix-hero__insight i {
            font-size: 1.1rem;
            color: #667eea;
            flex-shrink: 0;
        }
        .stage-mix-hero__insight strong {
            color: #0f172a;
            font-weight: 700;
        }
        @media (max-width: 900px) {
            .stage-mix-hero {
                padding: 1.75rem;
            }
            .stage-mix-hero__header {
                flex-direction: column;
                gap: 1.5rem;
            }
            .stage-mix-hero__main-stat {
                text-align: left;
                width: 100%;
                min-width: auto;
            }
            .stage-mix-hero__stat-label {
                justify-content: flex-start;
            }
            .stage-bubbles-hero {
                gap: 1.25rem;
            }
            .stage-bubble-hero {
                flex: 0 1 170px;
            }
            .stage-bubble-hero--early.stage-bubble-hero--large {
                flex: 0 1 210px;
            }
            .stage-bubble-hero__percent {
                font-size: 2.5rem;
            }
            .stage-bubble-hero--large .stage-bubble-hero__percent {
                font-size: 3rem;
            }
        }
        
        .intel-panel {
            background: linear-gradient(135deg, rgba(255,255,255,0.9), rgba(255,255,255,0.72));
            border: 1px solid var(--border);
            border-radius: var(--radius);
            box-shadow: var(--shadow);
            padding: 1.2rem 1.3rem 1.35rem;
            margin-bottom: 1.5rem;
            backdrop-filter: blur(14px);
        }
        .intel-panel h3 {
            margin: 0 0 0.35rem;
            font-size: 1rem;
            font-weight: 700;
            font-family: 'DM Sans', sans-serif;
            color: var(--text);
        }
        .intel-panel .intel-hint {
            font-size: 0.8rem;
            color: var(--text-muted);
            margin: 0 0 1rem;
            line-height: 1.45;
        }
        .heat-legend {
            display: flex;
            align-items: center;
            gap: 0.5rem;
            font-size: 0.72rem;
            color: var(--text-muted);
            margin-bottom: 0.65rem;
        }
        .heat-legend span { display: inline-flex; align-items: center; gap: 0.25rem; }
        .heat-legend i { width: 10px; height: 10px; border-radius: 2px; display: inline-block; }
        .heat-strip {
            display: flex;
            flex-wrap: wrap;
            gap: 3px;
            align-items: flex-end;
        }
        .heat-cell {
            width: 10px;
            height: 28px;
            border-radius: 3px;
            background: #e2e8f0;
            flex-shrink: 0;
            transition: transform 0.15s ease;
        }
        .heat-cell:hover { transform: scaleY(1.08); outline: 1px solid #94a3b8; }
        .velocity-row {
            display: flex;
            flex-wrap: wrap;
            gap: 1rem;
            margin-top: 1rem;
            padding-top: 1rem;
            border-top: 1px solid #f1f5f9;
        }
        .velocity-pill {
            flex: 1 1 12rem;
            background: rgba(248, 250, 252, 0.82);
            border-radius: 16px;
            padding: 0.95rem 1rem;
            border: 1px solid rgba(255,255,255,0.82);
            box-shadow: inset 0 1px 0 rgba(255,255,255,0.75);
        }
        .velocity-pill strong { display: block; font-size: 0.7rem; text-transform: uppercase; letter-spacing: 0.06em; color: var(--text-muted); margin-bottom: 0.25rem; }
        .velocity-pill .num { font-family: 'DM Sans', sans-serif; font-size: 1.35rem; font-weight: 700; color: var(--text); }
        .velocity-pill .meta { font-size: 0.78rem; color: var(--text-muted); margin-top: 0.2rem; }
        
        /* Two column hero (welcome + applications / stage mix) */
        .hero-three-col {
            display: grid;
            grid-template-columns: repeat(2, 1fr);
            gap: 0.85rem;
            margin-bottom: 1.5rem;
        }
        @media (max-width: 1600px) {
            .hero-three-col {
                gap: 0.75rem;
            }
        }
        @media (max-width: 1300px) {
            .hero-three-col {
                grid-template-columns: 1fr;
                gap: 1rem;
            }
            .hero-col {
                min-height: auto !important;
            }
        }
        .hero-col {
            background: var(--glass-strong);
            border: 1px solid var(--border);
            border-radius: 12px;
            padding: 0.95rem 1rem;
            box-shadow: var(--shadow);
            backdrop-filter: blur(14px);
            display: flex;
            flex-direction: column;
            min-height: 280px;
        }
        .hero-col--welcome {
            background: linear-gradient(135deg, rgba(255, 255, 255, 0.96) 0%, rgba(245, 243, 255, 0.9) 100%);
        }
        .hero-col--metrics {
            background: linear-gradient(135deg, rgba(255, 255, 255, 0.96) 0%, rgba(240, 249, 255, 0.9) 100%);
        }
        .hero-col__title {
            font-size: 0.6rem;
            font-weight: 800;
            text-transform: uppercase;
            letter-spacing: 0.08em;
            color: #7c3aed;
            margin-bottom: 0.75rem;
            display: flex;
            align-items: center;
            gap: 0.35rem;
        }
        .hero-col__title i {
            font-size: 0.65rem;
        }
        .hero-col__content {
            flex: 1;
        }
        
        /* Welcome Column Specific */
        .welcome-intro h2 {
            font-family: 'DM Sans', sans-serif;
            font-size: 1.15rem;
            font-weight: 800;
            color: #0f172a;
            margin: 0 0 0.5rem;
            line-height: 1.2;
        }
        .welcome-intro p {
            font-size: 0.7rem;
            color: #64748b;
            line-height: 1.5;
            margin-bottom: 0.85rem;
        }
        .welcome-intro strong {
            color: #0f172a;
            font-weight: 700;
        }
        .welcome-meta-pills {
            display: flex;
            flex-direction: column;
            gap: 0.5rem;
        }
        .welcome-meta-pill {
            display: flex;
            align-items: center;
            gap: 0.5rem;
            padding: 0.5rem 0.65rem;
            background: rgba(255, 255, 255, 0.6);
            border: 1px solid rgba(167, 139, 250, 0.2);
            border-radius: 8px;
            font-size: 0.65rem;
            color: #475569;
        }
        .welcome-meta-pill i {
            color: #7c3aed;
            font-size: 0.75rem;
        }
        
        /* Welcome Analytics */
        .welcome-analytics {
            margin-top: 1rem;
            padding-top: 1rem;
            border-top: 1px solid rgba(167, 139, 250, 0.15);
        }
        .welcome-analytics__title {
            font-size: 0.6rem;
            font-weight: 800;
            text-transform: uppercase;
            letter-spacing: 0.06em;
            color: #7c3aed;
            margin-bottom: 0.65rem;
        }
        .welcome-stats-grid {
            display: grid;
            grid-template-columns: 1fr 1fr;
            gap: 0.5rem;
        }
        .welcome-stat-card {
            background: rgba(255, 255, 255, 0.7);
            border: 1px solid rgba(167, 139, 250, 0.15);
            border-radius: 8px;
            padding: 0.6rem 0.75rem;
            transition: all 0.2s ease;
        }
        .welcome-stat-card:hover {
            transform: translateY(-1px);
            box-shadow: 0 4px 12px rgba(124, 58, 237, 0.1);
            border-color: rgba(167, 139, 250, 0.3);
        }
        .welcome-stat-card__label {
            font-size: 0.58rem;
            font-weight: 700;
            text-transform: uppercase;
            letter-spacing: 0.05em;
            color: #64748b;
            margin-bottom: 0.35rem;
        }
        .welcome-stat-card__value {
            font-family: 'DM Sans', sans-serif;
            font-size: 1.35rem;
            font-weight: 800;
            color: #0f172a;
            line-height: 1;
            margin-bottom: 0.25rem;
        }
        .welcome-stat-card__meta {
            font-size: 0.55rem;
            color: #94a3b8;
        }
        .welcome-stat-card--positive .welcome-stat-card__value {
            color: #059669;
        }
        .welcome-stat-card--negative .welcome-stat-card__value {
            color: #dc2626;
        }
        .welcome-stat-card--neutral .welcome-stat-card__value {
            color: #7c3aed;
        }
        
        /* District performance — curved glass panel */
        .district-curve-panel {
            position: relative;
            margin-bottom: 1.5rem;
            border-radius: 28px;
            overflow: hidden;
            background: linear-gradient(145deg,
                rgba(255, 255, 255, 0.95) 0%,
                rgba(224, 242, 254, 0.55) 38%,
                rgba(237, 233, 254, 0.65) 100%);
            border: 1px solid rgba(167, 139, 250, 0.22);
            box-shadow:
                0 18px 40px -18px rgba(15, 23, 42, 0.15),
                inset 0 1px 0 rgba(255, 255, 255, 0.85);
            backdrop-filter: blur(16px);
        }
        .district-curve-panel__waves {
            position: absolute;
            left: 0;
            right: 0;
            top: 0;
            height: 72px;
            pointer-events: none;
            opacity: 0.85;
        }
        .district-curve-panel__waves svg {
            width: 100%;
            height: 100%;
            display: block;
        }
        .district-curve-panel__body {
            position: relative;
            z-index: 1;
            padding: 1.15rem 1.35rem 1.35rem;
        }
        .district-curve-panel__head {
            display: flex;
            flex-wrap: wrap;
            align-items: flex-start;
            justify-content: space-between;
            gap: 1rem;
            margin-bottom: 1.1rem;
        }
        .district-curve-panel__eyebrow {
            font-size: 0.58rem;
            font-weight: 800;
            text-transform: uppercase;
            letter-spacing: 0.12em;
            color: #6366f1;
            margin: 0 0 0.35rem;
        }
        .district-curve-panel__title {
            font-family: 'DM Sans', sans-serif;
            font-size: 1.25rem;
            font-weight: 800;
            color: #0f172a;
            margin: 0;
            line-height: 1.2;
        }
        .district-curve-panel__sub {
            margin: 0.35rem 0 0;
            font-size: 0.78rem;
            color: #64748b;
            max-width: 36rem;
            line-height: 1.45;
        }
        .district-curve-panel__badge {
            display: inline-flex;
            align-items: center;
            gap: 0.4rem;
            padding: 0.45rem 0.75rem;
            border-radius: 999px;
            font-size: 0.7rem;
            font-weight: 700;
            color: #3730a3;
            background: rgba(255, 255, 255, 0.82);
            border: 1px solid rgba(99, 102, 241, 0.28);
            box-shadow: 0 4px 14px rgba(79, 70, 229, 0.1);
        }
        .district-curve-panel__grid {
            display: grid;
            grid-template-columns: minmax(12rem, 1fr) minmax(14rem, 1.35fr) minmax(12rem, 1fr);
            gap: 1.1rem;
            align-items: stretch;
        }
        @media (max-width: 1200px) {
            .district-curve-panel__grid {
                grid-template-columns: 1fr 1fr;
            }
            .district-curve-panel__col--chart {
                grid-column: 1 / -1;
                min-height: 200px;
            }
        }
        @media (max-width: 700px) {
            .district-curve-panel__grid {
                grid-template-columns: 1fr;
            }
        }
        .district-curve-panel__col {
            background: rgba(255, 255, 255, 0.62);
            border-radius: 22px;
            border: 1px solid rgba(255, 255, 255, 0.9);
            padding: 1rem 1.05rem;
            box-shadow: inset 0 1px 0 rgba(255, 255, 255, 0.95);
        }
        .district-curve-panel__col--chart {
            min-height: 210px;
            display: flex;
            flex-direction: column;
        }
        .district-curve-panel__col--chart .district-chart-canvas-wrap {
            flex: 1;
            min-height: 170px;
            position: relative;
        }
        .district-ring-wrap {
            display: flex;
            flex-direction: column;
            align-items: center;
            justify-content: center;
            gap: 0.65rem;
            text-align: center;
            padding: 0.35rem 0;
        }
        .district-ring {
            width: 132px;
            height: 132px;
            transform: rotate(-90deg);
        }
        .district-ring__bg {
            fill: none;
            stroke: rgba(148, 163, 184, 0.25);
            stroke-width: 10;
        }
        .district-ring__progress {
            fill: none;
            stroke: url(#districtRingGrad);
            stroke-width: 10;
            stroke-linecap: round;
            stroke-dasharray: 339.292;
            transition: stroke-dashoffset 0.6s ease;
        }
        .district-ring-meta {
            margin-top: -0.25rem;
        }
        .district-ring-meta__big {
            font-family: 'DM Sans', sans-serif;
            font-size: 1.65rem;
            font-weight: 800;
            color: #0f172a;
            line-height: 1;
        }
        .district-ring-meta__small {
            font-size: 0.68rem;
            color: #64748b;
            margin-top: 0.3rem;
        }
        .district-chip-row {
            display: flex;
            flex-wrap: wrap;
            gap: 0.45rem;
            margin-top: 0.65rem;
            justify-content: center;
        }
        .district-chip {
            border-radius: 999px;
            padding: 0.3rem 0.55rem;
            font-size: 0.62rem;
            font-weight: 700;
            letter-spacing: 0.03em;
        }
        .district-chip--seed { background: rgba(250, 204, 21, 0.22); color: #a16207; }
        .district-chip--early { background: rgba(59, 130, 246, 0.18); color: #1d4ed8; }
        .district-chip--growth { background: rgba(16, 185, 129, 0.2); color: #047857; }
        .district-stage-bars {
            margin-top: 0.75rem;
            display: flex;
            flex-direction: column;
            gap: 0.5rem;
        }
        .district-stage-bar {
            display: grid;
            grid-template-columns: 4.5rem 1fr 2.2rem;
            align-items: center;
            gap: 0.45rem;
            font-size: 0.68rem;
        }
        .district-stage-bar__label {
            font-weight: 700;
            color: #475569;
            text-transform: uppercase;
            letter-spacing: 0.04em;
        }
        .district-stage-bar__track {
            height: 10px;
            border-radius: 999px;
            background: rgba(241, 245, 249, 0.95);
            overflow: hidden;
            border: 1px solid rgba(226, 232, 240, 0.9);
        }
        .district-stage-bar__fill {
            height: 100%;
            border-radius: inherit;
            background: linear-gradient(90deg, #818cf8, #34d399);
            transition: width 0.5s ease;
        }
        .district-stage-bar__fill--seed {
            background: linear-gradient(90deg, #facc15, #f97316);
        }
        .district-stage-bar__fill--early {
            background: linear-gradient(90deg, #60a5fa, #6366f1);
        }
        .district-stage-bar__fill--growth {
            background: linear-gradient(90deg, #34d399, #14b8a6);
        }
        .district-stage-bar__pct {
            text-align: right;
            font-weight: 800;
            color: #0f172a;
            font-variant-numeric: tabular-nums;
        }
        .district-compare-card {
            display: flex;
            flex-direction: column;
            gap: 0.65rem;
        }
        .district-compare-card__label {
            font-size: 0.58rem;
            font-weight: 800;
            text-transform: uppercase;
            letter-spacing: 0.08em;
            color: #64748b;
        }
        .district-compare-card__row {
            display: flex;
            justify-content: space-between;
            align-items: baseline;
            gap: 0.5rem;
            font-size: 0.8rem;
            color: #334155;
        }
        .district-compare-card__row strong {
            font-family: 'DM Sans', sans-serif;
            font-size: 1.05rem;
            color: #0f172a;
        }
        .district-footnote {
            font-size: 0.68rem;
            color: #64748b;
            line-height: 1.45;
            margin: 1rem 0 0;
            padding-top: 0.85rem;
            border-top: 1px dashed rgba(148, 163, 184, 0.45);
        }
        
        @media (max-width: 900px) {
            .dashboard-intro {
                padding: 1.35rem;
                border-radius: 26px;
            }
            .dashboard-highlight-card {
                width: 100%;
            }
        }
    </style>
    @if (isset($cfaTotal))
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.1/css/all.min.css" crossorigin="anonymous" referrerpolicy="no-referrer">
    <script src="https://cdn.jsdelivr.net/npm/chart.js@4.4.1/dist/chart.umd.min.js" crossorigin="anonymous"></script>
    @endif
</head>
<body class="admin-app-body admin-app-body--dashboard">
    @include('partials.admin-topbar')
    <main class="admin-main">

    @unless (isset($cfaTotal))
        <div class="admin-page-head">
            <h1>Welcome, {{ auth()->user()->name }}</h1>
            <p class="admin-page-meta"><span class="pill">District staff</span></p>
        </div>
        <div class="staff-info-card">
            <p>Target and staff administration is handled by the state admin. Use the referral link from your administrator to share the CFA application form with applicants.</p>
            <p>If you need access to admin screens, contact your state admin.</p>
            <form method="post" action="{{ route('logout') }}" class="admin-topbar__logout">
                @csrf
                <button type="submit">Log out</button>
            </form>
        </div>
    @else
        <div class="dashboard-shell">
        
        {{-- Two column hero --}}
        <div class="hero-three-col">
            {{-- Column 1: Welcome Section --}}
            <div class="hero-col hero-col--welcome">
                <div class="hero-col__title">
                    <i class="fa-solid fa-sparkles" aria-hidden="true"></i>
                    District Staff Cockpit
                </div>
                <div class="hero-col__content">
                    <div class="welcome-intro">
                        <h2>Welcome back, {{ $staff->name }}</h2>
                        <p>
                            Your CFA referral activity
                            @if ($staff->district?->name)
                                · <strong>{{ $staff->district->name }}</strong>
                            @endif
                            @if ($staff->hub?->name)
                                · {{ $staff->hub->name }}
                            @endif
                            @if ($staff->designationRecord?->name)
                                · {{ $staff->designationRecord->name }}
                            @endif
                        </p>
                        <div class="welcome-meta-pills">
                            <div class="welcome-meta-pill">
                                <i class="fa-regular fa-calendar" aria-hidden="true"></i>
                                <span><strong>Fiscal year:</strong> {{ $activeFy?->name ?? '—' }}</span>
                            </div>
                            @if ($districtCfaTarget !== null)
                                <div class="welcome-meta-pill">
                                    <i class="fa-solid fa-bullseye" aria-hidden="true"></i>
                                    <span><strong>District CFA target:</strong> {{ number_format($districtCfaTarget) }}</span>
                                </div>
                            @endif
                            <div class="welcome-meta-pill">
                                <i class="fa-solid fa-link" aria-hidden="true"></i>
                                <span><strong>Referral activity</strong> dashboard</span>
                            </div>
                        </div>
                        
                        {{-- Quick Analytics --}}
                        <div class="welcome-analytics">
                            <div class="welcome-analytics__title">
                                <i class="fa-solid fa-chart-simple" aria-hidden="true"></i> Quick Insights
                            </div>
                            <div class="welcome-stats-grid">
                                <div class="welcome-stat-card welcome-stat-card--neutral">
                                    <div class="welcome-stat-card__label">Last 7 Days</div>
                                    <div class="welcome-stat-card__value">{{ number_format($recent7 ?? 0) }}</div>
                                    <div class="welcome-stat-card__meta">Applications</div>
                                </div>
                                
                                <div class="welcome-stat-card welcome-stat-card--{{ ($velocityChangePct ?? 0) >= 0 ? 'positive' : 'negative' }}">
                                    <div class="welcome-stat-card__label">Velocity</div>
                                    <div class="welcome-stat-card__value">
                                        @if ($velocityChangePct !== null)
                                            {{ ($velocityChangePct ?? 0) >= 0 ? '+' : '' }}{{ $velocityChangePct }}%
                                        @else
                                            —
                                        @endif
                                    </div>
                                    <div class="welcome-stat-card__meta">Week over week</div>
                                </div>
                                
                                <div class="welcome-stat-card welcome-stat-card--neutral">
                                    <div class="welcome-stat-card__label">This Month</div>
                                    <div class="welcome-stat-card__value">{{ number_format($cfaThisMonth ?? 0) }}</div>
                                    <div class="welcome-stat-card__meta">Applications</div>
                                </div>
                                
                                <div class="welcome-stat-card welcome-stat-card--positive">
                                    <div class="welcome-stat-card__label">Active Streak</div>
                                    <div class="welcome-stat-card__value">{{ $submissionStreakDays ?? 0 }}</div>
                                    <div class="welcome-stat-card__meta">Days in a row</div>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
            
            {{-- Column 2: Total Applications + Stage Bifurcation --}}
            <div class="hero-col hero-col--metrics">
                @php
                    $stageTotals = ['SEED' => 0, 'EARLY' => 0, 'GROWTH' => 0];
                    $stageTargets = ['SEED' => 30, 'EARLY' => 60, 'GROWTH' => 10];
                    
                    foreach ($businessStageMix['labels'] as $idx => $label) {
                        $upperLabel = strtoupper(trim($label));
                        if (isset($stageTotals[$upperLabel])) {
                            $stageTotals[$upperLabel] = $businessStageMix['values'][$idx] ?? 0;
                        }
                    }
                    
                    $totalApps = array_sum($stageTotals);
                    $stagePercentages = [];
                    $stageGaps = [];
                    
                    foreach ($stageTotals as $stage => $count) {
                        $stagePercentages[$stage] = $totalApps > 0 ? round(($count / $totalApps) * 100) : 0;
                        $stageGaps[$stage] = $stagePercentages[$stage] - $stageTargets[$stage];
                    }
                @endphp
                
                <div class="hero-col__title">
                    <i class="fa-solid fa-chart-line" aria-hidden="true"></i>
                    Applications & Stage Mix
                </div>
                <div class="hero-col__content">
                    <div class="highlight-card__header">
                        <div class="label">Applications this FY</div>
                        <div class="label-time"><i class="fa-regular fa-clock"></i> {{ now()->setTimezone('Asia/Kolkata')->format('d M, h:i A') }} IST</div>
                    </div>
                    
                    <div class="apps-highlight">
                        <div class="apps-highlight__main">
                            <div class="apps-highlight__number">{{ number_format($cfaThisFy) }}</div>
                            <div class="apps-highlight__label">Total Applications</div>
                        </div>
                        @if ($staffAnnualTarget !== null && (int) $staffAnnualTarget > 0)
                            @php
                                $targetProgress = round(($cfaThisFy / $staffAnnualTarget) * 100);
                            @endphp
                            <div class="apps-highlight__progress">
                                <div class="progress-bar-wrapper">
                                    <div class="progress-bar-fill" style="width: {{ min($targetProgress, 100) }}%;">
                                        <span class="progress-bar-label">{{ $targetProgress }}%</span>
                                    </div>
                                </div>
                                <div class="progress-bar-meta">
                                    {{ number_format($cfaThisFy) }} of {{ number_format($staffAnnualTarget) }} target
                                </div>
                            </div>
                        @else
                            <div class="apps-highlight__sub">Keep sharing your form link this cycle.</div>
                        @endif
                    </div>
                    
                    {{-- Business Category Mix Pie Chart --}}
                    <div class="business-mix-compact">
                        <div class="business-mix-compact__header">
                            <div class="business-mix-compact__title">Business Categories</div>
                            <div class="business-mix-compact__meta">Category distribution</div>
                        </div>
                        <div class="business-mix-compact__chart">
                            @if (count($businessMix['labels']) === 0)
                                <div class="no-data-message">
                                    <i class="fa-solid fa-chart-pie"></i>
                                    <p>No category data yet</p>
                                </div>
                            @else
                                <canvas id="staffBusinessMixCompact" style="max-height: 180px;"></canvas>
                                <div class="business-mix-legend">
                                    @foreach ($businessMix['labels'] as $idx => $label)
                                        <div class="legend-item">
                                            <span class="legend-color" style="background-color: {{ $businessMix['colors'][$idx] ?? '#6366f1' }};"></span>
                                            <span class="legend-label">{{ $label }}</span>
                                            <span class="legend-value">{{ $businessMix['values'][$idx] ?? 0 }}</span>
                                        </div>
                                    @endforeach
                                </div>
                            @endif
                        </div>
                    </div>
                    
                    <div class="stage-mix-compact">
                        <div class="stage-mix-compact__title">
                            <span>STAGE MIX</span>
                            <span class="stage-mix-compact__target">Target {{ $stageTargets['EARLY'] }} / {{ $stageTargets['SEED'] }} / {{ $stageTargets['GROWTH'] }}</span>
                        </div>
                        
                        {{-- SVG Connecting Arrows --}}
                        <svg class="stage-arrows" viewBox="0 0 400 100" xmlns="http://www.w3.org/2000/svg">
                            <defs>
                                <linearGradient id="arrowGradient1" x1="0%" y1="0%" x2="100%" y2="0%">
                                    <stop offset="0%" style="stop-color:#a78bfa;stop-opacity:0.6" />
                                    <stop offset="100%" style="stop-color:#fbbf24;stop-opacity:0.8" />
                                </linearGradient>
                                <linearGradient id="arrowGradient2" x1="0%" y1="0%" x2="100%" y2="0%">
                                    <stop offset="0%" style="stop-color:#a78bfa;stop-opacity:0.6" />
                                    <stop offset="100%" style="stop-color:#60a5fa;stop-opacity:0.8" />
                                </linearGradient>
                                <linearGradient id="arrowGradient3" x1="0%" y1="0%" x2="100%" y2="0%">
                                    <stop offset="0%" style="stop-color:#a78bfa;stop-opacity:0.6" />
                                    <stop offset="100%" style="stop-color:#34d399;stop-opacity:0.8" />
                                </linearGradient>
                            </defs>
                            
                            {{-- Arrow to SEED (left) --}}
                            <path d="M 200 20 Q 100 40, 80 80" 
                                  stroke="url(#arrowGradient1)" 
                                  stroke-width="2.5" 
                                  fill="none" 
                                  stroke-dasharray="5,3"
                                  opacity="0.7"/>
                            
                            {{-- Arrow to EARLY (center) --}}
                            <path d="M 200 20 L 200 80" 
                                  stroke="url(#arrowGradient2)" 
                                  stroke-width="3" 
                                  fill="none" 
                                  stroke-dasharray="5,3"
                                  opacity="0.7"/>
                            
                            {{-- Arrow to GROWTH (right) --}}
                            <path d="M 200 20 Q 300 40, 320 80" 
                                  stroke="url(#arrowGradient3)" 
                                  stroke-width="2.5" 
                                  fill="none" 
                                  stroke-dasharray="5,3"
                                  opacity="0.7"/>
                        </svg>
                        
                        <div class="stage-pills-grid">
                            <div class="stage-pill-compact stage-pill-compact--seed">
                                <div class="stage-pill-compact__label">SEED</div>
                                <div class="stage-pill-compact__value">{{ $stagePercentages['SEED'] }}%</div>
                                <div class="stage-pill-compact__meta">Target {{ $stageTargets['SEED'] }}% · {{ $stageTotals['SEED'] }}</div>
                                <div class="stage-pill-compact__gap stage-pill-compact__gap--{{ $stageGaps['SEED'] >= 0 ? 'positive' : 'negative' }}">
                                    Gap {{ $stageGaps['SEED'] > 0 ? '+' : '' }}{{ $stageGaps['SEED'] }}%
                                </div>
                            </div>
                            
                            <div class="stage-pill-compact stage-pill-compact--early stage-pill-compact--large">
                                <div class="stage-pill-compact__label">EARLY</div>
                                <div class="stage-pill-compact__value">{{ $stagePercentages['EARLY'] }}%</div>
                                <div class="stage-pill-compact__meta">Target {{ $stageTargets['EARLY'] }}% · {{ $stageTotals['EARLY'] }}</div>
                                <div class="stage-pill-compact__gap stage-pill-compact__gap--{{ $stageGaps['EARLY'] >= 0 ? 'positive' : 'negative' }}">
                                    Gap {{ $stageGaps['EARLY'] > 0 ? '+' : '' }}{{ $stageGaps['EARLY'] }}%
                                </div>
                            </div>
                            
                            <div class="stage-pill-compact stage-pill-compact--growth">
                                <div class="stage-pill-compact__label">GROWTH</div>
                                <div class="stage-pill-compact__value">{{ $stagePercentages['GROWTH'] }}%</div>
                                <div class="stage-pill-compact__meta">Target {{ $stageTargets['GROWTH'] }}% · {{ $stageTotals['GROWTH'] }}</div>
                                <div class="stage-pill-compact__gap stage-pill-compact__gap--{{ $stageGaps['GROWTH'] >= 0 ? 'positive' : 'negative' }}">
                                    Gap {{ $stageGaps['GROWTH'] > 0 ? '+' : '' }}{{ $stageGaps['GROWTH'] }}%
                                </div>
                            </div>
                        </div>
                        
                        <div class="stage-insight-compact">
                            @php
                                $mostDeficient = null;
                                $maxDeficit = 0;
                                foreach ($stageGaps as $stage => $gap) {
                                    if ($gap < $maxDeficit) {
                                        $maxDeficit = $gap;
                                        $mostDeficient = $stage;
                                    }
                                }
                            @endphp
                            @if ($totalApps === 0)
                                Start collecting applications to see stage distribution.
                            @elseif ($mostDeficient)
                                <strong>{{ ucfirst(strtolower($mostDeficient)) }}</strong> is short by {{ abs($stageGaps[$mostDeficient]) }}%.
                            @endif
                            Maintain mix close to <strong>Early {{ $stageTargets['EARLY'] }}%</strong> · <strong>Seed {{ $stageTargets['SEED'] }}%</strong> · <strong>Growth {{ $stageTargets['GROWTH'] }}%</strong>.
                        </div>
                    </div>
                </div>
            </div>
        </div>

        @if ($staff->district_id && $activeFy)
            @php
                $dStageTotals = ['SEED' => 0, 'EARLY' => 0, 'GROWTH' => 0];
                foreach ($districtBusinessStageMix['labels'] ?? [] as $dIdx => $dLabel) {
                    $u = strtoupper(trim((string) $dLabel));
                    if (isset($dStageTotals[$u])) {
                        $dStageTotals[$u] = (int) ($districtBusinessStageMix['values'][$dIdx] ?? 0);
                    }
                }
                $dStageSum = array_sum($dStageTotals);
                $dStagePct = [
                    'SEED' => $dStageSum > 0 ? (int) round(($dStageTotals['SEED'] / $dStageSum) * 100) : 0,
                    'EARLY' => $dStageSum > 0 ? (int) round(($dStageTotals['EARLY'] / $dStageSum) * 100) : 0,
                    'GROWTH' => $dStageSum > 0 ? (int) round(($dStageTotals['GROWTH'] / $dStageSum) * 100) : 0,
                ];
                $ringCirc = 339.292;
                $ringPct = $districtProgressPct !== null ? (int) min(100, max(0, $districtProgressPct)) : null;
                $ringOffset = $ringPct !== null ? $ringCirc * (1 - $ringPct / 100) : $ringCirc;
            @endphp
            <section class="district-curve-panel" aria-labelledby="district-performance-title">
                <div class="district-curve-panel__waves" aria-hidden="true">
                    <svg viewBox="0 0 1440 48" preserveAspectRatio="none" xmlns="http://www.w3.org/2000/svg">
                        <defs>
                            <linearGradient id="districtWaveGrad" x1="0%" y1="0%" x2="100%" y2="0%">
                                <stop offset="0%" style="stop-color:#818cf8;stop-opacity:0.35"/>
                                <stop offset="50%" style="stop-color:#22d3ee;stop-opacity:0.28"/>
                                <stop offset="100%" style="stop-color:#34d399;stop-opacity:0.3"/>
                            </linearGradient>
                        </defs>
                        <path fill="url(#districtWaveGrad)" d="M0,32 C180,8 360,42 540,22 C720,2 900,38 1080,24 C1260,10 1380,28 1440,18 L1440,0 L0,0 Z"/>
                    </svg>
                </div>
                <div class="district-curve-panel__body">
                    <div class="district-curve-panel__head">
                        <div>
                            <p class="district-curve-panel__eyebrow"><i class="fa-solid fa-landmark" aria-hidden="true"></i> District pulse</p>
                            <h2 id="district-performance-title" class="district-curve-panel__title">Overall district performance</h2>
                            <p class="district-curve-panel__sub">
                                All CFA applications recorded in <strong>{{ $staff->district?->name ?? 'your district' }}</strong> for
                                <strong>{{ $activeFy->name }}</strong> — including every referral and channel — so you can situate your own numbers in the wider picture.
                            </p>
                        </div>
                        <span class="district-curve-panel__badge">
                            <i class="fa-solid fa-chart-area" aria-hidden="true"></i>
                            District scope · this FY
                        </span>
                    </div>
                    <div class="district-curve-panel__grid">
                        <div class="district-curve-panel__col">
                            <div class="district-ring-wrap">
                                <svg class="district-ring" viewBox="0 0 120 120" aria-hidden="true">
                                    <defs>
                                        <linearGradient id="districtRingGrad" x1="0%" y1="0%" x2="100%" y2="100%">
                                            <stop offset="0%" style="stop-color:#6366f1"/>
                                            <stop offset="100%" style="stop-color:#2dd4bf"/>
                                        </linearGradient>
                                    </defs>
                                    <circle class="district-ring__bg" cx="60" cy="60" r="54"/>
                                    <circle class="district-ring__progress" cx="60" cy="60" r="54"
                                        stroke-dasharray="{{ $ringCirc }}"
                                        style="stroke-dashoffset: {{ $ringOffset }}"/>
                                </svg>
                                <div class="district-ring-meta">
                                    @if ($districtProgressPct !== null)
                                        <div class="district-ring-meta__big">{{ $districtProgressPct }}%</div>
                                        <div class="district-ring-meta__small">of district CFA target</div>
                                    @else
                                        <div class="district-ring-meta__big">—</div>
                                        <div class="district-ring-meta__small">Set district target to track %</div>
                                    @endif
                                    <div class="district-chip-row">
                                        <span class="district-chip district-chip--seed">Seed {{ $dStagePct['SEED'] }}%</span>
                                        <span class="district-chip district-chip--early">Early {{ $dStagePct['EARLY'] }}%</span>
                                        <span class="district-chip district-chip--growth">Growth {{ $dStagePct['GROWTH'] }}%</span>
                                    </div>
                                </div>
                            </div>
                        </div>
                        <div class="district-curve-panel__col district-curve-panel__col--chart">
                            <div style="display:flex;justify-content:space-between;align-items:baseline;margin-bottom:0.35rem;gap:0.5rem;flex-wrap:wrap;">
                                <span class="district-compare-card__label" style="margin:0;">14-day intake curve (district)</span>
                                <span style="font-size:0.72rem;color:#64748b;">Rolling daily CFA in {{ $staff->district?->name ?? 'district' }}</span>
                            </div>
                            <div class="district-chart-canvas-wrap">
                                <canvas id="districtTrendCurveChart" aria-label="District CFA applications per day, last 14 days"></canvas>
                            </div>
                        </div>
                        <div class="district-curve-panel__col">
                            <div class="district-compare-card">
                                <span class="district-compare-card__label">District totals</span>
                                <div class="district-compare-card__row">
                                    <span>Applications this FY</span>
                                    <strong>{{ number_format((int) ($districtCfaThisFy ?? 0)) }}</strong>
                                </div>
                                @if ($districtCfaTarget !== null)
                                    <div class="district-compare-card__row">
                                        <span>District CFA target</span>
                                        <strong>{{ number_format((int) $districtCfaTarget) }}</strong>
                                    </div>
                                @endif
                                <div class="district-compare-card__row">
                                    <span>Your referrals this FY</span>
                                    <strong>{{ number_format($cfaThisFy) }}</strong>
                                </div>
                                <div class="district-compare-card__row">
                                    <span>Your share of district CFA</span>
                                    <strong>
                                        @if ($staffShareOfDistrictPct !== null)
                                            {{ $staffShareOfDistrictPct }}%
                                        @else
                                            —
                                        @endif
                                    </strong>
                                </div>
                            </div>
                            <div class="district-stage-bars" aria-label="District stage mix">
                                <span class="district-compare-card__label" style="margin-top:0.15rem;">Stage mix (district)</span>
                                <div class="district-stage-bar">
                                    <span class="district-stage-bar__label">Seed</span>
                                    <div class="district-stage-bar__track">
                                        <div class="district-stage-bar__fill district-stage-bar__fill--seed" style="width: {{ $dStagePct['SEED'] }}%;"></div>
                                    </div>
                                    <span class="district-stage-bar__pct">{{ $dStagePct['SEED'] }}%</span>
                                </div>
                                <div class="district-stage-bar">
                                    <span class="district-stage-bar__label">Early</span>
                                    <div class="district-stage-bar__track">
                                        <div class="district-stage-bar__fill district-stage-bar__fill--early" style="width: {{ $dStagePct['EARLY'] }}%;"></div>
                                    </div>
                                    <span class="district-stage-bar__pct">{{ $dStagePct['EARLY'] }}%</span>
                                </div>
                                <div class="district-stage-bar">
                                    <span class="district-stage-bar__label">Growth</span>
                                    <div class="district-stage-bar__track">
                                        <div class="district-stage-bar__fill district-stage-bar__fill--growth" style="width: {{ $dStagePct['GROWTH'] }}%;"></div>
                                    </div>
                                    <span class="district-stage-bar__pct">{{ $dStagePct['GROWTH'] }}%</span>
                                </div>
                            </div>
                        </div>
                    </div>
                    <p class="district-footnote">
                        District totals count every CFA tied to {{ $staff->district?->name ?? 'this district' }} in {{ $activeFy->name }}.
                        Your column shows only applications submitted through your referral link. District stage mix scans up to the 2,000
                        most recent district applications with saved form data (capped for performance).
                    </p>
                </div>
            </section>
        @endif

        <div class="dashboard-intro" style="display: none;">
            {{-- Old code hidden - keeping for reference --}}
            <div class="dashboard-intro__content">
                <div class="dashboard-intro__eyebrow">
                    <i class="fa-solid fa-sparkles" aria-hidden="true"></i>
                    District staff cockpit
                </div>
                <h2>Welcome back, {{ $staff->name }}</h2>
                <p>
                    Your CFA referral activity
                    @if ($staff->district?->name)
                        · <strong>{{ $staff->district->name }}</strong>
                    @endif
                    @if ($staff->hub?->name)
                        · {{ $staff->hub->name }}
                    @endif
                    @if ($staff->designationRecord?->name)
                        · {{ $staff->designationRecord->name }}
                    @endif
                    <br>
                    Fiscal year: <strong>{{ $activeFy?->name ?? '—' }}</strong>
                    @if ($districtCfaTarget !== null)
                        · District CFA target: <strong>{{ number_format($districtCfaTarget) }}</strong>
                    @endif
                </p>
                <div class="dashboard-meta-row">
                    <span class="dashboard-meta-pill">
                        <i class="fa-regular fa-calendar" aria-hidden="true"></i>
                        FY {{ $activeFy?->name ?? '—' }}
                    </span>
                    @if ($districtCfaTarget !== null)
                        <span class="dashboard-meta-pill">
                            <i class="fa-solid fa-bullseye" aria-hidden="true"></i>
                            District target {{ number_format($districtCfaTarget) }}
                        </span>
                    @endif
                    <span class="dashboard-meta-pill">
                        <i class="fa-solid fa-link" aria-hidden="true"></i>
                        Referral activity dashboard
                    </span>
                </div>
            </div>
            <aside class="dashboard-highlight-card" aria-label="Quick summary">
                @php
                    $stageTotals = ['SEED' => 0, 'EARLY' => 0, 'GROWTH' => 0];
                    $stageTargets = ['SEED' => 30, 'EARLY' => 60, 'GROWTH' => 10];
                    
                    foreach ($businessStageMix['labels'] as $idx => $label) {
                        $upperLabel = strtoupper(trim($label));
                        if (isset($stageTotals[$upperLabel])) {
                            $stageTotals[$upperLabel] = $businessStageMix['values'][$idx] ?? 0;
                        }
                    }
                    
                    $totalApps = array_sum($stageTotals);
                    $stagePercentages = [];
                    $stageGaps = [];
                    
                    foreach ($stageTotals as $stage => $count) {
                        $stagePercentages[$stage] = $totalApps > 0 ? round(($count / $totalApps) * 100) : 0;
                        $stageGaps[$stage] = $stagePercentages[$stage] - $stageTargets[$stage];
                    }
                @endphp
                
                <div class="highlight-card__header">
                    <div class="label">Applications this FY</div>
                    <div class="label-time"><i class="fa-regular fa-clock"></i> {{ now()->setTimezone('Asia/Kolkata')->format('d M, h:i A') }} IST</div>
                </div>
                
                <div class="value">{{ number_format($cfaThisFy) }}</div>
                <div class="sub">Keep sharing your form link this cycle.</div>
                
                {{-- Mini bar chart visualization --}}
                <div class="mini-chart">
                    @for($i = 0; $i < 7; $i++)
                        @php
                            $height = $totalApps > 0 ? rand(20, 60) : rand(10, 30);
                        @endphp
                        <div class="mini-bar" style="height: {{ $height }}px;"></div>
                    @endfor
                </div>
                
                <div class="stage-mix-compact">
                    <div class="stage-mix-compact__title">
                        <span>STAGE MIX</span>
                        <span class="stage-mix-compact__target">Target {{ $stageTargets['EARLY'] }} / {{ $stageTargets['SEED'] }} / {{ $stageTargets['GROWTH'] }}</span>
                    </div>
                    
                    {{-- SVG Connecting Arrows --}}
                    <svg class="stage-arrows" viewBox="0 0 400 100" xmlns="http://www.w3.org/2000/svg">
                        <defs>
                            <linearGradient id="arrowGradient1" x1="0%" y1="0%" x2="100%" y2="0%">
                                <stop offset="0%" style="stop-color:#a78bfa;stop-opacity:0.6" />
                                <stop offset="100%" style="stop-color:#fbbf24;stop-opacity:0.8" />
                            </linearGradient>
                            <linearGradient id="arrowGradient2" x1="0%" y1="0%" x2="100%" y2="0%">
                                <stop offset="0%" style="stop-color:#a78bfa;stop-opacity:0.6" />
                                <stop offset="100%" style="stop-color:#60a5fa;stop-opacity:0.8" />
                            </linearGradient>
                            <linearGradient id="arrowGradient3" x1="0%" y1="0%" x2="100%" y2="0%">
                                <stop offset="0%" style="stop-color:#a78bfa;stop-opacity:0.6" />
                                <stop offset="100%" style="stop-color:#34d399;stop-opacity:0.8" />
                            </linearGradient>
                        </defs>
                        
                        {{-- Arrow to SEED (left) --}}
                        <path d="M 200 20 Q 100 40, 80 80" 
                              stroke="url(#arrowGradient1)" 
                              stroke-width="2.5" 
                              fill="none" 
                              stroke-dasharray="5,3"
                              opacity="0.7"/>
                        
                        {{-- Arrow to EARLY (center) --}}
                        <path d="M 200 20 L 200 80" 
                              stroke="url(#arrowGradient2)" 
                              stroke-width="3" 
                              fill="none" 
                              stroke-dasharray="5,3"
                              opacity="0.7"/>
                        
                        {{-- Arrow to GROWTH (right) --}}
                        <path d="M 200 20 Q 300 40, 320 80" 
                              stroke="url(#arrowGradient3)" 
                              stroke-width="2.5" 
                              fill="none" 
                              stroke-dasharray="5,3"
                              opacity="0.7"/>
                    </svg>
                    
                    <div class="stage-pills-grid">
                        <div class="stage-pill-compact stage-pill-compact--seed">
                            <div class="stage-pill-compact__label">SEED</div>
                            <div class="stage-pill-compact__value">{{ $stagePercentages['SEED'] }}%</div>
                            <div class="stage-pill-compact__meta">Target {{ $stageTargets['SEED'] }}% · {{ $stageTotals['SEED'] }}</div>
                            <div class="stage-pill-compact__gap stage-pill-compact__gap--{{ $stageGaps['SEED'] >= 0 ? 'positive' : 'negative' }}">
                                Gap {{ $stageGaps['SEED'] > 0 ? '+' : '' }}{{ $stageGaps['SEED'] }}%
                            </div>
                        </div>
                        
                        <div class="stage-pill-compact stage-pill-compact--early stage-pill-compact--large">
                            <div class="stage-pill-compact__label">EARLY</div>
                            <div class="stage-pill-compact__value">{{ $stagePercentages['EARLY'] }}%</div>
                            <div class="stage-pill-compact__meta">Target {{ $stageTargets['EARLY'] }}% · {{ $stageTotals['EARLY'] }}</div>
                            <div class="stage-pill-compact__gap stage-pill-compact__gap--{{ $stageGaps['EARLY'] >= 0 ? 'positive' : 'negative' }}">
                                Gap {{ $stageGaps['EARLY'] > 0 ? '+' : '' }}{{ $stageGaps['EARLY'] }}%
                            </div>
                        </div>
                        
                        <div class="stage-pill-compact stage-pill-compact--growth">
                            <div class="stage-pill-compact__label">GROWTH</div>
                            <div class="stage-pill-compact__value">{{ $stagePercentages['GROWTH'] }}%</div>
                            <div class="stage-pill-compact__meta">Target {{ $stageTargets['GROWTH'] }}% · {{ $stageTotals['GROWTH'] }}</div>
                            <div class="stage-pill-compact__gap stage-pill-compact__gap--{{ $stageGaps['GROWTH'] >= 0 ? 'positive' : 'negative' }}">
                                Gap {{ $stageGaps['GROWTH'] > 0 ? '+' : '' }}{{ $stageGaps['GROWTH'] }}%
                            </div>
                        </div>
                    </div>
                    
                    <div class="stage-insight-compact">
                        @php
                            $mostDeficient = null;
                            $maxDeficit = 0;
                            foreach ($stageGaps as $stage => $gap) {
                                if ($gap < $maxDeficit) {
                                    $maxDeficit = $gap;
                                    $mostDeficient = $stage;
                                }
                            }
                        @endphp
                        @if ($totalApps === 0)
                            Start collecting applications to see stage distribution.
                        @elseif ($mostDeficient)
                            <strong>{{ ucfirst(strtolower($mostDeficient)) }}</strong> is short by {{ abs($stageGaps[$mostDeficient]) }}%.
                        @endif
                        Maintain mix close to <strong>Early {{ $stageTargets['EARLY'] }}%</strong> · <strong>Seed {{ $stageTargets['SEED'] }}%</strong> · <strong>Growth {{ $stageTargets['GROWTH'] }}%</strong>.
                    </div>
                </div>
            </aside>
        </div>

        {{-- Old sections - hidden for now --}}
        <div style="display: none;">
        {{-- Insight Cards Grid - Moved up for better visual flow --}}
        <div class="insight-grid" aria-label="District insights">
            <div class="insight-card insight-card--indigo">
                <div class="insight-label"><i class="fa-solid fa-bullseye" aria-hidden="true"></i> Target progress</div>
                @if ($overallTargetPct !== null)
                    <div class="insight-val">{{ $overallTargetPct }}%</div>
                    <div class="insight-sub">CFA this FY vs your annual allocation</div>
                @else
                    <div class="insight-val">—</div>
                    <div class="insight-sub">Set monthly targets to track %</div>
                @endif
            </div>
            <div class="insight-card insight-card--teal">
                <div class="insight-label"><i class="fa-solid fa-gauge-high" aria-hidden="true"></i> Performance score</div>
                @if ($performanceScore !== null)
                    <div class="insight-val">{{ $performanceScore }}</div>
                    <div class="insight-sub">Blend of target pace, registration &amp; training (sample)</div>
                @else
                    <div class="insight-val">—</div>
                    <div class="insight-sub">Needs annual CFA target</div>
                @endif
            </div>
            <div class="insight-card insight-card--amber">
                <div class="insight-label"><i class="fa-solid fa-id-card" aria-hidden="true"></i> Registered (sample)</div>
                @if ($registrationRate !== null)
                    <div class="insight-val">{{ (int) $registrationRate }}%</div>
                    <div class="insight-sub">Yes among Yes/No answers in recent forms</div>
                @else
                    <div class="insight-val">—</div>
                    <div class="insight-sub">No registration data yet</div>
                @endif
            </div>
            <div class="insight-card insight-card--rose">
                <div class="insight-label"><i class="fa-solid fa-graduation-cap" aria-hidden="true"></i> Training (sample)</div>
                @if ($trainingRate !== null)
                    <div class="insight-val">{{ (int) $trainingRate }}%</div>
                    <div class="insight-sub">Yes among Yes/No answers in recent forms</div>
                @else
                    <div class="insight-val">—</div>
                    <div class="insight-sub">No Yes/No training data in sample yet</div>
                @endif
            </div>
        </div>
        </div>

        <nav class="staff-portal-links" aria-label="Quick pages">
            <a href="{{ route('staff.monthly-targets') }}" class="staff-portal-link">
                <i class="fa-solid fa-chart-column" aria-hidden="true"></i>
                Monthly targets
            </a>
            <a href="{{ route('staff.applications') }}" class="staff-portal-link">
                <i class="fa-solid fa-file-lines" aria-hidden="true"></i>
                Application list
            </a>
        </nav>

        @php
            $heatMax = max(1, collect($heatmap30 ?? [])->max('count') ?: 0);
        @endphp

        <div class="intel-panel">
            <h3><i class="fa-solid fa-fire-flame-curved" style="color:#ea580c;margin-right:0.35rem;" aria-hidden="true"></i> Activity &amp; pace</h3>
            <p class="intel-hint">Last 30 days: one block per day (darker = more applications). Velocity compares the last 7 days to the previous 7.</p>
            <div class="heat-legend" aria-hidden="true">
                <span><i style="background:#e2e8f0;"></i> 0</span>
                <span><i style="background:#c7d2fe;"></i> Low</span>
                <span><i style="background:#6366f1;"></i> Mid</span>
                <span><i style="background:#312e81;"></i> High</span>
            </div>
            <div class="heat-strip" role="img" aria-label="30 day submission heatmap">
                @foreach ($heatmap30 ?? [] as $cell)
                    @php
                        $c = (int) ($cell['count'] ?? 0);
                        $intensity = $heatMax > 0 ? $c / $heatMax : 0;
                        if ($c === 0) {
                            $bg = '#e2e8f0';
                        } elseif ($intensity < 0.34) {
                            $bg = '#c7d2fe';
                        } elseif ($intensity < 0.67) {
                            $bg = '#6366f1';
                        } else {
                            $bg = '#312e81';
                        }
                    @endphp
                    <span class="heat-cell" style="background: {{ $bg }};" title="{{ ($cell['date'] ?? '') }}: {{ $c }} application(s)"></span>
                @endforeach
            </div>
            <div class="velocity-row">
                <div class="velocity-pill">
                    <strong>Last 7 days</strong>
                    <div class="num">{{ number_format($recent7 ?? 0) }}</div>
                    <div class="meta">Applications via your link</div>
                </div>
                <div class="velocity-pill">
                    <strong>Previous 7 days</strong>
                    <div class="num">{{ number_format($recent7Prev ?? 0) }}</div>
                    <div class="meta">Baseline for comparison</div>
                </div>
                <div class="velocity-pill">
                    <strong>Velocity change</strong>
                    @if ($velocityChangePct !== null)
                        <div class="num" style="color: {{ ($velocityChangePct ?? 0) >= 0 ? '#059669' : '#dc2626' }};">
                            {{ ($velocityChangePct ?? 0) >= 0 ? '+' : '' }}{{ $velocityChangePct }}%
                        </div>
                    @else
                        <div class="num">—</div>
                    @endif
                    <div class="meta">Week over week (same referral scope)</div>
                </div>
                <div class="velocity-pill">
                    <strong>Active days streak</strong>
                    <div class="num">{{ number_format($submissionStreakDays ?? 0) }}</div>
                    <div class="meta">Consecutive days (to today) with ≥1 application</div>
                </div>
                @if (($daysToTargetAtPace ?? null) !== null && $staffAnnualTarget !== null && (int) $staffAnnualTarget > 0)
                    <div class="velocity-pill">
                        <strong>ETA at current pace</strong>
                        <div class="num">@if ((int) $daysToTargetAtPace === 0) Done @else ~{{ number_format((int) $daysToTargetAtPace) }}d @endif</div>
                        <div class="meta">Days to hit annual target if daily average holds (FY to date)</div>
                    </div>
                @endif
            </div>
        </div>

        <div class="kpi-grid">
            <div class="kpi">
                <div class="label">Via your referral link</div>
                <div class="val">{{ number_format($cfaTotal) }}</div>
                <div class="sub">All time CFA applications</div>
            </div>
            <div class="kpi">
                <div class="label">This month</div>
                <div class="val">{{ number_format($cfaThisMonth) }}</div>
                <div class="sub">Applications submitted</div>
            </div>
            <div class="kpi">
                <div class="label">Last 30 days</div>
                <div class="val">{{ number_format($cfaLast30) }}</div>
                <div class="sub">Rolling window</div>
            </div>
            <div class="kpi">
                <div class="label">This fiscal year</div>
                <div class="val">{{ number_format($cfaThisFy) }}</div>
                <div class="sub">Against your annual CFA allocation</div>
            </div>
        </div>

        @if ($referralUrl)
            <div class="referral-card">
                <h3>Share the application form</h3>
                <p>Applicants open this link to fill the Call For Application. Only submissions through your link are counted on your dashboard.</p>
                <div class="referral-row">
                    <input type="text" readonly value="{{ $referralUrl }}" id="staffReferralUrl" aria-label="Referral URL">
                    <button type="button" id="staffCopyReferral">Copy link</button>
                </div>
            </div>
        @else
            <div class="warn-banner">
                <strong>No referral link on your account.</strong> Ask your state admin to save your staff profile so a referral token is generated, then refresh this page.
            </div>
        @endif

        <div class="target-banner">
            <div>
                <h3>Your CFA progress (this fiscal year)</h3>
                @if ($staffAnnualTarget !== null && $activeFy)
                    @if ((int) $staffAnnualTarget > 0)
                        <p class="big">{{ number_format($cfaThisFy) }} / {{ number_format($staffAnnualTarget) }}</p>
                        <p class="meta">Applications received via your referral vs your annual CFA allocation (sum of M1–M12). State admin sets monthly targets under Staff → CFA targets.</p>
                    @else
                        <p class="big">{{ number_format($cfaThisFy) }}</p>
                        <p class="meta">Your monthly CFA targets are not set yet (annual total is 0). {{ number_format($cfaThisFy) }} application(s) recorded this year via your link.</p>
                    @endif
                @elseif ($activeFy)
                    <p class="big">{{ number_format($cfaThisFy) }}</p>
                    <p class="meta">CFA deliverable or targets could not be loaded. Contact state admin if this persists.</p>
                @else
                    <p class="big">—</p>
                    <p class="meta">No active fiscal year — progress cannot be compared to targets yet.</p>
                @endif
            </div>
            @if ($staffAnnualTarget !== null && (int) $staffAnnualTarget > 0)
                @php $pct = min(100, round(($cfaThisFy / (int) $staffAnnualTarget) * 100)); @endphp
                <div class="progress-wrap">
                    <div style="display:flex;justify-content:space-between;font-size:0.85rem;opacity:0.9;">
                        <span>Received vs your target</span>
                        <span>{{ $pct }}%</span>
                    </div>
                    <div class="bar"><div class="fill" style="width: {{ $pct }}%"></div></div>
                </div>
            @endif
        </div>

        @if ($monthlyTargetsByMonth !== [])
            <div class="referral-card" style="background:linear-gradient(135deg, rgba(255,255,255,0.92), rgba(248,250,252,0.84));">
                <h3 style="margin-bottom:0.35rem;">Your monthly CFA allocation (M1–M12)</h3>
                <p style="margin-bottom:0;">Set by state admin. Sum should match your share of the district CFA target.</p>
                <div class="month-strip">
                    @foreach ($monthlyTargetsByMonth as $m => $n)
                        <div>
                            <span>M{{ $m }}</span>
                            <div class="cell">{{ number_format($n) }}</div>
                        </div>
                    @endforeach
                </div>
            </div>
        @endif

        <div class="charts-grid">
            <div class="chart-card tall">
                <h4>Applications (last 14 days)</h4>
                <p class="hint">Daily count for your referral link only</p>
                <div class="canvas-wrap"><canvas id="staffChartTrend"></canvas></div>
            </div>
            <div class="chart-card tall">
                <h4>Key Insights</h4>
                <p class="hint">Performance highlights from your referrals</p>
                <div class="insights-container">
                    {{-- Top Insight Cards --}}
                    <div class="insight-cards-compact">
                        <div class="insight-card-mini insight-card-mini--primary">
                            <div class="insight-card-mini__icon">
                                <i class="fa-solid fa-bullseye"></i>
                            </div>
                            <div class="insight-card-mini__content">
                                <div class="insight-card-mini__value">
                                    @if ($overallTargetPct !== null)
                                        {{ $overallTargetPct }}%
                                    @else
                                        —
                                    @endif
                                </div>
                                <div class="insight-card-mini__label">Target Progress</div>
                                <div class="insight-card-mini__meta">
                                    @if ($overallTargetPct !== null)
                                        vs annual allocation
                                    @else
                                        Set targets to track
                                    @endif
                                </div>
                            </div>
                        </div>
                        
                        <div class="insight-card-mini insight-card-mini--success">
                            <div class="insight-card-mini__icon">
                                <i class="fa-solid fa-chart-line"></i>
                            </div>
                            <div class="insight-card-mini__content">
                                <div class="insight-card-mini__value">
                                    @if ($velocityChangePct !== null)
                                        {{ ($velocityChangePct ?? 0) >= 0 ? '+' : '' }}{{ $velocityChangePct }}%
                                    @else
                                        —
                                    @endif
                                </div>
                                <div class="insight-card-mini__label">Velocity Change</div>
                                <div class="insight-card-mini__meta">Week over week growth</div>
                            </div>
                        </div>
                    </div>
                    
                    {{-- Stats Grid --}}
                    <div class="insight-stats-grid">
                        <div class="insight-stat-item">
                            <div class="insight-stat-item__icon" style="background: linear-gradient(135deg, #6366f1, #8b5cf6);">
                                <i class="fa-solid fa-id-card"></i>
                            </div>
                            <div class="insight-stat-item__content">
                                <div class="insight-stat-item__value">
                                    @if ($registrationRate !== null)
                                        {{ (int) $registrationRate }}%
                                    @else
                                        —
                                    @endif
                                </div>
                                <div class="insight-stat-item__label">Registered</div>
                                <div class="insight-stat-item__meta">Udyam registered</div>
                            </div>
                        </div>
                        
                        <div class="insight-stat-item">
                            <div class="insight-stat-item__icon" style="background: linear-gradient(135deg, #f59e0b, #d97706);">
                                <i class="fa-solid fa-graduation-cap"></i>
                            </div>
                            <div class="insight-stat-item__content">
                                <div class="insight-stat-item__value">
                                    @if ($trainingRate !== null)
                                        {{ (int) $trainingRate }}%
                                    @else
                                        —
                                    @endif
                                </div>
                                <div class="insight-stat-item__label">Trained</div>
                                <div class="insight-stat-item__meta">Received training</div>
                            </div>
                        </div>
                        
                        <div class="insight-stat-item">
                            <div class="insight-stat-item__icon" style="background: linear-gradient(135deg, #14b8a6, #0d9488);">
                                <i class="fa-solid fa-fire-flame-curved"></i>
                            </div>
                            <div class="insight-stat-item__content">
                                <div class="insight-stat-item__value">{{ $submissionStreakDays ?? 0 }}</div>
                                <div class="insight-stat-item__label">Day Streak</div>
                                <div class="insight-stat-item__meta">Active days in row</div>
                            </div>
                        </div>
                        
                        <div class="insight-stat-item">
                            <div class="insight-stat-item__icon" style="background: linear-gradient(135deg, #ec4899, #be185d);">
                                <i class="fa-solid fa-calendar-check"></i>
                            </div>
                            <div class="insight-stat-item__content">
                                <div class="insight-stat-item__value">{{ number_format($cfaThisMonth ?? 0) }}</div>
                                <div class="insight-stat-item__label">This Month</div>
                                <div class="insight-stat-item__meta">Applications</div>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </div>

        <p class="analytics-section-title">Analytics — your referrals</p>
        <p style="font-size:0.82rem;color:var(--text-muted);margin:-0.35rem 0 1rem;line-height:1.45;">Based on recent applications submitted through your link (same sample as category mix).</p>

        <div class="charts-grid charts-grid--3">
            <div class="chart-card tall">
                <h4>Applicant category</h4>
                <p class="hint">Individual / SHG / CBO</p>
                <div class="canvas-wrap">
                    @if (count($applicantCategoryMix['labels']) === 0)
                        <p style="display:flex;align-items:center;justify-content:center;height:100%;margin:0;color:var(--text-muted);font-size:0.9rem;text-align:center;padding:1rem;">No category data yet.</p>
                    @else
                        <canvas id="staffChartApplicantCategory"></canvas>
                    @endif
                </div>
            </div>
            <div class="chart-card tall">
                <h4>Gender</h4>
                <p class="hint">As declared on the form</p>
                <div class="canvas-wrap">
                    @if (count($genderMix['labels']) === 0)
                        <p style="display:flex;align-items:center;justify-content:center;height:100%;margin:0;color:var(--text-muted);font-size:0.9rem;text-align:center;padding:1rem;">No gender data yet.</p>
                    @else
                        <canvas id="staffChartGender"></canvas>
                    @endif
                </div>
            </div>
            <div class="chart-card tall">
                <h4>Business stage</h4>
                <p class="hint">Auto-computed (Seed / Early / Growth)</p>
                <div class="canvas-wrap">
                    @if (count($businessStageMix['labels']) === 0)
                        <p style="display:flex;align-items:center;justify-content:center;height:100%;margin:0;color:var(--text-muted);font-size:0.9rem;text-align:center;padding:1rem;">No stage data yet.</p>
                    @else
                        <canvas id="staffChartBusinessStage"></canvas>
                    @endif
                </div>
            </div>
        </div>

        <div class="charts-grid charts-grid--3" style="margin-top:0.35rem;">
            <div class="chart-card tall">
                <h4>Social category (caste)</h4>
                <p class="hint">GEN / EWS / OBC / SC / ST / …</p>
                <div class="canvas-wrap">
                    @if (count($casteMix['labels']) === 0)
                        <p style="display:flex;align-items:center;justify-content:center;height:100%;margin:0;color:var(--text-muted);font-size:0.9rem;text-align:center;padding:1rem;">No data yet.</p>
                    @else
                        <canvas id="staffChartCaste"></canvas>
                    @endif
                </div>
            </div>
            <div class="chart-card tall">
                <h4>Block</h4>
                <p class="hint">Vikas khand (top blocks + Other)</p>
                <div class="canvas-wrap">
                    @if (count($blockMix['labels']) === 0)
                        <p style="display:flex;align-items:center;justify-content:center;height:100%;margin:0;color:var(--text-muted);font-size:0.9rem;text-align:center;padding:1rem;">No data yet.</p>
                    @else
                        <canvas id="staffChartBlock"></canvas>
                    @endif
                </div>
            </div>
            <div class="chart-card tall">
                <h4>Training received</h4>
                <p class="hint">Entrepreneurship / self-employment training</p>
                <div class="canvas-wrap">
                    @if (count($trainingMix['labels']) === 0)
                        <p style="display:flex;align-items:center;justify-content:center;height:100%;margin:0;color:var(--text-muted);font-size:0.9rem;text-align:center;padding:1rem;">No data yet.</p>
                    @else
                        <canvas id="staffChartTraining"></canvas>
                    @endif
                </div>
            </div>
        </div>

        <div class="charts-grid charts-grid--2" style="margin-top:0;">
            <div class="chart-card tall">
                <h4>Education</h4>
                <p class="hint">Declared qualification (top groups)</p>
                <div class="canvas-wrap">
                    @if (count($educationMix['labels']) === 0)
                        <p style="display:flex;align-items:center;justify-content:center;height:100%;margin:0;color:var(--text-muted);font-size:0.9rem;text-align:center;padding:1rem;">No data yet.</p>
                    @else
                        <canvas id="staffChartEducation"></canvas>
                    @endif
                </div>
            </div>
            <div class="chart-card tall">
                <h4>Enterprise registered</h4>
                <p class="hint">Udyam registered (Yes / No)</p>
                <div class="canvas-wrap">
                    @if (count($registeredMix['labels']) === 0)
                        <p style="display:flex;align-items:center;justify-content:center;height:100%;margin:0;color:var(--text-muted);font-size:0.9rem;text-align:center;padding:1rem;">No data yet.</p>
                    @else
                        <canvas id="staffChartRegistered"></canvas>
                    @endif
                </div>
            </div>
        </div>

        <div class="recent-table-wrap">
            <h4>Recent applications</h4>
            <table>
                <thead>
                    <tr>
                        <th>App. no.</th>
                        <th>Date (IST)</th>
                        <th>Applicant</th>
                        <th>Phone</th>
                        <th>Actions</th>
                    </tr>
                </thead>
                <tbody>
                    @forelse ($recentSubmissions as $row)
                        <tr>
                            <td style="font-weight:600;">{{ $row->application_no ?? '—' }}</td>
                            <td style="color:#64748b;white-space:nowrap;">{{ $row->created_at?->format('Y-m-d H:i') }}</td>
                            <td>{{ $row->applicant_name }}</td>
                            <td style="color:#64748b;">{{ $row->phone }}</td>
                            <td style="white-space:nowrap;">
                                <a href="{{ route('staff.applications.show', $row) }}" style="display:inline-block;padding:0.25rem 0.5rem;background:#18181b;color:#fff;border-radius:6px;font-size:0.75rem;font-weight:600;text-decoration:none;margin-right:0.25rem;">View</a>
                                <a href="{{ route('staff.applications.edit', $row) }}" style="display:inline-block;padding:0.25rem 0.5rem;background:#4f46e5;color:#fff;border-radius:6px;font-size:0.75rem;font-weight:600;text-decoration:none;">Edit</a>
                            </td>
                        </tr>
                    @empty
                        <tr>
                            <td colspan="5" style="padding:1.25rem;color:#64748b;">No applications via your link yet. Share your referral URL with applicants.</td>
                        </tr>
                    @endforelse
                </tbody>
            </table>
        </div>

        <p class="staff-note">To change CFA monthly targets, designation, or account status, contact your <strong>state admin</strong>. Open <strong>Applications</strong> in the top bar to view, print, or edit submissions from your referral link.</p>
        </div>

        @php
            $chartColors = ['#4f46e5', '#0d9488', '#ea580c', '#7c3aed', '#0891b2', '#db2777', '#ca8a04', '#16a34a'];
            $barColors = ['#4f46e5', '#0d9488', '#ea580c', '#7c3aed', '#0891b2', '#db2777'];
        @endphp
        <script>
        (function () {
            const btn = document.getElementById('staffCopyReferral');
            const input = document.getElementById('staffReferralUrl');
            if (btn && input) {
                btn.addEventListener('click', function () {
                    const t = input.value;
                    if (navigator.clipboard && navigator.clipboard.writeText) {
                        navigator.clipboard.writeText(t).then(function () {
                            btn.textContent = 'Copied!';
                            btn.classList.add('copied');
                            setTimeout(function () { btn.textContent = 'Copy link'; btn.classList.remove('copied'); }, 2000);
                        });
                    } else {
                        input.select();
                        document.execCommand('copy');
                        btn.textContent = 'Copied!';
                        setTimeout(function () { btn.textContent = 'Copy link'; }, 2000);
                    }
                });
            }

            const accent = '#4f46e5';
            const grid = { color: 'rgba(148, 163, 184, 0.25)' };
            const barPal = @json($barColors);
            const trendLabels = @json($cfaTrend['labels']);
            const trendValues = @json($cfaTrend['values']);
            new Chart(document.getElementById('staffChartTrend'), {
                type: 'line',
                data: {
                    labels: trendLabels,
                    datasets: [{
                        label: 'Applications',
                        data: trendValues,
                        borderColor: accent,
                        backgroundColor: 'rgba(79, 70, 229, 0.12)',
                        fill: true,
                        tension: 0.35,
                        borderWidth: 2,
                        pointRadius: 3,
                        pointHoverRadius: 5
                    }]
                },
                options: {
                    responsive: true,
                    maintainAspectRatio: false,
                    plugins: { legend: { display: false } },
                    scales: {
                        x: { grid: { display: false }, ticks: { maxRotation: 45, minRotation: 0, font: { size: 10 } } },
                        y: { beginAtZero: true, grid: { color: grid.color }, ticks: { stepSize: 1 } }
                    }
                }
            });

            @if ($staff->district_id)
            const dTrendLabels = @json($districtCfaTrend['labels'] ?? []);
            const dTrendVals = @json($districtCfaTrend['values'] ?? []);
            const dChartEl = document.getElementById('districtTrendCurveChart');
            if (dChartEl && dTrendLabels.length) {
                new Chart(dChartEl, {
                    type: 'line',
                    data: {
                        labels: dTrendLabels,
                        datasets: [{
                            label: 'District CFA',
                            data: dTrendVals,
                            borderColor: 'rgba(13, 148, 136, 0.95)',
                            backgroundColor: 'rgba(45, 212, 191, 0.16)',
                            fill: true,
                            tension: 0.5,
                            borderWidth: 2.5,
                            pointRadius: 0,
                            pointHoverRadius: 5,
                            pointHoverBorderWidth: 2
                        }]
                    },
                    options: {
                        responsive: true,
                        maintainAspectRatio: false,
                        interaction: { intersect: false, mode: 'index' },
                        plugins: {
                            legend: { display: false },
                            tooltip: {
                                backgroundColor: 'rgba(15, 23, 42, 0.92)',
                                padding: 10,
                                cornerRadius: 10
                            }
                        },
                        scales: {
                            x: {
                                grid: { display: false, drawBorder: false },
                                ticks: { maxRotation: 0, font: { size: 9 }, color: '#64748b' }
                            },
                            y: {
                                beginAtZero: true,
                                grid: { color: grid.color },
                                ticks: { stepSize: 1, font: { size: 10 } }
                            }
                        }
                    }
                });
            }
            @endif

            @if (count($businessMix['labels']) > 0)
            const mixLabels = @json($businessMix['labels']);
            const mixValues = @json($businessMix['values']);
            const mixColors = @json($chartColors);
            
            // Compact Business Mix Chart in Hero Section
            if (document.getElementById('staffBusinessMixCompact')) {
                new Chart(document.getElementById('staffBusinessMixCompact'), {
                    type: 'doughnut',
                    data: {
                        labels: mixLabels,
                        datasets: [{
                            data: mixValues,
                            backgroundColor: mixLabels.map((_, i) => mixColors[i % mixColors.length]),
                            borderWidth: 3,
                            borderColor: '#fff',
                            hoverBorderWidth: 4
                        }]
                    },
                    options: {
                        responsive: true,
                        maintainAspectRatio: true,
                        cutout: '65%',
                        plugins: {
                            legend: { display: false },
                            tooltip: {
                                backgroundColor: 'rgba(15, 23, 42, 0.95)',
                                titleColor: '#fff',
                                bodyColor: '#fff',
                                padding: 12,
                                cornerRadius: 8,
                                displayColors: true,
                                callbacks: {
                                    label: function(context) {
                                        const total = context.dataset.data.reduce((a, b) => a + b, 0);
                                        const value = context.parsed;
                                        const percentage = ((value / total) * 100).toFixed(1);
                                        return context.label + ': ' + value + ' (' + percentage + '%)';
                                    }
                                }
                            }
                        }
                    }
                });
            }
            
            // Original chart if it exists
            if (document.getElementById('staffChartDoughnut')) {
            new Chart(document.getElementById('staffChartDoughnut'), {
                type: 'doughnut',
                data: {
                    labels: mixLabels,
                    datasets: [{
                        data: mixValues,
                        backgroundColor: mixLabels.map((_, i) => mixColors[i % mixColors.length]),
                        borderWidth: 2,
                        borderColor: '#fff'
                    }]
                },
                options: {
                    responsive: true,
                    maintainAspectRatio: false,
                    plugins: {
                        legend: { position: 'right', labels: { boxWidth: 12, font: { size: 11 } } }
                    }
                }
            });
            }
            @endif

            @if (count($applicantCategoryMix['labels']) > 0)
            const appCatLabels = @json($applicantCategoryMix['labels']);
            const appCatValues = @json($applicantCategoryMix['values']);
            new Chart(document.getElementById('staffChartApplicantCategory'), {
                type: 'bar',
                data: {
                    labels: appCatLabels,
                    datasets: [{
                        label: 'Applications',
                        data: appCatValues,
                        backgroundColor: appCatLabels.map((_, i) => barPal[i % barPal.length]),
                        borderRadius: 6
                    }]
                },
                options: {
                    indexAxis: 'y',
                    responsive: true,
                    maintainAspectRatio: false,
                    plugins: { legend: { display: false } },
                    scales: {
                        x: { beginAtZero: true, ticks: { stepSize: 1 }, grid: { color: grid.color } },
                        y: { grid: { display: false }, ticks: { font: { size: 11 } } }
                    }
                }
            });
            @endif

            @if (count($genderMix['labels']) > 0)
            const gLabels = @json($genderMix['labels']);
            const gValues = @json($genderMix['values']);
            const gColors = @json($chartColors);
            new Chart(document.getElementById('staffChartGender'), {
                type: 'doughnut',
                data: {
                    labels: gLabels,
                    datasets: [{
                        data: gValues,
                        backgroundColor: gLabels.map((_, i) => gColors[i % gColors.length]),
                        borderWidth: 2,
                        borderColor: '#fff'
                    }]
                },
                options: {
                    responsive: true,
                    maintainAspectRatio: false,
                    plugins: {
                        legend: { position: 'right', labels: { boxWidth: 12, font: { size: 11 } } }
                    }
                }
            });
            @endif

            @if (count($businessStageMix['labels']) > 0)
            const stLabels = @json($businessStageMix['labels']);
            const stValues = @json($businessStageMix['values']);
            new Chart(document.getElementById('staffChartBusinessStage'), {
                type: 'bar',
                data: {
                    labels: stLabels,
                    datasets: [{
                        label: 'Applications',
                        data: stValues,
                        backgroundColor: stLabels.map((label, i) => {
                            const s = String(label).toLowerCase();
                            if (s.includes('seed')) return 'rgba(234, 179, 8, 0.85)';
                            if (s.includes('early')) return 'rgba(59, 130, 246, 0.85)';
                            if (s.includes('growth')) return 'rgba(16, 185, 129, 0.85)';
                            return barPal[i % barPal.length];
                        }),
                        borderRadius: 6
                    }]
                },
                options: {
                    indexAxis: 'y',
                    responsive: true,
                    maintainAspectRatio: false,
                    plugins: { legend: { display: false } },
                    scales: {
                        x: { beginAtZero: true, ticks: { stepSize: 1 }, grid: { color: grid.color } },
                        y: { grid: { display: false }, ticks: { font: { size: 11 } } }
                    }
                }
            });
            @endif

            @if (count($casteMix['labels']) > 0)
            const casteLabels = @json($casteMix['labels']);
            const casteValues = @json($casteMix['values']);
            new Chart(document.getElementById('staffChartCaste'), {
                type: 'bar',
                data: {
                    labels: casteLabels,
                    datasets: [{
                        label: 'Applications',
                        data: casteValues,
                        backgroundColor: casteLabels.map((_, i) => barPal[i % barPal.length]),
                        borderRadius: 6
                    }]
                },
                options: {
                    indexAxis: 'y',
                    responsive: true,
                    maintainAspectRatio: false,
                    plugins: { legend: { display: false } },
                    scales: {
                        x: { beginAtZero: true, ticks: { stepSize: 1 }, grid: { color: grid.color } },
                        y: { grid: { display: false }, ticks: { font: { size: 10 } } }
                    }
                }
            });
            @endif

            @if (count($blockMix['labels']) > 0)
            const blockLabels = @json($blockMix['labels']);
            const blockValues = @json($blockMix['values']);
            new Chart(document.getElementById('staffChartBlock'), {
                type: 'bar',
                data: {
                    labels: blockLabels,
                    datasets: [{
                        label: 'Applications',
                        data: blockValues,
                        backgroundColor: blockLabels.map((_, i) => 'rgba(79, 70, 229, ' + (0.35 + (i % 6) * 0.1) + ')'),
                        borderRadius: 6
                    }]
                },
                options: {
                    indexAxis: 'y',
                    responsive: true,
                    maintainAspectRatio: false,
                    plugins: { legend: { display: false } },
                    scales: {
                        x: { beginAtZero: true, ticks: { stepSize: 1 }, grid: { color: grid.color } },
                        y: { grid: { display: false }, ticks: { font: { size: 9 }, maxRotation: 0 } }
                    }
                }
            });
            @endif

            @if (count($trainingMix['labels']) > 0)
            const trLabels = @json($trainingMix['labels']);
            const trValues = @json($trainingMix['values']);
            const trColors = @json($chartColors);
            new Chart(document.getElementById('staffChartTraining'), {
                type: 'doughnut',
                data: {
                    labels: trLabels,
                    datasets: [{
                        data: trValues,
                        backgroundColor: trLabels.map((_, i) => trColors[i % trColors.length]),
                        borderWidth: 2,
                        borderColor: '#fff'
                    }]
                },
                options: {
                    responsive: true,
                    maintainAspectRatio: false,
                    plugins: {
                        legend: { position: 'right', labels: { boxWidth: 12, font: { size: 11 } } }
                    }
                }
            });
            @endif

            @if (count($educationMix['labels']) > 0)
            const eduLabels = @json($educationMix['labels']);
            const eduValues = @json($educationMix['values']);
            new Chart(document.getElementById('staffChartEducation'), {
                type: 'bar',
                data: {
                    labels: eduLabels,
                    datasets: [{
                        label: 'Applications',
                        data: eduValues,
                        backgroundColor: eduLabels.map((_, i) => barPal[(i + 2) % barPal.length]),
                        borderRadius: 6
                    }]
                },
                options: {
                    indexAxis: 'y',
                    responsive: true,
                    maintainAspectRatio: false,
                    plugins: { legend: { display: false } },
                    scales: {
                        x: { beginAtZero: true, ticks: { stepSize: 1 }, grid: { color: grid.color } },
                        y: { grid: { display: false }, ticks: { font: { size: 9 }, maxRotation: 0 } }
                    }
                }
            });
            @endif

            @if (count($registeredMix['labels']) > 0)
            const regLabels = @json($registeredMix['labels']);
            const regValues = @json($registeredMix['values']);
            const regColors = @json($chartColors);
            new Chart(document.getElementById('staffChartRegistered'), {
                type: 'doughnut',
                data: {
                    labels: regLabels,
                    datasets: [{
                        data: regValues,
                        backgroundColor: regLabels.map((label, i) => {
                            const s = String(label).toLowerCase();
                            if (s === 'yes') return 'rgba(16, 185, 129, 0.9)';
                            if (s === 'no') return 'rgba(148, 163, 184, 0.85)';
                            return regColors[i % regColors.length];
                        }),
                        borderWidth: 2,
                        borderColor: '#fff'
                    }]
                },
                options: {
                    responsive: true,
                    maintainAspectRatio: false,
                    plugins: {
                        legend: { position: 'right', labels: { boxWidth: 12, font: { size: 11 } } }
                    }
                }
            });
            @endif
        })();
        </script>
    @endunless

    </main>
</body>
</html>
