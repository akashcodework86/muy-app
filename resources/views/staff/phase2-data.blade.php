@extends('layouts.admin')

@section('title', 'Phase 2 data')
@section('heading', 'Phase 2 data')

@section('content')
    <h2 style="margin:0 0 0.35rem;font-size:1.2rem;font-weight:800;color:#0f172a;">
        All application of Phase 2 ({{ $staff->district?->name ?? '—' }})
    </h2>
    <p style="color:#64748b;font-size:0.9rem;margin:0 0 1rem;">
        Read-only legacy Phase 2 records from <code>rbiphase2</code>, locked to your assigned district:
        <strong>{{ $staff->district?->name ?? '—' }}</strong>.
    </p>

    @if ($noDistrict ?? false)
        <div style="margin-bottom:1rem;background:#fff7ed;color:#9a3412;border:1px solid #fdba74;padding:0.75rem 0.85rem;border-radius:10px;font-size:0.88rem;">
            Your user account has no district assignment. Ask state admin to map your district first.
        </div>
    @elseif ($legacyUnavailable ?? false)
        <div style="margin-bottom:1rem;background:#fef2f2;color:#b91c1c;border:1px solid #fecaca;padding:0.75rem 0.85rem;border-radius:10px;font-size:0.88rem;">
            Legacy database is not configured. Please set <code>LEGACY_DB_DATABASE</code> and related <code>LEGACY_DB_*</code> values.
        </div>
    @elseif ($legacyMissingTables ?? false)
        <div style="margin-bottom:1rem;background:#fef2f2;color:#b91c1c;border:1px solid #fecaca;padding:0.75rem 0.85rem;border-radius:10px;font-size:0.88rem;">
            Legacy connection works, but required tables are missing (<code>rbi_applications</code>, <code>rbi_applicant_details</code>, <code>rbi_services_assigned</code>).
        </div>
    @else
        <form method="get" action="{{ route('staff.phase2-data') }}" style="display:flex;flex-wrap:wrap;align-items:flex-end;gap:0.55rem;margin-bottom:0.9rem;padding:0.8rem;border:1px solid #e2e8f0;background:#f8fafc;border-radius:10px;">
            <div style="display:flex;flex-direction:column;gap:0.3rem;min-width:16rem;">
                <label for="phase2-search" style="font-size:0.75rem;font-weight:600;color:#64748b;text-transform:uppercase;letter-spacing:0.04em;">Search</label>
                <input
                    id="phase2-search"
                    type="text"
                    name="search"
                    value="{{ request('search') }}"
                    placeholder="Application no / applicant / phone"
                    style="padding:0.52rem 0.65rem;border:1px solid #cbd5e1;border-radius:8px;font-size:0.86rem;background:#fff;"
                >
            </div>
            <div style="display:flex;flex-direction:column;gap:0.3rem;min-width:11rem;">
                <label for="phase2-category" style="font-size:0.75rem;font-weight:600;color:#64748b;text-transform:uppercase;letter-spacing:0.04em;">Category</label>
                <select id="phase2-category" name="category" style="padding:0.52rem 0.65rem;border:1px solid #cbd5e1;border-radius:8px;font-size:0.86rem;background:#fff;">
                    <option value="">All</option>
                    @foreach (($categoryOptions ?? []) as $opt)
                        <option value="{{ $opt }}" @selected(request('category') === $opt)>{{ $opt }}</option>
                    @endforeach
                </select>
            </div>
            <div style="display:flex;flex-direction:column;gap:0.3rem;min-width:11rem;">
                <label for="phase2-stage" style="font-size:0.75rem;font-weight:600;color:#64748b;text-transform:uppercase;letter-spacing:0.04em;">Form stage</label>
                <select id="phase2-stage" name="form_stage" style="padding:0.52rem 0.65rem;border:1px solid #cbd5e1;border-radius:8px;font-size:0.86rem;background:#fff;">
                    <option value="">All</option>
                    @foreach (($stageOptions ?? []) as $opt)
                        <option value="{{ $opt }}" @selected(request('form_stage') === $opt)>{{ $opt }}</option>
                    @endforeach
                </select>
            </div>
            <div style="display:flex;flex-direction:column;gap:0.3rem;min-width:11rem;">
                <label for="phase2-onboard" style="font-size:0.75rem;font-weight:600;color:#64748b;text-transform:uppercase;letter-spacing:0.04em;">Onboarding</label>
                <select id="phase2-onboard" name="onboarding_status" style="padding:0.52rem 0.65rem;border:1px solid #cbd5e1;border-radius:8px;font-size:0.86rem;background:#fff;">
                    <option value="">All</option>
                    <option value="yes" @selected(request('onboarding_status') === 'yes')>Yes</option>
                    <option value="no" @selected(request('onboarding_status') === 'no')>No</option>
                </select>
            </div>
            <button type="submit" style="padding:0.52rem 0.85rem;border:none;border-radius:8px;background:#4f46e5;color:#fff;font-weight:600;font-size:0.84rem;cursor:pointer;">
                Apply
            </button>
            @if (request()->hasAny(['search', 'category', 'form_stage', 'onboarding_status']))
                <a href="{{ route('staff.phase2-data') }}" style="padding:0.5rem 0.85rem;border:1px solid #cbd5e1;border-radius:8px;background:#fff;color:#475569;font-weight:600;font-size:0.84rem;text-decoration:none;">
                    Clear
                </a>
            @endif
            <a href="{{ route('staff.phase2-data.export', request()->query()) }}" style="padding:0.52rem 0.85rem;border:none;border-radius:8px;background:#0d9488;color:#fff;font-weight:600;font-size:0.84rem;text-decoration:none;">
                Export to Excel (CSV)
            </a>
        </form>

        <div style="margin-bottom:0.65rem;font-size:0.82rem;color:#64748b;">
            @if (method_exists($rows, 'total'))
                Showing <strong style="color:#0f172a;">{{ number_format($rows->total()) }}</strong> record(s).
            @else
                Showing <strong style="color:#0f172a;">{{ number_format($rows->count()) }}</strong> record(s).
            @endif
        </div>

        <div style="overflow-x:auto;">
            <table style="width:100%;border-collapse:collapse;background:#fff;border:1px solid #e4e4e7;border-radius:10px;font-size:0.84rem;">
                <thead>
                    <tr style="text-align:left;background:#f8fafc;">
                        <th style="padding:0.55rem 0.65rem;border-bottom:1px solid #e4e4e7;">S. No.</th>
                        <th style="padding:0.55rem 0.65rem;border-bottom:1px solid #e4e4e7;">App. no</th>
                        <th style="padding:0.55rem 0.65rem;border-bottom:1px solid #e4e4e7;">Applicant</th>
                        <th style="padding:0.55rem 0.65rem;border-bottom:1px solid #e4e4e7;">Phone / Profile</th>
                        <th style="padding:0.55rem 0.65rem;border-bottom:1px solid #e4e4e7;">Block / Village</th>
                        <th style="padding:0.55rem 0.65rem;border-bottom:1px solid #e4e4e7;">Form / Product</th>
                        <th style="padding:0.55rem 0.65rem;border-bottom:1px solid #e4e4e7;">Business</th>
                        <th style="padding:0.55rem 0.65rem;border-bottom:1px solid #e4e4e7;">Services (all)</th>
                        <th style="padding:0.55rem 0.65rem;border-bottom:1px solid #e4e4e7;">Service flags</th>
                        <th style="padding:0.55rem 0.65rem;border-bottom:1px solid #e4e4e7;">CFA</th>
                        <th style="padding:0.55rem 0.65rem;border-bottom:1px solid #e4e4e7;">Onboard</th>
                    </tr>
                </thead>
                <tbody>
                    @forelse ($rows as $row)
                        <tr>
                            <td style="padding:0.5rem 0.65rem;border-bottom:1px solid #f1f5f9;color:#64748b;font-weight:700;white-space:nowrap;">
                                {{ ((method_exists($rows, 'currentPage') ? ($rows->currentPage() - 1) * $rows->perPage() : 0) + $loop->iteration) }}
                            </td>
                            <td style="padding:0.5rem 0.65rem;border-bottom:1px solid #f1f5f9;font-weight:600;color:#3730a3;white-space:nowrap;">
                                {{ $row['application_no'] }}
                            </td>
                            <td style="padding:0.5rem 0.65rem;border-bottom:1px solid #f1f5f9;">
                                <div style="font-weight:600;color:#0f172a;">{{ $row['applicant_name'] }}</div>
                                <div style="font-size:0.75rem;color:#64748b;">{{ $row['district'] }}</div>
                            </td>
                            <td style="padding:0.5rem 0.65rem;border-bottom:1px solid #f1f5f9;color:#334155;white-space:nowrap;">
                                <div>{{ $row['phone'] }}</div>
                                <div style="font-size:0.75rem;color:#64748b;">
                                    {{ $row['gender'] }} · {{ $row['caste'] }} · SHG: {{ $row['is_shg_member'] }}
                                </div>
                            </td>
                            <td style="padding:0.5rem 0.65rem;border-bottom:1px solid #f1f5f9;color:#475569;">
                                <div>{{ $row['block'] }}</div>
                                <div style="font-size:0.75rem;color:#64748b;">{{ $row['village'] }}</div>
                            </td>
                            <td style="padding:0.5rem 0.65rem;border-bottom:1px solid #f1f5f9;color:#475569;">
                                <div>{{ $row['app_category'] }} · {{ $row['form_stage'] }}</div>
                                <div style="font-size:0.75rem;color:#64748b;">{{ $row['product'] }}</div>
                                <div style="font-size:0.75rem;color:#64748b;white-space:nowrap;">{{ $row['submission_date'] }}</div>
                            </td>
                            <td style="padding:0.5rem 0.65rem;border-bottom:1px solid #f1f5f9;color:#475569;">
                                <div>{{ $row['business_category'] }}</div>
                                <div style="font-size:0.75rem;color:#64748b;">Turnover: {{ $row['turnover_last_year'] }}</div>
                                <div style="font-size:0.75rem;color:#64748b;">Loan: {{ $row['loan_taken'] }} · Bank: {{ $row['bank_loan'] }}</div>
                            </td>
                            <td style="padding:0.5rem 0.65rem;border-bottom:1px solid #f1f5f9;color:#0f172a;min-width:15rem;">
                                {{ $row['all_services'] }}
                            </td>
                            <td style="padding:0.5rem 0.65rem;border-bottom:1px solid #f1f5f9;color:#475569;min-width:14rem;">
                                <div>Mkt: <strong>{{ $row['marketing_service'] }}</strong></div>
                                <div>Fin: <strong>{{ $row['finance_service'] }}</strong></div>
                                <div>Trn: <strong>{{ $row['training_service'] }}</strong></div>
                            </td>
                            <td style="padding:0.5rem 0.65rem;border-bottom:1px solid #f1f5f9;vertical-align:top;min-width:8.5rem;">
                                @php
                                    $legacyId = (int) ($row['legacy_application_id'] ?? 0);
                                @endphp
                                @if ($legacyId > 0)
                                    <div style="display:flex;flex-wrap:wrap;gap:0.35rem;align-items:center;">
                                        <a href="{{ route('staff.phase2-profile.show', ['legacy_application' => $legacyId]) }}" style="font-weight:700;color:#0369a1;text-decoration:none;">View</a>
                                        <span style="color:#cbd5e1;font-weight:700;">·</span>
                                        <a href="{{ route('staff.phase2-profile.show', ['legacy_application' => $legacyId, 'autopdf' => 1]) }}" style="font-weight:700;color:#7c3aed;text-decoration:none;">PDF</a>
                                    </div>
                                @else
                                    <span style="color:#94a3b8;">—</span>
                                @endif
                            </td>
                            <td style="padding:0.5rem 0.65rem;border-bottom:1px solid #f1f5f9;white-space:nowrap;">
                                @if ($row['onboarding_status'] === 'yes')
                                    <span style="display:inline-block;padding:0.18rem 0.5rem;border-radius:999px;background:#dcfce7;color:#166534;font-weight:700;font-size:0.75rem;">Yes</span>
                                @else
                                    <span style="display:inline-block;padding:0.18rem 0.5rem;border-radius:999px;background:#f1f5f9;color:#475569;font-weight:700;font-size:0.75rem;">No</span>
                                @endif
                            </td>
                        </tr>
                    @empty
                        <tr>
                            <td colspan="11" style="padding:1.2rem 0.65rem;color:#64748b;">No Phase 2 records found for your district and filters.</td>
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
