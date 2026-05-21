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
    .smp-head { display:flex; flex-wrap:wrap; justify-content:space-between; align-items:flex-start; gap:0.75rem; margin-bottom:1rem; }
    .smp-head__title { margin:0; font-size:1rem; font-weight:700; color:#0f172a; }
    .smp-head__meta { margin:0.2rem 0 0; font-size:0.82rem; color:#64748b; font-weight:600; }
    .smp-head__count { display:inline-flex; align-items:center; padding:0.2rem 0.55rem; border-radius:999px; background:#eef2ff; border:1px solid #c7d2fe; color:#3730a3; font-size:0.78rem; font-weight:800; margin-left:0.35rem; }
    .smp-head__actions { display:flex; flex-wrap:wrap; gap:0.5rem; align-items:center; }
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
</style>
@endpush

@section('content')
@php
    $viewMode = $viewMode ?? 'posts';
    $filterQuery = array_filter([
        'q' => $filters['q'] ?? '',
        'from' => $filters['from'] ?? '',
        'to' => $filters['to'] ?? '',
        'view' => $viewMode,
    ], fn ($v) => $v !== null && $v !== '');
    $totalCount = (!empty($isPaginated) && is_object($rows) && method_exists($rows, 'total'))
        ? (int) $rows->total()
        : (int) (is_countable($rows) ? count($rows) : 0);
@endphp
<div class="smp-shell">
    @if (!empty($migrationMissing))
        <div class="smp-alert smp-alert--warning">
            <strong>Database update required.</strong> Run <code>php artisan migrate</code> for the social media posts tables
            (<code>posted_platforms</code>, preview fields).
        </div>
    @endif

    @if (session('status'))
        <div class="smp-alert smp-alert--success">{{ session('status') }}</div>
    @endif

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
            <div class="smp-head__actions">
                <div class="smp-view-toggle" role="group" aria-label="View mode">
                    <a href="{{ route($dashboardRoute, array_merge($filterQuery, ['view' => 'posts'])) }}" class="smp-view-toggle__btn @if ($viewMode === 'posts') is-active @endif">Posts</a>
                    <a href="{{ route($dashboardRoute, array_merge($filterQuery, ['view' => 'list'])) }}" class="smp-view-toggle__btn @if ($viewMode === 'list') is-active @endif">List</a>
                </div>
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
            <input type="hidden" name="view" value="{{ $viewMode }}">
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
                <a href="{{ route($dashboardRoute, ['view' => $viewMode]) }}" class="smp-btn smp-btn--secondary">Clear</a>
            </div>
        </form>
        @endif
    </div>

    @if ($viewMode === 'posts')
        @include('social-media-posts.partials.posts-grid')
        @if ($totalCount > 0)
            <div class="smp-posts-foot">
                Total entries: <strong>{{ number_format($totalCount) }}</strong>
                @if (!empty($isPaginated) && is_object($rows) && method_exists($rows, 'lastPage') && $rows->lastPage() > 1)
                    · Page {{ $rows->currentPage() }} of {{ $rows->lastPage() }}
                @endif
            </div>
        @endif
    @else
        @include('social-media-posts.partials.dashboard-list')
    @endif

    @if (!empty($isPaginated) && method_exists($rows, 'links'))
        <div style="margin-top:0.75rem;">{{ $rows->links() }}</div>
    @endif
</div>
@endsection
