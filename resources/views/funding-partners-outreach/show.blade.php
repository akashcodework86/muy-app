@extends('layouts.admin')

@section('title', 'Partner outreach detail')
@section('heading', 'Partner outreach detail')

@section('content')
@php use App\Support\FundingSchematicPartnersOutreachOptions as Opt; @endphp
<div style="max-width:52rem;display:flex;flex-direction:column;gap:1rem;">
    <p><a href="{{ route($dashboardRoute) }}" style="color:#7c3aed;font-weight:700;text-decoration:none;">← Back</a></p>
    <div style="background:#fff;border:1px solid #e2e8f0;border-radius:14px;padding:1.25rem;">
        <p style="margin:0 0 0.5rem;color:#64748b;font-size:0.82rem;">Outreach date: {{ $header->outreach_date?->format('d M Y') }} · Mode: {{ Opt::outreachModeLabel((string) $header->outreach_mode) }}</p>
        <h3 style="margin:0 0 1rem;">Partners in this submission</h3>
        @foreach ($batchRows as $row)
            <div style="border:1px solid #e2e8f0;border-radius:10px;padding:0.85rem;margin-bottom:0.65rem;background:#f8fafc;">
                <strong>{{ $row->partner_name }}</strong> · {{ Opt::partnerTypeLabel((string) $row->partner_type, $row->partner_type_other) }}
                <div style="font-size:0.84rem;color:#475569;margin-top:0.35rem;">
                    @if ($row->contact_name) Contact: {{ $row->contact_name }}@if($row->designation) ({{ $row->designation }})@endif · @endif
                    Phone: {{ $row->poc_phone }}
                    @if ($row->partner_link) · Link: {{ $row->partner_link }} @endif
                </div>
                @if ($row->remarks)<div style="margin-top:0.35rem;font-size:0.84rem;">{{ $row->remarks }}</div>@endif
            </div>
        @endforeach
        <p style="margin:0.75rem 0 0;font-size:0.82rem;color:#64748b;">Submitted by {{ $header->submitted_by_name }}</p>
        @if ($canDelete && $currentRole === 'state_staff')
            <form method="post" action="{{ route('spoc.funding-partners-outreach.destroy', $header) }}" style="margin-top:1rem;" onsubmit="return confirm('Delete this batch?');">
                @csrf @method('DELETE')
                <button type="submit" style="background:#fff;border:1px solid #fca5a5;color:#b91c1c;padding:0.5rem 0.85rem;border-radius:8px;font-weight:700;cursor:pointer;">Delete batch</button>
            </form>
        @endif
    </div>
</div>
@endsection
