@once
    @push('styles')
    <style>
        .mfw-cell { background: #fffbeb; box-shadow: inset 3px 0 0 #d97706; }
        .mfw-cell--status { min-width: 7rem; }
        .mfw-cell--approver { min-width: 8rem; font-weight: 700; color: #3730a3; }
    </style>
    @endpush
@endonce
@php
    use App\Models\ServiceCase;
    $status = method_exists($row, 'misFieldStatus') ? $row->misFieldStatus() : ServiceCase::STATUS_APPROVED;
    $statusClass = match ($status) {
        ServiceCase::STATUS_PENDING_APPROVAL => 'mfw-status--pending',
        ServiceCase::STATUS_APPROVED => 'mfw-status--approved',
        ServiceCase::STATUS_SENT_BACK => 'mfw-status--sent-back',
        ServiceCase::STATUS_REJECTED => 'mfw-status--rejected',
        default => '',
    };
    $approverName = $row->misFieldSpoc?->name ?? (\App\Support\MisFieldActivityApproval::approverUser()?->name ?? '—');
@endphp
@if ($row::supportsMisFieldWorkflow())
<td class="mfw-cell mfw-cell--status">
    <span class="mfw-status {{ $statusClass }}">{{ $row->misFieldStatusLabel() }}</span>
</td>
<td class="mfw-cell mfw-cell--approver">{{ $approverName }}</td>
@endif
