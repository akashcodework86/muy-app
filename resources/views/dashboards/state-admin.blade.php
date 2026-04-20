<!DOCTYPE html>
<html lang="{{ str_replace('_', '-', app()->getLocale()) }}">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>State Admin — {{ config('app.name') }}</title>
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=DM+Sans:ital,opsz,wght@0,9..40,400;0,9..40,500;0,9..40,600;0,9..40,700&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.1/css/all.min.css" crossorigin="anonymous" referrerpolicy="no-referrer">
    <script src="https://cdn.jsdelivr.net/npm/chart.js@4.4.1/dist/chart.umd.min.js" crossorigin="anonymous"></script>
    @include('partials.admin-shell-styles')
    <style>
        :root {
            --text: #0f172a;
            --text-muted: #5f6f86;
            --glass-border: rgba(255, 255, 255, 0.1);
            --radius: 24px;
            --shadow: 0 24px 60px rgba(99, 102, 241, 0.12);
            --border: rgba(255, 255, 255, 0.72);
        }
        .dashboard-shell {
            position: relative;
            max-width: 100%;
            overflow-x: clip;
            box-sizing: border-box;
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
        .dashboard-shell > * { position: relative; z-index: 1; }
        .glass-surface {
            backdrop-filter: blur(30px);
            -webkit-backdrop-filter: blur(30px);
            background: linear-gradient(135deg, rgba(255, 255, 255, 0.46) 0%, rgba(255, 255, 255, 0.28) 100%);
            border: 1px solid var(--glass-border);
            box-shadow: 0 8px 32px rgba(31, 38, 135, 0.08), inset 0 1px 0 rgba(255, 255, 255, 0.5);
        }
        .dashboard-intro {
            position: relative;
            display: flex;
            flex-wrap: wrap;
            align-items: stretch;
            justify-content: space-between;
            gap: 1.5rem;
            margin-bottom: 1.5rem;
            padding: 1.75rem;
            border-radius: 32px;
            background: linear-gradient(135deg, rgba(255, 255, 255, 0.86), rgba(255, 255, 255, 0.58)),
                linear-gradient(120deg, rgba(79, 70, 229, 0.08), rgba(45, 212, 191, 0.04));
            border: 1px solid rgba(255, 255, 255, 0.7);
            box-shadow: var(--shadow);
            overflow: hidden;
        }
        .dashboard-intro h2 {
            font-family: 'DM Sans', sans-serif;
            font-size: clamp(1.65rem, 4vw, 2.5rem);
            font-weight: 800;
            margin: 0;
            letter-spacing: -0.04em;
            color: var(--text);
            line-height: 1.05;
        }
        .dashboard-intro p { margin: 0.75rem 0 0; color: var(--text-muted); font-size: 1rem; max-width: 44rem; line-height: 1.6; }
        .dashboard-intro__eyebrow {
            display: inline-flex;
            align-items: center;
            gap: 0.45rem;
            padding: 0.45rem 0.8rem;
            border-radius: 999px;
            background: rgba(255, 255, 255, 0.85);
            border: 1px solid rgba(99, 102, 241, 0.15);
            color: #5b21b6;
            font-size: 0.76rem;
            font-weight: 700;
            text-transform: uppercase;
            letter-spacing: 0.08em;
            margin-bottom: 0.85rem;
        }
        /* Hero 2-column split */
        .dashboard-intro__grid { display: grid; grid-template-columns: minmax(0, 1fr) 340px; gap: 1.5rem; align-items: center; width: 100%; }
        @media (max-width: 960px) { .dashboard-intro__grid { grid-template-columns: 1fr; } }
        .dashboard-intro__left { min-width: 0; }
        .dashboard-intro__right { display: flex; flex-direction: column; gap: 0.8rem; min-width: 0; }

        /* Hero progress ring card */
        .hero-ring-card { position: relative; padding: 1rem 1.1rem; border-radius: 20px; background: linear-gradient(145deg, rgba(255, 255, 255, 0.95), rgba(238, 242, 255, 0.55)); border: 1px solid rgba(255, 255, 255, 0.85); box-shadow: 0 12px 30px rgba(79, 70, 229, 0.08); display: grid; grid-template-columns: auto 1fr; gap: 0.9rem; align-items: center; }
        .hero-ring-svg { width: 98px; height: 98px; flex-shrink: 0; }
        .hero-ring-svg .track { fill: none; stroke: rgba(148, 163, 184, 0.2); stroke-width: 9; }
        .hero-ring-svg .bar { fill: none; stroke: url(#heroRingGrad); stroke-width: 9; stroke-linecap: round; transform: rotate(-90deg); transform-origin: 50% 50%; transition: stroke-dashoffset 900ms cubic-bezier(0.22, 1, 0.36, 1); }
        .hero-ring-svg .pct { font-family: 'DM Sans', sans-serif; font-weight: 800; fill: #0f172a; font-size: 20px; }
        .hero-ring-svg .pct-sub { font-size: 7px; font-weight: 700; fill: #64748b; letter-spacing: 1px; }
        .hero-ring-body__eyebrow { font-size: 0.55rem; font-weight: 800; text-transform: uppercase; letter-spacing: 0.1em; color: #4f46e5; margin-bottom: 0.2rem; }
        .hero-ring-body__value { font-family: 'DM Sans', sans-serif; font-size: 1.1rem; font-weight: 800; color: #0f172a; line-height: 1.1; }
        .hero-ring-body__value small { color: #94a3b8; font-weight: 600; font-size: 0.7rem; letter-spacing: 0.02em; }
        .hero-ring-body__label { display: block; font-size: 0.68rem; color: #64748b; margin-top: 0.2rem; font-weight: 600; }
        .hero-ring-body__gap { display: inline-flex; align-items: center; gap: 0.25rem; margin-top: 0.35rem; padding: 0.2rem 0.5rem; border-radius: 999px; background: rgba(99, 102, 241, 0.1); color: #4338ca; font-size: 0.6rem; font-weight: 700; }
        .hero-ring-body__gap.is-good { background: rgba(34, 197, 94, 0.12); color: #15803d; }

        /* Hero "today" pills */
        .hero-today-row { display: grid; grid-template-columns: repeat(3, 1fr); gap: 0.45rem; }
        .hero-today { padding: 0.55rem 0.65rem; border-radius: 12px; background: rgba(255, 255, 255, 0.92); border: 1px solid rgba(226, 232, 240, 0.92); display: flex; flex-direction: column; gap: 0.15rem; transition: transform 200ms ease, box-shadow 200ms ease; position: relative; min-width: 0; }
        .hero-today:hover { transform: translateY(-2px); box-shadow: 0 8px 18px rgba(15, 23, 42, 0.08); }
        .hero-today__head { display: flex; align-items: center; gap: 0.35rem; font-size: 0.55rem; font-weight: 800; text-transform: uppercase; letter-spacing: 0.08em; color: #64748b; }
        .hero-today__head i { font-size: 0.7rem; }
        .hero-today__value { font-family: 'DM Sans', sans-serif; font-size: 1.25rem; font-weight: 800; color: #0f172a; line-height: 1; letter-spacing: -0.02em; }
        .hero-today__delta { font-size: 0.58rem; font-weight: 700; color: #64748b; display: inline-flex; align-items: center; gap: 0.15rem; }
        .hero-today__delta.is-up { color: #16a34a; }
        .hero-today__delta.is-down { color: #dc2626; }
        .hero-today--cfa .hero-today__head { color: #4338ca; }
        .hero-today--mentor .hero-today__head { color: #be185d; }
        .hero-today--online .hero-today__head { color: #15803d; }
        .hero-today--online::after { content: ''; position: absolute; top: 0.55rem; right: 0.55rem; width: 7px; height: 7px; border-radius: 999px; background: #22c55e; box-shadow: 0 0 0 3px rgba(34, 197, 94, 0.25); animation: heroDot 1.8s ease-in-out infinite; }
        @keyframes heroDot { 0%, 100% { box-shadow: 0 0 0 0 rgba(34, 197, 94, 0.55); } 50% { box-shadow: 0 0 0 5px rgba(34, 197, 94, 0); } }

        /* Hero sparkline */
        .hero-spark { padding: 0.6rem 0.8rem; border-radius: 14px; background: linear-gradient(145deg, rgba(255, 255, 255, 0.94), rgba(236, 254, 255, 0.55)); border: 1px solid rgba(226, 232, 240, 0.92); display: flex; align-items: center; gap: 0.7rem; min-width: 0; }
        .hero-spark__left { min-width: 0; flex-shrink: 0; }
        .hero-spark__eyebrow { font-size: 0.52rem; font-weight: 800; text-transform: uppercase; letter-spacing: 0.1em; color: #0891b2; line-height: 1; }
        .hero-spark__value { font-family: 'DM Sans', sans-serif; font-size: 0.95rem; font-weight: 800; color: #0f172a; margin-top: 0.2rem; line-height: 1; }
        .hero-spark__value small { color: #64748b; font-weight: 600; font-size: 0.62rem; margin-left: 0.2rem; }
        .hero-spark__chart { flex: 1; min-width: 0; height: 36px; position: relative; }
        .hero-spark__chart svg { width: 100%; height: 100%; overflow: visible; }
        .hero-spark__chart .spark-fill { fill: url(#heroSparkGrad); opacity: 0.55; }
        .hero-spark__chart .spark-line { fill: none; stroke: #0891b2; stroke-width: 1.6; stroke-linecap: round; stroke-linejoin: round; }
        .hero-spark__chart .spark-dot { fill: #0891b2; stroke: #fff; stroke-width: 1.5; }

        .welcome-meta-pills { display: flex; flex-wrap: wrap; gap: 0.75rem; margin-top: 1rem; }
        .welcome-meta-pill {
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
        .hero-three-col {
            display: grid;
            grid-template-columns: repeat(2, 1fr);
            gap: 0.85rem;
            margin-bottom: 1.5rem;
            align-items: stretch;
            width: 100%;
            max-width: 100%;
            min-width: 0;
        }
        @media (max-width: 1100px) { .hero-three-col { grid-template-columns: 1fr; } }
        .hero-col {
            border-radius: 16px;
            padding: 0.95rem 1rem;
            display: flex;
            flex-direction: column;
            min-width: 0;
            min-height: 200px;
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
        .hero-col__content { flex: 1; min-height: 0; display: flex; flex-direction: column; }
        /* State pulse (same structure as district pulse) */
        .welcome-district-embed { margin-top: 0.75rem; padding: 0.75rem 0.65rem; border-radius: 14px; background: linear-gradient(145deg, rgba(238, 242, 255, 0.35), rgba(224, 250, 248, 0.15)); }
        .welcome-district-embed__head { display: flex; flex-wrap: wrap; justify-content: space-between; align-items: center; gap: 0.35rem; margin-bottom: 0.55rem; }
        .welcome-district-embed__eyebrow { font-size: 0.55rem; font-weight: 800; text-transform: uppercase; letter-spacing: 0.1em; color: #4f46e5; }
        .welcome-district-embed__where { font-size: 0.62rem; color: #64748b; font-weight: 600; }
        .welcome-district-embed__grid { display: grid; grid-template-columns: auto 1fr; gap: 0.65rem; align-items: stretch; min-height: 0; }
        @media (max-width: 520px) { .welcome-district-embed__grid { grid-template-columns: 1fr; } }
        .welcome-district-embed__ring { display: flex; flex-direction: column; align-items: center; justify-content: center; gap: 0.25rem; }
        .welcome-district-embed__ring-meta { text-align: center; margin-top: 0.15rem; }
        .welcome-district-embed__pct { font-family: 'DM Sans', sans-serif; font-size: 1.1rem; font-weight: 800; color: #0f172a; display: block; line-height: 1.1; }
        .welcome-district-embed__pct-label { font-size: 0.58rem; color: #64748b; display: block; margin-top: 0.1rem; }
        .welcome-district-embed__chart { display: flex; flex-direction: column; min-height: 0; min-width: 0; }
        .welcome-district-embed__chart-head { display: flex; justify-content: space-between; align-items: baseline; font-size: 0.58rem; font-weight: 700; text-transform: uppercase; letter-spacing: 0.06em; color: #475569; margin-bottom: 0.35rem; }
        .welcome-district-embed__chart-hint { font-size: 0.55rem; font-weight: 600; color: #94a3b8; text-transform: none; letter-spacing: 0; }
        .welcome-district-chart-wrap { position: relative; flex: 1; min-height: 118px; max-height: 140px; }
        .welcome-district-chart-wrap canvas { position: absolute; inset: 0; width: 100% !important; height: 100% !important; }
        .welcome-district-embed__stats { display: grid; grid-template-columns: repeat(4, minmax(0, 1fr)); gap: 0.4rem; margin-top: 0.55rem; padding-top: 0.55rem; border-top: 1px dashed rgba(148, 163, 184, 0.45); }
        @media (max-width: 520px) { .welcome-district-embed__stats { grid-template-columns: repeat(2, 1fr); } }
        .welcome-d-stat { background: rgba(255, 255, 255, 0.75); border: 1px solid rgba(226, 232, 240, 0.9); border-radius: 10px; padding: 0.35rem 0.45rem; text-align: center; }
        .welcome-d-stat__l { display: block; font-size: 0.52rem; font-weight: 700; text-transform: uppercase; letter-spacing: 0.06em; color: #64748b; }
        .welcome-d-stat__v { display: block; font-family: 'DM Sans', sans-serif; font-size: 0.95rem; font-weight: 800; color: #0f172a; margin-top: 0.15rem; }
        .welcome-district-embed__mix { margin-top: 0.5rem; }
        .welcome-district-embed__mix-title { font-size: 0.52rem; font-weight: 800; text-transform: uppercase; letter-spacing: 0.08em; color: #64748b; display: block; margin-bottom: 0.35rem; }
        .welcome-d-stage-row { display: grid; grid-template-columns: 1.1rem 1fr 2rem; align-items: center; gap: 0.35rem; font-size: 0.62rem; margin-bottom: 0.28rem; }
        .welcome-d-stage-row:last-child { margin-bottom: 0; }
        .welcome-d-stage-row__l { font-weight: 800; color: #94a3b8; text-align: center; }
        .welcome-d-stage-row__t { height: 8px; border-radius: 999px; background: rgba(241, 245, 249, 0.95); overflow: hidden; border: 1px solid rgba(226, 232, 240, 0.85); }
        .welcome-d-stage-row__f { height: 100%; border-radius: inherit; min-width: 0; }
        .welcome-d-stage-row__f--seed { background: linear-gradient(90deg, #facc15, #ea580c); }
        .welcome-d-stage-row__f--early { background: linear-gradient(90deg, #60a5fa, #6366f1); }
        .welcome-d-stage-row__f--growth { background: linear-gradient(90deg, #34d399, #14b8a6); }
        .welcome-d-stage-row__p { font-weight: 800; color: #0f172a; text-align: right; font-variant-numeric: tabular-nums; font-size: 0.62rem; }
        .welcome-district-embed__note { margin: 0.45rem 0 0; font-size: 0.55rem; line-height: 1.35; color: #94a3b8; }
        .district-ring { width: 92px; height: 92px; transform: rotate(-90deg); }
        .district-ring__bg { fill: none; stroke: rgba(148, 163, 184, 0.25); stroke-width: 10; }
        .district-ring__progress { fill: none; stroke: url(#stateRingGradWelcome); stroke-width: 10; stroke-linecap: round; stroke-dasharray: 339.292; transition: stroke-dashoffset 0.6s ease; }
        .apps-highlight { margin-bottom: 0.75rem; }
        .apps-highlight__main { margin-bottom: 0.5rem; }
        .apps-highlight__number { font-family: 'DM Sans', sans-serif; font-size: 2rem; font-weight: 800; color: #0f172a; line-height: 1; }
        .apps-highlight__label { font-size: 0.72rem; color: #64748b; font-weight: 600; text-transform: uppercase; letter-spacing: 0.05em; margin-top: 0.25rem; }
        /* Business mix leaderboard */
        .business-mix-compact { margin-top: 0.75rem; padding: 1rem; background: linear-gradient(145deg, rgba(255, 255, 255, 0.72), rgba(248, 250, 252, 0.45)); border: 1px solid rgba(148, 163, 184, 0.22); border-radius: 16px; }
        .business-mix-compact__header { margin-bottom: 0.75rem; }
        .business-mix-compact__title { font-size: 0.7rem; font-weight: 800; text-transform: uppercase; letter-spacing: 0.08em; color: #334155; }
        .business-mix-compact__meta { font-size: 0.62rem; color: #64748b; margin-top: 0.1rem; }
        .biz-cat-lb { display: flex; flex-direction: column; gap: 0.5rem; }
        .biz-cat-lb__row {
            display: grid; grid-template-columns: 1.6rem minmax(0, 1fr); gap: 0.45rem; padding: 0.4rem 0.45rem;
            border-radius: 12px; background: rgba(255, 255, 255, 0.55); border: 1px solid rgba(226, 232, 240, 0.95);
        }
        .biz-cat-lb__rank { font-size: 0.58rem; font-weight: 800; color: #94a3b8; padding-top: 0.55rem; text-align: center; }
        .biz-cat-lb__main { min-width: 0; display: flex; flex-direction: column; gap: 0.32rem; }
        .biz-cat-lb__label-row { display: flex; justify-content: space-between; gap: 0.5rem; min-width: 0; align-items: center; }
        .biz-cat-lb__name-wrap { display: inline-flex; align-items: center; gap: 0.45rem; min-width: 0; }
        .biz-cat-lb__icon {
            width: 1.5rem; height: 1.5rem; flex-shrink: 0;
            display: inline-grid; place-items: center;
            border-radius: 8px;
            color: var(--biz-color, #6366f1);
            background: color-mix(in srgb, var(--biz-color, #6366f1) 14%, transparent);
            border: 1px solid color-mix(in srgb, var(--biz-color, #6366f1) 30%, transparent);
            font-size: 0.72rem;
        }
        .biz-cat-lb__name { font-size: 0.68rem; font-weight: 700; color: #0f172a; overflow: hidden; text-overflow: ellipsis; white-space: nowrap; }
        .biz-cat-lb__nums { display: inline-flex; gap: 0.45rem; flex-shrink: 0; }
        .biz-cat-lb__pct { font-size: 0.58rem; font-weight: 700; color: #64748b; }
        .biz-cat-lb__count { font-family: 'DM Sans', sans-serif; font-size: 0.82rem; font-weight: 800; color: #0f172a; }
        .biz-cat-lb__track { height: 7px; border-radius: 999px; background: rgba(241, 245, 249, 0.95); border: 1px solid rgba(226, 232, 240, 0.85); overflow: hidden; }
        .biz-cat-lb__fill { height: 100%; width: 0; background: var(--biz-color); border-radius: inherit;
            box-shadow: inset 0 1px 0 rgba(255, 255, 255, 0.42); animation: stateBizFill 0.85s cubic-bezier(0.22, 1, 0.36, 1) forwards; animation-delay: var(--biz-delay, 0s); }
        @keyframes stateBizFill { from { width: 0; opacity: 0.88; } to { width: var(--biz-pct); opacity: 1; } }
        @media (prefers-reduced-motion: reduce) { .biz-cat-lb__fill { animation: none; width: var(--biz-pct); } }
        .charts-grid { display: grid; grid-template-columns: repeat(2, 1fr); gap: 1.25rem; margin-bottom: 1.25rem; width: 100%; max-width: 100%; min-width: 0; box-sizing: border-box; }
        @media (max-width: 900px) { .charts-grid { grid-template-columns: 1fr; } }
        .charts-grid > * { min-width: 0; }

        .chart-card {
            background: linear-gradient(135deg, rgba(255, 255, 255, 0.42), rgba(255, 255, 255, 0.2));
            border-radius: var(--radius);
            border: 1px solid var(--glass-border);
            box-shadow: var(--shadow);
            padding: 1.2rem 1.3rem 1.4rem;
            backdrop-filter: blur(30px);
            min-width: 0;
            max-width: 100%;
        }
        .chart-card h4 { margin: 0 0 0.15rem; font-size: 0.95rem; font-weight: 700; font-family: 'DM Sans', sans-serif; color: var(--text); }
        .chart-card .hint { font-size: 0.78rem; color: var(--text-muted); margin-bottom: 0.75rem; }
        .chart-card .canvas-wrap { position: relative; height: 260px; max-width: 100%; overflow: hidden; }
        .chart-card.tall .canvas-wrap { height: 300px; }
        .target-strip {
            margin-top: 1rem;
            padding: 1rem 1.25rem;
            border-radius: 16px;
            background: linear-gradient(120deg, #1e1b4b 0%, #312e81 50%, #1e3a5f 100%);
            color: #e0e7ff;
            display: flex; flex-wrap: wrap; align-items: center; justify-content: space-between; gap: 1rem;
        }
        .target-strip h3 { margin: 0; font-size: 0.85rem; font-weight: 600; opacity: 0.9; }
        .target-strip .big { font-family: 'DM Sans', sans-serif; font-size: 1.75rem; font-weight: 700; margin: 0.25rem 0 0; }
        .target-strip .meta { font-size: 0.8rem; opacity: 0.8; max-width: 22rem; }
        .target-strip .meta a { color: #a5b4fc; }
        .target-strip .bar { height: 8px; background: rgba(255,255,255,0.15); border-radius: 999px; overflow: hidden; margin-top: 0.5rem; }
        .target-strip .fill { height: 100%; border-radius: 999px; background: linear-gradient(90deg, #34d399, #6ee7b7); }
        .state-bento { display: grid; grid-template-columns: repeat(auto-fill, minmax(200px, 1fr)); gap: 0.75rem; margin-top: 0.5rem; }
        .state-bento a {
            display: block; padding: 1rem 1.1rem; border-radius: 14px; text-decoration: none; color: inherit;
            border: 1px solid rgba(255, 255, 255, 0.65);
            background: rgba(255, 255, 255, 0.55);
            transition: transform 0.15s, box-shadow 0.15s;
        }
        .state-bento a:hover { transform: translateY(-2px); box-shadow: 0 12px 28px rgba(79, 70, 229, 0.12); }
        .state-bento .qi { font-size: 1.35rem; margin-bottom: 0.35rem; }
        .state-bento strong { display: block; font-size: 0.88rem; font-weight: 700; color: #0f172a; }
        .state-bento span { font-size: 0.76rem; color: #64748b; margin-top: 0.2rem; display: block; line-height: 1.35; }
        .banner { margin-bottom: 1rem; }
        .no-data-message { text-align: center; padding: 2rem; color: #94a3b8; font-size: 0.85rem; }
    </style>
</head>
<body class="admin-app-body admin-app-body--dashboard">
    @include('partials.admin-topbar')
    <main class="admin-main">
        @if (session('status'))
            <div class="banner">{{ session('status') }}</div>
        @endif

        <div class="dashboard-shell">
            @if ($activeFy)
                @php
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
                    $ringCirc = 339.292;
                    $ringPct = $stateProgressPct !== null ? (int) min(100, max(0, $stateProgressPct)) : null;
                    $ringOffset = $ringPct !== null ? $ringCirc * (1 - $ringPct / 100) : $ringCirc;
                @endphp

                @php
                    $heroRingPct = $stateProgressPct !== null ? (int) min(100, max(0, $stateProgressPct)) : 0;
                    $heroRingCirc = 2 * M_PI * 40;
                    $heroRingOffset = $heroRingCirc * (1 - $heroRingPct / 100);

                    $sparkVals = $heroSparkline30['values'] ?? [];
                    $sparkSum = (int) array_sum($sparkVals);
                    $sparkMax = ! empty($sparkVals) ? max(max($sparkVals), 1) : 1;
                    $sparkW = 160;
                    $sparkH = 36;
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
                    $sparkLastX = $sparkPts ? (float) explode(',', end($sparkPts))[0] : 0;
                    $sparkLastY = $sparkPts ? (float) explode(',', end($sparkPts))[1] : 0;

                    $halfMonth = (int) floor($sparkCount / 2);
                    $firstHalf = array_sum(array_slice($sparkVals, 0, $halfMonth));
                    $secondHalf = array_sum(array_slice($sparkVals, $halfMonth));
                    $sparkTrend = $firstHalf > 0 ? (int) round((($secondHalf - $firstHalf) / $firstHalf) * 100) : 0;

                    $todayDelta = (int) ($heroCfaTodayDelta ?? 0);
                @endphp

                <div class="dashboard-intro">
                    <div class="dashboard-intro__grid">
                        <div class="dashboard-intro__left">
                            <div class="dashboard-intro__eyebrow"><i class="fa-solid fa-flag" aria-hidden="true"></i> State overview</div>
                            <h2>Welcome back, {{ auth()->user()->name }}</h2>
                            <p>
                                All districts · CFA picture for <strong>{{ $activeFy->name }}</strong>.
                                Totals below use this fiscal year unless noted.
                            </p>
                            <div class="welcome-meta-pills">
                                <div class="welcome-meta-pill">
                                    <i class="fa-regular fa-calendar" aria-hidden="true"></i>
                                    <span><strong>Active FY</strong> {{ $activeFy->name }}</span>
                                </div>
                                @if ($stateCfaTarget !== null)
                                    <div class="welcome-meta-pill">
                                        <i class="fa-solid fa-bullseye" aria-hidden="true"></i>
                                        <span><strong>State CFA target</strong> {{ number_format((int) $stateCfaTarget) }}</span>
                                    </div>
                                @endif
                                <div class="welcome-meta-pill">
                                    <i class="fa-solid fa-map-location-dot" aria-hidden="true"></i>
                                    <span><strong>Districts</strong> {{ number_format($districtsCount) }}</span>
                                </div>
                                <div class="welcome-meta-pill">
                                    <i class="fa-solid fa-users" aria-hidden="true"></i>
                                    <span><strong>Staff (active)</strong> {{ number_format($staffActive) }} / {{ number_format($staffTotal) }}</span>
                                </div>
                            </div>
                        </div>

                        <div class="dashboard-intro__right">
                            {{-- FY progress ring --}}
                            <div class="hero-ring-card" title="CFA submissions vs state target for {{ $activeFy->name }}">
                                <svg class="hero-ring-svg" viewBox="0 0 100 100" aria-hidden="true">
                                    <defs>
                                        <linearGradient id="heroRingGrad" x1="0%" y1="0%" x2="100%" y2="100%">
                                            <stop offset="0%" stop-color="#6366f1"/>
                                            <stop offset="100%" stop-color="#14b8a6"/>
                                        </linearGradient>
                                    </defs>
                                    <circle class="track" cx="50" cy="50" r="40"/>
                                    <circle class="bar" cx="50" cy="50" r="40"
                                        stroke-dasharray="{{ round($heroRingCirc, 3) }}"
                                        stroke-dashoffset="{{ round($heroRingOffset, 3) }}"/>
                                    <text class="pct" x="50" y="52" text-anchor="middle" dominant-baseline="middle">{{ $heroRingPct }}%</text>
                                    <text class="pct-sub" x="50" y="66" text-anchor="middle">OF TARGET</text>
                                </svg>
                                <div>
                                    <div class="hero-ring-body__eyebrow">FY progress</div>
                                    <div class="hero-ring-body__value">
                                        {{ number_format((int) ($stateCfaThisFy ?? 0)) }}
                                        @if ($stateCfaTarget !== null)
                                            <small>/ {{ number_format((int) $stateCfaTarget) }}</small>
                                        @endif
                                    </div>
                                    <span class="hero-ring-body__label">CFA submissions this FY</span>
                                    @if ($stateCfaTarget !== null)
                                        @php $remaining = max(0, (int) $stateCfaTarget - (int) ($stateCfaThisFy ?? 0)); @endphp
                                        <span class="hero-ring-body__gap @if ($remaining === 0) is-good @endif">
                                            <i class="fa-solid @if ($remaining === 0) fa-trophy @else fa-arrow-trend-up @endif" aria-hidden="true"></i>
                                            @if ($remaining === 0) Target met! @else {{ number_format($remaining) }} to go @endif
                                        </span>
                                    @endif
                                </div>
                            </div>

                            {{-- Today pills --}}
                            <div class="hero-today-row">
                                <div class="hero-today hero-today--cfa">
                                    <div class="hero-today__head"><i class="fa-solid fa-file-circle-plus" aria-hidden="true"></i> CFA today</div>
                                    <div class="hero-today__value">{{ number_format((int) ($heroCfaToday ?? 0)) }}</div>
                                    @if ($todayDelta > 0)
                                        <span class="hero-today__delta is-up"><i class="fa-solid fa-caret-up" aria-hidden="true"></i> {{ $todayDelta }} vs yest.</span>
                                    @elseif ($todayDelta < 0)
                                        <span class="hero-today__delta is-down"><i class="fa-solid fa-caret-down" aria-hidden="true"></i> {{ abs($todayDelta) }} vs yest.</span>
                                    @else
                                        <span class="hero-today__delta">— same as yest.</span>
                                    @endif
                                </div>
                                <div class="hero-today hero-today--mentor">
                                    <div class="hero-today__head"><i class="fa-solid fa-handshake" aria-hidden="true"></i> Mentorship</div>
                                    <div class="hero-today__value">{{ number_format((int) ($heroMentorshipPending ?? 0)) }}</div>
                                    <span class="hero-today__delta">pending requests</span>
                                </div>
                                <div class="hero-today hero-today--online">
                                    <div class="hero-today__head"><i class="fa-solid fa-signal" aria-hidden="true"></i> Online now</div>
                                    <div class="hero-today__value">{{ number_format((int) ($heroStaffOnlineNow ?? 0)) }}</div>
                                    <span class="hero-today__delta">active in last 3 min</span>
                                </div>
                            </div>

                            {{-- 30-day sparkline --}}
                            @if (! empty($sparkLine))
                            <div class="hero-spark" title="Daily CFA submissions · last 30 days">
                                <div class="hero-spark__left">
                                    <div class="hero-spark__eyebrow">30-DAY PULSE</div>
                                    <div class="hero-spark__value">{{ number_format($sparkSum) }} <small>CFAs</small></div>
                                </div>
                                <div class="hero-spark__chart" aria-hidden="true">
                                    <svg viewBox="0 0 {{ $sparkW }} {{ $sparkH }}" preserveAspectRatio="none">
                                        <defs>
                                            <linearGradient id="heroSparkGrad" x1="0%" y1="0%" x2="0%" y2="100%">
                                                <stop offset="0%" stop-color="#0891b2" stop-opacity="0.45"/>
                                                <stop offset="100%" stop-color="#0891b2" stop-opacity="0"/>
                                            </linearGradient>
                                        </defs>
                                        <polygon class="spark-fill" points="{{ $sparkFill }}"/>
                                        <polyline class="spark-line" points="{{ $sparkLine }}"/>
                                        <circle class="spark-dot" cx="{{ $sparkLastX }}" cy="{{ $sparkLastY }}" r="2.4"/>
                                    </svg>
                                </div>
                                <div style="text-align:right;flex-shrink:0;">
                                    <span class="hero-today__delta @if ($sparkTrend > 0) is-up @elseif ($sparkTrend < 0) is-down @endif" style="font-size:0.62rem;">
                                        @if ($sparkTrend > 0)<i class="fa-solid fa-caret-up"></i> +{{ $sparkTrend }}%
                                        @elseif ($sparkTrend < 0)<i class="fa-solid fa-caret-down"></i> {{ $sparkTrend }}%
                                        @else — flat @endif
                                    </span>
                                    <div style="font-size:0.5rem;color:#94a3b8;font-weight:700;letter-spacing:0.08em;text-transform:uppercase;margin-top:0.15rem;">vs prev 15d</div>
                                </div>
                            </div>
                            @endif
                        </div>
                    </div>
                </div>

                <div class="hero-three-col">
                    <div class="hero-col hero-col--welcome glass-surface">
                        <div class="hero-col__title">
                            <i class="fa-solid fa-gauge-high" aria-hidden="true"></i> State cockpit
                        </div>
                        <div class="hero-col__content">
                            <div class="apps-highlight">
                                <div class="apps-highlight__main">
                                    <div class="apps-highlight__number">{{ number_format((int) ($stateCfaThisFy ?? 0)) }}</div>
                                    <div class="apps-highlight__label">CFA this FY (all districts)</div>
                                </div>
                            </div>
                            <p style="font-size:0.78rem;color:var(--text-muted);margin:0 0 0.5rem;line-height:1.45;">
                                All-time submissions: {{ number_format($cfaTotal) }} Â· Last 30 days: {{ number_format($cfaLast30) }}
                            </p>

                            @if ($stateCfaTarget !== null && (int) $stateCfaTarget > 0 && $districtAllocPct !== null)
                            <div class="target-strip" style="margin-top:0;border-radius:14px;">
                                <div>
                                    <h3>District target rows vs state MIS</h3>
                                    <p class="big">{{ $districtAllocPct }}%</p>
                                    <p class="meta">District CFA rows sum: {{ number_format((int) ($districtsCfaSum ?? 0)) }} of state {{ number_format((int) $stateCfaTarget) }}.
                                        @if ($cfaDeliverable)
                                            <a href="{{ route('admin.targets.district', ['fiscal_year_id' => $activeFy->id, 'deliverable_id' => $cfaDeliverable->id]) }}">Edit district targets</a>
                                        @endif
                                    </p>
                                </div>
                                <div style="min-width:12rem;flex:1;max-width:20rem;">
                                    <div class="bar"><div class="fill" style="width: {{ min(100, $districtAllocPct) }}%;"></div></div>
                                </div>
                            </div>
                            @endif

                            <div class="business-mix-compact">
                                <div class="business-mix-compact__header">
                                    <div class="business-mix-compact__title">Business categories</div>
                                    <div class="business-mix-compact__meta">Ranked mix Â· all {{ $activeFy->name }} applications ({{ number_format((int) array_sum($businessMix['values'] ?? [])) }})</div>
                                </div>
                                @if (count($businessMix['labels']) === 0)
                                    <div class="no-data-message">No category data yet</div>
                                @else
                                    @php
                                        $bizMixTotal = (int) array_sum($businessMix['values']);
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
                                    @endphp
                                    <div class="biz-cat-lb" role="list">
                                        @foreach ($businessMix['labels'] as $idx => $label)
                                            @php
                                                $bizV = (int) ($businessMix['values'][$idx] ?? 0);
                                                $bizPct = $bizMixTotal > 0 ? (int) round(100 * $bizV / $bizMixTotal) : 0;
                                                $bizCol = $businessMix['colors'][$idx] ?? '#6366f1';
                                                $bizIcon = $bizIconFor((string) $label);
                                            @endphp
                                            <div class="biz-cat-lb__row" style="--biz-color: {{ $bizCol }}; --biz-pct: {{ $bizPct }}%; --biz-delay: {{ $idx * 0.05 }}s;">
                                                <div class="biz-cat-lb__rank">#{{ $idx + 1 }}</div>
                                                <div class="biz-cat-lb__main">
                                                    <div class="biz-cat-lb__label-row">
                                                        <span class="biz-cat-lb__name-wrap">
                                                            <span class="biz-cat-lb__icon" aria-hidden="true"><i class="fa-solid {{ $bizIcon }}"></i></span>
                                                            <span class="biz-cat-lb__name">{{ $label }}</span>
                                                        </span>
                                                        <span class="biz-cat-lb__nums">
                                                            <span class="biz-cat-lb__pct">{{ $bizPct }}%</span>
                                                            <span class="biz-cat-lb__count">{{ number_format($bizV) }}</span>
                                                        </span>
                                                    </div>
                                                    <div class="biz-cat-lb__track"><div class="biz-cat-lb__fill"></div></div>
                                                </div>
                                            </div>
                                        @endforeach
                                    </div>
                                @endif
                            </div>
                        </div>
                    </div>

                    <div class="hero-col hero-col--metrics glass-surface">
                        <div class="hero-col__title">
                            <i class="fa-solid fa-chart-area" aria-hidden="true"></i> State pulse
                        </div>
                        <div class="welcome-district-embed glass-surface" style="margin-top:0;">
                            <div class="welcome-district-embed__head">
                                <span class="welcome-district-embed__eyebrow"><i class="fa-solid fa-earth-asia" aria-hidden="true"></i> All districts</span>
                                <span class="welcome-district-embed__where">{{ $activeFy->name }}</span>
                            </div>
                            <div class="welcome-district-embed__grid">
                                <div class="welcome-district-embed__ring">
                                    <svg class="district-ring" viewBox="0 0 120 120" aria-hidden="true">
                                        <defs>
                                            <linearGradient id="stateRingGradWelcome" x1="0%" y1="0%" x2="100%" y2="100%">
                                                <stop offset="0%" style="stop-color:#6366f1"/>
                                                <stop offset="100%" style="stop-color:#2dd4bf"/>
                                            </linearGradient>
                                        </defs>
                                        <circle class="district-ring__bg" cx="60" cy="60" r="54" fill="none"/>
                                        <circle class="district-ring__progress" cx="60" cy="60" r="54" fill="none"
                                            stroke="url(#stateRingGradWelcome)"
                                            stroke-dasharray="{{ $ringCirc }}"
                                            style="stroke-dashoffset: {{ $ringOffset }}"/>
                                    </svg>
                                    <div class="welcome-district-embed__ring-meta">
                                        @if ($stateProgressPct !== null)
                                            <span class="welcome-district-embed__pct">{{ $stateProgressPct }}%</span>
                                            <span class="welcome-district-embed__pct-label">vs state CFA target</span>
                                        @else
                                            <span class="welcome-district-embed__pct">—</span>
                                            <span class="welcome-district-embed__pct-label">Set state target</span>
                                        @endif
                                    </div>
                                </div>
                                <div class="welcome-district-embed__chart">
                                    <div class="welcome-district-embed__chart-head">
                                        <span>14-day state intake</span>
                                        <span class="welcome-district-embed__chart-hint">This FY</span>
                                    </div>
                                    <div class="welcome-district-chart-wrap">
                                        <canvas id="stateTrendCurveChart" aria-label="CFA per day, all districts"></canvas>
                                    </div>
                                </div>
                            </div>
                            <div class="welcome-district-embed__stats" role="group">
                                <div class="welcome-d-stat">
                                    <span class="welcome-d-stat__l">State FY</span>
                                    <span class="welcome-d-stat__v">{{ number_format((int) ($stateCfaThisFy ?? 0)) }}</span>
                                </div>
                                @if ($stateCfaTarget !== null)
                                    <div class="welcome-d-stat">
                                        <span class="welcome-d-stat__l">Target</span>
                                        <span class="welcome-d-stat__v">{{ number_format((int) $stateCfaTarget) }}</span>
                                    </div>
                                @endif
                                <div class="welcome-d-stat">
                                    <span class="welcome-d-stat__l">Districts</span>
                                    <span class="welcome-d-stat__v">{{ number_format($districtsCount) }}</span>
                                </div>
                                <div class="welcome-d-stat">
                                    <span class="welcome-d-stat__l">Hubs</span>
                                    <span class="welcome-d-stat__v">{{ number_format($hubsCount) }}</span>
                                </div>
                            </div>
                            <div class="welcome-district-embed__mix">
                                <span class="welcome-district-embed__mix-title">Stage mix (sample FY)</span>
                                <div class="welcome-d-stage-row">
                                    <span class="welcome-d-stage-row__l">S</span>
                                    <div class="welcome-d-stage-row__t"><div class="welcome-d-stage-row__f welcome-d-stage-row__f--seed" style="width: {{ $sStagePct['SEED'] }}%;"></div></div>
                                    <span class="welcome-d-stage-row__p">{{ $sStagePct['SEED'] }}%</span>
                                </div>
                                <div class="welcome-d-stage-row">
                                    <span class="welcome-d-stage-row__l">E</span>
                                    <div class="welcome-d-stage-row__t"><div class="welcome-d-stage-row__f welcome-d-stage-row__f--early" style="width: {{ $sStagePct['EARLY'] }}%;"></div></div>
                                    <span class="welcome-d-stage-row__p">{{ $sStagePct['EARLY'] }}%</span>
                                </div>
                                <div class="welcome-d-stage-row">
                                    <span class="welcome-d-stage-row__l">G</span>
                                    <div class="welcome-d-stage-row__t"><div class="welcome-d-stage-row__f welcome-d-stage-row__f--growth" style="width: {{ $sStagePct['GROWTH'] }}%;"></div></div>
                                    <span class="welcome-d-stage-row__p">{{ $sStagePct['GROWTH'] }}%</span>
                                </div>
                            </div>
                            <p class="welcome-district-embed__note">Stage mix from a recent sample of saved forms ({{ $activeFy->name }}). CFA counts are all submissions in this FY.</p>
                        </div>
                    </div>
                </div>

                <div class="charts-grid">
                    <div class="chart-card tall">
                        <h4>Applications by district</h4>
                        <p class="hint">CFA count this fiscal year Â· top districts</p>
                        <div class="canvas-wrap"><canvas id="chartDistrictCfa"></canvas></div>
                    </div>
                    <div class="chart-card tall">
                        <h4>Staff by district</h4>
                        <p class="hint">District staff users</p>
                        <div class="canvas-wrap"><canvas id="chartStaff"></canvas></div>
                    </div>
                </div>

                <h3 style="font-family:'DM Sans',sans-serif;font-size:1rem;margin:0 0 0.65rem;color:var(--text);font-weight:800;">Quick actions</h3>
                <div class="state-bento">
                    <a href="{{ route('admin.cfa.index') }}">
                        <div class="qi"><i class="fa-solid fa-clipboard-list" aria-hidden="true"></i></div>
                        <strong>CFA applications</strong>
                        <span>All districts — review submissions</span>
                    </a>
                    <a href="{{ route('admin.targets.state') }}">
                        <div class="qi"><i class="fa-solid fa-chart-column" aria-hidden="true"></i></div>
                        <strong>State targets</strong>
                        <span>MIS totals by deliverable</span>
                    </a>
                    <a href="{{ route('admin.targets.district') }}">
                        <div class="qi"><i class="fa-solid fa-map-location-dot" aria-hidden="true"></i></div>
                        <strong>District targets</strong>
                        <span>Per-district allocation</span>
                    </a>
                    <a href="{{ route('admin.staff.index') }}">
                        <div class="qi"><i class="fa-solid fa-user-tie" aria-hidden="true"></i></div>
                        <strong>Staff &amp; links</strong>
                        <span>Referral URLs &amp; targets</span>
                    </a>
                    <a href="{{ route('admin.designations.index') }}">
                        <div class="qi"><i class="fa-solid fa-tags" aria-hidden="true"></i></div>
                        <strong>Designations</strong>
                        <span>Role titles</span>
                    </a>
                    <a href="{{ route('admin.audit.index') }}">
                        <div class="qi"><i class="fa-solid fa-scroll" aria-hidden="true"></i></div>
                        <strong>Audit log</strong>
                        <span>Activity trail</span>
                    </a>
                </div>
            @else
                <div class="dashboard-intro">
                    <h2>State dashboard</h2>
                    <p>No active fiscal year is set. Configure fiscal years and targets to see statewide metrics.</p>
                </div>
            @endif
        </div>
    </main>

@if ($activeFy)
<script>
(function () {
    const grid = { color: 'rgba(148, 163, 184, 0.25)' };
    const accent = '#4f46e5';
    const teal = '#0d9488';

    const trendLabels = @json($stateCfaTrend['labels'] ?? []);
    const trendValues = @json($stateCfaTrend['values'] ?? []);
    const stEl = document.getElementById('stateTrendCurveChart');
    if (stEl && trendLabels.length) {
        const cx = stEl.getContext('2d');
        const dh = stEl.parentElement?.clientHeight || 130;
        const dFill = cx.createLinearGradient(0, 0, 0, dh);
        dFill.addColorStop(0, 'rgba(13, 148, 136, 0.32)');
        dFill.addColorStop(1, 'rgba(45, 212, 191, 0.02)');
        new Chart(stEl, {
            type: 'line',
            data: {
                labels: trendLabels,
                datasets: [{
                    label: 'State CFA',
                    data: trendValues,
                    borderColor: 'rgba(13, 148, 136, 0.95)',
                    backgroundColor: dFill,
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

    const dLabels = @json($cfaByDistrict['labels']);
    const dValues = @json($cfaByDistrict['values']);
    new Chart(document.getElementById('chartDistrictCfa'), {
        type: 'bar',
        data: {
            labels: dLabels.length ? dLabels : ['No data'],
            datasets: [{
                label: 'CFA (FY)',
                data: dLabels.length ? dValues : [0],
                backgroundColor: dLabels.length ? dValues.map((_, i) => 'rgba(79, 70, 229, ' + (0.4 + (i % 5) * 0.12) + ')') : ['#e2e8f0'],
                borderRadius: 6
            }]
        },
        options: {
            indexAxis: 'y',
            responsive: true,
            maintainAspectRatio: false,
            plugins: { legend: { display: false } },
            scales: {
                x: { beginAtZero: true, grid: { color: grid.color } },
                y: { grid: { display: false }, ticks: { font: { size: 10 } } }
            }
        }
    });

    const sLabels = @json($staffByDistrict['labels']);
    const sValues = @json($staffByDistrict['values']);
    new Chart(document.getElementById('chartStaff'), {
        type: 'bar',
        data: {
            labels: sLabels.length ? sLabels : ['No data'],
            datasets: [{
                label: 'Staff',
                data: sLabels.length ? sValues : [0],
                backgroundColor: teal,
                borderRadius: 6
            }]
        },
        options: {
            responsive: true,
            maintainAspectRatio: false,
            plugins: { legend: { display: false } },
            scales: {
                x: { grid: { display: false }, ticks: { maxRotation: 60, font: { size: 9 } } },
                y: { beginAtZero: true, ticks: { stepSize: 1 }, grid: { color: grid.color } }
            }
        }
    });
})();
</script>
@endif

</body>
</html>
