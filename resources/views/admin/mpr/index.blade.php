@extends('layouts.admin')

@section('title', 'Monthly Progress Report')
@section('heading', 'Monthly Progress Report')

@section('page_meta')
    <p class="admin-page-meta">State Admin test utility</p>
@endsection

@section('content')
    <style>
        .mpr-shell{max-width:960px;margin:0 auto}.mpr-hero{position:relative;overflow:hidden;background:linear-gradient(135deg,#9a3412 0%,#ea580c 58%,#f97316 100%);border-radius:20px;padding:2rem;color:#fff;box-shadow:0 18px 45px rgba(154,52,18,.18)}
        .mpr-hero:after{content:"";position:absolute;width:270px;height:270px;border:55px solid rgba(255,255,255,.1);border-radius:999px;right:-90px;top:-115px}.mpr-eyebrow{font-size:.75rem;font-weight:800;letter-spacing:.13em;text-transform:uppercase;opacity:.86}.mpr-title{margin:.45rem 0 .55rem;font-size:2rem;line-height:1.1}.mpr-copy{max-width:650px;margin:0;color:#ffedd5;line-height:1.55}.mpr-card{position:relative;margin-top:1rem;background:#fff;border:1px solid #fed7aa;border-radius:18px;padding:1.35rem;box-shadow:0 10px 30px rgba(15,23,42,.06)}
        .mpr-form{display:grid;grid-template-columns:minmax(240px,1fr) auto;gap:1rem;align-items:end}.mpr-label{display:block;margin-bottom:.42rem;color:#7c2d12;font-size:.76rem;font-weight:800;letter-spacing:.08em;text-transform:uppercase}.mpr-input{width:100%;height:48px;border:1px solid #cbd5e1;border-radius:11px;padding:0 .85rem;font:inherit;color:#0f172a;background:#fff}.mpr-input:focus{outline:none;border-color:#f97316;box-shadow:0 0 0 3px rgba(249,115,22,.14)}.mpr-button{height:48px;border:0;border-radius:11px;padding:0 1.25rem;background:linear-gradient(90deg,#4f46e5,#0d9488);color:#fff;font-weight:800;cursor:pointer;white-space:nowrap}.mpr-button:hover{filter:brightness(.98)}
        .mpr-note{display:flex;gap:.7rem;align-items:flex-start;margin-top:1rem;padding:.85rem 1rem;border-radius:12px;background:#fff7ed;color:#7c2d12;font-size:.9rem;line-height:1.45}.mpr-error{margin:.55rem 0 0;color:#b91c1c;font-size:.86rem;font-weight:700}@media(max-width:720px){.mpr-form{grid-template-columns:1fr}.mpr-button{width:100%}.mpr-hero{padding:1.4rem}.mpr-title{font-size:1.65rem}}
    </style>

    <div class="mpr-shell">
        <section class="mpr-hero">
            <div class="mpr-eyebrow">MUY · Automated reporting</div>
            <h2 class="mpr-title">Generate a formatted MPR in one step</h2>
            <p class="mpr-copy">Select a month. The system will use approved MIS achievements, cumulative FY progress, district breakup and eligible field photographs to prepare an editable Word report.</p>
        </section>

        <section class="mpr-card">
            <form method="get" action="{{ route('admin.mpr.download') }}" class="mpr-form">
                <div>
                    <label for="report_month" class="mpr-label">Reporting month</label>
                    <input id="report_month" class="mpr-input" type="month" name="report_month" value="{{ old('report_month', $defaultMonth) }}" max="{{ now()->format('Y-m') }}" required>
                    @error('report_month')<p class="mpr-error">{{ $message }}</p>@enderror
                </div>
                <button class="mpr-button" type="submit">Download MPR (Word)</button>
            </form>

            <div class="mpr-note">
                <span aria-hidden="true">ℹ</span>
                <span>This is a private State Admin test link and has not been added to the menu. The downloaded DOCX remains editable for final narrative review.</span>
            </div>
        </section>
    </div>
@endsection
