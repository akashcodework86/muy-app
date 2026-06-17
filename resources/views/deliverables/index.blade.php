@extends('layouts.admin')

@section('title', 'Deliverables')
@section('heading', 'Deliverables')

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
        <div style="display:flex;flex-direction:column;gap:0.25rem;">
            <label for="fiscal_year_id" style="font-size:0.75rem;font-weight:600;color:#475569;">Fiscal year</label>
            <select name="fiscal_year_id" id="fiscal_year_id" style="padding:0.45rem 0.55rem;border:1px solid #d4d4d8;border-radius:8px;min-width:10rem;">
                @foreach ($fiscalYears as $fy)
                    <option value="{{ $fy->id }}" data-start-year="{{ $fy->starts_on?->year }}" @selected((int) $fiscalYearId === (int) $fy->id)>{{ $fy->name }}</option>
                @endforeach
            </select>
        </div>
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
            <label for="year" style="font-size:0.75rem;font-weight:600;color:#475569;">Year</label>
            <input type="number" name="year" id="year" value="{{ $formDates['year'] ?? $filter->year }}" min="2020" max="2040" placeholder="e.g. 2026" style="padding:0.45rem 0.55rem;border:1px solid #d4d4d8;border-radius:8px;width:6.5rem;">
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
                        <td style="padding:0.45rem;border:1px solid #d4d4d8;text-align:center;">{{ $isHeading ? '' : ($row['indicator_type'] ?: '—') }}</td>
                        <td style="padding:0.45rem;border:1px solid #d4d4d8;text-align:center;">{{ $isHeading ? '' : ($row['level'] ?: '—') }}</td>
                        <td style="padding:0.45rem;border:1px solid #d4d4d8;text-align:center;">{{ ! $isHeading && $row['target'] !== null ? number_format($row['target']) : '' }}</td>
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
                        <td style="padding:0.45rem;border:1px solid #d4d4d8;text-align:center;">{{ ! $isHeading && $row['achievement_pct'] !== null ? $row['achievement_pct'].'%' : '' }}</td>
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
            .deliverables-report-table td:nth-child(7) { width: 9rem; }
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
                const yearEl = document.getElementById('year');
                const fyEl = document.getElementById('fiscal_year_id');
                const dateFromEl = document.getElementById('date_from');
                const dateToEl = document.getElementById('date_to');
                if (!quarterEl || !monthEl || !yearEl || !fyEl || !dateFromEl || !dateToEl) return;

                const fyQuarterMonths = @json($fyQuarterPeriods);
                let syncingPresetDates = false;

                function pad2(n) {
                    return String(n).padStart(2, '0');
                }

                function defaultYear() {
                    const parsed = parseInt(yearEl.value, 10);
                    if (!Number.isNaN(parsed) && parsed > 0) {
                        return parsed;
                    }
                    const fyOpt = fyEl.options[fyEl.selectedIndex];
                    const startYear = fyOpt ? parseInt(fyOpt.dataset.startYear || '', 10) : NaN;
                    return !Number.isNaN(startYear) && startYear > 0 ? startYear : new Date().getFullYear();
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

                    const year = defaultYear();
                    yearEl.value = year;
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

                yearEl.addEventListener('change', function () {
                    if (monthEl.value) {
                        syncDatesFromMonth();
                    }
                });

                dateFromEl.addEventListener('change', onManualDateChange);
                dateToEl.addEventListener('change', onManualDateChange);

                fyEl.addEventListener('change', function () {
                    quarterEl.value = '';
                    monthEl.value = '';
                    clearPeriodFields();
                });
            })();

            (function () {
                const btn = document.getElementById('deliverables-screenshot-btn');
                const root = document.getElementById('deliverables-screenshot-root');
                if (!btn || !root) return;

                function pad2(n) {
                    return String(n).padStart(2, '0');
                }

                function screenshotFilename() {
                    const fy = document.getElementById('fiscal_year_id');
                    const fyLabel = fy && fy.options[fy.selectedIndex]
                        ? fy.options[fy.selectedIndex].textContent.trim().replace(/\s+/g, '-')
                        : 'FY';
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
        </script>
    @endpush
@endsection
