@extends('layouts.admin')

@section('title', 'Market linkage')
@section('heading', 'Market linkage')

@section('content')
    @php
        $stats = $stats ?? [
            'unique_partners' => 0,
            'linked_incubatees' => 0,
            'partner_records' => 0,
            'online_partners' => 0,
            'offline_partners' => 0,
        ];
        $listTotal = (! empty($isPaginated) && is_object($rows) && method_exists($rows, 'total'))
            ? (int) $rows->total()
            : (int) (is_countable($rows) ? count($rows) : 0);
        $rowOffset = (! empty($isPaginated) && is_object($rows) && method_exists($rows, 'firstItem'))
            ? max(0, (int) $rows->firstItem() - 1)
            : 0;
        $districtTotal = collect($districtCounts ?? [])->sum('total');
    @endphp

    @push('styles')
    <style>
        .ml-shell { display: flex; flex-direction: column; gap: 1rem; }
        .ml-alert {
            border-radius: 12px;
            padding: 0.85rem 1rem;
            font-size: 0.88rem;
        }
        .ml-alert--warn { background: #fffbeb; border: 1px solid #fde68a; color: #92400e; }
        .ml-alert--ok { background: #f0fdf4; border: 1px solid #86efac; color: #166534; }
        .ml-toolbar {
            display: flex;
            flex-wrap: wrap;
            gap: 0.5rem;
            align-items: center;
            justify-content: space-between;
        }
        .ml-toolbar__actions { display: flex; flex-wrap: wrap; gap: 0.5rem; align-items: center; }
        .ml-btn {
            text-decoration: none;
            display: inline-flex;
            align-items: center;
            gap: 0.35rem;
            padding: 0.48rem 0.95rem;
            border-radius: 8px;
            font-size: 0.85rem;
            font-weight: 700;
            border: none;
            cursor: pointer;
            font-family: inherit;
        }
        .ml-btn--primary { background: #4f46e5; color: #fff; }
        .ml-btn--export { background: #065f46; color: #fff; }
        .ml-btn--ghost { background: #fff; color: #334155; border: 1px solid #cbd5e1; }
        .ml-stats {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(150px, 1fr));
            gap: 0.65rem;
        }
        .ml-stat {
            background: linear-gradient(180deg, #fff 0%, #f8fafc 100%);
            border: 1px solid #e2e8f0;
            border-radius: 14px;
            padding: 0.9rem 1rem;
            position: relative;
            overflow: hidden;
        }
        .ml-stat::before {
            content: '';
            position: absolute;
            top: 0;
            left: 0;
            right: 0;
            height: 3px;
            background: var(--ml-accent, #4f46e5);
        }
        .ml-stat--partners { --ml-accent: #4f46e5; }
        .ml-stat--incubatees { --ml-accent: #059669; }
        .ml-stat--records { --ml-accent: #0ea5e9; }
        .ml-stat--online { --ml-accent: #6366f1; }
        .ml-stat--offline { --ml-accent: #ea580c; }
        .ml-stat__label {
            font-size: 0.72rem;
            font-weight: 700;
            text-transform: uppercase;
            letter-spacing: 0.05em;
            color: #64748b;
        }
        .ml-stat__value {
            margin-top: 0.35rem;
            font-size: 1.55rem;
            font-weight: 800;
            color: #0f172a;
            font-variant-numeric: tabular-nums;
            line-height: 1.1;
        }
        .ml-stat__hint {
            margin-top: 0.25rem;
            font-size: 0.74rem;
            color: #94a3b8;
        }
        .ml-card {
            background: #fff;
            border: 1px solid #e2e8f0;
            border-radius: 14px;
            padding: 0.9rem 1rem;
        }
        .ml-card__title {
            margin: 0 0 0.55rem;
            font-size: 0.88rem;
            font-weight: 800;
            color: #0f172a;
        }
        .ml-card__meta {
            margin: 0 0 0.65rem;
            font-size: 0.8rem;
            color: #64748b;
        }
        .ml-district-grid {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(150px, 1fr));
            gap: 0.45rem;
        }
        .ml-district-pill {
            display: flex;
            justify-content: space-between;
            align-items: center;
            padding: 0.42rem 0.55rem;
            border-radius: 8px;
            text-decoration: none;
            font-size: 0.82rem;
            font-weight: 600;
            transition: transform 0.12s ease, box-shadow 0.12s ease;
        }
        .ml-district-pill:hover { transform: translateY(-1px); box-shadow: 0 2px 8px rgba(15, 23, 42, 0.08); }
        .ml-district-pill.is-active {
            border: 1px solid #93c5fd;
            background: #dbeafe;
            color: #1d4ed8;
        }
        .ml-district-pill.has-data {
            border: 1px solid #bbf7d0;
            background: #ecfdf5;
            color: #065f46;
        }
        .ml-district-pill.is-empty {
            border: 1px solid #e5e7eb;
            background: #f9fafb;
            color: #6b7280;
        }
        .ml-district-pill__count {
            font-weight: 800;
            font-variant-numeric: tabular-nums;
        }
        .ml-filters {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(160px, 1fr));
            gap: 0.55rem;
            align-items: end;
        }
        .ml-filters label {
            display: block;
            font-size: 0.78rem;
            font-weight: 700;
            margin-bottom: 0.2rem;
            color: #334155;
        }
        .ml-filters input,
        .ml-filters select {
            width: 100%;
            padding: 0.45rem 0.55rem;
            border: 1px solid #cbd5e1;
            border-radius: 8px;
            font-size: 0.88rem;
        }
        .ml-table-wrap {
            background: #fff;
            border: 1px solid #e2e8f0;
            border-radius: 14px;
            overflow: auto;
        }
        .ml-table {
            width: 100%;
            border-collapse: collapse;
            font-size: 0.84rem;
        }
        .ml-table thead tr { background: #f8fafc; }
        .ml-table th,
        .ml-table td {
            padding: 0.65rem 0.75rem;
            border-bottom: 1px solid #e2e8f0;
            vertical-align: top;
        }
        .ml-table tbody tr:last-child td { border-bottom: none; }
        .ml-table tbody tr:hover { background: #fafafa; }
        .ml-table__num {
            width: 2.75rem;
            text-align: center;
            color: #64748b;
            font-weight: 800;
            font-variant-numeric: tabular-nums;
        }
        .ml-table-foot {
            padding: 0.65rem 0.85rem;
            font-size: 0.8rem;
            color: #64748b;
            background: #f8fafc;
            border-top: 1px solid #e2e8f0;
        }
        .ml-dash-partners {
            display: flex;
            flex-direction: column;
            gap: 0.45rem;
        }
        .ml-dash-partner {
            display: grid;
            grid-template-columns: minmax(0, 1.2fr) minmax(0, 1fr) auto auto auto;
            gap: 0.35rem 0.65rem;
            align-items: center;
            padding: 0.45rem 0.55rem;
            background: #f8fafc;
            border: 1px solid #e2e8f0;
            border-radius: 8px;
            font-size: 0.8rem;
        }
        @media (max-width: 900px) {
            .ml-dash-partner {
                grid-template-columns: 1fr;
                align-items: start;
            }
        }
        .ml-dash-partner__name {
            font-weight: 700;
            color: #0f172a;
        }
        .ml-dash-partner__meta {
            color: #64748b;
            font-size: 0.76rem;
        }
        .ml-dash-badge {
            display: inline-flex;
            align-items: center;
            justify-content: center;
            min-width: 2rem;
            padding: 0.2rem 0.55rem;
            border-radius: 999px;
            font-size: 0.78rem;
            font-weight: 800;
            background: #ecfdf5;
            color: #166534;
            border: 1px solid #bbf7d0;
            font-variant-numeric: tabular-nums;
        }
        .ml-dash-mode {
            font-size: 0.76rem;
            font-weight: 700;
            padding: 0.15rem 0.45rem;
            border-radius: 6px;
            background: #eef2ff;
            color: #3730a3;
        }
        .ml-dash-mode--offline {
            background: #fff7ed;
            color: #9a3412;
        }
        .ml-dash-link {
            display: inline-flex;
            align-items: center;
            gap: 0.28rem;
            font-size: 0.76rem;
            font-weight: 700;
            color: #4f46e5;
            text-decoration: none;
        }
        .ml-dash-link:hover { text-decoration: underline; }
        .ml-dash-link svg {
            width: 0.82rem;
            height: 0.82rem;
            flex-shrink: 0;
        }
        .ml-dash-link--muted {
            color: #64748b;
            font-weight: 600;
        }
        .ml-dash-delete-form {
            display: inline;
            margin-left: 0.35rem;
        }
        .ml-dash-delete {
            appearance: none;
            border: 0;
            background: transparent;
            padding: 0;
            color: #dc2626;
            font: inherit;
            font-weight: 700;
            cursor: pointer;
            text-decoration: underline;
        }
        .ml-dash-delete:hover { color: #991b1b; }
    </style>
    @endpush

    <div class="ml-shell">
        @if (!empty($migrationMissing))
            <div class="ml-alert ml-alert--warn">
                Run <code>php artisan migrate</code> to enable market linkage tables.
            </div>
        @endif

        @if (session('status'))
            <div class="ml-alert ml-alert--ok">{{ session('status') }}</div>
        @endif

        <div class="ml-stats">
            <div class="ml-stat ml-stat--partners">
                <div class="ml-stat__label">Unique partners</div>
                <div class="ml-stat__value">{{ number_format((int) ($stats['unique_partners'] ?? 0)) }}</div>
                <div class="ml-stat__hint">Deliverable: partners outreach</div>
            </div>
            <div class="ml-stat ml-stat--incubatees">
                <div class="ml-stat__label">Linked incubatees</div>
                <div class="ml-stat__value">{{ number_format((int) ($stats['linked_incubatees'] ?? 0)) }}</div>
                <div class="ml-stat__hint">With market linkage service</div>
            </div>
            <div class="ml-stat ml-stat--records">
                <div class="ml-stat__label">Partner entries</div>
                <div class="ml-stat__value">{{ number_format((int) ($stats['partner_records'] ?? 0)) }}</div>
                <div class="ml-stat__hint">All online + offline rows</div>
            </div>
            <div class="ml-stat ml-stat--online">
                <div class="ml-stat__label">Online linkages</div>
                <div class="ml-stat__value">{{ number_format((int) ($stats['online_partners'] ?? 0)) }}</div>
            </div>
            <div class="ml-stat ml-stat--offline">
                <div class="ml-stat__label">Offline linkages</div>
                <div class="ml-stat__value">{{ number_format((int) ($stats['offline_partners'] ?? 0)) }}</div>
            </div>
        </div>

        <div class="ml-toolbar">
            <p style="margin:0;font-size:0.82rem;color:#64748b;max-width:40rem;">
                Totals reflect current filters. Includes Market Linkage module records and approved orphan service-case linkages (same set as phase3-services market-link filter). One table row per incubatee.
            </p>
            <div class="ml-toolbar__actions">
                @if ($createRoute)
                    <a href="{{ route($createRoute) }}" class="ml-btn ml-btn--primary">+ Add market linkage</a>
                @endif
                <a href="{{ route($exportRoute, request()->query()) }}" class="ml-btn ml-btn--export">⬇ Export Excel</a>
            </div>
        </div>

        @if (!empty($showDistrictScope ?? $isAdminView) && count($districtCounts ?? []) > 0)
            <div class="ml-card">
                <h3 class="ml-card__title">
                    District-wise linked incubatees
                    <span style="font-weight:800;color:#059669;margin-left:0.35rem;">{{ number_format($districtTotal) }} total</span>
                </h3>
                <p class="ml-card__meta">Click a district to filter the list below.</p>
                <div class="ml-district-grid">
                    @foreach ($districtCounts as $dc)
                        @php
                            $isSelected = (int) ($filters['district_id'] ?? 0) === (int) $dc['id'];
                            $pillClass = $isSelected ? 'is-active' : ($dc['total'] > 0 ? 'has-data' : 'is-empty');
                        @endphp
                        <a href="{{ route($dashboardRoute, array_merge(request()->query(), ['district_id' => $dc['id']])) }}"
                           class="ml-district-pill {{ $pillClass }}">
                            <span>{{ $dc['name'] }}</span>
                            <span class="ml-district-pill__count">{{ number_format($dc['total']) }}</span>
                        </a>
                    @endforeach
                </div>
            </div>
        @endif

        <form method="get" action="{{ route($dashboardRoute) }}" class="ml-card">
            <div class="ml-filters">
                <div>
                    <label>Search</label>
                    <input type="search" name="q" value="{{ $filters['q'] ?? '' }}" placeholder="Incubatee / partner / staff">
                </div>
                @if (!empty($showDistrictScope ?? $isAdminView))
                    <div>
                        <label>District</label>
                        <select name="district_id">
                            <option value="0">All districts</option>
                            @foreach ($districts as $district)
                                <option value="{{ $district->id }}" @selected((int) ($filters['district_id'] ?? 0) === (int) $district->id)>{{ $district->name }}</option>
                            @endforeach
                        </select>
                    </div>
                @endif
                <div>
                    <label>Linkage type</label>
                    <select name="linkage_mode">
                        <option value="">All</option>
                        <option value="online" @selected(($filters['linkage_mode'] ?? '') === 'online')>Online</option>
                        <option value="offline" @selected(($filters['linkage_mode'] ?? '') === 'offline')>Offline</option>
                    </select>
                </div>
                <div>
                    <label>From date</label>
                    <input type="date" name="from" value="{{ $filters['from'] ?? '' }}">
                </div>
                <div>
                    <label>To date</label>
                    <input type="date" name="to" value="{{ $filters['to'] ?? '' }}">
                </div>
                <div style="display:flex;gap:0.45rem;align-items:flex-end;">
                    <button type="submit" class="ml-btn ml-btn--primary">Filter</button>
                    <a href="{{ route($dashboardRoute) }}" class="ml-btn ml-btn--ghost">Clear</a>
                </div>
            </div>
        </form>

        <div class="ml-table-wrap">
            <table class="ml-table">
                <thead>
                    <tr>
                        <th class="ml-table__num">#</th>
                        @if (!empty($showDistrictScope ?? $isAdminView))
                            <th>District</th>
                        @endif
                        <th>Incubatee</th>
                        <th style="text-align:center;white-space:nowrap;">Partners</th>
                        <th>Partner details (all)</th>
                        <th style="white-space:nowrap;">Last recorded</th>
                        <th></th>
                    </tr>
                </thead>
                <tbody>
                    @forelse ($rows as $row)
                        @php $rowNum = $rowOffset + $loop->iteration; @endphp
                        <tr>
                            <td class="ml-table__num">{{ $rowNum }}</td>
                            @if (!empty($showDistrictScope ?? $isAdminView))
                                <td>{{ $row->district_name ?? '—' }}</td>
                            @endif
                            <td style="min-width:10rem;">
                                <strong>{{ $row->incubatee_name }}</strong>
                                @if ($row->application_no)
                                    <div style="font-size:0.78rem;color:#64748b;">{{ $row->application_no }}</div>
                                @endif
                                @if (($row->source ?? '') === 'service_case')
                                    <div style="font-size:0.72rem;color:#b45309;margin-top:0.2rem;font-weight:700;">Service case</div>
                                @elseif ($row->submission_count > 1)
                                    <div style="font-size:0.76rem;color:#6d28d9;margin-top:0.2rem;">{{ $row->submission_count }} submissions</div>
                                @endif
                            </td>
                            <td style="text-align:center;">
                                <span class="ml-dash-badge">{{ $row->partner_count }}</span>
                            </td>
                            <td style="min-width:22rem;">
                                <div class="ml-dash-partners">
                                    @foreach ($row->partners as $p)
                                        <div class="ml-dash-partner">
                                            <span class="ml-dash-partner__name">{{ $p['partner_name'] }}</span>
                                            <span class="ml-dash-partner__meta">
                                                @if (!empty($p['link_url']))
                                                    @if (!empty($p['link_href']))
                                                        <a href="{{ $p['link_href'] }}" class="ml-dash-link" target="_blank" rel="noopener noreferrer" title="{{ $p['link_url'] }}">
                                                            <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><path d="M10 13a5 5 0 0 0 7.54.54l3-3a5 5 0 0 0-7.07-7.07l-1.72 1.71"/><path d="M14 11a5 5 0 0 0-7.54-.54l-3 3a5 5 0 0 0 7.07 7.07l1.71-1.71"/></svg>
                                                            <span>Link</span>
                                                        </a>
                                                    @else
                                                        <span class="ml-dash-link ml-dash-link--muted" title="{{ $p['link_url'] }}">
                                                            <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><path d="M10 13a5 5 0 0 0 7.54.54l3-3a5 5 0 0 0-7.07-7.07l-1.72 1.71"/><path d="M14 11a5 5 0 0 0-7.54-.54l-3 3a5 5 0 0 0 7.07 7.07l1.71-1.71"/></svg>
                                                            <span>Link</span>
                                                        </span>
                                                    @endif
                                                @else
                                                    <span style="color:#94a3b8;">No link</span>
                                                @endif
                                            </span>
                                            <span class="ml-dash-mode @if (($p['linkage_mode'] ?? '') === 'offline') ml-dash-mode--offline @endif">{{ $p['linkage_mode_label'] }}</span>
                                            <span>{{ $p['linkage_date_display'] }}</span>
                                            <span>
                                                @if (!empty($p['has_document']) && !empty($p['document_url']))
                                                    <a href="{{ $p['document_url'] }}" style="font-size:0.76rem;font-weight:600;">Bill</a>
                                                @else
                                                    <span class="ml-dash-partner__meta">No bill</span>
                                                @endif
                                            </span>
                                            <span class="ml-dash-partner__meta" style="grid-column:1 / -1;">
                                                Recorded {{ $p['recorded_at'] }} · {{ $p['recorded_by'] }}
                                                @if (!empty($p['show_url']))
                                                    · <a href="{{ $p['show_url'] }}">{{ !empty($p['service_case_id']) ? 'Service case' : 'Submission' }}</a>
                                                @elseif (!empty($p['service_case_id']))
                                                    · <span>Service case</span>
                                                @endif
                                                @if (!empty($p['delete_url']))
                                                    <form method="post"
                                                          action="{{ $p['delete_url'] }}"
                                                          class="ml-dash-delete-form"
                                                          onsubmit="return confirm('Delete this market linkage submission and all of its partner entries?');">
                                                        @csrf
                                                        @method('DELETE')
                                                        <input type="hidden" name="redirect_to" value="dashboard">
                                                        <span aria-hidden="true"> · </span><button type="submit" class="ml-dash-delete">Delete</button>
                                                    </form>
                                                @endif
                                            </span>
                                        </div>
                                    @endforeach
                                </div>
                            </td>
                            <td style="white-space:nowrap;">
                                {{ $row->last_recorded_at?->timezone(config('app.timezone'))->format('d M Y') ?? '—' }}
                            </td>
                            <td style="white-space:nowrap;">
                                @if ($createRoute)
                                    <a href="{{ route($createRoute, array_filter([
                                        'cfa_submission_id' => $row->cfa_submission_id,
                                        'legacy_application_id' => $row->legacy_application_id,
                                    ])) }}">+ Add</a>
                                @endif
                            </td>
                        </tr>
                    @empty
                        <tr>
                            <td colspan="{{ !empty($showDistrictScope ?? $isAdminView) ? 7 : 6 }}" style="padding:1.25rem;color:#64748b;text-align:center;">
                                No market linkage records for this scope.
                            </td>
                        </tr>
                    @endforelse
                </tbody>
            </table>
            <div class="ml-table-foot">
                Showing <strong>{{ number_format($listTotal) }}</strong> linked incubatee{{ $listTotal === 1 ? '' : 's' }}
                @if (! empty($isPaginated) && is_object($rows) && method_exists($rows, 'hasPages') && $rows->hasPages())
                    · page {{ $rows->currentPage() }} of {{ $rows->lastPage() }}
                @endif
            </div>
        </div>

        @if (!empty($isPaginated) && is_object($rows) && method_exists($rows, 'links'))
            <div>{{ $rows->links() }}</div>
        @endif
    </div>
@endsection
