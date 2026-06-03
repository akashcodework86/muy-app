@php
    /** @var \App\Models\EapEdpSession|\App\Models\DistrictWorkshopSession|object $row */
    $firstParticipant = ($row->participantRows()[0] ?? []);
    $blockName = trim((string) ($firstParticipant['block_name'] ?? ''));
    $gpName = trim((string) ($firstParticipant['gram_panchayat_name'] ?? ''));
@endphp
{{ $blockName !== '' ? $blockName : '—' }}
