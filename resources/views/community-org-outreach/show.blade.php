@extends('layouts.admin')

@section('title', 'Outreach visit #'.$row->id)
@section('heading', 'Community organization outreach')

@push('styles')
<style>
    .coo-shell { display:flex; flex-direction:column; gap:1.25rem; max-width:52rem; }
    .coo-card { background:#fff; border:1px solid #e2e8f0; border-radius:14px; padding:1.25rem 1.35rem; }
    .coo-dl { display:grid; grid-template-columns:minmax(9rem, 34%) 1fr; gap:0.55rem 1rem; font-size:0.88rem; }
    .coo-dl dt { color:#64748b; font-weight:600; margin:0; }
    .coo-dl dd { margin:0; color:#0f172a; }
    .coo-actions { display:flex; flex-wrap:wrap; gap:0.65rem; align-items:center; }
    .coo-link { color:#0d9488; font-weight:700; text-decoration:none; font-size:0.88rem; }
    .coo-btn--delete { border:1px solid #fecaca; background:#fff; color:#b91c1c; padding:0.45rem 0.8rem; font-size:0.84rem; font-weight:700; border-radius:8px; cursor:pointer; }
    .coo-files { display:flex; flex-wrap:wrap; gap:0.5rem; margin:0; padding:0; list-style:none; }
    .coo-files a { display:inline-flex; align-items:center; padding:0.35rem 0.65rem; border-radius:8px; background:#f0fdfa; border:1px solid #99f6e4; color:#115e59; font-size:0.8rem; font-weight:700; text-decoration:none; }
    .coo-photo-grid { display:grid; grid-template-columns:repeat(auto-fill, minmax(120px, 1fr)); gap:0.65rem; margin-top:0.35rem; }
    .coo-photo-grid a { display:block; border-radius:10px; overflow:hidden; border:1px solid #e2e8f0; }
    .coo-photo-grid img { display:block; width:100%; height:110px; object-fit:cover; }
</style>
@endpush

@section('content')
<div class="coo-shell">
    <div class="coo-card">
        <dl class="coo-dl">
            <dt>Visit date</dt><dd>{{ $row->visit_date?->format('d M Y') }}</dd>
            <dt>Hub</dt><dd>{{ $row->hub_name }}</dd>
            <dt>District</dt><dd>{{ $row->district_name }}</dd>
            <dt>Organisation</dt><dd>{{ $row->organization_name }}</dd>
            <dt>Organisation type</dt><dd>{{ \App\Support\CommunityOrganizationOutreachOptions::organizationTypeDisplay((string) $row->organization_type, $row->organization_type_other) }}</dd>
            <dt>Person met</dt><dd>{{ $row->person_met_name }}</dd>
            <dt>Designation</dt><dd>{{ $row->person_met_designation ?: '—' }}</dd>
            <dt>POC</dt><dd>{{ $row->poc_name }}</dd>
            <dt>POC phone</dt><dd>{{ $row->poc_phone }}</dd>
            <dt>POC email</dt><dd>{{ $row->poc_email ?: '—' }}</dd>
            <dt>Purpose</dt><dd>{{ \App\Support\CommunityOrganizationOutreachOptions::labelFor('purpose', (string) $row->purpose) }}</dd>
            <dt>Meeting mode</dt><dd>{{ \App\Support\CommunityOrganizationOutreachOptions::labelFor('meeting_mode', (string) $row->meeting_mode) }}</dd>
            <dt>Remark</dt><dd>{{ $row->remarks ?: '—' }}</dd>
            <dt>Documents</dt>
            <dd>
                @php $documents = array_values((array) $row->documents_json); @endphp
                @if ($documents === [])
                    —
                @else
                    <ul class="coo-files">
                        @foreach ($documents as $index => $doc)
                            @if (is_array($doc))
                                <li>
                                    <a href="{{ route($documentRoute, [$row, 'index' => $index]) }}">
                                        {{ $doc['original_name'] ?? ('Document '.($index + 1)) }}
                                    </a>
                                </li>
                            @endif
                        @endforeach
                    </ul>
                @endif
            </dd>
            <dt>Photos</dt>
            <dd>
                @php $photos = array_values((array) $row->photos_json); @endphp
                @if ($photos === [])
                    —
                @else
                    <div class="coo-photo-grid">
                        @foreach ($photos as $index => $photo)
                            @if (is_array($photo))
                                <a href="{{ route($photoRoute, [$row, 'index' => $index, 'inline' => 1]) }}" target="_blank" rel="noopener">
                                    <img src="{{ route($photoRoute, [$row, 'index' => $index, 'inline' => 1]) }}" alt="Visit photo {{ $index + 1 }}">
                                </a>
                            @endif
                        @endforeach
                    </div>
                @endif
            </dd>
            <dt>Submitted by</dt><dd>{{ $row->submitted_by_name }} · {{ $row->created_at?->format('d M Y H:i') }}</dd>
        </dl>

        <div class="coo-actions" style="margin-top:1.25rem;">
            <a href="{{ route($dashboardRoute) }}" class="coo-link">← Back to dashboard</a>
            @if (!empty($canDelete))
                <form method="post" action="{{ route($destroyRoute, $row) }}" onsubmit="return confirm('Delete this outreach visit entry?');">
                    @csrf
                    @method('DELETE')
                    <button type="submit" class="coo-btn--delete">Delete entry</button>
                </form>
            @endif
        </div>
    </div>
</div>
@endsection
