@extends('layouts.admin')

@section('title', 'MUY Review PowerPoint')
@section('heading', 'MUY Review PowerPoint')

@section('page_meta')
    <p class="admin-page-meta">State Admin · FY {{ $fiscalYear->code }} · direct download link</p>
@endsection

@section('content')
    <style>
        .review-ppt-shell{max-width:980px;margin:0 auto}.review-ppt-card{background:#fff;border:1px solid #dbe4f0;border-radius:18px;padding:1.5rem;box-shadow:0 12px 35px rgba(15,23,42,.06)}.review-ppt-card h2{margin:0 0 .4rem;color:#16243a}.review-ppt-card p{margin:.3rem 0 1.15rem;color:#53667e;line-height:1.5}.review-ppt-form{display:grid;grid-template-columns:repeat(2,minmax(210px,1fr));gap:1rem;align-items:end}.review-ppt-form label{display:block;margin-bottom:.4rem;font-size:.78rem;font-weight:800;letter-spacing:.04em;text-transform:uppercase;color:#334155}.review-ppt-form input,.review-ppt-form select{width:100%;height:46px;padding:.5rem .75rem;border:1px solid #cbd5e1;border-radius:10px;background:#fff;color:#0f172a;font:inherit}.review-ppt-form button{grid-column:1/-1;height:48px;border:0;border-radius:10px;background:linear-gradient(90deg,#4f46e5,#0d9488);color:#fff;font:inherit;font-weight:800;cursor:pointer}.review-ppt-form button:disabled{opacity:.7;cursor:wait}.review-ppt-form .review-ppt-preview-button{background:#fff;border:1px solid #6366f1;color:#4338ca}.review-ppt-preview,.review-ppt-live{grid-column:1/-1;margin:0!important;padding:.8rem 1rem;background:#eef7ff;border:1px solid #bfdbfe;border-radius:10px;color:#1e3a5f!important;font-size:.88rem}.review-ppt-live[hidden]{display:none}.review-ppt-error{margin:.35rem 0 0!important;color:#b91c1c!important;font-size:.83rem}.review-ppt-note{margin-top:1rem;padding:.8rem 1rem;background:#f8fafc;border:1px solid #e2e8f0;border-radius:10px;color:#475569;line-height:1.5}.review-ppt-url{margin-top:1.1rem;word-break:break-all;font-size:.85rem;color:#475569}.review-ppt-field[hidden]{display:none}@media(max-width:640px){.review-ppt-form{grid-template-columns:1fr}}
    </style>

    <div class="review-ppt-shell">
        <section class="review-ppt-card">
            <h2>Download the district review</h2>
            <p>Choose a quarter, month, or custom date range. Select the district scope and whether figures show only that period or the FY cumulative position.</p>

            <form class="review-ppt-form" method="get" action="{{ route('admin.review-ppt.download') }}" id="review-ppt-form">
                <div>
                    <label for="period_kind">Reporting period</label>
                    <select id="period_kind" name="period_kind">
                        <option value="quarter">Quarter-wise</option>
                        <option value="month">Month-wise</option>
                        <option value="custom">Custom From–To dates</option>
                    </select>
                </div>
                <div class="review-ppt-field" id="review-quarter-field">
                    <label for="quarter">Quarter</label>
                    <select id="quarter" name="quarter">
                        @for ($q = 1; $q <= 4; $q++)
                            <option value="{{ $q }}" @selected($q === $defaultQuarter) @disabled($q > $defaultQuarter)>Q{{ $q }} · {{ ['Apr–Jun', 'Jul–Sep', 'Oct–Dec', 'Jan–Mar'][$q - 1] }}</option>
                        @endfor
                    </select>
                </div>
                <div class="review-ppt-field" id="review-month-field" hidden>
                    <label for="report_month">Month</label>
                    <input type="month" id="report_month" name="report_month" min="{{ array_key_first($months) }}" max="{{ min(array_key_last($months), now()->format('Y-m')) }}" value="{{ $defaultMonth }}" disabled>
                </div>
                <div class="review-ppt-field" id="review-from-field" hidden>
                    <label for="date_from">From date</label>
                    <input type="date" id="date_from" name="date_from" min="{{ $fiscalYear->starts_on->toDateString() }}" max="{{ min(now()->toDateString(), $fiscalYear->starts_on->copy()->startOfMonth()->addMonths(11)->endOfMonth()->toDateString()) }}" value="{{ $defaultFrom }}" disabled>
                </div>
                <div class="review-ppt-field" id="review-to-field" hidden>
                    <label for="date_to">To date</label>
                    <input type="date" id="date_to" name="date_to" min="{{ $fiscalYear->starts_on->toDateString() }}" max="{{ min(now()->toDateString(), $fiscalYear->starts_on->copy()->startOfMonth()->addMonths(11)->endOfMonth()->toDateString()) }}" value="{{ $defaultDate }}" disabled>
                </div>
                <div>
                    <label for="district_scope">District scope</label>
                    <select id="district_scope" name="district_scope">
                        <option value="all">All 13 districts · statewide</option>
                        <option value="kumaon">Kumaon region</option>
                        <option value="garhwal">Garhwal region</option>
                        @foreach (['kumaon' => 'Kumaon districts', 'garhwal' => 'Garhwal districts'] as $region => $heading)
                            <optgroup label="{{ $heading }}">
                                @foreach (config('review_ppt.'.$region, []) as $slug)
                                    <option value="{{ $slug }}">{{ $districtNames[$slug] ?? ucwords(str_replace('-', ' ', $slug)) }}</option>
                                @endforeach
                            </optgroup>
                        @endforeach
                    </select>
                </div>
                <div>
                    <label for="count_mode">Counting mode</label>
                    <select id="count_mode" name="count_mode">
                        <option value="period">Selected period only</option>
                        <option value="cumulative">FY cumulative through period end</option>
                    </select>
                </div>
                <p class="review-ppt-preview" id="review-ppt-preview" role="status"></p>
                @if ($errors->any())
                    <p class="review-ppt-error">{{ $errors->first() }}</p>
                @endif
                <button type="button" class="review-ppt-preview-button" id="review-ppt-preview-button">Preview live totals</button>
                <p class="review-ppt-live" id="review-ppt-live" role="status" hidden></p>
                <button type="submit" id="review-ppt-submit">Download PowerPoint</button>
            </form>

            <div class="review-ppt-note">Targets use the saved monthly allocations for the selected counting window; custom partial-month ranges include that month’s full target. Achievements use actual dates. The All 13 selection keeps the original five-slide design; region or district selections retain only relevant regional slides and editable tables.</div>
            <div class="review-ppt-url">Bookmark this page: <a href="{{ $pageUrl }}">{{ $pageUrl }}</a></div>
        </section>
    </div>

    <script>
        (() => {
            const form = document.getElementById('review-ppt-form');
            const kind = document.getElementById('period_kind');
            const quarter = document.getElementById('quarter');
            const month = document.getElementById('report_month');
            const from = document.getElementById('date_from');
            const to = document.getElementById('date_to');
            const scope = document.getElementById('district_scope');
            const mode = document.getElementById('count_mode');
            const preview = document.getElementById('review-ppt-preview');
            const button = document.getElementById('review-ppt-submit');
            const previewButton = document.getElementById('review-ppt-preview-button');
            const live = document.getElementById('review-ppt-live');
            const fyStart = '{{ $fiscalYear->starts_on->toDateString() }}';
            const today = '{{ min(now()->toDateString(), $fiscalYear->starts_on->copy()->startOfMonth()->addMonths(11)->endOfMonth()->toDateString()) }}';
            const iso = (d) => `${d.getFullYear()}-${String(d.getMonth()+1).padStart(2,'0')}-${String(d.getDate()).padStart(2,'0')}`;
            const clamp = (d) => d > today ? today : d;
            const update = () => {
                live.hidden = true;
                for (const [value, field, input] of [['quarter','review-quarter-field',quarter],['month','review-month-field',month],['custom','review-from-field',from],['custom','review-to-field',to]]) {
                    const show = kind.value === value;
                    document.getElementById(field).hidden = !show;
                    input.disabled = !show;
                }
                let first = from.value, last = to.value;
                if (kind.value === 'quarter') {
                    const start = new Date(2026, 3 + (Number(quarter.value)-1)*3, 1);
                    first = iso(start);
                    last = iso(new Date(start.getFullYear(), start.getMonth()+3, 0));
                } else if (kind.value === 'month' && month.value) {
                    const [year, m] = month.value.split('-').map(Number);
                    first = `${month.value}-01`;
                    last = iso(new Date(year, m, 0));
                }
                if (first < fyStart) first = fyStart;
                last = clamp(last);
                const achievementStart = mode.value === 'cumulative' ? fyStart : first;
                const n = scope.value === 'all' ? 5 : 3;
                preview.textContent = first > last ? 'Choose a period up to today.' :
                    `Achievement: ${achievementStart} to ${last}. Targets: saved monthly allocations for ${mode.value === 'cumulative' ? 'FY start through period end' : 'selected period'}. Scope: ${scope.options[scope.selectedIndex].text}. ${n} editable slides.`;
            };
            [kind,quarter,month,from,to,scope,mode].forEach(el => el.addEventListener('change', update));
            update();
            previewButton.addEventListener('click', async () => {
                previewButton.disabled = true;
                previewButton.textContent = 'Loading live totals…';
                live.hidden = false;
                live.textContent = 'Calculating from MIS…';
                try {
                    const query = new URLSearchParams(new FormData(form));
                    const response = await fetch(`{{ route('admin.review-ppt.preview') }}?${query}`, {headers:{'Accept':'application/json'}});
                    const result = await response.json();
                    if (!response.ok) throw new Error(result.message || Object.values(result.errors || {})[0]?.[0] || 'Could not load MIS totals.');
                    live.textContent = `${result.totals.map(row => `${row.name}: ${row.achievement.toLocaleString()} achieved / ${row.target.toLocaleString()} target`).join(' · ')}. Through ${result.through}; ${result.slides} slides.`;
                } catch (error) {
                    live.textContent = error.message || 'Could not load MIS totals.';
                } finally {
                    previewButton.disabled = false;
                    previewButton.textContent = 'Preview live totals';
                }
            });
            form.addEventListener('submit', () => {
                button.disabled = true;
                button.textContent = 'Preparing PowerPoint…';
                setTimeout(() => { button.disabled = false; button.textContent = 'Download PowerPoint'; }, 20000);
            });
        })();
    </script>
@endsection
