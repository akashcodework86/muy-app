@extends('layouts.admin')

@section('title', ($row['name'] ?? 'Deliverable records'))
@section('heading', 'S.N. '.$serial.' — '.($row['name'] ?? 'Achievement records'))

@section('content')
    @php
        $formDates = $filter->formDates($fiscalYear ?? null);
        $explicitPeriod = $filter->hasExplicitDateFilter();
        $dateFromValue = $explicitPeriod ? ($formDates['dateFrom'] ?? '') : '';
        $dateToValue = $explicitPeriod ? ($formDates['dateTo'] ?? '') : '';
        $isMarketIncubatees = ($breakdown['source_type'] ?? '') === 'market_linkage_incubatees';
        $baseQuery = array_merge($filter->queryParams(), ['serial' => $serial]);
        $offlineQuery = array_merge($baseQuery, ['linkage_mode' => 'offline', 'q' => ($listFilters['q'] ?? '') ?: null]);
        $onlineQuery = array_merge($baseQuery, ['linkage_mode' => 'online', 'q' => ($listFilters['q'] ?? '') ?: null]);
        $allQuery = array_merge($baseQuery, ['q' => ($listFilters['q'] ?? '') ?: null]);
        $activeMode = (string) ($listFilters['linkage_mode'] ?? '');
        $offlineCount = (int) ($breakdown['offline_incubatees'] ?? 0);
        $onlineCount = (int) ($breakdown['online_incubatees'] ?? 0);
        $rowOffset = max(0, (int) $records->firstItem() - 1);
    @endphp

    <style>
        .dlv-rec-nav { display:flex; flex-wrap:wrap; gap:0.55rem; align-items:center; margin-bottom:0.9rem; font-size:0.86rem; }
        .dlv-rec-nav a { color:#a63d02; font-weight:700; text-decoration:none; }
        .dlv-rec-nav a:hover { text-decoration:underline; }
        .dlv-rec-meta { color:#64748b; }
        .dlv-rec-modes { display:flex; flex-wrap:wrap; gap:0.45rem; margin-bottom:0.85rem; }
        .dlv-rec-mode {
            display:inline-flex; align-items:center; gap:0.4rem;
            padding:0.42rem 0.75rem; border-radius:999px; text-decoration:none;
            font-size:0.82rem; font-weight:700; border:1px solid #e2e8f0; background:#fff; color:#334155;
        }
        .dlv-rec-mode.is-active { background:#0f172a; color:#fff; border-color:#0f172a; }
        .dlv-rec-mode--offline.is-active { background:#c2410c; border-color:#c2410c; }
        .dlv-rec-mode--online.is-active { background:#1d4ed8; border-color:#1d4ed8; }
        .dlv-rec-mode__count { font-variant-numeric:tabular-nums; opacity:0.85; }
        .dlv-rec-card { background:#fff; border:1px solid #e2e8f0; border-radius:14px; padding:0.85rem 0.95rem; margin-bottom:0.85rem; }
        .dlv-rec-filters { display:grid; grid-template-columns:repeat(auto-fit, minmax(150px, 1fr)); gap:0.55rem; align-items:end; }
        .dlv-rec-filters label { display:block; font-size:0.75rem; font-weight:700; color:#475569; margin-bottom:0.2rem; }
        .dlv-rec-filters input, .dlv-rec-filters select {
            width:100%; padding:0.45rem 0.55rem; border:1px solid #d4d4d8; border-radius:8px; font-size:0.88rem;
        }
        .dlv-rec-actions { display:flex; flex-wrap:wrap; gap:0.45rem; }
        .dlv-rec-btn { display:inline-flex; align-items:center; padding:0.48rem 0.85rem; border-radius:8px; font-weight:700; font-size:0.84rem; text-decoration:none; border:none; cursor:pointer; font-family:inherit; }
        .dlv-rec-btn--apply { background:#18181b; color:#fff; }
        .dlv-rec-btn--ghost { background:#fff; color:#334155; border:1px solid #d4d4d8; }
        .dlv-rec-btn--xlsx { background:#065f46; color:#fff; }
        .dlv-rec-btn--csv { background:#f1f5f9; color:#334155; }
        .dlv-rec-btn--pdf { background:#fff7ed; color:#c2410c; border:1px solid #fed7aa; }
        .dlv-rec-table-wrap { background:#fff; border:1px solid #e2e8f0; border-radius:14px; overflow:auto; }
        .dlv-rec-table { width:100%; border-collapse:collapse; font-size:0.84rem; min-width: 1180px; }
        .dlv-rec-table th, .dlv-rec-table td { padding:0.6rem 0.7rem; border-bottom:1px solid #e2e8f0; text-align:left; vertical-align:top; }
        .dlv-rec-table th { font-size:0.72rem; text-transform:uppercase; letter-spacing:0.03em; color:#64748b; background:#f8fafc; position:sticky; top:0; }
        .dlv-rec-chip { display:inline-block; padding:0.12rem 0.4rem; border-radius:6px; font-size:0.7rem; font-weight:700; margin-right:0.2rem; }
        .dlv-rec-chip--offline { background:#fff7ed; color:#9a3412; }
        .dlv-rec-chip--online { background:#eff6ff; color:#1e40af; }
        .dlv-rec-sub { font-size:0.76rem; color:#64748b; margin-top:0.15rem; }
        .dlv-rec-partners { display:flex; flex-direction:column; gap:0.4rem; min-width:22rem; }
        .dlv-rec-partner {
            display:grid; grid-template-columns: minmax(7rem,1.2fr) auto auto auto;
            gap:0.3rem 0.55rem; align-items:center;
            padding:0.4rem 0.5rem; background:#f8fafc; border:1px solid #e2e8f0; border-radius:8px; font-size:0.78rem;
        }
        .dlv-rec-partner__name { font-weight:700; color:#0f172a; }
        .dlv-rec-link { color:#4338ca; font-weight:600; text-decoration:none; word-break:break-all; }
        .dlv-rec-link:hover { text-decoration:underline; }
        .dlv-rec-muted { color:#94a3b8; }
        .dlv-rec-foot { padding:0.7rem 0.85rem; display:flex; justify-content:space-between; gap:0.75rem; flex-wrap:wrap; align-items:center; color:#64748b; font-size:0.82rem; background:#f8fafc; border-top:1px solid #e2e8f0; }
    </style>

    <div class="dlv-rec-nav">
        <a href="{{ route($indexRoute, $filter->queryParams()) }}">← Deliverables</a>
        <span class="dlv-rec-meta">{{ $scopeLabel }} · {{ $periodLabel }} · {{ $modeLabel }}</span>
    </div>

    @if ($isMarketIncubatees)
        <div class="dlv-rec-modes" role="group" aria-label="Linkage mode">
            <a href="{{ route($recordsRoute, array_filter($allQuery)) }}" class="dlv-rec-mode @if ($activeMode === '') is-active @endif">
                All
                <span class="dlv-rec-mode__count">{{ number_format((int) ($breakdown['total'] ?? 0)) }}</span>
            </a>
            <a href="{{ route($recordsRoute, array_filter($offlineQuery)) }}" class="dlv-rec-mode dlv-rec-mode--offline @if ($activeMode === 'offline') is-active @endif">
                Offline
                <span class="dlv-rec-mode__count">{{ number_format($offlineCount) }}</span>
            </a>
            <a href="{{ route($recordsRoute, array_filter($onlineQuery)) }}" class="dlv-rec-mode dlv-rec-mode--online @if ($activeMode === 'online') is-active @endif">
                Online
                <span class="dlv-rec-mode__count">{{ number_format($onlineCount) }}</span>
            </a>
        </div>
    @endif

    <form method="get" action="{{ route($recordsRoute) }}" class="dlv-rec-card">
        <input type="hidden" name="serial" value="{{ $serial }}">
        <input type="hidden" name="fiscal_year_id" value="{{ $fiscalYearId }}">
        @if ($activeMode !== '')
            <input type="hidden" name="linkage_mode" value="{{ $activeMode }}">
        @endif
        <div class="dlv-rec-filters">
            <div>
                <label for="q">Search</label>
                <input type="search" name="q" id="q" value="{{ $listFilters['q'] ?? '' }}" placeholder="Name, application no, partner">
            </div>
            @if ($canPickDistrict)
                <div>
                    <label for="district_id">District</label>
                    <select name="district_id" id="district_id">
                        <option value="">All in scope</option>
                        @foreach ($districts as $d)
                            <option value="{{ $d->id }}" @selected((int) ($filter->districtId ?? 0) === (int) $d->id)>{{ $d->name }}</option>
                        @endforeach
                    </select>
                </div>
            @endif
            <div>
                <label for="month">Month</label>
                <select name="month" id="month">
                    <option value="">All months</option>
                    @foreach (range(1, 12) as $m)
                        <option value="{{ $m }}" @selected((int) ($filter->month ?? 0) === $m)>{{ \Carbon\Carbon::create(null, $m, 1)->format('F') }}</option>
                    @endforeach
                </select>
            </div>
            <div>
                <label for="date_from">From date</label>
                <input type="date" name="date_from" id="date_from" value="{{ $dateFromValue }}">
            </div>
            <div>
                <label for="date_to">To date</label>
                <input type="date" name="date_to" id="date_to" value="{{ $dateToValue }}">
            </div>
            <div class="dlv-rec-actions">
                <button type="submit" class="dlv-rec-btn dlv-rec-btn--apply">Apply</button>
                <a href="{{ route($recordsRoute, ['serial' => $serial, 'fiscal_year_id' => $fiscalYearId]) }}" class="dlv-rec-btn dlv-rec-btn--ghost">Reset</a>
                <a href="{{ route($breakdownExportRoute, $listQueryParams) }}" class="dlv-rec-btn dlv-rec-btn--xlsx">⬇ Excel</a>
                <a href="{{ route($breakdownExportCsvRoute, $listQueryParams) }}" class="dlv-rec-btn dlv-rec-btn--csv">⬇ CSV</a>
                <a href="{{ route($breakdownExportPdfRoute, $listQueryParams) }}" class="dlv-rec-btn dlv-rec-btn--pdf">⬇ PDF</a>
            </div>
        </div>
    </form>

    <div class="dlv-rec-table-wrap">
        <table class="dlv-rec-table">
            <thead>
                @if ($isMarketIncubatees)
                    <tr>
                        <th>#</th>
                        <th>Incubatee</th>
                        <th>District / Hub / Block</th>
                        <th>Phone</th>
                        <th>Submitted</th>
                        <th>Partner details</th>
                    </tr>
                @else
                    <tr>
                        <th>#</th>
                        <th>Reference</th>
                        <th>Applicant</th>
                        <th>District</th>
                        <th>Service</th>
                        <th>Date</th>
                    </tr>
                @endif
            </thead>
            <tbody>
                @forelse ($records as $index => $item)
                    @php
                        $modeRaw = (string) ($item['linkage_mode'] ?? '');
                        $partnerRows = is_array($item['partner_rows'] ?? null) ? $item['partner_rows'] : [];
                        $sourceLabel = (($item['source'] ?? '') === 'service_case') ? 'Service case' : 'Market linkage';
                        $statusLabel = trim((string) ($item['status'] ?? ''));
                        $statusLabel = $statusLabel !== '' ? ucfirst(str_replace('_', ' ', $statusLabel)) : 'Approved';
                    @endphp
                    <tr>
                        <td style="color:#94a3b8;font-weight:700;">{{ $rowOffset + $index + 1 }}</td>
                        @if ($isMarketIncubatees)
                            <td style="min-width:11rem;">
                                <div style="font-weight:700;">{{ $item['applicant'] ?? '—' }}</div>
                                <div class="dlv-rec-sub">{{ $item['reference'] ?? '—' }}</div>
                                <div class="dlv-rec-sub">{{ $sourceLabel }} · {{ $statusLabel }}</div>
                            </td>
                            <td>
                                <div>{{ $item['district'] ?? '—' }}</div>
                                @if (! empty($item['hub']))
                                    <div class="dlv-rec-sub">{{ $item['hub'] }}</div>
                                @endif
                                <div class="dlv-rec-sub">Block: {{ ! empty($item['block']) ? $item['block'] : '—' }}</div>
                            </td>
                            <td>{{ ! empty($item['phone']) ? $item['phone'] : '—' }}</td>
                            <td style="white-space:nowrap;">
                                <div>{{ $item['date'] ?? '—' }}</div>
                                @if (! empty($item['submitted_by']))
                                    <div class="dlv-rec-sub">{{ $item['submitted_by'] }}</div>
                                @endif
                            </td>
                            <td>
                                @if ($partnerRows !== [])
                                    <div class="dlv-rec-partners">
                                        @foreach ($partnerRows as $partner)
                                            @php
                                                $pMode = (string) ($partner['linkage_mode_label'] ?? $partner['linkage_mode'] ?? '—');
                                                $pHref = \App\Models\MarketLinkagePartner::clickableHref($partner['link_url'] ?? null);
                                            @endphp
                                            <div class="dlv-rec-partner">
                                                <span class="dlv-rec-partner__name">{{ $partner['partner_name'] ?? '—' }}</span>
                                                <span class="dlv-rec-chip {{ strtolower($pMode) === 'offline' ? 'dlv-rec-chip--offline' : 'dlv-rec-chip--online' }}">{{ $pMode }}</span>
                                                <span>{{ $partner['linkage_date_display'] ?? '—' }}</span>
                                                <span>
                                                    @if (! empty($partner['has_document']))
                                                        Bill{{ ! empty($partner['document_name']) ? ': '.$partner['document_name'] : '' }}
                                                    @else
                                                        <span class="dlv-rec-muted">No bill</span>
                                                    @endif
                                                </span>
                                                <span style="grid-column:1 / -1;">
                                                    @if ($pHref)
                                                        <a class="dlv-rec-link" href="{{ $pHref }}" target="_blank" rel="noopener noreferrer">{{ $partner['link_url'] }}</a>
                                                    @elseif (! empty($partner['link_url']))
                                                        {{ $partner['link_url'] }}
                                                    @else
                                                        <span class="dlv-rec-muted">No link</span>
                                                    @endif
                                                    @if (! empty($partner['recorded_by']) || ! empty($partner['recorded_at']))
                                                        <span class="dlv-rec-sub" style="display:inline;margin-left:0.4rem;">
                                                            · {{ $partner['recorded_at'] ?? '' }}
                                                            {{ ! empty($partner['recorded_by']) ? ' · '.$partner['recorded_by'] : '' }}
                                                        </span>
                                                    @endif
                                                </span>
                                            </div>
                                        @endforeach
                                    </div>
                                @else
                                    <div>{{ $item['service'] ?? '—' }}</div>
                                    @foreach (preg_split('/,\s*/', $modeRaw) as $modePart)
                                        @continue($modePart === '')
                                        <span class="dlv-rec-chip {{ strtolower($modePart) === 'offline' ? 'dlv-rec-chip--offline' : 'dlv-rec-chip--online' }}">{{ $modePart }}</span>
                                    @endforeach
                                @endif
                            </td>
                        @else
                            <td>{{ $item['reference'] ?? '—' }}</td>
                            <td>{{ $item['applicant'] ?? '—' }}</td>
                            <td>{{ $item['district'] ?? '—' }}</td>
                            <td>{{ $item['service'] ?? '—' }}</td>
                            <td>{{ $item['date'] ?? '—' }}</td>
                        @endif
                    </tr>
                @empty
                    <tr>
                        <td colspan="{{ $isMarketIncubatees ? 6 : 6 }}" style="padding:1.1rem;color:#64748b;font-style:italic;">
                            No records match the current filters.
                        </td>
                    </tr>
                @endforelse
            </tbody>
        </table>
        <div class="dlv-rec-foot">
            <div>
                Showing <strong>{{ number_format($recordsTotal) }}</strong>
                {{ strtolower($modeLabel) }}
                @if (($listFilters['q'] ?? '') !== '')
                    matching “{{ $listFilters['q'] }}”
                @endif
            </div>
            <div>{{ $records->links() }}</div>
        </div>
    </div>
@endsection
