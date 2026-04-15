@if ($paginator->hasPages())
    <nav role="navigation" aria-label="Pagination Navigation" style="display:flex;align-items:center;justify-content:space-between;gap:0.75rem;flex-wrap:wrap;margin-top:1rem;">
        @if ($paginator->onFirstPage())
            <span style="display:inline-flex;align-items:center;gap:0.42rem;padding:0.45rem 0.78rem;border:1px solid #cbd5e1;border-radius:10px;background:#f8fafc;color:#94a3b8;font-weight:700;font-size:0.83rem;cursor:not-allowed;">
                <span aria-hidden="true" style="font-size:0.92rem;line-height:1;">&larr;</span>
                Previous
            </span>
        @else
            <a href="{{ $paginator->previousPageUrl() }}" rel="prev" style="display:inline-flex;align-items:center;gap:0.42rem;padding:0.45rem 0.78rem;border:1px solid #cbd5e1;border-radius:10px;background:#fff;color:#334155;text-decoration:none;font-weight:700;font-size:0.83rem;box-shadow:0 1px 0 rgba(15,23,42,0.03);">
                <span aria-hidden="true" style="font-size:0.92rem;line-height:1;">&larr;</span>
                Previous
            </a>
        @endif

        <div style="font-size:0.83rem;color:#64748b;font-weight:600;">
            Page {{ $paginator->currentPage() }}
        </div>

        @if ($paginator->hasMorePages())
            <a href="{{ $paginator->nextPageUrl() }}" rel="next" style="display:inline-flex;align-items:center;gap:0.42rem;padding:0.45rem 0.78rem;border:1px solid #cbd5e1;border-radius:10px;background:#fff;color:#334155;text-decoration:none;font-weight:700;font-size:0.83rem;box-shadow:0 1px 0 rgba(15,23,42,0.03);">
                Next
                <span aria-hidden="true" style="font-size:0.92rem;line-height:1;">&rarr;</span>
            </a>
        @else
            <span style="display:inline-flex;align-items:center;gap:0.42rem;padding:0.45rem 0.78rem;border:1px solid #cbd5e1;border-radius:10px;background:#f8fafc;color:#94a3b8;font-weight:700;font-size:0.83rem;cursor:not-allowed;">
                Next
                <span aria-hidden="true" style="font-size:0.92rem;line-height:1;">&rarr;</span>
            </span>
        @endif
    </nav>
@endif
