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
    @include('partials.hub-dashboard-staff-skin-styles')
</head>
<body class="admin-app-body admin-app-body--dashboard">
    @include('partials.admin-topbar')
    <main class="admin-main">
        <div class="hub-dashboard-shell">
            <div class="hub-hero-three-col">
                <div class="hub-hero-col hub-hero-col--welcome glass-surface">
                    <div class="hub-hero-col__title">
                        <i class="fa-solid fa-building-user" aria-hidden="true"></i>
                        Hub admin cockpit
                    </div>
                    <div class="hub-welcome-intro">
                        <h2>Welcome back, {{ auth()->user()->name }}</h2>
                        <p>
                            CFA numbers below are <strong>aggregated for every district</strong> in <strong>{{ $hub->name }}</strong>
                            ({{ $districtsInHub }} district{{ $districtsInHub === 1 ? '' : 's' }}). Same hub scope as your batch tools.
                        </p>
                        <div class="hub-welcome-meta-pills">
                            <div class="hub-welcome-meta-pill">
                                <i class="fa-regular fa-calendar" aria-hidden="true"></i>
                                <span><strong>Fiscal year:</strong> {{ $activeFy?->name ?? '—' }}</span>
                            </div>
                            <div class="hub-welcome-meta-pill">
                                <i class="fa-solid fa-users" aria-hidden="true"></i>
                                <span><strong>District staff:</strong> {{ number_format($staffActive) }} active · {{ number_format($staffTotal) }} total</span>
                            </div>
                            <div class="hub-welcome-meta-pill hub-welcome-meta-pill--row">
                                <span><i class="fa-solid fa-layer-group" aria-hidden="true"></i> <strong>All-time CFA in hub:</strong> {{ number_format($cfaTotal) }}</span>
                                <a href="{{ route('hub.batches.index') }}" class="hub-batch-link">Open batch manager <i class="fa-solid fa-arrow-right" aria-hidden="true"></i></a>
                            </div>
                        </div>

                        <div class="hub-stage-row" role="group" aria-label="Stage counts, hub-wide" style="margin-top:0.9rem;">
                            <div class="hub-stage-pill hub-stage-pill--seed">Seed<div class="n">{{ number_format($seedCount) }}</div></div>
                            <div class="hub-stage-pill hub-stage-pill--early">Early<div class="n">{{ number_format($earlyCount) }}</div></div>
                            <div class="hub-stage-pill hub-stage-pill--growth">Growth<div class="n">{{ number_format($growthCount) }}</div></div>
                        </div>
                        <p style="margin:0.55rem 0 0;font-size:0.62rem;color:#94a3b8;line-height:1.45;">
                            Stage counts use saved form stage across hub districts · {{ number_format($cfaThisMonth) }} this month · {{ number_format($cfaLast30) }} last 30 days.
                        </p>
                    </div>
                </div>

                @php
                    $heroRingPct = $heroProgressPct !== null ? (int) min(100, max(0, $heroProgressPct)) : 0;
                    $heroRingCirc = 2 * M_PI * 40;
                    $heroRingOffset = $heroRingCirc * (1 - $heroRingPct / 100);

                    $sparkVals = $heroSparkline30['values'] ?? [];
                    $sparkSum = (int) array_sum($sparkVals);
                    $sparkMax = ! empty($sparkVals) ? max(max($sparkVals), 1) : 1;
                    $sparkW = 160;
                    $sparkH = 34;
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

                <div class="hub-hero-col hub-hero-col--metrics glass-surface hub-hero-right">
                    <div class="hub-hero-col__title">
                        <i class="fa-solid fa-chart-line" aria-hidden="true"></i>
                        Hub pulse · <span style="text-transform:none;letter-spacing:0;color:#64748b;font-weight:700;">{{ now()->setTimezone('Asia/Kolkata')->format('d M, h:i A') }} IST</span>
                    </div>

                    <div class="hero-ring-card" title="CFA submissions vs hub target for {{ $activeFy?->name ?? '—' }}">
                        <svg class="hero-ring-svg" viewBox="0 0 100 100" aria-hidden="true">
                            <defs>
                                <linearGradient id="heroRingGradHub" x1="0%" y1="0%" x2="100%" y2="100%">
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
                            <div class="hero-ring-body__eyebrow">Hub FY progress</div>
                            <div class="hero-ring-body__value">
                                {{ number_format((int) ($hubCfaThisFy ?? 0)) }}
                                @if ($hubCfaTargetSum !== null && (int) $hubCfaTargetSum > 0)
                                    <small>/ {{ number_format((int) $hubCfaTargetSum) }}</small>
                                @endif
                            </div>
                            <span class="hero-ring-body__label">CFA submissions · {{ $activeFy?->name ?? 'FY' }}</span>
                            @if ($heroRemaining !== null)
                                <span class="hero-ring-body__gap @if ($heroRemaining === 0) is-good @endif">
                                    <i class="fa-solid @if ($heroRemaining === 0) fa-trophy @else fa-arrow-trend-up @endif" aria-hidden="true"></i>
                                    @if ($heroRemaining === 0) Target met! @else {{ number_format($heroRemaining) }} to go @endif
                                </span>
                            @else
                                <span class="hero-ring-body__gap">
                                    <i class="fa-solid fa-circle-info" aria-hidden="true"></i>
                                    No hub target set
                                </span>
                            @endif
                        </div>
                    </div>

                    <div class="hero-today-row">
                        <div class="hero-today hero-today--cfa">
                            <div class="hero-today__head"><i class="fa-solid fa-file-circle-plus" aria-hidden="true"></i> CFA today</div>
                            <div class="hero-today__value">{{ number_format((int) ($heroCfaToday ?? 0)) }}</div>
                            @if ($todayDelta > 0)
                                <span class="hero-today__delta is-up"><i class="fa-solid fa-caret-up"></i> {{ $todayDelta }} vs yest.</span>
                            @elseif ($todayDelta < 0)
                                <span class="hero-today__delta is-down"><i class="fa-solid fa-caret-down"></i> {{ abs($todayDelta) }} vs yest.</span>
                            @else
                                <span class="hero-today__delta">— same as yest.</span>
                            @endif
                        </div>
                        <div class="hero-today hero-today--mentor">
                            <div class="hero-today__head"><i class="fa-solid fa-handshake" aria-hidden="true"></i> Mentorship</div>
                            <div class="hero-today__value">{{ number_format((int) ($heroMentorshipPending ?? 0)) }}</div>
                            <span class="hero-today__delta">pending in hub</span>
                        </div>
                        <div class="hero-today hero-today--online">
                            <div class="hero-today__head"><i class="fa-solid fa-signal" aria-hidden="true"></i> Online now</div>
                            <div class="hero-today__value">{{ number_format((int) ($heroStaffOnlineNow ?? 0)) }}</div>
                            <span class="hero-today__delta">hub users · 3 min</span>
                        </div>
                    </div>

                    @if (! empty($sparkLine))
                    <div class="hero-spark" title="Daily CFA submissions in hub · last 30 days">
                        <div class="hero-spark__left">
                            <div class="hero-spark__eyebrow">30-DAY PULSE</div>
                            <div class="hero-spark__value">{{ number_format($sparkSum) }} <small>CFAs</small></div>
                        </div>
                        <div class="hero-spark__chart" aria-hidden="true">
                            <svg viewBox="0 0 {{ $sparkW }} {{ $sparkH }}" preserveAspectRatio="none">
                                <defs>
                                    <linearGradient id="heroSparkGradHub" x1="0%" y1="0%" x2="0%" y2="100%">
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

            <div class="hub-charts-grid">
                <div class="hub-chart-card tall">
                    <h4>CFA applications (last 14 days)</h4>
                    <p class="hint">Daily submissions in this hub (all districts)</p>
                    <div class="canvas-wrap"><canvas id="chartTrend"></canvas></div>
                </div>
                <div class="hub-chart-card tall">
                    <h4>Business category mix</h4>
                    <p class="hint">Recent applications in hub (sample)</p>
                    <div class="canvas-wrap">
                        @if (count($businessMix['labels']) === 0)
                            <p style="display:flex;align-items:center;justify-content:center;height:100%;margin:0;color:#64748b;font-size:0.9rem;text-align:center;padding:1rem;">No category data yet.</p>
                        @else
                            <canvas id="chartDoughnut"></canvas>
                        @endif
                    </div>
                </div>
                <div class="hub-chart-card">
                    <h4>Applications by district</h4>
                    <p class="hint">Districts in {{ $hub->name }}</p>
                    <div class="canvas-wrap"><canvas id="chartDistrictCfa"></canvas></div>
                </div>
                <div class="hub-chart-card">
                    <h4>Staff by district</h4>
                    <p class="hint">District staff in this hub</p>
                    <div class="canvas-wrap"><canvas id="chartStaff"></canvas></div>
                </div>
            </div>

            <p class="hub-foot-note">To change targets, staff accounts, or designations, contact your <strong>state admin</strong>. This dashboard is read-only for hub oversight.</p>
        </div>
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
</body>
</html>
