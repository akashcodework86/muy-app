@php
    $upcoming = $upcoming ?? false;
    $statusLabel = match ($meeting->status) {
        'done' => __('incubatee.meetings_status_done'),
        'cancelled' => __('incubatee.meetings_status_cancelled'),
        default => __('incubatee.meetings_status_scheduled'),
    };
    $modeLabel = $meeting->isOnline()
        ? __('incubatee.meetings_online')
        : __('incubatee.meetings_in_person');
@endphp
<div class="inc-meet-item">
    <div class="inc-meet-item__top">
        <span class="inc-meet-item__title">{{ $meeting->title }}</span>
        <span style="font-size:0.68rem;font-weight:700;text-transform:uppercase;letter-spacing:0.04em;color:#64748b">{{ $statusLabel }}</span>
    </div>
    <p class="inc-meet-item__meta">
        {{ \App\Support\Ist::datetime($meeting->scheduled_at) }}
        · {{ $modeLabel }}
        @if ($meeting->duration_minutes)
            · {{ __('incubatee.meetings_duration', ['minutes' => $meeting->duration_minutes]) }}
        @endif
        @if ($meeting->createdBy?->name)
            · {{ __('incubatee.meetings_organizer') }}: {{ $meeting->createdBy->name }}
        @endif
        @if ($meeting->venue)
            · {{ __('incubatee.meetings_venue') }}: {{ $meeting->venue }}
        @endif
    </p>
    @if ($meeting->agenda)
        <p class="inc-meet-item__agenda">{{ $meeting->agenda }}</p>
    @endif
    @if ($upcoming && $meeting->isOnline() && $meeting->meeting_link)
        <a class="inc-meet-join" href="{{ $meeting->meeting_link }}" target="_blank" rel="noopener">{{ __('incubatee.join_meeting') }}</a>
    @endif
</div>
