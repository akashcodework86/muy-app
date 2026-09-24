@extends('layouts.admin')

@section('title', 'Market linkage coverage')
@section('heading', 'Market linkage coverage (6.3)')

@section('page_meta')
    <p class="admin-page-meta">{{ $scopeLabel }} · Onboarded incubatees vs approved market linkage</p>
@endsection

@section('content')
    @php
        $tabs = [
            'all' => 'All ('.number_format($summary['onboarded'] ?? 0).')',
            'linked' => 'Linked ('.number_format($summary['linked'] ?? 0).')',
            'not_linked' => 'Not linked ('.number_format($summary['not_linked'] ?? 0).')',
            'pending' => 'Pending ('.number_format($summary['pending'] ?? 0).')',
        ];
        $queryBase = array_filter([
            'hub_id' => $filters['hub_id'] ?? null,
            'district_id' => $filters['district_id'] ?? null,
            'sector' => $filters['sector'] ?: null,
            'block' => $filters['block'] ?: null,
            'q' => $filters['q'] ?: null,
            'fiscal_year' => ($activeFiscalYear ?? 'all') !== 'all' ? ($activeFiscalYear ?? null) : null,
        ]);
    @endphp

    <style>
        .mlc-shell{max-width:1200px}.mlc-note{margin:0 0 1rem;padding:.75rem 1rem;background:#eff6ff;border:1px solid #bfdbfe;border-radius:10px;color:#1e3a5f;font-size:.88rem;line-height:1.5}.mlc-stats{display:grid;grid-template-columns:repeat(auto-fit,minmax(150px,1fr));gap:.65rem;margin:0 0 1rem}.mlc-stat{background:#fff;border:1px solid #e2e8f0;border-radius:12px;padding:.85rem 1rem}.mlc-stat__label{font-size:.72rem;text-transform:uppercase;letter-spacing:.04em;color:#64748b;font-weight:700}.mlc-stat__value{font-size:1.35rem;font-weight:800;color:#0f172a;margin-top:.15rem}.mlc-stat--linked{border-color:#86efac;background:#f0fdf4}.mlc-stat--missing{border-color:#fecaca;background:#fef2f2}.mlc-stat--pending{border-color:#fde68a;background:#fffbeb}.mlc-toolbar{display:flex;flex-wrap:wrap;gap:.5rem;align-items:center;margin:0 0 1rem}.mlc-field{padding:.42rem .6rem;border:1px solid #d4d4d8;border-radius:8px;font:inherit;font-size:.85rem}.mlc-tabs{display:flex;flex-wrap:wrap;gap:.45rem;margin:0 0 1rem}.mlc-tab{border:1px solid #d4d4d8;background:#fff;color:#18181b;padding:.42rem .72rem;border-radius:999px;font-size:.82rem;font-weight:600;text-decoration:none}.mlc-tab.is-active{background:#18181b;border-color:#18181b;color:#fff}.mlc-table-wrap{overflow-x:auto}.mlc-table{width:100%;border-collapse:collapse;background:#fff;border:1px solid #e4e4e7;border-radius:10px;font-size:.84rem}.mlc-table th,.mlc-table td{padding:.65rem .7rem;border-bottom:1px solid #eef2f7;text-align:left;vertical-align:top}.mlc-table th{background:#f8fafc;font-size:.74rem;text-transform:uppercase;letter-spacing:.04em;color:#64748b}.mlc-pill{display:inline-block;padding:.15rem .45rem;border-radius:999px;font-size:.72rem;font-weight:700}.mlc-pill--linked{background:#dcfce7;color:#166534}.mlc-pill--not_linked{background:#fee2e2;color:#991b1b}.mlc-pill--pending{background:#fef3c7;color:#92400e}.mlc-btn{display:inline-block;padding:.45rem .8rem;background:#18181b;color:#fff;border-radius:8px;font-size:.82rem;font-weight:600;text-decoration:none;border:0;cursor:pointer}.mlc-btn--secondary{background:#fff;color:#18181b;border:1px solid #d4d4d8}.mlc-dim{font-size:.72rem;color:#64748b}
    </style>

    <div class="mlc-shell">
        <div class="mlc-note">
            Deliverable <strong>6.3 — Incubatees linked to online/offline Market</strong>.
            Universe = <strong>onboarded</strong> incubatees: Phase 3 locked batches (FY 2026-27) plus Phase 2 legacy onboarded (FY 2025-26) when you choose <strong>All FYs</strong> or <strong>2025-26</strong>.
            <strong>Linked</strong> = approved market linkage (module + legacy service cases). Pending = submitted, awaiting approval.
        </div>

        <div class="mlc-stats">
            <div class="mlc-stat"><div class="mlc-stat__label">Onboarded</div><div class="mlc-stat__value">{{ number_format($summary['onboarded'] ?? 0) }}</div></div>
            <div class="mlc-stat mlc-stat--linked"><div class="mlc-stat__label">Linked (6.3)</div><div class="mlc-stat__value">{{ number_format($summary['linked'] ?? 0) }}</div></div>
            <div class="mlc-stat mlc-stat--missing"><div class="mlc-stat__label">Not linked</div><div class="mlc-stat__value">{{ number_format($summary['not_linked'] ?? 0) }}</div></div>
            <div class="mlc-stat mlc-stat--pending"><div class="mlc-stat__label">Pending</div><div class="mlc-stat__value">{{ number_format($summary['pending'] ?? 0) }}</div></div>
        </div>

        <form method="get" action="{{ route($routePrefix.'.index') }}" class="mlc-toolbar">
            <input type="hidden" name="coverage" value="{{ $activeCoverage }}">
            <input class="mlc-field" type="search" name="q" value="{{ $filters['q'] ?? '' }}" placeholder="Search app no, name, phone" style="min-width:12rem;flex:1;max-width:20rem;">
            <select class="mlc-field" name="fiscal_year">
                <option value="all" @selected(($activeFiscalYear ?? 'all') === 'all')>All FYs</option>
                @foreach ($fiscalYears as $fy)
                    <option value="{{ $fy->code }}" @selected(($activeFiscalYear ?? 'all') === $fy->code)>FY {{ $fy->code }}</option>
                @endforeach
            </select>
            @if ($hubs->count() > 1)
                <select class="mlc-field" name="hub_id">
                    <option value="">All hubs</option>
                    @foreach ($hubs as $hub)
                        <option value="{{ $hub->id }}" @selected(($filters['hub_id'] ?? null) === (int) $hub->id)>{{ $hub->name }}</option>
                    @endforeach
                </select>
            @endif
            @if ($districts->count() > 1)
                <select class="mlc-field" name="district_id">
                    <option value="">All districts</option>
                    @foreach ($districts as $district)
                        <option value="{{ $district->id }}" @selected(($filters['district_id'] ?? null) === (int) $district->id)>{{ $district->name }}</option>
                    @endforeach
                </select>
            @endif
            <select class="mlc-field" name="sector">
                <option value="">All sectors</option>
                @foreach ($sectors as $sector)
                    <option value="{{ $sector }}" @selected(($filters['sector'] ?? '') === $sector)>{{ $sector }}</option>
                @endforeach
            </select>
            <select class="mlc-field" name="block">
                <option value="">All blocks</option>
                @foreach ($blocks as $block)
                    <option value="{{ $block }}" @selected(($filters['block'] ?? '') === $block)>{{ $block }}</option>
                @endforeach
            </select>
            <button type="submit" class="mlc-btn mlc-btn--secondary">Filter</button>
            <a href="{{ route($routePrefix.'.export', array_merge($queryBase, ['coverage' => $activeCoverage])) }}" class="mlc-btn" style="margin-left:auto;">Download CSV</a>
        </form>

        <div class="mlc-tabs">
            @foreach ($tabs as $key => $label)
                <a href="{{ route($routePrefix.'.index', array_merge($queryBase, ['coverage' => $key])) }}" class="mlc-tab @if ($activeCoverage === $key) is-active @endif">{{ $label }}</a>
            @endforeach
        </div>

        <div class="mlc-table-wrap">
            <table class="mlc-table">
                <thead>
                    <tr>
                        <th>Application</th>
                        <th>Incubatee</th>
                        <th>District</th>
                        <th>Sector</th>
                        <th>Block</th>
                        <th>Status</th>
                        <th>Mode</th>
                        <th></th>
                    </tr>
                </thead>
                <tbody>
                    @forelse ($rows as $row)
                        @php $showUrl = $showUrlResolver($row['cfa_submission_id']); @endphp
                        <tr>
                            <td>
                                <strong>{{ $row['application_no'] }}</strong>
                                <div class="mlc-dim">{{ $row['batch_name'] }} · FY {{ $row['fy_code'] ?? '—' }}</div>
                            </td>
                            <td>
                                {{ $row['applicant_name'] }}
                                <div class="mlc-dim">{{ $row['phone'] }}</div>
                            </td>
                            <td>
                                {{ $row['district_name'] }}
                                <div class="mlc-dim">{{ $row['hub_name'] }}</div>
                            </td>
                            <td>{{ $row['sector'] }}</td>
                            <td>{{ $row['block_name'] }}</td>
                            <td><span class="mlc-pill mlc-pill--{{ $row['coverage_status'] }}">{{ $row['coverage_label'] }}</span></td>
                            <td>{{ $row['linkage_mode'] }}</td>
                            <td>
                                @if ($showUrl)
                                    <a href="{{ $showUrl }}" class="mlc-btn mlc-btn--secondary" style="padding:.3rem .55rem;font-size:.76rem;">View CFA</a>
                                @endif
                            </td>
                        </tr>
                    @empty
                        <tr><td colspan="8" style="padding:1.2rem;color:#64748b;">No onboarded incubatees match these filters.</td></tr>
                    @endforelse
                </tbody>
            </table>
        </div>

        @if ($rows->hasPages())
            <div style="margin-top:1rem;">{{ $rows->links() }}</div>
        @endif
    </div>
@endsection
