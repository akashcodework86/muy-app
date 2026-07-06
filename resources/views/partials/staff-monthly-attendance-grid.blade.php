@php
    $gridContext = $gridContext ?? 'admin';
    $gridRowCount = $monthlyGrid['rows']->count();
    $todayDay = now()->day;
    $isCurrentMonth = $month->isSameMonth(now());
    $showEmployeeColumn = $gridContext === 'admin';
@endphp
<div class="satt-grid-wrap">
    <table class="satt-grid">
        <thead>
            <tr>
                @if ($showEmployeeColumn)
                    <th class="satt-grid__emp">Employee</th>
                @endif
                @for ($d = 1; $d <= $monthlyGrid['days_in_month']; $d++)
                    @php
                        $dayDate = $month->copy()->day($d);
                        $isSunday = $dayDate->isSunday();
                        $isSaturday = $dayDate->isSaturday();
                        $isToday = $isCurrentMonth && $d === $todayDay;
                    @endphp
                    <th class="@if ($isToday) is-today @elseif ($isSunday || $isSaturday) is-weekend @endif"
                        title="{{ $dayDate->format('l, d M') }}">
                        {{ $d }}
                    </th>
                @endfor
                <th class="satt-grid__col-total satt-grid__col-present">Present</th>
                <th class="satt-grid__col-total satt-grid__col-absent">Absent</th>
            </tr>
        </thead>
        <tbody>
            @forelse ($monthlyGrid['rows'] as $rowIndex => $row)
                @php $user = $row['user']; @endphp
                <tr>
                    @if ($showEmployeeColumn)
                        <td class="satt-grid__emp">
                            {{ $user->name }}
                            <span class="satt-grid__emp-sub">{{ $user->district?->name ?? '—' }}</span>
                        </td>
                    @endif
                    @for ($d = 1; $d <= $monthlyGrid['days_in_month']; $d++)
                        @php
                            $status = $row['days'][$d] ?? null;
                            $dayDate = $month->copy()->day($d);
                            $isSunday = $dayDate->isSunday();
                            $isSaturday = $dayDate->isSaturday();
                            $isToday = $isCurrentMonth && $d === $todayDay;
                            $sundaySpan = $isSunday && ($rowIndex % 4 === 0)
                                ? min(4, $gridRowCount - $rowIndex)
                                : 0;
                            $absentReason = ($row['absent_reasons'][$d] ?? null);
                        @endphp
                        @if ($isSunday)
                            @if ($sundaySpan > 0)
                                <td class="satt-grid__sunday" rowspan="{{ $sundaySpan }}" title="Sunday — weekly off">
                                    <span class="satt-grid__sunday-label">Sunday Sunday</span>
                                </td>
                            @endif
                        @else
                            <td class="@if ($isToday) is-today @elseif ($isSaturday) is-weekend @endif"
                                title="{{ $user->name }} — {{ $dayDate->format('d M Y') }}">
                                <div class="satt-grid__cell">
                                    @if ($status === 'present')
                                        <span class="satt-mark satt-mark--present" title="Present">✓</span>
                                    @elseif ($status === 'absent')
                                        @if ($gridContext === 'staff')
                                            <button type="button" class="satt-mark satt-mark--absent satt-absent-action"
                                                title="{{ $absentReason ? 'Edit absence reason' : 'Add absence reason' }}"
                                                data-date="{{ $dayDate->toDateString() }}"
                                                data-date-label="{{ $dayDate->format('d M Y') }}"
                                                data-reason="{{ e($absentReason ?? '') }}">✗</button>
                                        @else
                                            <button type="button" class="satt-mark satt-mark--absent satt-absent-view"
                                                title="View absence reason"
                                                data-name="{{ e($user->name) }}"
                                                data-date-label="{{ $dayDate->format('d M Y') }}"
                                                data-reason="{{ e($absentReason ?? '') }}">✗</button>
                                        @endif
                                    @else
                                        <span class="satt-mark satt-mark--future">—</span>
                                    @endif
                                </div>
                            </td>
                        @endif
                    @endfor
                    <td class="satt-grid__col-total satt-grid__col-present">
                        <span class="satt-grid__total-val satt-grid__total-val--p">{{ $row['present_count'] }}</span>
                    </td>
                    <td class="satt-grid__col-total satt-grid__col-absent">
                        <span class="satt-grid__total-val satt-grid__total-val--a">{{ $row['absent_count'] }}</span>
                    </td>
                </tr>
            @empty
                <tr>
                    <td colspan="{{ ($showEmployeeColumn ? 1 : 0) + $monthlyGrid['days_in_month'] + 2 }}" class="satt-empty">
                        No attendance records for this month.
                    </td>
                </tr>
            @endforelse
        </tbody>
    </table>
</div>
@if (! empty($monthlyGrid['sunday_labels']))
    <div class="satt-grid-foot">
        <i class="fa-solid fa-calendar-xmark satt-grid-foot__icon"></i>
        <strong>Sundays (weekly off — not counted in attendance):</strong>
        <div class="satt-sun-chips">
            @foreach ($monthlyGrid['sunday_labels'] as $sunLabel)
                <span class="satt-sun-chip">{{ $sunLabel }}</span>
            @endforeach
        </div>
    </div>
@endif
