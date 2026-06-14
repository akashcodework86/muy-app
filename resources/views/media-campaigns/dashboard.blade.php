@extends('layouts.admin')

@section('title', \App\Models\MediaCampaignEntry::MODULE_LABEL)
@section('heading', \App\Models\MediaCampaignEntry::MODULE_LABEL)

@push('styles')
@include('branding-communication.partials.form-styles')
@endpush

@section('content')
<div class="bc-shell">
    @if (!empty($migrationMissing))<div class="bc-alert bc-alert--warning">Run <code>php artisan migrate</code>.</div>@endif
    @if (session('status'))<div class="bc-alert bc-alert--success">{{ session('status') }}</div>@endif

    <div class="bc-card">
        <div style="display:flex; justify-content:space-between; flex-wrap:wrap; gap:0.5rem; margin-bottom:1rem;">
            <h3 style="margin:0; font-size:1rem;">{{ !empty($isAdminView) ? 'All media campaign entries' : 'Your media campaign entries' }}</h3>
            <div style="display:flex; gap:0.5rem;">
                @if ($createRoute)<a href="{{ route($createRoute) }}" class="bc-btn">+ New entry</a>@endif
                <a href="{{ route($exportRoute, request()->only(['q','from','to','media_type'])) }}" class="bc-btn">Export CSV</a>
            </div>
        </div>
        <form method="get" action="{{ route($dashboardRoute) }}">
            <div class="bc-filters">
                <div><label style="font-size:0.78rem;font-weight:700;">Search</label><input type="search" name="q" value="{{ $filters['q'] ?? '' }}" style="width:100%;padding:0.55rem;border:1px solid #cbd5e1;border-radius:8px;"></div>
                <div><label style="font-size:0.78rem;font-weight:700;">From</label><input type="date" name="from" value="{{ $filters['from'] ?? '' }}" style="width:100%;padding:0.55rem;border:1px solid #cbd5e1;border-radius:8px;"></div>
                <div><label style="font-size:0.78rem;font-weight:700;">To</label><input type="date" name="to" value="{{ $filters['to'] ?? '' }}" style="width:100%;padding:0.55rem;border:1px solid #cbd5e1;border-radius:8px;"></div>
                <div><label style="font-size:0.78rem;font-weight:700;">Media type</label>
                    <select name="media_type" style="width:100%;padding:0.55rem;border:1px solid #cbd5e1;border-radius:8px;">
                        <option value="">All</option>
                        @foreach ($mediaTypes as $v => $l)<option value="{{ $v }}" @selected(($filters['media_type'] ?? '') === $v)>{{ $l }}</option>@endforeach
                    </select>
                </div>
                <div><button type="submit" class="bc-btn">Filter</button></div>
                <div><a href="{{ route($dashboardRoute) }}" class="bc-btn bc-btn--secondary">Reset</a></div>
            </div>
        </form>

        <div class="bc-table-wrap">
            <table class="bc-table">
                <thead>
                    <tr>
                        <th class="bc-serial">#</th>
                        <th>Date</th>
                        <th>Title</th>
                        <th>Media</th>
                        <th>Channel</th>
                        <th>Coverage</th>
                        <th>Files</th>
                        <th>By</th>
                        <th></th>
                    </tr>
                </thead>
                <tbody>
                @forelse ($rows as $row)
                    @php
                        $serial = method_exists($rows, 'firstItem') && $rows->firstItem()
                            ? (int) $rows->firstItem() + $loop->index
                            : $loop->iteration;
                        $mediaCount = $row->relationLoaded('attachments') ? $row->attachments->count() : 0;
                    @endphp
                    <tr>
                        <td class="bc-serial">{{ $serial }}</td>
                        <td>{{ $row->campaign_date?->format('d M Y') }}</td>
                        <td>{{ $row->campaign_title }}</td>
                        <td>{{ \App\Support\BrandingCommunicationOptions::mediaTypeLabel((string) $row->media_type) }}</td>
                        <td>{{ $row->channel_name }}</td>
                        <td>{{ $row->coverage_area }}</td>
                        <td>{{ $mediaCount }} media</td>
                        <td>{{ $row->submitted_by_name }}</td>
                        <td><a href="{{ route($showRoute, $row) }}" class="bc-link">View</a></td>
                    </tr>
                @empty
                    <tr><td colspan="9" style="color:#64748b;">No entries yet.</td></tr>
                @endforelse
                </tbody>
            </table>
            @if (!empty($isPaginated) && is_object($rows) && method_exists($rows, 'links'))
                <div style="padding:0.75rem;">{{ $rows->links() }}</div>
            @endif
        </div>
    </div>
</div>
@endsection
