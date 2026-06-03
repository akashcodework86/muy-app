@php
    /** @var \App\Models\EapEdpSession|\App\Models\DistrictWorkshopSession|object $row */
    $firstParticipant = ($row->participantRows()[0] ?? []);
    $gpName = trim((string) ($firstParticipant['gram_panchayat_name'] ?? ''));
@endphp
{{ $gpName !== '' ? $gpName : '—' }}
