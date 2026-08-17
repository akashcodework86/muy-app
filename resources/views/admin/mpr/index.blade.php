@extends('layouts.admin')

@section('title', 'Monthly Progress Report')
@section('heading', 'Monthly Progress Report')

@section('page_meta')
    <p class="admin-page-meta">State Admin · direct link · MIS auto-report</p>
@endsection

@section('content')
    <style>
        .mpr-shell{max-width:960px;margin:0 auto}.mpr-hero{position:relative;overflow:hidden;background:linear-gradient(135deg,#9a3412 0%,#ea580c 58%,#f97316 100%);border-radius:20px;padding:2rem;color:#fff;box-shadow:0 18px 45px rgba(154,52,18,.18)}
        .mpr-hero:after{content:"";position:absolute;width:270px;height:270px;border:55px solid rgba(255,255,255,.1);border-radius:999px;right:-90px;top:-115px}.mpr-eyebrow{font-size:.75rem;font-weight:800;letter-spacing:.13em;text-transform:uppercase;opacity:.86}.mpr-title{margin:.45rem 0 .55rem;font-size:2rem;line-height:1.1}.mpr-copy{max-width:650px;margin:0;color:#ffedd5;line-height:1.55}.mpr-card{position:relative;margin-top:1rem;background:#fff;border:1px solid #fed7aa;border-radius:18px;padding:1.35rem;box-shadow:0 10px 30px rgba(15,23,42,.06)}
        .mpr-form{display:grid;grid-template-columns:minmax(240px,1fr) auto;gap:1rem;align-items:end}.mpr-label{display:block;margin-bottom:.42rem;color:#7c2d12;font-size:.76rem;font-weight:800;letter-spacing:.08em;text-transform:uppercase}.mpr-input{width:100%;height:48px;border:1px solid #cbd5e1;border-radius:11px;padding:0 .85rem;font:inherit;color:#0f172a;background:#fff}.mpr-input:focus{outline:none;border-color:#f97316;box-shadow:0 0 0 3px rgba(249,115,22,.14)}.mpr-button{height:48px;border:0;border-radius:11px;padding:0 1.25rem;background:linear-gradient(90deg,#4f46e5,#0d9488);color:#fff;font-weight:800;cursor:pointer;white-space:nowrap}.mpr-button:hover:not(:disabled){filter:brightness(.98)}.mpr-button:disabled{opacity:.72;cursor:wait}
        .mpr-note{display:flex;gap:.7rem;align-items:flex-start;margin-top:1rem;padding:.85rem 1rem;border-radius:12px;background:#fff7ed;color:#7c2d12;font-size:.9rem;line-height:1.45}.mpr-link-box{margin-top:1rem;padding:.85rem 1rem;border-radius:12px;background:#f8fafc;border:1px solid #e2e8f0}.mpr-link-label{display:block;margin-bottom:.35rem;color:#64748b;font-size:.72rem;font-weight:800;letter-spacing:.06em;text-transform:uppercase}.mpr-link-row{display:flex;gap:.5rem;align-items:center;flex-wrap:wrap}.mpr-link-url{flex:1;min-width:200px;padding:.45rem .65rem;border-radius:8px;background:#fff;border:1px solid #cbd5e1;font-size:.82rem;color:#0f172a;word-break:break-all}.mpr-copy-btn{border:1px solid #cbd5e1;border-radius:8px;padding:.45rem .75rem;background:#fff;color:#334155;font:inherit;font-size:.78rem;font-weight:700;cursor:pointer}.mpr-copy-btn:hover{background:#f8fafc}.mpr-error{margin:.55rem 0 0;color:#b91c1c;font-size:.86rem;font-weight:700}@media(max-width:720px){.mpr-form{grid-template-columns:1fr}.mpr-button{width:100%}.mpr-hero{padding:1.4rem}.mpr-title{font-size:1.65rem}}
    </style>

    <div class="mpr-shell">
        <section class="mpr-hero">
            <div class="mpr-eyebrow">MUY · Automated reporting</div>
            <h2 class="mpr-title">Generate a formatted MPR in one step</h2>
            <p class="mpr-copy">Select the reporting month. The system pulls approved MIS achievements, cumulative FY progress, district breakup and eligible field photographs into an editable Word report.</p>
        </section>

        <section class="mpr-card">
            <form method="get" action="{{ route('admin.mpr.download') }}" class="mpr-form" id="mpr-download-form">
                <div>
                    <label for="report_month" class="mpr-label">Reporting month</label>
                    <input id="report_month" class="mpr-input" type="month" name="report_month" value="{{ $defaultMonth }}" max="{{ now()->format('Y-m') }}" required>
                    @error('report_month')<p class="mpr-error">{{ $message }}</p>@enderror
                </div>
                <button class="mpr-button" type="submit" id="mpr-download-btn">Download MPR (Word)</button>
            </form>

            <div class="mpr-note">
                <span aria-hidden="true">ℹ</span>
                <span>The download may take a few seconds while MIS data is compiled. Open the file in Microsoft Word or LibreOffice for final narrative edits before sharing.</span>
            </div>

            <div class="mpr-link-box">
                <span class="mpr-link-label">Bookmark this page</span>
                <div class="mpr-link-row">
                    <code class="mpr-link-url" id="mpr-page-url">{{ $pageUrl }}</code>
                    <button type="button" class="mpr-copy-btn" id="mpr-copy-url">Copy link</button>
                </div>
            </div>
        </section>
    </div>
@endsection

@push('scripts')
<script>
(function () {
    var form = document.getElementById('mpr-download-form');
    var btn = document.getElementById('mpr-download-btn');
    var copyBtn = document.getElementById('mpr-copy-url');
    var urlEl = document.getElementById('mpr-page-url');

    if (form && btn) {
        form.addEventListener('submit', function () {
            btn.disabled = true;
            btn.textContent = 'Generating…';
        });
    }

    if (copyBtn && urlEl) {
        copyBtn.addEventListener('click', function () {
            var url = urlEl.textContent || '';
            if (!url) return;
            if (navigator.clipboard && navigator.clipboard.writeText) {
                navigator.clipboard.writeText(url).then(function () {
                    copyBtn.textContent = 'Copied';
                    setTimeout(function () { copyBtn.textContent = 'Copy link'; }, 1800);
                });
                return;
            }
            window.prompt('Copy this link:', url);
        });
    }
})();
</script>
@endpush
