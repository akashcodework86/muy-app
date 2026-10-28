@extends('layouts.admin')

@section('title', \App\Models\BuyerSellerMeetEntry::MODULE_LABEL)
@section('heading', \App\Models\BuyerSellerMeetEntry::MODULE_LABEL)

@push('styles')
@include('branding-communication.partials.form-styles')
@endpush

@section('content')
<div class="bc-shell">
    @if (!empty($migrationMissing))<div class="bc-alert bc-alert--warning">Run <code>php artisan migrate</code>.</div>@endif
    @if (session('status'))<div class="bc-alert bc-alert--success">{{ session('status') }}</div>@endif

    <div class="bc-card">
        <div style="display:flex; justify-content:space-between; flex-wrap:wrap; gap:0.5rem; margin-bottom:1rem;">
            <h3 style="margin:0; font-size:1rem;">{{ !empty($isAdminView) ? 'All Buyer-Seller Meets' : 'Your Buyer-Seller Meets' }}</h3>
            <div style="display:flex; gap:0.5rem;">
                @if ($createRoute)<a href="{{ route($createRoute) }}" class="bc-btn">+ Log meet</a>@endif
                <a href="{{ route($exportRoute, request()->only(['q','from','to','district_id'])) }}" class="bc-btn">Export CSV</a>
            </div>
        </div>
        <form method="get" action="{{ route($dashboardRoute) }}">
            <div class="bc-filters">
                <div><label style="font-size:0.78rem;font-weight:700;">Search</label><input type="search" name="q" value="{{ $filters['q'] ?? '' }}" style="width:100%;padding:0.55rem;border:1px solid #cbd5e1;border-radius:8px;"></div>
                <div><label style="font-size:0.78rem;font-weight:700;">From</label><input type="date" name="from" value="{{ $filters['from'] ?? '' }}" style="width:100%;padding:0.55rem;border:1px solid #cbd5e1;border-radius:8px;"></div>
                <div><label style="font-size:0.78rem;font-weight:700;">To</label><input type="date" name="to" value="{{ $filters['to'] ?? '' }}" style="width:100%;padding:0.55rem;border:1px solid #cbd5e1;border-radius:8px;"></div>
                <div><label style="font-size:0.78rem;font-weight:700;">District</label>
                    <select name="district_id" style="width:100%;padding:0.55rem;border:1px solid #cbd5e1;border-radius:8px;">
                        <option value="">All</option>
                        @foreach ($districts as $d)
                            <option value="{{ $d->id }}" @selected(($filters['district_id'] ?? '') === (string) $d->id)>{{ $d->name }}</option>
                        @endforeach
                    </select>
                </div>
                <div><button type="submit" class="bc-btn">Filter</button></div>
            </div>
        </form>

        <div class="bc-table-wrap">
            <table class="bc-table">
                <thead>
                    <tr>
                        <th>#</th>
                        <th>Date</th>
                        <th>District</th>
                        <th>Title</th>
                        <th>Participants</th>
                        <th>By</th>
                        <th></th>
                    </tr>
                </thead>
                <tbody>
                @forelse ($rows as $row)
                    <tr>
                        <td>{{ $row->id }}</td>
                        <td>{{ $row->meet_date?->format('d M Y') }}</td>
                        <td>{{ $row->district?->name ?? '—' }}</td>
                        <td>{{ $row->title }}</td>
                        <td>{{ number_format($row->total_participants) }}</td>
                        <td>{{ $row->submitted_by_name }}</td>
                        <td><a href="{{ route($showRoute, $row) }}" class="bc-link">View</a></td>
                    </tr>
                @empty
                    <tr><td colspan="7">No entries yet.</td></tr>
                @endforelse
                </tbody>
            </table>
        </div>
        @if (!empty($isPaginated) && method_exists($rows, 'links'))
            <div style="margin-top:1rem;">{{ $rows->links() }}</div>
        @endif
    </div>
</div>
@endsection
