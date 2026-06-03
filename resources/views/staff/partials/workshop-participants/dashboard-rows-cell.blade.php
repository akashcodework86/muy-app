@php
    /** @var \App\Models\EapEdpSession|\App\Models\DistrictWorkshopSession|object $row */
    $participantRows = method_exists($row, 'participantRows') ? $row->participantRows() : [];
    $filledRows = collect($participantRows)->filter(fn ($p) => ! empty($p['name']))->count();
    $rowCount = count($participantRows);

    if ($rowCount === 0 && $row instanceof \App\Models\EapEdpSession) {
        $headcount = (int) ($row->attendance_male_count ?? 0) + (int) ($row->attendance_female_count ?? 0);
    } elseif ($rowCount === 0 && $row instanceof \App\Models\DistrictWorkshopSession) {
        $headcount = (int) ($row->male_participants ?? 0) + (int) ($row->female_participants ?? 0);
    } else {
        $headcount = 0;
    }
@endphp
@if ($rowCount > 0)
    <span class="ws-dash-rows ws-dash-rows--ok" title="{{ $filledRows }} named of {{ $rowCount }} rows">
        {{ $filledRows }}/{{ $rowCount }} filled
    </span>
@elseif ($headcount > 0)
    <span class="ws-dash-rows ws-dash-rows--muted" title="Headcount recorded; no row details saved">
        {{ number_format($headcount) }} rows pending
    </span>
@else
    <span class="ws-dash-rows ws-dash-rows--muted">—</span>
@endif

@once
    @push('styles')
    <style>
        .ws-dash-rows { font-size:0.8rem; font-weight:700; }
        .ws-dash-rows--ok { color:#0d9488; }
        .ws-dash-rows--muted { color:#94a3b8; font-weight:600; }
    </style>
    @endpush
@endonce
