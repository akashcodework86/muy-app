@extends('layouts.admin')

@section('title', 'Deliverables')
@section('heading', 'Deliverables')

@if (auth()->user()?->role === 'state_admin')
@section('page_meta')
    <p class="admin-page-meta">
        <span class="dlv-state-viewer-badge" aria-label="Viewing as State Admin">State Admin</span>
    </p>
@endsection
@endif

@section('content')
    @php
        $queryParams = $filter->queryParams();
        $formDates = $filter->formDates($fiscalYear ?? null);
        $screenshotScopeLabel = $scopeLabel ?? 'Program scope';
        $screenshotPeriodLabel = ! empty($formDates['dateFrom']) && ! empty($formDates['dateTo'])
            ? \Carbon\Carbon::parse($formDates['dateFrom'])->format('d M Y').' – '.\Carbon\Carbon::parse($formDates['dateTo'])->format('d M Y')
            : 'Full fiscal year';
    @endphp

    <div id="deliverables-screenshot-root">
        <div id="deliverables-screenshot-banner" class="dlv-screenshot-banner" aria-hidden="true">
            <div class="dlv-screenshot-banner__title">MUY Program Deliverables</div>
            <div class="dlv-screenshot-banner__meta">
                <span>{{ $fiscalYear?->name ?? 'Fiscal year' }}</span>
                <span>·</span>
                <span>{{ $screenshotScopeLabel }}</span>
                <span>·</span>
                <span>{{ $screenshotPeriodLabel }}</span>
                <span>·</span>
                <span>Captured {{ now()->format('d M Y, H:i') }}</span>
            </div>
        </div>

    <form method="get" action="{{ route($indexRoute) }}" id="deliverables-filter-form" style="display:flex;flex-wrap:wrap;gap:0.65rem;align-items:flex-end;margin-bottom:1rem;background:#fff;border:1px solid #e4e4e7;border-radius:10px;padding:0.75rem 0.9rem;">
        <input type="hidden" name="fiscal_year_id" id="fiscal_year_id" value="{{ $fiscalYearId }}">
        @if ($canPickDistrict)
            <div style="display:flex;flex-direction:column;gap:0.25rem;">
                <label for="district_id" style="font-size:0.75rem;font-weight:600;color:#475569;">District</label>
                <select name="district_id" id="district_id" style="padding:0.45rem 0.55rem;border:1px solid #d4d4d8;border-radius:8px;min-width:11rem;">
                    <option value="">All in scope</option>
                    @foreach ($districts as $d)
                        <option value="{{ $d->id }}" @selected((int) ($filter->districtId ?? 0) === (int) $d->id)>{{ $d->name }}</option>
                    @endforeach
                </select>
            </div>
        @endif
        <div style="display:flex;flex-direction:column;gap:0.25rem;">
            <label for="indicator_type" style="font-size:0.75rem;font-weight:600;color:#475569;">Type of Indicator</label>
            <select name="indicator_type" id="indicator_type" style="padding:0.45rem 0.55rem;border:1px solid #d4d4d8;border-radius:8px;min-width:10rem;">
                <option value="">All types</option>
                @foreach ($indicatorTypeOptions as $option)
                    <option value="{{ $option }}" @selected(($filter->indicatorType ?? '') === $option)>{{ $option }}</option>
                @endforeach
            </select>
        </div>
        <div style="display:flex;flex-direction:column;gap:0.25rem;">
            <label for="level" style="font-size:0.75rem;font-weight:600;color:#475569;">Spoke/ Hub/ State</label>
            <select name="level" id="level" style="padding:0.45rem 0.55rem;border:1px solid #d4d4d8;border-radius:8px;min-width:11rem;">
                <option value="">All levels</option>
                @foreach ($levelOptions as $option)
                    <option value="{{ $option }}" @selected(($filter->level ?? '') === $option)>{{ $option }}</option>
                @endforeach
            </select>
        </div>
        <div style="display:flex;flex-direction:column;gap:0.25rem;">
            <label for="quarter" style="font-size:0.75rem;font-weight:600;color:#475569;">Quarter</label>
            <select name="quarter" id="quarter" style="padding:0.45rem 0.55rem;border:1px solid #d4d4d8;border-radius:8px;min-width:10rem;">
                <option value="">All quarters</option>
                @foreach (range(1, 4) as $q)
                    <option value="{{ $q }}" @selected((int) ($filter->quarter ?? 0) === $q)>
                        Q{{ $q }}@if ($fiscalYear) ({{ $fiscalYear->fiscalQuarterLabel($q) }})@endif
                    </option>
                @endforeach
            </select>
        </div>
        <div style="display:flex;flex-direction:column;gap:0.25rem;">
            <label for="month" style="font-size:0.75rem;font-weight:600;color:#475569;">Month</label>
            <select name="month" id="month" style="padding:0.45rem 0.55rem;border:1px solid #d4d4d8;border-radius:8px;min-width:8rem;">
                <option value="">All months</option>
                @foreach (range(1, 12) as $m)
                    <option value="{{ $m }}" @selected((int) ($filter->month ?? 0) === $m)>{{ \Carbon\Carbon::create(null, $m, 1)->format('F') }}</option>
                @endforeach
            </select>
        </div>
        <div style="display:flex;flex-direction:column;gap:0.25rem;">
            <label for="date_from" style="font-size:0.75rem;font-weight:600;color:#475569;">From date</label>
            <input type="date" name="date_from" id="date_from" value="{{ $formDates['dateFrom'] ?? '' }}" style="padding:0.45rem 0.55rem;border:1px solid #d4d4d8;border-radius:8px;min-width:10.5rem;">
        </div>
        <div style="display:flex;flex-direction:column;gap:0.25rem;">
            <label for="date_to" style="font-size:0.75rem;font-weight:600;color:#475569;">To date</label>
            <input type="date" name="date_to" id="date_to" value="{{ $formDates['dateTo'] ?? '' }}" style="padding:0.45rem 0.55rem;border:1px solid #d4d4d8;border-radius:8px;min-width:10.5rem;">
        </div>
        <button type="submit" style="background:#18181b;color:#fff;border:none;padding:0.48rem 0.9rem;border-radius:8px;font-weight:600;cursor:pointer;">Apply</button>
        <a href="{{ route($indexRoute) }}" style="padding:0.48rem 0.75rem;border:1px solid #d4d4d8;border-radius:8px;text-decoration:none;color:#334155;font-size:0.88rem;">Reset</a>
        <a href="{{ route($exportRoute, $queryParams) }}" style="text-decoration:none;background:#065f46;color:#fff;padding:0.48rem 0.9rem;border-radius:8px;font-weight:600;font-size:0.88rem;">⬇ Export .xlsx</a>
        <button
            type="button"
            id="deliverables-screenshot-btn"
            title="Download full-page PNG (first indicator to last)"
            style="background:#1d4ed8;color:#fff;border:none;padding:0.48rem 0.9rem;border-radius:8px;font-weight:600;font-size:0.88rem;cursor:pointer;display:inline-flex;align-items:center;gap:0.35rem;"
        >📸 Screenshot (4K)</button>
        @if ($showActivityGuideLink ?? false)
            <a href="{{ route($activityGuideRoute, $queryParams) }}" style="text-decoration:none;background:#fff7ed;color:#c2410c;border:1px solid #fed7aa;padding:0.48rem 0.9rem;border-radius:8px;font-weight:700;font-size:0.88rem;">Activity guide →</a>
        @endif
        @if ($showStateTargetsLink ?? false)
            <a href="{{ route('admin.targets.official-state-monthly', ['fiscal_year_id' => $fiscalYearId]) }}" style="font-size:0.85rem;color:#4f46e5;margin-left:auto;">Edit state targets →</a>
        @endif
    </form>

    @if ($canEditRowMetadata ?? false)
        <p style="margin:0 0 0.85rem; padding:0.55rem 0.75rem; background:#ecfdf5; border:1px solid #a7f3d0; border-radius:8px; font-size:0.84rem; color:#065f46; max-width:55rem;">
            Edit mode is on — click <em>Type of Indicator</em> or <em>Spoke/Hub/State</em> to update. Changes save automatically.
        </p>
    @endif

    <p class="dlv-pct-legend">
        <strong class="dlv-pct-legend__title">Achievement %:</strong>
        <span class="dlv-pct-legend-item">
            <span class="dlv-pct-legend-chip">
                <span class="dlv-pct-bar dlv-pct-bar--legend" aria-hidden="true"><span class="dlv-pct-bar__fill dlv-pct-bar__fill--good" style="width:95%"></span></span>
                <span class="dlv-pct-badge dlv-pct-badge--good dlv-pct-badge--sm">≥90%</span>
            </span>
            <span class="dlv-pct-legend-label">on track</span>
        </span>
        <span class="dlv-pct-legend-sep" aria-hidden="true">·</span>
        <span class="dlv-pct-legend-item">
            <span class="dlv-pct-legend-chip">
                <span class="dlv-pct-bar dlv-pct-bar--legend" aria-hidden="true"><span class="dlv-pct-bar__fill dlv-pct-bar__fill--warn" style="width:70%"></span></span>
                <span class="dlv-pct-badge dlv-pct-badge--warn dlv-pct-badge--sm">60–89%</span>
            </span>
            <span class="dlv-pct-legend-label">needs attention</span>
        </span>
        <span class="dlv-pct-legend-sep" aria-hidden="true">·</span>
        <span class="dlv-pct-legend-item">
            <span class="dlv-pct-legend-chip">
                <span class="dlv-pct-bar dlv-pct-bar--legend" aria-hidden="true"><span class="dlv-pct-bar__fill dlv-pct-bar__fill--critical" style="width:25%"></span></span>
                <span class="dlv-pct-badge dlv-pct-badge--critical dlv-pct-badge--sm">&lt;60%</span>
            </span>
            <span class="dlv-pct-legend-label">critical</span>
        </span>
    </p>

    <div id="deliverables-table-wrap" style="overflow-x:auto;">
        <table id="deliverables-table" class="deliverables-report-table" style="width:100%;min-width:72rem;border-collapse:collapse;font-size:0.86rem;background:#fff;table-layout:fixed;">
            <thead>
                <tr>
                    <th style="padding:0.55rem 0.45rem;border:1px solid #1c1917;background:#9a3412;color:#fff;font-weight:700;text-align:center;min-width:3.5rem;">S.N.</th>
                    <th style="padding:0.55rem 0.65rem;border:1px solid #1c1917;background:#9a3412;color:#fff;font-weight:700;text-align:left;min-width:16rem;">Indicator</th>
                    <th style="padding:0.55rem 0.65rem;border:1px solid #1c1917;background:#9a3412;color:#fff;font-weight:700;text-align:center;min-width:8rem;">Type of Indicator</th>
                    <th style="padding:0.55rem 0.65rem;border:1px solid #1c1917;background:#9a3412;color:#fff;font-weight:700;text-align:center;min-width:8rem;">Spoke/ Hub/ State</th>
                    <th
                        style="padding:0.55rem 0.65rem;border:1px solid #1c1917;background:#9a3412;color:#fff;font-weight:700;text-align:center;min-width:6rem;"
                        @if ($filter->hasExplicitDateFilter()) title="Targets are summed from the official monthly plan for the selected fiscal months." @endif
                    >Targets{!! $filter->hasExplicitDateFilter() ? '<span style="font-weight:400;font-size:0.7rem;opacity:0.85;display:block;line-height:1;margin-top:0.15rem;">(period)</span>' : '' !!}</th>
                    <th style="padding:0.55rem 0.65rem;border:1px solid #1c1917;background:#9a3412;color:#fff;font-weight:700;text-align:center;min-width:6rem;">Achievement</th>
                    <th style="padding:0.55rem 0.65rem;border:1px solid #1c1917;background:#9a3412;color:#fff;font-weight:700;text-align:center;min-width:6rem;">Achievement (%)</th>
                </tr>
            </thead>
            <tbody>
                @forelse ($rows as $row)
                    @php
                        $isHeading = in_array($row['row_type'], ['pillar', 'subcategory'], true);
                    @endphp
                    <tr @if ($isHeading) style="background:#ffedd5;font-weight:700;" @endif>
                        <td style="padding:0.45rem;border:1px solid #d4d4d8;text-align:center;">{{ $row['serial'] }}</td>
                        <td style="padding:0.45rem 0.55rem;border:1px solid #d4d4d8;">{{ $row['name'] }}</td>
                        <td style="padding:0.45rem;border:1px solid #d4d4d8;text-align:center;">
                            @if ($canEditRowMetadata && ! $isHeading)
                                <select class="dlv-meta-select" data-serial="{{ $row['serial'] }}" data-field="indicator_type" aria-label="Type of indicator for {{ $row['serial'] }}">
                                    <option value="">—</option>
                                    @foreach ($indicatorTypeOptions as $option)
                                        <option value="{{ $option }}" @selected($row['indicator_type'] === $option)>{{ $option }}</option>
                                    @endforeach
                                </select>
                            @else
                                {{ $isHeading ? '' : ($row['indicator_type'] ?: '—') }}
                            @endif
                        </td>
                        <td style="padding:0.45rem;border:1px solid #d4d4d8;text-align:center;">
                            @if ($canEditRowMetadata && ! $isHeading)
                                <select class="dlv-meta-select" data-serial="{{ $row['serial'] }}" data-field="level" aria-label="Spoke Hub State for {{ $row['serial'] }}">
                                    <option value="">—</option>
                                    @foreach ($levelOptions as $option)
                                        <option value="{{ $option }}" @selected($row['level'] === $option)>{{ $option }}</option>
                                    @endforeach
                                </select>
                            @else
                                {{ $isHeading ? '' : ($row['level'] ?: '—') }}
                            @endif
                        </td>
                        <td style="padding:0.45rem;border:1px solid #d4d4d8;text-align:center;">
                            @if (! $isHeading && ($row['target_label'] ?? null))
                                <span style="font-size:0.78rem;font-weight:600;color:#1d4ed8;white-space:nowrap;">{{ $row['target_label'] }}</span>
                            @elseif (! $isHeading && $row['target'] !== null)
                                {{ number_format($row['target']) }}
                            @endif
                        </td>
                        <td style="padding:0.45rem;border:1px solid #d4d4d8;text-align:center;">
                            @if (! $isHeading && ($row['drilldown'] ?? false))
                                <button
                                    type="button"
                                    class="dlv-ach-btn"
                                    data-dlv-breakdown
                                    data-serial="{{ $row['serial'] }}"
                                    data-name="{{ $row['name'] }}"
                                    title="View achievement breakdown"
                                >{{ number_format((int) ($row['achievement'] ?? 0)) }}</button>
                            @elseif (! $isHeading && $row['achievement'] !== null)
                                <span class="dlv-ach-static">{{ number_format($row['achievement']) }}</span>
                            @endif
                        </td>
                        <td style="padding:0.45rem;border:1px solid #d4d4d8;text-align:center;">
                            @if (! $isHeading && $row['achievement_pct'] !== null)
                                @php
                                    $tone = $row['performance_tone'] ?? 'critical';
                                    $barWidth = min(100, (int) $row['achievement_pct']);
                                @endphp
                                <div class="dlv-pct-stack">
                                    <div class="dlv-pct-bar" role="presentation" aria-hidden="true">
                                        <div class="dlv-pct-bar__fill dlv-pct-bar__fill--{{ $tone }}" style="width:{{ $barWidth }}%"></div>
                                    </div>
                                    <span class="dlv-pct-badge dlv-pct-badge--{{ $tone }}">
                                        {{ $row['achievement_pct'] }}%
                                    </span>
                                </div>
                            @endif
                        </td>
                    </tr>
                @empty
                    <tr>
                        <td colspan="7" style="padding:1.25rem;text-align:center;color:#64748b;border:1px solid #d4d4d8;">No data for this scope.</td>
                    </tr>
                @endforelse
            </tbody>
        </table>
    </div>
    </div>{{-- #deliverables-screenshot-root --}}

    @include('deliverables.partials.breakdown-drawer')

    @push('styles')
        <style>
            .dlv-state-viewer-badge {
                display: inline-block;
                background: linear-gradient(135deg, #ecfdf5 0%, #d1fae5 100%);
                color: #065f46;
                border: 1px solid #6ee7b7;
                padding: 0.2rem 0.65rem;
                border-radius: 999px;
                font-size: 0.8rem;
                font-weight: 700;
                letter-spacing: 0.01em;
                box-shadow: 0 1px 2px rgba(6, 95, 70, 0.12);
            }
            .dlv-screenshot-banner {
                display: none;
                margin-bottom: 0.85rem;
                padding: 0.85rem 1rem;
                border-radius: 10px;
                border: 1px solid #bfdbfe;
                background: linear-gradient(135deg, #eff6ff 0%, #f8fafc 100%);
            }
            .dlv-screenshot-banner.is-visible {
                display: block;
            }
            .dlv-screenshot-banner__title {
                font-size: 1.15rem;
                font-weight: 700;
                color: #0f172a;
                letter-spacing: -0.02em;
            }
            .dlv-screenshot-banner__meta {
                margin-top: 0.35rem;
                font-size: 0.82rem;
                color: #475569;
                display: flex;
                flex-wrap: wrap;
                gap: 0.35rem;
            }
            body.dlv-screenshot-busy {
                cursor: wait;
            }
            body.dlv-screenshot-busy #deliverables-screenshot-btn {
                opacity: 0.65;
                cursor: wait;
            }
            .deliverables-report-table th:nth-child(1),
            .deliverables-report-table td:nth-child(1) { width: 4rem; }
            .deliverables-report-table th:nth-child(2),
            .deliverables-report-table td:nth-child(2) { width: 24rem; word-break: break-word; }
            .deliverables-report-table th:nth-child(3),
            .deliverables-report-table td:nth-child(3) { width: 9rem; }
            .deliverables-report-table th:nth-child(4),
            .deliverables-report-table td:nth-child(4) { width: 8.5rem; }
            .deliverables-report-table th:nth-child(5),
            .deliverables-report-table td:nth-child(5) { width: 7.5rem; }
            .deliverables-report-table th:nth-child(6),
            .deliverables-report-table td:nth-child(6) { width: 8.5rem; }
            .deliverables-report-table th:nth-child(7),
            .deliverables-report-table td:nth-child(7) { width: 10rem; }
            .dlv-meta-select {
                width: 100%;
                max-width: 100%;
                padding: 0.28rem 0.35rem;
                border: 1px solid #cbd5e1;
                border-radius: 6px;
                font: inherit;
                font-size: 0.8rem;
                background: #fff;
                cursor: pointer;
            }
            .dlv-meta-select:focus {
                outline: 2px solid #6366f1;
                outline-offset: 1px;
                border-color: #6366f1;
            }
            .dlv-meta-select.is-saving {
                opacity: 0.65;
                cursor: wait;
            }
            .dlv-meta-select.is-saved {
                border-color: #059669;
                box-shadow: 0 0 0 1px rgba(5, 150, 105, 0.25);
            }
            .dlv-meta-select.is-error {
                border-color: #dc2626;
                box-shadow: 0 0 0 1px rgba(220, 38, 38, 0.25);
            }
            .dlv-pct-legend {
                margin: 0 0 0.65rem;
                font-size: 0.74rem;
                color: #475569;
                display: flex;
                flex-wrap: wrap;
                align-items: center;
                gap: 0.2rem 0.5rem;
                line-height: 1.2;
            }
            .dlv-pct-legend__title {
                color: #334155;
                font-weight: 700;
                margin-right: 0.1rem;
            }
            .dlv-pct-legend-item {
                display: inline-flex;
                align-items: center;
                gap: 0.3rem;
            }
            .dlv-pct-legend-chip {
                display: inline-flex;
                align-items: center;
                gap: 0.25rem;
            }
            .dlv-pct-legend-label {
                white-space: nowrap;
            }
            .dlv-pct-legend-sep {
                color: #cbd5e1;
                user-select: none;
                line-height: 1;
            }
            .dlv-pct-stack {
                display: flex;
                flex-direction: column;
                align-items: stretch;
                gap: 0.22rem;
                width: 100%;
                max-width: 4.75rem;
                margin: 0 auto;
            }
            .dlv-pct-bar {
                height: 5px;
                border-radius: 999px;
                background: #e2e8f0;
                overflow: hidden;
            }
            .dlv-pct-bar--legend {
                width: 1.75rem;
                height: 4px;
                flex-shrink: 0;
            }
            .dlv-pct-bar__fill {
                height: 100%;
                border-radius: 999px;
                min-width: 2px;
            }
            .dlv-pct-bar__fill--good {
                background: linear-gradient(90deg, #34d399, #059669);
            }
            .dlv-pct-bar__fill--warn {
                background: linear-gradient(90deg, #f59e0b, #d97706);
            }
            .dlv-pct-bar__fill--critical {
                background: linear-gradient(90deg, #f87171, #dc2626);
            }
            .dlv-pct-badge {
                display: inline-block;
                min-width: 2.5rem;
                padding: 0.14rem 0.42rem;
                border-radius: 999px;
                font-weight: 700;
                font-size: 0.78rem;
                line-height: 1.2;
                border: 1px solid transparent;
                text-align: center;
                white-space: nowrap;
            }
            .dlv-pct-badge--sm {
                min-width: 0;
                padding: 0.08rem 0.35rem;
                font-size: 0.68rem;
                line-height: 1.15;
            }
            .dlv-pct-badge--good {
                background: #d1fae5;
                color: #047857;
                border-color: #6ee7b7;
            }
            .dlv-pct-badge--warn {
                background: #fef3c7;
                color: #b45309;
                border-color: #fcd34d;
            }
            .dlv-pct-badge--critical {
                background: #fee2e2;
                color: #b91c1c;
                border-color: #fca5a5;
            }
            .dlv-screenshot-clone {
                position: fixed;
                left: -99999px;
                top: 0;
                z-index: 2147483647;
                background: #ffffff;
                padding: 16px;
                overflow: visible !important;
                width: auto !important;
                max-width: none !important;
                box-sizing: border-box;
            }
            /* html2canvas 1.4.1 throws on linear-gradient when lineLength is 0 (tiny/zero-width bars). */
            .dlv-screenshot-clone .dlv-screenshot-banner {
                background: #eff6ff !important;
            }
            .dlv-screenshot-clone .dlv-pct-bar__fill--good {
                background: #34d399 !important;
            }
            .dlv-screenshot-clone .dlv-pct-bar__fill--warn {
                background: #f59e0b !important;
            }
            .dlv-screenshot-clone .dlv-pct-bar__fill--critical {
                background: #f87171 !important;
            }
            .dlv-screenshot-clone #deliverables-table-wrap {
                overflow: visible !important;
                width: 100% !important;
                max-width: none !important;
            }
            .dlv-screenshot-clone .deliverables-report-table {
                width: 72rem !important;
                min-width: 72rem !important;
                max-width: none !important;
                table-layout: fixed !important;
            }
        </style>
    @endpush

    @push('scripts')
        <script src="https://cdnjs.cloudflare.com/ajax/libs/html2canvas/1.4.1/html2canvas.min.js" crossorigin="anonymous" referrerpolicy="no-referrer"></script>
        <script>
            (function () {
                const quarterEl = document.getElementById('quarter');
                const monthEl = document.getElementById('month');
                const dateFromEl = document.getElementById('date_from');
                const dateToEl = document.getElementById('date_to');
                if (!quarterEl || !monthEl || !dateFromEl || !dateToEl) return;

                const fyQuarterMonths = @json($fyQuarterPeriods);
                const fiscalStartYear = @json((int) ($fiscalYear?->starts_on?->year ?? now()->year));
                const fiscalStartMonth = @json((int) ($fiscalYear?->starts_on?->month ?? 4));
                let syncingPresetDates = false;

                function pad2(n) {
                    return String(n).padStart(2, '0');
                }

                function calendarYearForMonth(month) {
                    return month >= fiscalStartMonth ? fiscalStartYear : fiscalStartYear + 1;
                }

                function setDateRange(fromIso, toIso) {
                    syncingPresetDates = true;
                    dateFromEl.value = fromIso || '';
                    dateToEl.value = toIso || '';
                    syncingPresetDates = false;
                }

                function clearPeriodFields() {
                    setDateRange('', '');
                }

                function syncDatesFromMonth() {
                    const month = parseInt(monthEl.value, 10);
                    if (!month || month < 1 || month > 12) {
                        if (!quarterEl.value) {
                            clearPeriodFields();
                        }
                        return;
                    }

                    const year = calendarYearForMonth(month);
                    const lastDay = new Date(year, month, 0).getDate();
                    const fromIso = year + '-' + pad2(month) + '-01';
                    const toIso = year + '-' + pad2(month) + '-' + pad2(lastDay);
                    setDateRange(fromIso, toIso);
                }

                function syncDatesFromQuarter() {
                    const quarter = parseInt(quarterEl.value, 10);
                    if (!quarter || quarter < 1 || quarter > 4) {
                        if (!monthEl.value) {
                            clearPeriodFields();
                        }
                        return;
                    }

                    const range = fyQuarterMonths[quarter];
                    if (!range) {
                        clearPeriodFields();
                        return;
                    }

                    setDateRange(range.from, range.to);
                }

                function onManualDateChange() {
                    if (syncingPresetDates) {
                        return;
                    }
                    quarterEl.value = '';
                    monthEl.value = '';
                }

                quarterEl.addEventListener('change', function () {
                    if (quarterEl.value) {
                        monthEl.value = '';
                    }
                    syncDatesFromQuarter();
                });

                monthEl.addEventListener('change', function () {
                    if (monthEl.value) {
                        quarterEl.value = '';
                    }
                    syncDatesFromMonth();
                });

                dateFromEl.addEventListener('change', onManualDateChange);
                dateToEl.addEventListener('change', onManualDateChange);
            })();

            (function () {
                const btn = document.getElementById('deliverables-screenshot-btn');
                const root = document.getElementById('deliverables-screenshot-root');
                if (!btn || !root) return;

                function pad2(n) {
                    return String(n).padStart(2, '0');
                }

                function screenshotFilename() {
                    const fyLabel = @json(str_replace(' ', '-', (string) ($fiscalYear?->name ?? 'FY')));
                    const now = new Date();
                    const stamp = now.getFullYear()
                        + pad2(now.getMonth() + 1)
                        + pad2(now.getDate())
                        + '-'
                        + pad2(now.getHours())
                        + pad2(now.getMinutes());
                    return 'deliverables-' + fyLabel + '-' + stamp + '-4k.png';
                }

                function prepareScreenshotClone(clone) {
                    const cloneBanner = clone.querySelector('.dlv-screenshot-banner');
                    if (cloneBanner) {
                        cloneBanner.classList.add('is-visible');
                        cloneBanner.setAttribute('aria-hidden', 'false');
                    }

                    const shotBtn = clone.querySelector('#deliverables-screenshot-btn');
                    if (shotBtn) {
                        shotBtn.style.display = 'none';
                    }

                    const filterForm = clone.querySelector('#deliverables-filter-form');
                    if (filterForm) {
                        filterForm.querySelectorAll('button, a').forEach(function (el) {
                            el.style.visibility = 'hidden';
                        });
                    }

                    const tableWrap = clone.querySelector('#deliverables-table-wrap');
                    const table = clone.querySelector('#deliverables-table');
                    if (tableWrap) {
                        tableWrap.style.overflow = 'visible';
                        tableWrap.style.overflowX = 'visible';
                        tableWrap.style.width = '72rem';
                        tableWrap.style.maxWidth = 'none';
                    }
                    if (table) {
                        table.style.width = '72rem';
                        table.style.minWidth = '72rem';
                        table.style.maxWidth = 'none';
                        table.style.tableLayout = 'fixed';
                    }

                    clone.querySelectorAll('.dlv-ach-btn').forEach(function (button) {
                        const span = document.createElement('span');
                        span.textContent = button.textContent;
                        span.style.fontWeight = '600';
                        span.style.color = '#0f172a';
                        span.style.display = 'inline-block';
                        button.replaceWith(span);
                    });

                    clone.querySelectorAll('.dlv-meta-select').forEach(function (select) {
                        const span = document.createElement('span');
                        span.textContent = select.options[select.selectedIndex]?.text || '—';
                        span.style.display = 'inline-block';
                        select.replaceWith(span);
                    });

                    clone.querySelectorAll('.dlv-pct-bar__fill').forEach(function (fill) {
                        const width = parseFloat(fill.style.width) || 0;
                        if (width <= 0) {
                            fill.style.display = 'none';
                        }
                    });

                    clone.style.overflow = 'visible';
                    clone.style.background = '#ffffff';

                    const width = Math.ceil(clone.scrollWidth) + 8;
                    const height = Math.ceil(clone.scrollHeight) + 8;
                    clone.style.width = width + 'px';
                    clone.style.height = height + 'px';

                    return { width: width, height: height };
                }

                btn.addEventListener('click', async function () {
                    if (typeof html2canvas === 'undefined') {
                        alert('Screenshot library failed to load. Refresh the page and try again.');
                        return;
                    }

                    const previousScrollY = window.scrollY;
                    let captureClone = null;
                    try {
                        btn.disabled = true;
                        document.body.classList.add('dlv-screenshot-busy');

                        captureClone = root.cloneNode(true);
                        captureClone.removeAttribute('id');
                        captureClone.classList.add('dlv-screenshot-clone');
                        document.body.appendChild(captureClone);

                        const dims = prepareScreenshotClone(captureClone);

                        await new Promise(function (resolve) {
                            requestAnimationFrame(function () {
                                requestAnimationFrame(resolve);
                            });
                        });

                        const scale = 3;
                        const canvas = await html2canvas(captureClone, {
                            scale: scale,
                            useCORS: true,
                            allowTaint: true,
                            backgroundColor: '#ffffff',
                            logging: false,
                            width: dims.width,
                            height: dims.height,
                            windowWidth: dims.width,
                            windowHeight: dims.height,
                            scrollX: 0,
                            scrollY: 0,
                        });

                        const link = document.createElement('a');
                        link.download = screenshotFilename();
                        link.href = canvas.toDataURL('image/png', 1.0);
                        link.click();
                    } catch (error) {
                        console.error(error);
                        alert('Could not capture screenshot. Try again or use a shorter filter period.');
                    } finally {
                        if (captureClone && captureClone.parentNode) {
                            captureClone.parentNode.removeChild(captureClone);
                        }
                        document.body.classList.remove('dlv-screenshot-busy');
                        btn.disabled = false;
                        window.scrollTo(0, previousScrollY);
                    }
                });
            })();

            @if ($canEditRowMetadata && $rowMetadataUpdateRoute)
            (function () {
                const updateUrl = @json(route($rowMetadataUpdateRoute));
                const csrf = @json(csrf_token());

                document.querySelectorAll('.dlv-meta-select').forEach(function (select) {
                    let lastSaved = select.value;

                    select.addEventListener('change', async function () {
                        const serial = select.dataset.serial;
                        const field = select.dataset.field;
                        const value = select.value;

                        select.classList.remove('is-saved', 'is-error');
                        select.classList.add('is-saving');
                        select.disabled = true;

                        try {
                            const res = await fetch(updateUrl, {
                                method: 'PATCH',
                                headers: {
                                    'Content-Type': 'application/json',
                                    'Accept': 'application/json',
                                    'X-CSRF-TOKEN': csrf,
                                },
                                body: JSON.stringify({ serial: serial, field: field, value: value }),
                            });

                            if (!res.ok) {
                                const payload = await res.json().catch(function () { return {}; });
                                throw new Error(payload.message || 'Save failed');
                            }

                            const data = await res.json();
                            lastSaved = value;

                            document.querySelectorAll('.dlv-meta-select[data-serial="' + serial + '"]').forEach(function (el) {
                                if (el.dataset.field === 'indicator_type') {
                                    el.value = data.indicator_type || '';
                                }
                                if (el.dataset.field === 'level') {
                                    el.value = data.level || '';
                                }
                            });

                            select.classList.add('is-saved');
                            setTimeout(function () {
                                select.classList.remove('is-saved');
                            }, 1200);
                        } catch (error) {
                            select.value = lastSaved;
                            select.classList.add('is-error');
                            alert(error.message || 'Could not save. Try again.');
                        } finally {
                            select.disabled = false;
                            select.classList.remove('is-saving');
                        }
                    });
                });
            })();
            @endif
        </script>
    @endpush
@endsection
