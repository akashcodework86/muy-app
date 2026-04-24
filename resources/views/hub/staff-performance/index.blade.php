@extends('layouts.admin')

@section('title', 'Staff performance')
@section('heading', 'Staff performance')

@section('content')
    <div style="display:flex;justify-content:space-between;align-items:flex-end;gap:0.75rem;flex-wrap:wrap;margin-bottom:0.85rem;">
        <div>
            <p style="margin:0;font-size:0.95rem;font-weight:700;color:#0f172a;">{{ $hubName ?: 'Hub' }} — district-wise performance</p>
            <p style="margin:0.25rem 0 0;font-size:0.82rem;color:#64748b;">District staff strength and CFA performance summary.</p>
        </div>
        <form method="get" action="{{ route('hub.staff-performance.index') }}" style="display:flex;gap:0.45rem;align-items:center;">
            <label for="fy" style="font-size:0.78rem;color:#475569;font-weight:700;">Fiscal year</label>
            <select id="fy" name="fy" onchange="this.form.submit()" style="padding:0.4rem 0.55rem;border:1px solid #cbd5e1;border-radius:8px;background:#fff;">
                @foreach ($fiscalYears as $fy)
                    <option value="{{ $fy->id }}" @selected((int) $selectedFyId === (int) $fy->id)>{{ $fy->name }}</option>
                @endforeach
            </select>
        </form>
    </div>

    <div style="overflow-x:auto;background:#fff;border:1px solid #e5e7eb;border-radius:12px;">
        <table style="width:100%;border-collapse:collapse;min-width:900px;">
            <thead>
                <tr style="background:#f8fafc;">
                    <th style="text-align:left;padding:0.65rem 0.8rem;border-bottom:1px solid #e5e7eb;font-size:0.74rem;color:#475569;text-transform:uppercase;letter-spacing:0.06em;">District</th>
                    <th style="text-align:left;padding:0.65rem 0.8rem;border-bottom:1px solid #e5e7eb;font-size:0.74rem;color:#475569;text-transform:uppercase;letter-spacing:0.06em;">Total staff</th>
                    <th style="text-align:left;padding:0.65rem 0.8rem;border-bottom:1px solid #e5e7eb;font-size:0.74rem;color:#475569;text-transform:uppercase;letter-spacing:0.06em;">Active staff</th>
                    <th style="text-align:left;padding:0.65rem 0.8rem;border-bottom:1px solid #e5e7eb;font-size:0.74rem;color:#475569;text-transform:uppercase;letter-spacing:0.06em;">FY CFA</th>
                    <th style="text-align:left;padding:0.65rem 0.8rem;border-bottom:1px solid #e5e7eb;font-size:0.74rem;color:#475569;text-transform:uppercase;letter-spacing:0.06em;">This month CFA</th>
                    <th style="text-align:left;padding:0.65rem 0.8rem;border-bottom:1px solid #e5e7eb;font-size:0.74rem;color:#475569;text-transform:uppercase;letter-spacing:0.06em;">Avg per active staff</th>
                    <th style="text-align:left;padding:0.65rem 0.8rem;border-bottom:1px solid #e5e7eb;font-size:0.74rem;color:#475569;text-transform:uppercase;letter-spacing:0.06em;">Top performer</th>
                </tr>
            </thead>
            <tbody>
                @forelse ($rows as $row)
                    <tr>
                        <td style="padding:0.68rem 0.8rem;border-bottom:1px solid #f1f5f9;font-weight:700;color:#0f172a;">{{ $row['district_name'] }}</td>
                        <td style="padding:0.68rem 0.8rem;border-bottom:1px solid #f1f5f9;">{{ number_format($row['total_staff']) }}</td>
                        <td style="padding:0.68rem 0.8rem;border-bottom:1px solid #f1f5f9;">{{ number_format($row['active_staff']) }}</td>
                        <td style="padding:0.68rem 0.8rem;border-bottom:1px solid #f1f5f9;font-weight:700;color:#0f766e;">{{ number_format($row['fy_cfa']) }}</td>
                        <td style="padding:0.68rem 0.8rem;border-bottom:1px solid #f1f5f9;">{{ number_format($row['month_cfa']) }}</td>
                        <td style="padding:0.68rem 0.8rem;border-bottom:1px solid #f1f5f9;">{{ number_format($row['per_staff_avg'], 2) }}</td>
                        <td style="padding:0.68rem 0.8rem;border-bottom:1px solid #f1f5f9;">
                            @if ($row['top_staff'])
                                <span style="font-weight:700;color:#111827;">{{ $row['top_staff']['name'] }}</span>
                                <span style="color:#64748b;">({{ number_format($row['top_staff']['count']) }})</span>
                            @else
                                <span style="color:#94a3b8;">—</span>
                            @endif
                        </td>
                    </tr>
                @empty
                    <tr>
                        <td colspan="7" style="padding:1.3rem;text-align:center;color:#64748b;">No district data found for this hub.</td>
                    </tr>
                @endforelse
            </tbody>
        </table>
    </div>
@endsection

