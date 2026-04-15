@if ($paginator->hasPages())
    <nav role="navigation" aria-label="Pagination Navigation" style="display:flex;align-items:center;justify-content:space-between;gap:0.75rem;flex-wrap:wrap;margin-top:1rem;">
        <div style="display:flex;align-items:center;gap:0.45rem;flex-wrap:wrap;">
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

            @foreach ($elements as $element)
                @if (is_string($element))
                    <span style="display:inline-flex;align-items:center;justify-content:center;min-width:2.05rem;height:2.05rem;padding:0 0.5rem;border:1px solid #e2e8f0;border-radius:10px;background:#fff;color:#94a3b8;font-size:0.8rem;font-weight:700;">
                        {{ $element }}
                    </span>
                @endif

                @if (is_array($element))
                    @foreach ($element as $page => $url)
                        @if ($page == $paginator->currentPage())
                            <span aria-current="page" style="display:inline-flex;align-items:center;justify-content:center;min-width:2.05rem;height:2.05rem;padding:0 0.5rem;border:1px solid #4f46e5;border-radius:10px;background:#4f46e5;color:#fff;font-size:0.82rem;font-weight:800;">
                                {{ $page }}
                            </span>
                        @else
                            <a href="{{ $url }}" style="display:inline-flex;align-items:center;justify-content:center;min-width:2.05rem;height:2.05rem;padding:0 0.5rem;border:1px solid #cbd5e1;border-radius:10px;background:#fff;color:#334155;text-decoration:none;font-size:0.82rem;font-weight:700;">
                                {{ $page }}
                            </a>
                        @endif
                    @endforeach
                @endif
            @endforeach

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
        </div>

        <div style="font-size:0.83rem;color:#64748b;font-weight:600;">
            Page {{ $paginator->currentPage() }} of {{ $paginator->lastPage() }}
        </div>
    </nav>
@endif
