@extends('layouts.admin')

@section('title', 'Deliverables')
@section('heading', 'Deliverables')

@section('content')
    @php
        $queryParams = $filter->queryParams();
    @endphp

    <form method="get" action="{{ route($indexRoute) }}" style="display:flex;flex-wrap:wrap;gap:0.65rem;align-items:flex-end;margin-bottom:1rem;background:#fff;border:1px solid #e4e4e7;border-radius:10px;padding:0.75rem 0.9rem;">
        <div style="display:flex;flex-direction:column;gap:0.25rem;">
            <label for="fiscal_year_id" style="font-size:0.75rem;font-weight:600;color:#475569;">Fiscal year</label>
            <select name="fiscal_year_id" id="fiscal_year_id" style="padding:0.45rem 0.55rem;border:1px solid #d4d4d8;border-radius:8px;min-width:10rem;">
                @foreach ($fiscalYears as $fy)
                    <option value="{{ $fy->id }}" @selected((int) $fiscalYearId === (int) $fy->id)>{{ $fy->name }}</option>
                @endforeach
            </select>
        </div>
        @if ($canPickDistrict)
            <div style="display:flex;flex-direction:column;gap:0.25rem;">
                <label for="district_id" style="font-size:0.75rem;font-weight:600;color:#475569;">District</label>
                <select name="district_id" id="district_id" style="padding:0.45rem 0.55rem;border:1px solid #d4d4d8;border-radius:8px;min-width:11rem;">
                    <option value="">All in scope</option>
                    @foreach ($districts as $d)
                        <option value="{{ $d->id }}" @selected((int) ($filter->districtId ?? 0) === (int) $d->id)>{{ $d->name }}</option>
                    @endforeach
                </select>
            </div>
        @endif
        <div style="display:flex;flex-direction:column;gap:0.25rem;">
            <label for="month" style="font-size:0.75rem;font-weight:600;color:#475569;">Month</label>
            <select name="month" id="month" style="padding:0.45rem 0.55rem;border:1px solid #d4d4d8;border-radius:8px;min-width:8rem;">
                <option value="">All months</option>
                @foreach (range(1, 12) as $m)
                    <option value="{{ $m }}" @selected((int) ($filter->month ?? 0) === $m)>{{ \Carbon\Carbon::create(null, $m, 1)->format('F') }}</option>
                @endforeach
            </select>
        </div>
        <div style="display:flex;flex-direction:column;gap:0.25rem;">
            <label for="year" style="font-size:0.75rem;font-weight:600;color:#475569;">Year</label>
            <input type="number" name="year" id="year" value="{{ $filter->year }}" min="2020" max="2040" placeholder="e.g. 2025" style="padding:0.45rem 0.55rem;border:1px solid #d4d4d8;border-radius:8px;width:6.5rem;">
        </div>
        <div style="display:flex;flex-direction:column;gap:0.25rem;">
            <label for="date_from" style="font-size:0.75rem;font-weight:600;color:#475569;">From</label>
            <input type="date" name="date_from" id="date_from" value="{{ $filter->dateFrom }}" style="padding:0.45rem 0.55rem;border:1px solid #d4d4d8;border-radius:8px;">
        </div>
        <div style="display:flex;flex-direction:column;gap:0.25rem;">
            <label for="date_to" style="font-size:0.75rem;font-weight:600;color:#475569;">To</label>
            <input type="date" name="date_to" id="date_to" value="{{ $filter->dateTo }}" style="padding:0.45rem 0.55rem;border:1px solid #d4d4d8;border-radius:8px;">
        </div>
        <button type="submit" style="background:#18181b;color:#fff;border:none;padding:0.48rem 0.9rem;border-radius:8px;font-weight:600;cursor:pointer;">Apply</button>
        <a href="{{ route($indexRoute) }}" style="padding:0.48rem 0.75rem;border:1px solid #d4d4d8;border-radius:8px;text-decoration:none;color:#334155;font-size:0.88rem;">Reset</a>
        <a href="{{ route($exportRoute, $queryParams) }}" style="text-decoration:none;background:#065f46;color:#fff;padding:0.48rem 0.9rem;border-radius:8px;font-weight:600;font-size:0.88rem;">⬇ Export .xlsx</a>
        <a href="{{ route($activityGuideRoute, $queryParams) }}" style="text-decoration:none;background:#fff7ed;color:#c2410c;border:1px solid #fed7aa;padding:0.48rem 0.9rem;border-radius:8px;font-weight:700;font-size:0.88rem;">Activity guide →</a>
        @if ($showStateTargetsLink ?? false)
            <a href="{{ route('admin.targets.state', ['fiscal_year_id' => $fiscalYearId]) }}" style="font-size:0.85rem;color:#4f46e5;margin-left:auto;">Edit state targets →</a>
        @endif
    </form>

    <div style="overflow-x:auto;">
        <table style="width:100%;border-collapse:collapse;font-size:0.86rem;background:#fff;">
            <thead>
                <tr>
                    <th style="padding:0.55rem 0.45rem;border:1px solid #1c1917;background:#9a3412;color:#fff;font-weight:700;text-align:center;min-width:3.5rem;">S.N.</th>
                    <th style="padding:0.55rem 0.65rem;border:1px solid #1c1917;background:#9a3412;color:#fff;font-weight:700;text-align:left;min-width:16rem;">Indicator</th>
                    <th style="padding:0.55rem 0.65rem;border:1px solid #1c1917;background:#9a3412;color:#fff;font-weight:700;text-align:center;min-width:8rem;">Type of Indicator</th>
                    <th style="padding:0.55rem 0.65rem;border:1px solid #1c1917;background:#9a3412;color:#fff;font-weight:700;text-align:center;min-width:8rem;">Spoke/ Hub/ State</th>
                    <th
                        style="padding:0.55rem 0.65rem;border:1px solid #1c1917;background:#9a3412;color:#fff;font-weight:700;text-align:center;min-width:6rem;"
                        @if ($filter->hasExplicitDateFilter()) title="Targets are narrowed to the selected period. Real monthly staff allocations are used where they exist; otherwise FY totals are pro-rated by the fraction of the year selected." @endif
                    >Targets{!! $filter->hasExplicitDateFilter() ? '<span style="font-weight:400;font-size:0.7rem;opacity:0.85;display:block;line-height:1;margin-top:0.15rem;">(period)</span>' : '' !!}</th>
                    <th style="padding:0.55rem 0.65rem;border:1px solid #1c1917;background:#9a3412;color:#fff;font-weight:700;text-align:center;min-width:6rem;">Achievement</th>
                    <th style="padding:0.55rem 0.65rem;border:1px solid #1c1917;background:#9a3412;color:#fff;font-weight:700;text-align:center;min-width:6rem;">Achievement (%)</th>
                </tr>
            </thead>
            <tbody>
                @forelse ($rows as $row)
                    @php
                        $isHeading = in_array($row['row_type'], ['pillar', 'subcategory'], true);
                    @endphp
                    <tr @if ($isHeading) style="background:#ffedd5;font-weight:700;" @endif>
                        <td style="padding:0.45rem;border:1px solid #d4d4d8;text-align:center;">{{ $row['serial'] }}</td>
                        <td style="padding:0.45rem 0.55rem;border:1px solid #d4d4d8;">{{ $row['name'] }}</td>
                        <td style="padding:0.45rem;border:1px solid #d4d4d8;text-align:center;">{{ $isHeading ? '' : ($row['indicator_type'] ?: '—') }}</td>
                        <td style="padding:0.45rem;border:1px solid #d4d4d8;text-align:center;">{{ $isHeading ? '' : ($row['level'] ?: '—') }}</td>
                        <td style="padding:0.45rem;border:1px solid #d4d4d8;text-align:center;">{{ ! $isHeading && $row['target'] !== null ? number_format($row['target']) : '' }}</td>
                        <td style="padding:0.45rem;border:1px solid #d4d4d8;text-align:center;">
                            @if (! $isHeading && ($row['drilldown'] ?? false))
                                <button
                                    type="button"
                                    class="dlv-ach-btn"
                                    data-dlv-breakdown
                                    data-serial="{{ $row['serial'] }}"
                                    data-name="{{ $row['name'] }}"
                                    title="View achievement breakdown"
                                >{{ number_format((int) ($row['achievement'] ?? 0)) }}</button>
                            @elseif (! $isHeading && $row['achievement'] !== null)
                                <span class="dlv-ach-static">{{ number_format($row['achievement']) }}</span>
                            @endif
                        </td>
                        <td style="padding:0.45rem;border:1px solid #d4d4d8;text-align:center;">{{ ! $isHeading && $row['achievement_pct'] !== null ? $row['achievement_pct'].'%' : '' }}</td>
                    </tr>
                @empty
                    <tr>
                        <td colspan="7" style="padding:1.25rem;text-align:center;color:#64748b;border:1px solid #d4d4d8;">No data for this scope.</td>
                    </tr>
                @endforelse
            </tbody>
        </table>
    </div>

    @include('deliverables.partials.breakdown-drawer')
@endsection
