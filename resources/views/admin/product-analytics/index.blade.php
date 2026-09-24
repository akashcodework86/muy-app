@extends('layouts.admin')

@section('title', 'Product Analytics')
@section('heading', 'Product Analytics')

@push('styles')
<style>
    .pa-shell { display:flex; flex-direction:column; gap:1rem; }
    .pa-head { display:flex; justify-content:space-between; align-items:flex-end; gap:1rem; flex-wrap:wrap; }
    .pa-head h2 { margin:0; color:#0f172a; font-size:1.45rem; }
    .pa-head p { margin:.25rem 0 0; color:#64748b; font-size:.84rem; }
    .pa-back { color:#0f766e; font-weight:800; text-decoration:none; font-size:.82rem; }
    .pa-kpis { display:grid; grid-template-columns:repeat(4,minmax(0,1fr)); gap:.75rem; }
    .pa-kpi,.pa-card { background:#fff; border:1px solid #dbe4ee; border-radius:16px; box-shadow:0 3px 14px rgba(15,23,42,.05); }
    .pa-kpi { padding:1rem; }
    .pa-kpi span { color:#64748b; font-size:.69rem; font-weight:800; text-transform:uppercase; letter-spacing:.06em; }
    .pa-kpi strong { display:block; margin-top:.28rem; color:#0f172a; font-size:1.65rem; }
    .pa-kpi small { display:block; margin-top:.2rem; color:#64748b; }
    .pa-filters { display:grid; grid-template-columns:2fr 1fr 1fr auto; gap:.7rem; padding:1rem; align-items:end; }
    .pa-field { display:flex; flex-direction:column; gap:.28rem; min-width:0; }
    .pa-field label { color:#64748b; font-size:.67rem; font-weight:800; text-transform:uppercase; letter-spacing:.06em; }
    .pa-field input,.pa-field select { width:100%; box-sizing:border-box; border:1px solid #cbd5e1; border-radius:10px; padding:.58rem .68rem; background:#fff; color:#0f172a; }
    .pa-actions { display:flex; gap:.4rem; }
    .pa-btn { display:inline-flex; align-items:center; justify-content:center; border:0; border-radius:10px; padding:.62rem .85rem; background:#0f766e; color:#fff; font-weight:800; text-decoration:none; cursor:pointer; white-space:nowrap; }
    .pa-btn--light { background:#fff; color:#334155; border:1px solid #cbd5e1; }
    .pa-grid { display:grid; grid-template-columns:minmax(0,1.4fr) minmax(300px,.8fr); gap:1rem; align-items:start; }
    .pa-card { overflow:hidden; }
    .pa-card-head { padding:1rem 1.05rem; border-bottom:1px solid #e2e8f0; display:flex; justify-content:space-between; align-items:center; gap:.75rem; }
    .pa-card-head h3 { margin:0; color:#0f172a; font-size:1rem; }
    .pa-card-head p { margin:.2rem 0 0; color:#64748b; font-size:.76rem; }
    .pa-badge { border-radius:999px; padding:.25rem .55rem; background:#ecfdf5; color:#047857; font-size:.7rem; font-weight:800; white-space:nowrap; }
    .pa-table-wrap { overflow:auto; }
    .pa-table { width:100%; border-collapse:collapse; font-size:.79rem; }
    .pa-table th { padding:.7rem .8rem; background:#f8fafc; color:#64748b; text-align:left; font-size:.66rem; text-transform:uppercase; letter-spacing:.05em; white-space:nowrap; }
    .pa-table td { padding:.7rem .8rem; border-top:1px solid #eef2f7; color:#334155; vertical-align:middle; }
    .pa-table tr.is-selected td { background:#f0fdfa; }
    .pa-product-link { color:#0f172a; font-weight:800; text-decoration:none; }
    .pa-product-link:hover { color:#0f766e; text-decoration:underline; }
    .pa-share { min-width:120px; }
    .pa-track { height:6px; margin-top:.3rem; background:#e2e8f0; border-radius:999px; overflow:hidden; }
    .pa-track span { display:block; height:100%; background:linear-gradient(90deg,#4f46e5,#0d9488); border-radius:inherit; }
    .pa-pagination { padding:.8rem 1rem; border-top:1px solid #e2e8f0; }
    .pa-empty { padding:1.4rem; text-align:center; color:#64748b; }
    .pa-detail-summary { padding:1rem; display:grid; grid-template-columns:repeat(2,minmax(0,1fr)); gap:.6rem; }
    .pa-mini { padding:.7rem; background:#f8fafc; border-radius:12px; }
    .pa-mini span { display:block; color:#64748b; font-size:.65rem; font-weight:800; text-transform:uppercase; }
    .pa-mini strong { display:block; margin-top:.2rem; color:#0f172a; font-size:1.15rem; }
    .pa-breakdowns { padding:0 1rem 1rem; display:grid; grid-template-columns:repeat(3,minmax(0,1fr)); gap:.7rem; }
    .pa-break h4 { margin:0 0 .45rem; color:#334155; font-size:.74rem; }
    .pa-break-row { display:flex; justify-content:space-between; gap:.5rem; padding:.28rem 0; border-bottom:1px dashed #e2e8f0; font-size:.7rem; }
    .pa-break-row span { overflow:hidden; text-overflow:ellipsis; white-space:nowrap; color:#475569; }
    .pa-records { grid-column:1 / -1; }
    .pa-open { color:#0f766e; font-weight:800; text-decoration:none; }
    @media(max-width:1050px){.pa-kpis{grid-template-columns:repeat(2,1fr)}.pa-grid{grid-template-columns:1fr}.pa-filters{grid-template-columns:1fr 1fr}.pa-actions{grid-column:1/-1}.pa-records{grid-column:auto}}
    @media(max-width:620px){.pa-kpis,.pa-filters,.pa-breakdowns{grid-template-columns:1fr}.pa-detail-summary{grid-template-columns:1fr 1fr}}
</style>
@endpush

@section('content')
@php
    $filterQuery = array_filter([
        'q' => $filters['q'] ?? '',
        'district' => $filters['district'] ?? null,
        'sector' => $filters['sector'] ?? '',
    ], fn ($value) => $value !== '' && $value !== null);
@endphp
<div class="pa-shell">
    <div class="pa-head">
        <div>
            <h2>Onboarded Product Analytics</h2>
            <p>Locked Phase 3 onboarding batches · product, geography and incubatee drill-down</p>
        </div>
        <a class="pa-back" href="{{ route('dashboard') }}">← Back to State Dashboard</a>
    </div>

    <div class="pa-kpis">
        <div class="pa-kpi"><span>Total onboarded</span><strong>{{ number_format($summary['total']) }}</strong><small>Same locked-batch population as dashboard</small></div>
        <div class="pa-kpi"><span>Specified products</span><strong>{{ number_format($summary['distinct']) }}</strong><small>{{ number_format($summary['specified']) }} incubatees with product data</small></div>
        <div class="pa-kpi"><span>Not specified</span><strong>{{ number_format($summary['missing']) }}</strong><small>{{ $summary['total'] > 0 ? number_format(($summary['missing'] / $summary['total']) * 100, 1) : '0.0' }}% data gap</small></div>
        <div class="pa-kpi"><span>Current filter</span><strong>{{ number_format($summary['filtered_total']) }}</strong><small>Incubatees in selected scope</small></div>
    </div>

    <form method="get" action="{{ route('admin.product-analytics.index') }}" class="pa-card pa-filters">
        <div class="pa-field"><label for="pa-q">Search</label><input id="pa-q" name="q" value="{{ $filters['q'] ?? '' }}" placeholder="Product, incubatee, application, block"></div>
        <div class="pa-field"><label for="pa-district">District</label><select id="pa-district" name="district"><option value="">All districts</option>@foreach($districtOptions as $district)<option value="{{ $district->id }}" @selected((int)($filters['district'] ?? 0)===(int)$district->id)>{{ $district->name }}</option>@endforeach</select></div>
        <div class="pa-field"><label for="pa-sector">Sector</label><select id="pa-sector" name="sector"><option value="">All sectors</option>@foreach($sectors as $sector)<option value="{{ $sector }}" @selected(($filters['sector'] ?? '')===$sector)>{{ $sector }}</option>@endforeach</select></div>
        <div class="pa-actions"><button class="pa-btn" type="submit">Apply</button><a class="pa-btn pa-btn--light" href="{{ route('admin.product-analytics.index') }}">Reset</a></div>
    </form>

    <div class="pa-grid">
        <section class="pa-card">
            <div class="pa-card-head"><div><h3>Product catalogue</h3><p>Click any product to open its full analysis and incubatee records.</p></div><span class="pa-badge">{{ number_format($products->total()) }} rows</span></div>
            @if($products->isEmpty())
                <div class="pa-empty">No products match the selected filters.</div>
            @else
                <div class="pa-table-wrap"><table class="pa-table"><thead><tr><th>Product</th><th>Sector(s)</th><th>Districts</th><th>Incubatees / share</th><th></th></tr></thead><tbody>
                @foreach($products as $product)
                    @php $productUrl = route('admin.product-analytics.index', array_merge($filterQuery, ['product' => $product['key']])); @endphp
                    <tr @class(['is-selected' => ($selectedProduct['key'] ?? '') === $product['key']])>
                        <td><a class="pa-product-link" href="{{ $productUrl }}#product-detail">{{ $product['product'] }}</a></td>
                        <td>{{ implode(', ', array_slice($product['sectors'], 0, 2)) }}@if(count($product['sectors'])>2) +{{ count($product['sectors'])-2 }}@endif</td>
                        <td>{{ number_format($product['districts']) }}</td>
                        <td class="pa-share"><strong>{{ number_format($product['count']) }}</strong> · {{ number_format($product['pct'],1) }}%<div class="pa-track"><span style="width:{{ min(100,$product['pct']) }}%"></span></div></td>
                        <td><a class="pa-open" href="{{ $productUrl }}#product-detail">Analyze →</a></td>
                    </tr>
                @endforeach
                </tbody></table></div>
                <div class="pa-pagination">{{ $products->onEachSide(1)->links() }}</div>
            @endif
        </section>

        <section class="pa-card" id="product-detail">
            @if($selectedProduct)
                <div class="pa-card-head"><div><h3>{{ $selectedProduct['product'] }}</h3><p>Selected product analysis</p></div><a class="pa-open" href="{{ route('admin.product-analytics.index', $filterQuery) }}">Clear ×</a></div>
                <div class="pa-detail-summary">
                    <div class="pa-mini"><span>Incubatees</span><strong>{{ number_format($selectedProduct['count']) }}</strong></div>
                    <div class="pa-mini"><span>Districts</span><strong>{{ number_format($selectedProduct['districts']) }}</strong></div>
                </div>
                <div class="pa-breakdowns">
                    <div class="pa-break"><h4>District breakup</h4>@forelse($districtBreakdown as $label=>$count)<div class="pa-break-row"><span title="{{ $label }}">{{ $label }}</span><strong>{{ $count }}</strong></div>@empty<div class="pa-empty">No data</div>@endforelse</div>
                    <div class="pa-break"><h4>Sector breakup</h4>@forelse($sectorBreakdown as $label=>$count)<div class="pa-break-row"><span title="{{ $label }}">{{ $label }}</span><strong>{{ $count }}</strong></div>@empty<div class="pa-empty">No data</div>@endforelse</div>
                    <div class="pa-break"><h4>Business stage</h4>@forelse($stageBreakdown as $label=>$count)<div class="pa-break-row"><span title="{{ $label }}">{{ $label }}</span><strong>{{ $count }}</strong></div>@empty<div class="pa-empty">No data</div>@endforelse</div>
                </div>
            @else
                <div class="pa-empty"><strong>Select a product</strong><br>Its district, sector, stage and incubatee-level details will appear here.</div>
            @endif
        </section>

        @if($selectedProduct)
        <section class="pa-card pa-records">
            <div class="pa-card-head"><div><h3>{{ $selectedProduct['product'] }} — onboarded incubatees</h3><p>Every application number opens the full CFA record.</p></div><span class="pa-badge">{{ number_format($selectedRecords->total()) }} incubatees</span></div>
            <div class="pa-table-wrap"><table class="pa-table"><thead><tr><th>Application</th><th>Incubatee</th><th>District / block</th><th>Sector</th><th>Stage</th><th>Batch</th><th>Onboarded</th></tr></thead><tbody>
                @foreach($selectedRecords as $record)
                <tr>
                    <td><a class="pa-open" href="{{ route('admin.cfa.show', $record['id']) }}">{{ $record['application_no'] ?: '#'.$record['id'] }}</a></td>
                    <td><strong>{{ $record['applicant_name'] }}</strong><br><small>{{ $record['phone'] }}</small></td>
                    <td>{{ $record['district'] }}<br><small>{{ $record['block'] }}</small></td>
                    <td>{{ $record['sector'] }}</td><td>{{ $record['stage'] }}</td><td>{{ $record['batch_name'] }}</td><td>{{ $record['onboarded_at'] }}</td>
                </tr>
                @endforeach
            </tbody></table></div>
            <div class="pa-pagination">{{ $selectedRecords->onEachSide(1)->links() }}</div>
        </section>
        @endif
    </div>
</div>
@endsection
