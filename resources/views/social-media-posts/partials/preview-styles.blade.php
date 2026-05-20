<style>
    .smp-preview-box {
        aspect-ratio: 1 / 1;
        width: 100%;
        max-width: 320px;
        border: 1px solid #e2e8f0;
        border-radius: 12px;
        overflow: hidden;
        background: #f8fafc;
        display: flex;
        flex-direction: column;
    }
    .smp-preview-panel {
        flex: 1;
        min-height: 0;
        display: flex;
        flex-direction: column;
        position: relative;
    }
    .smp-preview-panel__iframe {
        flex: 1;
        width: 100%;
        border: 0;
        min-height: 0;
        background: #000;
    }
    .smp-preview-panel__thumb-link {
        flex: 1;
        min-height: 0;
        display: block;
        overflow: hidden;
        background: #0f172a;
    }
    .smp-preview-panel__thumb {
        width: 100%;
        height: 100%;
        object-fit: cover;
        display: block;
    }
    .smp-preview-panel__meta {
        padding: 0.5rem 0.65rem;
        background: #fff;
        border-top: 1px solid #e2e8f0;
    }
    .smp-preview-panel__platform {
        display: inline-flex;
        font-size: 0.68rem;
        font-weight: 800;
        text-transform: uppercase;
        letter-spacing: 0.06em;
        color: #4f46e5;
        background: #eef2ff;
        border: 1px solid #c7d2fe;
        padding: 0.12rem 0.45rem;
        border-radius: 999px;
    }
    .smp-preview-panel__platform--lg { font-size: 0.78rem; padding: 0.25rem 0.55rem; }
    .smp-preview-panel__title {
        margin: 0.35rem 0 0;
        font-size: 0.75rem;
        font-weight: 700;
        color: #0f172a;
        line-height: 1.35;
        display: -webkit-box;
        -webkit-line-clamp: 2;
        -webkit-box-orient: vertical;
        overflow: hidden;
    }
    .smp-preview-panel__author {
        margin: 0.15rem 0 0;
        font-size: 0.7rem;
        color: #64748b;
    }
    .smp-preview-panel__card {
        flex: 1;
        display: flex;
        flex-direction: column;
        align-items: center;
        justify-content: center;
        padding: 1rem;
        text-align: center;
        gap: 0.65rem;
    }
    .smp-preview-panel__card-url {
        margin: 0;
        font-size: 0.72rem;
        color: #64748b;
        word-break: break-all;
        line-height: 1.4;
    }
    .smp-preview-panel__empty {
        flex: 1;
        display: flex;
        align-items: center;
        justify-content: center;
        padding: 1rem;
        text-align: center;
        font-size: 0.8rem;
        color: #64748b;
    }
    .smp-preview-panel__footer {
        padding: 0.5rem 0.65rem;
        background: #fff;
        border-top: 1px solid #e2e8f0;
    }
    .smp-preview-panel__hint {
        margin: 0 0 0.35rem;
        font-size: 0.68rem;
        color: #94a3b8;
        line-height: 1.35;
    }
    .smp-preview-panel__open {
        font-size: 0.78rem;
        font-weight: 700;
        color: #4f46e5;
        text-decoration: none;
    }
    .smp-preview-panel__open:hover { text-decoration: underline; }
    .smp-preview-panel--compact .smp-preview-panel__footer { display: none; }
    .smp-preview-panel--compact .smp-preview-panel__meta { display: none; }
    .smp-list-thumb {
        width: 56px;
        height: 56px;
        border-radius: 8px;
        border: 1px solid #e2e8f0;
        overflow: hidden;
        background: linear-gradient(135deg, #eef2ff, #f0fdfa);
        display: flex;
        align-items: center;
        justify-content: center;
        font-size: 0.62rem;
        font-weight: 800;
        color: #4f46e5;
        text-align: center;
        line-height: 1.2;
        padding: 0.2rem;
    }
    .smp-list-thumb--image {
        display: block;
        text-decoration: none;
    }
    .smp-list-thumb img {
        width: 100%;
        height: 100%;
        object-fit: cover;
        display: block;
    }
</style>
