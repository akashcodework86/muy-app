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
            --text-muted: #64748b;
            --accent: #4f46e5;
            --accent2: #0d9488;
            --border: #e2e8f0;
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
        .dashboard-intro p { margin: 0.35rem 0 0; color: var(--text-muted); font-size: 0.95rem; max-width: 44rem; line-height: 1.5; }
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
        .referral-card {
            background: linear-gradient(135deg, #f8fafc 0%, #eef2ff 100%);
            border: 1px solid var(--border);
            border-radius: var(--radius);
            padding: 1.25rem 1.35rem;
            margin-bottom: 1.25rem;
            box-shadow: var(--shadow);
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
            padding: 0.55rem 1rem;
            border: none;
            border-radius: 8px;
            background: #18181b;
            color: #fff;
            font-weight: 600;
            font-size: 0.85rem;
            cursor: pointer;
            font-family: inherit;
        }
        .referral-row button:hover { background: #27272a; }
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
            background: linear-gradient(120deg, #134e4a 0%, #115e59 45%, #1e3a5f 100%);
            color: #ccfbf1;
            border-radius: var(--radius);
            padding: 1.35rem 1.5rem;
            margin-bottom: 1.25rem;
            display: flex;
            flex-wrap: wrap;
            align-items: center;
            justify-content: space-between;
            gap: 1rem;
            box-shadow: 0 12px 40px rgba(17, 94, 89, 0.28);
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
            background: #fff;
            border-radius: var(--radius);
            border: 1px solid var(--border);
            box-shadow: var(--shadow);
            padding: 1.15rem 1.25rem 1.35rem;
        }
        .chart-card h4 { margin: 0 0 0.15rem; font-size: 0.95rem; font-weight: 700; font-family: 'DM Sans', sans-serif; color: var(--text); }
        .chart-card .hint { font-size: 0.78rem; color: var(--text-muted); margin-bottom: 0.75rem; }
        .chart-card .canvas-wrap { position: relative; height: 260px; }
        .chart-card.tall .canvas-wrap { height: 280px; }
        .recent-table-wrap {
            background: #fff;
            border: 1px solid var(--border);
            border-radius: var(--radius);
            box-shadow: var(--shadow);
            overflow-x: auto;
            margin-bottom: 1.25rem;
        }
        .recent-table-wrap h4 { margin: 0; padding: 1rem 1.15rem 0.5rem; font-size: 0.95rem; font-weight: 700; color: var(--text); }
        .recent-table-wrap table { width: 100%; border-collapse: collapse; font-size: 0.85rem; }
        .recent-table-wrap th, .recent-table-wrap td { padding: 0.55rem 1rem; text-align: left; border-bottom: 1px solid #f1f5f9; }
        .recent-table-wrap th { color: var(--text-muted); font-weight: 600; font-size: 0.72rem; text-transform: uppercase; letter-spacing: 0.05em; }
        .staff-note {
            background: #fff;
            border: 1px solid var(--border);
            border-radius: var(--radius);
            padding: 1rem 1.25rem;
            font-size: 0.9rem;
            color: var(--text-muted);
            line-height: 1.5;
            box-shadow: var(--shadow);
        }
        .staff-portal-links {
            display: flex;
            flex-wrap: wrap;
            gap: 0.65rem;
            margin-bottom: 1.25rem;
        }
        .staff-portal-link {
            display: inline-flex;
            align-items: center;
            padding: 0.5rem 1rem;
            background: #fff;
            border: 1px solid var(--border);
            border-radius: 10px;
            font-size: 0.875rem;
            font-weight: 600;
            color: var(--text);
            text-decoration: none;
            box-shadow: var(--shadow);
            font-family: 'DM Sans', sans-serif;
        }
        .staff-portal-link:hover {
            border-color: #4f46e5;
            color: #4f46e5;
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
        .intel-panel {
            background: #fff;
            border: 1px solid var(--border);
            border-radius: var(--radius);
            box-shadow: var(--shadow);
            padding: 1.15rem 1.25rem 1.25rem;
            margin-bottom: 1.25rem;
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
            background: #f8fafc;
            border-radius: 10px;
            padding: 0.75rem 1rem;
            border: 1px solid #e2e8f0;
        }
        .velocity-pill strong { display: block; font-size: 0.7rem; text-transform: uppercase; letter-spacing: 0.06em; color: var(--text-muted); margin-bottom: 0.25rem; }
        .velocity-pill .num { font-family: 'DM Sans', sans-serif; font-size: 1.35rem; font-weight: 700; color: var(--text); }
        .velocity-pill .meta { font-size: 0.78rem; color: var(--text-muted); margin-top: 0.2rem; }
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
        <div class="dashboard-intro">
            <div>
                <h2>Welcome, {{ $staff->name }}</h2>
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
            </div>
        </div>

        <nav class="staff-portal-links" aria-label="Quick pages">
            <a href="{{ route('staff.monthly-targets') }}" class="staff-portal-link">Monthly targets</a>
            <a href="{{ route('staff.applications') }}" class="staff-portal-link">Application list</a>
        </nav>

        @php
            $heatMax = max(1, collect($heatmap30 ?? [])->max('count') ?: 0);
        @endphp
        <div class="insight-grid" aria-label="Key performance insights">
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
                    <div class="insight-sub">No Yes/No registration data in sample yet</div>
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
            <div class="referral-card" style="background:#fff;">
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
                <h4>Business category mix</h4>
                <p class="hint">Recent applications via your link (sample)</p>
                <div class="canvas-wrap">
                    @if (count($businessMix['labels']) === 0)
                        <p style="display:flex;align-items:center;justify-content:center;height:100%;margin:0;color:var(--text-muted);font-size:0.9rem;text-align:center;padding:1rem;">No category data yet.</p>
                    @else
                        <canvas id="staffChartDoughnut"></canvas>
                    @endif
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

            @if (count($businessMix['labels']) > 0)
            const mixLabels = @json($businessMix['labels']);
            const mixValues = @json($businessMix['values']);
            const mixColors = @json($chartColors);
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
