@extends('layouts.admin')

@section('title', 'Hub applications')
@section('heading', 'Hub applications')

@section('content')
    <div style="display:flex;justify-content:space-between;align-items:flex-end;gap:0.75rem;flex-wrap:wrap;margin-bottom:0.85rem;">
        <div>
            <p style="margin:0;font-size:0.95rem;font-weight:700;color:#0f172a;">Applications source tracker</p>
            <p style="margin:0.2rem 0 0;font-size:0.82rem;color:#64748b;">
                Filter by staff and source to see exactly where applications are coming from. Use “Open link” to verify the referral link owner.
            </p>
        </div>
    </div>

    <form method="get" action="{{ route('hub.applications.index') }}" style="display:grid;grid-template-columns:repeat(auto-fit,minmax(180px,1fr));gap:0.55rem;background:#fff;border:1px solid #e5e7eb;border-radius:12px;padding:0.75rem;margin-bottom:0.75rem;">
        <div>
            <label for="q" style="display:block;font-size:0.72rem;color:#475569;font-weight:700;margin-bottom:0.25rem;">Search</label>
            <input id="q" name="q" value="{{ $filters['q'] }}" placeholder="App no / name / phone" style="width:100%;padding:0.45rem 0.55rem;border:1px solid #cbd5e1;border-radius:8px;background:#fff;">
        </div>
        <div>
            <label for="staff_id" style="display:block;font-size:0.72rem;color:#475569;font-weight:700;margin-bottom:0.25rem;">Staff</label>
            <select id="staff_id" name="staff_id" style="width:100%;padding:0.45rem 0.55rem;border:1px solid #cbd5e1;border-radius:8px;background:#fff;">
                <option value="">All staff</option>
                @foreach ($staff as $s)
                    <option value="{{ $s->id }}" @selected((int) ($filters['staff_id'] ?? 0) === (int) $s->id)>
                        {{ $s->name }}
                    </option>
                @endforeach
            </select>
        </div>
        <div>
            <label for="district_id" style="display:block;font-size:0.72rem;color:#475569;font-weight:700;margin-bottom:0.25rem;">District</label>
            <select id="district_id" name="district_id" style="width:100%;padding:0.45rem 0.55rem;border:1px solid #cbd5e1;border-radius:8px;background:#fff;">
                <option value="">All districts</option>
                @foreach ($districts as $d)
                    <option value="{{ $d->id }}" @selected((int) ($filters['district_id'] ?? 0) === (int) $d->id)>
                        {{ $d->name }}
                    </option>
                @endforeach
            </select>
        </div>
        <div>
            <label for="source" style="display:block;font-size:0.72rem;color:#475569;font-weight:700;margin-bottom:0.25rem;">Source</label>
            <select id="source" name="source" style="width:100%;padding:0.45rem 0.55rem;border:1px solid #cbd5e1;border-radius:8px;background:#fff;">
                <option value="">All sources</option>
                <option value="referral" @selected(($filters['source'] ?? '') === 'referral')>Referral</option>
                <option value="walk_in" @selected(($filters['source'] ?? '') === 'walk_in')>Walk-in</option>
                <option value="admin" @selected(($filters['source'] ?? '') === 'admin')>Admin</option>
                <option value="import" @selected(($filters['source'] ?? '') === 'import')>Import</option>
                <option value="other" @selected(($filters['source'] ?? '') === 'other')>Other</option>
            </select>
        </div>
        <div>
            <label for="from" style="display:block;font-size:0.72rem;color:#475569;font-weight:700;margin-bottom:0.25rem;">From</label>
            <input id="from" type="date" name="from" value="{{ $filters['from'] }}" style="width:100%;padding:0.45rem 0.55rem;border:1px solid #cbd5e1;border-radius:8px;background:#fff;">
        </div>
        <div>
            <label for="to" style="display:block;font-size:0.72rem;color:#475569;font-weight:700;margin-bottom:0.25rem;">To</label>
            <input id="to" type="date" name="to" value="{{ $filters['to'] }}" style="width:100%;padding:0.45rem 0.55rem;border:1px solid #cbd5e1;border-radius:8px;background:#fff;">
        </div>
        <div style="display:flex;align-items:flex-end;gap:0.45rem;">
            <button type="submit" style="padding:0.46rem 0.75rem;border:none;border-radius:8px;background:#4f46e5;color:#fff;font-weight:700;cursor:pointer;">Apply filters</button>
            <a href="{{ route('hub.applications.index') }}" style="padding:0.43rem 0.68rem;border:1px solid #cbd5e1;border-radius:8px;background:#fff;color:#0f172a;text-decoration:none;font-weight:700;">Reset</a>
        </div>
    </form>

    <div style="display:grid;grid-template-columns:repeat(auto-fit,minmax(160px,1fr));gap:0.45rem;margin-bottom:0.7rem;">
        @php
            $srcLabels = ['referral' => 'Referral', 'walk_in' => 'Walk-in', 'admin' => 'Admin', 'import' => 'Import', 'other' => 'Other', 'unknown' => 'Unknown'];
        @endphp
        @foreach ($sourceCounts as $src => $total)
            <div style="background:#f8fafc;border:1px solid #e2e8f0;border-radius:10px;padding:0.5rem 0.6rem;">
                <div style="font-size:0.68rem;color:#64748b;text-transform:uppercase;font-weight:800;letter-spacing:0.06em;">{{ $srcLabels[$src] ?? ucfirst((string) $src) }}</div>
                <div style="font-size:1rem;color:#0f172a;font-weight:800;margin-top:0.1rem;">{{ number_format((int) $total) }}</div>
            </div>
        @endforeach
    </div>

    <div style="overflow-x:auto;background:#fff;border:1px solid #e5e7eb;border-radius:12px;">
        <table style="width:100%;min-width:1200px;border-collapse:collapse;">
            <thead>
                <tr style="background:#f8fafc;">
                    <th style="text-align:left;padding:0.62rem 0.72rem;border-bottom:1px solid #e5e7eb;font-size:0.7rem;color:#475569;text-transform:uppercase;">Applied at</th>
                    <th style="text-align:left;padding:0.62rem 0.72rem;border-bottom:1px solid #e5e7eb;font-size:0.7rem;color:#475569;text-transform:uppercase;">Application</th>
                    <th style="text-align:left;padding:0.62rem 0.72rem;border-bottom:1px solid #e5e7eb;font-size:0.7rem;color:#475569;text-transform:uppercase;">Applicant</th>
                    <th style="text-align:left;padding:0.62rem 0.72rem;border-bottom:1px solid #e5e7eb;font-size:0.7rem;color:#475569;text-transform:uppercase;">District</th>
                    <th style="text-align:left;padding:0.62rem 0.72rem;border-bottom:1px solid #e5e7eb;font-size:0.7rem;color:#475569;text-transform:uppercase;">Staff / Link owner</th>
                    <th style="text-align:left;padding:0.62rem 0.72rem;border-bottom:1px solid #e5e7eb;font-size:0.7rem;color:#475569;text-transform:uppercase;">Source</th>
                    <th style="text-align:left;padding:0.62rem 0.72rem;border-bottom:1px solid #e5e7eb;font-size:0.7rem;color:#475569;text-transform:uppercase;">View</th>
                </tr>
            </thead>
            <tbody>
                @forelse ($applications as $app)
                    <tr>
                        <td style="padding:0.62rem 0.72rem;border-bottom:1px solid #f1f5f9;font-size:0.82rem;color:#334155;">
                            {{ optional($app->created_at)->setTimezone('Asia/Kolkata')->format('d M Y, h:i A') }}
                        </td>
                        <td style="padding:0.62rem 0.72rem;border-bottom:1px solid #f1f5f9;font-weight:700;color:#0f172a;">
                            {{ $app->application_no ?: ('#'.$app->id) }}
                        </td>
                        <td style="padding:0.62rem 0.72rem;border-bottom:1px solid #f1f5f9;">
                            <div style="font-weight:700;color:#0f172a;">{{ $app->applicant_name }}</div>
                            <div style="font-size:0.78rem;color:#64748b;">{{ $app->phone }}</div>
                        </td>
                        <td style="padding:0.62rem 0.72rem;border-bottom:1px solid #f1f5f9;">{{ $app->district?->name ?? '—' }}</td>
                        <td style="padding:0.62rem 0.72rem;border-bottom:1px solid #f1f5f9;">
                            @if ($app->referralUser)
                                <div style="font-weight:700;color:#0f172a;">{{ $app->referralUser->name }}</div>
                                <div style="font-size:0.77rem;color:#64748b;">ID {{ $app->referralUser->id }}</div>
                            @else
                                <span style="color:#94a3b8;">Not linked</span>
                            @endif
                        </td>
                        <td style="padding:0.62rem 0.72rem;border-bottom:1px solid #f1f5f9;">
                            <span style="display:inline-flex;padding:0.15rem 0.48rem;border-radius:999px;font-size:0.7rem;font-weight:700;background:#e0f2fe;color:#0369a1;">
                                {{ $app->source ?: 'unknown' }}
                            </span>
                        </td>
                        <td style="padding:0.62rem 0.72rem;border-bottom:1px solid #f1f5f9;">
                            <a href="{{ route('hub.batches.cfa.show', $app) }}"
                               style="display:inline-flex;align-items:center;gap:0.28rem;padding:0.25rem 0.5rem;border-radius:8px;background:#ecfeff;border:1px solid #a5f3fc;color:#0f766e;text-decoration:none;font-size:0.72rem;font-weight:700;">
                                <i class="fa-regular fa-eye" aria-hidden="true"></i>
                                View
                            </a>
                        </td>
                    </tr>
                @empty
                    <tr>
                        <td colspan="7" style="padding:1.2rem;text-align:center;color:#64748b;">No applications found for selected filters.</td>
                    </tr>
                @endforelse
            </tbody>
        </table>
    </div>

    <div style="margin-top:0.75rem;">
        {{ $applications->links() }}
    </div>
@endsection

