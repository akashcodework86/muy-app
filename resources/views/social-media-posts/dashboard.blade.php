@extends('layouts.admin')

@section('title', 'Social media posts')
@section('heading', 'Social media posts')

@push('styles')
@include('social-media-posts.partials.preview-styles')
<style>
    .smp-shell { display:flex; flex-direction:column; gap:1.25rem; }
    .smp-alert { border-radius:12px; padding:0.85rem 1rem; font-size:0.88rem; }
    .smp-alert--warning { background:#fffbeb; border:1px solid #fde68a; color:#92400e; }
    .smp-alert--success { background:#f0fdf4; border:1px solid #86efac; color:#166534; }
    .smp-card { background:#fff; border:1px solid #e2e8f0; border-radius:14px; padding:1.25rem 1.35rem; }
    .smp-head { display:flex; flex-wrap:wrap; justify-content:space-between; align-items:center; gap:0.75rem; margin-bottom:1rem; }
    .smp-head__title { margin:0; font-size:1rem; font-weight:700; color:#0f172a; }
    .smp-head__meta { margin:0.2rem 0 0; font-size:0.82rem; color:#64748b; font-weight:600; }
    .smp-head__count { display:inline-flex; align-items:center; padding:0.2rem 0.55rem; border-radius:999px; background:#eef2ff; border:1px solid #c7d2fe; color:#3730a3; font-size:0.78rem; font-weight:800; margin-left:0.35rem; }
    .smp-table__num { width:3rem; text-align:center; color:#64748b; font-weight:700; font-variant-numeric: tabular-nums; }
    .smp-table__num-head { text-align:center; }
    .smp-table-foot { padding:0.65rem 0.75rem; font-size:0.8rem; color:#64748b; border-top:1px solid #e2e8f0; background:#f8fafc; }
    .smp-filters { display:grid; grid-template-columns:repeat(auto-fit, minmax(170px, 1fr)); gap:0.85rem; align-items:end; }
    .smp-filter-field { display:flex; flex-direction:column; gap:0.35rem; }
    .smp-filter-field label { font-size:0.78rem; font-weight:700; color:#0f172a; }
    .smp-filter-field input { border:1px solid #cbd5e1; border-radius:8px; padding:0.55rem 0.65rem; font-size:0.88rem; }
    .smp-filter-actions { display:flex; flex-wrap:wrap; gap:0.55rem; }
    .smp-btn { border:none; border-radius:8px; background:#4f46e5; color:#fff; padding:0.58rem 0.9rem; font-weight:700; cursor:pointer; font-size:0.88rem; text-decoration:none; display:inline-flex; align-items:center; }
    .smp-btn--secondary { background:#fff; color:#334155; border:1px solid #cbd5e1; }
    .smp-btn--export { background:#065f46; }
    .smp-table-wrap { background:#fff; border:1px solid #e2e8f0; border-radius:14px; overflow:auto; }
    .smp-table { width:100%; border-collapse:collapse; font-size:0.84rem; }
    .smp-table th, .smp-table td { text-align:left; padding:0.7rem 0.75rem; border-bottom:1px solid #e2e8f0; vertical-align:top; }
    .smp-table tbody tr:last-child td { border-bottom:none; }
    .smp-table thead tr { background:#f8fafc; }
    .smp-url { word-break:break-all; color:#4f46e5; font-weight:600; }
    .smp-desc { color:#64748b; font-size:0.8rem; max-width:20rem; }
    .smp-empty { padding:1rem; color:#64748b; }
    .smp-row-actions { display:flex; flex-wrap:wrap; gap:0.45rem; align-items:center; }
    .smp-btn--delete {
        border:1px solid #fecaca; background:#fff; color:#b91c1c;
        padding:0.4rem 0.7rem; font-size:0.8rem; font-weight:700; border-radius:8px; cursor:pointer;
        font-family:inherit;
    }
    .smp-btn--delete:hover { background:#fef2f2; }
    .smp-delete-inline { display:inline; margin:0; }
    .smp-platforms { display:flex; flex-wrap:wrap; gap:0.35rem; max-width:14rem; }
    .smp-platforms__chip {
        display:inline-flex; align-items:center;
        padding:0.18rem 0.45rem; border-radius:999px;
        background:#eef2ff; border:1px solid #c7d2fe; color:#3730a3;
        font-size:0.72rem; font-weight:700; white-space:nowrap;
    }
</style>
@endpush

@section('content')
<div class="smp-shell">
    @if (!empty($migrationMissing))
        <div class="smp-alert smp-alert--warning">
            <strong>Table not found.</strong> Run <code>php artisan migrate</code> first.
        </div>
    @endif

    @if (session('status'))
        <div class="smp-alert smp-alert--success">{{ session('status') }}</div>
    @endif

    @php
        $totalCount = (!empty($isPaginated) && is_object($rows) && method_exists($rows, 'total'))
            ? (int) $rows->total()
            : (int) (is_countable($rows) ? count($rows) : 0);
    @endphp

    <div class="smp-card">
        <div class="smp-head">
            <div>
                <h3 class="smp-head__title">
                    @if (!empty($isAdminView))
                        All entries (state)
                    @else
                        My entries
                    @endif
                    <span class="smp-head__count">{{ number_format($totalCount) }} total</span>
                </h3>
                @if (!empty($isPaginated) && is_object($rows) && method_exists($rows, 'firstItem') && $totalCount > 0)
                    <p class="smp-head__meta">
                        Showing {{ number_format((int) $rows->firstItem()) }}–{{ number_format((int) $rows->lastItem()) }} of {{ number_format($totalCount) }}
                    </p>
                @elseif ($totalCount === 0)
                    <p class="smp-head__meta">No entries match the current filters.</p>
                @endif
            </div>
            <div style="display:flex; flex-wrap:wrap; gap:0.5rem;">
                @if ($createRoute)
                    <a href="{{ route($createRoute) }}" class="smp-btn">+ Log new post</a>
                @endif
                @if (!empty($isPaginated))
                    <a href="{{ route($exportRoute, request()->query()) }}" class="smp-btn smp-btn--export">Export CSV</a>
                @endif
            </div>
        </div>

        @if (!empty($isPaginated))
        <form method="get" action="{{ route($dashboardRoute) }}" class="smp-filters" style="margin-bottom:1rem;">
            <div class="smp-filter-field">
                <label>Search</label>
                <input type="text" name="q" value="{{ $filters['q'] ?? '' }}" placeholder="URL, description, name">
            </div>
            <div class="smp-filter-field">
                <label>From</label>
                <input type="date" name="from" value="{{ $filters['from'] ?? '' }}">
            </div>
            <div class="smp-filter-field">
                <label>To</label>
                <input type="date" name="to" value="{{ $filters['to'] ?? '' }}">
            </div>
            <div class="smp-filter-actions">
                <button type="submit" class="smp-btn">Filter</button>
                <a href="{{ route($dashboardRoute) }}" class="smp-btn smp-btn--secondary">Clear</a>
            </div>
        </form>
        @endif
    </div>

    <div class="smp-table-wrap">
        <table class="smp-table">
            <thead>
                <tr>
                    <th class="smp-table__num-head">#</th>
                    <th>Preview</th>
                    <th>Date</th>
                    <th>URL</th>
                    <th>Platforms</th>
                    <th>Description</th>
                    @if (!empty($isAdminView))
                        <th>Submitted by</th>
                    @endif
                    <th></th>
                </tr>
            </thead>
            <tbody>
                @forelse ($rows as $row)
                    @php
                        $rowNumber = (!empty($isPaginated) && is_object($rows) && method_exists($rows, 'firstItem') && $rows->firstItem() !== null)
                            ? (int) $rows->firstItem() + $loop->index
                            : $loop->iteration;
                    @endphp
                    <tr>
                        <td class="smp-table__num">{{ $rowNumber }}</td>
                        <td>
                            @include('social-media-posts.partials.list-thumbnail', ['row' => $row])
                        </td>
                        <td>{{ $row->posted_on?->format('d M Y') }}</td>
                        <td>
                            <a class="smp-url" href="{{ $row->post_url }}" target="_blank" rel="noopener noreferrer">{{ $row->post_url }}</a>
                        </td>
                        <td>@include('social-media-posts.partials.platform-badges', ['row' => $row])</td>
                        <td><span class="smp-desc">{{ $row->description ?: '—' }}</span></td>
                        @if (!empty($isAdminView))
                            <td>{{ $row->submitted_by_name }}</td>
                        @endif
                        <td>
                            <div class="smp-row-actions">
                                <a href="{{ route($showRoute, $row) }}" class="smp-btn smp-btn--secondary" style="padding:0.4rem 0.7rem; font-size:0.8rem;">View</a>
                                @if (\App\Support\SocialMediaPostAccess::canDelete(auth()->user(), $row))
                                    @php
                                        $destroyRoute = auth()->user()->role === 'state_admin'
                                            ? 'admin.social-media-posts.destroy'
                                            : 'spoc.social-media-posts.destroy';
                                    @endphp
                                    <form
                                        class="smp-delete-inline"
                                        method="post"
                                        action="{{ route($destroyRoute, $row) }}"
                                        onsubmit="return confirm('Delete this social media post permanently?');"
                                    >
                                        @csrf
                                        @method('delete')
                                        <button type="submit" class="smp-btn--delete">Delete</button>
                                    </form>
                                @endif
                            </div>
                        </td>
                    </tr>
                @empty
                    <tr>
                        <td colspan="{{ !empty($isAdminView) ? 8 : 7 }}" class="smp-empty">No entries yet.</td>
                    </tr>
                @endforelse
            </tbody>
        </table>
        @if ($totalCount > 0)
            <div class="smp-table-foot">
                Total entries: <strong>{{ number_format($totalCount) }}</strong>
                @if (!empty($isPaginated) && is_object($rows) && method_exists($rows, 'lastPage') && $rows->lastPage() > 1)
                    · Page {{ $rows->currentPage() }} of {{ $rows->lastPage() }}
                @endif
            </div>
        @endif
    </div>

    @if (!empty($isPaginated) && method_exists($rows, 'links'))
        <div style="margin-top:0.75rem;">{{ $rows->links() }}</div>
    @endif
</div>
@endsection
