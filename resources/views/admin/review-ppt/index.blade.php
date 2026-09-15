@extends('layouts.admin')

@section('title', 'MUY Review PowerPoint')
@section('heading', 'MUY Review PowerPoint')

@section('page_meta')
    <p class="admin-page-meta">State Admin · FY {{ $fiscalYear->code }} · direct download link</p>
@endsection

@section('content')
    <style>
        .review-ppt-shell{max-width:900px;margin:0 auto}.review-ppt-card{background:#fff;border:1px solid #dbe4f0;border-radius:18px;padding:1.5rem;box-shadow:0 12px 35px rgba(15,23,42,.06)}.review-ppt-card h2{margin:0 0 .4rem;color:#16243a}.review-ppt-card p{margin:.3rem 0 1.15rem;color:#53667e;line-height:1.5}.review-ppt-form{display:grid;grid-template-columns:repeat(2,minmax(210px,1fr));gap:1rem;align-items:end}.review-ppt-form label{display:block;margin-bottom:.4rem;font-size:.78rem;font-weight:800;letter-spacing:.04em;text-transform:uppercase;color:#334155}.review-ppt-form input{width:100%;height:46px;padding:.5rem .75rem;border:1px solid #cbd5e1;border-radius:10px;background:#fff;color:#0f172a;font:inherit}.review-ppt-form button{grid-column:1/-1;height:48px;border:0;border-radius:10px;background:linear-gradient(90deg,#4f46e5,#0d9488);color:#fff;font:inherit;font-weight:800;cursor:pointer}.review-ppt-form button:disabled{opacity:.7;cursor:wait}.review-ppt-error{margin:.35rem 0 0!important;color:#b91c1c!important;font-size:.83rem}.review-ppt-note{margin-top:1rem;padding:.8rem 1rem;background:#f8fafc;border:1px solid #e2e8f0;border-radius:10px;color:#475569;line-height:1.5}.review-ppt-url{margin-top:1.1rem;word-break:break-all;font-size:.85rem;color:#475569}@media(max-width:640px){.review-ppt-form{grid-template-columns:1fr}}
    </style>

    <div class="review-ppt-shell">
        <section class="review-ppt-card">
            <h2>Download the five-slide district review</h2>
            <p>Select a reporting month and an achievement cut-off date. The saved monthly targets run through that fiscal quarter; achievements run from FY start through your selected date.</p>

            <form class="review-ppt-form" method="get" action="{{ route('admin.review-ppt.download') }}" id="review-ppt-form">
                <div>
                    <label for="report_month">Reporting month</label>
                    <input type="month" id="report_month" name="report_month" required min="{{ array_key_first($months) }}" max="{{ min(array_key_last($months), now()->format('Y-m')) }}" value="{{ old('report_month', $defaultMonth) }}">
                    @error('report_month')<p class="review-ppt-error">{{ $message }}</p>@enderror
                </div>
                <div>
                    <label for="as_of">Achievement through</label>
                    <input type="date" id="as_of" name="as_of" required max="{{ now()->toDateString() }}" value="{{ old('as_of', $defaultDate) }}">
                    @error('as_of')<p class="review-ppt-error">{{ $message }}</p>@enderror
                </div>
                <button type="submit" id="review-ppt-submit">Download PowerPoint</button>
            </form>

            <div class="review-ppt-note">The original review slide design and editable tables are reused. Statewide application and onboarding totals are summed from the same 13 district rows shown in the regional slides.</div>
            <div class="review-ppt-url">Bookmark this page: <a href="{{ $pageUrl }}">{{ $pageUrl }}</a></div>
        </section>
    </div>

    <script>
        (() => {
            const month = document.getElementById('report_month');
            const date = document.getElementById('as_of');
            const form = document.getElementById('review-ppt-form');
            const button = document.getElementById('review-ppt-submit');
            const today = '{{ now()->toDateString() }}';
            const sync = (reset) => {
                if (!month.value) return;
                const first = `${month.value}-01`;
                const [year, number] = month.value.split('-').map(Number);
                const last = `${month.value}-${String(new Date(year, number, 0).getDate()).padStart(2, '0')}`;
                date.min = first;
                date.max = last < today ? last : today;
                if (reset || !date.value || date.value < first || date.value > date.max) date.value = date.max;
            };
            month.addEventListener('change', () => sync(true));
            sync(false);
            form.addEventListener('submit', () => {
                button.disabled = true;
                button.textContent = 'Preparing PowerPoint…';
                setTimeout(() => { button.disabled = false; button.textContent = 'Download PowerPoint'; }, 15000);
            });
        })();
    </script>
@endsection
