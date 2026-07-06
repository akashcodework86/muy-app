<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="utf-8">
    <title>Staff attendance — {{ $grid['month']->format('F Y') }}</title>
    <style>
        * { box-sizing: border-box; }
        body { font-family: DejaVu Sans, sans-serif; font-size: 9px; color: #0f172a; margin: 16px; }
        h1 { font-size: 16px; margin: 0 0 4px; color: #4f46e5; }
        .meta { margin-bottom: 12px; color: #475569; line-height: 1.5; }
        .meta strong { color: #0f172a; }
        table { width: 100%; border-collapse: collapse; }
        th, td { border: 1px solid #cbd5e1; padding: 3px 2px; text-align: center; }
        th { background: #0f766e; color: #fff; font-weight: bold; }
        th.emp, td.emp { text-align: left; min-width: 110px; padding-left: 6px; font-weight: bold; }
        th.total-present { background: #16a34a; color: #fff; font-size: 8px; min-width: 36px; }
        th.total-absent { background: #dc2626; color: #fff; font-size: 8px; min-width: 36px; }
        td.total-present { background: #dcfce7; color: #15803d; font-weight: bold; }
        td.total-absent { background: #fee2e2; color: #b91c1c; font-weight: bold; }
        td.present { background: #dcfce7; color: #166534; font-weight: bold; }
        td.absent { background: #fee2e2; color: #991b1b; font-weight: bold; }
        td.future { background: #f8fafc; color: #94a3b8; }
        td.sunday {
            background: #f1f5f9;
            color: #94a3b8;
            font-weight: bold;
            font-size: 7px;
            writing-mode: vertical-rl;
            letter-spacing: 0.08em;
            text-transform: uppercase;
            padding: 6px 2px;
        }
        .legend { margin-top: 10px; font-size: 8px; color: #64748b; }
        .legend span { display: inline-block; margin-right: 12px; }
        .legend .box { display: inline-block; width: 10px; height: 10px; border: 1px solid #cbd5e1; vertical-align: middle; margin-right: 3px; }
        .legend .box--p { background: #dcfce7; }
        .legend .box--a { background: #fee2e2; }
        .sundays { margin-top: 8px; padding: 8px 10px; background: #f8fafc; border: 1px solid #e2e8f0; border-radius: 4px; font-size: 8px; color: #475569; }
        .sundays strong { color: #334155; }
    </style>
</head>
<body>
    <h1>Staff Monthly Attendance — {{ $grid['month']->format('F Y') }}</h1>
    <div class="meta">
        Generated: <strong>{{ $generatedAt }}</strong>
        &nbsp;|&nbsp; Staff: <strong>{{ number_format($grid['total_staff']) }}</strong>
        &nbsp;|&nbsp; Present: <strong>{{ number_format($grid['total_present']) }}</strong>
        &nbsp;|&nbsp; Absent: <strong>{{ number_format($grid['total_absent']) }}</strong>
        &nbsp;|&nbsp; Rate: <strong>{{ $grid['rate_pct'] }}%</strong>
        @if ($roleFilter !== '')
            &nbsp;|&nbsp; Role: <strong>{{ $roleOptions[$roleFilter] ?? $roleFilter }}</strong>
        @endif
        @if ($hubName)
            &nbsp;|&nbsp; Hub: <strong>{{ $hubName }}</strong>
        @endif
        @if ($districtName)
            &nbsp;|&nbsp; District: <strong>{{ $districtName }}</strong>
        @endif
    </div>

    @php
        $month = $grid['month'];
        $gridRowCount = $grid['rows']->count();
    @endphp
    <table>
        <thead>
            <tr>
                <th class="emp">Employee</th>
                @for ($d = 1; $d <= $grid['days_in_month']; $d++)
                    <th>{{ $d }}</th>
                @endfor
                <th class="total-present">Present</th>
                <th class="total-absent">Absent</th>
            </tr>
        </thead>
        <tbody>
            @foreach ($grid['rows'] as $rowIndex => $row)
                <tr>
                    <td class="emp">{{ $row['user']->name }}</td>
                    @for ($d = 1; $d <= $grid['days_in_month']; $d++)
                        @php
                            $status = $row['days'][$d] ?? null;
                            $isSunday = $month->copy()->day($d)->isSunday();
                            $sundaySpan = $isSunday && ($rowIndex % 4 === 0)
                                ? min(4, $gridRowCount - $rowIndex)
                                : 0;
                        @endphp
                        @if ($isSunday)
                            @if ($sundaySpan > 0)
                                <td class="sunday" rowspan="{{ $sundaySpan }}">Sunday Sunday</td>
                            @endif
                        @else
                            <td class="{{ $status === 'present' ? 'present' : ($status === 'absent' ? 'absent' : 'future') }}">
                                @if ($status === 'present')
                                    P
                                @elseif ($status === 'absent')
                                    A
                                @else
                                    —
                                @endif
                            </td>
                        @endif
                    @endfor
                    <td class="total-present">{{ $row['present_count'] }}</td>
                    <td class="total-absent">{{ $row['absent_count'] }}</td>
                </tr>
            @endforeach
        </tbody>
    </table>

    <div class="legend">
        <span><span class="box box--p"></span> P = Present</span>
        <span><span class="box box--a"></span> A = Absent</span>
        <span>— = Future</span>
    </div>

    @if (! empty($grid['sunday_labels']))
        <div class="sundays">
            <strong>Sundays (weekly off — not counted in attendance):</strong>
            {{ implode(' · ', $grid['sunday_labels']) }}
        </div>
    @endif
</body>
</html>
