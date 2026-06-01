@php
    $hasPaginator = method_exists($rows, 'currentPage');
    $total = $hasPaginator ? (int) $rows->total() : $rows->count();
    $perPage = $hasPaginator ? (int) $rows->perPage() : max(1, $total);
    $currentPage = $hasPaginator ? (int) $rows->currentPage() : 1;
    $serialFrom = $total > 0 ? (($currentPage - 1) * $perPage) + 1 : 0;
    $serialTo = $hasPaginator ? min($currentPage * $perPage, $total) : $total;
@endphp

<div class="p1l-toolbar">
    <p>
        @if ($total > 0)
            Showing <strong>{{ number_format($serialFrom) }}–{{ number_format($serialTo) }}</strong>
            of <strong>{{ number_format($total) }}</strong> application{{ $total !== 1 ? 's' : '' }}
            @if (! empty($filterActive))
                <span class="p1l-muted">(filtered)</span>
            @endif
        @else
            No applications to display
        @endif
    </p>
    @if ($hasPaginator && $rows->hasPages())
        <p class="p1l-muted">Page {{ $currentPage }} of {{ $rows->lastPage() }}</p>
    @endif
</div>

<div class="p1l-table-wrap">
    <table class="p1l-table">
        <thead>
            <tr>
                <th style="width:4.5rem;text-align:right;">Sr. No.</th>
                <th>App. no.</th>
                <th>Submitted</th>
                <th>Applicant</th>
                <th>Mobile</th>
                <th>District</th>
                <th>Block</th>
                <th>Village</th>
                <th>Category</th>
                <th>Stage</th>
                <th>Gender</th>
                <th>Onboard status</th>
                <th>Submitted by</th>
            </tr>
        </thead>
        <tbody>
            @forelse ($rows as $row)
                @php
                    $sr = $serialFrom + $loop->iteration - 1;
                @endphp
                <tr>
                    <td class="p1l-sr">{{ number_format($sr) }}</td>
                    <td class="p1l-appno">{{ $row->application_no ?? '—' }}</td>
                    <td style="white-space:nowrap;font-size:0.8rem;color:#64748b;">
                        @if ($row->submission_date)
                            {{ \Carbon\Carbon::parse($row->submission_date)->timezone(config('app.timezone'))->format('d M Y') }}
                            <span class="p1l-muted">{{ \Carbon\Carbon::parse($row->submission_date)->timezone(config('app.timezone'))->format('H:i') }}</span>
                        @else
                            —
                        @endif
                    </td>
                    <td class="p1l-name">{{ $row->applicant_name ?? '—' }}</td>
                    <td style="white-space:nowrap;">{{ $row->phone ?? '—' }}</td>
                    <td>{{ $row->district ?? '—' }}</td>
                    <td>{{ $row->block ?? '—' }}</td>
                    <td>{{ $row->village ?? '—' }}</td>
                    <td>
                        @if (! empty($row->category))
                            <span class="p1l-pill">{{ $row->category }}</span>
                        @else
                            —
                        @endif
                    </td>
                    <td>
                        @if (! empty($row->form_stage))
                            <span class="p1l-pill">{{ $row->form_stage }}</span>
                        @else
                            —
                        @endif
                    </td>
                    <td>{{ $row->gender ?? '—' }}</td>
                    <td>
                        @if (($row->onboard_status ?? '') === 'onboarded')
                            <span class="p1l-pill p1l-pill--onboard-yes">{{ $row->onboard_label ?? 'Onboarded' }}</span>
                        @else
                            <span class="p1l-pill p1l-pill--onboard-no">{{ $row->onboard_label ?? 'Non onboarded' }}</span>
                        @endif
                    </td>
                    <td class="p1l-muted">{{ $row->submitted_by_name ?? '—' }}</td>
                </tr>
            @empty
                <tr>
                    <td colspan="13" class="p1l-empty">{{ $emptyMessage ?? 'No applications found.' }}</td>
                </tr>
            @endforelse
        </tbody>
    </table>
</div>

@if ($hasPaginator && $rows->hasPages())
    <div class="p1l-pagination">
        {{ $rows->links() }}
    </div>
@endif
