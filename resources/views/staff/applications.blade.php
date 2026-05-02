@extends('layouts.admin')

@section('title', 'Applications')
@section('heading', 'Applications')

@section('content')
    @php
        $qParams = request()->except('page');
        $tabMineUrl = route('staff.applications', array_merge($qParams, ['scope' => 'mine']));
        $tabDistrictUrl = route('staff.applications', array_merge($qParams, ['scope' => 'district']));
        $exportUrl = \Illuminate\Support\Facades\Route::has('staff.applications.export')
            ? route('staff.applications.export', request()->query())
            : null;
    @endphp

    <div style="display:flex;flex-wrap:wrap;gap:0.55rem;align-items:center;margin:0 0 0.75rem;">
        <a
            href="{{ $tabMineUrl }}"
            style="border:1px solid {{ $scope === 'mine' ? '#18181b' : '#d4d4d8' }};background:{{ $scope === 'mine' ? '#18181b' : '#fff' }};color:{{ $scope === 'mine' ? '#fff' : '#18181b' }};padding:0.45rem 0.75rem;border-radius:999px;font-size:0.82rem;font-weight:600;text-decoration:none;display:inline-block;"
        >
            My applications ({{ number_format((int) ($mineCount ?? 0)) }})
        </a>
        <a
            href="{{ $tabDistrictUrl }}"
            style="border:1px solid {{ $scope === 'district' ? '#18181b' : '#d4d4d8' }};background:{{ $scope === 'district' ? '#18181b' : '#fff' }};color:{{ $scope === 'district' ? '#fff' : '#18181b' }};padding:0.45rem 0.75rem;border-radius:999px;font-size:0.82rem;font-weight:600;text-decoration:none;display:inline-block;"
        >
            District applications ({{ number_format((int) ($districtCount ?? 0)) }})
        </a>
    </div>

    @if (! empty($forceMineNotice))
        <p style="color:#b45309;background:#fffbeb;border:1px solid #fde68a;border-radius:8px;padding:0.55rem 0.7rem;font-size:0.85rem;margin:0 0 0.65rem;">
            District view is unavailable because no district is assigned on your account. Showing your applications instead.
        </p>
    @endif

    <form method="get" action="{{ route('staff.applications') }}" style="display:flex;flex-wrap:wrap;gap:0.5rem;align-items:center;margin:0 0 1rem;">
        <input type="hidden" name="scope" value="{{ $scope }}">
        <input
            id="app-search-q"
            type="search"
            name="q"
            value="{{ $searchQuery ?? '' }}"
            placeholder="Search (app no., name, phone, any form field…)"
            style="min-width:14rem;flex:1;max-width:28rem;padding:0.45rem 0.65rem;border:1px solid #d4d4d8;border-radius:8px;font-size:0.88rem;"
            autocomplete="off"
            aria-label="Search applications"
        >
        <button type="submit" style="padding:0.45rem 0.85rem;background:#18181b;color:#fff;border:none;border-radius:8px;font-size:0.85rem;font-weight:600;cursor:pointer;">Search</button>
        @if (! empty($searchQuery))
            <a href="{{ route('staff.applications', array_filter(['scope' => $scope])) }}" style="font-size:0.85rem;color:#4f46e5;">Clear search</a>
        @endif
        @if ($exportUrl)
            <a
                href="{{ $exportUrl }}"
                style="margin-left:auto;padding:0.45rem 0.85rem;background:#0f766e;color:#fff;border-radius:8px;font-size:0.85rem;font-weight:600;text-decoration:none;white-space:nowrap;"
            >Download CSV (all CFA fields)</a>
        @endif
    </form>

    <p style="color:#64748b;font-size:0.9rem;margin:0 0 1rem;">
        @if ($scope === 'district')
            Showing Phase 3 CFA submissions from your assigned district{{ ! empty($districtName) ? ' ('.$districtName.')' : '' }} (current Phase 3 fiscal year; legacy Phase 2 mirrors excluded), including source and referral details.
        @else
            Showing only submissions that used <strong>your</strong> referral link. Newest first.
        @endif
        @if (! empty($searchQuery))
            · Filtered by search.
        @endif
    </p>

    <div style="overflow-x:auto;">
        <table style="width:100%;border-collapse:collapse;background:#fff;border:1px solid #e4e4e7;border-radius:8px;font-size:0.875rem;">
            <thead>
                <tr style="text-align:left;background:#f8fafc;">
                    <th style="padding:0.55rem 0.65rem;border-bottom:1px solid #e4e4e7;">App. no.</th>
                    <th style="padding:0.55rem 0.65rem;border-bottom:1px solid #e4e4e7;">Date (IST)</th>
                    <th style="padding:0.55rem 0.65rem;border-bottom:1px solid #e4e4e7;">Applicant</th>
                    <th style="padding:0.55rem 0.65rem;border-bottom:1px solid #e4e4e7;">Phone</th>
                    <th style="padding:0.55rem 0.65rem;border-bottom:1px solid #e4e4e7;">District</th>
                    @if ($scope === 'district')
                        <th style="padding:0.55rem 0.65rem;border-bottom:1px solid #e4e4e7;">Source</th>
                        <th style="padding:0.55rem 0.65rem;border-bottom:1px solid #e4e4e7;">Referred by</th>
                    @endif
                    <th style="padding:0.55rem 0.65rem;border-bottom:1px solid #e4e4e7;">LGD (st / dist / blk)</th>
                    <th style="padding:0.55rem 0.65rem;border-bottom:1px solid #e4e4e7;">Actions</th>
                </tr>
            </thead>
            <tbody>
                @forelse ($submissions as $row)
                    @php
                        $isOwn = (int) ($row->referral_user_id ?? 0) === (int) auth()->id();
                        $source = (string) ($row->source ?? '');
                        $sourceLabel = match ($source) {
                            'public_form' => 'Public form',
                            'staff_referral' => 'Referral link',
                            default => $source !== '' ? ucwords(str_replace('_', ' ', $source)) : 'Unknown',
                        };
                        $referredBy = $row->referralUser?->name ?? 'Public / Not referred';
                    @endphp
                    <tr>
                        <td style="padding:0.45rem 0.65rem;border-bottom:1px solid #f4f4f5;font-weight:600;">{{ $row->application_no ?? '—' }}</td>
                        <td style="padding:0.45rem 0.65rem;border-bottom:1px solid #f4f4f5;color:#52525b;white-space:nowrap;">{{ $row->created_at?->format('Y-m-d H:i') }} IST</td>
                        <td style="padding:0.45rem 0.65rem;border-bottom:1px solid #f4f4f5;">{{ $row->applicant_name }}</td>
                        <td style="padding:0.45rem 0.65rem;border-bottom:1px solid #f4f4f5;color:#52525b;">{{ $row->phone }}</td>
                        <td style="padding:0.45rem 0.65rem;border-bottom:1px solid #f4f4f5;color:#52525b;">{{ $row->district?->name ?? '—' }}</td>
                        @if ($scope === 'district')
                            <td style="padding:0.45rem 0.65rem;border-bottom:1px solid #f4f4f5;color:#52525b;">{{ $sourceLabel }}</td>
                            <td style="padding:0.45rem 0.65rem;border-bottom:1px solid #f4f4f5;color:#52525b;">{{ $referredBy }}</td>
                        @endif
                        <td style="padding:0.45rem 0.65rem;border-bottom:1px solid #f4f4f5;color:#52525b;font-size:0.8rem;white-space:nowrap;">{{ $row->lgd_state_code ?? '—' }} / {{ $row->lgd_district_code ?? '—' }} / {{ $row->lgd_block_code ?? '—' }}</td>
                        <td style="padding:0.45rem 0.65rem;border-bottom:1px solid #f4f4f5;white-space:nowrap;">
                            <a href="{{ route('staff.applications.show', $row) }}" style="display:inline-block;padding:0.3rem 0.55rem;background:#18181b;color:#fff;border-radius:6px;font-size:0.78rem;font-weight:600;text-decoration:none;margin-right:0.35rem;">View</a>
                            <a href="{{ route('staff.applications.edit', $row) }}" style="display:inline-block;padding:0.3rem 0.55rem;background:#4f46e5;color:#fff;border-radius:6px;font-size:0.78rem;font-weight:600;text-decoration:none;">Edit</a>
                            @if ($scope === 'district' && ! $isOwn)
                                <div style="font-size:0.7rem;color:#a1a1aa;margin-top:0.2rem;">Not your referral</div>
                            @endif
                        </td>
                    </tr>
                @empty
                    <tr>
                        <td colspan="{{ $scope === 'district' ? 9 : 7 }}" style="padding:1.25rem;color:#64748b;">
                            @if ($scope === 'district')
                                No applications found for your assigned district.
                            @else
                                No applications yet. Share your referral link from the dashboard.
                            @endif
                        </td>
                    </tr>
                @endforelse
            </tbody>
        </table>
    </div>

    @if ($submissions->hasPages())
        <div style="margin-top:1rem;">{{ $submissions->links() }}</div>
    @endif
@endsection
