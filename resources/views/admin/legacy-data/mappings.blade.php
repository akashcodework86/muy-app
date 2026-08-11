@extends('layouts.admin')

@section('title', 'Legacy Service Mappings')
@section('heading', 'Legacy Service Mappings')

@push('styles')
<style>
.lsm-page{max-width:1500px;margin:0 auto;display:flex;flex-direction:column;gap:1rem;padding-bottom:2rem}.lsm-head{display:flex;justify-content:space-between;gap:1rem;align-items:flex-start;flex-wrap:wrap}.lsm-head h2{margin:0;color:#0f172a;font-size:1.2rem}.lsm-head p{margin:.3rem 0 0;color:#64748b;font-size:.82rem;max-width:850px}.lsm-btn{display:inline-flex;align-items:center;justify-content:center;border:1px solid #cbd5e1;border-radius:.6rem;padding:.48rem .75rem;background:#fff;color:#334155;text-decoration:none;font:inherit;font-size:.78rem;font-weight:800;cursor:pointer}.lsm-btn--primary{background:linear-gradient(135deg,#4f46e5,#0d9488);color:#fff;border-color:transparent}.lsm-stats{display:grid;grid-template-columns:repeat(4,minmax(0,1fr));gap:.75rem}.lsm-stat{background:#fff;border:1px solid #e2e8f0;border-radius:.9rem;padding:.85rem 1rem}.lsm-stat span{display:block;color:#64748b;font-size:.68rem;text-transform:uppercase;font-weight:800;letter-spacing:.05em}.lsm-stat strong{display:block;color:#0f172a;font-size:1.45rem;margin-top:.2rem}.lsm-filter{display:flex;gap:.6rem;align-items:end;flex-wrap:wrap;background:#fff;border:1px solid #e2e8f0;border-radius:.9rem;padding:.8rem}.lsm-field label{display:block;color:#64748b;font-size:.67rem;font-weight:800;text-transform:uppercase;margin-bottom:.25rem}.lsm-field input,.lsm-field select,.lsm-map-select{box-sizing:border-box;border:1px solid #cbd5e1;border-radius:.5rem;padding:.45rem .55rem;background:#fff;color:#0f172a;font:inherit;font-size:.78rem}.lsm-field input{min-width:280px}.lsm-panel{background:#fff;border:1px solid #e2e8f0;border-radius:1rem;overflow:hidden}.lsm-table-wrap{overflow:auto}.lsm-table{border-collapse:collapse;width:100%;font-size:.77rem}.lsm-table th{background:#f8fafc;padding:.62rem .7rem;text-align:left;color:#475569;font-size:.66rem;text-transform:uppercase;letter-spacing:.04em;white-space:nowrap}.lsm-table td{padding:.65rem .7rem;border-top:1px solid #f1f5f9;vertical-align:top;color:#334155}.lsm-name{font-weight:850;color:#0f172a}.lsm-muted{font-size:.69rem;color:#94a3b8;margin-top:.15rem}.lsm-pill{display:inline-flex;border-radius:999px;padding:.17rem .45rem;font-size:.65rem;font-weight:850;background:#ecfdf5;color:#047857}.lsm-pill--warn{background:#fff7ed;color:#c2410c}.lsm-map{display:flex;gap:.4rem;min-width:360px}.lsm-map-select{flex:1;min-width:260px}.lsm-note{background:#eff6ff;border:1px solid #bfdbfe;color:#1e40af;border-radius:.75rem;padding:.7rem .85rem;font-size:.75rem}.lsm-pagination{padding:.8rem 1rem}@media(max-width:800px){.lsm-stats{grid-template-columns:1fr 1fr}.lsm-field input{min-width:200px}}@media(max-width:520px){.lsm-stats{grid-template-columns:1fr}.lsm-map{min-width:300px}}
</style>
@endpush

@section('content')
<div class="lsm-page">
    <div class="lsm-head">
        <div><h2>Phase 1/2 → Phase 3 Service Master</h2><p>Phase 3 service names are final. This page only controls reporting aliases for historical Phase 1/2 names; it never edits Phase 3 services or service records.</p></div>
        <a class="lsm-btn" href="{{ route('admin.legacy-data.index') }}">← Back to Legacy Data</a>
    </div>

    <div class="lsm-stats">
        <div class="lsm-stat"><span>Historical names</span><strong>{{ number_format($stats['total']) }}</strong></div>
        <div class="lsm-stat"><span>Mapped</span><strong>{{ number_format($stats['mapped']) }}</strong></div>
        <div class="lsm-stat"><span>Needs mapping</span><strong>{{ number_format($stats['unmapped']) }}</strong></div>
        <div class="lsm-stat"><span>Unmapped records</span><strong>{{ number_format($stats['records_unmapped']) }}</strong></div>
    </div>

    <form class="lsm-filter" method="get">
        <div class="lsm-field"><label for="lsm-q">Search names</label><input id="lsm-q" name="q" value="{{ $filters['q'] }}" placeholder="Legacy or standard service name"></div>
        <div class="lsm-field"><label for="lsm-status">Mapping status</label><select id="lsm-status" name="status"><option value="all" @selected($filters['status']==='all')>All</option><option value="unmapped" @selected($filters['status']==='unmapped')>Needs mapping</option><option value="mapped" @selected($filters['status']==='mapped')>Mapped</option></select></div>
        <button class="lsm-btn lsm-btn--primary" type="submit">Apply</button>
    </form>

    <div class="lsm-note"><strong>Approval rule:</strong> every recorded Phase 1/2 service is treated as Approved. Phase 3 continues using its own approval workflow.</div>

    <div class="lsm-panel"><div class="lsm-table-wrap"><table class="lsm-table">
        <thead><tr><th>Source</th><th>Original historical name</th><th>Effective standard name</th><th>Records / beneficiaries</th><th>Map to Phase 3 service</th></tr></thead>
        <tbody>
        @forelse($rows as $row)
            <tr>
                <td><span class="lsm-pill">{{ $row['phase'] }}</span><div class="lsm-muted">All treated Approved</div></td>
                <td><div class="lsm-name">{{ $row['original_name'] }}</div><div class="lsm-muted">Original value retained</div></td>
                <td><div class="lsm-name">{{ $row['standard_name'] }}</div><span class="lsm-pill {{ $row['mapped'] ? '' : 'lsm-pill--warn' }}">{{ $row['mapped'] ? ucfirst($row['mapping_source']) : 'Needs mapping' }}</span></td>
                <td><strong>{{ number_format($row['records']) }}</strong> records<div class="lsm-muted">{{ number_format($row['beneficiaries']) }} unique beneficiaries</div></td>
                <td><form class="lsm-map" method="post" action="{{ route('admin.legacy-data.mappings.store') }}">@csrf
                    <input type="hidden" name="source_phase" value="{{ $row['source_phase'] }}"><input type="hidden" name="original_name" value="{{ $row['original_name'] }}">
                    <select class="lsm-map-select" name="service_id" required><option value="">Select Phase 3 service…</option>@foreach($services as $service)<option value="{{ $service->id }}" @selected((int)$row['service_id']===$service->id)>{{ $service->name }}{{ $service->is_active ? '' : ' (Inactive)' }}</option>@endforeach</select>
                    <button class="lsm-btn lsm-btn--primary" type="submit">Save</button>
                </form></td>
            </tr>
        @empty<tr><td colspan="5" style="padding:2rem;text-align:center;color:#64748b">No historical service names match these filters.</td></tr>@endforelse
        </tbody>
    </table></div><div class="lsm-pagination">{{ $rows->withQueryString()->links() }}</div></div>
</div>
@endsection
