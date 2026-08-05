@extends('layouts.admin')

@section('title', $shortlist->applicant_name.' - Professional profile')
@section('heading', 'Incubatee professional profile')

@push('styles')
<style>
    .csp-shell{display:grid;grid-template-columns:minmax(0,1fr) 350px;gap:1rem;align-items:start}.csp-main{display:flex;flex-direction:column;gap:1rem}.csp-card{background:#fff;border:1px solid #dce4ee;border-radius:16px;padding:1rem 1.1rem;box-shadow:0 4px 16px rgba(15,23,42,.035)}.csp-hero{background:linear-gradient(135deg,#312e81,#4f46e5 60%,#0ea5e9);color:#fff;border:0;padding:1.25rem}.csp-hero__top{display:flex;justify-content:space-between;gap:1rem;flex-wrap:wrap}.csp-avatar{width:58px;height:58px;border-radius:16px;background:rgba(255,255,255,.18);display:grid;place-items:center;font-size:1.35rem;font-weight:900;border:1px solid rgba(255,255,255,.35)}.csp-hero h2{margin:0;font-size:1.35rem}.csp-hero p{margin:.28rem 0 0;color:rgba(255,255,255,.8);font-size:.84rem}.csp-tags{display:flex;flex-wrap:wrap;gap:.4rem;margin-top:.8rem}.csp-tag{display:inline-flex;padding:.25rem .55rem;border-radius:99px;background:#eef2ff;color:#4338ca;font-size:.72rem;font-weight:800}.csp-hero .csp-tag{background:rgba(255,255,255,.15);color:#fff;border:1px solid rgba(255,255,255,.22)}.csp-title{margin:0 0 .8rem;color:#172033;font-size:1rem}.csp-fields{display:grid;grid-template-columns:repeat(auto-fit,minmax(210px,1fr));gap:.65rem}.csp-field{background:#f8fafc;border:1px solid #eef2f7;border-radius:10px;padding:.65rem .7rem}.csp-field__label{font-size:.69rem;text-transform:uppercase;letter-spacing:.045em;font-weight:850;color:#64748b}.csp-field__value{margin-top:.25rem;color:#172033;font-size:.85rem;line-height:1.45;overflow-wrap:anywhere}.csp-field--missing .csp-field__value{color:#94a3b8;font-style:italic}.csp-side{position:sticky;top:92px;display:flex;flex-direction:column;gap:1rem}.csp-service{display:grid;grid-template-columns:minmax(180px,1.2fr) .7fr .7fr;gap:.75rem;padding:.75rem 0;border-bottom:1px solid #e2e8f0;align-items:start}.csp-service:last-child{border-bottom:0}.csp-service strong{font-size:.86rem}.csp-muted{color:#64748b;font-size:.76rem;line-height:1.45}.csp-status{display:inline-flex;padding:.2rem .48rem;border-radius:99px;background:#ecfdf5;color:#166534;font-size:.7rem;font-weight:850}.csp-docs{display:flex;flex-wrap:wrap;gap:.35rem;margin-top:.45rem}.csp-doc{display:inline-flex;color:#4338ca;font-size:.72rem;font-weight:800;text-decoration:none;border:1px solid #c7d2fe;background:#eef2ff;border-radius:7px;padding:.25rem .45rem}.csp-doc:hover{text-decoration:underline}.csp-option{display:block;border:1px solid #dbe4f0;border-radius:11px;padding:.75rem;margin-bottom:.6rem}.csp-option__row{display:flex;gap:.6rem;align-items:flex-start}.csp-option input{margin-top:.18rem;width:17px;height:17px;accent-color:#4f46e5}.csp-option strong{display:block;font-size:.86rem}.csp-option p{margin:.2rem 0 0;color:#64748b;font-size:.75rem;line-height:1.4}.csp-option--received{background:#f0fdf4;border-color:#bbf7d0}.csp-textarea{box-sizing:border-box;width:100%;min-height:86px;border:1px solid #cbd5e1;border-radius:10px;padding:.65rem;font:inherit;font-size:.82rem;resize:vertical}.csp-btn{display:inline-flex;align-items:center;justify-content:center;border:0;border-radius:9px;padding:.6rem .9rem;background:#4f46e5;color:#fff;text-decoration:none;font:inherit;font-size:.8rem;font-weight:850;cursor:pointer}.csp-btn--light{background:#fff;color:#334155;border:1px solid #cbd5e1}.csp-audit{border-left:2px solid #c7d2fe;padding-left:.7rem;margin:.65rem 0}.csp-audit strong{font-size:.78rem}.csp-alert{padding:.75rem 1rem;border-radius:10px;background:#ecfdf5;border:1px solid #86efac;color:#166534;font-size:.84rem}@media(max-width:960px){.csp-shell{grid-template-columns:1fr}.csp-side{position:static}}@media(max-width:600px){.csp-service{grid-template-columns:1fr}.csp-card{padding:.85rem}}
</style>
@endpush

@section('content')
@php
    $initials = collect(preg_split('/\s+/', trim($shortlist->applicant_name)) ?: [])->filter()->take(2)->map(fn($v)=>mb_strtoupper(mb_substr($v,0,1)))->join('');
@endphp
<div class="csp-main" style="margin-bottom:1rem">
    <div><a class="csp-btn csp-btn--light" href="{{ route($routePrefix.'.index',['month'=>$shortlist->shortlist_month?->format('Y-m')]) }}">&larr; Back to monthly shortlist</a></div>
</div>

<div class="csp-shell">
    <main class="csp-main">
        <section class="csp-card csp-hero">
            <div class="csp-hero__top"><div style="display:flex;gap:.85rem;align-items:center"><div class="csp-avatar">{{ $initials ?: 'MUY' }}</div><div><h2>{{ $shortlist->applicant_name }}</h2><p>{{ $shortlist->application_no ?: $shortlist->candidate_key }} &middot; {{ $shortlist->district?->name }}</p></div></div><div style="text-align:right"><strong>{{ $shortlist->program_year }}</strong><p>{{ strtoupper($shortlist->source) }}</p></div></div>
            <div class="csp-tags"><span class="csp-tag">Shortlisted {{ $shortlist->shortlist_month?->format('F Y') }}</span>@if($shortlist->business_category)<span class="csp-tag">{{ $shortlist->business_category }}</span>@endif @if($shortlist->business_stage)<span class="csp-tag">{{ $shortlist->business_stage }}</span>@endif</div>
        </section>

        @foreach(['identity'=>'Applicant overview','enterprise'=>'Enterprise profile','potential'=>'Growth potential','journey'=>'Programme journey'] as $section=>$title)
        <section class="csp-card"><h3 class="csp-title">{{ $title }}</h3><div class="csp-fields">@foreach($profile[$section] ?? [] as $field)<div class="csp-field @if(!$field['available']) csp-field--missing @endif"><div class="csp-field__label">{{ $field['label'] }}</div><div class="csp-field__value">{{ $field['value'] }}</div></div>@endforeach</div></section>
        @endforeach

        <section class="csp-card">
            <h3 class="csp-title">Services already received / recorded</h3>
            <p class="csp-muted">Combined history from legacy programme databases and current MUY service modules. Status is shown separately from State Admin nominations.</p>
            @if(!empty($profile['incomplete_sources']))<div class="csp-alert" style="background:#fff7ed;border-color:#fdba74;color:#9a3412;margin:.7rem 0">Some service sources are temporarily unavailable: {{ implode(', ', $profile['incomplete_sources']) }}. Other available profile data is shown below.</div>@endif
            @forelse($profile['services'] as $service)
                <div class="csp-service"><div><strong>{{ $service['name'] ?: 'Service' }}</strong><div class="csp-muted">{{ $service['detail'] ?: 'No additional detail' }}</div>@if(!empty($service['documents']))<div class="csp-docs">@foreach($service['documents'] as $document)@php $documentUrl=$document['url']??route($routePrefix.'.documents.download',array_filter([$shortlist,$document['type']??'',$document['id']??0,'index'=>$document['index']??null],fn($value)=>$value!==null)); @endphp<a class="csp-doc" href="{{ $documentUrl }}" target="_blank" rel="noopener">&#128196; {{ $document['label'] ?? 'View document' }}</a>@endforeach</div>@endif</div><div><span class="csp-status">{{ $service['status'] ?: 'Recorded' }}</span><div class="csp-muted">{{ $service['source'] }}</div></div><div class="csp-muted">{{ $service['date'] ?: 'Date not available' }}@if($service['provider'])<br>By {{ $service['provider'] }}@endif</div></div>
            @empty<div class="csp-field csp-field--missing"><div class="csp-field__value">No delivered or recorded services were found in the connected programme databases.</div></div>@endforelse
        </section>
    </main>

    <aside class="csp-side">
        <section class="csp-card">
            <h3 class="csp-title">Nominate for services</h3>
            <p class="csp-muted">Nominations are recommendations and do not mark a service as delivered.</p>
            @if($canNominate)
            <form method="post" action="{{ route($routePrefix.'.nominations.update',$shortlist) }}">@csrf @method('PUT')
                @foreach($nominationServices as $code=>$option)
                    @php $received=(bool)($profile['received'][$code]??false); $active=$activeNominations->get($code); @endphp
                    <label class="csp-option @if($received) csp-option--received @endif"><span class="csp-option__row"><input type="checkbox" name="services[]" value="{{ $code }}" @checked($active && !$received) @disabled($received)><span><strong>{{ $option['label'] }} @if($received)<span class="csp-status">Already received</span>@elseif($active)<span class="csp-status">{{ str_replace('_',' ',ucfirst($active->status)) }}</span>@endif</strong><p>{{ $option['description'] }}</p></span></span></label>
                @endforeach
                <label class="csp-field__label" for="nomination_note">Nomination note (optional)</label><textarea id="nomination_note" name="nomination_note" maxlength="2000" class="csp-textarea" placeholder="Why is this incubatee suitable for the selected service?"></textarea>
                <button class="csp-btn" type="submit" style="width:100%;margin-top:.65rem">Save nominations</button>
            </form>
            @else
                @forelse($activeNominations as $nomination)<div class="csp-option"><strong>{{ $nominationServices[$nomination->service_code]['label'] ?? ucfirst($nomination->service_code) }}</strong><div class="csp-tags"><span class="csp-tag">{{ str_replace('_',' ',ucfirst($nomination->status)) }}</span></div><p>{{ $nomination->nomination_note }}</p></div>@empty<p class="csp-muted">No active service nominations yet.</p>@endforelse
            @endif
        </section>

        <section class="csp-card"><h3 class="csp-title">Nomination audit</h3>@forelse($shortlist->nominations as $nomination)<div class="csp-audit"><strong>{{ $nominationServices[$nomination->service_code]['label'] ?? ucfirst($nomination->service_code) }}</strong><div class="csp-muted">Current: {{ str_replace('_',' ',ucfirst($nomination->status)) }}</div>@foreach($nomination->events as $event)<div class="csp-muted" style="margin-top:.35rem">{{ ucfirst($event->action) }} by {{ $event->actor?->name ?: 'State Admin' }}<br>{{ $event->created_at?->format('d M Y, h:i A') }}</div>@endforeach</div>@empty<p class="csp-muted">No nomination activity yet.</p>@endforelse</section>

        <section class="csp-card"><h3 class="csp-title">Shortlist details</h3><div class="csp-muted">Shortlisted by</div><strong>{{ $shortlist->creator?->name }}</strong><div class="csp-muted" style="margin-top:.55rem">{{ $shortlist->created_at?->format('d M Y, h:i A') }}</div>@if($shortlist->remarks->isNotEmpty())<hr style="border:0;border-top:1px solid #e2e8f0;margin:.8rem 0">@foreach($shortlist->remarks as $remark)<div class="csp-audit"><strong>{{ $remark->author?->name }}</strong><div class="csp-muted">{{ $remark->remark }}</div></div>@endforeach @endif</section>
    </aside>
</div>
@endsection
