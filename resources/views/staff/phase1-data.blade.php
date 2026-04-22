@extends('layouts.admin')

@section('title', 'Phase 1 data')
@section('heading', 'Phase 1 data')

@section('content')
    <h2 style="margin:0 0 0.35rem;font-size:1.2rem;font-weight:800;color:#0f172a;">
        All application of Phase 1 ({{ $staff->district?->name ?? '—' }})
    </h2>
    <p style="color:#64748b;font-size:0.9rem;margin:0 0 1rem;">
        Read-only legacy Phase 1 records from <code>ukrbiin_rbi.tblapplication</code>, locked to your assigned district:
        <strong>{{ $staff->district?->name ?? '—' }}</strong> (matched via <code>City</code> column).
    </p>

    @if ($noDistrict ?? false)
        <div style="margin-bottom:1rem;background:#fff7ed;color:#9a3412;border:1px solid #fdba74;padding:0.75rem 0.85rem;border-radius:10px;font-size:0.88rem;">
            Your user account has no district assignment. Ask state admin to map your district first.
        </div>
    @elseif ($phase1Unavailable ?? false)
        <div style="margin-bottom:1rem;background:#fef2f2;color:#b91c1c;border:1px solid #fecaca;padding:0.75rem 0.85rem;border-radius:10px;font-size:0.88rem;">
            Phase 1 database is not configured. Please set <code>PHASE1_DB_DATABASE</code> and related <code>PHASE1_DB_*</code> values.
        </div>
    @elseif ($phase1MissingTables ?? false)
        <div style="margin-bottom:1rem;background:#fef2f2;color:#b91c1c;border:1px solid #fecaca;padding:0.75rem 0.85rem;border-radius:10px;font-size:0.88rem;">
            Phase 1 connection works, but required table is missing (<code>tblapplication</code>).
        </div>
    @else
        <form method="get" action="{{ route('staff.phase1-data') }}" style="display:flex;flex-wrap:wrap;align-items:flex-end;gap:0.55rem;margin-bottom:0.9rem;padding:0.8rem;border:1px solid #e2e8f0;background:#f8fafc;border-radius:10px;">
            <div style="display:flex;flex-direction:column;gap:0.3rem;min-width:16rem;">
                <label for="phase1-search" style="font-size:0.75rem;font-weight:600;color:#64748b;text-transform:uppercase;letter-spacing:0.04em;">Search</label>
                <input
                    id="phase1-search"
                    type="text"
                    name="search"
                    value="{{ request('search') }}"
                    placeholder="Application no / applicant / phone"
                    style="padding:0.52rem 0.65rem;border:1px solid #cbd5e1;border-radius:8px;font-size:0.86rem;background:#fff;"
                >
            </div>
            <button type="submit" style="padding:0.52rem 0.85rem;border:none;border-radius:8px;background:#4f46e5;color:#fff;font-weight:600;font-size:0.84rem;cursor:pointer;">
                Apply
            </button>
            @if (request()->has('search'))
                <a href="{{ route('staff.phase1-data') }}" style="padding:0.5rem 0.85rem;border:1px solid #cbd5e1;border-radius:8px;background:#fff;color:#475569;font-weight:600;font-size:0.84rem;text-decoration:none;">
                    Clear
                </a>
            @endif
        </form>

        <div style="margin-bottom:0.65rem;font-size:0.82rem;color:#64748b;">
            Showing <strong style="color:#0f172a;">{{ number_format(method_exists($rows, 'total') ? $rows->total() : $rows->count()) }}</strong> record(s).
        </div>

        <div style="overflow-x:auto;">
            <table style="width:100%;border-collapse:collapse;background:#fff;border:1px solid #e4e4e7;border-radius:10px;font-size:0.84rem;">
                <thead>
                    <tr style="text-align:left;background:#f8fafc;">
                        <th style="padding:0.55rem 0.65rem;border-bottom:1px solid #e4e4e7;">S. No.</th>
                        <th style="padding:0.55rem 0.65rem;border-bottom:1px solid #e4e4e7;">App. no</th>
                        <th style="padding:0.55rem 0.65rem;border-bottom:1px solid #e4e4e7;">Applicant</th>
                        <th style="padding:0.55rem 0.65rem;border-bottom:1px solid #e4e4e7;">Mobile</th>
                        <th style="padding:0.55rem 0.65rem;border-bottom:1px solid #e4e4e7;">Hub</th>
                        <th style="padding:0.55rem 0.65rem;border-bottom:1px solid #e4e4e7;">City</th>
                        <th style="padding:0.55rem 0.65rem;border-bottom:1px solid #e4e4e7;">Status</th>
                        <th style="padding:0.55rem 0.65rem;border-bottom:1px solid #e4e4e7;">Application date</th>
                    </tr>
                </thead>
                <tbody>
                    @forelse ($rows as $row)
                        <tr>
                            <td style="padding:0.5rem 0.65rem;border-bottom:1px solid #f1f5f9;color:#64748b;font-weight:700;white-space:nowrap;">
                                {{ ((method_exists($rows, 'currentPage') ? ($rows->currentPage() - 1) * $rows->perPage() : 0) + $loop->iteration) }}
                            </td>
                            <td style="padding:0.5rem 0.65rem;border-bottom:1px solid #f1f5f9;font-weight:600;color:#3730a3;white-space:nowrap;">
                                {{ $row->application_no ?? '—' }}
                            </td>
                            <td style="padding:0.5rem 0.65rem;border-bottom:1px solid #f1f5f9;color:#0f172a;">
                                {{ $row->full_name ?? '—' }}
                            </td>
                            <td style="padding:0.5rem 0.65rem;border-bottom:1px solid #f1f5f9;color:#334155;white-space:nowrap;">
                                {{ $row->mobile_number ?? '—' }}
                            </td>
                            <td style="padding:0.5rem 0.65rem;border-bottom:1px solid #f1f5f9;color:#475569;">
                                {{ $row->hub_name ?? '—' }}
                            </td>
                            <td style="padding:0.5rem 0.65rem;border-bottom:1px solid #f1f5f9;color:#475569;">
                                {{ $row->city_name ?? '—' }}
                            </td>
                            <td style="padding:0.5rem 0.65rem;border-bottom:1px solid #f1f5f9;color:#475569;">
                                {{ $row->application_status ?? '—' }}
                            </td>
                            <td style="padding:0.5rem 0.65rem;border-bottom:1px solid #f1f5f9;color:#475569;white-space:nowrap;">
                                @if ($row->application_date)
                                    {{ \Carbon\Carbon::parse($row->application_date)->timezone(config('app.timezone'))->format('d M Y H:i') }}
                                @else
                                    —
                                @endif
                            </td>
                        </tr>
                    @empty
                        <tr>
                            <td colspan="8" style="padding:1.2rem 0.65rem;color:#64748b;">No Phase 1 records found for your district and filters.</td>
                        </tr>
                    @endforelse
                </tbody>
            </table>
        </div>

        @if (method_exists($rows, 'hasPages') && $rows->hasPages())
            <div style="margin-top:1rem;display:flex;justify-content:space-between;align-items:center;gap:0.75rem;">
                <a
                    href="{{ $rows->onFirstPage() ? '#' : $rows->previousPageUrl() }}"
                    style="padding:0.45rem 0.8rem;border:1px solid #cbd5e1;border-radius:8px;background:{{ $rows->onFirstPage() ? '#f8fafc' : '#fff' }};color:{{ $rows->onFirstPage() ? '#94a3b8' : '#334155' }};text-decoration:none;font-weight:600;pointer-events:{{ $rows->onFirstPage() ? 'none' : 'auto' }};"
                >
                    ← Previous
                </a>
                <span style="font-size:0.84rem;color:#64748b;">
                    Page {{ $rows->currentPage() }} of {{ $rows->lastPage() }}
                </span>
                <a
                    href="{{ $rows->hasMorePages() ? $rows->nextPageUrl() : '#' }}"
                    style="padding:0.45rem 0.8rem;border:1px solid #cbd5e1;border-radius:8px;background:{{ $rows->hasMorePages() ? '#fff' : '#f8fafc' }};color:{{ $rows->hasMorePages() ? '#334155' : '#94a3b8' }};text-decoration:none;font-weight:600;pointer-events:{{ $rows->hasMorePages() ? 'auto' : 'none' }};"
                >
                    Next →
                </a>
            </div>
        @endif
    @endif
@endsection
