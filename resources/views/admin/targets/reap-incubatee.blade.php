@extends('layouts.admin')

@section('title', 'REAP incubatee targets (8.2)')
@section('heading', 'REAP incubatee targets (MIS 8.2)')

@section('content')
    @php
        $fyName = (string) ($progress['fiscal_year']['name'] ?? 'FY');
        $rows = (array) ($progress['rows'] ?? []);
        $grand = (array) ($progress['grand_totals'] ?? []);
        $grandBuckets = (array) ($grand['buckets'] ?? []);
        $grandTotals = (array) ($grand['totals'] ?? []);
    @endphp

    <style>
        .rit-page-top {
            display: flex;
            flex-wrap: wrap;
            gap: 0.75rem;
            align-items: flex-end;
            margin-bottom: 1rem;
        }
        .rit-note {
            background: #fff7ed;
            border: 1px solid #fed7aa;
            color: #7c2d12;
            border-radius: 10px;
            padding: 0.75rem 0.9rem;
            font-size: 0.86rem;
            line-height: 1.5;
            margin-bottom: 1rem;
        }
        .rit-table-wrap {
            overflow-x: auto;
            background: #fff;
            border: 1px solid #e4e4e7;
            border-radius: 10px;
        }
        .rit-table {
            width: 100%;
            border-collapse: collapse;
            font-size: 0.82rem;
            min-width: 56rem;
        }
        .rit-table th,
        .rit-table td {
            border: 1px solid #e4e4e7;
            padding: 0.45rem 0.55rem;
            text-align: center;
        }
        .rit-table th {
            background: #ffedd5;
            color: #7c2d12;
            font-weight: 700;
        }
        .rit-table td.rit-district {
            text-align: left;
            font-weight: 600;
            white-space: nowrap;
        }
        .rit-table td.rit-hub {
            text-align: left;
            color: #64748b;
            white-space: nowrap;
        }
        .rit-table tfoot td {
            background: #fff7ed;
            font-weight: 700;
        }
        .rit-cell--complete {
            background: #dcfce7;
            color: #166534;
        }
        .rit-cell--progress {
            background: #fff7ed;
            color: #9a3412;
        }
        .rit-cell--empty {
            color: #94a3b8;
        }
    </style>

    <form method="get" action="{{ route('admin.targets.reap-incubatee') }}" class="rit-page-top">
        <div>
            <label for="fy" style="display:block;font-size:0.78rem;font-weight:600;margin-bottom:0.2rem;">Fiscal year</label>
            <select id="fy" name="fiscal_year_id" style="padding:0.45rem 0.55rem;border:1px solid #d4d4d8;border-radius:8px;min-width:12rem;">
                @foreach ($fiscalYears as $fy)
                    <option value="{{ $fy->id }}" @selected((int) $fiscalYearId === (int) $fy->id)>{{ $fy->name }}</option>
                @endforeach
            </select>
        </div>
        <button type="submit" style="background:#18181b;color:#fff;border:none;padding:0.48rem 0.9rem;border-radius:8px;font-weight:600;cursor:pointer;">Load</button>
    </form>

    <div class="rit-note">
        <strong>Official FY plan:</strong> Farm and Non-farm incubatee support through REAP (MIS 8.2).
        Achievement counts <strong>approved</strong> REAP service cases by sector and support amount.
        District staff see the same breakdown on the REAP entry form.
    </div>

    <div class="rit-table-wrap">
        <table class="rit-table">
            <thead>
                <tr>
                    <th rowspan="2">S.No.</th>
                    <th rowspan="2">Hub</th>
                    <th rowspan="2">District</th>
                    <th colspan="2">Farm</th>
                    <th colspan="2">Non-farm</th>
                    <th rowspan="2">Total</th>
                </tr>
                <tr>
                    <th>1 Lakh</th>
                    <th>3 Lakh</th>
                    <th>1 Lakh</th>
                    <th>3 Lakh</th>
                </tr>
            </thead>
            <tbody>
                @foreach ($rows as $row)
                    @php
                        $buckets = (array) ($row['buckets'] ?? []);
                        $totals = (array) ($row['totals'] ?? []);
                    @endphp
                    <tr>
                        <td>{{ (int) ($row['serial'] ?? 0) }}</td>
                        <td class="rit-hub">{{ $row['district']['hub_name'] ?? '—' }}</td>
                        <td class="rit-district">{{ $row['district']['name'] ?? '—' }}</td>
                        @foreach (['farm_1_lakh', 'farm_3_lakh', 'non_farm_1_lakh', 'non_farm_3_lakh'] as $bucketKey)
                            @php
                                $cell = (array) ($buckets[$bucketKey] ?? []);
                                $approved = (int) ($cell['approved'] ?? 0);
                                $target = (int) ($cell['target'] ?? 0);
                                $class = $target > 0
                                    ? ($approved >= $target ? 'rit-cell--complete' : 'rit-cell--progress')
                                    : 'rit-cell--empty';
                            @endphp
                            <td class="{{ $class }}">{{ $approved }}/{{ $target }}</td>
                        @endforeach
                        <td class="rit-cell--progress">
                            {{ (int) ($totals['approved'] ?? 0) }}/{{ (int) ($totals['target'] ?? 0) }}
                        </td>
                    </tr>
                @endforeach
            </tbody>
            <tfoot>
                <tr>
                    <td colspan="3" style="text-align:right;">Grand total (achievement / target)</td>
                    @foreach (['farm_1_lakh', 'farm_3_lakh', 'non_farm_1_lakh', 'non_farm_3_lakh'] as $bucketKey)
                        @php
                            $cell = (array) ($grandBuckets[$bucketKey] ?? []);
                        @endphp
                        <td>{{ (int) ($cell['approved'] ?? 0) }}/{{ (int) ($cell['target'] ?? 0) }}</td>
                    @endforeach
                    <td>{{ (int) ($grandTotals['approved'] ?? 0) }}/{{ (int) ($grandTotals['target'] ?? 0) }}</td>
                </tr>
            </tfoot>
        </table>
    </div>
@endsection
