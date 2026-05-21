@extends('layouts.admin')

@section('title', 'Field Coordinator Report')
@section('heading', 'Field Coordinator Report')

@push('styles')
<style>
    .fcr-shell { display:flex; flex-direction:column; gap:1.1rem; padding-bottom:2.5rem; font-family:'DM Sans',sans-serif; }
    .fcr-banner {
        background:linear-gradient(135deg,rgba(13,148,136,0.09),rgba(79,70,229,0.06));
        border:1px solid rgba(13,148,136,0.2); border-radius:16px;
        padding:1rem 1.25rem; display:flex; flex-wrap:wrap; align-items:center; justify-content:space-between; gap:0.85rem;
    }
    .fcr-banner h2 { margin:0 0 0.2rem; font-size:1rem; font-weight:800; color:#0f172a; }
    .fcr-banner p { margin:0; font-size:0.82rem; color:#64748b; }
    .fcr-banner__actions { display:flex; gap:0.5rem; flex-wrap:wrap; }
    .fcr-btn {
        display:inline-flex; align-items:center; gap:0.35rem; padding:0.5rem 0.85rem;
        border-radius:9px; font-size:0.82rem; font-weight:700; text-decoration:none; border:none; cursor:pointer; font-family:inherit;
    }
    .fcr-btn--primary { background:#4f46e5; color:#fff; }
    .fcr-btn--ghost { background:#fff; color:#4f46e5; border:1px solid #c7d2fe; }
    .fcr-kpi-grid { display:grid; grid-template-columns:repeat(auto-fit,minmax(140px,1fr)); gap:0.65rem; }
    .fcr-kpi {
        background:#fff; border:1px solid #e2e8f0; border-radius:14px; padding:0.75rem 0.9rem;
        box-shadow:0 2px 10px rgba(15,23,42,0.04); display:flex; align-items:center; gap:0.6rem;
    }
    .fcr-kpi__icon { width:2rem; height:2rem; border-radius:9px; display:flex; align-items:center; justify-content:center; font-size:0.85rem; flex-shrink:0; }
    .fcr-kpi__label { font-size:0.66rem; text-transform:uppercase; letter-spacing:0.06em; color:#64748b; font-weight:700; }
    .fcr-kpi__value { font-size:1.15rem; font-weight:800; color:#0f172a; }
    .fcr-section-title { margin:0 0 0.55rem; font-size:0.92rem; font-weight:800; color:#0f172a; }
    .fcr-section-sub { margin:0 0 0.65rem; font-size:0.78rem; color:#64748b; }
    .fcr-district-grid { display:grid; grid-template-columns:repeat(auto-fill,minmax(200px,1fr)); gap:0.6rem; }
    .fcr-district-card {
        display:block; text-decoration:none; color:inherit; background:#fff; border:1px solid #e2e8f0;
        border-radius:12px; padding:0.75rem 0.85rem; transition:border-color 0.2s, box-shadow 0.2s;
    }
    .fcr-district-card:hover { border-color:#818cf8; box-shadow:0 4px 16px rgba(79,70,229,0.08); }
    .fcr-district-card.is-active { border-color:#4f46e5; box-shadow:0 0 0 2px rgba(79,70,229,0.12); }
    .fcr-district-card__top { display:flex; justify-content:space-between; gap:0.4rem; align-items:flex-start; }
    .fcr-district-card__name { font-size:0.88rem; font-weight:800; color:#0f172a; }
    .fcr-district-card__count { font-size:1.2rem; font-weight:800; color:#4f46e5; }
    .fcr-district-card__meta { margin-top:0.4rem; font-size:0.72rem; color:#64748b; display:flex; flex-wrap:wrap; gap:0.35rem 0.5rem; }
    .fcr-filter {
        background:#fff; border:1px solid #e2e8f0; border-radius:14px; padding:0.85rem 1rem;
    }
    .fcr-filter-row { display:flex; flex-wrap:wrap; gap:0.5rem; align-items:flex-end; }
    .fcr-field { display:flex; flex-direction:column; gap:0.25rem; flex:1; min-width:140px; }
    .fcr-field label { font-size:0.7rem; font-weight:700; color:#334155; text-transform:uppercase; letter-spacing:0.05em; }
    .fcr-input { padding:0.48rem 0.6rem; border:1px solid #cbd5e1; border-radius:8px; font-size:0.86rem; width:100%; font-family:inherit; }
    .fcr-card { background:#fff; border:1px solid #e2e8f0; border-radius:16px; overflow:hidden; box-shadow:0 3px 14px rgba(15,23,42,0.04); }
    .fcr-card-head { padding:0.85rem 1.1rem; border-bottom:1px solid #e2e8f0; display:flex; align-items:center; gap:0.5rem; }
    .fcr-card-head h3 { margin:0; font-size:0.9rem; font-weight:700; color:#0f172a; flex:1; }
    .fcr-table { width:100%; border-collapse:collapse; font-size:0.84rem; }
    .fcr-table th { padding:0.62rem 0.9rem; text-align:left; background:#f8fafc; font-size:0.67rem; text-transform:uppercase; letter-spacing:0.06em; color:#64748b; border-bottom:1px solid #e2e8f0; white-space:nowrap; }
    .fcr-table td { padding:0.7rem 0.9rem; border-bottom:1px solid #f1f5f9; vertical-align:top; color:#334155; }
    .fcr-table tbody tr:hover td { background:#fafafe; }
    .fcr-date { display:inline-block; padding:0.2rem 0.5rem; background:#eef2ff; color:#4338ca; border-radius:8px; font-size:0.76rem; font-weight:700; }
    .fcr-coord { font-weight:700; color:#0f172a; }
    .fcr-coord-meta { font-size:0.72rem; color:#64748b; }
    .fcr-loc-area { font-weight:600; color:#0f172a; }
    .fcr-loc-sub { font-size:0.74rem; color:#64748b; }
    .fcr-num { font-weight:700; }
    .fcr-dl { display:inline-flex; align-items:center; gap:0.25rem; padding:0.25rem 0.5rem; background:#0f766e; color:#fff; border-radius:6px; font-size:0.72rem; font-weight:700; text-decoration:none; margin-right:0.25rem; margin-bottom:0.2rem; }
    .fcr-empty { padding:2.5rem 1rem; text-align:center; color:#64748b; }
    .fcr-pill { display:inline-block; padding:0.15rem 0.45rem; border-radius:999px; font-size:0.68rem; font-weight:700; background:#f0fdf4; color:#15803d; border:1px solid #bbf7d0; }
    .cfa-match,.cfa-mismatch,.cfa-neutral { display:inline-flex; padding:0.22rem 0.55rem; border-radius:999px; font-size:0.72rem; font-weight:800; color:#fff; }
    .cfa-match { background:#15803d; } .cfa-mismatch { background:#dc2626; } .cfa-neutral { background:#d97706; }
</style>
@endpush

@section('content')
@php
    $overview = $overview ?? [];
    $districtSummaries = $districtSummaries ?? [];
    $filters = $filters ?? [];
    $routeIndex = $routeIndex ?? 'admin.field-coordinator-reports.index';
    $routeAttachment = $routeAttachment ?? 'admin.field-coordinator-reports.attachment';
    $routeSheet = $routeSheet ?? 'admin.field-coordinator-reports.sheet';
    $showCoordinatorCol = ($scope->coordinatorUserId ?? null) === null;
    $isFieldCoordinator = \App\Services\FieldCoordinatorReports\FieldCoordinatorReportScope::isFieldCoordinator($user);
    $filterQuery = array_filter($filters);
@endphp

<div class="fcr-shell">
    @if (!empty($migrationMissing))
        <div style="background:#fffbeb;border:1px solid #fde68a;color:#92400e;border-radius:12px;padding:0.85rem 1rem;font-size:0.88rem;">
            <strong>Field coordinator reports table not found.</strong> Run <code>php artisan migrate</code>.
        </div>
    @endif

    <div class="fcr-banner">
        <div>
            <h2>Field Coordinator Report</h2>
            <p>{{ $scope->scopeLabel ?? '' }} · {{ number_format((int) ($overview['reports'] ?? 0)) }} report(s)</p>
        </div>
        <div class="fcr-banner__actions">
            @if ($user->role === 'district_staff' && $isFieldCoordinator)
                <a href="{{ route('staff.attendance.index') }}" class="fcr-btn fcr-btn--primary">
                    <i class="fa-solid fa-plus"></i> Submit new
                </a>
            @endif
            @if (($filters['district'] ?? null) || ($filters['coordinator_id'] ?? null) || ($filters['q'] ?? null) || ($filters['from'] ?? null))
                <a href="{{ route($routeIndex) }}" class="fcr-btn fcr-btn--ghost">Clear filters</a>
            @endif
        </div>
    </div>

    <div class="fcr-kpi-grid">
        <div class="fcr-kpi"><div class="fcr-kpi__icon" style="background:#eef2ff;color:#4f46e5;"><i class="fa-solid fa-calendar-check"></i></div><div><div class="fcr-kpi__label">Reports</div><div class="fcr-kpi__value">{{ number_format((int) ($overview['reports'] ?? 0)) }}</div></div></div>
        <div class="fcr-kpi"><div class="fcr-kpi__icon" style="background:#ccfbf1;color:#0f766e;"><i class="fa-solid fa-users"></i></div><div><div class="fcr-kpi__label">Participants</div><div class="fcr-kpi__value">{{ number_format((int) ($overview['participants'] ?? 0)) }}</div></div></div>
        <div class="fcr-kpi"><div class="fcr-kpi__icon" style="background:#fef9c3;color:#a16207;"><i class="fa-solid fa-map-pin"></i></div><div><div class="fcr-kpi__label">Villages</div><div class="fcr-kpi__value">{{ number_format((int) ($overview['villages'] ?? 0)) }}</div></div></div>
        @if ($showCoordinatorCol)
        <div class="fcr-kpi"><div class="fcr-kpi__icon" style="background:#fce7f3;color:#be185d;"><i class="fa-solid fa-user-tie"></i></div><div><div class="fcr-kpi__label">Coordinators</div><div class="fcr-kpi__value">{{ number_format((int) ($overview['coordinators'] ?? 0)) }}</div></div></div>
        <div class="fcr-kpi"><div class="fcr-kpi__icon" style="background:#ecfeff;color:#0e7490;"><i class="fa-solid fa-map"></i></div><div><div class="fcr-kpi__label">Districts</div><div class="fcr-kpi__value">{{ number_format((int) ($overview['districts'] ?? 0)) }}</div></div></div>
        @endif
        <div class="fcr-kpi"><div class="fcr-kpi__icon" style="background:#ffe4e6;color:#be123c;"><i class="fa-solid fa-file-pen"></i></div><div><div class="fcr-kpi__label">CFAs reported</div><div class="fcr-kpi__value">{{ number_format((int) ($overview['cfas'] ?? 0)) }}</div></div></div>
    </div>

    @if (count($districtSummaries) > 1)
        <div>
            <h3 class="fcr-section-title">District-wise summary</h3>
            <p class="fcr-section-sub">Click a district to filter reports below.</p>
            <div class="fcr-district-grid">
                @foreach ($districtSummaries as $districtRow)
                    @php
                        $cardQuery = array_filter(array_merge($filterQuery, ['district' => $districtRow['district_id'] > 0 ? $districtRow['district_id'] : null]));
                        unset($cardQuery['district']);
                        if ($districtRow['district_id'] > 0) {
                            $cardQuery['district'] = $districtRow['district_id'];
                        }
                        $isActive = (int) ($filters['district'] ?? 0) === (int) $districtRow['district_id'];
                    @endphp
                    <a href="{{ route($routeIndex, $cardQuery) }}" class="fcr-district-card @if($isActive) is-active @endif">
                        <div class="fcr-district-card__top">
                            <div class="fcr-district-card__name">{{ $districtRow['district_name'] }}</div>
                            <div class="fcr-district-card__count">{{ number_format((int) $districtRow['reports']) }}</div>
                        </div>
                        <div class="fcr-district-card__meta">
                            <span>{{ $districtRow['share_pct'] }}% share</span>
                            <span>{{ number_format((int) $districtRow['participants']) }} participants</span>
                            @if ($showCoordinatorCol)
                                <span>{{ (int) $districtRow['coordinators'] }} FC(s)</span>
                            @endif
                        </div>
                    </a>
                @endforeach
            </div>
        </div>
    @endif

    <div class="fcr-filter">
        <form method="get" action="{{ route($routeIndex) }}">
            <div class="fcr-filter-row">
                @if ($scope->canFilterHub ?? false)
                    <div class="fcr-field" style="max-width:180px;">
                        <label>Hub</label>
                        <select name="hub" class="fcr-input">
                            <option value="">All hubs</option>
                            @foreach ($hubs as $hub)
                                <option value="{{ $hub->id }}" @selected((int) ($filters['hub'] ?? 0) === (int) $hub->id)>{{ $hub->name }}</option>
                            @endforeach
                        </select>
                    </div>
                @endif
                @if ($scope->canFilterDistrict ?? false)
                    <div class="fcr-field" style="max-width:200px;">
                        <label>District</label>
                        <select name="district" class="fcr-input">
                            <option value="">All districts</option>
                            @foreach ($districts as $district)
                                <option value="{{ $district->id }}" @selected((int) ($filters['district'] ?? 0) === (int) $district->id)>{{ $district->name }}</option>
                            @endforeach
                        </select>
                    </div>
                @endif
                @if ($scope->canFilterCoordinator ?? false)
                    <div class="fcr-field" style="max-width:220px;">
                        <label>Field coordinator</label>
                        <select name="coordinator_id" class="fcr-input">
                            <option value="">All coordinators</option>
                            @foreach ($coordinators as $c)
                                <option value="{{ $c->id }}" @selected((int) ($filters['coordinator_id'] ?? 0) === (int) $c->id)>
                                    {{ $c->name }}@if($c->district?->name) — {{ $c->district->name }}@endif
                                </option>
                            @endforeach
                        </select>
                    </div>
                @endif
                <div class="fcr-field" style="max-width:280px;">
                    <label>Search</label>
                    <input type="search" name="q" value="{{ $filters['q'] ?? '' }}" class="fcr-input" placeholder="Name, block, area, GP…">
                </div>
                <div class="fcr-field" style="max-width:150px;">
                    <label>From</label>
                    <input type="date" name="from" value="{{ $filters['from'] ?? '' }}" class="fcr-input">
                </div>
                <div class="fcr-field" style="max-width:150px;">
                    <label>To</label>
                    <input type="date" name="to" value="{{ $filters['to'] ?? '' }}" class="fcr-input">
                </div>
                @if (!empty($blockOptions))
                    <div class="fcr-field" style="max-width:180px;">
                        <label>Block</label>
                        <select name="block" class="fcr-input">
                            <option value="">All blocks</option>
                            @foreach ($blockOptions as $b)
                                <option value="{{ $b }}" @selected(($filters['block'] ?? '') === $b)>{{ $b }}</option>
                            @endforeach
                        </select>
                    </div>
                @endif
                <div style="display:flex;gap:0.4rem;">
                    <button type="submit" class="fcr-btn fcr-btn--primary"><i class="fa-solid fa-filter"></i> Filter</button>
                    <a href="{{ route($routeIndex) }}" class="fcr-btn fcr-btn--ghost">Reset</a>
                </div>
            </div>
        </form>
    </div>

    <div class="fcr-card">
        <div class="fcr-card-head">
            <h3>Field visit records</h3>
            <span style="font-size:0.78rem;color:#64748b;">{{ number_format($reports->total()) }} total</span>
        </div>
        <div style="overflow-x:auto;">
            <table class="fcr-table">
                <thead>
                    <tr>
                        <th>#</th>
                        @if ($showCoordinatorCol)<th>Coordinator</th>@endif
                        <th>Visit date</th>
                        <th>Entry date</th>
                        <th>Location</th>
                        <th>GP / Area</th>
                        <th>Participants</th>
                        <th>M / F</th>
                        <th>CFAs</th>
                        <th>CFA check</th>
                        <th>Files</th>
                    </tr>
                </thead>
                <tbody>
                    @forelse ($reports as $i => $row)
                        @php
                            $dateKey = $row->visit_date?->format('Y-m-d');
                            $cfaKey = (int) $row->field_coordinator_user_id.'|'.$dateKey;
                            $cfaCount = (int) ($cfaByCoordinatorDate[$cfaKey] ?? 0);
                            $reported = (int) $row->cfas_filled_total;
                            $rowNum = ($reports->currentPage() - 1) * $reports->perPage() + $i + 1;
                            $mediaItems = $row->visitMediaItems();
                        @endphp
                        <tr>
                            <td style="color:#94a3b8;font-weight:600;">{{ $rowNum }}</td>
                            @if ($showCoordinatorCol)
                                <td>
                                    <div class="fcr-coord">{{ $row->field_coordinator_name ?: ($row->coordinator?->name ?? '—') }}</div>
                                    @if ($row->district?->name)
                                        <div class="fcr-coord-meta">{{ $row->district->name }}</div>
                                    @endif
                                </td>
                            @endif
                            <td><span class="fcr-date">{{ $row->visit_date?->format('d M Y') }}</span></td>
                            <td style="font-size:0.78rem;color:#64748b;">{{ $row->entry_date?->format('d M Y') }}</td>
                            <td>
                                @if ($row->block)<div class="fcr-loc-area">{{ $row->block }}</div>@endif
                                @if ($row->district?->name)<div class="fcr-loc-sub">{{ $row->district->name }}</div>@endif
                            </td>
                            <td>
                                <div class="fcr-loc-area">{{ $row->gramPanchayat?->name ?: ($row->area ?: '—') }}</div>
                                @if ($row->villages_visited_total > 0)
                                    <span class="fcr-pill">{{ (int) $row->villages_visited_total }} village(s)</span>
                                @endif
                            </td>
                            <td><span class="fcr-num">{{ number_format((int) $row->participants_total) }}</span></td>
                            <td style="font-size:0.78rem;">{{ (int) $row->participants_male_count }} / {{ (int) $row->participants_female_count }}</td>
                            <td><span class="fcr-num">{{ number_format($reported) }}</span></td>
                            <td>
                                @if ($cfaCount > 0 && $cfaCount >= $reported)
                                    <span class="cfa-match"><i class="fa-solid fa-check"></i> {{ $cfaCount }} OK</span>
                                @elseif ($cfaCount > 0)
                                    <span class="cfa-mismatch">{{ $cfaCount }} vs {{ $reported }}</span>
                                @else
                                    <span class="cfa-neutral">No CFA</span>
                                @endif
                            </td>
                            <td>
                                @foreach ($mediaItems as $mi => $media)
                                    <a href="{{ route($routeAttachment, ['attendanceReport' => $row, 'index' => $mi, 'inline' => 1]) }}" class="fcr-dl" target="_blank" rel="noopener">
                                        <i class="fa-solid fa-image"></i> Photo {{ $mi + 1 }}
                                    </a>
                                @endforeach
                                @if ($row->attachment_path && $mediaItems === [])
                                    <a href="{{ route($routeAttachment, $row) }}" class="fcr-dl"><i class="fa-solid fa-download"></i> Doc</a>
                                @endif
                                @if ($row->hasAttendanceSheet())
                                    <a href="{{ route($routeSheet, $row) }}" class="fcr-dl" style="background:#4338ca;"><i class="fa-solid fa-file-excel"></i> Sheet</a>
                                @endif
                                @if ($mediaItems === [] && ! $row->attachment_path && ! $row->hasAttendanceSheet())
                                    <span style="color:#cbd5e1;">—</span>
                                @endif
                            </td>
                        </tr>
                    @empty
                        <tr><td colspan="{{ $showCoordinatorCol ? 11 : 10 }}"><div class="fcr-empty">No field coordinator reports found for the selected filters.</div></td></tr>
                    @endforelse
                </tbody>
            </table>
        </div>
    </div>

    @if ($reports->hasPages())
        <div>{{ $reports->links() }}</div>
    @endif
</div>
@endsection
