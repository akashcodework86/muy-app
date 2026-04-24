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

    <div style="margin-top:1rem;background:#ffffff;border:1px solid #e5e7eb;border-radius:12px;padding:0.8rem;">
        <div style="display:flex;justify-content:space-between;align-items:center;gap:0.65rem;flex-wrap:wrap;margin-bottom:0.65rem;">
            <p style="margin:0;font-weight:700;color:#0f172a;">All staff details & analysis</p>
            <input
                id="staffPerfSearch"
                type="text"
                placeholder="Search staff / district / email / phone"
                style="width:min(360px,100%);padding:0.45rem 0.6rem;border:1px solid #cbd5e1;border-radius:8px;background:#fff;"
            >
        </div>

        <div style="overflow-x:auto;">
            <table id="staffPerfTable" style="width:100%;min-width:1300px;border-collapse:collapse;">
                <thead>
                    <tr style="background:#f8fafc;">
                        <th style="text-align:left;padding:0.58rem 0.7rem;border-bottom:1px solid #e5e7eb;font-size:0.7rem;color:#475569;text-transform:uppercase;">District</th>
                        <th style="text-align:left;padding:0.58rem 0.7rem;border-bottom:1px solid #e5e7eb;font-size:0.7rem;color:#475569;text-transform:uppercase;">Staff</th>
                        <th style="text-align:left;padding:0.58rem 0.7rem;border-bottom:1px solid #e5e7eb;font-size:0.7rem;color:#475569;text-transform:uppercase;">Contact</th>
                        <th style="text-align:left;padding:0.58rem 0.7rem;border-bottom:1px solid #e5e7eb;font-size:0.7rem;color:#475569;text-transform:uppercase;">Status</th>
                        <th style="text-align:left;padding:0.58rem 0.7rem;border-bottom:1px solid #e5e7eb;font-size:0.7rem;color:#475569;text-transform:uppercase;">FY CFA</th>
                        <th style="text-align:left;padding:0.58rem 0.7rem;border-bottom:1px solid #e5e7eb;font-size:0.7rem;color:#475569;text-transform:uppercase;">This month CFA</th>
                        <th style="text-align:left;padding:0.58rem 0.7rem;border-bottom:1px solid #e5e7eb;font-size:0.7rem;color:#475569;text-transform:uppercase;">Service cases (FY)</th>
                        <th style="text-align:left;padding:0.58rem 0.7rem;border-bottom:1px solid #e5e7eb;font-size:0.7rem;color:#475569;text-transform:uppercase;">Approved</th>
                        <th style="text-align:left;padding:0.58rem 0.7rem;border-bottom:1px solid #e5e7eb;font-size:0.7rem;color:#475569;text-transform:uppercase;">Pending</th>
                        <th style="text-align:left;padding:0.58rem 0.7rem;border-bottom:1px solid #e5e7eb;font-size:0.7rem;color:#475569;text-transform:uppercase;">Sent back</th>
                        <th style="text-align:left;padding:0.58rem 0.7rem;border-bottom:1px solid #e5e7eb;font-size:0.7rem;color:#475569;text-transform:uppercase;">Rejected</th>
                        <th style="text-align:left;padding:0.58rem 0.7rem;border-bottom:1px solid #e5e7eb;font-size:0.7rem;color:#475569;text-transform:uppercase;">Approval rate</th>
                        <th style="text-align:left;padding:0.58rem 0.7rem;border-bottom:1px solid #e5e7eb;font-size:0.7rem;color:#475569;text-transform:uppercase;">Perf score</th>
                        <th style="text-align:left;padding:0.58rem 0.7rem;border-bottom:1px solid #e5e7eb;font-size:0.7rem;color:#475569;text-transform:uppercase;">Future service metrics</th>
                    </tr>
                </thead>
                <tbody>
                    @forelse ($staffRows as $row)
                        <tr class="staff-perf-row">
                            <td style="padding:0.6rem 0.7rem;border-bottom:1px solid #f1f5f9;">{{ $row['district_name'] }}</td>
                            <td style="padding:0.6rem 0.7rem;border-bottom:1px solid #f1f5f9;font-weight:700;color:#0f172a;">{{ $row['staff_name'] }}</td>
                            <td style="padding:0.6rem 0.7rem;border-bottom:1px solid #f1f5f9;">
                                <div style="font-size:0.8rem;color:#334155;">{{ $row['email'] ?: '—' }}</div>
                                <div style="font-size:0.75rem;color:#64748b;">{{ $row['phone'] ?: '—' }}</div>
                            </td>
                            <td style="padding:0.6rem 0.7rem;border-bottom:1px solid #f1f5f9;">
                                @if ($row['is_active'])
                                    <span style="display:inline-flex;padding:0.15rem 0.48rem;border-radius:999px;font-size:0.7rem;font-weight:700;background:#dcfce7;color:#166534;">Active</span>
                                @else
                                    <span style="display:inline-flex;padding:0.15rem 0.48rem;border-radius:999px;font-size:0.7rem;font-weight:700;background:#e2e8f0;color:#475569;">Inactive</span>
                                @endif
                            </td>
                            <td style="padding:0.6rem 0.7rem;border-bottom:1px solid #f1f5f9;font-weight:700;color:#0f766e;">{{ number_format($row['cfa_fy']) }}</td>
                            <td style="padding:0.6rem 0.7rem;border-bottom:1px solid #f1f5f9;">{{ number_format($row['cfa_month']) }}</td>
                            <td style="padding:0.6rem 0.7rem;border-bottom:1px solid #f1f5f9;font-weight:700;color:#0369a1;">{{ number_format($row['service_total']) }}</td>
                            <td style="padding:0.6rem 0.7rem;border-bottom:1px solid #f1f5f9;">{{ number_format($row['service_approved']) }}</td>
                            <td style="padding:0.6rem 0.7rem;border-bottom:1px solid #f1f5f9;">{{ number_format($row['service_pending']) }}</td>
                            <td style="padding:0.6rem 0.7rem;border-bottom:1px solid #f1f5f9;">{{ number_format($row['service_sent_back']) }}</td>
                            <td style="padding:0.6rem 0.7rem;border-bottom:1px solid #f1f5f9;">{{ number_format($row['service_rejected']) }}</td>
                            <td style="padding:0.6rem 0.7rem;border-bottom:1px solid #f1f5f9;">
                                @if (!is_null($row['service_approval_rate']))
                                    {{ number_format($row['service_approval_rate'], 1) }}%
                                @else
                                    —
                                @endif
                            </td>
                            <td style="padding:0.6rem 0.7rem;border-bottom:1px solid #f1f5f9;font-weight:700;">{{ number_format($row['performance_score'], 1) }}</td>
                            <td style="padding:0.6rem 0.7rem;border-bottom:1px solid #f1f5f9;">
                                <span style="font-size:0.75rem;color:#64748b;">
                                    Service-wise split (GST/FSSAI/etc.) will appear here.
                                </span>
                            </td>
                        </tr>
                    @empty
                        <tr>
                            <td colspan="14" style="padding:1.2rem;text-align:center;color:#64748b;">No staff records found.</td>
                        </tr>
                    @endforelse
                </tbody>
            </table>
        </div>
    </div>

    <script>
        (function () {
            const input = document.getElementById('staffPerfSearch');
            const table = document.getElementById('staffPerfTable');
            if (!input || !table) return;
            const rows = Array.from(table.querySelectorAll('tbody .staff-perf-row'));
            input.addEventListener('input', () => {
                const q = (input.value || '').toLowerCase().trim();
                rows.forEach((row) => {
                    const show = q === '' || row.innerText.toLowerCase().includes(q);
                    row.style.display = show ? '' : 'none';
                });
            });
        })();
    </script>
@endsection

