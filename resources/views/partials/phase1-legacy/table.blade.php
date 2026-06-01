@php
    $showDistrict = $showDistrict ?? false;
    $hasPaginator = method_exists($rows, 'currentPage');
    $total = $hasPaginator ? (int) $rows->total() : $rows->count();
    $perPage = $hasPaginator ? (int) $rows->perPage() : max(1, $total);
    $currentPage = $hasPaginator ? (int) $rows->currentPage() : 1;
    $serialFrom = $total > 0 ? (($currentPage - 1) * $perPage) + 1 : 0;
    $serialTo = $hasPaginator ? min($currentPage * $perPage, $total) : $total;
    $colspan = $showDistrict ? 12 : 11;
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
                <th>Application date</th>
                <th>Applicant</th>
                <th>Mobile</th>
                @if ($showDistrict)
                    <th>District</th>
                @endif
                <th>Legacy region</th>
                <th>Village / locality</th>
                <th>Onboard status</th>
                <th>Loan / scheme</th>
                <th>Gender</th>
                <th>Education</th>
            </tr>
        </thead>
        <tbody>
            @forelse ($rows as $row)
                @php
                    $sr = $serialFrom + $loop->iteration - 1;
                    $region = strtolower(trim((string) ($row->legacy_region ?? '')));
                    $regionClass = match ($region) {
                        'garhwal' => 'p1l-pill--region-garhwal',
                        'kumaon' => 'p1l-pill--region-kumaon',
                        default => '',
                    };
                @endphp
                <tr>
                    <td class="p1l-sr">{{ number_format($sr) }}</td>
                    <td class="p1l-appno">{{ $row->application_no ?? '—' }}</td>
                    <td style="white-space:nowrap;font-size:0.8rem;color:#64748b;">
                        @if ($row->application_date)
                            {{ \Carbon\Carbon::parse($row->application_date)->timezone(config('app.timezone'))->format('d M Y') }}
                            <span class="p1l-muted">{{ \Carbon\Carbon::parse($row->application_date)->timezone(config('app.timezone'))->format('H:i') }}</span>
                        @else
                            —
                        @endif
                    </td>
                    <td class="p1l-name">{{ $row->full_name ?? '—' }}</td>
                    <td style="white-space:nowrap;">{{ $row->mobile_number ?? '—' }}</td>
                    @if ($showDistrict)
                        <td>
                            <span>{{ $row->district_name ?? '—' }}</span>
                            @if (! empty($row->father_name_legacy) && ($row->district_name ?? '') !== $row->father_name_legacy)
                                <span class="p1l-muted" style="display:block;">({{ $row->father_name_legacy }})</span>
                            @endif
                        </td>
                    @endif
                    <td>
                        @if (! empty($row->legacy_region))
                            <span class="p1l-pill {{ $regionClass }}">{{ $row->legacy_region }}</span>
                        @else
                            <span class="p1l-muted">—</span>
                        @endif
                    </td>
                    <td>{{ $row->city_name ?: '—' }}</td>
                    <td>
                        @if (($row->onboard_status ?? '') === 'onboarded')
                            <span class="p1l-pill p1l-pill--onboard-yes">{{ $row->onboard_label ?? 'Onboarded' }}</span>
                        @else
                            <span class="p1l-pill p1l-pill--onboard-no">{{ $row->onboard_label ?? 'Non onboarded' }}</span>
                        @endif
                    </td>
                    <td>
                        @if (! empty($row->application_status))
                            <span class="p1l-pill">{{ $row->application_status }}</span>
                        @else
                            <span class="p1l-muted">—</span>
                        @endif
                    </td>
                    <td>{{ $row->gender ?? '—' }}</td>
                    <td>{{ $row->education ?? '—' }}</td>
                </tr>
            @empty
                <tr>
                    <td colspan="{{ $colspan }}" class="p1l-empty">
                        {{ $emptyMessage ?? 'No Phase 1 applications found for the selected filters.' }}
                    </td>
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
