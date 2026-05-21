@extends('layouts.admin')

@section('title', 'Social media post')
@section('heading', 'Social media post')

@push('styles')
@include('social-media-posts.partials.preview-styles')
<style>
    .smp-show-shell { display:flex; flex-direction:column; gap:1.25rem; }
    .smp-show-alert { border-radius:12px; padding:0.85rem 1rem; font-size:0.88rem; background:#f0fdf4; border:1px solid #86efac; color:#166534; }
    .smp-show-layout { display:grid; grid-template-columns:minmax(0, 1fr) minmax(220px, 360px); gap:1.25rem; align-items:start; }
    @media (max-width: 768px) { .smp-show-layout { grid-template-columns: 1fr; } }
    .smp-show-card { background:#fff; border:1px solid #e2e8f0; border-radius:14px; padding:1.2rem 1.35rem; }
    .smp-show-grid { display:grid; grid-template-columns:repeat(auto-fit, minmax(200px, 1fr)); gap:0.75rem; }
    .smp-show-label { font-size:0.72rem; font-weight:700; color:#64748b; text-transform:uppercase; letter-spacing:0.05em; }
    .smp-show-value { font-size:0.9rem; font-weight:700; color:#0f172a; margin-top:0.2rem; word-break:break-word; }
    .smp-show-preview-label { font-size:0.72rem; font-weight:700; text-transform:uppercase; letter-spacing:0.06em; color:#64748b; display:block; margin-bottom:0.35rem; }
    .smp-show-preview-box { max-width:360px; }
    .smp-show-actions { margin-top:1rem; display:flex; flex-wrap:wrap; gap:0.65rem; align-items:center; }
    .smp-link { color:#4f46e5; font-weight:700; text-decoration:none; font-size:0.88rem; }
    .smp-btn--delete {
        border:1px solid #fecaca; background:#fff; color:#b91c1c;
        padding:0.55rem 0.95rem; font-size:0.84rem; font-weight:700; border-radius:9px; cursor:pointer;
        font-family:inherit;
    }
    .smp-btn--delete:hover { background:#fef2f2; }
    .smp-delete-inline { display:inline; margin:0; }
    .smp-platforms__chip {
        display:inline-flex; align-items:center;
        padding:0.22rem 0.5rem; border-radius:999px;
        background:#eef2ff; border:1px solid #c7d2fe; color:#3730a3;
        font-size:0.78rem; font-weight:700;
    }
</style>
@endpush

@section('content')
<div class="smp-show-shell">
    @if (session('status'))
        <div class="smp-show-alert">{{ session('status') }}</div>
    @endif

    <div class="smp-show-layout">
        <div class="smp-show-card">
            <div class="smp-show-grid">
                <div>
                    <span class="smp-show-label">Posted on</span>
                    <div class="smp-show-value">{{ $row->posted_on?->format('d M Y') }}</div>
                </div>
                <div>
                    <span class="smp-show-label">Submitted by</span>
                    <div class="smp-show-value">{{ $row->submitted_by_name }}</div>
                </div>
                <div style="grid-column:1 / -1;">
                    <span class="smp-show-label">Post URL</span>
                    <div class="smp-show-value">
                        <a href="{{ $row->post_url }}" target="_blank" rel="noopener noreferrer">{{ $row->post_url }}</a>
                    </div>
                </div>
                <div style="grid-column:1 / -1;">
                    <span class="smp-show-label">Posted on platforms</span>
                    <div class="smp-show-value" style="margin-top:0.35rem;">
                        @include('social-media-posts.partials.platform-badges', ['row' => $row])
                    </div>
                </div>
                <div style="grid-column:1 / -1;">
                    <span class="smp-show-label">Description</span>
                    <div class="smp-show-value">{{ $row->description ?: '—' }}</div>
                </div>
            </div>
            <div class="smp-show-actions">
                <a href="{{ route($dashboardRoute) }}" class="smp-link">← Back to list</a>
                @if (!empty($canDelete))
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
        </div>
        <div>
            <span class="smp-show-preview-label">Preview</span>
            <div class="smp-preview-box smp-show-preview-box @if(($preview['mode'] ?? '') === 'instagram_embed') smp-preview-box--embed @endif">
                @include('social-media-posts.partials.preview-panel', ['preview' => $preview])
            </div>
        </div>
    </div>
</div>
@include('social-media-posts.partials.preview-script')
@endsection
