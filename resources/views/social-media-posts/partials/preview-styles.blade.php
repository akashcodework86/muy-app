<style>
    .is-hidden { display: none !important; }
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
    .smp-preview-box--embed {
        aspect-ratio: auto;
        min-height: 360px;
        max-height: 560px;
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
    .smp-preview-panel__embed {
        flex: 1;
        min-height: 0;
        overflow: auto;
        padding: 0.35rem;
        background: #fff;
    }
    .smp-preview-panel__embed .instagram-media,
    .smp-preview-panel__embed iframe {
        max-width: 100% !important;
        min-width: 0 !important;
        margin: 0 auto !important;
    }
    .smp-preview-panel__embed--failed {
        display: flex;
        align-items: center;
        justify-content: center;
        padding: 1rem;
        font-size: 0.78rem;
        color: #64748b;
        text-align: center;
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
    .smp-list-thumb--md {
        width: 72px;
        height: 72px;
        font-size: 0.68rem;
    }
    .smp-list-thumb--card {
        width: 100%;
        height: 100%;
        min-height: 220px;
        border-radius: 0;
        border: none;
        font-size: 0.85rem;
    }
    .smp-list-thumb img {
        width: 100%;
        height: 100%;
        object-fit: cover;
        display: block;
    }
    .smp-view-toggle {
        display: inline-flex;
        border: 1px solid #cbd5e1;
        border-radius: 10px;
        overflow: hidden;
        background: #fff;
    }
    .smp-view-toggle__btn {
        padding: 0.45rem 0.85rem;
        font-size: 0.82rem;
        font-weight: 700;
        color: #64748b;
        text-decoration: none;
        border-right: 1px solid #e2e8f0;
    }
    .smp-view-toggle__btn:last-child { border-right: none; }
    .smp-view-toggle__btn.is-active {
        background: #4f46e5;
        color: #fff;
    }
    .smp-posts-grid {
        display: grid;
        grid-template-columns: repeat(4, minmax(0, 1fr));
        gap: 1.25rem;
    }
    @media (max-width: 1280px) {
        .smp-posts-grid { grid-template-columns: repeat(3, minmax(0, 1fr)); }
    }
    @media (max-width: 960px) {
        .smp-posts-grid { grid-template-columns: repeat(2, minmax(0, 1fr)); }
    }
    @media (max-width: 520px) {
        .smp-posts-grid { grid-template-columns: 1fr; }
    }
    .smp-posts-empty {
        padding: 2rem;
        text-align: center;
        color: #64748b;
        background: #fff;
        border: 1px solid #e2e8f0;
        border-radius: 14px;
    }
    .smp-post-card {
        background: #fff;
        border: 1px solid #e2e8f0;
        border-radius: 14px;
        overflow: hidden;
        display: flex;
        flex-direction: column;
        box-shadow: 0 8px 24px -12px rgba(15, 23, 42, 0.18);
        transition: box-shadow 0.15s ease, transform 0.15s ease;
    }
    .smp-post-card:hover {
        box-shadow: 0 14px 32px -10px rgba(79, 70, 229, 0.22);
        transform: translateY(-2px);
    }
    .smp-post-card__media {
        position: relative;
        display: block;
        aspect-ratio: 4 / 5;
        background: #0f172a;
        overflow: hidden;
        text-decoration: none;
    }
    .smp-post-card__index {
        position: absolute;
        top: 0.5rem;
        left: 0.5rem;
        z-index: 2;
        background: rgba(15, 23, 42, 0.72);
        color: #fff;
        font-size: 0.72rem;
        font-weight: 800;
        padding: 0.15rem 0.45rem;
        border-radius: 6px;
    }
    .smp-post-card__body {
        padding: 0.85rem 0.95rem 1rem;
        display: flex;
        flex-direction: column;
        gap: 0.45rem;
        flex: 1;
    }
    .smp-post-card__head {
        display: flex;
        flex-wrap: wrap;
        justify-content: space-between;
        gap: 0.25rem;
        font-size: 0.75rem;
        color: #64748b;
        font-weight: 600;
    }
    .smp-post-card__by {
        color: #4f46e5;
        font-weight: 700;
        max-width: 55%;
        text-align: right;
        overflow: hidden;
        text-overflow: ellipsis;
        white-space: nowrap;
    }
    .smp-post-card__title {
        margin: 0;
        font-size: 0.88rem;
        font-weight: 800;
        color: #0f172a;
        line-height: 1.35;
        display: -webkit-box;
        -webkit-line-clamp: 2;
        -webkit-box-orient: vertical;
        overflow: hidden;
    }
    .smp-post-card__desc {
        margin: 0;
        font-size: 0.78rem;
        color: #64748b;
        line-height: 1.4;
        display: -webkit-box;
        -webkit-line-clamp: 2;
        -webkit-box-orient: vertical;
        overflow: hidden;
    }
    .smp-post-card__platforms { margin-top: 0.15rem; }
    .smp-post-card__actions {
        display: flex;
        flex-wrap: wrap;
        gap: 0.4rem;
        margin-top: auto;
        padding-top: 0.35rem;
    }
    .smp-post-card__btn {
        display: inline-flex;
        align-items: center;
        justify-content: center;
        padding: 0.38rem 0.65rem;
        border-radius: 8px;
        font-size: 0.76rem;
        font-weight: 700;
        text-decoration: none;
        border: 1px solid transparent;
        font-family: inherit;
        cursor: pointer;
        background: #fff;
    }
    .smp-post-card__btn--view {
        background: #eef2ff;
        border-color: #c7d2fe;
        color: #3730a3;
    }
    .smp-post-card__btn--link {
        border-color: #cbd5e1;
        color: #334155;
    }
    .smp-post-card__btn--delete {
        border-color: #fecaca;
        color: #b91c1c;
    }
    .smp-post-card__btn--delete:hover { background: #fef2f2; }
    .smp-posts-foot {
        margin-top: 0.75rem;
        padding: 0.65rem 0.85rem;
        font-size: 0.8rem;
        color: #64748b;
        background: #f8fafc;
        border: 1px solid #e2e8f0;
        border-radius: 10px;
    }
</style>
