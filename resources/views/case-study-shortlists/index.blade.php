@extends('layouts.admin')

@section('title', 'Monthly case study shortlist')
@section('heading', 'Monthly case study shortlist')

@push('styles')
<style>
    .css-shell{display:flex;flex-direction:column;gap:1rem}.css-card{background:#fff;border:1px solid #dce4ee;border-radius:16px;padding:1rem}.css-alert{border-radius:10px;padding:.75rem 1rem;font-size:.88rem}.css-alert--ok{background:#ecfdf5;color:#166534;border:1px solid #86efac}.css-alert--warn{background:#fffbeb;color:#92400e;border:1px solid #fde68a}.css-title{margin:0;color:#172033}.css-muted{color:#64748b;font-size:.82rem}.css-grid{display:grid;grid-template-columns:repeat(auto-fit,minmax(150px,1fr));gap:.7rem}.css-field label{display:block;font-size:.75rem;font-weight:800;color:#475569;margin-bottom:.3rem}.css-input{box-sizing:border-box;width:100%;padding:.58rem .65rem;border:1px solid #cbd5e1;border-radius:9px;background:#fff;font:inherit;font-size:.86rem}.css-actions{display:flex;gap:.5rem;align-items:end;flex-wrap:wrap}.css-btn{display:inline-flex;align-items:center;justify-content:center;border:0;border-radius:9px;padding:.58rem .9rem;font:inherit;font-size:.82rem;font-weight:800;cursor:pointer;text-decoration:none;background:#4f46e5;color:#fff}.css-btn--light{background:#fff;color:#334155;border:1px solid #cbd5e1}.css-btn--danger{background:#fff1f2;color:#be123c;border:1px solid #fecdd3}.css-btn:disabled{opacity:.5;cursor:not-allowed}.css-quota{display:flex;align-items:center;gap:.75rem;flex-wrap:wrap}.css-quota__bar{width:180px;height:8px;border-radius:99px;background:#e2e8f0;overflow:hidden}.css-quota__fill{height:100%;background:#4f46e5}.css-candidates{display:grid;grid-template-columns:repeat(auto-fit,minmax(280px,1fr));gap:.75rem;margin-top:1rem}.css-candidate{border:1px solid #dbe4f0;border-radius:13px;padding:.9rem;background:#f8fafc}.css-candidate__top{display:flex;justify-content:space-between;gap:.5rem}.css-name{font-size:.94rem;font-weight:850;color:#0f172a}.css-tags{display:flex;flex-wrap:wrap;gap:.35rem;margin:.6rem 0}.css-tag{padding:.2rem .5rem;border-radius:99px;background:#eef2ff;color:#4338ca;font-size:.72rem;font-weight:750}.css-tag--green{background:#dcfce7;color:#166534}.css-table-wrap{overflow-x:auto}.css-table{width:100%;border-collapse:collapse;font-size:.83rem}.css-table th{background:#f1f5f9;color:#334155;text-align:left;padding:.65rem;border-bottom:1px solid #cbd5e1;white-space:nowrap}.css-table td{padding:.75rem .65rem;border-bottom:1px solid #e2e8f0;vertical-align:top}.css-remarks{display:flex;flex-direction:column;gap:.45rem;min-width:240px}.css-remark{background:#f8fafc;border-radius:8px;padding:.48rem .55rem}.css-remark strong{font-size:.75rem}.css-inline{display:flex;gap:.4rem}.css-inline .css-input{min-width:170px}.css-status{font-weight:800}.css-status--removed{color:#be123c}@media(max-width:720px){.css-card{padding:.75rem}.css-table{min-width:900px}}
</style>
@endpush

@section('content')
<div class="css-shell">
    @if($migrationMissing)
        <div class="css-alert css-alert--warn">Database tables are not available yet. Run <code>php artisan migrate --force</code> during deployment.</div>
    @endif
    @if($errors->any())<div class="css-alert css-alert--warn"><strong>Please check:</strong> {{ $errors->first() }}</div>@endif

    <section class="css-card">
        <div style="display:flex;justify-content:space-between;gap:1rem;align-items:flex-start;flex-wrap:wrap">
            <div>
                <h2 class="css-title">{{ $user->role === 'district_staff' ? 'Choose this month’s incubatees' : 'District shortlists' }}</h2>
                <p class="css-muted" style="margin:.35rem 0 0">Maximum 5 active selections per district per month. An incubatee can be shortlisted only once across all programme years.</p>
            </div>
            @if($user->role === 'district_staff')
                <div class="css-quota">
                    <strong>{{ $activeCount }} / {{ $monthlyLimit }}</strong>
                    <div class="css-quota__bar"><div class="css-quota__fill" style="width:{{ min(100, ($activeCount / max(1,$monthlyLimit))*100) }}%"></div></div>
                    <span class="css-muted">{{ now()->format('F Y') }}</span>
                </div>
            @endif
        </div>

        <form method="get" action="{{ route($routePrefix.'.index') }}" style="margin-top:1rem">
            <div class="css-grid">
                @if($user->role !== 'district_staff')
                    <div class="css-field"><label>District</label><select name="district_id" class="css-input"><option value="0">All in scope</option>@foreach($districts as $district)<option value="{{ $district->id }}" @selected((int)$filters['district_id']===(int)$district->id)>{{ $district->name }}</option>@endforeach</select></div>
                @endif
                <div class="css-field"><label>Shortlist month</label><input type="month" name="month" value="{{ $filters['month'] }}" class="css-input"></div>
                @if($user->role !== 'district_staff')
                    <div class="css-field"><label>Incubatee programme year</label><select name="record_program_year" class="css-input"><option value="">All programme years</option>@foreach($programYears as $year=>$meta)<option value="{{ $year }}" @selected($filters['record_program_year']===$year)>{{ $meta['label'] }}</option>@endforeach</select></div>
                @endif
                @if($user->role === 'district_staff' && $month->isSameMonth(now()))
                    <div class="css-field"><label>Programme year</label><select name="program_year" class="css-input">@foreach($programYears as $year=>$meta)<option value="{{ $year }}" @selected($filters['program_year']===$year)>{{ $meta['label'] }}</option>@endforeach</select></div>
                    <div class="css-field"><label>Search</label><input type="search" name="q" value="{{ $filters['q'] }}" placeholder="Name / application / phone" class="css-input"></div>
                    <div class="css-field"><label>Block</label><input name="block" value="{{ $filters['block'] }}" placeholder="Any block" class="css-input"></div>
                    <div class="css-field"><label>Gender</label><select name="gender" class="css-input"><option value="">All</option>@foreach(['Female','Male','Other','NA'] as $v)<option value="{{ $v }}" @selected($filters['gender']===$v)>{{ $v }}</option>@endforeach</select></div>
                    <div class="css-field"><label>Business stage</label><select name="stage" class="css-input"><option value="">All stages</option>@foreach(['Early','Seed','Growth'] as $v)<option value="{{ $v }}" @selected(strcasecmp($filters['stage'],$v)===0)>{{ $v }}</option>@endforeach</select></div>
                    <div class="css-field"><label>Business category</label><select name="category" class="css-input"><option value="">All categories</option>@foreach(['Agri Allied','Food Processing','Handloom & Handicraft','Herbal and Aromatic','Homestay','Others'] as $v)<option value="{{ $v }}" @selected($filters['category']===$v)>{{ $v }}</option>@endforeach</select></div>
                @endif
                <div class="css-actions"><button class="css-btn" type="submit">Apply filters</button><a class="css-btn css-btn--light" href="{{ route($routePrefix.'.index') }}">Reset</a></div>
            </div>
            @if($user->role !== 'district_staff')
                <label style="display:inline-flex;align-items:center;gap:.4rem;margin-top:.8rem;font-size:.8rem"><input type="checkbox" name="include_removed" value="1" @checked(request()->boolean('include_removed'))> Include removed audit records</label>
            @endif
        </form>
    </section>

    @if($user->role === 'district_staff' && $month->isSameMonth(now()) && !$migrationMissing)
    <section class="css-card">
        <h3 class="css-title" style="font-size:1rem">Eligible onboarded incubatees · {{ $programYears[$filters['program_year']]['label'] }}</h3>
        <p class="css-muted">All matching onboarded incubatees in your district are shown. Already shortlisted people are automatically hidden.</p>
        <div class="css-candidates">
            @forelse($candidates as $candidate)
                <article class="css-candidate">
                    <div class="css-candidate__top"><div><div class="css-name">{{ $candidate['applicant_name'] }}</div><div class="css-muted">{{ $candidate['application_no'] ?: 'Application #'.$candidate['source_id'] }}</div></div><span class="css-tag css-tag--green">{{ $candidate['program_year'] }}</span></div>
                    <div class="css-tags">
                        @if($candidate['block'])<span class="css-tag">{{ $candidate['block'] }}</span>@endif
                        @if($candidate['category'])<span class="css-tag">{{ $candidate['category'] }}</span>@endif
                        @if($candidate['stage'])<span class="css-tag">{{ ucfirst(strtolower($candidate['stage'])) }}</span>@endif
                        @if($candidate['gender'])<span class="css-tag">{{ $candidate['gender'] }}</span>@endif
                        @if($candidate['applicant_type'])<span class="css-tag">{{ $candidate['applicant_type'] }}</span>@endif
                    </div>
                    @if($candidate['product'])<div class="css-muted" style="margin-bottom:.6rem">Product/enterprise: {{ $candidate['product'] }}</div>@endif
                    <form method="post" action="{{ route($routePrefix.'.store') }}">@csrf<input type="hidden" name="source" value="{{ $candidate['source'] }}"><input type="hidden" name="source_application_id" value="{{ $candidate['source_id'] }}"><button class="css-btn" type="submit" @disabled($activeCount >= $monthlyLimit)>+ Shortlist incubatee</button></form>
                </article>
            @empty
                <div class="css-alert css-alert--warn">No eligible incubatee matched these filters, or the selected legacy database is currently unavailable.</div>
            @endforelse
        </div>
    </section>
    @endif

    <section class="css-card" id="case-study-shortlist-live-section">
        <div style="display:flex;justify-content:space-between;gap:1rem;align-items:center;margin-bottom:.7rem"><h3 class="css-title" style="font-size:1rem">Shortlisted in {{ $month->format('F Y') }}</h3><div class="css-tags" style="margin:0"><span class="css-tag">{{ is_countable($rows) ? count($rows) : 0 }} shown</span>@if(in_array($user->role,['hub_admin','state_admin'],true))<span class="css-tag css-tag--green">Auto-updates</span>@endif</div></div>
        <div class="css-table-wrap"><table class="css-table"><thead><tr><th>Incubatee</th><th>Programme</th><th>District / block</th><th>Shortlisted by</th><th>Remarks</th><th>Status / action</th></tr></thead><tbody>
        @forelse($rows as $row)
            <tr>
                <td><div class="css-name">{{ $row->applicant_name }}</div><div class="css-muted">{{ $row->application_no ?: $row->candidate_key }}</div>@if($row->business_category)<div class="css-tags"><span class="css-tag">{{ $row->business_category }}</span>@if($row->business_stage)<span class="css-tag">{{ $row->business_stage }}</span>@endif</div>@endif</td>
                <td><strong>{{ $row->program_year }}</strong><div class="css-muted">{{ strtoupper($row->source) }}</div></td>
                <td>{{ $row->district?->name }}<div class="css-muted">{{ $row->block_name ?: 'Block not available' }}</div></td>
                <td>{{ $row->creator?->name ?: 'Unknown' }}<div class="css-muted">{{ str_replace('_',' ',ucfirst($row->creator?->role ?? '')) }} · {{ $row->created_at?->format('d M Y, h:i A') }}</div></td>
                <td><div class="css-remarks">@forelse($row->remarks as $remark)<div class="css-remark"><strong>{{ $remark->author?->name }} · {{ str_replace('_',' ',ucfirst($remark->author_role)) }}</strong><div>{{ $remark->remark }}</div><span class="css-muted">{{ $remark->created_at?->format('d M, h:i A') }}</span></div>@empty<span class="css-muted">No admin remarks yet.</span>@endforelse
                    @if(in_array($user->role,['hub_admin','state_admin'],true) && !$row->removed_at)<form class="css-inline" method="post" action="{{ route($routePrefix.'.remarks.store',$row) }}">@csrf<input class="css-input" name="remark" maxlength="2000" required placeholder="Add remark"><button class="css-btn" type="submit">Add</button></form>@endif
                </div></td>
                <td>@if($row->removed_at)<span class="css-status css-status--removed">Removed</span><div class="css-muted">{{ $row->removedBy?->name }} · {{ $row->removed_at->format('d M Y') }}</div>@if($row->removal_reason)<div class="css-muted">{{ $row->removal_reason }}</div>@endif
                    @elseif(\App\Support\CaseStudyShortlistAccess::canRemove($user,$row))<form method="post" action="{{ route($routePrefix.'.destroy',$row) }}" onsubmit="return confirm('Remove this shortlist entry? The audit record will be retained.')">@csrf @method('DELETE') @if(in_array($user->role,['hub_admin','state_admin'],true))<input class="css-input" name="removal_reason" maxlength="1000" required placeholder="Removal reason" style="margin-bottom:.4rem">@endif<button class="css-btn css-btn--danger" type="submit">Remove</button></form>
                    @else<span class="css-status" style="color:#15803d">Active</span>@endif</td>
            </tr>
        @empty<tr><td colspan="6" style="text-align:center;padding:2rem;color:#64748b">No shortlist entries for this district and month.</td></tr>@endforelse
        </tbody></table></div>
        @if(is_object($rows) && method_exists($rows,'links'))<div style="margin-top:1rem">{{ $rows->links() }}</div>@endif
    </section>
</div>
@endsection

@if(in_array($user->role,['hub_admin','state_admin'],true))
@push('scripts')
<script>
(() => {
    const refresh = async () => {
        if (document.visibilityState !== 'visible') return;
        const current = document.getElementById('case-study-shortlist-live-section');
        if (!current || current.contains(document.activeElement)) return;
        try {
            const response = await fetch(window.location.href, {headers: {'X-Requested-With': 'XMLHttpRequest', 'Accept': 'text/html'}});
            if (!response.ok) return;
            const page = new DOMParser().parseFromString(await response.text(), 'text/html');
            const updated = page.getElementById('case-study-shortlist-live-section');
            if (updated) current.replaceWith(updated);
        } catch (_) {
            // A temporary network/legacy DB issue must never interrupt the dashboard.
        }
    };
    window.setInterval(refresh, 15000);
})();
</script>
@endpush
@endif
