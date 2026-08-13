@extends('layouts.admin')

@section('title', 'Legacy Data')
@section('heading', 'Legacy Data')

@push('styles')
<style>
.ld-page{display:flex;flex-direction:column;gap:1rem;max-width:1500px;margin:0 auto;padding-bottom:2rem}.ld-intro{display:flex;justify-content:space-between;align-items:center;gap:1rem;flex-wrap:wrap}.ld-intro h2{margin:0;font-size:1.15rem;color:#0f172a}.ld-intro p{margin:.25rem 0 0;color:#64748b;font-size:.82rem}.ld-actions{display:flex;gap:.5rem;align-items:center;flex-wrap:wrap}.ld-btn{display:inline-flex;align-items:center;justify-content:center;gap:.35rem;border:1px solid #cbd5e1;border-radius:.65rem;padding:.5rem .8rem;background:#fff;color:#334155;text-decoration:none;font:inherit;font-size:.8rem;font-weight:700;cursor:pointer}.ld-btn:hover{background:#f8fafc}.ld-btn--primary{background:linear-gradient(135deg,#4f46e5,#0d9488);border-color:transparent;color:#fff}.ld-btn--warn{border-color:#fed7aa;color:#9a3412;background:#fff7ed}.ld-filter{background:#fff;border:1px solid #e2e8f0;border-radius:1rem;padding:1rem;box-shadow:0 1px 3px rgba(15,23,42,.05)}.ld-filter-grid{display:grid;grid-template-columns:repeat(6,minmax(135px,1fr));gap:.7rem}.ld-field label{display:block;margin-bottom:.25rem;color:#64748b;font-size:.67rem;font-weight:800;text-transform:uppercase;letter-spacing:.05em}.ld-field select,.ld-field input{width:100%;box-sizing:border-box;border:1px solid #cbd5e1;border-radius:.55rem;padding:.48rem .55rem;background:#fff;color:#0f172a;font:inherit;font-size:.8rem}.ld-field--search{grid-column:span 2}.ld-filter-foot{display:flex;justify-content:space-between;align-items:center;gap:.65rem;margin-top:.8rem;flex-wrap:wrap}.ld-more{margin-top:.8rem;padding-top:.8rem;border-top:1px solid #f1f5f9}.ld-more summary{cursor:pointer;color:#4f46e5;font-size:.8rem;font-weight:800;margin-bottom:.75rem}.ld-kpis{display:grid;grid-template-columns:repeat(4,minmax(0,1fr));gap:.75rem}.ld-kpi{background:#fff;border:1px solid #e2e8f0;border-radius:1rem;padding:1rem;box-shadow:0 1px 3px rgba(15,23,42,.05)}.ld-kpi span{font-size:.7rem;text-transform:uppercase;letter-spacing:.06em;color:#64748b;font-weight:800}.ld-kpi strong{display:block;margin-top:.25rem;font-size:1.7rem;color:#0f172a}.ld-kpi small{color:#94a3b8}.ld-kpi--green{border-color:#a7f3d0}.ld-kpi--green strong{color:#047857}.ld-kpi--violet{border-color:#c7d2fe}.ld-kpi--violet strong{color:#4f46e5}.ld-kpi--amber{border-color:#fde68a}.ld-kpi--amber strong{color:#b45309}.ld-panel{background:#fff;border:1px solid #e2e8f0;border-radius:1rem;overflow:hidden;box-shadow:0 1px 3px rgba(15,23,42,.05)}.ld-panel-head{display:flex;align-items:center;justify-content:space-between;gap:.75rem;flex-wrap:wrap;padding:.85rem 1rem;border-bottom:1px solid #e2e8f0}.ld-tabs{display:flex;background:#f1f5f9;padding:.2rem;border-radius:.7rem;gap:.15rem}.ld-tab{padding:.42rem .75rem;border-radius:.55rem;color:#64748b;text-decoration:none;font-size:.78rem;font-weight:800}.ld-tab.is-active{background:#fff;color:#4f46e5;box-shadow:0 1px 3px rgba(15,23,42,.12)}.ld-context{font-size:.75rem;color:#64748b}.ld-table-wrap{overflow:auto}.ld-table{width:100%;border-collapse:collapse;font-size:.78rem}.ld-table th{position:sticky;top:0;background:#f8fafc;padding:.65rem .75rem;text-align:left;color:#475569;font-size:.67rem;text-transform:uppercase;letter-spacing:.04em;white-space:nowrap;border-bottom:1px solid #e2e8f0}.ld-table td{padding:.65rem .75rem;border-bottom:1px solid #f1f5f9;color:#334155;vertical-align:top}.ld-table tbody tr:hover{background:#fafafe}.ld-num{text-align:right;font-variant-numeric:tabular-nums}.ld-name{font-weight:800;color:#0f172a}.ld-muted{color:#94a3b8;font-size:.7rem;margin-top:.12rem}.ld-pill{display:inline-flex;border-radius:999px;padding:.17rem .48rem;background:#eef2ff;color:#4338ca;font-size:.67rem;font-weight:800;white-space:nowrap}.ld-pill--green{background:#ecfdf5;color:#047857}.ld-pill--gray{background:#f1f5f9;color:#64748b}.ld-summary-link{color:#4f46e5;font-weight:800;text-decoration:none}.ld-summary-link:hover{text-decoration:underline}.ld-pagination{padding:.8rem 1rem}.ld-empty{text-align:center;padding:2.5rem 1rem;color:#64748b}.ld-note{font-size:.72rem;color:#64748b;background:#f8fafc;border:1px solid #e2e8f0;border-radius:.7rem;padding:.65rem .8rem}.ld-active{display:flex;gap:.35rem;flex-wrap:wrap}.ld-active span{background:#eef2ff;color:#4338ca;border-radius:999px;padding:.2rem .5rem;font-size:.67rem;font-weight:700}@media(max-width:1150px){.ld-filter-grid{grid-template-columns:repeat(3,minmax(140px,1fr))}.ld-field--search{grid-column:span 1}}@media(max-width:760px){.ld-filter-grid,.ld-kpis{grid-template-columns:1fr 1fr}}@media(max-width:520px){.ld-filter-grid,.ld-kpis{grid-template-columns:1fr}.ld-tabs{width:100%;overflow:auto}.ld-tab{white-space:nowrap}}
</style>
@endpush

@section('content')
@php
    $baseQuery = request()->except(['beneficiary_page', 'service_page']);
    $activeFilters = collect($filters)->except(['group'])->filter(fn($value) => $value !== '');
    $summaryFilter = match($filters['group']) {
        'fy' => 'fy', 'phase' => 'phase', 'service' => 'service', 'category' => 'category',
        'stage' => 'stage', 'gender' => 'gender', 'education' => 'education', 'type' => 'type', default => 'district'
    };
    $maskMobile = static function (string $mobile, bool $show): string {
        if ($mobile === '') return 'Not captured';
        if ($show || strlen($mobile) < 6) return $mobile;
        return substr($mobile, 0, 2).str_repeat('•', max(0, strlen($mobile)-4)).substr($mobile, -2);
    };
@endphp
<div class="ld-page">
    <div class="ld-intro">
        <div>
            <h2>Onboarding &amp; Services Explorer</h2>
            <p>State Admin only · Phase 3 service master names · Phase 1/2 services treated as approved · operational data read-only</p>
        </div>
        <div class="ld-actions">
            <a class="ld-btn" href="{{ route('admin.legacy-data.mappings') }}">Service mappings</a>
            <form method="post" action="{{ route('admin.legacy-data.refresh') }}">@csrf<button class="ld-btn ld-btn--warn" type="submit">↻ Refresh data</button></form>
            <a class="ld-btn ld-btn--primary" href="{{ route('admin.legacy-data.export', array_merge($baseQuery, ['view' => $viewMode === 'services' ? 'services' : 'beneficiaries'])) }}">↓ Export filtered CSV</a>
        </div>
    </div>

    <form class="ld-filter" method="get" action="{{ route('admin.legacy-data.index') }}">
        <input type="hidden" name="view" value="{{ $viewMode }}">
        <div class="ld-filter-grid">
            <div class="ld-field"><label for="ld-fy">Financial year</label><select id="ld-fy" name="fy"><option value="">All years</option>@foreach($options['financial_years'] as $option)<option @selected($filters['fy']===$option)>{{ $option }}</option>@endforeach</select></div>
            <div class="ld-field"><label for="ld-phase">Phase</label><select id="ld-phase" name="phase"><option value="">All phases</option>@foreach($options['phases'] as $option)<option @selected($filters['phase']===$option)>{{ $option }}</option>@endforeach</select></div>
            <div class="ld-field"><label for="ld-district">Home district</label><select id="ld-district" name="district"><option value="">All districts</option>@foreach($options['districts'] as $option)<option @selected($filters['district']===$option)>{{ $option }}</option>@endforeach</select></div>
            <div class="ld-field"><label for="ld-service">Service delivered</label><select id="ld-service" name="service"><option value="">All services</option>@foreach($options['services'] as $option)<option @selected($filters['service']===$option)>{{ $option }}</option>@endforeach</select></div>
            <div class="ld-field"><label for="ld-category">Business category</label><select id="ld-category" name="category"><option value="">All categories</option>@foreach($options['categories'] as $option)<option @selected($filters['category']===$option)>{{ $option }}</option>@endforeach</select></div>
            <div class="ld-field"><label for="ld-group">Group summary by</label><select id="ld-group" name="group">@foreach($groups as $key=>$label)<option value="{{ $key }}" @selected($filters['group']===$key)>{{ $label }}</option>@endforeach</select></div>
            <div class="ld-field ld-field--search"><label for="ld-q">Search</label><input id="ld-q" name="q" value="{{ $filters['q'] }}" placeholder="Application no, name or mobile"></div>
            <div class="ld-field"><label for="ld-from">Onboarded from</label><input id="ld-from" type="date" name="from" value="{{ $filters['from'] }}"></div>
            <div class="ld-field"><label for="ld-to">Onboarded to</label><input id="ld-to" type="date" name="to" value="{{ $filters['to'] }}"></div>
        </div>
        <details class="ld-more" @if(collect($filters)->only(['stage','gender','education','type','service_status'])->filter()->isNotEmpty()) open @endif>
            <summary>More filters</summary>
            <div class="ld-filter-grid">
                <div class="ld-field"><label>Business stage</label><select name="stage"><option value="">All stages</option>@foreach($options['stages'] as $option)<option @selected($filters['stage']===$option)>{{ $option }}</option>@endforeach</select></div>
                <div class="ld-field"><label>Gender</label><select name="gender"><option value="">All genders</option>@foreach($options['genders'] as $option)<option @selected($filters['gender']===$option)>{{ $option }}</option>@endforeach</select></div>
                <div class="ld-field"><label>Education</label><select name="education"><option value="">All education levels</option>@foreach($options['educations'] as $option)<option @selected($filters['education']===$option)>{{ $option }}</option>@endforeach</select></div>
                <div class="ld-field"><label>Beneficiary type</label><select name="type"><option value="">All types</option>@foreach($options['types'] as $option)<option @selected($filters['type']===$option)>{{ $option }}</option>@endforeach</select></div>
                <div class="ld-field"><label>Service status</label><select name="service_status"><option value="" @selected($filters['service_status']==='')>Approved only (reporting)</option><option value="__all__" @selected($filters['service_status']==='__all__')>All workflow statuses</option>@foreach($options['service_statuses'] as $option)@continue(strtolower($option)==='approved')<option value="{{ $option }}" @selected($filters['service_status']===$option)>{{ str_replace('_',' ',ucfirst($option)) }}</option>@endforeach</select></div>
            </div>
        </details>
        <div class="ld-filter-foot">
            <div class="ld-active">@forelse($activeFilters as $key=>$value)<span>{{ str_replace('_',' ',ucfirst($key)) }}: {{ $value }}</span>@empty<span>No filters applied</span>@endforelse</div>
            <div class="ld-actions"><a class="ld-btn" href="{{ route('admin.legacy-data.index') }}">Reset</a><button class="ld-btn ld-btn--primary" type="submit">Apply filters</button></div>
        </div>
    </form>

    <div class="ld-kpis">
        <div class="ld-kpi ld-kpi--violet"><span>Unique onboarded</span><strong>{{ number_format($kpis['onboarded']) }}</strong><small>Application number, then mobile dedupe</small></div>
        <div class="ld-kpi ld-kpi--green"><span>Beneficiaries served</span><strong>{{ number_format($kpis['served']) }}</strong><small>Approved services under current filters</small></div>
        <div class="ld-kpi"><span>Service deliveries</span><strong>{{ number_format($kpis['deliveries']) }}</strong><small>Approved by default; one beneficiary may have many</small></div>
        <div class="ld-kpi ld-kpi--amber"><span>No service recorded</span><strong>{{ number_format($kpis['without_service']) }}</strong><small>Onboarded but service data unavailable</small></div>
    </div>

    <div class="ld-panel">
        <div class="ld-panel-head">
            <div class="ld-tabs">
                @foreach(['summary'=>'Summary','beneficiaries'=>'Beneficiaries','services'=>'Service records'] as $key=>$label)
                    <a class="ld-tab @if($viewMode===$key) is-active @endif" href="{{ route('admin.legacy-data.index', array_merge($baseQuery,['view'=>$key])) }}">{{ $label }}</a>
                @endforeach
            </div>
            <div class="ld-actions">
                @if($viewMode!=='summary')<a class="ld-btn" href="{{ route('admin.legacy-data.index', array_merge($baseQuery,['view'=>$viewMode,'show_mobile'=>$showMobile?0:1])) }}">{{ $showMobile ? 'Mask mobile' : 'Show full mobile' }}</a>@endif
                <span class="ld-context">Generated {{ now()->format('d M Y, g:i A') }} · cached 5 min</span>
            </div>
        </div>

        @if($viewMode==='summary')
            <div class="ld-table-wrap"><table class="ld-table"><thead><tr><th>{{ $groups[$filters['group']] }}</th><th class="ld-num">Unique onboarded</th><th class="ld-num">Served</th><th class="ld-num">Service deliveries</th><th class="ld-num">No service</th></tr></thead><tbody>
            @forelse($summary as $row)
                @php $drillQuery=array_merge($baseQuery,['view'=>'beneficiaries',$summaryFilter=>$row['label']]); @endphp
                <tr><td><a class="ld-summary-link" href="{{ route('admin.legacy-data.index',$drillQuery) }}">{{ $row['label'] }}</a></td><td class="ld-num"><strong>{{ number_format($row['onboarded']) }}</strong></td><td class="ld-num">{{ number_format($row['served']) }}</td><td class="ld-num">{{ number_format($row['deliveries']) }}</td><td class="ld-num">{{ number_format($row['without_service']) }}</td></tr>
            @empty<tr><td colspan="5" class="ld-empty">No onboarded records match these filters.</td></tr>@endforelse
            </tbody></table></div>
        @elseif($viewMode==='beneficiaries')
            <div class="ld-table-wrap"><table class="ld-table"><thead><tr><th>Onboarding FY / Phase</th><th>Applicant</th><th>Mobile</th><th>District / Block</th><th>Business</th><th>Profile</th><th>Onboarding date</th><th class="ld-num">Services</th></tr></thead><tbody>
            @forelse($beneficiaries as $row)<tr>
                <td><span class="ld-pill">{{ $row['financial_year'] }}</span><div class="ld-muted">{{ $row['phase'] }}</div></td>
                <td><div class="ld-name">{{ $row['applicant'] }}</div><div class="ld-muted">{{ $row['application_no'] }}</div></td>
                <td>{{ $maskMobile($row['phone'],$showMobile) }}</td>
                <td>{{ $row['district'] }}<div class="ld-muted">{{ $row['block'] }}</div></td>
                <td>{{ $row['business_category'] }}<div class="ld-muted">{{ $row['business_stage'] }} · {{ $row['beneficiary_type'] }}</div></td>
                <td>{{ $row['gender'] }}<div class="ld-muted">{{ $row['education'] }}</div></td>
                <td>{{ $row['onboarding_date'] }}</td>
                <td class="ld-num"><span class="ld-pill {{ $row['filtered_services_count']?'ld-pill--green':'ld-pill--gray' }}">{{ $row['filtered_services_count'] }}</span></td>
            </tr>@empty<tr><td colspan="8" class="ld-empty">No onboarded beneficiaries match these filters.</td></tr>@endforelse
            </tbody></table></div><div class="ld-pagination">{{ $beneficiaries->links() }}</div>
        @else
            <div class="ld-table-wrap"><table class="ld-table"><thead><tr><th>Service FY / Source Phase</th><th>Applicant</th><th>Mobile</th><th>District</th><th>Service</th><th>Status</th><th>Delivery/event date</th></tr></thead><tbody>
            @forelse($services as $row)<tr>
                <td><span class="ld-pill">{{ $row['financial_year'] }}</span><div class="ld-muted">{{ $row['phase'] }}</div></td>
                <td><div class="ld-name">{{ $row['applicant'] }}</div><div class="ld-muted">{{ $row['application_no'] }}</div></td>
                <td>{{ $maskMobile($row['phone'],$showMobile) }}</td><td>{{ $row['district'] }}</td>
                <td><div class="ld-name">{{ $row['service'] }}</div>@if($row['original_service']!==$row['service'])<div class="ld-muted">Original: {{ $row['original_service'] }}</div>@endif @if($row['service_detail'])<div class="ld-muted">{{ $row['service_detail'] }}</div>@endif @if(!$row['service_mapped'])<span class="ld-pill ld-pill--gray">Needs mapping</span>@endif</td>
                <td><span class="ld-pill ld-pill--green">{{ $row['service_status'] }}</span></td><td>{{ $row['service_date'] }}</td>
            </tr>@empty<tr><td colspan="7" class="ld-empty">No service records match these filters.</td></tr>@endforelse
            </tbody></table></div><div class="ld-pagination">{{ $services->links() }}</div>
        @endif
    </div>
    <div class="ld-note"><strong>Method:</strong> Only onboarded records are included. Every member of a locked Phase 3 onboarding batch is reported in Phase 3, including legacy-source applicants onboarded through that batch. Phase 3 service names are the reporting standard. Phase 1/2 recorded services are treated as Approved and mapped to that master without changing historical or Phase 3 operational records. Phase 3 keeps its existing approval workflow. Missing fields remain “Not captured” or “Date NA”.</div>
</div>
@endsection
