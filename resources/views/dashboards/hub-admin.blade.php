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
    <style>
        .hub-insight-grid { margin-top: 0.7rem; display: grid; grid-template-columns: 1.1fr 1fr 1fr; gap: 0.65rem; }
        @media (max-width: 1100px) { .hub-insight-grid { grid-template-columns: 1fr; } }
        .hub-insight-card { background: rgba(255,255,255,.88); border: 1px solid rgba(226,232,240,.95); border-radius: 12px; padding: .62rem .72rem; }
        .hub-insight-title { font-size: .58rem; font-weight: 800; text-transform: uppercase; letter-spacing: .08em; color: #6366f1; margin-bottom: .38rem; }
        .hub-insight-title-row { display:flex; justify-content:space-between; align-items:center; gap:.45rem; margin-bottom:.3rem; }
        .hub-insight-btn { font-size:.58rem; font-weight:800; letter-spacing:.06em; text-transform:uppercase; color:#0f766e; background:#ecfeff; border:1px solid #a5f3fc; border-radius:999px; padding:.14rem .45rem; text-decoration:none; line-height:1.3; }
        .hub-insight-btn:hover { background:#cffafe; color:#115e59; }
        .hub-insight-top { display: flex; justify-content: space-between; align-items: baseline; gap: .5rem; margin-bottom: .25rem; }
        .hub-insight-value { font-size: .95rem; font-weight: 800; color: #0f172a; }
        .hub-insight-meta { font-size: .67rem; color: #64748b; font-weight: 700; }
        .hub-insight-bar { height: 7px; border-radius: 999px; background: #eef2f7; border: 1px solid #dde5ef; overflow: hidden; }
        .hub-insight-fill { height: 100%; border-radius: inherit; background: linear-gradient(90deg, #4f46e5, #14b8a6); }
        .hub-insight-foot { margin-top: .34rem; font-size: .67rem; color: #475569; line-height: 1.4; }
        .hub-insight-list { display: grid; gap: .3rem; }
        .hub-insight-kpi { display: flex; justify-content: space-between; gap: .5rem; align-items: center; font-size: .7rem; padding: .24rem .3rem; background: rgba(248,250,252,.9); border:1px solid rgba(226,232,240,.95); border-radius:8px; }
        .hub-insight-kpi strong { color:#0f172a; font-weight:800; }
        .hub-insight-chip.up { color:#15803d; font-weight:800; }
        .hub-insight-chip.down { color:#b91c1c; font-weight:800; }
        .hub-insight-chip.flat { color:#475569; font-weight:800; }
        .hub-split-title { margin-top:.4rem; font-size:.56rem; font-weight:800; letter-spacing:.07em; text-transform:uppercase; color:#64748b; }
        .hub-split-list { margin-top:.3rem; display:grid; gap:.22rem; max-height:95px; overflow-y:auto; padding-right:.14rem; }
        .hub-split-row { display:flex; justify-content:space-between; gap:.45rem; font-size:.67rem; padding:.2rem .3rem; background:rgba(248,250,252,.92); border:1px solid rgba(226,232,240,.9); border-radius:7px; }
        .hub-staff-cfa-panel { display:flex; flex-direction:column; gap:.45rem; min-height: 240px; }
        .hub-staff-cfa-controls { display:flex; gap:.4rem; flex-wrap:wrap; }
        .hub-staff-cfa-input, .hub-staff-cfa-select { border:1px solid #d1d5db; border-radius:8px; padding:.34rem .5rem; font-size:.72rem; color:#1f2937; background:#fff; }
        .hub-staff-cfa-input { flex:1; min-width:140px; }
        .hub-staff-cfa-select { min-width:120px; }
        .hub-staff-cfa-list { display:flex; flex-direction:column; gap:.28rem; max-height:190px; overflow:auto; }
        .hub-staff-cfa-row { display:flex; align-items:center; gap:.45rem; padding:.32rem .4rem; border:1px solid #e5e7eb; border-radius:10px; background:#fff; }
        .hub-staff-cfa-rank { font-size:.62rem; font-weight:800; color:#64748b; min-width:1.5rem; text-align:center; }
        .hub-staff-cfa-main { display:flex; align-items:center; gap:.38rem; min-width:0; flex:1; }
        .hub-staff-cfa-avatar { width:22px; height:22px; border-radius:999px; object-fit:cover; }
        .hub-staff-cfa-avatar-fallback { width:22px; height:22px; border-radius:999px; background:#0ea5e9; color:#fff; display:inline-flex; align-items:center; justify-content:center; font-size:.62rem; font-weight:800; }
        .hub-staff-cfa-name { font-size:.72rem; font-weight:800; color:#111827; line-height:1.1; }
        .hub-staff-cfa-district { font-size:.62rem; color:#6b7280; line-height:1.1; margin-top:.05rem; }
        .hub-staff-cfa-value { font-size:.72rem; font-weight:800; color:#4338ca; background:#eef2ff; border:1px solid #c7d2fe; border-radius:999px; padding:.15rem .42rem; min-width:34px; text-align:center; }
        .hub-staff-cfa-empty { font-size:.72rem; color:#64748b; text-align:center; padding:.6rem .4rem; }
    </style>
</head>
<body class="admin-app-body admin-app-body--dashboard">
    @include('partials.admin-topbar')
    <main class="admin-main">
        @include('partials.staff-daily-check-in-reminder')
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
                        @if ($hubCfaTargetSum !== null && (int) $hubCfaTargetSum > 0)
                            @php
                                $hCfaActual = (int) ($hubCfaThisFy ?? 0);
                                $hCfaTarget = (int) $hubCfaTargetSum;
                                $hCfaPct = $hCfaTarget > 0 ? (int) round(($hCfaActual / $hCfaTarget) * 100) : 0;
                                $hCfaGap = max(0, $hCfaTarget - $hCfaActual);
                                $hOnbTarget = (int) ($hubOnboardingTarget ?? 0);
                                $hOnbAch = (int) ($hubOnboardingAchieved ?? 0);
                                $hOnbPct = (int) ($hubOnboardingProgressPct ?? 0);
                                $hOnbGap = max(0, $hOnbTarget - $hOnbAch);
                                $hOnbRows = collect($hubOnboardingByDistrict ?? [])->take(8);
                            @endphp
                            <div class="hub-insight-grid">
                                <div class="hub-insight-card">
                                    <div class="hub-insight-title">CFA Target Insight</div>
                                    <div class="hub-insight-top">
                                        <div class="hub-insight-value">{{ number_format($hCfaActual) }} / {{ number_format($hCfaTarget) }}</div>
                                        <div class="hub-insight-meta">{{ $hCfaPct }}% achieved</div>
                                    </div>
                                    <div class="hub-insight-bar"><div class="hub-insight-fill" style="width: {{ min(100, max(0, $hCfaPct)) }}%;"></div></div>
                                    <div class="hub-insight-foot">Remaining to target: <strong>{{ number_format($hCfaGap) }}</strong>.</div>
                                </div>
                                <div class="hub-insight-card">
                                    <div class="hub-insight-title">Smart Signals</div>
                                    <div class="hub-insight-list">
                                        <div class="hub-insight-kpi"><span>Last 7 days CFA</span><strong>{{ number_format((int) ($cfaLast7 ?? 0)) }}</strong></div>
                                        <div class="hub-insight-kpi"><span>Week-over-week</span><span class="hub-insight-chip {{ ($cfaWoWDeltaPct ?? 0) > 0 ? 'up' : (($cfaWoWDeltaPct ?? 0) < 0 ? 'down' : 'flat') }}">{{ ($cfaWoWDeltaPct ?? 0) > 0 ? '+' : '' }}{{ (int) ($cfaWoWDeltaPct ?? 0) }}%</span></div>
                                        <div class="hub-insight-kpi"><span>Top district today</span><strong>{{ $todayTopDistrict['name'] ?? '—' }} @if(isset($todayTopDistrict['count']))({{ number_format((int) $todayTopDistrict['count']) }})@endif</strong></div>
                                        <div class="hub-insight-kpi"><span>Districts with 0 today</span><strong>{{ number_format((int) ($todayZeroDistricts ?? 0)) }}</strong></div>
                                    </div>
                                </div>
                                <div class="hub-insight-card">
                                    <div class="hub-insight-title-row">
                                        <div class="hub-insight-title" style="margin:0;">Onboarding Insight</div>
                                        <a href="{{ route('hub.onboarding-insight.index') }}" class="hub-insight-btn">Details</a>
                                    </div>
                                    @if ($hOnbTarget > 0)
                                        <div class="hub-insight-top">
                                            <div class="hub-insight-value">{{ number_format($hOnbAch) }} / {{ number_format($hOnbTarget) }}</div>
                                            <div class="hub-insight-meta">{{ $hOnbPct }}% achieved</div>
                                        </div>
                                        <div class="hub-insight-bar"><div class="hub-insight-fill" style="width: {{ min(100, max(0, $hOnbPct)) }}%;background:linear-gradient(90deg,#0ea5e9,#10b981);"></div></div>
                                        <div class="hub-insight-foot">Remaining onboarding gap: <strong>{{ number_format($hOnbGap) }}</strong>.</div>
                                        <div class="hub-split-title">District-wise bifurcation ({{ number_format($hOnbAch) }})</div>
                                        <div class="hub-split-list">
                                            @forelse ($hOnbRows as $row)
                                                <div class="hub-split-row"><span>{{ $row['district'] }}</span><strong>{{ number_format((int) ($row['count'] ?? 0)) }}</strong></div>
                                            @empty
                                                <div class="hub-insight-foot" style="margin-top:0;">No onboarding split yet.</div>
                                            @endforelse
                                        </div>
                                    @else
                                        <div class="hub-insight-foot">Onboarding target is not configured for this hub yet.</div>
                                    @endif
                                </div>
                            </div>
                        @endif

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
                    <h4>CFA by staffs</h4>
                    <p class="hint">Search by staff name and filter by district (FY {{ $activeFy?->name ?? '2026-27' }}).</p>
                    @php
                        $hubStaffRows = $staffCfaByStaff ?? [];
                        $hubDistrictOptions = collect($hubStaffRows)->pluck('district')->filter()->unique()->sort()->values()->all();
                    @endphp
                    <div class="hub-staff-cfa-panel">
                        <div class="hub-staff-cfa-controls">
                            <input id="hubStaffCfaSearch" class="hub-staff-cfa-input" type="text" placeholder="Search staff name..." autocomplete="off">
                            <select id="hubStaffCfaDistrictFilter" class="hub-staff-cfa-select">
                                <option value="">All districts</option>
                                @foreach ($hubDistrictOptions as $districtName)
                                    <option value="{{ strtolower($districtName) }}">{{ $districtName }}</option>
                                @endforeach
                            </select>
                        </div>
                        <div class="hub-staff-cfa-list" id="hubStaffCfaList">
                            @forelse ($hubStaffRows as $index => $row)
                                <div class="hub-staff-cfa-row" data-name="{{ strtolower($row['name']) }}" data-district="{{ strtolower($row['district']) }}">
                                    <div class="hub-staff-cfa-rank">#{{ $index + 1 }}</div>
                                    <div class="hub-staff-cfa-main">
                                        @if (!empty($row['avatar_url']))
                                            <img src="{{ $row['avatar_url'] }}" alt="" class="hub-staff-cfa-avatar">
                                        @else
                                            <span class="hub-staff-cfa-avatar-fallback">{{ strtoupper(substr(trim((string) $row['name']), 0, 1)) ?: '?' }}</span>
                                        @endif
                                        <div>
                                            <div class="hub-staff-cfa-name">{{ $row['name'] }}</div>
                                            <div class="hub-staff-cfa-district">{{ $row['district'] }}</div>
                                        </div>
                                    </div>
                                    <div class="hub-staff-cfa-value">{{ number_format((int) $row['cfa_total']) }}</div>
                                </div>
                            @empty
                                <div class="hub-staff-cfa-empty">No staff data yet</div>
                            @endforelse
                            <div class="hub-staff-cfa-empty" id="hubStaffCfaNoResults" style="display:none;">No staff matches this search/filter</div>
                        </div>
                    </div>
                </div>
            </div>

            <p class="hub-foot-note">To change targets, staff accounts, or designations, contact your <strong>state admin</strong>. This dashboard is read-only for hub oversight.</p>
            <div style="margin-top:0.65rem;">
                <a href="{{ route('library.documents.index') }}" class="hub-batch-link">Open document repository <i class="fa-solid fa-arrow-right" aria-hidden="true"></i></a>
            </div>
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

    const hubSearch = document.getElementById('hubStaffCfaSearch');
    const hubDistrictSelect = document.getElementById('hubStaffCfaDistrictFilter');
    const hubRows = Array.from(document.querySelectorAll('#hubStaffCfaList .hub-staff-cfa-row'));
    const hubNoResults = document.getElementById('hubStaffCfaNoResults');
    const applyHubStaffFilters = () => {
        const term = (hubSearch?.value || '').trim().toLowerCase();
        const district = (hubDistrictSelect?.value || '').trim().toLowerCase();
        let visible = 0;
        hubRows.forEach((row) => {
            const name = (row.dataset.name || '');
            const d = (row.dataset.district || '');
            const okName = !term || name.includes(term);
            const okDistrict = !district || d === district;
            const show = okName && okDistrict;
            row.style.display = show ? '' : 'none';
            if (show) visible++;
        });
        if (hubNoResults) {
            hubNoResults.style.display = visible === 0 ? '' : 'none';
        }
    };
    if (hubSearch) hubSearch.addEventListener('input', applyHubStaffFilters);
    if (hubDistrictSelect) hubDistrictSelect.addEventListener('change', applyHubStaffFilters);
})();
</script>
@include('partials.app-footer')
</body>
</html>
