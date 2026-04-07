@extends('layouts.admin')

@section('title', 'CFA — Phase 2 legacy')
@section('heading', 'CFA — Phase 2 legacy')

@section('content')
    <div style="margin-bottom:1rem;padding:0.75rem 1rem;background:#fffbeb;border:1px solid #fcd34d;border-radius:8px;font-size:0.9rem;color:#92400e;">
        <strong>Read-only.</strong> This list reads the legacy Phase 2 database (<code>rbi_applications</code> / <code>rbi_applicant_details</code>), not MUY <code>cfa_submissions</code>. FY filter uses the same window as Phase 2 targets (<code>submission_date</code> between fiscal year start and end).
    </div>

    @if ($legacyUnavailable ?? false)
        <p style="color:#b45309;">Legacy database is not configured. Set <code>LEGACY_DB_DATABASE</code> (and if needed <code>LEGACY_DB_*</code>) in <code>.env</code> — see <code>config/database.php</code> connection <code>legacy</code>.</p>
    @elseif ($legacyMissingTables ?? false)
        <p style="color:#b45309;">Legacy connection works but required tables were not found (<code>rbi_applications</code>, <code>rbi_applicant_details</code>).</p>
    @elseif ($fiscalYears->isEmpty())
        <p>No fiscal year configured. Add FY rows in the database first.</p>
    @else
        <form method="get" action="{{ route('admin.phase2-cfa.index') }}" style="margin-bottom:1rem; display:flex; flex-wrap:wrap; gap:0.5rem; align-items:center;">
            <label for="p2cfa-fy" style="font-size:0.9rem; font-weight:500;">Fiscal year</label>
            <select id="p2cfa-fy" name="fiscal_year_id" onchange="this.form.submit()" style="padding:0.4rem 0.5rem; border-radius:6px; border:1px solid #d4d4d8; min-width:12rem;">
                @foreach ($fiscalYears as $fy)
                    <option value="{{ $fy->id }}" @selected((int) $fiscalYearId === (int) $fy->id)>{{ $fy->name }}</option>
                @endforeach
            </select>
        </form>

        @if ($fiscalYear)
            <p style="color:#64748b;font-size:0.9rem;margin:0 0 1rem;">
                Window: {{ $fiscalYear->starts_on?->format('Y-m-d') }} — {{ $fiscalYear->ends_on?->format('Y-m-d') }}. Newest first.
            </p>
        @endif

        <div style="overflow-x:auto;">
            <table style="width:100%;border-collapse:collapse;background:#fff;border:1px solid #e4e4e7;border-radius:8px;font-size:0.875rem;">
                <thead>
                    <tr style="text-align:left;background:#f8fafc;">
                        <th style="padding:0.55rem 0.65rem;border-bottom:1px solid #e4e4e7;">App. no.</th>
                        <th style="padding:0.55rem 0.65rem;border-bottom:1px solid #e4e4e7;">Submitted</th>
                        <th style="padding:0.55rem 0.65rem;border-bottom:1px solid #e4e4e7;">Applicant</th>
                        <th style="padding:0.55rem 0.65rem;border-bottom:1px solid #e4e4e7;">Phone</th>
                        <th style="padding:0.55rem 0.65rem;border-bottom:1px solid #e4e4e7;">District</th>
                        <th style="padding:0.55rem 0.65rem;border-bottom:1px solid #e4e4e7;">Submitted by</th>
                        <th style="padding:0.55rem 0.65rem;border-bottom:1px solid #e4e4e7;color:#64748b;font-weight:500;">Legacy ID</th>
                    </tr>
                </thead>
                <tbody>
                    @forelse ($rows as $row)
                        <tr>
                            <td style="padding:0.45rem 0.65rem;border-bottom:1px solid #f4f4f5;font-weight:600;">{{ $row->application_no ?? '—' }}</td>
                            <td style="padding:0.45rem 0.65rem;border-bottom:1px solid #f4f4f5;color:#52525b;white-space:nowrap;">
                                @if ($row->submission_date)
                                    {{ \Carbon\Carbon::parse($row->submission_date)->timezone(config('app.timezone'))->format('Y-m-d H:i') }}
                                @else
                                    —
                                @endif
                            </td>
                            <td style="padding:0.45rem 0.65rem;border-bottom:1px solid #f4f4f5;">{{ $row->applicant_name ?? '—' }}</td>
                            <td style="padding:0.45rem 0.65rem;border-bottom:1px solid #f4f4f5;color:#52525b;">{{ $row->phone ?? '—' }}</td>
                            <td style="padding:0.45rem 0.65rem;border-bottom:1px solid #f4f4f5;color:#52525b;">{{ $row->district ?? '—' }}</td>
                            <td style="padding:0.45rem 0.65rem;border-bottom:1px solid #f4f4f5;color:#52525b;">{{ $row->submitted_by_name ?? '—' }}</td>
                            <td style="padding:0.45rem 0.65rem;border-bottom:1px solid #f4f4f5;color:#94a3b8;font-size:0.8rem;">{{ $row->legacy_id }}</td>
                        </tr>
                    @empty
                        <tr>
                            <td colspan="7" style="padding:1.25rem;color:#64748b;">No applications in this FY window.</td>
                        </tr>
                    @endforelse
                </tbody>
            </table>
        </div>

        @if ($rows->hasPages())
            <div style="margin-top:1rem;">{{ $rows->links() }}</div>
        @endif
    @endif
@endsection
