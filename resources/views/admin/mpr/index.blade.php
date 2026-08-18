@extends('layouts.admin')

@section('title', 'Progress Report Generator')
@section('heading', 'Progress Report Generator')

@section('page_meta')
    <p class="admin-page-meta">State Admin · direct link · MIS auto-report with yellow team placeholders</p>
@endsection

@section('content')
    <style>
        .mpr-shell{max-width:960px;margin:0 auto}.mpr-hero{position:relative;overflow:hidden;background:linear-gradient(135deg,#9a3412 0%,#ea580c 58%,#f97316 100%);border-radius:20px;padding:2rem;color:#fff;box-shadow:0 18px 45px rgba(154,52,18,.18)}
        .mpr-hero:after{content:"";position:absolute;width:270px;height:270px;border:55px solid rgba(255,255,255,.1);border-radius:999px;right:-90px;top:-115px}.mpr-eyebrow{font-size:.75rem;font-weight:800;letter-spacing:.13em;text-transform:uppercase;opacity:.86}.mpr-title{margin:.45rem 0 .55rem;font-size:2rem;line-height:1.1}.mpr-copy{max-width:650px;margin:0;color:#ffedd5;line-height:1.55}.mpr-card{position:relative;margin-top:1rem;background:#fff;border:1px solid #fed7aa;border-radius:18px;padding:1.35rem;box-shadow:0 10px 30px rgba(15,23,42,.06)}
        .mpr-form{display:grid;grid-template-columns:repeat(2,minmax(220px,1fr));gap:1rem;align-items:end}.mpr-label{display:block;margin-bottom:.42rem;color:#7c2d12;font-size:.76rem;font-weight:800;letter-spacing:.08em;text-transform:uppercase}.mpr-input,.mpr-select{width:100%;height:48px;border:1px solid #cbd5e1;border-radius:11px;padding:0 .85rem;font:inherit;color:#0f172a;background:#fff}.mpr-input:focus,.mpr-select:focus{outline:none;border-color:#f97316;box-shadow:0 0 0 3px rgba(249,115,22,.14)}.mpr-button{grid-column:1/-1;height:48px;border:0;border-radius:11px;padding:0 1.25rem;background:linear-gradient(90deg,#4f46e5,#0d9488);color:#fff;font-weight:800;cursor:pointer;white-space:nowrap}.mpr-button:hover:not(:disabled){filter:brightness(.98)}.mpr-button:disabled{opacity:.72;cursor:wait}
        .mpr-type-row{display:flex;gap:.75rem;flex-wrap:wrap}.mpr-type-option{display:flex;align-items:center;gap:.45rem;padding:.55rem .8rem;border:1px solid #cbd5e1;border-radius:10px;background:#fff;cursor:pointer;font-size:.9rem;color:#334155}.mpr-type-option input{margin:0}.mpr-hidden{display:none}
        .mpr-note{display:flex;gap:.7rem;align-items:flex-start;margin-top:1rem;padding:.85rem 1rem;border-radius:12px;background:#fff7ed;color:#7c2d12;font-size:.9rem;line-height:1.45}.mpr-link-box{margin-top:1rem;padding:.85rem 1rem;border-radius:12px;background:#f8fafc;border:1px solid #e2e8f0}.mpr-link-label{display:block;margin-bottom:.35rem;color:#64748b;font-size:.72rem;font-weight:800;letter-spacing:.06em;text-transform:uppercase}.mpr-link-row{display:flex;gap:.5rem;align-items:center;flex-wrap:wrap}.mpr-link-url{flex:1;min-width:200px;padding:.45rem .65rem;border-radius:8px;background:#fff;border:1px solid #cbd5e1;font-size:.82rem;color:#0f172a;word-break:break-all}.mpr-copy-btn{border:1px solid #cbd5e1;border-radius:8px;padding:.45rem .75rem;background:#fff;color:#334155;font:inherit;font-size:.78rem;font-weight:700;cursor:pointer}.mpr-copy-btn:hover{background:#f8fafc}.mpr-error{margin:.55rem 0 0;color:#b91c1c;font-size:.86rem;font-weight:700}@media(max-width:720px){.mpr-form{grid-template-columns:1fr}.mpr-hero{padding:1.4rem}.mpr-title{font-size:1.65rem}}
    </style>

    <div class="mpr-shell">
        <section class="mpr-hero">
            <div class="mpr-eyebrow">MUY · Automated reporting</div>
            <h2 class="mpr-title">Generate MPR or QPR from MIS</h2>
            <p class="mpr-copy">Download a QPR-style Word report with auto-filled MIS tables, district breakups, field photos and team roster. Yellow highlighted [TEAM: …] blocks show where narrative content should be added before sharing.</p>
        </section>

        <section class="mpr-card">
            <form method="get" action="{{ route('admin.mpr.download') }}" class="mpr-form" id="mpr-download-form">
                <div style="grid-column:1/-1">
                    <span class="mpr-label">Report type</span>
                    <div class="mpr-type-row">
                        <label class="mpr-type-option">
                            <input type="radio" name="report_type" value="mpr" @checked($defaultReportType === 'mpr')>
                            Monthly Progress Report (MPR)
                        </label>
                        <label class="mpr-type-option">
                            <input type="radio" name="report_type" value="qpr" @checked($defaultReportType === 'qpr')>
                            Quarterly Progress Report (QPR)
                        </label>
                    </div>
                </div>

                <div id="mpr-month-field">
                    <label for="report_month" class="mpr-label">Reporting month</label>
                    <input id="report_month" class="mpr-input" type="month" name="report_month" value="{{ $defaultMonth }}" max="{{ now()->format('Y-m') }}" required>
                    @error('report_month')<p class="mpr-error">{{ $message }}</p>@enderror
                </div>

                <div id="qpr-quarter-field" class="{{ $defaultReportType === 'qpr' ? '' : 'mpr-hidden' }}">
                    <label for="report_quarter" class="mpr-label">Fiscal quarter</label>
                    <select id="report_quarter" class="mpr-select" name="report_quarter">
                        @foreach($quarters as $quarterNumber => $quarterLabel)
                            <option value="{{ $quarterNumber }}" @selected((int) $defaultQuarter === (int) $quarterNumber)>
                                Q{{ $quarterNumber }} ({{ $quarterLabel }})
                            </option>
                        @endforeach
                    </select>
                    @error('report_quarter')<p class="mpr-error">{{ $message }}</p>@enderror
                </div>

                <div id="qpr-fy-field" class="{{ $defaultReportType === 'qpr' ? '' : 'mpr-hidden' }}">
                    <label for="fiscal_year_id" class="mpr-label">Fiscal year</label>
                    <select id="fiscal_year_id" class="mpr-select" name="fiscal_year_id">
                        @foreach($fiscalYears as $fy)
                            <option value="{{ $fy->id }}" @selected((int) $defaultFiscalYearId === (int) $fy->id)>
                                {{ $fy->name ?? $fy->code }}
                            </option>
                        @endforeach
                    </select>
                </div>

                <button class="mpr-button" type="submit" id="mpr-download-btn">Download Word report</button>
            </form>

            @unless($wordEngineReady)
                <p class="mpr-note" style="margin-top:.85rem;background:#fffbeb;border:1px solid #fde68a;color:#92400e">
                    <span aria-hidden="true">ℹ</span>
                    <span>
                        Native .docx formatting needs PHPWord on the server. Until then, download uses a compatible Word file (.doc).
                        @if($installWordEngineUrl)
                            Optional upgrade:
                            <a href="{{ $installWordEngineUrl }}" target="_blank" rel="noopener" style="color:#92400e;font-weight:800">Run one-time install</a>
                            (new tab), then refresh this page.
                        @endif
                    </span>
                </p>
            @endunless

            <div class="mpr-note">
                <span aria-hidden="true">ℹ</span>
                <span>Auto sections include quantitative tables, mobilization, onboarding, training, workstreams, line department meetings, field photos and team roster. Search for <strong>[TEAM:</strong> in Word to jump to manual narrative blocks.</span>
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
    var monthField = document.getElementById('mpr-month-field');
    var quarterField = document.getElementById('qpr-quarter-field');
    var fyField = document.getElementById('qpr-fy-field');
    var typeInputs = document.querySelectorAll('input[name="report_type"]');

    function syncReportType() {
        var selected = document.querySelector('input[name="report_type"]:checked');
        var isQpr = selected && selected.value === 'qpr';
        if (quarterField) quarterField.classList.toggle('mpr-hidden', !isQpr);
        if (fyField) fyField.classList.toggle('mpr-hidden', !isQpr);
        if (btn) btn.textContent = isQpr ? 'Download QPR (Word)' : 'Download MPR (Word)';
    }

    typeInputs.forEach(function (input) {
        input.addEventListener('change', syncReportType);
    });
    syncReportType();

    if (form && btn) {
        form.addEventListener('submit', function () {
            btn.disabled = true;
            btn.textContent = 'Generating…';
            window.setTimeout(function () {
                btn.disabled = false;
                syncReportType();
            }, 20000);
        });
        window.addEventListener('pageshow', function () {
            btn.disabled = false;
            syncReportType();
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
