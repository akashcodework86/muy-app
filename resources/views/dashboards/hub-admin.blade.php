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
                    </div>
                </div>

                <div class="hub-hero-col hub-hero-col--metrics glass-surface">
                    <div class="hub-hero-col__title">
                        <i class="fa-solid fa-chart-line" aria-hidden="true"></i>
                        Applications &amp; stage mix (hub)
                    </div>
                    <div class="hub-highlight-card__header">
                        <span class="label">CFA this fiscal year · all hub districts</span>
                        <span class="label-time"><i class="fa-regular fa-clock"></i> {{ now()->setTimezone('Asia/Kolkata')->format('d M, h:i A') }} IST</span>
                    </div>
                    <div class="hub-apps-highlight">
                        <div class="hub-apps-highlight__main">
                            <div class="hub-apps-highlight__number">{{ number_format((int) ($hubCfaThisFy ?? 0)) }}</div>
                            <div class="hub-apps-highlight__label">Total CFA (FY, hub-wide)</div>
                        </div>
                        @if ($hubCfaTargetSum !== null && (int) $hubCfaTargetSum > 0 && $hubCfaThisFy !== null)
                            @php
                                $fyPct = min(100, (int) round(((int) $hubCfaThisFy / (int) $hubCfaTargetSum) * 100));
                            @endphp
                            <div class="hub-progress-bar-wrap">
                                <div class="hub-progress-bar-fill" style="width: {{ $fyPct }}%;">
                                    <span>{{ $fyPct }}%</span>
                                </div>
                            </div>
                            <p class="hub-progress-bar-meta">
                                {{ number_format((int) $hubCfaThisFy) }} of {{ number_format((int) $hubCfaTargetSum) }} hub CFA target (district rows summed)
                            </p>
                        @else
                            <p class="hub-progress-bar-meta">Set district CFA targets for this hub to see FY progress here.</p>
                        @endif
                    </div>
                    <div class="hub-stage-row" role="group" aria-label="Stage counts, hub-wide">
                        <div class="hub-stage-pill hub-stage-pill--seed">Seed<div class="n">{{ number_format($seedCount) }}</div></div>
                        <div class="hub-stage-pill hub-stage-pill--early">Early<div class="n">{{ number_format($earlyCount) }}</div></div>
                        <div class="hub-stage-pill hub-stage-pill--growth">Growth<div class="n">{{ number_format($growthCount) }}</div></div>
                    </div>
                    <p style="margin:0.65rem 0 0;font-size:0.62rem;color:#94a3b8;line-height:1.45;">
                        Stage counts use saved form stage across all applications in hub districts. Activity cards: {{ number_format($cfaThisMonth) }} this month · {{ number_format($cfaLast30) }} last 30 days.
                    </p>
                </div>
            </div>

            <div class="hub-target-strip">
                <div>
                    <h3>CFA progress (hub districts vs applications)</h3>
                    @if ($hubCfaTargetSum !== null && (int) $hubCfaTargetSum > 0 && $hubCfaThisFy !== null)
                        <p class="big">{{ number_format((int) $hubCfaThisFy) }} / {{ number_format((int) $hubCfaTargetSum) }}</p>
                        <p class="meta">FY applications in this hub vs sum of CFA targets for hub districts. State admin sets targets under district allocation.</p>
                    @elseif ($hubCfaTargetSum !== null)
                        <p class="big">{{ number_format((int) ($hubCfaThisFy ?? 0)) }}</p>
                        <p class="meta">No CFA row targets for hub districts this year yet — FY applications shown above.</p>
                    @else
                        <p class="big">—</p>
                        <p class="meta">Set an active fiscal year and CFA deliverable targets for districts in this hub.</p>
                    @endif
                </div>
                @if ($hubCfaTargetSum !== null && (int) $hubCfaTargetSum > 0 && $hubCfaThisFy !== null)
                    @php $stripPct = min(100, (int) round(((int) $hubCfaThisFy / (int) $hubCfaTargetSum) * 100)); @endphp
                    <div class="progress-wrap">
                        <div style="display:flex;justify-content:space-between;font-size:0.85rem;opacity:0.85;">
                            <span>FY applications vs hub CFA target</span>
                            <span>{{ $stripPct }}%</span>
                        </div>
                        <div class="bar"><div class="fill" style="width: {{ $stripPct }}%"></div></div>
                    </div>
                @endif
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
