<!DOCTYPE html>
<html lang="{{ str_replace('_', '-', app()->getLocale()) }}">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>Hub — {{ $hub->name }} — {{ config('app.name') }}</title>
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=DM+Sans:ital,opsz,wght@0,9..40,400;0,9..40,500;0,9..40,600;0,9..40,700&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.1/css/all.min.css" crossorigin="anonymous" referrerpolicy="no-referrer">
    <script src="https://cdn.jsdelivr.net/npm/chart.js@4.4.1/dist/chart.umd.min.js" crossorigin="anonymous"></script>
    @include('partials.admin-shell-styles')
    <style>
        :root {
            --text: #0f172a;
            --text-muted: #64748b;
            --accent: #4f46e5;
            --accent2: #0d9488;
            --success: #059669;
            --border: #e2e8f0;
            --glow: rgba(79, 70, 229, 0.15);
            --radius: 14px;
            --shadow: 0 4px 24px rgba(15, 23, 42, 0.08);
        }
        .dashboard-intro {
            display: flex;
            flex-wrap: wrap;
            align-items: flex-start;
            justify-content: space-between;
            gap: 1rem;
            margin-bottom: 1.75rem;
        }
        .dashboard-intro h2 { font-family: 'DM Sans', sans-serif; font-size: 1.65rem; font-weight: 700; margin: 0; letter-spacing: -0.02em; color: var(--text); }
        .dashboard-intro p { margin: 0.35rem 0 0; color: var(--text-muted); font-size: 0.95rem; max-width: 42rem; }
        .kpi-grid {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(200px, 1fr));
            gap: 1rem;
            margin-bottom: 1.25rem;
        }
        .kpi {
            background: #fff;
            border-radius: var(--radius);
            padding: 1.15rem 1.25rem;
            border: 1px solid var(--border);
            box-shadow: var(--shadow);
            position: relative;
            overflow: hidden;
        }
        .kpi::before {
            content: '';
            position: absolute;
            top: 0; left: 0; right: 0;
            height: 3px;
            background: linear-gradient(90deg, var(--accent), var(--accent2));
            opacity: 0.85;
        }
        .kpi:nth-child(2)::before { background: linear-gradient(90deg, #7c3aed, #4f46e5); }
        .kpi:nth-child(3)::before { background: linear-gradient(90deg, #0d9488, #14b8a6); }
        .kpi:nth-child(4)::before { background: linear-gradient(90deg, #ea580c, #f59e0b); }
        .kpi .label { font-size: 0.75rem; font-weight: 600; text-transform: uppercase; letter-spacing: 0.06em; color: var(--text-muted); margin-bottom: 0.35rem; }
        .kpi .val { font-family: 'DM Sans', sans-serif; font-size: 1.75rem; font-weight: 700; letter-spacing: -0.02em; color: var(--text); }
        .kpi .sub { font-size: 0.8rem; color: var(--text-muted); margin-top: 0.35rem; }

        /* ── Phase KPI cards ── */
        .kpi-phase {
            border-radius: var(--radius);
            padding: 1.4rem 1.5rem 1.3rem;
            border: none;
            box-shadow: 0 6px 28px rgba(0,0,0,0.10);
            position: relative;
            overflow: hidden;
            transition: transform 0.22s cubic-bezier(.34,1.56,.64,1), box-shadow 0.22s ease;
            cursor: default;
        }
        .kpi-phase:hover {
            transform: translateY(-5px) scale(1.025);
            box-shadow: 0 16px 40px rgba(0,0,0,0.16);
        }
        .kpi-phase::after {
            content: '';
            position: absolute;
            inset: 0;
            background: linear-gradient(120deg, rgba(255,255,255,0) 30%, rgba(255,255,255,0.18) 50%, rgba(255,255,255,0) 70%);
            transform: translateX(-100%);
            transition: transform 0.55s ease;
        }
        .kpi-phase:hover::after { transform: translateX(100%); }
        .kpi-phase--seed  { background: linear-gradient(135deg, #064e3b 0%, #065f46 45%, #047857 100%); }
        .kpi-phase--early { background: linear-gradient(135deg, #0c4a6e 0%, #075985 45%, #0369a1 100%); }
        .kpi-phase--growth{ background: linear-gradient(135deg, #3b0764 0%, #4c1d95 45%, #6d28d9 100%); }
        .kpi-phase .phase-icon-wrap {
            display: inline-flex;
            align-items: center;
            justify-content: center;
            width: 3rem;
            height: 3rem;
            border-radius: 12px;
            margin-bottom: 0.85rem;
            background: rgba(255,255,255,0.15);
            backdrop-filter: blur(4px);
        }
        .kpi-phase .phase-icon-wrap i {
            font-size: 1.4rem;
            color: #fff;
            filter: drop-shadow(0 0 6px rgba(255,255,255,0.5));
        }
        @keyframes phase-pulse {
            0%   { box-shadow: 0 0 0 0 rgba(255,255,255,0.35); }
            70%  { box-shadow: 0 0 0 10px rgba(255,255,255,0); }
            100% { box-shadow: 0 0 0 0 rgba(255,255,255,0); }
        }
        .kpi-phase:hover .phase-icon-wrap { animation: phase-pulse 1s ease-out infinite; }
        .kpi-phase .phase-label {
            font-size: 0.7rem;
            font-weight: 700;
            text-transform: uppercase;
            letter-spacing: 0.1em;
            color: rgba(255,255,255,0.65);
            margin-bottom: 0.25rem;
        }
        .kpi-phase .phase-val {
            font-family: 'DM Sans', sans-serif;
            font-size: 2.6rem;
            font-weight: 800;
            letter-spacing: -0.03em;
            color: #fff;
            line-height: 1;
            text-shadow: 0 2px 12px rgba(0,0,0,0.2);
        }
        .kpi-phase .phase-sub {
            font-size: 0.78rem;
            color: rgba(255,255,255,0.6);
            margin-top: 0.45rem;
        }
        .kpi-phase .phase-sub strong { color: rgba(255,255,255,0.9); font-weight: 600; }
        .kpi-phase .phase-deco {
            position: absolute;
            right: -1.5rem;
            bottom: -1.5rem;
            width: 6rem;
            height: 6rem;
            border-radius: 50%;
            background: rgba(255,255,255,0.06);
            pointer-events: none;
        }
        .kpi-phase .phase-deco2 {
            position: absolute;
            right: 1rem;
            bottom: 2.5rem;
            width: 3.5rem;
            height: 3.5rem;
            border-radius: 50%;
            background: rgba(255,255,255,0.04);
            pointer-events: none;
        }
        .target-banner {
            background: linear-gradient(120deg, #1e1b4b 0%, #312e81 50%, #1e3a5f 100%);
            color: #e0e7ff;
            border-radius: var(--radius);
            padding: 1.35rem 1.5rem;
            margin-bottom: 1.25rem;
            display: flex;
            flex-wrap: wrap;
            align-items: center;
            justify-content: space-between;
            gap: 1rem;
            box-shadow: 0 12px 40px rgba(30, 27, 75, 0.35);
        }
        .target-banner h3 { margin: 0; font-size: 1rem; font-weight: 600; opacity: 0.9; }
        .target-banner .big { font-family: 'DM Sans', sans-serif; font-size: 2rem; font-weight: 700; margin: 0.25rem 0 0; }
        .target-banner .meta { font-size: 0.85rem; opacity: 0.75; max-width: 26rem; line-height: 1.45; }
        .progress-wrap { width: min(100%, 320px); }
        .progress-wrap .bar { height: 10px; background: rgba(255,255,255,0.15); border-radius: 999px; overflow: hidden; margin-top: 0.75rem; }
        .progress-wrap .fill { height: 100%; border-radius: 999px; background: linear-gradient(90deg, #34d399, #6ee7b7); transition: width 0.6s ease; }
        .charts-grid {
            display: grid;
            grid-template-columns: repeat(2, 1fr);
            gap: 1.25rem;
            margin-bottom: 1.25rem;
        }
        @media (max-width: 1100px) { .charts-grid { grid-template-columns: 1fr; } }
        .chart-card {
            background: #fff;
            border-radius: var(--radius);
            border: 1px solid var(--border);
            box-shadow: var(--shadow);
            padding: 1.15rem 1.25rem 1.35rem;
        }
        .chart-card h4 { margin: 0 0 0.15rem; font-size: 0.95rem; font-weight: 700; font-family: 'DM Sans', sans-serif; color: var(--text); }
        .chart-card .hint { font-size: 0.78rem; color: var(--text-muted); margin-bottom: 0.75rem; }
        .chart-card .canvas-wrap { position: relative; height: 260px; }
        .chart-card.tall .canvas-wrap { height: 300px; }
        .hub-note {
            background: #fff;
            border: 1px solid var(--border);
            border-radius: var(--radius);
            padding: 1rem 1.25rem;
            font-size: 0.9rem;
            color: var(--text-muted);
            line-height: 1.5;
            box-shadow: var(--shadow);
        }
    </style>
</head>
<body class="admin-app-body admin-app-body--dashboard">
    @include('partials.admin-topbar')
    <main class="admin-main">
        <div class="dashboard-intro">
            <div>
                <h2>{{ $hub->name }}</h2>
                <p>Hub overview — CFA activity and district staff in your hub only. Fiscal year: <strong>{{ $activeFy?->name ?? '—' }}</strong></p>
                <p style="margin-top:0.75rem;">
                    <a href="{{ route('hub.batches.index') }}" style="display:inline-block;background:#059669;color:#fff;padding:0.5rem 1rem;border-radius:10px;text-decoration:none;font-weight:600;font-size:0.9rem;">Open batch manager →</a>
                </p>
            </div>
        </div>

        <div class="kpi-grid">
            <div class="kpi">
                <div class="label">CFA applications (hub)</div>
                <div class="val">{{ number_format($cfaTotal) }}</div>
                <div class="sub">{{ number_format($cfaThisMonth) }} this month · {{ number_format($cfaLast30) }} last 30 days</div>
            </div>
            <div class="kpi-phase kpi-phase--seed">
                <div class="phase-deco"></div>
                <div class="phase-deco2"></div>
                <div class="phase-icon-wrap">
                    <i class="fa-solid fa-seedling"></i>
                </div>
                <div class="phase-label">Seed Phase</div>
                <div class="phase-val">{{ number_format($seedCount) }}</div>
                <div class="phase-sub">Applications in <strong>seed stage</strong></div>
            </div>
            <div class="kpi-phase kpi-phase--early">
                <div class="phase-deco"></div>
                <div class="phase-deco2"></div>
                <div class="phase-icon-wrap">
                    <i class="fa-solid fa-rocket"></i>
                </div>
                <div class="phase-label">Early Phase</div>
                <div class="phase-val">{{ number_format($earlyCount) }}</div>
                <div class="phase-sub">Applications in <strong>early stage</strong></div>
            </div>
            <div class="kpi-phase kpi-phase--growth">
                <div class="phase-deco"></div>
                <div class="phase-deco2"></div>
                <div class="phase-icon-wrap">
                    <i class="fa-solid fa-chart-line"></i>
                </div>
                <div class="phase-label">Growth Phase</div>
                <div class="phase-val">{{ number_format($growthCount) }}</div>
                <div class="phase-sub">Applications in <strong>growth stage</strong></div>
            </div>
        </div>

        <div class="target-banner">
            <div>
                <h3>CFA progress (hub districts vs applications)</h3>
                @if ($hubCfaTargetSum !== null && (int) $hubCfaTargetSum > 0)
                    <p class="big">{{ number_format($cfaTotal) }} / {{ number_format($hubCfaTargetSum) }}</p>
                    <p class="meta">Applications received vs sum of CFA targets set for districts in this hub. State admin sets targets under district allocation.</p>
                @elseif ($hubCfaTargetSum !== null)
                    <p class="big">{{ number_format($cfaTotal) }}</p>
                    <p class="meta">No CFA row targets for hub districts this year yet — {{ number_format($cfaTotal) }} application(s) recorded.</p>
                @else
                    <p class="big">—</p>
                    <p class="meta">Set an active fiscal year and CFA deliverable targets for districts in this hub to see totals here.</p>
                @endif
            </div>
            @if ($hubCfaTargetSum !== null && (int) $hubCfaTargetSum > 0)
                @php $pct = min(100, round(((int) $cfaTotal / (int) $hubCfaTargetSum) * 100)); @endphp
                <div class="progress-wrap">
                    <div style="display:flex;justify-content:space-between;font-size:0.85rem;opacity:0.85;">
                        <span>Applications vs hub CFA target</span>
                        <span>{{ $pct }}%</span>
                    </div>
                    <div class="bar"><div class="fill" style="width: {{ $pct }}%"></div></div>
                </div>
            @endif
        </div>

        <div class="charts-grid">
            <div class="chart-card tall">
                <h4>CFA applications (last 14 days)</h4>
                <p class="hint">Daily submissions in this hub</p>
                <div class="canvas-wrap"><canvas id="chartTrend"></canvas></div>
            </div>
            <div class="chart-card tall">
                <h4>Business category mix</h4>
                <p class="hint">From recent applications in this hub (sample)</p>
                <div class="canvas-wrap">
                    @if (count($businessMix['labels']) === 0)
                        <p style="display:flex;align-items:center;justify-content:center;height:100%;margin:0;color:var(--text-muted);font-size:0.9rem;text-align:center;padding:1rem;">No category data yet.</p>
                    @else
                        <canvas id="chartDoughnut"></canvas>
                    @endif
                </div>
            </div>
            <div class="chart-card">
                <h4>Applications by district</h4>
                <p class="hint">Districts in {{ $hub->name }}</p>
                <div class="canvas-wrap"><canvas id="chartDistrictCfa"></canvas></div>
            </div>
            <div class="chart-card">
                <h4>Staff by district</h4>
                <p class="hint">District staff in this hub</p>
                <div class="canvas-wrap"><canvas id="chartStaff"></canvas></div>
            </div>
        </div>

        <p class="hub-note">To change targets, staff accounts, or designations, contact your <strong>state admin</strong>. This dashboard is read-only for hub oversight.</p>
    </main>

@php
    $chartColors = ['#4f46e5', '#0d9488', '#ea580c', '#7c3aed', '#0891b2', '#db2777', '#ca8a04', '#16a34a'];
@endphp
<script>
(function () {
    const accent = '#4f46e5';
    const teal = '#0d9488';
    const grid = { color: 'rgba(148, 163, 184, 0.25)' };

    const trendLabels = @json($cfaTrend['labels']);
    const trendValues = @json($cfaTrend['values']);
    new Chart(document.getElementById('chartTrend'), {
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

    @if (count($businessMix['labels']) > 0)
    const mixLabels = @json($businessMix['labels']);
    const mixValues = @json($businessMix['values']);
    const mixColors = @json($chartColors);
    new Chart(document.getElementById('chartDoughnut'), {
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
    @endif

    const dLabels = @json($cfaByDistrict['labels']);
    const dValues = @json($cfaByDistrict['values']);
    new Chart(document.getElementById('chartDistrictCfa'), {
        type: 'bar',
        data: {
            labels: dLabels.length ? dLabels : ['No data'],
            datasets: [{
                label: 'CFA',
                data: dLabels.length ? dValues : [0],
                backgroundColor: dLabels.length ? dValues.map((_, i) => 'rgba(79, 70, 229, ' + (0.45 + (i % 5) * 0.1) + ')') : ['#e2e8f0'],
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

    @include('partials.attendance-modal')
</body>
</html>
