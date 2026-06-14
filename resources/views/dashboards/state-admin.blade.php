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
        /* =====================================================
           COGNIFY-INSPIRED DESIGN SYSTEM — State Admin Dashboard
           ===================================================== */
        :root {
            --cg-bg: #eef0f5;
            --cg-card: #ffffff;
            --cg-radius: 20px;
            --cg-radius-sm: 12px;
            --cg-shadow: 0 2px 20px rgba(0, 0, 0, 0.06), 0 1px 4px rgba(0, 0, 0, 0.04);
            --cg-shadow-hover: 0 8px 32px rgba(0, 0, 0, 0.10), 0 2px 8px rgba(0, 0, 0, 0.06);
            --cg-text: #1c1c1e;
            --cg-sub: #3c3c43;
            --cg-muted: #8e8e93;
            --cg-border: #e5e5ea;
            --cg-green: #34c759;
            --cg-green-bg: #e8faf0;
            --cg-blue: #007aff;
            --cg-blue-bg: #e5f1ff;
            --cg-pink: #ff2d55;
            --cg-pink-bg: #ffe5ea;
            --cg-orange: #ff9500;
            --cg-orange-bg: #fff3e0;
            --cg-purple: #af52de;
            --cg-purple-bg: #f5e6ff;
            --cg-teal: #26a69a;
            --cg-teal-bg: #e0f2f1;
        }

        .admin-app-body--dashboard .admin-main {
            padding: 0.85rem clamp(0.75rem, 2vw, 1.5rem) 2rem;
            background: var(--cg-bg);
        }

        /* === TICKER STRIP === */
        .cg-ticker-strip {
            display: flex;
            flex-wrap: nowrap;
            align-items: center;
            gap: 0.65rem 1rem;
            margin: 0 0 1rem;
            padding: 0.6rem 1rem;
            border-radius: var(--cg-radius-sm);
            background: #ffffff;
            color: #1c1c1e;
            border: 1px solid #e5e7eb;
            box-shadow: 0 2px 12px rgba(0, 0, 0, 0.07);
        }
        .cg-ticker-strip__left {
            display: flex;
            align-items: baseline;
            gap: 0.35rem 0.65rem;
            flex: 0 0 auto;
            white-space: nowrap;
        }
        .cg-ticker-strip__title {
            margin: 0;
            font-size: 1rem;
            font-weight: 800;
            letter-spacing: -0.02em;
            color: #1c1c1e;
        }
        .cg-ticker-strip__meta {
            flex: 1 1 auto;
            min-width: 0;
        }
        .cg-ticker-box {
            display: flex;
            align-items: center;
            gap: 0.55rem;
            padding: 0.38rem 0.85rem;
            border-radius: 999px;
            background: #f2f2f7;
            border: 1px solid #e5e7eb;
            font-size: 0.82rem;
            font-weight: 500;
            overflow: hidden;
            white-space: nowrap;
            text-overflow: ellipsis;
        }
        .cg-ticker-box__icon { flex-shrink: 0; opacity: 0.9; font-size: 0.82rem; }
        .cg-ticker-box__text {
            flex: 1;
            min-width: 0;
            white-space: nowrap;
            overflow: hidden;
            text-overflow: ellipsis;
            transition: opacity 0.35s ease;
        }
        .cg-ticker-box__text.is-fading { opacity: 0; }

        /* === PAGE HEADER === */
        .cg-page-head {
            display: flex;
            align-items: flex-start;
            justify-content: space-between;
            gap: 1rem;
            margin-bottom: 1.25rem;
            flex-wrap: wrap;
        }
        .cg-page-title {
            margin: 0 0 0.2rem;
            font-size: clamp(1.35rem, 2.5vw, 1.75rem);
            font-weight: 800;
            letter-spacing: -0.03em;
            color: var(--cg-text);
            line-height: 1.1;
        }
        .cg-page-sub {
            margin: 0;
            font-size: 0.82rem;
            color: var(--cg-muted);
        }
        .cg-page-head-right {
            display: flex;
            align-items: center;
            gap: 0.45rem;
            flex-wrap: wrap;
        }

        /* === ALERT PILLS === */
        .cg-alert {
            display: inline-flex;
            align-items: center;
            gap: 0.35rem;
            padding: 0.38rem 0.7rem;
            border-radius: 999px;
            font-size: 0.72rem;
            font-weight: 600;
            border: 1px solid transparent;
            white-space: nowrap;
        }
        .cg-alert--warn { background: #fff8e1; border-color: #ffe082; color: #e65100; }
        .cg-alert--ok   { background: var(--cg-green-bg); border-color: #a5d6a7; color: #1b5e20; }
        .cg-alert--info { background: var(--cg-blue-bg); border-color: #90caf9; color: #0d47a1; }

        /* === HERO KPI CARDS (3 large, like reference) === */
        .cg-hero-row {
            display: grid;
            grid-template-columns: repeat(3, 1fr);
            gap: 0.85rem;
            margin-bottom: 0.85rem;
        }
        @media (max-width: 900px) { .cg-hero-row { grid-template-columns: 1fr; } }
        @media (min-width: 601px) and (max-width: 900px) { .cg-hero-row { grid-template-columns: 1fr 1fr; } }

        .cg-hero-card {
            background: var(--cg-card);
            border-radius: var(--cg-radius);
            padding: 1.1rem 1.25rem 1rem;
            box-shadow: var(--cg-shadow);
            transition: transform 0.18s ease, box-shadow 0.18s ease;
            position: relative;
            overflow: hidden;
            display: flex;
            flex-direction: column;
            gap: 0;
        }
        .cg-hero-card:hover {
            transform: translateY(-2px);
            box-shadow: var(--cg-shadow-hover);
        }
        .cg-hero-card__top {
            display: flex;
            align-items: center;
            gap: 0.6rem;
            margin-bottom: 0.75rem;
        }
        .cg-hero-icon {
            width: 2.5rem;
            height: 2.5rem;
            border-radius: 50%;
            display: inline-flex;
            align-items: center;
            justify-content: center;
            font-size: 1rem;
            flex-shrink: 0;
        }
        .cg-hero-icon--green  { background: var(--cg-green-bg);  color: var(--cg-green);  }
        .cg-hero-icon--blue   { background: var(--cg-blue-bg);   color: var(--cg-blue);   }
        .cg-hero-icon--pink   { background: var(--cg-pink-bg);   color: var(--cg-pink);   }
        .cg-hero-icon--orange { background: var(--cg-orange-bg); color: var(--cg-orange); }
        .cg-hero-icon--purple { background: var(--cg-purple-bg); color: var(--cg-purple); }
        .cg-hero-icon--teal   { background: var(--cg-teal-bg);   color: var(--cg-teal);   }

        .cg-hero-card__label {
            font-size: 0.82rem;
            font-weight: 600;
            color: var(--cg-sub);
            flex: 1;
        }
        .cg-hero-card__arrow {
            width: 1.85rem;
            height: 1.85rem;
            border-radius: 50%;
            background: #f2f2f7;
            border: none;
            display: inline-flex;
            align-items: center;
            justify-content: center;
            color: var(--cg-muted);
            font-size: 0.75rem;
            cursor: pointer;
            transition: background 0.15s;
            text-decoration: none;
        }
        .cg-hero-card__arrow:hover { background: #e5e5ea; color: var(--cg-text); }

        .cg-hero-card__val {
            font-size: clamp(1.85rem, 3.5vw, 2.35rem);
            font-weight: 800;
            letter-spacing: -0.04em;
            color: var(--cg-text);
            line-height: 1;
            margin-bottom: 0.3rem;
        }
        .cg-hero-card__val-total {
            font-size: 1rem;
            font-weight: 600;
            color: var(--cg-muted);
            letter-spacing: 0;
        }
        .cg-hero-card__trend {
            display: inline-flex;
            align-items: center;
            gap: 0.2rem;
            font-size: 0.75rem;
            font-weight: 700;
            margin-bottom: 0.75rem;
        }
        .cg-hero-card__trend--up   { color: var(--cg-green); }
        .cg-hero-card__trend--down { color: var(--cg-pink); }
        .cg-hero-card__trend--flat { color: var(--cg-muted); }

        .cg-hero-card__foot {
            display: flex;
            align-items: center;
            justify-content: space-between;
            margin-top: auto;
            padding-top: 0.65rem;
            border-top: 1px solid #f2f2f7;
            font-size: 0.72rem;
            color: var(--cg-muted);
        }
        .cg-hero-card__pill {
            display: inline-flex;
            align-items: center;
            padding: 0.22rem 0.55rem;
            border-radius: 999px;
            background: #f2f2f7;
            color: var(--cg-sub);
            font-size: 0.68rem;
            font-weight: 700;
        }

        /* === SECONDARY CHIP ROW (5 smaller KPIs) === */
        .cg-chips-row {
            display: grid;
            grid-template-columns: repeat(5, 1fr);
            gap: 0.65rem;
            margin-bottom: 0.85rem;
        }
        @media (max-width: 900px) {
            .cg-chips-row {
                grid-template-columns: repeat(3, 1fr);
            }
        }
        @media (max-width: 600px) {
            .cg-chips-row {
                grid-template-columns: 1fr 1fr;
            }
        }

        .cg-chip {
            background: var(--cg-card);
            border-radius: var(--cg-radius-sm);
            padding: 0.75rem 0.85rem;
            box-shadow: var(--cg-shadow);
            display: flex;
            align-items: center;
            gap: 0.6rem;
            transition: transform 0.15s ease, box-shadow 0.15s ease;
            position: relative;
            overflow: hidden;
        }
        .cg-chip::before {
            content: '';
            position: absolute;
            top: 0; left: 0; right: 0;
            height: 3px;
            border-radius: 3px 3px 0 0;
        }
        .cg-chip:hover { transform: translateY(-1px); box-shadow: var(--cg-shadow-hover); }
        .cg-chip--today::before   { background: linear-gradient(90deg, #ff9500, #ffca28); }
        .cg-chip--onboard::before { background: linear-gradient(90deg, #ff2d55, #f48fb1); }
        .cg-chip--target::before  { background: linear-gradient(90deg, #ff8a65, #ffab91); }
        .cg-chip--blocks::before  { background: linear-gradient(90deg, #34c759, #a5d6a7); }
        .cg-chip--savings::before { background: linear-gradient(90deg, #af52de, #ce93d8); }

        .cg-chip-ico {
            width: 2rem;
            height: 2rem;
            border-radius: 10px;
            display: inline-flex;
            align-items: center;
            justify-content: center;
            font-size: 0.78rem;
            flex-shrink: 0;
        }
        .cg-chip-ico--yellow { background: #fff3e0; color: #ff9500; }
        .cg-chip-ico--rose   { background: var(--cg-pink-bg); color: var(--cg-pink); }
        .cg-chip-ico--amber  { background: #fff3e0; color: #ef6c00; }
        .cg-chip-ico--green  { background: var(--cg-green-bg); color: #1b5e20; }
        .cg-chip-ico--purple { background: var(--cg-purple-bg); color: var(--cg-purple); }

        .cg-chip-body { min-width: 0; }
        .cg-chip-label { font-size: 0.62rem; font-weight: 700; text-transform: uppercase; letter-spacing: 0.06em; color: var(--cg-muted); line-height: 1.2; }
        .cg-chip-val { font-size: 0.95rem; font-weight: 800; color: var(--cg-text); letter-spacing: -0.02em; white-space: nowrap; overflow: hidden; text-overflow: ellipsis; }
        .cg-chip-val.is-up   { color: var(--cg-green); }
        .cg-chip-val.is-down { color: var(--cg-pink); }

        /* === ALERTS ROW === */
        .cg-alerts-row {
            display: flex;
            flex-wrap: wrap;
            gap: 0.4rem;
            margin-bottom: 0.85rem;
        }

        /* === TAB NAVIGATION (pill style like reference) === */
        .cg-tabs {
            display: flex;
            align-items: center;
            gap: 0.25rem;
            margin-bottom: 0.85rem;
            padding: 0.3rem;
            background: var(--cg-card);
            border-radius: 999px;
            box-shadow: var(--cg-shadow);
            width: fit-content;
            max-width: 100%;
            overflow-x: auto;
            scrollbar-width: none;
            -webkit-overflow-scrolling: touch;
        }
        .cg-tabs::-webkit-scrollbar { display: none; }
        .cg-tab {
            flex: 0 0 auto;
            border: none;
            background: transparent;
            font-family: inherit;
            font-size: 0.8rem;
            font-weight: 700;
            color: var(--cg-muted);
            padding: 0.5rem 1rem;
            border-radius: 999px;
            cursor: pointer;
            display: inline-flex;
            align-items: center;
            gap: 0.4rem;
            transition: background 0.15s, color 0.15s;
            white-space: nowrap;
        }
        .cg-tab:hover { background: #f2f2f7; color: var(--cg-text); }
        .cg-tab.is-active {
            background: var(--cg-text);
            color: #ffffff;
            box-shadow: 0 4px 12px rgba(0,0,0,0.18);
        }

        /* === PANELS === */
        .cg-panel { display: none; animation: cgFade 0.25s ease; }
        .cg-panel.is-active { display: block; }
        @keyframes cgFade {
            from { opacity: 0; transform: translateY(6px); }
            to   { opacity: 1; transform: translateY(0); }
        }

        /* === MAIN 3-COLUMN ROW (like reference) === */
        .cg-main-row {
            display: grid;
            grid-template-columns: 1fr 1fr 1fr;
            gap: 0.85rem;
            margin-bottom: 0.85rem;
        }
        @media (max-width: 1200px) { .cg-main-row { grid-template-columns: 1fr 1fr; } }
        @media (max-width: 900px)  { .cg-main-row { grid-template-columns: 1fr; } }

        /* === CARDS === */
        .cg-card {
            background: var(--cg-card);
            border-radius: var(--cg-radius);
            padding: 1.1rem 1.2rem;
            box-shadow: var(--cg-shadow);
            min-width: 0;
        }
        .cg-card--full { width: 100%; }
        .cg-card__head {
            display: flex;
            align-items: center;
            justify-content: space-between;
            gap: 0.5rem;
            margin-bottom: 0.85rem;
        }
        .cg-card__title {
            font-size: 0.92rem;
            font-weight: 800;
            color: var(--cg-text);
            margin: 0;
            display: flex;
            align-items: center;
            gap: 0.4rem;
        }
        .cg-card__title i { color: var(--cg-teal); font-size: 0.88rem; }
        .cg-card__tag {
            font-size: 0.65rem;
            font-weight: 700;
            padding: 0.22rem 0.5rem;
            border-radius: 6px;
            background: var(--cg-teal-bg);
            color: #00695c;
        }
        .cg-card__hint {
            font-size: 0.72rem;
            color: var(--cg-muted);
            margin: -0.5rem 0 0.65rem;
            line-height: 1.4;
        }
        .cg-card-btn-group {
            display: flex;
            gap: 0.25rem;
        }
        .cg-icon-btn {
            width: 1.85rem;
            height: 1.85rem;
            border-radius: 8px;
            border: 1px solid var(--cg-border);
            background: #f2f2f7;
            color: var(--cg-muted);
            font-size: 0.72rem;
            font-weight: 700;
            cursor: pointer;
            display: inline-flex;
            align-items: center;
            justify-content: center;
            transition: background 0.15s;
        }
        .cg-icon-btn:hover { background: var(--cg-border); color: var(--cg-text); }

        /* === BIG PERCENTAGE (Task Progress style) === */
        .cg-prog-big {
            font-size: 2.5rem;
            font-weight: 800;
            letter-spacing: -0.04em;
            color: var(--cg-text);
            line-height: 1;
            margin-bottom: 0.2rem;
        }
        .cg-prog-sub {
            font-size: 0.78rem;
            color: var(--cg-muted);
            margin-bottom: 0.85rem;
            font-weight: 500;
        }

        /* === PROGRESS BARS === */
        .cg-progress { margin-bottom: 0.65rem; }
        .cg-progress:last-child { margin-bottom: 0; }
        .cg-progress__top {
            display: flex;
            justify-content: space-between;
            align-items: baseline;
            font-size: 0.75rem;
            font-weight: 600;
            margin-bottom: 0.3rem;
            color: var(--cg-sub);
        }
        .cg-progress__top strong { font-size: 0.88rem; font-weight: 800; color: var(--cg-text); }
        .cg-progress__track {
            height: 8px;
            border-radius: 999px;
            background: #f2f2f7;
            overflow: hidden;
        }
        .cg-progress__fill {
            height: 100%;
            border-radius: 999px;
            transition: width 0.6s ease;
        }
        .cg-progress__fill--green  { background: linear-gradient(90deg, var(--cg-green), #a5d6a7); }
        .cg-progress__fill--blue   { background: linear-gradient(90deg, var(--cg-blue), #90caf9); }
        .cg-progress__fill--orange { background: linear-gradient(90deg, var(--cg-orange), #ffca28); }
        .cg-progress__fill--pink   { background: linear-gradient(90deg, var(--cg-pink), #f48fb1); }
        .cg-progress__fill--teal   { background: linear-gradient(90deg, var(--cg-teal), #4db6ac); }
        .cg-progress__foot {
            font-size: 0.67rem;
            color: var(--cg-muted);
            margin-top: 0.3rem;
        }
        .cg-progress__foot a { color: var(--cg-teal); }

        /* === SIGNAL GRID === */
        .cg-signals {
            display: grid;
            grid-template-columns: 1fr 1fr;
            gap: 0.45rem;
            margin-top: 0.65rem;
        }
        .cg-signal {
            padding: 0.5rem 0.6rem;
            border-radius: var(--cg-radius-sm);
            background: #f8f8fb;
            border: 1px solid #ececf1;
        }
        .cg-signal span { display: block; font-size: 0.62rem; color: var(--cg-muted); font-weight: 600; margin-bottom: 0.15rem; }
        .cg-signal strong { font-size: 0.9rem; font-weight: 800; color: var(--cg-text); display: block; }
        .cg-chip-badge {
            display: inline-block;
            font-size: 0.68rem;
            font-weight: 700;
            padding: 0.15rem 0.42rem;
            border-radius: 6px;
        }
        .cg-chip-badge.up   { background: var(--cg-green-bg); color: #1b5e20; }
        .cg-chip-badge.down { background: var(--cg-pink-bg);  color: #880e4f; }
        .cg-chip-badge.flat { background: #f2f2f7; color: var(--cg-muted); }

        /* === RING CHART WRAPPER === */
        .cg-ring-wrap {
            display: flex;
            flex-direction: column;
            align-items: center;
            position: relative;
            margin: 0.25rem 0 1rem;
        }
        .cg-ring-svg { width: 140px; height: 140px; }
        .cg-ring-svg .cg-track { fill: none; stroke: #f2f2f7; stroke-width: 10; }
        .cg-ring-svg .cg-bar {
            fill: none;
            stroke: url(#cgRingGrad);
            stroke-width: 10;
            stroke-linecap: round;
            transform: rotate(-90deg);
            transform-origin: 50% 50%;
        }
        .cg-ring-svg .cg-pct {
            font-family: 'DM Sans', sans-serif;
            font-weight: 800;
            fill: var(--cg-text);
            font-size: 20px;
        }
        .cg-ring-svg .cg-pct-sub {
            font-family: 'DM Sans', sans-serif;
            font-weight: 600;
            fill: var(--cg-muted);
            font-size: 10px;
        }

        /* === BREAKDOWN ROWS (below ring, like reference) === */
        .cg-breakdown { margin-top: 0.5rem; }
        .cg-breakdown-row {
            display: flex;
            align-items: center;
            gap: 0.55rem;
            padding: 0.35rem 0;
            border-bottom: 1px solid #f5f5f7;
            font-size: 0.75rem;
        }
        .cg-breakdown-row:last-child { border-bottom: none; }
        .cg-breakdown-dot {
            width: 8px;
            height: 8px;
            border-radius: 50%;
            flex-shrink: 0;
        }
        .cg-breakdown-dot--green  { background: var(--cg-green); }
        .cg-breakdown-dot--blue   { background: var(--cg-blue); }
        .cg-breakdown-dot--orange { background: var(--cg-orange); }
        .cg-breakdown-label { flex: 1; font-weight: 600; color: var(--cg-sub); }
        .cg-breakdown-bar-wrap { flex: 2; }
        .cg-breakdown-bar-track {
            height: 5px;
            border-radius: 999px;
            background: #f2f2f7;
            overflow: hidden;
        }
        .cg-breakdown-bar-fill { height: 100%; border-radius: 999px; }
        .cg-breakdown-val { font-weight: 800; color: var(--cg-text); min-width: 2rem; text-align: right; }

        /* === SPARKLINE === */
        .cg-spark { height: 28px; margin-top: 0.35rem; }
        .cg-spark svg { width: 100%; height: 100%; display: block; overflow: visible; }
        .cg-spark__fill { fill: url(#cgSparkGrad); opacity: 0.35; }
        .cg-spark__line { fill: none; stroke: var(--cg-teal); stroke-width: 1.5; stroke-linecap: round; stroke-linejoin: round; }
        .cg-spark__dot  { fill: var(--cg-teal); }

        /* === PULSE TABS === */
        .cg-pulse-tabs {
            display: flex;
            flex-wrap: wrap;
            gap: 0.3rem;
            margin: 0.6rem 0 0.45rem;
        }
        .cg-pulse-tab {
            border: 1px solid var(--cg-border);
            background: #f8f8fb;
            border-radius: 999px;
            padding: 0.28rem 0.6rem;
            font-family: inherit;
            font-size: 0.68rem;
            font-weight: 700;
            color: var(--cg-muted);
            cursor: pointer;
            transition: background 0.15s, border-color 0.15s, color 0.15s;
        }
        .cg-pulse-tab:hover { background: #ececf1; color: var(--cg-text); }
        .cg-pulse-tab.is-active { background: var(--cg-teal); border-color: var(--cg-teal); color: #fff; }

        .cg-pulse-hint { margin: 0 0 0.35rem; font-size: 0.67rem; color: var(--cg-muted); line-height: 1.4; }

        /* === PULSE TOTALS (bottom of trend card, like reference) === */
        .cg-pulse-totals {
            display: grid;
            grid-template-columns: 1fr 1fr;
            gap: 0.45rem;
            padding-top: 0.65rem;
            border-top: 1px solid #f5f5f7;
            margin-top: 0.65rem;
        }
        .cg-pulse-total-label { font-size: 0.65rem; color: var(--cg-muted); font-weight: 600; margin-bottom: 0.15rem; }
        .cg-pulse-total-val { font-size: 1rem; font-weight: 800; letter-spacing: -0.02em; color: var(--cg-text); }
        .cg-pulse-total-val.nd-up   { color: var(--cg-green); }
        .cg-pulse-total-val.nd-down { color: var(--cg-pink); }
        .cg-pulse-total-val .cg-trend-arrow { font-size: 0.72rem; margin-left: 0.2rem; }

        /* === BUSINESS MIX === */
        .cg-biz-row {
            display: grid;
            grid-template-columns: auto 1fr auto;
            gap: 0.5rem;
            align-items: center;
            margin-bottom: 0.42rem;
            font-size: 0.75rem;
        }
        .cg-biz-rank {
            width: 1.5rem; height: 1.5rem;
            border-radius: 8px;
            background: #f2f2f7;
            font-size: 0.62rem; font-weight: 800;
            display: flex; align-items: center; justify-content: center;
            color: var(--cg-muted);
        }
        .cg-biz-label { font-weight: 700; white-space: nowrap; overflow: hidden; text-overflow: ellipsis; color: var(--cg-sub); }
        .cg-biz-track { height: 6px; border-radius: 999px; background: #f2f2f7; overflow: hidden; margin-top: 0.25rem; }
        .cg-biz-fill  { height: 100%; border-radius: 999px; }
        .cg-biz-nums  { font-weight: 800; color: var(--cg-text); white-space: nowrap; }

        /* === STAGE MIX === */
        .cg-stage-mix {
            padding-top: 0.65rem;
            border-top: 1px solid #f5f5f7;
            margin-top: 0.65rem;
        }
        .cg-stage-mix__head {
            display: flex; align-items: center; justify-content: space-between;
            flex-wrap: wrap; gap: 0.4rem; margin-bottom: 0.5rem;
        }
        .cg-stage-mix__title {
            font-size: 0.7rem; font-weight: 700; color: var(--cg-muted);
            text-transform: uppercase; letter-spacing: 0.04em; margin: 0;
        }
        .cg-stage-badge {
            font-size: 0.62rem; font-weight: 700; padding: 0.18rem 0.5rem;
            border-radius: 999px; white-space: nowrap;
        }
        .cg-stage-badge--ok   { background: var(--cg-green-bg); color: #1b5e20; border: 1px solid #a5d6a7; }
        .cg-stage-badge--warn { background: #fff8e1; color: #e65100; border: 1px solid #ffe082; }
        .cg-stage-row { margin-bottom: 0.42rem; }
        .cg-stage-row:last-child { margin-bottom: 0; }
        .cg-stage-label-row {
            display: flex; align-items: baseline; justify-content: space-between;
            font-size: 0.7rem; font-weight: 700; margin-bottom: 0.18rem; color: var(--cg-sub);
        }
        .cg-stage-nums { font-size: 0.62rem; font-weight: 600; color: var(--cg-muted); }
        .cg-stage-track {
            position: relative; height: 7px; border-radius: 999px;
            background: #f2f2f7; overflow: visible;
        }
        .cg-stage-track__fill { height: 100%; border-radius: 999px; max-width: 100%; }
        .cg-stage-track__target {
            position: absolute; top: -3px; bottom: -3px; width: 2px;
            border-radius: 1px; background: #334155; opacity: 0.75; z-index: 2;
            transform: translateX(-50%);
        }
        .cg-stage-foot {
            display: flex; justify-content: space-between; align-items: center;
            margin-top: 0.15rem; font-size: 0.6rem; color: var(--cg-muted);
        }
        .cg-stage-delta { font-weight: 700; }
        .cg-stage-delta.is-ok   { color: #1b5e20; }
        .cg-stage-delta.is-high { color: #e65100; }
        .cg-stage-delta.is-low  { color: #c62828; }
        .cg-stage-fill--seed   { background: #d97706; }
        .cg-stage-fill--early  { background: #ff9500; }
        .cg-stage-fill--growth { background: var(--cg-teal); }
        .cg-stage-legend {
            font-size: 0.6rem; color: var(--cg-muted); margin: 0 0 0.35rem;
        }
        .cg-stage-legend-tick {
            display: inline-block; width: 2px; height: 0.65rem;
            background: #334155; vertical-align: middle; margin: 0 0.2rem;
        }

        /* === GRID LAYOUTS === */
        .cg-grid-2 { display: grid; grid-template-columns: 1fr 1fr; gap: 0.85rem; }
        .cg-grid-3 { display: grid; grid-template-columns: 1fr 1fr 1fr; gap: 0.85rem; }
        @media (max-width: 1100px) { .cg-grid-2, .cg-grid-3 { grid-template-columns: 1fr; } }

        /* === DISTRICT CARDS === */
        .cg-district-cards {
            display: grid;
            grid-template-columns: repeat(auto-fill, minmax(9rem, 1fr));
            gap: 0.5rem;
            margin-bottom: 0.65rem;
        }
        .cg-district-card {
            padding: 0.6rem 0.7rem;
            border-radius: var(--cg-radius-sm);
            border: 1px solid var(--cg-border);
            background: #f8f8fb;
            transition: transform 0.15s;
        }
        .cg-district-card:hover { transform: translateY(-1px); }
        .cg-district-card.is-top { border-color: #ffe082; background: #fff8e1; }
        .cg-district-card__name { font-size: 0.68rem; font-weight: 700; color: var(--cg-muted); white-space: nowrap; overflow: hidden; text-overflow: ellipsis; }
        .cg-district-card__val  { font-size: 1.15rem; font-weight: 800; color: var(--cg-text); margin-top: 0.15rem; }

        /* === SPLIT TABLE === */
        .cg-split-table {
            max-height: 14rem;
            overflow-y: auto;
            border: 1px solid var(--cg-border);
            border-radius: var(--cg-radius-sm);
        }
        .cg-split-row {
            display: flex;
            justify-content: space-between;
            padding: 0.38rem 0.6rem;
            font-size: 0.75rem;
            border-bottom: 1px solid #f5f5f7;
            color: var(--cg-sub);
        }
        .cg-split-row:last-child { border-bottom: none; }
        .cg-split-row.is-zero { opacity: 0.5; }
        .cg-split-row strong { color: var(--cg-text); font-weight: 800; }

        /* === STAFF TABLE === */
        .cg-staff-controls {
            display: flex; gap: 0.4rem; margin-bottom: 0.5rem; flex-wrap: wrap;
        }
        .cg-staff-controls input,
        .cg-staff-controls select {
            flex: 1; min-width: 8rem;
            padding: 0.42rem 0.6rem;
            border: 1px solid var(--cg-border);
            border-radius: var(--cg-radius-sm);
            font-family: inherit; font-size: 0.78rem;
            background: #f8f8fb;
        }
        .cg-staff-controls input:focus,
        .cg-staff-controls select:focus {
            outline: none;
            border-color: var(--cg-teal);
            box-shadow: 0 0 0 3px rgba(38, 166, 154, 0.15);
        }
        .cg-staff-list { max-height: min(480px, 52vh); overflow-y: auto; }
        .cg-staff-row {
            display: grid;
            grid-template-columns: 2rem 1fr auto;
            gap: 0.45rem; align-items: center;
            padding: 0.45rem 0.35rem;
            border-bottom: 1px solid #f5f5f7;
            font-size: 0.78rem;
        }
        .cg-staff-row:last-child { border-bottom: none; }
        .cg-staff-rank { font-size: 0.68rem; font-weight: 800; color: var(--cg-muted); }
        .cg-staff-rank.is-medal { color: #ff9500; }
        .cg-staff-main { display: flex; align-items: center; gap: 0.45rem; min-width: 0; }
        .cg-staff-avatar, .cg-staff-fallback {
            width: 30px; height: 30px; border-radius: 10px;
            flex-shrink: 0; object-fit: cover;
        }
        .cg-staff-fallback {
            display: inline-flex; align-items: center; justify-content: center;
            background: linear-gradient(135deg, #26a69a, #4db6ac);
            color: #fff; font-size: 0.7rem; font-weight: 800;
        }
        .cg-staff-name { font-weight: 700; white-space: nowrap; overflow: hidden; text-overflow: ellipsis; color: var(--cg-text); }
        .cg-staff-district { font-size: 0.65rem; color: var(--cg-muted); }
        .cg-staff-val { font-weight: 800; font-size: 0.92rem; color: var(--cg-text); }

        /* === SAVINGS TILES === */
        .cg-savings-grid {
            display: grid;
            grid-template-columns: repeat(3, 1fr);
            gap: 0.65rem; margin-bottom: 0.65rem;
        }
        @media (max-width: 720px) { .cg-savings-grid { grid-template-columns: 1fr; } }
        .cg-savings-tile {
            padding: 0.75rem 0.85rem;
            border-radius: var(--cg-radius-sm);
            border: 1px solid;
        }
        .cg-savings-tile--green { border-color: #a5d6a7; background: var(--cg-green-bg); }
        .cg-savings-tile--blue  { border-color: #90caf9; background: var(--cg-blue-bg); }
        .cg-savings-tile--teal  { border-color: #80cbc4; background: var(--cg-teal-bg); }
        .cg-savings-tile__lbl { font-size: 0.62rem; font-weight: 700; text-transform: uppercase; letter-spacing: 0.05em; }
        .cg-savings-tile__val { font-size: 1.2rem; font-weight: 800; margin-top: 0.2rem; }

        /* === TABLE === */
        .cg-table-wrap { border: 1px solid var(--cg-border); border-radius: var(--cg-radius-sm); overflow: hidden; }
        .cg-table { width: 100%; border-collapse: collapse; font-size: 0.76rem; }
        .cg-table th { text-align: left; padding: 0.5rem 0.6rem; background: #f8f8fb; font-weight: 700; color: var(--cg-muted); }
        .cg-table th:not(:first-child), .cg-table td:not(:first-child) { text-align: right; }
        .cg-table td { padding: 0.42rem 0.6rem; border-top: 1px solid #f5f5f7; color: var(--cg-sub); }
        .cg-table td strong { color: var(--cg-text); }

        /* === DOCK (quick links) === */
        .cg-dock {
            margin-top: 1rem;
            padding: 0.85rem 1rem;
            background: var(--cg-card);
            border-radius: var(--cg-radius);
            box-shadow: var(--cg-shadow);
        }
        .cg-dock__title {
            font-size: 0.65rem; font-weight: 800;
            text-transform: uppercase; letter-spacing: 0.08em;
            color: var(--cg-muted); margin: 0 0 0.6rem;
        }
        .cg-dock__links { display: flex; flex-wrap: wrap; gap: 0.4rem; }
        .cg-dock__link {
            display: inline-flex; align-items: center; gap: 0.35rem;
            padding: 0.4rem 0.65rem;
            border-radius: var(--cg-radius-sm);
            border: 1px solid var(--cg-border);
            background: #f8f8fb;
            color: var(--cg-sub);
            text-decoration: none;
            font-size: 0.73rem; font-weight: 600;
            transition: background 0.12s, border-color 0.12s, transform 0.12s;
        }
        .cg-dock__link:hover {
            background: var(--cg-teal-bg);
            border-color: #80cbc4;
            color: #00695c;
            transform: translateY(-1px);
        }
        .cg-dock__link i { color: var(--cg-teal); font-size: 0.8rem; }

        /* === CHART BOXES === */
        .cg-chart-box { height: 175px; position: relative; }
        .cg-chart-box--pulse { height: 185px; }
        .cg-chart-box--tall { height: min(420px, 55vh); }
        .cg-chart-box--donut { height: 210px; position: relative; }

        /* === ALIGN PILLS === */
        .cg-align-status { display: flex; flex-wrap: wrap; gap: 0.35rem; margin-top: 0.35rem; }
        .cg-align-pill { font-size: 0.65rem; font-weight: 700; padding: 0.2rem 0.45rem; border-radius: 6px; }
        .cg-align-pill--ok  { background: var(--cg-green-bg); color: #1b5e20; }
        .cg-align-pill--bad { background: #fff8e1; color: #e65100; }
        .cg-align-gaps {
            margin-top: 0.4rem; padding: 0.45rem 0.6rem;
            border-radius: 8px; background: #f8f8fb;
            border: 1px solid var(--cg-border);
            font-size: 0.68rem; max-height: 8rem; overflow-y: auto;
        }
        .cg-align-gaps li { margin: 0.2rem 0; line-height: 1.35; color: var(--cg-sub); }
        details.cg-details summary {
            cursor: pointer; font-size: 0.72rem; font-weight: 700;
            color: var(--cg-teal); list-style: none;
        }
        details.cg-details summary::-webkit-details-marker { display: none; }

        /* === EMPTY STATE === */
        .cg-empty {
            text-align: center; padding: 1.5rem;
            color: var(--cg-muted); font-size: 0.82rem;
        }

        /* === DT TABS (insights sub-tabs) === */
        .cg-dt-tabs {
            display: flex; flex-wrap: nowrap; gap: 0.3rem;
            overflow-x: auto; scrollbar-width: thin;
            padding-bottom: 0.3rem; margin-bottom: 0.35rem;
            -webkit-overflow-scrolling: touch;
        }
        .cg-dt-tab {
            flex: 0 0 auto;
            border: 1px solid var(--cg-border);
            background: #f8f8fb;
            border-radius: 999px; padding: 0.3rem 0.65rem;
            font-family: inherit; font-size: 0.68rem; font-weight: 700;
            color: var(--cg-muted); cursor: pointer;
            transition: background 0.15s, border-color 0.15s, color 0.15s;
        }
        .cg-dt-tab:hover { background: #ececf1; color: var(--cg-text); }
        .cg-dt-tab.is-active {
            background: var(--cg-teal-bg); border-color: var(--cg-teal);
            color: #00695c;
        }
        .cg-dt-hint { margin: 0 0 0.45rem; font-size: 0.72rem; color: var(--cg-muted); line-height: 1.4; }

        /* === INSIGHTS GRID === */
        .cg-insights-grid { display: grid; grid-template-columns: repeat(3, 1fr); gap: 0.65rem; margin-top: 0.65rem; }
        .cg-insights-grid--2 { grid-template-columns: repeat(2, 1fr); }
        @media (max-width: 1100px) { .cg-insights-grid, .cg-insights-grid--2 { grid-template-columns: 1fr; } }

        /* ---- Keep SAD interop for _insights-panel include ---- */
        /* The _insights-panel uses .sad-* classes; map them to new style */
        .sad-panel  { display: none; animation: cgFade 0.25s ease; }
        .sad-panel.is-active { display: block; }
        /* Map sad-card → cg-card */
        .sad-card {
            background: var(--cg-card);
            border-radius: var(--cg-radius);
            padding: 1.1rem 1.2rem;
            box-shadow: var(--cg-shadow);
            border: none;
            min-width: 0;
        }
        .sad-card__head {
            display: flex; align-items: center; justify-content: space-between;
            gap: 0.5rem; margin-bottom: 0.85rem;
        }
        .sad-card__title { font-size: 0.92rem; font-weight: 800; color: var(--cg-text); margin: 0; display: flex; align-items: center; gap: 0.4rem; }
        .sad-card__title i { color: var(--cg-teal); }
        .sad-card__tag { font-size: 0.65rem; font-weight: 700; padding: 0.22rem 0.5rem; border-radius: 6px; background: var(--cg-teal-bg); color: #00695c; }
        .sad-card__hint { font-size: 0.72rem; color: var(--cg-muted); margin: -0.5rem 0 0.65rem; line-height: 1.4; }
        .sad-grid { display: grid; gap: 0.85rem; }
        .sad-grid--2 { grid-template-columns: 1fr 1fr; }
        .sad-grid--3 { grid-template-columns: 1.1fr 1fr 1fr; }
        @media (max-width: 1100px) { .sad-grid--2, .sad-grid--3 { grid-template-columns: 1fr; } }
        .sad-empty { text-align: center; padding: 1.5rem; color: var(--cg-muted); font-size: 0.82rem; }
        .sad-chart-box { height: 175px; position: relative; }
        .sad-chart-box--donut { height: 210px; }
        .sad-chart-box--md { height: 230px; }
        .sad-chart-box--tall { height: min(420px, 55vh); }
        .sad-chart-box--pulse { height: 185px; }
        .sad-dt-tabs { display: flex; flex-wrap: nowrap; gap: 0.3rem; overflow-x: auto; scrollbar-width: thin; padding-bottom: 0.3rem; margin-bottom: 0.35rem; -webkit-overflow-scrolling: touch; }
        .sad-dt-tab { flex: 0 0 auto; border: 1px solid var(--cg-border); background: #f8f8fb; border-radius: 999px; padding: 0.3rem 0.65rem; font-family: inherit; font-size: 0.68rem; font-weight: 700; color: var(--cg-muted); cursor: pointer; transition: background 0.15s, border-color 0.15s, color 0.15s; }
        .sad-dt-tab:hover { background: #ececf1; color: var(--cg-text); }
        .sad-dt-tab.is-active { background: var(--cg-teal-bg); border-color: var(--cg-teal); color: #00695c; }
        .sad-dt-hint { margin: 0 0 0.45rem; font-size: 0.72rem; color: var(--cg-muted); line-height: 1.4; }
        .sad-insights-grid { display: grid; grid-template-columns: repeat(3, 1fr); gap: 0.65rem; margin-top: 0.65rem; }
        .sad-insights-grid--2 { grid-template-columns: repeat(2, 1fr); }
        @media (max-width: 1100px) { .sad-insights-grid, .sad-insights-grid--2 { grid-template-columns: 1fr; } }
        /* sad-signals / sad-signal → map to cg-signals */
        .sad-signals { display: grid; grid-template-columns: 1fr 1fr; gap: 0.45rem; }
        .sad-signal { padding: 0.5rem 0.6rem; border-radius: var(--cg-radius-sm); background: #f8f8fb; border: 1px solid #ececf1; }
        .sad-signal span { display: block; font-size: 0.62rem; color: var(--cg-muted); font-weight: 600; margin-bottom: 0.15rem; }
        .sad-signal strong { font-size: 0.9rem; font-weight: 800; color: var(--cg-text); display: block; }
        /* sad-split-table */
        .sad-split-table { max-height: 14rem; overflow-y: auto; border: 1px solid var(--cg-border); border-radius: var(--cg-radius-sm); }
        .sad-split-row { display: flex; justify-content: space-between; padding: 0.38rem 0.6rem; font-size: 0.75rem; border-bottom: 1px solid #f5f5f7; color: var(--cg-sub); }
        .sad-split-row:last-child { border-bottom: none; }
        .sad-split-row.is-zero { opacity: 0.5; }
        /* sad-district-target-card */
        .sad-district-target-card { background: var(--cg-card); border-radius: var(--cg-radius); padding: 1.1rem 1.2rem; box-shadow: var(--cg-shadow); border: none; }

        @include('dashboards.state-admin._theme-styles')
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
            $ringCirc = 2 * M_PI * 55;
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
            $stagePolicyTargets = ['EARLY' => 60, 'SEED' => 30, 'GROWTH' => 10];
            $stagePolicyOrder = ['EARLY', 'SEED', 'GROWTH'];
            $stagePolicyRows = [];
            $stagePolicyDeviationTotal = 0;
            $stagePolicyWithinTolerance = $sStageSum > 0;
            foreach ($stagePolicyOrder as $stageKey) {
                $actual = (int) ($sStagePct[$stageKey] ?? 0);
                $target = (int) ($stagePolicyTargets[$stageKey] ?? 0);
                $delta = $actual - $target;
                $absDelta = abs($delta);
                $stagePolicyDeviationTotal += $absDelta;
                if ($absDelta > 5) {
                    $stagePolicyWithinTolerance = false;
                }
                $stagePolicyRows[] = [
                    'key' => $stageKey,
                    'label' => ucfirst(strtolower($stageKey)),
                    'actual' => $actual,
                    'target' => $target,
                    'delta' => $delta,
                    'count' => (int) ($sStageTotals[$stageKey] ?? 0),
                    'status' => $absDelta <= 5 ? 'ok' : ($delta > 0 ? 'high' : 'low'),
                ];
            }

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

        {{-- Ground activity ticker --}}
        <header class="cg-ticker-strip" aria-label="Dashboard context">
            <div class="cg-ticker-strip__left">
                <h1 class="cg-ticker-strip__title">
                    <i class="fa-solid fa-bolt" aria-hidden="true" style="opacity:0.85;margin-right:0.3rem;"></i>
                    Welcome, {{ auth()->user()->name }}
                </h1>
            </div>
            <div class="cg-ticker-strip__meta">
                <div class="cg-ticker-box" id="sadGroundTicker" aria-live="polite" aria-atomic="true">
                    <i class="fa-solid fa-bullhorn cg-ticker-box__icon" aria-hidden="true"></i>
                    <span class="cg-ticker-box__text" id="sadGroundTickerText"></span>
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
                var tmp = messages[i]; messages[i] = messages[j]; messages[j] = tmp;
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
            el.textContent = messages[0]; idx = 1;
            setInterval(showNext, 7000);
        })();
        </script>

        {{-- Page header row --}}
        <div class="cg-page-head">
            <div>
                <h1 class="cg-page-title">State Command Dashboard</h1>
                <p class="cg-page-sub">{{ $fyLabel }} · Live performance data</p>
            </div>
            <div class="cg-page-head-right">
                @if (($todayZeroDistricts ?? 0) > 0)
                    <span class="cg-alert cg-alert--warn">
                        <i class="fa-solid fa-triangle-exclamation" aria-hidden="true"></i>
                        {{ number_format((int) $todayZeroDistricts) }} district(s) with zero CFA today
                    </span>
                @endif
                @if ($todayTopDistrict)
                    <span class="cg-alert cg-alert--ok">
                        <i class="fa-solid fa-trophy" aria-hidden="true"></i>
                        Top today: {{ $todayTopDistrict['name'] }} ({{ number_format((int) $todayTopDistrict['count']) }})
                    </span>
                @endif
                @if (($cfaWoWDeltaPct ?? 0) !== 0)
                    <span class="cg-alert cg-alert--info">
                        <i class="fa-solid fa-chart-line" aria-hidden="true"></i>
                        7-day CFA {{ ($cfaWoWDeltaPct ?? 0) > 0 ? 'up' : 'down' }} {{ abs((int) ($cfaWoWDeltaPct ?? 0)) }}% vs prior week
                    </span>
                @endif
            </div>
        </div>

        {{-- ═══════════════════════════════════════════════
             HERO KPI CARDS — 3 large cards like reference
             ═══════════════════════════════════════════════ --}}
        <div class="cg-hero-row" role="group" aria-label="Primary KPIs">

            {{-- Card 1: CFA Total --}}
            <div class="cg-hero-card">
                <div class="cg-hero-card__top">
                    <span class="cg-hero-icon cg-hero-icon--green" aria-hidden="true">
                        <i class="fa-solid fa-file-circle-plus"></i>
                    </span>
                    <span class="cg-hero-card__label">CFA Total</span>
                    <a href="{{ route('admin.cfa.index') }}" class="cg-hero-card__arrow" title="View CFA">
                        <i class="fa-solid fa-arrow-up-right-from-square"></i>
                    </a>
                </div>
                <div class="cg-hero-card__val">{{ number_format($cfaTotalN) }}</div>
                @if ($todayDelta > 0)
                    <div class="cg-hero-card__trend cg-hero-card__trend--up">
                        <i class="fa-solid fa-arrow-up"></i> {{ number_format($todayDelta) }} today
                    </div>
                @elseif ($todayDelta < 0)
                    <div class="cg-hero-card__trend cg-hero-card__trend--down">
                        <i class="fa-solid fa-arrow-down"></i> {{ number_format(abs($todayDelta)) }} today
                    </div>
                @else
                    <div class="cg-hero-card__trend cg-hero-card__trend--flat">
                        <i class="fa-solid fa-minus"></i> Same as yesterday
                    </div>
                @endif
                @if ($sparkLine)
                    <div class="cg-spark" aria-hidden="true" title="30-day CFA volume: {{ number_format($sparkSum) }} total">
                        <svg viewBox="0 0 {{ $sparkW }} {{ $sparkH }}" preserveAspectRatio="none">
                            <defs>
                                <linearGradient id="cgSparkGrad" x1="0" y1="0" x2="0" y2="1">
                                    <stop offset="0%" stop-color="#26a69a" stop-opacity="0.5"/>
                                    <stop offset="100%" stop-color="#26a69a" stop-opacity="0"/>
                                </linearGradient>
                            </defs>
                            <polygon class="cg-spark__fill" points="{{ $sparkFill }}"/>
                            <polyline class="cg-spark__line" points="{{ $sparkLine }}"/>
                            @if ($sparkDotX !== null && $sparkDotY !== null)
                                <circle class="cg-spark__dot" cx="{{ $sparkDotX }}" cy="{{ $sparkDotY }}" r="2.5"/>
                            @endif
                        </svg>
                    </div>
                @endif
                <div class="cg-hero-card__foot">
                    <span>Phase 3 · {{ $phaseLabel }} onward</span>
                    <span class="cg-hero-card__pill">Live</span>
                </div>
            </div>

            {{-- Card 2: Total Onboarding --}}
            <div class="cg-hero-card">
                <div class="cg-hero-card__top">
                    <span class="cg-hero-icon cg-hero-icon--blue" aria-hidden="true">
                        <i class="fa-solid fa-user-check"></i>
                    </span>
                    <span class="cg-hero-card__label">Total Onboarding</span>
                    <a href="{{ route('admin.onboarded.index') }}" class="cg-hero-card__arrow" title="View onboarded">
                        <i class="fa-solid fa-arrow-up-right-from-square"></i>
                    </a>
                </div>
                <div class="cg-hero-card__val">{{ number_format($onbAchieved) }}</div>
                @if ($onbTarget > 0)
                    <div class="cg-hero-card__trend {{ $onbPct >= 100 ? 'cg-hero-card__trend--up' : 'cg-hero-card__trend--flat' }}">
                        <i class="fa-solid fa-bullseye"></i> {{ $onbPct }}% of {{ number_format($onbTarget) }} target
                    </div>
                @else
                    <div class="cg-hero-card__trend cg-hero-card__trend--flat">Locked hub members</div>
                @endif
                <div class="cg-hero-card__foot">
                    <span>Phase 3 · locked batches</span>
                    <span class="cg-hero-card__pill">{{ $fyLabel }}</span>
                </div>
            </div>

            {{-- Card 3: Business Stage Mix (Early / Seed / Growth) --}}
            <div class="cg-hero-card">
                <div class="cg-hero-card__top">
                    <span class="cg-hero-icon cg-hero-icon--pink" aria-hidden="true">
                        <i class="fa-solid fa-seedling"></i>
                    </span>
                    <span class="cg-hero-card__label">Stage Mix</span>
                    <button class="cg-hero-card__arrow" onclick="document.querySelector('[data-sad-tab=overview]')?.click();" title="View stage mix">
                        <i class="fa-solid fa-arrow-up-right-from-square"></i>
                    </button>
                </div>
                {{-- Big number = total CFA with stage data --}}
                <div class="cg-hero-card__val" style="font-size:1.55rem;">{{ number_format($sStageSum) }}</div>
                {{-- Stage mix badge --}}
                @if ($sStageSum > 0)
                    <div class="cg-hero-card__trend {{ $stagePolicyWithinTolerance ? 'cg-hero-card__trend--up' : 'cg-hero-card__trend--flat' }}">
                        @if ($stagePolicyWithinTolerance)
                            <i class="fa-solid fa-circle-check"></i> Within policy mix
                        @else
                            <i class="fa-solid fa-triangle-exclamation"></i> {{ $stagePolicyDeviationTotal }}pp drift
                        @endif
                    </div>
                @else
                    <div class="cg-hero-card__trend cg-hero-card__trend--flat">No stage data yet</div>
                @endif
                {{-- Early / Seed / Growth mini bars --}}
                <div style="display:flex;flex-direction:column;gap:0.3rem;margin:0.6rem 0 0.5rem;">
                    @php
                        $stageColorMap = ['EARLY' => '#ff9500', 'SEED' => '#d97706', 'GROWTH' => '#26a69a'];
                        $stageLabelMap = ['EARLY' => 'Early', 'SEED' => 'Seed', 'GROWTH' => 'Growth'];
                    @endphp
                    @foreach (['EARLY','SEED','GROWTH'] as $sk)
                        @php
                            $skCount = $sStageTotals[$sk] ?? 0;
                            $skPct   = $sStagePct[$sk] ?? 0;
                            $skColor = $stageColorMap[$sk];
                        @endphp
                        <div style="display:flex;align-items:center;gap:0.45rem;font-size:0.72rem;">
                            <span style="width:3.2rem;font-weight:700;color:#3c3c43;">{{ $stageLabelMap[$sk] }}</span>
                            <div style="flex:1;height:5px;border-radius:999px;background:#f2f2f7;overflow:hidden;">
                                <div style="height:100%;border-radius:999px;background:{{ $skColor }};width:{{ $skPct }}%;"></div>
                            </div>
                            <span style="font-weight:800;color:#1c1c1e;min-width:2.4rem;text-align:right;">{{ number_format($skCount) }}</span>
                            <span style="color:#8e8e93;min-width:2rem;text-align:right;">{{ $skPct }}%</span>
                        </div>
                    @endforeach
                </div>
                <div class="cg-hero-card__foot">
                    <span>CFA with stage data</span>
                    <span class="cg-hero-card__pill">Policy: E60·S30·G10</span>
                </div>
            </div>
        </div>

        {{-- ═══════════════════════════════════════════════
             SECONDARY CHIPS — 5 smaller KPIs
             ═══════════════════════════════════════════════ --}}
        <div class="cg-chips-row" role="group" aria-label="Secondary KPIs">
            <div class="cg-chip cg-chip--today" title="Today's CFA vs yesterday ({{ number_format($cfaYesterdayCount) }})">
                <div class="cg-chip-ico cg-chip-ico--yellow"><i class="fa-solid fa-sun"></i></div>
                <div class="cg-chip-body">
                    <div class="cg-chip-label">CFA Today</div>
                    <div class="cg-chip-val @if ($todayDelta > 0) is-up @elseif ($todayDelta < 0) is-down @endif">
                        {{ number_format($cfaTodayCount) }}
                    </div>
                </div>
            </div>
            <div class="cg-chip cg-chip--onboard" title="{{ $onbTarget > 0 ? number_format($onbAchieved).' / '.number_format($onbTarget).' locked hub members' : 'Locked hub members' }}">
                <div class="cg-chip-ico cg-chip-ico--rose"><i class="fa-solid fa-user-check"></i></div>
                <div class="cg-chip-body">
                    <div class="cg-chip-label">Onboarding</div>
                    <div class="cg-chip-val">
                        @if ($onbTarget > 0){{ $onbPct }}%@else{{ number_format($onbAchieved) }}@endif
                    </div>
                </div>
            </div>
            <div class="cg-chip cg-chip--target" title="{{ $cfaTargetN !== null ? number_format($cfaTotalN).' / '.number_format($cfaTargetN).' toward state target' : 'No CFA target set' }}">
                <div class="cg-chip-ico cg-chip-ico--amber"><i class="fa-solid fa-bullseye"></i></div>
                <div class="cg-chip-body">
                    <div class="cg-chip-label">Target</div>
                    <div class="cg-chip-val">{{ $ringPct }}%</div>
                </div>
            </div>
            <div class="cg-chip cg-chip--blocks" title="{{ number_format((int) ($insights['geo']['blocks'] ?? 0)) }} blocks · {{ number_format($hubsCount) }} hubs">
                <div class="cg-chip-ico cg-chip-ico--green"><i class="fa-solid fa-map-pin"></i></div>
                <div class="cg-chip-body">
                    <div class="cg-chip-label">Hubs / Blocks</div>
                    <div class="cg-chip-val">{{ number_format($hubsCount) }} / {{ number_format((int) ($insights['geo']['blocks'] ?? 0)) }}</div>
                </div>
            </div>
            <div class="cg-chip cg-chip--savings" title="Estimated savings from approved services this FY">
                <div class="cg-chip-ico cg-chip-ico--purple"><i class="fa-solid fa-piggy-bank"></i></div>
                <div class="cg-chip-body">
                    <div class="cg-chip-label">Savings FY</div>
                    <div class="cg-chip-val">₹{{ number_format($savingsTotalThisFy, 0) }}</div>
                </div>
            </div>
        </div>

        {{-- Plan alignment alert --}}
        @if ($planPct !== null && ! ($plan['all_aligned'] ?? false))
            <div class="cg-alerts-row">
                <span class="cg-alert cg-alert--warn">
                    <i class="fa-solid fa-scale-balanced" aria-hidden="true"></i>
                    CFA + services: {{ (int) $planPct }}% deliverables aligned
                    ({{ (int) ($plan['aligned_count'] ?? 0) }}/{{ (int) ($plan['tracked_count'] ?? 0) }})
                </span>
            </div>
        @endif

        {{-- ═══════════════════════════════════════════════
             PILL TAB NAVIGATION
             ═══════════════════════════════════════════════ --}}
        <nav class="cg-tabs" aria-label="Dashboard sections">
            <button type="button" class="cg-tab is-active" data-sad-tab="overview">
                <i class="fa-solid fa-gauge-high" aria-hidden="true"></i> Overview
            </button>
            <button type="button" class="cg-tab" data-sad-tab="insights">
                <i class="fa-solid fa-chart-pie" aria-hidden="true"></i> Insights
            </button>
            <button type="button" class="cg-tab" data-sad-tab="districts">
                <i class="fa-solid fa-map" aria-hidden="true"></i> Districts
            </button>
            <button type="button" class="cg-tab" data-sad-tab="team">
                <i class="fa-solid fa-users-gear" aria-hidden="true"></i> Team
            </button>
            <button type="button" class="cg-tab" data-sad-tab="impact">
                <i class="fa-solid fa-seedling" aria-hidden="true"></i> Impact &amp; Savings
            </button>
        </nav>

        {{-- ═══════════════════════════════════════════════
             OVERVIEW PANEL — 3 columns like reference
             ═══════════════════════════════════════════════ --}}
        <section class="cg-panel sad-panel is-active" data-sad-panel="overview">

            {{-- Main 3-column row --}}
            <div class="cg-main-row">

                {{-- Column 1: Program Intelligence (Task Progress style) --}}
                <div class="cg-card">
                    <div class="cg-card__head">
                        <h2 class="cg-card__title">
                            <i class="fa-solid fa-lightbulb" aria-hidden="true"></i> Program Intelligence
                        </h2>
                        @if ($achPct !== null)
                            <span class="cg-card__tag">{{ $achPct }}% achieved</span>
                        @endif
                    </div>

                    @if ($achPct !== null)
                        <div class="cg-prog-big">{{ $achPct }}%</div>
                        <div class="cg-prog-sub">Average CFA target achievement</div>
                    @endif

                    @if ($cfaTargetN !== null && $cfaTargetN > 0)
                        <div class="cg-progress">
                            <div class="cg-progress__top">
                                <span>CFA vs state target</span>
                                <strong>{{ number_format($cfaTotalN) }} / {{ number_format($cfaTargetN) }}</strong>
                            </div>
                            <div class="cg-progress__track">
                                <div class="cg-progress__fill cg-progress__fill--teal" style="width: {{ min(100, max(0, $achPct ?? 0)) }}%;"></div>
                            </div>
                            <p class="cg-progress__foot">Gap to target: <strong>{{ number_format($insGap) }}</strong> applications.</p>
                        </div>

                        @if ($planPct !== null)
                            <div class="cg-progress">
                                <div class="cg-progress__top">
                                    <span>District plan alignment</span>
                                    <strong>{{ (int) $planPct }}%</strong>
                                </div>
                                <div class="cg-progress__track">
                                    <div class="cg-progress__fill cg-progress__fill--blue" style="width: {{ min(100, (int) $planPct) }}%;"></div>
                                </div>
                                <p class="cg-progress__foot">
                                    <strong>{{ (int) ($plan['aligned_count'] ?? 0) }} of {{ (int) ($plan['tracked_count'] ?? 0) }}</strong> deliverables aligned.
                                    @if ($activeFy)
                                        <a href="{{ route('admin.targets.district', ['fiscal_year_id' => $activeFy->id]) }}">District targets →</a>
                                    @endif
                                </p>
                                <div class="cg-align-status">
                                    @if ($planCfa['tracked'] ?? false)
                                        <span class="cg-align-pill {{ ($planCfa['aligned'] ?? false) ? 'cg-align-pill--ok' : 'cg-align-pill--bad' }}">
                                            CFA {{ ($planCfa['aligned'] ?? false) ? 'aligned' : 'mismatch' }}
                                            ({{ number_format((int) ($planCfa['district'] ?? 0)) }}/{{ number_format((int) ($planCfa['state'] ?? 0)) }})
                                        </span>
                                    @else
                                        <span class="cg-align-pill cg-align-pill--bad">CFA target not set</span>
                                    @endif
                                    @if (($planSvc['tracked_count'] ?? 0) > 0)
                                        <span class="cg-align-pill {{ ($planSvc['all_aligned'] ?? false) ? 'cg-align-pill--ok' : 'cg-align-pill--bad' }}">
                                            Services {{ (int) ($planSvc['aligned_count'] ?? 0) }}/{{ (int) ($planSvc['tracked_count'] ?? 0) }} aligned
                                        </span>
                                    @else
                                        <span class="cg-align-pill cg-align-pill--bad">No service targets set</span>
                                    @endif
                                </div>
                                @if (count($planMisaligned) > 0)
                                    <details class="cg-details" style="margin-top:0.35rem;">
                                        <summary>{{ count($planMisaligned) }} deliverable(s) need district fix</summary>
                                        <ul class="cg-align-gaps">
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
                                    <p class="cg-progress__foot" style="color:#1b5e20;margin-top:0.35rem;">
                                        <i class="fa-solid fa-circle-check" aria-hidden="true"></i> All CFA and service targets aligned.
                                    </p>
                                @endif
                            </div>
                        @elseif ($cfaTargetN !== null && $cfaTargetN > 0)
                            <p class="cg-progress__foot">Set service targets in <a href="{{ route('admin.targets.state') }}">State targets</a> for full alignment view.</p>
                        @endif
                    @else
                        <p class="cg-progress__foot">Configure CFA state target in <a href="{{ route('admin.targets.state') }}">State targets</a>.</p>
                    @endif

                    @if ($onbTarget > 0)
                        <div class="cg-progress">
                            <div class="cg-progress__top">
                                <span>Onboarding (locked batches)</span>
                                <strong>{{ number_format($onbAchieved) }} / {{ number_format($onbTarget) }}</strong>
                            </div>
                            <div class="cg-progress__track">
                                <div class="cg-progress__fill cg-progress__fill--pink" style="width: {{ min(100, $onbPct) }}%;"></div>
                            </div>
                            <p class="cg-progress__foot">Remaining gap: <strong>{{ number_format($onbGap) }}</strong>.</p>
                        </div>
                    @endif

                    {{-- Stage mix bars (like the colored bars in Task Progress card) --}}
                    @if ($sStageSum > 0)
                        <div class="cg-stage-mix">
                            <div class="cg-stage-mix__head">
                                <p class="cg-stage-mix__title">Stage mix vs policy (Early 60 · Seed 30 · Growth 10)</p>
                                <span class="cg-stage-badge {{ $stagePolicyWithinTolerance ? 'cg-stage-badge--ok' : 'cg-stage-badge--warn' }}">
                                    @if ($stagePolicyWithinTolerance)
                                        <i class="fa-solid fa-circle-check" aria-hidden="true"></i> On policy
                                    @else
                                        <i class="fa-solid fa-triangle-exclamation" aria-hidden="true"></i> {{ $stagePolicyDeviationTotal }}pp drift
                                    @endif
                                </span>
                            </div>
                            <p class="cg-stage-legend"><span class="cg-stage-legend-tick" aria-hidden="true"></span> Dark tick = policy target</p>
                            @foreach ($stagePolicyRows as $stageRow)
                                <div class="cg-stage-row">
                                    <div class="cg-stage-label-row">
                                        <span>{{ $stageRow['label'] }}</span>
                                        <span class="cg-stage-nums">{{ $stageRow['actual'] }}% · target {{ $stageRow['target'] }}%</span>
                                    </div>
                                    <div class="cg-stage-track" title="{{ $stageRow['label'] }}: {{ $stageRow['actual'] }}% actual vs {{ $stageRow['target'] }}% policy">
                                        <div class="cg-stage-track__fill cg-stage-fill--{{ strtolower($stageRow['key']) }}" style="width: {{ min(100, max(0, $stageRow['actual'])) }}%;"></div>
                                        <span class="cg-stage-track__target" style="left: {{ $stageRow['target'] }}%;" aria-hidden="true"></span>
                                    </div>
                                    <div class="cg-stage-foot">
                                        <span>{{ number_format($stageRow['count']) }} forms</span>
                                        <span class="cg-stage-delta is-{{ $stageRow['status'] }}">
                                            @if ($stageRow['status'] === 'ok') On target (±5pp)
                                            @elseif ($stageRow['delta'] > 0) +{{ $stageRow['delta'] }}pp above
                                            @else {{ $stageRow['delta'] }}pp below
                                            @endif
                                        </span>
                                    </div>
                                </div>
                            @endforeach
                        </div>
                    @endif
                </div>

                {{-- Column 2: CFA Status / Ring chart (Project Status style) --}}
                <div class="cg-card">
                    <div class="cg-card__head">
                        <h2 class="cg-card__title">
                            <i class="fa-solid fa-chart-pie" aria-hidden="true"></i> CFA Status
                        </h2>
                        <div class="cg-card-btn-group" aria-label="Chart view">
                            <button class="cg-icon-btn" title="Absolute">∑</button>
                            <button class="cg-icon-btn" title="Percentage">%</button>
                        </div>
                    </div>

                    {{-- Ring chart like Project Status donut --}}
                    <div class="cg-ring-wrap" aria-hidden="true">
                        <svg class="cg-ring-svg" viewBox="0 0 120 120">
                            <defs>
                                <linearGradient id="cgRingGrad" x1="0%" y1="0%" x2="100%" y2="100%">
                                    <stop offset="0%" stop-color="#00897b"/>
                                    <stop offset="100%" stop-color="#4db6ac"/>
                                </linearGradient>
                            </defs>
                            <circle class="cg-track" cx="60" cy="60" r="55"/>
                            <circle class="cg-bar" cx="60" cy="60" r="55"
                                stroke-dasharray="{{ round($ringCirc, 3) }}"
                                stroke-dashoffset="{{ round($ringOffset, 3) }}"/>
                            <text class="cg-pct" x="60" y="56" text-anchor="middle" dominant-baseline="middle">{{ $ringPct }}%</text>
                            <text class="cg-pct-sub" x="60" y="72" text-anchor="middle" dominant-baseline="middle">of target</text>
                        </svg>
                    </div>

                    {{-- Breakdown rows below ring --}}
                    <div class="cg-breakdown">
                        @php
                            $svcTillDate = (int)($servicesDeliveredTillDate ?? 0);
                            $svcThisFyBd = (int)($servicesDeliveredThisFy ?? 0);
                            $cfaLast30Bd = (int)($cfaLast30 ?? 0);
                            $cfaThisMonthBd = (int)($cfaThisMonth ?? 0);
                            $bdMax = max($cfaTotalN, $svcTillDate, $onbAchieved, 1);
                        @endphp
                        <div class="cg-breakdown-row">
                            <span class="cg-breakdown-dot cg-breakdown-dot--green"></span>
                            <span class="cg-breakdown-label">CFA Phase 3</span>
                            <div class="cg-breakdown-bar-wrap">
                                <div class="cg-breakdown-bar-track">
                                    <div class="cg-breakdown-bar-fill" style="width:{{ $bdMax > 0 ? min(100, round($cfaTotalN/$bdMax*100)) : 0 }}%; background: #34c759;"></div>
                                </div>
                            </div>
                            <span class="cg-breakdown-val">{{ number_format($cfaTotalN) }}</span>
                        </div>
                        <div class="cg-breakdown-row">
                            <span class="cg-breakdown-dot cg-breakdown-dot--blue"></span>
                            <span class="cg-breakdown-label">Services delivered</span>
                            <div class="cg-breakdown-bar-wrap">
                                <div class="cg-breakdown-bar-track">
                                    <div class="cg-breakdown-bar-fill" style="width:{{ $bdMax > 0 ? min(100, round($svcTillDate/$bdMax*100)) : 0 }}%; background: #007aff;"></div>
                                </div>
                            </div>
                            <span class="cg-breakdown-val">{{ number_format($svcTillDate) }}</span>
                        </div>
                        @if ($onbAchieved > 0)
                        <div class="cg-breakdown-row">
                            <span class="cg-breakdown-dot" style="background:#ff9500;"></span>
                            <span class="cg-breakdown-label">Onboarded</span>
                            <div class="cg-breakdown-bar-wrap">
                                <div class="cg-breakdown-bar-track">
                                    <div class="cg-breakdown-bar-fill" style="width:{{ $bdMax > 0 ? min(100, round($onbAchieved/$bdMax*100)) : 0 }}%; background: #ff9500;"></div>
                                </div>
                            </div>
                            <span class="cg-breakdown-val">{{ number_format($onbAchieved) }}</span>
                        </div>
                        @endif
                        @if ($cfaTargetN !== null && $cfaTargetN > 0)
                        <div class="cg-breakdown-row">
                            <span class="cg-breakdown-dot" style="background:#e5e5ea;"></span>
                            <span class="cg-breakdown-label" style="color:#8e8e93;">Gap to target</span>
                            <div class="cg-breakdown-bar-wrap">
                                <div class="cg-breakdown-bar-track">
                                    <div class="cg-breakdown-bar-fill" style="width:{{ $cfaTargetN > 0 ? min(100, round($insGap/$cfaTargetN*100)) : 0 }}%; background: #e5e5ea;"></div>
                                </div>
                            </div>
                            <span class="cg-breakdown-val" style="color:#8e8e93;">{{ number_format($insGap) }}</span>
                        </div>
                        @endif
                    </div>

                    {{-- Signal grid --}}
                    <div class="cg-signals" style="margin-top:0.85rem;">
                        <div class="cg-signal">
                            <span>Last 7 days CFA</span>
                            <strong>{{ number_format((int) ($cfaLast7 ?? 0)) }}</strong>
                        </div>
                        <div class="cg-signal">
                            <span>This month</span>
                            <strong>{{ number_format((int) ($cfaThisMonth ?? 0)) }}</strong>
                        </div>
                        <div class="cg-signal">
                            <span>Deliverables</span>
                            <strong>{{ number_format($deliverablesCount) }}</strong>
                        </div>
                        <div class="cg-signal">
                            <span>Week-over-week</span>
                            <strong>
                                <span class="cg-chip-badge {{ ($cfaWoWDeltaPct ?? 0) > 0 ? 'up' : (($cfaWoWDeltaPct ?? 0) < 0 ? 'down' : 'flat') }}">
                                    {{ ($cfaWoWDeltaPct ?? 0) > 0 ? '+' : '' }}{{ (int) ($cfaWoWDeltaPct ?? 0) }}%
                                </span>
                            </strong>
                        </div>
                    </div>
                </div>

                {{-- Column 3: State Pulse / Trend chart (Productivity Trend style) --}}
                <div class="cg-card">
                    <div class="cg-card__head">
                        <h2 class="cg-card__title">
                            <i class="fa-solid fa-wave-square" aria-hidden="true"></i> State Pulse
                        </h2>
                        <span class="cg-card__tag">FY pace</span>
                    </div>

                    <div class="cg-pulse-tabs" role="tablist" aria-label="State pulse chart views">
                        <button type="button" class="cg-pulse-tab is-active" data-sad-pulse-tab="pace" aria-selected="true">Pace %</button>
                        <button type="button" class="cg-pulse-tab" data-sad-pulse-tab="cfa" aria-selected="false">CFA</button>
                        <button type="button" class="cg-pulse-tab" data-sad-pulse-tab="onboarding" aria-selected="false">Onboarding</button>
                        <button type="button" class="cg-pulse-tab" data-sad-pulse-tab="daily" aria-selected="false">Daily 14d</button>
                    </div>
                    <p class="cg-pulse-hint" data-sad-pulse-hint>
                        Cumulative achievement vs prorated FY target — 100% line means on pace.
                        @php $paceCfaTarget = $stateFyPaceChart['cfa_target'] ?? null; $paceOnbTarget = $stateFyPaceChart['onboarding_target'] ?? null; @endphp
                        @if ($paceCfaTarget)
                            CFA target {{ number_format((int) $paceCfaTarget) }}@if ($paceOnbTarget) · Onboard target {{ number_format((int) $paceOnbTarget) }}@endif.
                        @endif
                    </p>
                    <div class="cg-chart-box cg-chart-box--pulse">
                        <canvas id="stateTrendCurveChart" aria-label="CFA and onboarding pace chart"></canvas>
                    </div>

                    {{-- Totals footer like reference's "Total active time / pause time" --}}
                    <div class="cg-pulse-totals">
                        <div>
                            <div class="cg-pulse-total-label">Last 7 days CFA</div>
                            <div class="cg-pulse-total-val">
                                {{ number_format((int) ($cfaLast7 ?? 0)) }}
                            </div>
                        </div>
                        <div>
                            <div class="cg-pulse-total-label">Week-over-week</div>
                            <div class="cg-pulse-total-val {{ ($cfaWoWDeltaPct ?? 0) >= 0 ? 'nd-up' : 'nd-down' }}">
                                {{ ($cfaWoWDeltaPct ?? 0) > 0 ? '+' : '' }}{{ (int) ($cfaWoWDeltaPct ?? 0) }}%
                                <span class="cg-trend-arrow">{{ ($cfaWoWDeltaPct ?? 0) >= 0 ? '↑' : '↓' }}</span>
                            </div>
                        </div>
                    </div>
                </div>

            </div>

            {{-- Business category mix (full width card below 3 cols) --}}
            <div class="cg-card cg-card--full">
                <div class="cg-card__head">
                    <h2 class="cg-card__title">
                        <i class="fa-solid fa-chart-bar" aria-hidden="true"></i> Business Category Mix
                    </h2>
                    <span class="cg-card__tag">{{ number_format($bizMixTotal) }} apps</span>
                </div>
                @if (count($businessMix['labels'] ?? []) === 0)
                    <div class="cg-empty">No category data yet</div>
                @else
                    <div style="columns: 2; gap: 1.25rem; column-fill: balance;">
                        @foreach ($businessMix['labels'] as $idx => $label)
                            @php
                                $bizV = (int) ($businessMix['values'][$idx] ?? 0);
                                $bizPct = $bizMixTotal > 0 ? (int) round(100 * $bizV / $bizMixTotal) : 0;
                                $bizCol = $businessMix['colors'][$idx] ?? '#26a69a';
                            @endphp
                            <div class="cg-biz-row" style="break-inside: avoid;">
                                <span class="cg-biz-rank">#{{ $idx + 1 }}</span>
                                <div style="min-width:0;">
                                    <div class="cg-biz-label">
                                        <i class="fa-solid {{ $bizIconFor((string) $label) }}" aria-hidden="true" style="color:{{ $bizCol }};margin-right:0.25rem;"></i>
                                        {{ $label }}
                                    </div>
                                    <div class="cg-biz-track">
                                        <div class="cg-biz-fill" style="width:{{ $bizPct }}%;background:{{ $bizCol }};"></div>
                                    </div>
                                </div>
                                <span class="cg-biz-nums">{{ $bizPct }}% · {{ number_format($bizV) }}</span>
                            </div>
                        @endforeach
                    </div>
                @endif
            </div>
        </section>

        {{-- ═══════════════════════════════════════════════
             INSIGHTS PANEL
             ═══════════════════════════════════════════════ --}}
        @include('dashboards.state-admin._insights-panel')

        {{-- ═══════════════════════════════════════════════
             DISTRICTS PANEL
             ═══════════════════════════════════════════════ --}}
        <section class="cg-panel sad-panel" data-sad-panel="districts">
            <div class="cg-district-cards">
                @foreach ($topDistricts as $i => $d)
                    <div class="cg-district-card @if ($i === 0) is-top @endif">
                        <div class="cg-district-card__name">
                            @if ($i === 0)<i class="fa-solid fa-crown" style="color:#ff9500;margin-right:0.2rem;" aria-hidden="true"></i>@endif
                            {{ $d['name'] }}
                        </div>
                        <div class="cg-district-card__val">{{ number_format($d['total']) }}</div>
                    </div>
                @endforeach
            </div>

            <div class="cg-grid-2" style="margin-bottom:0.85rem;">
                @include('dashboards.state-admin._district-target-chart', ['insights' => $insights ?? []])
                <div class="cg-card">
                    <h2 class="cg-card__title"><i class="fa-solid fa-layer-group" aria-hidden="true"></i> Top blocks by CFA</h2>
                    <p class="cg-card__hint">Top 12 blocks in Phase 3 scope</p>
                    <div class="cg-chart-box cg-chart-box--tall">
                        <canvas id="chartTopBlocks"></canvas>
                    </div>
                </div>
            </div>

            <div class="cg-grid-2">
                <div class="cg-card">
                    <h2 class="cg-card__title"><i class="fa-solid fa-chart-bar" aria-hidden="true"></i> Applications by district</h2>
                    <p class="cg-card__hint">Phase 3 CFA from {{ $phaseLabel }}</p>
                    <div class="cg-chart-box cg-chart-box--tall">
                        <canvas id="chartDistrictCfa"></canvas>
                    </div>
                </div>
                <div class="cg-card">
                    <h2 class="cg-card__title"><i class="fa-solid fa-map-pin" aria-hidden="true"></i> District signals</h2>
                    <div class="cg-signals" style="margin-bottom:0.65rem;">
                        <div class="cg-signal">
                            <span>Top district today</span>
                            <strong>{{ $todayTopDistrict['name'] ?? '—' }} @if(isset($todayTopDistrict['count'])) ({{ number_format((int) $todayTopDistrict['count']) }}) @endif</strong>
                        </div>
                        <div class="cg-signal">
                            <span>Lowest active today</span>
                            <strong>
                                @if ($todayLowestActiveDistrict)
                                    {{ $todayLowestActiveDistrict['name'] }} ({{ number_format((int) $todayLowestActiveDistrict['count']) }})
                                @else —
                                @endif
                            </strong>
                        </div>
                    </div>
                    <h3 class="cg-card__title" style="font-size:0.8rem;margin-bottom:0.45rem;">
                        <i class="fa-solid fa-user-group" aria-hidden="true"></i> Onboarding by district
                    </h3>
                    <div class="cg-split-table">
                        @forelse ($onbDistrictRows as $row)
                            @php $rowCount = (int) ($row['count'] ?? 0); @endphp
                            <div class="cg-split-row @if ($rowCount === 0) is-zero @endif">
                                <span>{{ $row['district'] }}</span>
                                <strong>{{ number_format($rowCount) }}</strong>
                            </div>
                        @empty
                            <div class="cg-empty">No district onboarding data</div>
                        @endforelse
                    </div>
                </div>
            </div>
        </section>

        {{-- ═══════════════════════════════════════════════
             TEAM PANEL
             ═══════════════════════════════════════════════ --}}
        <section class="cg-panel sad-panel" data-sad-panel="team">
            <div class="cg-card" style="margin-bottom:0.85rem;">
                <h2 class="cg-card__title"><i class="fa-solid fa-chart-bar" aria-hidden="true"></i> Top staff by CFA</h2>
                <p class="cg-card__hint">Referral-linked CFA · top 10</p>
                <div class="cg-chart-box cg-chart-box--tall">
                    <canvas id="chartStaffTop"></canvas>
                </div>
            </div>
            <div class="cg-card">
                <div class="cg-card__head">
                    <h2 class="cg-card__title"><i class="fa-solid fa-ranking-star" aria-hidden="true"></i> CFA by district staff</h2>
                    <span class="cg-card__tag">{{ count($staffCfaRows) }} rows</span>
                </div>
                <p class="cg-card__hint">Referral-linked CFA aligned to staff district · Phase 3 from {{ $phaseLabel }}</p>
                <div class="cg-staff-controls">
                    <input type="text" id="stateStaffCfaSearch" placeholder="Search staff name…" autocomplete="off">
                    <select id="stateStaffCfaDistrictFilter">
                        <option value="">All districts</option>
                        @foreach ($staffDistrictOptions as $districtName)
                            <option value="{{ strtolower($districtName) }}">{{ $districtName }}</option>
                        @endforeach
                    </select>
                </div>
                <div class="cg-staff-list" id="stateStaffCfaList">
                    @forelse ($staffCfaRows as $index => $row)
                        <div class="cg-staff-row sad-staff-row"
                            data-name="{{ strtolower($row['name']) }}"
                            data-district="{{ strtolower($row['district']) }}">
                            <span class="cg-staff-rank @if ($index < 3) is-medal @endif">#{{ $index + 1 }}</span>
                            <div class="cg-staff-main">
                                @if (!empty($row['avatar_url']))
                                    <img src="{{ $row['avatar_url'] }}" alt="" class="cg-staff-avatar">
                                @else
                                    <span class="cg-staff-fallback">{{ strtoupper(substr(trim((string) $row['name']), 0, 1)) ?: '?' }}</span>
                                @endif
                                <div style="min-width:0;">
                                    <div class="cg-staff-name">{{ $row['name'] }}</div>
                                    <div class="cg-staff-district">{{ $row['district'] }}</div>
                                </div>
                            </div>
                            <span class="cg-staff-val">{{ number_format((int) $row['cfa_total']) }}</span>
                        </div>
                    @empty
                        <div class="cg-empty">No staff data yet</div>
                    @endforelse
                    <div class="cg-empty" id="stateStaffCfaNoResults" style="display:none;">No matches for this filter</div>
                </div>
            </div>
        </section>

        {{-- ═══════════════════════════════════════════════
             IMPACT & SAVINGS PANEL
             ═══════════════════════════════════════════════ --}}
        <section class="cg-panel sad-panel" data-sad-panel="impact">
            <div class="cg-savings-grid">
                <div class="cg-savings-tile cg-savings-tile--green">
                    <div class="cg-savings-tile__lbl" style="color:#1b5e20;">Total till date</div>
                    <div class="cg-savings-tile__val" style="color:#1b5e20;">₹{{ number_format($savingsTotalTillDate, 2) }}</div>
                </div>
                <div class="cg-savings-tile cg-savings-tile--blue">
                    <div class="cg-savings-tile__lbl" style="color:#0d47a1;">Estimated this FY</div>
                    <div class="cg-savings-tile__val" style="color:#0d47a1;">₹{{ number_format($savingsTotalThisFy, 2) }}</div>
                </div>
                <div class="cg-savings-tile cg-savings-tile--teal">
                    <div class="cg-savings-tile__lbl" style="color:#00695c;">Active deliverables</div>
                    <div class="cg-savings-tile__val" style="color:#00695c;">{{ number_format($deliverablesCount) }}</div>
                </div>
            </div>
            <div class="cg-card">
                <h2 class="cg-card__title"><i class="fa-solid fa-hand-holding-heart" aria-hidden="true"></i> Top services by estimated savings</h2>
                <p class="cg-card__hint">Approved service cases × configured average market price</p>
                <div class="cg-table-wrap">
                    <table class="cg-table">
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
                                    <td>₹{{ number_format((float) $svc['avg_price'], 2) }}</td>
                                    <td><strong>₹{{ number_format((float) $svc['savings'], 2) }}</strong></td>
                                </tr>
                            @empty
                                <tr>
                                    <td colspan="4" class="cg-empty">No savings data yet — configure service market prices.</td>
                                </tr>
                            @endforelse
                        </tbody>
                    </table>
                </div>
                <p class="cg-card__hint" style="margin-top:0.5rem;margin-bottom:0;">
                    <a href="{{ route('admin.phase3-services.index') }}" style="color:var(--cg-teal);">View Phase 3 service cases</a>
                    · <a href="{{ route('admin.deliverables.index') }}" style="color:var(--cg-teal);">Deliverables report</a>
                </p>
            </div>
        </section>

        {{-- ═══════════════════════════════════════════════
             QUICK COMMAND DOCK
             ═══════════════════════════════════════════════ --}}
        <div class="cg-dock">
            <p class="cg-dock__title">Quick command links</p>
            <div class="cg-dock__links">
                <a class="cg-dock__link" href="{{ route('admin.cfa.index') }}"><i class="fa-solid fa-clipboard-list"></i> CFA</a>
                <a class="cg-dock__link" href="{{ route('admin.targets.state') }}"><i class="fa-solid fa-bullseye"></i> State targets</a>
                <a class="cg-dock__link" href="{{ route('admin.targets.state-monthly') }}"><i class="fa-solid fa-calendar-days"></i> State monthly</a>
                <a class="cg-dock__link" href="{{ route('admin.targets.district') }}"><i class="fa-solid fa-map-location-dot"></i> District targets</a>
                <a class="cg-dock__link" href="{{ route('admin.targets.allocate-by-service') }}"><i class="fa-solid fa-sliders"></i> Allocate by service</a>
                <a class="cg-dock__link" href="{{ route('admin.state-tasks.index') }}"><i class="fa-solid fa-list-check"></i> State tasks</a>
                <a class="cg-dock__link" href="{{ route('admin.staff.index') }}"><i class="fa-solid fa-user-tie"></i> Staff</a>
                <a class="cg-dock__link" href="{{ route('admin.attendance.index') }}"><i class="fa-solid fa-calendar-check"></i> Field attendance</a>
                <a class="cg-dock__link" href="{{ route('admin.staff-check-ins.index') }}"><i class="fa-solid fa-location-dot"></i> Staff check-ins</a>
                <a class="cg-dock__link" href="{{ route('admin.live-map.index') }}"><i class="fa-solid fa-map-location-dot"></i> Live map</a>
                <a class="cg-dock__link" href="{{ route('admin.data-centre.index') }}"><i class="fa-solid fa-database"></i> Data centre</a>
                <a class="cg-dock__link" href="{{ route('admin.deliverables.index') }}"><i class="fa-solid fa-chart-column"></i> Deliverables</a>
                <a class="cg-dock__link" href="{{ route('admin.onboarded.index') }}"><i class="fa-solid fa-user-check"></i> Onboarded</a>
                <a class="cg-dock__link" href="{{ route('team.index') }}"><i class="fa-solid fa-people-group"></i> Team</a>
                <a class="cg-dock__link" href="{{ route('library.documents.index') }}"><i class="fa-solid fa-folder-open"></i> Documents</a>
                <a class="cg-dock__link" href="{{ route('admin.audit.index') }}"><i class="fa-solid fa-scroll"></i> Audit</a>
            </div>
        </div>

    </main>

@include('dashboards.state-admin._chart-scripts')

@include('partials.app-footer')
</body>
</html>
