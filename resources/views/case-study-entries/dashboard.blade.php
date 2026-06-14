@extends('layouts.admin')

@section('title', \App\Models\CaseStudyEntry::MODULE_LABEL)
@section('heading', \App\Models\CaseStudyEntry::MODULE_LABEL)

@push('styles')
@include('branding-communication.partials.form-styles')
@endpush

@section('content')
<div class="bc-shell">
    @if (!empty($migrationMissing))<div class="bc-alert bc-alert--warning">Run <code>php artisan migrate</code>.</div>@endif
    @if (session('status'))<div class="bc-alert bc-alert--success">{{ session('status') }}</div>@endif

    <div class="bc-card">
        <div style="display:flex; justify-content:space-between; flex-wrap:wrap; gap:0.5rem; margin-bottom:1rem;">
            <h3 style="margin:0; font-size:1rem;">{{ !empty($isAdminView) ? 'All case study entries' : 'Your case study entries' }}</h3>
            <div style="display:flex; gap:0.5rem;">
                @if ($createRoute)<a href="{{ route($createRoute) }}" class="bc-btn">+ New entry</a>@endif
                <a href="{{ route($exportRoute, request()->only(['q','from','to','story_type'])) }}" class="bc-btn">Export CSV</a>
            </div>
        </div>
        <form method="get" action="{{ route($dashboardRoute) }}">
            <div class="bc-filters">
                <div><label style="font-size:0.78rem;font-weight:700;">Search</label><input type="search" name="q" value="{{ $filters['q'] ?? '' }}" style="width:100%;padding:0.55rem;border:1px solid #cbd5e1;border-radius:8px;"></div>
                <div><label style="font-size:0.78rem;font-weight:700;">From</label><input type="date" name="from" value="{{ $filters['from'] ?? '' }}" style="width:100%;padding:0.55rem;border:1px solid #cbd5e1;border-radius:8px;"></div>
                <div><label style="font-size:0.78rem;font-weight:700;">To</label><input type="date" name="to" value="{{ $filters['to'] ?? '' }}" style="width:100%;padding:0.55rem;border:1px solid #cbd5e1;border-radius:8px;"></div>
                <div><label style="font-size:0.78rem;font-weight:700;">Type</label>
                    <select name="story_type" style="width:100%;padding:0.55rem;border:1px solid #cbd5e1;border-radius:8px;">
                        <option value="">All</option>
                        @foreach ($storyTypes as $v => $l)<option value="{{ $v }}" @selected(($filters['story_type'] ?? '') === $v)>{{ $l }}</option>@endforeach
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
                        <th>Type</th>
                        <th>Incubatee</th>
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
                    @endphp
                    <tr>
                        <td class="bc-serial">{{ $serial }}</td>
                        <td>{{ $row->story_date?->format('d M Y') }}</td>
                        <td>{{ $row->story_title }}</td>
                        <td>{{ \App\Support\BrandingCommunicationOptions::storyTypeLabel((string) $row->story_type) }}</td>
                        <td>{{ $row->incubatee_name }}@if($row->application_no)<br><span style="color:#64748b;font-size:0.78rem;">{{ $row->application_no }}</span>@endif</td>
                        <td>{{ $row->submitted_by_name }}</td>
                        <td><a href="{{ route($showRoute, $row) }}" class="bc-link">View</a></td>
                    </tr>
                @empty
                    <tr><td colspan="7" style="color:#64748b;">No entries yet.</td></tr>
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
